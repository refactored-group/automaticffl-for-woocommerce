<?php
/** Cart requests must finish while classic checkout still enforces separation. */

use RefactoredGroup\AutomaticFFL\Plugin;
use RefactoredGroup\AutomaticFFL\Views\Checkout;

require_once dirname( __DIR__, 2 ) . '/includes/class-plugin.php';
require_once dirname( __DIR__, 2 ) . '/includes/views/class-checkout.php';

function affl_reset_classic_checkout_analyzer() {
	$property = new ReflectionProperty( Checkout::class, 'analyzer' );
	$property->setValue( null, null );
}

function wc_add_notice( $message, $type = 'success' ) {
	$GLOBALS['affl_checkout_notices'][] = array( 'message' => $message, 'type' => $type );
}

function affl_checkout_request_result( $context, $kind, $state = '' ) {
	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/checkout-request.php' );
	foreach ( array( $context, $kind, $state ) as $argument ) {
		$command .= ' ' . escapeshellarg( $argument );
	}
	exec( $command . ' 2>&1', $output, $status );
	affl_assert_same( 0, $status, implode( "\n", $output ) );
	return implode( "\n", $output );
}

affl_test( 'mixed carts do not redirect AJAX, REST, admin, cart, or product requests', function () {
	foreach ( array( 'ajax', 'checkout-ajax', 'rest', 'admin', 'cart', 'product' ) as $context ) {
		foreach ( array( 'firearm-mixed', 'ammo-mixed' ) as $kind ) {
			affl_assert_same( 'continue', affl_checkout_request_result( $context, $kind, 'CA' ), $context . ': ' . $kind );
		}
	}
} );
affl_test( 'checkout page redirects mixed carts only when separation is required', function () {
	affl_assert_same( 'redirect:/cart/', affl_checkout_request_result( 'checkout', 'firearm-mixed' ) );
	affl_assert_same( 'redirect:/cart/', affl_checkout_request_result( 'checkout', 'ammo-mixed', 'CA' ) );
	affl_assert_same( 'redirect:/cart/', affl_checkout_request_result( 'checkout', 'ammo-mixed' ) );
	foreach ( array( 'regular', 'firearm-only', 'unavailable' ) as $kind ) {
		affl_assert_same( 'continue', affl_checkout_request_result( 'checkout', $kind ) );
	}
	affl_assert_same( 'continue', affl_checkout_request_result( 'checkout', 'ammo-mixed', 'UT' ) );
} );

function affl_classic_checkout_plugin() {
	$GLOBALS['affl_checkout_notices'] = array();
	$_POST = array( 'billing_first_name' => 'Test', 'billing_last_name' => 'Customer', 'billing_state' => 'UT' );
	return ( new ReflectionClass( Plugin::class ) )->newInstanceWithoutConstructor();
}

class AFFL_Test_Classic_Order {
	public $meta = array();
	public $shipping = array();
	public $billing = array();
	public $notes = array();
	public $save_count = 0;

	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
	public function get_meta( $key ) { return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : ''; }
	public function add_order_note( $note ) { $this->notes[] = $note; }
	public function save() { ++$this->save_count; }
	public function __call( $name, $arguments ) {
		if ( 0 === strpos( $name, 'set_shipping_' ) ) {
			$this->shipping[ substr( $name, 13 ) ] = isset( $arguments[0] ) ? $arguments[0] : '';
			return;
		}
		if ( 0 === strpos( $name, 'set_billing_' ) ) {
			$this->billing[ substr( $name, 12 ) ] = isset( $arguments[0] ) ? $arguments[0] : '';
			return;
		}
		throw new BadMethodCallException( $name );
	}
}

affl_test( 'classic FFL fields are registered as native order checkout data', function () {
	affl_cart_blocks_fixture( array( array( 'id' => 1 ) ) );
	unset( WC()->cart->items['b'] );
	affl_reset_classic_checkout_analyzer();
	$fields = Checkout::automaticffl_custom_fields(
		array(
			'order'    => array(),
			'shipping' => array(),
		)
	);

	foreach ( array( 'ffl_dealer_id', 'ffl_license_field', 'ffl_expiration_date', 'ffl_uuid', 'ffl_company_name' ) as $key ) {
		affl_assert_true( isset( $fields['order'][ $key ] ), $key . ' must be registered.' );
		affl_assert_same( 'hidden', $fields['order'][ $key ]['type'] );
	}
	affl_assert_false( $fields['order']['ffl_dealer_id']['required'], 'One complete-selection validator owns conditional requirements.' );
	affl_assert_false( $fields['order']['ffl_license_field']['required'], 'Native field validation must not duplicate the complete-selection error.' );
	affl_assert_false( $fields['order']['ffl_company_name']['required'], 'Native field validation must not duplicate the complete-selection error.' );
	affl_assert_false( $fields['order']['ffl_expiration_date']['required'] );
	affl_assert_false( $fields['order']['ffl_uuid']['required'] );
} );

