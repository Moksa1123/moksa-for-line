<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('建立並管理您的 LINE 圖文選單 (Rich Menu)。', 'moksa-line-login'); ?></p>
        </div>
    </div>

    <div class="moksa-editor-layout" style="display: block;">
        <!-- Create New Rich Menu -->
        <div class="moksa-card" style="max-width: 800px; margin-bottom: 30px;">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;"><?php _e('建立新圖文選單', 'moksa-line-login'); ?></h2>
            <form id="moksa-line-richmenu-form">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="rm_name" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('名稱', 'moksa-line-login'); ?></label>
                        <input type="text" id="rm_name" name="name" class="widefat" required placeholder="<?php _e('僅供內部識別用', 'moksa-line-login'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="rm_chat_bar" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('聊天列文字', 'moksa-line-login'); ?></label>
                        <input type="text" id="rm_chat_bar" name="chat_bar_text" class="widefat" value="選單" required placeholder="<?php _e('顯示於聊天室底部的文字', 'moksa-line-login'); ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="rm_size" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('尺寸', 'moksa-line-login'); ?></label>
                    <select id="rm_size" name="size" class="widefat" style="max-width: 300px;">
                        <option value="full"><?php _e('全版 (2500x1686)', 'moksa-line-login'); ?></option>
                        <option value="half"><?php _e('半版 (2500x843)', 'moksa-line-login'); ?></option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('圖片', 'moksa-line-login'); ?></label>
                    <input type="hidden" id="rm_image_id" name="image_id" required>
                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <button type="button" class="button" id="rm_upload_image">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 6px;">
                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                            </svg>
                            <?php _e('選擇圖片', 'moksa-line-login'); ?>
                        </button>
                        <div id="rm_image_preview" style="max-width: 300px; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;"></div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 25px;">
                    <label for="rm_areas" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('點擊區域 (JSON)', 'moksa-line-login'); ?></label>
                    <textarea id="rm_areas" name="areas" rows="5" class="widefat code" placeholder='[{"bounds":{"x":0,"y":0,"width":2500,"height":1686},"action":{"type":"message","text":"Hello"}}]' style="font-family: monospace;"></textarea>
                    <p class="description" style="margin-top: 5px;">
                        <?php printf(__('請輸入 JSON 格式的點擊區域定義。建議使用 <a href="%s" target="_blank">LINE Bot Designer</a> 產生此 JSON。', 'moksa-line-login'), 'https://developers.line.biz/en/services/bot-designer/'); ?>
                    </p>
                </div>

                <div style="padding-top: 20px; border-top: 1px solid #f1f5f9;">
                    <button type="submit" class="button button-primary button-large"><?php _e('建立圖文選單', 'moksa-line-login'); ?></button>
                </div>
            </form>
        </div>
        
        <!-- Existing Rich Menus -->
        <div class="moksa-card" style="max-width: 100%;">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;"><?php _e('現有圖文選單', 'moksa-line-login'); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 120px;"><?php _e('圖片', 'moksa-line-login'); ?></th>
                        <th><?php _e('名稱', 'moksa-line-login'); ?></th>
                        <th><?php _e('尺寸', 'moksa-line-login'); ?></th>
                        <th><?php _e('預設', 'moksa-line-login'); ?></th>
                        <th><?php _e('操作', 'moksa-line-login'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'moksa_line_richmenus';
                    // Check if table exists first to avoid errors on fresh install before activation fully completes
                    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                        $richmenus = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
                    } else {
                        $richmenus = array();
                    }
                    
                    if ($richmenus):
                        foreach ($richmenus as $rm):
                    ?>
                    <tr>
                        <td>
                            <div style="width: 100px; height: 60px; background-image: url('<?php echo esc_url($rm->image_url); ?>'); background-size: cover; background-position: center; border-radius: 4px; border: 1px solid #e2e8f0;"></div>
                        </td>
                        <td style="vertical-align: middle; font-weight: 500;"><?php echo esc_html($rm->name); ?></td>
                        <td style="vertical-align: middle;"><?php echo esc_html($rm->size); ?></td>
                        <td style="vertical-align: middle;">
                            <?php if ($rm->is_default): ?>
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="#10b981" style="vertical-align: middle;">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            <?php else: ?>
                                <button type="button" class="button button-small set-default-rm" data-id="<?php echo esc_attr($rm->id); ?>">
                                    <?php _e('設為預設', 'moksa-line-login'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: middle;">
                            <button type="button" class="button button-small button-link-delete delete-rm" data-id="<?php echo esc_attr($rm->id); ?>" style="color: #ef4444; border-color: #fecaca;">
                                <?php _e('刪除', 'moksa-line-login'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 20px; color: #64748b;"><?php _e('目前沒有圖文選單。', 'moksa-line-login'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Image Upload
    var frame;
    $('#rm_upload_image').on('click', function(e) {
        e.preventDefault();
        if (frame) { frame.open(); return; }
        
        frame = wp.media({
            title: '<?php _e('選擇圖文選單圖片', 'moksa-line-login'); ?>',
            button: { text: '<?php _e('使用此圖片', 'moksa-line-login'); ?>' },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#rm_image_id').val(attachment.id);
            $('#rm_image_preview').html('<img src="' + attachment.url + '" style="width: 100%; display: block;">');
        });
        
        frame.open();
    });
    
    // Create Form Submit
    $('#moksa-line-richmenu-form').on('submit', function(e) {
        e.preventDefault();
        var data = $(this).serialize() + '&action=moksa_line_create_richmenu&nonce=' + moksaLineAdmin.nonce;
        
        $.post(moksaLineAdmin.ajaxUrl, data, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data);
            }
        });
    });
    
    // Delete
    $('.delete-rm').on('click', function() {
        if (!confirm('<?php _e('確定要刪除此圖文選單嗎？', 'moksa-line-login'); ?>')) return;
        var id = $(this).data('id');
        
        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_delete_richmenu',
            nonce: moksaLineAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data);
            }
        });
    });
    
    // Set Default
    $('.set-default-rm').on('click', function() {
        var id = $(this).data('id');
        
        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_set_default_richmenu',
            nonce: moksaLineAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data);
            }
        });
    });
});
</script>

