const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const path = require('path');

// Remove the default DependencyExtractionWebpackPlugin
const filteredPlugins = defaultConfig.plugins.filter(
	(plugin) =>
		plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
);

module.exports = {
	...defaultConfig,
	entry: {
		'ffl-dealer-selection-frontend': path.resolve(
			__dirname,
			'assets/js/blocks/ffl-dealer-selection/frontend.js'
		),
		'ffl-dealer-selection-editor': path.resolve(
			__dirname,
			'assets/js/blocks/ffl-dealer-selection/index.js'
		),
	},
	output: {
		path: path.resolve(__dirname, 'build'),
		filename: '[name].js',
	},
	plugins: [
		...filteredPlugins,
		new WooCommerceDependencyExtractionWebpackPlugin(),
	],
};
