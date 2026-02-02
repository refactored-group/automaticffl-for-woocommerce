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

	/**
	 * Initialize save for later functionality
	 */
	function init() {
		// Bind click handlers to save buttons
		$(document).on('click', '.automaticffl-save-btn', handleSaveClick);
	}

	/**
	 * Handle save button click
	 *
	 * @param {Event} e Click event
	 */
	function handleSaveClick(e) {
		e.preventDefault();

		var $button = $(this);
		var itemType = $button.data('item-type');

		if (!itemType) {
			return;
		}

		// Disable button and show loading state
		$button.prop('disabled', true);
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
				if (response.success) {
					// Reload the page to show updated cart
					window.location.reload();
				} else {
					// Show error message
					showError(response.data.message || automaticfflSaveForLater.i18n.error);
					$button.prop('disabled', false);
					$button.removeClass('loading');
					$button.text(originalText);
				}
			},
			error: function() {
				showError(automaticfflSaveForLater.i18n.error);
				$button.prop('disabled', false);
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
			$('.automaticffl-save-for-later-actions').before($errorContainer);
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
