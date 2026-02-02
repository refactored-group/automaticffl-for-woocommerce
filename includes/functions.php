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
