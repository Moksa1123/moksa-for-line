<?php
/**
 * Shortcodes Handler
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Shortcodes {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('line_login_button', array($this, 'render_login_button'));
        add_shortcode('line_add_friend', array($this, 'render_add_friend_button'));
        add_shortcode('line_user_info', array($this, 'render_user_info'));
    }
    
    /**
     * Render LINE login button shortcode
     * Usage: [line_login_button text="Login with LINE" redirect_url="/my-account"]
     */
    public function render_login_button($atts) {
        $atts = shortcode_atts(array(
            'text' => get_option('moksa_line_button_text', __('Login with LINE', 'moksa-line-login')),
            'redirect_url' => '',
            'show_popup' => 'yes',
        ), $atts, 'line_login_button');
        
        if (is_user_logged_in()) {
            return '';
        }
        
        $auth = Moksa_Line_Auth::get_instance();
        $redirect = !empty($atts['redirect_url']) ? $atts['redirect_url'] : '';
        $login_url = $auth->get_login_url($redirect);
        
        $bg_color = get_option('moksa_line_button_bg_color', '#00B900');
        $text_color = get_option('moksa_line_button_text_color', '#FFFFFF');
        $border_radius = get_option('moksa_line_button_border_radius', '4');
        $width = get_option('moksa_line_button_width', '100%');
        $height = get_option('moksa_line_button_height', '44');
        
        $style = sprintf(
            'background-color: %s; color: %s; border-radius: %spx; width: %s; height: %spx;',
            esc_attr($bg_color),
            esc_attr($text_color),
            esc_attr($border_radius),
            esc_attr($width),
            esc_attr($height)
        );
        
        if ($atts['show_popup'] === 'yes') {
            $button_html = sprintf(
                '<button class="moksa-line-login-btn" data-redirect="%s" style="%s">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 0C4.477 0 0 3.846 0 8.571c0 4.236 3.756 7.78 8.823 8.456.343.074.81.226.928.52.106.265.07.68.034.948l-.148.89c-.045.266-.208 1.04.91.567 1.118-.473 6.023-3.546 8.218-6.072C19.893 12.238 20 10.45 20 8.571 20 3.846 15.523 0 10 0z" fill="currentColor"/>
                    </svg>
                    <span>%s</span>
                </button>',
                esc_url($login_url),
                esc_attr($style),
                esc_html($atts['text'])
            );
        } else {
            $button_html = sprintf(
                '<a href="%s" class="moksa-line-login-btn" style="%s">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 0C4.477 0 0 3.846 0 8.571c0 4.236 3.756 7.78 8.823 8.456.343.074.81.226.928.52.106.265.07.68.034.948l-.148.89c-.045.266-.208 1.04.91.567 1.118-.473 6.023-3.546 8.218-6.072C19.893 12.238 20 10.45 20 8.571 20 3.846 15.523 0 10 0z" fill="currentColor"/>
                    </svg>
                    <span>%s</span>
                </a>',
                esc_url($login_url),
                esc_attr($style),
                esc_html($atts['text'])
            );
        }
        
        return $button_html;
    }
    
    /**
     * Render add friend button shortcode
     * Usage: [line_add_friend text="Add Friend" url="https://line.me/R/ti/p/@example"]
     */
    public function render_add_friend_button($atts) {
        $atts = shortcode_atts(array(
            'text' => __('Add Friend', 'moksa-line-login'),
            'url' => get_option('moksa_line_add_friend_url', ''),
        ), $atts, 'line_add_friend');
        
        if (empty($atts['url'])) {
            return '';
        }
        
        return sprintf(
            '<a href="%s" target="_blank" class="moksa-line-add-friend-btn" style="background-color: #00B900; color: #FFFFFF; padding: 10px 20px; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 0C4.477 0 0 3.846 0 8.571c0 4.236 3.756 7.78 8.823 8.456.343.074.81.226.928.52.106.265.07.68.034.948l-.148.89c-.045.266-.208 1.04.91.567 1.118-.473 6.023-3.546 8.218-6.072C19.893 12.238 20 10.45 20 8.571 20 3.846 15.523 0 10 0z" fill="currentColor"/>
                </svg>
                <span>%s</span>
            </a>',
            esc_url($atts['url']),
            esc_html($atts['text'])
        );
    }
    
    /**
     * Render user info shortcode
     * Usage: [line_user_info]
     */
    public function render_user_info($atts) {
        if (!is_user_logged_in()) {
            return '';
        }
        
        $user_id = get_current_user_id();
        $db = Moksa_Line_Database::get_instance();
        $line_user = $db->get_line_user_by_wp_id($user_id);
        
        if (!$line_user) {
            return '<p>' . __('No LINE account connected.', 'moksa-line-login') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="moksa-line-user-info">
            <?php if ($line_user->picture_url): ?>
            <img src="<?php echo esc_url($line_user->picture_url); ?>" 
                 alt="<?php echo esc_attr($line_user->display_name); ?>"
                 class="moksa-line-avatar"
                 style="width: 64px; height: 64px; border-radius: 50%;">
            <?php endif; ?>
            <div class="moksa-line-details">
                <h3><?php echo esc_html($line_user->display_name); ?></h3>
                <?php if ($line_user->status_message): ?>
                <p><?php echo esc_html($line_user->status_message); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
