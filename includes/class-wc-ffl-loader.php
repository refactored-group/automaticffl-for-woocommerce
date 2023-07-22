<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

defined( 'ABSPATH' ) || exit;

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
	const PLUGIN_NAME = 'FFL for WooCommerce Plugin';

	/** FFL required Attribute Name */
	const FFL_ATTRIBUTE_NAME = 'ffl-required';

	/**
	 * Admin notices
	 *
	 * @var array admin notices.
	 */
	private $notices = array();

	/**
	 * Instance of this class
	 *
	 * @var WC_FFL_Loader instance of this class.
	 */
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
		add_filter( 'extra_plugin_headers', array( $this, 'add_documentation_header' ) );

		// if the environment check fails, initialize the plugin.
		if ( $this->is_environment_compatible() ) {
			add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
		}
	}

	/**
	 * Creates a product attribute. Inspired by WC_Helper_Product
	 *
	 * @since 1.0.0
	 *
	 * @param string $attribute_name Attribute Name.
	 * @param string $attribute_slug Attribute Slug.
	 *
	 * @return stdClass|null
	 */
	public function create_attribute( string $attribute_name, string $attribute_slug ) {
		delete_transient( 'wc_attribute_taxonomies' );
		\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );

		$attribute_labels  = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
		$attribute_wc_name = array_search( $attribute_slug, $attribute_labels, true );

		if ( ! $attribute_wc_name ) {
			$attribute_wc_name = wc_sanitize_taxonomy_name( $attribute_slug );
		}

		$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_wc_name );
		if ( ! $attribute_id ) {
			$taxonomy_name = wc_attribute_taxonomy_name( $attribute_wc_name );
			unregister_taxonomy( $taxonomy_name );
			$attribute_id = wc_create_attribute(
				array(
					'name'         => $attribute_name,
					'slug'         => $attribute_slug,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => 0,
				)
			);

			register_taxonomy(
				$taxonomy_name,
				/**
				 * Create a new taxonomy object.
				 *
				 * @since 1.0.0
				 */
				apply_filters(
					'woocommerce_taxonomy_objects_' . $taxonomy_name,
					array(
						'product',
					)
				),
				/**
				 * Filter to create a new attribute
				 *
				 * @since 1.0.0
				 *
				 * @param string  $hook_name Hook name.
				 * @param array   $arguments Hook arguments.
				 */
				apply_filters(
					'woocommerce_taxonomy_args_' . $taxonomy_name,
					array(
						'labels'       => array(
							'name' => $attribute_slug,
						),
						'hierarchical' => false,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
					)
				)
			);
		}

		return wc_get_attribute( $attribute_id );
	}

	/**
	 * Create a new Term. Inspired by WC_Helper_Product
	 *
	 * @since 1.0.0
	 *
	 * @param string $term_name Term name.
	 * @param string $term_slug Term slug.
	 * @param string $taxonomy Taxonomy.
	 * @param int    $order Order.
	 *
	 * @return WP_Term|null
	 */
	public function create_term( string $term_name, string $term_slug, string $taxonomy, int $order = 0 ): ?\WP_Term {
		$taxonomy = wc_attribute_taxonomy_name( $taxonomy );
		$term     = get_term_by( 'slug', $term_slug, $taxonomy );

		if ( ! $term ) {
			$term = wp_insert_term(
				$term_name,
				$taxonomy,
				array(
					'slug' => $term_slug,
				)
			);
			$term = get_term_by( 'id', $term['term_id'], $taxonomy );
			if ( $term ) {
				update_term_meta( $term->term_id, 'order', $order );
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

		_doing_it_wrong( __FUNCTION__, esc_html( sprintf( 'You cannot clone instances of %s.', get_class( $this ) ) ), '1.0.0' );
	}

	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {

		_doing_it_wrong( __FUNCTION__, esc_html( sprintf( 'You cannot unserialize instances of %s.', get_class( $this ) ) ), '1.0.0' );
	}

	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init_plugin() {
		// Verify if this plugin is compatible with the environment.
		if ( ! $this->plugins_compatible() ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'functions.php';

		wcffl();
	}

	/**
	 * Verifies if the environment meets the minimum requirements for the plugin activation.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function activation_check() {

		if ( ! $this->is_environment_compatible() ) {

			$this->deactivate_plugin();

			wp_die( esc_html( self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message() ) );
		}
		$this->create_attribute( 'FFL Required', self::FFL_ATTRIBUTE_NAME );
		$this->create_term( 'Yes', 'ffl-yes', self::FFL_ATTRIBUTE_NAME, 10 );
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

			$this->add_admin_notice(
				'update_wordpress',
				'error',
				sprintf(
					'%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
					'<strong>' . self::PLUGIN_NAME . '</strong>',
					self::MINIMUM_WP_VERSION,
					'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
					'</a>'
				)
			);
		}

		if ( ! $this->is_wc_compatible() ) {

			$this->add_admin_notice(
				'update_woocommerce',
				'error',
				sprintf(
					'%1$s requires WooCommerce version %2$s or higher. Please %3$supdate WooCommerce%4$s to the latest version, or %5$sdownload the minimum required version &raquo;%6$s',
					'<strong>' . self::PLUGIN_NAME . '</strong>',
					self::MINIMUM_WC_VERSION,
					'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
					'</a>',
					'<a href="' . esc_url( 'https://downloads.wordpress.org/plugin/woocommerce.' . self::MINIMUM_WC_VERSION . '.zip' ) . '">',
					'</a>'
				)
			);
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
	 * Determines if the WooCommerce version is compatible.
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
	}

	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug the slug for the notice.
	 * @param string $class the css class for the notice.
	 * @param string $message the notice message.
	 */
	private function add_admin_notice( $slug, $class, $message ) {

		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message,
		);
	}

	/**
	 * Displays any admin notices added with \WC_FFL_Loader::add_admin_notice()
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
	 * @since 1.0.0
	 *
	 * @param string[] $headers original headers.
	 *
	 * @return string[]
	 */
	public function add_documentation_header( $headers ) {

		$headers[] = 'Documentation URI';

		return $headers;
	}

	/**
	 * Determines if the server environment is compatible with this plugin.
	 *
	 * @TODO: Override this method to add checks for more than just the PHP version.
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

// Start the plugin.
WC_FFL_Loader::instance();
