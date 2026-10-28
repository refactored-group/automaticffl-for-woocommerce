<?php
/**
 * Minimal WordPress/WooCommerce test doubles for the standalone PHP suite.
 *
 * @package AutomaticFFL
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "The Automatic FFL PHP harness is CLI-only.\n" );
	exit( 2 );
}

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/wordpress/' );
defined( 'AFFL_VERSION' ) || define( 'AFFL_VERSION', 'test' );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['affl_tests']          = array();
$GLOBALS['affl_options']        = array();
$GLOBALS['affl_transients']     = array();
$GLOBALS['affl_products']       = array();
$GLOBALS['affl_http_queue']     = array();
$GLOBALS['affl_http_calls']     = array();
$GLOBALS['affl_actions']        = array();
$GLOBALS['affl_filters']        = array();
$GLOBALS['affl_current_time']   = 1700000000;
$GLOBALS['affl_can_manage_wc']  = true;
$GLOBALS['affl_nonce_is_valid'] = true;
$GLOBALS['affl_redirect']       = null;
$GLOBALS['affl_site_url']       = 'http://localhost:8080';

/** Register a test case. */
function affl_test( $name, callable $test ) {
	$GLOBALS['affl_tests'][] = array( $name, $test );
}

/** Fail a test with a useful message. */
function affl_fail( $message ) {
	throw new RuntimeException( $message );
}

function affl_assert_true( $value, $message = 'Expected true.' ) {
	if ( true !== $value ) {
		affl_fail( $message );
	}
}

function affl_assert_false( $value, $message = 'Expected false.' ) {
	if ( false !== $value ) {
		affl_fail( $message );
	}
}

function affl_assert_same( $expected, $actual, $message = '' ) {
	if ( $expected !== $actual ) {
		$detail = 'Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . '.';
		affl_fail( $message ? $message . ' ' . $detail : $detail );
	}
}

function affl_assert_contains( $needle, $haystack, $message = '' ) {
	if ( false === strpos( (string) $haystack, (string) $needle ) ) {
		affl_fail( $message ? $message : 'Expected output to contain ' . var_export( $needle, true ) . '.' );
	}
}

/** Reset mutable doubles between tests. */
function affl_reset_test_state() {
	$GLOBALS['affl_options']        = array();
	$GLOBALS['affl_transients']     = array();
	$GLOBALS['affl_products']       = array();
	$GLOBALS['affl_http_queue']     = array();
	$GLOBALS['affl_http_calls']     = array();
	$GLOBALS['affl_actions']        = array();
	$GLOBALS['affl_filters']        = array();
	$GLOBALS['affl_current_time']   = 1700000000;
	$GLOBALS['affl_can_manage_wc']  = true;
	$GLOBALS['affl_nonce_is_valid'] = true;
	$GLOBALS['affl_redirect']       = null;
	$GLOBALS['affl_site_url']       = 'http://localhost:8080';
}

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

class AFFL_Test_Product {
	private $id;
	private $parent_id;
	private $categories;
	private $meta;

	public function __construct( $id, $parent_id = 0, array $categories = array(), array $meta = array() ) {
		$this->id         = $id;
		$this->parent_id  = $parent_id;
		$this->categories = $categories;
		$this->meta       = $meta;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_parent_id() {
		return $this->parent_id;
	}

	public function get_category_ids() {
		return $this->categories;
	}

	public function get_meta( $key, $single = true, $context = 'view' ) {
		unset( $single, $context );
		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}
}

function wc_get_product( $id ) {
	return isset( $GLOBALS['affl_products'][ $id ] ) ? $GLOBALS['affl_products'][ $id ] : false;
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['affl_options'] ) ? $GLOBALS['affl_options'][ $name ] : $default;
}

function add_option( $name, $value = '', $deprecated = '', $autoload = 'yes' ) {
	unset( $deprecated, $autoload );
	if ( array_key_exists( $name, $GLOBALS['affl_options'] ) ) {
		return false;
	}
	$GLOBALS['affl_options'][ $name ] = $value;
	return true;
}

function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	$GLOBALS['affl_options'][ $name ] = $value;
	return true;
}

function delete_option( $name ) {
	unset( $GLOBALS['affl_options'][ $name ] );
	return true;
}

function get_transient( $name ) {
	return array_key_exists( $name, $GLOBALS['affl_transients'] ) ? $GLOBALS['affl_transients'][ $name ] : false;
}

function set_transient( $name, $value, $expiration = 0 ) {
	unset( $expiration );
	$GLOBALS['affl_transients'][ $name ] = $value;
	return true;
}

function delete_transient( $name ) {
	unset( $GLOBALS['affl_transients'][ $name ] );
	return true;
}

