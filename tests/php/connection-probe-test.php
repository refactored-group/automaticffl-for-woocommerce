<?php
/** Dedicated checkout connection probe behavior. */

affl_test(
	'checkout probe performs real empty-fact HTTP with a five-second timeout',
	function () {
		$client = affl_restrictions_client();
		$GLOBALS['affl_http_queue'][] = affl_http_response(
			200,
			array( 'schema_version' => 1, 'restrictions' => array() ),
			array( 'x-request-id' => 'request_123' )
		);

		$result = $client->probe_checkout_connection();
		affl_assert_same( 'connected', $result['state'] );
		affl_assert_same( 200, $result['http_status'] );
		affl_assert_same( 'request_123', $result['request_id'] );
		affl_assert_same( 1, count( $GLOBALS['affl_http_calls'] ) );
		affl_assert_same( 5, $GLOBALS['affl_http_calls'][0]['args']['timeout'] );
		$payload = json_decode( $GLOBALS['affl_http_calls'][0]['args']['body'], true );
		affl_assert_same( array(), $payload['products'] );
	}
);

affl_test(
	'checkout probe reports legacy capability without fallback',
	function () {
		$client = affl_restrictions_client();
		$GLOBALS['affl_http_queue'][] = affl_http_response( 404, array( 'untrusted' => 'body' ) );
		$result = $client->probe_checkout_connection();

		affl_assert_same( 'legacy_backend', $result['state'] );
		affl_assert_same( 404, $result['http_status'] );
		affl_assert_same( 1, count( $GLOBALS['affl_http_calls'] ) );
	}
);

affl_test(
	'checkout probe validates success and does not mutate cart caches or register credentials',
	function () {
		affl_seed_products( 1 );
		$client = affl_restrictions_client();
		$GLOBALS['affl_http_queue'] = array(
			affl_http_response( 200, array( 'schema_version' => 1, 'restrictions' => array() ) ),
			affl_http_response( 200, array( 'schema_version' => 1, 'restrictions' => array() ) ),
		);

		$client->get_restrictions( array( 1 ) );
		$result = $client->probe_checkout_connection();
		$client->get_restrictions( array( 1 ) );

		affl_assert_same( 'connected', $result['state'] );
		affl_assert_same( 2, count( $GLOBALS['affl_http_calls'] ) );
		affl_assert_false( isset( $GLOBALS['affl_options']['automaticffl_registered_version'] ) );
	}
);

affl_test(
	'checkout probe rejects malformed responses and missing hashes locally',
	function () {
		$client = affl_restrictions_client();
		$GLOBALS['affl_http_queue'][] = affl_http_response( 200, (object) array() );
		$result = $client->probe_checkout_connection();
		affl_assert_same( 'needs_attention', $result['state'] );
		affl_assert_same( 'invalid_response', $result['code'] );

		affl_reset_test_state();
		$client = new \RefactoredGroup\AutomaticFFL\Api\Restrictions_Client();
		$result = $client->probe_checkout_connection();
		affl_assert_same( 'missing_store_hash', $result['code'] );
		affl_assert_same( array(), $GLOBALS['affl_http_calls'] );
	}
);
