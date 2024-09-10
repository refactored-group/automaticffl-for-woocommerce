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

		if ( $total_ffl > 0 && $total_ffl < $total_products ) {
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
		?>
		<div class="woocommerce">
			<div class="woocommerce-info" role="alert">
				<?php echo esc_html( 'You have a firearm in your cart and must choose a Licensed Firearm Dealer (FFL) for the Shipping Address.' ); ?>
			</div>
		</div>
		<div id="automaticffl-dealer-selected">
		</div>
		<button type="button" id="automaticffl-select-dealer" value="12"
				class="button alt wp-element-button fa-search ffl-search-button"><?php echo esc_html( 'Find a Dealer' ); ?></button>
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
				<span id="automaticffl-close-modal-button" class="w3-button w3-display-topright" title="<?php echo esc_html( 'Close' ); ?>">
					<i class="fas fa-times"></i>
				</span>
				<div class="modal-container">
					<div class="modal-items">
						<div class="ffl-search-results">
							<div class="modal-header-container show-list">
								<div class="modal-header-logo">
									<img src="<?php echo esc_url( affl()->get_plugin_url() ); ?>/assets/images/logo-automaticffl.png">
									<h4 class="logo-header"><?php echo esc_html( 'FIND YOUR DEALER' ) ?></h4>
									<p class="logo-sub"><?php echo esc_html( 'Use the options below to search for a dealer near you.' ) ?></p>
								</div>
								<div class="modal-header-search" style="border-top: 1px solid #f2f2f2" id="ffl-search-form">
									<input type="text" name="search" id="automaticffl-search-input" value="" placeholder="<?php echo esc_html( 'Zip Code, City or FFL' ); ?>">
									<select name="ffl_miles_search" id="automaticffl-search-miles" class="select-ffl dealers-modal-button">
										<option value="5"><?php echo esc_html( '5 Miles' ); ?></option>
										<option value="10"><?php echo esc_html( '10 Miles' ); ?></option>
										<option value="30"><?php echo esc_html( '30 Miles' ); ?></option>
										<option value="75"><?php echo esc_html( '75 Miles' ); ?></option>
									</select>
									<button type="button" id="automaticffl-search-button" value="12" class="button alt ffl-search-button"></button>
								</div>
								<div class="modal-header-search">
									<p id="ffl-searching-message"><?php echo esc_html( 'Looking for dealers, please wait...' ); ?></p>
									<p id="ffl-results-message"></p><span id="toggle-map-text" class="hidden show-text-map"><?php echo esc_html( 'View map' ); ?></span>
									<div id="ffl-searching-error-message">
										<div class="woocommerce">
											<div class="woocommerce-error" role="alert">
												<?php echo esc_html( 'An error has ocurred. Please, try again later.' ); ?>
											</div>
										</div>
									</div>
								</div>
								<div class="modal-header-search show-list" id="search-result-list">
								</div>
							</div>
						</div>
						<div id="map-toggle" class="hide-map">
							<div class="inner-toggle hide-map">
								<span id="toggle-map-text-label" class="show-text-map-label"><?php echo esc_html( 'view map' ); ?></span>
								<i class="fa-icon fas fa-angle-double-up"></i>
							</div>
							<div class="automaticffl-map" id="automaticffl-map">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div id="automaticffl-dealer-result-template">
				<div class="ffl-single-result{{dealer-preferred}}" id="ffl-single-result{{dealer-index}}">
					<div class="ffl-preferred-header">
						<img  alt="<?php echo esc_html( 'Preferred Dealer' ); ?>" src="<?php echo esc_url( affl()->get_plugin_url() ); ?>/assets/images/icons/preferred.png">
					</div>
					<div class="ffl-result-body">
						<p class="dealer-name">{{dealer-name}}</p>
						<p class="dealer-address">{{dealer-address}}</p>
						<a href="tel:{{dealer-phone}}"><p class="dealer-phone dealer-phone-formatted">{{dealer-phone}}</p></a>
					</div>
					<div class="ffl-result-number">
						<div class="ffl-result-count">{{result-number}}</div>
					</div>
					<button type="button" class="automaticffl-select-button" id="automaticffl-select-button{{dealer-index}}">Select this dealer</button>
				</div>
			</div>
			<div id="automaticffl-popup-template">
				<div id="automaticffl-marker-modal{{dealer-index}}" class="automaticffl-marker-popup">
					<h2 class="heading" >{{dealer-name}}</h2>
					<div class="body-content">
						<p>{{dealer-address}}</p>
						<p><b><?php echo esc_html( 'Phone Number' ); ?>: </b><a href="tel:{{dealer-phone}}"><span class="dealer-phone dealer-phone-formatted">{{dealer-phone}}</span></a></p>
						<p><a id="automaticffl-select-dealer-link" href="#" class="automaticffl-select-dealer-link"><?php echo esc_html( 'Select this dealer' ); ?></a>
						</p>
					</div>
				</div>
			</div>
			<div id="automaticffl-popup-container">
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
	 * Get Javascript that handles the map experience
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function get_js() {
		$user_name = self::get_user_name();
		?>
		<script>
			// Set Google Maps API Key
			window.automaticffl_google_maps_api_key = '<?php echo esc_js( Config::get_google_maps_api_key() ); ?>';
			class AutomaticFflMap {
				constructor() {
					this.mapMarkersList = [];
					this.mapPositionsList = [];
					this.fflResults = [];
					this.currentFflItemId = null;
					this.googleMap = null;
					this.storeHash = '<?php echo esc_js( Config::get_store_hash() ); ?>';
					this.purpleMarker = '<?php echo esc_url( affl()->get_plugin_url() ); ?>/assets/images/icons/purple-marker.png';
					this.preferredMarker = '<?php echo esc_url( affl()->get_plugin_url() ); ?>/assets/images/icons/preferred-marker.png';
					this.fflApiUrl = '<?php echo esc_js( Config::get_ffl_dealers_url() ); ?>';
					this.fflResultsTemplate = '<?php echo esc_html( '{{results-count}} results have been found for {{search-string}}' ); ?>';
					this.fflNoResultsTemplate = '<?php echo esc_html( 'No dealers have been found for "{{search-string}}"' ); ?>';
					this.currentInfowindow = false;

					this.initMap();
					this.bindEvents();
				}

				bindEvents() {
					self = this;

					// Search Button on Dalers modal
					jQuery('#automaticffl-search-button').click(() => {
						self.getDealers();
					});

					jQuery('#automaticffl-search-input').keypress(function(e) {
						if(e.which == 13) {
							self.getDealers();
						}
					});

					// Closes the modal when Escape button is pressed.
					jQuery('body').keydown((e) => {
						var hidden = jQuery('.automaticffl-dealer-layer');
						if (hidden.hasClass('visible')) {
							if(e.which == 27) {
								jQuery('body').removeAttr('style');
								this.toggleDealers();
							}
						}
					});

					// Sets the boundaries for the modal and main layer.
					const target = document.querySelector('.modal-container')
					const layer = document.querySelector('.automaticffl-dealer-layer');

					// Add a eventListener 'click' and closes if the click is inside the layer but out of the modal boundaries.
					layer.addEventListener('click', (event) => {
					const withinBoundaries = event.composedPath().includes(target)

					if (layer.classList.contains('visible')) {
						if (!withinBoundaries) {
							jQuery('body').removeAttr('style');
							this.toggleDealers();
						}
					}
					});

					// Find a Dealer button on Checkout
					jQuery('#automaticffl-select-dealer').click(() => {
						jQuery('body').attr('style', 'overflow-y: hidden;');
						self.toggleDealers();
					});

					// Close button on mobile modal
					jQuery('#automaticffl-close-modal-button').click(() => {
						jQuery('body').removeAttr('style');
						self.toggleDealers();
					});

					// Toggle map on text or bottom bar
					jQuery('#toggle-map-text, .inner-toggle').click( function () {
						self.mapToggle();
					});
				}
				formatPhone() {
					jQuery('.dealer-phone-formatted').text(function(dealer_phone, text) {
						return text.replace(/(\d{3})(\d{3})(\d{4})/, '($1)-$2-$3');
					});
				}
				mapToggle() {
						jQuery("#map-toggle, .inner-toggle").toggleClass("show-map hide-map");
						jQuery("#toggle-map-text-label").toggleClass("show-text-map-label hide-text-map-label");
						jQuery("#toggle-map-text").toggleClass("show-text-map hide-text-map");
						jQuery(".show-text-map-label, .show-text-map").html("View map");
						jQuery(".hide-text-map-label, .hide-text-map").html("Hide map");
						jQuery(".fa-icon").toggleClass("fa-angle-double-up fa-angle-double-down");
						jQuery("#search-result-list").toggleClass("show-list hide-list");
				}
				closeMap() {
					var map = jQuery('#map-toggle');
					if (map.hasClass('show-map')) {
						self.mapToggle();
					}
				}
				selectDealer(dealer) {
					var selectedDealer = this.fflResults[dealer];
					console.log(selectedDealer)

					// Set values to the hidden Shipping address fields
					// @TODO: Force customer to enter First and Last Name to use here instead of the dealer's business name
					jQuery('#shipping_first_name').val('<?php echo esc_html( $user_name['first_name'] ) ?>');
					jQuery('#shipping_last_name').val('<?php echo esc_html( $user_name['last_name'] ) ?>');
					jQuery('#shipping_company').val(selectedDealer.business_name);
					jQuery('#shipping_company').val(selectedDealer.business_name);
					jQuery('#ffl_license_field').val(selectedDealer.license);
					jQuery('#shipping_phone').val(selectedDealer.phone_number);
					jQuery('#shipping_country').val('US');
					jQuery('#shipping_state').val(selectedDealer.premise_state);
					jQuery('#shipping_address_1').val(selectedDealer.premise_street);
					jQuery('#shipping_city').val(selectedDealer.premise_city);
					jQuery('#shipping_postcode').val(selectedDealer.premise_zip);
					jQuery('#automaticffl-select-dealer').html("Change Dealer");
					jQuery('#automaticffl-dealer-selected').addClass('automaticffl-dealer-selected');
					return selectedDealer;
				}
				parseDealersResult(dealers) {
					var self = this;

					//Clear all markers
					self.removeMarkersFromMap();

					// Remove search results from the sidebar
					jQuery('#search-result-list').html(' ');

					if (dealers.length > 0) {
						const resultTemplate = document.getElementById('automaticffl-dealer-result-template').innerHTML;
						const dealerTemplate = document.getElementById('automaticffl-dealer-card-template').innerHTML;
						let mappedResult;

						dealers.forEach((dealer, index) => {

								mappedResult = {
									"{{dealer-name}}": dealer.business_name,
									"{{dealer-address}}": `${dealer.premise_street}, ${dealer.premise_city}, ${dealer.premise_state}`,
									"{{dealer-license}}": dealer.license,
									"{{dealer-phone}}": dealer.phone_number,
									"{{result-number}}": index + 1,
									"{{dealer-preferred}}": dealer.preferred ? ' preferred' : '',
									"{{dealer-index}}": index,
								};

								jQuery('#search-result-list').append(self.formatTemplate(resultTemplate, mappedResult));
								dealer.id = index;
								dealer.icon_url = dealer.preferred ? self.preferredMarker : self.purpleMarker;

								self.addMarker(dealer, mappedResult, index);

								// Open map popup when clicking on card results
								jQuery('#ffl-single-result' + index).click(() => {
									var self = this;
									jQuery(`div[aria-label="${index + 1}"]`).trigger('click');

								});

								// Select the dealer when clicking on the 'Select this dealer' button
								jQuery('#automaticffl-select-button' + index).click(() => {
									self.selectDealer(index);
									jQuery('#automaticffl-dealer-selected').empty();
									jQuery('#automaticffl-dealer-selected').append(self.formatTemplate(dealerTemplate, {
										"{{dealer-name}}": dealer.business_name,
										"{{dealer-address}}": `${dealer.premise_street}, ${dealer.premise_city}, ${dealer.premise_state}`,
										"{{dealer-license}}": dealer.license,
										"{{dealer-phone}}": dealer.phone_number,
									}));
									jQuery('body').removeAttr("style");
									self.toggleDealers();
									self.formatPhone();
								});
						});

						// Show results message
						jQuery('#ffl-results-message').html(self.formatTemplate(self.fflResultsTemplate, {
							'{{results-count}}': dealers.length,
							'{{search-string}}': jQuery('#automaticffl-search-input').val()
						})).show();
						if(window.outerWidth < 800) {
							jQuery("#toggle-map-text").removeClass("hidden");
						}
						self.fflResults = dealers;
						self.centerMap();
					} else {
						// Show 0 results message
						jQuery('#ffl-results-message').html(self.formatTemplate(self.fflNoResultsTemplate, {
							'{{search-string}}': jQuery('#automaticffl-search-input').val()
						})).show();
					}

					jQuery('#ffl-searching-message').hide();

					// Format Phone Numbers on Search Results
					self.formatPhone();
				}
				addPopupToMarker(marker, mappedResult, dealerId) {
					var self = this;

					const dealerTemplate = document.getElementById('automaticffl-dealer-card-template').innerHTML;

					// Get marker popup template
					const contentString = document.getElementById('automaticffl-popup-template').innerHTML;

					// Remove from DOM in case it has been previously added
					jQuery('#automaticffl-marker-modal' + dealerId).remove();

					// Add popup to DOM so we can use later
					jQuery('#automaticffl-popup-container').append(self.formatTemplate(contentString, mappedResult));
					var domElement = document.getElementById('automaticffl-marker-modal' + dealerId);

					// Create popup and add to marker
					const infowindow = new google.maps.InfoWindow({
						content: domElement,
					});
					marker.addListener('click', () => {
						if (self.currentInfowindow) {
							self.currentInfowindow.close();
						}
						self.currentInfowindow = infowindow;
						infowindow.open({
							anchor: marker,
							map: self.googleMap,
							shouldFocus: false,
						});
					});

					// Select dealer when the link is clicked
					jQuery('#automaticffl-marker-modal' + dealerId + ' .automaticffl-select-dealer-link').click(() => {
						const selectedDealer = self.selectDealer(dealerId);
						jQuery('#automaticffl-dealer-selected').empty();
						jQuery('#automaticffl-dealer-selected').append(self.formatTemplate(dealerTemplate, {
							"{{dealer-name}}": selectedDealer.business_name,
							"{{dealer-address}}": `${selectedDealer.premise_street}, ${selectedDealer.premise_city}, ${selectedDealer.premise_state}`,
							"{{dealer-license}}": selectedDealer.license,
							"{{dealer-phone}}": selectedDealer.phone_number,
						}));
						jQuery('body').removeAttr("style");
						self.toggleDealers();
						self.formatPhone();
					});
				}
				addMarker(dealer, mappedResult, zIndex) {
					var self = this;
					var marker = new google.maps.Marker({
						position: {lat: dealer.lat, lng: dealer.lng},
						zIndex,
						map: self.googleMap,
						label: {
							text: (dealer.id + 1).toString(),
							color: 'white'
						},
						icon: {
							url: dealer.icon_url,
							labelOrigin: new google.maps.Point(33, 20)
						},
					});

					this.addPopupToMarker(marker, mappedResult, dealer.id);
					this.mapMarkersList.push(marker);
					self.mapPositionsList.push(new google.maps.LatLng(dealer.lat, dealer.lng));
				}

				initMap() {
					const myLatLng = {lat: 40.363, lng: -95.044};
					this.googleMap = new google.maps.Map(document.getElementById("automaticffl-map"), {
						zoom: 4,
						center: myLatLng,
						mapTypeControlOptions: {
							mapTypeIds: []
						},
						fullscreenControl: false,
						panControl: false,
						streetViewControl: false,
						mapTypeId: 'roadmap',
						styles: [{"featureType":"administrative","elementType":"labels.text.fill","stylers":[{"color":"#444444"}]},{"featureType":"landscape","elementType":"all","stylers":[{"color":"#f2f2f2"}]},{"featureType":"poi","elementType":"all","stylers":[{"visibility":"off"}]},{"featureType":"road","elementType":"all","stylers":[{"saturation":-100},{"lightness":45}]},{"featureType":"road.highway","elementType":"all","stylers":[{"visibility":"simplified"}]},{"featureType":"road.arterial","elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"featureType":"transit","elementType":"all","stylers":[{"visibility":"off"}]},{"featureType":"water","elementType":"all","stylers":[{"color":"#e3e3fb"},{"visibility":"on"}]}]
					});

					//@TODO: Setup dealer popup message on the map (same as the Magento extension)
				}

				centerMap () {
					var self = this;
					var bounds = new google.maps.LatLngBounds();

					for (var i = 0, LtLgLen = self.mapPositionsList.length; i < LtLgLen; i++) {
						bounds.extend(self.mapPositionsList[i]);
					}
					self.googleMap.fitBounds(bounds);
				}

				formatTemplate (str, mapObj) {
					var re = new RegExp(Object.keys(mapObj).join("|"),"gi");

					return str.replace(re, function(matched){
						return mapObj[matched.toLowerCase()];
					});
				}

				/**
				 * Retrieve a list of FFL dealers
				 */
				getDealers () {
					var self = this;
					var searchString = jQuery('#automaticffl-search-input').val();
					var searchRadius = jQuery('#automaticffl-search-miles').val();

					// Display searching message
					// @TODO: could be replaced by an animated gif
					jQuery('#ffl-searching-message').show();

					// Hide results message
					jQuery('#ffl-results-message').hide();

					// Hide error message
					jQuery('#ffl-searching-error-message').hide();

					jQuery.ajax({
						url: self.fflApiUrl + '?location=' + searchString + '&radius=' + searchRadius,
						headers: {"store-hash": self.storeHash, "origin": window.location.origin},
						success: function (results) {
							self.parseDealersResult(results.dealers);
						},
						error: function (result) {
							jQuery('#ffl-searching-message').hide();
							jQuery('#ffl-searching-error-message').show();
						}
					});
				}

				/**
				 * Remove all markers from the map
				 */
				removeMarkersFromMap() {
					var self = this;

					//Clear all markers
					for (var i = 0; i < self.mapMarkersList.length; i++) {
						self.mapMarkersList[i].setMap(null);
					}

					// Clear all positions
					self.mapPositionsList = [];
				}
				toggleDealers() {
					self.closeMap();
					var hidden = jQuery('.automaticffl-dealer-layer');
					if (hidden.hasClass('visible')) {
						hidden.animate({"left": "100%"}, "slow").removeClass('visible');
					} else {
						hidden.animate({"left": "0"}, "slow").addClass('visible');
					}
				}
			}

			// Callback function for initializing the Maps API
			function initMap() {
				const automaticFFL = new AutomaticFflMap();
			}

			var mapsScript = document.createElement('script');
			mapsScript.async = true;

			// Load Maps API and execute callback function
			mapsScript.src = `https://maps.googleapis.com/maps/api/js?key=${automaticffl_google_maps_api_key}&callback=initMap`;
			document.getElementsByTagName('script')[0].parentNode.appendChild(mapsScript);
		</script>
		<?php
	}

}
