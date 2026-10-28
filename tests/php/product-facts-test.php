<?php
/** Product fact collector behavior. */

use RefactoredGroup\AutomaticFFL\Helper\Product_Facts;

require_once dirname( __DIR__, 2 ) . '/includes/helper/class-product-facts.php';

affl_test(
	'product facts use authoritative parent data and canonicalize duplicates',
	function () {
		$GLOBALS['affl_products'][101] = new AFFL_Test_Product( 101, 1, array( 999 ), array( '_ffl_required' => 'no' ) );
		$GLOBALS['affl_products'][1]   = new AFFL_Test_Product( 1, 0, array( 20, 10, 20 ), array( '_ffl_required' => 'YeS' ) );
		$GLOBALS['affl_products'][2]   = new AFFL_Test_Product( 2, 0, array(), array( '_ffl_required' => ' yes ' ) );

		$facts = ( new Product_Facts() )->collect( array( 2, 101, 1, 101 ) );

		affl_assert_same(
			array(
				array( 'id' => 1, 'category_ids' => array( 10, 20 ), 'ffl_required' => true ),
				array( 'id' => 2, 'category_ids' => array(), 'ffl_required' => false ),
			),
			$facts
		);
	}
);

affl_test(
	'product facts accept only a case-insensitive exact yes flag',
	function () {
		$values = array(
			array( 'yes', true ),
			array( 'YES', true ),
			array( 'YeS', true ),
			array( ' yes ', false ),
			array( '1', false ),
			array( 1, false ),
			array( true, false ),
			array( 'no', false ),
		);

		$id = 1;
		foreach ( $values as $case ) {
			list( $value, $expected ) = $case;
			$GLOBALS['affl_products'][ $id ] = new AFFL_Test_Product( $id, 0, array(), array( '_ffl_required' => $value ) );
			$facts = ( new Product_Facts() )->collect( array( $id ) );
			affl_assert_same( $expected, $facts[0]['ffl_required'], 'Unexpected flag normalization.' );
			++$id;
		}
	}
);

affl_test(
	'product facts fail the whole operation for missing products',
	function () {
		$GLOBALS['affl_products'][1] = new AFFL_Test_Product( 1 );
		$result = ( new Product_Facts() )->collect( array( 1, 2 ) );

		affl_assert_true( is_wp_error( $result ) );
		affl_assert_same( 'invalid_product_facts', $result->get_error_code() );
	}
);

affl_test(
	'product facts enforce the raw category bound before deduplication',
	function () {
		$GLOBALS['affl_products'][1] = new AFFL_Test_Product( 1, 0, array_fill( 0, 257, 10 ) );
		$result = ( new Product_Facts() )->collect( array( 1 ) );

		affl_assert_true( is_wp_error( $result ) );
		affl_assert_same( 'invalid_product_facts', $result->get_error_code() );
	}
);

affl_test(
	'product facts ignore browser-style classification claims',
	function () {
		$GLOBALS['affl_products'][1] = new AFFL_Test_Product( 1, 0, array( 30 ), array( '_ffl_required' => 'no' ) );
		$forged = array( 'id' => 1, 'category_ids' => array( 10 ), 'ffl_required' => true );

		$facts = ( new Product_Facts() )->collect( array( $forged['id'] ) );

		affl_assert_same(
			array( array( 'id' => 1, 'category_ids' => array( 30 ), 'ffl_required' => false ) ),
			$facts
		);
	}
);
