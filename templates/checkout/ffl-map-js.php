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

		// Some classic checkout templates skip visible shipping fields. Keep
		// posting the fields WooCommerce expects by appending hidden fallbacks.
		function getCheckoutField(fieldId, fieldName) {
			var $field = $('#' + fieldId);
			if ($field.length) {
				return $field;
			}

			$field = $('[name="' + fieldName + '"]').first();
			if ($field.length) {
				return $field;
			}

			var $form = $('form.checkout').first();
			if (!$form.length) {
				return $();
			}

			return $('<input>', {
				type: 'hidden',
				id: fieldId,
				name: fieldName
			}).appendTo($form);
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

				// Map iframe dealer fields to WooCommerce shipping fields.
				// shipping_first_name / shipping_last_name are left as-is when
				// the customer already typed something there. When they're
				// empty (common: a guest who hasn't visited the shipping form
				// yet because the dealer modal opened first), copy from
				// billing_first_name / billing_last_name so the order has a
				// shipping name and the server-side validator doesn't block
				// checkout with no obvious field for the customer to fix.
				var $shippingFirstName = getCheckoutField('shipping_first_name', 'shipping_first_name');
				var $shippingLastName = getCheckoutField('shipping_last_name', 'shipping_last_name');
				var $shippingCompany = getCheckoutField('shipping_company', 'shipping_company');
				var $shippingPhone = getCheckoutField('shipping_phone', 'shipping_phone');
				var $shippingCountry = getCheckoutField('shipping_country', 'shipping_country');
				var $shippingState = getCheckoutField('shipping_state', 'shipping_state');
				var $shippingAddress1 = getCheckoutField('shipping_address_1', 'shipping_address_1');
				var $shippingAddress2 = getCheckoutField('shipping_address_2', 'shipping_address_2');
				var $shippingCity = getCheckoutField('shipping_city', 'shipping_city');
				var $shippingPostcode = getCheckoutField('shipping_postcode', 'shipping_postcode');

				forceShipToDifferentAddress();

				if ( ! ($shippingFirstName.val() || '').trim() ) {
					$shippingFirstName.val($('#billing_first_name').val() || '');
				}
				if ( ! ($shippingLastName.val() || '').trim() ) {
					$shippingLastName.val($('#billing_last_name').val() || '');
				}
				$shippingCompany.val(dealer.company || '');
				$('#ffl_dealer_id').val(dealer.id || '');
				$('#ffl_license_field').val(dealer.fflID || '');
				$('#ffl_expiration_date').val(dealer.expirationDate || '');
				$('#ffl_uuid').val(dealer.uuid || '');
				$('#ffl_company_name').val(dealer.company || '');
				$shippingPhone.val(dealer.phone || '');
				$shippingCountry.val(dealer.countryCode || 'US');
				$shippingState.val(dealer.stateOrProvinceCode || '');
				$shippingAddress1.val(dealer.address1 || '');
				$shippingAddress2.val(dealer.address2 || '');
				$shippingCity.val(dealer.city || '');
				$shippingPostcode.val(dealer.postalCode || '');

				// Update button text
				$('#automaticffl-select-dealer').text("Change Dealer");
				$('#automaticffl-dealer-selected').addClass('automaticffl-dealer-selected');

				// Update selected dealer card display - using safe text insertion
				const formattedAddress = (dealer.address1 || '') + ', ' + (dealer.city || '') + ', ' + (dealer.stateOrProvinceCode || '');
				const formattedPhone = formatPhone(dealer.phone || '');

				const $cardTemplate = $('#automaticffl-dealer-card-template').clone();
				var firstName = $shippingFirstName.val() || '';
				var lastName = $shippingLastName.val() || '';
				$cardTemplate.find('.customer-name').text(firstName + ' ' + lastName);
				$cardTemplate.find('.dealer-name').text(dealer.company || '');
				$cardTemplate.find('.dealer-address').text(formattedAddress);
				$cardTemplate.find('.dealer-phone-formatted').text(formattedPhone);
				$cardTemplate.find('a').attr('href', 'tel:' + (dealer.phone || ''));

				$('#automaticffl-dealer-selected').empty().append($cardTemplate.html());

				// Trigger WooCommerce checkout update
				$('body').trigger('update_checkout');

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
	});
</script>
