<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="moksa-line-settings-container" style="display: flex; gap: 40px;">
        <div class="moksa-line-settings-form" style="flex: 1;">
            <form method="post" action="options.php">
                <?php
                settings_fields('moksa_line_button');
                do_settings_sections('moksa_line_button');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_text">按鈕文字</label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_button_text" name="moksa_line_button_text" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_text', '使用 LINE 登入')); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_bg_color">背景顏色</label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_button_bg_color" name="moksa_line_button_bg_color" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_bg_color', '#06C755')); ?>" class="moksa-color-picker">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_text_color">文字顏色</label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_button_text_color" name="moksa_line_button_text_color" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_text_color', '#FFFFFF')); ?>" class="moksa-color-picker">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_border_radius">圓角半徑 (px)</label>
                        </th>
                        <td>
                            <input type="number" id="moksa_line_button_border_radius" name="moksa_line_button_border_radius" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_border_radius', '4')); ?>" class="small-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_width">寬度</label>
                        </th>
                        <td>
                            <input type="text" id="moksa_line_button_width" name="moksa_line_button_width" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_width', '100%')); ?>" class="regular-text">
                            <p class="description">例如：100%, 200px</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moksa_line_button_height">高度 (px)</label>
                        </th>
                        <td>
                            <input type="number" id="moksa_line_button_height" name="moksa_line_button_height" 
                                   value="<?php echo esc_attr(get_option('moksa_line_button_height', '44')); ?>" class="small-text">
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <div class="moksa-line-preview-panel" style="flex: 1; padding: 24px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
            <h3 style="margin-top: 0; color: #1e293b; font-weight: 600; font-size: 1.125rem;">即時預覽</h3>
            <div style="margin-top: 20px; padding: 40px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; justify-content: center; align-items: center;">
                <button id="moksa-line-preview-btn" style="display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; gap: 10px;">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 0C4.477 0 0 3.846 0 8.571c0 4.236 3.756 7.78 8.823 8.456.343.074.81.226.928.52.106.265.07.68.034.948l-.148.89c-.045.266-.208 1.04.91.567 1.118-.473 6.023-3.546 8.218-6.072C19.893 12.238 20 10.45 20 8.571 20 3.846 15.523 0 10 0z" fill="currentColor"/>
                    </svg>
                    <span class="btn-text"></span>
                </button>
            </div>
            <p class="description" style="margin-top: 15px;">
                簡碼用法：<br>
                <code>[line_login_button]</code>
            </p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize color pickers
    $('.moksa-color-picker').wpColorPicker({
        change: function(event, ui) {
            updatePreview();
        }
    });
    
    // Live preview updates
    function updatePreview() {
        var text = $('#moksa_line_button_text').val();
        var bgColor = $('#moksa_line_button_bg_color').val();
        var textColor = $('#moksa_line_button_text_color').val();
        var radius = $('#moksa_line_button_border_radius').val();
        var width = $('#moksa_line_button_width').val();
        var height = $('#moksa_line_button_height').val();
        
        var $btn = $('#moksa-line-preview-btn');
        
        $btn.find('.btn-text').text(text);
        $btn.css({
            'background-color': bgColor,
            'color': textColor,
            'border-radius': radius + 'px',
            'width': width,
            'height': height + 'px'
        });
    }
    
    // Bind events
    $('input').on('change input', updatePreview);
    
    // Initial update
    updatePreview();
});
</script>
