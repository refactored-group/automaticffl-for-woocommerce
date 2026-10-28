/**
 * FFL Dealer Selection — Frontend Entry
 *
 * Registers the React component that replaces the block on the
 * checkout frontend. force=true is inferred from the lock attribute
 * in block.json, so this block auto-renders with shipping options even
 * when WooCommerce suppresses address fields in billing-only mode.
 */

import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';
import metadata from './block.json';
import Block from './block';

registerCheckoutBlock( {
	metadata,
	component: Block,
} );
