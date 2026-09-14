<?php
/**
 * Plugin Name: Moksa for LINE
 * Description: LINE Login, Messaging API bot, Flex Message builder, tabbed rich menus, customer-service inbox, AI replies and LINE Pay for WordPress and WooCommerce.
 * Version: 1.0.0
 * Author: Moksa
 * Author URI: https://moksaweb.com/
 * Text Domain: moksa-for-line
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.2
 * Requires PHP: 7.4
 *
 * @package Mofoline
 */

defined( 'ABSPATH' ) || exit;

define( 'MOFOLINE_VERSION', '1.0.0' );
define( 'MOFOLINE_FILE', __FILE__ );
define( 'MOFOLINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'MOFOLINE_URL', plugin_dir_url( __FILE__ ) );
define( 'MOFOLINE_BASENAME', plugin_basename( __FILE__ ) );

// Kept for templates and third-party snippets written against 1.x.
define( 'MOFOLINE_PLUGIN_DIR', MOFOLINE_DIR );
define( 'MOFOLINE_PLUGIN_URL', MOFOLINE_URL );
define( 'MOFOLINE_PLUGIN_BASENAME', MOFOLINE_BASENAME );

/**
 * PSR-4 autoloader for the Mofoline namespace.
 *
 * Composer is deliberately not required: this plugin is installed by copying a
 * folder, and a missing vendor/ directory would be a fatal error rather than a
 * degraded experience.
 *
 * @param string $class Fully qualified class name.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'Mofoline\\';

		if ( 0 !== strncmp( $prefix, $class, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = MOFOLINE_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/*
 * Tell WooCommerce which of its features this plugin has been written for.
 * Without these it lists the plugin as incompatible with order storage in
 * custom tables and with the block checkout -- and it would be right to,
 * except that every order here goes through wc_get_order() and the gateway
 * registers with the block registry. Declaring it is what makes that true
 * from WooCommerce's side as well.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

register_activation_hook( __FILE__, array( 'Mofoline\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Mofoline\\Plugin', 'deactivate' ) );

/**
 * Plugin instance.
 */
function mofoline(): Mofoline\Plugin {
	return Mofoline\Plugin::instance();
}

mofoline()->boot();
