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
use RefactoredGroup\AutomaticFFL\Blocks\Blocks_Integration;
use RefactoredGroup\AutomaticFFL\Blocks\Store_Api_Extension;

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
	const PLUGIN_ID = 'automaticffl-for-wc';

	/**
	 * Instance of Plugin
	 *
	 * @var Plugin
	 */
	protected static $instance;

	/**
	 * Admin settings instance
	 *
	 * @var \RefactoredGroup\AutomaticFFL\Admin\Settings
	 */
	public $admin_settings;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->add_hooks();
		$this->add_filters();
		// Note: Blocks integration is now registered early in AFFL_Loader to catch woocommerce_blocks_loaded

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
		add_action( 'woocommerce_process_product_meta', array($this, 'save_ffl_required'), 10, 3  );
		add_action('woocommerce_product_bulk_edit_start', array($this, 'ffl_required_add_admin_edit_checkbox'));
		add_action('woocommerce_product_bulk_edit_save', array($this, 'ffl_required_save_admin_edit_checkbox'));
		add_action('woocommerce_product_quick_edit_start', array($this, 'ffl_required_add_admin_edit_checkbox'));
		add_action('woocommerce_product_quick_edit_save', array($this, 'ffl_required_save_admin_edit_checkbox'));
		add_action('wp_enqueue_scripts', array($this, 'automaticffl_enqueue'));

		// Override the shipping fields since some themes copy the billing address to the shipping address
		add_action('woocommerce_checkout_create_order', function($order, $data) {
			if ( ! Config::is_ffl_cart() ) {
				return;
			}
			if (isset($data['ship_to_different_address']) && $data['ship_to_different_address']) {
				$order->set_shipping_first_name($data['shipping_first_name'] ?? '');
				$order->set_shipping_last_name($data['shipping_last_name'] ?? '');
				$order->set_shipping_company($data['shipping_company'] ?? '');
				$order->set_shipping_country($data['shipping_country'] ?? '');
				$order->set_shipping_address_1($data['shipping_address_1'] ?? '');
				$order->set_shipping_address_2($data['shipping_address_2'] ?? '');
				$order->set_shipping_city($data['shipping_city'] ?? '');
				$order->set_shipping_state($data['shipping_state'] ?? '');
				$order->set_shipping_postcode($data['shipping_postcode'] ?? '');
				$order->set_shipping_phone($data['shipping_phone'] ?? '');
			}
		}, 99, 2);
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
		add_filter( 'product_type_options', array($this, 'ffl_required'), 100, 1 );
		add_filter( 'woocommerce_product_export_column_names', array($this, 'add_ffl_required_export_column') );
		add_filter( 'woocommerce_product_export_product_default_columns', array($this, 'add_ffl_required_export_column') );
		add_filter( 'woocommerce_product_export_product_column_ffl_required', array($this, 'add_ffl_required_export_data'), 10, 2 );
		add_filter( 'woocommerce_csv_product_import_mapping_options', array($this, 'add_ffl_required_to_importer') );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array($this, 'add_ffl_required_to_mapping_screen') );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array($this, 'process_ffl_required_import'), 10, 2 );
	}

	/**
	 * If this is a FFL cart, do not update customer account data.
	 * This prevents the dealer shipping address from being saved.
	 *
	 * @TODO: Find a way of preventing only the shipping address from being saved
	 *
	 * @since 1.0.0
	 *
	 * @param bool $boolean Yes/No to whether the customer will be updated.
	 *
	 * @return boolean
	 */
	public function maybe_update_customer_data( $boolean ) {
		if ( Config::is_ffl_cart() ) {
			$boolean = false;
		}
		return $boolean;
	}

	public function ffl_required($product_type_options) {
		$product_type_options['ffl_required'] = [
			'id'            => '_ffl_required',
			'value'			=> get_post_meta( get_the_ID(), 'ffl_required', true),
			'wrapper_class' => 'show_if_simple show_if_variable show_if_grouped',
			'label'         => 'FFL Required',
			'description'   => 'Check this box if the product requires shipment to an FFL dealer',
			'default'       => 'no',
		];

		return $product_type_options;
	}

	public function save_ffl_required( $id ) {
		$is_ffl = isset ($_POST['_ffl_required']) && 'on' === $_POST['_ffl_required'] ? 'yes' : 'no';
		update_post_meta( $id, '_ffl_required', $is_ffl);
	}

	/**
	 * Add the FFL Required to the exporter and the exporter column menu.
	 *
	 * @param array $fields
	 * @return array $fields
	 */
	function add_ffl_required_export_column( $fields ) {

		$fields['ffl_required'] = 'FFL Required';

		return $fields;
	}

	/**
	 * Provide the FFL Required data to be exported the column.
	 *
	 * @param mixed $value (default: '')
	 * @param WC_Product $product
	 * @return mixed $value - Should be in a format that can be output into a text file (string - 'yes' or 'no').
	 */
	function add_ffl_required_export_data( $value, $product ) {
		$value = $product->get_meta( '_ffl_required' , true, 'edit' );
		return $value;
	}

	/**
	 * Register the FFL Required column in the importer.
	 *
	 * @param array $fields
	 * @return array $fields
	 */
	function add_ffl_required_to_importer( $fields ) {

		$fields['ffl_required'] = 'FFL Required';

		return $fields;
	}

	/**
	 * Add automatic mapping support for FFL Required.
	 * This will automatically select the correct mapping for columns named 'FFL Required' or 'ffl required'.
	 *
	 * @param array $fields
	 * @return array $fields
	 */
	function add_ffl_required_to_mapping_screen( $fields ) {

		$fields['FFL Required'] = 'ffl_required';
		$fields['ffl required'] = 'ffl_required';

		return $fields;
	}

	/**
	 * Process the data read from the CSV file and update the FFL Required field.
	 *
	 * @param WC_Product $object - Product being imported or updated.
	 * @param array $data - CSV data read for the product.
	 * @return WC_Product $object
	 */
	function process_ffl_required_import( $object, $data ) {

		if ( ! empty( $data['ffl_required'] ) ) {
			$object->update_meta_data( '_ffl_required', $data['ffl_required'] );
		}

		return $object;
	}

	/**
	 * Add the FFL Required field to Bulk Edit or Quick Edit on Admin Panel.
	 *
	 */
	function ffl_required_add_admin_edit_checkbox(){
		?>
		<div class="inline-edit-group">
			<label class="alignleft ffl_required" style="width: 100%">
				<span class="title" style="width: fit-content;"><?php esc_html_e('FFL Required', 'automaticffl-for-wc'); ?></span>
				<span class="input-text-wrap" >
					<select class="ffl_required change_to" name="_ffl_required" style="margin-left: 10px">
						<option value=""><?php esc_html_e('— No Change —', 'automaticffl-for-wc'); ?></option>
						<option value="1"><?php esc_html_e('Yes', 'automaticffl-for-wc'); ?></option>
						<option value="0"><?php esc_html_e('No', 'automaticffl-for-wc'); ?></option>
					</select>
				</span>
			</label>
		</div>
	</br></br>
		<?php
	}

	/**
	 * Save the FFL Required field from Bulk Edit or Quick Edit on Admin Panel.
	 *
	 * @param WC_Product $product
	 * @return void
	 */
	function ffl_required_save_admin_edit_checkbox($product){
		$product_id = method_exists($product, 'get_id') ? $product->get_id() : $product->id;

		if(isset($_REQUEST['_ffl_required'])){
			if($_REQUEST['_ffl_required'] == '1'){
				update_post_meta($product_id, '_ffl_required', 'yes');
			} else if($_REQUEST['_ffl_required'] == '0'){
				update_post_meta($product_id, '_ffl_required', 'no');
			}
		}
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
	 * Enqueue Styles and Scripts for Automatic FFL Plugin
	 *
	 * @return void
	 */
	function automaticffl_enqueue() {
		wp_enqueue_style('main', self::get_plugin_url('/') . '/assets/css/main.css');
	}

	/**
	 * Get Plugin URL
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return untrailingslashit( plugins_url( '/', _AFFL_LOADER_ ) );
	}

	/**
	 * Get Plugin File
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_plugin_file() {
		$slug = dirname( plugin_basename( _AFFL_LOADER_ ) );
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
			return sprintf( '<a href="%s">%s</a>', $settings_url, esc_html__( 'Configure', 'automaticffl-for-wc' ) );
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
		return __( 'Automatic FFL for WooCommerce', 'automaticffl-for-wc' );
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
