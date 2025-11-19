<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="moksa-line-flex-container" style="display: flex; gap: 20px;">
        
        <!-- List Column -->
        <div class="card" style="flex: 2;">
            <h2><?php _e('Greeting Message', 'moksa-line-login'); ?></h2>
            <p><?php _e('Message sent when a user adds your account as a friend.', 'moksa-line-login'); ?></p>
            <form id="greeting-form">
                <div class="form-group">
                    <textarea id="greeting_message" class="widefat" rows="3" placeholder="<?php _e('Enter welcome message...', 'moksa-line-login'); ?>"><?php echo esc_textarea(get_option('moksa_line_greeting_message')); ?></textarea>
                </div>
                <button type="submit" class="button button-primary" style="margin-top: 10px;"><?php _e('Save Greeting', 'moksa-line-login'); ?></button>
            </form>
            
            <hr style="margin: 20px 0;">
            
            <h2><?php _e('Auto Reply Rules', 'moksa-line-login'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Keyword', 'moksa-line-login'); ?></th>
                        <th><?php _e('Match Type', 'moksa-line-login'); ?></th>
                        <th><?php _e('Reply Type', 'moksa-line-login'); ?></th>
                        <th><?php _e('Actions', 'moksa-line-login'); ?></th>
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
                                    data-data="' . esc_attr($rule->reply_data) . '">' . __('Edit', 'moksa-line-login') . '</button> ';
                            echo '<button class="button button-link-delete delete-rule" data-id="' . $rule->id . '">' . __('Delete', 'moksa-line-login') . '</button>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="4">' . __('No rules found.', 'moksa-line-login') . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Editor Column -->
        <div class="card" style="flex: 1;">
            <h2 id="editor-title"><?php _e('Create New Rule', 'moksa-line-login'); ?></h2>
            <form id="autoreply-form">
                <input type="hidden" id="rule_id" name="id" value="">
                
                <div class="form-group">
                    <label for="keyword"><?php _e('Keyword', 'moksa-line-login'); ?></label>
                    <input type="text" id="keyword" class="widefat" required placeholder="<?php _e('e.g., hello', 'moksa-line-login'); ?>">
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label for="match_type"><?php _e('Match Type', 'moksa-line-login'); ?></label>
                    <select id="match_type" class="widefat">
                        <option value="exact"><?php _e('Exact Match', 'moksa-line-login'); ?></option>
                        <option value="partial"><?php _e('Partial Match', 'moksa-line-login'); ?></option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label for="reply_type"><?php _e('Reply Type', 'moksa-line-login'); ?></label>
                    <select id="reply_type" class="widefat">
                        <option value="text"><?php _e('Text Message', 'moksa-line-login'); ?></option>
                        <option value="flex"><?php _e('Flex Message (JSON)', 'moksa-line-login'); ?></option>
                        <option value="quick_reply"><?php _e('Quick Reply Set', 'moksa-line-login'); ?></option>
                    </select>
                </div>
                
                <!-- Text Input -->
                <div id="input-text" class="reply-input-group" style="margin-top: 15px;">
                    <label for="reply_text"><?php _e('Reply Content', 'moksa-line-login'); ?></label>
                    <textarea id="reply_text" class="widefat" rows="5"></textarea>
                </div>
                
                <!-- Flex Input -->
                <div id="input-flex" class="reply-input-group" style="margin-top: 15px; display: none;">
                    <label for="reply_flex"><?php _e('Flex Message JSON', 'moksa-line-login'); ?></label>
                    <textarea id="reply_flex" class="widefat" rows="10" placeholder='{"type": "bubble", ...}'></textarea>
                </div>
                
                <!-- Quick Reply Select -->
                <div id="input-quick_reply" class="reply-input-group" style="margin-top: 15px; display: none;">
                    <label for="reply_qr"><?php _e('Select Quick Reply Set', 'moksa-line-login'); ?></label>
                    <select id="reply_qr" class="widefat">
                        <?php
                        $qr_manager = Moksa_Line_QuickReply::get_instance();
                        $qrs = $qr_manager->get_all_quick_replies();
                        if ($qrs) {
                            foreach ($qrs as $qr) {
                                echo '<option value="' . $qr->id . '">' . esc_html($qr->name) . '</option>';
                            }
                        } else {
                            echo '<option value="">' . __('No Quick Reply Sets found', 'moksa-line-login') . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="button button-primary"><?php _e('Save Rule', 'moksa-line-login'); ?></button>
                    <button type="button" class="button" id="cancel_edit" style="display: none;"><?php _e('Cancel', 'moksa-line-login'); ?></button>
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
                alert('<?php _e('Greeting message saved!', 'moksa-line-login'); ?>');
            }
        });
    });

    // Toggle Inputs based on type
    $('#reply_type').on('change', function() {
        var type = $(this).val();
        $('.reply-input-group').hide();
        $('#input-' + type).show();
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
            alert('Please enter reply content.');
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
                alert('Error: ' + response.data);
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
        else if (type === 'flex') $('#reply_flex').val(JSON.stringify(data, null, 2)); // Pretty print if it's object
        else if (type === 'quick_reply') $('#reply_qr').val(data);
        
        // Handle Flex JSON string vs object issue if data-attr parsed it
        if (type === 'flex' && typeof data === 'object') {
             $('#reply_flex').val(JSON.stringify(data, null, 2));
        } else if (type === 'flex') {
             $('#reply_flex').val(data);
        }
        
        $('#editor-title').text('<?php _e('Edit Rule', 'moksa-line-login'); ?>');
        $('#cancel_edit').show();
    });
    
    // Cancel Edit
    $('#cancel_edit').on('click', function() {
        $('#rule_id').val('');
        $('#keyword').val('');
        $('#reply_text').val('');
        $('#reply_flex').val('');
        $('#editor-title').text('<?php _e('Create New Rule', 'moksa-line-login'); ?>');
        $(this).hide();
    });
    
    // Delete
    $('.delete-rule').on('click', function() {
        if (!confirm('<?php _e('Are you sure?', 'moksa-line-login'); ?>')) return;
        
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
