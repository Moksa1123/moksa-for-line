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
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-database.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-admin.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-auth.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-profile.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-shortcodes.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('moksa-line-login', false, dirname(MOKSA_LINE_PLUGIN_BASENAME) . '/languages');
        
        // Initialize components
        Moksa_Line_Database::get_instance();
        Moksa_Line_Admin::get_instance();
        Moksa_Line_Auth::get_instance();
        Moksa_Line_Profile::get_instance();
        Moksa_Line_Shortcodes::get_instance();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        Moksa_Line_Database::create_tables();
        
        // Set default options
        $default_options = array(
            'channel_id' => '',
            'channel_secret' => '',
            'auto_register' => '1',
            'sync_profile' => '1',
            'button_text' => __('Login with LINE', 'moksa-line-login'),
            'redirect_after_login' => home_url(),
        );
        
        foreach ($default_options as $key => $value) {
            if (get_option('moksa_line_' . $key) === false) {
                add_option('moksa_line_' . $key, $value);
            }
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
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
