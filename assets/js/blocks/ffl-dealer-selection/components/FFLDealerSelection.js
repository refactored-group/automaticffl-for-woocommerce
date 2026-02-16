/**
 * FFL Dealer Selection Component
 *
 * Main component that handles FFL dealer selection in WooCommerce Blocks checkout.
 */

import { useState, useEffect, useCallback, useRef, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { CART_STORE_KEY } from '@woocommerce/block-data';
import { getSetting } from '@woocommerce/settings';
import DealerModal from './DealerModal';
import SelectedDealerCard from './SelectedDealerCard';
import SaveForLaterButtons from './SaveForLaterButtons';

/**
 * Module-level persistence for ammo FFL lock-in state.
 * Survives React remounts caused by WooCommerce re-rendering the checkout.
 */
let persistedAmmoFflLocked = false;
let persistedDealer = null;

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
		userName: settings.userName || { first_name: '', last_name: '' },
		isConfigured: settings.isConfigured || false,
		// New fields for restrictions API
		hasFirearms: settings.hasFirearms || false,
		hasAmmo: settings.hasAmmo || false,
		isAmmoOnly: settings.isAmmoOnly || false,
		isAmmoEnabled: settings.isAmmoEnabled || false,
		ammoRestrictedStates: settings.ammoRestrictedStates || [],
		isApiAvailable: settings.isApiAvailable !== false, // Default to true if not set
		usStates: settings.usStates || {},
		// Ammo + regular mixed cart fields
		isAmmoRegularMixed: settings.isAmmoRegularMixed || false,
		isFirearmsRegularMixed: settings.isFirearmsRegularMixed || false,
		selectedAmmoState: settings.selectedAmmoState || '',
		cartUrl: settings.cartUrl || '',
		// Save for later fields
		fflItemCount: settings.fflItemCount || 0,
		regularItemCount: settings.regularItemCount || 0,
		hasSavedItems: settings.hasSavedItems || false,
		savedItemsCount: settings.savedItemsCount || 0,
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
	const [ selectedDealer, setSelectedDealer ] = useState( persistedDealer );
	const [ hasValidationError, setHasValidationError ] = useState( false );
	// API unavailable notice state
	const [ noticeDismissed, setNoticeDismissed ] = useState( false );
	// Ammo FFL lock-in: once a restricted state triggers FFL, lock into firearms-style flow
	const [ ammoFflLocked, setAmmoFflLocked ] = useState( persistedAmmoFflLocked );

	// Get shipping address dispatch
	const { setShippingAddress } = useDispatch( CART_STORE_KEY );

	// Get shipping state and name from the cart store
	const shippingState = useSelect( ( select ) => {
		const store = select( CART_STORE_KEY );
		const shippingAddress = store.getCustomerData?.()?.shippingAddress || {};
		return shippingAddress.state || '';
	}, [] );

	const shippingFirstName = useSelect( ( select ) => {
		return select( CART_STORE_KEY ).getCustomerData?.()?.shippingAddress?.first_name || '';
	}, [] );

	const shippingLastName = useSelect( ( select ) => {
		return select( CART_STORE_KEY ).getCustomerData?.()?.shippingAddress?.last_name || '';
	}, [] );

	const shippingName = useMemo(
		() => ( { first_name: shippingFirstName, last_name: shippingLastName } ),
		[ shippingFirstName, shippingLastName ]
	);

	// Determine if FFL is required based on shipping state (for ammo-only carts)
	const requiresFfl = settings.isAmmoOnly &&
		settings.isAmmoEnabled &&
		shippingState !== '' &&
		settings.ammoRestrictedStates.includes( shippingState );

	// Ref to track current FFL status for the checkout store subscriber (avoids re-subscribing)
	const fflStatusRef = useRef( { required: false, selected: false, isAmmoOnly: false } );

	// Sync lock-in state and dealer to module-level persistence (survives remounts)
	useEffect( () => { persistedAmmoFflLocked = ammoFflLocked; }, [ ammoFflLocked ] );
	useEffect( () => { persistedDealer = selectedDealer; }, [ selectedDealer ] );

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

			// Lock in ammo FFL mode on dealer selection.
			// This hides the shipping form and prevents the feedback loop where
			// the dealer's address state triggers re-evaluation of FFL requirements.
			if ( settings.isAmmoOnly && settings.isAmmoEnabled && ! ammoFflLocked ) {
				setAmmoFflLocked( true );
			}

			// Set the FFL data in extension data for the Store API
			// The data object format is { key: value } which gets merged into extensions.automaticffl
			setExtensionData( 'automaticffl', {
				fflLicense: dealer.fflID || '',
				fflExpirationDate: dealer.expirationDate || '',
				fflUuid: dealer.uuid || '',
			} );

			// Update shipping address with dealer information.
			// Read current shipping name from store (customer's input) instead of
			// settings.userName so guest users keep their own entered name.
			const customerData = wp.data.select( CART_STORE_KEY ).getCustomerData?.() || {};
			const currentShipping = customerData.shippingAddress || {};

			setShippingAddress( {
				first_name: currentShipping.first_name || '',
				last_name: currentShipping.last_name || '',
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
		[ setExtensionData, setShippingAddress, settings.isAmmoOnly, settings.isAmmoEnabled, ammoFflLocked ]
	);

	/**
	 * Hide the shipping address form and handle billing address when FFL products are in cart
	 * This runs on mount and when settings change
	 *
	 * Logic:
	 * - Firearms: Always hide shipping form (FFL required, dealer address used)
	 * - Ammo-only: Never hide shipping form (user enters their own address)
	 */
	useEffect( () => {
		// Hide shipping form for firearms, or when ammo-only is locked into FFL flow
		const shouldHideForm = ( settings.hasFirearms && ! settings.isMixedCart && settings.isConfigured )
			|| ammoFflLocked;

		// Shipping address field visibility is handled via CSS body class
		// (body.automaticffl-checkout-active hides address fields but keeps name fields visible).
		// No need to set element.style.display directly on the shipping block.
		const hideShippingForm = () => {
			shippingFormHiddenRef.current = shouldHideForm;
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
							return USE_SAME_ADDRESS_SELECTORS.some( ( selector ) =>
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
	}, [ settings.hasFirearms, settings.isMixedCart, settings.isConfigured, ammoFflLocked ] );

	/**
	 * Checkout validation for FFL requirements using WooCommerce validation store.
	 * Sets validation errors that block checkout when:
	 * - Firearms in cart and no dealer selected
	 * - Ammo-only cart with restricted state and no dealer selected
	 * - Ammo + regular cart with restricted shipping state
	 */
	useEffect( () => {
		// Access the validation store via wp.data
		const validationStore = wp?.data?.dispatch?.( 'wc/store/validation' );
		if ( ! validationStore ) {
			return;
		}

		const { setValidationErrors, clearValidationError } = validationStore;
		const AMMO_REGULAR_ERROR_ID = 'automaticffl-ammo-regular-blocked';
		const FFL_REQUIRED_ERROR_ID = 'automaticffl-ffl-required';

		/**
		 * Determine if FFL dealer is required for this checkout
		 */
		const isFflRequired = () => {
			// Firearms always require FFL
			if ( settings.hasFirearms && ! settings.isMixedCart ) {
				return true;
			}
			// Ammo-only locked into FFL flow, or with restricted state
			if ( settings.isAmmoOnly && settings.isAmmoEnabled && ( ammoFflLocked || requiresFfl ) ) {
				return true;
			}
			return false;
		};

		/**
		 * Check if ammo + regular mixed cart is blocked
		 */
		const isAmmoRegularBlocked = () => {
			if ( settings.isAmmoRegularMixed && settings.isAmmoEnabled && shippingState ) {
				return settings.ammoRestrictedStates.includes( shippingState );
			}
			return false;
		};

		// Set or clear ammo+regular validation error
		if ( isAmmoRegularBlocked() ) {
			setValidationErrors( {
				[ AMMO_REGULAR_ERROR_ID ]: {
					message: __( 'Please modify your cart to continue.', 'automaticffl-for-wc' ),
					hidden: true, // Hidden because we show inline message
				},
			} );
		} else {
			clearValidationError( AMMO_REGULAR_ERROR_ID );
		}

		// Set or clear FFL required validation error
		if ( isFflRequired() && ! selectedDealer ) {
			setValidationErrors( {
				[ FFL_REQUIRED_ERROR_ID ]: {
					message: settings.isAmmoOnly
						? ( settings.i18n?.selectDealerAmmoRequired || __( 'Please select an FFL dealer. Your state requires ammunition to be shipped to a licensed dealer.', 'automaticffl-for-wc' ) )
						: ( settings.i18n?.selectDealerBeforeOrder || __( 'Please select an FFL dealer before placing your order.', 'automaticffl-for-wc' ) ),
					hidden: true, // Will be shown when checkout is attempted
				},
			} );
			setHasValidationError( true );
		} else {
			clearValidationError( FFL_REQUIRED_ERROR_ID );
			setHasValidationError( false );
		}

		return () => {
			// Cleanup: clear validation errors when component unmounts
			if ( validationStore ) {
				clearValidationError( AMMO_REGULAR_ERROR_ID );
				clearValidationError( FFL_REQUIRED_ERROR_ID );
			}
		};
	}, [ selectedDealer, settings.hasFirearms, settings.isMixedCart, settings.isAmmoOnly, settings.isAmmoEnabled, requiresFfl, ammoFflLocked, settings.isAmmoRegularMixed, settings.ammoRestrictedStates, settings.usStates, shippingState ] );

	/**
	 * Keep the FFL status ref in sync for the checkout store subscriber.
	 */
	useEffect( () => {
		const isFflRequired =
			( settings.hasFirearms && ! settings.isMixedCart ) ||
			( settings.isAmmoOnly && settings.isAmmoEnabled && ( ammoFflLocked || requiresFfl ) );
		fflStatusRef.current = {
			required: isFflRequired,
			selected: !! selectedDealer,
			isAmmoOnly: settings.isAmmoOnly,
		};
	}, [ selectedDealer, settings.hasFirearms, settings.isMixedCart, settings.isAmmoOnly, settings.isAmmoEnabled, ammoFflLocked, requiresFfl ] );

	/**
	 * Show red error notice at the top of checkout when a customer tries to
	 * place an order without selecting an FFL dealer.
	 *
	 * Subscribes to the wc/store/checkout data store. When checkout transitions
	 * from before_processing back to idle (validation blocked it), dispatches
	 * an error notice via core/notices into the wc/checkout context.
	 */
	useEffect( () => {
		const checkoutStore = wp?.data?.select?.( 'wc/store/checkout' );
		if ( ! checkoutStore ) {
			return;
		}

		const FFL_NOTICE_ID = 'automaticffl-ffl-required';
		let wasBeforeProcessing = false;

		const unsubscribe = wp.data.subscribe( () => {
			const isBefore = !!(
				checkoutStore.isBeforeProcessing?.() ||
				checkoutStore.getCheckoutStatus?.() === 'before_processing'
			);

			if ( isBefore ) {
				wasBeforeProcessing = true;
				return;
			}

			// Detect transition from before_processing → idle (validation failure)
			if ( wasBeforeProcessing ) {
				wasBeforeProcessing = false;
				const { required, selected, isAmmoOnly } = fflStatusRef.current;

				if ( required && ! selected ) {
					const noticeStore = wp?.data?.dispatch?.( 'core/notices' );
					if ( noticeStore?.createNotice ) {
						const message = isAmmoOnly
							? ( settings.i18n?.selectDealerAmmoRequired || __( 'Please select an FFL dealer. Your state requires ammunition to be shipped to a licensed dealer.', 'automaticffl-for-wc' ) )
							: ( settings.i18n?.selectDealerBeforeOrder || __( 'Please select an FFL dealer before placing your order.', 'automaticffl-for-wc' ) );

						noticeStore.createNotice( 'error', message, {
							context: 'wc/checkout',
							id: FFL_NOTICE_ID,
						} );
					}
				}
			}
		} );

		return () => {
			unsubscribe();
			// Cleanup: remove notice on unmount
			const noticeStore = wp?.data?.dispatch?.( 'core/notices' );
			noticeStore?.removeNotice?.( FFL_NOTICE_ID, 'wc/checkout' );
		};
	}, [] );

	/**
	 * Clear the FFL error notice when a dealer is selected.
	 */
	useEffect( () => {
		if ( selectedDealer ) {
			const noticeStore = wp?.data?.dispatch?.( 'core/notices' );
			noticeStore?.removeNotice?.( 'automaticffl-ffl-required', 'wc/checkout' );
		}
	}, [ selectedDealer ] );

	/**
	 * Clear dealer selection when shipping state changes to unrestricted.
	 * Skip when ammoFflLocked — the dealer address becomes the shipping address
	 * and state changes are driven by the dealer, not the user.
	 */
	useEffect( () => {
		if ( ammoFflLocked ) {
			return;
		}
		if ( settings.isAmmoOnly && settings.isAmmoEnabled && shippingState !== '' ) {
			const isRestricted = settings.ammoRestrictedStates.includes( shippingState );
			if ( ! isRestricted && selectedDealer ) {
				// Clear dealer when switching to unrestricted state
				setSelectedDealer( null );
				setExtensionData( 'automaticffl', {
					fflLicense: '',
					fflExpirationDate: '',
					fflUuid: '',
				} );
			}
		}
	}, [ shippingState, settings.isAmmoOnly, settings.isAmmoEnabled, settings.ammoRestrictedStates, selectedDealer, setExtensionData, ammoFflLocked ] );

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

	// Firearms + regular = always blocked
	if ( settings.isFirearmsRegularMixed ) {
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
						{ __( 'Firearms must be shipped to an FFL dealer and cannot be combined with regular products.', 'automaticffl-for-wc' ) }
						<SaveForLaterButtons
							fflCount={ settings.fflItemCount }
							regularCount={ settings.regularItemCount }
						/>
					</div>
				</div>
			</div>
		);
	}

	// Ammo + regular mixed cart - only show notice for restricted states
	if ( settings.isAmmoRegularMixed && settings.isAmmoEnabled ) {
		const isRestricted = shippingState && settings.ammoRestrictedStates.includes( shippingState );
		const stateName = settings.usStates[ shippingState ] || shippingState;

		// Restricted state - show error with save for later buttons
		if ( isRestricted ) {
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
							{ __( 'Ammunition and regular products in your cart require separate orders for shipping to', 'automaticffl-for-wc' ) } { stateName }.
							<SaveForLaterButtons
								fflCount={ settings.fflItemCount }
								regularCount={ settings.regularItemCount }
							/>
						</div>
					</div>
				</div>
			);
		}

		// Unrestricted or no state yet - no notice needed
		return null;
	}

	// Fallback for legacy isMixedCart detection (no firearms/ammo API data)
	if ( settings.isMixedCart && ! settings.isFirearmsRegularMixed && ! settings.isAmmoRegularMixed ) {
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
						{ __( 'FFL products and regular products cannot be purchased together.', 'automaticffl-for-wc' ) }
						<SaveForLaterButtons
							fflCount={ settings.fflItemCount }
							regularCount={ settings.regularItemCount }
						/>
					</div>
				</div>
			</div>
		);
	}

	// Ammo-only checkout with ammo features enabled
	// When ammoFflLocked, skip these render paths and fall through to firearms-style FFL UI
	if ( settings.isAmmoOnly && settings.isAmmoEnabled && ! ammoFflLocked ) {
		// No state selected yet - show info banner prompting user to select state
		if ( ! shippingState ) {
			return (
				<div className="automaticffl-ammo-notice">
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
								{ settings.i18n?.ammoSelectState || __(
									'You have ammunition in your cart. Please select your shipping state to determine shipping options.',
									'automaticffl-for-wc'
								) }
							</div>
						</div>
					</div>
				</div>
			);
		}

		// State selected but not restricted - no banner needed, allow normal checkout
		if ( ! requiresFfl ) {
			return null;
		}

		// State is restricted - show FFL selection requirement
		if ( ! settings.isConfigured ) {
			return (
				<div className="automaticffl-dealer-selection">
					<div className="wc-block-components-notices">
						<div className="wc-block-components-notice-banner is-error">
							<div className="wc-block-components-notice-banner__content">
								{ settings.i18n?.fflNotConfiguredContact || __(
									'FFL dealer selection is required for ammunition shipping to your state, but is not configured. Please contact the site administrator.',
									'automaticffl-for-wc'
								) }
							</div>
						</div>
					</div>
				</div>
			);
		}

		// Show FFL selection for restricted state
		return (
			<div className="automaticffl-dealer-selection">
				{ /* Restricted State Warning */ }
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
							{ settings.i18n?.selectDealerBelow || __(
								'FFL dealer selection is required for ammunition shipping to your state. Please select a dealer below.',
								'automaticffl-for-wc'
							) }
						</div>
					</div>
				</div>

				{ /* Selected Dealer Card */ }
				{ selectedDealer && (
					<SelectedDealerCard
						dealer={ selectedDealer }
						userName={ shippingName }
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

	// Hide the info banner for ammo-locked carts once a dealer is selected
	const showInfoBanner = ! ( ammoFflLocked && selectedDealer );
	const infoBannerMessage = ammoFflLocked
		? __( 'Your state requires ammunition to be shipped to a licensed FFL dealer. Please select a dealer below.', 'automaticffl-for-wc' )
		: __( 'You have a firearm in your cart and must choose a Licensed Firearm Dealer (FFL) for the Shipping Address.', 'automaticffl-for-wc' );

	return (
		<div className="automaticffl-dealer-selection">
			{ /* Info Banner */ }
			{ showInfoBanner && (
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
							{ infoBannerMessage }
						</div>
					</div>
				</div>
			) }

			{ /* Selected Dealer Card */ }
			{ selectedDealer && (
				<SelectedDealerCard
					dealer={ selectedDealer }
					userName={ shippingName }
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