affl_test( 'classic normalized data preserves current dealer shipping and customer billing', function () {
	affl_cart_blocks_fixture( array( array( 'id' => 1 ) ) );
	unset( WC()->cart->items['b'] );
	$plugin = affl_classic_checkout_plugin();
	$_POST = array(
		'ffl_dealer_id'       => '42',
		'ffl_license_field'   => 'fixture-license',
		'ffl_company_name'    => 'Fixture Dealer',
		'shipping_first_name' => 'Test',
		'shipping_last_name'  => 'Customer',
		'shipping_company'    => 'Fixture Dealer',
		'shipping_address_1'  => '42 Dealer Street',
		'shipping_address_2'  => 'Suite 2',
		'shipping_city'       => 'Orem',
		'shipping_state'      => 'UT',
		'shipping_postcode'   => '84057',
		'shipping_country'    => 'US',
		'shipping_phone'      => '8015550100',
	);
	$data = array(
		'billing_address_1'  => '1 Customer Street',
		'shipping_address_1' => '1 Customer Street',
		'shipping_city'      => 'Provo',
		'shipping_state'     => 'UT',
		'shipping_postcode'  => '84601',
		'shipping_country'   => 'US',
	);

	$normalized = $plugin->normalize_classic_ffl_checkout_data( $data );
	affl_assert_same( '1 Customer Street', $normalized['billing_address_1'] );
	affl_assert_same( '42 Dealer Street', $normalized['shipping_address_1'] );
	affl_assert_same( 'Fixture Dealer', $normalized['shipping_company'] );
	affl_assert_same( 'fixture-license', $normalized['ffl_license_field'] );
	affl_assert_same( '42', $normalized['ffl_dealer_id'] );

	$_POST['shipping_address_1'] = array( 'invalid' );
	$normalized = $plugin->normalize_classic_ffl_checkout_data( $data );
	affl_assert_same( '', $normalized['shipping_address_1'], 'Non-scalar dealer fields must be rejected.' );
} );

function affl_complete_classic_ffl_data() {
	return array(
		'ffl_dealer_id'         => '42',
		'ffl_license_field'     => 'fixture-license',
		'ffl_expiration_date'   => '2030-01-01',
		'ffl_uuid'              => 'fixture-uuid',
		'ffl_company_name'      => 'Fixture Dealer',
		'billing_first_name'    => 'Test',
		'billing_last_name'     => 'Customer',
		'billing_address_1'     => '1 Customer Street',
		'shipping_first_name'   => 'Test',
		'shipping_last_name'    => 'Customer',
		'shipping_company'      => 'Fixture Dealer',
		'shipping_address_1'    => '42 Dealer Street',
		'shipping_address_2'    => '',
		'shipping_city'         => 'Orem',
		'shipping_state'        => 'UT',
		'shipping_postcode'     => '84057',
		'shipping_country'      => 'US',
		'shipping_phone'        => '8015550100',
	);
}

affl_test( 'classic totals-only and final validation require one complete current dealer', function () {
	foreach ( array( true, false ) as $totals_only ) {
		affl_cart_blocks_fixture( array( array( 'id' => 1 ) ) );
		unset( WC()->cart->items['b'] );
		$plugin = affl_classic_checkout_plugin();
		$data = affl_complete_classic_ffl_data();
		if ( $totals_only ) {
			$data['woocommerce_checkout_update_totals'] = 1;
		}
		$errors = new WP_Error();
		$plugin->validate_classic_ffl_checkout_data( $data, $errors );
		affl_assert_same( array(), $errors->get_error_messages(), 'Complete dealer must pass both preflight and final validation.' );

		foreach ( array( 'ffl_dealer_id', 'ffl_license_field', 'ffl_company_name', 'shipping_address_1', 'shipping_city', 'shipping_state', 'shipping_postcode', 'shipping_country' ) as $missing ) {
			$partial = $data;
			$partial[ $missing ] = '';
			$errors = new WP_Error();
			$plugin->validate_classic_ffl_checkout_data( $partial, $errors );
			affl_assert_same( 1, count( $errors->get_error_messages() ), $missing . ' must be rejected once.' );
		}

		$partial = $data;
		$partial['ffl_dealer_id'] = array( 'invalid' );
		$errors = new WP_Error();
		$plugin->validate_classic_ffl_checkout_data( $partial, $errors );
		affl_assert_same( 1, count( $errors->get_error_messages() ), 'Non-scalar dealer ID must be rejected.' );
	}
} );

