<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="moksa-line-flex-container" style="display: flex; gap: 20px;">
        
        <!-- List Column -->
        <div class="card" style="flex: 1;">
            <h2><?php _e('Quick Reply Sets', 'moksa-line-login'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'moksa-line-login'); ?></th>
                        <th><?php _e('Items Count', 'moksa-line-login'); ?></th>
                        <th><?php _e('Actions', 'moksa-line-login'); ?></th>
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
                            echo '<td>' . esc_html($reply->name) . '</td>';
                            echo '<td>' . $count . '</td>';
                            echo '<td>';
                            echo '<button class="button edit-quick-reply" data-id="' . $reply->id . '" data-name="' . esc_attr($reply->name) . '" data-items="' . esc_attr($reply->items) . '">' . __('Edit', 'moksa-line-login') . '</button> ';
                            echo '<button class="button button-link-delete delete-quick-reply" data-id="' . $reply->id . '">' . __('Delete', 'moksa-line-login') . '</button>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="3">' . __('No Quick Replies found.', 'moksa-line-login') . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Editor Column -->
        <div class="card" style="flex: 1;">
            <h2 id="editor-title"><?php _e('Create New Quick Reply', 'moksa-line-login'); ?></h2>
            <form id="quick-reply-form">
                <input type="hidden" id="qr_id" name="id" value="">
                
                <div class="form-group">
                    <label for="qr_name"><?php _e('Name', 'moksa-line-login'); ?></label>
                    <input type="text" id="qr_name" class="widefat" required placeholder="<?php _e('e.g., Default Quick Reply', 'moksa-line-login'); ?>">
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label><?php _e('Items', 'moksa-line-login'); ?></label>
                    <div id="qr_items_container">
                        <!-- Items will be added here -->
                    </div>
                    <button type="button" class="button" id="add_qr_item" style="margin-top: 10px;">
                        + <?php _e('Add Item', 'moksa-line-login'); ?>
                    </button>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="button button-primary"><?php _e('Save Quick Reply', 'moksa-line-login'); ?></button>
                    <button type="button" class="button" id="cancel_edit" style="display: none;"><?php _e('Cancel', 'moksa-line-login'); ?></button>
                </div>
            </form>
        </div>
        
    </div>
</div>

<script type="text/template" id="qr_item_template">
    <div class="qr-item" style="background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; margin-bottom: 10px; border-radius: 8px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
            <strong><?php _e('Item', 'moksa-line-login'); ?></strong>
            <button type="button" class="button-link-delete remove-qr-item">x</button>
        </div>
        <p>
            <label><?php _e('Label', 'moksa-line-login'); ?></label>
            <input type="text" class="widefat qr-label" maxlength="20" required>
        </p>
        <p>
            <label><?php _e('Text to Send', 'moksa-line-login'); ?></label>
            <input type="text" class="widefat qr-text" maxlength="300" required>
        </p>
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
            alert('請至少新增一個項目。');
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
        var items = $(this).data('items'); // Already object if parsed by data-attr, but let's check
        
        if (typeof items === 'string') {
            items = JSON.parse(items);
        }
        
        $('#qr_id').val(id);
        $('#qr_name').val(name);
        $('#editor-title').text('<?php _e('Edit Quick Reply', 'moksa-line-login'); ?>');
        $('#cancel_edit').show();
        
        $('#qr_items_container').empty();
        var template = $('#qr_item_template').html();
        
        items.forEach(function(item) {
            var $el = $(template);
            $el.find('.qr-label').val(item.action.label);
            $el.find('.qr-text').val(item.action.text);
            $('#qr_items_container').append($el);
        });
    });
    
    // Cancel Edit
    $('#cancel_edit').on('click', function() {
        $('#qr_id').val('');
        $('#qr_name').val('');
        $('#qr_items_container').empty();
        $('#editor-title').text('<?php _e('Create New Quick Reply', 'moksa-line-login'); ?>');
        $(this).hide();
    });
    
    // Delete
    $('.delete-quick-reply').on('click', function() {
        if (!confirm('確定要刪除嗎？')) return;
        
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
