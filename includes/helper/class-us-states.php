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
 * Provides a single source of truth for US state codes and names.
 *
 * @since 1.0.15
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
	 * Get all US states.
	 *
	 * @since 1.0.15
	 *
	 * @return array Array of state codes => state names.
	 */
	public static function get_all(): array {
		return self::$states;
	}

	/**
	 * Get state name by code.
	 *
	 * @since 1.0.15
	 *
	 * @param string $code Two-letter state code (e.g., 'CA').
	 * @return string State name, or the code itself if not found.
	 */
	public static function get_name( string $code ): string {
		return self::$states[ $code ] ?? $code;
	}

	/**
	 * Check if a state code is valid.
	 *
	 * @since 1.0.15
	 *
	 * @param string $code Two-letter state code to validate.
	 * @return bool True if valid state code, false otherwise.
	 */
	public static function is_valid( string $code ): bool {
		return isset( self::$states[ $code ] );
	}
}
