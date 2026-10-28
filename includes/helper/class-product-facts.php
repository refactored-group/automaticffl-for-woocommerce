<?php
/**
 * Authoritative WooCommerce product fact collection.
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the bounded product descriptors sent to Automatic FFL.
 */
class Product_Facts {

	/** Largest integer represented exactly by JSON numbers in common clients. */
	const MAX_SAFE_INTEGER = 9007199254740991;

	/** Maximum directly assigned categories accepted for one product. */
	const MAX_CATEGORIES = 256;

	/**
	 * Collect normalized parent-product facts for cart product IDs.
	 *
	 * Browser-supplied classification data is intentionally not accepted. Every
	 * value is loaded from WooCommerce's saved product object in edit context.
	 *
	 * @param array $product_ids Cart product or variation IDs.
	 * @return array|\WP_Error
	 */
	public function collect( array $product_ids ) {
		$parent_ids = array();

		foreach ( $product_ids as $product_id ) {
			if ( ! $this->valid_id( $product_id ) ) {
				return $this->invalid_facts();
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return $this->invalid_facts();
			}

			$parent_id = (int) $product->get_parent_id();
			$fact_id   = $parent_id > 0 ? $parent_id : (int) $product->get_id();
			if ( ! $this->valid_id( $fact_id ) ) {
				return $this->invalid_facts();
			}

			$parent_ids[ $fact_id ] = true;
		}

		$parent_ids = array_keys( $parent_ids );
		sort( $parent_ids, SORT_NUMERIC );
		$facts = array();

		foreach ( $parent_ids as $parent_id ) {
			$product = wc_get_product( $parent_id );
			if ( ! $product ) {
				return $this->invalid_facts();
			}

			$categories = $product->get_category_ids();
			if ( ! is_array( $categories ) || count( $categories ) > self::MAX_CATEGORIES ) {
				return $this->invalid_facts();
			}

			$normalized_categories = array();
			foreach ( $categories as $category_id ) {
				if ( ! $this->valid_id( $category_id ) ) {
					return $this->invalid_facts();
				}
				$normalized_categories[ (int) $category_id ] = true;
			}

			$normalized_categories = array_keys( $normalized_categories );
			sort( $normalized_categories, SORT_NUMERIC );
			$flag = $product->get_meta( '_ffl_required', true, 'edit' );

			$facts[] = array(
				'id'           => (int) $parent_id,
				'category_ids' => $normalized_categories,
				'ffl_required' => is_string( $flag ) && 'yes' === strtolower( $flag ),
			);
		}

		return $facts;
	}

	/** @param mixed $value Candidate ID. */
	private function valid_id( $value ) {
		return is_int( $value ) && $value >= 1 && $value <= self::MAX_SAFE_INTEGER;
	}

	/** @return \WP_Error */
	private function invalid_facts() {
		return new \WP_Error( 'invalid_product_facts', __( 'A cart product could not be classified.', 'automaticffl-for-wc' ) );
	}
}
