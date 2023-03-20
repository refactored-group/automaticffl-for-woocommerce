<?php
/**
 * FFL for WooCommerce Plugin
 * @author    Refactored Group
 * @copyright Copyright (c) 2023
 * @license   @TODO: Find appropriate license
 */

namespace RefactoredGroup\AutomaticFFL\Views;

defined( 'ABSPATH' ) or exit;

/**
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
        $cart = WC()->cart->get_cart();
        $total_products = count($cart);
        $total_ffl = 0;
        foreach ( $cart as $product ) {
            foreach ( $product['data']->get_attributes() as $attribute ) {
                if ( $attribute['name'] == 'pa_ffl-required') {
                    $total_ffl++;
                }
            }
        }

        if ( $total_ffl > 0 && $total_ffl < $total_products ) :
            ?>
            <div class="woocommerce">
                <div class="woocommerce-error" role="alert">
                    <?php echo __( 'You can not checkout with a mixed cart. Please remove all items from your cart that need to be shipped to a Dealer or the items that do not.' ); ?>
                </div>
            </div>
            <style>
                .wc-proceed-to-checkout {
                    display: none;
                }
            </style>
        <?php
        endif;
    }
}
