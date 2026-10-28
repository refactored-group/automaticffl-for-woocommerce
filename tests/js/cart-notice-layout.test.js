import { alignCartNotice } from '../../assets/js/blocks/cart-notice/cart-notice-layout';

let resize;
let observer;
let originalObserver;
beforeEach( () => {
	originalObserver = window.ResizeObserver;
	window.ResizeObserver = jest.fn( function ( callback ) {
		resize = callback;
		observer = this;
		this.observe = jest.fn();
		this.unobserve = jest.fn();
		this.disconnect = jest.fn();
	} );
	document.body.innerHTML = '<div data-automaticffl-cart-notice></div><div class="wp-block-woocommerce-cart"><main class="wc-block-cart__main"><table class="wc-block-cart-items"></table></main><aside>Totals</aside></div>';
} );
afterEach( () => { window.ResizeObserver = originalObserver; document.body.innerHTML = ''; } );
const rect = ( left, width ) => ( { left, right: left + width, width } );

test( 'tracks actual product-column bounds on resize and changes only extension-owned styles', () => {
	const mount = document.querySelector( '[data-automaticffl-cart-notice]' );
	const cart = mount.nextElementSibling;
	const table = cart.querySelector( 'table' );
	let tableRect = rect( 120, 680 );
	mount.getBoundingClientRect = () => rect( 100, 1000 );
	table.getBoundingClientRect = () => tableRect;
	const originalCart = cart.outerHTML;
	const dispose = alignCartNotice( mount );
	expect( mount.style.getPropertyValue( '--automaticffl-cart-notice-width' ) ).toBe( '680px' );
	expect( mount.style.getPropertyValue( '--automaticffl-cart-notice-offset' ) ).toBe( '20px' );
	expect( observer.observe ).toHaveBeenCalledWith( table );
	tableRect = rect( 100, 360 );
	resize();
	expect( mount.style.getPropertyValue( '--automaticffl-cart-notice-width' ) ).toBe( '360px' );
	expect( cart.outerHTML ).toBe( originalCart );
	dispose();
	expect( observer.disconnect ).toHaveBeenCalledTimes( 1 );
	expect( mount.style.getPropertyValue( '--automaticffl-cart-notice-width' ) ).toBe( '' );
} );

test( 'uses the product table once hydration adds it and aligns RTL leading edges', () => {
	const mount = document.querySelector( '[data-automaticffl-cart-notice]' );
	mount.style.direction = 'rtl';
	mount.getBoundingClientRect = () => rect( 0, 1000 );
	const cart = mount.nextElementSibling;
	const main = cart.querySelector( 'main' );
	main.innerHTML = '';
	main.getBoundingClientRect = () => rect( 350, 650 );
	const dispose = alignCartNotice( mount );
	expect( mount.style.getPropertyValue( '--automaticffl-cart-notice-width' ) ).toBe( '650px' );
	const table = document.createElement( 'table' );
	table.className = 'wc-block-cart-items';
	table.getBoundingClientRect = () => rect( 400, 600 );
	main.appendChild( table );
	resize();
	expect( mount.style.getPropertyValue( '--automaticffl-cart-notice-width' ) ).toBe( '600px' );
	expect( mount.style.getPropertyValue( '--automaticffl-cart-notice-offset' ) ).toBe( '0px' );
	expect( observer.observe ).toHaveBeenCalledWith( table );
	dispose();
} );
