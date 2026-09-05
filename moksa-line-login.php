<?php
/**
 * Plugin Name: Moksa LINE Suite
 * Plugin URI: https://moksaweb.com/
 * Description: LINE Login, Messaging API bot, Flex Message builder, tabbed rich menus, customer-service inbox, AI replies and LINE Pay for WordPress and WooCommerce.
 * Version: 2.0.0
 * Author: Moksa
 * Author URI: https://moksaweb.com/
 * Text Domain: moksa-line-login
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.2
 * Requires PHP: 7.4
 *
 * @package Moksa\Line
 */

defined( 'ABSPATH' ) || exit;

define( 'MOKSA_LINE_VERSION', '2.0.0' );
define( 'MOKSA_LINE_FILE', __FILE__ );
define( 'MOKSA_LINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'MOKSA_LINE_URL', plugin_dir_url( __FILE__ ) );
define( 'MOKSA_LINE_BASENAME', plugin_basename( __FILE__ ) );

// Kept for templates and third-party snippets written against 1.x.
define( 'MOKSA_LINE_PLUGIN_DIR', MOKSA_LINE_DIR );
define( 'MOKSA_LINE_PLUGIN_URL', MOKSA_LINE_URL );
define( 'MOKSA_LINE_PLUGIN_BASENAME', MOKSA_LINE_BASENAME );

/**
 * PSR-4 autoloader for the Moksa\Line namespace.
 *
 * Composer is deliberately not required: this plugin is installed by copying a
 * folder, and a missing vendor/ directory would be a fatal error rather than a
 * degraded experience.
 *
 * @param string $class Fully qualified class name.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'Moksa\\Line\\';

		if ( 0 !== strncmp( $prefix, $class, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = MOKSA_LINE_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( 'Moksa\\Line\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Moksa\\Line\\Plugin', 'deactivate' ) );

/**
 * Plugin instance.
 */
function moksa_line(): Moksa\Line\Plugin {
	return Moksa\Line\Plugin::instance();
}

moksa_line()->boot();
