<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="moksa-line-flex-container" style="display: flex; gap: 20px;">
        
        <!-- List Column -->
        <div class="card" style="flex: 2;">
            <h2><?php _e('Imagemaps', 'moksa-line-login'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Title', 'moksa-line-login'); ?></th>
                        <th><?php _e('Base URL', 'moksa-line-login'); ?></th>
                        <th><?php _e('Actions', 'moksa-line-login'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $imap_manager = Moksa_Line_Imagemap::get_instance();
                    $imaps = $imap_manager->get_all_imagemaps();
                    
                    if ($imaps) {
                        foreach ($imaps as $imap) {
                            echo '<tr>';
                            echo '<td>' . esc_html($imap->title) . '</td>';
                            echo '<td><code>' . esc_html($imap->base_url) . '</code></td>';
                            echo '<td>';
                            echo '<button class="button edit-imap" 
                                    data-id="' . $imap->id . '" 
                                    data-title="' . esc_attr($imap->title) . '" 
                                    data-alt="' . esc_attr($imap->alt_text) . '" 
                                    data-image="' . esc_attr($imap->image_id) . '" 
                                    data-actions="' . esc_attr($imap->actions) . '">' . __('Edit', 'moksa-line-login') . '</button> ';
                            echo '<button class="button button-link-delete delete-imap" data-id="' . $imap->id . '">' . __('Delete', 'moksa-line-login') . '</button>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="3">' . __('No Imagemaps found.', 'moksa-line-login') . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Editor Column -->
        <div class="card" style="flex: 1;">
            <h2 id="editor-title"><?php _e('Create Imagemap', 'moksa-line-login'); ?></h2>
            <form id="imagemap-form">
                <input type="hidden" id="imap_id" name="id" value="">
                
                <div class="form-group">
                    <label for="title"><?php _e('Title', 'moksa-line-login'); ?></label>
                    <input type="text" id="title" class="widefat" required>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label for="alt_text"><?php _e('Alt Text', 'moksa-line-login'); ?></label>
                    <input type="text" id="alt_text" class="widefat" required placeholder="<?php _e('Text shown in notification', 'moksa-line-login'); ?>">
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label><?php _e('Image', 'moksa-line-login'); ?></label>
                    <div id="image-preview" style="margin-bottom: 10px; max-width: 100%;"></div>
                    <input type="hidden" id="image_id" name="image_id" required>
                    <button type="button" class="button" id="upload_image"><?php _e('Upload Image', 'moksa-line-login'); ?></button>
                    <p class="description"><?php _e('Recommended width: 1040px. JPEG format.', 'moksa-line-login'); ?></p>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label for="actions"><?php _e('Actions (JSON)', 'moksa-line-login'); ?></label>
                    <textarea id="actions" class="widefat" rows="10" placeholder='[{"type":"uri","linkUri":"https://...","area":{"x":0,"y":0,"width":520,"height":1040}}]'></textarea>
                    <p class="description"><?php _e('Define clickable areas. Use x, y, width, height.', 'moksa-line-login'); ?></p>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="button button-primary"><?php _e('Save Imagemap', 'moksa-line-login'); ?></button>
                    <button type="button" class="button" id="cancel_edit" style="display: none;"><?php _e('Cancel', 'moksa-line-login'); ?></button>
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
            title: 'Select or Upload Image',
            button: { text: 'Use this image' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#image_id').val(attachment.id);
            $('#image-preview').html('<img src="' + attachment.url + '" style="max-width:100%; height:auto;">');
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
                alert('Error: ' + response.data);
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
        
        // Fetch image url for preview (simplified, might not show if not cached in JS, but user can re-upload if needed or we can fetch via ajax. For now just show ID or "Image Selected")
        $('#image-preview').html('<p>Image ID: ' + image + ' (Re-upload to change)</p>');
        
        $('#editor-title').text('<?php _e('Edit Imagemap', 'moksa-line-login'); ?>');
        $('#cancel_edit').show();
    });
    
    // Cancel
    $('#cancel_edit').on('click', function() {
        $('#imap_id').val('');
        $('#title').val('');
        $('#alt_text').val('');
        $('#image_id').val('');
        $('#actions').val('');
        $('#image-preview').empty();
        $('#editor-title').text('<?php _e('Create Imagemap', 'moksa-line-login'); ?>');
        $(this).hide();
    });
    
    // Delete
    $('.delete-imap').on('click', function() {
        if (!confirm('<?php _e('Are you sure?', 'moksa-line-login'); ?>')) return;
        
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
