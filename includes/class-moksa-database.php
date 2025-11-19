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
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'moksa_line_users';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            wp_user_id BIGINT(20) UNSIGNED NOT NULL,
            line_user_id VARCHAR(255) NOT NULL UNIQUE,
            display_name VARCHAR(255),
            picture_url TEXT,
            status_message TEXT,
            email VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX wp_user_id_index (wp_user_id),
            INDEX line_user_id_index (line_user_id)
        ) $charset_collate;";
        
        $table_messages = $wpdb->prefix . 'moksa_line_messages';
        $sql .= "CREATE TABLE IF NOT EXISTS $table_messages (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            message_type VARCHAR(50) NOT NULL,
            message_content LONGTEXT,
            status VARCHAR(20) DEFAULT 'pending',
            sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX user_id_index (user_id)
        ) $charset_collate;";
        
        $table_richmenus = $wpdb->prefix . 'moksa_line_richmenus';
        $sql .= "CREATE TABLE IF NOT EXISTS $table_richmenus (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            richmenu_id VARCHAR(255) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            chat_bar_text VARCHAR(100),
            size VARCHAR(20),
            areas LONGTEXT,
            image_url TEXT,
            is_default TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";
        
        $table_webhooks = $wpdb->prefix . 'moksa_line_webhooks';
        $sql .= "CREATE TABLE IF NOT EXISTS $table_webhooks (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50),
            source_type VARCHAR(50),
            source_id VARCHAR(255),
            message TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get LINE user by LINE user ID
     */
    public function get_line_user_by_line_id($line_user_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE line_user_id = %s",
            $line_user_id
        ));
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
