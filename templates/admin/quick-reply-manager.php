<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('建立並管理您的 LINE 快速回覆 (Quick Reply)。', 'moksa-line-login'); ?></p>
        </div>
    </div>

    <div class="moksa-editor-layout" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        
        <!-- List Column -->
        <div class="moksa-card" style="height: fit-content;">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;"><?php _e('現有快速回覆', 'moksa-line-login'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('名稱', 'moksa-line-login'); ?></th>
                        <th><?php _e('項目數量', 'moksa-line-login'); ?></th>
                        <th style="width: 120px;"><?php _e('操作', 'moksa-line-login'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $quick_reply_manager = Moksa_Line_QuickReply::get_instance();
                    $replies = $quick_reply_manager->get_all_quick_replies();
                    
                    if ($replies) {
                        foreach ($replies as $reply) {
                            $items = json_decode($reply->items, true);
                            $count = is_array($items) ? count($items) : 0;
                            echo '<tr>';
                            echo '<td style="vertical-align: middle; font-weight: 500;">' . esc_html($reply->name) . '</td>';
                            echo '<td style="vertical-align: middle;"><span class="moksa-badge">' . $count . '</span></td>';
                            echo '<td style="vertical-align: middle;">';
                            echo '<button class="button button-small edit-quick-reply" data-id="' . $reply->id . '" data-name="' . esc_attr($reply->name) . '" data-items="' . esc_attr($reply->items) . '">' . __('編輯', 'moksa-line-login') . '</button> ';
                            echo '<button class="button button-small button-link-delete delete-quick-reply" data-id="' . $reply->id . '" style="color: #ef4444; border-color: #fecaca;">' . __('刪除', 'moksa-line-login') . '</button>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="3" style="text-align: center; padding: 20px; color: #64748b;">' . __('目前沒有快速回覆。', 'moksa-line-login') . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Editor Column -->
        <div class="moksa-card" style="height: fit-content;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">
                <h2 id="editor-title" style="margin: 0;"><?php _e('建立快速回覆', 'moksa-line-login'); ?></h2>
                <button type="button" class="button" id="cancel_edit" style="display: none; font-size: 12px;"><?php _e('取消編輯', 'moksa-line-login'); ?></button>
            </div>

            <form id="quick-reply-form">
                <input type="hidden" id="qr_id" name="id" value="">
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="qr_name" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('名稱', 'moksa-line-login'); ?></label>
                    <input type="text" id="qr_name" class="widefat" required placeholder="<?php _e('例如：預設快速回覆', 'moksa-line-login'); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600;"><?php _e('回覆項目', 'moksa-line-login'); ?></label>
                    <div id="qr_items_container" style="display: flex; flex-direction: column; gap: 10px;">
                        <!-- Items will be added here -->
                    </div>
                    <button type="button" class="button" id="add_qr_item" style="margin-top: 15px; width: 100%; border-style: dashed;">
                        <span class="dashicons dashicons-plus" style="margin-top: 3px;"></span> <?php _e('新增項目', 'moksa-line-login'); ?>
                    </button>
                </div>
                
                <div style="padding-top: 20px; border-top: 1px solid #f1f5f9;">
                    <button type="submit" class="button button-primary button-large"><?php _e('儲存快速回覆', 'moksa-line-login'); ?></button>
                </div>
            </form>
        </div>
        
    </div>
</div>

<script type="text/template" id="qr_item_template">
    <div class="qr-item" style="background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; position: relative;">
        <button type="button" class="button-link-delete remove-qr-item" style="position: absolute; top: 10px; right: 10px; color: #94a3b8; text-decoration: none;">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
        
        <div style="margin-bottom: 10px;">
            <label style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;"><?php _e('標籤 (Label)', 'moksa-line-login'); ?></label>
            <input type="text" class="widefat qr-label" maxlength="20" required placeholder="<?php _e('按鈕上顯示的文字', 'moksa-line-login'); ?>" style="font-size: 13px;">
        </div>
        
        <div>
            <label style="display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;"><?php _e('發送文字 (Text)', 'moksa-line-login'); ?></label>
            <input type="text" class="widefat qr-text" maxlength="300" required placeholder="<?php _e('使用者點擊後發送的文字', 'moksa-line-login'); ?>" style="font-size: 13px;">
        </div>
    </div>
</script>

<script>
jQuery(document).ready(function($) {
    
    // Add Item
    $('#add_qr_item').on('click', function() {
        var template = $('#qr_item_template').html();
        $('#qr_items_container').append(template);
    });
    
    // Remove Item
    $(document).on('click', '.remove-qr-item', function() {
        $(this).closest('.qr-item').remove();
    });
    
    // Save
    $('#quick-reply-form').on('submit', function(e) {
        e.preventDefault();
        
        var items = [];
        $('.qr-item').each(function() {
            items.push({
                type: 'action',
                action: {
                    type: 'message',
                    label: $(this).find('.qr-label').val(),
                    text: $(this).find('.qr-text').val()
                }
            });
        });
        
        if (items.length === 0) {
            alert('<?php _e('請至少新增一個項目。', 'moksa-line-login'); ?>');
            return;
        }
        
        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_save_quick_reply',
            nonce: moksaLineAdmin.nonce,
            name: $('#qr_name').val(),
            id: $('#qr_id').val(),
            items: JSON.stringify(items)
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('錯誤：' + response.data);
            }
        });
    });
    
    // Edit
    $('.edit-quick-reply').on('click', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var items = $(this).data('items');
        
        if (typeof items === 'string') {
            items = JSON.parse(items);
        }
        
        $('#qr_id').val(id);
        $('#qr_name').val(name);
        $('#editor-title').text('<?php _e('編輯快速回覆', 'moksa-line-login'); ?>');
        $('#cancel_edit').show();
        
        $('#qr_items_container').empty();
        var template = $('#qr_item_template').html();
        
        items.forEach(function(item) {
            var $el = $(template);
            $el.find('.qr-label').val(item.action.label);
            $el.find('.qr-text').val(item.action.text);
            $('#qr_items_container').append($el);
        });
        
        // Scroll to form
        $('html, body').animate({
            scrollTop: $("#quick-reply-form").offset().top - 100
        }, 500);
    });
    
    // Cancel Edit
    $('#cancel_edit').on('click', function() {
        $('#qr_id').val('');
        $('#qr_name').val('');
        $('#qr_items_container').empty();
        $('#editor-title').text('<?php _e('建立快速回覆', 'moksa-line-login'); ?>');
        $(this).hide();
    });
    
    // Delete
    $('.delete-quick-reply').on('click', function() {
        if (!confirm('<?php _e('確定要刪除嗎？', 'moksa-line-login'); ?>')) return;
        
        var id = $(this).data('id');
        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_delete_quick_reply',
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
