<?php
/**
 * FFL Restrictions API Client.
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Api;

defined( 'ABSPATH' ) || exit;

use RefactoredGroup\AutomaticFFL\Helper\Config;
use RefactoredGroup\AutomaticFFL\Helper\Product_Facts;

/** Classifies authoritative cart products through the Automatic FFL API. */
class Restrictions_Client {

	const TOTAL_TIMEOUT = 15.0;
	const BATCH_SIZE = 100;
	const MAX_RESPONSE_BYTES = 524288;
	const API_STATUS_CACHE_KEY = 'wc_ffl_api_status';

	/** @var array Complete request-local results keyed by context and facts. */
	private static $static_cache = array();

	/** @var array Complete results used by this instance. */
	private $request_cache = array();

	/** @var bool|null */
	private $api_available = null;

	/** @var Product_Facts */
	private $product_facts;

	/** @var callable|null */
	private $clock;

	/**
	 * @param Product_Facts|null $product_facts Fact collector override for tests.
	 * @param callable|null      $clock         Monotonic clock override for tests.
	 */
	public function __construct( $product_facts = null, $clock = null ) {
		$this->product_facts = $product_facts instanceof Product_Facts ? $product_facts : new Product_Facts();
		$this->clock         = is_callable( $clock ) ? $clock : null;
	}

	/**
	 * Fetch restrictions for cart product IDs.
	 *
	 * @param array $product_ids Cart product or variation IDs.
	 * @return array Existing restrictions-list shape or `_api_error` sentinel.
	 */
	public function get_restrictions( array $product_ids ): array {
		if ( empty( $product_ids ) ) {
			return array();
		}

		$product_ids = array_values( array_unique( array_map( 'intval', $product_ids ) ) );
		sort( $product_ids, SORT_NUMERIC );
		$facts = $this->product_facts->collect( $product_ids );

		if ( is_wp_error( $facts ) ) {
			return $this->api_error( 'invalid_product_facts' );
		}

		$cache_key = $this->cache_key( $facts );
		if ( array_key_exists( $cache_key, $this->request_cache ) ) {
			return $this->request_cache[ $cache_key ];
		}

		if ( array_key_exists( $cache_key, self::$static_cache ) ) {
			$this->request_cache[ $cache_key ] = self::$static_cache[ $cache_key ];
			return self::$static_cache[ $cache_key ];
		}

		$result = $this->fetch_direct( $facts, $this->now() + self::TOTAL_TIMEOUT );
		$this->request_cache[ $cache_key ] = $result;
		self::$static_cache[ $cache_key ]   = $result;

		return $result;
	}