affl_test( 'classic restricted ammunition cannot escape completeness after dealer shipping replaces the customer state', function () {
	$restrictions = array(
		array(
			'id'         => 1,
			'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'CA' ) ) ),
		),
	);
	affl_cart_blocks_fixture( $restrictions );
	unset( WC()->cart->items['b'] );
	$plugin = affl_classic_checkout_plugin();

	$partial = affl_complete_classic_ffl_data();
	$partial['shipping_state'] = 'UT';
	$partial['shipping_city']  = '';
	$errors = new WP_Error();
	$plugin->validate_classic_ffl_checkout_data( $partial, $errors );
	affl_assert_same( 1, count( $errors->get_error_messages() ), 'A started dealer selection stays subject to completeness after its destination replaces the customer state.' );

	$missing = affl_complete_classic_ffl_data();
	foreach ( array( 'ffl_dealer_id', 'ffl_license_field', 'ffl_company_name', 'ffl_expiration_date', 'ffl_uuid' ) as $field ) {
		$missing[ $field ] = '';
	}
	$missing['shipping_state'] = 'CA';
	$errors = new WP_Error();
	$plugin->validate_classic_ffl_checkout_data( $missing, $errors );
	affl_assert_same( 1, count( $errors->get_error_messages() ), 'Restricted customer shipping without a dealer must fail.' );
} );

affl_test( 'classic dealer requirement replaces other errors only while the current cart needs an FFL', function () {
	$plugin = affl_classic_checkout_plugin();

	// Firearms always require a dealer. Hidden shipping-field and unrelated
	// validation errors should collapse to one actionable dealer message.
	affl_cart_blocks_fixture( array( array( 'id' => 1 ) ) );
	unset( WC()->cart->items['b'] );
	$data = affl_complete_classic_ffl_data();
	foreach ( array( 'ffl_dealer_id', 'ffl_license_field', 'ffl_company_name', 'ffl_expiration_date', 'ffl_uuid', 'shipping_address_1' ) as $field ) {
		$data[ $field ] = '';
	}
	$errors = new WP_Error();
	$errors->add( 'shipping_address_1_required', 'Shipping Street address is a required field.' );
	$errors->add( 'shipping', 'Please enter an address to continue.' );
	$errors->add( 'billing_email_required', 'Billing Email is a required field.' );
	$plugin->validate_classic_ffl_checkout_data( $data, $errors );
	affl_assert_same( array( 'ffl_dealer_required' ), $errors->get_error_codes() );
	affl_assert_same( array( 'Please select a complete FFL dealer for this order.' ), $errors->get_error_messages() );

	// Restricted ammunition uses the same single message for its current state.
	$restrictions = array(
		array(
			'id'         => 1,
			'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'CA' ) ) ),
		),
	);
	affl_cart_blocks_fixture( $restrictions );
	unset( WC()->cart->items['b'] );
	$data['shipping_state'] = 'CA';
	$errors = new WP_Error();
	$errors->add( 'shipping_address_1_required', 'Shipping Street address is a required field.' );
	$plugin->validate_classic_ffl_checkout_data( $data, $errors );
	affl_assert_same( array( 'ffl_dealer_required' ), $errors->get_error_codes() );

	// The same ammunition cart in an unrestricted state must retain normal
	// WooCommerce validation instead of showing the dealer message.
	affl_cart_blocks_fixture( $restrictions );
	unset( WC()->cart->items['b'] );
	$data['shipping_state'] = 'UT';
	$errors = new WP_Error();
	$errors->add( 'shipping_address_1_required', 'Shipping Street address is a required field.' );
	$plugin->validate_classic_ffl_checkout_data( $data, $errors );
	affl_assert_same( array( 'shipping_address_1_required' ), $errors->get_error_codes() );

	// Ordinary carts also keep every standard WooCommerce validation error.
	affl_cart_blocks_fixture();
	$errors = new WP_Error();
	$errors->add( 'shipping_address_1_required', 'Shipping Street address is a required field.' );
	$errors->add( 'billing_email_required', 'Billing Email is a required field.' );
	$plugin->validate_classic_ffl_checkout_data( $data, $errors );
	affl_assert_same( array( 'shipping_address_1_required', 'billing_email_required' ), $errors->get_error_codes() );
} );

