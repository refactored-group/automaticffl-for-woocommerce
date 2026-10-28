/** Mixed-cart presentation follows confirmed Store API data, never a snapshot. */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';
import MixedCartNotice from '../dealer-selection/components/MixedCartNotice';

const BLOCKED_CLASS = 'automaticffl-cart-blocked';

const CartNotice = ( { extensions, context } ) => {
	const data = extensions?.automaticffl;
	const { usStates = {}, checkoutUrl } = getSetting( 'automaticffl-cart_data', {} );
	const [ isUpdating, setIsUpdating ] = useState( false );
	const [ error, setError ] = useState( '' );
	const visible = context === 'woocommerce/cart' && data?.isApiAvailable !== false &&
		( data?.isFirearmsRegularMixed || data?.isAmmoRegularMixed );
	const state = data?.selectedAmmoState || '';
	const restricted = ( data?.ammoRestrictedStates || [] ).includes( state );
	const blocked = Boolean( visible && ( isUpdating || data.isFirearmsRegularMixed || ! state || restricted ) );

	useEffect( () => {
		document.body.classList.toggle( BLOCKED_CLASS, blocked );
		return () => document.body.classList.remove( BLOCKED_CLASS );
	}, [ blocked ] );

	if ( ! visible ) {
		return null;
	}

	const updateState = async ( event ) => {
		const nextState = event.target.value;
		setIsUpdating( true );
		setError( '' );
		try {
			await extensionCartUpdate( {
				namespace: 'automaticffl-cart-notice',
				data: { state: nextState },
			} );
		} catch ( updateError ) {
			setError( updateError.message || __( 'Unable to save your shipping state. Please try again.', 'automaticffl-for-wc' ) );
		} finally {
			setIsUpdating( false );
		}
	};

	let type = 'error';
	let message = __( 'FFL items ship to your dealer. Regular items ship to you.', 'automaticffl-for-wc' );
	if ( ! data.isFirearmsRegularMixed ) {
		if ( ! state ) {
			type = 'info';
			message = __( 'Your cart contains ammunition and regular products. Please select your shipping state to continue.', 'automaticffl-for-wc' );
		} else if ( restricted ) {
			message = sprintf(
				/* translators: %s: shipping state name. */
				__( 'Ammunition requires shipping to an FFL dealer in %s. Ammunition and regular products cannot be combined.', 'automaticffl-for-wc' ),
				usStates[ state ] || state
			);
		} else {
			type = 'success';
			message = sprintf(
				/* translators: %s: shipping state name. */
				__( 'Standard shipping is available to %s. You may proceed to checkout.', 'automaticffl-for-wc' ),
				usStates[ state ] || state
			);
		}
	}

	return (
		<MixedCartNotice className="automaticffl-cart-notice" type={ type } message={ message } fflCount={ data.fflItemCount } regularCount={ data.regularItemCount } checkoutUrl={ checkoutUrl } isAmmo={ ! data.isFirearmsRegularMixed } showChoices={ data.isFirearmsRegularMixed || restricted } isUpdating={ isUpdating }>
			{ error && <p className="automaticffl-cart-notice-error">{ error }</p> }
			{ ! data.isFirearmsRegularMixed && (
				<p className="automaticffl-state-row">
					<label htmlFor="automaticffl-block-cart-state">{ __( 'Shipping State', 'automaticffl-for-wc' ) }</label>
					<select id="automaticffl-block-cart-state" value={ state } onChange={ updateState } disabled={ isUpdating }>
						<option value="">{ __( 'Select a state...', 'automaticffl-for-wc' ) }</option>
						{ Object.entries( usStates ).map( ( [ code, name ] ) => <option key={ code } value={ code }>{ name }</option> ) }
					</select>
				</p>
			) }
		</MixedCartNotice>
	);
};

export default CartNotice;