	/**
	 * Probe only the direct checkout-classification capability.
	 *
	 * This deliberately bypasses cart caches and never falls back or attempts
	 * credential registration.
	 *
	 * @return array Sanitized connection result.
	 */
	public function probe_checkout_connection() {
		if ( '' === (string) Config::get_store_hash() ) {
			return $this->probe_result( 'needs_attention', 'missing_store_hash' );
		}

		$response = wp_remote_post(
			$this->classification_url(),
			array(
				'timeout'             => 5,
				'limit_response_size' => self::MAX_RESPONSE_BYTES + 1,
				'headers'              => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body'                 => wp_json_encode(
					array( 'schema_version' => 1, 'products' => array() )
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_code = (string) $response->get_error_code();
			$code       = false !== stripos( $error_code, 'timeout' ) ? 'timeout' : 'transport_error';
			return $this->probe_result( 'needs_attention', $code );
		}

		$status     = wp_remote_retrieve_response_code( $response );
		$request_id = $this->sanitize_request_id( wp_remote_retrieve_header( $response, 'x-request-id' ) );

		if ( 404 === $status || 405 === $status ) {
			return $this->probe_result( 'legacy_backend', 'route_unavailable', $status, $request_id );
		}

		if ( 200 !== $status ) {
			$code = in_array( $status, array( 401, 422 ), true ) ? 'eligibility_failure' : 'service_error';
			return $this->probe_result( 'needs_attention', $code, $status, $request_id );
		}

		$validated = $this->validate_http_response( $response, array(), true );
		if ( is_wp_error( $validated ) ) {
			$code = 'response_too_large' === $validated->get_error_code() ? 'response_too_large' : 'invalid_response';
			return $this->probe_result( 'needs_attention', $code, $status, $request_id );
		}

		return $this->probe_result( 'connected', null, $status, $request_id );
	}

	private function probe_result( $state, $code = null, $http_status = null, $request_id = null ) {
		return array(
			'state'       => $state,
			'code'        => $code,
			'http_status' => $http_status,
			'request_id'  => $request_id,
		);
	}

	private function sanitize_request_id( $request_id ) {
		return is_string( $request_id ) && strlen( $request_id ) <= 128 &&
			1 === preg_match( '/^[A-Za-z0-9_-]+$/', $request_id ) ? $request_id : null;
	}

	/** @return array Complete result or API error sentinel. */
	private function fetch_direct( array $facts, $deadline ) {
		$firearms = array();
		$ammo     = array();

		foreach ( array_chunk( $facts, self::BATCH_SIZE ) as $batch ) {
			$remaining = $deadline - $this->now();
			if ( $remaining <= 0 ) {
				return $this->api_error( 'timeout' );
			}

			$response = wp_remote_post(
				$this->classification_url(),
				array(
					'timeout'             => $remaining,
					'limit_response_size' => self::MAX_RESPONSE_BYTES + 1,
					'headers'              => array(
						'Accept'       => 'application/json',
						'Content-Type' => 'application/json',
					),
					'body'                 => wp_json_encode(
						array(
							'schema_version' => 1,
							'products'       => $batch,
						)
					),
				)
			);

			$validated = $this->validate_http_response( $response, $batch, true );
			if ( is_wp_error( $validated ) ) {
				if ( in_array( $validated->get_error_code(), array( 'http_404', 'http_405' ), true ) ) {
					return $this->fetch_legacy( $facts, $deadline );
				}
				return $this->api_error( $validated->get_error_code() );
			}

			foreach ( $validated as $restriction ) {
				if ( isset( $restriction['conditions'] ) ) {
					$ammo[] = $restriction;
				} else {
					$firearms[] = $restriction;
				}
			}
		}

		$this->api_available = true;
		delete_transient( self::API_STATUS_CACHE_KEY );
		return array_merge( $firearms, $ammo );
	}

	/**
	 * Restart the complete operation through the old credential-dependent route.
	 *
	 * @return array Complete result or API error sentinel.
	 */
	private function fetch_legacy( array $facts, $deadline ) {
		$firearms = array();
		$ammo     = array();

		foreach ( array_chunk( $facts, self::BATCH_SIZE ) as $batch ) {
			$remaining = $deadline - $this->now();
			if ( $remaining <= 0 ) {
				return $this->api_error( 'timeout' );
			}

			$response = wp_remote_get(
				$this->legacy_url( $batch ),
				array(
					'timeout'             => $remaining,
					'limit_response_size' => self::MAX_RESPONSE_BYTES + 1,
					'headers'              => array( 'Accept' => 'application/json' ),
				)
			);

			$validated = $this->validate_http_response( $response, $batch, false );
			if ( is_wp_error( $validated ) ) {
				return $this->api_error( $validated->get_error_code() );
			}

			foreach ( $validated as $restriction ) {
				if ( isset( $restriction['conditions'] ) ) {
					$ammo[] = $restriction;
				} else {
					$firearms[] = $restriction;
				}
			}
		}

		$this->api_available = true;
		delete_transient( self::API_STATUS_CACHE_KEY );
		return array_merge( $firearms, $ammo );
	}

	/** @return array|\WP_Error */
	private function validate_http_response( $response, array $submitted_facts, $enveloped ) {
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'transport_error' );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return new \WP_Error( 'http_' . (int) $status );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_RESPONSE_BYTES ) {
			return new \WP_Error( 'response_too_large' );
		}

