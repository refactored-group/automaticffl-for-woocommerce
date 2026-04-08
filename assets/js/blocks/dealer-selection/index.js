/**
 * FFL Dealer Selection — Editor Entry
 *
 * Registers the block as a forced child of
 * woocommerce/checkout-shipping-address-block. The block auto-inserts
 * into every checkout via the lock.default.{remove,move} flags in
 * block.json — no merchant action required.
 *
 * Docs:
 * https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/packages/checkout/blocks-registry/README.md
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<div
				className="wc-block-components-placeholder"
				style={ {
					padding: '20px',
					border: '1px dashed #ccc',
					backgroundColor: '#f9f9f9',
					borderRadius: '4px',
				} }
			>
				<div className="wc-block-components-placeholder__content">
					<strong style={ { display: 'block', marginBottom: '8px' } }>
						{ __( 'FFL Dealer Selection', 'automaticffl-for-wc' ) }
					</strong>
					<span style={ { color: '#666' } }>
						{ __(
							'Customers with firearms or restricted ammunition will select an FFL dealer here.',
							'automaticffl-for-wc'
						) }
					</span>
				</div>
			</div>
		</div>
	);
};

const Save = () => null;

registerBlockType( metadata.name, {
	...metadata,
	icon: {
		src: (
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 512 512"
				width="24"
				height="24"
				fill="currentColor"
			>
				<path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z" />
			</svg>
		),
	},
	edit: Edit,
	save: Save,
} );
