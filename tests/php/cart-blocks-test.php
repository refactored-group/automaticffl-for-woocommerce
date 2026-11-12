<?php
/** Cart Block live extension contract and session updates. */

use RefactoredGroup\AutomaticFFL\Blocks\Store_Api_Extension;
use RefactoredGroup\AutomaticFFL\Api\Restrictions_Client;
use RefactoredGroup\AutomaticFFL\Helper\Ammo_Destination_Edit;
use RefactoredGroup\AutomaticFFL\Plugin;

require_once dirname( __DIR__, 2 ) . '/includes/helper/class-cart-analyzer.php';
require_once dirname( __DIR__, 2 ) . '/includes/helper/class-us-states.php';
require_once dirname( __DIR__, 2 ) . '/includes/helper/class-ammo-destination-edit.php';
require_once dirname( __DIR__, 2 ) . '/includes/helper/class-config.php';
require_once dirname( __DIR__, 2 ) . '/includes/blocks/class-store-api-extension.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-plugin.php';
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

class AFFL_Test_Countries {
	public function get_shipping_countries() { return array( 'US' => 'United States', 'PR' => 'Puerto Rico' ); }
	public function get_allowed_countries() { return $this->get_shipping_countries(); }
	public function get_address_fields( $country, $prefix ) {
		$required = array( 'first_name', 'last_name', 'country', 'address_1', 'city', 'postcode' );
		if ( 'PR' !== $country ) {
			$required[] = 'state';
		}
		$fields = array();
		foreach ( array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone' ) as $name ) {
			$fields[ $prefix . $name ] = array(
				'required' => in_array( $name, $required, true ),
				'hidden'   => false,
			);
		}
		return $fields;
	}
}

class AFFL_Test_Session_Customer {
	public $shipping = array();
	public $save_count = 0;

	public function __construct( array $shipping ) {
		$this->shipping = $shipping;
	}

	public function __call( $name, $arguments ) {
		if ( 0 !== strpos( $name, 'set_shipping_' ) ) {
			throw new BadMethodCallException( $name );
		}
		$field                    = substr( $name, strlen( 'set_shipping_' ) );
		$this->shipping[ $field ] = isset( $arguments[0] ) ? $arguments[0] : '';
	}

	public function save() {
		++$this->save_count;
	}
}

class AFFL_Test_Request {
	private $method;
	private $params;
	public function __construct( $method, array $params ) {
		$this->method = $method;
		$this->params = $params;
	}
	public function get_method() { return $this->method; }
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
}

class AFFL_Test_Blocks_Order {
	public $meta = array();
	public $shipping = array(
		'first_name' => 'Test',
		'last_name'  => 'Customer',
		'country'    => 'US',
		'state'      => 'UT',
	);
	public $billing = array(
		'first_name' => 'Test',
		'last_name'  => 'Customer',
		'country'    => 'US',
		'state'      => 'UT',
	);
	public $notes = array();
	public $save_count = 0;

	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
	public function get_meta( $key ) { return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : ''; }
	public function add_order_note( $note ) { $this->notes[] = $note; }
	public function save() { ++$this->save_count; }
	public function __call( $name, $arguments ) {
		foreach ( array( 'shipping', 'billing' ) as $type ) {
			$get_prefix = 'get_' . $type . '_';
			$set_prefix = 'set_' . $type . '_';
			if ( 0 === strpos( $name, $get_prefix ) ) {
				$field = substr( $name, strlen( $get_prefix ) );
				return isset( $this->{$type}[ $field ] ) ? $this->{$type}[ $field ] : '';
			}
			if ( 0 === strpos( $name, $set_prefix ) ) {
				$field = substr( $name, strlen( $set_prefix ) );
				$this->{$type}[ $field ] = isset( $arguments[0] ) ? $arguments[0] : '';
				return;
			}
		}
		throw new BadMethodCallException( $name );
	}
}

function affl_complete_blocks_ffl_data() {
	return array(
		'fflDealerId'       => '42',
		'fflLicense'        => 'fixture-license',
		'fflExpirationDate' => '2030-01-01',
		'fflUuid'           => 'fixture-uuid',
		'fflCompanyName'    => 'Fixture Dealer',
		'fflDealerAddress1' => '42 Dealer Street',
		'fflDealerAddress2' => '',
		'fflDealerCity'     => 'Orem',
		'fflDealerState'    => 'UT',
		'fflDealerPostcode' => '84057',
		'fflDealerCountry'  => 'US',
		'fflDealerPhone'    => '8015550100',
	);
}

