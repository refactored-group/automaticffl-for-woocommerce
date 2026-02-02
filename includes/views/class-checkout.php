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

/**
 * Class Checkout
 *
 * @since 1.0.0
 */
class Checkout {

	/**
	 * Shared Cart_Analyzer instance for the current request.
	 *
	 * @var Cart_Analyzer|null
	 */
	private static $analyzer = null;

	/**
	 * Get shared Cart_Analyzer instance (avoids duplicate API calls per request).
	 *
	 * @since 1.0.15
	 *
	 * @return Cart_Analyzer
	 */
	private static function get_analyzer(): Cart_Analyzer {
		if ( null === self::$analyzer ) {
			self::$analyzer = new Cart_Analyzer();
		}
		return self::$analyzer;
	}

	/**
	 * US States list for state selector.
	 *
	 * @var array
	 */
	private static $us_states = array(
		'AL' => 'Alabama',
		'AK' => 'Alaska',
		'AZ' => 'Arizona',
		'AR' => 'Arkansas',
		'CA' => 'California',
		'CO' => 'Colorado',
		'CT' => 'Connecticut',
		'DE' => 'Delaware',
		'DC' => 'District of Columbia',
		'FL' => 'Florida',
		'GA' => 'Georgia',
		'HI' => 'Hawaii',
		'ID' => 'Idaho',
		'IL' => 'Illinois',
		'IN' => 'Indiana',
		'IA' => 'Iowa',
		'KS' => 'Kansas',
		'KY' => 'Kentucky',
		'LA' => 'Louisiana',
		'ME' => 'Maine',
		'MD' => 'Maryland',
		'MA' => 'Massachusetts',
		'MI' => 'Michigan',
		'MN' => 'Minnesota',
		'MS' => 'Mississippi',
		'MO' => 'Missouri',
		'MT' => 'Montana',
		'NE' => 'Nebraska',
		'NV' => 'Nevada',
		'NH' => 'New Hampshire',
		'NJ' => 'New Jersey',
		'NM' => 'New Mexico',
		'NY' => 'New York',
		'NC' => 'North Carolina',
		'ND' => 'North Dakota',
		'OH' => 'Ohio',
		'OK' => 'Oklahoma',
		'OR' => 'Oregon',
		'PA' => 'Pennsylvania',
		'RI' => 'Rhode Island',
		'SC' => 'South Carolina',
		'SD' => 'South Dakota',
		'TN' => 'Tennessee',
		'TX' => 'Texas',
		'UT' => 'Utah',
		'VT' => 'Vermont',
		'VA' => 'Virginia',
		'WA' => 'Washington',
		'WV' => 'West Virginia',
		'WI' => 'Wisconsin',
		'WY' => 'Wyoming',
	);

