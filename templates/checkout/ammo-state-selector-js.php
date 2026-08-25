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

	// State of the currently-picked dealer, captured from the dealerUpdate
	// postMessage. Used by the state-change handler to detect when the user
	// has switched away from the dealer's state — at which point the dealer
	// must be dropped, otherwise the order ships to the previous (and now
	// incorrect) state.
	let pickedDealerState = '';

	// Selectors for shipping fields to hide in restricted state.
	// Excludes name fields (customer must be able to enter their own name) and
	// the state field (customer must be able to switch out of the restricted state).
	const shippingAddressSelectors = [
		'#shipping_company_field',
		'#shipping_country_field',
		'#shipping_address_1_field',
		'#shipping_address_2_field',
		'#shipping_city_field',
		'#shipping_postcode_field',
		'#shipping_phone_field'
	];

	// Fields that move into the FFL "Shipping details" container when the
	// customer picks a restricted state, so first/last name and state sit
	// grouped with the dealer card instead of floating orphaned above it.
	const fflGroupedFieldSelectors = [
		'#shipping_first_name_field',
		'#shipping_last_name_field',
		'#shipping_state_field'
	];

	// Remember where to restore each moved field to.
	var fieldOriginalPositions = {};

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
	 * Move the shipping first/last name + state fields into the
	 * "Shipping Address" header section, ABOVE the blue state-message
	 * banner and the FFL container. Order: heading → name fields →
	 * state → banner → dealer card → button. Otherwise the fields
	 * would sit orphaned below — the WC "Ship to a different address?"
	 * h3 is always hidden in ammo flow and the surrounding address
	 * fields are hidden when a restricted state is picked.
	 */
	function moveFieldsIntoFFLContainer() {
		// Capture each field's original position on first move so we can
		// restore them exactly where WooCommerce rendered them.
		fflGroupedFieldSelectors.forEach(function(selector) {
			if (fieldOriginalPositions[selector]) {
				return;
			}
			var $field = $(selector);
			if (!$field.length) {
				return;
			}
			fieldOriginalPositions[selector] = {
				anchor: $field.prev(),
				parent: $field.parent()
			};
		});

		if ($('#automaticffl-grouped-fields-row').length > 0) {
			return;
		}
		var $row = $('<div id="automaticffl-grouped-fields-row" class="automaticffl-grouped-fields-row"></div>');
		fflGroupedFieldSelectors.forEach(function(selector) {
			var $field = $(selector);
			if ($field.length) {
				$row.append($field);
			}
		});
		if ($row.children().length > 0) {
			$('#automaticffl-grouped-fields-anchor').empty().append($row);
		}
	}

	/**
	 * Restore the grouped fields to their original WooCommerce positions so
	 * the full shipping form reads naturally when the user switches back to
	 * an unrestricted state.
	 */
	function restoreFieldsToOriginalPosition() {
		var $row = $('#automaticffl-grouped-fields-row');
		if ($row.length === 0) {
			return;
		}
		fflGroupedFieldSelectors.forEach(function(selector) {
			var $field = $(selector).detach();
			var pos = fieldOriginalPositions[selector];
			if (!$field.length || !pos) {
				return;
			}
			if (pos.anchor && pos.anchor.length) {
				$field.insertAfter(pos.anchor);
			} else if (pos.parent && pos.parent.length) {
				pos.parent.prepend($field);
			}
		});
		$row.remove();
	}

	/**
	 * Clear the currently-selected dealer and reset the FFL UI.
	 */
	function clearDealer() {
		$('#ffl_dealer_id').val('');
		$('#ffl_license_field').val('');
		$('#ffl_expiration_date').val('');
		$('#ffl_uuid').val('');
		$('#ffl_company_name').val('');
		$('#automaticffl-dealer-selected').empty().removeClass('automaticffl-dealer-selected');
		$('#automaticffl-select-dealer').text('<?php echo esc_js( __( 'Find a Dealer', 'automaticffl-for-wc' ) ); ?>');
		pickedDealerState = '';
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
	 *
	 * Restricted state: declutter the form to first name + last name + state
	 * (everything else hidden), move the name fields into the FFL "Shipping
	 * details" container, and show the dealer picker.
	 *
	 * Unrestricted state: restore the full shipping form and clear any
	 * previously-selected dealer.
	 */
	function updateAmmoCheckout() {
		const state = getEffectiveShippingState();
		const isRestricted = state !== '' && restrictedStates.includes(state);
		const dealerSelected = ($('#ffl_license_field').val() || '') !== '';
		const $messageContainer = $('#automaticffl-state-message');
		const $fflContainer = $('#automaticffl-ffl-container');

		// Once a dealer has been picked, the FFL UI is locked in for this
		// checkout — same shape as the firearms flow, which has no state
		// watcher and never clears the dealer. The ammo flow's reactive
		// updateAmmoCheckout otherwise re-evaluates on every `updated_checkout`
		// (including the one fired by ffl-map-js right after the pick) and
		// can take the empty/unrestricted branch, which clobbers the dealer
		// card and un-hides the shipping form. Guests hit this most readily
		// because their billing_state can fall outside restrictedStates while
		// their shipping_state is the dealer's address.
		//
		// #ffl_license_field is the right signal: ffl-map-js sets it on pick,
		// it lives outside the replaced fragments, and clearDealer() is the
		// only thing that empties it — so the lock self-clears when a future
		// "Change Dealer" flow explicitly resets state.
		if (dealerSelected) {
			hideShippingForm();
			moveFieldsIntoFFLContainer();
			$messageContainer.empty().hide();
			$fflContainer.show();
			fflRequired = true;
			return;
		}

		if (!state) {
			// No state selected yet: prompt, full shipping form visible.
			showShippingForm();
			restoreFieldsToOriginalPosition();
			$messageContainer
				.html('<div class="woocommerce"><div class="woocommerce-info" role="alert"><?php echo esc_js( $messages['ammoSelectState'] ); ?></div></div>')
				.show();
			$fflContainer.hide();
			fflRequired = false;
			return;
		}

		if (isRestricted) {
			// Declutter the form and group name fields with the dealer card.
			hideShippingForm();
			moveFieldsIntoFFLContainer();
			$messageContainer
				.html('<div class="woocommerce"><div class="woocommerce-info" role="alert"><?php echo esc_js( $messages['fflRequiredForState'] ); ?></div></div>')
				.show();
			$fflContainer.show();
			fflRequired = true;
		} else {
			// Unrestricted state: restore the full shipping form and drop
			// any previously-selected dealer so the customer ships to their
			// own address.
			showShippingForm();
			restoreFieldsToOriginalPosition();
			$messageContainer.empty().hide();
			$fflContainer.hide();
			fflRequired = false;
			clearDealer();
		}
	}

	// Listen for shipping state changes.
	//
	// A user-driven change to the state field invalidates any picked dealer:
	// the dealer's address belongs to the previous state, so leaving it in
	// place would ship the order to the wrong state. Clear the dealer first,
	// then let updateAmmoCheckout() decide the UI from a clean slate (prompt
	// for re-pick if the new state is also restricted, or restore the full
	// shipping form if it isn't).
	//
	// We compare against pickedDealerState rather than clearing
	// unconditionally so that a billing_state change (which doesn't affect
	// the effective shipping state when ship-to-different is checked, as it
	// always is once a dealer is picked) doesn't drop the dealer.
	//
	// The "dealer locked" early-return inside updateAmmoCheckout still
	// protects against the post-pick updated_checkout event clobbering the
	// dealer card — that path runs through updated_checkout, not change.
	$(document.body).on('change', '#shipping_state, #billing_state', function() {
		var dealerSelected = ($('#ffl_license_field').val() || '') !== '';
		if (dealerSelected && getEffectiveShippingState() !== pickedDealerState) {
			clearDealer();
		}
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

	// When a dealer is chosen, hide the "FFL required" banner.
	// The decluttered UI (hidden shipping fields, name fields in the FFL
	// container) is already driven by updateAmmoCheckout.
	$(window).on('message', function(e) {
		var event = e.originalEvent;
		if (event.data && event.data.type === 'dealerUpdate' && event.data.value) {
			pickedDealerState = event.data.value.stateOrProvinceCode || '';
			if (fflRequired) {
				$('#automaticffl-state-message').hide();
			}
		}
	});

	// Ensure shipping form is visible on load.
	// The ammo checkout UI is rendered inside .shipping_address via the
	// woocommerce_before_checkout_shipping_form hook, so the "ship to different
	// address" checkbox must be checked for it to be visible.
	showShippingForm();

	// If WC restored a previously-picked dealer from session, capture its
	// state from the shipping form so the state-change handler has a baseline
	// to compare against (otherwise the first change event would always look
	// like a divergence and clear the dealer).
	if (($('#ffl_license_field').val() || '') !== '') {
		pickedDealerState = $('#shipping_state').val() || '';
	}

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
