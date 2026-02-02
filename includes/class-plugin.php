<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL;

use RefactoredGroup\AutomaticFFL\Views\Cart;
use RefactoredGroup\AutomaticFFL\Views\Checkout;
use RefactoredGroup\AutomaticFFL\Views\Thank_You;
use RefactoredGroup\AutomaticFFL\Helper\Config;
use RefactoredGroup\AutomaticFFL\Helper\Cart_Analyzer;
use RefactoredGroup\AutomaticFFL\Helper\Saved_Cart;
use RefactoredGroup\AutomaticFFL\Admin\Settings;
use RefactoredGroup\AutomaticFFL\Admin\Product_FFL_Meta;
use RefactoredGroup\AutomaticFFL\Blocks\Blocks_Integration;
use RefactoredGroup\AutomaticFFL\Blocks\Store_Api_Extension;
use RefactoredGroup\AutomaticFFL\Helper\US_States;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin.
 *
 * @since 1.0.0
 */
class Plugin {

	/** Plugin ID */
	const PLUGIN_ID = 'automaticffl-for-wc';

	/**
	 * Shipping address field keys used for stash/restore operations.
	 *
	 * @var array
	 */
	const SHIPPING_FIELDS = array(
		'first_name',
		'last_name',
		'company',
		'address_1',
		'address_2',
		'city',
		'state',
		'postcode',
		'country',
		'phone',
	);

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
	 * Product FFL Meta handler instance
	 *
	 * @var \RefactoredGroup\AutomaticFFL\Admin\Product_FFL_Meta
	 */
	private $product_ffl_meta;

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
			$this->admin_settings   = new \RefactoredGroup\AutomaticFFL\Admin\Settings();
			$this->product_ffl_meta = new Product_FFL_Meta();
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
		add_action( 'woocommerce_before_cart_table', array( Cart::class, 'show_restoration_notice' ), 5 );
		add_action( 'woocommerce_before_cart_table', array( Cart::class, 'verify_mixed_cart' ) );
		add_action( 'woocommerce_checkout_init', array( Checkout::class, 'verify_mixed_cart' ) );

		// Reactive ammo + regular notice for classic checkout (watches state fields).
		add_action( 'woocommerce_checkout_before_customer_details', array( Checkout::class, 'get_ammo_regular_notice' ) );

		// Load map experience.
		add_action( 'woocommerce_before_checkout_shipping_form', array( Checkout::class, 'get_ffl' ) );
		add_action('woocommerce_after_order_notes', array(Checkout::class, 'add_automaticffl_checkout_field'));
		add_action('woocommerce_checkout_update_order_meta', array(Checkout::class, 'after_checkout_create_order'), 20, 2);
		add_action('woocommerce_checkout_update_order_meta', array(Checkout::class, 'save_automaticffl_checkout_field_value'));
		add_action('wp_enqueue_scripts', array($this, 'automaticffl_enqueue'));

		// AJAX handler for cart state selection (ammo + regular mixed carts).
		add_action( 'wp_ajax_automaticffl_set_ammo_state', array( Cart::class, 'ajax_set_ammo_state' ) );
		add_action( 'wp_ajax_nopriv_automaticffl_set_ammo_state', array( Cart::class, 'ajax_set_ammo_state' ) );

		// Save for later AJAX handlers.
		add_action( 'wp_ajax_automaticffl_save_for_later', array( Cart::class, 'ajax_save_for_later' ) );
		add_action( 'wp_ajax_nopriv_automaticffl_save_for_later', array( Cart::class, 'ajax_save_for_later' ) );

		// Thank you page redirect for saved items (top of page).
		add_action( 'woocommerce_before_thankyou', array( Thank_You::class, 'maybe_show_redirect_notice' ) );

		// Cart restoration after checkout.
		// Use template_redirect instead of woocommerce_before_cart because the latter
		// doesn't fire when the cart is empty (which it will be after checkout).
		add_action( 'template_redirect', array( $this, 'maybe_restore_saved_items' ) );