	/**
	 * Verifies if there are FFL products with regular products in the shopping cart.
	 * If there are any, redirects customer back to the Cart page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function verify_mixed_cart() {
		if ( is_admin() ) {
			return;
		}

		$analyzer = self::get_analyzer();

		// If API is unavailable, skip mixed cart check - allow normal checkout.
		if ( $analyzer->has_api_error() ) {
			return;
		}

		// If mixed cart (FFL + regular products), redirect to cart.
		if ( $analyzer->is_mixed_ffl_regular() && ! is_cart() ) {
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
		$analyzer = self::get_analyzer();

		// Check if API is available - if not, show unavailable notice and allow normal checkout.
		if ( $analyzer->has_api_error() ) {
			self::get_api_unavailable_notice();
			return;
		}

		// No FFL products at all.
		if ( ! $analyzer->has_firearms() && ! $analyzer->has_ammo() ) {
			return;
		}

		// Mixed cart with regular products should not proceed.
		if ( $analyzer->is_mixed_ffl_regular() ) {
			return;
		}

		// Firearms present (with or without ammo) - always show FFL selection.
		if ( $analyzer->has_firearms() ) {
			self::get_js();
			self::get_map();
			self::disable_enter_key();
			return;
		}

		// Ammo only - show state selector if ammo features are enabled.
		if ( $analyzer->is_ammo_only() && Config::is_ammo_enabled() ) {
			self::get_ammo_checkout( $analyzer );
			return;
		}
	}

	/**
	 * Display notice when the Automatic FFL API is unavailable.
	 * Allows customer to proceed with normal checkout.
	 *
	 * @since 1.0.15
	 *
	 * @return void
	 */
	private static function get_api_unavailable_notice() {
		?>
		<div id="automaticffl-unavailable-notice" class="automaticffl-unavailable-notice">
			<div class="woocommerce">
				<div class="woocommerce-info automaticffl-unavailable-message" role="alert">
					<p><strong><?php esc_html_e( 'Automatic FFL Unavailable', 'automaticffl-for-wc' ); ?></strong></p>
					<p><?php esc_html_e( 'Please contact our store after placing an order.', 'automaticffl-for-wc' ); ?></p>
					<button type="button" id="automaticffl-unavailable-ok" class="button alt wp-element-button">
						<?php esc_html_e( 'OK', 'automaticffl-for-wc' ); ?>
					</button>
				</div>
			</div>
		</div>
		<script>
			jQuery(document).ready(function($) {
				$('#automaticffl-unavailable-ok').on('click', function() {
					$('#automaticffl-unavailable-notice').slideUp();
				});
			});
		</script>
		<?php
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
					<?php echo esc_html__( 'FFL dealer selection is not configured. Please contact the site administrator.', 'automaticffl-for-wc' ); ?>
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
				<?php echo esc_html__( 'You have a firearm in your cart and must choose a Licensed Firearm Dealer (FFL) for the Shipping Address.', 'automaticffl-for-wc' ); ?>
			</div>
		</div>
		<div id="automaticffl-dealer-selected">
		</div>
		<button type="button" id="automaticffl-select-dealer" value="12"
				class="button alt wp-element-button ffl-search-button">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="16" height="16" fill="currentColor" aria-hidden="true" style="vertical-align: middle; margin-right: 8px;">
					<path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"/>
				</svg><?php echo esc_html__( 'Find a Dealer', 'automaticffl-for-wc' ); ?></button>
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
			<p><?php echo esc_html__( 'Your order will be shipped to', 'automaticffl-for-wc' ); ?>:</p>
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

	/**
	 * Display ammo-only checkout with state selector.
	 *
	 * @since 1.0.15
	 *
	 * @param Cart_Analyzer $analyzer Cart analyzer instance.
	 * @return void
	 */
	private static function get_ammo_checkout( Cart_Analyzer $analyzer ) {
		$restricted_states = $analyzer->get_ammo_restricted_states();
		$user_name = self::get_user_name();
		$iframe_url = self::build_iframe_url();

		// Check if FFL is configured for when we need to show the dealer selection.
		$is_configured = ( false !== $iframe_url );
		?>
		<div id="automaticffl-ammo-checkout">
			<h3><?php esc_html_e( 'Ammunition Shipping', 'automaticffl-for-wc' ); ?></h3>
			<div class="woocommerce">
				<div class="woocommerce-info" role="alert">
					<?php esc_html_e( 'You have ammunition in your cart. Please select your shipping state to determine shipping options.', 'automaticffl-for-wc' ); ?>
				</div>
			</div>

			<p class="form-row">
				<label for="automaticffl-state-selector"><?php esc_html_e( 'Shipping State', 'automaticffl-for-wc' ); ?> <abbr class="required" title="required">*</abbr></label>
				<select id="automaticffl-state-selector" class="select" required>
					<option value=""><?php esc_html_e( 'Select a state...', 'automaticffl-for-wc' ); ?></option>
					<?php foreach ( self::$us_states as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<div id="automaticffl-state-message" style="display:none;"></div>

			<div id="automaticffl-ffl-container" style="display:none;">
				<?php if ( $is_configured ) : ?>
					<div id="automaticffl-dealer-selected"></div>
					<button type="button" id="automaticffl-select-dealer" value="12"
							class="button alt wp-element-button ffl-search-button">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="16" height="16" fill="currentColor" aria-hidden="true" style="vertical-align: middle; margin-right: 8px;">
							<path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"/>
						</svg><?php echo esc_html__( 'Find a Dealer', 'automaticffl-for-wc' ); ?>
					</button>
					<div class="automaticffl-dealer-layer" id="automaticffl-dealer-layer">
						<div class="dealers-container">
							<iframe id="automaticffl-map-iframe" src="<?php echo esc_url( $iframe_url ); ?>"></iframe>
						</div>
					</div>
					<div id="automaticffl-dealer-card-template">
						<p><?php echo esc_html__( 'Your order will be shipped to', 'automaticffl-for-wc' ); ?>:</p>
						<div id="ffl-selected-dealer" class="ffl-result-body">
							<p class="customer-name"><?php echo esc_html( $user_name['first_name'] ) . ' ' . esc_html( $user_name['last_name'] ); ?></p>
							<p class="dealer-name">{{dealer-name}}</p>
							<p class="dealer-address">{{dealer-address}}</p>
							<a href="tel:{{dealer-phone}}"><p><span class="dealer-phone dealer-phone-formatted">{{dealer-phone}}</span></p></a>
						</div>
					</div>
				<?php else : ?>
					<div class="woocommerce">
						<div class="woocommerce-error" role="alert">
							<?php echo esc_html__( 'FFL dealer selection is not configured. Please contact the site administrator.', 'automaticffl-for-wc' ); ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		self::get_ammo_js( $restricted_states );

		if ( $is_configured ) {
			self::get_js();
		}
	}

	/**
	 * Output JavaScript for ammo state selection.
	 *
	 * @since 1.0.15
	 *
	 * @param array $restricted_states Array of state codes where FFL is required.
	 * @return void
	 */
	private static function get_ammo_js( array $restricted_states ) {
		?>
		<script>
		jQuery(document).ready(function($) {
			const restrictedStates = <?php echo wp_json_encode( $restricted_states ); ?>;
			let fflRequired = false;

			$('#automaticffl-state-selector').on('change', function() {
				const state = $(this).val();
				const isRestricted = restrictedStates.includes(state);
				const $messageContainer = $('#automaticffl-state-message');
				const $fflContainer = $('#automaticffl-ffl-container');

				if (!state) {
					$messageContainer.hide();
					$fflContainer.hide();
					fflRequired = false;
					return;
				}

				if (isRestricted) {
					// Show FFL selection requirement
					$messageContainer
						.removeClass('automaticffl-state-allowed')
						.addClass('automaticffl-state-restricted')
						.html('<div class="woocommerce"><div class="woocommerce-error" role="alert"><?php echo esc_js( __( 'FFL dealer selection is required for ammunition shipping to this state.', 'automaticffl-for-wc' ) ); ?></div></div>')
						.show();
					$fflContainer.show();
					fflRequired = true;

					// Force ship to different address checkbox
					var checkbox = document.getElementById('ship-to-different-address-checkbox');
					if (checkbox) {
						checkbox.checked = true;
					}
				} else {
					// Standard checkout allowed
					$messageContainer
						.removeClass('automaticffl-state-restricted')
						.addClass('automaticffl-state-allowed')
						.html('<div class="woocommerce"><div class="woocommerce-message" role="alert"><?php echo esc_js( __( 'Standard shipping is available for ammunition to this state.', 'automaticffl-for-wc' ) ); ?></div></div>')
						.show();
					$fflContainer.hide();
					fflRequired = false;

					// Clear any previously selected dealer
					$('#ffl_license_field').val('');
				}

				// Update shipping state field
				$('#shipping_state').val(state).trigger('change');
				$('body').trigger('update_checkout');
			});

			// Validate before checkout submission
			$('form.checkout').on('checkout_place_order', function() {
				if (fflRequired) {
					const fflLicense = $('#ffl_license_field').val();
					if (!fflLicense) {
						alert('<?php echo esc_js( __( 'Please select an FFL dealer before placing your order.', 'automaticffl-for-wc' ) ); ?>');
						return false;
					}
				}
				return true;
			});
		});
		</script>
		<?php
	}

	/**
	 * Get US states list.
	 *
	 * @since 1.0.15
	 *
	 * @return array
	 */
	public static function get_us_states(): array {
		return self::$us_states;
	}

}
