/** Mount only in our own placeholder preceding the complete WooCommerce cart. */
import domReady from '@wordpress/dom-ready';
import * as element from '@wordpress/element';
import CartNoticeApp from './cart-notice-app';

domReady( () => {
	document.querySelectorAll( '[data-automaticffl-cart-notice]' ).forEach( ( mount ) => {
		if ( mount.dataset.automaticfflMounted ) {
			return;
		}
		mount.dataset.automaticfflMounted = 'true';
		if ( typeof element.createRoot === 'function' ) {
			element.createRoot( mount ).render( <CartNoticeApp mount={ mount } /> );
		} else {
			// createRoot was added in WordPress 6.2; preserve older installations.
			element.render( <CartNoticeApp mount={ mount } />, mount );
		}
	} );
} );
