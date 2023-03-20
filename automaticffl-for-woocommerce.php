<?php
/**
 * Plugin Name: Automatic FFL for WooCommerce
 * Plugin URI: http://refactored.group/ffl/woocommerce/ @TODO: Verify if this URL exists in the website
 * Description: The official Automatic FFL for WooCommerce plugin 2
 * Author: Refactored Group
 * Author URI: http://refactored.group
 * Version: 1.0.0
 * Text Domain: automaticffl-for-woocommerce
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2023, Refactored Group
 *
 * License: @TODO: Find appropriate License
 * License URI: @TODO: find license URL
 *
 * @package   FFLCommerce
 * @author    Refactored Group
 * @copyright Copyright (c) 2023, Refactored Group.
 * @license   @TODO: Verify this information
 *
 * Woo: 99999:00000000000000000000000000000000 @TODO: update this when the plugin is released
 */

defined( 'ABSPATH' ) or exit;
ini_set('display_errors', 'On');

define( '_FFL_LOADER_', __FILE__);

// Plugin updates @TODO: Update when available
#woothemes_queue_update( plugin_basename( __FILE__ ), '00000000000000000000000000000000', '99999' ); //

/**
 * The plugin loader class.
 *
 * @since 1.0.0
 */
class WC_FFL_Loader {

    /** Minimum PHP version required */
    const MINIMUM_PHP_VERSION = '7.0';

    /** Minimum WordPress version required */
    const MINIMUM_WP_VERSION = '5.2';

    /** Minimum WooCommerce version required */
    const MINIMUM_WC_VERSION = '3.5';

    /** This plugin's name */
    const PLUGIN_NAME = 'FFL for  WooCommerce Plugin';

    /** @var array admin notices */
    private $notices = array();

    /** @var WC_FFL_Loader instance of this class */
    protected static $instance;

