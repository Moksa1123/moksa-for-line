<?php
/**
 * Notify History
 * 記錄通知發送歷史
 */

if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Notify_History {
    
    private static $instance = null;
    private static $table_name = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'moksa_notify_history';
        
        // 註冊啟用時創建資料表
        register_activation_hook(MOKSA_LINE_PLUGIN_BASENAME, array($this, 'create_table'));
        
        // 檢查並創建資料表（如果不存在）
        add_action('admin_init', array($this, 'maybe_create_table'));
    }
    
    /**
     * 創建資料表
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE " . self::$table_name . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            user_info varchar(255) DEFAULT NULL,
            order_id bigint(20) DEFAULT NULL,
            notify_id bigint(20) DEFAULT NULL,
            notify_type varchar(50) DEFAULT 'line',
            notify_content longtext,
            notify_time datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(50) DEFAULT 'success',
            error_message text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY notify_id (notify_id),
            KEY notify_time (notify_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        update_option('moksa_notify_db_version', MOKSA_LINE_VERSION);
    }
    
    /**
     * 檢查並創建資料表（如果不存在）
     */
    public function maybe_create_table() {
        if (get_option('moksa_notify_db_version') !== MOKSA_LINE_VERSION) {
            $this->create_table();
        }
    }
    
    /**
     * 插入歷史記錄
     * 
     * @param int $user_id 用戶 ID
     * @param string $user_info 用戶資訊
     * @param int $order_id 訂單 ID
     * @param int $notify_id 通知範本 ID
     * @param string $notify_type 通知類型
     * @param string $notify_content 通知內容
     * @param string $status 狀態
     * @param string $error_message 錯誤訊息
     * @return int|false 插入的記錄 ID 或 false
     */
    public static function insert($user_id, $user_info, $order_id, $notify_id, $notify_type = 'line', $notify_content = '', $status = 'success', $error_message = '') {
        global $wpdb;
        
        $result = $wpdb->insert(
            self::$table_name,
            array(
                'user_id' => $user_id,
                'user_info' => $user_info,
                'order_id' => $order_id,
                'notify_id' => $notify_id,
                'notify_type' => $notify_type,
                'notify_content' => $notify_content,
                'notify_time' => current_time('mysql'),
                'status' => $status,
                'error_message' => $error_message
            ),
            array('%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * 更新歷史記錄狀態
     * 
     * @param int $history_id 歷史記錄 ID
     * @param string $status 狀態
     * @param string $error_message 錯誤訊息
     * @return bool
     */
    public static function update($history_id, $status, $error_message = '') {
        global $wpdb;
        
        return $wpdb->update(
            self::$table_name,
            array(
                'status' => $status,
                'error_message' => $error_message
            ),
            array('id' => $history_id),
            array('%s', '%s'),
            array('%d')
        ) !== false;
    }
    
    /**
     * 獲取歷史記錄
     * 
     * @param array $args 查詢參數
     * @return array
     */
    public static function get_history($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'order_id' => null,
            'user_id' => null,
            'status' => null,
            'date_start' => null,
            'date_end' => null,
            'orderby' => 'notify_time',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if ($args['order_id']) {
            $where[] = 'order_id = %d';
            $where_values[] = $args['order_id'];
        }
        
        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }
        
        if ($args['status']) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if ($args['date_start']) {
            $where[] = 'notify_time >= %s';
            $where_values[] = $args['date_start'] . ' 00:00:00';
        }
        
        if ($args['date_end']) {
            $where[] = 'notify_time <= %s';
            $where_values[] = $args['date_end'] . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $where_clause = $wpdb->prepare($where_clause, $where_values);
        }
        
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $sql = "SELECT * FROM " . self::$table_name . " WHERE $where_clause ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        $sql = $wpdb->prepare($sql, $args['per_page'], $offset);
        
        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * 獲取歷史記錄總數
     * 
     * @param array $args 查詢參數
     * @return int
     */
    public static function get_count($args = array()) {
        global $wpdb;
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['order_id'])) {
            $where[] = 'order_id = %d';
            $where_values[] = $args['order_id'];
        }
        
        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['date_start'])) {
            $where[] = 'notify_time >= %s';
            $where_values[] = $args['date_start'] . ' 00:00:00';
        }
        
        if (!empty($args['date_end'])) {
            $where[] = 'notify_time <= %s';
            $where_values[] = $args['date_end'] . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $where_clause = $wpdb->prepare($where_clause, $where_values);
        }
        
        $sql = "SELECT COUNT(*) FROM " . self::$table_name . " WHERE $where_clause";
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * 刪除歷史記錄
     * 
     * @param int|array $ids 記錄 ID 或 ID 陣列
     * @return bool
     */
    public static function delete($ids) {
        global $wpdb;
        
        if (!is_array($ids)) {
            $ids = array($ids);
        }
        
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        $sql = $wpdb->prepare("DELETE FROM " . self::$table_name . " WHERE id IN ($placeholders)", $ids);
        
        return $wpdb->query($sql) !== false;
    }
}

// Initialize
Moksa_Notify_History::get_instance();

