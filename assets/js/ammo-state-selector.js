/**
 * Ammo State Selector - Handles state selection for ammo + regular mixed carts
 *
 * This script is loaded on all cart pages and reads config from HTML data attributes
 * (not wp_localize_script) so it works with AJAX cart updates.
 *
 * @package AutomaticFFL
 */

(function($) {
	'use strict';

	var AmmoStateSelector = {
		// Configuration (read from HTML data attributes)
		config: null,

		// SVG icons for dynamic updates
		icons: {
			error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
			success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
			info: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>'
		},

		/**
		 * Initialize the state selector
		 */
		init: function() {
			var $notice = $('#automaticffl-cart-state-selector');

			// Only initialize if the state selector notice exists
			if (!$notice.length) {
				return;
			}

			// Read config from data attributes on the notice element
			this.config = this.readConfigFromDOM($notice);

			if (!this.config) {
				return;
			}

			// Bind events (use namespaced events to prevent duplicates)
			this.bindEvents();

			// Initialize UI if state is already selected
			var $select = $('#automaticffl-cart-state-select');
			if ($select.length && $select.val()) {
				this.updateStateUI($select.val());
			}
		},

		/**
		 * Read configuration from DOM data attributes
		 */
		readConfigFromDOM: function($notice) {
			var configJson = $notice.attr('data-config');

			if (!configJson) {
				return null;
			}

			try {
				return JSON.parse(configJson);
			} catch (e) {
				console.error('AutomaticFFL: Failed to parse config JSON', e);
				return null;
			}
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Remove any existing handlers and attach new one
			$(document).off('change.automaticffl-state', '#automaticffl-cart-state-select')
				.on('change.automaticffl-state', '#automaticffl-cart-state-select', function() {
					var state = $(this).val();
					self.saveStateToSession(state);
				});
		},

		/**
		 * Update the UI based on selected state
		 */
		updateStateUI: function(state) {
			var $notice = $('#automaticffl-cart-state-selector');
			var $message = $('#automaticffl-cart-state-message');
			var $icon = $notice.find('.automaticffl-notice-icon');
			var $checkoutButtons = $('.wc-proceed-to-checkout');
			var $saveForLater = $('#automaticffl-ammo-save-for-later');

			if (!$notice.length || !this.config) {
				return;
			}

			var isRestricted = this.config.restrictedStates.indexOf(state) !== -1;
			var stateName = this.config.usStates[state] || state;

			// Remove all state classes
			$notice.removeClass('automaticffl-notice-error automaticffl-notice-success automaticffl-notice-info');

			var newType, newMessage;

			if (!state) {
				// No state selected
				newType = 'info';
				newMessage = this.config.i18n.selectState;
				$checkoutButtons.hide();
				$saveForLater.hide();
			} else if (isRestricted) {
				// Restricted state - cannot checkout
				newType = 'error';
				newMessage = this.config.i18n.restrictedPrefix + ' ' + stateName + '. ' + this.config.i18n.restrictedSuffix;
				$checkoutButtons.hide();
				$saveForLater.show();
			} else {
				// Unrestricted state - can checkout
				newType = 'success';
				newMessage = this.config.i18n.unrestrictedPrefix + ' ' + stateName + '. ' + this.config.i18n.unrestrictedSuffix;
				$checkoutButtons.show();
				$saveForLater.hide();
			}

			// Update notice class and icon
			$notice.addClass('automaticffl-notice-' + newType).attr('data-banner-type', newType);
			$icon.html(this.icons[newType]);
			$message.text(newMessage);
		},

		/**
		 * Save selected state to session via AJAX
		 */
		saveStateToSession: function(state) {
			var self = this;

			// Update UI immediately for responsiveness
			this.updateStateUI(state);

			// Save to session in background
			$.ajax({
				url: this.config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'automaticffl_set_ammo_state',
					state: state,
					nonce: this.config.nonce
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		AmmoStateSelector.init();
	});

	// Re-initialize after WooCommerce cart updates (AJAX)
	$(document.body).on('updated_cart_totals updated_wc_div', function() {
		AmmoStateSelector.init();
	});

})(jQuery);
