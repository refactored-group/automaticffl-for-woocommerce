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
use RefactoredGroup\AutomaticFFL\Helper\Cart_Analyzer;
use RefactoredGroup\AutomaticFFL\Helper\Messages;
use RefactoredGroup\AutomaticFFL\Helper\Saved_Cart;
use RefactoredGroup\AutomaticFFL\Helper\US_States;
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
	 * Cached script data to avoid duplicate computation.
	 *
	 * @var array|null
	 */
	private $script_data_cache = null;

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
		if ( null !== $this->script_data_cache ) {
			return $this->script_data_cache;
		}

		$user_name  = Checkout::get_user_name();
		$iframe_url = Config::build_iframe_url() ?: '';

		// Use Cart_Analyzer for accurate classification
		$analyzer = new Cart_Analyzer();

		// Get item counts for save for later buttons (total quantity, not line items).
		$ffl_count     = $analyzer->get_quantity_by_category( 'firearms' ) + $analyzer->get_quantity_by_category( 'ammo' );
		$regular_count = $analyzer->get_quantity_by_category( 'regular' );

		$this->script_data_cache = array(
			// Cart state flags for frontend
			'isFflCart'            => $analyzer->has_ffl_products() && ! $analyzer->is_mixed_ffl_regular(),
			'hasFflProducts'       => $analyzer->has_ffl_products(),
			'isMixedCart'          => $analyzer->is_mixed_ffl_regular(),

			// New fields for restrictions API
			'hasFirearms'          => $analyzer->has_firearms(),
			'hasAmmo'              => $analyzer->has_ammo(),
			'isAmmoOnly'           => $analyzer->is_ammo_only(),
			'isAmmoEnabled'        => true,
			'ammoRestrictedStates' => $analyzer->get_ammo_restricted_states(),
			'isApiAvailable'       => $analyzer->is_api_available(),
			'usStates'             => US_States::get_all(),

			// Ammo + regular mixed cart fields
			'isAmmoRegularMixed'     => $analyzer->is_ammo_regular_mixed(),
			'isFirearmsRegularMixed' => $analyzer->is_firearms_regular_mixed(),
			'selectedAmmoState'      => WC()->session ? WC()->session->get( 'automaticffl_ammo_state', '' ) : '',
			'cartUrl'                => wc_get_cart_url(),

			// Save for later fields
			'fflItemCount'         => $ffl_count,
			'regularItemCount'     => $regular_count,
			'hasSavedItems'        => Saved_Cart::has_saved_items(),
			'savedItemsCount'      => Saved_Cart::get_saved_items_count(),

			// Common fields
			'iframeUrl'            => $iframe_url,
			'allowedOrigins'       => Config::get_iframe_allowed_origins(),
			'userName'             => $user_name,
			'isConfigured'         => ! empty( $iframe_url ),

			// Centralized messages for consistency with classic checkout
			'i18n'                 => Messages::get_all(),
		);

		return $this->script_data_cache;
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
		return AFFL_VERSION;
	}
}
