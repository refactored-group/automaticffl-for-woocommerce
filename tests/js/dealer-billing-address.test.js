import React, { act } from 'react';
import { createRoot } from 'react-dom/client';

const mockReact = React;
let mockSettings, mockForced, mockCustomer, mockCart, mockExtensions, mockCollection, mockEditingShippingAddress, mockValidationErrors, mockModal, mockSessionShipping;
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
const mockUpdateCustomer = jest.fn( ( { shippingAddress } = {}, editing = true ) => {
	if ( shippingAddress ) {
		mockSessionShipping = { ...mockSessionShipping, ...shippingAddress };
	}
	return Promise.resolve( { editing } );
} );
const mockCartSelectors = {
	getCustomerData: () => mockCustomer,
	getCartData: () => mockCart,
	getNeedsShipping: () => mockCart.needsShipping,
};
const mockCheckoutSelectors = {
	getExtensionData: () => mockExtensions,
	prefersCollection: () => mockCollection,
	getEditingShippingAddress: () => mockEditingShippingAddress,
	getUseShippingAsBilling: () => false,
};
const mockSelect = ( name ) => {
	if ( name === 'wc/store/cart' ) return mockCartSelectors;
	if ( name === 'wc/store/validation' ) return { getValidationError: ( key ) => mockValidationErrors[ key ] };
	return mockCheckoutSelectors;
};
const mockCartActions = { setShippingAddress: mockShipping, setBillingAddress: mockBilling, updateCustomerData: mockUpdateCustomer };
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

let root, mount, nativeFields, Block;
const customer = { first_name: 'Alex', last_name: 'Shopper', company: '', city: 'Customer city', phone: '8015550100', address_1: 'Customer street', state: 'UT', country: 'US', postcode: '84101', custom_field: 'keep' };
const texas = { id: 101, fflID: 'TX-license', company: 'Texas dealer', address1: 'Dealer street', city: 'Dallas', stateOrProvinceCode: 'TX', countryCode: 'US', postalCode: '75201', phone: '2145550100' };
const california = { ...texas, id: 202, fflID: 'CA-license', company: 'CA dealer', stateOrProvinceCode: 'CA', postalCode: '90001' };
const puertoRico = { ...texas, id: 404, fflID: 'PR-license', company: 'PR dealer', stateOrProvinceCode: 'PR', countryCode: 'US', postalCode: '00901' };

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	mockSettings = { hasFirearms: true, isConfigured: true, isApiAvailable: true, iframeUrl: 'about:blank', ammoRestrictedStates: [ 'CA', 'NY' ] };
	mockForced = true;
	mockCollection = false;
	mockEditingShippingAddress = false;
	mockValidationErrors = {};
	mockExtensions = {};
	mockCustomer = { shippingAddress: { ...customer }, billingAddress: { ...customer } };
	mockSessionShipping = { ...customer };
	mockCart = { needsShipping: true, extensions: { automaticffl: { fflItemCount: 1, regularItemCount: 0 } } };
	mockShipping.mockClear(); mockBilling.mockClear(); mockUpdateCustomer.mockClear(); mockListeners.clear();
	global.wp = { data: { select: mockSelect, subscribe: mockSubscribe, dispatch: ( name ) => name === 'wc/store/cart' ? mockCartActions : {
		setExtensionData: ( namespace, data ) => { mockExtensions = { ...mockExtensions, [ namespace ]: data }; mockNotify(); },
		setValidationErrors: jest.fn(), clearValidationError: jest.fn(), removeNotice: jest.fn(),
	} } };
	jest.isolateModules( () => { Block = require( '../../assets/js/blocks/dealer-selection/block' ).default; } );
	mount = document.createElement( 'div' );document.body.appendChild( mount );root = createRoot( mount );
	nativeFields = document.createElement( 'div' );document.body.appendChild( nativeFields );
} );
afterEach( () => { act( () => root.unmount() );mount.remove();nativeFields.remove();expect( mockListeners.size ).toBe( 0 );delete global.wp; } );
const render = () => act( () => root.render( <Block /> ) );
const choose = ( dealer ) => {
	act( () => [ ...mount.querySelectorAll( 'button' ) ].find( ( button ) => /Find a Dealer|Change Dealer/.test( button.textContent ) ).click() );
	act( () => mockModal.onSelect( dealer ) );
};
const billingInput = ( values, verify ) => act( () => {
	// Native Woo handler order, including a possible immediate blur before React effects.
	mockBilling( values );mockShipping( values );verify?.();
} );

