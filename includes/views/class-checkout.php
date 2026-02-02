<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Views;

defined( 'ABSPATH' ) || exit;

use RefactoredGroup\AutomaticFFL\Helper\Config;
use RefactoredGroup\AutomaticFFL\Helper\Cart_Analyzer;
use RefactoredGroup\AutomaticFFL\Helper\Messages;
use RefactoredGroup\AutomaticFFL\Helper\US_States;

/**
 * Class Checkout
 *
 * @since 1.0.0
 */
class Checkout {

	/**
	 * Shared Cart_Analyzer instance for the current request.
	 *
	 * @var Cart_Analyzer|null
	 */
	private static $analyzer = null;

	/**
	 * Get shared Cart_Analyzer instance (avoids duplicate API calls per request).
	 *
	 * @since 1.0.15
	 *
	 * @return Cart_Analyzer
	 */
	private static function get_analyzer(): Cart_Analyzer {
		if ( null === self::$analyzer ) {
			self::$analyzer = new Cart_Analyzer();
		}
		return self::$analyzer;
	}

	/**
	 * Check if the current cart needs FFL checkout handling.
	 *
	 * Uses Cart_Analyzer to detect firearms and ammo products,
	 * replacing the old Config::is_ffl_cart() which only checked post meta.
	 *
	 * @since 1.0.15
	 *
	 * @return bool True if FFL fields should be added to checkout.
	 */
	private static function needs_ffl_checkout(): bool {
		$analyzer = self::get_analyzer();

		if ( $analyzer->has_api_error() ) {
			return false;
		}

		if ( $analyzer->is_mixed_ffl_regular() ) {
			return false;
		}

		if ( $analyzer->has_firearms() ) {
			return true;
		}

		if ( $analyzer->is_ammo_only() ) {
			return true;
		}

		return false;
	}

