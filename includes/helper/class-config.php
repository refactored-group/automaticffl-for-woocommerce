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
	const FFL_AMMO_ENABLED_CONFIG        = 'wc_ffl_ammo_enabled';

	/** Permanent Settings */
	const SETTING_GOOGLE_MAPS_URL            = 'https://maps.googleapis.com/maps/api/js';
	const SETTING_FFL_PRODUCTION_URL         = 'https://app.automaticffl.com/store-front/api';
	const SETTING_FFL_SANDBOX_URL            = 'https://app-stage.automaticffl.com/store-front/api';
	const SETTING_FFL_IFRAME_PRODUCTION_URL  = 'https://static.automaticffl.com/big-commerce-enhanced-checkout/index.html';
	const SETTING_FFL_IFRAME_SANDBOX_URL     = 'https://static-stage.automaticffl.com/big-commerce-enhanced-checkout/index.html';
	const SETTING_YES                        = 1;
	const SETTING_NO                         = 0;
	const RESTRICTIONS_CACHE_TTL             = 3600;

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
	 * Get FFL iframe map URL
	 *
	 * @since 1.0.13
	 *
	 * @return string
	 */
	public static function get_iframe_map_url() {
		if ( get_option( self::FFL_SANDBOX_MODE_CONFIG, true ) === "1" ) {
			return self::SETTING_FFL_IFRAME_SANDBOX_URL;
		}
		return self::SETTING_FFL_IFRAME_PRODUCTION_URL;
	}

	/**
	 * Get allowed origins for iframe postMessage validation
	 *
	 * @since 1.0.13
	 *
	 * @return array
	 */
	public static function get_iframe_allowed_origins() {
		return array(
			'https://static.automaticffl.com',
			'https://static-stage.automaticffl.com',
		);
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

		if ( $total_ffl > 0 && $total_ffl < $total_products && ! is_cart() ) {
			// Redirect back to the cart where the error message will be displayed.
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}
	}

	/**
	 * Check if the current checkout page is using WooCommerce Blocks
	 *
	 * @since 1.0.14
	 *
	 * @return bool
	 */
	public static function is_blocks_checkout() {
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) ) {
			return false;
		}

		return \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default();
	}

	/**
	 * Check if ammo features are enabled.
	 *
	 * @since 1.0.15
	 *
	 * @return bool
	 */
	public static function is_ammo_enabled(): bool {
		return get_option( self::FFL_AMMO_ENABLED_CONFIG, '0' ) === '1';
	}

	/**
	 * Get the restrictions API URL.
	 *
	 * @since 1.0.15
	 *
	 * @return string
	 */
	public static function get_restrictions_api_url(): string {
		return self::get_ffl_api_url() . '/stores/' . self::get_store_hash() . '/products/restrictions';
	}

	/**
	 * Check if the cart contains any FFL products
	 *
	 * @since 1.0.14
	 *
	 * @return bool
	 */
	public static function has_ffl_products() {
		if ( ! WC()->cart ) {
			return false;
		}

		$cart = WC()->cart->get_cart();
		foreach ( $cart as $product ) {
			$product_id = $product['product_id'];
			$ffl_required = get_post_meta( $product_id, '_ffl_required', true );

			if ( $ffl_required === 'yes' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register store credentials with the AutomaticFFL backend.
	 *
	 * Creates a WordPress Application Password and sends it to the backend
	 * so it can fetch product/category data from the WooCommerce REST API.
	 *
	 * @since 1.0.15
	 *
	 * @return void
	 */
	public static function register_with_backend() {
		$store_hash = get_option( self::FFL_STORE_HASH_CONFIG );
		if ( empty( $store_hash ) || $store_hash === '1' ) {
			return;
		}

		$credentials = Credentials::get_or_create_app_password();
		if ( is_wp_error( $credentials ) ) {
			error_log( 'AutomaticFFL: Failed to create credentials - ' . $credentials->get_error_message() );
			return;
		}

		$response = wp_remote_post(
			self::get_ffl_api_url() . '/stores/' . $store_hash . '/register',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'username' => $credentials['username'],
					'password' => $credentials['password'],
					'site_url' => get_site_url(),
					'platform' => 'woocommerce',
				) ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'AutomaticFFL: Registration request failed - ' . $response->get_error_message() );
			return;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code === 200 ) {
			update_option( 'automaticffl_registered_version', AFFL_VERSION );
		} else {
			error_log( 'AutomaticFFL: Registration failed with status ' . $status_code );
		}
	}

	/**
	 * Check if the cart contains a mix of FFL and non-FFL products
	 *
	 * @since 1.0.14
	 *
	 * @return bool
	 */
	public static function is_mixed_cart() {
		if ( ! WC()->cart ) {
			return false;
		}

		$cart = WC()->cart->get_cart();
		$total_products = count( $cart );
		$total_ffl = 0;

		foreach ( $cart as $product ) {
			$product_id = $product['product_id'];
			$ffl_required = get_post_meta( $product_id, '_ffl_required', true );

			if ( $ffl_required === 'yes' ) {
				$total_ffl++;
			}
		}

		// Mixed cart = has FFL products but not all products are FFL
		return $total_ffl > 0 && $total_ffl < $total_products;
	}
}
