/**
 * State Selector Component for Ammo Checkout
 *
 * Displays a state dropdown for ammo-only carts to determine
 * if FFL dealer selection is required based on shipping state.
 *
 * @since 1.0.15
 */

import { useState, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * State Selector Component
 *
 * @param {Object}   props                  Component props.
 * @param {Array}    props.restrictedStates Array of state codes where FFL is required.
 * @param {Function} props.onStateSelect    Callback when state is selected. Receives (stateCode, isRestricted).
 * @param {string}   props.selectedState    Currently selected state code.
 * @param {Object}   props.usStates         US states object { code: name } passed from PHP.
 *
 * @return {JSX.Element} Component output.
 */
const StateSelector = ( { restrictedStates = [], onStateSelect, selectedState = '', usStates = {} } ) => {
	const [ localState, setLocalState ] = useState( selectedState );

	// Convert PHP associative array { 'AL': 'Alabama' } to array of { value, label }
	const stateOptions = useMemo(
		() => Object.entries( usStates ).map( ( [ value, label ] ) => ( { value, label } ) ),
		[ usStates ]
	);

	const isRestricted = restrictedStates.includes( localState );

	const handleChange = useCallback(
		( event ) => {
			const state = event.target.value;
			setLocalState( state );

			if ( onStateSelect ) {
				onStateSelect( state, restrictedStates.includes( state ) );
			}
		},
		[ onStateSelect, restrictedStates ]
	);

	return (
		<div className="automaticffl-state-selector">
			<h3>{ __( 'Ammunition Shipping', 'automaticffl-for-wc' ) }</h3>

			{ /* Info Banner */ }
			<div className="wc-block-components-notices">
				<div className="wc-block-components-notice-banner is-info">
					<svg
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 24 24"
						width="24"
						height="24"
						aria-hidden="true"
						focusable="false"
					>
						<path d="M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.2-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.2 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 8h2v6h-2V8zm0 8h2v2h-2v-2z" />
					</svg>
					<div className="wc-block-components-notice-banner__content">
						{ __(
							'You have ammunition in your cart. Please select your shipping state to determine shipping options.',
							'automaticffl-for-wc'
						) }
					</div>
				</div>
			</div>

			{ /* State Dropdown */ }
			<div className="wc-block-components-text-input automaticffl-state-select-wrapper">
				<label htmlFor="automaticffl-state-selector">
					{ __( 'Shipping State', 'automaticffl-for-wc' ) }
					<abbr className="required" title="required">*</abbr>
				</label>
				<select
					id="automaticffl-state-selector"
					className="wc-block-components-select-input"
					value={ localState }
					onChange={ handleChange }
					required
				>
					<option value="">
						{ __( 'Select a state...', 'automaticffl-for-wc' ) }
					</option>
					{ stateOptions.map( ( { value, label } ) => (
						<option key={ value } value={ value }>
							{ label }
						</option>
					) ) }
				</select>
			</div>

			{ /* State Message */ }
			{ localState && isRestricted && (
				<div className="wc-block-components-notices automaticffl-state-message">
					<div className="wc-block-components-notice-banner is-error">
						<svg
							xmlns="http://www.w3.org/2000/svg"
							viewBox="0 0 24 24"
							width="24"
							height="24"
							aria-hidden="true"
							focusable="false"
						>
							<path d="M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.2-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.2 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 8h2v5h-2V8zm0 6h2v2h-2v-2z" />
						</svg>
						<div className="wc-block-components-notice-banner__content">
							{ __(
								'FFL dealer selection is required for ammunition shipping to this state.',
								'automaticffl-for-wc'
							) }
						</div>
					</div>
				</div>
			) }

			{ localState && ! isRestricted && (
				<div className="wc-block-components-notices automaticffl-state-message">
					<div className="wc-block-components-notice-banner is-success">
						<svg
							xmlns="http://www.w3.org/2000/svg"
							viewBox="0 0 24 24"
							width="24"
							height="24"
							aria-hidden="true"
							focusable="false"
						>
							<path d="M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z" />
						</svg>
						<div className="wc-block-components-notice-banner__content">
							{ __(
								'Standard shipping is available for ammunition to this state.',
								'automaticffl-for-wc'
							) }
						</div>
					</div>
				</div>
			) }
		</div>
	);
};

export default StateSelector;
