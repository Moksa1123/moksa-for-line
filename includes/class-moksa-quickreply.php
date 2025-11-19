<?php
/**
 * Quick Reply Manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_QuickReply {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_moksa_line_save_quick_reply', array($this, 'ajax_save_quick_reply'));
        add_action('wp_ajax_moksa_line_delete_quick_reply', array($this, 'ajax_delete_quick_reply'));
    }
    
    /**
     * Save Quick Reply
     */
    public function ajax_save_quick_reply() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $name = sanitize_text_field($_POST['name']);
        $items_json = stripslashes($_POST['items']);
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (empty($name) || empty($items_json)) {
            wp_send_json_error('Missing required fields');
        }
        
        // Validate JSON
        $items = json_decode($items_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON format');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_quick_replies';
        
        $data = array(
            'name' => $name,
            'items' => $items_json,
            'is_active' => 1
        );
        
        if ($id > 0) {
            $wpdb->update($table_name, $data, array('id' => $id));
        } else {
            $wpdb->insert($table_name, $data);
        }
        
        wp_send_json_success('Quick Reply saved successfully');
    }
    
    /**
     * Delete Quick Reply
     */
    public function ajax_delete_quick_reply() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $id = intval($_POST['id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_quick_replies';
        
        $wpdb->delete($table_name, array('id' => $id));
        
        wp_send_json_success('Quick Reply deleted successfully');
    }
    
    /**
     * Get All Quick Replies
     */
    public function get_all_quick_replies() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_quick_replies';
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    }
}
