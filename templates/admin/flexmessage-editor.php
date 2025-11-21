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

        <!-- Center Column: JSON Input -->
        <div class="moksa-editor-main">
            <div class="moksa-line-simulator-guide" style="background: linear-gradient(135deg, #06C755 0%, #05B048 100%); color: white; padding: 24px; border-radius: 4px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(6, 199, 85, 0.3);">
                <div style="display: flex; align-items: center; margin-bottom: 16px;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 12px;">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <div>
                        <h3 style="margin: 0 0 4px 0; font-size: 18px; font-weight: 600;"><?php _e('使用 LINE 官方 Flex Message Simulator', 'moksa-line-login'); ?></h3>
                        <p style="margin: 0; font-size: 14px; opacity: 0.95;"><?php _e('我們建議您使用 LINE 官方的 Flex Message Simulator 來設計您的訊息，功能更完整且更易於使用。', 'moksa-line-login'); ?></p>
                    </div>
                </div>
                <a href="https://developers.line.biz/flex-simulator/" target="_blank" class="button button-primary button-large" style="background: white; color: #06C755; border: none; font-weight: 600; padding: 12px 24px; text-decoration: none; display: inline-block; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); transition: transform 0.2s;">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                        <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                    </svg>
                    <?php _e('前往 LINE Flex Message Simulator', 'moksa-line-login'); ?>
                </a>
            </div>

            <div class="editor-toolbar">
                <div class="editor-toolbar-left">
                    <span class="editor-label"><?php _e('JSON 輸入', 'moksa-line-login'); ?></span>
                    <span class="editor-status" id="editor-status"></span>
                </div>
                <div class="editor-toolbar-right">
                    <button type="button" class="moksa-json-tool-btn" id="format_json" title="<?php _e('格式化 JSON', 'moksa-line-login'); ?>">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 4px;">
                            <path d="M3 3h14v2H3V3zm0 4h14v2H3V7zm0 4h14v2H3v-2zm0 4h14v2H3v-2z"/>
                        </svg>
                        <span><?php _e('格式化', 'moksa-line-login'); ?></span>
                    </button>
                    <button type="button" class="moksa-json-tool-btn" id="validate_json" title="<?php _e('驗證 JSON', 'moksa-line-login'); ?>">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 4px;">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span><?php _e('驗證', 'moksa-line-login'); ?></span>
                    </button>
                </div>
            </div>
            <textarea id="flex_json" class="widefat" rows="20" style="font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; padding: 16px; border: 1px solid #e2e8f0; border-radius: 4px; resize: vertical;" placeholder='<?php _e('請從 LINE Flex Message Simulator 複製 JSON 並貼上於此...', 'moksa-line-login'); ?>'></textarea>
            <p class="description" style="margin-top: 8px; font-size: 12px; color: #64748b;">
                <?php _e('💡 提示：在 LINE Flex Message Simulator 中設計好訊息後，點擊右上角的「View as JSON」按鈕，複製 JSON 並貼上於此。', 'moksa-line-login'); ?>
            </p>
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
                    <div class="flex-bubble-preview" style="background: #fff; padding: 16px; border-radius: 4px; text-align: center; color: #94a3b8; font-size: 13px;">
                        <?php _e('預覽將顯示於此', 'moksa-line-login'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var $jsonTextarea = $('#flex_json');
    var $editorStatus = $('#editor-status');
    
    // Format JSON
    $('#format_json').on('click', function() {
        try {
            var jsonStr = $jsonTextarea.val().trim();
            if (!jsonStr) {
                alert('<?php _e('請先輸入 JSON', 'moksa-line-login'); ?>');
                return;
            }
            var jsonObj = JSON.parse(jsonStr);
            $jsonTextarea.val(JSON.stringify(jsonObj, null, 2));
            updateEditorStatus('success', '<?php _e('格式化成功', 'moksa-line-login'); ?>');
        } catch (e) {
            updateEditorStatus('error', '<?php _e('JSON 格式錯誤', 'moksa-line-login'); ?>: ' + e.message);
        }
    });
    
    // Validate JSON
    $('#validate_json').on('click', function() {
        try {
            var jsonStr = $jsonTextarea.val().trim();
            if (!jsonStr) {
                updateEditorStatus('warning', '<?php _e('請輸入 JSON', 'moksa-line-login'); ?>');
                return;
            }
            JSON.parse(jsonStr);
            updateEditorStatus('success', '<?php _e('JSON 格式正確', 'moksa-line-login'); ?>');
            // Trigger preview update
            if (typeof updatePreview === 'function') {
                updatePreview(jsonStr);
            }
        } catch (e) {
            updateEditorStatus('error', '<?php _e('JSON 格式錯誤', 'moksa-line-login'); ?>: ' + e.message);
        }
    });
    
    // Auto validate on input (debounced)
    var validateTimeout;
    $jsonTextarea.on('input', function() {
        clearTimeout(validateTimeout);
        validateTimeout = setTimeout(function() {
            var jsonStr = $jsonTextarea.val().trim();
            if (jsonStr) {
                try {
                    JSON.parse(jsonStr);
                    updateEditorStatus('success', '<?php _e('JSON 格式正確', 'moksa-line-login'); ?>');
                    if (typeof updatePreview === 'function') {
                        updatePreview(jsonStr);
                    }
                } catch (e) {
                    updateEditorStatus('error', '<?php _e('JSON 格式錯誤', 'moksa-line-login'); ?>');
                }
            } else {
                $editorStatus.text('').removeClass('status-success status-error status-warning');
            }
        }, 500);
    });
    
    function updateEditorStatus(type, message) {
        $editorStatus.text(message)
            .removeClass('status-success status-error status-warning')
            .addClass('status-' + type);
    }
    
    // Initial preview if there's content
    if ($jsonTextarea.val().trim()) {
        $('#validate_json').trigger('click');
    }
});
</script>
