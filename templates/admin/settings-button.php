<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('自訂 LINE 登入按鈕的外觀。', 'moksa-line-login'); ?></p>
        </div>
    </div>

    <div class="moksa-editor-layout" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="moksa-card" style="height: fit-content;">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;"><?php _e('按鈕樣式', 'moksa-line-login'); ?></h2>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('moksa_line_button');
                do_settings_sections('moksa_line_button');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_text"><?php _e('按鈕文字', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_button_text" name="moksa_line_button_text" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_text', '使用 LINE 登入')); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_bg_color"><?php _e('背景顏色', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_button_bg_color" name="moksa_line_button_bg_color" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_bg_color', '#06C755')); ?>" class="moksa-color-picker">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_text_color"><?php _e('文字顏色', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_button_text_color" name="moksa_line_button_text_color" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_text_color', '#FFFFFF')); ?>" class="moksa-color-picker">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_border_radius"><?php _e('圓角半徑 (px)', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="moksa_line_button_border_radius" name="moksa_line_button_border_radius" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_border_radius', '4')); ?>" class="small-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_width"><?php _e('寬度', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_button_width" name="moksa_line_button_width" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_width', '100%')); ?>" class="regular-text">
                            <p class="description"><?php _e('例如：100%, 200px', 'moksa-line-login'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_height"><?php _e('高度 (px)', 'moksa-line-login'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="moksa_line_button_height" name="moksa_line_button_height" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_height', '44')); ?>" class="small-text">
                        </td>
                    </tr>
                </table>
                
                <div style="padding-top: 20px; border-top: 1px solid #f1f5f9; margin-top: 20px;">
                    <?php submit_button(__('儲存設定', 'moksa-line-login'), 'primary large', 'submit', false); ?>
                </div>
            </form>
        </div>
        
        <div class="moksa-card" style="height: fit-content; background: #f8fafc;">
            <h2 style="margin-top: 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; color: #1e293b;"><?php _e('即時預覽', 'moksa-line-login'); ?></h2>
            
            <div style="padding: 60px 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; justify-content: center; align-items: center; margin-bottom: 20px;">
                <button id="moksa-line-preview-btn" style="display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; gap: 10px; transition: opacity 0.2s; font-weight: bold; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                    <span class="btn-icon"></span>
                    <span class="btn-text"></span>
                </button>
            </div>
            
            <div style="background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0;">
                <p style="margin: 0 0 5px 0; font-weight: 600; font-size: 12px; color: #64748b; text-transform: uppercase;"><?php _e('簡碼用法', 'moksa-line-login'); ?></p>
                <code style="font-size: 14px; color: #2563eb;">[line_login_button]</code>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize color pickers
    $('.moksa-color-picker').wpColorPicker({
        change: function(event, ui) {
            setTimeout(updatePreview, 10); // Small delay to ensure value is updated
        }
    });
    
    // Official LINE Icon SVG
    function getLineIconSVG(size) {
        return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="LINE" role="img">' +
               '<rect width="24" height="24" rx="4" fill="#06C755"/>' +
               '<path d="M12.016 5.5C8.693 5.5 6 7.81 6 10.73c0 2.69 2.38 4.95 5.66 5.35.182.04.43.12.49.28.056.14.037.36.018.5l-.078.47c-.024.14-.11.55.48.3.59-.25 3.19-1.88 4.35-3.22 1.16-1.34 1.7-2.93 1.7-4.38 0-2.92-2.693-5.23-6.016-5.23zm-2.66 7.87h-1.54c-.18 0-.33-.16-.33-.36v-3.45c0-.2.15-.36.33-.36.18 0 .33.16.33.36v3.09h1.54c.18 0 .33.16.33.36 0 .2-.15.36-.33.36zm-1.1-3.45h-.33c-.18 0-.33-.16-.33-.36v-3.45c0-.2.15-.36.33-.36.18 0 .33.16.33.36v3.45c0 .2-.15.36-.33.36zm2.89 0h-.33c-.18 0-.33-.16-.33-.36v-2.64l-.83 2.74c-.05.16-.16.26-.3.26h-.33c-.18 0-.33-.16-.33-.36v-3.45c0-.2.15-.36.33-.36.18 0 .33.16.33.36v2.64l.83-2.74c.05-.16.16-.26.3-.26h.33c.18 0 .33.16.33.36v3.45c0 .2-.15.36-.33.36zm2.34 0h-.33c-.18 0-.33-.16-.33-.36v-3.45c0-.2.15-.36.33-.36h1.54c.18 0 .33.16.33.36 0 .2-.15.36-.33.36h-1.21v.89h1.21c.18 0 .33.16.33.36 0 .2-.15.36-.33.36h-1.21v1.09h1.21c.18 0 .33.16.33.36 0 .2-.15.36-.33.36z" fill="#FFFFFF"/>' +
               '</svg>';
    }
    
    // Live preview updates
    function updatePreview() {
        var text = $('#moksa_line_button_text').val();
        var bgColor = $('#moksa_line_button_bg_color').val();
        var textColor = $('#moksa_line_button_text_color').val();
        var radius = $('#moksa_line_button_border_radius').val();
        var width = $('#moksa_line_button_width').val();
        var height = $('#moksa_line_button_height').val();
        
        var $btn = $('#moksa-line-preview-btn');
        var iconSize = Math.min(parseInt(height) || 24, 24);
        
        $btn.find('.btn-text').text(text);
        $btn.find('.btn-icon').html(getLineIconSVG(iconSize));
        $btn.css({
            'background-color': bgColor,
            'color': textColor,
            'border-radius': radius + 'px',
            'width': width,
            'height': height + 'px'
        });
    }
    
    // Bind events
    $('input').on('change input keyup', updatePreview);
    
    // Initial update
    updatePreview();
});
</script>