		// Set restore flag on checkout complete (classic checkout).
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'set_restore_flag_on_checkout' ) );

		// Set restore flag on checkout complete (Blocks checkout).
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'set_restore_flag_on_checkout' ) );

		// Validate checkout for ammo + regular mixed carts in restricted states.
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_ammo_regular_checkout' ) );

		// Stash customer shipping address before blocks checkout overwrites user meta.
		// Classic checkout doesn't need a stash (user meta is protected by maybe_update_customer_data),
		// but blocks' sync_customer_data_with_order() writes the dealer address to user meta directly.
		add_action( 'template_redirect', array( $this, 'stash_shipping_before_checkout' ) );

		// Restore customer shipping address after FFL order.
		// Classic: session-only pollution — reload correct address from user meta.
		// Blocks: session AND user meta pollution — restore from stash to both.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'restore_shipping_after_ffl_classic' ), 20 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'restore_shipping_after_ffl_blocks' ), 20 );

		// Hide "ship to different address" when FFL dealer handles shipping.
		add_action( 'wp_head', array( $this, 'maybe_hide_ship_to_different_address' ) );

		// Clear session state when cart is emptied.
		add_action( 'woocommerce_cart_emptied', function() {
			if ( WC()->session ) {
				WC()->session->set( 'automaticffl_ammo_state', '' );
			}
		});

		// Override the shipping fields since some themes copy the billing address to the shipping address.
		// Uses Cart_Analyzer so ammo-only carts in restricted states are also handled.
		add_action('woocommerce_checkout_create_order', function($order, $data) {
			$analyzer = new Cart_Analyzer();
			if ( ! $analyzer->has_ffl_products() || $analyzer->is_mixed_ffl_regular() ) {
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

		// Add spacing between paragraphs in order notes.
		add_action( 'admin_head', function() {
			echo '<style>
				.note_content p { margin-bottom: 10px !important; }
			</style>';
		});
	}

	/**
	 * Validate checkout for ammo + regular mixed carts in restricted states.
	 *
	 * Prevents checkout when the cart contains both ammunition and regular products
	 * and the shipping state requires FFL for ammunition.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public function validate_ammo_regular_checkout() {
		$analyzer = new Cart_Analyzer();

		// Only validate ammo + regular mixed carts.
		if ( ! $analyzer->is_ammo_regular_mixed() ) {
			return;
		}

		$restricted_states = $analyzer->get_ammo_restricted_states();

		// Determine the actual shipping state from the checkout form.
		// When "ship to different address" is unchecked, WooCommerce uses billing address.
		// Always check POST data first — the session state from the cart page may be stale
		// if the user changed their address on the checkout form.
		$ship_to_different = ! empty( $_POST['ship_to_different_address'] );

		if ( $ship_to_different && ! empty( $_POST['shipping_state'] ) ) {
			$shipping_state = sanitize_text_field( wp_unslash( $_POST['shipping_state'] ) );
		} elseif ( ! empty( $_POST['billing_state'] ) ) {
			$shipping_state = sanitize_text_field( wp_unslash( $_POST['billing_state'] ) );
		} else {
			// Fallback to session state (e.g., if POST data is missing).
			$shipping_state = WC()->session ? WC()->session->get( 'automaticffl_ammo_state', '' ) : '';
		}

		// If state is restricted, block checkout.
		if ( ! empty( $shipping_state ) && in_array( $shipping_state, $restricted_states, true ) ) {
			$state_name = US_States::get_name( $shipping_state );

			wc_add_notice(
				sprintf(
					/* translators: %s: state name */
					__( 'Ammunition and regular products in your cart require separate orders for shipping to %s. Please modify your cart.', 'automaticffl-for-wc' ),
					$state_name
				),
				'error'
			);
		}
	}

	/**
	 * Maybe restore saved items to cart.
	 *
	 * Called on template_redirect hook. Checks for the token in the URL
	 * parameter and restores the saved items.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public function maybe_restore_saved_items() {
		// Only check URL parameter - this is the reliable trigger from thank you page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['affl_token'] )
			? sanitize_text_field( wp_unslash( $_GET['affl_token'] ) )
			: '';

		if ( empty( $token ) ) {
			return;
		}

		// Ensure WooCommerce is available.
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		// Ensure cart is loaded.
		if ( is_null( WC()->cart ) && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( ! WC()->cart ) {
			return;
		}

		// Get items directly by token.
		$saved_data = Saved_Cart::get_saved_items_by_token( $token );
		if ( ! $saved_data || empty( $saved_data['items'] ) ) {
			return;
		}

		// Restore items using the saved data.
		$result = Saved_Cart::restore_items( $saved_data );

		// Clean up transient after restoration.
		Saved_Cart::clear_saved_items_by_token( $token );

		// Store notice for display via woocommerce_before_cart_table hook.
		$notice_data = array(
			'success' => $result['success'],
			'message' => $result['message'],
			'failed'  => $result['failed'] ?? array(),
		);
		set_transient( 'affl_restoration_notice_' . WC()->session->get_customer_id(), $notice_data, 60 );

		// Redirect to cart without the token to prevent duplicate restoration
		// and ensure the cart page renders with the restored items.
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	/**
	 * Save token to order meta when checkout is completed.
	 *
	 * Called on woocommerce_checkout_order_processed (classic) and
	 * woocommerce_store_api_checkout_order_processed (Blocks).
	 *
	 * The token stored in order meta allows reliable retrieval on the thank you page.
	 *
	 * @since 1.0.14
	 *
	 * @param int|\WC_Order $order_or_id Order ID or order object.
	 * @return void
	 */
	public function set_restore_flag_on_checkout( $order_or_id ) {
		$order_id = $order_or_id instanceof \WC_Order
			? $order_or_id->get_id()
			: intval( $order_or_id );

		// Copy token from session to order meta for reliable retrieval.
		Saved_Cart::save_token_to_order( $order_id );
	}

	/**
	 * Stash customer's shipping address on checkout page load.
	 *
	 * Needed for blocks checkout where sync_customer_data_with_order() writes
	 * the FFL dealer address to user meta. The stash captures the real address
	 * from user meta before any checkout interactions can overwrite it.
	 *
	 * Classic checkout doesn't need this (user meta is protected by
	 * maybe_update_customer_data), but stashing is harmless and the stash is
	 * always cleaned up after order completion.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public function stash_shipping_before_checkout() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		// Skip the order-received (thank you) page.
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		if ( ! WC()->session ) {
			return;
		}

		$user_id = get_current_user_id();

		// Read directly from user meta — not WC()->customer which uses the
		// session data store and may already have stale dealer address data.
		$stash = self::get_shipping_from_user_meta( $user_id );

		WC()->session->set( '_affl_original_shipping', $stash );
	}

	/**
	 * Restore customer shipping address after FFL order — classic checkout.
	 *
	 * Classic checkout only pollutes the session (via update_order_review AJAX).
	 * User meta is intact because maybe_update_customer_data() blocks
	 * process_customer() from saving. So we just reload from user meta into
	 * the session-backed WC_Customer.
	 *
	 * @since 1.0.14
	 *
	 * @param int $order_id The order ID.
	 * @return void
	 */
	public function restore_shipping_after_ffl_classic( $order_id ) {
		// Always clean up the stash.
		if ( WC()->session ) {
			WC()->session->set( '_affl_original_shipping', null );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Only restore for FFL orders.
		if ( empty( $order->get_meta( '_ffl_license_field' ) ) ) {
			return;
		}

		$customer = WC()->customer;
		if ( ! $customer ) {
			return;
		}

		$user_id = get_current_user_id();

		// User meta is intact — reload the real address into the session.
		// For guests, this returns empty strings which clears the dealer address.
		$address = self::get_shipping_from_user_meta( $user_id );
		self::set_customer_shipping( $customer, $address );

		// WC()->customer uses the session data store, so save() writes to session only.
		$customer->save();
	}

	/**
	 * Restore customer shipping address after FFL order — blocks checkout.
	 *
	 * Blocks checkout pollutes BOTH session and user meta. The session is
	 * polluted by the cart/update-customer Store API endpoint. User meta is
	 * polluted by sync_customer_data_with_order() which creates a non-session
	 * WC_Customer from the order and saves to wp_usermeta.
	 *
	 * Restores from the stash (captured on page load before interactions)
	 * to both session and user meta.
	 *
	 * @since 1.0.14
	 *
	 * @param \WC_Order $order The order object.
	 * @return void
	 */
	public function restore_shipping_after_ffl_blocks( $order ) {
		// Retrieve and clear the stash.
		$stash = null;
		if ( WC()->session ) {
			$stash = WC()->session->get( '_affl_original_shipping' );
			WC()->session->set( '_affl_original_shipping', null );
		}

		if ( ! $order ) {
			return;
		}

		// Only restore for FFL orders.
		if ( empty( $order->get_meta( '_ffl_license_field' ) ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$address = is_array( $stash ) ? $stash : self::get_empty_shipping();

		// 1. Restore session — set props on WC()->customer (session data store).
		$customer = WC()->customer;
		if ( $customer ) {
			self::set_customer_shipping( $customer, $address );
			$customer->save();
		}

		// 2. Restore user meta — WC()->customer->save() only writes to session
		//    (session data store), so we must update user meta directly to undo
		//    the write from sync_customer_data_with_order().
		if ( $user_id ) {
			self::update_user_meta_shipping( $user_id, $address );
		}
	}

	/**
	 * Get shipping address from user meta, or empty strings for guests.
	 *
	 * @since 1.0.14
	 *
	 * @param int $user_id The user ID (0 for guests).
	 * @return array Associative array of shipping field values.
	 */
	private static function get_shipping_from_user_meta( $user_id ) {
		$address = array();
		foreach ( self::SHIPPING_FIELDS as $field ) {
			$address[ $field ] = $user_id
				? get_user_meta( $user_id, 'shipping_' . $field, true )
				: '';
		}
		return $address;
	}

	/**
	 * Get an empty shipping address array.
	 *
	 * @since 1.0.14
	 *
	 * @return array Associative array with empty string values.
	 */
	private static function get_empty_shipping() {
		return array_fill_keys( self::SHIPPING_FIELDS, '' );
	}

	/**
	 * Set shipping address properties on a WC_Customer instance.
	 *
	 * @since 1.0.14
	 *
	 * @param \WC_Customer $customer The customer object.
	 * @param array        $address  Associative array of shipping field values.
	 * @return void
	 */
	private static function set_customer_shipping( $customer, array $address ) {
		foreach ( self::SHIPPING_FIELDS as $field ) {
			$setter = 'set_shipping_' . $field;
			$customer->$setter( $address[ $field ] ?? '' );
		}
	}

	/**
	 * Update shipping address in user meta.
	 *
	 * @since 1.0.14
	 *
	 * @param int   $user_id The user ID.
	 * @param array $address Associative array of shipping field values.
	 * @return void
	 */
	private static function update_user_meta_shipping( $user_id, array $address ) {
		foreach ( self::SHIPPING_FIELDS as $field ) {
			update_user_meta( $user_id, 'shipping_' . $field, $address[ $field ] ?? '' );
		}
	}

	/**
	 * If an FFL dealer was selected, do not update customer account data.
	 *
	 * Defense-in-depth for classic checkout. Prevents process_customer() from
	 * writing the FFL dealer address to user meta. The primary fix is
	 * restore_shipping_after_ffl_classic() which restores the session.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $boolean Yes/No to whether the customer will be updated.
	 *
	 * @return boolean
	 */
	public function maybe_update_customer_data( $boolean ) {
		if ( ! empty( $_POST['ffl_license_field'] ) ) {
			$boolean = false;
		}
		return $boolean;
	}

	/**
	 * Clear all shipping address fields for firearms carts.
	 *
	 * Only clears shipping fields when the cart contains firearms,
	 * since the FFL dealer address replaces shipping. Ammo-only carts
	 * keep shipping fields so the user can enter their address and the
	 * state field drives FFL requirement detection.
	 *
	 * @param mixed $value Input Value.
	 * @param mixed $input Input name.
	 *
	 * @return mixed|string
	 */
	public function clear_shipping_address_fields( $value, $input ) {
		if ( strpos( $input, 'shipping_' ) === false ) {
			return $value;
		}

		// Cache the check across multiple filter calls.
		static $should_clear = null;
		if ( null === $should_clear ) {
			$analyzer     = new Cart_Analyzer();
			$should_clear = $analyzer->has_firearms() && ! $analyzer->is_mixed_ffl_regular();
		}

		return $should_clear ? '' : $value;
	}

	/**
	 * Enqueue Styles and Scripts for Automatic FFL Plugin
	 *
	 * @return void
	 */
	public function automaticffl_enqueue() {
		// Only load CSS on cart and checkout pages.
		if ( is_cart() || is_checkout() ) {
			wp_enqueue_style(
				'automaticffl-main',
				self::get_plugin_url() . '/assets/css/main.css',
				array(),
				filemtime( dirname( _AFFL_LOADER_ ) . '/assets/css/main.css' )
			);
		}

		// Load ammo state selector script on cart page (needed for AJAX cart updates).
		if ( is_cart() ) {
			wp_enqueue_script(
				'automaticffl-ammo-state',
				self::get_plugin_url() . '/assets/js/ammo-state-selector.js',
				array( 'jquery' ),
				filemtime( dirname( _AFFL_LOADER_ ) . '/assets/js/ammo-state-selector.js' ),
				true
			);
		}
	}

	/**
	 * Hide "ship to different address" checkbox on checkout for firearms carts.
	 *
	 * FFL dealer selection handles shipping, so the standard shipping form
	 * should be hidden. Ammo-only carts keep it visible so the state field
	 * can drive FFL requirement detection.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_hide_ship_to_different_address() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! WC()->cart ) {
			return;
		}

		$analyzer = new Cart_Analyzer();

		if ( $analyzer->has_api_error() || $analyzer->is_mixed_ffl_regular() ) {
			return;
		}

		if ( $analyzer->has_firearms() ) {
			echo '<style>
				#ship-to-different-address, .woocommerce-shipping-fields__field-wrapper {
					display: none !important;
				}
			</style>';
		}
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

}