affl_test( 'classic explicit data saves metadata and dealer shipping without browser POST', function () {
	$_POST = array(
		'shipping_address_1' => '999 Stale Browser Street',
		'ffl_uuid'           => 'stale-browser-uuid',
	);
	$data  = affl_complete_classic_ffl_data();
	$data['shipping_first_name'] = '';
	$data['shipping_last_name']  = '';
	$order = new AFFL_Test_Classic_Order();
	$order->meta['_ffl_expiration_date'] = 'old-expiration';
	$order->meta['_ffl_uuid']            = 'old-uuid';

	Checkout::save_automaticffl_checkout_fields( $order, $data );
	$plugin = affl_classic_checkout_plugin();
	$plugin->override_classic_ffl_shipping_address( $order, $data );

	affl_assert_same( 42, $order->meta['_ffl_dealer_id'] );
	affl_assert_same( 'fixture-license', $order->meta['_ffl_license_field'] );
	affl_assert_same( 'fixture-uuid', $order->meta['_ffl_uuid'] );
	affl_assert_same( '42 Dealer Street', $order->shipping['address_1'] );
	affl_assert_same( 'Fixture Dealer', $order->shipping['company'] );
	affl_assert_same( 'Test', $order->shipping['first_name'], 'Billing name must fill the dealer recipient without using POST.' );
	affl_assert_same( array(), $order->billing, 'Order projection must not replace customer billing.' );

	$data['ffl_expiration_date'] = '';
	$data['ffl_uuid']            = '';
	Checkout::save_automaticffl_checkout_fields( $order, $data );
	Checkout::save_automaticffl_checkout_fields( $order, $data );
	affl_assert_false( isset( $order->meta['_ffl_expiration_date'] ) );
	affl_assert_false( isset( $order->meta['_ffl_uuid'] ) );
	affl_assert_same( 'fixture-license', $order->meta['_ffl_license_field'] );
} );

affl_test( 'classic submission rejects firearm mixed carts even with a dealer selected', function () {
	foreach ( array( '', 'fixture-license' ) as $license ) {
		affl_cart_blocks_fixture( array( array( 'id' => 1 ) ) );
		$plugin = affl_classic_checkout_plugin();
		$_POST['ffl_license_field'] = $license;
		$plugin->validate_ffl_checkout();
		affl_assert_same( 1, count( $GLOBALS['affl_checkout_notices'] ) );
		affl_assert_same( 'error', $GLOBALS['affl_checkout_notices'][0]['type'] );
		affl_assert_contains( 'cannot be combined', $GLOBALS['affl_checkout_notices'][0]['message'] );
	}
} );

affl_test( 'classic submission preserves ordinary, firearm-only, and unavailable classification behavior', function () {
	$plugin = affl_classic_checkout_plugin();
	affl_cart_blocks_fixture();
	$plugin->validate_ffl_checkout();
	affl_assert_same( array(), $GLOBALS['affl_checkout_notices'] );

	affl_cart_blocks_fixture( array( array( 'id' => 1 ), array( 'id' => 2 ) ) );
	$plugin->validate_ffl_checkout();
	affl_assert_same( 1, count( $GLOBALS['affl_checkout_notices'] ) );
	affl_assert_contains( 'select an FFL dealer', $GLOBALS['affl_checkout_notices'][0]['message'] );

	$GLOBALS['affl_checkout_notices'] = array();
	$_POST['ffl_license_field'] = 'fixture-license';
	$plugin->validate_ffl_checkout();
	affl_assert_same( array(), $GLOBALS['affl_checkout_notices'] );

	affl_cart_blocks_fixture();
	$GLOBALS['affl_http_queue'] = array( affl_http_response( 500, '' ) );
	$plugin->validate_ffl_checkout();
	affl_assert_same( array(), $GLOBALS['affl_checkout_notices'] );
} );

