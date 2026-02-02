<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Credentials class
 *
 * Manages WordPress Application Passwords for WooCommerce REST API access.
 * These credentials are sent to the AutomaticFFL backend so it can fetch
 * product and category data from the merchant's WooCommerce store.
 *
 * @since 1.0.14
 */
class Credentials {
	const APP_NAME       = 'AutomaticFFL Integration';
	const OPTION_KEY     = 'automaticffl_app_credentials';

	/**
	 * Get or create Application Password for current admin user.
	 * Uses WordPress's built-in Application Passwords (WP 5.6+).
	 *
	 * @since 1.0.14
	 *
	 * @return array|\WP_Error Array with 'username' and 'password' keys, or WP_Error on failure.
	 */
	public static function get_or_create_app_password() {
		$stored = get_option( self::OPTION_KEY );
		if ( $stored && is_array( $stored ) && ! empty( $stored['username'] ) && ! empty( $stored['password'] ) ) {
			return $stored;
		}

		$user_id = self::get_admin_user_id();
		if ( ! $user_id ) {
			return new \WP_Error( 'no_admin', 'No admin user available for Application Password creation.' );
		}

		if ( ! class_exists( '\WP_Application_Passwords' ) ) {
			return new \WP_Error( 'unsupported', 'WordPress Application Passwords are not available. WordPress 5.6+ is required.' );
		}

		// Delete existing app password if one exists, to regenerate.
		if ( \WP_Application_Passwords::application_name_exists_for_user( $user_id, self::APP_NAME ) ) {
			$passwords = \WP_Application_Passwords::get_user_application_passwords( $user_id );
			foreach ( $passwords as $pw ) {
				if ( $pw['name'] === self::APP_NAME ) {
					\WP_Application_Passwords::delete_application_password( $user_id, $pw['uuid'] );
					break;
				}
			}
		}

		// Create new Application Password.
		$result = \WP_Application_Passwords::create_new_application_password(
			$user_id,
			array(
				'name'   => self::APP_NAME,
				'app_id' => 'automaticffl-woocommerce',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$user        = get_user_by( 'id', $user_id );
		$credentials = array(
			'username' => $user->user_login,
			'password' => $result[0], // Plain password (only available at creation).
		);

		update_option( self::OPTION_KEY, $credentials );

		return $credentials;
	}

	/**
	 * Get an admin user ID suitable for Application Password creation.
	 *
	 * @since 1.0.14
	 *
	 * @return int|null User ID or null if none found.
	 */
	private static function get_admin_user_id() {
		$current = get_current_user_id();
		if ( $current && user_can( $current, 'manage_woocommerce' ) ) {
			return $current;
		}

		$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
		return $admins ? $admins[0]->ID : null;
	}
}
