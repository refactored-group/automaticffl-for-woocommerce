/**
 * FFL Dealer Selection — Frontend Component
 *
 * Renders inside the WooCommerce shipping address inner-block as a
 * forced child block. Returns null for non-FFL carts. On dealer pick
 * it overwrites shipping with the dealer's address (preserving the
 * customer's first/last name), restores the customer's original
 * address as billing if "use shipping as billing" was on, and adds a
 * body class that drives the CSS rules hiding non-name shipping fields.
 */

import { useState, useEffect, useCallback, useRef, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { CART_STORE_KEY } from '@woocommerce/block-data';
import { getSetting } from '@woocommerce/settings';
import DealerModal from './components/DealerModal';
import SelectedDealerCard from './components/SelectedDealerCard';
import SaveForLaterButtons from './components/SaveForLaterButtons';

// Survives React remounts caused by WC re-rendering the checkout tree.
let persistedAmmoFflLocked = false;
let persistedDealer = null;
// Snapshot of the customer's pre-dealer shipping so we can restore it
// as billing when a dealer pick flips "use shipping as billing" off.
let persistedCustomerAddress = null;

const BODY_CLASS_FFL_REQUIRED = 'automaticffl-ffl-required';

const getSettings = () => {
	let settings = getSetting( 'automaticffl_data', null );

	if ( ! settings && typeof window !== 'undefined' && window.automaticfflBlocksData ) {
		settings = window.automaticfflBlocksData;
	}

	settings = settings || {};

	return {
		isFflCart: settings.isFflCart || false,
		hasFflProducts: settings.hasFflProducts || false,
		isMixedCart: settings.isMixedCart || false,
		iframeUrl: settings.iframeUrl || '',
		allowedOrigins: settings.allowedOrigins || [],
		userName: settings.userName || { first_name: '', last_name: '' },
		isConfigured: settings.isConfigured || false,
		hasFirearms: settings.hasFirearms || false,
		hasAmmo: settings.hasAmmo || false,
		isAmmoOnly: settings.isAmmoOnly || false,
		isAmmoEnabled: settings.isAmmoEnabled || false,
		ammoRestrictedStates: settings.ammoRestrictedStates || [],
		isApiAvailable: settings.isApiAvailable !== false,
		usStates: settings.usStates || {},
		isAmmoRegularMixed: settings.isAmmoRegularMixed || false,
		isFirearmsRegularMixed: settings.isFirearmsRegularMixed || false,
		selectedAmmoState: settings.selectedAmmoState || '',
		cartUrl: settings.cartUrl || '',
		fflItemCount: settings.fflItemCount || 0,
		regularItemCount: settings.regularItemCount || 0,
		hasSavedItems: settings.hasSavedItems || false,
		savedItemsCount: settings.savedItemsCount || 0,
		i18n: settings.i18n || {},
	};
};

const ApiUnavailableNotice = ( { onDismiss } ) => (
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

const Block = () => {
	const settings = getSettings();

	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ selectedDealer, setSelectedDealer ] = useState( persistedDealer );
	const [ noticeDismissed, setNoticeDismissed ] = useState( false );
	const [ ammoFflLocked, setAmmoFflLocked ] = useState( persistedAmmoFflLocked );

	const { setShippingAddress, setBillingAddress } = useDispatch( CART_STORE_KEY );

	const shippingState = useSelect( ( select ) => {
		return select( CART_STORE_KEY ).getCustomerData?.()?.shippingAddress?.state || '';
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

	const requiresFfl =
		settings.isAmmoOnly &&
		settings.isAmmoEnabled &&
		shippingState !== '' &&
		settings.ammoRestrictedStates.includes( shippingState );

	const fflStatusRef = useRef( { required: false, selected: false, isAmmoOnly: false } );

	useEffect( () => { persistedAmmoFflLocked = ammoFflLocked; }, [ ammoFflLocked ] );
	useEffect( () => { persistedDealer = selectedDealer; }, [ selectedDealer ] );

	// FR-4: toggle the body class that drives the hide-fields CSS.
	// Applied whenever an FFL is required (whether or not a dealer is
	// picked yet) so Edit-mode shows only first/last name in both
	// pre-pick and post-pick states. The customer can correct name
	// typos at any time; other fields are either pending (pre-pick,
	// will be set by the FFL) or locked to the dealer (post-pick).
	const isFflRequiredForDisplay =
		( settings.hasFirearms && ! settings.isMixedCart ) ||
		( settings.isAmmoOnly && settings.isAmmoEnabled && ( ammoFflLocked || requiresFfl ) );
	useEffect( () => {
		if ( isFflRequiredForDisplay ) {
			document.body.classList.add( BODY_CLASS_FFL_REQUIRED );
		} else {
			document.body.classList.remove( BODY_CLASS_FFL_REQUIRED );
		}
		return () => {
			document.body.classList.remove( BODY_CLASS_FFL_REQUIRED );
		};
	}, [ isFflRequiredForDisplay ] );

	/**
	 * Set extension data for the Store API checkout request.
	 *
	 * Tries the public setExtensionData (WC 8.9+); falls back to the
	 * internal __internalSetExtensionData on older versions.
	 */
	const setExtensionData = useCallback( ( namespace, data ) => {
		try {
			const checkoutStore = wp.data.dispatch( 'wc/store/checkout' );
			if ( checkoutStore && typeof checkoutStore.setExtensionData === 'function' ) {
				checkoutStore.setExtensionData( namespace, data );
			} else if ( checkoutStore && typeof checkoutStore.__internalSetExtensionData === 'function' ) {
				checkoutStore.__internalSetExtensionData( namespace, data );
			}
		} catch ( e ) {
			// eslint-disable-next-line no-console
			console.error( 'AutomaticFFL: Could not set extension data', e );
		}
	}, [] );

	/**
	 * FR-4a: when a dealer is selected and "use shipping as billing"
	 * is currently on, mirror the customer's pre-dealer address into
	 * billing and flip the toggle off so billing fields render.
	 *
	 * __internalSetUseShippingAsBilling is the only API for the toggle.
	 * Wrapped in try/catch with no-op fallback if WC removes/renames it
	 * — same defensive pattern as setExtensionData above.
	 */
	const restoreCustomerBillingIfNeeded = useCallback( ( customerAddress ) => {
		try {
			const checkoutSelect = wp.data.select( 'wc/store/checkout' );
			const usingShippingForBilling = checkoutSelect?.getUseShippingAsBilling?.();
			if ( ! usingShippingForBilling ) {
				return;
			}
			if ( customerAddress ) {
				setBillingAddress( customerAddress );
			}
			const checkoutDispatch = wp.data.dispatch( 'wc/store/checkout' );
			if ( typeof checkoutDispatch?.__internalSetUseShippingAsBilling === 'function' ) {
				checkoutDispatch.__internalSetUseShippingAsBilling( false );
			}
		} catch ( e ) {
			// eslint-disable-next-line no-console
			console.error( 'AutomaticFFL: Could not restore customer billing', e );
		}
	}, [ setBillingAddress ] );

	const handleDealerSelect = useCallback(
		( dealer ) => {
			// Snapshot the customer's pre-dealer shipping for billing restoration.
			const customerData = wp.data.select( CART_STORE_KEY ).getCustomerData?.() || {};
			const currentShipping = customerData.shippingAddress || {};
			const customerAddress = persistedCustomerAddress || {
				first_name: currentShipping.first_name || '',
				last_name: currentShipping.last_name || '',
				company: currentShipping.company || '',
				address_1: currentShipping.address_1 || '',
				address_2: currentShipping.address_2 || '',
				city: currentShipping.city || '',
				state: currentShipping.state || '',
				postcode: currentShipping.postcode || '',
				country: currentShipping.country || 'US',
				phone: currentShipping.phone || '',
			};
			persistedCustomerAddress = customerAddress;

			setSelectedDealer( dealer );
			setIsModalOpen( false );

			if ( settings.isAmmoOnly && settings.isAmmoEnabled && ! ammoFflLocked ) {
				setAmmoFflLocked( true );
			}

			setExtensionData( 'automaticffl', {
				fflLicense: dealer.fflID || '',
				fflExpirationDate: dealer.expirationDate || '',
				fflUuid: dealer.uuid || '',
				fflCompanyName: dealer.company || '',
			} );

			setShippingAddress( {
				first_name: currentShipping.first_name || '',
				last_name: currentShipping.last_name || '',
				address_1: dealer.address1 || '',
				address_2: dealer.address2 || '',
				city: dealer.city || '',
				state: dealer.stateOrProvinceCode || '',
				postcode: dealer.postalCode || '',
				country: dealer.countryCode || 'US',
				phone: dealer.phone || '',
			} );

			restoreCustomerBillingIfNeeded( customerAddress );
		},
		[
			setExtensionData,
			setShippingAddress,
			restoreCustomerBillingIfNeeded,
			settings.isAmmoOnly,
			settings.isAmmoEnabled,
			ammoFflLocked,
		]
	);

	useEffect( () => {
		const validationStore = wp?.data?.dispatch?.( 'wc/store/validation' );
		if ( ! validationStore ) {
			return;
		}

		const { setValidationErrors, clearValidationError } = validationStore;
		const AMMO_REGULAR_ERROR_ID = 'automaticffl-ammo-regular-blocked';
		const FFL_REQUIRED_ERROR_ID = 'automaticffl-ffl-required';

		const isFflRequired = () => {
			if ( settings.hasFirearms && ! settings.isMixedCart ) {
				return true;
			}
			if ( settings.isAmmoOnly && settings.isAmmoEnabled && ( ammoFflLocked || requiresFfl ) ) {
				return true;
			}
			return false;
		};

		const isAmmoRegularBlocked = () => {
			if ( settings.isAmmoRegularMixed && settings.isAmmoEnabled && shippingState ) {
				return settings.ammoRestrictedStates.includes( shippingState );
			}
			return false;
		};

		if ( isAmmoRegularBlocked() ) {
			setValidationErrors( {
				[ AMMO_REGULAR_ERROR_ID ]: {
					message: __( 'Please modify your cart to continue.', 'automaticffl-for-wc' ),
					hidden: true,
				},
			} );
		} else {
			clearValidationError( AMMO_REGULAR_ERROR_ID );
		}

		if ( isFflRequired() && ! selectedDealer ) {
			setValidationErrors( {
				[ FFL_REQUIRED_ERROR_ID ]: {
					message: settings.isAmmoOnly
						? ( settings.i18n?.selectDealerAmmoRequired || __( 'Please select an FFL dealer. Your state requires ammunition to be shipped to a licensed dealer.', 'automaticffl-for-wc' ) )
						: ( settings.i18n?.selectDealerBeforeOrder || __( 'Please select an FFL dealer before placing your order.', 'automaticffl-for-wc' ) ),
					hidden: true,
				},
			} );
		} else {
			clearValidationError( FFL_REQUIRED_ERROR_ID );
		}

		return () => {
			if ( validationStore ) {
				clearValidationError( AMMO_REGULAR_ERROR_ID );
				clearValidationError( FFL_REQUIRED_ERROR_ID );
			}
		};
	}, [
		selectedDealer,
		settings.hasFirearms,
		settings.isMixedCart,
		settings.isAmmoOnly,
		settings.isAmmoEnabled,
		requiresFfl,
		ammoFflLocked,
		settings.isAmmoRegularMixed,
		settings.ammoRestrictedStates,
		settings.usStates,
		shippingState,
	] );

	useEffect( () => {
		const isFflRequired =
			( settings.hasFirearms && ! settings.isMixedCart ) ||
			( settings.isAmmoOnly && settings.isAmmoEnabled && ( ammoFflLocked || requiresFfl ) );
		fflStatusRef.current = {
			required: isFflRequired,
			selected: !! selectedDealer,
			isAmmoOnly: settings.isAmmoOnly,
		};
	}, [
		selectedDealer,
		settings.hasFirearms,
		settings.isMixedCart,
		settings.isAmmoOnly,
		settings.isAmmoEnabled,
		ammoFflLocked,
		requiresFfl,
	] );

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
			const noticeStore = wp?.data?.dispatch?.( 'core/notices' );
			noticeStore?.removeNotice?.( FFL_NOTICE_ID, 'wc/checkout' );
		};
	}, [] );

	useEffect( () => {
		if ( selectedDealer ) {
			const noticeStore = wp?.data?.dispatch?.( 'core/notices' );
			noticeStore?.removeNotice?.( 'automaticffl-ffl-required', 'wc/checkout' );
		}
	}, [ selectedDealer ] );

	// Clear the dealer when an ammo-only customer transitions to an
	// unrestricted state.
	//
	// Runs even when ammoFflLocked is true: a user-driven switch to an
	// unrestricted state authoritatively resets the FFL flow. Without
	// this, the dealer's restricted-state address would silently remain
	// as the order's shipping (and the body class + hide-fields CSS
	// would stay on, leaving the customer's address form unusable —
	// State and ZIP styled as disabled, other shipping fields hidden
	// entirely).
	//
	// Restores the customer's pre-dealer address (with the now-current
	// state overriding the persisted one) so the form doesn't show the
	// dealer's street/city/zip pre-filled as "their" address. The
	// persisted snapshot was captured before the dealer pick in
	// handleDealerSelect; clearing it here lets a future dealer pick
	// recapture from the now-restored address.
	useEffect( () => {
		if ( ! settings.isAmmoOnly || ! settings.isAmmoEnabled ) {
			return;
		}
		if ( shippingState === '' ) {
			return;
		}
		if ( settings.ammoRestrictedStates.includes( shippingState ) ) {
			return;
		}
		if ( ! selectedDealer && ! ammoFflLocked ) {
			return;
		}

		setSelectedDealer( null );
		setAmmoFflLocked( false );
		setExtensionData( 'automaticffl', {
			fflLicense: '',
			fflExpirationDate: '',
			fflUuid: '',
			fflCompanyName: '',
		} );

		if ( persistedCustomerAddress ) {
			setShippingAddress( {
				...persistedCustomerAddress,
				state: shippingState,
			} );
		}
		persistedCustomerAddress = null;
	}, [
		shippingState,
		settings.isAmmoOnly,
		settings.isAmmoEnabled,
		settings.ammoRestrictedStates,
		selectedDealer,
		ammoFflLocked,
		setExtensionData,
		setShippingAddress,
	] );

	if ( ! settings.isApiAvailable && ! noticeDismissed ) {
		return <ApiUnavailableNotice onDismiss={ () => setNoticeDismissed( true ) } />;
	}

	if ( ! settings.isApiAvailable && noticeDismissed ) {
		return null;
	}

	if ( ! settings.hasFirearms && ! settings.hasAmmo && ! settings.hasFflProducts ) {
		return null;
	}

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

	if ( settings.isAmmoRegularMixed && settings.isAmmoEnabled ) {
		const isRestricted = shippingState && settings.ammoRestrictedStates.includes( shippingState );
		const stateName = settings.usStates[ shippingState ] || shippingState;

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

		return null;
	}

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

	if ( settings.isAmmoOnly && settings.isAmmoEnabled && ! ammoFflLocked ) {
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

		if ( ! requiresFfl ) {
			return null;
		}

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

		return (
			<div className="automaticffl-dealer-selection">
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

				{ selectedDealer && (
					<SelectedDealerCard
						dealer={ selectedDealer }
						userName={ shippingName }
					/>
				) }

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

	const showInfoBanner = ! ( ammoFflLocked && selectedDealer );
	const infoBannerMessage = ammoFflLocked
		? __( 'Your state requires ammunition to be shipped to a licensed FFL dealer. Please select a dealer below.', 'automaticffl-for-wc' )
		: __( 'You have a firearm in your cart and must choose a Licensed Firearm Dealer (FFL) for the Shipping Address.', 'automaticffl-for-wc' );

	return (
		<div className="automaticffl-dealer-selection">
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

			{ selectedDealer && (
				<SelectedDealerCard
					dealer={ selectedDealer }
					userName={ shippingName }
				/>
			) }

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

export default Block;
