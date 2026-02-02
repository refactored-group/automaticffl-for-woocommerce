/**
 * FFL Dealer Selection Component
 *
 * Main component that handles FFL dealer selection in WooCommerce Blocks checkout.
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { CART_STORE_KEY } from '@woocommerce/block-data';
import { getSetting } from '@woocommerce/settings';
import DealerModal from './DealerModal';
import SelectedDealerCard from './SelectedDealerCard';
import StateSelector from './StateSelector';

/**
 * Selectors for the shipping address block in WooCommerce Blocks checkout
 */
const SHIPPING_ADDRESS_SELECTORS = [
	'.wp-block-woocommerce-checkout-shipping-address-block',
	'.wc-block-checkout__shipping-fields',
];

/**
 * Selectors for the "Use same address for billing" checkbox
 */
const USE_SAME_ADDRESS_SELECTORS = [
	'.wc-block-checkout__use-address-for-billing',
];

/**
 * Get the AutomaticFFL settings passed from PHP
 */
const getSettings = () => {
	// Try getSetting first, then fallback to global localized data
	let settings = getSetting( 'automaticffl_data', null );

	if ( ! settings && typeof window !== 'undefined' && window.automaticfflBlocksData ) {
		settings = window.automaticfflBlocksData;
	}

	settings = settings || {};

	return {
		// Legacy fields for backwards compatibility
		isFflCart: settings.isFflCart || false,
		hasFflProducts: settings.hasFflProducts || false,
		isMixedCart: settings.isMixedCart || false,
		iframeUrl: settings.iframeUrl || '',
		allowedOrigins: settings.allowedOrigins || [],
		userName: settings.userName || { first_name: 'FFL', last_name: 'Dealer' },
		isConfigured: settings.isConfigured || false,
		// New fields for restrictions API
		hasFirearms: settings.hasFirearms || false,
		hasAmmo: settings.hasAmmo || false,
		isAmmoOnly: settings.isAmmoOnly || false,
		isAmmoEnabled: settings.isAmmoEnabled || false,
		ammoRestrictedStates: settings.ammoRestrictedStates || [],
		isApiAvailable: settings.isApiAvailable !== false, // Default to true if not set
		usStates: settings.usStates || {},
	};
};

/**
 * API Unavailable Notice Component
 *
 * @param {Object}   props           Component props.
 * @param {Function} props.onDismiss Callback when notice is dismissed.
 *
 * @return {JSX.Element} Component output.
 */
const ApiUnavailableNotice = ( { onDismiss } ) => {
	return (
		<div className="automaticffl-unavailable-notice">
			<div className="wc-block-components-notices">
				<div className="wc-block-components-notice-banner is-info automaticffl-unavailable-message">
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
						<p><strong>{ __( 'Automatic FFL Unavailable', 'automaticffl-for-wc' ) }</strong></p>
						<p>{ __( 'Please contact our store after placing an order.', 'automaticffl-for-wc' ) }</p>
						<button
							type="button"
							className="wc-block-components-button wp-element-button"
							onClick={ onDismiss }
						>
							{ __( 'OK', 'automaticffl-for-wc' ) }
						</button>
					</div>
				</div>
			</div>
		</div>
	);
};

/**
 * FFL Dealer Selection Component
 *
 * @return {JSX.Element|null} Component output
 */
