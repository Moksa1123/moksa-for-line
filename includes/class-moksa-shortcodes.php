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
        $svg_icon = '<svg width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 10px; height: 100%; width: auto; display: block;">
            <g clip-path="url(#clip0_line)">
            <path d="M0 0H44V44H0V0Z" fill="#06C755"/>
            <path d="M22.032 10C15.387 10 10 14.683 10 20.462C10 25.386 14.355 29.558 20.226 30.688C20.699 30.883 21.145 31.624 21.258 32.247C21.36 32.803 21.258 33.973 21.258 33.973C21.258 33.973 21.086 34.938 21.022 35.162C20.84 35.796 20.646 37.121 22.989 36.244C25.333 35.367 33.925 29.392 33.925 29.392C38.065 27.053 40 23.875 40 20.462C40 14.683 34.613 10 22.032 10ZM16.989 24.731H14.075C13.71 24.731 13.409 24.439 13.409 24.073V17.565C13.409 17.199 13.71 16.907 14.075 16.907C14.441 16.907 14.742 17.199 14.742 17.565V23.398H16.989C17.355 23.398 17.656 23.69 17.656 24.055C17.656 24.421 17.355 24.731 16.989 24.731ZM20.183 24.731H19.516C19.151 24.731 18.849 24.439 18.849 24.073V17.565C18.849 17.199 19.151 16.907 19.516 16.907C19.882 16.907 20.183 17.199 20.183 17.565V24.073C20.183 24.439 19.882 24.731 20.183 24.731ZM25.957 24.731H25.29C24.925 24.731 24.624 24.439 24.624 24.073V19.066L22.968 24.239C22.871 24.531 22.591 24.731 22.28 24.731H21.613C21.247 24.731 20.946 24.439 20.946 24.073V17.565C20.946 17.199 21.247 16.907 21.613 16.907C21.978 16.907 22.28 17.199 22.28 17.565V22.43L23.925 17.399C24.022 17.107 24.301 16.907 24.613 16.907H25.28C25.645 16.907 25.946 17.199 25.946 17.565V24.073C25.946 24.439 25.645 24.731 25.957 24.731ZM30.645 24.073C30.645 24.439 30.344 24.731 29.978 24.731H27.065C26.699 24.731 26.398 24.439 26.398 24.073V17.565C26.398 17.199 26.699 16.907 27.065 16.907H29.978C30.344 16.907 30.645 17.199 30.645 17.565C30.645 17.931 30.344 18.222 29.978 18.222H27.731V19.898H29.978C30.344 19.898 30.645 20.19 30.645 20.555C30.645 20.921 30.344 21.213 29.978 21.213H27.731V23.398H29.978C30.344 23.398 30.645 23.69 30.645 24.055V24.073Z" fill="white"/>
            </g>
            <defs>
            <clipPath id="clip0_line">
            <rect width="44" height="44" fill="white"/>
            </clipPath>
            </defs>
            </svg>';
        
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
