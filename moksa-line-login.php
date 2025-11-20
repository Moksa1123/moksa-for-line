<?php
/**
 * Plugin Name: Moksa LINE Login
 * Plugin URI: https://moksaweb.com/
 * Description: A comprehensive LINE Login and messaging solution for WordPress.
 * Version: 1.3.4
 * Author: Moksa
 * Author URI: https://moksaweb.com/
 * Text Domain: moksa-line-login
 * Domain Path: /languages
 * License: GPL v2 or later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MOKSA_LINE_VERSION', '1.3.4');
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
     * Get instance
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
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-popup.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-woocommerce.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-messaging.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-webhook.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-richmenu.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-flexmessage.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-quickreply.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-autoreply.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-woocarousel.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-liff.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-imagemap.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-dashboard.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-tools.php';
        require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-security.php';
    }
    
    /**
     * Initialize hooks and classes
     */
    private function init_hooks() {
        // Initialize classes
        Moksa_Line_Database::get_instance();
        Moksa_Line_Admin::get_instance();
        Moksa_Line_Auth::get_instance();
        Moksa_Line_Profile::get_instance();
        Moksa_Line_Shortcodes::get_instance();
        Moksa_Line_Popup::get_instance();
        Moksa_Line_WooCommerce::get_instance();
        Moksa_Line_Messaging::get_instance();
        Moksa_Line_Webhook::get_instance();
        Moksa_Line_RichMenu::get_instance();
        Moksa_Line_FlexMessage::get_instance();
        Moksa_Line_QuickReply::get_instance();
        Moksa_Line_AutoReply::get_instance();
        Moksa_Line_WooCarousel::get_instance();
        Moksa_Line_LIFF::get_instance();
        Moksa_Line_Imagemap::get_instance();
        Moksa_Line_Dashboard::get_instance();
        Moksa_Line_Tools::get_instance();
        Moksa_Line_Security::get_instance();
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Register deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('moksa-line-login', false, dirname(MOKSA_LINE_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $db = Moksa_Line_Database::get_instance();
        $db->create_tables();
        
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
