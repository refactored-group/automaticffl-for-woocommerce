<?php
/**
 * WooCommerce Framework Plugin
 *
 * @package AutomaticFFL
 *
 * Author:    Refactored Group
 * License:   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * Copyright (c) 2023 Refactored Group
 */

use RefactoredGroup\AutomaticFFL\Plugin;

/**
 * Create a new instance for this plugin
 *
 * @since 1.0.0
 */
function affl() {
	return Plugin::instance();
}

function ts_hide_ship_to_different_address() {
    if (is_checkout()) {
        echo '<style>#ship-to-different-address { display: none; }</style>';
    }
}

add_action('wp_head', 'ts_hide_ship_to_different_address');
