<?php
/**
 * User Profile Management
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Profile {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('show_user_profile', array($this, 'show_line_profile_fields'));
        add_action('edit_user_profile', array($this, 'show_line_profile_fields'));
        add_action('personal_options_update', array($this, 'save_line_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_line_profile_fields'));
        add_filter('get_avatar', array($this, 'use_line_avatar'), 10, 5);
    }
    
    /**
     * Show LINE profile fields on user edit page
     */
    public function show_line_profile_fields($user) {
        $db = Moksa_Line_Database::get_instance();
        $line_user = $db->get_line_user_by_wp_id($user->ID);
        ?>
        <h2><?php _e('LINE Account Information', 'moksa-line-login'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php _e('LINE User ID', 'moksa-line-login'); ?></th>
                <td>
                    <?php if ($line_user): ?>
                        <code><?php echo esc_html($line_user->line_user_id); ?></code>
                    <?php else: ?>
                        <span class="description"><?php _e('Not connected to LINE', 'moksa-line-login'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($line_user): ?>
            <tr>
                <th><?php _e('LINE Display Name', 'moksa-line-login'); ?></th>
                <td><?php echo esc_html($line_user->display_name); ?></td>
            </tr>
            <tr>
                <th><?php _e('LINE Profile Picture', 'moksa-line-login'); ?></th>
                <td>
                    <?php if ($line_user->picture_url): ?>
                        <img src="<?php echo esc_url($line_user->picture_url); ?>" 
                             alt="<?php echo esc_attr($line_user->display_name); ?>" 
                             style="width: 96px; height: 96px; border-radius: 50%;">
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Status Message', 'moksa-line-login'); ?></th>
                <td><?php echo esc_html($line_user->status_message); ?></td>
            </tr>
            <tr>
                <th><?php _e('Connected Since', 'moksa-line-login'); ?></th>
                <td><?php echo esc_html($line_user->created_at); ?></td>
            </tr>
            <tr>
                <th><?php _e('Unbind LINE Account', 'moksa-line-login'); ?></th>
                <td>
                    <button type="button" class="button button-secondary" id="moksa-unbind-line" 
                            data-user-id="<?php echo esc_attr($user->ID); ?>">
                        <?php _e('Unbind LINE Account', 'moksa-line-login'); ?>
                    </button>
                    <p class="description">
                        <?php _e('This will disconnect the LINE account from this WordPress user.', 'moksa-line-login'); ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    /**
     * Save LINE profile fields
     */
    public function save_line_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        // Handle unbind action via AJAX instead
    }
    
    /**
     * Use LINE avatar if available
     */
    public function use_line_avatar($avatar, $id_or_email, $size, $default, $alt) {
        $user = false;
        
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
            $user = get_user_by('id', $user_id);
        } elseif (is_object($id_or_email)) {
            if (!empty($id_or_email->user_id)) {
                $user_id = (int) $id_or_email->user_id;
                $user = get_user_by('id', $user_id);
            }
        } else {
            $user = get_user_by('email', $id_or_email);
        }
        
        if (!$user) {
            return $avatar;
        }
        
        $line_avatar = get_user_meta($user->ID, 'moksa_line_avatar', true);
        
        if ($line_avatar) {
            $avatar = sprintf(
                '<img alt="%s" src="%s" class="avatar avatar-%d photo" height="%d" width="%d" />',
                esc_attr($alt),
                esc_url($line_avatar),
                esc_attr($size),
                esc_attr($size),
                esc_attr($size)
            );
        }
        
        return $avatar;
    }
}
