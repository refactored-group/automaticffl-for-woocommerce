<?php
/**
 * US States Helper Class
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Class US_States
 *
 * Provides a single source of truth for supported ammunition destinations.
 *
 * @since 1.0.14
 */
class US_States {

	/**
	 * US States list with code => name mapping.
	 *
	 * @var array
	 */
	private static $states = array(
		'AL' => 'Alabama',
		'AK' => 'Alaska',
		'AZ' => 'Arizona',
		'AR' => 'Arkansas',
		'CA' => 'California',
		'CO' => 'Colorado',
		'CT' => 'Connecticut',
		'DE' => 'Delaware',
		'DC' => 'District of Columbia',
		'FL' => 'Florida',
		'GA' => 'Georgia',
		'HI' => 'Hawaii',
		'ID' => 'Idaho',
		'IL' => 'Illinois',
		'IN' => 'Indiana',
		'IA' => 'Iowa',
		'KS' => 'Kansas',
		'KY' => 'Kentucky',
		'LA' => 'Louisiana',
		'ME' => 'Maine',
		'MD' => 'Maryland',
		'MA' => 'Massachusetts',
		'MI' => 'Michigan',
		'MN' => 'Minnesota',
		'MS' => 'Mississippi',
		'MO' => 'Missouri',
		'MT' => 'Montana',
		'NE' => 'Nebraska',
		'NV' => 'Nevada',
		'NH' => 'New Hampshire',
		'NJ' => 'New Jersey',
		'NM' => 'New Mexico',
		'NY' => 'New York',
		'NC' => 'North Carolina',
		'ND' => 'North Dakota',
		'OH' => 'Ohio',
		'OK' => 'Oklahoma',
		'OR' => 'Oregon',
		'PA' => 'Pennsylvania',
		'RI' => 'Rhode Island',
		'SC' => 'South Carolina',
		'SD' => 'South Dakota',
		'TN' => 'Tennessee',
		'TX' => 'Texas',
		'UT' => 'Utah',
		'VT' => 'Vermont',
		'VA' => 'Virginia',
		'WA' => 'Washington',
		'WV' => 'West Virginia',
		'WI' => 'Wisconsin',
		'WY' => 'Wyoming',
	);

	/**
	 * U.S. territories represented by WooCommerce as countries.
	 *
	 * These display names intentionally match the Automatic FFL backend.
	 * WooCommerce may use a shorter label for the Virgin Islands depending on
	 * version/locale, but merchant rules need one stable, unabbreviated name.
	 *
	 * @var array
	 */
	private static $territories = array(
		'AS' => 'American Samoa',
		'GU' => 'Guam',
		'MP' => 'Northern Mariana Islands',
		'PR' => 'Puerto Rico',
		'VI' => 'United States Virgin Islands',
	);

	/**
	 * Get supported states and territories.
	 *
	 * @since 1.0.14
	 *
	 * @return array Array of state codes => state names.
	 */
	public static function get_all(): array {
		return array_merge( self::get_states(), self::$territories );
	}

	/**
	 * Get supported U.S. states using WooCommerce's native labels when loaded.
	 *
	 * The fallback keeps standalone tests and early bootstrap calls deterministic.
	 * Only the explicitly supported 50 states plus D.C. are returned; WooCommerce
	 * also exposes military pseudo-regions that are outside this feature's scope.
	 *
	 * @since 1.0.33
	 *
	 * @return array Array of state codes => state names.
	 */
	public static function get_states(): array {
		$states = self::$states;

		if ( function_exists( 'WC' ) && WC() && isset( WC()->countries ) && is_callable( array( WC()->countries, 'get_states' ) ) ) {
			$native_states = WC()->countries->get_states( 'US' );
			if ( is_array( $native_states ) ) {
				foreach ( array_keys( $states ) as $code ) {
					if ( isset( $native_states[ $code ] ) && is_string( $native_states[ $code ] ) ) {
						$states[ $code ] = $native_states[ $code ];
					}
				}
			}
		}

		return $states;
	}

	/**
	 * Get supported U.S. territories.
	 *
	 * @since 1.0.33
	 *
	 * @return array Array of territory codes => territory names.
	 */
	public static function get_territories(): array {
		return self::$territories;
	}

	/**
	 * Get state name by code.
	 *
	 * @since 1.0.14
	 *
	 * @param string $code Two-letter state code (e.g., 'CA').
	 * @return string State name, or the code itself if not found.
	 */
	public static function get_name( string $code ): string {
		$code = strtoupper( trim( $code ) );
		$all  = self::get_all();
		return $all[ $code ] ?? $code;
	}

	/**
	 * Check if a state code is valid.
	 *
	 * @since 1.0.14
	 *
	 * @param string $code Two-letter state code to validate.
	 * @return bool True if valid state code, false otherwise.
	 */
	public static function is_valid( string $code ): bool {
		return isset( self::get_all()[ strtoupper( trim( $code ) ) ] );
	}

	/**
	 * Resolve a WooCommerce country/state pair to a backend jurisdiction code.
	 *
	 * WooCommerce stores the five supported territories as countries, while D.C.
	 * is a normal US state. Foreign country/state collisions are deliberately not
	 * accepted.
	 *
	 * @since 1.0.33
	 *
	 * @param string $country WooCommerce country code.
	 * @param string $state   WooCommerce state/region code.
	 * @return string Canonical jurisdiction code, or an empty string.
	 */
	public static function resolve_destination( string $country, string $state ): string {
		$country = strtoupper( trim( $country ) );
		$state   = strtoupper( trim( $state ) );

		if ( 'US' === $country && isset( self::$states[ $state ] ) ) {
			return $state;
		}

		if ( isset( self::$territories[ $country ] ) ) {
			return $country;
		}

		return '';
	}

	/**
	 * Convert a dealer destination to WooCommerce's native address shape.
	 *
	 * @since 1.0.33
	 *
	 * @param string $country Dealer country code.
	 * @param string $state   Dealer state/region code.
	 * @return array{country:string,state:string} WooCommerce country/state pair.
	 */
	public static function to_woocommerce_destination( string $country, string $state ): array {
		$country = strtoupper( trim( $country ) ) ?: 'US';
		$state   = strtoupper( trim( $state ) );

		if ( 'US' === $country && isset( self::$territories[ $state ] ) ) {
			return array( 'country' => $state, 'state' => '' );
		}

		if ( isset( self::$territories[ $country ] ) ) {
			return array( 'country' => $country, 'state' => '' );
		}

		return array( 'country' => $country, 'state' => $state );
	}
}
