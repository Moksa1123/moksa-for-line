<?php
/**
 * Webhook Handler
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Webhook {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_webhook_route'));
    }
    
    /**
     * Register Webhook Route
     */
    public function register_webhook_route() {
        register_rest_route('moksa-line/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Handle Webhook Request
     */
    public function handle_webhook($request) {
        $signature = $request->get_header('x-line-signature');
        $body = $request->get_body();
        $channel_secret = get_option('moksa_line_messaging_secret'); // Use Messaging API secret if different, or same as login
        
        if (empty($channel_secret)) {
            // Fallback to login channel secret if messaging secret is not set
            $channel_secret = get_option('moksa_line_channel_secret');
        }
        
        // Verify signature
        if (!$this->verify_signature($body, $channel_secret, $signature)) {
            return new WP_Error('invalid_signature', 'Invalid signature', array('status' => 403));
        }
        
        $events = json_decode($body, true);
        
        if (!isset($events['events'])) {
            return new WP_REST_Response(array('status' => 'ok'), 200);
        }
        
        foreach ($events['events'] as $event) {
            $this->process_event($event);
        }
        
        return new WP_REST_Response(array('status' => 'ok'), 200);
    }
    
    /**
     * Verify Signature
     */
    private function verify_signature($body, $secret, $signature) {
        $hash = hash_hmac('sha256', $body, $secret, true);
        $calculated_signature = base64_encode($hash);
        return hash_equals($calculated_signature, $signature);
    }
    
    /**
     * Process Event
     */
    private function process_event($event) {
        $type = $event['type'];
        $source = $event['source'];
        
        // Log event
        $this->log_event($event);
        
        switch ($type) {
            case 'follow':
                $this->handle_follow($event);
                break;
            case 'unfollow':
                $this->handle_unfollow($event);
                break;
            case 'message':
                $this->handle_message($event);
                break;
            case 'postback':
                $this->handle_postback($event);
                break;
        }
    }
    
    /**
     * Handle Follow Event
     */
    private function handle_follow($event) {
        $user_id = $event['source']['userId'];
        $messaging = Moksa_Line_Messaging::get_instance();
        
        // Get user profile
        $profile = $messaging->get_profile($user_id);
        
        if (!is_wp_error($profile)) {
            // We can update or create user here if needed
            // For now, we just ensure the user exists in our LINE users table if they linked before
            // Or we can just log it.
            // If we want to support "Add Friend" -> "Create Account", we need more logic.
        }
        
        // Send welcome message if configured
        // $messaging->reply_message($event['replyToken'], ...);
    }
    
    /**
     * Handle Unfollow Event
     */
    private function handle_unfollow($event) {
        $user_id = $event['source']['userId'];
        // Mark user as blocked in DB?
    }
    
    /**
     * Handle Message Event
     */
    private function handle_message($event) {
        // Handle auto-replies or keywords here
    }
    
    /**
     * Handle Postback Event
     */
    private function handle_postback($event) {
        // Handle rich menu actions etc.
    }
    
    /**
     * Log Event to DB
     */
    private function log_event($event) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'moksa_line_webhooks';
        
        $wpdb->insert(
            $table_name,
            array(
                'event_type' => $event['type'],
                'source_type' => $event['source']['type'],
                'source_id' => isset($event['source']['userId']) ? $event['source']['userId'] : '',
                'message' => json_encode($event),
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
}
