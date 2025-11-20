<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1>訂單通知範本設定</h1>
            <p class="description">自訂當訂單狀態變更時發送給使用者的 Flex Message 訊息範本。</p>
        </div>
        <div class="header-actions">
            <button type="button" class="button button-primary button-large" id="moksa-save-template">
                <span class="dashicons dashicons-saved"></span> 儲存範本
            </button>
            <button type="button" class="button button-secondary" id="moksa-reset-template">
                <span class="dashicons dashicons-undo"></span> 重置為預設值
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

        <!-- Center Column: Editor -->
        <div class="moksa-editor-main">
            <div class="editor-toolbar">
                <span class="editor-label">JSON 編輯器</span>
                <span class="editor-status" id="editor-status"></span>
            </div>
            <div id="moksa-json-editor"></div>
            <textarea id="moksa_line_order_template" name="moksa_line_order_template" style="display: none;"></textarea>
        </div>

        <!-- Right Column: Preview -->
        <div class="moksa-editor-preview-col">
            <div class="preview-header">
                <h3>即時預覽</h3>
            </div>
            <div id="moksa-flex-preview" class="moksa-phone-preview">
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
    // Wait for Monaco loader to be ready with interval checking
    var initMonaco = function() {
        if (typeof require !== 'undefined' && typeof require.config === 'function') {
            require(['vs/editor/editor.main'], function() {
                window.editor = monaco.editor.create(document.getElementById('moksa-json-editor'), {
                    value: '',
                    language: 'json',
                    theme: 'vs-light',
                    minimap: { enabled: false },
                    automaticLayout: true,
                    formatOnPaste: true,
                    formatOnType: true,
                    scrollBeyondLastLine: false,
                    fontSize: 14
                });
                
                // Initial Load
                loadTemplate($('#moksa-order-status').val());
                
                // Sync with hidden textarea and update preview
                window.editor.onDidChangeModelContent(function() {
                    var val = window.editor.getValue();
                    $('#moksa_line_order_template').val(val);
                    updatePreview(val);
                });

                // --- Drag and Drop Implementation ---
                var editorContainer = document.getElementById('moksa-json-editor');
                
                // Handle Drag Start on Variables
                $('.moksa-variable-item').on('dragstart', function(e) {
                    e.originalEvent.dataTransfer.setData('text/plain', $(this).data('variable'));
                    e.originalEvent.dataTransfer.effectAllowed = 'copy';
                });

                // Handle Drag Over on Editor
                editorContainer.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.dataTransfer.dropEffect = 'copy';
                });

                // Handle Drop on Editor
                editorContainer.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var text = e.dataTransfer.getData('text/plain');
                    if (!text) return;

                    var target = window.editor.getTargetAtClientPoint(e.clientX, e.clientY);
                    
                    if (target && target.position) {
                        var position = target.position;
                        window.editor.executeEdits('dnd', [{
                            range: new monaco.Range(position.lineNumber, position.column, position.lineNumber, position.column),
                            text: text,
                            forceMoveMarkers: true
                        }]);
                        window.editor.setPosition(position);
                        window.editor.focus();
                    }
                });
                
                // Restore AMD if it was disabled
                if (window.moksaMonacoAMD) {
                    define.amd = window.moksaMonacoAMD;
                }
            });
        } else {
            // Retry after 100ms if Monaco not ready yet
            setTimeout(initMonaco, 100);
        }
    };
    
    initMonaco();
    
    
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
        
        btn.prop('disabled', true).find('span').removeClass('dashicons-saved').addClass('dashicons-update spin');
        
        $.post(ajaxurl, {
            action: 'moksa_line_save_order_template',
            nonce: '<?php echo wp_create_nonce("moksa_line_admin_nonce"); ?>',
            status: status,
            template: json
        }, function(response) {
            btn.prop('disabled', false).find('span').removeClass('dashicons-update spin').addClass('dashicons-saved');
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
                .replace(/{{status_color}}/g, '#17c950')
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
                container.html('<div style="color:red;">Flex Renderer not loaded.</div>');
            }
            
        } catch (e) {
            // Invalid JSON, show error
            var container = $('#preview_container');
            if (!jsonStr || !jsonStr.trim()) return;
            container.html('<div style="background: #fff; padding: 12px 16px; border-radius: 8px; color: #dc2626; border: 1px solid #fecaca; font-size: 13px;">JSON 格式錯誤：' + e.message + '</div>');
        }
    }
});
</script>
