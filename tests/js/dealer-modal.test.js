import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import DealerModal from '../../assets/js/blocks/dealer-selection/components/DealerModal';

jest.mock( '@wordpress/element', () => require( 'react' ), { virtual: true } );
let mount;
let root;
let originalScrollTo;
beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	mount = document.createElement( 'div' );
	document.body.appendChild( mount );
	root = createRoot( mount );
	originalScrollTo = window.scrollTo;
	window.scrollTo = jest.fn();
	Object.defineProperty( window, 'scrollY', { configurable: true, value: 640 } );
	Object.defineProperty( window, 'scrollX', { configurable: true, value: 12 } );
	document.body.style.position = 'relative';
	document.body.style.top = '5px';
	document.documentElement.style.setProperty( 'overflow', 'scroll', 'important' );
	document.documentElement.style.scrollBehavior = 'smooth';
} );
afterEach( () => {
	act( () => root.unmount() );
	mount.remove();
	document.body.removeAttribute( 'style' );
	document.documentElement.removeAttribute( 'style' );
	window.scrollTo = originalScrollTo;
} );

test( 'locks the actual page and restores its position and existing styles on close', () => {
	const props = { iframeUrl: 'about:blank', allowedOrigins: [ 'https://example.test' ], onSelect: jest.fn(), onClose: jest.fn() };
	act( () => root.render( <DealerModal { ...props } /> ) );
	expect( document.body.style.position ).toBe( 'fixed' );
	expect( document.body.style.top ).toBe( '-640px' );
	expect( document.body.style.left ).toBe( '-12px' );
	expect( document.documentElement.style.overflow ).toBe( 'hidden' );
	// Inline callback identities change during checkout updates; retain the opening offset.
	Object.defineProperty( window, 'scrollY', { configurable: true, value: 0 } );
	act( () => root.render( <DealerModal { ...props } onClose={ () => {} } /> ) );
	expect( document.body.style.top ).toBe( '-640px' );
	expect( window.scrollTo ).not.toHaveBeenCalled();
	document.body.style.color = 'red';
	act( () => root.render( null ) );
	expect( document.body.style.position ).toBe( 'relative' );
	expect( document.body.style.top ).toBe( '5px' );
	expect( document.body.style.color ).toBe( 'red' );
	expect( document.documentElement.style.overflow ).toBe( 'scroll' );
	expect( document.documentElement.style.getPropertyPriority( 'overflow' ) ).toBe( 'important' );
	expect( document.documentElement.style.scrollBehavior ).toBe( 'smooth' );
	expect( window.scrollTo ).toHaveBeenCalledTimes( 1 );
	expect( window.scrollTo ).toHaveBeenCalledWith( 12, 640 );
} );

test( 'selection and cancel messages keep the same validation and clean up the lock', () => {
	const onSelect = jest.fn();
	const onClose = jest.fn();
	act( () => root.render( <DealerModal iframeUrl="about:blank" allowedOrigins={ [ 'https://example.test' ] } onSelect={ onSelect } onClose={ onClose } /> ) );
	const send = ( origin, data ) => act( () => window.dispatchEvent( new MessageEvent( 'message', { origin, data } ) ) );
	send( 'https://untrusted.test', { type: 'dealerUpdate', value: { fflID: 'fixture' } } );
	expect( onSelect ).not.toHaveBeenCalled();
	send( 'https://example.test', { type: 'dealerUpdate', value: {} } );
	expect( onSelect ).not.toHaveBeenCalled();
	send( 'https://example.test', { type: 'dealerUpdate', value: { fflID: 'fixture' } } );
	expect( onSelect ).toHaveBeenCalledWith( { fflID: 'fixture' } );
	send( 'https://example.test', { type: 'closeModal' } );
	expect( onClose ).toHaveBeenCalledTimes( 1 );
	act( () => root.render( null ) );
	expect( window.scrollTo ).toHaveBeenCalledWith( 12, 640 );
} );
