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
