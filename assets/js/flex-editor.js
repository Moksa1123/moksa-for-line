jQuery(document).ready(function ($) {

    // Toggle User Selector
    $('#target_type').on('change', function () {
        if ($(this).val() === 'specific') {
            $('#user_selector').slideDown();
        } else {
            $('#user_selector').slideUp();
        }
    });

    // Load Sample
    $('#load_sample').on('click', function () {
        var sample = {
            "type": "bubble",
            "hero": {
                "type": "image",
                "url": "https://scdn.line-apps.com/n/channel_devcenter/img/fx/01_1_cafe.png",
                "size": "full",
                "aspectRatio": "20:13",
                "aspectMode": "cover",
                "action": {
                    "type": "uri",
                    "uri": "http://linecorp.com/"
                }
            },
            "body": {
                "type": "box",
                "layout": "vertical",
                "contents": [
                    {
                        "type": "text",
                        "text": "Brown Cafe",
                        "weight": "bold",
                        "size": "xl"
                    }
                ]
            }
        };

        var $jsonTextarea = $('#flex_json');
        $jsonTextarea.val(JSON.stringify(sample, null, 2));
        updatePreview(JSON.stringify(sample, null, 2));
    });

    // Send Flex Message
    $('#send_flex').on('click', function () {
        var $btn = $(this);
        var json = $('#flex_json').val();
        var altText = $('#flex_alt_text').val();
        var targetType = $('#target_type').val();
        var userIds = [];

        if (targetType === 'specific') {
            $('input[name="user_ids[]"]:checked').each(function () {
                userIds.push($(this).val());
            });
        }

        if (!altText) {
            alert('請輸入替代文字');
            return;
        }

        try {
            JSON.parse(json);
        } catch (e) {
            alert('JSON 格式錯誤：' + e.message);
            return;
        }

        $btn.prop('disabled', true).text('發送中...');

        $.post(ajaxurl, {
            action: 'moksa_line_send_flex',
            nonce: moksaLineAdmin.nonce,
            flex_json: json,
            alt_text: altText,
            target_type: targetType,
            user_ids: userIds
        }, function (response) {
            if (response.success) {
                alert('訊息發送成功！');
            } else {
                alert('錯誤：' + response.data);
            }
            $btn.prop('disabled', false).text('立即發送');
        });
    });

    // Auto-update preview when textarea changes (debounced)
    var previewTimeout;
    $('#flex_json').on('input', function () {
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(function () {
            var json = $('#flex_json').val();
            if (json.trim()) {
                try {
                    JSON.parse(json);
                    updatePreview(json);
                } catch (e) {
                    // Invalid JSON, don't update preview
                }
            }
        }, 500);
    });


    // Preview Function with enhanced debugging and retry logic
    window.updatePreview = function (jsonStr) {
        var container = $('#preview_container');
        console.log('[Flex Preview] updatePreview called');
        console.log('[Flex Preview] MoksaFlexRenderer available:', !!window.MoksaFlexRenderer);
        console.log('[Flex Preview] jQuery available:', !!window.jQuery);
        console.log('[Flex Preview] Container found:', container.length > 0);

        try {
            var flexObj = JSON.parse(jsonStr);
            console.log('[Flex Preview] JSON parsed successfully');

            // Use Shared Renderer with retry logic
            if (window.MoksaFlexRenderer) {
                console.log('[Flex Preview] Calling renderer...');
                window.MoksaFlexRenderer.render(flexObj, container);
            } else {
                // Renderer not loaded yet, try waiting
                console.warn('[Flex Preview] Renderer not loaded, waiting 500ms...');
                setTimeout(function () {
                    if (window.MoksaFlexRenderer) {
                        console.log('[Flex Preview] Renderer loaded after wait, rendering now...');
                        window.MoksaFlexRenderer.render(flexObj, container);
                    } else {
                        console.error('[Flex Preview] Renderer still not loaded after wait');
                        container.html('<div style="background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 12px; border-radius: 8px; font-size: 13px;"><strong>Flex Renderer not loaded.</strong><br>請重新整理頁面或檢查瀏覽器控制台。</div>');
                    }
                }, 500);
            }

        } catch (e) {
            if (!jsonStr || !jsonStr.trim()) return;
            console.error('[Flex Preview] JSON parse error:', e);
            container.html('<div style="background: #fff; padding: 12px 16px; border-radius: 8px; color: #dc2626; border: 1px solid #fecaca; font-size: 13px;">JSON 格式錯誤：' + e.message + '</div>');
        }
    };

    // Preview Button
    $('#preview_flex').on('click', function () {
        var json = $('#flex_json').val();
        updatePreview(json);
    });

    // Device Size Selector
    $('.moksa-device-btn').on('click', function () {
        var device = $(this).data('device');
        $('.moksa-device-btn').removeClass('active');
        $(this).addClass('active');
        $('#phone-preview').removeClass('mobile tablet desktop').addClass(device);
    });

    // Refresh Preview
    $('#refresh_preview').on('click', function () {
        var json = $('#flex_json').val();
        updatePreview(json);
    });

    // Format JSON (handled in template inline script)
    // Copy JSON (removed, not needed)
    // Validate JSON (handled in template inline script)

    // Update Editor Status Helper
    function updateEditorStatus(type, message) {
        var $status = $('#editor-status');
        $status.removeClass('valid invalid loading').addClass(type);
        $status.text(message || '');
        if (message) {
            setTimeout(function () {
                $status.text('').removeClass(type);
            }, 3000);
        }
    }

    // Auto-validate on change (handled in template inline script)
});
