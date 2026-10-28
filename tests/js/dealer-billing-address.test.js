import React, { act } from 'react';
import { createRoot } from 'react-dom/client';

const mockReact = React;
let mockSettings, mockForced, mockCustomer, mockCart, mockExtensions, mockCollection, mockModal;
const mockListeners = new Set();
const mockNotify = () => [ ...mockListeners ].forEach( ( callback ) => callback() );
const mockShipping = jest.fn( ( address ) => {
	mockCustomer = { ...mockCustomer, shippingAddress: { ...mockCustomer.shippingAddress, ...address } };
	mockNotify();
} );
const mockBilling = jest.fn( ( address ) => {
	mockCustomer = { ...mockCustomer, billingAddress: { ...mockCustomer.billingAddress, ...address } };
	mockNotify();
} );
const mockCartSelectors = {
	getCustomerData: () => mockCustomer,
	getCartData: () => mockCart,
	getNeedsShipping: () => mockCart.needsShipping,
};
const mockCheckoutSelectors = {
	getExtensionData: () => mockExtensions,
	prefersCollection: () => mockCollection,
	getUseShippingAsBilling: () => false,
};
const mockSelect = ( name ) => name === 'wc/store/cart' ? mockCartSelectors : mockCheckoutSelectors;
const mockCartActions = { setShippingAddress: mockShipping, setBillingAddress: mockBilling };
const mockSubscribe = ( callback ) => { mockListeners.add( callback ); return () => mockListeners.delete( callback ); };
jest.mock( '@wordpress/element', () => mockReact, { virtual: true } );
jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => mockCartActions,
	useSelect: ( selector ) => mockReact.useSyncExternalStore( mockSubscribe, () => selector( mockSelect ) ),
} ), { virtual: true } );
jest.mock( '@woocommerce/block-data', () => ( { CART_STORE_KEY: 'wc/store/cart' } ), { virtual: true } );
jest.mock( '@woocommerce/settings', () => ( { getSetting: ( key, fallback ) => key === 'automaticffl_data' ? mockSettings : key === 'forcedBillingAddress' ? mockForced : fallback } ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( text ) => text } ), { virtual: true } );
jest.mock( '../../assets/js/blocks/dealer-selection/components/DealerModal', () => ( props ) => { mockModal = props; return null; } );
jest.mock( '../../assets/js/blocks/dealer-selection/components/SelectedDealerCard', () => () => null );
jest.mock( '../../assets/js/blocks/dealer-selection/components/MixedCartNotice', () => () => null );

let root, mount, Block;
const customer = { first_name: 'Alex', last_name: 'Shopper', company: '', city: 'Customer city', phone: '8015550100', address_1: 'Customer street', state: 'UT', country: 'US', postcode: '84101', custom_field: 'keep' };
const texas = { id: 101, fflID: 'TX-license', company: 'Texas dealer', address1: 'Dealer street', city: 'Dallas', stateOrProvinceCode: 'TX', countryCode: 'US', postalCode: '75201', phone: '2145550100' };
const california = { ...texas, id: 202, fflID: 'CA-license', company: 'CA dealer', stateOrProvinceCode: 'CA', postalCode: '90001' };

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	mockSettings = { hasFirearms: true, isConfigured: true, isApiAvailable: true, iframeUrl: 'about:blank', ammoRestrictedStates: [ 'CA', 'NY' ] };
	mockForced = true;
	mockCollection = false;
	mockExtensions = {};
	mockCustomer = { shippingAddress: { ...customer }, billingAddress: { ...customer } };
	mockCart = { needsShipping: true, extensions: { automaticffl: { fflItemCount: 1, regularItemCount: 0 } } };
	mockShipping.mockClear(); mockBilling.mockClear(); mockListeners.clear();
	global.wp = { data: { select: mockSelect, subscribe: mockSubscribe, dispatch: ( name ) => name === 'wc/store/cart' ? mockCartActions : {
		setExtensionData: ( namespace, data ) => { mockExtensions = { ...mockExtensions, [ namespace ]: data }; mockNotify(); },
		setValidationErrors: jest.fn(), clearValidationError: jest.fn(), removeNotice: jest.fn(),
	} } };
	jest.isolateModules( () => { Block = require( '../../assets/js/blocks/dealer-selection/block' ).default; } );
	mount = document.createElement( 'div' );document.body.appendChild( mount );root = createRoot( mount );
} );
afterEach( () => { act( () => root.unmount() );mount.remove();expect( mockListeners.size ).toBe( 0 );delete global.wp; } );
const render = () => act( () => root.render( <Block /> ) );
const choose = ( dealer ) => {
	act( () => [ ...mount.querySelectorAll( 'button' ) ].find( ( button ) => /Find a Dealer|Change Dealer/.test( button.textContent ) ).click() );
	act( () => mockModal.onSelect( dealer ) );
};
const billingInput = ( values, verify ) => act( () => {
	// Native Woo handler order, including a possible immediate blur before React effects.
	mockBilling( values );mockShipping( values );verify?.();
} );

