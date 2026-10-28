<?php
/**
 * Standalone Automatic FFL PHP behavior test runner.
 *
 * @package AutomaticFFL
 */

require_once __DIR__ . '/bootstrap.php';

$test_files = glob( __DIR__ . '/*-test.php' );
sort( $test_files );

foreach ( $test_files as $test_file ) {
	require_once $test_file;
}

$failures = 0;
$count    = count( $GLOBALS['affl_tests'] );

foreach ( $GLOBALS['affl_tests'] as $test_case ) {
	affl_reset_test_state();
	list( $name, $test ) = $test_case;

	try {
		$test();
		fwrite( STDOUT, "PASS {$name}\n" );
	} catch ( Throwable $error ) {
		++$failures;
		fwrite( STDERR, "FAIL {$name}: {$error->getMessage()}\n" );
	}
}

fwrite( STDOUT, sprintf( "\n%d test(s), %d failure(s).\n", $count, $failures ) );
exit( 0 === $failures ? 0 : 1 );
