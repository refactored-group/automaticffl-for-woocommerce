<?php
/**
 * Cart Analyzer Helper
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Helper;

defined( 'ABSPATH' ) || exit;

use RefactoredGroup\AutomaticFFL\Api\Restrictions_Client;

/**
 * Cart_Analyzer class
 *
 * Analyzes cart contents using the Restrictions API to determine
 * product classifications and checkout requirements.
 *
 * @since 1.0.14
 */
class Cart_Analyzer {

	/**
	 * Restrictions API client.
	 *
	 * @var Restrictions_Client
	 */
	private $restrictions_client;

	/**
	 * Cached analysis results.
	 *
	 * @var array|null
	 */
	private $analysis_cache = null;

	/**
	 * Tracks if the API is available.
	 *
	 * @var bool|null
	 */
	private $api_available = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.14
	 *
	 * @param Restrictions_Client|null $client Optional restrictions client instance.
	 */
	public function __construct( Restrictions_Client $client = null ) {
		$this->restrictions_client = $client ?? new Restrictions_Client();
	}

	/**
	 * Analyze current cart contents using restrictions API.
	 *
	 * @since 1.0.14
	 *
	 * @return array Categorized product data with keys: firearms, ammo, regular, restrictions, api_error.
	 */
	public function analyze(): array {
		if ( null !== $this->analysis_cache ) {
			return $this->analysis_cache;
		}

		$result = array(
			'firearms'     => array(),
			'ammo'         => array(),
			'regular'      => array(),
			'restrictions' => array(),
			'api_error'    => false,
		);

		if ( ! WC()->cart ) {
			$this->api_available = true;
			$this->analysis_cache = $result;
			return $result;
		}

		$cart = WC()->cart->get_cart();

		if ( empty( $cart ) ) {
			$this->api_available = true;
			$this->analysis_cache = $result;
			return $result;
		}

		// Collect all product IDs from cart.
		$product_ids = array();
		foreach ( $cart as $cart_item ) {
			$product_ids[] = intval( $cart_item['product_id'] );
		}

		// Fetch restrictions from API.
		$restrictions = $this->restrictions_client->get_restrictions( $product_ids );

		// Check if API returned an error.
		if ( $this->restrictions_client->has_api_error( $restrictions ) ) {
			$this->api_available = false;
			$result['api_error'] = true;
			// Treat all products as regular when API unavailable.
			foreach ( $cart as $cart_item_key => $cart_item ) {
				$result['regular'][] = array(
					'product_id'    => intval( $cart_item['product_id'] ),
					'cart_item_key' => $cart_item_key,
					'quantity'      => intval( $cart_item['quantity'] ),
				);
			}
			$this->analysis_cache = $result;
			return $result;
		}

		$this->api_available = true;
		$result['restrictions'] = $restrictions;

		// Categorize products based on API response.
		foreach ( $cart as $cart_item_key => $cart_item ) {
			$product_id = intval( $cart_item['product_id'] );
			$categorized = false;

			foreach ( $restrictions as $restriction ) {
				if ( isset( $restriction['id'] ) && intval( $restriction['id'] ) === $product_id ) {
					if ( isset( $restriction['conditions'] ) ) {
						// Ammo: has conditions.
						$result['ammo'][] = array(
							'product_id'    => $product_id,
							'cart_item_key' => $cart_item_key,
							'quantity'      => intval( $cart_item['quantity'] ),
							'restriction'   => $restriction,
						);
					} else {
						// Firearm: no conditions.
						$result['firearms'][] = array(
							'product_id'    => $product_id,
							'cart_item_key' => $cart_item_key,
							'quantity'      => intval( $cart_item['quantity'] ),
							'restriction'   => $restriction,
						);
					}
					$categorized = true;
					break;
				}
			}

			// If not in API response, it's a regular product.
			if ( ! $categorized ) {
				$result['regular'][] = array(
					'product_id'    => $product_id,
					'cart_item_key' => $cart_item_key,
					'quantity'      => intval( $cart_item['quantity'] ),
				);
			}
		}

		$this->analysis_cache = $result;
		return $result;
	}

	/**
	 * Check if the Restrictions API is available.
	 *
	 * @since 1.0.14
	 *
	 * @return bool True if API is available.
	 */
	public function is_api_available(): bool {
		// Trigger analysis if not done yet.
		if ( null === $this->api_available ) {
			$this->analyze();
		}

		return $this->api_available ?? true;
	}

	/**
	 * Check if there was an API error during analysis.
	 *
	 * @since 1.0.14
	 *
	 * @return bool True if there was an API error.
	 */
	public function has_api_error(): bool {
		$analysis = $this->analyze();
		return ! empty( $analysis['api_error'] );
	}

	/**
	 * Check if cart has any firearms.
	 *
	 * @since 1.0.14
	 *
	 * @return bool True if cart contains firearms.
	 */
	public function has_firearms(): bool {
		$analysis = $this->analyze();
		return ! empty( $analysis['firearms'] );
	}

	/**
	 * Check if cart has any ammo products.
	 *
	 * @since 1.0.14
	 *
	 * @return bool True if cart contains ammo.
	 */
	public function has_ammo(): bool {
		$analysis = $this->analyze();
		return ! empty( $analysis['ammo'] );
	}

