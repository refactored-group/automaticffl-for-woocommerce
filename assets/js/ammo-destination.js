( function( root, factory ) {
	const api = factory();

	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	}

	if ( root ) {
		root.AutomaticFflAmmoDestination = api;
	}
}( typeof window !== 'undefined' ? window : globalThis, function() {
	'use strict';

	const territoryCodes = [ 'AS', 'GU', 'MP', 'PR', 'VI' ];

	const normalize = ( value ) => String( value || '' ).trim().toUpperCase();

	/**
	 * Resolve WooCommerce's country/state shape to an Automatic FFL code.
	 * Territories are countries in WooCommerce; D.C. remains a US state.
	 */
	const resolve = ( country, state, supportedCodes ) => {
		const normalizedCountry = normalize( country );
		const normalizedState = normalize( state );

		if ( territoryCodes.includes( normalizedCountry ) ) {
			return normalizedCountry;
		}

		if ( normalizedCountry !== 'US' || ! normalizedState ) {
			return '';
		}

		if ( Array.isArray( supportedCodes ) && supportedCodes.length > 0 && ! supportedCodes.includes( normalizedState ) ) {
			return '';
		}

		return normalizedState;
	};

	/** Convert a dealer address into WooCommerce's native country/state shape. */
	const toWooCommerce = ( country, state ) => {
		const normalizedCountry = normalize( country ) || 'US';
		const normalizedState = normalize( state );

		if ( normalizedCountry === 'US' && territoryCodes.includes( normalizedState ) ) {
			return { country: normalizedState, state: '' };
		}

		if ( territoryCodes.includes( normalizedCountry ) ) {
			return { country: normalizedCountry, state: '' };
		}

		return { country: normalizedCountry, state: normalizedState };
	};

	return { territoryCodes, resolve, toWooCommerce };
} ) );
