<div class="wrap moksa-line-wrap">
    <h1>訂單通知範本設定</h1>
    <p>自訂當訂單狀態變更時發送給使用者的 Flex Message 訊息範本。</p>

    <div class="moksa-card">
        <div style="margin-bottom: 20px; padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; align-items: center; gap: 15px;">
            <label for="moksa-order-status" style="font-weight: bold;">選擇訂單狀態：</label>
            <select id="moksa-order-status" style="min-width: 200px;">
                <option value="default">預設範本 (Default)</option>
                <?php
                $statuses = wc_get_order_statuses();
                foreach ($statuses as $status => $label) {
                    $status_key = str_replace('wc-', '', $status);
                    echo '<option value="' . esc_attr($status_key) . '">' . esc_html($label) . '</option>';
                }
                ?>
            </select>
            <span class="description">切換狀態以編輯該狀態專屬的通知範本。</span>
        </div>

        <div style="display: flex; gap: 20px;">
            <div style="flex: 1;">
                <h3>JSON 範本編輯器</h3>
                <div id="moksa-json-editor" style="height: 600px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;"></div>
                <textarea id="moksa_line_order_template" name="moksa_line_order_template" style="display: none;"></textarea>
                
                <div style="margin-top: 20px;">
                    <button type="button" class="button button-primary button-large" id="moksa-save-template">
                        儲存範本
                    </button>
                    <button type="button" class="button button-secondary" id="moksa-reset-template">
                        重置為預設值
                    </button>
                </div>
            </div>
            
            <div style="width: 350px;">
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
                            '{{tracking_number}}' => '物流追蹤碼 (ECPay/RY/AST)',
                            '{{store_name}}' => '超商門市名稱',
                            '{{store_address}}' => '超商門市地址',
                        )
                    );
                    
                    foreach ($variables as $category => $vars) {
                        echo '<h4 style="margin: 15px 0 8px; color: #1e293b; font-weight: 600; font-size: 14px;">' . esc_html($category) . '</h4>';
                        foreach ($vars as $var => $desc) {
                            echo '<div class="moksa-variable-item" draggable="true" data-variable="' . esc_attr($var) . '" onclick="navigator.clipboard.writeText(\'' . $var . '\')">';
                            echo '<code>' . $var . '</code>';
                            echo '<span>' . $desc . '</span>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>

                <h3 style="margin-top: 30px;">即時預覽</h3>
                <div id="moksa-flex-preview" class="moksa-phone-preview">
                    <div class="moksa-phone-header">LINE</div>
                    <div class="moksa-phone-content" id="moksa-preview-container">
                        <!-- Preview will be rendered here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.moksa-variable-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 600px;
    overflow-y: auto;
    padding-right: 5px;
}
.moksa-variable-item {
    padding: 10px 14px;
    background: #fff;
    border-radius: 8px;
    cursor: grab;
    transition: all 0.2s ease;
    border: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.moksa-variable-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateX(2px);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.moksa-variable-item:active {
    cursor: grabbing;
}
.moksa-variable-item code {
    font-weight: 600;
    color: #2563eb;
    background: #eff6ff;
    padding: 3px 6px;
    border-radius: 4px;
    font-size: 12px;
}
.moksa-variable-item span {
    font-size: 13px;
    color: #64748b;
}
/* Phone Preview Styles */
.moksa-phone-preview {
    width: 100%;
    max-width: 300px;
    height: 500px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
}
.moksa-phone-header {
    background: #1e293b;
    color: #fff;
    padding: 12px;
    text-align: center;
    font-weight: 600;
    font-size: 14px;
}
.moksa-phone-content {
    flex: 1;
    background: #e2e8f0;
    padding: 12px;
    overflow-y: auto;
}
/* Simple Flex Message Renderer Styles */
.flex-bubble {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    max-width: 100%;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.flex-hero img {
    width: 100%;
    height: auto;
    display: block;
}
.flex-body, .flex-header, .flex-footer {
    padding: 15px;
}
.flex-text {
    margin: 0;
    line-height: 1.5;
}
.flex-button {
    display: block;
    text-align: center;
    padding: 10px;
    background: #f1f5f9;
    text-decoration: none;
    color: #475569;
    border-radius: 6px;
    margin-top: 5px;
    font-weight: 500;
    transition: all 0.2s;
}
.flex-button.primary {
    background: #2563eb;
    color: #fff;
}
.flex-button:hover {
    opacity: 0.9;
}
</style>

<script>
jQuery(document).ready(function($) {
    var editor;
    var currentStatus = 'default';

    // Initialize Monaco Editor
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs' }});
    
    require(['vs/editor/editor.main'], function() {
        editor = monaco.editor.create(document.getElementById('moksa-json-editor'), {
            value: '', // Will be loaded via AJAX
            language: 'json',
            theme: 'vs-light',
            automaticLayout: true,
            minimap: { enabled: false },
            formatOnPaste: true,
            formatOnType: true
        });
        
        // Load initial template
        loadTemplate('default');
        
        // Update hidden textarea and preview on change
        editor.onDidChangeModelContent(function() {
            var value = editor.getValue();
            $('#moksa_line_order_template').val(value);
            // Debounce preview update to avoid too frequent updates
            clearTimeout(window.previewTimeout);
            window.previewTimeout = setTimeout(function() {
                updatePreview(value);
            }, 500);
        });

        // Drag and Drop Support
        var dropTarget = editor.getDomNode();
        
        dropTarget.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
        }, false);

        dropTarget.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var variable = e.dataTransfer.getData('text/plain');
            if (variable) {
                var position = editor.getPosition(); // Get current cursor position (or drop position if possible)
                // Note: Getting exact drop coordinates in Monaco is complex, inserting at cursor is standard fallback
                
                var range = new monaco.Range(position.lineNumber, position.column, position.lineNumber, position.column);
                var op = { range: range, text: variable, forceMoveMarkers: true };
                editor.executeEdits("my-source", [op]);
            }
        }, false);
    });

    // Handle Variable Drag Start
    $('.moksa-variable-item').on('dragstart', function(e) {
        e.originalEvent.dataTransfer.setData('text/plain', $(this).data('variable'));
    });

    // Status Change
    $('#moksa-order-status').on('change', function() {
        currentStatus = $(this).val();
        loadTemplate(currentStatus);
    });
    
    // Load Template Function
    function loadTemplate(status) {
        if (!editor) return;
        
        // Show loading state if needed
        
        $.post(ajaxurl, {
            action: 'moksa_line_get_order_template',
            status: status,
            nonce: '<?php echo wp_create_nonce('moksa_line_save_template'); ?>'
        }, function(response) {
            if (response.success) {
                var formattedJson = JSON.stringify(JSON.parse(response.data.template), null, 4);
                editor.setValue(formattedJson);
                updatePreview(formattedJson);
            } else {
                alert('載入範本失敗: ' + response.data);
            }
        });
    }

    // Save Button
    $('#moksa-save-template').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('儲存中...');
        
        $.post(ajaxurl, {
            action: 'moksa_line_save_order_template',
            template: editor.getValue(),
            status: currentStatus,
            nonce: '<?php echo wp_create_nonce('moksa_line_save_template'); ?>'
        }, function(response) {
            btn.prop('disabled', false).text('儲存範本');
            if (response.success) {
                alert('範本儲存成功！');
            } else {
                alert('儲存失敗: ' + response.data);
            }
        });
    });
    
    // Reset Button
    $('#moksa-reset-template').on('click', function() {
        if (confirm('確定要重置為預設範本嗎？此操作無法復原。')) {
            // To reset, we just need to clear the option (or load default)
            // Here we can just reload with a flag or handle in backend. 
            // Simplest is to just re-fetch default from backend logic by passing a special flag or just empty.
            // Actually, let's just re-trigger load but maybe we need a reset endpoint?
            // For now, let's just manually set a default structure or re-fetch.
            // Let's re-fetch but implies we need to delete the option first? 
            // Or we can just construct a default JSON here.
            // Better: Reload page or ask backend for default.
            // Let's just reload the 'default' template for this status from backend (which falls back to code default if option empty)
            // But if option exists, it loads that.
            // So we need a way to force get default.
            // Let's just set a basic default here for now to be safe.
            
             var defaultJson = {
                "type": "bubble",
                "body": {
                    "type": "box",
                    "layout": "vertical",
                    "contents": [
                        { "type": "text", "text": "訂單狀態更新", "weight": "bold", "color": "#1DB446" },
                        { "type": "text", "text": "{{status_label}}", "size": "xxl", "weight": "bold" },
                        { "type": "text", "text": "#{{order_number}}", "color": "#aaaaaa" }
                    ]
                }
            };
            editor.setValue(JSON.stringify(defaultJson, null, 4));
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
                .replace(/{{shipping_name}}/g, '王小明')
                .replace(/{{shipping_address}}/g, '台北市信義區市府路1號')
                .replace(/{{payment_method}}/g, '信用卡付款')
                .replace(/{{shipping_method}}/g, '宅配')
                .replace(/{{tracking_number}}/g, '1234567890')
                .replace(/{{store_name}}/g, '7-11 市府門市')
                .replace(/{{store_address}}/g, '台北市信義區...')
                .replace(/{{view_order_url}}/g, '#');
            
            var flexObj = JSON.parse(json);
            var container = $('#moksa-preview-container');
            container.empty();
            
            // Simple Renderer
            renderSimpleFlex(flexObj, container);
            
        } catch (e) {
            // Invalid JSON, show error
            var container = $('#moksa-preview-container');
            container.html('<div style="background: #fff; padding: 12px 16px; border-radius: 8px; color: #dc2626; border: 1px solid #fecaca; font-size: 13px;">JSON 格式錯誤：' + e.message + '</div>');
        }
    }

    function renderSimpleFlex(obj, container) {
        // Handle Bubble
        if (obj.type === 'bubble') {
            var bubble = $('<div class="flex-bubble" style="background: #fff; border-radius: 12px; overflow: hidden; max-width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"></div>');
            
            if (obj.header) renderBox(obj.header, bubble, 'flex-header');
            if (obj.hero) renderImage(obj.hero, bubble, 'flex-hero');
            if (obj.body) renderBox(obj.body, bubble, 'flex-body');
            if (obj.footer) renderBox(obj.footer, bubble, 'flex-footer');
            
            container.append(bubble);
        } else if (obj.type === 'flex') {
            if (obj.contents) {
                renderSimpleFlex(obj.contents, container);
            }
        } else if (obj.type === 'carousel') {
            // Handle carousel (simplified - just show first bubble)
            if (obj.contents && obj.contents.length > 0) {
                renderSimpleFlex(obj.contents[0], container);
            }
        }
    }

    function renderBox(box, parent, className) {
        var div = $('<div class="' + (className || '') + '" style="padding: 16px;"></div>');
        
        // Apply styles
        if (box.backgroundColor) div.css('background-color', box.backgroundColor);
        if (box.layout === 'horizontal') div.css({display: 'flex', flexDirection: 'row', gap: '5px', alignItems: 'center'});
        if (box.layout === 'vertical') div.css({display: 'flex', flexDirection: 'column', gap: '5px'});
        
        if (box.contents && Array.isArray(box.contents)) {
            box.contents.forEach(function(item) {
                if (item.type === 'text') {
                    var p = $('<p class="flex-text" style="margin: 0; line-height: 1.5;"></p>');
                    p.text(item.text);
                    if (item.color) p.css('color', item.color);
                    if (item.size === 'xs') p.css('font-size', '10px');
                    if (item.size === 'sm') p.css('font-size', '12px');
                    if (item.size === 'md') p.css('font-size', '14px');
                    if (item.size === 'lg') p.css('font-size', '16px');
                    if (item.size === 'xl') p.css('font-size', '18px');
                    if (item.size === 'xxl') p.css('font-size', '20px');
                    if (item.weight === 'bold') p.css('font-weight', 'bold');
                    if (item.align) p.css('text-align', item.align);
                    if (item.flex) p.css('flex', item.flex);
                    div.append(p);
                } else if (item.type === 'button') {
                    var a = $('<a href="#" class="flex-button" style="display: block; text-align: center; padding: 10px; background: #f1f5f9; text-decoration: none; color: #475569; border-radius: 6px; margin-top: 5px; font-weight: 500; transition: all 0.2s;"></a>');
                    a.text(item.action ? (item.action.label || '按鈕') : '按鈕');
                    if (item.style === 'primary') a.css({'background-color': '#2563eb', 'color': '#fff'});
                    if (item.color) a.css('background-color', item.color);
                    div.append(a);
                } else if (item.type === 'box') {
                    renderBox(item, div);
                } else if (item.type === 'separator') {
                    div.append('<hr style="border:0; border-top:1px solid #e2e8f0; margin: 8px 0;">');
                } else if (item.type === 'image') {
                    renderImage(item, div);
                }
            });
        }
        
        parent.append(div);
    }
    
    function renderImage(img, parent, className) {
        var div = $('<div class="' + (className || '') + '"></div>');
        var imgEl = $('<img style="width: 100%; height: auto; display: block;">');
        imgEl.attr('src', img.url || '');
        if (img.aspectRatio) {
            imgEl.css('aspect-ratio', img.aspectRatio);
        }
        div.append(imgEl);
        parent.append(div);
    }
});
</script>
