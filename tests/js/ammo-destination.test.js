const destination = require( '../../assets/js/ammo-destination' );

describe( 'WooCommerce ammunition destination normalization', () => {
	test( 'keeps D.C. in the native US state model', () => {
		expect( destination.resolve( 'US', 'DC', [ 'DC' ] ) ).toBe( 'DC' );
		expect( destination.toWooCommerce( 'US', 'DC' ) ).toEqual( { country: 'US', state: 'DC' } );
	} );

	test.each( [ 'AS', 'GU', 'MP', 'PR', 'VI' ] )( 'resolves native WooCommerce territory country %s', ( code ) => {
		expect( destination.resolve( code, '' ) ).toBe( code );
		expect( destination.toWooCommerce( 'US', code ) ).toEqual( { country: code, state: '' } );
		expect( destination.toWooCommerce( code, code ) ).toEqual( { country: code, state: '' } );
	} );

	test( 'does not infer a destination from a foreign state-code collision', () => {
		expect( destination.resolve( 'CA', 'PR' ) ).toBe( '' );
		expect( destination.resolve( 'MX', 'DC' ) ).toBe( '' );
		expect( destination.resolve( '', 'PR' ) ).toBe( '' );
	} );
} );
