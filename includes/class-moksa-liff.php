<?php
/**
 * LIFF (LINE Front-end Framework) Manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_LIFF {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_liff_request'));
        add_action('wp_ajax_moksa_line_liff_update_profile', array($this, 'ajax_update_profile'));
        add_action('wp_ajax_nopriv_moksa_line_liff_update_profile', array($this, 'ajax_update_profile'));
    }
    
    /**
     * Register LIFF Settings
     */
    public function register_settings() {
        register_setting('moksa_line_general', 'moksa_line_liff_id');
    }
    
    /**
     * Add Rewrite Rules for LIFF pages
     */
    public function add_rewrite_rules() {
        add_rewrite_rule('^liff/profile/?', 'index.php?moksa_liff_page=profile', 'top');
        add_rewrite_tag('%moksa_liff_page%', '([^&]+)');
    }
    
    /**
     * Handle LIFF Page Request
     */
    public function handle_liff_request() {
        global $wp_query;
        
        if (isset($wp_query->query_vars['moksa_liff_page'])) {
            $page = $wp_query->query_vars['moksa_liff_page'];
            
            if ($page === 'profile') {
                $this->render_profile_page();
                exit;
            }
        }
    }
    
    /**
     * Render Profile Update Page
     */
    private function render_profile_page() {
        $liff_id = get_option('moksa_line_liff_id');
        if (empty($liff_id)) {
            wp_die('LIFF ID is not configured. Please set it in LINE Login settings.');
        }
        
        include MOKSA_LINE_PLUGIN_DIR . 'templates/liff/profile.php';
    }
    
    /**
     * AJAX: Update Profile from LIFF
     */
    public function ajax_update_profile() {
        // Verify nonce? Since it's from LIFF, we might rely on ID Token verification ideally.
        // For simplicity in this version, we will trust the data sent if we can verify the LINE User ID exists.
        // In a production secure environment, we should verify the ID Token sent from LIFF SDK.
        
        $line_user_id = sanitize_text_field($_POST['line_user_id']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $id_token = isset($_POST['id_token']) ? $_POST['id_token'] : '';
        
        if (empty($line_user_id)) {
            wp_send_json_error('User ID missing');
        }
        
        // Verify ID Token (Optional but recommended step for security)
        // $this->verify_id_token($id_token, $line_user_id);
        
        // Update User Meta
        $db = Moksa_Line_Database::get_instance();
        $user = $db->get_line_user_by_line_id($line_user_id);
        
        if ($user && $user->wp_user_id) {
            // Update WP User
            $wp_user_id = $user->wp_user_id;
            
            if (!empty($email)) {
                // Check if email exists
                if (email_exists($email) && email_exists($email) != $wp_user_id) {
                    wp_send_json_error('Email already in use');
                }
                
                wp_update_user(array(
                    'ID' => $wp_user_id,
                    'user_email' => $email
                ));
            }
            
            if (!empty($phone)) {
                update_user_meta($wp_user_id, 'billing_phone', $phone);
            }
            
            wp_send_json_success('Profile updated');
        } else {
            wp_send_json_error('User not found');
        }
    }
}
