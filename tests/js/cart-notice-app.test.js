import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import CartNoticeApp from '../../assets/js/blocks/cart-notice/cart-notice-app';

let mockCart;
const mockListeners = new Set();
jest.mock( '@wordpress/element', () => require( 'react' ), { virtual: true } );
jest.mock( '@wordpress/data', () => ( {
	useSelect: ( selector ) => require( 'react' ).useSyncExternalStore(
		( callback ) => { mockListeners.add( callback ); return () => mockListeners.delete( callback ); },
		() => selector( () => ( { getCartData: () => mockCart } ) )
	),
} ), { virtual: true } );
jest.mock( '@woocommerce/block-data', () => ( { CART_STORE_KEY: 'wc/store/cart' } ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( value ) => value, _n: ( single, plural, count ) => count === 1 ? single : plural, sprintf: ( value, ...args ) => value.replace( /%d/g, () => args.shift() ) } ), { virtual: true } );
jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock( '@woocommerce/blocks-checkout', () => ( { extensionCartUpdate: jest.fn() } ), { virtual: true } );
jest.mock( '@woocommerce/settings', () => ( { getSetting: () => ( {} ) } ), { virtual: true } );

const mixed = { isApiAvailable: true, isFirearmsRegularMixed: true, fflItemCount: 1, regularItemCount: 1 };

test( 'top-of-cart root follows live Cart Store changes without touching WooCommerce markup', () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	document.body.innerHTML = '<div class="automaticffl-cart-notice-root"></div><div class="wp-block-woocommerce-cart"><main>Products</main><aside>Totals</aside></div>';
	const mount = document.querySelector( '.automaticffl-cart-notice-root' );
	const wooCart = document.querySelector( '.wp-block-woocommerce-cart' );
	const originalCart = wooCart.outerHTML;
	const root = createRoot( mount );
	const updateCart = ( data ) => act( () => {
		mockCart = { extensions: { automaticffl: data } };
		mockListeners.forEach( ( callback ) => callback() );
	} );

	mockCart = { extensions: {} };
	act( () => root.render( <CartNoticeApp /> ) );
	expect( mount.textContent ).toBe( '' );
	updateCart( mixed );
	expect( mount.textContent ).toContain( 'FFL item (1)' );
	expect( mount.nextElementSibling ).toBe( wooCart );
	expect( wooCart.querySelector( '.automaticffl-cart-notice' ) ).toBeNull();
	updateCart( { ...mixed, fflItemCount: 3 } );
	expect( mount.textContent ).toContain( 'FFL items (3)' );
	updateCart( { ...mixed, isFirearmsRegularMixed: false, regularItemCount: 0 } );
	expect( mount.textContent ).toBe( '' );
	expect( document.body.classList.contains( 'automaticffl-cart-blocked' ) ).toBe( false );
	expect( wooCart.outerHTML ).toBe( originalCart );
	act( () => root.unmount() );
	expect( mockListeners.size ).toBe( 0 );
	document.body.innerHTML = '';
} );
