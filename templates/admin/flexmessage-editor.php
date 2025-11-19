<div class="wrap moksa-line-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="moksa-line-flex-container" style="display: flex; gap: 20px; height: calc(100vh - 150px);">
        <!-- Editor Column -->
        <div class="flex-editor-col" style="flex: 1; display: flex; flex-direction: column;">
            <div class="moksa-card" style="flex: 1; display: flex; flex-direction: column; margin: 0;">
                <h2>Flex Message 編輯器</h2>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="flex_alt_text" style="display: block; margin-bottom: 5px; font-weight: bold;">替代文字 (Alt Text)</label>
                    <input type="text" id="flex_alt_text" class="widefat" placeholder="在聊天列表中顯示的訊息預覽文字">
                </div>
                
                <div class="form-group" style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">JSON 內容</label>
                    <div id="monaco-editor" style="flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;"></div>
                    <textarea id="flex_json" style="display: none;"></textarea>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <button type="button" class="button" id="load_sample">載入範例</button>
                    <button type="button" class="button button-primary" id="preview_flex">更新預覽</button>
                </div>
            </div>
        </div>
        
        <!-- Preview & Send Column -->
        <div class="flex-preview-col" style="width: 350px; display: flex; flex-direction: column;">
            <div class="moksa-card" style="margin: 0 0 20px 0;">
                <h2>發送訊息</h2>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 5px;">發送對象</label>
                    <select id="target_type" class="widefat">
                        <option value="all">所有使用者</option>
                        <option value="specific">特定使用者</option>
                    </select>
                </div>
                
                <div id="user_selector" style="display: none; margin-top: 10px;">
                    <p class="description">請從下方列表中選擇使用者：</p>
                    <div style="max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px; background: #f8fafc;">
                        <?php
                        $db = Moksa_Line_Database::get_instance();
                        $users = $db->get_all_line_users(100);
                        if ($users) {
                            foreach ($users as $user) {
                                echo '<label style="display: block;"><input type="checkbox" name="user_ids[]" value="' . esc_attr($user->line_user_id) . '"> ' . esc_html($user->display_name) . '</label>';
                            }
                        } else {
                            echo '<p>沒有找到 LINE 使用者。</p>';
                        }
                        ?>
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <button type="button" class="button button-primary button-large" id="send_flex" style="width: 100%;">
                        立即發送
                    </button>
                </div>
            </div>
            
            <!-- Phone Preview -->
            <div class="phone-preview" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px; flex: 1; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);">
                <div style="text-align: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 12px; font-weight: 600; color: #1e293b; font-size: 14px;">
                    LINE
                </div>
                <div id="preview_container" style="flex: 1; overflow-y: auto; background: #e2e8f0; padding: 12px; border-radius: 8px;">
                    <!-- Preview content will be rendered here -->
                    <div class="flex-bubble-preview" style="background: #fff; padding: 16px; border-radius: 12px; text-align: center; color: #94a3b8; font-size: 13px;">
                        預覽將顯示於此
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
        
        // Initial sync and preview
        document.getElementById('flex_json').value = window.editor.getValue();
        
        // Initial preview
        if (typeof updatePreview === 'function') {
            setTimeout(function() {
                updatePreview(window.editor.getValue());
            }, 500);
        }
    });
</script>