function affl_firearm_blocks_fixture() {
	$extension = affl_cart_blocks_fixture( array( array( 'id' => 1 ) ) );
	unset( WC()->cart->items['b'] );
	return $extension;
}

function affl_blocks_checkout_request( $method, $ffl_data = null ) {
	$extensions = null === $ffl_data ? array() : array( Store_Api_Extension::NAMESPACE => $ffl_data );
	return new AFFL_Test_Request( $method, array( 'extensions' => $extensions ) );
}

function WC() {
	return $GLOBALS['affl_wc'];
}

function affl_cart_blocks_fixture( array $restrictions = array() ) {
	$GLOBALS['affl_wc'] = (object) array( 'cart' => new AFFL_Test_Cart(), 'session' => new AFFL_Test_Session(), 'countries' => new AFFL_Test_Countries() );
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

affl_test( 'native ammo address editing is limited to signed-in shippable ammo carts', function () {
	$ammo = new class {
		public function has_api_error() { return false; }
		public function has_ammo() { return true; }
		public function has_firearms() { return false; }
	};
	$GLOBALS['affl_logged_in'] = true;
	affl_assert_true( Ammo_Destination_Edit::is_eligible( $ammo ) );

	$GLOBALS['affl_logged_in'] = false;
	affl_assert_false( Ammo_Destination_Edit::is_eligible( $ammo ) );
	$GLOBALS['affl_logged_in'] = true;
	$GLOBALS['affl_options']['woocommerce_ship_to_destination'] = 'billing_only';
	affl_assert_false( Ammo_Destination_Edit::is_eligible( $ammo ) );

	$firearm = new class {
		public function has_api_error() { return false; }
		public function has_ammo() { return true; }
		public function has_firearms() { return true; }
	};
	$GLOBALS['affl_options']['woocommerce_ship_to_destination'] = 'shipping';
	affl_assert_false( Ammo_Destination_Edit::is_eligible( $firearm ) );
} );

affl_test( 'native ammo edit data clears destination fields and publishes country requirements', function () {
	$restrictions = array( array( 'id' => 1, 'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'CA' ) ) ) ) );
	affl_cart_blocks_fixture( $restrictions );
	unset( WC()->cart->items['b'] );
	$GLOBALS['affl_logged_in'] = true;
	$data = Ammo_Destination_Edit::get_script_data();
	affl_assert_true( $data['required'] );
	affl_assert_same( array( 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone' ), $data['physicalFields'] );
	affl_assert_true( in_array( 'state', $data['requiredFields']['US'], true ) );
	affl_assert_false( in_array( 'state', $data['requiredFields']['PR'], true ) );
	affl_assert_false( isset( $data['savedAddress'] ) );
} );

affl_test( 'new blocks checkout restores persistent shipping before the stale session reaches the cart response', function () {
	$restrictions = array( array( 'id' => 1, 'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'CA' ) ) ) ) );
	affl_cart_blocks_fixture( $restrictions );
	unset( WC()->cart->items['b'] );
	$GLOBALS['affl_logged_in']          = true;
	$GLOBALS['affl_is_checkout']        = true;
	$GLOBALS['affl_has_checkout_block'] = true;
	$GLOBALS['affl_user_meta'][1] = array(
		'shipping_first_name' => 'Automatic FFL',
		'shipping_last_name'  => 'QA Test Updated',
		'shipping_company'    => '',
		'shipping_address_1'  => '5916 Lost Valley Dr',
		'shipping_address_2'  => '',
		'shipping_city'       => 'The Colony',
		'shipping_state'      => 'TX',
		'shipping_postcode'   => '75056',
		'shipping_country'    => 'US',
		'shipping_phone'      => '14692071149',
	);
	$stale_dealer = array(
		'first_name' => 'Giancarlo',
		'last_name'  => 'Byrne',
		'company'    => 'RIFLEGEAR',
		'address_1'  => '4001 STATE HWY 121',
		'address_2'  => '',
		'city'       => 'THE COLONY',
		'state'      => 'TX',
		'postcode'   => '75056',
		'country'    => 'US',
		'phone'      => '9722927678',
	);
	WC()->customer = new AFFL_Test_Session_Customer( $stale_dealer );
	WC()->session->set( Plugin::SESSION_FFL_DESTINATION, $stale_dealer );

	$plugin = ( new ReflectionClass( Plugin::class ) )->newInstanceWithoutConstructor();
	$plugin->restore_saved_shipping_for_ammo_checkout();

	affl_assert_same( '5916 Lost Valley Dr', WC()->customer->shipping['address_1'] );
	affl_assert_same( 'Automatic FFL', WC()->customer->shipping['first_name'] );
	affl_assert_same( 1, WC()->customer->save_count );
	affl_assert_same( null, WC()->session->get( Plugin::SESSION_FFL_DESTINATION ) );
	affl_assert_same( '5916 Lost Valley Dr', $GLOBALS['affl_user_meta'][1]['shipping_address_1'] );
} );

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

affl_test( 'cart extension accepts D.C. and all five territory rule codes', function () {
	$extension = affl_cart_blocks_fixture();
	foreach ( array( 'DC', 'AS', 'GU', 'MP', 'PR', 'VI' ) as $code ) {
		$extension->update_cart_state( array( 'state' => $code ) );
		affl_assert_same( $code, $extension->get_cart_schema_data()['selectedAmmoState'] );
	}
} );

affl_test( 'Store API enforces native territory countries and ignores foreign collisions', function () {
	$territory_order = new class {
		public $meta = '';
		public function get_shipping_country() { return 'PR'; }
		public function get_shipping_state() { return ''; }
		public function get_billing_country() { return 'US'; }
		public function get_billing_state() { return 'UT'; }
		public function get_meta( $key ) { return $this->meta; }
	};
	$restrictions = array( array( 'id' => 1, 'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'PR' ) ) ) ) );
	$extension = affl_cart_blocks_fixture( $restrictions );
	try {
		$extension->validate_order( $territory_order );
		affl_fail( 'Native Puerto Rico country did not activate the mixed-cart rule.' );
	} catch ( AFFL_Test_Route_Exception $error ) {
		affl_assert_contains( 'Puerto Rico', $error->getMessage() );
	}

	$foreign_order = new class {
		public function get_shipping_country() { return 'CA'; }
		public function get_shipping_state() { return 'PR'; }
		public function get_billing_country() { return 'CA'; }
		public function get_billing_state() { return 'PR'; }
		public function get_meta( $key ) { return ''; }
	};
	$extension = affl_cart_blocks_fixture( $restrictions );
	$extension->validate_order( $foreign_order );
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
	affl_assert_false( isset( $extension->get_schema()['ammoDestinationEntered'] ) );
	affl_assert_false( isset( $extension->get_schema()['isFirearmsRegularMixed'] ) );
} );

affl_test( 'Store API clears stale dealer draft data without a custom destination signal', function () {
	$restrictions = array( array( 'id' => 1, 'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'CA' ) ) ) ) );
	$extension = affl_cart_blocks_fixture( $restrictions );
	$GLOBALS['affl_logged_in'] = true;
	$order = new class {
		public $deleted = array();
		public function delete_meta_data( $key ) { $this->deleted[] = $key; }
	};
	$extensions = array( 'automaticffl' => array( 'fflLicense' => '' ) );
	$extension->update_order_from_request( $order, new AFFL_Test_Request( 'PUT', array( 'extensions' => $extensions ) ) );
	affl_assert_contains( '_ffl_license_field', implode( ',', $order->deleted ) );
	$extension->update_order_from_request( $order, new AFFL_Test_Request( 'POST', array( 'extensions' => $extensions ) ) );
} );

affl_test( 'final Store API POST rejects missing and partial current dealer data before mutation', function () {
	$cases = array(
		'missing extension'   => null,
		'blank license'       => array_merge( affl_complete_blocks_ffl_data(), array( 'fflLicense' => '' ) ),
		'license only'        => array( 'fflLicense' => 'fixture-license' ),
		'missing dealer ID'   => array_merge( affl_complete_blocks_ffl_data(), array( 'fflDealerId' => '' ) ),
		'partial destination' => array_merge( affl_complete_blocks_ffl_data(), array( 'fflDealerCity' => '' ) ),
	);

	foreach ( $cases as $label => $data ) {
		$extension = affl_firearm_blocks_fixture();
		$order = new AFFL_Test_Blocks_Order();
		$order->meta = array(
			'_ffl_dealer_id'       => 999,
			'_ffl_license_field'   => 'stale-license',
			'_ffl_expiration_date' => 'stale-expiration',
			'_ffl_uuid'            => 'stale-uuid',
		);
		$before = $order->meta;
		try {
			$extension->update_order_from_request( $order, affl_blocks_checkout_request( 'POST', $data ) );
			affl_fail( $label . ' was accepted.' );
		} catch ( AFFL_Test_Route_Exception $error ) {
			affl_assert_contains( 'complete FFL dealer', $error->getMessage(), $label );
		}
		affl_assert_same( $before, $order->meta, $label . ' must not mutate stored draft data.' );
		affl_assert_same( 0, $order->save_count, $label . ' must reject before persistence.' );
		affl_assert_same( array(), $order->notes, $label . ' must reject before notes.' );
	}
} );

affl_test( 'complete final Store API data persists without browser POST and replacement is idempotent', function () {
	$extension = affl_firearm_blocks_fixture();
	$order = new AFFL_Test_Blocks_Order();
	$_POST = array();
	$data = affl_complete_blocks_ffl_data();
	$extension->update_order_from_request( $order, affl_blocks_checkout_request( 'POST', $data ) );

	affl_assert_same( 42, $order->meta['_ffl_dealer_id'] );
	affl_assert_same( 'fixture-license', $order->meta['_ffl_license_field'] );
	affl_assert_same( 'fixture-uuid', $order->meta['_ffl_uuid'] );
	affl_assert_same( 'Fixture Dealer', $order->shipping['company'] );
	affl_assert_same( '42 Dealer Street', $order->shipping['address_1'] );
	affl_assert_same( 1, count( $order->notes ) );

	$replacement = array_merge(
		$data,
		array(
			'fflDealerId'       => '84',
			'fflExpirationDate' => '',
			'fflUuid'           => '',
			'fflCompanyName'    => 'Replacement Dealer',
			'fflDealerAddress1' => '84 New Dealer Street',
		)
	);
	$extension->update_order_from_request( $order, affl_blocks_checkout_request( 'POST', $replacement ) );
	affl_assert_same( 84, $order->meta['_ffl_dealer_id'] );
	affl_assert_false( isset( $order->meta['_ffl_expiration_date'] ) );
	affl_assert_false( isset( $order->meta['_ffl_uuid'] ) );
	affl_assert_same( 'Replacement Dealer', $order->shipping['company'] );
	affl_assert_same( 2, count( $order->notes ), 'Meaningful license details replacement should add one new note.' );

	$extension->update_order_from_request( $order, affl_blocks_checkout_request( 'POST', $replacement ) );
	affl_assert_same( 2, count( $order->notes ), 'Replaying identical final data must not duplicate notes.' );
} );

affl_test( 'final Store API completion adds one note when a partial draft already stored the same license', function () {
	$extension = affl_firearm_blocks_fixture();
	$order = new AFFL_Test_Blocks_Order();
	$order->meta['_ffl_license_field'] = 'fixture-license';
	$data = affl_complete_blocks_ffl_data();
	$data['fflExpirationDate'] = '';
	$data['fflUuid']           = '';

	$extension->update_order_from_request( $order, affl_blocks_checkout_request( 'POST', $data ) );
	affl_assert_same( 1, count( $order->notes ), 'The incomplete-to-complete transition must create the first valid FFL note.' );
	$extension->update_order_from_request( $order, affl_blocks_checkout_request( 'POST', $data ) );
	affl_assert_same( 1, count( $order->notes ), 'Replaying the complete selection must not duplicate the note.' );
} );

affl_test( 'partial Store API PUT and PATCH preserve an existing draft without requiring current data', function () {
	foreach ( array( 'PUT', 'PATCH' ) as $method ) {
		$extension = affl_firearm_blocks_fixture();
		$order = new AFFL_Test_Blocks_Order();
		$order->meta = array(
			'_ffl_dealer_id'       => 999,
			'_ffl_license_field'   => 'stale-license',
			'_ffl_expiration_date' => 'stale-expiration',
			'_ffl_uuid'            => 'stale-uuid',
		);
		$before = $order->meta;
		$extension->update_order_from_request( $order, affl_blocks_checkout_request( $method ) );
		affl_assert_same( $before, $order->meta, $method . ' must preserve the draft.' );
		affl_assert_same( 0, $order->save_count );
		affl_assert_same( array(), $order->notes );
	}
} );

affl_test( 'final Store API ammunition POST validates a started dealer after its address replaces the restricted state', function () {
	$restrictions = array(
		array(
			'id'         => 1,
			'conditions' => array( array( 'type' => 'ship_state', 'states' => array( 'CA' ) ) ),
		),
	);
	$extension = affl_cart_blocks_fixture( $restrictions );
	unset( WC()->cart->items['b'] );
	$order = new AFFL_Test_Blocks_Order();
	$order->shipping['state'] = 'UT';
	$data = affl_complete_blocks_ffl_data();
	$data['fflDealerCity'] = '';
	try {
		$extension->update_order_from_request( $order, affl_blocks_checkout_request( 'POST', $data ) );
		affl_fail( 'A partial current ammunition dealer was accepted after its destination replaced the restricted customer state.' );
	} catch ( AFFL_Test_Route_Exception $error ) {
		affl_assert_contains( 'complete FFL dealer', $error->getMessage() );
	}
	affl_assert_same( array(), $order->meta );
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
