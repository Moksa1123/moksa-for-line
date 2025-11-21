<div class="wrap moksa-line-wrap">
    <div class="moksa-editor-header">
        <div class="header-left">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php _e('從 WooCommerce 商品自動生成 Flex Message 輪播 JSON。', 'moksa-line-login'); ?></p>
        </div>
    </div>
    
    <?php if (!class_exists('WooCommerce')) : ?>
        <div class="moksa-notice moksa-notice-error" style="margin-bottom: 24px;">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle;">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span><?php _e('WooCommerce 未啟用。請先啟用 WooCommerce 以使用此功能。', 'moksa-line-login'); ?></span>
        </div>
    <?php else : ?>
    
    <div class="moksa-editor-layout" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        
        <!-- Settings Column -->
        <div class="moksa-card">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 15px; font-size: 18px;"><?php _e('輪播設定', 'moksa-line-login'); ?></h2>
            <form id="woocarousel-form">
                
                <div class="form-group">
                    <label for="carousel_type" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('商品來源', 'moksa-line-login'); ?></label>
                    <select id="carousel_type" class="widefat">
                        <option value="latest"><?php _e('最新商品', 'moksa-line-login'); ?></option>
                        <option value="category"><?php _e('商品分類', 'moksa-line-login'); ?></option>
                        <option value="specific"><?php _e('指定商品 (ID)', 'moksa-line-login'); ?></option>
                    </select>
                </div>
                
                <div id="setting-limit" class="form-group" style="margin-top: 15px;">
                    <label for="carousel_limit" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('商品數量', 'moksa-line-login'); ?></label>
                    <input type="number" id="carousel_limit" class="widefat" value="5" min="1" max="12">
                    <p class="description"><?php _e('輪播中最多 12 個氣泡。', 'moksa-line-login'); ?></p>
                </div>
                
                <div id="setting-category" class="form-group" style="margin-top: 15px; display: none;">
                    <label for="carousel_category" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('選擇分類', 'moksa-line-login'); ?></label>
                    <?php
                    $args = array(
                        'taxonomy' => 'product_cat',
                        'orderby' => 'name',
                        'show_count' => 0,
                        'pad_counts' => 0,
                        'hierarchical' => 1,
                        'title_li' => '',
                        'hide_empty' => 0
                    );
                    $all_categories = get_categories($args);
                    ?>
                    <select id="carousel_category" class="widefat">
                        <?php foreach ($all_categories as $cat) : ?>
                            <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="setting-specific" class="form-group" style="margin-top: 15px; display: none;">
                    <label for="carousel_ids" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('商品 ID', 'moksa-line-login'); ?></label>
                    <input type="text" id="carousel_ids" class="widefat" placeholder="e.g., 101, 102, 105">
                    <p class="description"><?php _e('以逗號分隔的商品 ID 列表。', 'moksa-line-login'); ?></p>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                    <button type="submit" class="button button-primary button-large" style="width: 100%; justify-content: center;"><?php _e('生成 JSON', 'moksa-line-login'); ?></button>
                </div>
            </form>
        </div>
        
        <!-- Result Column -->
        <div class="moksa-card">
            <h2 style="margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 15px; font-size: 18px;"><?php _e('生成的 JSON', 'moksa-line-login'); ?></h2>
            <p style="color: #64748b; margin-bottom: 10px;"><?php _e('複製此 JSON 並用於自動回覆或 Flex 訊息發送器。', 'moksa-line-login'); ?></p>
            <textarea id="carousel_result" class="widefat" rows="15" readonly style="font-family: monospace; background: #f8fafc; font-size: 12px;"></textarea>
            <button type="button" class="button" id="copy_json" style="margin-top: 15px; width: 100%; justify-content: center;">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 5px;">
                    <path d="M8 2a1 1 0 000 2h2a1 1 0 100-2H8z"/><path d="M6 4a2 2 0 012-2h2a2 2 0 012 2v2h2a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h2V4z"/>
                </svg>
                <?php _e('複製到剪貼簿', 'moksa-line-login'); ?>
            </button>
        </div>
        
    </div>
    
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Toggle settings
    $('#carousel_type').on('change', function() {
        var type = $(this).val();
        $('#setting-category').hide();
        $('#setting-specific').hide();
        
        if (type === 'category') {
            $('#setting-category').show();
        } else if (type === 'specific') {
            $('#setting-specific').show();
        }
    });
    
    // Generate
    $('#woocarousel-form').on('submit', function(e) {
        e.preventDefault();
        
        var type = $('#carousel_type').val();
        var $btn = $(this).find('button[type="submit"]');
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text('<?php _e('生成中...', 'moksa-line-login'); ?>');
        
        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_generate_woo_carousel',
            nonce: moksaLineAdmin.nonce,
            type: type,
            limit: $('#carousel_limit').val(),
            category: $('#carousel_category').val(),
            product_ids: $('#carousel_ids').val()
        }, function(response) {
            $btn.prop('disabled', false).text(originalText);
            
            if (response.success) {
                $('#carousel_result').val(JSON.stringify(response.data, null, 2));
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Copy
    $('#copy_json').on('click', function() {
        var copyText = document.getElementById("carousel_result");
        copyText.select();
        copyText.setSelectionRange(0, 99999); /* For mobile devices */
        document.execCommand("copy");
        
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.html('<svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align: middle; margin-right: 5px; display: inline-block;"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> <?php _e('已複製！', 'moksa-line-login'); ?>');
        setTimeout(function() {
            $btn.html(originalText);
        }, 2000);
    });
});
</script>
