<?php
/**
 * FFL for WooCommerce Plugin
 * @author    Refactored Group
 * @copyright Copyright (c) 2023
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

namespace RefactoredGroup\AutomaticFFL\Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Helper Class
 * The purpose of this class is to centralize common utility functions.
 */
class Helper {

	/** encoding used for mb_*() string functions */
	const MB_ENCODING = 'UTF-8';

	/**
	 * Returns true if the haystack string starts with needle
	 *
	 * Note: case-sensitive
	 *
	 * @since 1.0.0
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	public static function str_starts_with( $haystack, $needle ) {
		if ( self::multibyte_loaded() ) {
			if ( '' === $needle ) {
				return true;
			}
			return 0 === mb_strpos( $haystack, $needle, 0, self::MB_ENCODING );
		} else {
			$needle = self::str_to_ascii( $needle );
			if ( '' === $needle ) {
				return true;
			}
			return 0 === strpos( self::str_to_ascii( $haystack ), self::str_to_ascii( $needle ) );
		}
	}


	/**
	 * Return true if the haystack string ends with needle
	 *
	 * Note: case-sensitive
	 *
	 * @since 1.0.0
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	public static function str_ends_with( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}
		if ( self::multibyte_loaded() ) {
			return mb_substr( $haystack, -mb_strlen( $needle, self::MB_ENCODING ), null, self::MB_ENCODING ) === $needle;
		} else {
			$haystack = self::str_to_ascii( $haystack );
			$needle   = self::str_to_ascii( $needle );
			return substr( $haystack, -strlen( $needle ) ) === $needle;
		}
	}


	/**
	 * Returns true if the needle exists in haystack
	 *
	 * Note: case-sensitive
	 *
	 * @since 1.0.0
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	public static function str_exists( $haystack, $needle ) {
		if ( self::multibyte_loaded() ) {
			if ( '' === $needle ) {
				return false;
			}
			return false !== mb_strpos( $haystack, $needle, 0, self::MB_ENCODING );
		} else {
			$needle = self::str_to_ascii( $needle );
			if ( '' === $needle ) {
				return false;
			}
			return false !== strpos( self::str_to_ascii( $haystack ), self::str_to_ascii( $needle ) );
		}
	}


	/**
	 * Returns a string with all non-ASCII characters removed. This is useful
	 * for any string functions that expect only ASCII chars and can't
	 * safely handle UTF-8. Note this only allows ASCII chars in the range
	 * 33-126 (newlines/carriage returns are stripped)
	 *
	 * @since 1.0.0
	 * @param string $string string to make ASCII
	 * @return string
	 */
	public static function str_to_ascii( $string ) {
		// strip ASCII chars 32 and under
		$string = filter_var( $string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW );
		// strip ASCII chars 127 and higher
		return filter_var( $string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH );
	}


	/**
	 * Helper method to check if the multibyte extension is loaded, which
	 * indicates it's safe to use the mb_*() string methods
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	protected static function multibyte_loaded() {
		return extension_loaded( 'mbstring' );
	}


	/**
	 * Safely gets a value from $_POST.
	 *
	 * If the expected data is a string also trims it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key posted data key
	 * @param int|float|array|bool|null|string $default default data type to return (default empty string)
	 * @return int|float|array|bool|null|string posted data value if key found, or default
	 */
	public static function get_posted_value( $key, $default = '' ) {

		$value = $default;

		if ( isset( $_POST[ $key ] ) ) {
			$value = is_string( $_POST[ $key ] ) ? trim( $_POST[ $key ] ) : $_POST[ $key ];
		}

		return $value;
	}


	/**
	 * Safely gets a value from $_REQUEST.
	 *
	 * If the expected data is a string also trims it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key posted data key
	 * @param int|float|array|bool|null|string $default default data type to return (default empty string)
	 * @return int|float|array|bool|null|string posted data value if key found, or default
	 */
	public static function get_requested_value( $key, $default = '' ) {

		$value = $default;

		if ( isset( $_REQUEST[ $key ] ) ) {
			$value = is_string( $_REQUEST[ $key ] ) ? trim( $_REQUEST[ $key ] ) : $_REQUEST[ $key ];
		}

		return $value;
	}

