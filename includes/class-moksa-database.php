<?php
/**
 * Database operations for Moksa LINE Login
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Database {
    
    private static $instance = null;
    private $table_name;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'moksa_line_users';
    }
    
    /**
     * Create database tables
     */
    }
    
    /**
     * Get LINE user by WordPress user ID
     */
    public function get_line_user_by_wp_id($wp_user_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE wp_user_id = %d",
            $wp_user_id
        ));
    }
    
    /**
     * Create LINE user record
     */
    public function create_line_user($data) {
        global $wpdb;
        
        $defaults = array(
            'wp_user_id' => 0,
            'line_user_id' => '',
            'display_name' => '',
            'picture_url' => '',
            'status_message' => '',
            'email' => '',
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'wp_user_id' => $data['wp_user_id'],
                'line_user_id' => $data['line_user_id'],
                'display_name' => $data['display_name'],
                'picture_url' => $data['picture_url'],
                'status_message' => $data['status_message'],
                'email' => $data['email'],
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update LINE user record
     */
    public function update_line_user($line_user_id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if (isset($data['display_name'])) {
            $update_data['display_name'] = $data['display_name'];
            $format[] = '%s';
        }
        
        if (isset($data['picture_url'])) {
            $update_data['picture_url'] = $data['picture_url'];
            $format[] = '%s';
        }
        
        if (isset($data['status_message'])) {
            $update_data['status_message'] = $data['status_message'];
            $format[] = '%s';
        }
        
        if (isset($data['email'])) {
            $update_data['email'] = $data['email'];
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $this->table_name,
            $update_data,
            array('line_user_id' => $line_user_id),
            $format,
            array('%s')
        );
    }
    
    /**
     * Delete LINE user record
     */
    public function delete_line_user($line_user_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            array('line_user_id' => $line_user_id),
            array('%s')
        );
    }
    
    /**
     * Get all LINE users
     */
    public function get_all_line_users($limit = 100, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }
    
    /**
     * Get total LINE users count
     */
    public function get_total_count() {
        global $wpdb;
        
        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }
    
    /**
     * Check if LINE user exists
     */
    public function line_user_exists($line_user_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE line_user_id = %s",
            $line_user_id
        ));
        
        return $count > 0;
    }
}