affl_test( 'classic ammo mixed submission uses the submitted destination instead of a stale cart state', function () {
	$restrictions = array( array( 'id' => 1, 'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'CA' ) ) ) ) );
	foreach ( array( 'billing', 'shipping' ) as $destination ) {
		affl_cart_blocks_fixture( $restrictions );
		$plugin = affl_classic_checkout_plugin();
		WC()->session->set( 'automaticffl_ammo_state', 'UT' );
		$_POST[ $destination . '_state' ] = 'CA';
		$_POST['ship_to_different_address'] = 'shipping' === $destination ? '1' : '';
		$plugin->validate_ffl_checkout();
		$plugin->validate_ammo_regular_checkout();
		affl_assert_same( 1, count( $GLOBALS['affl_checkout_notices'] ) );
		affl_assert_contains( 'separate orders', $GLOBALS['affl_checkout_notices'][0]['message'] );
	}

	affl_cart_blocks_fixture( $restrictions );
	$plugin = affl_classic_checkout_plugin();
	WC()->session->set( 'automaticffl_ammo_state', 'CA' );
	$plugin->validate_ffl_checkout();
	$plugin->validate_ammo_regular_checkout();
	affl_assert_same( array(), $GLOBALS['affl_checkout_notices'] );
} );
affl_test( 'classic ammo mixed submission resolves WooCommerce territory countries', function () {
	$restrictions = array( array( 'id' => 1, 'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'PR' ) ) ) ) );
	$plugin = affl_classic_checkout_plugin();
	affl_cart_blocks_fixture( $restrictions );
	$_POST['billing_country'] = 'PR';
	$_POST['billing_state']   = '';
	$plugin->validate_ammo_regular_checkout();
	affl_assert_same( 1, count( $GLOBALS['affl_checkout_notices'] ) );
	affl_assert_contains( 'Puerto Rico', $GLOBALS['affl_checkout_notices'][0]['message'] );

	$GLOBALS['affl_checkout_notices'] = array();
	$_POST['billing_country'] = 'CA';
	$_POST['billing_state']   = 'PR';
	$plugin->validate_ammo_regular_checkout();
	affl_assert_same( array(), $GLOBALS['affl_checkout_notices'] );
} );

affl_test( 'classic ammo checkout preserves its native saved destination fields', function () {
	$restrictions = array( array( 'id' => 1, 'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'CA' ) ) ) ) );
	affl_cart_blocks_fixture( $restrictions );
	unset( WC()->cart->items['b'] );
	$plugin = affl_classic_checkout_plugin();

	affl_assert_same( 'Saved street', $plugin->clear_shipping_address_fields( 'Saved street', 'shipping_address_1' ) );
	affl_assert_same( 'CA', $plugin->clear_shipping_address_fields( 'CA', 'shipping_state' ) );
} );

affl_test( 'fresh classic ammo checkout restores persistent shipping over a stale dealer session', function () {
	$restrictions = array( array( 'id' => 1, 'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'CA' ) ) ) ) );
	affl_cart_blocks_fixture( $restrictions );
	unset( WC()->cart->items['b'] );
	$GLOBALS['affl_logged_in']          = true;
	$GLOBALS['affl_is_checkout']        = true;
	$GLOBALS['affl_has_checkout_block'] = false;
	$GLOBALS['affl_user_meta'][1]       = array(
		'shipping_first_name' => 'Automatic FFL',
		'shipping_last_name'  => 'QA Test Updated',
		'shipping_company'    => '',
		'shipping_address_1'  => '5916 Lost Valley Dr',
		'shipping_address_2'  => '',
		'shipping_city'       => 'The Colony',
		'shipping_state'      => 'TX',
		'shipping_postcode'   => '75056',
		'shipping_country'    => 'US',
		'shipping_phone'      => '14692071149',
	);
	$stale_dealer = array(
		'first_name' => 'Giancarlo',
		'last_name'  => 'Byrne',
		'company'    => 'GGD, LLC',
		'address_1'  => '6310 SAN FERNANDO RD',
		'address_2'  => '',
		'city'       => 'GLENDALE',
		'state'      => 'CA',
		'postcode'   => '91201',
		'country'    => 'US',
		'phone'      => '8186491041',
	);
	WC()->customer = new AFFL_Test_Session_Customer( $stale_dealer );
	WC()->session->set( Plugin::SESSION_FFL_DESTINATION, $stale_dealer );

	$plugin = affl_classic_checkout_plugin();
	$plugin->restore_saved_shipping_for_ammo_checkout();

	affl_assert_same( '5916 Lost Valley Dr', WC()->customer->shipping['address_1'] );
	affl_assert_same( 'Automatic FFL', WC()->customer->shipping['first_name'] );
	affl_assert_same( 1, WC()->customer->save_count );
	affl_assert_same( null, WC()->session->get( Plugin::SESSION_FFL_DESTINATION ) );
	affl_assert_same( '5916 Lost Valley Dr', $GLOBALS['affl_user_meta'][1]['shipping_address_1'] );
} );