function wp_remote_get( $url, $args = array() ) {
	return affl_http_request( 'GET', $url, $args );
}

function wp_remote_post( $url, $args = array() ) {
	return affl_http_request( 'POST', $url, $args );
}

function affl_http_request( $method, $url, array $args ) {
	$GLOBALS['affl_http_calls'][] = compact( 'method', 'url', 'args' );
	if ( empty( $GLOBALS['affl_http_queue'] ) ) {
		return new WP_Error( 'unexpected_http', 'No queued HTTP response.' );
	}
	$response = array_shift( $GLOBALS['affl_http_queue'] );
	return is_callable( $response ) ? $response( $method, $url, $args ) : $response;
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
}

function wp_remote_retrieve_header( $response, $name ) {
	$headers = isset( $response['headers'] ) ? $response['headers'] : array();
	foreach ( $headers as $key => $value ) {
		if ( strtolower( $key ) === strtolower( $name ) ) {
			return $value;
		}
	}
	return '';
}

function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}

function get_site_url() {
	return $GLOBALS['affl_site_url'];
}

function current_time( $type, $gmt = 0 ) {
	unset( $gmt );
	return 'timestamp' === $type ? $GLOBALS['affl_current_time'] : gmdate( 'Y-m-d H:i:s', $GLOBALS['affl_current_time'] );
}

function wp_date( $format, $timestamp = null ) {
	return gmdate( $format, null === $timestamp ? $GLOBALS['affl_current_time'] : $timestamp );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['affl_actions'][ $hook ][] = compact( 'callback', 'priority', 'accepted_args' );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['affl_filters'][ $hook ][] = compact( 'callback', 'priority', 'accepted_args' );
}

function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}

function current_user_can( $capability ) {
	unset( $capability );
	return $GLOBALS['affl_can_manage_wc'];
}

function check_admin_referer( $action ) {
	unset( $action );
	if ( ! $GLOBALS['affl_nonce_is_valid'] ) {
		throw new RuntimeException( 'invalid_nonce' );
	}
	return true;
}

function wp_verify_nonce( $nonce, $action ) {
	unset( $nonce, $action );
	return $GLOBALS['affl_nonce_is_valid'];
}

function wp_create_nonce( $action ) {
	return 'nonce-' . $action;
}

function wp_nonce_field( $action ) {
	echo '<input type="hidden" name="_wpnonce" value="' . htmlspecialchars( $action, ENT_QUOTES, 'UTF-8' ) . '">';
}

function wp_safe_redirect( $location ) {
	$GLOBALS['affl_redirect'] = $location;
	return true;
}

function admin_url( $path = '' ) {
	return 'http://localhost:8080/wp-admin/' . ltrim( $path, '/' );
}

function add_query_arg( $key, $value = null, $url = null ) {
	if ( is_array( $key ) ) {
		$query = http_build_query( $key );
		return rtrim( (string) $value, '?' ) . '?' . $query;
	}
	$separator = false === strpos( (string) $url, '?' ) ? '?' : '&';
	return $url . $separator . rawurlencode( $key ) . '=' . rawurlencode( $value );
}

function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $value ) {
	return esc_attr( $value );
}

function sanitize_text_field( $value ) {
	return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $value ) ) );
}

function __( $text, $domain = null ) {
	unset( $domain );
	return $text;
}

function esc_html__( $text, $domain = null ) {
	return esc_html( __( $text, $domain ) );
}

function wp_kses_post( $value ) {
	return (string) $value;
}

function wp_kses( $value, $allowed_html = array() ) {
	unset( $allowed_html );
	return (string) $value;
}

function wp_unslash( $value ) {
	return $value;
}

function wp_die( $message ) {
	throw new RuntimeException( (string) $message );
}

function is_admin() {
	return true;
}

function submit_button( $text, $type = 'primary', $name = 'submit', $wrap = true ) {
	unset( $wrap );
	echo '<button type="submit" class="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>';
}

function woocommerce_admin_fields( $settings ) {
	unset( $settings );
	echo '<div class="woocommerce-fields"></div>';
}

function woocommerce_update_options( $settings ) {
	unset( $settings );
}

function add_submenu_page() {
	return true;
}

function get_current_user_id() {
	return 1;
}

function user_can( $user_id, $capability ) {
	unset( $user_id, $capability );
	return $GLOBALS['affl_can_manage_wc'];
}

function _e( $text, $domain = null ) {
	echo esc_html( __( $text, $domain ) );
}

function selected( $value, $current, $echo = true ) {
	$result = (string) $value === (string) $current ? ' selected="selected"' : '';
	if ( $echo ) {
		echo $result;
	}
	return $result;
}
