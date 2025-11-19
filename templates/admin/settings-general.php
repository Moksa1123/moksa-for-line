<div class="wrap moksa-line-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('moksa_line_general');
        do_settings_sections('moksa_line_general');
        ?>
        
        <div class="moksa-card">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="moksa_line_channel_id">頻道 ID</label>
                    </th>
                    <td>
                        <input type="text" id="moksa_line_channel_id" name="moksa_line_channel_id" 
                               value="<?php echo esc_attr(get_option('moksa_line_channel_id')); ?>" class="regular-text">
                        <p class="description">請輸入 LINE Login Channel 的 Channel ID。</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="moksa_line_channel_secret">Channel Secret</label>
                    </th>
                    <td>
                        <input type="password" id="moksa_line_channel_secret" name="moksa_line_channel_secret" 
                               value="<?php echo esc_attr(get_option('moksa_line_channel_secret')); ?>" class="regular-text">
                        <p class="description">請輸入 LINE Login Channel 的 Channel Secret。</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="moksa_line_liff_id">LIFF ID (選填)</label>
                    </th>
                    <td>
                        <input type="text" id="moksa_line_liff_id" name="moksa_line_liff_id" 
                               value="<?php echo esc_attr(get_option('moksa_line_liff_id')); ?>" class="regular-text">
                        <p class="description">用於在 LINE 內部瀏覽器中自動登入。請在 LINE Developers Console 中建立 LIFF App，並將 LIFF ID 貼於此處。</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label>Callback URL</label>
                    </th>
                    <td>
                        <code><?php echo esc_url(admin_url('admin-ajax.php?action=moksa_line_callback')); ?></code>
                        <p class="description">請複製此網址並貼到 LINE Developers Console 的 "Callback URL" 欄位中。</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="moksa_line_auto_register">自動註冊</label>
                    </th>
                    <td>
                        <input type="checkbox" id="moksa_line_auto_register" name="moksa_line_auto_register" value="1" 
                               <?php checked('1', get_option('moksa_line_auto_register')); ?>>
                        <span class="description">當新的 LINE 使用者登入時，自動為其建立 WordPress 帳號。</span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="moksa_line_sync_profile">同步個人資料</label>
                    </th>
                    <td>
                        <input type="checkbox" id="moksa_line_sync_profile" name="moksa_line_sync_profile" value="1" 
                               <?php checked('1', get_option('moksa_line_sync_profile')); ?>>
                        <span class="description">每次登入時，自動同步 LINE 的顯示名稱和頭像到 WordPress 個人資料。</span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="moksa_line_redirect_after_login">登入後跳轉</label>
                    </th>
                    <td>
                        <input type="text" id="moksa_line_redirect_after_login" name="moksa_line_redirect_after_login" 
                               value="<?php echo esc_attr(get_option('moksa_line_redirect_after_login')); ?>" class="regular-text" placeholder="/my-account">
                        <p class="description">登入成功後要跳轉的頁面路徑 (例如: /my-account)。留空則跳轉回原頁面。</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="moksa_line_n8n_webhook_url">n8n Webhook 網址</label>
                    </th>
                    <td>
                        <input type="url" id="moksa_line_n8n_webhook_url" name="moksa_line_n8n_webhook_url" 
                               value="<?php echo esc_attr(get_option('moksa_line_n8n_webhook_url')); ?>" class="regular-text">
                        <p class="description">將所有 LINE Webhook 事件轉發到此 n8n Webhook URL (選填)。</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button('儲存設定'); ?>
    </form>
</div>
