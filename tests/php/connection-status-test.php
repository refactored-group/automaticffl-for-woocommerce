<?php
/** Diagnostic state storage behavior. */

use RefactoredGroup\AutomaticFFL\Helper\Connection_Status;

require_once dirname( __DIR__, 2 ) . '/includes/helper/class-connection-status.php';

affl_test(
	'connection status stores checkout and registration independently',
	function () {
		$GLOBALS['affl_options']['wc_ffl_store_hash'] = 'hash-one';
		Connection_Status::save_checkout( 'connected', null, 200, 'request_1', 1700000000 );
		Connection_Status::save_registration( 'needs_attention', 'invalid_credentials', 401, 'request_2', 1700000001 );

		$checkout     = Connection_Status::get_checkout( 1700000010 );
		$registration = Connection_Status::get_registration( 1700000010 );
		affl_assert_same( 'connected', $checkout['state'] );
		affl_assert_same( 'needs_attention', $registration['state'] );
		affl_assert_same( 'invalid_credentials', $registration['code'] );
		affl_assert_same( false, $checkout['stale'] );
	}
);

affl_test(
	'connection status invalidates on context change and labels stale evidence',
	function () {
		$GLOBALS['affl_options']['wc_ffl_store_hash'] = 'hash-one';
		Connection_Status::save_checkout( 'connected', null, 200, null, 1700000000 );

		$stale = Connection_Status::get_checkout( 1700000000 + 86401 );
		affl_assert_same( 'connected', $stale['state'] );
		affl_assert_true( $stale['stale'] );
		affl_assert_false( $stale['current'] );

		$GLOBALS['affl_options']['wc_ffl_store_hash'] = 'hash-two';
		$mismatch = Connection_Status::get_checkout( 1700000010 );
		affl_assert_same( 'not_checked', $mismatch['state'] );
		affl_assert_false( $mismatch['current'] );
	}
);

affl_test(
	'connection status allowlists and bounds all persisted diagnostics',
	function () {
		$GLOBALS['affl_options']['wc_ffl_store_hash'] = 'hash-one';
		Connection_Status::save_registration(
			'needs_attention',
			'<script>secret-password</script>',
			999,
			'bad request id with spaces',
			1700000000
		);

		$stored = $GLOBALS['affl_options'][ Connection_Status::OPTION_KEY ];
		$record = $stored['registration'];
		affl_assert_same( 'registration_failed', $record['code'] );
		affl_assert_same( null, $record['http_status'] );
		affl_assert_same( null, $record['request_id'] );
		affl_assert_false( false !== strpos( json_encode( $stored ), 'secret-password' ) );
		affl_assert_false( isset( $record['password'] ) );
	}
);

affl_test(
	'missing configuration remains a bounded local status',
	function () {
		Connection_Status::save_checkout( 'needs_attention', 'missing_store_hash', null, null, 1700000000 );
		$status = Connection_Status::get_checkout( 1700000001 );
		affl_assert_same( 'needs_attention', $status['state'] );
		affl_assert_same( 'missing_store_hash', $status['code'] );
		affl_assert_same( array(), $GLOBALS['affl_http_calls'] );
	}
);
