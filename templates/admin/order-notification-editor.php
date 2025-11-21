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
            <button type="button" class="button button-secondary" id="moksa-reset-template">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 6px;">
                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                </svg>
                重置為預設值
            </button>
        </div>
    </div>

    <div class="moksa-editor-layout">
        <!-- Left Column: Variables -->
        <div class="moksa-editor-sidebar">
            <div class="sidebar-section">
                <h3>選擇訂單狀態</h3>
                <div class="moksa-select-wrapper">
                    <select id="moksa-order-status">
                        <option value="default">預設範本 (Default)</option>
                        <?php
                        $statuses = wc_get_order_statuses();
                        foreach ($statuses as $status => $label) {
                            $status_key = str_replace('wc-', '', $status);
                            echo '<option value="' . esc_attr($status_key) . '">' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="sidebar-section flex-grow">
                <h3>可用變數</h3>
                <p class="description">拖曳變數至編輯器，或點擊複製。</p>
                
                <div class="moksa-variable-list">
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
                        echo '<div class="variable-category">' . esc_html($category) . '</div>';
                        foreach ($vars as $var => $desc) {
                            echo '<div class="moksa-variable-item" draggable="true" data-variable="' . esc_attr($var) . '" onclick="navigator.clipboard.writeText(\'' . $var . '\')">';
                            echo '<code>' . $var . '</code>';
                            echo '<span>' . $desc . '</span>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Center Column: JSON Input -->
        <div class="moksa-editor-main">
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

            <div class="editor-toolbar">
                <span class="editor-label">JSON 輸入</span>
                <span class="editor-status" id="editor-status"></span>
            </div>
            <textarea id="moksa_line_order_template" name="moksa_line_order_template" class="widefat" rows="20" style="font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; resize: vertical;" placeholder="請從 LINE Flex Message Simulator 複製 JSON 並貼上於此..."></textarea>
            <p class="description" style="margin-top: 8px; font-size: 12px; color: #64748b;">
                💡 提示：在 LINE Flex Message Simulator 中設計好訊息後，點擊右上角的「View as JSON」按鈕，複製 JSON 並貼上於此。您可以使用變數（如 {{order_number}}）來動態顯示訂單資訊。
            </p>
        </div>

        <!-- Right Column: Preview -->
        <div class="moksa-editor-preview-col">
            <div class="preview-header">
                <h3>即時預覽</h3>
            </div>
            <div class="moksa-preview-toolbar">
                <div class="moksa-preview-toolbar-left">
                    <div class="moksa-device-selector">
                        <button type="button" class="moksa-device-btn active" data-device="mobile" title="<?php _e('手機', 'moksa-line-login'); ?>">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M7 2a2 2 0 00-2 2v12a2 2 0 002 2h6a2 2 0 002-2V4a2 2 0 00-2-2H7zm3 14a1 1 0 100-2 1 1 0 000 2z"/>
                            </svg>
                        </button>
                        <button type="button" class="moksa-device-btn" data-device="tablet" title="<?php _e('平板', 'moksa-line-login'); ?>">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M2 4a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V4zm3 1h10v10H5V5z"/>
                            </svg>
                        </button>
                        <button type="button" class="moksa-device-btn" data-device="desktop" title="<?php _e('桌面', 'moksa-line-login'); ?>">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="moksa-preview-toolbar-right">
                    <button type="button" class="moksa-preview-action-btn" id="refresh_preview_order" title="<?php _e('重新整理預覽', 'moksa-line-login'); ?>">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div id="moksa-flex-preview" class="moksa-phone-preview mobile">
                <div class="moksa-phone-header">LINE</div>
                <div class="moksa-phone-content" id="preview_container">
                    <!-- Preview will be rendered here -->
                </div>
            </div>
        </div>
    </div>
</div>


<script>
jQuery(document).ready(function($) {
    var $jsonTextarea = $('#moksa_line_order_template');
    var $editorStatus = $('#editor-status');
    
    // Load template function
    function loadTemplate(status) {
        // This function should load template from server or use default
        // For now, we'll keep it simple
        if (!$jsonTextarea.val().trim() && status !== 'default') {
            // Load template logic here if needed
        }
    }
    
    // Format JSON
    function formatJSON() {
        try {
            var jsonStr = $jsonTextarea.val().trim();
            if (!jsonStr) {
                alert('請先輸入 JSON');
                return;
            }
            var jsonObj = JSON.parse(jsonStr);
            $jsonTextarea.val(JSON.stringify(jsonObj, null, 2));
            updateEditorStatus('success', '格式化成功');
        } catch (e) {
            updateEditorStatus('error', 'JSON 格式錯誤: ' + e.message);
        }
    }
    
    // Validate JSON
    function validateJSON() {
        try {
            var jsonStr = $jsonTextarea.val().trim();
            if (!jsonStr) {
                updateEditorStatus('warning', '請輸入 JSON');
                return;
            }
            JSON.parse(jsonStr);
            updateEditorStatus('success', 'JSON 格式正確');
            // Trigger preview update
            if (typeof updatePreview === 'function') {
                updatePreview(jsonStr);
            }
        } catch (e) {
            updateEditorStatus('error', 'JSON 格式錯誤: ' + e.message);
        }
    }
    
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
                    if (typeof updatePreview === 'function') {
                        updatePreview(jsonStr);
                    }
                } catch (e) {
                    updateEditorStatus('error', 'JSON 格式錯誤');
                }
            } else {
                $editorStatus.text('').removeClass('status-success status-error status-warning');
            }
        }, 500);
    });
    
    // Variable click to insert
    $('.moksa-variable-item').on('click', function() {
        var variable = $(this).data('variable');
        var textarea = $jsonTextarea[0];
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var text = $jsonTextarea.val();
        var before = text.substring(0, start);
        var after = text.substring(end);
        $jsonTextarea.val(before + variable + after);
        textarea.selectionStart = textarea.selectionEnd = start + variable.length;
        $jsonTextarea.focus();
        // Trigger validation
        validateJSON();
    });
    
    // Initial Load
    loadTemplate($('#moksa-order-status').val());
    
    // Status change handler
    $('#moksa-order-status').on('change', function() {
        loadTemplate($(this).val());
    });
    
    
    // Change Status -> Load Template
    $('#moksa-order-status').on('change', function() {
        var status = $(this).val();
        loadTemplate(status);
    });
    
    function loadTemplate(status) {
        // Show loading state if needed
        if (window.editor) window.editor.updateOptions({ readOnly: true });
        $('#editor-status').text('載入中...');
        
        $.post(ajaxurl, {
            action: 'moksa_line_get_order_template',
            nonce: '<?php echo wp_create_nonce("moksa_line_admin_nonce"); ?>', // Ensure nonce is available
            status: status
        }, function(response) {
            if (window.editor) window.editor.updateOptions({ readOnly: false });
            $('#editor-status').text('');
            
            if (response.success) {
                var content = response.data;
                if (typeof content === 'object') {
                    content = JSON.stringify(content, null, 4);
                }
                if (window.editor) {
                    window.editor.setValue(content);
                    // Trigger preview update
                    updatePreview(content);
                }
            } else {
                alert('無法載入範本：' + (response.data || '未知錯誤'));
            }
        }).fail(function() {
            if (window.editor) window.editor.updateOptions({ readOnly: false });
            $('#editor-status').text('');
            alert('網路錯誤，無法載入範本。');
        });
    }
    
    // Save Template
    $('#moksa-save-template').on('click', function() {
        var btn = $(this);
        var status = $('#moksa-order-status').val();
        var json = window.editor ? window.editor.getValue() : $('#moksa_line_order_template').val();
        
        // Validate JSON
        try {
            JSON.parse(json);
        } catch (e) {
            alert('JSON 格式錯誤，請修正後再儲存。');
            return;
        }
        
        btn.prop('disabled', true).find('svg').html('<path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/><style>@keyframes spin { 100% { transform: rotate(360deg); } } svg { animation: spin 1s linear infinite; }</style>');
        
        $.post(ajaxurl, {
            action: 'moksa_line_save_order_template',
            nonce: '<?php echo wp_create_nonce("moksa_line_admin_nonce"); ?>',
            status: status,
            template: json
        }, function(response) {
            btn.prop('disabled', false).find('svg').html('<path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z"/>').find('style').remove();
            if (response.success) {
                // Show toast or small notification instead of alert if possible, but alert is fine for now
                alert('範本儲存成功！');
            } else {
                alert('儲存失敗: ' + response.data);
            }
        });
    });
    
    // Reset Button
    $('#moksa-reset-template').on('click', function() {
        if (confirm('確定要重置為預設範本嗎？此操作無法復原。')) {
             var defaultJson = {
                "type": "bubble",
                "size": "mega",
                "header": {
                    "type": "box",
                    "layout": "vertical",
                    "backgroundColor": "#06c755",
                    "paddingAll": "20px",
                    "contents": [
                        { "type": "text", "text": "訂單狀態更新", "weight": "bold", "color": "#ffffff", "size": "xs" },
                        { "type": "text", "text": "{{status_label}}", "weight": "bold", "color": "#ffffff", "size": "xl", "margin": "md" }
                    ]
                },
                "body": {
                    "type": "box",
                    "layout": "vertical",
                    "contents": [
                        {
                            "type": "box",
                            "layout": "vertical",
                            "margin": "lg",
                            "spacing": "sm",
                            "contents": [
                                {
                                    "type": "box",
                                    "layout": "baseline",
                                    "spacing": "sm",
                                    "contents": [
                                        { "type": "text", "text": "訂單編號", "color": "#aaaaaa", "size": "sm", "flex": 2 },
                                        { "type": "text", "text": "#{{order_number}}", "wrap": true, "color": "#666666", "size": "sm", "flex": 5 }
                                    ]
                                },
                                {
                                    "type": "box",
                                    "layout": "baseline",
                                    "spacing": "sm",
                                    "contents": [
                                        { "type": "text", "text": "總金額", "color": "#aaaaaa", "size": "sm", "flex": 2 },
                                        { "type": "text", "text": "{{total}}", "wrap": true, "color": "#666666", "size": "sm", "flex": 5 }
                                    ]
                                }
                            ]
                        }
                    ]
                },
                "footer": {
                    "type": "box",
                    "layout": "vertical",
                    "spacing": "sm",
                    "contents": [
                        {
                            "type": "button",
                            "style": "link",
                            "height": "sm",
                            "action": { "type": "uri", "label": "查看訂單", "uri": "{{view_order_url}}" }
                        }
                    ]
                }
            };
            if (window.editor) {
                window.editor.setValue(JSON.stringify(defaultJson, null, 4));
            }
        }
    });
    
    function updatePreview(jsonStr) {
        try {
            // Mock data for preview
            var json = jsonStr
                .replace(/{{order_number}}/g, '2024111901')
                .replace(/{{status}}/g, 'processing')
                .replace(/{{status_label}}/g, '處理中')
                .replace(/{{status_color}}/g, '#06C755')
                .replace(/{{total}}/g, 'NT$1,500')
                .replace(/{{items_count}}/g, '3')
                .replace(/{{billing_name}}/g, '王小明')
                .replace(/{{billing_phone}}/g, '0912345678')
                .replace(/{{shipping_name}}/g, '王小明')
                .replace(/{{shipping_address}}/g, '台北市信義區市府路1號')
                .replace(/{{customer_note}}/g, '請盡快出貨')
                .replace(/{{payment_method}}/g, '信用卡付款')
                .replace(/{{shipping_method}}/g, '宅配')
                .replace(/{{tracking_number}}/g, '1234567890')
                .replace(/{{store_name}}/g, '7-11 市府門市')
                .replace(/{{store_address}}/g, '台北市信義區...')
                .replace(/{{view_order_url}}/g, '#');
            
            var flexObj = JSON.parse(json);
            var container = $('#preview_container');
            container.empty(); // Clear previous content
            
            // Use Shared Renderer
            if (window.MoksaFlexRenderer) {
                window.MoksaFlexRenderer.render(flexObj, container);
            } else {
                container.html('<div style="background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 12px; border-radius: 8px; font-size: 13px;"><strong>Flex Renderer not loaded.</strong><br>請重新整理頁面或檢查瀏覽器控制台。</div>');
            }
            
        } catch (e) {
            // Invalid JSON, show error
            var container = $('#preview_container');
            if (!jsonStr || !jsonStr.trim()) return;
            container.html('<div style="background: #fff; padding: 12px 16px; border-radius: 8px; color: #dc2626; border: 1px solid #fecaca; font-size: 13px;">JSON 格式錯誤：' + e.message + '</div>');
        }
    }

    // Device Size Selector
    $('.moksa-device-btn').on('click', function () {
        var device = $(this).data('device');
        $('.moksa-device-btn').removeClass('active');
        $(this).addClass('active');
        $('#moksa-flex-preview').removeClass('mobile tablet desktop').addClass(device);
    });

    // Refresh Preview
    $('#refresh_preview_order').on('click', function () {
        var json = window.editor ? window.editor.getValue() : $('#moksa_line_order_template').val();
        updatePreview(json);
    });
});
</script>
