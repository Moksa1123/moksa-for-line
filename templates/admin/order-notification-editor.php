<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1>訂單通知範本設定</h1>
            <p class="description">自訂當訂單狀態變更時發送給使用者的 Flex Message 訊息範本。</p>
        </div>
        <div class="header-actions">
            <button type="button" class="button button-primary button-large" id="moksa-save-template">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 6px;">
                    <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z"/>
                </svg>
                儲存範本
            </button>
        </div>
    </div>

    <div class="moksa-editor-layout" style="display: grid; grid-template-columns: 350px 1fr; gap: 20px;">
        <!-- Variables Section (Sidebar) -->
        <div class="moksa-card" style="height: fit-content; position: sticky; top: 20px;">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; font-size: 16px;">可用變數</h2>
            <p class="description" style="margin-bottom: 15px; font-size: 12px;">點擊變數即可複製到剪貼簿</p>
            
            <div class="moksa-variable-list" style="max-height: calc(100vh - 300px); overflow-y: auto; padding-right: 5px;">
                <?php
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
                        '{{order_link}}' => '訂單連結（同 view_order_url）',
                        '{{status_color}}' => '狀態顏色代碼',
                        '{{order_date}}' => '訂單日期',
                        '{{order_date_paid}}' => '付款日期',
                        '{{order_date_completed}}' => '完成日期',
                    ),
                    '客戶資訊' => array(
                        '{{customer_email}}' => '客戶電子郵件',
                        '{{customer_first_name}}' => '客戶名字',
                        '{{customer_last_name}}' => '客戶姓氏',
                        '{{customer_full_name}}' => '客戶全名',
                        '{{customer_phone}}' => '客戶電話',
                        '{{customer_user_id}}' => '客戶使用者 ID',
                        '{{customer_username}}' => '客戶使用者名稱',
                        '{{billing_name}}' => '帳單姓名',
                        '{{billing_first_name}}' => '帳單名字',
                        '{{billing_last_name}}' => '帳單姓氏',
                        '{{billing_phone}}' => '帳單電話',
                        '{{billing_email}}' => '帳單電子郵件',
                        '{{billing_address}}' => '帳單地址',
                        '{{billing_city}}' => '帳單城市',
                        '{{billing_postcode}}' => '帳單郵遞區號',
                        '{{billing_country}}' => '帳單國家',
                        '{{billing_state}}' => '帳單州/省',
                        '{{billing_company}}' => '帳單公司',
                    ),
                    '收件資訊' => array(
                        '{{shipping_name}}' => '收件姓名',
                        '{{shipping_first_name}}' => '收件名字',
                        '{{shipping_last_name}}' => '收件姓氏',
                        '{{shipping_address}}' => '收件地址',
                        '{{shipping_address_line_1}}' => '收件地址第一行',
                        '{{shipping_address_line_2}}' => '收件地址第二行',
                        '{{shipping_city}}' => '收件城市',
                        '{{shipping_postcode}}' => '收件郵遞區號',
                        '{{shipping_country}}' => '收件國家',
                        '{{shipping_state}}' => '收件州/省',
                        '{{shipping_company}}' => '收件公司',
                        '{{shipping_phone}}' => '收件電話',
                    ),
                    '物流與支付' => array(
                        '{{payment_method}}' => '付款方式',
                        '{{payment_method_title}}' => '付款方式標題',
                        '{{shipping_method}}' => '運送方式',
                        '{{shipping_method_title}}' => '運送方式標題',
                        '{{tracking_number}}' => '物流追蹤碼',
                        '{{store_name}}' => '超商門市名稱',
                        '{{store_address}}' => '超商門市地址',
                        '{{customer_note}}' => '客戶備註',
                        '{{order_note_for_customer}}' => '給客戶的訂單備註',
                    ),
                    '商店資訊' => array(
                        '{{shop_title}}' => '商店名稱',
                        '{{shop_tagline}}' => '商店標語',
                        '{{shop_url}}' => '商店網址',
                        '{{shop_admin_email}}' => '商店管理員電子郵件',
                        '{{shop_shop_url}}' => '商店購物頁面網址',
                    )
                );
                
                foreach ($variables as $category => $vars) {
                    echo '<div class="moksa-params-toggle" style="margin-bottom: 12px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff;">';
                    echo '<div class="moksa-params-toggle-header" style="padding: 12px 15px; background: #f8fafc; cursor: pointer; user-select: none; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s;" onclick="this.parentElement.classList.toggle(\'expanded\');">';
                    echo '<h3 style="margin: 0; font-size: 13px; font-weight: 600; color: #334155;">' . esc_html($category) . '</h3>';
                    echo '<svg class="toggle-indicator" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="color: #64748b; transition: transform 0.2s;"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';
                    echo '</div>';
                    echo '<div class="moksa-params-toggle-content" style="display: none; padding: 8px;">';
                    echo '<ul style="list-style: none; padding: 0; margin: 0;">';
                    foreach ($vars as $var => $desc) {
                        echo '<li style="margin-bottom: 4px;">';
                        echo '<button type="button" class="moksa-param-btn" data-clipboard-text="' . esc_attr($var) . '" style="width: 100%; text-align: left; padding: 8px 10px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; transition: all 0.2s; font-size: 12px; display: flex; justify-content: space-between; align-items: center;" onmouseover="this.style.borderColor=\'#06C755\'; this.style.background=\'#f0fdf4\'" onmouseout="this.style.borderColor=\'#e2e8f0\'; this.style.background=\'#fff\'">';
                        echo '<code style="font-size: 11px; color: #06C755; font-weight: 600; background: transparent; padding: 0;">' . esc_html($var) . '</code>';
                        echo '<span style="font-size: 11px; color: #64748b; margin-left: 8px; flex: 1; text-align: right;">' . esc_html($desc) . '</span>';
                        echo '<span class="copy-tooltip" style="display: none; font-size: 10px; color: #06C755; margin-left: 8px; font-weight: 600;">已複製！</span>';
                        echo '</button>';
                        echo '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <!-- JSON Input Section -->
        <div class="moksa-card" style="grid-column: 2;">
            <div class="moksa-line-simulator-guide" style="background: linear-gradient(135deg, #06C755 0%, #05B048 100%); color: white; padding: 24px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(6, 199, 85, 0.3);">
                <div style="display: flex; align-items: center; margin-bottom: 16px;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 12px;">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <div>
                        <h3 style="margin: 0 0 4px 0; font-size: 18px; font-weight: 600;">使用 LINE 官方 Flex Message Simulator</h3>
                        <p style="margin: 0; font-size: 14px; opacity: 0.95;">我們建議您使用 LINE 官方的 Flex Message Simulator 來設計您的訊息，功能更完整且更易於使用。</p>
                    </div>
                </div>
                <a href="https://developers.line.biz/flex-simulator/" target="_blank" class="button button-primary button-large" style="background: white; color: #06C755; border: none; font-weight: 600; padding: 12px 24px; text-decoration: none; display: inline-block; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); transition: transform 0.2s;">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                        <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                    </svg>
                    前往 LINE Flex Message Simulator
                </a>
            </div>

            <div class="editor-toolbar" style="margin-bottom: 15px;">
                <span class="editor-label">JSON 範本</span>
                <span class="editor-status" id="editor-status"></span>
            </div>
            <textarea id="moksa_line_order_template" name="moksa_line_order_template" class="widefat" rows="25" style="font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; resize: vertical;"><?php
                // 直接輸出預設 JSON 範本
                $default_template = array(
                    "type" => "bubble",
                    "size" => "mega",
                    "header" => array(
                        "type" => "box",
                        "layout" => "vertical",
                        "backgroundColor" => "#06c755",
                        "paddingAll" => "20px",
                        "contents" => array(
                            array("type" => "text", "text" => "訂單狀態更新", "weight" => "bold", "color" => "#ffffff", "size" => "xs"),
                            array("type" => "text", "text" => "{{status_label}}", "weight" => "bold", "color" => "#ffffff", "size" => "xl", "margin" => "md")
                        )
                    ),
                    "body" => array(
                        "type" => "box",
                        "layout" => "vertical",
                        "contents" => array(
                            array(
                                "type" => "box",
                                "layout" => "vertical",
                                "margin" => "lg",
                                "spacing" => "sm",
                                "contents" => array(
                                    array(
                                        "type" => "box",
                                        "layout" => "baseline",
                                        "spacing" => "sm",
                                        "contents" => array(
                                            array("type" => "text", "text" => "訂單編號", "color" => "#aaaaaa", "size" => "sm", "flex" => 2),
                                            array("type" => "text", "text" => "#{{order_number}}", "wrap" => true, "color" => "#666666", "size" => "sm", "flex" => 5)
                                        )
                                    ),
                                    array(
                                        "type" => "box",
                                        "layout" => "baseline",
                                        "spacing" => "sm",
                                        "contents" => array(
                                            array("type" => "text", "text" => "總金額", "color" => "#aaaaaa", "size" => "sm", "flex" => 2),
                                            array("type" => "text", "text" => "{{total}}", "wrap" => true, "color" => "#666666", "size" => "sm", "flex" => 5)
                                        )
                                    )
                                )
                            )
                        )
                    ),
                    "footer" => array(
                        "type" => "box",
                        "layout" => "vertical",
                        "spacing" => "sm",
                        "contents" => array(
                            array(
                                "type" => "button",
                                "style" => "link",
                                "height" => "sm",
                                "action" => array("type" => "uri", "label" => "查看訂單", "uri" => "{{view_order_url}}")
                            )
                        )
                    )
                );
                echo esc_textarea(json_encode($default_template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            ?></textarea>
            <p class="description" style="margin-top: 8px; font-size: 12px; color: #64748b;">
                💡 提示：在 LINE Flex Message Simulator 中設計好訊息後，點擊右上角的「View as JSON」按鈕，複製 JSON 並貼上於此。您可以使用變數（如 {{order_number}}）來動態顯示訂單資訊。
            </p>
        </div>
    </div>
</div>


<script>
jQuery(document).ready(function($) {
    var $jsonTextarea = $('#moksa_line_order_template');
    var $editorStatus = $('#editor-status');
    
    function updateEditorStatus(type, message) {
        $editorStatus.text(message)
            .removeClass('status-success status-error status-warning')
            .addClass('status-' + type);
    }
    
    // Auto validate on input (debounced)
    var validateTimeout;
    $jsonTextarea.on('input', function() {
        clearTimeout(validateTimeout);
        validateTimeout = setTimeout(function() {
            var jsonStr = $jsonTextarea.val().trim();
            if (jsonStr) {
                try {
                    JSON.parse(jsonStr);
                    updateEditorStatus('success', 'JSON 格式正確');
                } catch (e) {
                    updateEditorStatus('error', 'JSON 格式錯誤');
                }
            } else {
                $editorStatus.text('').removeClass('status-success status-error status-warning');
            }
        }, 500);
    });
    
    // Variable click to copy (學習 woocommerce-notify 的設計)
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
    
    // Save Template
    $('#moksa-save-template').on('click', function() {
        var btn = $(this);
        var json = $jsonTextarea.val();
        
        // Validate JSON
        try {
            JSON.parse(json);
        } catch (e) {
            alert('JSON 格式錯誤，請修正後再儲存。\n錯誤：' + e.message);
            return;
        }
        
        btn.prop('disabled', true);
        var originalHtml = btn.html();
        btn.html('<svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 6px; animation: spin 1s linear infinite;"><style>@keyframes spin { 100% { transform: rotate(360deg); } }</style><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>儲存中...</svg>');
        
        $.post(ajaxurl, {
            action: 'moksa_line_save_order_template',
            nonce: '<?php echo wp_create_nonce("moksa_line_admin_nonce"); ?>',
            status: 'default',
            template: json
        }, function(response) {
            btn.prop('disabled', false).html(originalHtml);
            if (response.success) {
                updateEditorStatus('success', '範本儲存成功！');
                setTimeout(function() {
                    $editorStatus.text('').removeClass('status-success');
                }, 3000);
            } else {
                alert('儲存失敗: ' + (response.data || '未知錯誤'));
            }
        }).fail(function() {
            btn.prop('disabled', false).html(originalHtml);
            alert('網路錯誤，無法儲存範本。');
        });
    });
});
</script>
