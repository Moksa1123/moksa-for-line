<?php
/**
 * Admin Settings and Management
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('manage_users_columns', array($this, 'add_line_user_column'));
        add_action('manage_users_custom_column', array($this, 'show_line_user_column'), 10, 3);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('LINE Login Settings', 'moksa-line-login'),
            __('LINE Login', 'moksa-line-login'),
            'manage_options',
            'moksa-line-login',
            array($this, 'render_settings_page'),
            'dashicons-share',
            80
        );
        
        add_submenu_page(
            'moksa-line-login',
            __('General Settings', 'moksa-line-login'),
            __('General', 'moksa-line-login'),
            'manage_options',
            'moksa-line-login',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            __('Button Settings', 'moksa-line-login'),
            __('Button Style', 'moksa-line-login'),
            'manage_options',
            'moksa-line-button',
            array($this, 'render_button_settings')
        );
        
        add_submenu_page(
            'moksa-line-login',
            __('Messaging API', 'moksa-line-login'),
            __('Messaging', 'moksa-line-login'),
            'manage_options',
            'moksa-line-messaging',
            array($this, 'render_messaging_settings')
        );
        
        add_submenu_page(
            'moksa-line-login',
            __('Rich Menu', 'moksa-line-login'),
            __('Rich Menu', 'moksa-line-login'),
            'manage_options',
            'moksa-line-richmenu',
            array($this, 'render_richmenu_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            __('Flex Message', 'moksa-line-login'),
            __('Flex Message', 'moksa-line-login'),
            'manage_options',
            'moksa-line-flexmessage',
            array($this, 'render_flexmessage_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            __('Tools', 'moksa-line-login'),
            __('Tools', 'moksa-line-login'),
            'manage_options',
            'moksa-line-tools',
            array($this, 'render_tools_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            __('Documentation', 'moksa-line-login'),
            __('Documentation', 'moksa-line-login'),
            'manage_options',
            'moksa-line-docs',
            array($this, 'render_documentation_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings
        register_setting('moksa_line_general', 'moksa_line_channel_id');
        register_setting('moksa_line_general', 'moksa_line_channel_secret');
        register_setting('moksa_line_general', 'moksa_line_auto_register');
        register_setting('moksa_line_general', 'moksa_line_sync_profile');
        register_setting('moksa_line_general', 'moksa_line_redirect_after_login');
        
        // Button Settings
        register_setting('moksa_line_button', 'moksa_line_button_text');
        register_setting('moksa_line_button', 'moksa_line_button_bg_color');
        register_setting('moksa_line_button', 'moksa_line_button_text_color');
        register_setting('moksa_line_button', 'moksa_line_button_border_radius');
        register_setting('moksa_line_button', 'moksa_line_button_width');
        register_setting('moksa_line_button', 'moksa_line_button_height');
        
        // Messaging API Settings
        register_setting('moksa_line_messaging', 'moksa_line_messaging_token');
        register_setting('moksa_line_messaging', 'moksa_line_messaging_secret');
        register_setting('moksa_line_messaging', 'moksa_line_add_friend_url');
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'moksa-line') === false) {
            return;
        }
        
        // WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Admin styles
        wp_enqueue_style(
            'moksa-line-admin',
            MOKSA_LINE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MOKSA_LINE_VERSION
        );
        
        // Admin scripts
        wp_enqueue_script(
            'moksa-line-admin',
            MOKSA_LINE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            MOKSA_LINE_VERSION,
            true
        );
        
        wp_localize_script('moksa-line-admin', 'moksaLineAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('moksa_line_admin'),
        ));
        
        // Flex Editor script (only on flex message page)
        if (isset($_GET['page']) && $_GET['page'] === 'moksa-line-flexmessage') {
            wp_enqueue_script(
                'moksa-line-flex-editor',
                MOKSA_LINE_PLUGIN_URL . 'assets/js/flex-editor.js',
                array('jquery'),
                MOKSA_LINE_VERSION,
                true
            );
        }
    }
    
    /**
     * Render general settings page
     */
    public function render_settings_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/settings-general.php';
        echo '</div>';
    }
    
    /**
     * Render button settings page
     */
    public function render_button_settings() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/settings-button.php';
        echo '</div>';
    }
    
    /**
     * Render messaging settings page
     */
    public function render_messaging_settings() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/settings-messaging.php';
        echo '</div>';
    }
    
    /**
     * Render Rich Menu page
     */
    public function render_richmenu_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/richmenu-manager.php';
        echo '</div>';
    }
    
    /**
     * Render Flex Message page
     */
    public function render_flexmessage_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/flexmessage-editor.php';
        echo '</div>';
    }
    
    /**
     * Render documentation page
     */
    public function render_documentation_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/docs/getting-started.php';
        echo '</div>';
    }
    
    /**
     * Render tools page
     */
    public function render_tools_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/tools-page.php';
        echo '</div>';
    }
    
    /**
     * Add LINE User ID column to users table
     */
    public function add_line_user_column($columns) {
        $columns['line_user_id'] = __('LINE User ID', 'moksa-line-login');
        return $columns;
    }
    
    /**
     * Show LINE User ID in users table
     */
    public function show_line_user_column($value, $column_name, $user_id) {
        if ($column_name === 'line_user_id') {
            $db = Moksa_Line_Database::get_instance();
            $line_user = $db->get_line_user_by_wp_id($user_id);
            
            if ($line_user) {
                return '<code>' . esc_html($line_user->line_user_id) . '</code>';
            }
            return '—';
        }
        return $value;
    }
}
