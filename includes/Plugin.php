<?php
/**
 * FFL for WooCommerce Plugin
 * @author    Refactored Group
 * @copyright Copyright (c) 2023
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

namespace RefactoredGroup\AutomaticFFL;

use RefactoredGroup\AutomaticFFL\Views\Cart;
use RefactoredGroup\AutomaticFFL\Views\Checkout;

defined( 'ABSPATH' ) or exit;

/**
 * @since 1.0.0
 */
class Plugin {

    /** plugin version number */
    const VERSION = '1.0.0';

    /** plugin id */
    const PLUGIN_ID = 'automaticffl-for-woocommerce';

    /** @var Plugin */
    protected static $instance;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->add_hooks();

        if ( is_admin() ) {
            $this->admin_settings = new \RefactoredGroup\AutomaticFFL\Admin\Settings;
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
     * @since 1.0.0
     *
     * @return void
     */
    private function add_hooks() {
        // Add frontend hooks
        add_action( 'woocommerce_before_cart', array( Cart::class, 'verify_mixed_cart' ) );
        add_action( 'woocommerce_checkout_init', array( Checkout::class, 'verify_mixed_cart' ) );
        add_action( 'woocommerce_check_cart_items', array( Checkout::class, 'verify_mixed_cart' ) );

        // Load map experience
        add_action( 'woocommerce_before_checkout_shipping_form', array( Checkout::class, 'get_map' ) );
        add_action( 'woocommerce_before_checkout_shipping_form', array( Checkout::class, 'get_js' ) );
        add_action( 'woocommerce_before_checkout_shipping_form', array( Checkout::class, 'get_css' ) );

        // add a 'Configure' link to the plugin action links
        add_filter( 'plugin_action_links_' . plugin_basename( $this->get_plugin_file() ), array( $this, 'plugin_action_links' ) );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function get_plugin_url() {
        return untrailingslashit( plugins_url( '/', _FFL_LOADER_ ) );
    }

    /**
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
     * @param $actions
     * @return array
     */
    public function plugin_action_links( $actions ) {
        $custom_actions = [];

        // Settings URL
        if ( $this->get_settings_link( $this->get_id() ) ) {
            $custom_actions['configure'] = $this->get_settings_link( $this->get_id() );
        }

        // Add links to the front of the actions list
        return array_merge( $custom_actions, $actions );
    }

    /**
     * Create configure link for plugins page
     *
     * @since 1.0.0
     *
     * @param $plugin_id
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
     * @param $plugin_id
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
