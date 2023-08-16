<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL;

use RefactoredGroup\AutomaticFFL\Views\Cart;
use RefactoredGroup\AutomaticFFL\Views\Checkout;
use RefactoredGroup\AutomaticFFL\Helper\Config;
use RefactoredGroup\AutomaticFFL\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin.
 *
 * @since 1.0.0
 */
class Plugin {

	/** Plugin version number */
	const VERSION = '1.0.0';

	/** Plugin ID */
	const PLUGIN_ID = 'automaticffl-for-woocommerce';

	/**
	 * Instance of Plugin
	 *
	 * @var Plugin
	 */
	protected static $instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->add_hooks();
		$this->add_filters();

		if ( is_admin() ) {
			$this->admin_settings = new \RefactoredGroup\AutomaticFFL\Admin\Settings();
		}
	}

	/**
	 * Get plugin ID
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id() {
		return self::PLUGIN_ID;
	}

	/**
	 * Add hooks
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function add_hooks() {
		// Add frontend hooks.
		add_action( 'woocommerce_before_cart_table', array( Cart::class, 'verify_mixed_cart' ) );
		add_action( 'woocommerce_checkout_init', array( Checkout::class, 'verify_mixed_cart' ) );

		// Load map experience.
		add_action( 'woocommerce_before_checkout_shipping_form', array( Checkout::class, 'get_ffl' ) );
		add_action('woocommerce_after_order_notes', array(Checkout::class, 'add_automaticffl_checkout_field'));
		add_action('woocommerce_checkout_update_order_meta', array(Checkout::class, 'after_checkout_create_order'), 20, 2);
		add_action('woocommerce_checkout_update_order_meta', array(Checkout::class, 'save_automaticffl_checkout_field_value'));
	}

	/**
	 * Add filters
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function add_filters() {
		// add a 'Configure' link to the plugin action links.
		add_filter( 'plugin_action_links_' . plugin_basename( $this->get_plugin_file() ), array( $this, 'plugin_action_links' ) );

		// Clear shipping address fields in the form.
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'clear_shipping_address_fields' ), 10, 2 );

		// Do not save shipping address.
		add_filter( 'woocommerce_checkout_update_customer_data', array( $this, 'maybe_update_customer_data' ), 10, 2 );
		add_filter( 'woocommerce_checkout_fields', array(Checkout::class, 'automaticffl_custom_fields') );
	}

	/**
	 * If this is a FFL cart, do not update customer account data.
	 * This prevents the dealer shipping address from being saved.
	 *
	 * @TODO: Find a way of preventing only the shipping address from being saved
	 *
	 * @since 1.0.0
	 *
	 * @param bool $boolean Yes/No to wther the customer are will be updated.
	 *
	 * @return boolean
	 */
	public function maybe_update_customer_data( $boolean ) {
		if ( Config::is_ffl_cart() ) {
			$boolean = false;
		}
		return $boolean;
	}

	/**
	 * Clear all shipping address fields
	 *
	 * @param mixed $value Input Value.
	 * @param mixed $input Input name.
	 *
	 * @return mixed|string
	 */
	public function clear_shipping_address_fields( $value, $input ) {

		if ( strpos( $input, 'shipping_' ) !== false ) {
			$value = '';
		}

		return $value;
	}

	/**
	 * Get Plugin URL
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return untrailingslashit( plugins_url( '/', _FFL_LOADER_ ) );
	}

	/**
	 * Get Plugin File
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_plugin_file() {
		$slug = dirname( plugin_basename( _FFL_LOADER_ ) );
		return trailingslashit( $slug ) . $slug . '.php';
	}

	/**
	 * Create action links for the plugins page
	 *
	 * @since 1.0.0
	 *
	 * List of actions
	 *
	 * @param array $actions List of actions.
	 *
	 * @return array
	 */
	public function plugin_action_links( $actions ) {
		$custom_actions = array();

		// Settings URL.
		if ( $this->get_settings_link( $this->get_id() ) ) {
			$custom_actions['configure'] = $this->get_settings_link( $this->get_id() );
		}

		// Add links to the front of the actions list.
		return array_merge( $custom_actions, $actions );
	}

	/**
	 * Create configure link for plugins page
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $plugin_id The plugin ID.
	 *
	 * @return string
	 */
	public function get_settings_link( $plugin_id = null ) {
		$settings_url = $this->get_settings_url( $plugin_id );
		if ( $settings_url ) {
			return sprintf( '<a href="%s">%s</a>', $settings_url, esc_html__( 'Configure', 'automaticffl-for-woocommerce' ) );
		}

		return '';
	}

	/**
	 * URL to be used as the configuration page
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $plugin_id The Plugin ID.
	 *
	 * @return string|void
	 */
	public function get_settings_url( $plugin_id = null ) {
		return admin_url( 'admin.php?page=wc-ffl' );
	}

	/**
	 * Ensures only one instance is/can be loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get plugin name for the plugins listing page
	 *
	 * @since 1.0.0
	 *
	 * @return string|void
	 */
	public function get_plugin_name() {
		return __( 'Automatic FFL for WooCommerce', 'automaticffl-for-woocommerce' );
	}

	/**
	 * Get the plugin file
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_file() {
		return __FILE__;
	}
}