	/**
	 * Verifies if there are FFL products with regular products in the shopping cart.
	 * If there are any, redirects customer back to the Cart page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function verify_mixed_cart() {
		if ( is_admin() ) {
			return;
		}

		$analyzer = self::get_analyzer();

		// If API is unavailable, skip mixed cart check - allow normal checkout.
		if ( $analyzer->has_api_error() ) {
			return;
		}

		// Skip if on cart page.
		if ( is_cart() ) {
			return;
		}

		// Firearms + regular = always redirect to cart.
		if ( $analyzer->is_firearms_regular_mixed() ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit();
		}

		// Ammo + regular = check session state.
		if ( $analyzer->is_ammo_regular_mixed() ) {
			$selected_state = WC()->session ? WC()->session->get( 'automaticffl_ammo_state', '' ) : '';

			// No state selected = redirect to cart.
			if ( empty( $selected_state ) ) {
				wc_add_notice( __( 'Please select your shipping state on the cart page.', 'automaticffl-for-wc' ), 'error' );
				wp_safe_redirect( wc_get_cart_url() );
				exit();
			}

			// Restricted state = redirect to cart (inline notice already shown there).
			if ( $analyzer->requires_ffl_for_state( $selected_state ) ) {
				wp_safe_redirect( wc_get_cart_url() );
				exit();
			}

			// Unrestricted state = allow checkout (fall through).
		}
	}

	public static function add_automaticffl_checkout_field($checkout) {
		if ( self::needs_ffl_checkout() ) {
			$analyzer = self::get_analyzer();
			woocommerce_form_field('ffl_license_field', array(
				'type' => 'text',
				'class' => array('hidden'),
				'label' => __('FFL License', 'automaticffl-for-wc'),
				'placeholder' => __('FFL License', 'automaticffl-for-wc'),
				'required' => $analyzer->has_firearms(),
			), $checkout->get_value('ffl_license_field'));

			// Hidden field for FFL expiration date.
			woocommerce_form_field('ffl_expiration_date', array(
				'type' => 'text',
				'class' => array('hidden'),
				'label' => __('FFL Expiration Date', 'automaticffl-for-wc'),
				'required' => false,
			), $checkout->get_value('ffl_expiration_date'));

			// Hidden field for FFL UUID (for certificate link).
			woocommerce_form_field('ffl_uuid', array(
				'type' => 'text',
				'class' => array('hidden'),
				'label' => __('FFL UUID', 'automaticffl-for-wc'),
				'required' => false,
			), $checkout->get_value('ffl_uuid'));
		}
	}

	public static function save_automaticffl_checkout_field_value($order_id) {
		// Verify WooCommerce checkout nonce (defense-in-depth; WC_Checkout::process_checkout() also checks).
		if ( ! isset( $_POST['woocommerce-process-checkout-nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) ), 'woocommerce-process_checkout' )
		) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$updated = false;

		if ( ! empty( $_POST['ffl_license_field'] ) ) {
			$order->update_meta_data( '_ffl_license_field', sanitize_text_field( wp_unslash( $_POST['ffl_license_field'] ) ) );
			$updated = true;
		}
		if ( ! empty( $_POST['ffl_expiration_date'] ) ) {
			$order->update_meta_data( '_ffl_expiration_date', sanitize_text_field( wp_unslash( $_POST['ffl_expiration_date'] ) ) );
			$updated = true;
		}
		if ( ! empty( $_POST['ffl_uuid'] ) ) {
			$order->update_meta_data( '_ffl_uuid', sanitize_text_field( wp_unslash( $_POST['ffl_uuid'] ) ) );
			$updated = true;
		}

		if ( $updated ) {
			$order->save();
		}
	}

	public static function after_checkout_create_order($order_id) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$ffl_license = $order->get_meta( '_ffl_license_field' );

		if ( ! empty( $ffl_license ) ) {
			$note = Config::build_enhanced_order_note(
				$ffl_license,
				$order->get_meta( '_ffl_expiration_date' ),
				$order->get_meta( '_ffl_uuid' )
			);
			$order->add_order_note( $note );
			$order->save();
		}
	}

	public static function automaticffl_custom_fields( $fields ) {
		if ( self::needs_ffl_checkout() ) {
			$analyzer = self::get_analyzer();
			// Only replace shipping_phone with hidden "Dealer Phone" for firearms carts.
			// Ammo-only carts keep the standard shipping form (including phone) visible
			// so the state field can drive FFL requirement detection.
			if ( $analyzer->has_firearms() ) {
				$fields['shipping']['shipping_phone'] = array(
					'label'       => __('Dealer Phone', 'automaticffl-for-wc'),
					'placeholder' => _x('Dealer Phone', 'placeholder', 'automaticffl-for-wc'),
					'required'    => true,
					'class'       => array('hidden'),
					'clear'       => true,
				);
			}
		}
		return $fields;
	}

	/**
	 * Check if there's a logged-in user and returns it's First and Last name.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_user_name() {
		if(is_user_logged_in()){
			$current_user = wp_get_current_user();
			$first_name = $current_user->first_name;
			$last_name = $current_user->last_name;

			if( ! empty($first_name) && ! empty($last_name)){
				return array('first_name' => $first_name, 'last_name' => $last_name);
			}
		}
		return array( 'first_name' => 'FFL', 'last_name' => 'Dealer');
	}

	/**
	 * Build iframe URL with query parameters.
	 *
	 * @since 1.0.13
	 *
	 * @return string|false Returns URL string on success, false if required data is missing.
	 */
	private static function build_iframe_url() {
		return Config::build_iframe_url();
	}

	/**
	 * Used by the Hook to return the map when a FFL cart is loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function get_ffl() {
		$analyzer = self::get_analyzer();

		// Check if API is available - if not, show unavailable notice and allow normal checkout.
		if ( $analyzer->has_api_error() ) {
			self::get_api_unavailable_notice();
			return;
		}

		// No FFL products at all.
		if ( ! $analyzer->has_firearms() && ! $analyzer->has_ammo() ) {
			return;
		}

		// Mixed cart with regular products should not proceed.
		if ( $analyzer->is_mixed_ffl_regular() ) {
			return;
		}

		// Firearms present (with or without ammo) - always show FFL selection.
		if ( $analyzer->has_firearms() ) {
			self::get_js();
			self::get_map();
			self::disable_enter_key();
			return;
		}

		// Ammo only - show state selector.
		if ( $analyzer->is_ammo_only() ) {
			self::get_ammo_checkout( $analyzer );
			return;
		}
	}

	/**
	 * Display notice when the Automatic FFL API is unavailable.
	 * Allows customer to proceed with normal checkout.
	 *
	 * @since 1.0.15
	 *
	 * @return void
	 */
	private static function get_api_unavailable_notice() {
		?>
		<div id="automaticffl-unavailable-notice" class="automaticffl-unavailable-notice">
			<div class="woocommerce">
				<div class="woocommerce-info automaticffl-unavailable-message" role="alert">
					<p><strong><?php esc_html_e( 'Automatic FFL Unavailable', 'automaticffl-for-wc' ); ?></strong></p>
					<p><?php esc_html_e( 'Please contact our store after placing an order.', 'automaticffl-for-wc' ); ?></p>
					<button type="button" id="automaticffl-unavailable-ok" class="button alt wp-element-button">
						<?php esc_html_e( 'OK', 'automaticffl-for-wc' ); ?>
					</button>
				</div>
			</div>
		</div>
		<script>
			jQuery(document).ready(function($) {
				$('#automaticffl-unavailable-ok').on('click', function() {
					$('#automaticffl-unavailable-notice').slideUp();
				});
			});
		</script>
		<?php
	}

