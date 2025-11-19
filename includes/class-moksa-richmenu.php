<?php
/**
 * Rich Menu Manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_RichMenu {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_moksa_line_create_richmenu', array($this, 'ajax_create_richmenu'));
        add_action('wp_ajax_moksa_line_delete_richmenu', array($this, 'ajax_delete_richmenu'));
        add_action('wp_ajax_moksa_line_set_default_richmenu', array($this, 'ajax_set_default_richmenu'));
    }
    
    /**
     * Create Rich Menu via AJAX
     */
    public function ajax_create_richmenu() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $name = sanitize_text_field($_POST['name']);
        $chat_bar_text = sanitize_text_field($_POST['chat_bar_text']);
        $size = sanitize_text_field($_POST['size']); // full or half
        $areas = json_decode(stripslashes($_POST['areas']), true);
        $image_id = intval($_POST['image_id']);
        
        if (empty($name) || empty($image_id)) {
            wp_send_json_error('Missing required fields');
        }
        
        $messaging = Moksa_Line_Messaging::get_instance();
        
        // 1. Create Rich Menu Object
        $rich_menu_data = array(
            'size' => array(
                'width' => 2500,
                'height' => ($size === 'half') ? 843 : 1686
            ),
            'selected' => true,
            'name' => $name,
            'chatBarText' => $chat_bar_text,
            'areas' => $areas
        );
        
        $result = $messaging->create_rich_menu($rich_menu_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        $rich_menu_id = $result['richMenuId'];
        
        // 2. Upload Image
        $image_path = get_attached_file($image_id);
        $content_type = get_post_mime_type($image_id);
        
        $upload_result = $messaging->upload_rich_menu_image($rich_menu_id, $image_path, $content_type);
        
        if (is_wp_error($upload_result)) {
            // Cleanup
            $messaging->delete_rich_menu($rich_menu_id);
            wp_send_json_error('Image upload failed: ' . $upload_result->get_error_message());
        }
        
        // 3. Save to DB
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_richmenus';
        
        $wpdb->insert(
            $table_name,
            array(
                'richmenu_id' => $rich_menu_id,
                'name' => $name,
                'chat_bar_text' => $chat_bar_text,
                'size' => $size,
                'areas' => json_encode($areas),
                'image_url' => wp_get_attachment_url($image_id),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        wp_send_json_success('Rich Menu created successfully');
    }
    
    /**
     * Delete Rich Menu via AJAX
     */
    public function ajax_delete_richmenu() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $id = intval($_POST['id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_richmenus';
        
        $rich_menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        
        if (!$rich_menu) {
            wp_send_json_error('Rich Menu not found');
        }
        
        // Delete from LINE
        $messaging = Moksa_Line_Messaging::get_instance();
        $messaging->delete_rich_menu($rich_menu->richmenu_id);
        
        // Delete from DB
        $wpdb->delete($table_name, array('id' => $id), array('%d'));
        
        wp_send_json_success('Rich Menu deleted');
    }
    
    /**
     * Set Default Rich Menu via AJAX
     */
    public function ajax_set_default_richmenu() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $id = intval($_POST['id']);
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_richmenus';
        
        $rich_menu = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        
        if (!$rich_menu) {
            wp_send_json_error('Rich Menu not found');
        }
        
        $messaging = Moksa_Line_Messaging::get_instance();
        $result = $messaging->set_default_rich_menu($rich_menu->richmenu_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Update DB flags
        $wpdb->update($table_name, array('is_default' => 0), array('is_default' => 1));
        $wpdb->update($table_name, array('is_default' => 1), array('id' => $id));
        
        wp_send_json_success('Default Rich Menu set');
    }
}
