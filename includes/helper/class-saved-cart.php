<?php
/**
 * Saved Cart Helper
 *
 * @package AutomaticFFL
 */

namespace RefactoredGroup\AutomaticFFL\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Saved_Cart class
 *
 * Handles saving cart items for later restoration.
 * Used when customers have mixed carts (FFL + regular items) and
 * need to complete separate orders.
 *
 * Flow:
 * 1. SAVE: Items saved to transient, token stored in session + cookie
 * 2. CHECKOUT: Token copied from session to order meta
 * 3. THANK YOU: Token retrieved from order meta, redirect URL built
 * 4. CART: Token from URL used to retrieve and restore items
 *
 * @since 1.0.14
 */
class Saved_Cart {

	/**
	 * Session key for the restore token.
	 * Stores the token string in WC session for retrieval during checkout.
	 */
	const SESSION_TOKEN_KEY = 'automaticffl_cart_token';

	/**
	 * Order meta key for the restore token.
	 * Stores the token in order meta for reliable retrieval on thank you page.
	 */
	const ORDER_META_KEY = '_automaticffl_restore_token';

	/**
	 * Transient prefix for storage.
	 * Data is stored in transients keyed by a token.
	 */
	const TRANSIENT_PREFIX = 'affl_saved_cart_';

	/**
	 * Cookie name for the storage token (backup method).
	 */
	const COOKIE_NAME = 'automaticffl_saved_cart_token';

