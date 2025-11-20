<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('設計並發送 Flex Message 給您的使用者。', 'moksa-line-login'); ?></p>
        </div>
        <div class="header-actions">
            <button type="button" class="button" id="load_sample">
                <span class="dashicons dashicons-welcome-add-page"></span> <?php _e('載入範例', 'moksa-line-login'); ?>
            </button>
            <button type="button" class="button button-primary button-large" id="send_flex">
                <span class="dashicons dashicons-paperplane"></span> <?php _e('立即發送', 'moksa-line-login'); ?>
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
                <span class="editor-label"><?php _e('JSON 編輯器', 'moksa-line-login'); ?></span>
                <span class="editor-status" id="editor-status"></span>
            </div>
            <div id="monaco-editor" style="width: 100%; flex: 1;"></div>
            <textarea id="flex_json" style="display: none;"></textarea>
        </div>

        <!-- Right Column: Preview -->
        <div class="moksa-editor-preview-col">
            <div class="preview-header">
                <h3><?php _e('即時預覽', 'moksa-line-login'); ?></h3>
            </div>
            <div class="moksa-phone-preview">
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

<!-- Load Monaco Editor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.36.1/min/vs/loader.min.js"></script>
<script>
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.36.1/min/vs' }});
    require(['vs/editor/editor.main'], function() {
        window.editor = monaco.editor.create(document.getElementById('monaco-editor'), {
            value: '{\n  "type": "bubble",\n  "body": {\n    "type": "box",\n    "layout": "vertical",\n    "contents": [\n      {\n        "type": "text",\n        "text": "Hello World",\n        "weight": "bold",\n        "size": "xl"\n      }\n    ]\n  }\n}',
            language: 'json',
            theme: 'vs-light',
            minimap: { enabled: false },
            automaticLayout: true,
            formatOnPaste: true,
            formatOnType: true,
            scrollBeyondLastLine: false,
            fontSize: 14
        });
        
        // Sync with hidden textarea
        window.editor.onDidChangeModelContent(function() {
            document.getElementById('flex_json').value = window.editor.getValue();
        });
        
        // Initial sync
        document.getElementById('flex_json').value = window.editor.getValue();
        
        // Trigger ready event for other scripts
        jQuery(document).trigger('moksa-monaco-ready', [window.editor]);
    });
</script>
