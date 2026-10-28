/** Shared Native notice for mixed carts in Cart and Checkout Blocks. */
import { __ } from '@wordpress/i18n';
import SaveForLaterButtons from './SaveForLaterButtons';

const MixedCartNotice = ( { message, fflCount, regularCount, checkoutUrl, isAmmo = false, type = 'info', showChoices = true, isUpdating = false, children, className = '' } ) => (
	<div className={ `automaticffl-notice automaticffl-notice-${ type } automaticffl-mixed-notice ${ showChoices ? 'automaticffl-mixed-notice--separate' : '' } ${ className }` } role="alert" aria-busy={ isUpdating }>
		<span className="automaticffl-notice-icon">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false">
				<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z" />
			</svg>
		</span>
		<div className="automaticffl-notice-content">
			<p className="automaticffl-notice-message">{ showChoices ? __( 'These items need separate orders', 'automaticffl-for-wc' ) : __( 'Ammunition shipping', 'automaticffl-for-wc' ) }</p>
			<p className="automaticffl-mixed-description">{ message }</p>
			{ children }
			{ showChoices && <p className="automaticffl-mixed-help">{ __( 'The other items will be saved and put back in your cart after checkout.', 'automaticffl-for-wc' ) }</p> }
		</div>
		{ showChoices && <SaveForLaterButtons fflCount={ fflCount } regularCount={ regularCount } checkoutUrl={ checkoutUrl } isAmmo={ isAmmo } disabled={ isUpdating } /> }
	</div>
);

export default MixedCartNotice;
