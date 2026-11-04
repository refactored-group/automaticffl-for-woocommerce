<?php
/**
 * Native ammunition destination edit helpers.
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes the narrow Checkout Blocks edit contract.
 */
class Ammo_Destination_Edit {

	/**
	 * Active checkout fields cleared after WooCommerce opens native Edit.
	 *
	 * @return string[]
	 */
	public static function physical_fields() {
		return array( 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone' );
	}

	/**
	 * Whether native shipping Edit should start a fresh ammo destination.
	 *
	 * @param Cart_Analyzer|null $analyzer Existing analyzer when available.
	 * @return bool
	 */
	public static function is_eligible( $analyzer = null ) {
		if ( ! is_user_logged_in() || 'billing_only' === get_option( 'woocommerce_ship_to_destination' ) ) {
			return false;
		}

		$analyzer = $analyzer ?: new Cart_Analyzer();

		return ! $analyzer->has_api_error() && $analyzer->has_ammo() && ! $analyzer->has_firearms();
	}

	/**
	 * Build country-aware required-field data for Checkout Blocks.
	 *
	 * @param Cart_Analyzer|null $analyzer Existing analyzer when available.
	 * @return array
	 */
	public static function get_script_data( $analyzer = null ) {
		if ( ! self::is_eligible( $analyzer ) ) {
			return array( 'required' => false );
		}

		$prefix         = 'shipping_';
		$allowed_fields = array_merge( array( 'first_name', 'last_name', 'country' ), self::physical_fields() );
		$required       = array();
		$countries      = WC()->countries->get_shipping_countries() + WC()->countries->get_allowed_countries();

		foreach ( array_keys( $countries ) as $country ) {
			$required[ $country ] = array();
			foreach ( WC()->countries->get_address_fields( $country, $prefix ) as $key => $field ) {
				$name = substr( $key, strlen( $prefix ) );
				if ( ! empty( $field['required'] ) && empty( $field['hidden'] ) && in_array( $name, $allowed_fields, true ) ) {
					$required[ $country ][] = $name;
				}
			}
		}

		return array(
			'required'       => true,
			'customerId'     => get_current_user_id(),
			'physicalFields' => self::physical_fields(),
			'requiredFields' => $required,
		);
	}
}
