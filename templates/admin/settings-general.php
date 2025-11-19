<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('moksa_line_general');
        do_settings_sections('moksa_line_general');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="moksa_line_channel_id"><?php _e('Channel ID', 'moksa-line-login'); ?></label>
                </th>
                <td>
                    <input type="text" id="moksa_line_channel_id" name="moksa_line_channel_id" 
                    <code><?php echo esc_url(admin_url('admin-ajax.php?action=moksa_line_callback')); ?></code>
                    <p class="description"><?php _e('Copy this URL and paste it into the "Callback URL" field in your LINE Developers console.', 'moksa-line-login'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="moksa_line_auto_register"><?php _e('Auto Registration', 'moksa-line-login'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="moksa_line_auto_register" name="moksa_line_auto_register" value="1" 
                           <?php checked('1', get_option('moksa_line_auto_register')); ?>>
                    <span class="description"><?php _e('Automatically create a WordPress user when a new LINE user logs in.', 'moksa-line-login'); ?></span>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="moksa_line_sync_profile"><?php _e('Sync Profile', 'moksa-line-login'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="moksa_line_sync_profile" name="moksa_line_sync_profile" value="1" 
                           <?php checked('1', get_option('moksa_line_sync_profile')); ?>>
                    <span class="description"><?php _e('Update WordPress user display name and avatar from LINE profile on every login.', 'moksa-line-login'); ?></span>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="moksa_line_redirect_after_login"><?php _e('Redirect After Login', 'moksa-line-login'); ?></label>
                </th>
                <td>
                    <input type="text" id="moksa_line_redirect_after_login" name="moksa_line_redirect_after_login" 
                           value="<?php echo esc_attr(get_option('moksa_line_redirect_after_login', home_url())); ?>" class="regular-text">
                    <p class="description"><?php _e('URL to redirect to after successful login (default: home page).', 'moksa-line-login'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
</div>
