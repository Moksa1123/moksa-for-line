<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('建立並管理您的 LINE 影像地圖 (Imagemap)。', 'moksa-line-login'); ?></p>
        </div>
    </div>

    <div class="moksa-editor-layout" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        
        <!-- List Column -->
        <div class="moksa-card" style="height: fit-content;">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;"><?php _e('現有影像地圖', 'moksa-line-login'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('標題', 'moksa-line-login'); ?></th>
                        <th><?php _e('Base URL', 'moksa-line-login'); ?></th>
                        <th style="width: 120px;"><?php _e('操作', 'moksa-line-login'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $imap_manager = Moksa_Line_Imagemap::get_instance();
                    $imaps = $imap_manager->get_all_imagemaps();
                    
                    if ($imaps) {
                        foreach ($imaps as $imap) {
                            echo '<tr>';
                            echo '<td style="vertical-align: middle; font-weight: 500;">' . esc_html($imap->title) . '</td>';
                            echo '<td style="vertical-align: middle;"><code style="font-size: 11px; background: #f1f5f9; padding: 2px 4px; border-radius: 3px;">' . esc_html($imap->base_url) . '</code></td>';
                            echo '<td style="vertical-align: middle;">';
                            echo '<button class="button button-small edit-imap" 
                                    data-id="' . $imap->id . '" 
                                    data-title="' . esc_attr($imap->title) . '" 
                                    data-alt="' . esc_attr($imap->alt_text) . '" 
                                    data-image="' . esc_attr($imap->image_id) . '" 
                                    data-actions="' . esc_attr($imap->actions) . '">' . __('編輯', 'moksa-line-login') . '</button> ';
                            echo '<button class="button button-small button-link-delete delete-imap" data-id="' . $imap->id . '" style="color: #ef4444; border-color: #fecaca;">' . __('刪除', 'moksa-line-login') . '</button>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="3" style="text-align: center; padding: 20px; color: #64748b;">' . __('目前沒有影像地圖。', 'moksa-line-login') . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Editor Column -->
        <div class="moksa-card" style="height: fit-content;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">
                <h2 id="editor-title" style="margin: 0;"><?php _e('建立影像地圖', 'moksa-line-login'); ?></h2>
                <button type="button" class="button" id="cancel_edit" style="display: none; font-size: 12px;"><?php _e('取消編輯', 'moksa-line-login'); ?></button>
            </div>

            <form id="imagemap-form">
                <input type="hidden" id="imap_id" name="id" value="">
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="title" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('標題', 'moksa-line-login'); ?></label>
                    <input type="text" id="title" class="widefat" required placeholder="<?php _e('僅供內部識別用', 'moksa-line-login'); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="alt_text" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('替代文字', 'moksa-line-login'); ?></label>
                    <input type="text" id="alt_text" class="widefat" required placeholder="<?php _e('顯示於聊天室列表的文字', 'moksa-line-login'); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('圖片', 'moksa-line-login'); ?></label>
                    <input type="hidden" id="image_id" name="image_id" required>
                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <button type="button" class="button" id="upload_image">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 6px;">
                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                            </svg>
                            <?php _e('上傳圖片', 'moksa-line-login'); ?>
                        </button>
                        <div id="image-preview" style="max-width: 200px; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;"></div>
                    </div>
                    <p class="description" style="margin-top: 8px; color: #64748b; font-size: 12px;">
                        <?php _e('建議寬度：1040px。JPEG 格式。', 'moksa-line-login'); ?>
                    </p>
                </div>
                
                <div class="form-group" style="margin-bottom: 25px;">
                    <label for="actions" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('動作定義 (JSON)', 'moksa-line-login'); ?></label>
                    <textarea id="actions" class="widefat code" rows="10" placeholder='[{"type":"uri","linkUri":"https://...","area":{"x":0,"y":0,"width":520,"height":1040}}]' style="font-family: monospace; font-size: 13px; line-height: 1.5;"></textarea>
                    <p class="description" style="margin-top: 5px;">
                        <?php _e('定義可點擊區域。使用 x, y, width, height 座標。', 'moksa-line-login'); ?>
                    </p>
                </div>
                
                <div style="padding-top: 20px; border-top: 1px solid #f1f5f9;">
                    <button type="submit" class="button button-primary button-large"><?php _e('儲存影像地圖', 'moksa-line-login'); ?></button>
                </div>
            </form>
        </div>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Media Uploader
    var frame;
    $('#upload_image').on('click', function(e) {
        e.preventDefault();
        if (frame) {
            frame.open();
            return;
        }
        frame = wp.media({
            title: '<?php _e('選擇或上傳圖片', 'moksa-line-login'); ?>',
            button: { text: '<?php _e('使用此圖片', 'moksa-line-login'); ?>' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#image_id').val(attachment.id);
            $('#image-preview').html('<img src="' + attachment.url + '" style="width: 100%; display: block;">');
        });
        frame.open();
    });
    
    // Save
    $('#imagemap-form').on('submit', function(e) {
        e.preventDefault();
        
        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_save_imagemap',
            nonce: moksaLineAdmin.nonce,
            id: $('#imap_id').val(),
            title: $('#title').val(),
            alt_text: $('#alt_text').val(),
            image_id: $('#image_id').val(),
            actions: $('#actions').val()
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('錯誤：' + response.data);
            }
        });
    });
    
    // Edit
    $('.edit-imap').on('click', function() {
        var id = $(this).data('id');
        var title = $(this).data('title');
        var alt = $(this).data('alt');
        var image = $(this).data('image');
        var actions = $(this).data('actions');
        
        $('#imap_id').val(id);
        $('#title').val(title);
        $('#alt_text').val(alt);
        $('#image_id').val(image);
        $('#actions').val(JSON.stringify(actions, null, 2));
        
        // Fetch image url for preview (simplified)
        $('#image-preview').html('<p style="margin: 5px 0; font-size: 12px; color: #64748b;">ID: ' + image + ' (重新上傳以變更)</p>');
        
        $('#editor-title').text('<?php _e('編輯影像地圖', 'moksa-line-login'); ?>');
        $('#cancel_edit').show();
        
        // Scroll to form
        $('html, body').animate({
            scrollTop: $("#imagemap-form").offset().top - 100
        }, 500);
    });
    
    // Cancel
    $('#cancel_edit').on('click', function() {
        $('#imap_id').val('');
        $('#title').val('');
        $('#alt_text').val('');
        $('#image_id').val('');
        $('#actions').val('');
        $('#image-preview').empty();
        $('#editor-title').text('<?php _e('建立影像地圖', 'moksa-line-login'); ?>');
        $(this).hide();
    });
    
    // Delete
    $('.delete-imap').on('click', function() {
        if (!confirm('<?php _e('確定要刪除嗎？', 'moksa-line-login'); ?>')) return;
        
        var id = $(this).data('id');
        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_delete_imagemap',
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
