/** Subscribe independently of WooCommerce's React tree to its live cart data. */
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { CART_STORE_KEY } from '@woocommerce/block-data';
import CartNotice from './cart-notice';
import { alignCartNotice } from './cart-notice-layout';

const CartNoticeApp = ( { mount } ) => {
	const extensions = useSelect( ( select ) => select( CART_STORE_KEY ).getCartData()?.extensions, [] );
	useEffect( () => alignCartNotice( mount ), [ mount, extensions ] );
	return <CartNotice context="woocommerce/cart" extensions={ extensions } />;
};

export default CartNoticeApp;
