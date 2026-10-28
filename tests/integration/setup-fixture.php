<?php
/**
 * Disposable localhost WooCommerce fixture for checkout acceptance.
 *
 * Run only through WP-CLI eval-file as documented in README.md beside this file.
 */

use RefactoredGroup\AutomaticFFL\Helper\Config;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This fixture must run through WP-CLI.' );
}

/** Return true only for loopback HTTP(S) URLs. */
function automaticffl_fixture_local_url( $url ) {
	$parts = wp_parse_url( $url );
	$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
}

/** Delete fixture records and the test-only MU plugin. */
function automaticffl_fixture_cleanup() {
	$ids = get_option( 'automaticffl_local_fixture_ids', array() );
	foreach ( isset( $ids['products'] ) ? $ids['products'] : array() as $product_id ) {
		wp_delete_post( $product_id, true );
	}
	foreach ( isset( $ids['pages'] ) ? $ids['pages'] : array() as $page_id ) {
		wp_delete_post( $page_id, true );
	}
	foreach ( isset( $ids['categories'] ) ? $ids['categories'] : array() as $term_id ) {
		wp_delete_term( $term_id, 'product_cat' );
	}
	foreach ( isset( $ids['shipping_zones'] ) ? $ids['shipping_zones'] : array() as $zone_id ) {
		WC_Shipping_Zones::delete_zone( (int) $zone_id );
	}
	$mu_plugin = WPMU_PLUGIN_DIR . '/automaticffl-disable-application-passwords.php';
	if ( file_exists( $mu_plugin ) ) {
		unlink( $mu_plugin );
	}
	delete_option( 'automaticffl_local_fixture_ids' );
}

if ( '1' === getenv( 'AUTOMATICFFL_FIXTURE_CLEANUP' ) ) {
	automaticffl_fixture_cleanup();
	WP_CLI::success( 'Automatic FFL local fixture removed.' );
	return;
}

$site_url   = get_site_url();
$api_url    = Config::get_ffl_api_url();
$iframe_url = Config::get_iframe_map_url();
$origins    = Config::get_iframe_allowed_origins();

if ( ! automaticffl_fixture_local_url( $site_url ) ||
	! automaticffl_fixture_local_url( $api_url ) ||
	! automaticffl_fixture_local_url( $iframe_url ) ) {
	throw new RuntimeException( 'Refusing fixture setup: WordPress, API, and widget destinations must all be loopback URLs.' );
}

$iframe_parts  = wp_parse_url( $iframe_url );
$iframe_origin = $iframe_parts['scheme'] . '://' . $iframe_parts['host'];
if ( isset( $iframe_parts['port'] ) ) {
	$iframe_origin .= ':' . $iframe_parts['port'];
}
if ( ! in_array( $iframe_origin, $origins, true ) ) {
	throw new RuntimeException( 'The disposable plugin copy must allow the exact local iframe origin.' );
}

$store_hash = getenv( 'AUTOMATICFFL_FIXTURE_STORE_HASH' );
if ( ! is_string( $store_hash ) || '' === trim( $store_hash ) ) {
	throw new RuntimeException( 'Set AUTOMATICFFL_FIXTURE_STORE_HASH to the local backend fixture hash.' );
}

automaticffl_fixture_cleanup();
wp_mkdir_p( WPMU_PLUGIN_DIR );
$mu_plugin_source = <<<'PHP'
<?php
add_filter( 'wp_is_application_passwords_available', '__return_false' );

