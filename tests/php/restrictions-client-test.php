<?php
/** Direct classification transport behavior. */

use RefactoredGroup\AutomaticFFL\Api\Restrictions_Client;

require_once dirname( __DIR__, 2 ) . '/includes/helper/class-config.php';
require_once dirname( __DIR__, 2 ) . '/includes/helper/class-product-facts.php';
require_once dirname( __DIR__, 2 ) . '/includes/api/class-restrictions-client.php';

/** Create a WordPress-style HTTP response. */
function affl_http_response( $status, $body, array $headers = array() ) {
	return array(
		'response' => array( 'code' => $status ),
		'body'     => is_string( $body ) ? $body : json_encode( $body ),
		'headers'  => $headers,
	);
}

/** Seed authoritative simple products. */
function affl_seed_products( $count ) {
	for ( $id = 1; $id <= $count; ++$id ) {
		$GLOBALS['affl_products'][ $id ] = new AFFL_Test_Product( $id );
	}
}

/** Create a client with empty request-level caches. */
function affl_restrictions_client( $clock = null ) {
	$GLOBALS['affl_options']['wc_ffl_store_hash']   = 'test-hash';
	$GLOBALS['affl_options']['wc_ffl_sandbox_mode'] = '0';
	$client = new Restrictions_Client( null, $clock );
	$client->clear_cache();
	return $client;
}

affl_test(
	'direct transport batches 100, 101, and 201 facts without omission',
	function () {
		foreach ( array( 100 => 1, 101 => 2, 201 => 3 ) as $count => $expected_calls ) {
			affl_reset_test_state();
			affl_seed_products( $count );
			$client = affl_restrictions_client();

			for ( $batch = 0; $batch < $expected_calls; ++$batch ) {
				$is_last = $batch === $expected_calls - 1;
				$id      = $is_last ? $count : ( $batch * 100 ) + 1;
				$GLOBALS['affl_http_queue'][] = affl_http_response(
					200,
					array(
						'schema_version' => 1,
						'restrictions'   => $is_last ? array( array( 'id' => $id ) ) : array(),
					)
				);
			}

			$result = $client->get_restrictions( range( 1, $count ) );
			affl_assert_same( array( array( 'id' => $count ) ), $result );
			affl_assert_same( $expected_calls, count( $GLOBALS['affl_http_calls'] ) );

			foreach ( $GLOBALS['affl_http_calls'] as $call ) {
				affl_assert_same( 'POST', $call['method'] );
				affl_assert_contains( '/stores/test-hash/products/classify', $call['url'] );
				$payload = json_decode( $call['args']['body'], true );
				affl_assert_true( count( $payload['products'] ) <= 100 );
			}
		}
	}
);

affl_test(
	'direct transport validates envelope, IDs, condition records, ordering, and duplicates',
	function () {
		affl_seed_products( 2 );
		$invalid_bodies = array(
			'not json',
			array(),
			array( 'schema_version' => 2, 'restrictions' => array() ),
			array( 'schema_version' => 1, 'restrictions' => (object) array() ),
			array( 'schema_version' => 1, 'restrictions' => array( array( 'id' => 3 ) ) ),
			array( 'schema_version' => 1, 'restrictions' => array( array( 'id' => '1' ) ) ),
			array( 'schema_version' => 1, 'restrictions' => array( array( 'id' => 1 ), array( 'id' => 1 ) ) ),
			array(
				'schema_version' => 1,
				'restrictions'   => array(
					array( 'id' => 1, 'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'CA' ) ) ) ),
					array( 'id' => 2 ),
				),
			),
			array(
				'schema_version' => 1,
				'restrictions'   => array(
					array( 'id' => 1, 'conditions' => array( array( 'type' => 'other', 'states' => array( 'CA' ) ) ) ),
				),
			),
		);

		foreach ( $invalid_bodies as $body ) {
			$GLOBALS['affl_http_calls'] = array();
			$GLOBALS['affl_http_queue'] = array( affl_http_response( 200, $body ) );
			$client = affl_restrictions_client();
			$result = $client->get_restrictions( array( 1, 2 ) );
			affl_assert_true( $client->has_api_error( $result ) );
		}
	}
);

affl_test(
	'direct transport rejects responses larger than 512 KiB',
	function () {
		affl_seed_products( 1 );
		$GLOBALS['affl_http_queue'][] = affl_http_response( 200, str_repeat( 'x', 524289 ) );
		$client = affl_restrictions_client();

		$result = $client->get_restrictions( array( 1 ) );
		affl_assert_true( $client->has_api_error( $result ) );
		affl_assert_same( 524289, $GLOBALS['affl_http_calls'][0]['args']['limit_response_size'] );
	}
);

affl_test(
	'direct transport uses one deadline and discards partial batch results',
	function () {
		affl_seed_products( 101 );
		$times = array( 100.0, 100.0, 114.5 );
		$clock = function () use ( &$times ) {
			return array_shift( $times );
		};
		$client = affl_restrictions_client( $clock );
		$GLOBALS['affl_http_queue'][] = affl_http_response( 200, array( 'schema_version' => 1, 'restrictions' => array( array( 'id' => 1 ) ) ) );
		$GLOBALS['affl_http_queue'][] = affl_http_response( 500, array( 'error' => 'ignored' ) );

		$result = $client->get_restrictions( range( 1, 101 ) );
		affl_assert_true( $client->has_api_error( $result ) );
		affl_assert_same( 2, count( $GLOBALS['affl_http_calls'] ) );
		affl_assert_true( $GLOBALS['affl_http_calls'][0]['args']['timeout'] <= 15.0 );
		affl_assert_true( $GLOBALS['affl_http_calls'][1]['args']['timeout'] <= 0.5 );
	}
);

affl_test(
	'direct transport cache keys include all facts and context and clear_cache resets them',
	function () {
		$GLOBALS['affl_products'][1] = new AFFL_Test_Product( 1, 0, array( 10 ), array( '_ffl_required' => 'no' ) );
		$client = affl_restrictions_client();
		$success = affl_http_response( 200, array( 'schema_version' => 1, 'restrictions' => array() ) );
		$GLOBALS['affl_http_queue'] = array( $success, $success, $success, $success );

		$client->get_restrictions( array( 1 ) );
		$client->get_restrictions( array( 1 ) );
		affl_assert_same( 1, count( $GLOBALS['affl_http_calls'] ) );

		$GLOBALS['affl_products'][1] = new AFFL_Test_Product( 1, 0, array( 20 ), array( '_ffl_required' => 'yes' ) );
		$client->get_restrictions( array( 1 ) );
		affl_assert_same( 2, count( $GLOBALS['affl_http_calls'] ) );

		$GLOBALS['affl_options']['wc_ffl_store_hash'] = 'other-hash';
		$client->get_restrictions( array( 1 ) );
		affl_assert_same( 3, count( $GLOBALS['affl_http_calls'] ) );

		$client->clear_cache();
		$client->get_restrictions( array( 1 ) );
		affl_assert_same( 4, count( $GLOBALS['affl_http_calls'] ) );
	}
);

affl_test(
	'empty carts return locally without transport or registration',
	function () {
		$client = affl_restrictions_client();
		affl_assert_same( array(), $client->get_restrictions( array() ) );
		affl_assert_same( array(), $GLOBALS['affl_http_calls'] );
	}
);
