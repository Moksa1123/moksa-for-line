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
        try {
            var flexObj = JSON.parse(jsonStr);
            var container = $('#preview_container');
            container.empty();

            // Render Flex Message
            renderSimpleFlex(flexObj, container);

        } catch (e) {
            var container = $('#preview_container');
            // Don't clear if empty string to avoid flashing error on init if empty
            if (!jsonStr || !jsonStr.trim()) return;

            container.html('<div style="background: #fff; padding: 12px 16px; border-radius: 8px; color: #dc2626; border: 1px solid #fecaca; font-size: 13px;">JSON 格式錯誤：' + e.message + '</div>');
        }
    };

    function renderSimpleFlex(obj, container) {
        // Handle Bubble
        if (obj.type === 'bubble') {
            var bubble = $('<div class="flex-bubble" style="background: #fff; border-radius: 12px; overflow: hidden; max-width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"></div>');

            if (obj.header) renderBox(obj.header, bubble, 'flex-header');
            if (obj.hero) renderImage(obj.hero, bubble, 'flex-hero');
            if (obj.body) renderBox(obj.body, bubble, 'flex-body');
            if (obj.footer) renderBox(obj.footer, bubble, 'flex-footer');

            container.append(bubble);
        } else if (obj.type === 'flex') {
            if (obj.contents) {
                renderSimpleFlex(obj.contents, container);
            }
        } else if (obj.type === 'carousel') {
            // Handle carousel (simplified - just show first bubble)
            if (obj.contents && obj.contents.length > 0) {
                container.append('<div style="text-align:center; font-size:12px; color:#64748b; margin-bottom:8px;">(Carousel Preview - Showing first item)</div>');
                renderSimpleFlex(obj.contents[0], container);
            }
        }
    }

    function renderBox(box, parent, className) {
        var div = $('<div class="' + (className || '') + '" style="padding: 16px;"></div>');

        // Apply styles
        if (box.backgroundColor) div.css('background-color', box.backgroundColor);
        if (box.layout === 'horizontal') div.css({ display: 'flex', flexDirection: 'row', gap: '5px', alignItems: 'center' });
        if (box.layout === 'vertical') div.css({ display: 'flex', flexDirection: 'column', gap: '5px' });

        // Padding handling (simplified)
        if (box.paddingAll) div.css('padding', box.paddingAll);

        if (box.contents && Array.isArray(box.contents)) {
            box.contents.forEach(function (item) {
                if (item.type === 'text') {
                    var p = $('<p class="flex-text" style="margin: 0; line-height: 1.5;"></p>');
                    p.text(item.text);
                    if (item.color) p.css('color', item.color);
                    if (item.size === 'xs') p.css('font-size', '10px');
                    if (item.size === 'sm') p.css('font-size', '12px');
                    if (item.size === 'md') p.css('font-size', '14px');
                    if (item.size === 'lg') p.css('font-size', '16px');
                    if (item.size === 'xl') p.css('font-size', '18px');
                    if (item.size === 'xxl') p.css('font-size', '20px');
                    if (item.weight === 'bold') p.css('font-weight', 'bold');
                    if (item.align) p.css('text-align', item.align);
                    if (item.flex) p.css('flex', item.flex);
                    if (item.wrap) p.css('white-space', 'pre-wrap');
                    div.append(p);
                } else if (item.type === 'button') {
                    var a = $('<a href="#" class="flex-button" style="display: block; text-align: center; padding: 10px; background: #f1f5f9; text-decoration: none; color: #475569; border-radius: 6px; margin-top: 5px; font-weight: 500; transition: all 0.2s;"></a>');
                    a.text(item.action ? (item.action.label || '按鈕') : '按鈕');
                    if (item.style === 'primary') a.css({ 'background-color': '#2563eb', 'color': '#fff' });
                    if (item.style === 'secondary') a.css({ 'background-color': '#e2e8f0', 'color': '#475569' });
                    if (item.color) a.css('background-color', item.color);
                    div.append(a);
                } else if (item.type === 'box') {
                    renderBox(item, div);
                } else if (item.type === 'separator') {
                    div.append('<hr style="border:0; border-top:1px solid #e2e8f0; margin: 8px 0;">');
                } else if (item.type === 'image') {
                    renderImage(item, div);
                }
            });
        }

        parent.append(div);
    }

    function renderImage(img, parent, className) {
        var div = $('<div class="' + (className || '') + '"></div>');
        var imgEl = $('<img style="width: 100%; height: auto; display: block;">');
        imgEl.attr('src', img.url || '');
        if (img.aspectRatio) {
            var ratio = img.aspectRatio.split(':');
            var percent = (parseFloat(ratio[1]) / parseFloat(ratio[0])) * 100;
            imgEl.css('aspect-ratio', img.aspectRatio.replace(':', '/'));
        }
        if (img.size === 'full') imgEl.css('width', '100%');
        if (img.size === 'xl') imgEl.css('width', '100%');
        if (img.size === 'lg') imgEl.css('width', '75%');
        if (img.size === 'md') imgEl.css('width', '50%');
        if (img.size === 'sm') imgEl.css('width', '25%');
        if (img.size === 'xs') imgEl.css('width', '15%');

        div.append(imgEl);
        parent.append(div);
    }

    // Preview Button
    $('#preview_flex').on('click', function () {
        var json = window.editor ? window.editor.getValue() : $('#flex_json').val();
        updatePreview(json);
    });
});
