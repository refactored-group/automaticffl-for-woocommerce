<?php
/** Registration diagnostic mapping behavior. */

use RefactoredGroup\AutomaticFFL\Helper\Config;
use RefactoredGroup\AutomaticFFL\Helper\Connection_Status;

require_once dirname( __DIR__, 2 ) . '/includes/helper/class-credentials.php';

/** Seed safe registration prerequisites. */
function affl_seed_registration() {
	$GLOBALS['affl_options']['wc_ffl_store_hash'] = 'test-hash';
	$GLOBALS['affl_options']['automaticffl_app_credentials'] = array(
		'username' => 'manager',
		'password' => 'top-secret-password',
	);
}

affl_test(
	'registration records known reasons without raw response data',
	function () {
		affl_seed_registration();
		$GLOBALS['affl_http_queue'][] = affl_http_response(
			400,
			array( 'error' => 'invalid_credentials', 'password' => 'leaked-secret' ),
			array( 'x-request-id' => 'request_9' )
		);

		Config::register_with_backend();
		$status = Connection_Status::get_registration();
		affl_assert_same( 'needs_attention', $status['state'] );
		affl_assert_same( 'invalid_credentials', $status['code'] );
		affl_assert_same( 'request_9', $status['request_id'] );
		affl_assert_false( false !== strpos( json_encode( $GLOBALS['affl_options'][ Connection_Status::OPTION_KEY ] ), 'leaked-secret' ) );
	}
);

affl_test(
	'registration maps unknown and malformed failures to a local generic code',
	function () {
		foreach ( array( '<script>hostile</script>', '{' ) as $body ) {
			affl_reset_test_state();
			affl_seed_registration();
			$GLOBALS['affl_http_queue'][] = affl_http_response( 400, $body );
			Config::register_with_backend();
			$status = Connection_Status::get_registration();
			affl_assert_same( 'registration_failed', $status['code'] );
			affl_assert_false( false !== strpos( json_encode( $GLOBALS['affl_options'][ Connection_Status::OPTION_KEY ] ), 'hostile' ) );
		}
	}
);

affl_test(
	'successful registration preserves checkout status and registered-version behavior',
	function () {
		affl_seed_registration();
		Connection_Status::save_checkout( 'connected', null, 200, null, time() );
		$GLOBALS['affl_http_queue'][] = affl_http_response( 200, array( 'success' => true ) );

		Config::register_with_backend();
		affl_assert_same( AFFL_VERSION, $GLOBALS['affl_options']['automaticffl_registered_version'] );
		affl_assert_same( 'registered', Connection_Status::get_registration()['state'] );
		affl_assert_same( 'connected', Connection_Status::get_checkout()['state'] );
	}
);

affl_test(
	'credential and transport failures remain independent of checkout status',
	function () {
		$GLOBALS['affl_options']['wc_ffl_store_hash'] = 'test-hash';
		Connection_Status::save_checkout( 'connected', null, 200, null, time() );
		$GLOBALS['affl_options']['automaticffl_app_credentials'] = array();

		Config::register_with_backend();
		affl_assert_same( 'needs_attention', Connection_Status::get_registration()['state'] );
		affl_assert_same( 'connected', Connection_Status::get_checkout()['state'] );
	}
);
