<?php
/**
 * Sanitized, context-specific connection diagnostics.
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Helper;

defined( 'ABSPATH' ) || exit;

/** Stores independent checkout and additional-feature connection evidence. */
class Connection_Status {

	const OPTION_KEY = 'automaticffl_connection_status';
	const STALE_AFTER = 86400;

	/** @var array */
	private static $checkout_states = array( 'connected', 'legacy_backend', 'needs_attention' );

	/** @var array */
	private static $registration_states = array( 'registered', 'needs_attention' );

	/** @var array */
	private static $checkout_codes = array(
		'missing_store_hash',
		'route_unavailable',
		'eligibility_failure',
		'invalid_response',
		'response_too_large',
		'service_error',
		'transport_error',
		'timeout',
	);

	/** @var array */
	private static $registration_codes = array(
		'missing_store_hash',
		'missing_credentials',
		'application_passwords_unavailable',
		'invalid_credentials',
		'site_url_mismatch',
		'unsupported_platform',
		'verification_transport_failure',
		'verification_status_failure',
		'registration_failed',
	);

	public static function current_context() {
		return hash(
			'sha256',
			wp_json_encode(
				array(
					'api_base'   => Config::get_ffl_api_url(),
					'store_hash' => Config::get_store_hash(),
					'site_url'   => get_site_url(),
					'version'    => defined( 'AFFL_VERSION' ) ? AFFL_VERSION : '',
				)
			)
		);
	}

	public static function save_checkout( $state, $code = null, $http_status = null, $request_id = null, $checked_at = null ) {
		return self::save_record( 'checkout', $state, $code, $http_status, $request_id, $checked_at );
	}

	public static function save_registration( $state, $code = null, $http_status = null, $request_id = null, $checked_at = null ) {
		return self::save_record( 'registration', $state, $code, $http_status, $request_id, $checked_at );
	}

	public static function get_checkout( $now = null ) {
		return self::get_record( 'checkout', $now );
	}

	public static function get_registration( $now = null ) {
		return self::get_record( 'registration', $now );
	}

	public static function invalidate() {
		delete_option( self::OPTION_KEY );
	}

	private static function save_record( $kind, $state, $code, $http_status, $request_id, $checked_at ) {
		$states = 'checkout' === $kind ? self::$checkout_states : self::$registration_states;
		if ( ! in_array( $state, $states, true ) ) {
			$state = 'needs_attention';
		}

		$record = array(
			'context'     => self::current_context(),
			'checked_at'  => is_int( $checked_at ) && $checked_at > 0 ? $checked_at : time(),
			'state'       => $state,
			'code'        => self::sanitize_code( $kind, $code ),
			'http_status' => is_int( $http_status ) && $http_status >= 100 && $http_status <= 599 ? $http_status : null,
			'request_id'  => is_string( $request_id ) && strlen( $request_id ) <= 128 &&
				1 === preg_match( '/^[A-Za-z0-9_-]+$/', $request_id ) ? $request_id : null,
		);

		$status          = get_option( self::OPTION_KEY, array() );
		$status          = is_array( $status ) ? $status : array();
		$status[ $kind ] = $record;
		update_option( self::OPTION_KEY, $status, false );
		return $record;
	}

	private static function get_record( $kind, $now ) {
		$status = get_option( self::OPTION_KEY, array() );
		$record = is_array( $status ) && isset( $status[ $kind ] ) && is_array( $status[ $kind ] ) ? $status[ $kind ] : null;

		if ( ! $record || ! isset( $record['context'], $record['checked_at'], $record['state'] ) ||
			! hash_equals( self::current_context(), (string) $record['context'] ) ) {
			return self::not_checked();
		}

		$states = 'checkout' === $kind ? self::$checkout_states : self::$registration_states;
		if ( ! in_array( $record['state'], $states, true ) ) {
			return self::not_checked();
		}

		$now     = is_int( $now ) ? $now : time();
		$stale   = ( $now - (int) $record['checked_at'] ) > self::STALE_AFTER;
		$record += array( 'code' => null, 'http_status' => null, 'request_id' => null );
		$record['stale']   = $stale;
		$record['current'] = ! $stale;
		return $record;
	}

	private static function not_checked() {
		return array(
			'state'       => 'not_checked',
			'code'        => null,
			'http_status' => null,
			'request_id'  => null,
			'checked_at'  => null,
			'stale'       => false,
			'current'     => false,
		);
	}

	private static function sanitize_code( $kind, $code ) {
		if ( null === $code || '' === $code ) {
			return null;
		}

		$allowed = 'checkout' === $kind ? self::$checkout_codes : self::$registration_codes;
		if ( is_string( $code ) && strlen( $code ) <= 64 && in_array( $code, $allowed, true ) ) {
			return $code;
		}

		return 'checkout' === $kind ? 'service_error' : 'registration_failed';
	}
}
