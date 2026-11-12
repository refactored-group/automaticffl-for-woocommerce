<?php
/**
 * Real WooCommerce lifecycle checks for complete FFL data before payment.
 *
 * Run only with WP-CLI against the disposable loopback fixture documented in
 * this directory. No provider credentials or external payment calls are used.
 */

use RefactoredGroup\AutomaticFFL\Blocks\Store_Api_Extension;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This fixture must run through WP-CLI.' );
}

$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
if ( ! in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
	throw new RuntimeException( 'Refusing lifecycle checks outside a loopback WordPress site.' );
}

$fixture = get_option( 'automaticffl_local_fixture_ids', array() );
if ( empty( $fixture['products'][0] ) || ! WC()->cart ) {
	throw new RuntimeException( 'Run setup-fixture.php first and initialize the WooCommerce cart.' );
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

// Reject every non-loopback HTTP request made accidentally during this check.
$http_guard = static function ( $preempt, $args, $url ) {
	$url_host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( ! in_array( $url_host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
		return new WP_Error( 'automaticffl_fixture_external_request', 'External HTTP is disabled in the lifecycle fixture.' );
	}
	return $preempt;
};
add_filter( 'pre_http_request', $http_guard, 10, 3 );

$payment_calls = array();
$gateway       = new class( $payment_calls ) extends WC_Payment_Gateway {
	private $calls;

	public function __construct( &$calls ) {
		$this->id    = 'automaticffl_fixture_gateway';
		$this->calls = &$calls;
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$this->calls[] = array(
			'order_id'  => $order_id,
			'dealer_id' => $order->get_meta( '_ffl_dealer_id' ),
			'license'   => $order->get_meta( '_ffl_license_field' ),
			'shipping'  => $order->get_shipping_address_1(),
		);
		$order->update_status( 'on-hold', 'Fixture payment intentionally left incomplete.' );
		return array( 'result' => 'success', 'redirect' => $order->get_checkout_order_received_url() );
	}
};

$dealer = array(
	'ffl_dealer_id'         => '4242',
	'ffl_license_field'     => 'fixture-license',
	'ffl_expiration_date'   => '2030-01-01',
	'ffl_uuid'              => 'fixture-certificate',
	'ffl_company_name'      => 'Fixture Dealer',
	'billing_first_name'    => 'Test',
	'billing_last_name'     => 'Customer',
	'billing_address_1'     => '1 Customer Street',
	'billing_city'          => 'Provo',
	'billing_state'         => 'UT',
	'billing_postcode'      => '84601',
	'billing_country'       => 'US',
	'shipping_first_name'   => 'Test',
	'shipping_last_name'    => 'Customer',
	'shipping_company'      => 'Fixture Dealer',
	'shipping_address_1'    => '42 Dealer Street',
	'shipping_address_2'    => '',
	'shipping_city'         => 'Orem',
	'shipping_state'        => 'UT',
	'shipping_postcode'     => '84057',
	'shipping_country'      => 'US',
	'shipping_phone'        => '8015550100',
);

$extension_data = array(
	'fflDealerId'        => 4242,
	'fflLicense'         => 'fixture-license',
	'fflExpirationDate'  => '2030-01-01',
	'fflUuid'            => 'fixture-certificate',
	'fflCompanyName'     => 'Fixture Dealer',
	'fflDealerAddress1'  => '42 Dealer Street',
	'fflDealerAddress2'  => '',
	'fflDealerCity'      => 'Orem',
	'fflDealerState'     => 'UT',
	'fflDealerPostcode'  => '84057',
	'fflDealerCountry'   => 'US',
	'fflDealerPhone'     => '8015550100',
);

$orders = array();
$shipping_destination = get_option( 'woocommerce_ship_to_destination', 'shipping' );
try {
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( (int) $fixture['products'][0] );
	update_option( 'woocommerce_ship_to_destination', 'billing_only' );

	// Capture the same normalized data a gateway can retain after totals preflight.
	$_POST = $dealer;
	$normalized_dealer = apply_filters( 'woocommerce_checkout_posted_data', $dealer );
	$assert( '42 Dealer Street' === $normalized_dealer['shipping_address_1'], 'Classic collection must retain dealer shipping in billing-only mode.' );
	$assert( '1 Customer Street' === $normalized_dealer['billing_address_1'], 'Classic collection must preserve customer billing.' );

	$totals_data = $normalized_dealer;
	$totals_data['woocommerce_checkout_update_totals'] = 1;
	$totals_errors = new WP_Error();
	do_action( 'woocommerce_after_checkout_validation', $totals_data, $totals_errors );
	$assert( ! $totals_errors->has_errors(), 'A complete dealer must pass totals-only native validation.' );
	$totals_data['ffl_license_field'] = '';
	$totals_errors = new WP_Error();
	do_action( 'woocommerce_after_checkout_validation', $totals_data, $totals_errors );
	$assert( $totals_errors->has_errors(), 'A partial dealer must fail totals-only native validation before payment.' );

	// Classic replay: the normalized payload must project with no browser POST.
	$_POST        = array();
	$classic      = wc_create_order();
	$orders[]     = $classic;
	$classic->set_billing_first_name( 'Test' );
	$classic->set_billing_last_name( 'Customer' );
	$classic->set_billing_address_1( '1 Customer Street' );
	do_action( 'woocommerce_checkout_create_order', $classic, $normalized_dealer );
	$classic->save();
	$assert( 4242 === (int) $classic->get_meta( '_ffl_dealer_id' ), 'Classic stored-data replay must save the dealer ID.' );
	$assert( 'fixture-license' === $classic->get_meta( '_ffl_license_field' ), 'Classic stored-data replay must save the license.' );
	$assert( '42 Dealer Street' === $classic->get_shipping_address_1(), 'Classic stored-data replay must save dealer shipping.' );
	$assert( '1 Customer Street' === $classic->get_billing_address_1(), 'Classic stored-data replay must preserve billing.' );
	$gateway->process_payment( $classic->get_id() );
	$assert( 'fixture-license' === $payment_calls[0]['license'], 'Classic fake payment must receive complete FFL data.' );
	$assert( wc_get_order( $classic->get_id() )->has_status( 'on-hold' ), 'Classic fake payment must leave a complete unpaid order on hold.' );

	// Replaying a replacement must clear optional values without browser POST.
	$replacement = $normalized_dealer;
	$replacement['ffl_dealer_id']       = '8484';
	$replacement['ffl_expiration_date'] = '';
	$replacement['ffl_uuid']            = '';
	$replacement['ffl_company_name']    = 'Replacement Dealer';
	$replacement['shipping_company']    = 'Replacement Dealer';
	$replacement['shipping_address_1']  = '84 Replacement Street';
	do_action( 'woocommerce_checkout_create_order', $classic, $replacement );
	do_action( 'woocommerce_checkout_create_order', $classic, $replacement );
	$assert( 8484 === (int) $classic->get_meta( '_ffl_dealer_id' ), 'Classic replacement must save the new dealer ID.' );
	$assert( '' === $classic->get_meta( '_ffl_expiration_date' ), 'Classic replacement must clear omitted expiration.' );
	$assert( '' === $classic->get_meta( '_ffl_uuid' ), 'Classic replacement must clear omitted certificate UUID.' );
	$assert( '84 Replacement Street' === $classic->get_shipping_address_1(), 'Classic repeated save must retain only the replacement dealer.' );

	// Blocks final POST: complete current extension data must replace order data.
	$blocks  = wc_create_order();
	$orders[] = $blocks;
	$blocks->set_billing_first_name( 'Test' );
	$blocks->set_billing_last_name( 'Customer' );
	$blocks->set_billing_address_1( '1 Customer Street' );
	$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
	$request->set_param( 'extensions', array( Store_Api_Extension::NAMESPACE => $extension_data ) );
	$store_api = new Store_Api_Extension();
	$store_api->update_order_from_request( $blocks, $request );
	$assert( 'fixture-license' === $blocks->get_meta( '_ffl_license_field' ), 'Blocks final POST must save the current license.' );
	$assert( '42 Dealer Street' === $blocks->get_shipping_address_1(), 'Blocks final POST must save dealer shipping.' );
	$gateway->process_payment( $blocks->get_id() );
	$assert( 'fixture-license' === $payment_calls[1]['license'], 'Blocks fake payment must receive complete FFL data.' );
	$assert( wc_get_order( $blocks->get_id() )->has_status( 'on-hold' ), 'Blocks fake payment must leave a complete unpaid order on hold.' );

	// Final missing data must reject stale draft metadata before fake payment.
	$invalid  = wc_create_order();
	$orders[] = $invalid;
	$invalid->update_meta_data( '_ffl_dealer_id', 9999 );
	$invalid->update_meta_data( '_ffl_license_field', 'stale-license' );
	$invalid->save();
	$missing = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
	$missing->set_param( 'extensions', array() );
	$rejected = false;
	try {
		$store_api->update_order_from_request( $invalid, $missing );
	} catch ( Throwable $error ) {
		$rejected = true;
	}
	$assert( $rejected, 'Final Blocks POST must reject missing current extension data.' );
	$assert( 2 === count( $payment_calls ), 'Invalid final Blocks data must not invoke payment.' );

	// A partial draft update remains legal and must not reuse or erase the old dealer.
	$partial = new WP_REST_Request( 'PUT', '/wc/store/v1/checkout' );
	$partial->set_param( 'extensions', array() );
	$store_api->update_order_from_request( $invalid, $partial );
	$assert( 'stale-license' === $invalid->get_meta( '_ffl_license_field' ), 'Partial draft update must not mutate existing FFL data.' );

	WP_CLI::success( 'Classic replay, final Blocks POST, partial draft update, and fake payment lifecycle checks passed.' );
} finally {
	remove_filter( 'pre_http_request', $http_guard, 10 );
	update_option( 'woocommerce_ship_to_destination', $shipping_destination );
	WC()->cart->empty_cart();
	foreach ( $orders as $order ) {
		if ( $order instanceof WC_Order ) {
			$order->delete( true );
		}
	}
}