const FFLDealerSelection = () => {
	const settings = getSettings();

	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ selectedDealer, setSelectedDealer ] = useState( null );
	const [ hasValidationError, setHasValidationError ] = useState( false );
	// Ammo-specific state
	const [ selectedState, setSelectedState ] = useState( '' );
	const [ requiresFfl, setRequiresFfl ] = useState( false );
	// API unavailable notice state
	const [ noticeDismissed, setNoticeDismissed ] = useState( false );

	// Get shipping address dispatch
	const { setShippingAddress } = useDispatch( CART_STORE_KEY );

	/**
	 * Set extension data for the Store API checkout request.
	 *
	 * NOTE: We use wp.data.dispatch('wc/store/checkout') directly because the
	 * public `checkoutExtensionData` prop (with its `setExtensionData` method)
	 * is only passed to payment method components, not to SlotFill components
	 * like ExperimentalOrderShippingPackages where this component renders.
	 *
	 * The __internalSetExtensionData dispatch action is an internal WooCommerce
	 * API (denoted by the __ prefix). If WooCommerce changes this API, the
	 * try/catch will prevent runtime errors and the console.error will aid
	 * debugging. Monitor WooCommerce changelogs on updates.
	 *
	 * @param {string} namespace The extension namespace
	 * @param {Object} data The data to set
	 */
	const setExtensionData = useCallback(
		( namespace, data ) => {
			try {
				const checkoutStore = wp.data.dispatch( 'wc/store/checkout' );

				// Try the public method first (WooCommerce 8.9+)
				if ( checkoutStore && typeof checkoutStore.setExtensionData === 'function' ) {
					checkoutStore.setExtensionData( namespace, data );
				// Fall back to internal method for older versions
				} else if ( checkoutStore && typeof checkoutStore.__internalSetExtensionData === 'function' ) {
					checkoutStore.__internalSetExtensionData( namespace, data );
				}
			} catch ( e ) {
				// eslint-disable-next-line no-console
				console.error( 'AutomaticFFL: Could not set extension data', e );
			}
		},
		[]
	);

	// Track if we've hidden the shipping form
	const shippingFormHiddenRef = useRef( false );

	/**
	 * Handle dealer selection from the iframe
	 */
	const handleDealerSelect = useCallback(
		( dealer ) => {
			setSelectedDealer( dealer );
			setIsModalOpen( false );

			// Clear validation error if dealer is selected
			if ( dealer && dealer.fflID ) {
				setHasValidationError( false );
			}

			// Set the FFL license in extension data for the Store API
			// The data object format is { key: value } which gets merged into extensions.automaticffl
			setExtensionData( 'automaticffl', { fflLicense: dealer.fflID || '' } );

			// Update shipping address with dealer information
			setShippingAddress( {
				first_name: settings.userName.first_name,
				last_name: settings.userName.last_name,
				company: dealer.company || '',
				address_1: dealer.address1 || '',
				address_2: dealer.address2 || '',
				city: dealer.city || '',
				state: dealer.stateOrProvinceCode || '',
				postcode: dealer.postalCode || '',
				country: dealer.countryCode || 'US',
				phone: dealer.phone || '',
			} );
		},
		[ setExtensionData, setShippingAddress, settings.userName ]
	);

	/**
	 * Hide the shipping address form and handle billing address when FFL products are in cart
	 * This runs on mount and when settings change
	 */
	useEffect( () => {
		const shouldHideForm = settings.hasFflProducts && ! settings.isMixedCart && settings.isConfigured;

		// Find and hide/show the shipping address form
		const hideShippingForm = () => {
			for ( const selector of SHIPPING_ADDRESS_SELECTORS ) {
				const element = document.querySelector( selector );
				if ( element ) {
					if ( shouldHideForm ) {
						element.style.display = 'none';
						element.setAttribute( 'data-ffl-hidden', 'true' );
						shippingFormHiddenRef.current = true;
					} else if ( element.getAttribute( 'data-ffl-hidden' ) === 'true' ) {
						element.style.display = '';
						element.removeAttribute( 'data-ffl-hidden' );
						shippingFormHiddenRef.current = false;
					}
				}
			}
		};

		/**
		 * Handle the "Use same address for billing" checkbox
		 * We need to uncheck it and hide it so the billing address form is always visible
		 */
		const handleBillingCheckbox = () => {
			for ( const selector of USE_SAME_ADDRESS_SELECTORS ) {
				const container = document.querySelector( selector );
				if ( container ) {
					if ( shouldHideForm ) {
						// Find the checkbox input within the container
						const checkbox = container.querySelector( 'input[type="checkbox"]' ) || container;

						// If checkbox is checked, uncheck it to show the billing form
						if ( checkbox.checked ) {
							// Trigger a click to uncheck and let React handle state
							checkbox.click();
						}

						// Hide the checkbox container so users can't re-enable it
						container.style.display = 'none';
						container.setAttribute( 'data-ffl-hidden', 'true' );
					} else if ( container.getAttribute( 'data-ffl-hidden' ) === 'true' ) {
						container.style.display = '';
						container.removeAttribute( 'data-ffl-hidden' );
					}
				}
			}
		};

		/**
		 * Apply form hiding logic
		 */
		const applyFormHiding = () => {
			hideShippingForm();
			handleBillingCheckbox();
		};

		// Run immediately
		applyFormHiding();

		// Use MutationObserver to detect when checkout elements are added to the DOM
		const observer = new MutationObserver( ( mutations ) => {
			// Check if any relevant elements were added
			const hasRelevantChanges = mutations.some( ( mutation ) => {
				if ( mutation.type === 'childList' && mutation.addedNodes.length > 0 ) {
					return Array.from( mutation.addedNodes ).some( ( node ) => {
						if ( node.nodeType === Node.ELEMENT_NODE ) {
							// Check if the added node or its children match our selectors
							const allSelectors = [ ...SHIPPING_ADDRESS_SELECTORS, ...USE_SAME_ADDRESS_SELECTORS ];
							return allSelectors.some( ( selector ) =>
								node.matches?.( selector ) || node.querySelector?.( selector )
							);
						}
						return false;
					} );
				}
				return false;
			} );

			if ( hasRelevantChanges ) {
				applyFormHiding();
			}
		} );

		// Start observing the checkout container or body
		const checkoutContainer = document.querySelector( '.wc-block-checkout' ) || document.body;
		observer.observe( checkoutContainer, {
			childList: true,
			subtree: true,
		} );

		// Add a body class for CSS targeting
		if ( shouldHideForm ) {
			document.body.classList.add( 'automaticffl-checkout-active' );
		} else {
			document.body.classList.remove( 'automaticffl-checkout-active' );
		}

		// Cleanup: restore the form if component unmounts
		return () => {
			observer.disconnect();
			if ( shippingFormHiddenRef.current ) {
				for ( const selector of SHIPPING_ADDRESS_SELECTORS ) {
					const element = document.querySelector( selector );
					if ( element && element.getAttribute( 'data-ffl-hidden' ) === 'true' ) {
						element.style.display = '';
						element.removeAttribute( 'data-ffl-hidden' );
					}
				}
				for ( const selector of USE_SAME_ADDRESS_SELECTORS ) {
					const element = document.querySelector( selector );
					if ( element && element.getAttribute( 'data-ffl-hidden' ) === 'true' ) {
						element.style.display = '';
						element.removeAttribute( 'data-ffl-hidden' );
					}
				}
				document.body.classList.remove( 'automaticffl-checkout-active' );
			}
		};
	}, [ settings.hasFflProducts, settings.isMixedCart, settings.isConfigured ] );

	/**
	 * Show validation error after failed checkout attempt
	 */
	useEffect( () => {
		// Guard against wp.hooks not being available
		if ( typeof wp === 'undefined' || ! wp.hooks ) {
			return;
		}

		const handleCheckoutError = () => {
			if ( ! selectedDealer ) {
				setHasValidationError( true );
			}
		};

		wp.hooks.addAction(
			'woocommerce_blocks_checkout_after_processing_with_error',
			'automaticffl',
			handleCheckoutError
		);

		return () => {
			if ( typeof wp !== 'undefined' && wp.hooks ) {
				wp.hooks.removeAction(
					'woocommerce_blocks_checkout_after_processing_with_error',
					'automaticffl'
				);
			}
		};
	}, [ selectedDealer ] );

	/**
	 * Handle state selection for ammo-only checkout
	 */
	const handleStateSelect = useCallback(
		( state, isRestricted ) => {
			setSelectedState( state );
			setRequiresFfl( isRestricted );

			// Update shipping state
			setShippingAddress( { state } );

			// Clear dealer if state is not restricted
			if ( ! isRestricted ) {
				setSelectedDealer( null );
				setExtensionData( 'automaticffl', { fflLicense: '' } );
			}
		},
		[ setShippingAddress, setExtensionData ]
	);

	// Show API unavailable notice if API is down (and not dismissed)
	if ( ! settings.isApiAvailable && ! noticeDismissed ) {
		return (
			<ApiUnavailableNotice onDismiss={ () => setNoticeDismissed( true ) } />
		);
	}

	// Don't render if API unavailable and notice dismissed - allow normal checkout
	if ( ! settings.isApiAvailable && noticeDismissed ) {
		return null;
	}

	// Don't render if no FFL products (firearms or ammo)
	if ( ! settings.hasFirearms && ! settings.hasAmmo && ! settings.hasFflProducts ) {
		return null;
	}

	// Show error for mixed cart
	if ( settings.isMixedCart ) {
		return (
			<div className="wc-block-components-notices">
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
							'Your cart contains both FFL-required items and non-FFL items. Please purchase them separately.',
							'automaticffl-for-wc'
						) }
					</div>
				</div>
			</div>
		);
	}

	// Ammo-only checkout with ammo features enabled
	if ( settings.isAmmoOnly && settings.isAmmoEnabled ) {
		// No state selected yet - show state selector
		if ( ! selectedState ) {
			return (
				<StateSelector
					restrictedStates={ settings.ammoRestrictedStates }
					onStateSelect={ handleStateSelect }
					selectedState={ selectedState }
					usStates={ settings.usStates }
				/>
			);
		}

		// State selected but not restricted - standard checkout
		if ( ! requiresFfl ) {
			return (
				<StateSelector
					restrictedStates={ settings.ammoRestrictedStates }
					onStateSelect={ handleStateSelect }
					selectedState={ selectedState }
					usStates={ settings.usStates }
				/>
			);
		}

		// State is restricted - show FFL selection after state selector
		if ( ! settings.isConfigured ) {
			return (
				<div className="automaticffl-dealer-selection">
					<StateSelector
						restrictedStates={ settings.ammoRestrictedStates }
						onStateSelect={ handleStateSelect }
						selectedState={ selectedState }
					/>
					<div className="wc-block-components-notices">
						<div className="wc-block-components-notice-banner is-error">
							<div className="wc-block-components-notice-banner__content">
								{ __(
									'FFL dealer selection is not configured. Please contact the site administrator.',
									'automaticffl-for-wc'
								) }
							</div>
						</div>
					</div>
				</div>
			);
		}

		// Show state selector + FFL selection
		return (
			<div className="automaticffl-dealer-selection">
				<StateSelector
					restrictedStates={ settings.ammoRestrictedStates }
					onStateSelect={ handleStateSelect }
					selectedState={ selectedState }
					usStates={ settings.usStates }
				/>

				{ /* Validation Error */ }
				{ hasValidationError && ! selectedDealer && (
					<div className="wc-block-components-notices">
						<div className="wc-block-components-notice-banner is-error">
							<div className="wc-block-components-notice-banner__content">
								{ __(
									'Please select an FFL dealer before placing your order.',
									'automaticffl-for-wc'
								) }
							</div>
						</div>
					</div>
				) }

				{ /* Selected Dealer Card */ }
				{ selectedDealer && (
					<SelectedDealerCard
						dealer={ selectedDealer }
						userName={ settings.userName }
					/>
				) }

				{ /* Find/Change Dealer Button */ }
				<button
					type="button"
					className="wc-block-components-button wp-element-button ffl-search-button"
					onClick={ () => setIsModalOpen( true ) }
				>
					<svg
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 512 512"
						width="16"
						height="16"
						fill="currentColor"
						aria-hidden="true"
						style={ { verticalAlign: 'middle', marginRight: '8px' } }
					>
						<path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z" />
					</svg>
					{ selectedDealer
						? __( 'Change Dealer', 'automaticffl-for-wc' )
						: __( 'Find a Dealer', 'automaticffl-for-wc' ) }
				</button>

				{ /* Dealer Selection Modal */ }
				{ isModalOpen && (
					<DealerModal
						iframeUrl={ settings.iframeUrl }
						allowedOrigins={ settings.allowedOrigins }
						onSelect={ handleDealerSelect }
						onClose={ () => setIsModalOpen( false ) }
					/>
				) }
			</div>
		);
	}

	// Firearms checkout (or legacy FFL cart) - always require FFL
	// Show configuration error
	if ( ! settings.isConfigured ) {
		return (
			<div className="wc-block-components-notices">
				<div className="wc-block-components-notice-banner is-error">
					<div className="wc-block-components-notice-banner__content">
						{ __(
							'FFL dealer selection is not configured. Please contact the site administrator.',
							'automaticffl-for-wc'
						) }
					</div>
				</div>
			</div>
		);
	}

	return (
		<div className="automaticffl-dealer-selection">
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
							'You have a firearm in your cart and must choose a Licensed Firearm Dealer (FFL) for the Shipping Address.',
							'automaticffl-for-wc'
						) }
					</div>
				</div>
			</div>

			{ /* Validation Error */ }
			{ hasValidationError && ! selectedDealer && (
				<div className="wc-block-components-notices">
					<div className="wc-block-components-notice-banner is-error">
						<div className="wc-block-components-notice-banner__content">
							{ __(
								'Please select an FFL dealer before placing your order.',
								'automaticffl-for-wc'
							) }
						</div>
					</div>
				</div>
			) }

			{ /* Selected Dealer Card */ }
			{ selectedDealer && (
				<SelectedDealerCard
					dealer={ selectedDealer }
					userName={ settings.userName }
				/>
			) }

			{ /* Find/Change Dealer Button */ }
			<button
				type="button"
				className="wc-block-components-button wp-element-button ffl-search-button"
				onClick={ () => setIsModalOpen( true ) }
			>
				<svg
					xmlns="http://www.w3.org/2000/svg"
					viewBox="0 0 512 512"
					width="16"
					height="16"
					fill="currentColor"
					aria-hidden="true"
					style={ { verticalAlign: 'middle', marginRight: '8px' } }
				>
					<path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z" />
				</svg>
				{ selectedDealer
					? __( 'Change Dealer', 'automaticffl-for-wc' )
					: __( 'Find a Dealer', 'automaticffl-for-wc' ) }
			</button>

			{ /* Dealer Selection Modal */ }
			{ isModalOpen && (
				<DealerModal
					iframeUrl={ settings.iframeUrl }
					allowedOrigins={ settings.allowedOrigins }
					onSelect={ handleDealerSelect }
					onClose={ () => setIsModalOpen( false ) }
				/>
			) }
		</div>
	);
};

export default FFLDealerSelection;
