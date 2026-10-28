/** Match the server-registered parent in frontend and editor. */
import { getSetting } from '@woocommerce/settings';
import metadata from './block.json';

export const getDealerSelectionMetadata = () => {
	const settings = getSetting( 'automaticffl_data', {} );
	return {
		...metadata,
		parent: settings.dealerSelectionParent
			? [ settings.dealerSelectionParent ]
			: metadata.parent,
	};
};