test( 'billing copy is corrected synchronously before blur, without billing writes or idle loops', () => {
	render();choose( texas );mockBilling.mockClear();
	for ( const state of [ 'CA', 'NY', 'CA' ] ) {
		const values = { ...customer, first_name: 'Avery', state, postcode: '90001' };
		billingInput( values, () => expect( mockCustomer.shippingAddress ).toMatchObject( { first_name: 'Avery', state: 'TX', address_1: 'Dealer street', custom_field: 'keep' } ) );
		expect( mockCustomer.billingAddress ).toEqual( values );
	}
	expect( mockBilling ).toHaveBeenCalledTimes( 3 );
	const calls = mockShipping.mock.calls.length;
	act( () => { mockNotify();mockNotify(); } );expect( mockShipping ).toHaveBeenCalledTimes( calls );
} );

test( 'reads the current dealer on change and stops immediately when extension data is cleared', () => {
	render();choose( texas );choose( california );
	billingInput( { ...customer, state: 'NY' }, () => expect( mockCustomer.shippingAddress ).toMatchObject( { company: 'CA dealer', state: 'CA' } ) );
	act( () => wp.data.dispatch( 'wc/store/checkout' ).setExtensionData( 'automaticffl', {} ) );
	billingInput( customer, () => expect( mockCustomer.shippingAddress.address_1 ).toBe( customer.address_1 ) );
} );

test.each( [ 'normal', 'pickup', 'no-shipping', 'non-ffl', 'mixed' ] )( 'leaves %s address updates alone', ( mode ) => {
	mockForced = mode !== 'normal';render();choose( texas );
	if ( mode === 'pickup' ) mockCollection = true;
	if ( mode === 'no-shipping' ) mockCart.needsShipping = false;
	if ( mode === 'non-ffl' ) mockCart.extensions.automaticffl.fflItemCount = 0;
	if ( mode === 'mixed' ) mockCart.extensions.automaticffl.isFirearmsRegularMixed = true;
	billingInput( customer, () => expect( mockCustomer.shippingAddress.address_1 ).toBe( customer.address_1 ) );
} );

test( 'before selection, native billing-only address copying remains unchanged', () => {
	render();billingInput( { ...customer, state: 'CA' }, () => expect( mockCustomer.shippingAddress.state ).toBe( 'CA' ) );
} );

test( 'ammo retains dealer for restricted billing edits and releases it for unrestricted billing', () => {
	mockSettings = { ...mockSettings, hasFirearms: false, hasAmmo: true, isAmmoOnly: true, isAmmoEnabled: true };
	mockCustomer = { shippingAddress: { ...customer, state: 'CA' }, billingAddress: { ...customer, state: 'CA' } };
	render();choose( california );
	billingInput( { ...customer, state: 'NY' }, () => expect( mockCustomer.shippingAddress ).toMatchObject( { company: 'CA dealer', state: 'CA' } ) );
	billingInput( { ...customer, state: 'UT' } );
	expect( mockExtensions.automaticffl.fflLicense ).toBe( '' );
	expect( mockCustomer.shippingAddress.state ).toBe( 'UT' );
	expect( mockCustomer.billingAddress.state ).toBe( 'UT' );
} );