const nativeEditAmmoSettings = () => ( {
	...mockSettings,
	hasFirearms: false,
	hasAmmo: true,
	isAmmoOnly: true,
	isAmmoEnabled: true,
	usStates: { CA: 'California', TX: 'Texas', UT: 'Utah' },
	ammoDestinationEdit: {
		required: true,
		customerId: 77,
		physicalFields: [ 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone' ],
		requiredFields: {
			US: [ 'first_name', 'last_name', 'country', 'address_1', 'city', 'state', 'postcode' ],
		},
	},
} );

const setNativeShippingEditing = ( editing ) => {
	act( () => {
		mockEditingShippingAddress = editing;
		mockNotify();
	} );
};

test( 'initial saved ammo address remains intact and routes without a pre-dealer lock', () => {
	mockForced = false;
	const savedShipping = { ...customer, state: 'CA', postcode: '90001' };
	mockSettings = nativeEditAmmoSettings();
	const savedBilling = { ...customer, address_1: 'Billing street', state: 'UT' };
	mockCustomer = { shippingAddress: savedShipping, billingAddress: savedBilling };
	render();

	expect( mockCustomer.shippingAddress ).toEqual( savedShipping );
	expect( mockCustomer.billingAddress ).toEqual( savedBilling );
	expect( mount.textContent ).toContain( 'Find a Dealer' );
	expect( document.body.classList.contains( 'automaticffl-ffl-required' ) ).toBe( false );
} );

test( 'server-corrected checkout does not launch a competing customer update on mount', () => {
	mockForced = false;
	const savedShipping = {
		...customer,
		first_name: 'Automatic FFL',
		last_name: 'QA Test Updated',
		address_1: '5916 Lost Valley Dr',
		city: 'The Colony',
		state: 'TX',
		postcode: '75056',
	};
	const savedBilling = { ...customer, address_1: 'Separate billing street' };
	mockSettings = nativeEditAmmoSettings();
	mockCustomer = { shippingAddress: { ...savedShipping }, billingAddress: savedBilling };
	mockExtensions = { automaticffl: { fflLicense: california.fflID } };

	render();

	expect( mockUpdateCustomer ).not.toHaveBeenCalled();
	expect( mockCustomer.shippingAddress ).toMatchObject( savedShipping );
	expect( mockCustomer.billingAddress ).toEqual( savedBilling );
	expect( mockExtensions.automaticffl.fflLicense ).toBe( '' );
	expect( mount.textContent ).toBe( '' );
} );

test( 'an initial saved unrestricted address remains intact and needs no dealer', () => {
	mockForced = false;
	mockSettings = nativeEditAmmoSettings();
	mockCustomer = {
		shippingAddress: { ...customer, state: 'UT', postcode: '84101' },
		billingAddress: { ...customer },
	};
	render();
	expect( mockCustomer.shippingAddress.address_1 ).toBe( customer.address_1 );
	expect( mount.textContent ).toBe( '' );
	expect( document.body.classList.contains( 'automaticffl-ffl-required' ) ).toBe( false );
} );

test.each( [ 'guest', 'billing-only', 'API-unavailable' ] )( 'native Edit does not reset %s checkout', ( mode ) => {
	mockForced = mode === 'billing-only';
	mockSettings = {
		...nativeEditAmmoSettings(),
		isApiAvailable: mode !== 'API-unavailable',
		ammoDestinationEdit: { required: false },
	};
	const savedShipping = { ...customer, state: 'CA', postcode: '90001' };
	mockCustomer = { shippingAddress: savedShipping, billingAddress: { ...customer } };
	render();
	setNativeShippingEditing( true );
	expect( mockCustomer.shippingAddress ).toEqual( savedShipping );
} );

test( 'native Edit starts names-only guest-style entry and routes only after completion', () => {
	mockForced = false;
	mockSettings = nativeEditAmmoSettings();
	mockCustomer = {
		shippingAddress: { ...customer, state: 'CA', postcode: '90001' },
		billingAddress: { ...customer },
	};
	render();
	setNativeShippingEditing( true );

	expect( mockCustomer.shippingAddress ).toMatchObject( {
		first_name: customer.first_name,
		last_name: customer.last_name,
		country: 'US',
		company: '', address_1: '', address_2: '', city: '', state: '', postcode: '', phone: '',
	} );
	expect( mockCustomer.billingAddress.address_1 ).toBe( customer.address_1 );
	expect( mockExtensions.automaticffl.fflLicense ).toBe( '' );
	expect( mount.textContent ).toBe( '' );

	act( () => mockShipping( { address_1: 'New street' } ) );
	expect( mount.textContent ).toBe( '' );

	act( () => mockShipping( {
		address_1: 'New street', city: 'Los Angeles', state: 'CA', postcode: '90001', country: 'US', phone: '8015550199',
	} ) );
	expect( mount.textContent ).toContain( 'Find a Dealer' );
	expect( document.body.classList.contains( 'automaticffl-ffl-required' ) ).toBe( false );

	act( () => root.unmount() );
	root = createRoot( mount );
	render();
	expect( mockCustomer.shippingAddress.address_1 ).toBe( 'New street' );
	expect( mount.textContent ).toContain( 'Find a Dealer' );
} );

test( 'native validation errors keep fresh entry pending after fields are populated', () => {
	mockForced = false;
	mockSettings = nativeEditAmmoSettings();
	mockCustomer = {
		shippingAddress: { ...customer, state: 'CA', postcode: '90001' },
		billingAddress: { ...customer },
	};
	render();
	setNativeShippingEditing( true );
	mockValidationErrors.shipping_postcode = { message: 'Invalid ZIP' };
	act( () => mockShipping( { address_1: 'New', city: 'LA', state: 'CA', postcode: 'bad', country: 'US' } ) );
	expect( mount.textContent ).toBe( '' );
	delete mockValidationErrors.shipping_postcode;
	act( () => mockNotify() );
	expect( mount.textContent ).toContain( 'Find a Dealer' );
} );

test( 'pickup keeps its native address semantics when the shipping editor opens', () => {
	mockForced = false;
	mockCollection = true;
	mockSettings = nativeEditAmmoSettings();
	mockCustomer = {
		shippingAddress: { ...customer, address_1: 'Pickup contact street', state: 'CA' },
		billingAddress: { ...customer },
	};
	render();
	setNativeShippingEditing( true );
	expect( mockCustomer.shippingAddress.address_1 ).toBe( 'Pickup contact street' );
	expect( mount.textContent ).toBe( '' );
} );

test( 'native Edit after dealer selection clears the dealer and restarts fresh entry', () => {
	mockForced = false;
	const savedShipping = { ...customer, state: 'CA', postcode: '90001' };
	mockSettings = nativeEditAmmoSettings();
	mockCustomer = {
		shippingAddress: savedShipping,
		billingAddress: { ...customer },
	};
	render();
	choose( california );
	expect( mockExtensions.automaticffl.fflLicense ).toBe( 'CA-license' );

	setNativeShippingEditing( true );
	expect( mockExtensions.automaticffl.fflLicense ).toBe( '' );
	expect( mockCustomer.shippingAddress ).toMatchObject( {
		first_name: customer.first_name,
		last_name: customer.last_name,
		address_1: '', city: '', state: '', postcode: '', phone: '',
	} );
	expect( mount.textContent ).toBe( '' );
	expect( document.body.classList.contains( 'automaticffl-ffl-required' ) ).toBe( false );
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

test.each( [ false, true ] )( 'ammo keeps an unrestricted-state dealer through updates, replacement and remount (billing-only: %s)', ( forced ) => {
	mockForced = forced;
	mockSettings = { ...mockSettings, hasFirearms: false, hasAmmo: true, isAmmoOnly: true, isAmmoEnabled: true };
	mockCustomer = { shippingAddress: { ...customer, state: 'CA' }, billingAddress: { ...customer, state: 'CA' } };
	render();
	// The native editor stays usable until a dealer has actually been selected.
	expect( document.body.classList.contains( 'automaticffl-ffl-required' ) ).toBe( false );
	choose( texas );

	const expectDealer = ( dealer ) => {
		expect( mockExtensions.automaticffl ).toMatchObject( {
			fflDealerId: String( dealer.id ), fflLicense: dealer.fflID, fflDealerState: dealer.stateOrProvinceCode,
		} );
		expect( mockCustomer.shippingAddress ).toMatchObject( { state: dealer.stateOrProvinceCode, address_1: dealer.address1 } );
		expect( document.body.classList.contains( 'automaticffl-ffl-required' ) ).toBe( true );
		expect( mount.textContent ).toContain( 'Change Dealer' );
	};
	expectDealer( texas );
	act( () => { mockNotify();mockNotify(); } );
	expectDealer( texas );

	if ( forced ) {
		billingInput( { ...customer, state: 'NY' } );
	} else {
		act( () => mockBilling( { ...customer, state: 'UT' } ) );
	}
	expectDealer( texas );

	const colorado = { ...texas, id: 303, fflID: 'CO-license', stateOrProvinceCode: 'CO' };
	choose( colorado );
	expectDealer( colorado );
	act( () => root.unmount() );
	root = createRoot( mount );render();
	expectDealer( colorado );
} );

test( 'ammo still allows an unrestricted destination before a dealer is chosen', () => {
	mockForced = false;
	mockSettings = { ...mockSettings, hasFirearms: false, hasAmmo: true, isAmmoOnly: true, isAmmoEnabled: true };
	mockCustomer.shippingAddress.state = 'CA';
	render();
	expect( mount.textContent ).toContain( 'Find a Dealer' );
	act( () => mockShipping( { state: 'UT' } ) );
	expect( document.body.classList.contains( 'automaticffl-ffl-required' ) ).toBe( false );
	expect( mount.textContent ).toBe( '' );
} );

test( 'ammo uses WooCommerce native territory countries for rules and dealer shipping', () => {
	mockSettings = {
		...mockSettings,
		hasFirearms: false,
		hasAmmo: true,
		isAmmoOnly: true,
		isAmmoEnabled: true,
		ammoRestrictedStates: [ 'PR' ],
		usStates: { PR: 'Puerto Rico' },
	};
	mockCustomer = {
		shippingAddress: { ...customer, country: 'PR', state: '' },
		billingAddress: { ...customer, country: 'PR', state: '' },
	};
	render();
	expect( mount.textContent ).toContain( 'Find a Dealer' );
	choose( puertoRico );
	expect( mockExtensions.automaticffl ).toMatchObject( {
		fflDealerCountry: 'US',
		fflDealerState: 'PR',
	} );
	expect( mockCustomer.shippingAddress ).toMatchObject( {
		country: 'PR',
		state: '',
		address_1: puertoRico.address1,
	} );
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
