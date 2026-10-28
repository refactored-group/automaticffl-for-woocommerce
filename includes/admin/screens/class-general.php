<?php
/**
 * FFL for WooCommerce Plugin
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Admin\Screens;

defined( 'ABSPATH' ) || exit;

use RefactoredGroup\AutomaticFFL\Admin\Abstract_Settings_Screen;
use RefactoredGroup\AutomaticFFL\Admin\Settings;
use RefactoredGroup\AutomaticFFL\Helper\Config;
use RefactoredGroup\AutomaticFFL\Helper\Connection_Status;

/**
 * General Screen Class
 */
class General extends Abstract_Settings_Screen {
	// @var string Screen ID.
	const ID = 'general';

	/**
	 * General screen constructor. This screen will be displayed in a tab.
	 */
	public function __construct() {

		$this->id    = self::ID;
		$this->label = __( 'General', 'automaticffl-for-wc' );
		$this->title = __( 'General', 'automaticffl-for-wc' );
	}

	/**
	 * Save settings and register credentials with backend.
	 *
	 * @since 1.0.14
	 */
	public function save() {
		parent::save();
		Connection_Status::invalidate();

		try {
			Settings::run_checkout_probe();
		} catch ( \Throwable $error ) {
			Connection_Status::save_checkout( 'needs_attention', 'service_error' );
		}

		try {
			Config::register_with_backend();
		} catch ( \Throwable $error ) {
			Connection_Status::save_registration( 'needs_attention', 'registration_failed' );
		}
	}

	/** Render settings first, then a separate non-nested connection-check form. */
	public function render() {
		parent::render();
		$this->render_connection_status();
	}

