<?php
/**
 * Imagemap Manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Imagemap {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_image_request'));
        add_action('wp_ajax_moksa_line_save_imagemap', array($this, 'ajax_save_imagemap'));
        add_action('wp_ajax_moksa_line_delete_imagemap', array($this, 'ajax_delete_imagemap'));
    }
    
    /**
     * Add Rewrite Rules for Imagemap images
     * URL: site.com/moksa-imagemap/{id}/{size}
     */
    public function add_rewrite_rules() {
        add_rewrite_rule('^moksa-imagemap/([0-9]+)/([0-9]+)/?$', 'index.php?moksa_imap_id=$matches[1]&moksa_imap_size=$matches[2]', 'top');
        add_rewrite_tag('%moksa_imap_id%', '([0-9]+)');
        add_rewrite_tag('%moksa_imap_size%', '([0-9]+)');
    }
    
    /**
     * Handle Image Request
     */
    public function handle_image_request() {
        global $wp_query;
        
        if (isset($wp_query->query_vars['moksa_imap_id']) && isset($wp_query->query_vars['moksa_imap_size'])) {
            $id = intval($wp_query->query_vars['moksa_imap_id']);
            $size = intval($wp_query->query_vars['moksa_imap_size']);
            
            $this->serve_image($id, $size);
            exit;
        }
    }
    
    /**
     * Serve Resized Image
     */
    private function serve_image($id, $size) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_imagemaps';
        $imap = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        
        if (!$imap || !$imap->image_id) {
            status_header(404);
            echo 'Imagemap not found';
            return;
        }
        
        $attachment_path = get_attached_file($imap->image_id);
        if (!file_exists($attachment_path)) {
            status_header(404);
            echo 'Image file not found';
            return;
        }
        
        // LINE requests specific sizes: 240, 300, 460, 700, 1040
        // We need to resize the original image to this width, maintaining aspect ratio is usually expected but LINE Imagemap is strict.
        // Actually LINE Imagemap images are usually square or fixed aspect ratio, but the key is the width.
        
        $editor = wp_get_image_editor($attachment_path);
        if (is_wp_error($editor)) {
            status_header(500);
            echo 'Image processing error';
            return;
        }
        
        $editor->resize($size, null, false); // Resize to width, auto height
        
        // Output
        $mime = 'image/jpeg'; // LINE usually expects JPEG or PNG. Let's force JPEG for consistency or detect.
        header('Content-Type: ' . $mime);
        $editor->stream($mime);
    }
    
    /**
     * Save Imagemap
     */
    public function ajax_save_imagemap() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $title = sanitize_text_field($_POST['title']);
        $alt_text = sanitize_text_field($_POST['alt_text']);
        $image_id = intval($_POST['image_id']);
        $actions = stripslashes($_POST['actions']); // JSON
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (empty($title) || empty($image_id)) {
            wp_send_json_error('Title and Image are required');
        }
        
        // Validate JSON
        json_decode($actions);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid Actions JSON');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_imagemaps';
        
        // Construct Base URL
        // We need to save the record first to get the ID if it's new
        
        $data = array(
            'title' => $title,
            'alt_text' => $alt_text,
            'image_id' => $image_id,
            'actions' => $actions,
            'base_width' => 1040, // Default max width
            'base_height' => 1040 // We might need to calculate this from image
        );
        
        // Get image dimensions
        $meta = wp_get_attachment_metadata($image_id);
        if ($meta) {
            $data['base_height'] = $meta['height'] * (1040 / $meta['width']); // Scale height to 1040 width
        }
        
        if ($id > 0) {
            $data['base_url'] = home_url('/moksa-imagemap/' . $id);
            $wpdb->update($table_name, $data, array('id' => $id));
        } else {
            // Insert with temp base url
            $data['base_url'] = ''; 
            $wpdb->insert($table_name, $data);
            $id = $wpdb->insert_id;
            
            // Update base url with real ID
            $base_url = home_url('/moksa-imagemap/' . $id);
            $wpdb->update($table_name, array('base_url' => $base_url), array('id' => $id));
        }
        
        // Flush rules to ensure new endpoint works (expensive, maybe do it only on activation or verify)
        flush_rewrite_rules();
        
        wp_send_json_success('Imagemap saved');
    }
    
    /**
     * Delete Imagemap
     */
    public function ajax_delete_imagemap() {
        check_ajax_referer('moksa_line_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $id = intval($_POST['id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_imagemaps';
        $wpdb->delete($table_name, array('id' => $id));
        
        wp_send_json_success('Imagemap deleted');
    }
    
    /**
     * Get All Imagemaps
     */
    public function get_all_imagemaps() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_imagemaps';
        // Security: Safe query - $table_name is internally defined, no user input
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    }
}
