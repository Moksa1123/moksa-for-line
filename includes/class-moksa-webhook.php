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
        }
        
        // Send welcome message if configured
        $greeting = get_option('moksa_line_greeting_message');
        if (!empty($greeting)) {
            $messaging->reply_message($event['replyToken'], array(
                array(
                    'type' => 'text',
                    'text' => $greeting
                )
            ));
        }
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
        $message = $event['message'];
        
        if ($message['type'] === 'text') {
            $text = $message['text'];
            $reply_token = $event['replyToken'];
            
            // Check Auto Reply Rules
            $autoreply = Moksa_Line_AutoReply::get_instance();
            $rule = $autoreply->find_match($text);
            
            if ($rule) {
                $messaging = Moksa_Line_Messaging::get_instance();
                $messages = array();
                
                if ($rule->reply_type === 'text') {
                    $messages[] = array(
                        'type' => 'text',
                        'text' => $rule->reply_data
                    );
                } elseif ($rule->reply_type === 'flex') {
                    $flex_content = json_decode($rule->reply_data, true);
                    if ($flex_content) {
                        $messages[] = $flex_content;
                    }
                } elseif ($rule->reply_type === 'quick_reply') {
                    // For Quick Reply Set, we need to attach it to a text message
                    // Since Quick Reply is a property of a message, not a message type itself
                    // We'll send a default text with the Quick Reply items
                    
                    $qr_manager = Moksa_Line_QuickReply::get_instance();
                    // Get the Quick Reply Set by ID (stored in reply_data)
                    global $wpdb;
                    $qr_table = $wpdb->prefix . 'moksa_line_quick_replies';
                    $qr_set = $wpdb->get_row($wpdb->prepare("SELECT * FROM $qr_table WHERE id = %d", intval($rule->reply_data)));
                    
                    if ($qr_set) {
                        $items = json_decode($qr_set->items, true);
                        if ($items) {
                            $messages[] = array(
                                'type' => 'text',
                                'text' => __('Please select an option:', 'moksa-line-login'),
                                'quickReply' => array(
                                    'items' => $items
                                )
                            );
                        }
                    }
                }
                
                if (!empty($messages)) {
                    $messaging->reply_message($reply_token, $messages);
                }
            }
        }
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
