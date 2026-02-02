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
 * @since 1.0.14
 */
class Restrictions_Client {

	/**
	 * Static request-level cache shared across all instances.
	 *
	 * Prevents duplicate API calls when multiple Cart_Analyzer instances
	 * are created within a single page load (e.g., Plugin, Cart, Checkout
	 * each create their own). Only lives for the duration of the PHP request.
	 *
	 * @var array|null
	 */
	private static $static_cache = null;

	/**
	 * Product IDs for the static cache.
	 *
	 * @var array
	 */
	private static $static_cache_ids = array();

	/**
	 * Cached restrictions data for this instance.
	 *
	 * @var array|null
	 */
	private $request_cache = null;

	/**
	 * Product IDs used for this instance's cache.
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
	 * @since 1.0.14
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

		// Check instance cache first.
		if ( $this->request_cache !== null && $this->cached_product_ids === $product_ids ) {
			return $this->request_cache;
		}

		// Check static cache (shared across all instances within this request).
		if ( self::$static_cache !== null && self::$static_cache_ids === $product_ids ) {
			$this->request_cache      = self::$static_cache;
			$this->cached_product_ids = $product_ids;
			return self::$static_cache;
		}

		// Fetch fresh data from API on every page load so category changes are reflected immediately.
		$restrictions = $this->fetch_from_api( $product_ids );

		// Store in both instance and static caches.
		$this->request_cache      = $restrictions;
		$this->cached_product_ids = $product_ids;
		self::$static_cache       = $restrictions;
		self::$static_cache_ids   = $product_ids;

		return $restrictions;
	}

	/**
	 * Fetch restrictions from the API.
	 *
	 * @since 1.0.14
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
	 * Check if the last API call resulted in an error.
	 *
	 * @since 1.0.14
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
	 * @since 1.0.14
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
	 * Clear cached restrictions for the current request.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->request_cache      = null;
		$this->cached_product_ids = array();
		self::$static_cache       = null;
		self::$static_cache_ids   = array();
	}
}
