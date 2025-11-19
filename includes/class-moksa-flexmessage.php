<?php
/**
 * Flex Message Handler
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_FlexMessage {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_moksa_line_send_flex', array($this, 'ajax_send_flex'));
    }
    
    /**
     * Send Flex Message via AJAX
     */
    public function ajax_send_flex() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $json = stripslashes($_POST['flex_json']);
        $alt_text = sanitize_text_field($_POST['alt_text']);
        $target_type = sanitize_text_field($_POST['target_type']); // all or specific
        $user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : array();
        
        if (empty($json) || empty($alt_text)) {
            wp_send_json_error('Missing required fields');
        }
        
        $flex_content = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON format');
        }
        
        $message = array(
            'type' => 'flex',
            'altText' => $alt_text,
            'contents' => $flex_content
        );
        
        $messaging = Moksa_Line_Messaging::get_instance();
        $db = Moksa_Line_Database::get_instance();
        
        if ($target_type === 'all') {
            // Get all LINE users (batching needed for large numbers, but simple for now)
            $users = $db->get_all_line_users(1000); // Limit to 1000 for safety
            $to = array();
            foreach ($users as $user) {
                $to[] = $user->line_user_id;
            }
            
            if (empty($to)) {
                wp_send_json_error('No users found');
            }
            
            // Multicast supports up to 500 users at once
            $chunks = array_chunk($to, 500);
            foreach ($chunks as $chunk) {
                $result = $messaging->multicast($chunk, array($message));
                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message());
                }
            }
            
        } else {
            // Specific users
            if (empty($user_ids)) {
                wp_send_json_error('No users selected');
            }
            
            $result = $messaging->multicast($user_ids, array($message));
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
        }
        
        // Log message
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_messages';
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => 0, // System/Broadcast
                'message_type' => 'flex',
                'message_content' => $json,
                'status' => 'sent',
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        wp_send_json_success('Flex Message sent successfully');
    }
}
