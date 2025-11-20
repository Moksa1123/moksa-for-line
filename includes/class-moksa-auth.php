<?php
/**
 * LINE Authentication Handler
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Auth {
    
    private static $instance = null;
    private $line_api_base = 'https://api.line.me';
    private $line_oauth_base = 'https://access.line.me/oauth2/v2.1';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_moksa_line_callback', array($this, 'handle_callback'));
        add_action('wp_ajax_nopriv_moksa_line_callback', array($this, 'handle_callback'));
        add_action('init', array($this, 'handle_logout'));
    }
    
    /**
     * Get LINE login URL
     */
    public function get_login_url($redirect_url = '') {
        $channel_id = get_option('moksa_line_channel_id');
        
        if (empty($channel_id)) {
            return '#';
        }
        
        $callback_url = admin_url('admin-ajax.php?action=moksa_line_callback');
        $state = wp_create_nonce('moksa_line_login');
        
        // Security: Use WordPress Transients instead of PHP Session
        // Generate a unique transient key
        $transient_key = 'moksa_line_state_' . wp_generate_password(32, false);
        
        // Store state and redirect URL in transient (expires in 10 minutes)
        set_transient($transient_key, array(
            'state' => $state,
            'redirect' => !empty($redirect_url) ? $redirect_url : home_url(),
            'created' => time()
        ), 600);
        
        $params = array(
            'response_type' => 'code',
            'client_id' => $channel_id,
            'redirect_uri' => $callback_url,
            'state' => $state,
            'scope' => 'profile openid email',
            'nonce' => $transient_key, // Pass transient key as nonce parameter
        );
        
        return $this->line_oauth_base . '/authorize?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback
     */
    public function handle_callback() {
        // Security: Get transient key from nonce parameter
        $transient_key = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
        
        if (empty($transient_key)) {
            wp_die(__('Invalid request. Please try again.', 'moksa-line-login'));
        }
        
        // Security: Get stored data from transient
        $stored_data = get_transient($transient_key);
        
        if ($stored_data === false) {
            wp_die(__('Session expired. Please try logging in again.', 'moksa-line-login'));
        }
        
        // Verify state parameter
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
        $stored_state = isset($stored_data['state']) ? $stored_data['state'] : '';
        
        if (empty($state) || $state !== $stored_state) {
            delete_transient($transient_key);
            wp_die(__('Invalid state parameter. Please try again.', 'moksa-line-login'));
        }
        
        // Get authorization code
        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        
        if (empty($code)) {
            delete_transient($transient_key);
            wp_die(__('Authorization code not received.', 'moksa-line-login'));
        }
        
        // Exchange code for access token
        $token_data = $this->get_access_token($code);
        
        if (is_wp_error($token_data)) {
            delete_transient($transient_key);
            wp_die($token_data->get_error_message());
        }
        
        // Get user profile
        $profile = $this->get_user_profile($token_data['access_token']);
        
        if (is_wp_error($profile)) {
            delete_transient($transient_key);
            wp_die($profile->get_error_message());
        }
        
        // Process user login/registration
        $user_id = $this->process_user($profile);
        
        if (is_wp_error($user_id)) {
            delete_transient($transient_key);
            wp_die($user_id->get_error_message());
        }
        
        // Log user in
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));
        
        // Redirect
        $redirect_url = isset($stored_data['redirect']) ? $stored_data['redirect'] : home_url();
        
        // Security: Delete transient after use
        delete_transient($transient_key);
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Exchange authorization code for access token
     */
    private function get_access_token($code) {
        $channel_id = get_option('moksa_line_channel_id');
        $channel_secret = get_option('moksa_line_channel_secret');
        $callback_url = admin_url('admin-ajax.php?action=moksa_line_callback');
        
        $response = wp_remote_post($this->line_oauth_base . '/token', array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $callback_url,
                'client_id' => $channel_id,
                'client_secret' => $channel_secret,
            ),
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('token_error', $body['error_description']);
        }
        
        return $body;
    }
    
    /**
     * Get user profile from LINE API
     */
    private function get_user_profile($access_token) {
        $response = wp_remote_get($this->line_api_base . '/v2/profile', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('profile_error', $body['message']);
        }
        
        return $body;
    }
    
    /**
     * Process user login/registration
     */
    private function process_user($profile) {
        $db = Moksa_Line_Database::get_instance();
        
        // Check if LINE user exists
        $line_user = $db->get_line_user_by_line_id($profile['userId']);
        
        if ($line_user) {
            // Update profile if sync is enabled
            if (get_option('moksa_line_sync_profile') === '1') {
                $db->update_line_user($profile['userId'], array(
                    'display_name' => isset($profile['displayName']) ? $profile['displayName'] : '',
                    'picture_url' => isset($profile['pictureUrl']) ? $profile['pictureUrl'] : '',
                    'status_message' => isset($profile['statusMessage']) ? $profile['statusMessage'] : '',
                ));
            }
            
            return $line_user->wp_user_id;
        }
        
        // Auto registration
        if (get_option('moksa_line_auto_register') !== '1') {
            return new WP_Error('registration_disabled', __('User registration is disabled.', 'moksa-line-login'));
        }
        
        // Create WordPress user
        $username = 'line_' . $profile['userId'];
        $email = isset($profile['email']) ? $profile['email'] : $username . '@line.local';
        $display_name = isset($profile['displayName']) ? $profile['displayName'] : $username;
        
        // Check if email exists
        if (email_exists($email)) {
            $email = $username . '@line.local';
        }
        
        $user_id = wp_create_user($username, wp_generate_password(16, true, true), $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Update user meta
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name,
            'first_name' => $display_name,
        ));
        
        // Save LINE profile picture as avatar
        if (isset($profile['pictureUrl'])) {
            update_user_meta($user_id, 'moksa_line_avatar', $profile['pictureUrl']);
        }
        
        // Create LINE user record
        $db->create_line_user(array(
            'wp_user_id' => $user_id,
            'line_user_id' => $profile['userId'],
            'display_name' => $display_name,
            'picture_url' => isset($profile['pictureUrl']) ? $profile['pictureUrl'] : '',
            'status_message' => isset($profile['statusMessage']) ? $profile['statusMessage'] : '',
            'email' => isset($profile['email']) ? $profile['email'] : '',
        ));
        
        return $user_id;
    }
    
    /**
     * Handle logout
     */
    public function handle_logout() {
        if (isset($_GET['moksa_line_logout']) && wp_verify_nonce($_GET['_wpnonce'], 'moksa_line_logout')) {
            wp_logout();
            $redirect = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url();
            wp_redirect($redirect);
            exit;
        }
    }
}
