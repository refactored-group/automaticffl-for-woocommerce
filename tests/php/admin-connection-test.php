<?php
/** Admin action and status presentation behavior. */

use RefactoredGroup\AutomaticFFL\Admin\Settings;
use RefactoredGroup\AutomaticFFL\Admin\Screens\General;
use RefactoredGroup\AutomaticFFL\Helper\Connection_Status;

require_once dirname( __DIR__, 2 ) . '/includes/admin/class-abstract-settings-screen.php';
require_once dirname( __DIR__, 2 ) . '/includes/admin/screens/class-general.php';
require_once dirname( __DIR__, 2 ) . '/includes/admin/class-settings.php';

affl_test(
	'admin checkout action enforces capability and nonce',
	function () {
		$settings = new Settings();
		$GLOBALS['affl_can_manage_wc'] = false;
		try {
			$settings->check_checkout_connection();
			affl_fail( 'Expected capability failure.' );
		} catch ( RuntimeException $error ) {
			affl_assert_contains( 'permission', strtolower( $error->getMessage() ) );
		}

		$GLOBALS['affl_can_manage_wc']  = true;
		$GLOBALS['affl_nonce_is_valid'] = false;
		try {
			$settings->check_checkout_connection();
			affl_fail( 'Expected nonce failure.' );
		} catch ( RuntimeException $error ) {
			affl_assert_same( 'invalid_nonce', $error->getMessage() );
		}
	}
);

affl_test(
	'admin checkout action uses saved destinations and redirects safely',
	function () {
		$GLOBALS['affl_options']['wc_ffl_store_hash'] = 'saved-hash';
		$GLOBALS['affl_http_queue'][] = affl_http_response( 200, array( 'schema_version' => 1, 'restrictions' => array() ) );
		$_POST['url'] = 'https://attacker.example';
		$settings = new Settings();
		$settings->check_checkout_connection();

		affl_assert_contains( '/stores/saved-hash/products/classify', $GLOBALS['affl_http_calls'][0]['url'] );
		affl_assert_false( false !== strpos( $GLOBALS['affl_http_calls'][0]['url'], 'attacker' ) );
		affl_assert_contains( 'admin.php?page=wc-ffl', $GLOBALS['affl_redirect'] );
		unset( $_POST['url'] );
	}
);

affl_test(
	'connection rendering is read-only, escaped, separate, and precise',
	function () {
		$GLOBALS['affl_options']['wc_ffl_store_hash'] = 'saved-hash';
		Connection_Status::save_checkout( 'connected', null, 200, null, time() );
		Connection_Status::save_registration( 'needs_attention', 'registration_failed', 400, null, time() );

		ob_start();
		( new General() )->render_connection_status();
		$output = ob_get_clean();

		affl_assert_contains( 'Checkout API', $output );
		affl_assert_contains( 'Additional-feature credentials', $output );
		affl_assert_contains( 'category browsing', $output );
		affl_assert_contains( 'order-level certificate uploads', $output );
		affl_assert_contains( 'order reporting', $output );
		affl_assert_contains( 'normal dealer certificate links', $output );
		affl_assert_contains( '<form', $output );
		affl_assert_same( array(), $GLOBALS['affl_http_calls'] );
	}
);

affl_test(
	'connection rendering shows separate missing hash and Maps prerequisites',
	function () {
		ob_start();
		( new General() )->render_connection_status();
		$output = ob_get_clean();
		affl_assert_contains( 'Store Hash', $output );
		affl_assert_contains( 'Google Maps API key', $output );
		affl_assert_false( false !== strpos( $output, '<script>' ) );
	}
);
