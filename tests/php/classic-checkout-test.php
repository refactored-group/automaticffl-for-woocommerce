<?php
/** Cart requests must finish while classic checkout still enforces separation. */

use RefactoredGroup\AutomaticFFL\Plugin;

require_once dirname( __DIR__, 2 ) . '/includes/class-plugin.php';

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
