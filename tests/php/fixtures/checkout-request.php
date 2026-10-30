<?php
/** Isolated request contexts for the classic checkout redirect. */

namespace RefactoredGroup\AutomaticFFL\Helper {

	class Cart_Analyzer {
		public function has_api_error() { return 'unavailable' === $GLOBALS['cart_kind']; }
		public function is_firearms_regular_mixed() { return 'firearm-mixed' === $GLOBALS['cart_kind']; }
		public function is_ammo_regular_mixed() { return 'ammo-mixed' === $GLOBALS['cart_kind']; }
		public function requires_ffl_for_state( $state ) { return 'CA' === $state; }
	}
}

namespace {
	if ( PHP_SAPI !== 'cli' ) { exit( 2 ); }

	define( 'ABSPATH', __DIR__ );
	$context = $argv[1];
	$GLOBALS['cart_kind'] = $argv[2];
	$GLOBALS['ammo_state'] = isset( $argv[3] ) ? $argv[3] : '';
	define( 'REST_REQUEST', 'rest' === $context );
	define( 'DOING_AJAX', in_array( $context, array( 'ajax', 'checkout-ajax' ), true ) );
	function is_admin() { return 'admin' === $GLOBALS['context']; }
	function wp_doing_ajax() { return DOING_AJAX; }
	function is_cart() { return 'cart' === $GLOBALS['context']; }
	function is_checkout() { return in_array( $GLOBALS['context'], array( 'checkout', 'checkout-ajax' ), true ); }
	function wc_get_cart_url() { return '/cart/'; }
	function wp_safe_redirect( $url ) { throw new \RuntimeException( $url ); }
	function wc_add_notice( $message, $type ) {}
	function __( $message, $domain ) { return $message; }
	function WC() {
		return (object) array( 'session' => new class {
			public function get( $key, $default = '' ) { return $GLOBALS['ammo_state']; }
		} );
	}

	require dirname( __DIR__, 3 ) . '/includes/views/class-checkout.php';
	try {
		\RefactoredGroup\AutomaticFFL\Views\Checkout::verify_mixed_cart();
		echo 'continue';
	} catch ( \RuntimeException $error ) {
		echo 'redirect:' . $error->getMessage();
	}
}
