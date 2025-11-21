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
     * Get official LINE icon SVG
     * @param int $size Icon size in pixels (default: 24)
     * @return string SVG markup
     */
    public static function get_line_icon_svg($size = 24) {
        $unique_id = 'line-icon-' . wp_generate_password(8, false);
        return sprintf(
            '<svg width="%d" height="%d" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="LINE" role="img">
                <rect width="24" height="24" rx="4" fill="#06C755"/>
                <path d="M12.016 5.5C8.693 5.5 6 7.81 6 10.73c0 2.69 2.38 4.95 5.66 5.35.182.04.43.12.49.28.056.14.037.36.018.5l-.078.47c-.024.14-.11.55.48.3.59-.25 3.19-1.88 4.35-3.22 1.16-1.34 1.7-2.93 1.7-4.38 0-2.92-2.693-5.23-6.016-5.23zm-2.66 7.87h-1.54c-.18 0-.33-.16-.33-.36v-3.45c0-.2.15-.36.33-.36.18 0 .33.16.33.36v3.09h1.54c.18 0 .33.16.33.36 0 .2-.15.36-.33.36zm-1.1-3.45h-.33c-.18 0-.33-.16-.33-.36v-3.45c0-.2.15-.36.33-.36.18 0 .33.16.33.36v3.45c0 .2-.15.36-.33.36zm2.89 0h-.33c-.18 0-.33-.16-.33-.36v-2.64l-.83 2.74c-.05.16-.16.26-.3.26h-.33c-.18 0-.33-.16-.33-.36v-3.45c0-.2.15-.36.33-.36.18 0 .33.16.33.36v2.64l.83-2.74c.05-.16.16-.26.3-.26h.33c.18 0 .33.16.33.36v3.45c0 .2-.15.36-.33.36zm2.34 0h-.33c-.18 0-.33-.16-.33-.36v-3.45c0-.2.15-.36.33-.36h1.54c.18 0 .33.16.33.36 0 .2-.15.36-.33.36h-1.21v.89h1.21c.18 0 .33.16.33.36 0 .2-.15.36-.33.36h-1.21v1.09h1.21c.18 0 .33.16.33.36 0 .2-.15.36-.33.36z" fill="#FFFFFF"/>
            </svg>',
            $size,
            $size
        );
    }
    
    /**
     * Render LINE login button shortcode
     * Usage: [line_login_button text="Login with LINE" redirect_url="/my-account"]
     */
    public function render_login_button($atts) {
        $atts = shortcode_atts(array(
            'text' => get_option('moksa_line_button_text', '使用 LINE 登入'),
            'redirect_url' => '',
            'show_popup' => 'yes',
        ), $atts, 'line_login_button');
        
        if (is_user_logged_in()) {
            return '';
        }
        
        $auth = Moksa_Line_Auth::get_instance();
        $redirect = !empty($atts['redirect_url']) ? $atts['redirect_url'] : '';
        $login_url = $auth->get_login_url($redirect);
        
        $bg_color = get_option('moksa_line_button_bg_color', '#06C755');
        $text_color = get_option('moksa_line_button_text_color', '#FFFFFF');
        $border_radius = get_option('moksa_line_button_border_radius', '4');
        $width = get_option('moksa_line_button_width', '100%');
        $height = get_option('moksa_line_button_height', '44');
        
        $style = sprintf(
            'background-color: %s; color: %s; border-radius: %spx; width: %s; height: %spx; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border: none; cursor: pointer; font-weight: bold; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;',
            esc_attr($bg_color),
            esc_attr($text_color),
            esc_attr($border_radius),
            esc_attr($width),
            esc_attr($height)
        );
        
        // Official LINE Icon SVG
        $icon_size = min(intval($height), 24); // Use button height or 24px, whichever is smaller
        $svg_icon = '<span style="margin-right: 10px; display: inline-flex; align-items: center; height: 100%;">' . 
                    self::get_line_icon_svg($icon_size) . 
                    '</span>';
        
        if ($atts['show_popup'] === 'yes') {
            $button_html = sprintf(
                '<button class="moksa-line-login-btn" data-redirect="%s" style="%s">
                    %s
                    <span>%s</span>
                </button>',
                esc_url($login_url),
                esc_attr($style),
                $svg_icon,
                esc_html($atts['text'])
            );
        } else {
            $button_html = sprintf(
                '<a href="%s" class="moksa-line-login-btn" style="%s">
                    %s
                    <span>%s</span>
                </a>',
                esc_url($login_url),
                esc_attr($style),
                $svg_icon,
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
                %s
                <span>%s</span>
            </a>',
            esc_url($atts['url']),
            self::get_line_icon_svg(20),
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
