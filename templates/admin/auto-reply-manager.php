<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="moksa-line-flex-container" style="display: flex; gap: 20px;">
        
        <!-- List Column -->
        <div class="card" style="flex: 2;">
            <h2><?php _e('加入好友歡迎訊息', 'moksa-line-login'); ?></h2>
            <p><?php _e('當使用者將您的帳號加為好友時發送的訊息。', 'moksa-line-login'); ?></p>
            <form id="greeting-form">
                <div class="form-group">
                    <textarea id="greeting_message" class="widefat" rows="3" placeholder="<?php _e('請輸入歡迎訊息...', 'moksa-line-login'); ?>"><?php echo esc_textarea(get_option('moksa_line_greeting_message')); ?></textarea>
                </div>
                <button type="submit" class="button button-primary" style="margin-top: 10px;"><?php _e('儲存歡迎訊息', 'moksa-line-login'); ?></button>
            </form>
            
            <hr style="margin: 20px 0;">
            
            <h2><?php _e('自動回覆規則', 'moksa-line-login'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('關鍵字', 'moksa-line-login'); ?></th>
                        <th><?php _e('比對方式', 'moksa-line-login'); ?></th>
                        <th><?php _e('回覆類型', 'moksa-line-login'); ?></th>
                        <th><?php _e('操作', 'moksa-line-login'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $autoreply_manager = Moksa_Line_AutoReply::get_instance();
                    $rules = $autoreply_manager->get_all_rules();
                    
                    if ($rules) {
                        foreach ($rules as $rule) {
                            echo '<tr>';
                            echo '<td>' . esc_html($rule->keyword) . '</td>';
                            echo '<td>' . esc_html(ucfirst($rule->match_type)) . '</td>';
                            echo '<td>' . esc_html(ucfirst($rule->reply_type)) . '</td>';
                            echo '<td>';
                            echo '<button class="button edit-rule" 
                                    data-id="' . $rule->id . '" 
                                    data-keyword="' . esc_attr($rule->keyword) . '" 
                                    data-match="' . esc_attr($rule->match_type) . '" 
                                    data-type="' . esc_attr($rule->reply_type) . '" 
                                    data-data="' . esc_attr($rule->reply_data) . '">' . __('編輯', 'moksa-line-login') . '</button> ';
                            echo '<button class="button button-link-delete delete-rule" data-id="' . $rule->id . '">' . __('刪除', 'moksa-line-login') . '</button>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="4">' . __('找不到規則。', 'moksa-line-login') . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Editor Column -->
        <div class="card" style="flex: 1;">
            <h2 id="editor-title"><?php _e('建立新規則', 'moksa-line-login'); ?></h2>
            <form id="autoreply-form">
                <input type="hidden" id="rule_id" name="id" value="">
                
                <div class="form-group">
                    <label for="keyword"><?php _e('關鍵字', 'moksa-line-login'); ?></label>
                    <input type="text" id="keyword" class="widefat" required placeholder="<?php _e('例如：你好', 'moksa-line-login'); ?>">
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label for="match_type"><?php _e('比對方式', 'moksa-line-login'); ?></label>
                    <select id="match_type" class="widefat">
                        <option value="exact"><?php _e('完全符合', 'moksa-line-login'); ?></option>
                        <option value="partial"><?php _e('部分符合', 'moksa-line-login'); ?></option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label for="reply_type"><?php _e('回覆類型', 'moksa-line-login'); ?></label>
                    <select id="reply_type" class="widefat">
                        <option value="text"><?php _e('文字訊息', 'moksa-line-login'); ?></option>
                        <option value="flex"><?php _e('Flex 訊息 (JSON)', 'moksa-line-login'); ?></option>
                        <option value="quick_reply"><?php _e('快速回覆組', 'moksa-line-login'); ?></option>
                    </select>
                </div>
                
                <!-- Text Input -->
                <div id="input-text" class="reply-input-group" style="margin-top: 15px;">
                    <label for="reply_text"><?php _e('回覆內容', 'moksa-line-login'); ?></label>
                    <textarea id="reply_text" class="widefat" rows="5"></textarea>
                </div>
                
                <!-- Flex Input -->
                <div id="input-flex" class="reply-input-group" style="margin-top: 15px; display: none;">
                    <label for="reply_flex"><?php _e('Flex 訊息 JSON', 'moksa-line-login'); ?></label>
                    <div style="display: flex; gap: 15px; align-items: flex-start;">
                        <div style="flex: 1;">
                            <textarea id="reply_flex" class="widefat" rows="10" placeholder='{"type": "bubble", ...}'></textarea>
                            <p class="description"><?php _e('請在此貼上您的 Flex Message JSON。', 'moksa-line-login'); ?></p>
                        </div>
                        <!-- Preview Container -->
                        <div class="phone-preview" style="width: 300px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);">
                            <div style="text-align: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 12px; font-weight: 600; color: #1e293b; font-size: 14px;">
                                LINE
                            </div>
                            <div id="preview_container" style="height: 300px; overflow-y: auto; background: #e2e8f0; padding: 12px; border-radius: 8px;">
                                <div class="flex-bubble-preview" style="background: #fff; padding: 16px; border-radius: 12px; text-align: center; color: #94a3b8; font-size: 13px;">
                                    <?php _e('預覽將顯示於此', 'moksa-line-login'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Reply Select -->
                <div id="input-quick_reply" class="reply-input-group" style="margin-top: 15px; display: none;">
                    <label for="reply_qr"><?php _e('選擇快速回覆組', 'moksa-line-login'); ?></label>
                    <select id="reply_qr" class="widefat">
                        <?php
                        $qr_manager = Moksa_Line_QuickReply::get_instance();
                        $qrs = $qr_manager->get_all_quick_replies();
                        if ($qrs) {
                            foreach ($qrs as $qr) {
                                echo '<option value="' . $qr->id . '">' . esc_html($qr->name) . '</option>';
                            }
                        } else {
                            echo '<option value="">' . __('找不到快速回覆組', 'moksa-line-login') . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="button button-primary"><?php _e('儲存規則', 'moksa-line-login'); ?></button>
                    <button type="button" class="button" id="cancel_edit" style="display: none;"><?php _e('取消', 'moksa-line-login'); ?></button>
                </div>
            </form>
        </div>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
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
            if (window.updatePreview && json) {
                window.updatePreview(json);
            }
        }
    });
    
    // Live Preview for Flex Message
    $('#reply_flex').on('input', function() {
        var json = $(this).val();
        if (window.updatePreview) {
            // Debounce
            clearTimeout(window.flexPreviewTimeout);
            window.flexPreviewTimeout = setTimeout(function() {
                window.updatePreview(json);
            }, 500);
        }
    });
    
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
    
    // Edit
    $('.edit-rule').on('click', function() {
        var id = $(this).data('id');
        var keyword = $(this).data('keyword');
        var match = $(this).data('match');
        var type = $(this).data('type');
        var data = $(this).data('data');
        
        $('#rule_id').val(id);
        $('#keyword').val(keyword);
        $('#match_type').val(match);
        $('#reply_type').val(type).trigger('change');
        
        if (type === 'text') $('#reply_text').val(data);
        else if (type === 'flex') {
            var jsonStr = '';
            if (typeof data === 'object') {
                jsonStr = JSON.stringify(data, null, 2);
            } else {
                jsonStr = data;
            }
            $('#reply_flex').val(jsonStr);
            
            // Trigger preview
            if (window.updatePreview) {
                window.updatePreview(jsonStr);
            }
        }
        else if (type === 'quick_reply') $('#reply_qr').val(data);
        
        $('#editor-title').text('<?php _e('編輯規則', 'moksa-line-login'); ?>');
        $('#cancel_edit').show();
    });
    
    // Cancel Edit
    $('#cancel_edit').on('click', function() {
        $('#rule_id').val('');
        $('#keyword').val('');
        $('#reply_text').val('');
        $('#reply_flex').val('');
        $('#editor-title').text('<?php _e('建立新規則', 'moksa-line-login'); ?>');
        $(this).hide();
        
        // Clear preview
        if (window.updatePreview) {
            $('#preview_container').html('<div class="flex-bubble-preview" style="background: #fff; padding: 16px; border-radius: 12px; text-align: center; color: #94a3b8; font-size: 13px;"><?php _e('預覽將顯示於此', 'moksa-line-login'); ?></div>');
        }
    });
    
    // Delete
    $('.delete-rule').on('click', function() {
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
