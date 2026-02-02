<?php
/**
 * Centralized Messages Helper
 *
 * Provides all user-facing messages for FFL checkout in one place.
 * Used by both classic and blocks checkout to ensure consistency.
 *
 * @package AutomaticFFL
 * @since 1.0.15
 */

namespace RefactoredGroup\AutomaticFFL\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Class Messages
 *
 * Centralizes all user-facing checkout messages.
 */
class Messages {

	/**
	 * Get all messages as an array.
	 *
	 * Used to pass messages to JavaScript via wp_localize_script.
	 *
	 * @return array
	 */
	public static function get_all(): array {
		return array(
			// State selection prompts
			'ammoSelectState'           => self::ammo_select_state(),
			'ammoRegularSelectState'    => self::ammo_regular_select_state(),

			// State-based messages
			'fflRequiredForState'       => self::ffl_required_for_state(),
			'standardShippingAvailable' => self::standard_shipping_available(),

			// FFL selection messages
			'selectDealerBeforeOrder'   => self::select_dealer_before_order(),
			'selectDealerAmmoRequired'  => self::select_dealer_ammo_required(),
			'selectDealerBelow'         => self::select_dealer_below(),

			// Configuration errors
			'fflNotConfigured'          => self::ffl_not_configured(),
			'fflNotConfiguredContact'   => self::ffl_not_configured_contact(),

			// Validation messages
			'selectStateBeforeOrder'    => self::select_state_before_order(),
		);
	}

	/**
	 * Message: Prompt to select shipping state for ammo-only cart.
	 *
	 * @return string
	 */
	public static function ammo_select_state(): string {
		return __( 'You have ammunition in your cart. Please select your shipping state to determine shipping options.', 'automaticffl-for-wc' );
	}

	/**
	 * Message: Prompt to select state for ammo + regular mixed cart.
	 *
	 * @return string
	 */
	public static function ammo_regular_select_state(): string {
		return __( 'Your cart contains ammunition and regular products. Please select your shipping state to continue.', 'automaticffl-for-wc' );
	}

	/**
	 * Message: FFL is required for shipping to the selected state.
	 *
	 * @return string
	 */
	public static function ffl_required_for_state(): string {
		return __( 'FFL dealer selection is required for ammunition shipping to your state.', 'automaticffl-for-wc' );
	}

	/**
	 * Message: Standard shipping is available.
	 *
	 * @return string
	 */
	public static function standard_shipping_available(): string {
		return __( 'Standard shipping is available for ammunition to your state.', 'automaticffl-for-wc' );
	}

	/**
	 * Message: Generic prompt to select dealer before order.
	 *
	 * @return string
	 */
	public static function select_dealer_before_order(): string {
		return __( 'Please select an FFL dealer before placing your order.', 'automaticffl-for-wc' );
	}

	/**
	 * Message: Prompt to select dealer for ammo (state requires it).
	 *
	 * @return string
	 */
	public static function select_dealer_ammo_required(): string {
		return __( 'Please select an FFL dealer. Your state requires ammunition to be shipped to a licensed dealer.', 'automaticffl-for-wc' );
	}

	/**
	 * Message: FFL required with instruction to select dealer below.
	 *
	 * @return string
	 */
	public static function select_dealer_below(): string {
		return __( 'FFL dealer selection is required for ammunition shipping to your state. Please select a dealer below.', 'automaticffl-for-wc' );
	}

	/**
	 * Message: FFL not configured (short version).
	 *
	 * @return string
	 */
	public static function ffl_not_configured(): string {
		return __( 'FFL dealer selection is not configured. Please contact the site administrator.', 'automaticffl-for-wc' );
	}

	/**
	 * Message: FFL not configured with context that it's required.
	 *
	 * @return string
	 */
	public static function ffl_not_configured_contact(): string {
		return __( 'FFL dealer selection is required for ammunition shipping to your state, but is not configured. Please contact the site administrator.', 'automaticffl-for-wc' );
	}

	/**
	 * Message: Validation error - must select state before order.
	 *
	 * @return string
	 */
	public static function select_state_before_order(): string {
		return __( 'Please select a shipping state before placing your order.', 'automaticffl-for-wc' );
	}
}
