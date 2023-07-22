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
 * Class Cart.
 *
 * @since 1.0.0
 */
class Cart {

	/**
	 * Verifies if there are FFL products with regular products in the shopping cart.
	 * If there are, shows message and block the login
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function verify_mixed_cart() {
		$cart           = WC()->cart->get_cart();
		$total_products = count( $cart );
		$ffl_products   = array();
		$total_ffl      = 0;
		foreach ( $cart as $product ) {
			foreach ( $product['data']->get_attributes() as $attribute ) {
				if ( Config::FFL_ATTRIBUTE_NAME === $attribute['name'] ) {
					$ffl_products[] = $product['product_id'];
					$total_ffl++;
				}
			}
		}

		if ( $total_ffl > 0 && $total_ffl < $total_products ) :
			?>
			<script>
				window.ffl_products_in_cart = <?php echo wp_json_encode( $ffl_products ); ?>;
			</script>
			<div class="woocommerce">
				<div class="woocommerce-error" role="alert" id="ffl-mixed-cart-error">
					<?php echo esc_html( 'You can not checkout with a mixed cart. Please remove all items from your cart that need to be shipped to a Dealer or the items that do not.' ); ?>
				</div>
			</div>
			<style>
				.wc-proceed-to-checkout {
					display: none;
				}
			</style>
			<?php
			self::verify_cart_modified();
		endif;
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

					console.log(foundFfl);

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
