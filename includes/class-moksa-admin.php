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
        
        // Add LINE User ID to users table
        add_filter('manage_users_columns', array($this, 'add_line_user_column'));
        add_filter('manage_users_custom_column', array($this, 'show_line_user_column'), 10, 3);
    }
    
    /**
     * Add Admin Menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Moksa LINE Login',
            'LINE Login',
            'manage_options',
            'moksa-line-login',
            array($this, 'render_settings_page'),
            'dashicons-smartphone',
            50
        );
        
        add_submenu_page(
            'moksa-line-login',
            '一般設定',
            '一般設定',
            'manage_options',
            'moksa-line-login',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            '按鈕樣式設定',
            '按鈕樣式',
            'manage_options',
            'moksa-line-button-settings',
            array($this, 'render_button_settings')
        );
        
        add_submenu_page(
            'moksa-line-login',
            'Messaging API 設定',
            'Messaging API',
            'manage_options',
            'moksa-line-messaging-settings',
            array($this, 'render_messaging_settings')
        );
        
        add_submenu_page(
            'moksa-line-login',
            '圖文選單 (Rich Menu) 管理',
            '圖文選單',
            'manage_options',
            'moksa-line-rich-menu',
            array($this, 'render_richmenu_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            'Flex Message 編輯器',
            'Flex 編輯器',
            'manage_options',
            'moksa-line-flex-message',
            array($this, 'render_flex_editor_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            '快速回覆 (Quick Reply) 管理',
            '快速回覆',
            'manage_options',
            'moksa-line-quick-reply',
            array($this, 'render_quickreply_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            '自動回覆規則',
            '自動回覆',
            'manage_options',
            'moksa-line-auto-reply',
            array($this, 'render_autoreply_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            'WooCommerce 商品輪播',
            '商品輪播',
            'manage_options',
            'moksa-line-woocarousel',
            array($this, 'render_woocarousel_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            '訂單通知範本',
            '訂單通知',
            'manage_options',
            'moksa-line-order-notification',
            array($this, 'render_order_notification_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            '圖文訊息 (Imagemap) 管理',
            '圖文訊息',
            'manage_options',
            'moksa-line-imagemap',
            array($this, 'render_imagemap_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            '儀表板',
            '儀表板',
            'manage_options',
            'moksa-line-dashboard',
            array($this, 'render_dashboard_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            '使用說明',
            '使用說明',
            'manage_options',
            'moksa-line-docs',
            array($this, 'render_docs_page')
        );
        
        add_submenu_page(
            'moksa-line-login',
            '工具',
            '工具',
            'manage_options',
            'moksa-line-tools',
            array($this, 'render_tools_page')
        );
    }
    
    /**
     * Register Settings
     */
    public function register_settings() {
        // General Settings
        register_setting('moksa_line_general', 'moksa_line_channel_id');
        register_setting('moksa_line_general', 'moksa_line_channel_secret');
        register_setting('moksa_line_general', 'moksa_line_auto_register');
        register_setting('moksa_line_general', 'moksa_line_sync_profile');
        register_setting('moksa_line_general', 'moksa_line_redirect_after_login');
        register_setting('moksa_line_general', 'moksa_line_liff_id');
        register_setting('moksa_line_general', 'moksa_line_n8n_webhook_url');
        
        // Button Settings
        register_setting('moksa_line_button', 'moksa_line_button_text');
        register_setting('moksa_line_button', 'moksa_line_button_bg_color');
        register_setting('moksa_line_button', 'moksa_line_button_text_color');
        register_setting('moksa_line_button', 'moksa_line_button_border_radius');
        
        // Messaging Settings
        register_setting('moksa_line_messaging', 'moksa_line_messaging_token');
        register_setting('moksa_line_messaging', 'moksa_line_messaging_secret');
        register_setting('moksa_line_messaging', 'moksa_line_add_friend_url');
        register_setting('moksa_line_messaging', 'moksa_line_order_delay');
    }
    
    /**
     * Enqueue Admin Assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'moksa-line') === false) {
            return;
        }
        
        wp_enqueue_style('moksa-line-admin', MOKSA_LINE_PLUGIN_URL . 'assets/css/admin.css', array(), MOKSA_LINE_VERSION);
        wp_enqueue_script('moksa-line-admin', MOKSA_LINE_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), MOKSA_LINE_VERSION, true);
        
        // Enqueue Flex Simulator assets for Flex Editor AND Order Notification page
        $screen = get_current_screen();
        if ($screen && ($screen->id === 'moksa-line-login_page_moksa-line-flex-message' || $screen->id === 'moksa-line-login_page_moksa-line-order-notification')) {
            wp_enqueue_style('moksa-line-flex-simulator', MOKSA_LINE_PLUGIN_URL . 'assets/css/flex-simulator.css', array(), MOKSA_LINE_VERSION);
            wp_enqueue_script('moksa-line-flex-editor', MOKSA_LINE_PLUGIN_URL . 'assets/js/flex-editor.js', array('jquery'), MOKSA_LINE_VERSION, true);
        }
    }
    
    /**
     * Render settings page
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
     * Render Flex Editor page
     */
    public function render_flex_editor_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/flexmessage-editor.php';
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
     * Render Quick Reply page
     */
    public function render_quickreply_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/quick-reply-manager.php';
        echo '</div>';
    }
    
    /**
     * Render Auto Reply page
     */
    public function render_autoreply_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/auto-reply-manager.php';
        echo '</div>';
    }
    
    /**
     * Render WooCarousel page
     */
    public function render_woocarousel_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/woocarousel-generator.php';
        echo '</div>';
    }
    
    /**
     * Render Imagemap page
     */
    public function render_imagemap_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/imagemap-manager.php';
        echo '</div>';
    }
    
    /**
     * Render Dashboard page
     */
    public function render_dashboard_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/dashboard.php';
        echo '</div>';
    }
    
    /**
     * Render Docs page
     */
    public function render_docs_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/docs/getting-started.php';
        echo '</div>';
    }

    /**
     * Render Order Notification Editor page
     */
    public function render_order_notification_page() {
        echo '<div class="moksa-line-wrap">';
        include MOKSA_LINE_PLUGIN_DIR . 'templates/admin/order-notification-editor.php';
        echo '</div>';
    }
    
    /**
     * Add LINE User ID column to users table
     */
    public function add_line_user_column($columns) {
        $columns['line_user_id'] = 'LINE 使用者 ID';
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