	/**
	 * Get the count of notices added, either for all notices (default) or for one
	 * particular notice type specified by $notice_type.
	 *
	 * WC notice functions are not available in the admin
	 *
	 * @since 1.0.0
	 * @param string $notice_type The name of the notice type - either error, success or notice. [optional]
	 * @return int
	 */
	public static function wc_notice_count( $notice_type = '' ) {

		if ( function_exists( 'wc_notice_count' ) ) {
			return wc_notice_count( $notice_type );
		}

		return 0;
	}


	/**
	 * Add and store a notice.
	 *
	 * WC notice functions are not available in the admin
	 *
	 * @since 1.0.0
	 * @param string $message The text to display in the notice.
	 * @param string $notice_type The singular name of the notice type - either error, success or notice. [optional]
	 */
	public static function wc_add_notice( $message, $notice_type = 'success' ) {

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $notice_type );
		}
	}


	/**
	 * Print a single notice immediately
	 *
	 * WC notice functions are not available in the admin
	 *
	 * @since 1.0.0
	 * @param string $message The text to display in the notice.
	 * @param string $notice_type The singular name of the notice type - either error, success or notice. [optional]
	 */
	public static function wc_print_notice( $message, $notice_type = 'success' ) {

		if ( function_exists( 'wc_print_notice' ) ) {
			wc_print_notice( $message, $notice_type );
		}
	}


	/**
	 * Gets the current WordPress site name.
	 *
	 * This is helpful for retrieving the actual site name instead of the
	 * network name on multisite installations.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_site_name() {

		return ( is_multisite() ) ? get_blog_details()->blogname : get_bloginfo( 'name' );
	}

	/**
	 * Gets the WordPress current screen.
	 *
	 * @see get_current_screen() replacement which is always available, unlike the WordPress core function
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Screen|null
	 */
	public static function get_current_screen() {
		global $current_screen;

		return $current_screen ?: null;
	}


	/**
	 * Checks if the current screen matches a specified ID.
	 *
	 * This helps avoiding using the get_current_screen() function which is not always available,
	 * or setting the substitute global $current_screen every time a check needs to be performed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id id (or property) to compare
	 * @param string $prop optional property to compare, defaults to screen id
	 * @return bool
	 */
	public static function is_current_screen( $id, $prop = 'id' ) {
		global $current_screen;

		return isset( $current_screen->$prop ) && $id === $current_screen->$prop;
	}


	/**
	 * Determines if the current request is for a WC REST API endpoint.
	 *
	 * @see \WooCommerce::is_rest_api_request()
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_rest_api_request() {

		if ( is_callable( 'WC' ) && is_callable( [ WC(), 'is_rest_api_request' ] ) ) {
			return (bool) WC()->is_rest_api_request();
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) || ! function_exists( 'rest_get_url_prefix' ) ) {
			return false;
		}

		$rest_prefix         = trailingslashit( rest_get_url_prefix() );
		$is_rest_api_request = false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix );

		/* applies WooCommerce core filter */
		return (bool) apply_filters( 'woocommerce_is_rest_api_request', $is_rest_api_request );
	}


	/**
	 * Triggers a PHP error.
	 *
	 * This wrapper method ensures AJAX isn't broken in the process.
	 *
	 * @since 1.0.0
	 * @param string $message the error message
	 * @param int $type Optional. The error type. Defaults to E_USER_NOTICE
	 */
	public static function trigger_error( $message, $type = E_USER_NOTICE ) {

		if ( is_callable( 'is_ajax' ) && is_ajax() ) {

			switch ( $type ) {

				case E_USER_NOTICE:
					$prefix = 'Notice: ';
				break;

				case E_USER_WARNING:
					$prefix = 'Warning: ';
				break;

				default:
					$prefix = '';
			}

			error_log( $prefix . $message );

		} else {

			trigger_error( $message, $type );
		}
	}
}
