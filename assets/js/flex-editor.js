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

            // Container Style for Preview Area
            container.css({
                'background-color': '#849ebf', // LINE Chat Background Color
                'padding': '20px',
                'min-height': '300px',
                'display': 'flex',
                'flex-direction': 'column',
                'align-items': 'flex-start', // Align bubbles to left
                'gap': '10px',
                'overflow-x': 'hidden' // Prevent container scroll, handle carousel inside
            });

            // Render Flex Message
            if (flexObj.type === 'flex') {
                renderFlexContainer(flexObj, container);
            } else if (flexObj.type === 'bubble' || flexObj.type === 'carousel') {
                renderFlexContainer(flexObj, container);
            } else {
                // Handle raw bubble object without type: flex wrapper
                renderFlexContainer({ type: 'bubble', ...flexObj }, container);
            }

        } catch (e) {
            var container = $('#preview_container');
            if (!jsonStr || !jsonStr.trim()) return;
            container.html('<div style="background: #fff; padding: 12px 16px; border-radius: 8px; color: #dc2626; border: 1px solid #fecaca; font-size: 13px;">JSON 格式錯誤：' + e.message + '</div>');
        }
    };

    function renderFlexContainer(obj, container) {
        if (obj.type === 'bubble') {
            container.append(renderBubble(obj));
        } else if (obj.type === 'carousel') {
            var carousel = $('<div class="flex-carousel" style="display: flex; overflow-x: auto; gap: 10px; padding-bottom: 10px; width: 100%; scroll-snap-type: x mandatory;"></div>');
            if (obj.contents && Array.isArray(obj.contents)) {
                obj.contents.forEach(function (bubbleObj) {
                    var bubbleWrapper = $('<div style="flex: 0 0 auto; width: 300px; scroll-snap-align: start;"></div>');
                    bubbleWrapper.append(renderBubble(bubbleObj));
                    carousel.append(bubbleWrapper);
                });
            }
            container.append(carousel);
        } else if (obj.contents) {
            // Handle wrapper object
            renderFlexContainer(obj.contents, container);
        }
    }

    function renderBubble(bubbleObj) {
        var bubble = $('<div class="flex-bubble" style="background: #fff; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; position: relative;"></div>');

        // Bubble Styles
        if (bubbleObj.size === 'giga') bubble.css('width', '100%');
        else if (bubbleObj.size === 'mega') bubble.css('width', '300px');
        else if (bubbleObj.size === 'kilo') bubble.css('width', '260px');
        else if (bubbleObj.size === 'micro') bubble.css('width', '160px');
        else if (bubbleObj.size === 'nano') bubble.css('width', '120px');
        else bubble.css('width', '300px'); // Default to mega-like

        // Direction
        var dir = bubbleObj.direction || 'ltr';
        bubble.css('direction', dir);

        // Header
        if (bubbleObj.header) {
            bubble.append(renderBox(bubbleObj.header, 'header'));
        }

        // Hero
        if (bubbleObj.hero) {
            bubble.append(renderImage(bubbleObj.hero, 'hero'));
        }

        // Body
        if (bubbleObj.body) {
            bubble.append(renderBox(bubbleObj.body, 'body'));
        }

        // Footer
        if (bubbleObj.footer) {
            bubble.append(renderBox(bubbleObj.footer, 'footer'));
        }

        // Styles
        if (bubbleObj.styles) {
            if (bubbleObj.styles.header && bubbleObj.styles.header.backgroundColor) {
                bubble.find('.flex-box-header').css('background-color', bubbleObj.styles.header.backgroundColor);
            }
            if (bubbleObj.styles.hero && bubbleObj.styles.hero.backgroundColor) {
                bubble.find('.flex-image-hero').css('background-color', bubbleObj.styles.hero.backgroundColor);
            }
            if (bubbleObj.styles.body && bubbleObj.styles.body.backgroundColor) {
                bubble.find('.flex-box-body').css('background-color', bubbleObj.styles.body.backgroundColor);
            }
            if (bubbleObj.styles.footer && bubbleObj.styles.footer.backgroundColor) {
                bubble.find('.flex-box-footer').css('background-color', bubbleObj.styles.footer.backgroundColor);
            }
        }

        return bubble;
    }

    function renderBox(boxObj, blockType) {
        var box = $('<div class="flex-box flex-box-' + blockType + '"></div>');

        // Layout
        var layout = boxObj.layout || 'vertical';
        box.css('display', 'flex');
        box.css('flex-direction', layout === 'horizontal' ? 'row' : 'column');

        // Background Color
        if (boxObj.backgroundColor) box.css('background-color', boxObj.backgroundColor);

        // Padding
        if (boxObj.paddingAll) box.css('padding', boxObj.paddingAll);
        else if (!boxObj.paddingAll && !boxObj.paddingTop && !boxObj.paddingBottom && !boxObj.paddingStart && !boxObj.paddingEnd) {
            // Default padding if none specified
            if (blockType === 'body') box.css('padding', '20px');
            else if (blockType === 'header') box.css('padding', '20px');
            else if (blockType === 'footer') box.css('padding', '20px');
        } else {
            if (boxObj.paddingTop) box.css('padding-top', boxObj.paddingTop);
            if (boxObj.paddingBottom) box.css('padding-bottom', boxObj.paddingBottom);
            if (boxObj.paddingStart) box.css('padding-left', boxObj.paddingStart);
            if (boxObj.paddingEnd) box.css('padding-right', boxObj.paddingEnd);
        }

        // Spacing (Gap)
        var spacing = boxObj.spacing || 'none';
        var gapMap = { 'none': '0', 'xs': '2px', 'sm': '4px', 'md': '8px', 'lg': '12px', 'xl': '16px', 'xxl': '20px' };
        box.css('gap', gapMap[spacing] || spacing);

        // Margin
        if (boxObj.margin) {
            var marginMap = { 'none': '0', 'xs': '2px', 'sm': '4px', 'md': '8px', 'lg': '12px', 'xl': '16px', 'xxl': '20px' };
            box.css('margin-top', marginMap[boxObj.margin] || boxObj.margin);
        }

        // Flex (for child of horizontal box)
        if (boxObj.flex !== undefined) box.css('flex', boxObj.flex);

        // Width/Height
        if (boxObj.width) box.css('width', boxObj.width);
        if (boxObj.height) box.css('height', boxObj.height);

        // Justify Content
        if (boxObj.justifyContent) {
            var justifyMap = {
                'center': 'center', 'flex-start': 'flex-start', 'flex-end': 'flex-end',
                'space-between': 'space-between', 'space-around': 'space-around', 'space-evenly': 'space-evenly'
            };
            box.css('justify-content', justifyMap[boxObj.justifyContent] || 'flex-start');
        }

        // Align Items
        if (boxObj.alignItems) {
            var alignMap = { 'center': 'center', 'flex-start': 'flex-start', 'flex-end': 'flex-end' };
            box.css('align-items', alignMap[boxObj.alignItems] || 'flex-start');
        }

        // Border
        if (boxObj.borderWidth) box.css('border-width', boxObj.borderWidth);
        if (boxObj.borderColor) box.css('border-color', boxObj.borderColor);
        if (boxObj.cornerRadius) box.css('border-radius', boxObj.cornerRadius);
        if (boxObj.borderWidth) box.css('border-style', 'solid');

        // Contents
        if (boxObj.contents && Array.isArray(boxObj.contents)) {
            boxObj.contents.forEach(function (item) {
                if (item.type === 'box') {
                    box.append(renderBox(item, 'child'));
                } else if (item.type === 'text') {
                    box.append(renderText(item));
                } else if (item.type === 'image') {
                    box.append(renderImage(item, 'child'));
                } else if (item.type === 'button') {
                    box.append(renderButton(item));
                } else if (item.type === 'separator') {
                    box.append(renderSeparator(item));
                } else if (item.type === 'filler') {
                    box.append($('<div style="flex-grow: 1;"></div>'));
                } else if (item.type === 'spacer') {
                    // Spacer is usually handled by margin/padding in Flex, but simple div here
                    var sizeMap = { 'xs': '2px', 'sm': '4px', 'md': '8px', 'lg': '12px', 'xl': '16px', 'xxl': '20px' };
                    box.append($('<div style="height: ' + (sizeMap[item.size] || '8px') + ';"></div>'));
                }
            });
        }

        return box;
    }

    function renderText(textObj) {
        var p = $('<div class="flex-text"></div>');
        p.text(textObj.text || '');

        // Styles
        p.css('color', textObj.color || '#000000');

        var sizeMap = { 'xxs': '11px', 'xs': '13px', 'sm': '14px', 'md': '16px', 'lg': '19px', 'xl': '22px', 'xxl': '29px', '3xl': '35px', '4xl': '48px', '5xl': '74px' };
        p.css('font-size', sizeMap[textObj.size] || '16px');

        if (textObj.weight === 'bold') p.css('font-weight', 'bold');
        if (textObj.style === 'italic') p.css('font-style', 'italic');
        if (textObj.decoration === 'underline') p.css('text-decoration', 'underline');
        if (textObj.decoration === 'line-through') p.css('text-decoration', 'line-through');

        if (textObj.align) p.css('text-align', textObj.align);
        if (textObj.wrap) p.css('white-space', 'pre-wrap');
        else p.css('white-space', 'nowrap');

        if (textObj.flex !== undefined) p.css('flex', textObj.flex);
        if (textObj.margin) {
            var marginMap = { 'none': '0', 'xs': '2px', 'sm': '4px', 'md': '8px', 'lg': '12px', 'xl': '16px', 'xxl': '20px' };
            p.css('margin-top', marginMap[textObj.margin] || textObj.margin);
        }

        // Line Height (simplified)
        p.css('line-height', '1.5');

        return p;
    }

    function renderImage(imgObj, blockType) {
        var wrapper = $('<div class="flex-image-wrapper flex-image-' + blockType + '" style="position: relative; overflow: hidden;"></div>');
        var img = $('<img style="display: block; width: 100%; height: 100%;">');
        img.attr('src', imgObj.url);

        // Aspect Ratio
        var ratio = imgObj.aspectRatio || '1:1';
        wrapper.css('aspect-ratio', ratio.replace(':', '/'));

        // Aspect Mode
        if (imgObj.aspectMode === 'cover') {
            img.css('object-fit', 'cover');
        } else {
            img.css('object-fit', 'contain');
        }

        // Size
        if (imgObj.size) {
            var sizeMap = { 'xxs': '15%', 'xs': '20%', 'sm': '24%', 'md': '30%', 'lg': '46%', 'xl': '50%', 'xxl': '70%', '3xl': '80%', '4xl': '90%', '5xl': '100%', 'full': '100%' };
            if (sizeMap[imgObj.size]) {
                wrapper.css('width', sizeMap[imgObj.size]);
                if (imgObj.align === 'center') wrapper.css('margin', '0 auto');
                if (imgObj.align === 'end') wrapper.css('margin-left', 'auto');
            } else {
                wrapper.css('width', imgObj.size);
            }
        } else {
            wrapper.css('width', '100%');
        }

        // Background Color
        if (imgObj.backgroundColor) wrapper.css('background-color', imgObj.backgroundColor);

        // Margin
        if (imgObj.margin) {
            var marginMap = { 'none': '0', 'xs': '2px', 'sm': '4px', 'md': '8px', 'lg': '12px', 'xl': '16px', 'xxl': '20px' };
            wrapper.css('margin-top', marginMap[imgObj.margin] || imgObj.margin);
        }

        // Flex
        if (imgObj.flex !== undefined) wrapper.css('flex', imgObj.flex);

        wrapper.append(img);
        return wrapper;
    }

    function renderButton(btnObj) {
        var btn = $('<button class="flex-button"></button>');
        btn.text(btnObj.action ? (btnObj.action.label || 'Button') : 'Button');

        // Basic Styles
        btn.css({
            'display': 'block',
            'width': '100%',
            'padding': '0 16px',
            'height': btnObj.height === 'sm' ? '30px' : '40px',
            'border-radius': '4px',
            'border': 'none',
            'cursor': 'pointer',
            'font-weight': 'bold',
            'font-size': '14px',
            'line-height': btnObj.height === 'sm' ? '28px' : '38px',
            'text-align': 'center',
            'box-sizing': 'border-box'
        });

        // Style Types
        if (btnObj.style === 'primary') {
            btn.css({ 'background-color': btnObj.color || '#17c950', 'color': '#ffffff' });
        } else if (btnObj.style === 'secondary') {
            btn.css({ 'background-color': btnObj.color || '#dcdfe5', 'color': '#111111' });
        } else if (btnObj.style === 'link') {
            btn.css({ 'background-color': 'transparent', 'color': btnObj.color || '#17c950' });
        } else {
            // Default to link style if not specified
            btn.css({ 'background-color': 'transparent', 'color': btnObj.color || '#17c950' });
        }

        // Margin
        if (btnObj.margin) {
            var marginMap = { 'none': '0', 'xs': '2px', 'sm': '4px', 'md': '8px', 'lg': '12px', 'xl': '16px', 'xxl': '20px' };
            btn.css('margin-top', marginMap[btnObj.margin] || btnObj.margin);
        }

        // Flex
        if (btnObj.flex !== undefined) btn.css('flex', btnObj.flex);

        return btn;
    }

    function renderSeparator(sepObj) {
        var hr = $('<hr>');
        hr.css({
            'border': 'none',
            'border-top': '1px solid ' + (sepObj.color || '#e2e5e8'),
            'margin': 0,
            'width': '100%'
        });

        if (sepObj.margin) {
            var marginMap = { 'none': '0', 'xs': '2px', 'sm': '4px', 'md': '8px', 'lg': '12px', 'xl': '16px', 'xxl': '20px' };
            hr.css('margin-top', marginMap[sepObj.margin] || sepObj.margin);
            hr.css('margin-bottom', marginMap[sepObj.margin] || sepObj.margin);
        }

        return hr;
    }

    // Preview Button
    $('#preview_flex').on('click', function () {
        var json = window.editor ? window.editor.getValue() : $('#flex_json').val();
        updatePreview(json);
    });
});
