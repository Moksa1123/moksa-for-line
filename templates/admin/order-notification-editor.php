<div class="wrap">
    <h1><?php _e('Order Notification Template', 'moksa-line-login'); ?></h1>
    <p><?php _e('Customize the Flex Message sent to users when their order status changes.', 'moksa-line-login'); ?></p>

    <div class="moksa-card">
        <div style="display: flex; gap: 20px;">
            <div style="flex: 1;">
                <h3><?php _e('JSON Template', 'moksa-line-login'); ?></h3>
                <div id="moksa-json-editor" style="height: 600px; border: 1px solid #ddd; border-radius: 4px;"></div>
                <textarea id="moksa_line_order_template" name="moksa_line_order_template" style="display: none;"><?php echo esc_textarea(get_option('moksa_line_order_template')); ?></textarea>
                
                <div style="margin-top: 20px;">
                    <button type="button" class="button button-primary button-large" id="moksa-save-template">
                        <?php _e('Save Template', 'moksa-line-login'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="moksa-reset-template">
                        <?php _e('Reset to Default', 'moksa-line-login'); ?>
                    </button>
                </div>
            </div>
            
            <div style="width: 300px;">
                <h3><?php _e('Available Variables', 'moksa-line-login'); ?></h3>
                <p class="description"><?php _e('Click to copy variable to clipboard.', 'moksa-line-login'); ?></p>
                
                <div class="moksa-variable-list">
                    <?php
                    $variables = array(
                        'Basic' => array(
                            '{{order_number}}' => __('Order Number', 'moksa-line-login'),
                            '{{status_label}}' => __('Order Status', 'moksa-line-login'),
                            '{{total}}' => __('Order Total', 'moksa-line-login'),
                            '{{items_count}}' => __('Item Count', 'moksa-line-login'),
                            '{{view_order_url}}' => __('View Order URL', 'moksa-line-login'),
                            '{{status_color}}' => __('Status Color', 'moksa-line-login'),
                        ),
                        'Customer' => array(
                            '{{billing_name}}' => __('Billing Name', 'moksa-line-login'),
                            '{{billing_phone}}' => __('Billing Phone', 'moksa-line-login'),
                            '{{shipping_name}}' => __('Shipping Name', 'moksa-line-login'),
                            '{{shipping_address}}' => __('Shipping Address', 'moksa-line-login'),
                            '{{customer_note}}' => __('Customer Note', 'moksa-line-login'),
                        ),
                        'Logistics & Payment' => array(
                            '{{payment_method}}' => __('Payment Method', 'moksa-line-login'),
                            '{{shipping_method}}' => __('Shipping Method', 'moksa-line-login'),
                            '{{tracking_number}}' => __('Tracking Number (ECPay/RY)', 'moksa-line-login'),
                            '{{store_name}}' => __('CVS Store Name', 'moksa-line-login'),
                            '{{store_address}}' => __('CVS Store Address', 'moksa-line-login'),
                        )
                    );
                    
                    foreach ($variables as $category => $vars) {
                        echo '<h4 style="margin: 15px 0 5px; color: #444;">' . esc_html($category) . '</h4>';
                        foreach ($vars as $var => $desc) {
                            echo '<div class="moksa-variable-item" onclick="navigator.clipboard.writeText(\'' . $var . '\')">';
                            echo '<code>' . $var . '</code>';
                            echo '<span>' . $desc . '</span>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>

                <h3 style="margin-top: 30px;"><?php _e('Preview', 'moksa-line-login'); ?></h3>
                <div id="moksa-flex-preview" class="moksa-phone-preview">
                    <div class="moksa-phone-header">LINE</div>
                    <div class="moksa-phone-content" id="moksa-preview-container">
                        <!-- Preview will be rendered here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.moksa-variable-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.moksa-variable-item {
    padding: 10px;
    background: #f5f5f5;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s;
    border: 1px solid #eee;
}
.moksa-variable-item:hover {
    background: #eef;
    border-color: #ccf;
}
.moksa-variable-item code {
    display: block;
    font-weight: bold;
    color: #d63384;
    margin-bottom: 4px;
}
.moksa-variable-item span {
    font-size: 0.9em;
    color: #666;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize Monaco Editor
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs' }});
    
    require(['vs/editor/editor.main'], function() {
        var defaultJson = <?php echo json_encode(Moksa_Line_WooCommerce::get_instance()->get_default_order_template()); ?>;
        var savedJson = $('#moksa_line_order_template').val();
        
        var editor = monaco.editor.create(document.getElementById('moksa-json-editor'), {
            value: savedJson || JSON.stringify(defaultJson, null, 4),
            language: 'json',
            theme: 'vs-light',
            automaticLayout: true,
            minimap: { enabled: false }
        });
        
        // Update hidden textarea on change
        editor.onDidChangeModelContent(function() {
            var value = editor.getValue();
            $('#moksa_line_order_template').val(value);
            updatePreview(value);
        });
        
        // Initial Preview
        updatePreview(editor.getValue());
        
        // Save Button
        $('#moksa-save-template').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).text('<?php _e('Saving...', 'moksa-line-login'); ?>');
            
            $.post(ajaxurl, {
                action: 'moksa_line_save_order_template',
                template: editor.getValue(),
                nonce: '<?php echo wp_create_nonce('moksa_line_save_template'); ?>'
            }, function(response) {
                btn.prop('disabled', false).text('<?php _e('Save Template', 'moksa-line-login'); ?>');
                if (response.success) {
                    alert('<?php _e('Template saved successfully!', 'moksa-line-login'); ?>');
                } else {
                    alert('<?php _e('Error saving template.', 'moksa-line-login'); ?>');
                }
            });
        });
        
        // Reset Button
        $('#moksa-reset-template').on('click', function() {
            if (confirm('<?php _e('Are you sure you want to reset to the default template?', 'moksa-line-login'); ?>')) {
                editor.setValue(JSON.stringify(defaultJson, null, 4));
            }
        });
        
        function updatePreview(jsonStr) {
            try {
                // Mock data for preview
                var json = jsonStr
                    .replace(/{{order_number}}/g, '12345')
                    .replace(/{{status}}/g, 'processing')
                    .replace(/{{status_label}}/g, 'Processing')
                    .replace(/{{status_color}}/g, '#17c950')
                    .replace(/{{total}}/g, '$150.00')
                    .replace(/{{items_count}}/g, '3')
                    .replace(/{{billing_name}}/g, 'John Doe')
                    .replace(/{{view_order_url}}/g, '#');
                
                var flexObj = JSON.parse(json);
                
                // Use the existing render function from flex-editor.js if available, 
                // or simple render if it's a bubble
                var container = $('#moksa-preview-container');
                container.empty();
                
                // Reuse the global renderFlexMessage if it exists (loaded from flex-editor.js)
                // We need to make sure flex-editor.js is enqueued or copy the logic.
                // For now, let's assume we need to enqueue it.
                if (typeof renderFlexMessage === 'function') {
                    renderFlexMessage(flexObj, container[0]);
                } else {
                    container.html('<p style="padding:20px; text-align:center; color:#999;">Preview requires flex-editor.js</p>');
                }
            } catch (e) {
                // Invalid JSON, ignore
            }
        }
    });
});
</script>
