<?php
/**
 * WooCommerce order certificate upload.
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Admin;

use RefactoredGroup\AutomaticFFL\Helper\Config;
use RefactoredGroup\AutomaticFFL\Helper\Credentials;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the missing-certificate order action and the narrow REST contract used
 * by the browser and the Automatic FFL callback worker.
 *
 * @since 1.0.24
 */
class Order_Certificate_Upload {

	const NOTE_ID_META = '_automaticffl_certificate_note_id';
	const MANAGED_META = '_automaticffl_certificate_upload_managed';

	/** Register hooks. */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'render_order_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/** Register local browser routes and the server-authenticated callback. */
	public function register_rest_routes() {
		register_rest_route(
			'automaticffl/v1',
			'/integration/verify',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'verify_integration' ),
				'permission_callback' => array( $this, 'can_manage_integration' ),
			)
		);

		register_rest_route(
			'automaticffl/v1',
			'/orders/(?P<id>\d+)/certificate-uploads',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_upload_sessions' ),
				'permission_callback' => array( $this, 'can_edit_order' ),
				'args'                => array(
					'id'    => array( 'sanitize_callback' => 'absint' ),
					'files' => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);

		register_rest_route(
			'automaticffl/v1',
			'/orders/(?P<id>\d+)/certificate-status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_certificate_status' ),
				'permission_callback' => array( $this, 'can_edit_order' ),
				'args'                => array(
					'id' => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			'automaticffl/v1',
			'/orders/(?P<id>\d+)/certificate-attachment',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'attach_certificate' ),
				'permission_callback' => array( $this, 'can_edit_order' ),
				'args'                => array(
					'id' => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	/** Return the canonical site identity after Application Password auth. */
	public function verify_integration() {
		return rest_ensure_response(
			array(
				'site_url' => untrailingslashit( get_site_url() ),
			)
		);
	}

	/**
	 * Proxy file descriptors to AutoFFL and return one-time GCS sessions.
	 * Credentials never leave this server-side method.
	 */
	public function create_upload_sessions( \WP_REST_Request $request ) {
		$order = wc_get_order( absint( $request['id'] ) );
		if ( ! $this->is_upload_eligible( $order ) ) {
			return new \WP_Error( 'automaticffl_not_eligible', __( 'This order is not eligible for a certificate upload.', 'automaticffl-for-wc' ), array( 'status' => 409 ) );
		}

		$files = $this->sanitize_file_descriptors( $request->get_param( 'files' ) );
		if ( is_wp_error( $files ) ) {
			return $files;
		}

		$credentials = Credentials::get_or_create_app_password();
		if ( is_wp_error( $credentials ) ) {
			return new \WP_Error( 'automaticffl_credentials', $credentials->get_error_message(), array( 'status' => 500 ) );
		}

		$authorization = base64_encode( $credentials['username'] . ':' . $credentials['password'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication.
		$response      = wp_remote_post(
			Config::get_order_certificate_uploads_url(),
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . $authorization,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'order_id'         => $order->get_id(),
						'expected_license' => $order->get_meta( '_ffl_license_field' ),
						'files'            => $files,
					)
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'automaticffl_backend_unavailable', __( 'Automatic FFL could not start the upload. Please try again.', 'automaticffl-for-wc' ), array( 'status' => 502 ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 201 !== $status || ! is_array( $body ) || ! isset( $body['uploads'] ) || ! is_array( $body['uploads'] ) ) {
			if ( 401 === $status ) {
				return new \WP_Error( 'automaticffl_upload_session_failed', __( 'Automatic FFL credentials need to be reconnected.', 'automaticffl-for-wc' ), array( 'status' => 401 ) );
			}
			if ( 422 === $status ) {
				return new \WP_Error( 'automaticffl_invalid_upload', __( 'One or more files are unsupported, empty, or too large.', 'automaticffl-for-wc' ), array( 'status' => 400 ) );
			}
			return new \WP_Error( 'automaticffl_upload_session_failed', __( 'Automatic FFL could not start the upload. Please try again.', 'automaticffl-for-wc' ), array( 'status' => 502 ) );
		}

		return rest_ensure_response( array( 'uploads' => $body['uploads'] ) );
	}

	/** Return the only status the MVP exposes: attached or not attached. */
	public function get_certificate_status( \WP_REST_Request $request ) {
		$order = wc_get_order( absint( $request['id'] ) );
		$uuid  = $order ? (string) $order->get_meta( '_ffl_uuid' ) : '';

		return rest_ensure_response(
			array(
				'attached'        => '' !== $uuid,
				'certificate_url' => Config::build_certificate_url( $uuid ),
			)
		);
	}

	/**
	 * Idempotently attach the canonical matching certificate to an order.
	 * This never changes status or creates a customer-facing note.
	 */
	public function attach_certificate( \WP_REST_Request $request ) {
		$order = wc_get_order( absint( $request['id'] ) );
		if ( ! $order ) {
			return new \WP_Error( 'automaticffl_order_not_found', __( 'Order not found.', 'automaticffl-for-wc' ), array( 'status' => 404 ) );
		}

		$store_hash       = sanitize_text_field( (string) $request->get_param( 'store_hash' ) );
		$expected_license = $this->normalize_license( $request->get_param( 'expected_ffl_license' ) );
		$actual_license   = $this->normalize_license( $order->get_meta( '_ffl_license_field' ) );
		$uuid             = sanitize_text_field( (string) $request->get_param( 'certificate_uuid' ) );
		$expiration       = sanitize_text_field( (string) $request->get_param( 'expiration_date' ) );

		$configured_store_hash = (string) Config::get_store_hash();
		if ( '' === $configured_store_hash || '' === $store_hash || ! hash_equals( $configured_store_hash, $store_hash ) ) {
			return new \WP_Error( 'automaticffl_store_mismatch', __( 'Store mismatch.', 'automaticffl-for-wc' ), array( 'status' => 403 ) );
		}
		if ( '' === $actual_license || ! hash_equals( $actual_license, $expected_license ) ) {
			return new \WP_Error( 'automaticffl_license_mismatch', __( 'The order FFL does not match this certificate.', 'automaticffl-for-wc' ), array( 'status' => 409 ) );
		}
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid ) ) {
			return new \WP_Error( 'automaticffl_invalid_uuid', __( 'Invalid certificate UUID.', 'automaticffl-for-wc' ), array( 'status' => 400 ) );
		}
		if ( ! $this->is_active_iso_date( $expiration ) ) {
			return new \WP_Error( 'automaticffl_invalid_expiration', __( 'Invalid or expired certificate.', 'automaticffl-for-wc' ), array( 'status' => 400 ) );
		}

		$current_uuid = (string) $order->get_meta( '_ffl_uuid' );
		$managed      = 'yes' === $order->get_meta( self::MANAGED_META );

		if ( hash_equals( $current_uuid, $uuid ) && ( ! $managed || $this->has_valid_private_note( $order ) ) ) {
			return $this->attachment_response( $uuid );
		}

		if ( '' !== $current_uuid && ! hash_equals( $current_uuid, $uuid ) && ! $managed ) {
			return new \WP_Error( 'automaticffl_already_attached', __( 'This order already has a certificate.', 'automaticffl-for-wc' ), array( 'status' => 409 ) );
		}

		if ( ! hash_equals( $current_uuid, $uuid ) || $managed ) {
			$order->update_meta_data( '_ffl_uuid', $uuid );
			$order->update_meta_data( '_ffl_expiration_date', $expiration );
			$order->update_meta_data( self::MANAGED_META, 'yes' );
			$order->save();

			$note_id = $this->upsert_private_note( $order, $actual_license, $expiration, $uuid );
			if ( is_wp_error( $note_id ) ) {
				return $note_id;
			}

			$order->update_meta_data( self::NOTE_ID_META, $note_id );
			$order->save();
		}

		return $this->attachment_response( $uuid );
	}

	/** Render the action only for orders that selected an FFL and have no UUID. */
	public function render_order_action( $order ) {
		if ( ! $this->is_upload_eligible( $order ) || ! $this->user_can_edit_order( $order->get_id() ) ) {
			return;
		}
		?>
		<p class="automaticffl-order-certificate-action">
			<button type="button" class="button" id="automaticffl-upload-certificate" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<?php esc_html_e( 'Upload FFL certificate(s)', 'automaticffl-for-wc' ); ?>
			</button>
		</p>
		<script type="text/template" id="tmpl-automaticffl-certificate-upload-modal">
			<div class="wc-backbone-modal automaticffl-certificate-modal">
				<div class="wc-backbone-modal-content">
					<section class="wc-backbone-modal-main" role="main" aria-labelledby="automaticffl-modal-title">
						<header class="wc-backbone-modal-header">
							<h1 id="automaticffl-modal-title"><?php esc_html_e( 'Upload FFL certificate(s)', 'automaticffl-for-wc' ); ?></h1>
							<button class="modal-close modal-close-link dashicons dashicons-no-alt" type="button">
								<span class="screen-reader-text"><?php esc_html_e( 'Close modal', 'automaticffl-for-wc' ); ?></span>
							</button>
						</header>
						<article>
							<label for="automaticffl-certificate-files"><?php esc_html_e( 'Certificate files', 'automaticffl-for-wc' ); ?></label>
							<input id="automaticffl-certificate-files" type="file" multiple accept=".pdf,.zip,image/*" />
							<p class="description"><?php esc_html_e( 'Select PDFs, images, or ZIP files.', 'automaticffl-for-wc' ); ?></p>
							<div id="automaticffl-upload-status" role="status" aria-live="polite"></div>
							<label class="screen-reader-text" for="automaticffl-upload-progress"><?php esc_html_e( 'Upload progress', 'automaticffl-for-wc' ); ?></label>
							<progress id="automaticffl-upload-progress" value="0" max="100" hidden></progress>
						</article>
						<footer>
							<div class="inner">
								<button type="button" class="button button-primary" id="automaticffl-start-upload" disabled><?php esc_html_e( 'Upload', 'automaticffl-for-wc' ); ?></button>
								<button type="button" class="button" id="automaticffl-retry-upload" hidden><?php esc_html_e( 'Retry failed', 'automaticffl-for-wc' ); ?></button>
								<button type="button" class="button" id="automaticffl-upload-more" hidden><?php esc_html_e( 'Upload more', 'automaticffl-for-wc' ); ?></button>
							</div>
						</footer>
					</section>
				</div>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</script>
		<?php
	}

	/** Enqueue only on the legacy or HPOS order editor. */
	public function enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen || ! $this->is_order_screen( $screen->id ) ) {
			return;
		}

		wp_enqueue_script(
			'automaticffl-order-certificate-upload',
			plugins_url( 'assets/js/order-certificate-upload.js', _AFFL_LOADER_ ),
			array( 'jquery', 'wp-util', 'backbone', 'wc-backbone-modal' ),
			AFFL_VERSION,
			true
		);
		wp_enqueue_style(
			'automaticffl-order-certificate-upload',
			plugins_url( 'assets/css/order-certificate-upload.css', _AFFL_LOADER_ ),
			array(),
			AFFL_VERSION
		);
		wp_localize_script(
			'automaticffl-order-certificate-upload',
			'automaticfflOrderCertificateUpload',
			array(
				'restBase'      => esc_url_raw( rest_url( 'automaticffl/v1' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'checkInterval' => 5000,
				'maxChecks'     => 18,
				'preparing'     => __( 'Preparing %d file(s)…', 'automaticffl-for-wc' ),
				'uploading'     => __( 'Uploading %d file(s)…', 'automaticffl-for-wc' ),
				'selected'      => __( '%d file(s) selected.', 'automaticffl-for-wc' ),
				'received'      => __( 'Your files were received. Automatic FFL usually validates and attaches a matching certificate within 30–60 seconds. You can leave this page; this order will update automatically.', 'automaticffl-for-wc' ),
				'timedOut'      => __( 'This certificate is still processing. You can close this page and refresh the order later.', 'automaticffl-for-wc' ),
				'uploadFailed'  => __( 'Some files could not be uploaded. Retry the failed files or upload more.', 'automaticffl-for-wc' ),
				'unauthorized'  => __( 'Your session expired. Refresh the order before checking again.', 'automaticffl-for-wc' ),
			)
		);
	}

	/** WordPress Application Password user must be able to administer WooCommerce. */
	public function can_manage_integration() {
		return current_user_can( 'manage_woocommerce' );
	}

	/** Require a real order and its edit capability for every order route. */
	public function can_edit_order( \WP_REST_Request $request ) {
		$order_id = absint( $request['id'] );
		if ( ! wc_get_order( $order_id ) || ! $this->user_can_edit_order( $order_id ) ) {
			return new \WP_Error( 'automaticffl_forbidden', __( 'You cannot edit this order.', 'automaticffl-for-wc' ), array( 'status' => 403 ) );
		}
		return true;
	}

	private function user_can_edit_order( $order_id ) {
		return current_user_can( 'edit_shop_order', $order_id ) || current_user_can( 'manage_woocommerce' );
	}

	private function is_upload_eligible( $order ) {
		return $order instanceof \WC_Order
			&& 'trash' !== $order->get_status()
			&& '' !== trim( (string) $order->get_meta( '_ffl_license_field' ) )
			&& '' === trim( (string) $order->get_meta( '_ffl_uuid' ) );
	}

	private function sanitize_file_descriptors( $files ) {
		if ( ! is_array( $files ) || empty( $files ) ) {
			return new \WP_Error( 'automaticffl_no_files', __( 'Choose at least one certificate file.', 'automaticffl-for-wc' ), array( 'status' => 400 ) );
		}

		$sanitized = array();
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) || empty( $file['name'] ) || empty( $file['size'] ) ) {
				return new \WP_Error( 'automaticffl_invalid_file', __( 'One of the selected files is invalid.', 'automaticffl-for-wc' ), array( 'status' => 400 ) );
			}
			$sanitized[] = array(
				'name' => sanitize_file_name( $file['name'] ),
				'size' => absint( $file['size'] ),
				'type' => sanitize_mime_type( isset( $file['type'] ) ? $file['type'] : '' ),
			);
		}

		return $sanitized;
	}

	private function normalize_license( $license ) {
		return preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $license ) );
	}

	private function is_active_iso_date( $date ) {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new \DateTimeZone( 'UTC' ) );
		$errors = \DateTimeImmutable::getLastErrors();
		$valid  = false !== $parsed && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) );
		return $valid && $parsed->format( 'Y-m-d' ) === $date && $date >= gmdate( 'Y-m-d' );
	}

	private function upsert_private_note( $order, $license, $expiration, $uuid ) {
		$note    = Config::build_enhanced_order_note( $license, $expiration, $uuid );
		$note_id = absint( $order->get_meta( self::NOTE_ID_META ) );

		if ( $note_id ) {
			$comment = get_comment( $note_id );
			if ( $comment && 'order_note' === $comment->comment_type && absint( $comment->comment_post_ID ) === $order->get_id() ) {
				$result = wp_update_comment(
					array(
						'comment_ID'      => $note_id,
						'comment_content' => wp_kses_post( $note ),
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					return new \WP_Error( 'automaticffl_note_update_failed', __( 'The certificate attached, but its private note could not be updated.', 'automaticffl-for-wc' ), array( 'status' => 500 ) );
				}
				return $note_id;
			}
		}

		$created_note_id = $order->add_order_note( $note, false, true );
		if ( ! $created_note_id ) {
			return new \WP_Error( 'automaticffl_note_create_failed', __( 'The certificate attached, but its private note could not be created.', 'automaticffl-for-wc' ), array( 'status' => 500 ) );
		}

		return absint( $created_note_id );
	}

	private function has_valid_private_note( $order ) {
		$note_id = absint( $order->get_meta( self::NOTE_ID_META ) );
		$comment = $note_id ? get_comment( $note_id ) : null;

		return $comment
			&& 'order_note' === $comment->comment_type
			&& absint( $comment->comment_post_ID ) === $order->get_id();
	}

	private function attachment_response( $uuid ) {
		return rest_ensure_response(
			array(
				'attached' => true,
				'uuid'     => $uuid,
			)
		);
	}

	private function is_order_screen( $screen_id ) {
		$order_screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$order_screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		return in_array( $screen_id, array_unique( $order_screens ), true );
	}
}