	/**
	 * Disable enter key during the checkout to prevent the order
	 * from being submitted before a dealer is selected
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function disable_enter_key() {
		?>
		<script>
			jQuery(document).ready(function($) {
				$('form').keypress(function(e) {
					//Enter key
					if (e.which == 13) {
						return false;
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Get FFL map.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function get_map() {
		$user_name  = self::get_user_name();
		$iframe_url = self::build_iframe_url();

		// If configuration is invalid, show error message.
		if ( false === $iframe_url ) {
			?>
			<div class="woocommerce">
				<div class="woocommerce-error" role="alert">
					<?php echo esc_html__( 'FFL dealer selection is not configured. Please contact the site administrator.', 'automaticffl-for-wc' ); ?>
				</div>
			</div>
			<?php
			return;
		}

		// Include the template.
		include AFFL_TEMPLATES_PATH . 'checkout/ffl-map.php';
	}

	/**
	 * Get Javascript that handles the iframe postMessage communication
	 *
	 * @return void
	 * @since 1.0.13
	 */
	public static function get_js() {
		$user_name       = self::get_user_name();
		$allowed_origins = Config::get_iframe_allowed_origins();

		// Include the template.
		include AFFL_TEMPLATES_PATH . 'checkout/ffl-map-js.php';
	}

	/**
	 * Display ammo-only checkout with state selector.
	 *
	 * @since 1.0.15
	 *
	 * @param Cart_Analyzer $analyzer Cart analyzer instance.
	 * @return void
	 */
	private static function get_ammo_checkout( Cart_Analyzer $analyzer ) {
		$restricted_states = $analyzer->get_ammo_restricted_states();
		$user_name         = self::get_user_name();
		$iframe_url        = self::build_iframe_url();
		$us_states         = US_States::get_all();
		$messages          = Messages::get_all();

		// Check if FFL is configured for when we need to show the dealer selection.
		$is_configured = ( false !== $iframe_url );

		// Include the template.
		include AFFL_TEMPLATES_PATH . 'checkout/ammo-state-selector.php';

		self::get_ammo_js( $restricted_states );

		if ( $is_configured ) {
			self::get_js();
		}
	}

	/**
	 * Output JavaScript for ammo state detection.
	 *
	 * Watches the WooCommerce shipping state field and controls UI:
	 * - No state selected: Show info message, shipping form visible
	 * - Unrestricted state: Show success message, shipping form visible
	 * - Restricted state: Hide shipping fields, show FFL dealer selection
	 *
	 * @since 1.0.15
	 *
	 * @param array $restricted_states Array of state codes where FFL is required.
	 * @return void
	 */
	private static function get_ammo_js( array $restricted_states ) {
		$messages = Messages::get_all();
		// Include the template.
		include AFFL_TEMPLATES_PATH . 'checkout/ammo-state-selector-js.php';
	}

