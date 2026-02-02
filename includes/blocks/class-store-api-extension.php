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
use RefactoredGroup\AutomaticFFL\Helper\Saved_Cart;
use RefactoredGroup\AutomaticFFL\Helper\US_States;

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

		// Register REST API route for save for later.
		add_action( 'rest_api_init', array( $this, 'register_save_for_later_route' ) );
	}

	/**
	 * Register the save for later REST API route.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public function register_save_for_later_route() {
		register_rest_route(
			'automaticffl/v1',
			'/save-for-later',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_save_for_later' ),
				'permission_callback' => function( $request ) {
					$nonce = $request->get_header( 'X-WP-Nonce' );
					return $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
				},
				'args'                => array(
					'item_type' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'ffl', 'regular' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Handle the save for later REST API request.
	 *
	 * @since 1.0.14
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object.
	 */
	public function handle_save_for_later( $request ) {
		// Ensure WooCommerce session is available for REST API context.
		if ( null === WC()->session ) {
			$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
			WC()->session  = new $session_class();
			WC()->session->init();
		}

		// Ensure cart is available.
		if ( null === WC()->cart ) {
			WC()->cart = new \WC_Cart();
		}

		// Ensure customer is available (needed by cart).
		if ( null === WC()->customer ) {
			WC()->customer = new \WC_Customer( get_current_user_id(), true );
		}

		$item_type = $request->get_param( 'item_type' );

		// Validate item type.
		if ( ! in_array( $item_type, array( 'ffl', 'regular' ), true ) ) {
			return new \WP_Error(
				'invalid_item_type',
				__( 'Invalid item type.', 'automaticffl-for-wc' ),
				array( 'status' => 400 )
			);
		}

		// Save items.
		$result = Saved_Cart::save_items( $item_type );

		if ( $result['success'] ) {
			return rest_ensure_response(
				array(
					'success'     => true,
					'saved_count' => $result['saved_count'],
					'message'     => $result['message'],
				)
			);
		}

		return new \WP_Error(
			'save_failed',
			$result['message'],
			array( 'status' => 400 )
		);
	}

	/**
	 * Get schema data for the extension.
	 *
	 * @return array
	 */
	public function get_schema_data() {
		return array(
			'fflLicense'        => '',
			'fflExpirationDate' => '',
			'fflUuid'           => '',
		);
	}

	/**
	 * Get schema for the extension.
	 *
	 * @return array
	 */
	public function get_schema() {
		return array(
			'fflLicense'        => array(
				'description' => __( 'FFL License number for the selected dealer.', 'automaticffl-for-wc' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
			'fflExpirationDate' => array(
				'description' => __( 'FFL License expiration date.', 'automaticffl-for-wc' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
			'fflUuid'           => array(
				'description' => __( 'FFL dealer UUID for certificate lookup.', 'automaticffl-for-wc' ),
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

			// Save FFL license to order meta.
			$order->update_meta_data( '_ffl_license_field', $ffl_license );

			// Save expiration date if provided.
			if ( ! empty( $ffl_data['fflExpirationDate'] ) ) {
				$expiration_date = sanitize_text_field( $ffl_data['fflExpirationDate'] );
				$order->update_meta_data( '_ffl_expiration_date', $expiration_date );
			}

			// Save UUID if provided.
			if ( ! empty( $ffl_data['fflUuid'] ) ) {
				$uuid = sanitize_text_field( $ffl_data['fflUuid'] );
				$order->update_meta_data( '_ffl_uuid', $uuid );
			}

			$order->save();

			// Build enhanced order note.
			$note = Config::build_enhanced_order_note(
				$ffl_license,
				! empty( $ffl_data['fflExpirationDate'] ) ? sanitize_text_field( $ffl_data['fflExpirationDate'] ) : '',
				! empty( $ffl_data['fflUuid'] ) ? sanitize_text_field( $ffl_data['fflUuid'] ) : ''
			);
			$order->add_order_note( $note );
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

		// Ammo + regular mixed cart in restricted state - block checkout.
		if ( $analyzer->is_ammo_regular_mixed() ) {
			$shipping_state = $order->get_shipping_state();
			if ( empty( $shipping_state ) ) {
				$shipping_state = $order->get_billing_state();
			}

			if ( $analyzer->requires_ffl_for_state( $shipping_state ) ) {
				$state_name = US_States::get_name( $shipping_state );

				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
					'ammo_regular_mixed_restricted',
					sprintf(
						/* translators: %s: state name */
						__( 'Ammunition and regular products in your cart require separate orders for shipping to %s. Modify your cart.', 'automaticffl-for-wc' ),
						$state_name
					),
					400
				);
			}
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
		if ( $analyzer->is_ammo_only() ) {
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
