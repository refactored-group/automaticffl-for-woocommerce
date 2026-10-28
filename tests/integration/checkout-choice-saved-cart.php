<?php
/** Run with wp eval-file in the existing disposable loopback acceptance site. */
use RefactoredGroup\AutomaticFFL\Helper\Saved_Cart;
use RefactoredGroup\AutomaticFFL\Helper\Cart_Analyzer;
use RefactoredGroup\AutomaticFFL\Views\Cart;

if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost', '::1' ), true ) || 'local-cart-blocks-only' !== get_option( 'wc_ffl_store_hash' ) ) {
	throw new RuntimeException( 'Requires the disposable Cart Block fixture.' );
}
if ( ! WC()->cart ) {
	wc_load_cart();
}
$ids = get_option( 'affl_cart_fixture_ids' );
$assert = function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};
$parent = new WC_Product_Variable();
$parent->set_name( 'Checkout choice fixture variation parent' );
$parent->set_status( 'publish' );
$parent->update_meta_data( '_affl_fixture_kind', 'firearm' );
$parent->save();
$variation = new WC_Product_Variation();
$variation->set_parent_id( $parent->get_id() );
$variation->set_regular_price( '30' );
$variation->set_status( 'publish' );
$variation->update_meta_data( '_ffl_required', 'yes' );
$variation->update_meta_data( '_affl_fixture_kind', 'firearm' );
$variation->save();
WC_Product_Variable::sync( $parent->get_id() );
try {
	foreach ( array( 'firearm', 'ammo', 'variation' ) as $kind ) {
		foreach ( array( 'ffl' => 'regular', 'regular' => 'ffl' ) as $checkout_group => $saved_group ) {
			$token = WC()->session->get( Saved_Cart::SESSION_TOKEN_KEY );
			if ( $token ) Saved_Cart::clear_saved_items_by_token( $token );
			WC()->cart->empty_cart();
			$ffl_id = 'variation' === $kind ? $parent->get_id() : $ids[ $kind ];
			$variation_id = 'variation' === $kind ? $variation->get_id() : 0;
			$ffl_key = WC()->cart->add_to_cart( $ffl_id, 3, $variation_id, array(), array( 'fixture_extra' => 'preserved' ) );
			$regular_key = WC()->cart->add_to_cart( $ids['regular'], 2 );
			$assert( $ffl_key && $regular_key, 'Both fixture groups must be purchasable.' );
			$analysis = new Cart_Analyzer();
			ob_start();
			Cart::verify_mixed_cart();
			$markup = ob_get_clean();
			if ( 'ammo' !== $kind ) {
				$assert( false !== strpos( $markup, 'Check out FFL items (3)' ), 'Classic FFL quantity wording.' );
				$assert( false !== strpos( $markup, 'Check out regular items (2)' ), 'Classic regular quantity wording.' );
			}
			$result = Saved_Cart::save_items( $saved_group );
			$assert( $result['success'], 'The opposite group must save successfully.' );
			$remaining = WC()->cart->get_cart();
			$kept_key = 'ffl' === $checkout_group ? $ffl_key : $regular_key;
			$assert( array_keys( $remaining ) === array( $kept_key ), 'Only the chosen checkout group may remain.' );
			$assert( $remaining[ $kept_key ]['quantity'] === ( 'ffl' === $checkout_group ? 3 : 2 ), 'Checkout quantities must remain unchanged.' );
			$token = WC()->session->get( Saved_Cart::SESSION_TOKEN_KEY );
			$saved = Saved_Cart::get_saved_items_by_token( $token );
			$assert( count( $saved['items'] ) === 1, 'Only the opposite group may be saved.' );
			$assert( $saved['items'][0]['product_id'] === ( 'ffl' === $saved_group ? $ffl_id : $ids['regular'] ), 'The saved product must belong to the opposite group.' );
			$assert( $saved['items'][0]['quantity'] === ( 'ffl' === $saved_group ? 3 : 2 ), 'Saved quantities must remain unchanged.' );
			WC()->cart->empty_cart(); // Models the cart after the selected order completes; no order/payment is submitted.
			$restored = Saved_Cart::restore_items( $saved );
			$assert( $restored['success'] && empty( $restored['failed'] ), 'Saved items must restore successfully.' );
			$restored_items = array_values( WC()->cart->get_cart() );
			$assert( $restored_items[0]['quantity'] === $saved['items'][0]['quantity'], 'Restoration must preserve quantity.' );
			if ( 'variation' === $kind && 'ffl' === $saved_group ) {
				$assert( $restored_items[0]['variation_id'] === $variation_id, 'Restoration must preserve variation ID.' );
				$assert( $restored_items[0]['fixture_extra'] === 'preserved', 'Restoration must preserve custom item data.' );
			}
			Saved_Cart::clear_saved_items_by_token( $token );
			fwrite( STDOUT, "PASS {$kind}: checkout {$checkout_group}, save/restore {$saved_group}, quantities preserved\n" );
		}
	}
} finally {
	WC()->cart->empty_cart();
	$variation->delete( true );
	$parent->delete( true );
}
