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

				// Map iframe dealer fields to WooCommerce shipping fields
				// Note: shipping_first_name and shipping_last_name are left as-is
				// so the customer's own input is preserved (important for guests).
				$('#shipping_company').val(dealer.company || '');
				$('#ffl_license_field').val(dealer.fflID || '');
				$('#ffl_expiration_date').val(dealer.expirationDate || '');
				$('#ffl_uuid').val(dealer.uuid || '');
				$('#shipping_phone').val(dealer.phone || '');
				$('#shipping_country').val(dealer.countryCode || 'US');
				$('#shipping_state').val(dealer.stateOrProvinceCode || '');
				$('#shipping_address_1').val(dealer.address1 || '');
				$('#shipping_city').val(dealer.city || '');
				$('#shipping_postcode').val(dealer.postalCode || '');

				// Update button text
				$('#automaticffl-select-dealer').text("Change Dealer");
				$('#automaticffl-dealer-selected').addClass('automaticffl-dealer-selected');

				// Update selected dealer card display - using safe text insertion
				const formattedAddress = (dealer.address1 || '') + ', ' + (dealer.city || '') + ', ' + (dealer.stateOrProvinceCode || '');
				const formattedPhone = formatPhone(dealer.phone || '');

				const $cardTemplate = $('#automaticffl-dealer-card-template').clone();
				var firstName = $('#shipping_first_name').val() || '';
				var lastName = $('#shipping_last_name').val() || '';
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
