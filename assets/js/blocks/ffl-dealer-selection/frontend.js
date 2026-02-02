/**
 * FFL Dealer Selection Block - Frontend Entry
 *
 * Uses WooCommerce Blocks SlotFill to inject FFL dealer selection
 * into the checkout shipping section automatically.
 *
 * NOTE: ExperimentalOrderShippingPackages is an experimental WooCommerce slot
 * (prefixed with "Experimental"). It is subject to renaming or removal when it
 * graduates to stable. If WooCommerce drops the "Experimental" prefix, update
 * the import to `OrderShippingPackages`. Monitor WooCommerce changelogs on updates.
 * See: https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/available-slot-fills/
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
