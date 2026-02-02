<?php
/**
 * WooCommerce Blocks Integration
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Blocks;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use RefactoredGroup\AutomaticFFL\Helper\Config;
use RefactoredGroup\AutomaticFFL\Views\Checkout;

/**
 * Class Blocks_Integration
 *
 * Integrates the FFL dealer selection into WooCommerce Blocks checkout.
 *
 * @since 1.0.14
 */
class Blocks_Integration implements IntegrationInterface {

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'automaticffl';
	}

	/**
	 * Initialize the integration.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->register_block_type();
		$this->register_frontend_scripts();
		$this->register_editor_scripts();
		$this->add_settings_data();
	}

	/**
	 * Add settings data to wcSettings for frontend access.
	 *
	 * @return void
	 */
	private function add_settings_data() {
		// Register data with the WooCommerce Blocks Asset Data Registry
		add_action(
			'woocommerce_blocks_checkout_enqueue_data',
			function() {
				if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
					$data_registry = \Automattic\WooCommerce\Blocks\Package::container()->get(
						\Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry::class
					);
					$data_registry->add( 'automaticffl_data', $this->get_script_data() );
				}
			}
		);

		// Fallback: Also add via script localization
		add_action(
			'wp_enqueue_scripts',
			function() {
				if ( function_exists( 'is_checkout' ) && is_checkout() ) {
					wp_localize_script(
						'automaticffl-blocks-frontend',
						'automaticfflBlocksData',
						$this->get_script_data()
					);
				}
			},
			20
		);
	}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	private function register_block_type() {
		register_block_type(
			dirname( _AFFL_LOADER_ ) . '/assets/js/blocks/ffl-dealer-selection'
		);
	}

	/**
	 * Register frontend scripts.
	 *
	 * @return void
	 */
	private function register_frontend_scripts() {
		$script_path       = '/build/ffl-dealer-selection-frontend.js';
		$script_url        = plugins_url( $script_path, _AFFL_LOADER_ );
		$script_asset_path = dirname( _AFFL_LOADER_ ) . '/build/ffl-dealer-selection-frontend.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_path ),
			);

		wp_register_script(
			'automaticffl-blocks-frontend',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
	}

	/**
	 * Register editor scripts.
	 *
	 * @return void
	 */
	private function register_editor_scripts() {
		$script_path       = '/build/ffl-dealer-selection-editor.js';
		$script_url        = plugins_url( $script_path, _AFFL_LOADER_ );
		$script_asset_path = dirname( _AFFL_LOADER_ ) . '/build/ffl-dealer-selection-editor.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_path ),
			);

		wp_register_script(
			'automaticffl-blocks-editor',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'automaticffl-blocks-frontend' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 'automaticffl-blocks-editor' );
	}

	/**
	 * Returns an array of script data to pass to the frontend.
	 *
	 * Data will be available at wcSettings['automaticffl_data']
	 *
	 * @return array
	 */
	public function get_script_data() {
		$user_name = Checkout::get_user_name();
		$store_hash = Config::get_store_hash();
		$maps_api_key = Config::get_google_maps_api_key();

		// Build iframe URL
		$iframe_url = '';
		// Config::get_store_hash() and get_google_maps_api_key() pass `true` as the
		// get_option() default, so when the option is unset they return boolean true.
		// The '1' check handles SETTING_YES being stored as the default value.
		$has_valid_store_hash = ! empty( $store_hash ) && $store_hash !== true && $store_hash !== '1';
		$has_valid_maps_key   = ! empty( $maps_api_key ) && $maps_api_key !== true && $maps_api_key !== '1';

		if ( $has_valid_store_hash && $has_valid_maps_key ) {
			$base_url = Config::get_iframe_map_url();
			$params = array(
				'store_hash'   => $store_hash,
				'platform'     => 'WooCommerce',
				'maps_api_key' => $maps_api_key,
			);
			$iframe_url = add_query_arg( $params, $base_url );
		}

		return array(
			'isFflCart'        => Config::has_ffl_products() && ! Config::is_mixed_cart(),
			'hasFflProducts'   => Config::has_ffl_products(),
			'isMixedCart'      => Config::is_mixed_cart(),
			'iframeUrl'        => $iframe_url,
			'allowedOrigins'   => Config::get_iframe_allowed_origins(),
			'userName'         => $user_name,
			'isConfigured'     => $has_valid_store_hash && $has_valid_maps_key,
		);
	}

	/**
	 * Get file version based on file modification time.
	 *
	 * @param string $file Relative file path.
	 *
	 * @return string
	 */
	private function get_file_version( $file ) {
		$file_path = dirname( _AFFL_LOADER_ ) . $file;
		if ( file_exists( $file_path ) ) {
			return (string) filemtime( $file_path );
		}
		return '1.0.14';
	}
}
