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
	const SETTING_GOOGLE_MAPS_URL            = 'https://maps.googleapis.com/maps/api/js';
	const SETTING_FFL_PRODUCTION_URL         = 'https://app.automaticffl.com/store-front/api';
	const SETTING_FFL_SANDBOX_URL            = 'https://app-stage.automaticffl.com/store-front/api';
	const SETTING_FFL_IFRAME_PRODUCTION_URL  = 'https://static.automaticffl.com/big-commerce-enhanced-checkout/index.html';
	const SETTING_FFL_IFRAME_SANDBOX_URL     = 'https://static-stage.automaticffl.com/big-commerce-enhanced-checkout/index.html';
	const SETTING_YES                        = 1;
	const SETTING_NO                         = 0;

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
	 * Build iframe URL with query parameters.
	 *
	 * @since 1.0.14
	 *
	 * @return string|false Returns URL string on success, false if required data is missing.
	 */
	public static function build_iframe_url() {
		$store_hash   = self::get_store_hash();
		$maps_api_key = self::get_google_maps_api_key();

		if ( empty( $store_hash ) || empty( $maps_api_key ) ) {
			return false;
		}

		$params = array(
			'store_hash'   => $store_hash,
			'platform'     => 'WooCommerce',
			'maps_api_key' => $maps_api_key,
		);

		return add_query_arg( $params, self::get_iframe_map_url() );
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
	 * @return string
	 */
	public static function get_store_hash() {
		return get_option( self::FFL_STORE_HASH_CONFIG, '' );
	}


	/**
	 * Get Google Maps API Key
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_google_maps_api_key() {
		return get_option( self::FFL_GOOGLE_MAPS_API_KEY_CONFIG, '' );
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
	 * Get the restrictions API URL.
	 *
	 * @since 1.0.14
	 *
	 * @return string
	 */
	public static function get_restrictions_api_url(): string {
		return self::get_ffl_api_url() . '/stores/' . self::get_store_hash() . '/products/restrictions';
	}

	/**
	 * Register store credentials with the AutomaticFFL backend.
	 *
	 * Creates a WordPress Application Password and sends it to the backend
	 * so it can fetch product/category data from the WooCommerce REST API.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public static function register_with_backend() {
		$store_hash = self::get_store_hash();
		if ( empty( $store_hash ) ) {
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
	 * Build ATF EzCheck URL from FFL ID.
	 *
	 * FFL ID format: X-XX-XXX-XX-XXXXX or X-XX-XXX-XX-XX-XXXXX
	 * - licsRegn = 1st part (e.g., "5")
	 * - licsDis = 2nd part (e.g., "75")
	 * - licsSeq = last part (e.g., "23572")
	 *
	 * @since 1.0.14
	 *
	 * @param string $ffl_id The FFL license number.
	 * @return string The ATF EzCheck URL, or empty string if FFL ID is malformed.
	 */
	public static function build_ezcheck_url( $ffl_id ) {
		$parts = explode( '-', $ffl_id );
		if ( count( $parts ) < 5 ) {
			return '';
		}
		$lics_regn = $parts[0];
		$lics_dis  = $parts[1];
		$lics_seq  = end( $parts );
		return sprintf(
			'https://fflezcheck.atf.gov/FFLEzCheck/fflSearch?licsRegn=%s&licsDis=%s&licsSeq=%s',
			rawurlencode( $lics_regn ),
			rawurlencode( $lics_dis ),
			rawurlencode( $lics_seq )
		);
	}

	/**
	 * Build the FFL certificate URL from UUID.
	 *
	 * @since 1.0.14
	 *
	 * @param string $uuid The FFL UUID.
	 * @return string The certificate URL, or empty string if UUID is empty.
	 */
	public static function build_certificate_url( $uuid ) {
		if ( empty( $uuid ) ) {
			return '';
		}
		return 'https://certificate.automaticffl.com/' . rawurlencode( $uuid );
	}

	/**
	 * Build enhanced order note with FFL details.
	 *
	 * Shared between classic and blocks checkout paths.
	 *
	 * @since 1.0.14
	 *
	 * @param string $ffl_license     The FFL license number.
	 * @param string $expiration_date The FFL expiration date (optional).
	 * @param string $uuid            The FFL UUID for certificate link (optional).
	 * @return string The formatted order note.
	 */
	public static function build_enhanced_order_note( $ffl_license, $expiration_date = '', $uuid = '' ) {
		$note_lines = array();

		$note_lines[] = 'FFL License: ' . esc_html( $ffl_license );

		$ezcheck_url = self::build_ezcheck_url( $ffl_license );
		if ( ! empty( $ezcheck_url ) ) {
			$note_lines[] = 'ezCheck: <a href="' . esc_url( $ezcheck_url ) . '" target="_blank">' . esc_html( $ezcheck_url ) . '</a>';
		}

		if ( ! empty( $expiration_date ) ) {
			$note_lines[] = 'Expiration: ' . esc_html( $expiration_date );
		}

		$certificate_url = self::build_certificate_url( $uuid );
		if ( ! empty( $certificate_url ) ) {
			$note_lines[] = 'Certificate: <a href="' . esc_url( $certificate_url ) . '" target="_blank">' . esc_html( $certificate_url ) . '</a>';
		}

		return implode( "\n\n", $note_lines );
	}

}
