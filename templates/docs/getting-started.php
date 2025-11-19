<div class="wrap">
    <h1><?php _e('Moksa LINE Login - Instruction Manual', 'moksa-line-login'); ?></h1>
    
    <div class="moksa-line-flex-container" style="display: flex; gap: 20px;">
        
        <!-- Sidebar Navigation -->
        <div class="card" style="flex: 0 0 250px; height: fit-content;">
            <h3><?php _e('Table of Contents', 'moksa-line-login'); ?></h3>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <li style="margin-bottom: 10px;"><a href="#setup" style="text-decoration: none; font-weight: bold;"><?php _e('1. Initial Setup', 'moksa-line-login'); ?></a></li>
                <li style="margin-bottom: 10px;"><a href="#login" style="text-decoration: none; font-weight: bold;"><?php _e('2. Login & Registration', 'moksa-line-login'); ?></a></li>
                <li style="margin-bottom: 10px;"><a href="#woocommerce" style="text-decoration: none; font-weight: bold;"><?php _e('3. WooCommerce Integration', 'moksa-line-login'); ?></a></li>
                <li style="margin-bottom: 10px;"><a href="#richmenu" style="text-decoration: none; font-weight: bold;"><?php _e('4. Rich Menus', 'moksa-line-login'); ?></a></li>
                <li style="margin-bottom: 10px;"><a href="#autoreply" style="text-decoration: none; font-weight: bold;"><?php _e('5. Auto Reply & Greeting', 'moksa-line-login'); ?></a></li>
                <li style="margin-bottom: 10px;"><a href="#flex" style="text-decoration: none; font-weight: bold;"><?php _e('6. Flex Messages', 'moksa-line-login'); ?></a></li>
                <li style="margin-bottom: 10px;"><a href="#imagemap" style="text-decoration: none; font-weight: bold;"><?php _e('7. Imagemap', 'moksa-line-login'); ?></a></li>
                <li style="margin-bottom: 10px;"><a href="#liff" style="text-decoration: none; font-weight: bold;"><?php _e('8. LIFF Integration', 'moksa-line-login'); ?></a></li>
                <li style="margin-bottom: 10px;"><a href="#shortcodes" style="text-decoration: none; font-weight: bold;"><?php _e('9. Shortcodes', 'moksa-line-login'); ?></a></li>
            </ul>
        </div>
        
        <!-- Content -->
        <div style="flex: 1;">
            
            <!-- Setup -->
            <div id="setup" class="card" style="margin-top: 0;">
                <h2><?php _e('1. Initial Setup', 'moksa-line-login'); ?></h2>
                <ol>
                    <li><?php _e('Go to <strong>LINE Developers Console</strong> and create a new Provider and Channel (LINE Login).', 'moksa-line-login'); ?></li>
                    <li><?php _e('Copy the <strong>Channel ID</strong> and <strong>Channel Secret</strong>.', 'moksa-line-login'); ?></li>
                    <li><?php _e('Go to <strong>Moksa LINE Login > General Settings</strong> and paste the credentials.', 'moksa-line-login'); ?></li>
                    <li><?php _e('Copy the <strong>Callback URL</strong> from the plugin settings and paste it into your LINE Channel settings.', 'moksa-line-login'); ?></li>
                    <li><?php _e('Make sure to publish your LINE Channel.', 'moksa-line-login'); ?></li>
                </ol>
            </div>
            
            <!-- Login -->
            <div id="login" class="card">
                <h2><?php _e('2. Login & Registration', 'moksa-line-login'); ?></h2>
                <p><?php _e('The plugin handles user registration automatically.', 'moksa-line-login'); ?></p>
                <ul>
                    <li><strong><?php _e('Auto Registration:', 'moksa-line-login'); ?></strong> <?php _e('If enabled, a new WordPress user is created when a LINE user logs in for the first time.', 'moksa-line-login'); ?></li>
                    <li><strong><?php _e('Profile Sync:', 'moksa-line-login'); ?></strong> <?php _e('Updates the WordPress display name and avatar with LINE profile data on every login.', 'moksa-line-login'); ?></li>
                </ul>
            </div>
            
            <!-- WooCommerce -->
            <div id="woocommerce" class="card">
                <h2><?php _e('3. WooCommerce Integration', 'moksa-line-login'); ?></h2>
                <p><?php _e('If WooCommerce is active, the plugin adds:', 'moksa-line-login'); ?></p>
                <ul>
                    <li><?php _e('<strong>Login Button</strong> on Checkout and My Account pages.', 'moksa-line-login'); ?></li>
                    <li><?php _e('<strong>Order Notifications</strong>: Sends a LINE message when order status changes (requires Messaging API).', 'moksa-line-login'); ?></li>
                    <li><?php _e('<strong>Product Carousel</strong>: Generate product carousels for marketing.', 'moksa-line-login'); ?></li>
                </ul>
            </div>
            
            <!-- Rich Menu -->
            <div id="richmenu" class="card">
                <h2><?php _e('4. Rich Menus', 'moksa-line-login'); ?></h2>
                <p><?php _e('Create interactive menus at the bottom of the chat screen.', 'moksa-line-login'); ?></p>
                <ol>
                    <li><?php _e('Go to <strong>Rich Menu</strong>.', 'moksa-line-login'); ?></li>
                    <li><?php _e('Upload an image (recommended 2500x1686 or 2500x843).', 'moksa-line-login'); ?></li>
                    <li><?php _e('Define clickable areas (Tapping Area) and actions (Link or Text).', 'moksa-line-login'); ?></li>
                    <li><?php _e('Click <strong>Save & Publish</strong> to set it as the default menu for all users.', 'moksa-line-login'); ?></li>
                </ol>
            </div>
            
            <!-- Auto Reply -->
            <div id="autoreply" class="card">
                <h2><?php _e('5. Auto Reply & Greeting', 'moksa-line-login'); ?></h2>
                <ul>
                    <li><strong><?php _e('Greeting Message:', 'moksa-line-login'); ?></strong> <?php _e('Set a welcome message sent when a user adds your account as a friend.', 'moksa-line-login'); ?></li>
                    <li><strong><?php _e('Auto Reply:', 'moksa-line-login'); ?></strong> <?php _e('Set up keywords (e.g., "hours", "location") and the bot will reply automatically.', 'moksa-line-login'); ?></li>
                </ul>
            </div>
            
            <!-- Flex Messages -->
            <div id="flex" class="card">
                <h2><?php _e('6. Flex Messages', 'moksa-line-login'); ?></h2>
                <p><?php _e('Send highly customizable messages.', 'moksa-line-login'); ?></p>
                <ul>
                    <li><?php _e('Use the <strong>Flex Message Editor</strong> to create JSON layouts.', 'moksa-line-login'); ?></li>
                    <li><?php _e('Use the <strong>Real-time Preview</strong> to see how it looks.', 'moksa-line-login'); ?></li>
                    <li><?php _e('Send to all users or specific users.', 'moksa-line-login'); ?></li>
                </ul>
            </div>
            
            <!-- Imagemap -->
            <div id="imagemap" class="card">
                <h2><?php _e('7. Imagemap', 'moksa-line-login'); ?></h2>
                <p><?php _e('Send large images with multiple clickable links.', 'moksa-line-login'); ?></p>
                <ol>
                    <li><?php _e('Upload a large image (1040x1040).', 'moksa-line-login'); ?></li>
                    <li><?php _e('Define actions in JSON format.', 'moksa-line-login'); ?></li>
                    <li><?php _e('Use the generated <strong>Base URL</strong> in your broadcasts.', 'moksa-line-login'); ?></li>
                </ol>
            </div>
            
            <!-- LIFF -->
            <div id="liff" class="card">
                <h2><?php _e('8. LIFF Integration', 'moksa-line-login'); ?></h2>
                <p><?php _e('Allow users to open web pages within LINE.', 'moksa-line-login'); ?></p>
                <ul>
                    <li><?php _e('<strong>Profile Update:</strong> Users can update their email/phone without leaving LINE.', 'moksa-line-login'); ?></li>
                    <li><?php _e('Endpoint: <code>/liff/profile</code>', 'moksa-line-login'); ?></li>
                </ul>
            </div>
            
            <!-- Shortcodes -->
            <div id="shortcodes" class="card">
                <h2><?php _e('9. Shortcodes', 'moksa-line-login'); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Shortcode</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[line_login_button]</code></td>
                            <td><?php _e('Displays the login button.', 'moksa-line-login'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[line_add_friend]</code></td>
                            <td><?php _e('Displays "Add Friend" button.', 'moksa-line-login'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[line_user_info]</code></td>
                            <td><?php _e('Displays user profile info.', 'moksa-line-login'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>
</div>
