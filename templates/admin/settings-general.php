<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('設定 LINE Login 頻道的基本資訊。', 'moksa-line-login'); ?></p>
        </div>
    </div>

    <div class="moksa-editor-layout" style="display: block; max-width: 100%;">
        <div class="moksa-card">
            <form method="post" action="options.php">
                <?php
                settings_fields('moksa_line_general');
                do_settings_sections('moksa_line_general');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_channel_id"><?php _e('頻道 ID', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_channel_id" name="moksa_line_channel_id" 
                                   value="<?php echo esc_attr(get_option('moksa_line_channel_id')); ?>" class="regular-text">
                            <p class="description"><?php _e('請輸入 LINE Login Channel 的 Channel ID。', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_channel_secret">Channel Secret</label>
                        </th>
                        <td>
                            <input type="password" id="moksa_line_channel_secret" name="moksa_line_channel_secret" 
                                   value="<?php echo esc_attr(get_option('moksa_line_channel_secret')); ?>" class="regular-text">
                            <p class="description"><?php _e('請輸入 LINE Login Channel 的 Channel Secret。', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="moksa_line_liff_id"><?php _e('LIFF ID (選填)', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_liff_id" name="moksa_line_liff_id" 
                                   value="<?php echo esc_attr(get_option('moksa_line_liff_id')); ?>" class="regular-text">
                            <p class="description"><?php _e('用於在 LINE 內部瀏覽器中自動登入。請在 LINE Developers Console 中建立 LIFF App，並將 LIFF ID 貼於此處。', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>Callback URL</label>
                        </th>
                        <td>
                            <code style="background: #f1f5f9; padding: 5px 10px; border-radius: 4px; display: inline-block;"><?php echo esc_url(admin_url('admin-ajax.php?action=moksa_line_callback')); ?></code>
                            <p class="description"><?php _e('請複製此網址並貼到 LINE Developers Console 的 "Callback URL" 欄位中。', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_auto_register"><?php _e('自動註冊', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="moksa_line_auto_register" name="moksa_line_auto_register" value="1" 
                                       <?php checked('1', get_option('moksa_line_auto_register')); ?>>
                                <?php _e('當新的 LINE 使用者登入時，自動為其建立 WordPress 帳號。', 'moksa-line-login'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_sync_profile"><?php _e('同步個人資料', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="moksa_line_sync_profile" name="moksa_line_sync_profile" value="1" 
                                       <?php checked('1', get_option('moksa_line_sync_profile')); ?>>
                                <?php _e('每次登入時，自動同步 LINE 的顯示名稱和頭像到 WordPress 個人資料。', 'moksa-line-login'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="moksa_line_redirect_after_login"><?php _e('登入後跳轉', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_redirect_after_login" name="moksa_line_redirect_after_login" 
                                   value="<?php echo esc_attr(get_option('moksa_line_redirect_after_login')); ?>" class="regular-text" placeholder="/my-account">
                            <p class="description"><?php _e('登入成功後要跳轉的頁面路徑 (例如: /my-account)。留空則跳轉回原頁面。', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="moksa_line_n8n_webhook_url"><?php _e('n8n Webhook 網址', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="moksa_line_n8n_webhook_url" name="moksa_line_n8n_webhook_url" 
                                   value="<?php echo esc_attr(get_option('moksa_line_n8n_webhook_url')); ?>" class="regular-text">
                            <p class="description"><?php _e('將所有 LINE Webhook 事件轉發到此 n8n Webhook URL (選填)。', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div style="padding-top: 20px; border-top: 1px solid #f1f5f9; margin-top: 20px;">
                    <?php submit_button(__('儲存設定', 'moksa-line-login'), 'primary large', 'submit', false); ?>
                </div>
            </form>
        </div>
    </div>
</div>
