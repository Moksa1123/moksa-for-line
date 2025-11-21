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
        // AJAX Get Template
        add_action('wp_ajax_moksa_line_get_order_template', array($this, 'ajax_get_order_template'));
    }
    
    /**
     * AJAX Save Order Template
     */
    public function ajax_save_order_template() {
        check_ajax_referer('moksa_line_save_template', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $template = isset($_POST['template']) ? wp_unslash($_POST['template']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'default';
        
        // Validate JSON
        if (json_decode($template) === null) {
            wp_send_json_error('無效的 JSON 格式');
        }
        
        if ($status === 'default') {
            update_option('moksa_line_order_template', $template);
        } else {
            update_option('moksa_line_order_template_' . $status, $template);
        }
        
        wp_send_json_success();
    }

    /**
     * AJAX Get Order Template
     */
    public function ajax_get_order_template() {
        check_ajax_referer('moksa_line_save_template', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'default';
        
        if ($status === 'default') {
            $template = get_option('moksa_line_order_template');
        } else {
            $template = get_option('moksa_line_order_template_' . $status);
        }

        if (empty($template)) {
            $template = json_encode($this->get_default_order_template($status));
        }
        
        wp_send_json_success(array('template' => $template));
    }
    
    /**
     * Get Default Order Template
     */
    public function get_default_order_template($status = 'default') {
        $color = '#06C755'; // Official LINE Green
        $title = '訂單狀態更新';
        
        if (in_array($status, array('pending', 'on-hold'))) {
            $color = '#ff9800';
            $title = '訂單待付款/處理中';
        }
        if (in_array($status, array('cancelled', 'failed', 'refunded'))) {
            $color = '#ff334b';
            $title = '訂單已取消/退款';
        }
        if ($status === 'completed') {
            $color = '#06c755';
            $title = '訂單已完成';
        }

        return array(
            'type' => 'flex',
            'altText' => '訂單 #{{order_number}} 狀態更新: {{status_label}}',
            'contents' => array(
                'type' => 'bubble',
                'header' => array(
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => array(
                        array(
                            'type' => 'text',
                            'text' => $title,
                            'weight' => 'bold',
                            'color' => $color,
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
                                            'text' => '總金額',
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
                                            'text' => '商品數量',
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
                                'label' => '查看訂單',
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
     * 改進：針對 processing 狀態添加延遲，確保物流編號已從物流商回傳並更新
     */
    public function send_order_notification($order_id, $old_status, $new_status, $order) {
        // 針對 processing 狀態的特殊延遲處理（因為物流編號需要等待物流商 API 回傳）
        $processing_delay = (int) get_option('moksa_line_order_processing_delay', 60); // 預設 60 秒
        $general_delay = (int) get_option('moksa_line_order_delay', 0);
        
        // 決定延遲時間
        $delay = 0;
        if ($new_status === 'processing') {
            // 處理中狀態使用專用延遲時間（等待物流編號回傳）
            $delay = $processing_delay;
        } elseif ($general_delay > 0) {
            // 其他狀態使用一般延遲時間
            $delay = $general_delay;
        }
        
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
     * 使用 CPT 系統，支援多個通知範本
     * 改進：針對 processing 狀態，檢查物流編號是否已更新，如果沒有則再次延遲
     */
    public function process_delayed_order_notification($order_id, $old_status, $new_status) {
        // Re-get order to ensure fresh data (e.g. tracking numbers)
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        // 改進：針對 processing 狀態，檢查物流編號是否已更新
        if ($new_status === 'processing') {
            $tracking_number = $this->get_tracking_number($order);
            $processing_delay = (int) get_option('moksa_line_order_processing_delay', 60);
            $max_retries = (int) get_option('moksa_line_order_processing_max_retries', 3); // 最多重試 3 次
            $retry_count = (int) get_post_meta($order_id, '_moksa_notify_retry_count', true);
            
            // 如果物流編號不存在且未超過重試次數，則再次延遲
            if (empty($tracking_number) && $retry_count < $max_retries) {
                $retry_count++;
                update_post_meta($order_id, '_moksa_notify_retry_count', $retry_count);
                
                // 再次延遲發送
                wp_schedule_single_event(
                    time() + $processing_delay,
                    'moksa_line_send_delayed_order_notification',
                    array($order_id, $old_status, $new_status)
                );
                
                return; // 等待下次重試
            }
            
            // 重置重試計數
            if ($retry_count > 0) {
                delete_post_meta($order_id, '_moksa_notify_retry_count');
            }
        }
        
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
        
        // 取得符合此訂單狀態的所有通知範本（包含規則檢查）
        $notify_ids = Moksa_Line_Order_Notify::get_notify_ids_for_status('wc-' . $new_status, $order);
        
        if (empty($notify_ids)) {
            // 也檢查 new-order 狀態
            if ($new_status === 'pending' || $new_status === 'processing') {
                $notify_ids = Moksa_Line_Order_Notify::get_notify_ids_for_status('wc-new-order', $order);
            }
        }
        
        if (empty($notify_ids)) {
            return;
        }
        
        $messaging = Moksa_Line_Messaging::get_instance();
        
        // 發送所有符合條件的通知範本（記錄歷史）
        foreach ($notify_ids as $notify_id) {
            $flex_message = $this->get_order_flex_message_from_notify($order, $new_status, $notify_id);
            
            if ($flex_message) {
                $notify_content = json_encode($flex_message, JSON_UNESCAPED_UNICODE);
                $billing_first_name = $order->get_billing_first_name();
                $billing_last_name = $order->get_billing_last_name();
                $billing_email = $order->get_billing_email();
                $user_info = trim($billing_first_name . ' ' . $billing_last_name) . ' (' . $billing_email . ')';
                
                // 記錄歷史
                $history_id = Moksa_Notify_History::insert(
                    $user_id,
                    $user_info,
                    $order_id,
                    $notify_id,
                    'line',
                    $notify_content,
                    'pending'
                );
                
                // 發送訊息
                $result = $messaging->push_message($line_user_id, array($flex_message));
                
                // 更新歷史記錄狀態
                if ($history_id) {
                    if ($result && isset($result['status']) && $result['status'] === 'success') {
                        Moksa_Notify_History::update($history_id, 'success');
                    } else {
                        $error_msg = isset($result['message']) ? $result['message'] : '發送失敗';
                        Moksa_Notify_History::update($history_id, 'failed', $error_msg);
                    }
                }
            }
        }
    }
    
    /**
     * Generate Flex Message for Order from Notify CPT
     * 從 CPT 範本生成訊息
     */
    private function get_order_flex_message_from_notify($order, $status, $notify_id) {
        // Get template from CPT
        $template_json = get_post_meta($notify_id, '_moksa_notify_content', true);
        
        if (empty($template_json)) {
            // Fallback to default
            return $this->get_order_flex_message($order, $status);
        }
        
        // Replace variables (使用現有的替換邏輯)
        return $this->replace_order_variables($template_json, $order, $status);
    }
    
    /**
     * Generate Flex Message for Order (Legacy - for backward compatibility)
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
        $tracking_number = $this->get_tracking_number($order);
        
        // Convenience Store Info
        $store_name = $order->get_meta('_shipping_store_name', true); // Common
        if (!$store_name) $store_name = $order->get_meta('_ecpay_receiver_store_name', true); // ECPay
        if (!$store_name) $store_name = $order->get_meta('ry_store_name', true);
        
        $store_address = $order->get_meta('_shipping_store_address', true);
        if (!$store_address) $store_address = $order->get_meta('_ecpay_receiver_store_address', true);
        
        // Color based on status
        $color = '#06C755'; // Official LINE Green
        if (in_array($status, array('pending', 'on-hold'))) $color = '#ff9800';
        if (in_array($status, array('cancelled', 'failed', 'refunded'))) $color = '#ff334b';
        if ($status === 'completed') $color = '#06c755';
        
        // Get Template - Try specific status first, then default
        $template_json = get_option('moksa_line_order_template_' . $status);
        if (empty($template_json)) {
            $template_json = get_option('moksa_line_order_template'); // Fallback to global default
        }
        if (empty($template_json)) {
            $template_arr = $this->get_default_order_template($status);
            $template_json = json_encode($template_arr);
        }
        
        // Replace Variables (使用統一的替換邏輯)
        return $this->replace_order_variables($template_json, $order, $status);
    }
    
    /**
     * Replace Order Variables in Template
     * 完整的參數替換系統
     */
    private function replace_order_variables($template_json, $order, $status) {
        // Get basic order data
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
        $tracking_number = $this->get_tracking_number($order);
        
        $store_name = $order->get_meta('_shipping_store_name', true);
        if (!$store_name) $store_name = $order->get_meta('_ecpay_receiver_store_name', true);
        if (!$store_name) $store_name = $order->get_meta('ry_store_name', true);
        
        $store_address = $order->get_meta('_shipping_store_address', true);
        if (!$store_address) $store_address = $order->get_meta('_ecpay_receiver_store_address', true);
        
        // Color based on status
        $color = '#06C755';
        if (in_array($status, array('pending', 'on-hold'))) $color = '#ff9800';
        if (in_array($status, array('cancelled', 'failed', 'refunded'))) $color = '#ff334b';
        if ($status === 'completed') $color = '#06c755';
        
        // Extended Data (學習 woocommerce-notify 的完整參數系統)
        $billing_email = $order->get_billing_email();
        $billing_address_1 = $order->get_billing_address_1();
        $billing_address_2 = $order->get_billing_address_2();
        $billing_city = $order->get_billing_city();
        $billing_postcode = $order->get_billing_postcode();
        $billing_country = $order->get_billing_country();
        $billing_state = $order->get_billing_state();
        $billing_company = $order->get_billing_company();
        
        $shipping_address_1 = $order->get_shipping_address_1();
        $shipping_address_2 = $order->get_shipping_address_2();
        $shipping_city = $order->get_shipping_city();
        $shipping_postcode = $order->get_shipping_postcode();
        $shipping_country = $order->get_shipping_country();
        $shipping_state = $order->get_shipping_state();
        $shipping_company = $order->get_shipping_company();
        $shipping_phone = $order->get_shipping_phone();
        
        $order_subtotal = number_format($order->get_subtotal(), 0);
        $order_date = $order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d') : '';
        $order_date_paid = $order->get_date_paid() ? $order->get_date_paid()->date_i18n('Y-m-d') : '';
        $order_date_completed = $order->get_date_completed() ? $order->get_date_completed()->date_i18n('Y-m-d') : '';
        
        // Get order items list
        $order_items = array();
        $order_items_nums = array();
        foreach ($order->get_items() as $item) {
            $order_items[] = $item->get_name();
            $order_items_nums[] = $item->get_name() . ' x ' . $item->get_quantity();
        }
        
        // Customer data
        $customer_id = $order->get_customer_id();
        $customer_email = $billing_email;
        $customer_username = '';
        if ($customer_id) {
            $customer = get_userdata($customer_id);
            if ($customer) {
                $customer_username = $customer->user_login;
            }
        }
        
        // Get latest order note for customer
        $order_notes = $order->get_customer_order_notes();
        $order_note_for_customer = $order_notes ? reset($order_notes)->comment_content : '';
        
        // Shop data
        $shop_title = get_bloginfo('name');
        $shop_tagline = get_bloginfo('description');
        $shop_url = get_bloginfo('url');
        $shop_admin_email = get_bloginfo('admin_email');
        $shop_shop_url = get_permalink(wc_get_page_id('shop'));
        
        // Replace Variables (學習 woocommerce-notify 的完整參數系統)
        $replacements = array(
            // 基本資訊
            '{{order_number}}' => $order_number,
            '{{order_status}}' => $status,
            '{{status}}' => $status,
            '{{status_label}}' => $status_label,
            '{{status_color}}' => $color,
            '{{total}}' => strip_tags($total),
            '{{order_total}}' => strip_tags($total),
            '{{order_subtotal}}' => $order_subtotal,
            '{{items_count}}' => (string)$items_count,
            '{{order_itemscount}}' => (string)$items_count,
            '{{order_items}}' => implode(' | ', $order_items),
            '{{order_items_nums}}' => implode("\\n", $order_items_nums),
            '{{view_order_url}}' => $view_order_url,
            '{{order_link}}' => $view_order_url,
            '{{order_date}}' => $order_date,
            '{{order_date_paid}}' => $order_date_paid,
            '{{order_date_completed}}' => $order_date_completed,
            
            // 客戶資訊
            '{{customer_email}}' => $customer_email,
            '{{customer_first_name}}' => $billing_first_name,
            '{{customer_last_name}}' => $billing_last_name,
            '{{customer_full_name}}' => trim($billing_first_name . ' ' . $billing_last_name),
            '{{customer_user_id}}' => (string)$customer_id,
            '{{customer_username}}' => $customer_username,
            '{{billing_name}}' => trim($billing_first_name . ' ' . $billing_last_name),
            '{{billing_first_name}}' => $billing_first_name,
            '{{billing_last_name}}' => $billing_last_name,
            '{{billing_phone}}' => $billing_phone,
            '{{billing_email}}' => $billing_email,
            '{{billing_address}}' => trim($billing_address_1 . ' ' . $billing_address_2),
            '{{billing_address_line_1}}' => $billing_address_1,
            '{{billing_address_line_2}}' => $billing_address_2,
            '{{billing_city}}' => $billing_city,
            '{{billing_postcode}}' => $billing_postcode,
            '{{billing_country}}' => $billing_country,
            '{{billing_state}}' => $billing_state,
            '{{billing_company}}' => $billing_company,
            
            // 收件資訊
            '{{shipping_name}}' => trim($shipping_first_name . ' ' . $shipping_last_name),
            '{{shipping_first_name}}' => $shipping_first_name,
            '{{shipping_last_name}}' => $shipping_last_name,
            '{{shipping_address}}' => trim($shipping_address_1 . ' ' . $shipping_address_2 . ', ' . $shipping_city),
            '{{shipping_address_line_1}}' => $shipping_address_1,
            '{{shipping_address_line_2}}' => $shipping_address_2,
            '{{shipping_city}}' => $shipping_city,
            '{{shipping_postcode}}' => $shipping_postcode,
            '{{shipping_country}}' => $shipping_country,
            '{{shipping_state}}' => $shipping_state,
            '{{shipping_company}}' => $shipping_company,
            '{{shipping_phone}}' => $shipping_phone,
            
            // 物流與支付
            '{{payment_method}}' => $payment_method,
            '{{payment_method_title}}' => $payment_method,
            '{{shipping_method}}' => $shipping_method,
            '{{shipping_method_title}}' => $shipping_method,
            '{{tracking_number}}' => $tracking_number ? $tracking_number : '無',
            '{{store_name}}' => $store_name ? $store_name : '',
            '{{store_address}}' => $store_address ? $store_address : '',
            '{{customer_note}}' => $customer_note ? $customer_note : '無',
            '{{order_note_for_customer}}' => $order_note_for_customer ? str_replace("\n", "\\n", $order_note_for_customer) : '',
            
            // 商店資訊
            '{{shop_title}}' => $shop_title,
            '{{shop_tagline}}' => $shop_tagline,
            '{{shop_url}}' => $shop_url,
            '{{shop_admin_email}}' => $shop_admin_email,
            '{{shop_shop_url}}' => $shop_shop_url,
        );
        
        // Replace all variables
        foreach ($replacements as $key => $value) {
            if ($template_json) {
                $template_json = str_replace($key, $value, $template_json);
            }
        }
        
        // 學習 woocommerce-notify：支援動態從訂單 meta 和用戶 meta 獲取參數
        // 匹配所有 {{xxx}} 格式的參數
        $pattern = '/\{\{([^}]+)\}\}/';
        preg_match_all($pattern, $template_json, $matches);
        if ($matches && !empty($matches[1])) {
            foreach ($matches[1] as $match) {
                $param_key = '{{' . $match . '}}';
                
                // 如果已經替換過，跳過
                if (isset($replacements[$param_key])) {
                    continue;
                }
                
                // 嘗試從訂單 meta 獲取
                $meta_value = $order->get_meta($match, true);
                if ($meta_value) {
                    $template_json = str_replace($param_key, is_array($meta_value) ? implode(', ', $meta_value) : $meta_value, $template_json);
                    continue;
                }
                
                // 嘗試從用戶 meta 獲取
                if ($customer_id) {
                    $user_meta = get_user_meta($customer_id, $match, true);
                    if ($user_meta) {
                        $data = is_array($user_meta) ? implode(', ', $user_meta) : $user_meta;
                        $template_json = str_replace($param_key, $data, $template_json);
                        continue;
                    }
                }
                
                // 如果找不到，替換為 '-'
                $template_json = str_replace($param_key, '-', $template_json);
            }
        }
        
        return json_decode($template_json, true);
    }
    
    /**
     * Get Tracking Number from Order
     * 改進：統一獲取物流編號的方法，支援多種物流外掛
     */
    private function get_tracking_number($order) {
        // 常見的物流編號 meta key
        $tracking_keys = array(
            '_shipping_tracking_number',      // AST (Advanced Shipment Tracking)
            '_ecpay_logistics_id',            // ECPay 物流
            'ry_tracking_number',             // RY Tools
            '_tracking_number',               // 通用
            '_wc_shipment_tracking_items',    // WooCommerce Shipment Tracking
            '_tracking_provider',             // 某些外掛使用
            '_tracking_link',                 // 某些外掛使用
        );
        
        // 嘗試從各種 meta key 獲取物流編號
        foreach ($tracking_keys as $key) {
            $value = $order->get_meta($key, true);
            
            // 如果是陣列（例如 WooCommerce Shipment Tracking）
            if (is_array($value) && !empty($value)) {
                // 嘗試從陣列中提取追蹤號碼
                if (isset($value[0]['tracking_number'])) {
                    return $value[0]['tracking_number'];
                }
                if (isset($value['tracking_number'])) {
                    return $value['tracking_number'];
                }
                // 如果陣列中有字串值，取第一個
                $first_value = reset($value);
                if (is_string($first_value) && !empty($first_value)) {
                    return $first_value;
                }
            }
            
            // 如果是字串且不為空
            if (is_string($value) && !empty($value)) {
                return $value;
            }
        }
        
        return '';
    }
    
    /**
     * Add "LINE Account" to My Account menu
     */
    public function add_account_menu_item($items) {
        // Insert before 'customer-logout'
        $logout = $items['customer-logout'];
        unset($items['customer-logout']);
        
        $items['line-account'] = 'LINE 帳號綁定';
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
        <h3>LINE 帳號綁定</h3>
        
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
                        <?php printf('已連結於: %s', date_i18n(get_option('date_format'), strtotime($line_user->created_at))); ?>
                    </p>
                </div>
            </div>
            
            <button type="button" class="button" id="moksa-unbind-line" data-user-id="<?php echo esc_attr($user_id); ?>">
                解除 LINE 綁定
            </button>
            
        <?php else: ?>
            <p>連結您的 LINE 帳號，以便快速登入並接收訂單更新通知。</p>
            
            <?php echo do_shortcode('[line_login_button text="連結 LINE 帳號"]'); ?>
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
                <span style="background: #fff; padding: 0 10px; position: relative; z-index: 1; color: #777;">或</span>
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
        echo '擁有 LINE 帳號嗎？ ';
        echo '<a href="#" class="showlogin moksa-line-login-trigger">點此使用 LINE 快速登入</a>';
        echo '</div>';
    }
}