	/** Render bounded, read-only connection evidence and an explicit check action. */
	public function render_connection_status() {
		$checkout     = Connection_Status::get_checkout();
		$registration = Connection_Status::get_registration();
		$store_hash   = Config::get_store_hash();
		$maps_key     = Config::get_google_maps_api_key();
		?>
		<section class="automaticffl-connection-status" aria-labelledby="automaticffl-connection-heading">
			<h2 id="automaticffl-connection-heading"><?php echo esc_html__( 'Connection status', 'automaticffl-for-wc' ); ?></h2>
			<?php if ( empty( $store_hash ) ) : ?>
				<p class="notice notice-error inline"><?php echo esc_html__( 'Store Hash is required before checkout connectivity can be checked.', 'automaticffl-for-wc' ); ?></p>
			<?php endif; ?>
			<?php if ( empty( $maps_key ) ) : ?>
				<p class="notice notice-warning inline"><?php echo esc_html__( 'A Google Maps API key is still required for the dealer map; the checkout API check does not test Maps configuration.', 'automaticffl-for-wc' ); ?></p>
			<?php endif; ?>

			<h3><?php echo esc_html__( 'Checkout API', 'automaticffl-for-wc' ); ?></h3>
			<p><?php echo esc_html( $this->checkout_status_text( $checkout ) ); ?></p>
			<?php $this->render_checked_time( $checkout ); ?>
			<p><?php echo esc_html__( 'This check covers service connectivity only. It does not test Google Maps restrictions, live cart extraction, or the complete shopper experience.', 'automaticffl-for-wc' ); ?></p>

			<h3><?php echo esc_html__( 'Additional-feature credentials', 'automaticffl-for-wc' ); ?></h3>
			<p><?php echo esc_html( $this->registration_status_text( $registration ) ); ?></p>
			<?php $this->render_checked_time( $registration ); ?>
			<p><?php echo esc_html__( 'These credentials are used for category browsing, order-level certificate uploads and automatic attachment callbacks, and order reporting. Checkout connectivity and normal dealer certificate links are independent.', 'automaticffl-for-wc' ); ?></p>
		</section>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="automaticffl-connection-check">
			<input type="hidden" name="action" value="automaticffl_check_checkout_connection" />
			<?php wp_nonce_field( 'automaticffl_check_checkout_connection' ); ?>
			<?php submit_button( __( 'Check checkout connection', 'automaticffl-for-wc' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	private function checkout_status_text( array $status ) {
		if ( 'not_checked' === $status['state'] ) {
			return __( 'Not checked. Run the connection check for the saved settings.', 'automaticffl-for-wc' );
		}
		if ( 'connected' === $status['state'] ) {
			return $status['stale'] ? __( 'Previously connected. Run the check again for current evidence.', 'automaticffl-for-wc' ) : __( 'Connected to the checkout classification service.', 'automaticffl-for-wc' );
		}
		if ( 'legacy_backend' === $status['state'] ) {
			return __( 'The checkout service is in legacy mode. A server upgrade is required for checkout without WordPress Application Passwords; an existing credential-based checkout may still work.', 'automaticffl-for-wc' );
		}

		$messages = array(
			'missing_store_hash'    => __( 'Add and save the Store Hash, then check again.', 'automaticffl-for-wc' ),
			'eligibility_failure'    => __( 'The saved store is not eligible for checkout classification. Verify the store hash and subscription.', 'automaticffl-for-wc' ),
			'invalid_response'       => __( 'The checkout service returned an invalid response. Try again or contact support.', 'automaticffl-for-wc' ),
			'response_too_large'     => __( 'The checkout service response exceeded the safe limit.', 'automaticffl-for-wc' ),
			'timeout'                => __( 'The checkout service timed out. Try again.', 'automaticffl-for-wc' ),
			'transport_error'        => __( 'The site could not reach the checkout service. Check outbound HTTPS and try again.', 'automaticffl-for-wc' ),
			'service_error'          => __( 'The checkout service needs attention. Try again or contact support.', 'automaticffl-for-wc' ),
		);
		return isset( $messages[ $status['code'] ] ) ? $messages[ $status['code'] ] : $messages['service_error'];
	}

	private function registration_status_text( array $status ) {
		if ( 'not_checked' === $status['state'] ) {
			return __( 'Not checked. Save settings to attempt credential registration.', 'automaticffl-for-wc' );
		}
		if ( 'registered' === $status['state'] ) {
			return $status['stale'] ? __( 'Credentials were registered previously; save settings to verify them again.', 'automaticffl-for-wc' ) : __( 'Credential registration succeeded. This does not individually test every additional operation.', 'automaticffl-for-wc' );
		}
		return __( 'Credential registration needs attention. Checkout can still use the direct service when its separate status is connected.', 'automaticffl-for-wc' );
	}

	private function render_checked_time( array $status ) {
		if ( empty( $status['checked_at'] ) ) {
			return;
		}
		?>
		<p class="description"><?php echo esc_html( sprintf( __( 'Last checked: %s', 'automaticffl-for-wc' ), wp_date( 'M j, Y g:i a', $status['checked_at'] ) ) ); ?></p>
		<?php
	}

	/**
	 * Get Settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings() {

		return array(
			array(
				'type'  => 'title',
				'title' => __( 'Settings' ),
			),
			array(
				'id'      => Config::FFL_SANDBOX_MODE_CONFIG,
				'title'   => __( 'Sandbox Mode', 'automaticffl-for-wc' ),
				'type'    => 'select',
				'desc'    => __( 'Enable the sandbox mode if you are testing or debugging this extension.', 'automaticffl-for-wc' ),
				'default' => Config::SETTING_NO,
				'options' => array(
					Config::SETTING_YES => __( 'Yes', 'automaticffl-for-wc' ),
					Config::SETTING_NO  => __( 'No', 'automaticffl-for-wc' ),
				),
			),
			array(
				'id'    => Config::FFL_STORE_HASH_CONFIG,
				'title' => __( 'Store Hash', 'automaticffl-for-wc' ),
				'type'  => 'text',
				'label' => 'Label',
				'desc'  => __( "Your subscription's store hash. If you still do not have one, <a href='https://www.automaticffl.com/' target='_blank'>click here</a>.", 'automaticffl-for-wc' ),
			),
			array( 'type' => 'sectionend' ),
			array(
				'type'  => 'title',
				'title' => __( 'Google Maps', 'automaticffl-for-wc' ),
				'desc'  => __( 'Google Maps is used to create the map experience during the checkout. It is mandatory to have an API Key in order for this plugin to work.</br>If you do not have one yet, <a href="https://console.cloud.google.com/projectselector2/google/maps-apis/credentials" target="_blank">click here</a>.', 'automaticffl-for-wc' ),
			),
			array(
				'id'    => Config::FFL_GOOGLE_MAPS_API_KEY_CONFIG,
				'title' => __( 'API Key', 'automaticffl-for-wc' ),
				'type'  => 'text',
				'label' => 'Label',
				'css'   => 'width: 250px;',
				'desc'  => __( 'Your Google Maps API Key.', 'automaticffl-for-wc' ),
			),
			array( 'type' => 'sectionend' ),
		);
	}
}