	/**
	 * Transient/cookie expiration time (24 hours).
	 * Gives users enough time to complete checkout even if interrupted.
	 */
	const EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Get or create a unique token for this user's saved cart.
	 *
	 * Uses a dedicated cookie that persists independently of WC session.
	 *
	 * @since 1.0.14
	 *
	 * @param bool $create Whether to create a new token if one doesn't exist.
	 * @return string|null The token or null if not available and not creating.
	 */
	private static function get_token( bool $create = false ): ?string {
		// Check for existing cookie.
		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) && ! empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		}

		// Create new token if requested.
		if ( $create ) {
			$token = wp_generate_uuid4();
			self::set_cookie( $token );
			return $token;
		}

		return null;
	}

	/**
	 * Set the token cookie.
	 *
	 * @since 1.0.14
	 *
	 * @param string $token The token to store.
	 * @return void
	 */
	private static function set_cookie( string $token ): void {
		$expire = time() + self::EXPIRATION;
		$secure = is_ssl();
		$path   = COOKIEPATH ? COOKIEPATH : '/';

		// Use wc_setcookie if available (handles headers sent check).
		if ( function_exists( 'wc_setcookie' ) ) {
			wc_setcookie( self::COOKIE_NAME, $token, $expire, $secure );
		} elseif ( ! headers_sent() ) {
			setcookie( self::COOKIE_NAME, $token, $expire, $path, COOKIE_DOMAIN, $secure, true );
		}

		// Also set in $_COOKIE superglobal for immediate availability.
		$_COOKIE[ self::COOKIE_NAME ] = $token;
	}

	/**
	 * Clear the token cookie.
	 *
	 * @since 1.0.14
	 *
	 * @return void
	 */
	private static function clear_cookie(): void {
		$path   = COOKIEPATH ? COOKIEPATH : '/';
		$secure = is_ssl();

		if ( function_exists( 'wc_setcookie' ) ) {
			wc_setcookie( self::COOKIE_NAME, '', time() - YEAR_IN_SECONDS, $secure );
		} elseif ( ! headers_sent() ) {
			setcookie( self::COOKIE_NAME, '', time() - YEAR_IN_SECONDS, $path, COOKIE_DOMAIN, $secure, true );
		}

		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	/**
	 * Save items of a specific type and remove from cart.
	 *
	 * @since 1.0.14
	 *
	 * @param string $item_type Type of items to save: 'ffl' or 'regular'.
	 * @return array Array with 'success', 'saved_count', and 'message' keys.
	 */
	public static function save_items( string $item_type ): array {
		if ( ! WC()->cart || ! WC()->session ) {
			return array(
				'success'     => false,
				'saved_count' => 0,
				'message'     => __( 'Cart session not available.', 'automaticffl-for-wc' ),
			);
		}

		$analyzer = new Cart_Analyzer();
		$analysis = $analyzer->analyze();

		// Determine which items to save based on type.
		$items_to_save = array();
		if ( 'ffl' === $item_type ) {
			$items_to_save = array_merge( $analysis['firearms'], $analysis['ammo'] );
		} elseif ( 'regular' === $item_type ) {
			$items_to_save = $analysis['regular'];
		}

		if ( empty( $items_to_save ) ) {
			return array(
				'success'     => false,
				'saved_count' => 0,
				'message'     => __( 'No items to save.', 'automaticffl-for-wc' ),
			);
		}

		// Build saved items array with full cart item data.
		$cart                = WC()->cart->get_cart();
		$saved_items         = array();
		$cart_keys_to_remove = array();

		foreach ( $items_to_save as $item ) {
			$cart_item_key = $item['cart_item_key'];
			if ( isset( $cart[ $cart_item_key ] ) ) {
				$cart_item = $cart[ $cart_item_key ];
				$product   = wc_get_product( $cart_item['product_id'] );

				$saved_items[] = array(
					'product_id'     => intval( $cart_item['product_id'] ),
					'quantity'       => intval( $cart_item['quantity'] ),
					'variation_id'   => isset( $cart_item['variation_id'] ) ? intval( $cart_item['variation_id'] ) : 0,
					'variation'      => isset( $cart_item['variation'] ) ? $cart_item['variation'] : array(),
					'cart_item_data' => self::get_cart_item_data( $cart_item ),
					'product_name'   => $product ? $product->get_name() : __( 'Product', 'automaticffl-for-wc' ),
				);

				$cart_keys_to_remove[] = $cart_item_key;
			}
		}

		// Prepare data to save.
		$save_data = array(
			'saved_at' => time(),
			'items'    => $saved_items,
		);

		// Generate token and save to transient.
		$token = self::get_token( true );
		if ( $token ) {
			$transient_key = self::TRANSIENT_PREFIX . 'items_' . $token;
			set_transient( $transient_key, $save_data, self::EXPIRATION );

			// Store token in WC session for retrieval during checkout.
			self::save_token_to_session( $token );
		}

		// Remove items from cart.
		foreach ( $cart_keys_to_remove as $cart_key ) {
			WC()->cart->remove_cart_item( $cart_key );
		}

		// Recalculate cart totals.
		WC()->cart->calculate_totals();

		return array(
			'success'     => true,
			'saved_count' => count( $saved_items ),
			'message'     => sprintf(
				/* translators: %d: number of saved items */
				_n( '%d item saved for later.', '%d items saved for later.', count( $saved_items ), 'automaticffl-for-wc' ),
				count( $saved_items )
			),
		);
	}

	/**
	 * Extract custom cart item data (exclude standard keys).
	 *
	 * @since 1.0.14
	 *
	 * @param array $cart_item Cart item array.
	 * @return array Filtered cart item data.
	 */
	private static function get_cart_item_data( array $cart_item ): array {
		$exclude_keys = array(
			'key',
			'product_id',
			'variation_id',
			'variation',
			'quantity',
			'data',
			'data_hash',
			'line_tax_data',
			'line_subtotal',
			'line_subtotal_tax',
			'line_total',
			'line_tax',
		);

		$cart_item_data = array();
		foreach ( $cart_item as $key => $value ) {
			if ( ! in_array( $key, $exclude_keys, true ) ) {
				$cart_item_data[ $key ] = $value;
			}
		}

		return $cart_item_data;
	}

	/**
	 * Store the token in WC session.
	 *
	 * Called during save_items() to store the token in the session
	 * for later retrieval during checkout.
	 *
	 * @since 1.0.14
	 *
	 * @param string $token The token to store.
	 * @return void
	 */
	public static function save_token_to_session( string $token ): void {
		if ( WC()->session ) {
			WC()->session->set( self::SESSION_TOKEN_KEY, $token );
		}
	}

	/**
	 * Copy token from WC session to order meta.
	 *
	 * Called during checkout to ensure the token is stored with the order.
	 * This makes token retrieval reliable on the thank you page.
	 *
	 * @since 1.0.14
	 *
	 * @param int $order_id The order ID.
	 * @return void
	 */
	public static function save_token_to_order( int $order_id ): void {
		$token = null;

		// First try to get token from WC session.
		if ( WC()->session ) {
			$token = WC()->session->get( self::SESSION_TOKEN_KEY );
		}

		// Fallback to cookie if session doesn't have it.
		if ( empty( $token ) ) {
			$token = self::get_token( false );
		}

		// Save token to order meta if we have one.
		if ( ! empty( $token ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->update_meta_data( self::ORDER_META_KEY, $token );
				$order->save();
			}
		}
	}

	/**
	 * Get token from order meta.
	 *
	 * Used on thank you page to retrieve the token for building the redirect URL.
	 *
	 * @since 1.0.14
	 *
	 * @param int $order_id The order ID.
	 * @return string|null The token or null if not found.
	 */
	public static function get_token_from_order( int $order_id ): ?string {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}
		$token = $order->get_meta( self::ORDER_META_KEY );
		return ! empty( $token ) ? $token : null;
	}

	/**
	 * Check if there are saved items for the current user.
	 *
	 * Uses the cookie-based token to check transient storage.
	 *
	 * @since 1.0.14
	 *
	 * @return bool True if saved items exist.
	 */
	public static function has_saved_items(): bool {
		$token = self::get_token( false );
		if ( ! $token ) {
			return false;
		}

		$saved_data = self::get_saved_items_by_token( $token );
		return ! empty( $saved_data );
	}

	/**
	 * Get count of saved items for the current user.
	 *
	 * @since 1.0.14
	 *
	 * @return int Number of saved items.
	 */
	public static function get_saved_items_count(): int {
		$token = self::get_token( false );
		if ( ! $token ) {
			return 0;
		}

		$saved_data = self::get_saved_items_by_token( $token );
		if ( ! empty( $saved_data ) && ! empty( $saved_data['items'] ) ) {
			return count( $saved_data['items'] );
		}

		return 0;
	}

	/**
	 * Get saved items using a specific token.
	 *
	 * @since 1.0.14
	 *
	 * @param string $token The token to use for lookup.
	 * @return array|null Saved items data or null if none.
	 */
	public static function get_saved_items_by_token( string $token ): ?array {
		$transient_key = self::TRANSIENT_PREFIX . 'items_' . $token;
		$saved_data    = get_transient( $transient_key );

		if ( ! empty( $saved_data ) && ! empty( $saved_data['items'] ) ) {
			return $saved_data;
		}

		return null;
	}

	/**
	 * Restore saved items to the cart from a data array.
	 *
	 * Validates each product (exists, purchasable, in stock) before adding.
	 * Adjusts quantities if stock is insufficient.
	 *
	 * @since 1.0.14
	 *
	 * @param array $saved_data The saved items data with 'items' key.
	 * @return array Result with 'success', 'restored_count', 'failed', and 'message'.
	 */
	public static function restore_items( array $saved_data ): array {
		if ( ! WC()->cart ) {
			return array(
				'success'        => false,
				'restored_count' => 0,
				'failed'         => array(),
				'message'        => __( 'Cart not available.', 'automaticffl-for-wc' ),
			);
		}

		$restored_count = 0;
		$failed_items   = array();

		foreach ( $saved_data['items'] as $item ) {
			$product = wc_get_product( $item['product_id'] );

			if ( ! $product || ! $product->is_purchasable() ) {
				$failed_items[] = array(
					'product_name' => $item['product_name'] ?? __( 'Product', 'automaticffl-for-wc' ),
					'reason'       => __( 'Product no longer available.', 'automaticffl-for-wc' ),
				);
				continue;
			}

			if ( ! $product->is_in_stock() ) {
				$failed_items[] = array(
					'product_name' => $item['product_name'] ?? $product->get_name(),
					'reason'       => __( 'Product is out of stock.', 'automaticffl-for-wc' ),
				);
				continue;
			}

			$quantity = $item['quantity'];

			if ( $product->managing_stock() && $product->get_stock_quantity() < $quantity ) {
				$available = $product->get_stock_quantity();
				if ( $available > 0 ) {
					$quantity = $available;
				} else {
					$failed_items[] = array(
						'product_name' => $item['product_name'] ?? $product->get_name(),
						'reason'       => __( 'Insufficient stock.', 'automaticffl-for-wc' ),
					);
					continue;
				}
			}

			$added = WC()->cart->add_to_cart(
				$item['product_id'],
				$quantity,
				$item['variation_id'] ?? 0,
				$item['variation'] ?? array(),
				$item['cart_item_data'] ?? array()
			);

			if ( $added ) {
				$restored_count++;
			} else {
				$failed_items[] = array(
					'product_name' => $item['product_name'] ?? $product->get_name(),
					'reason'       => __( 'Could not add to cart.', 'automaticffl-for-wc' ),
				);
			}
		}

		if ( $restored_count > 0 && empty( $failed_items ) ) {
			$message = sprintf(
				_n(
					'%d item has been restored to your cart.',
					'%d items have been restored to your cart.',
					$restored_count,
					'automaticffl-for-wc'
				),
				$restored_count
			);
		} elseif ( $restored_count > 0 && ! empty( $failed_items ) ) {
			$message = sprintf(
				_n(
					'%d item has been restored to your cart. Some items could not be restored.',
					'%d items have been restored to your cart. Some items could not be restored.',
					$restored_count,
					'automaticffl-for-wc'
				),
				$restored_count
			);
		} else {
			$message = __( 'Items could not be restored to your cart.', 'automaticffl-for-wc' );
		}

		return array(
			'success'        => $restored_count > 0,
			'restored_count' => $restored_count,
			'failed'         => $failed_items,
			'message'        => $message,
		);
	}

	/**
	 * Clear saved items using a specific token.
	 *
	 * @since 1.0.14
	 *
	 * @param string $token The token to use for cleanup.
	 * @return void
	 */
	public static function clear_saved_items_by_token( string $token ): void {
		delete_transient( self::TRANSIENT_PREFIX . 'items_' . $token );

		// Clear session token if it matches.
		if ( WC()->session && WC()->session->get( self::SESSION_TOKEN_KEY ) === $token ) {
			WC()->session->set( self::SESSION_TOKEN_KEY, null );
		}

		// Clear cookie.
		self::clear_cookie();
	}
}
