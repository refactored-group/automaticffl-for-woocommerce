import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { Simulate } from 'react-dom/test-utils';
import CartNotice from '../../assets/js/blocks/cart-notice/cart-notice';
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/element', () => require( 'react' ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( value ) => value, _n: ( single, plural, count ) => count === 1 ? single : plural, sprintf: ( value, ...args ) => value.replace( /%[sd]/g, () => args.shift() ) } ), { virtual: true } );
jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock( '@woocommerce/blocks-checkout', () => ( { extensionCartUpdate: jest.fn() } ), { virtual: true } );
jest.mock( '@woocommerce/settings', () => ( { getSetting: () => ( { usStates: { CA: 'California', UT: 'Utah' }, checkoutUrl: '/checkout/' } ) } ), { virtual: true } );

const mixed = {
	isApiAvailable: true,
	isFirearmsRegularMixed: true,
	isAmmoRegularMixed: false,
	fflItemCount: 3,
	regularItemCount: 2,
	ammoRestrictedStates: [ 'CA' ],
	selectedAmmoState: '',
};
const ammo = { ...mixed, isFirearmsRegularMixed: false, isAmmoRegularMixed: true };
const gate = 'automaticffl-cart-blocked';
let container;
let root;
const originalLocation = window.location;
beforeAll( () => {
	delete window.location;
	window.location = { reload: jest.fn(), assign: jest.fn() };
} );
afterAll( () => { window.location = originalLocation; } );
const render = ( data, context = 'woocommerce/cart' ) => {
	act( () => root.render( <CartNotice context={ context } extensions={ { automaticffl: data } } /> ) );
};
const selectState = ( state ) => act( () => {
	Simulate.change( container.querySelector( 'select' ), { target: { value: state } } );
} );

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	jest.clearAllMocks();
} );
afterEach( () => {
	act( () => root.unmount() );
	container.remove();
	expect( document.body.classList.contains( gate ) ).toBe( false );
} );

test( 'firearm mixed banner uses live quantities and disappears after cart resolution', () => {
	render( mixed );
	expect( container.querySelectorAll( '[role="alert"]' ) ).toHaveLength( 1 );
	expect( container.textContent ).toContain( 'These items need separate orders' );
	expect( container.querySelector( '.automaticffl-mixed-notice--separate' ) ).not.toBeNull();
	expect( container.textContent ).toContain( 'Check out FFL items (3)' );
	expect( document.body.classList.contains( gate ) ).toBe( true );
	render( { ...mixed, fflItemCount: 5, regularItemCount: 1 } );
	expect( container.textContent ).toContain( 'Check out FFL items (5)' );
	expect( container.textContent ).toContain( 'Check out regular item (1)' );
	render( { ...mixed, isFirearmsRegularMixed: false, regularItemCount: 0 } );
	expect( container.textContent ).toBe( '' );
	expect( document.body.classList.contains( gate ) ).toBe( false );
} );

test.each( [ undefined, { ...mixed, isApiAvailable: false }, { ...mixed, isFirearmsRegularMixed: false, fflItemCount: 0, regularItemCount: 0 } ] )( 'does not block missing, unavailable or empty cart data', ( data ) => {
	render( data );
	expect( container.textContent ).toBe( '' );
	expect( document.body.classList.contains( gate ) ).toBe( false );
} );

test( 'never renders or gates checkout context', () => {
	render( mixed, 'woocommerce/checkout' );
	expect( container.textContent ).toBe( '' );
} );

test.each( [ [ 0, 'regular' ], [ 1, 'ffl' ] ] )( 'checkout choice %s saves the opposite group through the existing route, disables duplicates, and permits retry', async ( index, itemType ) => {
	let rejectSave;
	apiFetch.mockImplementationOnce( () => new Promise( ( resolve, reject ) => { rejectSave = reject; } ) );
	render( mixed );
	act( () => container.querySelectorAll( 'button' )[ index ].click() );
	expect( apiFetch ).toHaveBeenCalledWith( { path: '/automaticffl/v1/save-for-later', method: 'POST', data: { item_type: itemType } } );
	expect( window.location.assign ).not.toHaveBeenCalled();
	expect( Array.from( container.querySelectorAll( 'button' ) ).every( ( button ) => button.disabled ) ).toBe( true );
	await act( async () => rejectSave( new Error( 'Please retry' ) ) );
	expect( container.textContent ).toContain( 'Please retry' );
	expect( container.querySelectorAll( 'button' )[ index ].disabled ).toBe( false );
} );

