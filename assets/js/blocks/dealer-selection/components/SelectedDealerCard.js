/**
 * Selected Dealer Card Component
 *
 * Displays information about the selected FFL dealer.
 */

import { __ } from '@wordpress/i18n';

/**
 * Format phone number for display
 *
 * @param {string} phone Raw phone number
 * @return {string} Formatted phone number
 */
const formatPhone = ( phone ) => {
	if ( ! phone ) {
		return '';
	}
	// Remove non-digits
	const digits = phone.replace( /\D/g, '' );
	// Format as (XXX)-XXX-XXXX
	if ( digits.length === 10 ) {
		return `(${ digits.slice( 0, 3 ) })-${ digits.slice( 3, 6 ) }-${ digits.slice( 6 ) }`;
	}
	return phone;
};

/**
 * SelectedDealerCard Component
 *
 * @param {Object} props Component props
 * @param {Object} props.dealer Selected dealer information
 * @param {Object} props.userName User name information
 * @return {JSX.Element} Selected dealer card
 */
const SelectedDealerCard = ( { dealer, userName } ) => {
	const formattedAddress = [
		dealer.address1,
		dealer.city,
		dealer.stateOrProvinceCode,
	]
		.filter( Boolean )
		.join( ', ' );

	const formattedPhone = formatPhone( dealer.phone );
	const fullName = `${ userName.first_name } ${ userName.last_name }`;

	return (
		<div
			id="automaticffl-dealer-selected"
			className="automaticffl-dealer-selected"
		>
			<p>{ __( 'Your order will be shipped to', 'automaticffl-for-wc' ) }:</p>
			<div id="ffl-selected-dealer" className="ffl-result-body">
				<p className="customer-name">{ fullName }</p>
				<p className="dealer-name">{ dealer.company || '' }</p>
				<p className="dealer-address">{ formattedAddress }</p>
				{ dealer.phone && (
					<a href={ `tel:${ dealer.phone }` }>
						<p>
							<span className="dealer-phone dealer-phone-formatted">
								{ formattedPhone }
							</span>
						</p>
					</a>
				) }
			</div>
		</div>
	);
};

export default SelectedDealerCard;
