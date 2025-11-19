<div class="card">
    <h2><?php _e('Getting Started', 'moksa-line-login'); ?></h2>
    <p><?php _e('Welcome to Moksa LINE Login! Follow these steps to get started:', 'moksa-line-login'); ?></p>
    <ol>
        <li><?php _e('Create a LINE Login Channel in the LINE Developers Console.', 'moksa-line-login'); ?></li>
        <li><?php _e('Configure the Channel ID and Channel Secret in the General Settings tab.', 'moksa-line-login'); ?></li>
        <li><?php _e('Add the Callback URL to your LINE Login Channel settings.', 'moksa-line-login'); ?></li>
        <li><?php _e('Use the shortcode <code>[line_login_button]</code> to display the login button.', 'moksa-line-login'); ?></li>
    </ol>
    <p>
        <a href="<?php echo admin_url('admin.php?page=moksa-line-login'); ?>" class="button button-primary"><?php _e('Go to Settings', 'moksa-line-login'); ?></a>
    </p>
</div>

<div class="card">
    <h2><?php _e('Shortcodes Reference', 'moksa-line-login'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Shortcode</th>
                <th>Description</th>
                <th>Attributes</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[line_login_button]</code></td>
                <td><?php _e('Displays the LINE Login button.', 'moksa-line-login'); ?></td>
                <td>
                    <code>text</code>: <?php _e('Button text', 'moksa-line-login'); ?><br>
                    <code>redirect_url</code>: <?php _e('URL to redirect after login', 'moksa-line-login'); ?><br>
                    <code>show_popup</code>: <?php _e('yes/no (default: yes)', 'moksa-line-login'); ?>
                </td>
            </tr>
            <tr>
                <td><code>[line_add_friend]</code></td>
                <td><?php _e('Displays a button to add your Official Account as a friend.', 'moksa-line-login'); ?></td>
                <td>
                    <code>text</code>: <?php _e('Button text', 'moksa-line-login'); ?><br>
                    <code>url</code>: <?php _e('Add friend URL (overrides setting)', 'moksa-line-login'); ?>
                </td>
            </tr>
            <tr>
                <td><code>[line_user_info]</code></td>
                <td><?php _e('Displays the connected LINE user\'s profile info.', 'moksa-line-login'); ?></td>
                <td><?php _e('None', 'moksa-line-login'); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="card">
    <h2><?php _e('WooCommerce Integration', 'moksa-line-login'); ?></h2>
    <p><?php _e('The plugin automatically integrates with WooCommerce if it is active:', 'moksa-line-login'); ?></p>
    <ul>
        <li><?php _e('Adds a "LINE Account" tab to the My Account page.', 'moksa-line-login'); ?></li>
        <li><?php _e('Adds a login button to the checkout page.', 'moksa-line-login'); ?></li>
        <li><?php _e('Sends order status updates via LINE Flex Messages (requires Messaging API configuration).', 'moksa-line-login'); ?></li>
    </ul>
</div>
