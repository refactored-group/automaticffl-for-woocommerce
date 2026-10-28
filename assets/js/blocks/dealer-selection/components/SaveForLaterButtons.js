/**
 * Save for Later Buttons Component
 *
 * Renders checkout-group choices for mixed carts; the opposite group is saved.
 *
 * @since 1.0.14
 */

import { useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Save for Later Buttons Component
 *
 * @param {Object} props              Component props.
 * @param {number} props.fflCount     Number of FFL items in cart.
 * @param {number} props.regularCount Number of regular items in cart.
 * @param {Function} props.onSaved    Callback after items are saved.
 *
 * @return {JSX.Element} Component output.
 */
const SaveForLaterButtons = ( { fflCount, regularCount, checkoutUrl, isAmmo = false, disabled = false, onSaved } ) => {
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const saving = useRef( false );

	/**
	 * Handle save button click
	 *
	 * @param {string} checkoutType Group to check out: 'ffl' or 'regular'
	 */
	const handleCheckout = async ( checkoutType ) => {
		if ( saving.current || disabled ) {
			return;
		}
		saving.current = true;
		setIsSaving( true );
		setError( null );

		try {
			const response = await apiFetch( {
				path: '/automaticffl/v1/save-for-later',
				method: 'POST',
				// The API names the group being saved, opposite the checkout choice.
				data: { item_type: checkoutType === 'ffl' ? 'regular' : 'ffl' },
			} );

			if ( response.success ) {
				if ( typeof onSaved === 'function' ) {
					onSaved( response );
				}
				window.location.assign( checkoutUrl );
			} else {
				setError( response.message || __( 'Failed to save items.', 'automaticffl-for-wc' ) );
				setIsSaving( false );
				saving.current = false;
			}
		} catch ( err ) {
			setError( err.message || __( 'An error occurred. Please try again.', 'automaticffl-for-wc' ) );
			setIsSaving( false );
			saving.current = false;
		}
	};

	// Translators: %d is the total quantity in the selected checkout group.
	const fflText = isAmmo
		? sprintf( _n( 'Check out ammo item (%d)', 'Check out ammo items (%d)', fflCount, 'automaticffl-for-wc' ), fflCount )
		: sprintf( _n( 'Check out FFL item (%d)', 'Check out FFL items (%d)', fflCount, 'automaticffl-for-wc' ), fflCount );
	const regularText = sprintf( _n( 'Check out regular item (%d)', 'Check out regular items (%d)', regularCount, 'automaticffl-for-wc' ), regularCount );

	return (
		<div className="automaticffl-mixed-actions" aria-busy={ isSaving }>
			{ error && <div className="automaticffl-cart-notice-error" role="alert">{ error }</div> }
			<div className="automaticffl-blocks-save-buttons">
				<button type="button" className="wc-block-components-button wp-element-button automaticffl-blocks-save-btn automaticffl-blocks-save-btn--primary" onClick={ () => handleCheckout( 'ffl' ) } disabled={ isSaving || disabled }>
					{ isSaving ? __( 'Continuing...', 'automaticffl-for-wc' ) : fflText }
				</button>
				<button type="button" className="wc-block-components-button wp-element-button automaticffl-blocks-save-btn automaticffl-blocks-save-btn--secondary" onClick={ () => handleCheckout( 'regular' ) } disabled={ isSaving || disabled }>
					{ isSaving ? __( 'Continuing...', 'automaticffl-for-wc' ) : regularText }
				</button>
			</div>
		</div>
	);
};

export default SaveForLaterButtons;
