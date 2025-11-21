<?php
/**
 * Order Notify CPT
 * 
 * 管理訂單通知範本
 */

if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Order_Notify {
    
    private static $instance = null;
    public static $post_type = 'moksa-order-notify';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::$post_type, array($this, 'save_meta_boxes'), 10, 2);
        add_filter('manage_' . self::$post_type . '_posts_columns', array($this, 'add_list_columns'));
        add_action('manage_' . self::$post_type . '_posts_custom_column', array($this, 'render_list_columns'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_moksa_test_notify', array($this, 'ajax_test_notify'));
    }
    
    /**
     * Register Post Type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => __('訂單通知範本', 'moksa-line-login'),
            'singular_name'         => __('訂單通知範本', 'moksa-line-login'),
            'menu_name'             => __('訂單通知', 'moksa-line-login'),
            'add_new'               => __('新增範本', 'moksa-line-login'),
            'add_new_item'          => __('新增通知範本', 'moksa-line-login'),
            'edit_item'             => __('編輯通知範本', 'moksa-line-login'),
            'new_item'              => __('新通知範本', 'moksa-line-login'),
            'view_item'             => __('檢視通知範本', 'moksa-line-login'),
            'search_items'          => __('搜尋通知範本', 'moksa-line-login'),
            'not_found'             => __('找不到通知範本', 'moksa-line-login'),
            'not_found_in_trash'    => __('垃圾桶中找不到通知範本', 'moksa-line-login'),
            'all_items'             => __('所有通知範本', 'moksa-line-login'),
        );
        
        $args = array(
            'labels'                => $labels,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'moksa-line-login',
            'menu_position'         => 30,
            'menu_icon'             => 'dashicons-email-alt',
            'capability_type'       => 'post',
            'hierarchical'          => false,
            'supports'              => array('title', 'revisions'),
            'has_archive'           => false,
            'rewrite'               => false,
            'query_var'             => false,
            'show_in_rest'          => false,
        );
        
        register_post_type(self::$post_type, $args);
    }
    
    /**
     * Add Meta Boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'moksa_notify_trigger',
            __('觸發條件', 'moksa-line-login'),
            array($this, 'render_trigger_metabox'),
            self::$post_type,
            'normal',
            'high'
        );
        
        add_meta_box(
            'moksa_notify_content',
            __('Flex Message 內容', 'moksa-line-login'),
            array($this, 'render_content_metabox'),
            self::$post_type,
            'normal',
            'default'
        );
        
        add_meta_box(
            'moksa_notify_params',
            __('可用變數', 'moksa-line-login'),
            array($this, 'render_params_metabox'),
            self::$post_type,
            'side',
            'default'
        );
        
        add_meta_box(
            'moksa_notify_test',
            __('測試發送', 'moksa-line-login'),
            array($this, 'render_test_metabox'),
            self::$post_type,
            'side',
            'default'
        );
    }
    
    /**
     * Render Trigger Metabox
     * 支援複雜的觸發規則（支付方式、運送方式、訂單金額等）
     */
    public function render_trigger_metabox($post) {
        wp_nonce_field('moksa_notify_meta_box', 'moksa_notify_meta_box_nonce');
        
        $trigger_statuses = get_post_meta($post->ID, '_moksa_notify_trigger_statuses', true);
        if (!is_array($trigger_statuses)) {
            $trigger_statuses = array();
        }
        
        $trigger_rules = get_post_meta($post->ID, '_moksa_notify_trigger_rules', true);
        if (!is_array($trigger_rules)) {
            $trigger_rules = array();
        }
        
        $order_statuses = array();
        if (function_exists('wc_get_order_statuses')) {
            $order_statuses = wc_get_order_statuses();
        }
        
        // Get payment gateways
        $payment_gateways = array();
        if (function_exists('WC')) {
            $gateways = WC()->payment_gateways->payment_gateways();
            foreach ($gateways as $gateway) {
                if ($gateway->enabled === 'yes') {
                    $payment_gateways[$gateway->id] = $gateway->title;
                }
            }
        }
        
        // Get shipping methods
        $shipping_methods = array();
        if (function_exists('WC')) {
            $methods = WC()->shipping->get_shipping_methods();
            foreach ($methods as $method) {
                if ($method->enabled === 'yes') {
                    $shipping_methods[$method->id] = $method->method_title;
                }
            }
        }
        
        ?>
        <div class="moksa-notify-trigger-box">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="moksa_notify_trigger_statuses"><?php _e('觸發的訂單狀態', 'moksa-line-login'); ?></label>
                    </th>
                    <td>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 8px;">
                            <?php foreach ($order_statuses as $status_key => $status_label): ?>
                                <label style="display: flex; align-items: center; padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#06C755'; this.style.background='#f0fdf4'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f8fafc'">
                                    <input type="checkbox" name="moksa_notify_trigger_statuses[]" value="<?php echo esc_attr($status_key); ?>" <?php checked(in_array($status_key, $trigger_statuses)); ?> style="margin-right: 8px;">
                                    <span style="font-size: 13px; color: #334155;"><?php echo esc_html($status_label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="description" style="margin-top: 12px; font-size: 12px; color: #64748b;">
                            <?php _e('選擇當訂單變更為哪些狀態時，會觸發此通知範本。可以選擇多個狀態。', 'moksa-line-login'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php _e('進階觸發規則', 'moksa-line-login'); ?></label>
                    </th>
                    <td>
                        <div style="background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 12px 0; font-size: 13px; color: #64748b;">
                                <?php _e('可選：設定額外的觸發條件，例如特定支付方式、運送方式或訂單金額。', 'moksa-line-login'); ?>
                            </p>
                            
                            <div id="moksa-trigger-rules-container">
                                <?php if (!empty($trigger_rules)): ?>
                                    <?php foreach ($trigger_rules as $index => $rule): ?>
                                        <div class="moksa-rule-item" style="background: white; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 10px;">
                                            <div style="display: grid; grid-template-columns: 2fr 1fr 2fr auto; gap: 10px; align-items: center;">
                                                <select name="moksa_notify_trigger_rules[<?php echo $index; ?>][type]" class="moksa-rule-type" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                                    <option value=""><?php _e('選擇條件類型', 'moksa-line-login'); ?></option>
                                                    <option value="payment_method" <?php selected($rule['type'] ?? '', 'payment_method'); ?>><?php _e('支付方式', 'moksa-line-login'); ?></option>
                                                    <option value="shipping_method" <?php selected($rule['type'] ?? '', 'shipping_method'); ?>><?php _e('運送方式', 'moksa-line-login'); ?></option>
                                                    <option value="order_total" <?php selected($rule['type'] ?? '', 'order_total'); ?>><?php _e('訂單金額', 'moksa-line-login'); ?></option>
                                                </select>
                                                
                                                <select name="moksa_notify_trigger_rules[<?php echo $index; ?>][operator]" class="moksa-rule-operator" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                                    <option value="is" <?php selected($rule['operator'] ?? '', 'is'); ?>><?php _e('是', 'moksa-line-login'); ?></option>
                                                    <option value="is_not" <?php selected($rule['operator'] ?? '', 'is_not'); ?>><?php _e('不是', 'moksa-line-login'); ?></option>
                                                    <option value="gt" <?php selected($rule['operator'] ?? '', 'gt'); ?>><?php _e('大於', 'moksa-line-login'); ?></option>
                                                    <option value="gte" <?php selected($rule['operator'] ?? '', 'gte'); ?>><?php _e('大於等於', 'moksa-line-login'); ?></option>
                                                    <option value="lt" <?php selected($rule['operator'] ?? '', 'lt'); ?>><?php _e('小於', 'moksa-line-login'); ?></option>
                                                    <option value="lte" <?php selected($rule['operator'] ?? '', 'lte'); ?>><?php _e('小於等於', 'moksa-line-login'); ?></option>
                                                </select>
                                                
                                                <div class="moksa-rule-value-container">
                                                    <?php if (($rule['type'] ?? '') === 'payment_method'): ?>
                                                        <select name="moksa_notify_trigger_rules[<?php echo $index; ?>][value]" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                                            <option value=""><?php _e('選擇支付方式', 'moksa-line-login'); ?></option>
                                                            <?php foreach ($payment_gateways as $gateway_id => $gateway_title): ?>
                                                                <option value="<?php echo esc_attr($gateway_id); ?>" <?php selected($rule['value'] ?? '', $gateway_id); ?>><?php echo esc_html($gateway_title); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php elseif (($rule['type'] ?? '') === 'shipping_method'): ?>
                                                        <select name="moksa_notify_trigger_rules[<?php echo $index; ?>][value]" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                                            <option value=""><?php _e('選擇運送方式', 'moksa-line-login'); ?></option>
                                                            <?php foreach ($shipping_methods as $method_id => $method_title): ?>
                                                                <option value="<?php echo esc_attr($method_id); ?>" <?php selected($rule['value'] ?? '', $method_id); ?>><?php echo esc_html($method_title); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else: ?>
                                                        <input type="number" name="moksa_notify_trigger_rules[<?php echo $index; ?>][value]" value="<?php echo esc_attr($rule['value'] ?? ''); ?>" placeholder="<?php _e('輸入金額', 'moksa-line-login'); ?>" step="0.01" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <button type="button" class="button moksa-remove-rule" style="padding: 6px 12px; min-height: auto;">
                                                    <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <button type="button" id="moksa-add-rule" class="button button-secondary" style="margin-top: 10px;">
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 6px;">
                                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"/>
                                </svg>
                                <?php _e('新增規則', 'moksa-line-login'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var ruleIndex = <?php echo count($trigger_rules); ?>;
            var paymentGateways = <?php echo json_encode($payment_gateways); ?>;
            var shippingMethods = <?php echo json_encode($shipping_methods); ?>;
            
            // Add rule
            $('#moksa-add-rule').on('click', function() {
                var ruleHtml = '<div class="moksa-rule-item" style="background: white; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 10px;">' +
                    '<div style="display: grid; grid-template-columns: 2fr 1fr 2fr auto; gap: 10px; align-items: center;">' +
                    '<select name="moksa_notify_trigger_rules[' + ruleIndex + '][type]" class="moksa-rule-type" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">' +
                    '<option value=""><?php _e('選擇條件類型', 'moksa-line-login'); ?></option>' +
                    '<option value="payment_method"><?php _e('支付方式', 'moksa-line-login'); ?></option>' +
                    '<option value="shipping_method"><?php _e('運送方式', 'moksa-line-login'); ?></option>' +
                    '<option value="order_total"><?php _e('訂單金額', 'moksa-line-login'); ?></option>' +
                    '</select>' +
                    '<select name="moksa_notify_trigger_rules[' + ruleIndex + '][operator]" class="moksa-rule-operator" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">' +
                    '<option value="is"><?php _e('是', 'moksa-line-login'); ?></option>' +
                    '<option value="is_not"><?php _e('不是', 'moksa-line-login'); ?></option>' +
                    '<option value="gt"><?php _e('大於', 'moksa-line-login'); ?></option>' +
                    '<option value="gte"><?php _e('大於等於', 'moksa-line-login'); ?></option>' +
                    '<option value="lt"><?php _e('小於', 'moksa-line-login'); ?></option>' +
                    '<option value="lte"><?php _e('小於等於', 'moksa-line-login'); ?></option>' +
                    '</select>' +
                    '<div class="moksa-rule-value-container">' +
                    '<input type="text" name="moksa_notify_trigger_rules[' + ruleIndex + '][value]" placeholder="<?php _e('選擇條件類型後顯示', 'moksa-line-login'); ?>" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;" disabled>' +
                    '</div>' +
                    '<button type="button" class="button moksa-remove-rule" style="padding: 6px 12px; min-height: auto;">' +
                    '<svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>' +
                    '</button>' +
                    '</div>' +
                    '</div>';
                
                $('#moksa-trigger-rules-container').append(ruleHtml);
                ruleIndex++;
            });
            
            // Remove rule
            $(document).on('click', '.moksa-remove-rule', function() {
                $(this).closest('.moksa-rule-item').remove();
            });
            
            // Update rule value container based on type
            $(document).on('change', '.moksa-rule-type', function() {
                var $container = $(this).closest('.moksa-rule-item').find('.moksa-rule-value-container');
                var type = $(this).val();
                var operator = $(this).closest('.moksa-rule-item').find('.moksa-rule-operator').val();
                var namePrefix = $(this).attr('name').replace('[type]', '');
                
                var html = '';
                if (type === 'payment_method') {
                    html = '<select name="' + namePrefix + '[value]" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;"><option value=""><?php _e('選擇支付方式', 'moksa-line-login'); ?></option>';
                    for (var id in paymentGateways) {
                        html += '<option value="' + id + '">' + paymentGateways[id] + '</option>';
                    }
                    html += '</select>';
                } else if (type === 'shipping_method') {
                    html = '<select name="' + namePrefix + '[value]" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;"><option value=""><?php _e('選擇運送方式', 'moksa-line-login'); ?></option>';
                    for (var id in shippingMethods) {
                        html += '<option value="' + id + '">' + shippingMethods[id] + '</option>';
                    }
                    html += '</select>';
                } else if (type === 'order_total') {
                    html = '<input type="number" name="' + namePrefix + '[value]" placeholder="<?php _e('輸入金額', 'moksa-line-login'); ?>" step="0.01" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">';
                } else {
                    html = '<input type="text" name="' + namePrefix + '[value]" placeholder="<?php _e('選擇條件類型後顯示', 'moksa-line-login'); ?>" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;" disabled>';
                }
                
                $container.html(html);
                
                // Update operator options based on type
                var $operator = $(this).closest('.moksa-rule-item').find('.moksa-rule-operator');
                if (type === 'order_total') {
                    $operator.html('<option value="gt"><?php _e('大於', 'moksa-line-login'); ?></option>' +
                        '<option value="gte"><?php _e('大於等於', 'moksa-line-login'); ?></option>' +
                        '<option value="eq"><?php _e('等於', 'moksa-line-login'); ?></option>' +
                        '<option value="lte"><?php _e('小於等於', 'moksa-line-login'); ?></option>' +
                        '<option value="lt"><?php _e('小於', 'moksa-line-login'); ?></option>');
                } else {
                    $operator.html('<option value="is"><?php _e('是', 'moksa-line-login'); ?></option>' +
                        '<option value="is_not"><?php _e('不是', 'moksa-line-login'); ?></option>');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render Content Metabox
     */
    public function render_content_metabox($post) {
        $content = get_post_meta($post->ID, '_moksa_notify_content', true);
        if (empty($content)) {
            // 預設範本
            $woocommerce = Moksa_Line_WooCommerce::get_instance();
            $default_template = $woocommerce->get_default_order_template('default');
            $content = json_encode($default_template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        
        ?>
        <div class="moksa-notify-content-box">
            <div class="moksa-line-simulator-guide" style="background: linear-gradient(135deg, #06C755 0%, #05B048 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(6, 199, 85, 0.3);">
                <div style="display: flex; align-items: center; margin-bottom: 12px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 10px;">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <div>
                        <h3 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 600;">使用 LINE 官方 Flex Message Simulator</h3>
                        <p style="margin: 0; font-size: 13px; opacity: 0.95;">建議使用 LINE 官方的 Flex Message Simulator 來設計訊息。</p>
                    </div>
                </div>
                <a href="https://developers.line.biz/flex-simulator/" target="_blank" class="button button-primary" style="background: white; color: #06C755; border: none; font-weight: 600; padding: 10px 20px; text-decoration: none; display: inline-block; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 6px;">
                        <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                        <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                    </svg>
                    前往 LINE Flex Message Simulator
                </a>
            </div>
            
            <div style="margin-bottom: 10px;">
                <label for="moksa_notify_content" style="display: block; margin-bottom: 8px; font-weight: 600; color: #334155;">
                    <?php _e('JSON 範本', 'moksa-line-login'); ?>
                    <span id="moksa-notify-content-status" style="margin-left: 12px; font-size: 12px; font-weight: normal;"></span>
                </label>
                <textarea id="moksa_notify_content" name="moksa_notify_content" rows="25" style="width: 100%; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; resize: vertical;"><?php echo esc_textarea($content); ?></textarea>
                <p class="description" style="margin-top: 8px; font-size: 12px; color: #64748b;">
                    💡 提示：在 LINE Flex Message Simulator 中設計好訊息後，點擊右上角的「View as JSON」按鈕，複製 JSON 並貼上於此。
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Params Metabox
     */
    public function render_params_metabox($post) {
        $variables = array(
            '基本資訊' => array(
                '{{order_number}}' => '訂單編號',
                '{{status_label}}' => '訂單狀態名稱',
                '{{order_status}}' => '訂單狀態代碼',
                '{{total}}' => '訂單總金額',
                '{{order_subtotal}}' => '訂單小計',
                '{{items_count}}' => '商品數量',
                '{{order_items}}' => '商品列表',
                '{{order_items_nums}}' => '商品列表（含數量）',
                '{{view_order_url}}' => '訂單連結',
                '{{order_date}}' => '訂單日期',
            ),
            '客戶資訊' => array(
                '{{customer_email}}' => '客戶電子郵件',
                '{{customer_full_name}}' => '客戶全名',
                '{{billing_name}}' => '帳單姓名',
                '{{billing_phone}}' => '帳單電話',
                '{{billing_address}}' => '帳單地址',
            ),
            '收件資訊' => array(
                '{{shipping_name}}' => '收件姓名',
                '{{shipping_address}}' => '收件地址',
                '{{shipping_city}}' => '收件城市',
            ),
            '物流與支付' => array(
                '{{payment_method}}' => '付款方式',
                '{{shipping_method}}' => '運送方式',
                '{{tracking_number}}' => '物流追蹤碼',
            ),
        );
        
        ?>
        <div class="moksa-notify-params-box" style="max-height: calc(100vh - 300px); overflow-y: auto;">
            <p class="description" style="margin-bottom: 12px; font-size: 12px;">點擊變數即可複製</p>
            <?php foreach ($variables as $category => $vars): ?>
                <div class="moksa-params-toggle" style="margin-bottom: 10px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff;">
                    <div class="moksa-params-toggle-header" style="padding: 10px 12px; background: #f8fafc; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center;" onclick="this.parentElement.classList.toggle('expanded');">
                        <h3 style="margin: 0; font-size: 12px; font-weight: 600; color: #334155;"><?php echo esc_html($category); ?></h3>
                        <svg class="toggle-indicator" width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="color: #64748b; transition: transform 0.2s;"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="moksa-params-toggle-content" style="display: none; padding: 8px;">
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <?php foreach ($vars as $var => $desc): ?>
                                <li style="margin-bottom: 4px;">
                                    <button type="button" class="moksa-param-btn" data-clipboard-text="<?php echo esc_attr($var); ?>" style="width: 100%; text-align: left; padding: 6px 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; transition: all 0.2s; font-size: 11px; display: flex; justify-content: space-between; align-items: center;" onmouseover="this.style.borderColor='#06C755'; this.style.background='#f0fdf4'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#fff'">
                                        <code style="font-size: 10px; color: #06C755; font-weight: 600; background: transparent; padding: 0;"><?php echo esc_html($var); ?></code>
                                        <span style="font-size: 10px; color: #64748b; margin-left: 8px; flex: 1; text-align: right;"><?php echo esc_html($desc); ?></span>
                                        <span class="copy-tooltip" style="display: none; font-size: 9px; color: #06C755; margin-left: 8px; font-weight: 600;">已複製！</span>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Save Meta Boxes
     */
    public function save_meta_boxes($post_id, $post) {
        // Check nonce
        if (!isset($_POST['moksa_notify_meta_box_nonce']) || !wp_verify_nonce($_POST['moksa_notify_meta_box_nonce'], 'moksa_notify_meta_box')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save trigger statuses
        if (isset($_POST['moksa_notify_trigger_statuses'])) {
            $statuses = array_map('sanitize_text_field', $_POST['moksa_notify_trigger_statuses']);
            update_post_meta($post_id, '_moksa_notify_trigger_statuses', $statuses);
        } else {
            delete_post_meta($post_id, '_moksa_notify_trigger_statuses');
        }
        
        // Save trigger rules
        if (isset($_POST['moksa_notify_trigger_rules']) && is_array($_POST['moksa_notify_trigger_rules'])) {
            $rules = array();
            foreach ($_POST['moksa_notify_trigger_rules'] as $rule) {
                if (!empty($rule['type']) && !empty($rule['operator']) && !empty($rule['value'])) {
                    $rules[] = array(
                        'type' => sanitize_text_field($rule['type']),
                        'operator' => sanitize_text_field($rule['operator']),
                        'value' => sanitize_text_field($rule['value'])
                    );
                }
            }
            update_post_meta($post_id, '_moksa_notify_trigger_rules', $rules);
        } else {
            delete_post_meta($post_id, '_moksa_notify_trigger_rules');
        }
        
        // Save content
        if (isset($_POST['moksa_notify_content'])) {
            $content = sanitize_textarea_field($_POST['moksa_notify_content']);
            // Validate JSON
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                update_post_meta($post_id, '_moksa_notify_content', $content);
            }
        }
    }
    
    /**
     * Add List Columns
     * 改進：添加統計資訊（woocommerce-notify 缺少的功能）
     */
    public function add_list_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __('範本名稱', 'moksa-line-login');
        $new_columns['trigger_statuses'] = __('觸發狀態', 'moksa-line-login');
        $new_columns['statistics'] = __('統計', 'moksa-line-login');
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }
    
    /**
     * Render List Columns
     * 改進：顯示統計資訊（woocommerce-notify 缺少的功能）
     */
    public function render_list_columns($column, $post_id) {
        if ($column === 'trigger_statuses') {
            $statuses = get_post_meta($post_id, '_moksa_notify_trigger_statuses', true);
            if (is_array($statuses) && !empty($statuses)) {
                $status_labels = array();
                if (function_exists('wc_get_order_statuses')) {
                    $all_statuses = wc_get_order_statuses();
                    foreach ($statuses as $status) {
                        if (isset($all_statuses[$status])) {
                            $status_labels[] = '<span style="display: inline-block; padding: 4px 10px; background: #f0fdf4; color: #06C755; border-radius: 12px; font-size: 11px; font-weight: 500; border: 1px solid #06C755; margin-right: 4px; margin-bottom: 4px;">' . esc_html($all_statuses[$status]) . '</span>';
                        }
                    }
                }
                echo implode('', $status_labels);
            } else {
                echo '<span style="color: #94a3b8; font-size: 12px;">未設定</span>';
            }
        }
        
        if ($column === 'statistics') {
            // 獲取統計資訊
            $stats = $this->get_notify_statistics($post_id);
            
            echo '<div style="display: flex; flex-direction: column; gap: 6px; font-size: 12px;">';
            echo '<div style="display: flex; align-items: center; gap: 6px;">';
            echo '<span style="color: #06C755; font-weight: 600;">✓</span>';
            echo '<span style="color: #334155;">成功: <strong>' . number_format($stats['success']) . '</strong></span>';
            echo '</div>';
            echo '<div style="display: flex; align-items: center; gap: 6px;">';
            echo '<span style="color: #ef4444; font-weight: 600;">✗</span>';
            echo '<span style="color: #334155;">失敗: <strong>' . number_format($stats['failed']) . '</strong></span>';
            echo '</div>';
            echo '<div style="display: flex; align-items: center; gap: 6px;">';
            echo '<span style="color: #64748b; font-weight: 600;">📊</span>';
            echo '<span style="color: #334155;">總計: <strong>' . number_format($stats['total']) . '</strong></span>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Get Notify Statistics
     * 改進：獲取通知統計資訊（woocommerce-notify 缺少的功能）
     */
    private function get_notify_statistics($notify_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'moksa_notify_history';
        
        $stats = array(
            'success' => 0,
            'failed' => 0,
            'total' => 0
        );
        
        // 檢查資料表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $success = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE notify_id = %d AND status = 'success'",
                $notify_id
            ));
            
            $failed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE notify_id = %d AND status = 'failed'",
                $notify_id
            ));
            
            $stats['success'] = intval($success);
            $stats['failed'] = intval($failed);
            $stats['total'] = $stats['success'] + $stats['failed'];
        }
        
        return $stats;
    }
    
    /**
     * Enqueue Scripts
     */
    public function enqueue_scripts($hook) {
        global $post_type;
        
        if ($post_type !== self::$post_type) {
            return;
        }
        
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_script('jquery');
            ?>
            <script>
            jQuery(document).ready(function($) {
                var $contentTextarea = $('#moksa_notify_content');
                var $statusSpan = $('#moksa-notify-content-status');
                
                // Auto validate JSON
                var validateTimeout;
                $contentTextarea.on('input', function() {
                    clearTimeout(validateTimeout);
                    validateTimeout = setTimeout(function() {
                        var jsonStr = $contentTextarea.val().trim();
                        if (jsonStr) {
                            try {
                                JSON.parse(jsonStr);
                                $statusSpan.text('✓ JSON 格式正確').css('color', '#06C755');
                            } catch (e) {
                                $statusSpan.text('✗ JSON 格式錯誤').css('color', '#ef4444');
                            }
                        } else {
                            $statusSpan.text('');
                        }
                    }, 500);
                });
                
                // Copy parameter
                $('.moksa-param-btn').on('click', function() {
                    var variable = $(this).data('clipboard-text');
                    var $btn = $(this);
                    var $tooltip = $btn.find('.copy-tooltip');
                    
                    navigator.clipboard.writeText(variable).then(function() {
                        $tooltip.show();
                        setTimeout(function() {
                            $tooltip.fadeOut(200);
                        }, 2000);
                    }).catch(function(err) {
                        console.error('複製失敗:', err);
                        alert('複製失敗，請手動複製：' + variable);
                    });
                });
                
                // Auto-expand first category
                $('.moksa-params-toggle').first().addClass('expanded').find('.moksa-params-toggle-content').show();
                
                // Format JSON
                $('#moksa-format-json').on('click', function() {
                    var $textarea = $('#moksa_notify_content');
                    var jsonStr = $textarea.val().trim();
                    
                    if (!jsonStr) {
                        alert('請先輸入 JSON 內容');
                        return;
                    }
                    
                    try {
                        var obj = JSON.parse(jsonStr);
                        var formatted = JSON.stringify(obj, null, 2);
                        $textarea.val(formatted);
                        $statusSpan.text('✓ JSON 格式正確').css('color', '#06C755');
                    } catch (e) {
                        alert('JSON 格式錯誤：' + e.message);
                        $statusSpan.text('✗ JSON 格式錯誤').css('color', '#ef4444');
                    }
                });
                
                // Copy JSON
                $('#moksa-copy-json').on('click', function() {
                    var $textarea = $('#moksa_notify_content');
                    $textarea.select();
                    document.execCommand('copy');
                    
                    var $btn = $(this);
                    var originalText = $btn.html();
                    $btn.html('<span style="color: #06C755;">✓ 已複製</span>');
                    setTimeout(function() {
                        $btn.html(originalText);
                    }, 2000);
                });
            });
            </script>
            <?php
        }
    }
    
    /**
     * AJAX Test Notify
     * 改進：測試發送功能（woocommerce-notify 缺少的功能）
     */
    public function ajax_test_notify() {
        check_ajax_referer('moksa_test_notify', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('權限不足', 'moksa-line-login'));
            return;
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        $notify_id = intval($_POST['notify_id'] ?? 0);
        
        if (!$order_id || !$notify_id) {
            wp_send_json_error(__('參數錯誤', 'moksa-line-login'));
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(__('訂單不存在', 'moksa-line-login'));
            return;
        }
        
        // 獲取 LINE User ID
        $user_id = $order->get_user_id();
        $line_user_id = get_post_meta($order_id, '_moksa_line_user_id', true);
        
        if (!$line_user_id && $user_id) {
            $db = Moksa_Line_Database::get_instance();
            $line_user = $db->get_line_user_by_wp_id($user_id);
            if ($line_user) {
                $line_user_id = $line_user->line_user_id;
            }
        }
        
        if (!$line_user_id) {
            wp_send_json_error(__('此訂單的客戶未綁定 LINE 帳號', 'moksa-line-login'));
            return;
        }
        
        // 生成 Flex Message
        $woocommerce = Moksa_Line_WooCommerce::get_instance();
        $status = $order->get_status();
        $flex_message = $woocommerce->get_order_flex_message_from_notify($order, $status, $notify_id);
        
        if (!$flex_message) {
            wp_send_json_error(__('無法生成 Flex Message', 'moksa-line-login'));
            return;
        }
        
        // 發送訊息
        $messaging = Moksa_Line_Messaging::get_instance();
        $result = $messaging->push_message($line_user_id, array($flex_message));
        
        if ($result && isset($result['status']) && $result['status'] === 'success') {
            wp_send_json_success(array(
                'message' => __('測試發送成功！訊息已發送到客戶的 LINE 帳號。', 'moksa-line-login')
            ));
        } else {
            $error_msg = isset($result['message']) ? $result['message'] : __('發送失敗', 'moksa-line-login');
            wp_send_json_error($error_msg);
        }
    }
    
    /**
     * Get Notify IDs for Order Status
     * 檢查訂單狀態和觸發規則是否匹配
     */
    public static function get_notify_ids_for_status($status, $order = null) {
        $args = array(
            'post_type' => self::$post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_moksa_notify_trigger_statuses',
                    'value' => $status,
                    'compare' => 'LIKE'
                )
            )
        );
        
        $notify_ids = get_posts($args);
        
        // 如果有訂單物件，檢查規則
        if ($order && !empty($notify_ids)) {
            require_once MOKSA_LINE_PLUGIN_DIR . 'includes/class-moksa-notify-check.php';
            
            $filtered_ids = array();
            foreach ($notify_ids as $notify_id) {
                $rules = get_post_meta($notify_id, '_moksa_notify_trigger_rules', true);
                
                // 如果沒有規則或規則匹配，則加入
                if (empty($rules) || Moksa_Notify_Check::check_rules_match($rules, $order)) {
                    $filtered_ids[] = $notify_id;
                }
            }
            
            return $filtered_ids;
        }
        
        return $notify_ids;
    }
}

// Initialize
Moksa_Line_Order_Notify::get_instance();

