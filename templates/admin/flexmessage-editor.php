<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="moksa-line-flex-container" style="display: flex; gap: 20px; height: calc(100vh - 150px);">
        <!-- Editor Column -->
        <div class="flex-editor-col" style="flex: 1; display: flex; flex-direction: column;">
            <div class="card" style="flex: 1; display: flex; flex-direction: column; margin: 0;">
                <h2><?php _e('Flex Message Editor', 'moksa-line-login'); ?></h2>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="flex_alt_text" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Alt Text', 'moksa-line-login'); ?></label>
                    <input type="text" id="flex_alt_text" class="widefat" placeholder="<?php _e('Message displayed in chat list', 'moksa-line-login'); ?>">
                </div>
                
                <div class="form-group" style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('JSON Content', 'moksa-line-login'); ?></label>
                    <div id="monaco-editor" style="flex: 1; border: 1px solid #ccc;"></div>
                    <textarea id="flex_json" style="display: none;"></textarea>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <button type="button" class="button" id="load_sample"><?php _e('Load Sample', 'moksa-line-login'); ?></button>
                    <button type="button" class="button button-primary" id="preview_flex"><?php _e('Update Preview', 'moksa-line-login'); ?></button>
                </div>
            </div>
        </div>
        
        <!-- Preview & Send Column -->
        <div class="flex-preview-col" style="width: 350px; display: flex; flex-direction: column;">
            <div class="card" style="margin: 0 0 20px 0;">
                <h2><?php _e('Send Message', 'moksa-line-login'); ?></h2>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;"><?php _e('Target', 'moksa-line-login'); ?></label>
                    <select id="target_type" class="widefat">
                        <option value="all"><?php _e('All Users', 'moksa-line-login'); ?></option>
                        <option value="specific"><?php _e('Specific Users', 'moksa-line-login'); ?></option>
                    </select>
                </div>
                
                <div id="user_selector" style="display: none; margin-top: 10px;">
                    <p class="description"><?php _e('Select users from the list below:', 'moksa-line-login'); ?></p>
                    <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 5px;">
                        <?php
                        $db = Moksa_Line_Database::get_instance();
                        $users = $db->get_all_line_users(100);
                        foreach ($users as $user) {
                            echo '<label style="display: block;"><input type="checkbox" name="user_ids[]" value="' . esc_attr($user->line_user_id) . '"> ' . esc_html($user->display_name) . '</label>';
                        }
                        ?>
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <button type="button" class="button button-primary button-large" id="send_flex" style="width: 100%;">
                        <?php _e('Send Now', 'moksa-line-login'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Phone Preview -->
            <div class="phone-preview" style="background: #fff; border: 1px solid #ddd; border-radius: 20px; padding: 15px; flex: 1; overflow: hidden; display: flex; flex-direction: column;">
                <div style="text-align: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; font-weight: bold;">
                    LINE
                </div>
                <div id="preview_container" style="flex: 1; overflow-y: auto; background: #849ebf; padding: 10px; border-radius: 4px;">
                    <!-- Preview content will be rendered here -->
                    <div class="flex-bubble-preview" style="background: #fff; padding: 10px; border-radius: 10px; text-align: center; color: #999;">
                        <?php _e('Preview will appear here', 'moksa-line-login'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load Monaco Editor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.36.1/min/vs/loader.min.js"></script>
<script>
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.36.1/min/vs' }});
    require(['vs/editor/editor.main'], function() {
        window.editor = monaco.editor.create(document.getElementById('monaco-editor'), {
            value: '{\n  "type": "bubble",\n  "body": {\n    "type": "box",\n    "layout": "vertical",\n    "contents": [\n      {\n        "type": "text",\n        "text": "Hello World"\n      }\n    ]\n  }\n}',
            language: 'json',
            theme: 'vs-light',
            minimap: { enabled: false }
        });
        
        // Sync with hidden textarea
        window.editor.onDidChangeModelContent(function() {
            document.getElementById('flex_json').value = window.editor.getValue();
        });
        
        // Initial sync
        document.getElementById('flex_json').value = window.editor.getValue();
    });
</script>
