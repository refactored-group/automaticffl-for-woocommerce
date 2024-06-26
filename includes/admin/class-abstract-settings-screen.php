<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Abstract_Settings_Screen.
 */
abstract class Abstract_Settings_Screen {
	/**
	 * Screen ID
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Screen Label
	 *
	 * @var string
	 */
	protected $label;

	/**
	 * Screen Title
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Screen Description
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * Renders the screen.
	 *
	 * @since 1.0.0
	 */
	public function render() {

		/**
		 * Filters the screen settings.
		 *
		 * @since 1.0.0
		 *
		 * @param array $settings settings
		 */
		$settings = (array) apply_filters( 'wc_ffl_admin_' . $this->get_id() . '_settings', $this->get_settings(), $this );

		if ( empty( $settings ) ) {
			return;
		}
		?>
		<form class="wc-ffl-settings" method="post" id="mainform" action="" enctype="multipart/form-data">
			<?php woocommerce_admin_fields( $settings ); ?>
			<input type="hidden" name="admin_nonce" value="<?php echo esc_attr( wp_create_nonce( 'admin_nonce' ) ); ?>" />
			<input type="hidden" name="screen_id" value="<?php echo esc_attr( $this->get_id() ); ?>">
			<?php wp_nonce_field( 'wc_ffl_admin_save_' . $this->get_id() . '_settings' ); ?>
			<?php submit_button( __( 'Save changes', 'automaticffl-for-wc' ), 'primary', 'save_' . $this->get_id() . '_settings' ); ?>
		</form>
		<?php
	}

	/**
	 * Saves the settings.
	 *
	 * @since 1.0.0
	 */
	public function save() {
		woocommerce_update_options( $this->get_settings() );
	}

	/**
	 * Gets the settings.
	 *
	 * Should return a multi-dimensional array of settings in the format expected by \WC_Admin_Settings
	 *
	 * @return array
	 */
	abstract public function get_settings();


	/**
	 * Returns the screen ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}


	/**
	 * Returns the screen label.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label() {
		/**
		 * Filters the screen label.
		 *
		 * @since 1.0.0
		 *
		 * @param string $label screen label, for display
		 */
		return (string) apply_filters( 'wc_ffl_admin_settings_' . $this->get_id() . '_screen_label', $this->label, $this );
	}


	/**
	 * Returns the screen title.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_title() {
		/**
		 * Filters the screen title.
		 *
		 * @since 1.0.0
		 *
		 * @param string $title screen title, for display
		 */
		return (string) apply_filters( 'wc_ffl_admin_settings_' . $this->get_id() . '_screen_title', $this->title, $this );
	}


	/**
	 * Returns the screen description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description() {
		/**
		 * Filters the screen description.
		 *
		 * @since 1.0.0
		 *
		 * @param string $description screen description, for display
		 */
		return (string) apply_filters( 'wc_ffl_admin_settings_' . $this->get_id() . '_screen_description', $this->description, $this );
	}
}
