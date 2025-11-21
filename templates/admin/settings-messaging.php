<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('設定 LINE Messaging API 相關資訊。', 'moksa-line-login'); ?></p>
        </div>
    </div>

    <div class="moksa-editor-layout" style="display: block; max-width: 800px;">
        <div class="moksa-card">
            <form method="post" action="options.php">
                <?php
                settings_fields('moksa_line_messaging');
                do_settings_sections('moksa_line_messaging');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_messaging_token"><?php _e('頻道存取權杖', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <textarea id="moksa_line_messaging_token" name="moksa_line_messaging_token" rows="5" class="large-text code" style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea(get_option('moksa_line_messaging_token')); ?></textarea>
                            <p class="description"><?php _e('請輸入 LINE Developers Console 中的 Long-lived Channel Access Token。', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_messaging_secret">Channel Secret</label>
                        </th>
                        <td>
                            <input type="password" id="moksa_line_messaging_secret" name="moksa_line_messaging_secret" 
                                   value="<?php echo esc_attr(get_option('moksa_line_messaging_secret')); ?>" class="regular-text">
                            <p class="description"><?php _e('用於 Webhook 驗證的 Channel Secret (如果與 Login Channel Secret 不同)。', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Webhook URL</th>
                        <td>
                            <code style="background: #f1f5f9; padding: 5px 10px; border-radius: 4px; display: inline-block;"><?php echo esc_url(rest_url('moksa-line/v1/webhook')); ?></code>
                            <p class="description"><?php _e('請將此網址設定到 LINE Developers Console 的 "Messaging API" 頁籤中的 Webhook URL。', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_add_friend_url"><?php _e('加好友連結', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_add_friend_url" name="moksa_line_add_friend_url" 
                                   value="<?php echo esc_attr(get_option('moksa_line_add_friend_url')); ?>" class="regular-text">
                            <p class="description"><?php _e('讓使用者將您的官方帳號加為好友的連結 (例如: https://line.me/R/ti/p/@yourid)。', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_order_processing_delay"><?php _e('處理中狀態延遲', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="moksa_line_order_processing_delay" name="moksa_line_order_processing_delay" min="0" step="1"
                                   value="<?php echo esc_attr(get_option('moksa_line_order_processing_delay', 30)); ?>" class="regular-text" style="width: 100px;"> <?php _e('秒', 'moksa-line-login'); ?>
                            <p class="description">
                                <?php _e('當訂單狀態變更為「處理中」時，延遲發送通知的時間。', 'moksa-line-login'); ?><br>
                                <?php _e('因為訂單編號可能因第三方 API 回傳而延遲生成，建議設定 30-60 秒。', 'moksa-line-login'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_order_delay"><?php _e('其他狀態延遲', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="moksa_line_order_delay" name="moksa_line_order_delay" min="0" step="1"
                                   value="<?php echo esc_attr(get_option('moksa_line_order_delay', 0)); ?>" class="regular-text" style="width: 100px;"> <?php _e('秒', 'moksa-line-login'); ?>
                            <p class="description"><?php _e('其他訂單狀態的通知延遲時間（例如：已完成、已取消等）。', 'moksa-line-login'); ?></p>
                            <p class="description"><?php printf(__('例如: <strong>%s</strong> 代表 10 分鐘。設為 0 則立即發送。', 'moksa-line-login'), '600'); ?></p>
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
