<?php
/**
 * Plugin Name: Moksa LINE Login
 * Plugin URI: https://moksaweb.com
 * Description: Complete LINE Login integration for WordPress with user registration, profile management, and database storage.
 * Version: 1.0.0
 * Author: Moksa Team
 * Author URI: https://moksaweb.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: moksa-line-login
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MOKSA_LINE_VERSION', '1.0.0');
define('MOKSA_LINE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MOKSA_LINE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MOKSA_LINE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Moksa LINE Login Plugin Class
 */
class Moksa_Line_Login {
    
    /**
     * Single instance of the class
     */
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-database.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-admin.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-auth.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-profile.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-shortcodes.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-popup.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-woocommerce.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-messaging.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-webhook.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-richmenu.php';
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
<?php
/**
 * Plugin Name: Moksa LINE Login
 * Plugin URI: https://moksaweb.com
 * Description: Complete LINE Login integration for WordPress with user registration, profile management, and database storage.
 * Version: 1.0.0
 * Author: Moksa Team
 * Author URI: https://moksaweb.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: moksa-line-login
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MOKSA_LINE_VERSION', '1.0.0');
define('MOKSA_LINE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MOKSA_LINE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MOKSA_LINE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Moksa LINE Login Plugin Class
 */
class Moksa_Line_Login {
    
    /**
     * Single instance of the class
     */
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-database.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-admin.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-auth.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-profile.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-shortcodes.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-popup.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-woocommerce.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-messaging.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-webhook.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-richmenu.php';
        Moksa_Line_LIFF::get_instance();
        Moksa_Line_Imagemap::get_instance();
        Moksa_Line_Dashboard::get_instance();
        Moksa_Line_Tools::get_instance();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function moksa_line_login() {
    return Moksa_Line_Login::get_instance();
}

// Start the plugin
moksa_line_login();
