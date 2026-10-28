<?php
/** Cart Block live extension contract and session updates. */

use RefactoredGroup\AutomaticFFL\Blocks\Store_Api_Extension;
use RefactoredGroup\AutomaticFFL\Api\Restrictions_Client;

require_once dirname( __DIR__, 2 ) . '/includes/helper/class-cart-analyzer.php';
require_once dirname( __DIR__, 2 ) . '/includes/helper/class-us-states.php';
require_once dirname( __DIR__, 2 ) . '/includes/blocks/class-store-api-extension.php';
require_once __DIR__ . '/fixtures/blocks-interface.php';
require_once dirname( __DIR__, 2 ) . '/includes/blocks/class-cart-blocks-integration.php';

affl_test( 'cart notice mount precedes the full cart and inherits allowed alignment', function () {
	$integration = new \RefactoredGroup\AutomaticFFL\Blocks\Cart_Blocks_Integration();
	$cart_html   = '<div class="wp-block-woocommerce-cart alignwide"><div>Products</div><aside>Totals</aside></div>';
	foreach ( array( 'wide', 'full' ) as $align ) {
		$block  = array( 'blockName' => 'woocommerce/cart', 'attrs' => array( 'align' => $align ) );
		$output = $integration->render_cart_notice_mount( $cart_html, $block );
		affl_assert_contains( 'class="automaticffl-cart-notice-root align' . $align . '"', $output );
		affl_assert_true( strpos( $output, 'automaticffl-cart-notice-root' ) < strpos( $output, 'wp-block-woocommerce-cart' ) );
		affl_assert_same( $cart_html, substr( $output, -strlen( $cart_html ) ) );
		affl_assert_same( $output, $integration->render_cart_notice_mount( $output, $block ), 'Filtering twice must not duplicate the mount.' );
	}
} );

affl_test( 'cart notice mount leaves other blocks and empty output unchanged', function () {
	$integration = new \RefactoredGroup\AutomaticFFL\Blocks\Cart_Blocks_Integration();
	$html = '<div>Unrelated block</div>';
	foreach ( array( 'woocommerce/checkout', 'core/paragraph' ) as $name ) {
		affl_assert_same( $html, $integration->render_cart_notice_mount( $html, array( 'blockName' => $name ) ) );
	}
	affl_assert_same( '', $integration->render_cart_notice_mount( '', array( 'blockName' => 'woocommerce/cart' ) ) );
	$output = $integration->render_cart_notice_mount( $html, array( 'blockName' => 'woocommerce/cart', 'attrs' => array( 'align' => 'wide" onclick="evil' ) ) );
	affl_assert_false( false !== strpos( $output, 'onclick' ) );
} );

class AFFL_Test_Route_Exception extends RuntimeException {
	public function __construct( $code, $message, $status ) {
		parent::__construct( $message, $status );
	}
}
class_alias( 'AFFL_Test_Route_Exception', 'Automattic\WooCommerce\StoreApi\Exceptions\RouteException' );

class AFFL_Test_Cart {
	public $items = array();
	public function get_cart() {
		return $this->items;
	}
}

class AFFL_Test_Session {
	public $values = array();
	public function get( $key, $default = null ) {
		return isset( $this->values[ $key ] ) ? $this->values[ $key ] : $default;
	}
	public function set( $key, $value ) {
		$this->values[ $key ] = $value;
	}
}

function WC() {
	return $GLOBALS['affl_wc'];
}

function affl_cart_blocks_fixture( array $restrictions = array() ) {
	$GLOBALS['affl_wc'] = (object) array( 'cart' => new AFFL_Test_Cart(), 'session' => new AFFL_Test_Session() );
	WC()->cart->items = array(
		'a' => array( 'product_id' => 1, 'quantity' => 3 ),
		'b' => array( 'product_id' => 2, 'quantity' => 2 ),
	);
	$GLOBALS['affl_products'][1] = new AFFL_Test_Product( 1 );
	$GLOBALS['affl_products'][2] = new AFFL_Test_Product( 2 );
	$GLOBALS['affl_options']['wc_ffl_store_hash'] = 'test-hash';
	$GLOBALS['affl_options']['wc_ffl_sandbox_mode'] = '0';
	$client = new Restrictions_Client();
	$client->clear_cache();
	$GLOBALS['affl_http_queue'] = array( affl_http_response( 200, array( 'schema_version' => 1, 'restrictions' => $restrictions ) ) );
	return new Store_Api_Extension();
}

affl_test( 'cart extension reflects classification and quantities on each response', function () {
	$extension = affl_cart_blocks_fixture( array( array( 'id' => 1 ) ) );
	$data = $extension->get_cart_schema_data();
	affl_assert_true( $data['isApiAvailable'] );
	affl_assert_true( $data['isFirearmsRegularMixed'] );
	affl_assert_false( $data['isAmmoRegularMixed'] );
	affl_assert_same( 3, $data['fflItemCount'] );
	affl_assert_same( 2, $data['regularItemCount'] );
	WC()->cart->items['a']['quantity'] = 6;
	affl_assert_same( 6, $extension->get_cart_schema_data()['fflItemCount'] );
	unset( WC()->cart->items['b'] );
	$GLOBALS['affl_http_queue'][] = affl_http_response( 200, array( 'schema_version' => 1, 'restrictions' => array( array( 'id' => 1 ) ) ) );
	$data = $extension->get_cart_schema_data();
	affl_assert_false( $data['isFirearmsRegularMixed'] );
	affl_assert_same( 0, $data['regularItemCount'] );
} );

