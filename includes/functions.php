<?php
/**
 * WooCommerce Framework Plugin
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @author    Refactored Group
 * @copyright Copyright (c) 2023 Refactored Group
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

use RefactoredGroup\AutomaticFFL\Plugin;
ini_set('display_errors', 'On');

/**
 * @since 1.0.0
 */
function wcffl()
{
    return Plugin::instance();
}
