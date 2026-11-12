<?php
/**
 * Store API Extension for FFL
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Blocks;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use RefactoredGroup\AutomaticFFL\Helper\Config;
use RefactoredGroup\AutomaticFFL\Helper\Cart_Analyzer;
use RefactoredGroup\AutomaticFFL\Helper\Saved_Cart;
use RefactoredGroup\AutomaticFFL\Helper\US_States;
use RefactoredGroup\AutomaticFFL\Helper\Ammo_Destination_Edit;

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

		$this->extend_schema->register_endpoint_data(
			array(
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => self::NAMESPACE,
				'data_callback'   => array( $this, 'get_cart_schema_data' ),
				'schema_callback' => array( $this, 'get_cart_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		$this->extend_schema->register_update_callback(
			array(
				'namespace' => 'automaticffl-cart-notice',
				'callback'  => array( $this, 'update_cart_state' ),
			)
		);
	}

	/**
	 * Current cart facts, refreshed with each Store API cart response.
	 *
	 * @return array
	 */
	public function get_cart_schema_data() {
		$analyzer = new Cart_Analyzer();
		$state    = WC()->session ? WC()->session->get( 'automaticffl_ammo_state', '' ) : '';
		$states   = US_States::get_all();

		return array(
			'isApiAvailable'         => $analyzer->is_api_available(),
			'isFirearmsRegularMixed' => $analyzer->is_firearms_regular_mixed(),
			'isAmmoRegularMixed'     => $analyzer->is_ammo_regular_mixed(),
			'fflItemCount'           => $analyzer->get_quantity_by_category( 'firearms' ) + $analyzer->get_quantity_by_category( 'ammo' ),
			'regularItemCount'       => $analyzer->get_quantity_by_category( 'regular' ),
			'ammoRestrictedStates'   => array_values( array_unique( $analyzer->get_ammo_restricted_states() ) ),
			'selectedAmmoState'      => is_string( $state ) && isset( $states[ $state ] ) ? $state : '',
		);
	}

	/**
	 * Read-only cart extension schema; checkout dealer data stays separate.
	 *
	 * @return array
	 */
	public function get_cart_schema() {
		$fields = array(
			'isApiAvailable'         => array( 'type' => 'boolean' ),
			'isFirearmsRegularMixed' => array( 'type' => 'boolean' ),
			'isAmmoRegularMixed'     => array( 'type' => 'boolean' ),
			'fflItemCount'           => array( 'type' => 'integer', 'minimum' => 0 ),
			'regularItemCount'       => array( 'type' => 'integer', 'minimum' => 0 ),
			'ammoRestrictedStates'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'selectedAmmoState'      => array( 'type' => 'string' ),
		);

		foreach ( $fields as &$field ) {
			$field['context']  = array( 'view' );
			$field['readonly'] = true;
		}
		unset( $field );

		return $fields;
	}

	/**
	 * Persist a confirmed ammunition state through the nonce-protected cart API.
	 *
	 * @param array $data Extension update data.
	 * @return void
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException Invalid state or session.
	 */
	public function update_cart_state( $data ) {
		$states = US_States::get_all();
		if ( ! is_array( $data ) || ! isset( $data['state'] ) || ! is_string( $data['state'] ) || ( '' !== $data['state'] && ! isset( $states[ $data['state'] ] ) ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'automaticffl_invalid_state',
				__( 'Please select a valid shipping state.', 'automaticffl-for-wc' ),
				400
			);
		}

		if ( ! WC()->session ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'automaticffl_session_unavailable',
				__( 'Your cart session is unavailable. Please reload the page and try again.', 'automaticffl-for-wc' ),
				400
			);
		}

		WC()->session->set( 'automaticffl_ammo_state', $data['state'] );
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
			'fflDealerId'       => '',
			'fflLicense'        => '',
			'fflExpirationDate' => '',
			'fflUuid'           => '',
			'fflCompanyName'    => '',
			'fflDealerAddress1' => '',
			'fflDealerAddress2' => '',
			'fflDealerCity'     => '',
			'fflDealerState'    => '',
			'fflDealerPostcode' => '',
			'fflDealerCountry'  => '',
			'fflDealerPhone'    => '',
		);
	}

	/**
	 * Get schema for the extension.
	 *
	 * @return array
	 */
	public function get_schema() {
		return array(
			'fflDealerId'       => array(
				'description' => __( 'Canonical Automatic FFL dealer ID.', 'automaticffl-for-wc' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
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
			'fflCompanyName'    => array(
				'description' => __( 'FFL dealer company name.', 'automaticffl-for-wc' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
			'fflDealerAddress1' => array(
				'description' => __( 'FFL dealer address line 1.', 'automaticffl-for-wc' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
			'fflDealerAddress2' => array(
				'description' => __( 'FFL dealer address line 2.', 'automaticffl-for-wc' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
			'fflDealerCity'     => array(
				'description' => __( 'FFL dealer city.', 'automaticffl-for-wc' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
			'fflDealerState'    => array(
				'description' => __( 'FFL dealer state.', 'automaticffl-for-wc' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
			'fflDealerPostcode' => array(
				'description' => __( 'FFL dealer postal code.', 'automaticffl-for-wc' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
			'fflDealerCountry'  => array(
				'description' => __( 'FFL dealer country.', 'automaticffl-for-wc' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
				'optional'    => true,
			),
			'fflDealerPhone'    => array(
				'description' => __( 'FFL dealer phone.', 'automaticffl-for-wc' ),
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
		$has_ffl_data = is_array( $extensions ) && array_key_exists( self::NAMESPACE, $extensions );
		$ffl_data     = $has_ffl_data && is_array( $extensions[ self::NAMESPACE ] )
			? $extensions[ self::NAMESPACE ]
			: array();
		$is_final_post = is_callable( array( $request, 'get_method' ) ) && 'POST' === strtoupper( (string) $request->get_method() );

		if ( $is_final_post ) {
			$this->validate_final_request( $order, $ffl_data, $has_ffl_data );
		}

		if ( ! $has_ffl_data ) {
			return;
		}

		if ( Ammo_Destination_Edit::is_eligible() && array_key_exists( 'fflLicense', $ffl_data ) && empty( $ffl_data['fflLicense'] ) ) {
			foreach ( array( '_ffl_dealer_id', '_ffl_license_field', '_ffl_expiration_date', '_ffl_uuid' ) as $meta_key ) {
				$order->delete_meta_data( $meta_key );
			}
		}

		$ffl_license = $this->get_extension_value( $ffl_data, 'fflLicense' );
		if ( '' !== $ffl_license ) {
			$expiration_date            = $this->get_extension_value( $ffl_data, 'fflExpirationDate' );
			$uuid                       = $this->get_extension_value( $ffl_data, 'fflUuid' );
			$complete_selection         = $this->has_complete_current_selection( $order, $ffl_data );
			$previous_complete_selection = $this->order_has_complete_selection( $order );
			$note_changed                = ! $previous_complete_selection
				|| $ffl_license !== (string) $order->get_meta( '_ffl_license_field' )
				|| $expiration_date !== (string) $order->get_meta( '_ffl_expiration_date' )
				|| $uuid !== (string) $order->get_meta( '_ffl_uuid' );

			// Save the canonical dealer ID used for order attribution.
			$dealer_id = absint( $this->get_extension_value( $ffl_data, 'fflDealerId' ) );
			if ( $dealer_id ) {
				$order->update_meta_data( '_ffl_dealer_id', $dealer_id );
			}

			// Save FFL license to order meta.
			$order->update_meta_data( '_ffl_license_field', $ffl_license );

			// A complete dealer selection replaces optional license details too.
			foreach (
				array(
					'fflExpirationDate' => array( '_ffl_expiration_date', $expiration_date ),
					'fflUuid'           => array( '_ffl_uuid', $uuid ),
				) as $field => $optional
			) {
				list( $meta_key, $value ) = $optional;
				if ( '' !== $value ) {
					$order->update_meta_data( $meta_key, $value );
				} elseif ( $complete_selection || array_key_exists( $field, $ffl_data ) ) {
					$order->delete_meta_data( $meta_key );
				}
			}

			$this->set_order_shipping_from_ffl_data( $order, $ffl_data );

			$order->save();

			if ( $complete_selection && $note_changed ) {
				$note = Config::build_enhanced_order_note( $ffl_license, $expiration_date, $uuid );
				$order->add_order_note( $note );
			}
		}
	}

	/**
	 * Validate current Store API data before a final checkout POST mutates the order.
	 *
	 * @since 1.0.35
	 *
	 * @param \WC_Order $order        Draft order.
	 * @param array     $ffl_data     Current request extension data.
	 * @param bool      $has_ffl_data Whether the current request included the extension.
	 * @return void
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException Invalid cart or dealer data.
	 */
	private function validate_final_request( $order, array $ffl_data, $has_ffl_data ) {
		$analyzer = new Cart_Analyzer();
		if ( $analyzer->has_api_error() ) {
			return;
		}

		$this->validate_mixed_cart( $order, $analyzer );

		$current_selection_started = false;
		foreach ( array( 'fflDealerId', 'fflLicense', 'fflCompanyName', 'fflExpirationDate', 'fflUuid' ) as $field ) {
			if ( '' !== $this->get_extension_value( $ffl_data, $field ) ) {
				$current_selection_started = true;
				break;
			}
		}

		$requires_dealer = $analyzer->has_firearms() || $current_selection_started;
		if ( ! $requires_dealer && $analyzer->is_ammo_only() ) {
			$destination = $this->get_order_destination( $order );
			$requires_dealer = $analyzer->requires_ffl_for_state( $destination );
			if ( ! $requires_dealer && WC()->session ) {
				$selected_state = WC()->session->get( 'automaticffl_ammo_state', '' );
				$requires_dealer = is_string( $selected_state ) && $analyzer->requires_ffl_for_state( $selected_state );
			}
			// A prior draft selection must never satisfy an omitted current selection.
			if ( ! $requires_dealer && '' !== (string) $order->get_meta( '_ffl_license_field' ) ) {
				$requires_dealer = true;
			}
		}

		if ( $requires_dealer && ( ! $has_ffl_data || ! $this->has_complete_current_selection( $order, $ffl_data ) ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'ffl_dealer_required',
				__( 'Please select a complete FFL dealer for this order.', 'automaticffl-for-wc' ),
				400
			);
		}
	}

	/**
	 * Set the order shipping address from selected FFL dealer Store API data.
	 *
	 * @since 1.0.22
	 *
	 * @param \WC_Order $order    Order object.
	 * @param array     $ffl_data Automatic FFL extension data.
	 * @return void
	 */
	private function set_order_shipping_from_ffl_data( $order, array $ffl_data ) {
		$company = $this->get_extension_value( $ffl_data, 'fflCompanyName' );
		if ( '' !== $company ) {
			$order->set_shipping_company( $company );
		}

		if ( ! $this->has_dealer_address_data( $ffl_data ) ) {
			return;
		}

		$shipping_first_name = $order->get_shipping_first_name();
		if ( '' === $shipping_first_name ) {
			$shipping_first_name = $order->get_billing_first_name();
		}

		$shipping_last_name = $order->get_shipping_last_name();
		if ( '' === $shipping_last_name ) {
			$shipping_last_name = $order->get_billing_last_name();
		}

		$order->set_shipping_first_name( $shipping_first_name );
		$order->set_shipping_last_name( $shipping_last_name );
		$order->set_shipping_company( $company );
		$order->set_shipping_address_1( $this->get_extension_value( $ffl_data, 'fflDealerAddress1' ) );
		$order->set_shipping_address_2( $this->get_extension_value( $ffl_data, 'fflDealerAddress2' ) );
		$destination = US_States::to_woocommerce_destination(
			$this->get_extension_value( $ffl_data, 'fflDealerCountry' ),
			$this->get_extension_value( $ffl_data, 'fflDealerState' )
		);

		$order->set_shipping_city( $this->get_extension_value( $ffl_data, 'fflDealerCity' ) );
		$order->set_shipping_state( $destination['state'] );
		$order->set_shipping_postcode( $this->get_extension_value( $ffl_data, 'fflDealerPostcode' ) );
		$order->set_shipping_country( $destination['country'] );
		$order->set_shipping_phone( $this->get_extension_value( $ffl_data, 'fflDealerPhone' ) );
	}

	/**
	 * Determine whether extension data contains a dealer destination.
	 *
	 * @since 1.0.22
	 *
	 * @param array $ffl_data Automatic FFL extension data.
	 * @return bool
	 */
	private function has_dealer_address_data( array $ffl_data ) {
		$keys = array(
			'fflCompanyName',
			'fflDealerAddress1',
			'fflDealerCity',
			'fflDealerPostcode',
			'fflDealerCountry',
		);

		foreach ( $keys as $key ) {
			if ( '' === $this->get_extension_value( $ffl_data, $key ) ) {
				return false;
			}
		}

		$destination = US_States::to_woocommerce_destination(
			$this->get_extension_value( $ffl_data, 'fflDealerCountry' ),
			$this->get_extension_value( $ffl_data, 'fflDealerState' )
		);

		return ! $this->is_state_required( $destination['country'] ) || '' !== $destination['state'];
	}

	/**
	 * Determine whether a final request contains one complete current dealer.
	 *
	 * @since 1.0.35
	 *
	 * @param \WC_Order $order    Draft order before extension mutation.
	 * @param array     $ffl_data Current request extension data.
	 * @return bool
	 */
	private function has_complete_current_selection( $order, array $ffl_data ) {
		if ( 0 >= absint( $this->get_extension_value( $ffl_data, 'fflDealerId' ) )
			|| '' === $this->get_extension_value( $ffl_data, 'fflLicense' )
			|| ! $this->has_dealer_address_data( $ffl_data ) ) {
			return false;
		}

		$first_name = $this->get_order_address_value( $order, 'shipping', 'first_name' );
		$last_name  = $this->get_order_address_value( $order, 'shipping', 'last_name' );
		if ( '' === $first_name ) {
			$first_name = $this->get_order_address_value( $order, 'billing', 'first_name' );
		}
		if ( '' === $last_name ) {
			$last_name = $this->get_order_address_value( $order, 'billing', 'last_name' );
		}

		return '' !== $first_name && '' !== $last_name;
	}

	/**
	 * Determine whether the saved order contains a complete FFL projection.
	 *
	 * @since 1.0.35
	 *
	 * @param \WC_Order $order Order object.
	 * @return bool
	 */
	private function order_has_complete_selection( $order ) {
		if ( 0 >= absint( $order->get_meta( '_ffl_dealer_id' ) )
			|| '' === sanitize_text_field( (string) $order->get_meta( '_ffl_license_field' ) ) ) {
			return false;
		}

		foreach ( array( 'company', 'address_1', 'city', 'postcode', 'country' ) as $field ) {
			if ( '' === $this->get_order_address_value( $order, 'shipping', $field ) ) {
				return false;
			}
		}

		$country = $this->get_order_address_value( $order, 'shipping', 'country' );
		$state   = $this->get_order_address_value( $order, 'shipping', 'state' );
		if ( $this->is_state_required( $country ) && '' === $state ) {
			return false;
		}

		$first_name = $this->get_order_address_value( $order, 'shipping', 'first_name' );
		$last_name  = $this->get_order_address_value( $order, 'shipping', 'last_name' );
		if ( '' === $first_name ) {
			$first_name = $this->get_order_address_value( $order, 'billing', 'first_name' );
		}
		if ( '' === $last_name ) {
			$last_name = $this->get_order_address_value( $order, 'billing', 'last_name' );
		}

		return '' !== $first_name && '' !== $last_name;
	}

	/**
	 * Read a sanitized order address value.
	 *
	 * @since 1.0.35
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $type  shipping or billing.
	 * @param string    $field Address field.
	 * @return string
	 */
	private function get_order_address_value( $order, $type, $field ) {
		$getter = 'get_' . $type . '_' . $field;
		if ( ! is_callable( array( $order, $getter ) ) ) {
			return '';
		}

		$value = $order->$getter();
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Resolve the current order destination before FFL extension mutation.
	 *
	 * @since 1.0.35
	 *
	 * @param \WC_Order $order Order object.
	 * @return string
	 */
	private function get_order_destination( $order ) {
		$destination = US_States::resolve_destination(
			$this->get_order_address_value( $order, 'shipping', 'country' ),
			$this->get_order_address_value( $order, 'shipping', 'state' )
		);
		if ( '' === $destination ) {
			$destination = US_States::resolve_destination(
				$this->get_order_address_value( $order, 'billing', 'country' ),
				$this->get_order_address_value( $order, 'billing', 'state' )
			);
		}
		return $destination;
	}

	/**
	 * Check WooCommerce's country-specific state requirement.
	 *
	 * @since 1.0.35
	 *
	 * @param string $country WooCommerce country code.
	 * @return bool
	 */
	private function is_state_required( $country ) {
		if ( ! WC()->countries || ! is_callable( array( WC()->countries, 'get_address_fields' ) ) ) {
			return false;
		}
		$fields = WC()->countries->get_address_fields( $country, 'shipping_' );
		return ! empty( $fields['shipping_state']['required'] );
	}

	/**
	 * Read a sanitized extension value.
	 *
	 * @since 1.0.22
	 *
	 * @param array  $data Extension data.
	 * @param string $key  Data key.
	 * @return string
	 */
	private function get_extension_value( array $data, $key ) {
		if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( (string) $data[ $key ] ) );
	}

	/**
	 * Enforce existing mixed-cart policy at both Store API validation boundaries.
	 *
	 * @since 1.0.35
	 *
	 * @param \WC_Order    $order    Order object.
	 * @param Cart_Analyzer $analyzer Current cart analyzer.
	 * @return void
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException Invalid mixed cart.
	 */
	private function validate_mixed_cart( $order, Cart_Analyzer $analyzer ) {
		if ( $analyzer->is_firearms_regular_mixed() ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'ffl_regular_mixed',
				__( 'Firearms must be shipped to an FFL dealer and cannot be combined with regular products.', 'automaticffl-for-wc' ),
				400
			);
		}

		if ( $analyzer->is_ammo_regular_mixed() ) {
			$shipping_destination = $this->get_order_destination( $order );
			if ( $analyzer->requires_ffl_for_state( $shipping_destination ) ) {
				$state_name = US_States::get_name( $shipping_destination );
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

		$this->validate_mixed_cart( $order, $analyzer );

		// Firearms always need FFL
		if ( $analyzer->has_firearms() ) {
			if ( ! $this->order_has_complete_selection( $order ) ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
					'ffl_dealer_required',
					__( 'Please select a complete FFL dealer for your firearm order.', 'automaticffl-for-wc' ),
					400
				);
			}
			return;
		}

		// Ammo only - check state restriction
		if ( $analyzer->is_ammo_only() ) {
			$shipping_destination = $this->get_order_destination( $order );

			if ( $analyzer->requires_ffl_for_state( $shipping_destination ) ) {
				if ( ! $this->order_has_complete_selection( $order ) ) {
					throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
						'ffl_dealer_required_ammo',
						__( 'Please select a complete FFL dealer for ammunition shipping to this state.', 'automaticffl-for-wc' ),
						400
					);
				}
			}
		}
	}
}
