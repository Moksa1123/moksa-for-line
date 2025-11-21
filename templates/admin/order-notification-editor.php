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

    <div class="moksa-editor-layout" style="display: block; max-width: 1200px;">
        <!-- Variables Section -->
        <div class="moksa-card" style="margin-bottom: 20px;">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">可用變數</h2>
            <p class="description" style="margin-bottom: 15px;">點擊變數即可複製到剪貼簿，然後貼到 JSON 中使用。</p>
            
            <div class="moksa-variable-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px;">
                <?php
                $variables = array(
                    '基本資訊' => array(
                        '{{order_number}}' => '訂單編號',
                        '{{status_label}}' => '訂單狀態名稱',
                        '{{total}}' => '訂單總金額',
                        '{{items_count}}' => '商品數量',
                        '{{view_order_url}}' => '訂單連結',
                        '{{status_color}}' => '狀態顏色代碼',
                    ),
                    '客戶資訊' => array(
                        '{{billing_name}}' => '帳單姓名',
                        '{{billing_phone}}' => '帳單電話',
                        '{{shipping_name}}' => '收件姓名',
                        '{{shipping_address}}' => '收件地址',
                        '{{customer_note}}' => '客戶備註',
                    ),
                    '物流與支付' => array(
                        '{{payment_method}}' => '付款方式',
                        '{{shipping_method}}' => '運送方式',
                        '{{tracking_number}}' => '物流追蹤碼',
                        '{{store_name}}' => '超商門市名稱',
                        '{{store_address}}' => '超商門市地址',
                    )
                );
                
                foreach ($variables as $category => $vars) {
                    echo '<div style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">';
                    echo '<div style="font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 13px;">' . esc_html($category) . '</div>';
                    foreach ($vars as $var => $desc) {
                        echo '<div class="moksa-variable-item" data-variable="' . esc_attr($var) . '" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 8px; margin-bottom: 4px; background: white; border-radius: 4px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent;" onmouseover="this.style.borderColor=\'#06C755\'; this.style.background=\'#f0fdf4\'" onmouseout="this.style.borderColor=\'transparent\'; this.style.background=\'white\'">';
                        echo '<code style="font-size: 12px; color: #06C755; font-weight: 600;">' . esc_html($var) . '</code>';
                        echo '<span style="font-size: 11px; color: #64748b;">' . esc_html($desc) . '</span>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <!-- JSON Input Section -->
        <div class="moksa-card">
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
    
    // Variable click to copy
    $('.moksa-variable-item').on('click', function() {
        var variable = $(this).data('variable');
        navigator.clipboard.writeText(variable).then(function() {
            var $item = $(this);
            var originalBg = $item.css('background');
            $item.css('background', '#dcfce7').css('border-color', '#06C755');
            setTimeout(function() {
                $item.css('background', originalBg).css('border-color', 'transparent');
            }, 500);
        }.bind(this));
    });
    
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
