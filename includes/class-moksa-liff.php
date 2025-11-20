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
        $line_user_id = sanitize_text_field($_POST['line_user_id']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $id_token = isset($_POST['id_token']) ? $_POST['id_token'] : '';
        
        if (empty($line_user_id)) {
            wp_send_json_error('User ID missing');
        }
        
        // Security: Verify ID Token is required
        if (empty($id_token)) {
            wp_send_json_error('ID Token required for security');
        }
        
        // Security: Verify ID Token matches the LINE User ID
        $verified = $this->verify_id_token($id_token, $line_user_id);
        if (!$verified) {
            wp_send_json_error('Invalid or expired ID Token');
        }
        
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
    
    /**
     * Verify LINE ID Token
     * 
     * @param string $id_token The ID token from LIFF SDK
     * @param string $expected_user_id The expected LINE user ID
     * @return bool True if token is valid and matches user ID
     */
    private function verify_id_token($id_token, $expected_user_id) {
        $channel_id = get_option('moksa_line_channel_id');
        
        if (empty($channel_id)) {
            return false;
        }
        
        // Verify ID Token with LINE API
        $response = wp_remote_post('https://api.line.me/oauth2/v2.1/verify', array(
            'body' => array(
                'id_token' => $id_token,
                'client_id' => $channel_id
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            error_log('LIFF ID Token verification failed: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('LIFF ID Token verification failed with status: ' . $status_code);
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Verify the token is valid and matches the expected user ID
        if (!isset($body['sub']) || $body['sub'] !== $expected_user_id) {
            error_log('LIFF ID Token user ID mismatch');
            return false;
        }
        
        // Verify the token is for our channel
        if (!isset($body['aud']) || $body['aud'] !== $channel_id) {
            error_log('LIFF ID Token channel ID mismatch');
            return false;
        }
        
        // Verify token is not expired
        if (!isset($body['exp']) || $body['exp'] < time()) {
            error_log('LIFF ID Token expired');
            return false;
        }
        
        return true;
    }
}
