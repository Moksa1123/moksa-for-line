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
            alert('Please enter Alt Text');
            return;
        }

        $btn.prop('disabled', true).text('Sending...');

        $.post(moksaLineAdmin.ajaxUrl, {
            action: 'moksa_line_send_flex',
            nonce: moksaLineAdmin.nonce,
            flex_json: json,
            alt_text: altText,
            target_type: targetType,
            user_ids: userIds
        }, function (response) {
            if (response.success) {
                alert('Message sent successfully!');
            } else {
                alert('Error: ' + response.data);
            }
            $btn.prop('disabled', false).text('Send Now');
        });
    });

    // Preview (Simple implementation - rendering Flex Message in HTML is complex, 
    // so we might just show a placeholder or try to parse basic structure)
    $('#preview_flex').on('click', function () {
        // For a real preview, we would need a Flex Message renderer library.
        // For now, we just validate the JSON.
        try {
            var json = window.editor ? window.editor.getValue() : $('#flex_json').val();
            JSON.parse(json);
            $('#preview_container').html('<div style="background:#fff; padding:10px; border-radius:10px;">JSON is valid. (Visual preview requires external library)</div>');
        } catch (e) {
            $('#preview_container').html('<div style="color:red; background:#fff; padding:10px; border-radius:10px;">Invalid JSON</div>');
        }
    });
});
