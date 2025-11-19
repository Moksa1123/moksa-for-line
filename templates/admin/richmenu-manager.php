<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="moksa-line-richmenu-container">
        <div class="card">
            <h2><?php _e('Create New Rich Menu', 'moksa-line-login'); ?></h2>
            <form id="moksa-line-richmenu-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="rm_name"><?php _e('Name', 'moksa-line-login'); ?></label></th>
                        <td><input type="text" id="rm_name" name="name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rm_chat_bar"><?php _e('Chat Bar Text', 'moksa-line-login'); ?></label></th>
                        <td><input type="text" id="rm_chat_bar" name="chat_bar_text" class="regular-text" value="Menu" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rm_size"><?php _e('Size', 'moksa-line-login'); ?></label></th>
                        <td>
                            <select id="rm_size" name="size">
                                <option value="full"><?php _e('Full (2500x1686)', 'moksa-line-login'); ?></option>
                                <option value="half"><?php _e('Half (2500x843)', 'moksa-line-login'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php _e('Image', 'moksa-line-login'); ?></label></th>
                        <td>
                            <input type="hidden" id="rm_image_id" name="image_id" required>
                            <button type="button" class="button" id="rm_upload_image"><?php _e('Select Image', 'moksa-line-login'); ?></button>
                            <div id="rm_image_preview" style="margin-top: 10px; max-width: 300px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php _e('Areas (JSON)', 'moksa-line-login'); ?></label></th>
                        <td>
                            <textarea id="rm_areas" name="areas" rows="5" class="large-text code" placeholder='[{"bounds":{"x":0,"y":0,"width":2500,"height":1686},"action":{"type":"message","text":"Hello"}}]'></textarea>
                            <p class="description"><?php _e('Define touch areas in JSON format. Use the LINE Bot Designer to generate this.', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Create Rich Menu', 'moksa-line-login'); ?></button>
                </p>
            </form>
        </div>
        
        <br>
        
        <h2><?php _e('Existing Rich Menus', 'moksa-line-login'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Image', 'moksa-line-login'); ?></th>
                    <th><?php _e('Name', 'moksa-line-login'); ?></th>
                    <th><?php _e('Size', 'moksa-line-login'); ?></th>
                    <th><?php _e('Default', 'moksa-line-login'); ?></th>
                    <th><?php _e('Actions', 'moksa-line-login'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'moksa_line_richmenus';
                $richmenus = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
                
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
                                <?php _e('Set Default', 'moksa-line-login'); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="button button-small button-link-delete delete-rm" data-id="<?php echo esc_attr($rm->id); ?>">
                            <?php _e('Delete', 'moksa-line-login'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5"><?php _e('No Rich Menus found.', 'moksa-line-login'); ?></td></tr>
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
