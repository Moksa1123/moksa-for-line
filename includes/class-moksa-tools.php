<?php
/**
 * Tools Handler (Export/Import)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Tools {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'handle_export_users'));
        add_action('admin_init', array($this, 'handle_settings_export'));
        add_action('admin_init', array($this, 'handle_settings_import'));
    }
    
    /**
     * Handle User Export to CSV
     */
    public function handle_export_users() {
        if (!isset($_POST['moksa_line_action']) || $_POST['moksa_line_action'] !== 'export_users') {
            return;
        }
        
        check_admin_referer('moksa_line_tools_action');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $db = Moksa_Line_Database::get_instance();
        $users = $db->get_all_line_users(10000); // Export up to 10000 users
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=line-users-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, array('WP User ID', 'LINE User ID', 'Display Name', 'Status Message', 'Connected Date'));
        
        foreach ($users as $user) {
            fputcsv($output, array(
                $user->wp_user_id,
                $user->line_user_id,
                $user->display_name,
                $user->status_message,
                $user->created_at
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Handle Settings Export via JSON
     */
    public function handle_settings_export() {
        if (!isset($_POST['moksa_line_action']) || $_POST['moksa_line_action'] !== 'export_settings') {
            return;
        }
        
        check_admin_referer('moksa_line_tools_action');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $settings = array(
            'general' => array(
                'channel_id' => get_option('moksa_line_channel_id'),
                'channel_secret' => get_option('moksa_line_channel_secret'),
                'auto_register' => get_option('moksa_line_auto_register'),
                'sync_profile' => get_option('moksa_line_sync_profile'),
                'redirect_after_login' => get_option('moksa_line_redirect_after_login'),
            ),
            'button' => array(
                'text' => get_option('moksa_line_button_text'),
                'bg_color' => get_option('moksa_line_button_bg_color'),
                'text_color' => get_option('moksa_line_button_text_color'),
                'border_radius' => get_option('moksa_line_button_border_radius'),
                'width' => get_option('moksa_line_button_width'),
                'height' => get_option('moksa_line_button_height'),
            ),
            'messaging' => array(
                'token' => get_option('moksa_line_messaging_token'),
                'secret' => get_option('moksa_line_messaging_secret'),
                'add_friend_url' => get_option('moksa_line_add_friend_url'),
            )
        );
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=moksa-line-settings-' . date('Y-m-d') . '.json');
        
        echo json_encode($settings, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Handle Settings Import
     */
    public function handle_settings_import() {
        if (!isset($_POST['moksa_line_action']) || $_POST['moksa_line_action'] !== 'import_settings') {
            return;
        }
        
        check_admin_referer('moksa_line_tools_action');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        if (empty($_FILES['import_file']['tmp_name'])) {
            wp_die('Please select a file.');
        }
        
        $json = file_get_contents($_FILES['import_file']['tmp_name']);
        $settings = json_decode($json, true);
        
        if (!$settings) {
            wp_die('Invalid JSON file.');
        }
        
        // Import General
        if (isset($settings['general'])) {
            update_option('moksa_line_channel_id', sanitize_text_field($settings['general']['channel_id']));
            update_option('moksa_line_channel_secret', sanitize_text_field($settings['general']['channel_secret']));
            update_option('moksa_line_auto_register', sanitize_text_field($settings['general']['auto_register']));
            update_option('moksa_line_sync_profile', sanitize_text_field($settings['general']['sync_profile']));
            update_option('moksa_line_redirect_after_login', sanitize_text_field($settings['general']['redirect_after_login']));
        }
        
        // Import Button
        if (isset($settings['button'])) {
            update_option('moksa_line_button_text', sanitize_text_field($settings['button']['text']));
            update_option('moksa_line_button_bg_color', sanitize_text_field($settings['button']['bg_color']));
            update_option('moksa_line_button_text_color', sanitize_text_field($settings['button']['text_color']));
            update_option('moksa_line_button_border_radius', sanitize_text_field($settings['button']['border_radius']));
            update_option('moksa_line_button_width', sanitize_text_field($settings['button']['width']));
            update_option('moksa_line_button_height', sanitize_text_field($settings['button']['height']));
        }
        
        // Import Messaging
        if (isset($settings['messaging'])) {
            update_option('moksa_line_messaging_token', sanitize_textarea_field($settings['messaging']['token']));
            update_option('moksa_line_messaging_secret', sanitize_text_field($settings['messaging']['secret']));
            update_option('moksa_line_add_friend_url', sanitize_text_field($settings['messaging']['add_friend_url']));
        }
        
        wp_redirect(add_query_arg('imported', '1', wp_get_referer()));
        exit;
    }
}