    /**
     * Constructs the class.
     *
     * @since 1.0.0
     */
    protected function __construct() {

        register_activation_hook( __FILE__, array( $this, 'activation_check' ) );

        add_action( 'admin_init', array( $this, 'check_environment' ) );
        add_action( 'admin_init', array( $this, 'add_plugin_notices' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
        add_filter( 'extra_plugin_headers', array( $this, 'add_documentation_header') );

        // if the environment check fails, initialize the plugin
        if ( $this->is_environment_compatible() ) {
            add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
        }
    }

    /**
     * Creates a product attribute. Inspired by WC_Helper_Product
     *
     * @since 1.0.0
     *
     * @param string $attributeName
     * @param string $attributeSlug
     * @return stdClass|null
     */
    function createAttribute(string $attributeName, string $attributeSlug) {
        delete_transient('wc_attribute_taxonomies');
        \WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');

        $attributeLabels = wp_list_pluck(wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name');
        $attributeWCName = array_search($attributeSlug, $attributeLabels, TRUE);

        if (! $attributeWCName) {
            $attributeWCName = wc_sanitize_taxonomy_name($attributeSlug);
        }

        $attributeId = wc_attribute_taxonomy_id_by_name($attributeWCName);
        if (! $attributeId) {
            $taxonomyName = wc_attribute_taxonomy_name($attributeWCName);
            unregister_taxonomy($taxonomyName);
            $attributeId = wc_create_attribute(array(
                'name' => $attributeName,
                'slug' => $attributeSlug,
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => 0,
            ));

            register_taxonomy($taxonomyName, apply_filters('woocommerce_taxonomy_objects_' . $taxonomyName, array(
                'product'
            )), apply_filters('woocommerce_taxonomy_args_' . $taxonomyName, array(
                'labels' => array(
                    'name' => $attributeSlug,
                ),
                'hierarchical' => FALSE,
                'show_ui' => FALSE,
                'query_var' => TRUE,
                'rewrite' => FALSE,
            )));
        }

        return wc_get_attribute($attributeId);
    }

    /**
     * Create a new Term. Inspired by WC_Helper_Product
     *
     * @since 1.0.0
     *
     * @param string $termName
     * @param string $termSlug
     * @param string $taxonomy
     * @param int $order
     * @return WP_Term|null
     */
    function createTerm(string $termName, string $termSlug, string $taxonomy, int $order = 0): ?\WP_Term {
        $taxonomy = wc_attribute_taxonomy_name($taxonomy);

        if (! $term = get_term_by('slug', $termSlug, $taxonomy)) {
            $term = wp_insert_term($termName, $taxonomy, array(
                'slug' => $termSlug,
            ));
            $term = get_term_by('id', $term['term_id'], $taxonomy);
            if ($term) {
                update_term_meta($term->term_id, 'order', $order);
            }
        }

        return $term;
    }

    /**
     * Cloning instances is forbidden due to singleton pattern.
     *
     * @since 1.0.0
     */
    public function __clone() {

        _doing_it_wrong( __FUNCTION__, sprintf( 'You cannot clone instances of %s.', get_class( $this ) ), '1.0.0' );
    }

    /**
     * Unserializing instances is forbidden due to singleton pattern.
     *
     * @since 1.0.0
     */
    public function __wakeup() {

        _doing_it_wrong( __FUNCTION__, sprintf( 'You cannot unserialize instances of %s.', get_class( $this ) ), '1.0.0' );
    }

    /**
     * Initializes the plugin
     *
     * @since 1.0.0
     */
    public function init_plugin() {
        // Verify if this plugin is compatible with the environment
        if ( ! $this->plugins_compatible() ) {
            return;
        }

        // autoload plugin and vendor files
        $loader = require_once( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' );
        $loader->addPsr4( 'RefactoredGroup\\AutomaticFFL\\', __DIR__ . '/includes' );
        require_once( plugin_dir_path( __FILE__ ) . 'includes/Functions.php' );

        WCFFL();
    }

    /**
     * Verifies if the environment meets the minimum requirements for the plugin activation
     *
     * @internal
     *
     * @since 1.0.0
     */
    public function activation_check() {

        if ( ! $this->is_environment_compatible() ) {

            $this->deactivate_plugin();

            wp_die( self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message() );
        }
        $this->createAttribute('FFL Required', 'ffl-required');
        $this->createTerm('Yes', 'ffl-yes', 'ffl-required', 10);
    }

    /**
     * Checks the environment on loading WordPress, just in case the environment changes after activation.
     *
     * @internal
     *
     * @since 1.0.0
     */
    public function check_environment() {

        if ( ! $this->is_environment_compatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {

            $this->deactivate_plugin();

            $this->add_admin_notice( 'bad_environment', 'error', self::PLUGIN_NAME . ' has been deactivated. ' . $this->get_environment_message() );
        }
    }

    /**
     * Adds notices for out-of-date WordPress and/or WooCommerce versions.
     *
     * @internal
     *
     * @since 1.0.0
     */
    public function add_plugin_notices() {

        if ( ! $this->is_wp_compatible() ) {

            $this->add_admin_notice( 'update_wordpress', 'error', sprintf(
                '%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WP_VERSION,
                '<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>'
            ) );
        }

        if ( ! $this->is_wc_compatible() ) {

            $this->add_admin_notice( 'update_woocommerce', 'error', sprintf(
                '%1$s requires WooCommerce version %2$s or higher. Please %3$supdate WooCommerce%4$s to the latest version, or %5$sdownload the minimum required version &raquo;%6$s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WC_VERSION,
                '<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>',
                '<a href="' . esc_url( 'https://downloads.wordpress.org/plugin/woocommerce.' . self::MINIMUM_WC_VERSION . '.zip' ) . '">', '</a>'
            ) );
        }
    }

    /**
     * Determines if the required plugins are compatible.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    private function plugins_compatible() {

        return $this->is_wp_compatible() && $this->is_wc_compatible();
    }

    /**
     * Determines if the WordPress compatible.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    private function is_wp_compatible() {

        if ( ! self::MINIMUM_WP_VERSION ) {
            return true;
        }

        return version_compare( get_bloginfo( 'version' ), self::MINIMUM_WP_VERSION, '>=' );
    }


    /**
     * Determines if the WooCommerce version is compatible
     *
     * @since 1.0.0
     *
     * @return bool
     */
    private function is_wc_compatible() {

        if ( ! self::MINIMUM_WC_VERSION ) {
            return true;
        }

        return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::MINIMUM_WC_VERSION, '>=' );
    }

    /**
     * Deactivates the plugin.
     *
     * @internal
     *
     * @since 1.0.0
     */
    protected function deactivate_plugin() {

        deactivate_plugins( plugin_basename( __FILE__ ) );

        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }

    /**
     * Adds an admin notice to be displayed.
     *
     * @since 1.0.0
     *
     * @param string $slug the slug for the notice
     * @param string $class the css class for the notice
     * @param string $message the notice message
     */
    private function add_admin_notice( $slug, $class, $message ) {

        $this->notices[ $slug ] = array(
            'class'   => $class,
            'message' => $message
        );
    }

    /**
     * Displays any admin notices added with \WC_FFL_Loader::add_admin_notice()
     *
     * @internal
     *
     * @since 1.0.0
     */
    public function admin_notices() {
        foreach ( (array) $this->notices as $notice_key => $notice ) {
            ?>
            <div class="<?php echo esc_attr( $notice['class'] ); ?>">
                <p><?php echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Adds the Documentation URI header.
     *
     * @internal
     *
     * @since 1.0.0
     *
     * @param string[] $headers original headers
     * @return string[]
     */
    public function add_documentation_header( $headers ) {

        $headers[] = 'Documentation URI';

        return $headers;
    }

    /**
     * Determines if the server environment is compatible with this plugin.
     *
     * Override this method to add checks for more than just the PHP version.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    private function is_environment_compatible() {

        return version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' );
    }

    /**
     * Gets the message for display when the environment is incompatible with this plugin.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function get_environment_message() {

        return sprintf( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::MINIMUM_PHP_VERSION, PHP_VERSION );
    }

    /**
     * Gets the main \WC_FFL_Loader instance.
     *
     * Ensures only one instance can be loaded.
     *
     * @since 1.0.0
     *
     * @return \WC_FFL_Loader
     */
    public static function instance() {

        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}

// fire it up!
WC_FFL_Loader::instance();
