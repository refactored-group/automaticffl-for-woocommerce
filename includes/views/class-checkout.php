<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Views;

defined( 'ABSPATH' ) || exit;

use RefactoredGroup\AutomaticFFL\Helper\Config;

/**
 * Class Checkout
 *
 * @since 1.0.0
 */
class Checkout {

	/**
	 * Verifies if there are FFL products with regular products in the shopping cart.
	 * If there are any, redirects customer back to the Cart page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function verify_mixed_cart() {
		
		if (is_admin()) return false;
		
		$cart           = WC()->cart->get_cart();
		$total_products = count( $cart );
		$total_ffl      = 0;
		foreach ( $cart as $product ) {
			$product_id = $product['product_id'];
			$ffl_required = get_post_meta($product_id, '_ffl_required', true);
			
			if ( $ffl_required === 'yes' ) {
				$total_ffl++;
			}
		}

		if ( $total_ffl > 0 && $total_ffl < $total_products && ! is_cart() ) {
			// Redirect back to the cart where the error message will be displayed.
			wp_safe_redirect( wc_get_cart_url() );
			exit();
		}
	}

	public static function add_automaticffl_checkout_field($checkout) {
		if( Config::is_ffl_cart() ){
			woocommerce_form_field('ffl_license_field', array(
				'type' => 'text',
				'class' => array('hidden'),
				'label' => __('FFL License', 'automaticffl-for-wc'),
				'placeholder' => __('FFL License', 'automaticffl-for-wc'),
				'required' => true,
			), $checkout->get_value('ffl_license_field'));
		}
	}

	public static function save_automaticffl_checkout_field_value($order_id) {
		if (!empty($_POST['ffl_license_field'])) {
			update_post_meta($order_id, '_ffl_license_field', sanitize_text_field($_POST['ffl_license_field']));
		}
	}

	public static function after_checkout_create_order($order_id) {
		if( Config::is_ffl_cart() ){
			$ffl_license = get_post_meta($order_id, '_ffl_license_field', true);

			$order = wc_get_order($order_id);

			$order->add_order_note('FFL License: ' . $ffl_license);
			$order->save();
		}
	}

	public static function automaticffl_custom_fields( $fields ) {
		if( Config::is_ffl_cart() ){
				$fields['shipping']['shipping_phone'] = array(
				'label'		=>	__('Dealer Phone', 'automaticffl-for-wc'),
				'placeholder'   => _x('Dealer Phone', 'placeholder', 'automaticffl-for-wc'),
				'required'  => true,
				'class'     => array('hidden'),
				'clear'     => true
			);
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
	 * Build iframe URL with query parameters
	 *
	 * @since 1.0.13
	 *
	 * @return string|false Returns URL string on success, false if required data is missing
	 */
	private static function build_iframe_url() {
		$base_url = Config::get_iframe_map_url();
		$store_hash = Config::get_store_hash();
		$maps_api_key = Config::get_google_maps_api_key();

		// Validate required parameters
		if ( empty( $store_hash ) || empty( $maps_api_key ) ) {
			return false;
		}

		$params = array(
			'store_hash' => $store_hash,
			'platform' => 'WooCommerce',
			'maps_api_key' => $maps_api_key,
		);

		return add_query_arg( $params, $base_url );
	}

	/**
	 * Used by the Hook to return the map when a FFL cart is loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function get_ffl() {
		if ( ! Config::is_ffl_cart() ) {
			return;
		}

		self::get_js();
		self::get_map();
		self::disable_enter_key();
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
		$user_name = self::get_user_name();
		$iframe_url = self::build_iframe_url();

		// If configuration is invalid, show error message
		if ( false === $iframe_url ) {
			?>
			<div class="woocommerce">
				<div class="woocommerce-error" role="alert">
					<?php echo esc_html( 'FFL dealer selection is not configured. Please contact the site administrator.' ); ?>
				</div>
			</div>
			<?php
			return;
		}
		?>
		<h3>
			<label style="font-weight: 100">
				<span>Ship to a different address?</span>
			</label>
		</h3>
		<div class="woocommerce">
			<div class="woocommerce-info" role="alert">
				<?php echo esc_html( 'You have a firearm in your cart and must choose a Licensed Firearm Dealer (FFL) for the Shipping Address.' ); ?>
			</div>
		</div>
		<div id="automaticffl-dealer-selected">
		</div>
		<button type="button" id="automaticffl-select-dealer" value="12"
				class="button alt wp-element-button ffl-search-button">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="16" height="16" fill="currentColor" aria-hidden="true" style="vertical-align: middle; margin-right: 8px;">
					<path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"/>
				</svg><?php echo esc_html( 'Find a Dealer' ); ?></button>
		<div id="automaticffl-dealer-card" class="woocomerce" style="background: #f8f8f8; color: #000000; display: none">
			<div class="woocommerce-info" role="alert">
			</div>
		</div>
		<script>
			// Force customers to enter a Shipping Address different than Billing
			document.getElementById('ship-to-different-address-checkbox').checked = true;
		</script>
		<div class="automaticffl-dealer-layer" id="automaticffl-dealer-layer">
			<div class="dealers-container">
				<iframe id="automaticffl-map-iframe" src="<?php echo esc_url( $iframe_url ); ?>"></iframe>
			</div>
		</div>
		<div id="automaticffl-dealer-card-template">
			<p><?php echo esc_html( 'Your order will be shipped to' ); ?>:</p>
			<div id="ffl-selected-dealer" class="ffl-result-body">
				<p class="customer-name"><?php echo esc_html( $user_name['first_name'] ) . ' ' . esc_html( $user_name['last_name'] ) ?></p>
				<p class="dealer-name">{{dealer-name}}</p>
				<p class="dealer-address">{{dealer-address}}</p>
				<a href="tel:{{dealer-phone}}"><p><span class="dealer-phone dealer-phone-formatted">{{dealer-phone}}</span></p></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Get Javascript that handles the iframe postMessage communication
	 *
	 * @return void
	 * @since 1.0.13
	 */
	public static function get_js() {
		$user_name = self::get_user_name();
		$allowed_origins = Config::get_iframe_allowed_origins();
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
						$('#shipping_first_name').val('<?php echo esc_js( $user_name['first_name'] ); ?>');
						$('#shipping_last_name').val('<?php echo esc_js( $user_name['last_name'] ); ?>');
						$('#shipping_company').val(dealer.company || '');
						$('#ffl_license_field').val(dealer.fflID || '');
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
						$cardTemplate.find('.customer-name').text('<?php echo esc_js( $user_name['first_name'] . ' ' . $user_name['last_name'] ); ?>');
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
			});
		</script>
		<?php
	}

}
