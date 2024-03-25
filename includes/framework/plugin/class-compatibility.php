<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Framework\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Compatibility.
 */
class Compatibility {

	/**
	 * Retrieves a list of the latest available WooCommerce versions.
	 *
	 * Excludes betas, release candidates and development versions.
	 * Versions are sorted from most recent to least recent.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] array of semver strings.
	 */
	public static function get_latest_wc_versions() {
		$latest_wc_versions = get_transient( 'affl_wc_versions' );
		if ( ! is_array( $latest_wc_versions ) ) {
			$wp_org_request = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/woocommerce.json', array( 'timeout' => 1 ) );
			if ( is_array( $wp_org_request ) && isset( $wp_org_request['body'] ) ) {
				$plugin_info = json_decode( $wp_org_request['body'], true );
				if ( is_array( $plugin_info ) && ! empty( $plugin_info['versions'] ) && is_array( $plugin_info['versions'] ) ) {
					$latest_wc_versions = array();
					// reverse array as WordPress supplies oldest version first, newest last.
					foreach ( array_keys( array_reverse( $plugin_info['versions'] ) ) as $wc_version ) {
						// skip trunk, release candidates, betas and other non-final or irregular versions.
						if (
							is_string( $wc_version )
							&& '' !== $wc_version
							&& is_numeric( $wc_version[0] )
							&& false === strpos( $wc_version, '-' )
						) {
							$latest_wc_versions[] = $wc_version;
						}
					}
					set_transient( 'affl_wc_versions', $latest_wc_versions, WEEK_IN_SECONDS );
				}
			}
		}
		return is_array( $latest_wc_versions ) ? $latest_wc_versions : array();
	}

	/**
	 * Gets the version of the currently installed WooCommerce.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null Woocommerce version number or null if undetermined
	 */
	public static function get_wc_version() {

		return defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
	}


	/**
	 * Determines if the installed version of WooCommerce is equal or greater than a given version.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version version number to compare.
	 * @return bool
	 */
	public static function is_wc_version_gte( $version ) {

		$wc_version = self::get_wc_version();

		return $wc_version && version_compare( $wc_version, $version, '>=' );
	}


	/**
	 * Determines whether the enhanced admin is available.
	 *
	 * This checks both for WooCommerce v4.0+ and the underlying package availability.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_enhanced_admin_available() {
		return self::is_wc_version_gte( '4.0' ) && function_exists( 'wc_admin_url' );
	}


	/**
	 * Converts a shorthand byte value to an integer byte value.
	 *
	 * Wrapper for wp_convert_hr_to_bytes(), moved to load.php in WordPress 4.6 from media.php
	 *
	 * Based on ActionScheduler's compat wrapper for the same function:
	 * ActionScheduler_Compatibility::convert_hr_to_bytes()
	 *
	 * @link https://secure.php.net/manual/en/function.ini-get.php
	 * @link https://secure.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
	 *
	 * @since 1.0.0
	 *
	 * @param string $value A (PHP ini) byte value, either shorthand or ordinary.
	 * @return int An integer byte value.
	 */
	public static function convert_hr_to_bytes( $value ) {

		if ( function_exists( 'wp_convert_hr_to_bytes' ) ) {

			return wp_convert_hr_to_bytes( $value );
		}

		$value = strtolower( trim( $value ) );
		$bytes = (int) $value;

		if ( false !== strpos( $value, 'g' ) ) {

			$bytes *= GB_IN_BYTES;

		} elseif ( false !== strpos( $value, 'm' ) ) {

			$bytes *= MB_IN_BYTES;

		} elseif ( false !== strpos( $value, 'k' ) ) {

			$bytes *= KB_IN_BYTES;
		}

		// deal with large (float) values which run into the maximum integer size.
		return min( $bytes, PHP_INT_MAX );
	}
}
