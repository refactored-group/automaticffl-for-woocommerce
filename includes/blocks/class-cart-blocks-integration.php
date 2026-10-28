<?php
/**
 * Mixed-cart notices for the WooCommerce Cart Block.
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Blocks;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use RefactoredGroup\AutomaticFFL\Helper\US_States;

/** Cart-only assets and non-sensitive static settings. */
class Cart_Blocks_Integration implements IntegrationInterface {

	/** @return string */
	public function get_name() {
		return 'automaticffl-cart';
	}

	/** @return void */
	public function initialize() {
		$asset = require dirname( _AFFL_LOADER_ ) . '/build/cart-notice-frontend.asset.php';
		wp_register_script(
			'automaticffl-cart-notice',
			plugins_url( '/build/cart-notice-frontend.js', _AFFL_LOADER_ ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Generic filter also supports the plugin's WordPress 5.6 minimum.
		add_filter( 'render_block', array( $this, 'render_cart_notice_mount' ), 10, 2 );
	}

	/**
	 * Place our React root above the complete Cart Block, outside Woo's root.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block Parsed block data.
	 * @return string
	 */
	public function render_cart_notice_mount( $block_content, $block ) {
		if ( 'woocommerce/cart' !== ( $block['blockName'] ?? '' ) || '' === trim( $block_content ) || false !== strpos( $block_content, 'data-automaticffl-cart-notice' ) ) {
			return $block_content;
		}

		$classes = 'automaticffl-cart-notice-root';
		$align   = $block['attrs']['align'] ?? 'wide';
		if ( in_array( $align, array( 'wide', 'full' ), true ) ) {
			$classes .= ' align' . $align;
		}

		return '<div class="' . esc_attr( $classes ) . '" data-automaticffl-cart-notice></div>' . $block_content;
	}

	/** @return string[] */
	public function get_script_handles() {
		return array( 'automaticffl-cart-notice' );
	}

	/** @return string[] */
	public function get_editor_script_handles() {
		return array();
	}

	/** @return array */
	public function get_script_data() {
		return array( 'usStates' => US_States::get_all(), 'checkoutUrl' => wc_get_checkout_url() );
	}
}
