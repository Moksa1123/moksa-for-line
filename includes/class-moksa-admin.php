<?php
/**
 * Admin Settings and Management
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Admin {
        register_setting('moksa_line_general', 'moksa_line_channel_id');
        register_setting('moksa_line_general', 'moksa_line_channel_secret');
        register_setting('moksa_line_general', 'moksa_line_auto_register');
        register_setting('moksa_line_general', 'moksa_line_sync_profile');
    
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
        $columns['line_user_id'] = __('LINE User ID', 'moksa-line-login');
<?php
/**
 * Admin Settings and Management
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Admin {
        register_setting('moksa_line_general', 'moksa_line_channel_id');
        register_setting('moksa_line_general', 'moksa_line_channel_secret');
        register_setting('moksa_line_general', 'moksa_line_auto_register');
        register_setting('moksa_line_general', 'moksa_line_sync_profile');
    
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
                add_submenu_page(
                        'moksa-line-login',
                        __('WooCommerce Carousel', 'moksa-line-login'),
                        __('WooCarousel', 'moksa-line-login'),
                        'manage_options',
                        'moksa-line-woocarousel',
                        array($this, 'render_woocarousel_page')
                    );

                add_submenu_page(
                        'moksa-line-login',
                        __('Order Notification', 'moksa-line-login'),
                        __('Order Notification', 'moksa-line-login'),
                        'manage_options',
                        'moksa-line-order-notification',
                        array($this, 'render_order_notification_page')
                    );
                
                add_submenu_page(
                        'moksa-line-login', '<code>' . esc_html($line_user->line_user_id) . '</code>';
            }
            return '—';
        }
        return $value;
    }
}
