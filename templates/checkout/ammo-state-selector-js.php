<?php
/**
 * Ammo State Selector JavaScript Template
 *
 * Watches the WooCommerce shipping state field to determine FFL requirements
 * for ammo-only carts, matching the block checkout behavior.
 *
 * @package AutomaticFFL
 * @since 1.0.14
 *
 * Available variables:
 * @var array $restricted_states Array of state codes where FFL is required.
 * @var array $messages          Centralized messages from Messages class.
 */

defined( 'ABSPATH' ) || exit;
?>
<script>
jQuery(document).ready(function($) {
	const restrictedStates = <?php echo wp_json_encode( $restricted_states ); ?>;
	let fflRequired = false;
	let ammoFflLocked = false;

	// Selectors for shipping address fields to hide/show (excluding name fields).
	// Name fields stay visible so the customer can enter their name (important for guests).
	const shippingAddressSelectors = [
		'#shipping_company_field',
		'#shipping_country_field',
		'#shipping_address_1_field',
		'#shipping_address_2_field',
		'#shipping_city_field',
		'#shipping_state_field',
		'#shipping_postcode_field',
		'#shipping_phone_field'
	];

	/**
	 * Get the effective shipping state from the checkout form.
	 * Uses the shipping state if "ship to different address" is checked,
	 * otherwise falls back to the billing state.
	 */
	function getEffectiveShippingState() {
		const $shipDiffCheckbox = $('#ship-to-different-address-checkbox');
		const shipToDifferent = $shipDiffCheckbox.length ? $shipDiffCheckbox.prop('checked') : false;

		if (shipToDifferent) {
			return $('#shipping_state').val() || '';
		}
		return $('#billing_state').val() || '';
	}

	/**
	 * Hide the standard WooCommerce shipping address fields (keeps name fields visible).
	 */
	function hideShippingForm() {
		$('#ship-to-different-address').hide();
		shippingAddressSelectors.forEach(function(selector) {
			$(selector).hide();
		});
	}

	/**
	 * Show the standard WooCommerce shipping address fields.
	 * The WooCommerce heading is always hidden (our custom h3 replaces it).
	 */
	function showShippingForm() {
		$('#ship-to-different-address').hide();
		shippingAddressSelectors.forEach(function(selector) {
			$(selector).show();
		});
		// Force checkbox checked (so WooCommerce processes shipping fields)
		var $checkbox = $('#ship-to-different-address-checkbox');
		if ($checkbox.length && !$checkbox.prop('checked')) {
			$checkbox.prop('checked', true).trigger('change');
		}
	}

	/**
	 * Update checkout UI based on the current shipping state.
	 */
	function updateAmmoCheckout() {
		// If locked into FFL flow (dealer selected for restricted state), don't change UI
		if (ammoFflLocked) {
			return;
		}

		const state = getEffectiveShippingState();
		const isRestricted = state !== '' && restrictedStates.includes(state);
		const $messageContainer = $('#automaticffl-state-message');
		const $fflContainer = $('#automaticffl-ffl-container');

		if (!state) {
			// No state selected: show info message, keep shipping form visible
			$messageContainer
				.html('<div class="woocommerce"><div class="woocommerce-info" role="alert"><?php echo esc_js( $messages['ammoSelectState'] ); ?></div></div>')
				.show();
			$fflContainer.hide();
			fflRequired = false;
			return;
		}

		if (isRestricted) {
			// Restricted state: keep shipping form visible so the user can change state.
			// Only hide shipping form after a dealer is selected (ammoFflLocked).
			showShippingForm();
			$messageContainer
				.html('<div class="woocommerce"><div class="woocommerce-info" role="alert"><?php echo esc_js( $messages['fflRequiredForState'] ); ?></div></div>')
				.show();
			$fflContainer.show();
			fflRequired = true;
		} else {
			// Unrestricted state: show shipping form, hide FFL container
			showShippingForm();
			$messageContainer.empty().hide();
			$fflContainer.hide();
			fflRequired = false;

			// Clear any previously selected dealer
			$('#ffl_license_field').val('');
			$('#ffl_expiration_date').val('');
			$('#ffl_uuid').val('');
			$('#automaticffl-dealer-selected').empty().removeClass('automaticffl-dealer-selected');
			$('#automaticffl-select-dealer').text('<?php echo esc_js( __( 'Find a Dealer', 'automaticffl-for-wc' ) ); ?>');
		}
	}

	// Listen for shipping state changes
	$(document.body).on('change', '#shipping_state, #billing_state', function() {
		updateAmmoCheckout();
	});

	// Listen for ship-to-different-address checkbox changes
	$(document.body).on('change', '#ship-to-different-address-checkbox', function() {
		updateAmmoCheckout();
	});

	// Listen for WooCommerce checkout update events
	$(document.body).on('updated_checkout', function() {
		updateAmmoCheckout();
	});

	// Lock into FFL mode when a dealer is selected (via postMessage from iframe).
	// This prevents the feedback loop where dealer address state triggers re-evaluation.
	// Now that a dealer is chosen, hide the shipping form and the warning banner.
	$(window).on('message', function(e) {
		var event = e.originalEvent;
		if (event.data && event.data.type === 'dealerUpdate' && event.data.value) {
			if (fflRequired) {
				ammoFflLocked = true;
				hideShippingForm();
				$('#automaticffl-state-message').hide();
			}
		}
	});

	// Ensure shipping form is visible on load.
	// The ammo checkout UI is rendered inside .shipping_address via the
	// woocommerce_before_checkout_shipping_form hook, so the "ship to different
	// address" checkbox must be checked for it to be visible.
	showShippingForm();

	// Run initial check
	updateAmmoCheckout();

	// Validate before checkout submission
	$('form.checkout').on('checkout_place_order', function() {
		// Require FFL dealer if state is restricted
		if (fflRequired) {
			const fflLicense = $('#ffl_license_field').val();
			if (!fflLicense) {
				// Remove only our previously added top-of-page error, not the inline one
				$('.automaticffl-checkout-error').remove();
				$('form.checkout').prepend('<div class="woocommerce-error automaticffl-checkout-error"><?php echo esc_js( $messages['selectDealerBeforeOrder'] ); ?></div>');
				$('html, body').animate({ scrollTop: $('form.checkout').offset().top - 100 }, 500);
				return false;
			}
		}
		return true;
	});
});
</script>
