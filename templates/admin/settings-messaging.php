<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('moksa_line_messaging');
        do_settings_sections('moksa_line_messaging');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="moksa_line_messaging_token"><?php _e('Channel Access Token', 'moksa-line-login'); ?></label>
                </th>
                <td>
                    <textarea id="moksa_line_messaging_token" name="moksa_line_messaging_token" rows="5" class="large-text code"><?php echo esc_textarea(get_option('moksa_line_messaging_token')); ?></textarea>
                    <p class="description"><?php _e('Long-lived Channel Access Token from LINE Developers Console.', 'moksa-line-login'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="moksa_line_messaging_secret"><?php _e('Channel Secret', 'moksa-line-login'); ?></label>
                </th>
                <td>
                    <input type="password" id="moksa_line_messaging_secret" name="moksa_line_messaging_secret" 
                           value="<?php echo esc_attr(get_option('moksa_line_messaging_secret')); ?>" class="regular-text">
                    <p class="description"><?php _e('Channel Secret for Webhook verification (if different from Login Channel Secret).', 'moksa-line-login'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Webhook URL', 'moksa-line-login'); ?></th>
                <td>
                    <code><?php echo esc_url(rest_url('moksa-line/v1/webhook')); ?></code>
                    <p class="description"><?php _e('Set this URL in the "Messaging API" tab of your LINE Developers console.', 'moksa-line-login'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="moksa_line_add_friend_url"><?php _e('Add Friend URL', 'moksa-line-login'); ?></label>
                </th>
                <td>
                    <input type="text" id="moksa_line_add_friend_url" name="moksa_line_add_friend_url" 
                           value="<?php echo esc_attr(get_option('moksa_line_add_friend_url')); ?>" class="regular-text">
                    <p class="description"><?php _e('URL for users to add your Official Account as a friend (e.g., https://line.me/R/ti/p/@yourid).', 'moksa-line-login'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="moksa_line_order_delay"><?php _e('Order Notification Delay', 'moksa-line-login'); ?></label>
                </th>
                <td>
                    <input type="number" id="moksa_line_order_delay" name="moksa_line_order_delay" min="0" step="1"
                           value="<?php echo esc_attr(get_option('moksa_line_order_delay', 0)); ?>" class="regular-text"> <?php _e('seconds', 'moksa-line-login'); ?>
                    <p class="description"><?php _e('Delay sending order status notifications to ensure tracking numbers are updated by third-party plugins.', 'moksa-line-login'); ?></p>
                    <p class="description"><?php _e('Example: <strong>600</strong> for 10 minutes. Set to 0 to disable.', 'moksa-line-login'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
</div>
