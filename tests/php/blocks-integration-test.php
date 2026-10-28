<?php
/**
 * WooCommerce Blocks integration regression coverage.
 *
 * @package AutomaticFFL
 */

$plugin_root = dirname( __DIR__, 2 );

affl_test(
	'blocks dealer selection stays mounted for billing-only shipping',
	function () use ( $plugin_root ) {
		$metadata_path = $plugin_root . '/assets/js/blocks/dealer-selection/block.json';
		$bundle_path   = $plugin_root . '/build/dealer-selection-frontend.js';
		$metadata      = json_decode( file_get_contents( $metadata_path ), true );
		$bundle        = file_get_contents( $bundle_path );

		affl_assert_same(
			array( 'woocommerce/checkout-shipping-methods-block' ),
			$metadata['parent'],
			'The forced block must use the shipping-options area that remains mounted in billing-only mode.'
		);
		affl_assert_contains(
			'woocommerce/checkout-shipping-methods-block',
			$bundle,
			'The built frontend bundle must match block.json.'
		);
	}
);
