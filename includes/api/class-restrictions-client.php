<?php
/**
 * FFL Restrictions API Client
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Api;

defined( 'ABSPATH' ) || exit;

use RefactoredGroup\AutomaticFFL\Helper\Config;

/**
 * Restrictions_Client class
 *
 * Handles communication with the FFL Restrictions API to determine
 * product classifications (firearm, ammo, regular).
 *
 * @since 1.0.15
 */
class Restrictions_Client {

	/**
	 * Cache key prefix for restrictions.
	 *
	 * @var string
	 */
	const CACHE_KEY_PREFIX = 'wc_ffl_restrictions_';

	/**
	 * Cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_TTL = 3600;

	/**
	 * Cached restrictions data for the current request.
	 *
	 * @var array|null
	 */
	private $request_cache = null;

	/**
	 * Product IDs used for the current request cache.
	 *
	 * @var array
	 */
	private $cached_product_ids = array();

	/**
	 * Tracks whether the last API call was successful.
	 *
	 * @var bool|null
	 */
	private $api_available = null;

	/**
	 * Cache key for API availability status.
	 *
	 * @var string
	 */
	const API_STATUS_CACHE_KEY = 'wc_ffl_api_status';

	/**
	 * Fetch restrictions for given product IDs from API.
	 *
	 * @since 1.0.15
	 *
	 * @param array $product_ids Array of product IDs to check.
	 * @return array API response with product restrictions.
	 */
	public function get_restrictions( array $product_ids ): array {
		if ( empty( $product_ids ) ) {
			return array();
		}

		// Normalize and sort product IDs for consistent caching.
		$product_ids = array_map( 'intval', $product_ids );
		$product_ids = array_unique( $product_ids );
		sort( $product_ids );

		// Check request cache first.
		if ( $this->request_cache !== null && $this->cached_product_ids === $product_ids ) {
			return $this->request_cache;
		}

		// Check transient cache.
		$cache_key = $this->get_cache_key( $product_ids );
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			$this->request_cache = $cached;
			$this->cached_product_ids = $product_ids;
			return $cached;
		}

		// Fetch from API.
		$restrictions = $this->fetch_from_api( $product_ids );

		// Only cache successful responses — error responses should be retried on next request.
		if ( ! $this->has_api_error( $restrictions ) ) {
			set_transient( $cache_key, $restrictions, self::CACHE_TTL );
		}

		$this->request_cache = $restrictions;
		$this->cached_product_ids = $product_ids;

