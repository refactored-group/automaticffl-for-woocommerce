<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Helper;

/**
 * Config class
 */
class Config {
	const FFL_STORE_HASH_CONFIG          = 'wc_ffl_store_hash';
	const FFL_SANDBOX_MODE_CONFIG        = 'wc_ffl_sandbox_mode';
	const FFL_GOOGLE_MAPS_API_KEY_CONFIG = 'wc_ffl_google_maps_api_key';

	/** Permanent Settings */
	const SETTING_GOOGLE_MAPS_URL    = 'https://maps.googleapis.com/maps/api/js';
	const SETTING_FFL_PRODUCTION_URL = 'https://app.automaticffl.com/store-front/api';
	const SETTING_FFL_SANDBOX_URL    = 'https://app-stage.automaticffl.com/store-front/api';
	const SETTING_YES                = 1;
	const SETTING_NO                 = 0;

	/**
	 * Get FFL API URL
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_ffl_api_url() {
		if ( get_option( self::FFL_SANDBOX_MODE_CONFIG, true ) === "1" ) {
			return self::SETTING_FFL_SANDBOX_URL;
		}
		return self::SETTING_FFL_PRODUCTION_URL;
	}

	/**
	 * Returns a bool of whether the current cart has only FFL products or not
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_ffl_cart() {
		$cart           = WC()->cart->get_cart();
		$total_products = count( $cart );
		$total_ffl      = 0;
		foreach ( $cart as $product ) {
			$product_id = $product['product_id'];
			$ffl_required = get_post_meta($product_id, '_ffl_required', true);
			
			if ( $ffl_required === 'yes' ) {
				$total_ffl++;
			}
		}

		if ( $total_products === $total_ffl ) {
			return true;
		}

		return false;
	}

	/**
	 * Get URL to retrieve a dealers list
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_ffl_dealers_url() {
		return sprintf( '%s/%s/%s', self::get_ffl_api_url(), self::get_store_hash(), 'dealers' );
	}


	/**
	 * Get the store URL
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_ffl_store_url() {
		return sprintf( '%s/%s/%s', self::get_ffl_api_url(), 'stores', self::get_store_hash() );
	}


	/**
	 * Get the store hash
	 *
	 * @since 1.0.0
	 *
	 * @return false|mixed|void
	 */
	public static function get_store_hash() {
		return get_option( self::FFL_STORE_HASH_CONFIG, true );
	}


	/**
	 * Get Google Maps API Key
	 *
	 * @since 1.0.0
	 *
	 * @return false|mixed|void
	 */
	public static function get_google_maps_api_key() {
		return get_option( self::FFL_GOOGLE_MAPS_API_KEY_CONFIG, true );
	}

	/**
	 * Get Google Maps API URL
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_google_maps_api_url() {
		return self::SETTING_GOOGLE_MAPS_URL;
	}

	/**
	 * Verifies if there are FFL products with regular products in the shopping cart.
	 * If there are any, redirects customer back to the Cart page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function verify_mixed_cart() {
		$cart           = WC()->cart->get_cart();
		$total_products = count( $cart );
		$total_ffl      = 0;
		foreach ( $cart as $product ) {
			$product_id = $product['product_id'];
			$ffl_required = get_post_meta($product_id, '_ffl_required', true);
			
			if ( $ffl_required === 'yes' ) {
				$total_ffl++;
			}
		}

		if ( $total_ffl > 0 && $total_ffl < $total_products ) {
			// Redirect back to the cart where the error message will be displayed.
			wp_safe_redirect( wc_get_cart_url() );
		}
	}
}