	/**
	 * Check if cart contains only ammo products (no firearms, no regular).
	 *
	 * @since 1.0.14
	 *
	 * @return bool True if cart is ammo-only.
	 */
	public function is_ammo_only(): bool {
		$analysis = $this->analyze();
		return ! empty( $analysis['ammo'] ) && empty( $analysis['firearms'] ) && empty( $analysis['regular'] );
	}

	/**
	 * Check if cart is a mixed cart (FFL items + regular items).
	 * This combination should block checkout.
	 *
	 * @since 1.0.14
	 *
	 * @return bool True if cart has both FFL and regular products.
	 */
	public function is_mixed_ffl_regular(): bool {
		$analysis = $this->analyze();
		$has_ffl = ! empty( $analysis['firearms'] ) || ! empty( $analysis['ammo'] );
		$has_regular = ! empty( $analysis['regular'] );
		return $has_ffl && $has_regular;
	}

	/**
	 * Check if cart contains ammo + regular products (no firearms).
	 *
	 * This combination may be allowed depending on shipping state.
	 * Unlike firearms+regular which is always blocked, ammo+regular
	 * can proceed if shipping to a state that doesn't require FFL for ammo.
	 *
	 * @since 1.0.14
	 *
	 * @return bool True if cart has ammo + regular but no firearms.
	 */
	public function is_ammo_regular_mixed(): bool {
		$analysis = $this->analyze();
		return ! empty( $analysis['ammo'] )
			&& empty( $analysis['firearms'] )
			&& ! empty( $analysis['regular'] );
	}

	/**
	 * Check if cart contains firearms + regular products.
	 *
	 * This combination is always blocked - firearms must always
	 * ship to an FFL dealer and cannot be combined with regular products.
	 *
	 * @since 1.0.14
	 *
	 * @return bool True if cart has firearms + regular products.
	 */
	public function is_firearms_regular_mixed(): bool {
		$analysis = $this->analyze();
		return ! empty( $analysis['firearms'] )
			&& ! empty( $analysis['regular'] );
	}

	/**
	 * Check if cart has any FFL products (firearms or ammo).
	 *
	 * @since 1.0.14
	 *
	 * @return bool True if cart has FFL products.
	 */
	public function has_ffl_products(): bool {
		return $this->has_firearms() || $this->has_ammo();
	}

	/**
	 * Get all restricted states from ammo products in cart.
	 * Returns the union of all restricted states across all ammo items.
	 *
	 * @since 1.0.14
	 *
	 * @return array Array of unique state codes where FFL is required for ammo.
	 */
	public function get_ammo_restricted_states(): array {
		$analysis = $this->analyze();
		$all_states = array();

		foreach ( $analysis['ammo'] as $ammo_item ) {
			if ( isset( $ammo_item['restriction']['conditions'] ) && is_array( $ammo_item['restriction']['conditions'] ) ) {
				// conditions is an array of condition objects
				foreach ( $ammo_item['restriction']['conditions'] as $condition ) {
					if ( isset( $condition['type'] ) && 'ship_state' === $condition['type'] && isset( $condition['states'] ) && is_array( $condition['states'] ) ) {
						$all_states = array_merge( $all_states, $condition['states'] );
					}
				}
			}
		}

		return array_unique( $all_states );
	}

	/**
	 * Check if given state requires FFL for current cart's ammo products.
	 *
	 * @since 1.0.14
	 *
	 * @param string $state_code Two-letter state code (e.g., 'CA', 'NY').
	 * @return bool True if state is restricted for any ammo in cart.
	 */
	public function requires_ffl_for_state( string $state_code ): bool {
		$restricted_states = $this->get_ammo_restricted_states();
		return in_array( strtoupper( $state_code ), $restricted_states, true );
	}

	/**
	 * Get product IDs by category.
	 *
	 * @since 1.0.14
	 *
	 * @param string $category Category: 'firearms', 'ammo', or 'regular'.
	 * @return array Array of product IDs.
	 */
	public function get_product_ids_by_category( string $category ): array {
		$analysis = $this->analyze();

		if ( ! isset( $analysis[ $category ] ) ) {
			return array();
		}

		return array_column( $analysis[ $category ], 'product_id' );
	}

	/**
	 * Get total quantity of items by category.
	 *
	 * @since 1.0.14
	 *
	 * @param string $category Category: 'firearms', 'ammo', or 'regular'.
	 * @return int Total quantity of items in the category.
	 */
	public function get_quantity_by_category( string $category ): int {
		$analysis = $this->analyze();

		if ( ! isset( $analysis[ $category ] ) ) {
			return 0;
		}

		return array_sum( array_column( $analysis[ $category ], 'quantity' ) );
	}

	/**
	 * Clear cached analysis.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->analysis_cache = null;
		$this->restrictions_client->clear_cache();
	}

	/**
	 * Get the restrictions client instance.
	 *
	 * @since 1.0.14
	 *
	 * @return Restrictions_Client
	 */
	public function get_restrictions_client(): Restrictions_Client {
		return $this->restrictions_client;
	}
}
