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

        if (window.editor) {
            window.editor.setValue(JSON.stringify(sample, null, 2));
            updatePreview(JSON.stringify(sample, null, 2));
        }
    });

    // Send Flex Message
    $('#send_flex').on('click', function () {
        var $btn = $(this);
        var json = window.editor ? window.editor.getValue() : $('#flex_json').val();
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

    // Listen for Monaco Editor Ready Event
    $(document).on('moksa-monaco-ready', function (e, editor) {
        window.editor = editor; // Ensure global access

        // Auto-update preview when editor changes (debounced)
        editor.onDidChangeModelContent(function () {
            clearTimeout(window.flexPreviewTimeout);
            window.flexPreviewTimeout = setTimeout(function () {
                var json = editor.getValue();
                updatePreview(json);
            }, 500);
        });

        // Initial preview
        updatePreview(editor.getValue());
    });

    // Preview Function
    window.updatePreview = function (jsonStr) {
        var container = $('#preview_container');

        try {
            var flexObj = JSON.parse(jsonStr);

            // Use Shared Renderer
            if (window.MoksaFlexRenderer) {
                window.MoksaFlexRenderer.render(flexObj, container);
            } else {
                container.html('<div style="color:red;">Flex Renderer not loaded.</div>');
            }

        } catch (e) {
            if (!jsonStr || !jsonStr.trim()) return;
            container.html('<div style="background: #fff; padding: 12px 16px; border-radius: 8px; color: #dc2626; border: 1px solid #fecaca; font-size: 13px;">JSON 格式錯誤：' + e.message + '</div>');
        }
    };

    // Preview Button
    $('#preview_flex').on('click', function () {
        var json = window.editor ? window.editor.getValue() : $('#flex_json').val();
        updatePreview(json);
    });
});
