<?php
/**
 * LINE Messaging API Handler
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Messaging {
    
    private static $instance = null;
    private $api_base = 'https://api.line.me/v2/bot';
    private $data_base = 'https://api-data.line.me/v2/bot';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Get Channel Access Token
     */
    private function get_access_token() {
        return get_option('moksa_line_messaging_token');
    }
    
    /**
     * Send Push Message
     */
    public function push_message($to, $messages) {
        return $this->request('/message/push', 'POST', array(
            'to' => $to,
            'messages' => $messages
        ));
    }
    
    /**
     * Send Reply Message
     */
    public function reply_message($reply_token, $messages) {
        return $this->request('/message/reply', 'POST', array(
            'replyToken' => $reply_token,
            'messages' => $messages
        ));
    }
    
    /**
     * Send Multicast Message
     */
    public function multicast($to_array, $messages) {
        return $this->request('/message/multicast', 'POST', array(
            'to' => $to_array,
            'messages' => $messages
        ));
    }
    
    /**
     * Get Profile
     */
    public function get_profile($user_id) {
        return $this->request('/profile/' . $user_id, 'GET');
    }
    
    /**
     * Create Rich Menu
     */
    public function create_rich_menu($data) {
        return $this->request('/richmenu', 'POST', $data);
    }
    
    /**
     * Upload Rich Menu Image
     */
    public function upload_rich_menu_image($rich_menu_id, $image_path, $content_type) {
        $url = $this->data_base . '/richmenu/' . $rich_menu_id . '/content';
        $token = $this->get_access_token();
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => $content_type,
            ),
            'body' => file_get_contents($image_path),
            'method' => 'POST',
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Set Default Rich Menu
     */
    public function set_default_rich_menu($rich_menu_id) {
        return $this->request('/user/all/richmenu/' . $rich_menu_id, 'POST');
    }
    
    /**
     * Link Rich Menu to User
     */
    public function link_rich_menu_to_user($user_id, $rich_menu_id) {
        return $this->request('/user/' . $user_id . '/richmenu/' . $rich_menu_id, 'POST');
    }
    
    /**
     * Unlink Rich Menu from User
     */
    public function unlink_rich_menu_from_user($user_id) {
        return $this->request('/user/' . $user_id . '/richmenu', 'DELETE');
    }
    
    /**
     * Delete Rich Menu
     */
    public function delete_rich_menu($rich_menu_id) {
        return $this->request('/richmenu/' . $rich_menu_id, 'DELETE');
    }
    
    /**
     * Make API Request
     */
    private function request($endpoint, $method, $body = null) {
        $token = $this->get_access_token();
        
        if (empty($token)) {
            return new WP_Error('missing_token', __('Channel Access Token is missing.', 'moksa-line-login'));
        }
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ),
            'method' => $method,
        );
        
        if ($body !== null) {
            $args['body'] = json_encode($body);
        }
        
        $response = wp_remote_request($this->api_base . $endpoint, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (isset($data['message'])) {
            return new WP_Error('api_error', $data['message']);
        }
        
        return $data;
    }
}
