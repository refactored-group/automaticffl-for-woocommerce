<?php
/**
 * FFL for WooCommerce Plugin
 * @author    Refactored Group
 * @copyright Copyright (c) 2023
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

namespace RefactoredGroup\AutomaticFFL\Views;

defined('ABSPATH') or exit;

use RefactoredGroup\AutomaticFFL\Helper\Config;

/**
 * @since 1.0.0
 */
class Checkout
{
    /**
     * Verifies if there are FFL products with regular products in the shopping cart.
     * If there are any, redirects customer back to the Cart page.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public static function verify_mixed_cart()
    {
        $cart = WC()->cart->get_cart();
        $total_products = count($cart);
        $total_ffl = 0;
        foreach ($cart as $product) {
            foreach ($product['data']->get_attributes() as $attribute) {
                if ($attribute['name'] == Config::FFL_ATTRIBUTE_NAME) {
                    $total_ffl++;
                }
            }
        }

        if ($total_ffl > 0 && $total_ffl < $total_products) {
            // Redirect back to the cart where the error message will be displayed
            wp_redirect(wc_get_cart_url());
        }
    }

    /**
     *
     * @return void
     * @since 1.0.0
     */
    public static function get_map()
    {
        ?>
        <div class="woocommerce">
            <div class="woocommerce-info" role="alert">
                <?php echo __('You have a firearm in your cart and must choose a Licensed Firearm Dealer (FFL) for the Shipping Address.'); ?>
            </div>
        </div>
        <div id="automaticffl-dealer-selected">
        </div>
        <button type="button" id="automaticffl-select-dealer" value="12"
                class="button alt wp-element-button fa-search ffl-search-button"><?php echo __('Find a Dealer'); ?></button>
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
                <span id="close-modal-button" class="w3-button w3-display-topright">
                    <i class="fas fa-times"></i>
                </span>
                <div class="modal-container">
                    <div class="modal-items">
                        <div class="ffl-search-results">
                            <div class="modal-header-container show-list">
                                <div class="modal-header-logo">
                                    <img src="<?php echo esc_url( WCFFL()->get_plugin_url() ); ?>/assets/images/logo-grey.png">
                                </div>
                                <div class="modal-header-search" style="border-top: 1px solid #f2f2f2" id="ffl-search-form">
                                    <input type="text" name="search" id="automaticffl-search-input" value="" placeholder="<?php echo __( 'Zip Code, City or FFL' ); ?>">
                                    <select name="ffl_miles_search" id="automaticffl-search-miles" class="select-ffl dealers-modal-button">
                                        <option value="5"><?php echo __( '5 Miles' ); ?></option>
                                        <option value="10"><?php echo __( '10 Miles' ); ?></option>
                                        <option value="30"><?php echo __( '30 Miles' ); ?></option>
                                        <option value="75"><?php echo __( '75 Miles' ); ?></option>
                                    </select>
                                    <button type="button" id="automaticffl-search-button" value="12" class="button alt ffl-search-button"></button>
                                </div>
                                <div class="modal-header-search">
                                    <p id="ffl-searching-message"><?php echo __( 'Looking for dealers, please wait...' ); ?></p>
                                    <p id="ffl-results-message"></p><span id="toggle-map-text" class="hidden show-text-map"><?php echo __( 'View map' ); ?></span>
                                    <div id="ffl-searching-error-message">
                                        <div class="woocommerce">
                                            <div class="woocommerce-error" role="alert">
                                                <?php echo __( 'An error has ocurred. Please, try again later.' ); ?>
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
                                <span id="toggle-map-text-label" class="show-text-map-label"><?php echo __( 'view map' ); ?></span>
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
                        <img  alt="<?php echo __( 'Preferred Dealer' ); ?>" src="<?php echo esc_url( WCFFL()->get_plugin_url() ); ?>/assets/images/icons/preferred.png">
                    </div>
                    <div class="ffl-result-body">
                        <p class="dealer-name">{{dealer-name}}</p>
                        <p class="dealer-address">{{dealer-address}}</p>
                        <a href="tel:{{dealer-phone}}"><p class="dealer-phone dealer-phone-formatted">{{dealer-phone}}</p></a>
                        <p class="dealer-license">{{dealer-license}}</p>
                    </div>
                    <div class="ffl-result-number">
                        <div class="ffl-result-count">{{result-number}}</div>
                    </div>
                </div>
            </div>
            <div id="automaticffl-popup-template">
                <div id="automaticffl-marker-modal{{dealer-index}}" class="automaticffl-marker-popup">
                    <h2 class="heading" >{{dealer-name}}</h2>
                    <div class="body-content">
                        <p>{{dealer-address}}</p>
                        <p><b><?php echo __( 'Phone Number' ); ?>: </b><a href="tel:{{dealer-phone}}"><span class="dealer-phone dealer-phone-formatted">{{dealer-phone}}</span></a></p>
                        <p><b><?php echo __( 'License' ); ?>: </b>{{dealer-license}}</p>
                        <p><a id="automaticffl-select-dealer-link" href="#" class="automaticffl-select-dealer-link"><?php echo __( 'Select this dealer' ); ?></a>
                        </p>
                    </div>
                </div>
            </div>
            <div id="automaticffl-popup-container">
            </div>
        </div>
        <div id="automaticffl-dealer-card-template">
            <p><?php echo __( 'Your order will be shipped to' ); ?>:</p>
            <div id="ffl-selected-dealer" class="ffl-result-body">
                <p class="dealer-name">{{dealer-name}}</p>
                <p class="dealer-address">{{dealer-address}}</p>
                <a href="tel:{{dealer-phone}}"><p><span class="dealer-phone dealer-phone-formatted">{{dealer-phone}}</span></p></a>
                <p class="dealer-license">{{dealer-license}}</p>
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
        ?>
        <script>
            // Set Google Maps API Key
            window.automaticffl_google_maps_api_key = '<?php echo Config::get_google_maps_api_key(); ?>';
            class AutomaticFflMap {
                constructor() {
                    this.mapMarkersList = [];
                    this.mapPositionsList = [];
                    this.fflResults = [];
                    this.currentFflItemId = null;
                    this.googleMap = null;
                    this.storeHash = '<?php echo Config::get_store_hash(); ?>';
                    this.purpleMarker = '<?php echo esc_url( WCFFL()->get_plugin_url() ); ?>/assets/images/icons/purple-marker.png';
                    this.preferredMarker = '<?php echo esc_url( WCFFL()->get_plugin_url() ); ?>/assets/images/icons/preferred-marker.png';
                    this.fflApiUrl = '<?php echo Config::get_ffl_dealers_url(); ?>';
                    this.fflResultsTemplate = '<?php echo __( '{{results-count}} results have been found for {{search-string}}' ); ?>';
                    this.fflNoResultsTemplate = '<?php echo __( 'No dealers have been found for "{{search-string}}"' ); ?>';

                    this.initMap();
                    this.bindEvents();
                }

                bindEvents() {
                    self = this;

                    // Search Button on Dalers modal
                    jQuery('#automaticffl-search-button').click(() => {
                        self.getDealers();
                    });

                    // Find a Dealer button on Checkout
                    jQuery('#automaticffl-select-dealer').click(() => {
                        jQuery('body').attr('style', 'overflow-y: hidden;');
                        self.toggleDealers();
                    });

                    // Close button on mobile modal
                    jQuery('#close-modal-button').click(() => {
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

                    // Set values to the hidden Shipping address fields
                    // @TODO: Force customer to enter First and Last Name to use here instead of the dealer's business name
                    jQuery('#shipping_first_name').val(selectedDealer.business_name);
                    jQuery('#shipping_last_name').val('.');
                    jQuery('#shipping_country').val('US');
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

                            if (dealer.enabled === true) {
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

                                self.addMarker(dealer, mappedResult);

                                // Select dealer when clicking on th card result
                                jQuery('#ffl-single-result' + index).click(() => {
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
                            }
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
                addMarker(dealer, mappedResult) {
                    var self = this;
                    var marker = new google.maps.Marker({
                        position: {lat: dealer.lat, lng: dealer.lng},
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

    /**
     * CSS styles for the map experience
     *
     * @return void
     * @since 1.0.0
     */
    public static function get_css() {
        ?>
        <style>
            /* Search Results */
            .ffl-single-result .dealer-name {
                color: #666;
                margin-bottom: 0;
            }

            .ffl-single-result .dealer-address {
                color: #777;
                font-size: 12px;
                margin-bottom: 0;
            }

            .ffl-single-result .dealer-license {
                margin: 0;
                background: #f9f9f9;
                font-size: 11px;
                padding: 5px;
                text-align: center;
                color: #666;
            }

            #search-result-list {
                flex-flow: column;
                width: 100%;
                padding-right: 4%;
                margin-right: 1%;
                overflow-y: scroll;
                height: inherit;
                flex-direction: column;
                padding-left: 4%;
            }

            #search-result-list::-webkit-scrollbar {
                width: 10px;
                right: 5px;
            }

            /* Custom sidebar */
            #search-result-list::-webkit-scrollbar-track {
                background: #ccc;
                border: 4px solid transparent;
                background-clip: content-box;
            }

            /* Handle */
            #search-result-list::-webkit-scrollbar-thumb {
                background: #ccc;
                border: 1px solid #ccc;
                height: 50px;
            }

            /* Search Header and form */
            .modal-header-search {
                align-items: flex-start;
                display: flex;
                background-color: transparent;
                width: 80%;
                margin: auto;
            }

            .modal-header-search p {
                font-size: 13px;
                color: #999999;
            }

            #ffl-search-form {
                padding-bottom: 20px;
            }

            .modal-header-logo {
                align-items: center;
                display: flex;
                justify-content: center;
                background-color: transparent;
                flex-grow: 1;
                width: 100%;
                padding-top: 30px;
                padding-bottom: 20px;
            }

            .modal-header-logo img {
                max-width: 80%;
            }

            .ffl-search-button::before {
                content: "\f002";
                font-family: "Font Awesome 5 Free";
                font-weight: 900;
                color: white;
                padding-right: 10px;

            }

            #automaticffl-search-button {
                padding-top: 7px;
                border-radius: 0 10px 10px 0;
                background-color: #512a74;
                height: 40px;
            }

            #automaticffl-search-input, #automaticffl-search-miles {
                padding: 0.6180469716em;
                background-color: #f2f2f2;
                border: 0;
                height: 40px;
                box-sizing: border-box;
                font-weight: 400;
                box-shadow: inset 0 1px 1px rgb(0 0 0 / 13%);
                text-transform: uppercase;
                font-size: 12px;
                color: #767676;
                width: 30%;
            }

            #automaticffl-search-input {
                border-radius: 10px 0 0 10px;
                width: 60%;
            }

            #automaticffl-search-input::placeholder {
                font-size: 12px;
                text-transform: uppercase;
            }

            .automaticffl-map {
                align-items: center;
                color: rgb(255, 255, 255);
                display: flex;
                justify-content: center;
                background-color: #ffffff;
                width: 100%;
                height: 100%;
                flex-grow: 1;
                margin: 0;
                padding: 0;
            }

            .ffl-search-results {
                align-items: flex-start;
                display: flex;
                justify-content: center;
                background-color: transparent;
                width: 30%;
                height: 100%;
                flex-grow: 1;
                margin: 0;
                padding: 0;
                flex-direction: column;
            }

            #automaticffl-search-input:focus, #automaticffl-search-miles:focus {
                outline: 0 !important;
                border: 2px solid #7f54b3 !important;
            }

            #automaticffl-dealer-layer .modal-header-container {
                display: flex;
                background-color: transparent;
                width: 100%;
                height: 70%;
                margin: 0;
                padding: 0;
                flex: 1 1 0;
                flex-flow: row wrap;
                place-content: flex-start;
                align-items: flex-start;
                overflow-y: hidden;
            }

