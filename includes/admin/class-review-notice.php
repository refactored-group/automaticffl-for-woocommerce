<?php
/**
 * Admin review request notice.
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Review_Notice.
 *
 * Displays a dismissible admin notice asking store owners to leave a review
 * on WordPress.org after 14 days of plugin activation.
 *
 * @since 1.0.17
 */
class Review_Notice {

	/** @var string Option key for plugin activation timestamp. */
	const ACTIVATED_AT_OPTION = 'wc_ffl_activated_at';

	/** @var string User meta key for permanent dismissal. */
	const DISMISSED_META = 'wc_ffl_review_dismissed';

	/** @var string User meta key for snooze timestamp. */
	const SNOOZED_META = 'wc_ffl_review_snoozed_until';

	/** @var string Nonce action name. */
	const NONCE_ACTION = 'wc_ffl_review_notice';

	/** @var int Number of seconds before showing the notice (14 days). */
	const DISPLAY_DELAY = 1209600;

	/** @var int Number of seconds to snooze the notice (14 days). */
	const SNOOZE_DURATION = 1209600;

	/** @var string WordPress.org review URL. */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/automatic-ffl-for-wc/reviews/#new-post';

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.17
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_display_notice' ) );
	}

	/**
	 * Display the review notice if all conditions are met.
	 *
	 * @since 1.0.17
	 *
	 * @return void
	 */
	public static function maybe_display_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$activated_at = get_option( self::ACTIVATED_AT_OPTION );
		if ( false === $activated_at ) {
			// Backfill for existing installs that upgraded to this version.
			update_option( self::ACTIVATED_AT_OPTION, time() );
			return;
		}
		if ( ( time() - (int) $activated_at ) < self::DISPLAY_DELAY ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( get_user_meta( $user_id, self::DISMISSED_META, true ) ) {
			return;
		}

		$snoozed_until = get_user_meta( $user_id, self::SNOOZED_META, true );
		if ( $snoozed_until && time() < (int) $snoozed_until ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg( 'wc_ffl_review_action', 'dismiss' ),
			self::NONCE_ACTION,
			'wc_ffl_review_nonce'
		);

		$snooze_url = wp_nonce_url(
			add_query_arg( 'wc_ffl_review_action', 'snooze' ),
			self::NONCE_ACTION,
			'wc_ffl_review_nonce'
		);

		?>
		<div class="notice notice-info is-dismissible" id="wc-ffl-review-notice" style="border-left-color: #512a74;">
			<p>
				<?php esc_html_e( "Enjoying Automatic FFL? We'd really appreciate a quick review. It helps other firearms retailers find us. Thanks!", 'automaticffl-for-wc' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( self::REVIEW_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Leave a review', 'automaticffl-for-wc' ); ?></a> | <a href="<?php echo esc_url( $snooze_url ); ?>"><?php esc_html_e( 'Maybe later', 'automaticffl-for-wc' ); ?></a>
			</p>
		</div>
		<script>
		jQuery( function( $ ) {
			$( document ).on( 'click', '#wc-ffl-review-notice .notice-dismiss', function() {
				$.get( <?php echo wp_json_encode( esc_url_raw( $dismiss_url ) ); ?> );
			} );
		} );
		</script>
		<?php
	}

	/**
	 * Handle dismiss and snooze actions.
	 *
	 * @since 1.0.17
	 *
	 * @return void
	 */
	public static function handle_action() {
		if ( ! isset( $_GET['wc_ffl_review_action'] ) ) {
			return;
		}

		if ( ! isset( $_GET['wc_ffl_review_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wc_ffl_review_nonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$action  = sanitize_text_field( wp_unslash( $_GET['wc_ffl_review_action'] ) );
		$user_id = get_current_user_id();

		if ( 'dismiss' === $action ) {
			update_user_meta( $user_id, self::DISMISSED_META, 1 );
		} elseif ( 'snooze' === $action ) {
			update_user_meta( $user_id, self::SNOOZED_META, time() + self::SNOOZE_DURATION );
		}

		wp_safe_redirect( remove_query_arg( array( 'wc_ffl_review_action', 'wc_ffl_review_nonce' ) ) );
		exit;
	}
}
