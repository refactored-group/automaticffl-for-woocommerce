<?php
/** Mixed-version fallback behavior. */

use RefactoredGroup\AutomaticFFL\Api\Restrictions_Client;

affl_test(
	'404 and 405 switch once to legacy mode',
	function () {
		foreach ( array( 404, 405 ) as $status ) {
			affl_reset_test_state();
			affl_seed_products( 1 );
			$client = affl_restrictions_client();
			$GLOBALS['affl_http_queue'] = array(
				affl_http_response( $status, array( 'ignored' => true ) ),
				affl_http_response( 200, array( array( 'id' => 1 ) ) ),
			);

			affl_assert_same( array( array( 'id' => 1 ) ), $client->get_restrictions( array( 1 ) ) );
			affl_assert_same( array( 'POST', 'GET' ), array_column( $GLOBALS['affl_http_calls'], 'method' ) );
		}
	}
);

affl_test(
	'late capability loss discards direct results and restarts every ID in legacy batches',
	function () {
		affl_seed_products( 201 );
		$client = affl_restrictions_client();
		$GLOBALS['affl_http_queue'] = array(
			affl_http_response( 200, array( 'schema_version' => 1, 'restrictions' => array( array( 'id' => 1 ) ) ) ),
			affl_http_response( 404, array() ),
			affl_http_response( 200, array() ),
			affl_http_response( 200, array() ),
			affl_http_response( 200, array( array( 'id' => 201 ) ) ),
		);

		$result = $client->get_restrictions( range( 1, 201 ) );
		affl_assert_same( array( array( 'id' => 201 ) ), $result );
		affl_assert_same( array( 'POST', 'POST', 'GET', 'GET', 'GET' ), array_column( $GLOBALS['affl_http_calls'], 'method' ) );

		$legacy_ids = array();
		foreach ( array_slice( $GLOBALS['affl_http_calls'], 2 ) as $call ) {
			parse_str( (string) parse_url( $call['url'], PHP_URL_QUERY ), $query );
			affl_assert_true( count( $query['product_ids'] ) <= 100 );
			$legacy_ids = array_merge( $legacy_ids, array_map( 'intval', $query['product_ids'] ) );
		}
		affl_assert_same( range( 1, 201 ), $legacy_ids );
	}
);

affl_test(
	'legacy mode shares the direct deadline and discards partial failures',
	function () {
		affl_seed_products( 101 );
		$times = array( 0.0, 0.0, 1.0, 16.0 );
		$clock = function () use ( &$times ) {
			return array_shift( $times );
		};
		$client = affl_restrictions_client( $clock );
		$GLOBALS['affl_http_queue'] = array(
			affl_http_response( 404, array() ),
			affl_http_response( 200, array( array( 'id' => 1 ) ) ),
		);

		$result = $client->get_restrictions( range( 1, 101 ) );
		affl_assert_true( $client->has_api_error( $result ) );
		affl_assert_same( 2, count( $GLOBALS['affl_http_calls'] ) );

		affl_reset_test_state();
		affl_seed_products( 101 );
		$client = affl_restrictions_client();
		$GLOBALS['affl_http_queue'] = array(
			affl_http_response( 404, array() ),
			affl_http_response( 200, array( array( 'id' => 1 ) ) ),
			affl_http_response( 500, array() ),
		);
		$result = $client->get_restrictions( range( 1, 101 ) );
		affl_assert_true( $client->has_api_error( $result ) );
	}
);

affl_test(
	'legacy mode strictly validates top-level arrays, records, IDs, and response bounds',
	function () {
		affl_seed_products( 1 );
		$invalid = array(
			(object) array(),
			(object) array( 'error' => 'not trusted' ),
			array( array( 'id' => 2 ) ),
			array( array( 'id' => '1' ) ),
			array( array( 'id' => 1 ), array( 'id' => 1 ) ),
			array( array( 'id' => 1, 'unknown' => true ) ),
		);

		foreach ( $invalid as $legacy_body ) {
			$GLOBALS['affl_http_calls'] = array();
			$GLOBALS['affl_http_queue'] = array(
				affl_http_response( 404, array() ),
				affl_http_response( 200, $legacy_body ),
			);
			$client = affl_restrictions_client();
			affl_assert_true( $client->has_api_error( $client->get_restrictions( array( 1 ) ) ) );
		}

		$GLOBALS['affl_http_queue'] = array(
			affl_http_response( 404, array() ),
			affl_http_response( 200, str_repeat( 'x', 524289 ) ),
		);
		$client = affl_restrictions_client();
		affl_assert_true( $client->has_api_error( $client->get_restrictions( array( 1 ) ) ) );
	}
);

affl_test(
	'only 404 and 405 trigger legacy fallback',
	function () {
		affl_seed_products( 1 );
		$responses = array(
			affl_http_response( 401, array() ),
			affl_http_response( 422, array() ),
			affl_http_response( 429, array() ),
			affl_http_response( 500, array() ),
			new WP_Error( 'timeout', 'ignored' ),
			affl_http_response( 200, '{' ),
		);

		foreach ( $responses as $response ) {
			$GLOBALS['affl_http_calls'] = array();
			$GLOBALS['affl_http_queue'] = array( $response, affl_http_response( 200, array() ) );
			$client = affl_restrictions_client();
			affl_assert_true( $client->has_api_error( $client->get_restrictions( array( 1 ) ) ) );
			affl_assert_same( 1, count( $GLOBALS['affl_http_calls'] ) );
		}
	}
);
