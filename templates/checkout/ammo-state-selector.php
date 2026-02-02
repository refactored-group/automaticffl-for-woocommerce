<?php
/**
 * Ammo State Selector Template
 *
 * This template displays the ammo checkout UI on the classic checkout page.
 * Instead of a custom state selector, it uses the standard WooCommerce shipping
 * form's state field to determine FFL requirements (matching block checkout behavior).
 *
 * @package AutomaticFFL
 * @since 1.0.15
 *
 * Available variables:
 * @var string $iframe_url      The URL for the dealer selection iframe (or false if not configured).
 * @var array  $user_name       Array with 'first_name' and 'last_name' keys.
 * @var bool   $is_configured   Whether FFL dealer selection is configured.
 * @var array  $us_states       Array of US states (code => name).
 * @var array  $messages        Centralized messages from Messages class.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="automaticffl-ammo-checkout">
	<h3 id="automaticffl-shipping-heading"><?php esc_html_e( 'Shipping details', 'automaticffl-for-wc' ); ?></h3>
	<div id="automaticffl-state-message">
		<div class="woocommerce">
			<div class="woocommerce-info" role="alert">
				<?php echo esc_html( $messages['ammoSelectState'] ); ?>
			</div>
		</div>
	</div>

	<div id="automaticffl-ffl-container" style="display:none;">
		<?php if ( $is_configured ) : ?>
			<div id="automaticffl-dealer-selected"></div>
			<button type="button" id="automaticffl-select-dealer"
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
					<?php echo esc_html( $messages['fflNotConfigured'] ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
