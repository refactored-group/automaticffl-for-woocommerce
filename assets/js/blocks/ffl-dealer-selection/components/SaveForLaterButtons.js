/**
 * Save for Later Buttons Component
 *
 * Renders save for later buttons for mixed cart scenarios in the
 * WooCommerce Blocks checkout.
 *
 * @since 1.0.15
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
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
const SaveForLaterButtons = ( { fflCount, regularCount, onSaved } ) => {
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	/**
	 * Handle save button click
	 *
	 * @param {string} itemType Type of items to save: 'ffl' or 'regular'
	 */
	const handleSave = async ( itemType ) => {
		setIsSaving( true );
		setError( null );

		try {
			const response = await apiFetch( {
				path: '/automaticffl/v1/save-for-later',
				method: 'POST',
				data: { item_type: itemType },
			} );

			if ( response.success ) {
				// Reload the page to refresh cart data
				if ( typeof onSaved === 'function' ) {
					onSaved( response );
				}
				window.location.reload();
			} else {
				setError( response.message || __( 'Failed to save items.', 'automaticffl-for-wc' ) );
				setIsSaving( false );
			}
		} catch ( err ) {
			setError( err.message || __( 'An error occurred. Please try again.', 'automaticffl-for-wc' ) );
			setIsSaving( false );
		}
	};

	/**
	 * Get button text for FFL items
	 *
	 * @return {string} Button text.
	 */
	const getFflButtonText = () => {
		if ( isSaving ) {
			return __( 'Saving...', 'automaticffl-for-wc' );
		}
		return fflCount === 1
			? __( 'FFL item (1)', 'automaticffl-for-wc' )
			: `${ __( 'FFL items', 'automaticffl-for-wc' ) } (${ fflCount })`;
	};

	/**
	 * Get button text for regular items
	 *
	 * @return {string} Button text.
	 */
	const getRegularButtonText = () => {
		if ( isSaving ) {
			return __( 'Saving...', 'automaticffl-for-wc' );
		}
		return regularCount === 1
			? __( 'Regular item (1)', 'automaticffl-for-wc' )
			: `${ __( 'Regular items', 'automaticffl-for-wc' ) } (${ regularCount })`;
	};

	return (
		<div className="automaticffl-blocks-save-for-later">
			<p className="automaticffl-blocks-save-prompt">
				{ __( 'Choose which items to save for your next order:', 'automaticffl-for-wc' ) }
			</p>

			{ error && (
				<div className="wc-block-components-notice-banner is-error" style={ { marginBottom: '12px' } }>
					<div className="wc-block-components-notice-banner__content">
						{ error }
					</div>
				</div>
			) }

			<div className="automaticffl-blocks-save-buttons">
				<button
					type="button"
					className="wc-block-components-button wp-element-button automaticffl-blocks-save-btn automaticffl-blocks-save-btn--primary"
					onClick={ () => handleSave( 'ffl' ) }
					disabled={ isSaving }
				>
					{ getFflButtonText() }
				</button>
				<button
					type="button"
					className="wc-block-components-button wp-element-button automaticffl-blocks-save-btn automaticffl-blocks-save-btn--secondary"
					onClick={ () => handleSave( 'regular' ) }
					disabled={ isSaving }
				>
					{ getRegularButtonText() }
				</button>
			</div>

			<p className="automaticffl-blocks-save-help">
				{ __( 'Saved items will be restored to your cart after checkout.', 'automaticffl-for-wc' ) }
			</p>
		</div>
	);
};

export default SaveForLaterButtons;