	/**
	 * Display reactive ammo + regular notice on classic checkout.
	 *
	 * Renders above billing/shipping and reactively watches the effective
	 * shipping state (billing state when "ship to different" is unchecked,
	 * shipping state when checked). Only visible when the state is restricted
	 * for ammo — shows an error with Save For Later buttons and blocks Place
	 * Order. Hidden entirely for unrestricted or empty states.
	 *
	 * Hooked to woocommerce_checkout_before_customer_details (classic only).
	 *
	 * @since 1.0.15
	 *
	 * @return void
	 */
	public static function get_ammo_regular_notice() {
		$analyzer = self::get_analyzer();

		// Only show for ammo + regular mixed carts.
		if ( ! $analyzer->is_ammo_regular_mixed() ) {
			return;
		}

		if ( $analyzer->has_api_error() ) {
			return;
		}

		$restricted_states = $analyzer->get_ammo_restricted_states();
		if ( empty( $restricted_states ) ) {
			return;
		}

		$us_states     = US_States::get_all();
		$ffl_count     = $analyzer->get_quantity_by_category( 'ammo' );
		$regular_count = $analyzer->get_quantity_by_category( 'regular' );

		$icon_error = Cart::get_notice_icon( 'error' );

		?>
		<div id="automaticffl-ammo-regular-message" class="automaticffl-notice automaticffl-notice-error" role="alert">
			<span class="automaticffl-notice-icon">
				<?php echo $icon_error; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<div class="automaticffl-notice-content">
				<p class="automaticffl-notice-message" id="automaticffl-checkout-ammo-message"></p>
				<div id="automaticffl-checkout-save-for-later" style="display: none;">
					<p class="automaticffl-save-prompt"><?php esc_html_e( 'Choose which items to save for your next order:', 'automaticffl-for-wc' ); ?></p>
					<div class="automaticffl-save-buttons">
						<button type="button" class="button automaticffl-save-btn" data-item-type="ffl">
							<?php
							printf(
								/* translators: %d: number of ammo items */
								esc_html( _n( 'Ammo item (%d)', 'Ammo items (%d)', $ffl_count, 'automaticffl-for-wc' ) ),
								intval( $ffl_count )
							);
							?>
						</button>
						<button type="button" class="button automaticffl-save-btn" data-item-type="regular">
							<?php
							printf(
								/* translators: %d: number of regular items */
								esc_html( _n( 'Regular item (%d)', 'Regular items (%d)', $regular_count, 'automaticffl-for-wc' ) ),
								intval( $regular_count )
							);
							?>
						</button>
					</div>
					<p class="automaticffl-save-help"><?php esc_html_e( 'Saved items will be restored to your cart after checkout.', 'automaticffl-for-wc' ); ?></p>
				</div>
			</div>
		</div>
		<script>
		(function($) {
			'use strict';

			var restrictedStates = <?php echo wp_json_encode( $restricted_states ); ?>;
			var usStates = <?php echo wp_json_encode( $us_states ); ?>;
			var icons = {
				error: <?php echo wp_json_encode( $icon_error ); ?>
			};

			var $notice       = $('#automaticffl-ammo-regular-message');
			var $message      = $('#automaticffl-checkout-ammo-message');
			var $saveForLater = $('#automaticffl-checkout-save-for-later');
			var $icon         = $notice.find('.automaticffl-notice-icon');

			function getEffectiveState() {
				var shipToDifferent = $('#ship-to-different-address-checkbox').is(':checked');
				if (shipToDifferent) {
					return ($('#shipping_state').val() || '').toUpperCase();
				}
				return ($('#billing_state').val() || '').toUpperCase();
			}

			function update() {
				var state       = getEffectiveState();
				var stateName   = usStates[state] || state;
				var isRestricted = state && restrictedStates.indexOf(state) !== -1;

				if (isRestricted) {
					$notice.addClass('automaticffl-notice-error is-visible');
					$icon.html(icons.error);
					$message.text(
						<?php echo wp_json_encode( __( 'Ammunition requires shipping to an FFL dealer in', 'automaticffl-for-wc' ) ); ?> +
						' ' + stateName + '. ' +
						<?php echo wp_json_encode( __( 'Ammunition and regular products cannot be combined.', 'automaticffl-for-wc' ) ); ?>
					);
					$saveForLater.show();
					setPlaceOrderBlocked(true);
				} else {
					$notice.removeClass('is-visible');
					$saveForLater.hide();
					setPlaceOrderBlocked(false);
				}
			}

			function setPlaceOrderBlocked(blocked) {
				var $placeOrder = $('#place_order');
				if (blocked) {
					$placeOrder.prop('disabled', true).addClass('automaticffl-blocked');
				} else {
					$placeOrder.prop('disabled', false).removeClass('automaticffl-blocked');
				}
			}

			// Watch state fields and checkbox via event delegation.
			$(document.body).on('change', '#billing_state, #shipping_state, #ship-to-different-address-checkbox', update);

			// WooCommerce events fired after AJAX checkout update and country/state field changes.
			$(document.body).on('updated_checkout country_to_state_changed', update);

			// Block form submission as defense-in-depth.
			$('form.checkout').on('checkout_place_order', function() {
				var state = getEffectiveState();
				if (state && restrictedStates.indexOf(state) !== -1) {
					return false;
				}
			});

			// Initial check on DOM ready.
			$(document).ready(function() {
				update();
			});
		})(jQuery);
		</script>
		<?php
		Cart::enqueue_save_for_later_script();
	}


}