test.each( [ 0, 1 ] )( 'successful checkout choice %s navigates only after saving', async ( index ) => {
	let finishSave;
	apiFetch.mockImplementationOnce( () => new Promise( ( resolve ) => { finishSave = resolve; } ) );
	render( mixed );
	act( () => { container.querySelectorAll( 'button' )[ index ].click(); container.querySelectorAll( 'button' )[ index ].click(); } );
	expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	expect( window.location.assign ).not.toHaveBeenCalled();
	await act( async () => finishSave( { success: true } ) );
	expect( window.location.assign ).toHaveBeenCalledWith( '/checkout/' );
	expect( window.location.reload ).not.toHaveBeenCalled();
} );

test( 'ammo states move between info, restriction and success using confirmed data', () => {
	render( ammo );
	expect( container.querySelector( 'label' ).htmlFor ).toBe( container.querySelector( 'select' ).id );
	expect( container.textContent ).toContain( 'Please select your shipping state' );
	expect( container.querySelectorAll( 'button' ) ).toHaveLength( 0 );
	expect( document.body.classList.contains( gate ) ).toBe( true );
	render( { ...ammo, selectedAmmoState: 'CA' } );
	expect( container.textContent ).toContain( 'California' );
	expect( container.querySelector( '.automaticffl-mixed-notice--separate' ) ).not.toBeNull();
	expect( container.querySelectorAll( 'button' ) ).toHaveLength( 2 );
	render( { ...ammo, selectedAmmoState: 'UT' } );
	expect( container.textContent ).toContain( 'You may proceed to checkout.' );
	expect( container.querySelector( '.automaticffl-mixed-notice--separate' ) ).toBeNull();
	expect( container.querySelectorAll( 'button' ) ).toHaveLength( 0 );
	expect( document.body.classList.contains( gate ) ).toBe( false );
} );

test( 'state update waits for confirmed response and keeps prior state on failure', async () => {
	let rejectUpdate;
	extensionCartUpdate.mockImplementationOnce( () => new Promise( ( resolve, reject ) => { rejectUpdate = reject; } ) );
	render( { ...ammo, selectedAmmoState: 'CA' } );
	selectState( 'UT' );
	expect( extensionCartUpdate ).toHaveBeenCalledWith( { namespace: 'automaticffl-cart-notice', data: { state: 'UT' } } );
	expect( container.querySelector( 'select' ).disabled ).toBe( true );
	expect( document.body.classList.contains( gate ) ).toBe( true );
	await act( async () => rejectUpdate( new Error( 'State save failed' ) ) );
	expect( container.querySelector( 'select' ).value ).toBe( 'CA' );
	expect( container.querySelector( 'select' ).disabled ).toBe( false );
	expect( container.textContent ).toContain( 'State save failed' );
	extensionCartUpdate.mockResolvedValueOnce( {} );
	await act( async () => selectState( 'UT' ) );
	render( { ...ammo, selectedAmmoState: 'UT' } );
	expect( document.body.classList.contains( gate ) ).toBe( false );
} );

test( 'pending change blocks an otherwise eligible ammo cart until completion', async () => {
	let finishUpdate;
	extensionCartUpdate.mockImplementationOnce( () => new Promise( ( resolve ) => { finishUpdate = resolve; } ) );
	render( { ...ammo, selectedAmmoState: 'UT' } );
	selectState( 'CA' );
	expect( document.body.classList.contains( gate ) ).toBe( true );
	await act( async () => finishUpdate() );
	render( { ...ammo, selectedAmmoState: 'CA' } );
	expect( document.body.classList.contains( gate ) ).toBe( true );
} );


test( 'both mixed-cart banners explain restoration and use pluralized quantity totals', () => {
	render( { ...mixed, fflItemCount: 1, regularItemCount: 4 } );
	expect( container.textContent ).toContain( 'Check out FFL item (1)' );
	expect( container.textContent ).toContain( 'Check out regular items (4)' );
	expect( container.textContent ).toContain( 'The other items will be saved and put back in your cart after checkout.' );
	render( { ...ammo, selectedAmmoState: 'CA', fflItemCount: 2, regularItemCount: 1 } );
	expect( container.textContent ).toContain( 'Check out ammo items (2)' );
	expect( container.textContent ).toContain( 'Check out regular item (1)' );
	expect( container.textContent ).toContain( 'The other items will be saved and put back in your cart after checkout.' );
} );
