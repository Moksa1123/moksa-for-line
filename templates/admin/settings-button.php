<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('自訂 LINE 登入按鈕的外觀。', 'moksa-line-login'); ?></p>
        </div>
    </div>

    <div class="moksa-editor-layout" style="display: block; max-width: 800px;">
        <div class="moksa-card" style="text-align: center;">
            <h2 style="margin-top: 0; margin-bottom: 24px; color: #1e293b;"><?php _e('LINE 登入按鈕預覽', 'moksa-line-login'); ?></h2>
            
            <div style="padding: 60px 20px; background: #f8fafc; border: 1px solid #e2e8f0; margin-bottom: 24px; display: flex; justify-content: center; align-items: center;">
                <button id="moksa-line-preview-btn" style="display: inline-flex; align-items: center; justify-content: center; border: none; cursor: default; gap: 10px; font-weight: bold; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; pointer-events: none;">
                    <span class="btn-icon"></span>
                    <span class="btn-text"></span>
                </button>
            </div>
            
            <div style="background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                <p style="margin: 0 0 12px 0; font-weight: 600; font-size: 13px; color: #475569;"><?php _e('簡碼用法', 'moksa-line-login'); ?></p>
                <code style="font-size: 14px; color: #2563eb; background: #fff; padding: 8px 12px; display: inline-block; border: 1px solid #e2e8f0;">[line_login_button]</code>
            </div>
            
            <div style="background: #fef3c7; border: 1px solid #fbbf24; padding: 16px; text-align: left;">
                <p style="margin: 0; color: #92400e; font-size: 13px; line-height: 1.6;">
                    <strong><?php _e('注意：', 'moksa-line-login'); ?></strong> <?php _e('按鈕樣式已固定為 LINE 官方規範，無法自訂修改。', 'moksa-line-login'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Official LINE Icon Image
    function getLineIcon(size) {
        return '<img src="https://moksaweb.com/wp-content/uploads/2025/11/LINE_Brand_icon.png" alt="LINE" width="' + size + '" height="' + size + '" style="display: inline-block; vertical-align: middle;" />';
    }
    
    // Fixed preview (no customization allowed)
    function updatePreview() {
        var $btn = $('#moksa-line-preview-btn');
        var text = '<?php echo esc_js(get_option('moksa_line_button_text', '使用 LINE 登入')); ?>';
        var bgColor = '#06C755';
        var textColor = '#FFFFFF';
        var radius = '4';
        var width = '100%';
        var height = '44';
        
        $btn.find('.btn-text').text(text);
        $btn.find('.btn-icon').html(getLineIcon(24));
        $btn.css({
            'background-color': bgColor,
            'color': textColor,
            'border-radius': radius + 'px',
            'width': width,
            'height': height + 'px',
            'padding': '0 20px',
            'font-size': '16px'
        });
    }
    
    // Initial update
    updatePreview();
});
</script>
