const destinationEntry = require( '../../assets/js/ammo-destination-entry' );

const config = {
	physicalFields: [
		'company',
		'address_1',
		'address_2',
		'city',
		'state',
		'postcode',
		'phone',
	],
	requiredFields: {
		US: [
			'first_name',
			'last_name',
			'country',
			'address_1',
			'city',
			'state',
			'postcode',
		],
		PR: [
			'first_name',
			'last_name',
			'country',
			'address_1',
			'city',
			'postcode',
		],
	},
};

test( 'creates a names-only active destination without mutating the saved source', () => {
	const saved = {
		first_name: 'Alex',
		last_name: 'Shopper',
		country: 'US',
		phone: '8015550100',
		email: 'alex@example.com',
		address_1: 'Saved street',
		city: 'Los Angeles',
		state: 'CA',
		postcode: '90001',
		custom_field: 'keep',
	};

	expect(
		destinationEntry.blankForEdit( saved, config.physicalFields )
	).toEqual( {
		...saved,
		company: '',
		address_1: '',
		address_2: '',
		city: '',
		state: '',
		postcode: '',
		phone: '',
	} );
	expect( saved.address_1 ).toBe( 'Saved street' );
	expect( saved.phone ).toBe( '8015550100' );
} );

test( 'requires a country-aware complete destination with no native validation errors', () => {
	const address = {
		first_name: 'Alex',
		last_name: 'Shopper',
		country: 'US',
		address_1: 'New street',
		city: 'Dallas',
		state: 'TX',
		postcode: '75201',
	};
	expect(
		destinationEntry.isComplete( address, config, {}, 'shipping' )
	).toBe( true );
	expect(
		destinationEntry.isComplete(
			{ ...address, city: '' },
			config,
			{},
			'shipping'
		)
	).toBe( false );
	expect(
		destinationEntry.isComplete(
			address,
			config,
			{ shipping_postcode: 'Invalid ZIP' },
			'shipping'
		)
	).toBe( false );
	expect(
		destinationEntry.isComplete(
			{ ...address, country: 'PR', state: '' },
			config,
			{},
			'shipping'
		)
	).toBe( true );
} );

test( 'separate browser-tab realms keep independent native-edit state', () => {
	const firstTab = destinationEntry.createEditState( 77 );
	const secondTab = destinationEntry.createEditState( 77 );
	firstTab.fresh = true;
	firstTab.ready = true;
	firstTab.previousEditing = true;

	expect( secondTab ).toEqual( {
		customerId: 77,
		accountRestored: false,
		fresh: false,
		ready: true,
		previousEditing: null,
	} );
} );
