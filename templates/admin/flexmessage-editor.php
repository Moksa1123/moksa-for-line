<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('設計並發送 Flex Message 給您的使用者。', 'moksa-line-login'); ?></p>
        </div>
        <div class="header-actions">
            <button type="button" class="button" id="load_sample">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 6px;">
                    <path d="M10 2L3 7v11h4v-6h6v6h4V7l-7-5z"/>
                </svg>
                <?php _e('載入範例', 'moksa-line-login'); ?>
            </button>
            <button type="button" class="button button-primary button-large" id="send_flex">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 6px;">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
                <?php _e('立即發送', 'moksa-line-login'); ?>
            </button>
        </div>
    </div>

    <div class="moksa-editor-layout">
        <!-- Left Column: Settings -->
        <div class="moksa-editor-sidebar">
            <div class="sidebar-section">
                <h3><?php _e('訊息設定', 'moksa-line-login'); ?></h3>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="flex_alt_text" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #475569;"><?php _e('替代文字 (Alt Text)', 'moksa-line-login'); ?></label>
                    <input type="text" id="flex_alt_text" class="widefat" placeholder="<?php _e('在聊天列表中顯示的預覽文字', 'moksa-line-login'); ?>">
                    <p class="description" style="margin-top: 4px; font-size: 12px;"><?php _e('當使用者收到訊息時，在聊天列表顯示的文字。', 'moksa-line-login'); ?></p>
                </div>

                <div class="form-group">
                    <label for="target_type" style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #475569;"><?php _e('發送對象', 'moksa-line-login'); ?></label>
                    <div class="moksa-select-wrapper">
                        <select id="target_type" class="widefat">
                            <option value="all"><?php _e('所有使用者', 'moksa-line-login'); ?></option>
                            <option value="specific"><?php _e('特定使用者', 'moksa-line-login'); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="user_selector" class="sidebar-section flex-grow" style="display: none;">
                <h3><?php _e('選擇使用者', 'moksa-line-login'); ?></h3>
                <div style="flex: 1; overflow-y: auto; padding-right: 5px;">
                    <?php
                    $db = Moksa_Line_Database::get_instance();
                    $users = $db->get_all_line_users(100);
                    if ($users) {
                        foreach ($users as $user) {
                            echo '<label class="moksa-user-item" style="display: flex; align-items: center; padding: 8px; border-bottom: 1px solid #f1f5f9; cursor: pointer;">';
                            echo '<input type="checkbox" name="user_ids[]" value="' . esc_attr($user->line_user_id) . '" style="margin-right: 10px;">';
                            echo '<div style="flex: 1;">';
                            echo '<div style="font-weight: 500; color: #334155;">' . esc_html($user->display_name) . '</div>';
                            echo '<div style="font-size: 11px; color: #94a3b8;">' . esc_html(substr($user->line_user_id, 0, 8)) . '...</div>';
                            echo '</div>';
                            echo '</label>';
                        }
                    } else {
                        echo '<p class="description" style="padding: 10px;">' . __('沒有找到 LINE 使用者。', 'moksa-line-login') . '</p>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Center Column: Editor -->
        <div class="moksa-editor-main">
            <div class="editor-toolbar">
                <div class="editor-toolbar-left">
                    <span class="editor-label"><?php _e('JSON 編輯器', 'moksa-line-login'); ?></span>
                    <span class="editor-status" id="editor-status"></span>
                </div>
                <div class="editor-toolbar-right">
                    <button type="button" class="moksa-json-tool-btn" id="format_json" title="<?php _e('格式化 JSON', 'moksa-line-login'); ?>">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 4px;">
                            <path d="M3 3h14v2H3V3zm0 4h14v2H3V7zm0 4h14v2H3v-2zm0 4h14v2H3v-2z"/>
                        </svg>
                        <span><?php _e('格式化', 'moksa-line-login'); ?></span>
                    </button>
                    <button type="button" class="moksa-json-tool-btn" id="copy_json" title="<?php _e('複製 JSON', 'moksa-line-login'); ?>">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 4px;">
                            <path d="M8 2a1 1 0 000 2h2a1 1 0 100-2H8z"/><path d="M6 4a2 2 0 012-2h2a2 2 0 012 2v2h2a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h2V4z"/>
                        </svg>
                        <span><?php _e('複製', 'moksa-line-login'); ?></span>
                    </button>
                    <button type="button" class="moksa-json-tool-btn" id="validate_json" title="<?php _e('驗證 JSON', 'moksa-line-login'); ?>">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 4px;">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span><?php _e('驗證', 'moksa-line-login'); ?></span>
                    </button>
                </div>
            </div>
            <div id="monaco-editor" style="width: 100%; flex: 1;"></div>
            <textarea id="flex_json" style="display: none;"></textarea>
        </div>

        <!-- Right Column: Preview -->
        <div class="moksa-editor-preview-col">
            <div class="preview-header">
                <h3><?php _e('即時預覽', 'moksa-line-login'); ?></h3>
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
                    <button type="button" class="moksa-preview-action-btn" id="refresh_preview" title="<?php _e('重新整理預覽', 'moksa-line-login'); ?>">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="moksa-phone-preview mobile" id="phone-preview">
                <div class="moksa-phone-header">LINE</div>
                <div class="moksa-phone-content" id="preview_container">
                    <!-- Preview content will be rendered here -->
                    <div class="flex-bubble-preview" style="background: #fff; padding: 16px; border-radius: 12px; text-align: center; color: #94a3b8; font-size: 13px;">
                        <?php _e('預覽將顯示於此', 'moksa-line-login'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var editorElement = document.getElementById('monaco-editor');
    var maxRetries = 10;
    var retryCount = 0;
    
    function initMonaco() {
        // Check if require is available
        if (typeof require === 'undefined') {
            retryCount++;
            if (retryCount < maxRetries) {
                console.log('[Monaco Editor] Waiting for require... (' + retryCount + '/' + maxRetries + ')');
                setTimeout(initMonaco, 200);
                return;
            }
            console.error('[Monaco Editor] require is not defined after ' + maxRetries + ' retries');
            editorElement.innerHTML = '<div style="padding: 20px; text-align: center; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; background: #fee2e2; margin: 20px;"><strong>Monaco Editor 載入失敗</strong><br>請確認網路連線正常，或重新整理頁面。</div>';
            return;
        }
        
        try {
            // Check if Monaco is already loaded
            if (typeof monaco !== 'undefined' && monaco.editor) {
                createEditor();
                return;
            }
            
            // Load Monaco Editor
            require(['vs/editor/editor.main'], function() {
                if (typeof monaco === 'undefined' || !monaco.editor) {
                    console.error('[Monaco Editor] monaco is not defined after loading editor.main');
                    editorElement.innerHTML = '<div style="padding: 20px; text-align: center; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; background: #fee2e2; margin: 20px;"><strong>Monaco Editor 初始化失敗</strong><br>請重新整理頁面。</div>';
                    return;
                }
                createEditor();
            }, function(err) {
                console.error('[Monaco Editor] Failed to load editor.main:', err);
                editorElement.innerHTML = '<div style="padding: 20px; text-align: center; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; background: #fee2e2; margin: 20px;"><strong>Monaco Editor 載入錯誤</strong><br>' + (err.message || err) + '<br>請重新整理頁面。</div>';
            });
        } catch (e) {
            console.error('[Monaco Editor] Exception:', e);
            editorElement.innerHTML = '<div style="padding: 20px; text-align: center; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; background: #fee2e2; margin: 20px;"><strong>Monaco Editor 初始化異常</strong><br>' + e.message + '<br>請重新整理頁面。</div>';
        }
    }
    
    function createEditor() {
        if (!editorElement) {
            console.error('[Monaco Editor] Editor element not found');
            return;
        }
        
        try {
            window.editor = monaco.editor.create(editorElement, {
                value: '{\n  "type": "bubble",\n  "body": {\n    "type": "box",\n    "layout": "vertical",\n    "contents": [\n      {\n        "type": "text",\n        "text": "Hello World",\n        "weight": "bold",\n        "size": "xl"\n      }\n    ]\n  }\n}',
                language: 'json',
                theme: 'vs-light',
                minimap: { enabled: false },
                automaticLayout: true,
                formatOnPaste: true,
                formatOnType: true,
                scrollBeyondLastLine: false,
                fontSize: 14,
                readOnly: false,
                wordWrap: 'on'
            });
            
            // Sync with hidden textarea
            window.editor.onDidChangeModelContent(function() {
                document.getElementById('flex_json').value = window.editor.getValue();
            });
            
            // Initial sync
            document.getElementById('flex_json').value = window.editor.getValue();
            
            // Trigger ready event for other scripts
            jQuery(document).trigger('moksa-monaco-ready', [window.editor]);
            
            console.log('[Monaco Editor] Editor initialized successfully');
        } catch (e) {
            console.error('[Monaco Editor] Failed to create editor:', e);
            editorElement.innerHTML = '<div style="padding: 20px; text-align: center; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; background: #fee2e2; margin: 20px;"><strong>Monaco Editor 建立失敗</strong><br>' + e.message + '<br>請重新整理頁面。</div>';
        }
    }
    
    // Start initialization
    initMonaco();
});
</script>
