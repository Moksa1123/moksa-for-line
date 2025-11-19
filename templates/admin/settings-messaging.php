<div class="wrap moksa-line-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('moksa_line_messaging');
        do_settings_sections('moksa_line_messaging');
        ?>
        
        <div class="moksa-card">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="moksa_line_messaging_token">頻道存取權杖</label>
                    </th>
                    <td>
                        <textarea id="moksa_line_messaging_token" name="moksa_line_messaging_token" rows="5" class="large-text code"><?php echo esc_textarea(get_option('moksa_line_messaging_token')); ?></textarea>
                        <p class="description">請輸入 LINE Developers Console 中的 Long-lived Channel Access Token。</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="moksa_line_messaging_secret">Channel Secret</label>
                    </th>
                    <td>
                        <input type="password" id="moksa_line_messaging_secret" name="moksa_line_messaging_secret" 
                               value="<?php echo esc_attr(get_option('moksa_line_messaging_secret')); ?>" class="regular-text">
                        <p class="description">用於 Webhook 驗證的 Channel Secret (如果與 Login Channel Secret 不同)。</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Webhook URL</th>
                    <td>
                        <code><?php echo esc_url(rest_url('moksa-line/v1/webhook')); ?></code>
                        <p class="description">請將此網址設定到 LINE Developers Console 的 "Messaging API" 頁籤中的 Webhook URL。</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="moksa_line_add_friend_url">加好友連結</label>
                    </th>
                    <td>
                        <input type="text" id="moksa_line_add_friend_url" name="moksa_line_add_friend_url" 
                               value="<?php echo esc_attr(get_option('moksa_line_add_friend_url')); ?>" class="regular-text">
                        <p class="description">讓使用者將您的官方帳號加為好友的連結 (例如: https://line.me/R/ti/p/@yourid)。</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="moksa_line_order_delay">訂單通知延遲</label>
                    </th>
                    <td>
                        <input type="number" id="moksa_line_order_delay" name="moksa_line_order_delay" min="0" step="1"
                               value="<?php echo esc_attr(get_option('moksa_line_order_delay', 0)); ?>" class="regular-text"> 秒
                        <p class="description">延遲發送訂單狀態通知，以確保第三方物流外掛已更新追蹤號碼。</p>
                        <p class="description">例如: <strong>600</strong> 代表 10 分鐘。設為 0 則立即發送。</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button('儲存設定'); ?>
    </form>
</div>
