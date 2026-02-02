<?php
/**
 * Thank You Page View
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Views;

defined( 'ABSPATH' ) || exit;

use RefactoredGroup\AutomaticFFL\Helper\Saved_Cart;

/**
 * Class Thank_You.
 *
 * Handles the thank you page display when there are saved items
 * that need to be restored after checkout.
 *
 * @since 1.0.14
 */
class Thank_You {

	/**
	 * Display redirect notice if there are saved items to restore.
	 *
	 * Called on woocommerce_before_thankyou hook (top of page).
	 *
	 * @since 1.0.14
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function maybe_show_redirect_notice( $order_id ) {
		// Get token from order meta (most reliable method).
		$token = Saved_Cart::get_token_from_order( intval( $order_id ) );

		if ( ! $token ) {
			return; // No saved items for this order.
		}

		// Verify items still exist in transient.
		$saved_data = Saved_Cart::get_saved_items_by_token( $token );
		if ( ! $saved_data || empty( $saved_data['items'] ) ) {
			return;
		}

		// Restore items to the cart immediately (server-side).
		// This ensures items are safe even if the customer navigates away.
		$result = Saved_Cart::restore_items( $saved_data );

		// Clean up the transient now that items are restored.
		Saved_Cart::clear_saved_items_by_token( $token );

		$cart_url = wc_get_cart_url();
		$type     = $result['success'] ? 'success' : 'error';
		$icon     = Cart::get_notice_icon( $type );

		?>
		<div class="automaticffl-notice automaticffl-notice-<?php echo esc_attr( $type ); ?> automaticffl-redirect-notice" role="alert">
			<span class="automaticffl-notice-icon">
				<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<div class="automaticffl-notice-content">
				<p class="automaticffl-notice-message"><?php echo esc_html( $result['message'] ); ?></p>
				<?php if ( ! empty( $result['failed'] ) ) : ?>
					<ul class="automaticffl-notice-failed-list">
						<?php foreach ( $result['failed'] as $item ) : ?>
							<li><?php echo esc_html( $item['product_name'] . ': ' . $item['reason'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $result['success'] ) : ?>
					<p class="automaticffl-redirect-action">
						<a href="<?php echo esc_url( $cart_url ); ?>" class="button"><?php esc_html_e( 'Go to Cart', 'automaticffl-for-wc' ); ?></a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
