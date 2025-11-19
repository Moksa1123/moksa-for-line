<?php
/**
 * WooCommerce Integration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_WooCommerce {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // My Account Page
        add_filter('woocommerce_account_menu_items', array($this, 'add_account_menu_item'));
        add_action('init', array($this, 'add_account_endpoint'));
        add_action('woocommerce_account_line-account_endpoint', array($this, 'render_account_content'));
        
        // Login Forms
        add_action('woocommerce_login_form_start', array($this, 'render_login_button'));
        add_action('woocommerce_register_form_start', array($this, 'render_login_button'));
        
        // Checkout
        add_action('woocommerce_checkout_before_customer_details', array($this, 'render_checkout_login'));
        
        // Order Status Notifications
        add_action('woocommerce_order_status_changed', array($this, 'send_order_notification'), 10, 4);
        
        // Delayed Notification Handler
        add_action('moksa_line_send_delayed_order_notification', array($this, 'process_delayed_order_notification'), 10, 3);
        
        // Save LINE User ID to Order Meta
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_line_user_id_to_order'), 10, 2);
        
        // Show LINE User ID in Admin Order Details
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'show_line_user_id_in_admin_order'), 10, 1);
        
        // AJAX Save Template
        add_action('wp_ajax_moksa_line_save_order_template', array($this, 'ajax_save_order_template'));
    }
    
    /**
     * AJAX Save Order Template
     */
    public function ajax_save_order_template() {
        check_ajax_referer('moksa_line_save_template', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $template = isset($_POST['template']) ? wp_unslash($_POST['template']) : '';
        
        // Validate JSON
        if (json_decode($template) === null) {
            wp_send_json_error('Invalid JSON');
        }
        
        update_option('moksa_line_order_template', $template);
        wp_send_json_success();
    }
    
    /**
     * Get Default Order Template
     */
    public function get_default_order_template() {
        return array(
            'type' => 'flex',
            'altText' => 'Order #{{order_number}} Status Update: {{status_label}}',
            'contents' => array(
                'type' => 'bubble',
                'header' => array(
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => array(
                        array(
                            'type' => 'text',
                            'text' => 'Order Update',
                            'weight' => 'bold',
                            'color' => '#1DB446',
                            'size' => 'sm'
                        ),
                        array(
                            'type' => 'text',
                            'text' => '{{status_label}}',
                            'weight' => 'bold',
                            'size' => 'xxl',
                            'margin' => 'md',
                            'color' => '#111111'
                        ),
                        array(
                            'type' => 'text',
                            'text' => '#{{order_number}}',
                            'size' => 'xs',
                            'color' => '#aaaaaa',
                            'wrap' => true
                        )
                    )
                ),
                'body' => array(
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => array(
                        array(
                            'type' => 'box',
                            'layout' => 'vertical',
                            'margin' => 'xxl',
                            'spacing' => 'sm',
                            'contents' => array(
                                array(
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'contents' => array(
                                        array(
                                            'type' => 'text',
                                            'text' => 'Total',
                                            'size' => 'sm',
                                            'color' => '#555555',
                                            'flex' => 0
                                        ),
                                        array(
                                            'type' => 'text',
                                            'text' => '{{total}}',
                                            'size' => 'sm',
                                            'color' => '#111111',
                                            'align' => 'end'
                                        )
                                    )
                                ),
                                array(
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'contents' => array(
                                        array(
                                            'type' => 'text',
                                            'text' => 'Items',
                                            'size' => 'sm',
                                            'color' => '#555555',
                                            'flex' => 0
                                        ),
                                        array(
                                            'type' => 'text',
                                            'text' => '{{items_count}}',
                                            'size' => 'sm',
                                            'color' => '#111111',
                                            'align' => 'end'
                                        )
                                    )
                                )
                            )
                        ),
                        array(
                            'type' => 'separator',
                            'margin' => 'xxl'
                        )
                    )
                ),
                'footer' => array(
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'contents' => array(
                        array(
                            'type' => 'button',
                            'style' => 'primary',
                            'height' => 'sm',
                            'action' => array(
                                'type' => 'uri',
                                'label' => 'View Order',
                                'uri' => '{{view_order_url}}'
                            ),
                            'color' => '{{status_color}}'
                        )
                    )
                )
            )
        );
    }
    
    /**
     * Save LINE User ID to Order Meta
     */
    public function save_line_user_id_to_order($order_id, $data) {
        $user_id = get_current_user_id();
        if (!$user_id) return;
        
        $db = Moksa_Line_Database::get_instance();
        $line_user = $db->get_line_user_by_wp_id($user_id);
        
        if ($line_user) {
            update_post_meta($order_id, '_moksa_line_user_id', $line_user->line_user_id);
        }
    }
    
    /**
     * Show LINE User ID in Admin Order Details
     */
    public function show_line_user_id_in_admin_order($order) {
        $line_user_id = get_post_meta($order->get_id(), '_moksa_line_user_id', true);
        
        if ($line_user_id) {
            echo '<p><strong>' . __('LINE User ID', 'moksa-line-login') . ':</strong> <br>' . esc_html($line_user_id) . '</p>';
        }
    }
    
    /**
     * Send Order Notification via LINE
     */
    public function send_order_notification($order_id, $old_status, $new_status, $order) {
        $delay = (int) get_option('moksa_line_order_delay', 0);
        
        if ($delay > 0) {
            // Schedule delayed event
            wp_schedule_single_event(time() + $delay, 'moksa_line_send_delayed_order_notification', array($order_id, $old_status, $new_status));
        } else {
            // Send immediately
            $this->process_delayed_order_notification($order_id, $old_status, $new_status);
        }
    }
    
    /**
     * Process Delayed Order Notification
     */
    public function process_delayed_order_notification($order_id, $old_status, $new_status) {
        // Re-get order to ensure fresh data (e.g. tracking numbers)
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $user_id = $order->get_user_id();
        
        // Try to get LINE ID from Order Meta first (more reliable if user unbound later)
        $line_user_id = get_post_meta($order_id, '_moksa_line_user_id', true);
        
        if (!$line_user_id && $user_id) {
            // Fallback to current user binding
            $db = Moksa_Line_Database::get_instance();
            $line_user = $db->get_line_user_by_wp_id($user_id);
            if ($line_user) {
                $line_user_id = $line_user->line_user_id;
            }
        }
        
        if (!$line_user_id) {
            return;
        }
        
        $messaging = Moksa_Line_Messaging::get_instance();
        
        // Construct Flex Message
        $flex_message = $this->get_order_flex_message($order, $new_status);
        
        if ($flex_message) {
            $messaging->push_message($line_user_id, array($flex_message));
        }
    }
    
    /**
     * Generate Flex Message for Order
     */
    private function get_order_flex_message($order, $status) {
        $status_label = wc_get_order_status_name($status);
        $order_number = $order->get_order_number();
        $total = $order->get_formatted_order_total();
        $items_count = $order->get_item_count();
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name = $order->get_billing_last_name();
        $view_order_url = $order->get_view_order_url();
        
        // Extended WooCommerce Data
        $billing_phone = $order->get_billing_phone();
        $shipping_first_name = $order->get_shipping_first_name();
        $shipping_last_name = $order->get_shipping_last_name();
        $shipping_address = $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() . ', ' . $order->get_shipping_city();
        $payment_method = $order->get_payment_method_title();
        $shipping_method = $order->get_shipping_method();
        $customer_note = $order->get_customer_note();
        
        // 3rd Party / Logistics Data (ECPay, RY Tools, AST)
        // Try to find tracking number from common keys
        $tracking_number = $order->get_meta('_shipping_tracking_number', true); // AST
        if (!$tracking_number) $tracking_number = $order->get_meta('_ecpay_logistics_id', true); // ECPay
        if (!$tracking_number) $tracking_number = $order->get_meta('ry_tracking_number', true); // RY Tools (Generic guess)
        
        // Convenience Store Info
        $store_name = $order->get_meta('_shipping_store_name', true); // Common
        if (!$store_name) $store_name = $order->get_meta('_ecpay_receiver_store_name', true); // ECPay
        if (!$store_name) $store_name = $order->get_meta('ry_store_name', true);
        
        $store_address = $order->get_meta('_shipping_store_address', true);
        if (!$store_address) $store_address = $order->get_meta('_ecpay_receiver_store_address', true);
        
        // Color based on status
        $color = '#17c950'; // Default Green
        if (in_array($status, array('pending', 'on-hold'))) $color = '#ff9800';
        if (in_array($status, array('cancelled', 'failed', 'refunded'))) $color = '#ff334b';
        if ($status === 'completed') $color = '#06c755';
        
        // Get Template
        $template_json = get_option('moksa_line_order_template');
        if (empty($template_json)) {
            $template_arr = $this->get_default_order_template();
            $template_json = json_encode($template_arr);
        }
        
        // Replace Variables
        $replacements = array(
            '{{order_number}}' => $order_number,
            '{{status}}' => $status,
            '{{status_label}}' => $status_label,
            '{{status_color}}' => $color,
            '{{total}}' => strip_tags($total),
            '{{items_count}}' => (string)$items_count,
            '{{billing_name}}' => $billing_first_name . ' ' . $billing_last_name,
            '{{billing_phone}}' => $billing_phone,
            '{{shipping_name}}' => $shipping_first_name . ' ' . $shipping_last_name,
            '{{shipping_address}}' => $shipping_address,
            '{{payment_method}}' => $payment_method,
            '{{shipping_method}}' => $shipping_method,
            '{{customer_note}}' => $customer_note,
            '{{tracking_number}}' => $tracking_number ? $tracking_number : __('N/A', 'moksa-line-login'),
            '{{store_name}}' => $store_name ? $store_name : '',
            '{{store_address}}' => $store_address ? $store_address : '',
            '{{view_order_url}}' => $view_order_url
        );
        
        foreach ($replacements as $key => $value) {
            $template_json = str_replace($key, $value, $template_json);
        }
        
        return json_decode($template_json, true);
    }
    
    /**
     * Add "LINE Account" to My Account menu
     */
    public function add_account_menu_item($items) {
        // Insert before 'customer-logout'
        $logout = $items['customer-logout'];
        unset($items['customer-logout']);
        
        $items['line-account'] = __('LINE Account', 'moksa-line-login');
        $items['customer-logout'] = $logout;
        
        return $items;
    }
    
    /**
     * Register endpoint
     */
    public function add_account_endpoint() {
        add_rewrite_endpoint('line-account', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Render My Account content
     */
    public function render_account_content() {
        $user_id = get_current_user_id();
        $db = Moksa_Line_Database::get_instance();
        $line_user = $db->get_line_user_by_wp_id($user_id);
        
        ?>
        <h3><?php _e('LINE Account Connection', 'moksa-line-login'); ?></h3>
        
        <?php if ($line_user): ?>
            <div class="moksa-line-account-info" style="background: #f9f9f9; padding: 20px; border-radius: 8px; display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                <?php if ($line_user->picture_url): ?>
                    <img src="<?php echo esc_url($line_user->picture_url); ?>" 
                         alt="<?php echo esc_attr($line_user->display_name); ?>" 
                         style="width: 80px; height: 80px; border-radius: 50%;">
                <?php endif; ?>
                
                <div>
                    <h4 style="margin: 0 0 5px;"><?php echo esc_html($line_user->display_name); ?></h4>
                    <?php if ($line_user->status_message): ?>
                        <p style="margin: 0 0 10px; color: #666;"><?php echo esc_html($line_user->status_message); ?></p>
                    <?php endif; ?>
                    <p style="margin: 0; font-size: 0.9em; color: #999;">
                        <?php printf(__('Connected since: %s', 'moksa-line-login'), date_i18n(get_option('date_format'), strtotime($line_user->created_at))); ?>
                    </p>
                </div>
            </div>
            
            <button type="button" class="button" id="moksa-unbind-line" data-user-id="<?php echo esc_attr($user_id); ?>">
                <?php _e('Disconnect LINE Account', 'moksa-line-login'); ?>
            </button>
            
        <?php else: ?>
            <p><?php _e('Connect your LINE account to sign in easily and receive order updates.', 'moksa-line-login'); ?></p>
            
            <?php echo do_shortcode('[line_login_button text="' . __('Connect LINE Account', 'moksa-line-login') . '"]'); ?>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render login button on login forms
     */
    public function render_login_button() {
        echo '<div class="moksa-line-wc-login" style="margin-bottom: 20px;">';
        echo do_shortcode('[line_login_button]');
        echo '</div>';
        
        // Add separator
        echo '<div style="text-align: center; margin-bottom: 20px; position: relative;">
                <span style="background: #fff; padding: 0 10px; position: relative; z-index: 1; color: #777;">' . __('OR', 'moksa-line-login') . '</span>
                <div style="position: absolute; top: 50%; left: 0; width: 100%; height: 1px; background: #eee;"></div>
              </div>';
    }
    
    /**
     * Render login button on checkout
     */
    public function render_checkout_login() {
        if (is_user_logged_in()) {
            return;
        }
        
        echo '<div class="woocommerce-info">';
        echo __('Have a LINE account?', 'moksa-line-login') . ' ';
        echo '<a href="#" class="showlogin moksa-line-login-trigger">' . __('Click here to login with LINE', 'moksa-line-login') . '</a>';
        echo '</div>';
    }
}
