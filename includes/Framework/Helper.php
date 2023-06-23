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
