<?php
/** WooCommerce country/state normalization for ammunition rules. */

use RefactoredGroup\AutomaticFFL\Helper\US_States;

require_once dirname( __DIR__, 2 ) . '/includes/helper/class-us-states.php';

affl_test( 'supported destination labels are full names', function () {
	$GLOBALS['affl_wc'] = null;
	$expected = array(
		'DC' => 'District of Columbia',
		'AS' => 'American Samoa',
		'GU' => 'Guam',
		'MP' => 'Northern Mariana Islands',
		'PR' => 'Puerto Rico',
		'VI' => 'United States Virgin Islands',
	);

	foreach ( $expected as $code => $name ) {
		affl_assert_same( $name, US_States::get_name( $code ) );
		affl_assert_true( US_States::is_valid( $code ) );
	}
} );

affl_test( 'D.C. resolves as a state and territories resolve as countries', function () {
	$GLOBALS['affl_wc'] = null;
	affl_assert_same( 'DC', US_States::resolve_destination( 'US', 'DC' ) );
	affl_assert_same( array( 'country' => 'US', 'state' => 'DC' ), US_States::to_woocommerce_destination( 'US', 'DC' ) );

	foreach ( array( 'AS', 'GU', 'MP', 'PR', 'VI' ) as $code ) {
		affl_assert_same( $code, US_States::resolve_destination( $code, '' ) );
		affl_assert_same( array( 'country' => $code, 'state' => '' ), US_States::to_woocommerce_destination( 'US', $code ) );
	}
} );

affl_test( 'foreign state-code collisions are not treated as US destinations', function () {
	$GLOBALS['affl_wc'] = null;
	affl_assert_same( '', US_States::resolve_destination( 'CA', 'PR' ) );
	affl_assert_same( '', US_States::resolve_destination( 'MX', 'DC' ) );
	affl_assert_same( '', US_States::resolve_destination( '', 'PR' ) );
} );
