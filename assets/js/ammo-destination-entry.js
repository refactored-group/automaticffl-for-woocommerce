/* global globalThis */
/**
 * Native ammunition destination edit helpers.
 *
 * These pure helpers are shared by Checkout Blocks. WooCommerce owns the
 * address card, Edit action, form, and native validation.
 *
 * @param {Window|Object} root    Browser or module global.
 * @param {Function}      factory API factory.
 */
( function ( root, factory ) {
	const api = factory();

	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
		return;
	}

	root.AutomaticFflAmmoDestinationEntry = api;
} )( typeof window !== 'undefined' ? window : globalThis, function () {
	const DEFAULT_REQUIRED = [
		'first_name',
		'last_name',
		'country',
		'address_1',
		'city',
		'state',
		'postcode',
	];

	const createEditState = ( customerId = null ) => ( {
		customerId,
		accountRestored: false,
		fresh: false,
		ready: true,
		previousEditing: null,
	} );

	const blankForEdit = ( address = {}, physicalFields = [] ) =>
		physicalFields.reduce(
			( result, field ) => ( { ...result, [ field ]: '' } ),
			{
				...address,
			}
		);

	const requiredFor = ( config = {}, country = '' ) => {
		const configured = config.requiredFields?.[ country ];
		return Array.isArray( configured ) && configured.length
			? configured
			: DEFAULT_REQUIRED;
	};

	const isComplete = (
		address = {},
		config = {},
		validation = null,
		prefix = 'shipping'
	) => {
		const required = requiredFor( config, address.country || '' );
		return required.every( ( field ) => {
			if ( ! String( address[ field ] || '' ).trim() ) {
				return false;
			}
			const key = `${ prefix }_${ field }`;
			const error = validation?.getValidationError
				? validation.getValidationError( key )
				: validation?.[ key ];
			return ! ( error?.message || error );
		} );
	};

	return {
		blankForEdit,
		createEditState,
		isComplete,
		requiredFor,
	};
} );
