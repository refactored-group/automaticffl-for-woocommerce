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
use RefactoredGroup\AutomaticFFL\Helper\Saved_Cart;
use RefactoredGroup\AutomaticFFL\Helper\US_States;

/**
 * Class Cart.
 *
 * @since 1.0.0
 */
class Cart {

	/**
	 * Get SVG icon markup for notice banners.
	 *
	 * @since 1.0.14
	 *
	 * @param string $type Icon type: 'error', 'success', or 'info'.
	 * @return string SVG markup.
	 */
	public static function get_notice_icon( string $type ): string {
		$icons = array(
			'error'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
			'success' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
			'info'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>',
		);

		return $icons[ $type ] ?? $icons['info'];
	}

	/**
	 * Display restoration notice if items were just restored to cart.
	 *
	 * Called on woocommerce_before_cart_table hook.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public static function show_restoration_notice() {
		$transient_key = 'affl_restoration_notice_' . WC()->session->get_customer_id();
		$notice_data   = get_transient( $transient_key );

		if ( ! $notice_data ) {
			return;
		}

		// Delete transient immediately so it only shows once.
		delete_transient( $transient_key );

		$type    = $notice_data['success'] ? 'success' : 'error';
		$message = $notice_data['message'];
		$failed  = $notice_data['failed'] ?? array();

		?>
		<div class="automaticffl-notice automaticffl-notice-<?php echo esc_attr( $type ); ?>" role="alert">
			<span class="automaticffl-notice-icon">
				<?php echo self::get_notice_icon( $type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<div class="automaticffl-notice-content">
				<p class="automaticffl-notice-message"><?php echo esc_html( $message ); ?></p>
				<?php if ( ! empty( $failed ) ) : ?>
					<ul class="automaticffl-notice-failed-list">
						<?php foreach ( $failed as $item ) : ?>
							<li><?php echo esc_html( $item['product_name'] . ': ' . $item['reason'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Verifies if there are FFL products with regular products in the shopping cart.
	 * If there are, shows message and block the login
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function verify_mixed_cart() {
		$analyzer = new Cart_Analyzer();

		// If API is unavailable, skip mixed cart check - allow normal checkout.
		if ( $analyzer->has_api_error() ) {
			return;
		}

		// Firearms + regular = always block (existing behavior).
		if ( $analyzer->is_firearms_regular_mixed() ) {
			self::show_mixed_cart_error( $analyzer );
			return;
		}

		// Ammo + regular = show state selector.
		if ( $analyzer->is_ammo_regular_mixed() ) {
			self::show_ammo_regular_state_selector( $analyzer );
			return;
		}

		// Show ammo notice for ammo-only carts.
		if ( $analyzer->has_ammo() && ! $analyzer->has_firearms() ) {
			self::show_ammo_notice( $analyzer );
		}
	}

	/**
	 * Display mixed cart error and block checkout.
	 *
	 * @since 1.0.0
	 *
	 * @param Cart_Analyzer $analyzer Cart analyzer instance.
	 * @return void
	 */
	private static function show_mixed_cart_error( Cart_Analyzer $analyzer ) {
		$ffl_products = array_merge(
			$analyzer->get_product_ids_by_category( 'firearms' ),
			$analyzer->get_product_ids_by_category( 'ammo' )
		);

		if ( ! empty( $ffl_products ) ) :
			// Determine appropriate message based on cart contents.
			if ( $analyzer->has_firearms() ) {
				$error_message = __( 'Firearms must be shipped to an FFL dealer and cannot be combined with regular products.', 'automaticffl-for-wc' );
			} else {
				$error_message = __( 'Ammunition and regular products cannot be purchased together. Ammunition may require shipping to an FFL dealer depending on your state.', 'automaticffl-for-wc' );
			}

			// Count items for buttons (total quantity, not line items).
			$ffl_count     = $analyzer->get_quantity_by_category( 'firearms' ) + $analyzer->get_quantity_by_category( 'ammo' );
			$regular_count = $analyzer->get_quantity_by_category( 'regular' );
			?>
			<script>
				window.ffl_products_in_cart = <?php echo wp_json_encode( $ffl_products ); ?>;
			</script>
			<div class="automaticffl-notice automaticffl-notice-error" role="alert" id="ffl-mixed-cart-error">
				<span class="automaticffl-notice-icon">
					<?php echo self::get_notice_icon( 'error' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<div class="automaticffl-notice-content">
					<p class="automaticffl-notice-message"><?php echo esc_html( $error_message ); ?></p>
					<p class="automaticffl-save-prompt"><?php esc_html_e( 'Choose which items to save for your next order:', 'automaticffl-for-wc' ); ?></p>
					<div class="automaticffl-save-buttons">
						<button type="button" class="button automaticffl-save-btn" data-item-type="ffl">
							<?php
							printf(
								/* translators: %d: number of FFL items */
								esc_html( _n( 'FFL item (%d)', 'FFL items (%d)', $ffl_count, 'automaticffl-for-wc' ) ),
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
			<style>
				.wc-proceed-to-checkout {
					display: none;
				}
			</style>
			<?php
			self::enqueue_save_for_later_script();
			self::verify_cart_modified();
		endif;
	}

	/**
	 * Enqueue the save for later script.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public static function enqueue_save_for_later_script() {
		$plugin_url = untrailingslashit( plugins_url( '/', _AFFL_LOADER_ ) );

		wp_enqueue_script(
			'automaticffl-save-for-later',
			$plugin_url . '/assets/js/save-for-later.js',
			array( 'jquery' ),
			filemtime( dirname( _AFFL_LOADER_ ) . '/assets/js/save-for-later.js' ),
			true
		);

		wp_localize_script(
			'automaticffl-save-for-later',
			'automaticfflSaveForLater',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'automaticffl_save_for_later' ),
				'i18n'    => array(
					'saving' => __( 'Saving...', 'automaticffl-for-wc' ),
					'error'  => __( 'An error occurred. Please try again.', 'automaticffl-for-wc' ),
				),
			)
		);
	}

	/**
	 * AJAX handler for saving items for later.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public static function ajax_save_for_later() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'automaticffl_save_for_later' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'automaticffl-for-wc' ) ) );
		}

		$item_type = isset( $_POST['item_type'] ) ? sanitize_text_field( wp_unslash( $_POST['item_type'] ) ) : '';

		// Validate item type.
		if ( ! in_array( $item_type, array( 'ffl', 'regular' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid item type.', 'automaticffl-for-wc' ) ) );
		}

		// Save items.
		$result = Saved_Cart::save_items( $item_type );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Display informational notice for ammo-only carts.
	 *
	 * @since 1.0.14
	 *
	 * @param Cart_Analyzer $analyzer Cart analyzer instance.
	 * @return void
	 */
	private static function show_ammo_notice( Cart_Analyzer $analyzer ) {
		$restricted_states = $analyzer->get_ammo_restricted_states();
		if ( empty( $restricted_states ) ) {
			return;
		}
		?>
		<div class="automaticffl-notice automaticffl-notice-info" role="alert" id="ffl-ammo-notice">
			<span class="automaticffl-notice-icon">
				<?php echo self::get_notice_icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<div class="automaticffl-notice-content">
				<p class="automaticffl-notice-message"><?php echo esc_html__( 'Your cart contains ammunition. FFL dealer selection may be required during checkout depending on your shipping state.', 'automaticffl-for-wc' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Display state selector for ammo + regular product mixed carts.
	 *
	 * Shows a state dropdown that determines checkout eligibility:
	 * - Unrestricted state: checkout allowed
	 * - Restricted state: checkout blocked (must separate orders)
	 *
	 * @since 1.0.14
	 *
	 * @param Cart_Analyzer $analyzer Cart analyzer instance.
	 * @return void
	 */
	private static function show_ammo_regular_state_selector( Cart_Analyzer $analyzer ) {
		$restricted_states = $analyzer->get_ammo_restricted_states();
		$selected_state    = WC()->session ? WC()->session->get( 'automaticffl_ammo_state', '' ) : '';

		// Determine initial banner state based on session.
		$is_restricted   = ! empty( $selected_state ) && in_array( $selected_state, $restricted_states, true );
		$is_unrestricted = ! empty( $selected_state ) && ! in_array( $selected_state, $restricted_states, true );
		$state_name      = ! empty( $selected_state ) ? US_States::get_name( $selected_state ) : '';

		// Determine banner type, class, and message.
		if ( $is_restricted ) {
			$banner_type  = 'error';
			$banner_class = 'automaticffl-notice-error';
			/* translators: %s: state name */
			$banner_message = sprintf( __( 'Ammunition requires shipping to an FFL dealer in %s. Ammunition and regular products cannot be combined.', 'automaticffl-for-wc' ), $state_name );
			$show_checkout  = false;
		} elseif ( $is_unrestricted ) {
			$banner_type  = 'success';
			$banner_class = 'automaticffl-notice-success';
			/* translators: %s: state name */
			$banner_message = sprintf( __( 'Standard shipping is available to %s. You may proceed to checkout.', 'automaticffl-for-wc' ), $state_name );
			$show_checkout  = true;
		} else {
			$banner_type    = 'info';
			$banner_class   = 'automaticffl-notice-info';
			$banner_message = __( 'Your cart contains ammunition and regular products. Please select your shipping state to continue.', 'automaticffl-for-wc' );
			$show_checkout  = false;
		}

		// Output products data for JS.
		$ffl_products = $analyzer->get_product_ids_by_category( 'ammo' );

		// Count items for save for later buttons (total quantity, not line items).
		$ffl_count     = $analyzer->get_quantity_by_category( 'ammo' );
		$regular_count = $analyzer->get_quantity_by_category( 'regular' );

		// Build config for JS (embedded in HTML for AJAX compatibility).
		$js_config = array(
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'automaticffl_set_ammo_state' ),
			'restrictedStates' => $restricted_states,
			'usStates'         => US_States::get_all(),
			'currentState'     => $selected_state,
			'i18n'             => array(
				'selectState'        => __( 'Your cart contains ammunition and regular products. Please select your shipping state to continue.', 'automaticffl-for-wc' ),
				'restrictedPrefix'   => __( 'Ammunition requires shipping to an FFL dealer in', 'automaticffl-for-wc' ),
				'restrictedSuffix'   => __( 'Ammunition and regular products cannot be combined.', 'automaticffl-for-wc' ),
				'unrestrictedPrefix' => __( 'Standard shipping is available to', 'automaticffl-for-wc' ),
				'unrestrictedSuffix' => __( 'You may proceed to checkout.', 'automaticffl-for-wc' ),
			),
		);
		?>
		<script>
			window.ffl_products_in_cart = <?php echo wp_json_encode( $ffl_products ); ?>;
		</script>

		<div id="automaticffl-cart-state-selector" class="automaticffl-notice <?php echo esc_attr( $banner_class ); ?>" data-banner-type="<?php echo esc_attr( $banner_type ); ?>" data-config="<?php echo esc_attr( wp_json_encode( $js_config ) ); ?>" role="alert">
			<span class="automaticffl-notice-icon">
				<?php echo self::get_notice_icon( $banner_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<div class="automaticffl-notice-content">
				<p class="automaticffl-notice-message" id="automaticffl-cart-state-message"><?php echo esc_html( $banner_message ); ?></p>

				<p class="form-row automaticffl-state-row">
					<label for="automaticffl-cart-state-select"><?php esc_html_e( 'Shipping State', 'automaticffl-for-wc' ); ?> <abbr class="required" title="required">*</abbr></label>
					<select id="automaticffl-cart-state-select" class="select" name="automaticffl_cart_state">
						<option value=""><?php esc_html_e( 'Select a state...', 'automaticffl-for-wc' ); ?></option>
						<?php foreach ( US_States::get_all() as $code => $name ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $selected_state, $code ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<!-- Save for later buttons (shown only when restricted state selected) -->
				<div id="automaticffl-ammo-save-for-later" style="<?php echo $is_restricted ? '' : 'display: none;'; ?>">
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

		<style>
			/* Initially hide/show checkout button based on state */
			.wc-proceed-to-checkout {
				display: <?php echo $show_checkout ? 'block' : 'none'; ?>;
			}
		</style>

		<?php
		self::enqueue_save_for_later_script();
		// Note: ammo-state-selector.js is enqueued globally on cart pages (in class-plugin.php)
		// and reads config from data-config attribute for AJAX compatibility.
		self::verify_cart_modified();
	}

	/**
	 * AJAX handler for setting ammo state in session.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public static function ajax_set_ammo_state() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'automaticffl_set_ammo_state' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}

		$state = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';

		// Validate state is a valid US state code (2 uppercase letters).
		if ( ! empty( $state ) && ! preg_match( '/^[A-Z]{2}$/', $state ) ) {
			wp_send_json_error( array( 'message' => 'Invalid state code' ) );
		}

		// Save to session.
		if ( WC()->session ) {
			WC()->session->set( 'automaticffl_ammo_state', $state );
		}

		wp_send_json_success( array( 'state' => $state ) );
	}

	/**
	 * When a cart is modified by removing a product using the X button on the cart table,
	 * update the visibility of the error message and the checkout button accordingly
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function verify_cart_modified() {
		?>
		<script>
			/**
			 * Returns a list of the current products ID on the cart HTML table
			 * @returns {*[]}
			 */
			function getCurrentCartProductIdsFfl() {
				var productIds = [];
				var cartContainer = document.querySelector('.woocommerce-cart-form__contents');

				if (cartContainer) {
					var elements = cartContainer.querySelectorAll('[data-product_id]');

					for (var i = 0; i < elements.length; i++) {
						productIds.push(elements[i].getAttribute('data-product_id'));
					}
				}

				return productIds;
			}

			/**
			 * Check if the ammo state selector allows checkout
			 * @returns {boolean|null} true if allowed, false if blocked, null if no state selector
			 */
			function isAmmoStateCheckoutAllowed() {
				var notice = document.getElementById('automaticffl-cart-state-selector');
				if (!notice) {
					return null; // No state selector present
				}

				// Check if the notice has success state (unrestricted state)
				if (notice.classList.contains('automaticffl-notice-success')) {
					return true; // Unrestricted state selected
				}

				return false; // No state or restricted state
			}

			/*
			 * Runs every time the DOM is changed
			 * This fixes the issue with products being removed from the cart but not updating the
			 * cart button visibility
			 */
			document.addEventListener("DOMContentLoaded", function() {
				var observer = new MutationObserver(function(mutations) {
					const currentProducts = getCurrentCartProductIdsFfl();
					let foundFfl = 0;

					for(let i = 0; i < currentProducts.length; i++) {
						if(window.ffl_products_in_cart.includes(parseInt(currentProducts[i]))) {
							foundFfl++;
						}
					}

					// Check if ammo state selector is managing checkout visibility
					var ammoStateAllowed = isAmmoStateCheckoutAllowed();
					if (ammoStateAllowed !== null) {
						// Ammo state selector is present - let it control visibility
						// Only intervene if cart is no longer mixed
						if (foundFfl === 0 || foundFfl === currentProducts.length) {
							// No longer a mixed cart - show checkout
							var checkoutButtons = document.querySelectorAll('.wc-proceed-to-checkout');
							checkoutButtons.forEach(function(element) {
								element.style.display = 'block';
							});
							// Hide the state selector since it's no longer needed
							var stateSelector = document.getElementById('automaticffl-cart-state-selector');
							if (stateSelector) {
								stateSelector.style.display = 'none';
							}
						}
						return; // Let the state selector JS handle the rest
					}

					if (foundFfl === 0 || foundFfl === currentProducts.length) {
						// Not a mixed cart
						// Hide the message and show the checkout button
						if (document.getElementById('ffl-mixed-cart-error')) {
							document.getElementById('ffl-mixed-cart-error').style.display = 'none';
						}

						// Display the checkout button
						var checkoutButtons = document.querySelectorAll('.wc-proceed-to-checkout');
						checkoutButtons.forEach(function(element) {
							element.style.display = 'block';
						});
					} else {
						// Mixed cart!!
						// show the message and show the checkout button
						if (document.getElementById('ffl-mixed-cart-error')) {
							document.getElementById('ffl-mixed-cart-error').style.display = 'block';
						}

						// Hide checkout button
						var checkoutButtons = document.querySelectorAll('.wc-proceed-to-checkout');
						checkoutButtons.forEach(function(element) {
							element.style.display = 'none';
						});
					}
				});

				// Configuration of the observer:
				var config = {
					attributes: true,
					childList: true,
					subtree: true
				};

				// Pass in the target node (in this case, the entire document)
				observer.observe(document, config);
			});
		</script>
		<?php
	}
}
