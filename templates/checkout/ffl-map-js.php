<?php
/**
 * FFL Dealer Map JavaScript Template
 *
 * This template contains the JavaScript for the FFL dealer selection iframe communication.
 *
 * @package AutomaticFFL
 * @since 1.0.14
 *
 * Available variables:
 * @var array  $allowed_origins  Array of allowed origins for postMessage security.
 */

defined( 'ABSPATH' ) || exit;
?>
<script>
	jQuery(document).ready(function($) {
		var destinationResolver = window.AutomaticFflAmmoDestination;
		var currentDealer = null;
		var fflFieldNames = [
			'ffl_dealer_id',
			'ffl_license_field',
			'ffl_expiration_date',
			'ffl_uuid',
			'ffl_company_name'
		];
		// Open modal when "Find a Dealer" button is clicked
		$('#automaticffl-select-dealer').click(function() {
			$('body').css('overflow', 'hidden');
			$('.automaticffl-dealer-layer').addClass('visible');
		});

		// Close modal when Escape key is pressed
		$('body').keydown(function(e) {
			var modal = $('.automaticffl-dealer-layer');
			if (modal.hasClass('visible') && e.which == 27) {
				$('body').css('overflow', '');
				modal.removeClass('visible');
			}
		});

		// Close modal when clicking on overlay (outside the modal content)
		$('.automaticffl-dealer-layer').click(function(event) {
			if (event.target === this) {
				$('body').css('overflow', '');
				$(this).removeClass('visible');
			}
		});

		// Format phone number helper
		function formatPhone(phone) {
			return phone.replace(/(\d{3})(\d{3})(\d{4})/, '($1)-$2-$3');
		}

		function getCheckoutForm() {
			return $('form.checkout').first();
		}

		// Native fields can be rendered in a builder module outside the form that
		// WooCommerce or a gateway serializes. Maintain one enabled canonical FFL
		// input in the actual checkout form while keeping display copies updated.
		function getCanonicalFflField(fieldName) {
			var $form = getCheckoutForm();
			if (!$form.length) {
				return $();
			}

			var $field = $form.find('[name="' + fieldName + '"]').first();
			var $copies = $('[name="' + fieldName + '"]');
			if (!$field.length) {
				$field = $('<input>', {
					type: 'hidden',
					name: fieldName,
					value: $copies.first().val() || ''
				}).appendTo($form);
			}

			$copies = $('[name="' + fieldName + '"]');
			$copies.not($field).filter(function() {
				return $.contains($form[0], this);
			}).prop('disabled', true);
			$('[id="' + fieldName + '"]').not($field).removeAttr('id');
			$field.attr('id', fieldName).prop('disabled', false);
			return $field;
		}

		function ensureCanonicalFflFields() {
			fflFieldNames.forEach(function(fieldName) {
				getCanonicalFflField(fieldName);
			});
		}

		// Some classic checkout templates skip visible shipping fields. Keep
		// posting the fields WooCommerce expects by appending hidden fallbacks to
		// the actual checkout form, never to a separate builder form.
		function getCheckoutField(fieldId, fieldName) {
			if (fflFieldNames.includes(fieldName)) {
				return getCanonicalFflField(fieldName);
			}

			var $form = getCheckoutForm();
			if (!$form.length) {
				return $();
			}

			var $field = $form.find('[name="' + fieldName + '"]').first();
			if ($field.length) {
				return $field;
			}

			return $('<input>', {
				type: 'hidden',
				id: $('#' + fieldId).length ? 'automaticffl-submit-' + fieldId : fieldId,
				name: fieldName
			}).appendTo($form);
		}

		function setCheckoutFieldValue(fieldId, fieldName, value, triggerChange) {
			var $field = getCheckoutField(fieldId, fieldName);
			if (!$field.length) {
				return $field;
			}

			var normalizedValue = value || '';
			var changed = ($field.val() || '') !== String(normalizedValue);
			$field.val(normalizedValue);
			$('[name="' + fieldName + '"]').not($field).val(normalizedValue);
			if (triggerChange && changed) {
				$field.trigger('change');
			}
			return $field;
		}

		function forceShipToDifferentAddress() {
			var $shipToDifferent = getCheckoutField('ship_to_different_address', 'ship_to_different_address');
			if (!$shipToDifferent.length) {
				return;
			}

			$shipToDifferent.val('1');
			if ($shipToDifferent.is(':checkbox')) {
				$shipToDifferent.prop('checked', true);
			}
		}

		function synchronizeDealerToCheckout(dealer, triggerCheckoutUpdate) {
			if (!dealer) {
				ensureCanonicalFflFields();
				return;
			}

			var $shippingFirstName = getCheckoutField('shipping_first_name', 'shipping_first_name');
			var $shippingLastName = getCheckoutField('shipping_last_name', 'shipping_last_name');
			forceShipToDifferentAddress();

			if ( ! ($shippingFirstName.val() || '').trim() ) {
				setCheckoutFieldValue('shipping_first_name', 'shipping_first_name', $('#billing_first_name').val() || '', false);
			}
			if ( ! ($shippingLastName.val() || '').trim() ) {
				setCheckoutFieldValue('shipping_last_name', 'shipping_last_name', $('#billing_last_name').val() || '', false);
			}

			setCheckoutFieldValue('shipping_company', 'shipping_company', dealer.company || '', false);
			setCheckoutFieldValue('shipping_phone', 'shipping_phone', dealer.phone || '', false);

			var dealerDestination = destinationResolver
				? destinationResolver.toWooCommerce(dealer.countryCode || 'US', dealer.stateOrProvinceCode || '')
				: { country: dealer.countryCode || 'US', state: dealer.stateOrProvinceCode || '' };
			// Set state first so ammunition state-change listeners see the new
			// dealer destination while the country control is updating.
			setCheckoutFieldValue('shipping_state', 'shipping_state', dealerDestination.state, false);
			setCheckoutFieldValue('shipping_country', 'shipping_country', dealerDestination.country, true);
			// Country changes can replace WooCommerce's state control.
			setCheckoutFieldValue('shipping_state', 'shipping_state', dealerDestination.state, false);
			setCheckoutFieldValue('shipping_address_1', 'shipping_address_1', dealer.address1 || '', false);
			setCheckoutFieldValue('shipping_address_2', 'shipping_address_2', dealer.address2 || '', false);
			setCheckoutFieldValue('shipping_city', 'shipping_city', dealer.city || '', false);
			setCheckoutFieldValue('shipping_postcode', 'shipping_postcode', dealer.postalCode || '', false);
			// Country/state change handlers may clear a previous selection. Write
			// the current dealer identity after the destination is settled.
			setCheckoutFieldValue('ffl_dealer_id', 'ffl_dealer_id', dealer.id || '', false);
			setCheckoutFieldValue('ffl_license_field', 'ffl_license_field', dealer.fflID || '', false);
			setCheckoutFieldValue('ffl_expiration_date', 'ffl_expiration_date', dealer.expirationDate || '', false);
			setCheckoutFieldValue('ffl_uuid', 'ffl_uuid', dealer.uuid || '', false);
			setCheckoutFieldValue('ffl_company_name', 'ffl_company_name', dealer.company || '', false);

			if (triggerCheckoutUpdate) {
				$('body').trigger('update_checkout');
			}
		}

		function renderDealerSelection(dealer) {
			if (!dealer) {
				return;
			}

			$('#automaticffl-select-dealer').text("Change Dealer");
			$('#automaticffl-dealer-selected').addClass('automaticffl-dealer-selected');
			$('#automaticffl-ffl-container').show();
			[
				'#shipping_company_field',
				'#shipping_country_field',
				'#shipping_address_1_field',
				'#shipping_address_2_field',
				'#shipping_city_field',
				'#shipping_postcode_field',
				'#shipping_phone_field'
			].forEach(function(selector) {
				$(selector).hide();
			});
			const formattedAddress = (dealer.address1 || '') + ', ' + (dealer.city || '') + ', ' + (dealer.stateOrProvinceCode || '');
			const formattedPhone = formatPhone(dealer.phone || '');
			const $cardTemplate = $('#automaticffl-dealer-card-template').clone();
			var firstName = getCheckoutField('shipping_first_name', 'shipping_first_name').val() || '';
			var lastName = getCheckoutField('shipping_last_name', 'shipping_last_name').val() || '';
			$cardTemplate.find('.customer-name').text(firstName + ' ' + lastName);
			$cardTemplate.find('.dealer-name').text(dealer.company || '');
			$cardTemplate.find('.dealer-address').text(formattedAddress);
			$cardTemplate.find('.dealer-phone-formatted').text(formattedPhone);
			$cardTemplate.find('a').attr('href', 'tel:' + (dealer.phone || ''));
			$('#automaticffl-dealer-selected').empty().append($cardTemplate.html());
		}

		function clearDealerRequiredCheckoutError() {
			$('.automaticffl-checkout-error').remove();
			$('.woocommerce-error [data-id="ffl_dealer_required"]').each(function() {
				var $notice = $(this).closest('.woocommerce-error');
				$(this).remove();
				if ($notice.find('li').length === 0) {
					$notice.remove();
				}
			});
		}

		// Allowed origins for postMessage security
		const allowedOrigins = <?php echo wp_json_encode( $allowed_origins ); ?>;

		// Listen for postMessage from iframe
		window.addEventListener('message', function(event) {
			// Security: Validate message origin
			if (!allowedOrigins.includes(event.origin)) {
				return;
			}

			// Validate message type
			if (event.data.type === 'dealerUpdate') {
				const dealer = event.data.value;

				// Validate dealer object exists
				if (!dealer) {
					return;
				}
				currentDealer = dealer;

				// Map iframe dealer fields to WooCommerce shipping fields.
				// shipping_first_name / shipping_last_name are left as-is when
				// the customer already typed something there. When they're
				// empty (common: a guest who hasn't visited the shipping form
				// yet because the dealer modal opened first), copy from
				// billing_first_name / billing_last_name so the order has a
				// shipping name and the server-side validator doesn't block
				// checkout with no obvious field for the customer to fix.
				synchronizeDealerToCheckout(dealer, true);

				renderDealerSelection(dealer);
				clearDealerRequiredCheckoutError();

				// Close modal
				$('body').css('overflow', '');
				$('.automaticffl-dealer-layer').removeClass('visible');
			} else if (event.data.type === 'closeModal') {
				// Handle close modal message from iframe
				$('body').css('overflow', '');
				$('.automaticffl-dealer-layer').removeClass('visible');
			}
		});

		// Update the dealer card name live when the customer edits the name fields.
		$(document.body).on('input', '#shipping_first_name, #shipping_last_name', function() {
			var $card = $('#automaticffl-dealer-selected .customer-name');
			if ($card.length) {
				var firstName = $('#shipping_first_name').val() || '';
				var lastName = $('#shipping_last_name').val() || '';
				$card.text(firstName + ' ' + lastName);
			}
		});

		// Payment areas and builder modules can be replaced by checkout AJAX.
		// Recreate only same-page submission fields from in-memory selection.
		$(document.body).on('updated_checkout', function() {
			synchronizeDealerToCheckout(currentDealer, false);
			renderDealerSelection(currentDealer);
		});

		ensureCanonicalFflFields();
	});
</script>
