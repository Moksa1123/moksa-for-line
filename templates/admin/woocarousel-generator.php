<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (!class_exists('WooCommerce')) : ?>
        <div class="notice notice-error"><p><?php _e('WooCommerce is not active. Please activate WooCommerce to use this feature.', 'moksa-line-login'); ?></p></div>
    <?php else : ?>
    
    <div class="moksa-line-flex-container" style="display: flex; gap: 20px;">
        
        <!-- Settings Column -->
        <div class="card" style="flex: 1;">
            <h2><?php _e('Carousel Settings', 'moksa-line-login'); ?></h2>
            <form id="woocarousel-form">
                
                <div class="form-group">
                    <label for="carousel_type"><?php _e('Product Source', 'moksa-line-login'); ?></label>
                    <select id="carousel_type" class="widefat">
                        <option value="latest"><?php _e('Latest Products', 'moksa-line-login'); ?></option>
                        <option value="category"><?php _e('Product Category', 'moksa-line-login'); ?></option>
                        <option value="specific"><?php _e('Specific Products (IDs)', 'moksa-line-login'); ?></option>
                    </select>
                </div>
                
                <div id="setting-limit" class="form-group" style="margin-top: 15px;">
                    <label for="carousel_limit"><?php _e('Number of Products', 'moksa-line-login'); ?></label>
                    <input type="number" id="carousel_limit" class="widefat" value="5" min="1" max="12">
                    <p class="description">輪播中最多 12 個氣泡。</p>
                </div>
                
                <div id="setting-category" class="form-group" style="margin-top: 15px; display: none;">
                    <label for="carousel_category"><?php _e('Select Category', 'moksa-line-login'); ?></label>
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
                    <label for="carousel_ids"><?php _e('Product IDs', 'moksa-line-login'); ?></label>
                    <input type="text" id="carousel_ids" class="widefat" placeholder="e.g., 101, 102, 105">
                    <p class="description">以逗號分隔的商品 ID 列表。</p>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="button button-primary"><?php _e('Generate JSON', 'moksa-line-login'); ?></button>
                </div>
            </form>
        </div>
        
        <!-- Result Column -->
        <div class="card" style="flex: 1;">
            <h2><?php _e('Generated JSON', 'moksa-line-login'); ?></h2>
            <p><?php _e('Copy this JSON and use it in Auto Reply or Flex Message Sender.', 'moksa-line-login'); ?></p>
            <textarea id="carousel_result" class="widefat" rows="15" readonly></textarea>
            <button type="button" class="button" id="copy_json" style="margin-top: 10px;"><?php _e('Copy to Clipboard', 'moksa-line-login'); ?></button>
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
        
        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_generate_woo_carousel',
            nonce: moksaLineAdmin.nonce,
            type: type,
            limit: $('#carousel_limit').val(),
            category: $('#carousel_category').val(),
            product_ids: $('#carousel_ids').val()
        }, function(response) {
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
        alert("Copied to clipboard!");
    });
});
</script>
