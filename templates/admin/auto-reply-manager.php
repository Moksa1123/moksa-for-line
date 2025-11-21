<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('設定關鍵字自動回覆與加入好友歡迎訊息。', 'moksa-line-login'); ?></p>
        </div>
    </div>

    <div class="moksa-editor-layout" style="grid-template-columns: 400px 1fr; gap: 20px;">
        <!-- Left Column: Rules List & Greeting -->
        <div class="moksa-editor-sidebar" style="display: flex; flex-direction: column; overflow: hidden;">
            <!-- Greeting Section -->
            <div class="sidebar-section">
                <h3><?php _e('加入好友歡迎訊息', 'moksa-line-login'); ?></h3>
                <form id="greeting-form">
                    <div class="form-group" style="position: relative; margin-bottom: 10px;">
                        <textarea id="greeting_message" class="widefat" rows="3" placeholder="<?php _e('請輸入歡迎訊息...', 'moksa-line-login'); ?>" style="resize: vertical; min-height: 80px;"><?php echo esc_textarea(get_option('moksa_line_greeting_message')); ?></textarea>
                        <button type="button" id="toggle-emoji" class="button button-small" style="position: absolute; bottom: 8px; right: 8px; padding: 0 5px;">😀</button>
                        <div id="emoji-picker" style="display: none; position: absolute; bottom: 35px; right: 0; width: 250px; background: #fff; border: 1px solid #ccc; padding: 10px; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 100; max-height: 150px; overflow-y: auto; display: grid; grid-template-columns: repeat(8, 1fr); gap: 5px;">
                            <!-- Emojis will be loaded here -->
                        </div>
                    </div>
                    <button type="submit" class="button button-primary" style="width: 100%;"><?php _e('儲存歡迎訊息', 'moksa-line-login'); ?></button>
                </form>
            </div>
            
            <!-- Rules List Section -->
            <div class="sidebar-section flex-grow">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="margin: 0;"><?php _e('自動回覆規則', 'moksa-line-login'); ?></h3>
                    <button type="button" class="button button-small" id="btn-new-rule"><?php _e('新增規則', 'moksa-line-login'); ?></button>
                </div>
                
                <div class="moksa-rules-list" style="flex: 1; overflow-y: auto; padding-right: 5px;">
                    <?php
                    $autoreply_manager = Moksa_Line_AutoReply::get_instance();
                    $rules = $autoreply_manager->get_all_rules();
                    
                    if ($rules) {
                        foreach ($rules as $rule) {
                            echo '<div class="moksa-rule-item" data-id="' . $rule->id . '" style="padding: 12px; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 8px; background: #f8fafc; cursor: pointer; transition: all 0.2s;">';
                            echo '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">';
                            echo '<span style="font-weight: 600; color: #334155;">' . esc_html($rule->keyword) . '</span>';
                            echo '<span class="badge" style="background: #e2e8f0; color: #64748b; font-size: 10px; padding: 2px 6px; border-radius: 2px;">' . esc_html(ucfirst($rule->reply_type)) . '</span>';
                            echo '</div>';
                            echo '<div style="font-size: 12px; color: #64748b; display: flex; justify-content: space-between; align-items: center;">';
                            echo '<span>' . ($rule->match_type === 'exact' ? '完全符合' : '部分符合') . '</span>';
                            echo '<button class="button-link-delete delete-rule" data-id="' . $rule->id . '" style="color: #ef4444; text-decoration: none; font-size: 12px;">刪除</button>';
                            echo '</div>';
                            
                            // Hidden data for editing
                            echo '<input type="hidden" class="rule-data" ';
                            echo 'data-id="' . $rule->id . '" ';
                            echo 'data-keyword="' . esc_attr($rule->keyword) . '" ';
                            echo 'data-match="' . esc_attr($rule->match_type) . '" ';
                            echo 'data-type="' . esc_attr($rule->reply_type) . '" ';
                            echo 'data-data="' . esc_attr($rule->reply_data) . '">';
                            echo '</div>';
                        }
                    } else {
                        echo '<p class="description" style="text-align: center; padding: 20px;">' . __('尚無規則，請點擊上方按鈕新增。', 'moksa-line-login') . '</p>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Editor -->
        <div class="moksa-editor-main">
            <div class="editor-toolbar">
                <span class="editor-label" id="editor-title"><?php _e('建立新規則', 'moksa-line-login'); ?></span>
                <button type="button" class="button button-small" id="cancel_edit" style="display: none;"><?php _e('取消編輯', 'moksa-line-login'); ?></button>
            </div>
            
            <div style="padding: 20px; overflow-y: auto; flex: 1;">
                <form id="autoreply-form">
                    <input type="hidden" id="rule_id" name="id" value="">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label for="keyword" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('關鍵字', 'moksa-line-login'); ?></label>
                            <input type="text" id="keyword" class="widefat" required placeholder="<?php _e('例如：你好', 'moksa-line-login'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="match_type" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('比對方式', 'moksa-line-login'); ?></label>
                            <select id="match_type" class="widefat">
                                <option value="exact"><?php _e('完全符合', 'moksa-line-login'); ?></option>
                                <option value="partial"><?php _e('部分符合', 'moksa-line-login'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="reply_type" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('回覆類型', 'moksa-line-login'); ?></label>
                        <select id="reply_type" class="widefat">
                            <option value="text"><?php _e('文字訊息', 'moksa-line-login'); ?></option>
                            <option value="flex"><?php _e('Flex 訊息 (JSON)', 'moksa-line-login'); ?></option>
                            <option value="quick_reply"><?php _e('快速回覆組', 'moksa-line-login'); ?></option>
                        </select>
                    </div>
                    
                    <!-- Text Input -->
                    <div id="input-text" class="reply-input-group">
                        <label for="reply_text" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('回覆內容', 'moksa-line-login'); ?></label>
                        <textarea id="reply_text" class="widefat" rows="8" style="resize: vertical;"></textarea>
                    </div>
                    
                    <!-- Flex Input -->
                    <div id="input-flex" class="reply-input-group" style="display: none;">
                        <div style="display: flex; gap: 20px;">
                            <div style="flex: 1;">
                                <label for="reply_flex" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Flex 訊息 JSON', 'moksa-line-login'); ?></label>
                                <textarea id="reply_flex" class="widefat" rows="15" placeholder='{"type": "bubble", ...}' style="font-family: monospace; font-size: 12px;"></textarea>
                                <p class="description"><?php _e('請在此貼上您的 Flex Message JSON。', 'moksa-line-login'); ?></p>
                            </div>
                            <div style="width: 280px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('預覽', 'moksa-line-login'); ?></label>
                                <div class="moksa-phone-preview" style="height: 400px; border-width: 8px; border-radius: 4px;">
                                    <div class="moksa-phone-header" style="padding: 8px; font-size: 12px;">LINE</div>
                                    <div class="moksa-phone-content" id="preview_container" style="padding: 10px;">
                                        <div class="flex-bubble-preview" style="background: #fff; padding: 16px; border-radius: 4px; text-align: center; color: #94a3b8; font-size: 13px;">
                                            <?php _e('預覽將顯示於此', 'moksa-line-login'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Reply Select -->
                    <div id="input-quick_reply" class="reply-input-group" style="display: none;">
                        <label for="reply_qr" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('選擇快速回覆組', 'moksa-line-login'); ?></label>
                        <select id="reply_qr" class="widefat" required>
                            <option value=""><?php _e('-- 請選擇快速回覆組 --', 'moksa-line-login'); ?></option>
                            <?php
                            $qr_manager = Moksa_Line_QuickReply::get_instance();
                            $qrs = $qr_manager->get_all_quick_replies();
                            if ($qrs) {
                                foreach ($qrs as $qr) {
                                    echo '<option value="' . esc_attr($qr->id) . '">' . esc_html($qr->name) . '</option>';
                                }
                            } else {
                                echo '<option value="" disabled>' . __('目前沒有快速回覆組，請先建立快速回覆組。', 'moksa-line-login') . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description" style="margin-top: 8px; font-size: 12px; color: #64748b;">
                            <?php _e('💡 提示：如果沒有快速回覆組，請先前往「快速回覆管理」頁面建立。', 'moksa-line-login'); ?>
                        </p>
                    </div>
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <button type="submit" class="button button-primary button-large"><?php _e('儲存規則', 'moksa-line-login'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // --- Emoji Picker Logic ---
    var emojis = [
        '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇',
        '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚',
        '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩',
        '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣',
        '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬',
        '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗',
        '🤔', '🤭', '🤫', '🤥', '😶', '😐', '😑', '😬', '🙄', '😯',
        '😦', '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐',
        '🥴', '🤢', '🤮', '🤧', '😷', '🤒', '🤕', '🤑', '🤠', '😈',
        '👿', '👹', '👺', '🤡', '💩', '👻', '💀', '☠️', '👽', '👾',
        '🤖', '🎃', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿',
        '😾', '👋', '🤚', '🖐', '✋', '🖖', '👌', '🤏', '✌️', '🤞',
        '🤟', '🤘', '🤙', '👈', '👉', '👆', '🖕', '👇', '☝️', '👍',
        '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲', '🤝',
        '🙏', '✍️', '💅', '🤳', '💪', '🦾', '🦵', '🦿', '🦶', '👂',
        '🦻', '👃', '🧠', '🦷', '👀', '👁', '👅', '👄', '💋', '❤️',
        '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️',
        '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️', '✝️'
    ];

    var picker = $('#emoji-picker');
    var pickerInitialized = false;

    $('#toggle-emoji').on('click', function(e) {
        e.stopPropagation();
        if (!pickerInitialized) {
            emojis.forEach(function(emoji) {
                var span = $('<span style="cursor: pointer; font-size: 20px; text-align: center;">' + emoji + '</span>');
                span.on('click', function() {
                    insertAtCursor($('#greeting_message')[0], emoji);
                    picker.hide();
                });
                picker.append(span);
            });
            pickerInitialized = true;
        }
        picker.toggle();
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#emoji-picker').length && !$(e.target).is('#toggle-emoji')) {
            picker.hide();
        }
    });

    function insertAtCursor(myField, myValue) {
        if (document.selection) {
            myField.focus();
            sel = document.selection.createRange();
            sel.text = myValue;
        } else if (myField.selectionStart || myField.selectionStart == '0') {
            var startPos = myField.selectionStart;
            var endPos = myField.selectionEnd;
            myField.value = myField.value.substring(0, startPos)
                + myValue
                + myField.value.substring(endPos, myField.value.length);
        } else {
            myField.value += myValue;
        }
    }

    // Save Greeting
    $('#greeting-form').on('submit', function(e) {
        e.preventDefault();
        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_save_greeting',
            nonce: moksaLineAdmin.nonce,
            message: $('#greeting_message').val()
        }, function(response) {
            if (response.success) {
                alert('<?php _e('歡迎訊息已儲存！', 'moksa-line-login'); ?>');
            }
        });
    });

    // Toggle Inputs based on type
    $('#reply_type').on('change', function() {
        var type = $(this).val();
        $('.reply-input-group').hide();
        $('#input-' + type).show();
        
        // Trigger preview update if switching to flex
        if (type === 'flex') {
            var json = $('#reply_flex').val();
            updateFlexPreview(json);
        }
    });
    
    // Live Preview for Flex Message
    $('#reply_flex').on('input', function() {
        var json = $(this).val();
        // Debounce
        clearTimeout(window.flexPreviewTimeout);
        window.flexPreviewTimeout = setTimeout(function() {
            updateFlexPreview(json);
        }, 500);
    });
    
    function updateFlexPreview(jsonStr) {
        var container = $('#preview_container');
        
        try {
            var flexObj = JSON.parse(jsonStr);
            
            // Use Shared Renderer
            if (window.MoksaFlexRenderer) {
                window.MoksaFlexRenderer.render(flexObj, container);
            } else {
                container.html('<div style="background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 12px; border-radius: 4px; font-size: 13px;"><strong>Flex Renderer not loaded.</strong><br>請重新整理頁面或檢查瀏覽器控制台。</div>');
            }
            
        } catch (e) {
            if (!jsonStr || !jsonStr.trim()) return;
            container.html('<div style="background: #fff; padding: 12px 16px; border-radius: 4px; color: #dc2626; border: 1px solid #fecaca; font-size: 13px;">JSON 格式錯誤：' + e.message + '</div>');
        }
    }
    
    // Save
    $('#autoreply-form').on('submit', function(e) {
        e.preventDefault();
        
        var type = $('#reply_type').val();
        var data = '';
        
        if (type === 'text') data = $('#reply_text').val();
        else if (type === 'flex') data = $('#reply_flex').val();
        else if (type === 'quick_reply') data = $('#reply_qr').val();
        
        if (!data) {
            alert('<?php _e('請輸入回覆內容。', 'moksa-line-login'); ?>');
            return;
        }
        
        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_save_auto_reply',
            nonce: moksaLineAdmin.nonce,
            id: $('#rule_id').val(),
            keyword: $('#keyword').val(),
            match_type: $('#match_type').val(),
            reply_type: type,
            reply_data: data
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('<?php _e('錯誤：', 'moksa-line-login'); ?> ' + response.data);
            }
        });
    });
    
    // Edit (Click on list item)
    $('.moksa-rule-item').on('click', function(e) {
        if ($(e.target).hasClass('delete-rule')) return; // Ignore delete button click
        
        // Highlight selected
        $('.moksa-rule-item').css('border-color', '#e2e8f0').css('background', '#f8fafc');
        $(this).css('border-color', '#3b82f6').css('background', '#eff6ff');
        
        var input = $(this).find('.rule-data');
        var id = input.data('id');
        var keyword = input.data('keyword');
        var match = input.data('match');
        var type = input.data('type');
        var data = input.data('data');
        
        $('#rule_id').val(id);
        $('#keyword').val(keyword);
        $('#match_type').val(match);
        $('#reply_type').val(type).trigger('change');
        
        if (type === 'text') {
            $('#reply_text').val(data);
        } else if (type === 'flex') {
            var jsonStr = '';
            if (typeof data === 'object') {
                jsonStr = JSON.stringify(data, null, 2);
            } else {
                jsonStr = data;
            }
            $('#reply_flex').val(jsonStr);
            updateFlexPreview(jsonStr);
        } else if (type === 'quick_reply') {
            $('#reply_qr').val(data || '');
        }
        
        $('#editor-title').text('<?php _e('編輯規則', 'moksa-line-login'); ?>');
        $('#cancel_edit').show();
    });
    
    // New Rule Button
    $('#btn-new-rule').on('click', function() {
        resetForm();
    });
    
    // Cancel Edit
    $('#cancel_edit').on('click', function() {
        resetForm();
    });
    
    function resetForm() {
        $('#rule_id').val('');
        $('#keyword').val('');
        $('#reply_text').val('');
        $('#reply_flex').val('');
        $('#editor-title').text('<?php _e('建立新規則', 'moksa-line-login'); ?>');
        $('#cancel_edit').hide();
        $('.moksa-rule-item').css('border-color', '#e2e8f0').css('background', '#f8fafc');
        
        // Clear preview
        $('#preview_container').html('<div class="flex-bubble-preview" style="background: #fff; padding: 16px; border-radius: 4px; text-align: center; color: #94a3b8; font-size: 13px;"><?php _e('預覽將顯示於此', 'moksa-line-login'); ?></div>');
    }
    
    // Delete
    $('.delete-rule').on('click', function(e) {
        e.stopPropagation();
        if (!confirm('<?php _e('確定要刪除嗎？', 'moksa-line-login'); ?>')) return;
        
        var id = $(this).data('id');
        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_delete_auto_reply',
            nonce: moksaLineAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                location.reload();
            }
        });
    });
});
</script>