		return $restrictions;
	}

	/**
	 * Fetch restrictions from the API.
	 *
	 * @since 1.0.15
	 *
	 * @param array $product_ids Array of product IDs.
	 * @return array API response, or array with '_api_error' key on failure.
	 */
	private function fetch_from_api( array $product_ids ): array {
		$url = $this->build_api_url( $product_ids );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'AutomaticFFL: Restrictions API error - ' . $response->get_error_message() );
			$this->api_available = false;
			set_transient( self::API_STATUS_CACHE_KEY, 'unavailable', 300 ); // Cache for 5 minutes
			return array( '_api_error' => true );
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			error_log( 'AutomaticFFL: Restrictions API returned status ' . $status_code );
			$this->api_available = false;
			set_transient( self::API_STATUS_CACHE_KEY, 'unavailable', 300 );
			return array( '_api_error' => true );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			error_log( 'AutomaticFFL: Restrictions API returned invalid JSON' );
			$this->api_available = false;
			set_transient( self::API_STATUS_CACHE_KEY, 'unavailable', 300 );
			return array( '_api_error' => true );
		}

		$this->api_available = true;
		delete_transient( self::API_STATUS_CACHE_KEY );
		return $data;
	}

	/**
	 * Check if the API is available.
	 *
	 * @since 1.0.15
	 *
	 * @return bool True if API is available, false if unavailable or unknown.
	 */
	public function is_api_available(): bool {
		// If we've already checked in this request, use that result
		if ( null !== $this->api_available ) {
			return $this->api_available;
		}

		// Check transient cache for recent API status
		$cached_status = get_transient( self::API_STATUS_CACHE_KEY );
		if ( 'unavailable' === $cached_status ) {
			$this->api_available = false;
			return false;
		}

		// Default to true (assume available until proven otherwise)
		return true;
	}

	/**
	 * Check if the last API call resulted in an error.
	 *
	 * @since 1.0.15
	 *
	 * @param array $restrictions The restrictions array to check.
	 * @return bool True if there was an API error.
	 */
	public function has_api_error( array $restrictions ): bool {
		return isset( $restrictions['_api_error'] ) && true === $restrictions['_api_error'];
	}

	/**
	 * Build the API URL with product IDs.
	 *
	 * @since 1.0.15
	 *
	 * @param array $product_ids Array of product IDs.
	 * @return string API URL.
	 */
	private function build_api_url( array $product_ids ): string {
		$base_url = Config::get_ffl_api_url();
		$store_hash = Config::get_store_hash();

		$url = sprintf( '%s/stores/%s/products/restrictions', $base_url, $store_hash );

		// Build query string manually because WordPress's add_query_arg() does not
		// reliably handle PHP array-style parameters (product_ids[]=1&product_ids[]=2).
		$query_params = array();
		foreach ( $product_ids as $id ) {
			$query_params[] = 'product_ids[]=' . intval( $id );
		}

		return $url . '?' . implode( '&', $query_params );
	}

	/**
	 * Generate cache key for product IDs.
	 *
	 * @since 1.0.15
	 *
	 * @param array $product_ids Array of product IDs.
	 * @return string Cache key.
	 */
	private function get_cache_key( array $product_ids ): string {
		return self::CACHE_KEY_PREFIX . md5( implode( ',', $product_ids ) );
	}

	/**
	 * Check if product is a firearm (in API response, no conditions).
	 *
	 * @since 1.0.15
	 *
	 * @param int        $product_id   Product ID to check.
	 * @param array|null $restrictions Pre-fetched restrictions or null to fetch.
	 * @return bool True if product is a firearm.
	 */
	public function is_firearm( int $product_id, array $restrictions = null ): bool {
		if ( null === $restrictions ) {
			$restrictions = $this->get_restrictions( array( $product_id ) );
		}

		foreach ( $restrictions as $restriction ) {
			if ( isset( $restriction['id'] ) && intval( $restriction['id'] ) === $product_id ) {
				// Firearm: in response WITHOUT conditions key.
				return ! isset( $restriction['conditions'] );
			}
		}

		return false;
	}

	/**
	 * Check if product is ammo (in API response, has conditions).
	 *
	 * @since 1.0.15
	 *
	 * @param int        $product_id   Product ID to check.
	 * @param array|null $restrictions Pre-fetched restrictions or null to fetch.
	 * @return bool True if product is ammo.
	 */
	public function is_ammo( int $product_id, array $restrictions = null ): bool {
		if ( null === $restrictions ) {
			$restrictions = $this->get_restrictions( array( $product_id ) );
		}

		foreach ( $restrictions as $restriction ) {
			if ( isset( $restriction['id'] ) && intval( $restriction['id'] ) === $product_id ) {
				// Ammo: in response WITH conditions key.
				return isset( $restriction['conditions'] );
			}
		}

		return false;
	}

	/**
	 * Get restricted states for ammo product.
	 *
	 * @since 1.0.15
	 *
	 * @param int        $product_id   Product ID to check.
	 * @param array|null $restrictions Pre-fetched restrictions or null to fetch.
	 * @return array Array of state codes where FFL is required.
	 */
	public function get_restricted_states( int $product_id, array $restrictions = null ): array {
		if ( null === $restrictions ) {
			$restrictions = $this->get_restrictions( array( $product_id ) );
		}

		foreach ( $restrictions as $restriction ) {
			if ( isset( $restriction['id'] ) && intval( $restriction['id'] ) === $product_id ) {
				if ( isset( $restriction['conditions']['states'] ) && is_array( $restriction['conditions']['states'] ) ) {
					return $restriction['conditions']['states'];
				}
			}
		}

		return array();
	}

	/**
	 * Clear cached restrictions.
	 *
	 * @since 1.0.15
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->request_cache = null;
		$this->cached_product_ids = array();

		// Note: Transient cache will expire naturally.
		// For a full clear, we would need to track all cache keys.
	}

	/**
	 * Check if product is an FFL product (firearm or ammo).
	 *
	 * @since 1.0.15
	 *
	 * @param int        $product_id   Product ID to check.
	 * @param array|null $restrictions Pre-fetched restrictions or null to fetch.
	 * @return bool True if product requires FFL handling.
	 */
	public function is_ffl_product( int $product_id, array $restrictions = null ): bool {
		if ( null === $restrictions ) {
			$restrictions = $this->get_restrictions( array( $product_id ) );
		}

		foreach ( $restrictions as $restriction ) {
			if ( isset( $restriction['id'] ) && intval( $restriction['id'] ) === $product_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if product is a regular (non-FFL) product.
	 *
	 * @since 1.0.15
	 *
	 * @param int        $product_id   Product ID to check.
	 * @param array|null $restrictions Pre-fetched restrictions or null to fetch.
	 * @return bool True if product is regular (not in API response).
	 */
	public function is_regular_product( int $product_id, array $restrictions = null ): bool {
		return ! $this->is_ffl_product( $product_id, $restrictions );
	}
}
