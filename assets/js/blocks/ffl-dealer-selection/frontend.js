/**
 * FFL Dealer Selection Block - Frontend Entry
 *
 * Uses WooCommerce Blocks SlotFill to inject FFL dealer selection
 * into the checkout shipping section automatically.
 */

import { ExperimentalOrderShippingPackages } from '@woocommerce/blocks-checkout';
import { registerPlugin } from '@wordpress/plugins';
import FFLDealerSelection from './components/FFLDealerSelection';

/**
 * Render the FFL Dealer Selection in the shipping packages slot
 */
const FflShippingIntegration = () => {
	return (
		<ExperimentalOrderShippingPackages>
			<FFLDealerSelection />
		</ExperimentalOrderShippingPackages>
	);
};

/**
 * Register the plugin to inject FFL dealer selection
 */
registerPlugin( 'automaticffl-dealer-selection', {
	render: FflShippingIntegration,
	scope: 'woocommerce-checkout',
} );
