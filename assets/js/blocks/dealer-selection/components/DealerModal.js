/**
 * Dealer Modal Component
 *
 * Modal overlay containing the FFL dealer selection iframe.
 */

import { useEffect, useCallback } from '@wordpress/element';
import { createPortal } from 'react-dom';

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
	 * Set up event listeners and body scroll lock
	 */
	useEffect( () => {
		// Add event listeners
		window.addEventListener( 'message', handleMessage );
		document.addEventListener( 'keydown', handleKeyDown );

		// Prevent body scroll
		const originalOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';

		// Cleanup
		return () => {
			window.removeEventListener( 'message', handleMessage );
			document.removeEventListener( 'keydown', handleKeyDown );
			document.body.style.overflow = originalOverflow;
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
