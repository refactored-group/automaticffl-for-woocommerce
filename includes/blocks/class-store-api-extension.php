<?php
/**
 * Store API Extension for FFL
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Blocks;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use RefactoredGroup\AutomaticFFL\Helper\Config;
use RefactoredGroup\AutomaticFFL\Helper\Cart_Analyzer;

/**
 * Class Store_Api_Extension
 *
 * Extends the Store API to handle FFL license data during checkout.
 *
 * @since 1.0.14
 */
class Store_Api_Extension {

	/**
	 * Extension namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'automaticffl';

	/**
	 * Schema instance.
	 *
	 * @var ExtendSchema
	 */
	private $extend_schema;

	/**
	 * Initialize the extension.
	 *
	 * @return void
	 */
	public function init() {
		$this->extend_schema = StoreApi::container()->get( ExtendSchema::class );
		$this->register_endpoint_data();
		$this->register_hooks();
	}

	/**
	 * Register endpoint data with the Store API.
	 *
	 * @return void
	 */
	private function register_endpoint_data() {
		$this->extend_schema->register_endpoint_data(
			array(
				'endpoint'        => CheckoutSchema::IDENTIFIER,
				'namespace'       => self::NAMESPACE,
				'data_callback'   => array( $this, 'get_schema_data' ),
				'schema_callback' => array( $this, 'get_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			array( $this, 'update_order_from_request' ),
			10,
			2
		);

		add_action(
			'woocommerce_store_api_checkout_order_processed',
			array( $this, 'validate_order' ),
			10,
			1
		);
	}

	/**
	 * Get schema data for the extension.
	 *
	 * @return array
	 */
	public function get_schema_data() {
		return array(
			'fflLicense' => '',
		);
	}

	/**
	 * Get schema for the extension.
	 *
	 * @return array
	 */
	public function get_schema() {
		return array(
			'fflLicense' => array(
				'description' => __( 'FFL License number for the selected dealer.', 'automaticffl-for-wc' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
		);
	}

	/**
	 * Update order with FFL license data from the checkout request.
	 *
	 * @param \WC_Order         $order   The order object.
	 * @param \WP_REST_Request  $request The request object.
	 *
	 * @return void
	 */
	public function update_order_from_request( $order, $request ) {
		$extensions = $request->get_param( 'extensions' );

		if ( empty( $extensions ) || ! isset( $extensions[ self::NAMESPACE ] ) ) {
			return;
		}

		$ffl_data = $extensions[ self::NAMESPACE ];

		if ( ! empty( $ffl_data['fflLicense'] ) ) {
			$ffl_license = sanitize_text_field( $ffl_data['fflLicense'] );

			// Save to order meta
			$order->update_meta_data( '_ffl_license_field', $ffl_license );
			$order->save();

			// Add order note
			$order->add_order_note(
				sprintf(
					/* translators: %s: FFL License number */
					__( 'FFL License: %s', 'automaticffl-for-wc' ),
					$ffl_license
				)
			);
		}
	}

	/**
	 * Validate the order has FFL license if required.
	 *
	 * @param \WC_Order $order The order object.
	 *
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException If validation fails.
	 *
	 * @return void
	 */
	public function validate_order( $order ) {
		$analyzer = new Cart_Analyzer();

		// If API is unavailable, skip validation and allow normal checkout.
		// Customer was shown notice to contact store after placing order.
		if ( $analyzer->has_api_error() ) {
			return;
		}

		// Firearms always need FFL
		if ( $analyzer->has_firearms() ) {
			$ffl_license = $order->get_meta( '_ffl_license_field' );

			if ( empty( $ffl_license ) ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
					'ffl_dealer_required',
					__( 'Please select an FFL dealer for your firearm order.', 'automaticffl-for-wc' ),
					400
				);
			}
			return;
		}

		// Ammo only - check state restriction
		if ( $analyzer->is_ammo_only() && Config::is_ammo_enabled() ) {
			$shipping_state = $order->get_shipping_state();

			if ( $analyzer->requires_ffl_for_state( $shipping_state ) ) {
				$ffl_license = $order->get_meta( '_ffl_license_field' );

				if ( empty( $ffl_license ) ) {
					throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
						'ffl_dealer_required_ammo',
						__( 'Please select an FFL dealer for ammunition shipping to this state.', 'automaticffl-for-wc' ),
						400
					);
				}
			}
		}
	}
}
