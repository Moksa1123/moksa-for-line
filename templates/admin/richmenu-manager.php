<div class="wrap moksa-line-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="moksa-line-richmenu-container">
        <div class="moksa-card">
            <h2>建立新圖文選單 (Rich Menu)</h2>
            <form id="moksa-line-richmenu-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="rm_name">名稱</label></th>
                        <td><input type="text" id="rm_name" name="name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rm_chat_bar">聊天列文字</label></th>
                        <td><input type="text" id="rm_chat_bar" name="chat_bar_text" class="regular-text" value="選單" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rm_size">尺寸</label></th>
                        <td>
                            <select id="rm_size" name="size">
                                <option value="full">全版 (2500x1686)</option>
                                <option value="half">半版 (2500x843)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>圖片</label></th>
                        <td>
                            <input type="hidden" id="rm_image_id" name="image_id" required>
                            <button type="button" class="button" id="rm_upload_image">選擇圖片</button>
                            <div id="rm_image_preview" style="margin-top: 10px; max-width: 300px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>點擊區域 (JSON)</label></th>
                        <td>
                            <textarea id="rm_areas" name="areas" rows="5" class="large-text code" placeholder='[{"bounds":{"x":0,"y":0,"width":2500,"height":1686},"action":{"type":"message","text":"Hello"}}]'></textarea>
                            <p class="description">請輸入 JSON 格式的點擊區域定義。建議使用 LINE Bot Designer 產生此 JSON。</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">建立圖文選單</button>
                </p>
            </form>
        </div>
        
        <br>
        
        <h2>現有圖文選單</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>圖片</th>
                    <th>名稱</th>
                    <th>尺寸</th>
                    <th>預設</th>
                    <th>操作</th>
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
                    <td><img src="<?php echo esc_url($rm->image_url); ?>" style="width: 100px; height: auto;"></td>
                    <td><?php echo esc_html($rm->name); ?></td>
                    <td><?php echo esc_html($rm->size); ?></td>
                    <td>
                        <?php if ($rm->is_default): ?>
                            <span class="dashicons dashicons-yes" style="color: green;"></span>
                        <?php else: ?>
                            <button type="button" class="button button-small set-default-rm" data-id="<?php echo esc_attr($rm->id); ?>">
                                設為預設
                            </button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="button button-small button-link-delete delete-rm" data-id="<?php echo esc_attr($rm->id); ?>">
                            刪除
                        </button>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5">目前沒有圖文選單。</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
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
            title: '選擇圖文選單圖片',
            button: { text: '使用此圖片' },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#rm_image_id').val(attachment.id);
            $('#rm_image_preview').html('<img src="' + attachment.url + '" style="max-width:100%;">');
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
        if (!confirm('確定要刪除嗎？')) return;
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

<script>
jQuery(document).ready(function($) {
    // Image Upload
    var frame;
    $('#rm_upload_image').on('click', function(e) {
        e.preventDefault();
        if (frame) { frame.open(); return; }
        
        frame = wp.media({
            title: 'Select Rich Menu Image',
            button: { text: 'Use this image' },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#rm_image_id').val(attachment.id);
            $('#rm_image_preview').html('<img src="' + attachment.url + '" style="max-width:100%;">');
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
        if (!confirm('Are you sure?')) return;
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
