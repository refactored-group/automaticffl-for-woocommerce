/* eslint-disable import/no-extraneous-dependencies */
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';

import SelectedDealerCard from '../../assets/js/blocks/dealer-selection/components/SelectedDealerCard';

jest.mock( '@wordpress/i18n', () => ( { __: ( text ) => text } ), {
	virtual: true,
} );

test( 'identifies the retained card as the selected FFL dealer', () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	const mount = document.createElement( 'div' );
	document.body.appendChild( mount );
	const root = createRoot( mount );

	act( () => {
		root.render(
			<SelectedDealerCard
				dealer={ {
					company: 'RIFLEGEAR',
					address1: '4001 STATE HWY 121',
					city: 'THE COLONY',
					stateOrProvinceCode: 'TX',
					phone: '9722927678',
				} }
				userName={ { first_name: 'Giancarlo', last_name: 'Byrne' } }
			/>
		);
	} );

	expect(
		mount.querySelector( '.automaticffl-dealer-selected > p' ).textContent
	).toBe( 'Selected FFL dealer:' );
	expect( mount.textContent ).not.toContain(
		'Your order will be shipped to:'
	);
	expect( mount.textContent ).toContain( 'Giancarlo Byrne' );
	expect( mount.textContent ).toContain( 'RIFLEGEAR' );
	expect( mount.textContent ).toContain(
		'4001 STATE HWY 121, THE COLONY, TX'
	);
	expect( mount.textContent ).toContain( '(972)-292-7678' );

	act( () => root.unmount() );
	mount.remove();
} );