		$decoded = json_decode( $body );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error( 'invalid_response' );
		}

		if ( $enveloped ) {
			if ( ! is_object( $decoded ) || ! isset( $decoded->schema_version ) ||
				1 !== $decoded->schema_version || ! property_exists( $decoded, 'restrictions' ) ||
				! is_array( $decoded->restrictions ) ) {
				return new \WP_Error( 'invalid_response' );
			}
			$records = $decoded->restrictions;
		} else {
			if ( ! is_array( $decoded ) ) {
				return new \WP_Error( 'invalid_response' );
			}
			$records = $decoded;
		}

		$submitted_ids = array();
		foreach ( $submitted_facts as $fact ) {
			$submitted_ids[ (string) $fact['id'] ] = true;
		}

		return $this->validate_restriction_records( $records, $submitted_ids );
	}

	/** @return array|\WP_Error */
	private function validate_restriction_records( array $records, array $submitted_ids ) {
		$normalized = array();
		$seen       = array();
		$saw_ammo   = false;

		foreach ( $records as $record ) {
			if ( ! is_object( $record ) || ! property_exists( $record, 'id' ) ||
				! is_int( $record->id ) || ! isset( $submitted_ids[ (string) $record->id ] ) ) {
				return new \WP_Error( 'invalid_response' );
			}

			$keys = array_keys( get_object_vars( $record ) );
			sort( $keys, SORT_STRING );

			if ( array( 'id' ) === $keys ) {
				if ( $saw_ammo ) {
					return new \WP_Error( 'invalid_response' );
				}
				$kind              = 'firearm';
				$normalized_record = array( 'id' => $record->id );
			} elseif ( array( 'conditions', 'id' ) === $keys ) {
				$condition = $this->validate_condition( $record->conditions );
				if ( is_wp_error( $condition ) ) {
					return $condition;
				}
				$saw_ammo          = true;
				$kind              = 'ammo';
				$normalized_record = array(
					'id'         => $record->id,
					'conditions' => array( $condition ),
				);
			} else {
				return new \WP_Error( 'invalid_response' );
			}

			$seen_key = $kind . ':' . $record->id;
			if ( isset( $seen[ $seen_key ] ) ) {
				return new \WP_Error( 'invalid_response' );
			}
			$seen[ $seen_key ] = true;
			$normalized[]      = $normalized_record;
		}

		return $normalized;
	}

	/** @return array|\WP_Error */
	private function validate_condition( $conditions ) {
		if ( ! is_array( $conditions ) || 1 !== count( $conditions ) || ! is_object( $conditions[0] ) ) {
			return new \WP_Error( 'invalid_response' );
		}

		$condition = $conditions[0];
		$keys      = array_keys( get_object_vars( $condition ) );
		sort( $keys, SORT_STRING );

		if ( array( 'states', 'type' ) !== $keys || 'ship_state' !== $condition->type ||
			! is_array( $condition->states ) ) {
			return new \WP_Error( 'invalid_response' );
		}

		$states = array();
		foreach ( $condition->states as $state ) {
			if ( ! is_string( $state ) || 1 !== preg_match( '/^[A-Z]{2}$/', $state ) ) {
				return new \WP_Error( 'invalid_response' );
			}
			$states[] = $state;
		}

		return array( 'type' => 'ship_state', 'states' => $states );
	}

	private function cache_key( array $facts ) {
		return hash(
			'sha256',
			wp_json_encode(
				array(
					'api_base'       => Config::get_ffl_api_url(),
					'store_hash'     => Config::get_store_hash(),
					'schema_version' => 1,
					'products'       => $facts,
				)
			)
		);
	}

	private function classification_url() {
		return sprintf(
			'%s/stores/%s/products/classify',
			Config::get_ffl_api_url(),
			rawurlencode( Config::get_store_hash() )
		);
	}

	/** @param array $facts Legacy batch facts. */
	private function legacy_url( array $facts ) {
		$query = array();
		foreach ( $facts as $fact ) {
			$query[] = 'product_ids[]=' . (int) $fact['id'];
		}

		return sprintf(
			'%s/stores/%s/products/restrictions?%s',
			Config::get_ffl_api_url(),
			rawurlencode( Config::get_store_hash() ),
			implode( '&', $query )
		);
	}

	private function now() {
		return $this->clock ? (float) call_user_func( $this->clock ) : microtime( true );
	}

	private function api_error( $code ) {
		$this->api_available = false;
		set_transient( self::API_STATUS_CACHE_KEY, 'unavailable', 300 );
		error_log( 'AutomaticFFL: Restrictions API classification failed (' . sanitize_text_field( $code ) . ')' );
		return array( '_api_error' => true );
	}

	public function has_api_error( array $restrictions ): bool {
		return isset( $restrictions['_api_error'] ) && true === $restrictions['_api_error'];
	}

	public function clear_cache(): void {
		$this->request_cache = array();
		self::$static_cache  = array();
	}
}
