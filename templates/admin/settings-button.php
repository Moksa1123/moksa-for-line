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
    
    // Official LINE Icon Image
    function getLineIcon(size) {
        return '<img src="https://moksaweb.com/wp-content/uploads/2025/11/LINE_Brand_icon.png" alt="LINE" width="' + size + '" height="' + size + '" style="display: inline-block; vertical-align: middle;" />';
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
        $btn.find('.btn-icon').html(getLineIcon(iconSize));
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
