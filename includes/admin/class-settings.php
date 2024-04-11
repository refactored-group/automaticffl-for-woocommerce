<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Admin;

use RefactoredGroup\AutomaticFFL\Admin\Screens;
use RefactoredGroup\AutomaticFFL\Framework\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings.
 */
class Settings {

	/**
	 * Base settings page ID
	 *
	 * @var string
	 */
	const PAGE_ID = 'wc-ffl';

	/**
	 * Abstract Screens
	 *
	 * @var Abstract_Settings_Screen[]
	 */
	private $screens;

	/**
	 * Whether the new Woo nav should be used.
	 *
	 * @var bool
	 */
	public $use_woo_nav;

	/**
	 * Settings constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->screens = $this->build_menu_item_array();

		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );
		add_action( 'wp_loaded', array( $this, 'save' ) );

	}

	/**
	 * Collection of all screens to be displayed in the Settings page.
	 *
	 * @since 1.0.0
	 */
	private function build_menu_item_array() {

		$screens = array(
			Screens\General::ID => new Screens\General(),
		);

		return $screens;
	}

	/**
	 * Adds FFL to the WooCommerce menu
	 *
	 * @since 1.0.0
	 */
	public function add_menu_item() {
		add_submenu_page(
			'woocommerce',
			__( 'Automatic FFL for WooCommerce', 'automaticffl-for-wc' ),
			__( 'Automatic FFL', 'automaticffl-for-wc' ),
			'manage_woocommerce',
			self::PAGE_ID,
			array( $this, 'render' )
		);
		$this->connect_to_enhanced_admin( 'woocommerce_page_wc-ffl' );
	}

	/**
	 * Enables enhanced admin support for the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $screen_id the ID to connect to.
	 */
	private function connect_to_enhanced_admin( $screen_id ) {
		if ( is_callable( 'wc_admin_connect_page' ) ) {
			$crumbs = array(
				__( 'Automatic FFL for WooCommerce', 'automaticffl-for-wc' ),
			);
			wc_admin_connect_page(
				array(
					'id'        => self::PAGE_ID,
					'screen_id' => $screen_id,
					'path'      => add_query_arg( 'page', self::PAGE_ID, 'admin.php' ),
					'title'     => $crumbs,
				)
			);
		}
	}

	/**
	 * Renders all the tabs of the settings page and its contents
	 *
	 * @since 1.0.0
	 */
	public function render() {
		$tabs        = $this->get_tabs();
		$current_tab = Helper::get_requested_value( 'tab' );
		if ( ! $current_tab ) {
			$current_tab = current( array_keys( $tabs ) );
		}
		$screen = $this->get_screen( $current_tab );
		?>
		<div class="wrap woocommerce">
			<?php if ( ! $this->use_woo_nav ) : ?>
				<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
					<?php foreach ( $tabs as $id => $label ) : ?>
						<a href="<?php echo esc_html( admin_url( 'admin.php?page=' . self::PAGE_ID . '&tab=' . esc_attr( $id ) ) ); ?>" class="nav-tab <?php echo $current_tab === $id ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
			<?php if ( $screen ) : ?>
				<h1 class="screen-reader-text"><?php echo esc_html( $screen->get_title() ); ?></h1>
				<p><?php echo wp_kses_post( $screen->get_description() ); ?></p>
				<?php $screen->render(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Saves the settings page.
	 *
	 * @since 1.0.0
	 */
	public function save() {

		if ( ! is_admin() || Helper::get_requested_value( 'page' ) !== self::PAGE_ID ) {
			return;
		}

		$screen = false;

		if ( ! empty( wp_kses_post( wp_unslash( $_POST['admin_nonce'] ) ) )
			&& wp_verify_nonce( wp_kses_post( wp_unslash( $_POST['admin_nonce'] ) ), 'admin_nonce' ) ) {
			$screen = $this->get_screen( Helper::get_posted_value( 'screen_id' ) );
		}

		if ( ! $screen ) {
			return;
		}
		if ( ! Helper::get_posted_value( 'save_' . $screen->get_id() . '_settings' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to save these settings.', 'automaticffl-for-wc' ) );
		}
		check_admin_referer( 'wc_ffl_admin_save_' . $screen->get_id() . '_settings' );
		try {
			$screen->save();
			// @TODO: Implement message handler
		} catch ( Exception $exception ) {
			// @TODO: Implement message handler and show error message
			echo esc_html( $exception->getMessage() );
			exit();
		}
	}

	/**
	 * Gets a settings screen object based on ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $screen_id desired screen ID.
	 * @return Abstract_Settings_Screen|null
	 */
	public function get_screen( $screen_id ) {
		$screens = $this->get_screens();
		return ! empty( $screens[ $screen_id ] ) && $screens[ $screen_id ] instanceof Abstract_Settings_Screen ? $screens[ $screen_id ] : null;
	}

	/**
	 * Gets the available screens.
	 *
	 * @since 1.0.0
	 *
	 * @return Abstract_Settings_Screen[]
	 */
	public function get_screens() {
		/**
		 * Filters the admin settings screens.
		 *
		 * @since 1.0.0
		 *
		 * @param array $screens available screen objects
		 */
		$screens = (array) apply_filters( 'wc_ffl_admin_settings_screens', $this->screens, $this );
		// ensure no bogus values are added via filter.
		$screens = array_filter(
			$screens,
			function( $value ) {
				return $value instanceof Abstract_Settings_Screen;
			}
		);
		return $screens;
	}

	/**
	 * Gets the tabs.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_tabs() {
		$tabs = array();
		foreach ( $this->get_screens() as $screen_id => $screen ) {
			$tabs[ $screen_id ] = $screen->get_label();
		}
		/**
		 * Filters the admin settings tabs.
		 *
		 * @since 1.0.0
		 *
		 * @param array $tabs tab data, as $id => $label
		 */
		return (array) apply_filters( 'wc_ffl_admin_settings_tabs', $tabs, $this );
	}
}
