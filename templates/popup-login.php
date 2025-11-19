<?php
/**
 * Popup Login Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$login_url = Moksa_Line_Auth::get_instance()->get_login_url();
$button_text = get_option('moksa_line_button_text', __('Login with LINE', 'moksa-line-login'));
?>

<div id="moksa-line-popup" class="moksa-line-popup-overlay">
    <div class="moksa-line-popup-content">
        <button type="button" class="moksa-line-popup-close" aria-label="<?php _e('Close', 'moksa-line-login'); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <div class="moksa-line-popup-footer">
            <p><?php printf(__('By continuing, you agree to our %s and %s.', 'moksa-line-login'), '<a href="#">' . __('Terms', 'moksa-line-login') . '</a>', '<a href="#">' . __('Privacy Policy', 'moksa-line-login') . '</a>'); ?></p>
        </div>
    </div>
</div>
