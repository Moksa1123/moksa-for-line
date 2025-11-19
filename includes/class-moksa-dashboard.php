<?php
/**
 * Dashboard Analytics
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Dashboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // No specific actions needed for now, data is pulled on render
    }
    
    /**
     * Get Total Friends (Users in DB)
     */
    public function get_total_friends() {
        $db = Moksa_Line_Database::get_instance();
        return $db->get_total_count();
    }
    
    /**
     * Get Message Stats (Sent messages)
     */
    public function get_message_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_messages';
        
        // Count sent messages
        $sent = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'sent'");
        
        // Count by type
        $by_type = $wpdb->get_results("SELECT message_type, COUNT(*) as count FROM $table_name GROUP BY message_type");
        
        return array(
            'total_sent' => $sent,
            'by_type' => $by_type
        );
    }
    
    /**
     * Get Recent Webhook Events
     */
    public function get_recent_events($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_webhooks';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d", $limit));
    }
}
