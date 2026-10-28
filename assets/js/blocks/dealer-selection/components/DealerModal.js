/**
 * Dealer Modal Component
 *
 * Modal overlay containing the FFL dealer selection iframe.
 */

import { useEffect, useLayoutEffect, useCallback } from '@wordpress/element';
import { createPortal } from 'react-dom';

/** Freeze the page itself, including iframe-driven programmatic scrolling. */
const lockPageScroll = () => {
	const body = document.body;
	const html = document.documentElement;
	const x = window.scrollX;
	const y = window.scrollY;
	const width = body.getBoundingClientRect().width;
	const properties = [ 'position', 'top', 'left', 'width', 'box-sizing', 'overflow' ];
	const snapshot = ( style, property ) => [ property, style.getPropertyValue( property ), style.getPropertyPriority( property ) ];
	const originalBody = properties.map( ( property ) => snapshot( body.style, property ) );
	const originalOverflow = snapshot( html.style, 'overflow' );
	const originalScrollBehavior = snapshot( html.style, 'scroll-behavior' );
	const restore = ( style, [ property, value, priority ] ) => {
		if ( value ) style.setProperty( property, value, priority );
		else style.removeProperty( property );
	};

	html.style.setProperty( 'overflow', 'hidden', 'important' );
	body.style.position = 'fixed';
	body.style.top = `${ -y }px`;
	body.style.left = `${ -x }px`;
	body.style.width = width > 0 ? `${ width }px` : '100%';
	body.style.boxSizing = 'border-box';
	body.style.overflow = 'hidden';

	return () => {
		originalBody.forEach( ( property ) => restore( body.style, property ) );
		restore( html.style, originalOverflow );
		// Theme smooth scrolling must not animate restoration of the locked offset.
		html.style.setProperty( 'scroll-behavior', 'auto', 'important' );
		window.scrollTo( x, y );
		restore( html.style, originalScrollBehavior );
	};
};

/**
 * DealerModal Component
 *
 * @param {Object} props Component props
 * @param {string} props.iframeUrl URL for the dealer selection iframe
 * @param {Array} props.allowedOrigins Allowed origins for postMessage validation
 * @param {Function} props.onSelect Callback when dealer is selected
 * @param {Function} props.onClose Callback when modal should close
 * @return {JSX.Element} Modal component
 */
const DealerModal = ( { iframeUrl, allowedOrigins, onSelect, onClose } ) => {
	// Capture once per opening; checkout updates can change callback identities.
	useLayoutEffect( lockPageScroll, [] );

	/**
	 * Handle postMessage from iframe
	 */
	const handleMessage = useCallback(
		( event ) => {
			// Validate origin
			if ( ! allowedOrigins.includes( event.origin ) ) {
				return;
			}

			// Handle dealer selection message.
			//
			// Origin validation above gates *who* can send a message; this
			// guard validates *what* they sent. A misbehaving iframe (stale
			// CDN cache, future contractor bug, staging env mistake)
			// sending non-string fields like { company: {} } would
			// otherwise flow into setShippingAddress / setExtensionData and
			// into React text rendering — where a non-string child throws
			// "Objects are not valid as a React child" and crashes the
			// entire checkout block. Required string is fflID; everything
			// else gets defaulted in handleDealerSelect via `|| ''`.
			if ( event.data && event.data.type === 'dealerUpdate' ) {
				const dealer = event.data.value;
				if (
					dealer &&
					typeof dealer === 'object' &&
					typeof dealer.fflID === 'string'
				) {
					onSelect( dealer );
				}
			}

			// Handle close modal message
			if ( event.data && event.data.type === 'closeModal' ) {
				onClose();
			}
		},
		[ allowedOrigins, onSelect, onClose ]
	);

	/**
	 * Handle keyboard events (Escape to close)
	 */
	const handleKeyDown = useCallback(
		( event ) => {
			if ( event.key === 'Escape' ) {
				onClose();
			}
		},
		[ onClose ]
	);

	/**
	 * Handle click on overlay (outside modal content)
	 */
	const handleOverlayClick = useCallback(
		( event ) => {
			if ( event.target === event.currentTarget ) {
				onClose();
			}
		},
		[ onClose ]
	);

	/**
	 * Set up event listeners independently of the page scroll lock.
	 */
	useEffect( () => {
		// Add event listeners
		window.addEventListener( 'message', handleMessage );
		document.addEventListener( 'keydown', handleKeyDown );

		// Cleanup
		return () => {
			window.removeEventListener( 'message', handleMessage );
			document.removeEventListener( 'keydown', handleKeyDown );
		};
	}, [ handleMessage, handleKeyDown ] );

	/**
	 * Modal content
	 */
	const modalContent = (
		<div
			className="automaticffl-dealer-layer visible"
			onClick={ handleOverlayClick }
			role="dialog"
			aria-modal="true"
			aria-label="FFL Dealer Selection"
		>
			<div className="dealers-container">
				<iframe
					id="automaticffl-map-iframe"
					src={ iframeUrl }
					title="FFL Dealer Selection"
					style={ {
						width: '100%',
						height: '100%',
						border: 'none',
					} }
				/>
			</div>
		</div>
	);

	// Render modal using portal to ensure it's above all other content
	return createPortal( modalContent, document.body );
};

export default DealerModal;
