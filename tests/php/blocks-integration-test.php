<?php
/** Dealer block placement regression coverage. */
use RefactoredGroup\AutomaticFFL\Blocks\Blocks_Integration;

require_once __DIR__ . '/fixtures/blocks-interface.php';
require_once dirname( __DIR__, 2 ) . '/includes/blocks/class-blocks-integration.php';
defined( '_AFFL_LOADER_' ) || define( '_AFFL_LOADER_', dirname( __DIR__, 2 ) . '/automaticffl-for-woocommerce.php' );

function register_block_type( $path, $args = array() ) {
	$GLOBALS['affl_registered_block'] = array_merge( json_decode( file_get_contents( $path . '/block.json' ), true ), $args );
}

affl_test( 'dealer block uses address normally and options only for billing-only stores', function () {
	$integration = new Blocks_Integration();
	$register = new ReflectionMethod( $integration, 'register_dealer_selection_block_type' );
	if ( PHP_VERSION_ID < 80100 ) {
		$register->setAccessible( true );
	}
	foreach ( array( 'shipping', 'billing', 'billing_only' ) as $policy ) {
		$GLOBALS['affl_options']['woocommerce_ship_to_destination'] = $policy;
		$register->invoke( $integration );
		$block = $GLOBALS['affl_registered_block'];
		$parent = 'billing_only' === $policy ? 'woocommerce/checkout-shipping-methods-block' : 'woocommerce/checkout-shipping-address-block';
		affl_assert_same( array( $parent ), $block['parent'] );
		affl_assert_same( 'automaticffl/dealer-selection-shipping', $block['name'] );
		affl_assert_same( $policy, get_option( 'woocommerce_ship_to_destination' ), 'Registration must not alter merchant policy.' );
	}
} );
