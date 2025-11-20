<?php
/**
 * Security Utilities
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Security {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('send_headers', array($this, 'add_security_headers'));
        add_action('rest_api_init', array($this, 'add_rest_security'));
    }
    
    /**
     * Add Security Headers
     */
    public function add_security_headers() {
        // Only add headers for plugin pages
        if (!is_admin() && !$this->is_plugin_request()) {
            return;
        }
        
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * Add REST API Security
     */
    public function add_rest_security() {
        // Add rate limiting to webhook endpoint
        add_filter('rest_pre_dispatch', array($this, 'rest_rate_limit'), 10, 3);
    }
    
    /**
     * Rate Limiting for REST API
     */
    public function rest_rate_limit($result, $server, $request) {
        $route = $request->get_route();
        
        // Apply rate limiting to webhook endpoint
        if (strpos($route, '/moksa-line/v1/webhook') !== false) {
            $ip = $this->get_client_ip();
            
            if (!$this->check_rate_limit('webhook_' . $ip, 60, 60)) {
                return new WP_Error(
                    'rate_limit_exceeded',
                    'Too many requests. Please try again later.',
                    array('status' => 429)
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Check Rate Limit
     * 
     * @param string $key Unique key for this rate limit
     * @param int $max_requests Maximum requests allowed
     * @param int $period Time period in seconds
     * @return bool True if within limit, false if exceeded
     */
    public function check_rate_limit($key, $max_requests = 60, $period = 60) {
        $transient_key = 'rate_limit_' . md5($key);
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            // First request in this period
            set_transient($transient_key, 1, $period);
            return true;
        }
        
        if ($requests >= $max_requests) {
            // Rate limit exceeded
            return false;
        }
        
        // Increment counter
        set_transient($transient_key, $requests + 1, $period);
        return true;
    }
    
    /**
     * Get Client IP Address
     */
    private function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Check if this is a plugin request
     */
    private function is_plugin_request() {
        return (
            (isset($_GET['action']) && strpos($_GET['action'], 'moksa_line') !== false) ||
            (isset($_POST['action']) && strpos($_POST['action'], 'moksa_line') !== false) ||
            (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'moksa-line') !== false)
        );
    }
    
    /**
     * Sanitize and validate JSON input
     * 
     * @param string $json JSON string
     * @param int $max_depth Maximum nesting depth
     * @return array|WP_Error Decoded array or error
     */
    public static function validate_json($json, $max_depth = 10) {
        if (empty($json)) {
            return new WP_Error('empty_json', 'JSON data is empty');
        }
        
        // Check JSON length (max 1MB)
        if (strlen($json) > 1048576) {
            return new WP_Error('json_too_large', 'JSON data exceeds maximum size');
        }
        
        $data = json_decode($json, true, $max_depth);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'Invalid JSON: ' . json_last_error_msg());
        }
        
        if (!is_array($data)) {
            return new WP_Error('invalid_json_format', 'JSON must be an object or array');
        }
        
        return $data;
    }
    
    /**
     * Log security events
     * 
     * @param string $event_type Type of security event
     * @param string $message Event message
     * @param array $context Additional context
     */
    public static function log_security_event($event_type, $message, $context = array()) {
        if (!WP_DEBUG) {
            return;
        }
        
        $log_message = sprintf(
            '[Moksa LINE Security] %s: %s',
            $event_type,
            $message
        );
        
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }
        
        error_log($log_message);
    }
}
