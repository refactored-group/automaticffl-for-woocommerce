/**
 * FFL Dealer Selection — Frontend Entry
 *
 * Registers the React component that replaces the block on the
 * checkout frontend. force=true is inferred from the lock attribute
 * in block.json. Use Shipping address normally, with Shipping options
 * as the fallback when the merchant uses billing-only shipping.
 */

import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';
import { getDealerSelectionMetadata } from './registration';
import Block from './block';

registerCheckoutBlock( {
	metadata: getDealerSelectionMetadata(),
	component: Block,
} );
