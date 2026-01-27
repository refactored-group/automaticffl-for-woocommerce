<?php
/**
 * Plugin Name: Automatic FFL for WooCommerce
 * Plugin URI: http://refactored.group/ffl/woocommerce/
 * Description: The official Automatic FFL for WooCommerce plugin
 * Author: Refactored Group
 * Author URI: http://refactored.group
 * Version: 1.0.14
 * Tested up to: 6.9
 * WC tested up to: 10.4.3
 * Text Domain: automaticffl-for-wc
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2023, Refactored Group
 *
 * @package   AutomaticFFL
 * Author    Refactored Group
 * Copyright Copyright (c) 2023, Refactored Group.
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 *
 * Woo: 99999:00000000000000000000000000000000 @TODO: update this when the plugin is released
 */

defined( 'ABSPATH' ) || exit;
define( '_AFFL_LOADER_', __FILE__ );

require_once 'includes/class-wc-ffl-loader.php';

spl_autoload_register(
	function ( $class ) {
		$namespace = 'RefactoredGroup\AutomaticFFL';

		// Does the class use the namespace prefix?
		$len = strlen( $namespace );
		if ( strncmp( $namespace, $class, $len ) !== 0 ) {
			// no, move to the next registered autoloader.
			return false;
		}

		// Get the relative class name.
		$relative_class = str_replace( '\\', '/', substr( $class, $len + 1 ) );

		// Transform the relative class name into the actual class path.
		$relative_class                                 = explode( '/', $relative_class );
		$class_file_name                                = 'class-' . str_replace( '_', '-', end( $relative_class ) );
		$relative_class[ count( $relative_class ) - 1 ] = $class_file_name;
		$relative_class                                 = strtolower( implode( '/', $relative_class ) );

		// Get the class file path.
		$file = dirname( __FILE__ ) . '/includes/' . $relative_class . '.php';

		// if the file exists, require it.
		if ( file_exists( $file ) ) {
			require_once $file;
			return true;
		}
		return false;
	}
);

// Start the plugin.
AFFL_Loader::instance();