            #automaticffl-dealer-layer .modal-container {
                display: flex;
                height: 100%;
                border-radius: 50px 0 0 0;
            }

            #automaticffl-dealer-layer .modal-items {
                display: flex;
                flex: 1 1 0%;
                flex-flow: row wrap;
                place-content: stretch flex-start;
                align-items: stretch;
            }

            #ship-to-different-address-checkbox {
                display: none;
            }

            .shipping_address .woocommerce-shipping-fields__field-wrapper {
                display: none;
            }

            .automaticffl-dealer-layer {
                height: 100%;
            }

            .dealers-container {
                width: 90%;
                height: 100%;
                background-color: #ffffff;
                border-radius: 50px 0 0 0;
                left: 10%;
                position: absolute;
                z-index: 1001;
                display: block;
                box-shadow: rgba(0, 0, 0, 0.4) 0px 30px 90px;
                margin-top: 0%;
            }

            .automaticffl-dealer-layer {
                width: 100%;
                z-index: 1000;
                position: fixed;
                left: 100%;
                background: transparent;
                color: #000;
                height: 100%;
                top: 0;
                bottom: 0;
            }

            /** Results list **/
            .ffl-single-result {
                border-radius: 4px;
                width: 100%;
                padding: 10px 0 10px 20px;
                border: 1px solid #f2f2f2;
                border-right: 3px solid #cccccc;
                margin-bottom: 20px;
                height: auto;
                background: white;
            }

            .ffl-single-result:hover, .preferred {
                box-shadow: rgba(50, 50, 93, 0.25) 0px 50px 100px -20px, rgba(0, 0, 0, 0.3) 0px 30px 60px -30px;
                border-right: 3px solid #522a74;
                padding-right: 0;
                cursor: pointer;
            }

            .ffl-single-result:hover .ffl-result-count {
                background-color: #512a74;
            }

            .ffl-single-result .ffl-preferred-header {
                width: 20%;
                background-color: transparent;
                float: left;
            }

            .ffl-preferred-header img {
                display: none;
            }

            .preferred .ffl-preferred-header img {
                display: block;
            }

            .ffl-single-result .ffl-result-body {
                width: 70%;
                background-color: transparent;
                float: left;
                padding-left: 20px
            }

            .ffl-single-result .ffl-result-number {
                width: 10%;
                background-color: transparent;
                float: right;
            }

            .ffl-single-result .ffl-result-count {
                background: #cccccc;
                color: white;
                text-align: center;
                width: 100%;
                font-size: 20px;
                border-radius: 15px 0 0 15px;
            }

            .preferred .ffl-result-count {
                background: #522a74;
            }

            .automaticffl-dealer-selected {
                border: solid 2px #522a74;
                padding: 20px 20px 0px 20px;
                margin-bottom: 2.617924em;
                border-left: 0.6180469716em solid #522a74;
                color: #333333;
                border-radius: 2px;
            }

            .automaticffl-dealer-selected a {
                color: #333333;
            }

            /* Marker popup */
            .automaticffl-marker-popup p {
                color: #000000;
            }

            /* Hide templates and messages*/
            #automaticffl-popup-template,
            #automaticffl-dealer-result-template,
            #ffl-searching-message,
            #ffl-results-message,
            #ffl-searching-error-message,
            #automaticffl-popup-container,
            #automaticffl-dealer-card-template,
            .hidden,
            .inner-toggle,
            #close-modal-button {
                display: none;
            }
            #map-toggle {
                bottom: 0;
                margin: 0;
                padding: 0;
                height: 100%;
                width: 70%;
                position: relative;
            }
            @media screen and (max-width: 800px) {
                .dealers-container {
                    width: 100%;
                    left: 0;
                    margin-top: 0;
                    z-index: 10000;
                }
                .automaticffl-dealer-layer {
                    z-index: 10000;
                }
                #automaticffl-dealer-layer .modal-items {
                    flex: 1 1 100%;
                }
                .ffl-search-results {
                    width: 100%;
                    height: 90%;
                }
                .automaticffl-map {
                    height: 102%;
                    width: 100%;
                }
                #automaticffl-dealer-layer .modal-header-container {
                    height: 30%;
                    flex: 1 1 100%;
                }
                .modal-header-logo {
                    padding-top: 0;
                }
                .automaticffl-marker-popup .heading {
                    font-size: medium;
                }
                #search-result-list {
                    height: 50%;
                    padding-left: 5%;
                    padding-bottom: 5%;
                }
                .toggle-map-text {
                    display: flex;
                }
                span#toggle-map-text {
                    font-size: 13px;
                    width: 20%;
                }
                p#ffl-results-message, p#ffl-searching-message {
                    width: 75%;
                    margin-right: 5%;
                }
                .show-map {
                    animation: show-map 1s ease forwards;
                }
                .hide-map {
                    animation: hide-map 1s ease forwards;
                }
                #map-toggle {
                    bottom: -25%;
                    margin: 0;
                    padding: 0;
                    height: 80%;
                    width: 100%;
                    position: relative;
                    background-color: #512a74;
                }
                .inner-toggle {
                    height: 6%;
                    color: #ffffff;
                    text-align: center;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .inner-toggle #toggle-map-text-label {
                    text-transform: lowercase;
                }
                .inner-toggle .fa-icon {
                    padding-left: 10px;
                }
                #close-modal-button {
                    margin-left: 5%;
                    padding-top: 5%;
                    display: block;
                    color: #767676;
                }
                .hide-list {
                    animation: hide-list 1s ease forwards;
                }
                .show-list {
                    animation: show-list 1s ease forwards;
                }
                #automaticffl-search-input, #automaticffl-search-miles{
                    border-radius: 0;
                }
                @keyframes hide-map {
                    from {
                        bottom: 82%;
                    }
                    to {
                        bottom: 0;
                    }
                }
                @keyframes show-map {
                    from {
                        bottom: 0;
                    }
                    to {
                        bottom: 82%;
                    }
                }
                @keyframes hide-list {
                    from {
                        height: 75%;
                    }
                    to {
                        height: 30%;
                    }
                }
                @keyframes show-list {
                    from {
                        height: 30%;
                    }
                    to {
                        height: 75%;
                    }
                }
            }
        </style>
        <?php
    }
}
