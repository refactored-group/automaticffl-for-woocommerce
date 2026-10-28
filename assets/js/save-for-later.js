/**
 * Save for Later - Classic Cart Handler
 *
 * Handles the save for later functionality in the classic WooCommerce cart
 * when customers have mixed carts (FFL items + regular items).
 *
 * @since 1.0.14
 */

(function($) {
	'use strict';
	var isSaving = false;

	/**
	 * Initialize save for later functionality
	 */
	function init() {
		// Bind click handlers to save buttons
		$(document).off('click.automaticffl-checkout', '.automaticffl-save-btn')
			.on('click.automaticffl-checkout', '.automaticffl-save-btn', handleSaveClick);
	}

	/**
	 * Handle save button click
	 *
	 * @param {Event} e Click event
	 */
	function handleSaveClick(e) {
		e.preventDefault();

		var $button = $(this);
		var checkoutType = $button.data('checkout-type');
		var itemType = checkoutType === 'ffl' ? 'regular' : 'ffl';

		if (isSaving || (checkoutType !== 'ffl' && checkoutType !== 'regular')) {
			return;
		}

		// Disable button and show loading state
		isSaving = true;
		var $buttons = $('.automaticffl-save-btn');
		$buttons.prop('disabled', true);
		$button.addClass('loading');

		var originalText = $button.text();
		$button.text(automaticfflSaveForLater.i18n.saving);

		// Send AJAX request
		$.ajax({
			url: automaticfflSaveForLater.ajaxUrl,
			type: 'POST',
			data: {
				action: 'automaticffl_save_for_later',
				item_type: itemType,
				nonce: automaticfflSaveForLater.nonce
			},
			success: function(response) {
				if (response && response.success) {
					// The opposite group is now saved; continue with the chosen group.
					window.location.assign(automaticfflSaveForLater.checkoutUrl);
				} else {
					// Show error message
					showError((response && response.data && response.data.message) || automaticfflSaveForLater.i18n.error);
					isSaving = false;
					$buttons.prop('disabled', false);
					$button.removeClass('loading');
					$button.text(originalText);
				}
			},
			error: function() {
				showError(automaticfflSaveForLater.i18n.error);
				isSaving = false;
				$buttons.prop('disabled', false);
				$button.removeClass('loading');
				$button.text(originalText);
			}
		});
	}

	/**
	 * Show error message
	 *
	 * @param {string} message Error message to display
	 */
	function showError(message) {
		// Find or create error container
		var $errorContainer = $('#automaticffl-save-error');

		if ($errorContainer.length === 0) {
			$errorContainer = $('<div id="automaticffl-save-error" class="woocommerce-error" role="alert"></div>');
			$('.automaticffl-mixed-actions').first().prepend($errorContainer);
		}

		$errorContainer.text(message).show();

		// Auto-hide after 5 seconds
		setTimeout(function() {
			$errorContainer.fadeOut();
		}, 5000);
	}

	// Initialize when DOM is ready
	$(document).ready(init);

	// Also reinitialize after cart fragments update (for WooCommerce AJAX cart updates)
	$(document.body).on('updated_cart_totals', init);

})(jQuery);