add_action(
	'wp_loaded',
	function () {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( ! in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ||
			! isset( $_GET['automaticffl_fixture_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['automaticffl_fixture_action'] ) );
		if ( 'empty_cart' === $action && WC()->cart ) {
			WC()->cart->empty_cart();
			if ( WC()->session ) {
				WC()->session->set( 'automaticffl_ammo_state', '' );
			}
		}

		if ( 'ammo_state' === $action && WC()->session && isset( $_GET['state'] ) ) {
			$state = strtoupper( sanitize_text_field( wp_unslash( $_GET['state'] ) ) );
			if ( 1 === preg_match( '/^[A-Z]{2}$/', $state ) ) {
				WC()->session->set( 'automaticffl_ammo_state', $state );
			}
		}

		wp_safe_redirect( remove_query_arg( array( 'automaticffl_fixture_action', 'state' ) ) );
		exit;
	}
);
PHP;
file_put_contents(
	WPMU_PLUGIN_DIR . '/automaticffl-disable-application-passwords.php',
	$mu_plugin_source
);

update_option( Config::FFL_STORE_HASH_CONFIG, trim( $store_hash ) );
update_option( Config::FFL_SANDBOX_MODE_CONFIG, '0' );
update_option( Config::FFL_GOOGLE_MAPS_API_KEY_CONFIG, 'local-fixture-key' );

$firearm_term = wp_insert_term( 'Automatic FFL Fixture Firearms', 'product_cat' );
$ammo_term    = wp_insert_term( 'Automatic FFL Fixture Ammunition', 'product_cat' );
if ( is_wp_error( $firearm_term ) || is_wp_error( $ammo_term ) ) {
	throw new RuntimeException( 'Could not create fixture categories.' );
}

$firearm_category_id = (int) $firearm_term['term_id'];
$ammo_category_id    = (int) $ammo_term['term_id'];

$create_product = function ( $name, $categories, $ffl_required ) {
	$product = new WC_Product_Simple();
	$product->set_name( $name );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_regular_price( '10.00' );
	$product->set_category_ids( $categories );
	$product->update_meta_data( '_ffl_required', $ffl_required ? 'yes' : 'no' );
	return $product->save();
};

$firearm_id = $create_product( 'Automatic FFL Fixture Flagged Firearm', array(), true );
$category_firearm_id = $create_product( 'Automatic FFL Fixture Category Firearm', array( $firearm_category_id ), false );
$ammo_id = $create_product( 'Automatic FFL Fixture Ammunition', array( $ammo_category_id ), false );
$overlap_id = $create_product( 'Automatic FFL Fixture Overlap', array( $firearm_category_id, $ammo_category_id ), false );
$regular_id = $create_product( 'Automatic FFL Fixture Ordinary Product', array(), false );

$variable = new WC_Product_Variable();
$variable->set_name( 'Automatic FFL Fixture Parent Variation' );
$variable->set_status( 'publish' );
$variable->set_category_ids( array( $firearm_category_id ) );
$variable->update_meta_data( '_ffl_required', 'yes' );
$variable_id = $variable->save();
$variation = new WC_Product_Variation();
$variation->set_parent_id( $variable_id );
$variation->set_status( 'publish' );
$variation->set_regular_price( '10.00' );
$variation->update_meta_data( '_ffl_required', 'no' );
$variation_id = $variation->save();

$classic_cart_page = wp_insert_post(
	array(
		'post_title'   => 'Automatic FFL Classic Cart Fixture',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '[woocommerce_cart]',
	)
);
$classic_page = wp_insert_post(
	array(
		'post_title'   => 'Automatic FFL Classic Checkout Fixture',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '[woocommerce_checkout]',
	)
);
$installed_checkout_page_id = (int) get_option( 'woocommerce_checkout_page_id' );
$blocks_checkout_content    = $installed_checkout_page_id > 0
	? get_post_field( 'post_content', $installed_checkout_page_id )
	: '';
if ( ! is_string( $blocks_checkout_content ) || false === strpos( $blocks_checkout_content, '<!-- wp:woocommerce/checkout' ) ) {
	throw new RuntimeException( 'Could not load the installed WooCommerce checkout-block markup.' );
}
$blocks_page = wp_insert_post(
	array(
		'post_title'   => 'Automatic FFL Blocks Checkout Fixture',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $blocks_checkout_content,
	)
);

$cod = get_option( 'woocommerce_cod_settings', array() );
$cod['enabled'] = 'yes';
update_option( 'woocommerce_cod_settings', $cod );

$shipping_zone = new WC_Shipping_Zone();
$shipping_zone->set_zone_name( 'Automatic FFL Fixture United States' );
$shipping_zone->set_zone_order( 1 );
$shipping_zone_id = $shipping_zone->save();
$shipping_zone->add_location( 'US', 'country' );
$shipping_method_id = $shipping_zone->add_shipping_method( 'free_shipping' );
if ( ! $shipping_zone_id || ! $shipping_method_id ) {
	throw new RuntimeException( 'Could not create the fixture shipping method.' );
}
update_option(
	'woocommerce_free_shipping_' . $shipping_method_id . '_settings',
	array(
		'enabled'      => 'yes',
		'title'        => 'Free shipping',
		'requires'     => '',
		'min_amount'   => '0',
		'ignore_discounts' => 'no',
	)
);

$fixture = array(
	'products'       => array( $firearm_id, $category_firearm_id, $ammo_id, $overlap_id, $regular_id, $variable_id, $variation_id ),
	'pages'          => array( $classic_cart_page, $classic_page, $blocks_page ),
	'categories'     => array( $firearm_category_id, $ammo_category_id ),
	'shipping_zones' => array( $shipping_zone_id ),
);
update_option( 'automaticffl_local_fixture_ids', $fixture, false );

WP_CLI::line(
	wp_json_encode(
		array(
			'store_hash'             => trim( $store_hash ),
			'wordpress_url'           => $site_url,
			'api_url'                 => $api_url,
			'iframe_url'              => $iframe_url,
			'firearm_category_id'     => $firearm_category_id,
			'ammunition_category_id'  => $ammo_category_id,
			'product_ids'             => $fixture['products'],
			'classic_checkout_page'   => get_permalink( $classic_page ),
			'classic_cart_page'       => get_permalink( $classic_cart_page ),
			'blocks_checkout_page'    => get_permalink( $blocks_page ),
			'application_passwords'   => 'disabled_by_test_mu_plugin',
			'offline_payment_gateway' => 'cod',
		)
	)
);
WP_CLI::success( 'Automatic FFL localhost fixture created.' );
