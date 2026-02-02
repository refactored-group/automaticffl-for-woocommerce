<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Framework;

defined( 'ABSPATH' ) || exit;

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
	 * @param string                           $key posted data key.
	 * @param int|float|array|bool|null|string $default default data type to return (default empty string).
	 * @return int|float|array|bool|null|string posted data value if key found, or default.
	 */
	public static function get_posted_value( $key, $default = '' ) {
		$value = $default;
		if ( isset( $_POST['admin_nonce'] )
			&& ! empty( wp_kses_post( wp_unslash( $_POST['admin_nonce'] ) ) )
			&& wp_verify_nonce( wp_kses_post( wp_unslash( $_POST['admin_nonce'] ) ), 'admin_nonce' ) ) {
			if ( isset( $_POST[ $key ] ) ) {
				$post_value = wp_kses_post( wp_unslash( $_POST[ $key ] ) );
				$value      = is_string( $post_value ) ? trim( $post_value ) : $post_value;
			}
		} else {
			die( 'Nonce verification failed.' );
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
	 * @param string                           $key posted data key.
	 * @param int|float|array|bool|null|string $default default data type to return (default empty string).
	 * @return int|float|array|bool|null|string posted data value if key found, or default.
	 */
	public static function get_requested_value( $key, $default = '' ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$value = $default;

		// phpcs:ignore
		$requested_value = ! empty( $_GET[ $key ] ) ? wp_kses( wp_unslash( $_GET[ $key ] ), array() ) : $value;

		if ( isset( $requested_value ) ) {
			$value = is_string( $requested_value ) ? trim( $requested_value ) : $requested_value;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $value;
	}

}
