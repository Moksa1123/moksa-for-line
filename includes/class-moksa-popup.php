<?php
/**
 * Popup Handler for LINE Login
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Moksa_Line_Popup {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'render_popup'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'moksa-line-popup',
            MOKSA_LINE_PLUGIN_URL . 'assets/css/popup.css',
            array(),
            MOKSA_LINE_VERSION
        );
        
        wp_enqueue_script(
            'moksa-line-popup',
            MOKSA_LINE_PLUGIN_URL . 'assets/js/popup.js',
            array('jquery'),
            MOKSA_LINE_VERSION,
            true
        );
        
        wp_localize_script('moksa-line-popup', 'moksaLinePopup', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('moksa_line_popup'),
        ));
    }
    
    /**
     * Render popup HTML
     */
    public function render_popup() {
        if (is_user_logged_in()) {
            return;
        }
        
        include MOKSA_LINE_PLUGIN_DIR . 'templates/popup-login.php';
    }
}
