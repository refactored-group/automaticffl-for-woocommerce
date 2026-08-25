<?php
/**
 * WooCommerce placed-order attribution reporter.
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Api;

use RefactoredGroup\AutomaticFFL\Helper\Config;
use RefactoredGroup\AutomaticFFL\Helper\Credentials;

defined( 'ABSPATH' ) || exit;

/**
 * Reports successfully placed FFL orders without blocking checkout.
 *
 * @since 1.0.27
 */
class Order_Attribution {

	const ACTION_HOOK      = 'automaticffl_report_order_attribution';
	const ACTION_GROUP     = 'automaticffl';
	const MAX_ATTEMPTS     = 5;
	const BASE_RETRY_DELAY = 60;
	const MAX_RETRY_DELAY  = 3600;
	const LOGGER_SOURCE    = 'automaticffl-order-attribution';

	/** Register placement and worker hooks. */
	public function __construct() {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'enqueue' ), 40, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'enqueue' ), 40, 1 );
		add_action( self::ACTION_HOOK, array( $this, 'report' ), 10, 2 );
	}

	/**
	 * Enqueue the reporting job and return immediately to checkout.
	 *
	 * @param int|\WC_Order $order_or_id Order ID or object.
	 * @return void
	 */
	public function enqueue( $order_or_id ) {
		$order_id = $order_or_id instanceof \WC_Order ? $order_or_id->get_id() : absint( $order_or_id );
		if ( ! $order_id ) {
			return;
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$this->log( 'error', 'Action Scheduler is unavailable; order attribution was not queued.', array( 'order_id' => $order_id ) );
			return;
		}

		$action_id = as_enqueue_async_action(
			self::ACTION_HOOK,
			array( $order_id, 1 ),
			self::ACTION_GROUP,
			true
		);

		if ( ! $action_id ) {
			$this->log( 'error', 'Action Scheduler could not queue order attribution.', array( 'order_id' => $order_id ) );
		}
	}

	/**
	 * Reload an order and report its immutable placement evidence.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @param int $attempt  One-based delivery attempt.
	 * @return void
	 */
	public function report( $order_id, $attempt = 1 ) {
		$order_id = absint( $order_id );
		$attempt  = max( 1, absint( $attempt ) );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			$this->log_terminal( 'Order attribution stopped because the order no longer exists.', $order_id, $attempt );
			return;
		}

		$dealer_id   = absint( $order->get_meta( '_ffl_dealer_id' ) );
		$ffl_license = trim( (string) $order->get_meta( '_ffl_license_field' ) );

		// Ordinary non-FFL orders have neither value and need no attribution.
		if ( ! $dealer_id && '' === $ffl_license ) {
			return;
		}

		if ( ! $dealer_id || '' === $ffl_license ) {
			$this->log_terminal( 'Order attribution stopped because required FFL metadata is incomplete.', $order_id, $attempt );
			return;
		}

		$created_at = $order->get_date_created();
		if ( ! $created_at ) {
			$this->log_terminal( 'Order attribution stopped because the order creation time is missing.', $order_id, $attempt );
			return;
		}

		$store_hash = trim( (string) Config::get_store_hash() );
		if ( '' === $store_hash ) {
			$this->log_terminal( 'Order attribution stopped because the Automatic FFL store hash is missing.', $order_id, $attempt );
			return;
		}

		$credentials = Credentials::get_or_create_app_password();
		if ( is_wp_error( $credentials ) ) {
			$this->log(
				'error',
				'Order attribution stopped because integration credentials are unavailable.',
				array(
					'order_id'   => $order_id,
					'attempt'    => $attempt,
					'error_code' => $credentials->get_error_code(),
				)
			);
			return;
		}

		$authorization = base64_encode( $credentials['username'] . ':' . $credentials['password'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication.
		$response      = wp_remote_post(
			Config::get_order_attributions_url(),
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . $authorization,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'order_id'    => $order_id,
						'dealer_id'   => $dealer_id,
						'ffl_license' => $ffl_license,
						'ordered_at'  => gmdate( 'Y-m-d\TH:i:s\Z', $created_at->getTimestamp() ),
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->schedule_retry(
				$order_id,
				$attempt,
				'network_error',
				array( 'error_code' => $response->get_error_code() )
			);
			return;
		}

		$status = absint( wp_remote_retrieve_response_code( $response ) );
		if ( in_array( $status, array( 200, 201 ), true ) ) {
			return;
		}

		if ( 429 === $status || $status >= 500 ) {
			$this->schedule_retry( $order_id, $attempt, 'http_error', array( 'status' => $status ) );
			return;
		}

		$this->log_terminal(
			'Order attribution stopped after a non-retryable backend response.',
			$order_id,
			$attempt,
			array( 'status' => $status )
		);
	}

	/**
	 * Schedule a bounded exponential-backoff retry.
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $attempt  Current attempt.
	 * @param string $reason   Retry reason.
	 * @param array  $context  Safe diagnostic context.
	 * @return void
	 */
	private function schedule_retry( $order_id, $attempt, $reason, array $context = array() ) {
		if ( $attempt >= self::MAX_ATTEMPTS ) {
			$this->log(
				'error',
				'Order attribution exhausted its retry limit.',
				array_merge(
					$context,
					array(
						'order_id' => $order_id,
						'attempt'  => $attempt,
						'reason'   => $reason,
					)
				)
			);
			return;
		}

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$this->log(
				'error',
				'Order attribution could not schedule a retry because Action Scheduler is unavailable.',
				array_merge( $context, array( 'order_id' => $order_id, 'attempt' => $attempt, 'reason' => $reason ) )
			);
			return;
		}

		$delay = min( self::MAX_RETRY_DELAY, self::BASE_RETRY_DELAY * pow( 2, $attempt - 1 ) );
		$action_id = as_schedule_single_action(
			time() + (int) $delay,
			self::ACTION_HOOK,
			array( $order_id, $attempt + 1 ),
			self::ACTION_GROUP,
			true
		);

		if ( ! $action_id ) {
			$this->log(
				'error',
				'Action Scheduler could not queue an order attribution retry.',
				array_merge( $context, array( 'order_id' => $order_id, 'attempt' => $attempt, 'reason' => $reason ) )
			);
		}
	}

	/** Log a terminal failure with common context. */
	private function log_terminal( $message, $order_id, $attempt, array $context = array() ) {
		$this->log(
			'error',
			$message,
			array_merge( $context, array( 'order_id' => $order_id, 'attempt' => $attempt ) )
		);
	}

	/** Write minimal diagnostics without buyer data. */
	private function log( $level, $message, array $context = array() ) {
		$context = array_merge( array( 'source' => self::LOGGER_SOURCE ), $context );
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, $context );
			return;
		}

		error_log( 'AutomaticFFL: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Last-resort diagnostics when the WooCommerce logger is unavailable.
	}
}