affl_test( 'cart extension preserves confirmed ammunition state and restrictions', function () {
	$extension = affl_cart_blocks_fixture( array( array( 'id' => 1, 'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'CA', 'NY' ) ) ) ) ) );
	$extension->update_cart_state( array( 'state' => 'CA' ) );
	$data = $extension->get_cart_schema_data();
	affl_assert_true( $data['isAmmoRegularMixed'] );
	affl_assert_same( array( 'CA', 'NY' ), $data['ammoRestrictedStates'] );
	affl_assert_same( 'CA', $data['selectedAmmoState'] );
	$extension->update_cart_state( array( 'state' => 'UT' ) );
	affl_assert_same( 'UT', $extension->get_cart_schema_data()['selectedAmmoState'] );
	$extension->update_cart_state( array( 'state' => '' ) );
	affl_assert_same( '', WC()->session->get( 'automaticffl_ammo_state' ) );
} );

affl_test( 'cart state rejects invalid data without changing the session', function () {
	$extension = affl_cart_blocks_fixture();
	WC()->session->set( 'automaticffl_ammo_state', 'CA' );
	foreach ( array( array(), array( 'state' => 'XX' ), array( 'state' => 'ca' ), array( 'state' => array( 'UT' ) ), array( 'state' => null ), 'UT' ) as $input ) {
		try {
			$extension->update_cart_state( $input );
			affl_fail( 'Invalid state accepted.' );
		} catch ( AFFL_Test_Route_Exception $error ) {
			affl_assert_same( 'CA', WC()->session->get( 'automaticffl_ammo_state' ) );
		}
	}
	WC()->session = null;
	try {
		$extension->update_cart_state( array( 'state' => 'UT' ) );
		affl_fail( 'Missing session accepted.' );
	} catch ( AFFL_Test_Route_Exception $error ) {
		affl_assert_contains( 'session', strtolower( $error->getMessage() ) );
	}
} );

affl_test( 'empty and unavailable cart classifications do not claim mixed contents', function () {
	$extension = affl_cart_blocks_fixture();
	WC()->cart->items = array();
	$data = $extension->get_cart_schema_data();
	affl_assert_false( $data['isFirearmsRegularMixed'] );
	affl_assert_same( 0, $data['fflItemCount'] );
	$extension = affl_cart_blocks_fixture();
	$GLOBALS['affl_http_queue'] = array( affl_http_response( 500, '' ) );
	$data = $extension->get_cart_schema_data();
	affl_assert_false( $data['isApiAvailable'] );
	affl_assert_false( $data['isAmmoRegularMixed'] );
	affl_assert_false( $data['isFirearmsRegularMixed'] );
} );

affl_test( 'cart schema is read-only and leaves checkout dealer fields unchanged', function () {
	$extension = new Store_Api_Extension();
	$schema = $extension->get_cart_schema();
	foreach ( $schema as $field ) {
		affl_assert_true( $field['readonly'] );
	}
	affl_assert_same( 0, $schema['fflItemCount']['minimum'] );
	affl_assert_same( 'array', $schema['ammoRestrictedStates']['type'] );
	affl_assert_true( isset( $extension->get_schema()['fflDealerId'] ) );
	affl_assert_false( isset( $extension->get_schema()['isFirearmsRegularMixed'] ) );
} );

affl_test( 'REST cart updates do not run the classic checkout page redirect', function () {
	$file = dirname( __DIR__, 2 ) . '/includes/views/class-checkout.php';
	$code = 'define("ABSPATH", __DIR__); define("REST_REQUEST", true); function is_admin() { return false; } require ' . var_export( $file, true ) . '; \\RefactoredGroup\\AutomaticFFL\\Views\\Checkout::verify_mixed_cart(); echo "cart update continues";';
	$output = array();
	$status = 0;
	exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1', $output, $status );
	affl_assert_same( 0, $status, implode( "\n", $output ) );
	affl_assert_same( 'cart update continues', implode( "\n", $output ) );
} );

affl_test( 'Store API rejects a firearm mixed order even if a dealer is supplied', function () {
	$extension = affl_cart_blocks_fixture( array( array( 'id' => 1 ) ) );
	$order = new class {
		public function get_meta( $key ) { return 'fixture-dealer-license'; }
	};
	try {
		$extension->validate_order( $order );
		affl_fail( 'A mixed firearm and ordinary order must require separation.' );
	} catch ( AFFL_Test_Route_Exception $error ) {
		affl_assert_contains( 'cannot be combined', $error->getMessage() );
	}
} );
