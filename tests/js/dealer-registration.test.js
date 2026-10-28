let mockSettings = {};
const mockCheckoutRegister = jest.fn();
const mockEditorRegister = jest.fn();
jest.mock( '@woocommerce/settings', () => ( { getSetting: () => mockSettings } ), { virtual: true } );
jest.mock( '@woocommerce/blocks-checkout', () => ( { registerCheckoutBlock: ( ...args ) => mockCheckoutRegister( ...args ) } ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: ( ...args ) => mockEditorRegister( ...args ) } ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => ( { useBlockProps: () => ( {} ) } ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( text ) => text } ), { virtual: true } );
jest.mock( '../../assets/js/blocks/dealer-selection/block', () => () => null );

beforeEach( () => {
	jest.resetModules();
	mockCheckoutRegister.mockClear();
	mockEditorRegister.mockClear();
	mockSettings = {};
} );

test.each( [
	[ undefined, 'woocommerce/checkout-shipping-address-block' ],
	[ 'woocommerce/checkout-shipping-address-block', 'woocommerce/checkout-shipping-address-block' ],
	[ 'woocommerce/checkout-shipping-methods-block', 'woocommerce/checkout-shipping-methods-block' ],
] )( 'frontend and editor register one matching parent for %s', ( setting, expected ) => {
	mockSettings = { dealerSelectionParent: setting };
	require( '../../assets/js/blocks/dealer-selection/frontend' );
	require( '../../assets/js/blocks/dealer-selection/index' );
	expect( mockCheckoutRegister ).toHaveBeenCalledTimes( 1 );
	expect( mockEditorRegister ).toHaveBeenCalledTimes( 1 );
	const frontend = mockCheckoutRegister.mock.calls[ 0 ][ 0 ].metadata;
	const [ name, editor ] = mockEditorRegister.mock.calls[ 0 ];
	expect( frontend.name ).toBe( 'automaticffl/dealer-selection-shipping' );
	expect( name ).toBe( frontend.name );
	expect( frontend.parent ).toEqual( [ expected ] );
	expect( editor.parent ).toEqual( frontend.parent );
	// Real saved layouts contain a self-closing block comment, not a second HTML marker.
	expect( editor.save() ).toBeNull();
} );
