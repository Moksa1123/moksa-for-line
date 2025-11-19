<?php
/**
 * Auto Reply Manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_AutoReply {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_moksa_line_save_auto_reply', array($this, 'ajax_save_auto_reply'));
        add_action('wp_ajax_moksa_line_delete_auto_reply', array($this, 'ajax_delete_auto_reply'));
        add_action('wp_ajax_moksa_line_save_greeting', array($this, 'ajax_save_greeting'));
    }
    
    /**
     * Save Greeting Message
     */
    public function ajax_save_greeting() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $message = sanitize_textarea_field($_POST['message']);
        update_option('moksa_line_greeting_message', $message);
        
        wp_send_json_success('Greeting saved');
    }
    
    /**
     * Save Auto Reply Rule
     */
    public function ajax_save_auto_reply() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $keyword = sanitize_text_field($_POST['keyword']);
        $match_type = sanitize_text_field($_POST['match_type']);
        $reply_type = sanitize_text_field($_POST['reply_type']);
        $reply_data = ''; // Will be sanitized based on type
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (empty($keyword)) {
            wp_send_json_error('Keyword is required');
        }
        
        if ($reply_type === 'text') {
            $reply_data = sanitize_textarea_field($_POST['reply_data']);
        } elseif ($reply_type === 'flex') {
            // JSON data, basic validation
            $json = stripslashes($_POST['reply_data']);
            json_decode($json);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('Invalid JSON for Flex Message');
            }
            $reply_data = $json;
        } elseif ($reply_type === 'quick_reply') {
            $reply_data = intval($_POST['reply_data']); // ID of Quick Reply Set
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_auto_replies';
        
        $data = array(
            'keyword' => $keyword,
            'match_type' => $match_type,
            'reply_type' => $reply_type,
            'reply_data' => $reply_data,
            'is_active' => 1
        );
        
        if ($id > 0) {
            $wpdb->update($table_name, $data, array('id' => $id));
        } else {
            $wpdb->insert($table_name, $data);
        }
        
        wp_send_json_success('Auto Reply saved successfully');
    }
    
    /**
     * Delete Auto Reply Rule
     */
    public function ajax_delete_auto_reply() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $id = intval($_POST['id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_auto_replies';
        
        $wpdb->delete($table_name, array('id' => $id));
        
        wp_send_json_success('Auto Reply deleted successfully');
    }
    
    /**
     * Get All Rules
     */
    public function get_all_rules() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_auto_replies';
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    }
    
    /**
     * Find matching rule for a message
     */
    public function find_match($message_text) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_auto_replies';
        
        // Get all active rules
        $rules = $wpdb->get_results("SELECT * FROM $table_name WHERE is_active = 1");
        
        foreach ($rules as $rule) {
            if ($rule->match_type === 'exact') {
                if (trim($message_text) === $rule->keyword) {
                    return $rule;
                }
            } elseif ($rule->match_type === 'partial') {
                if (strpos($message_text, $rule->keyword) !== false) {
                    return $rule;
                }
            }
        }
        
        return null;
    }
}
