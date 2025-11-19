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
        register_setting('moksa_line_general', 'moksa_line_redirect_after_login');
        
        // Button Settings
        register_setting('moksa_line_button', 'moksa_line_button_text');
        register_setting('moksa_line_button', 'moksa_line_button_bg_color');
        register_setting('moksa_line_button', 'moksa_line_button_text_color');
        register_setting('moksa_line_button', 'moksa_line_button_border_radius');
    
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
