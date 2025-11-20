/**
 * Moksa Flex Message Renderer
 * Shared logic for rendering LINE Flex Messages in the admin preview.
 */

(function (window) {
    'use strict';

    console.log('MoksaFlexRenderer loaded');

    var MoksaFlexRenderer = {
        /**
         * Render a Flex Message JSON object into a container
         * @param {Object} flexObj - The parsed JSON object
         * @param {jQuery} container - The jQuery container element
         */
        render: function (flexObj, container) {
            container.empty();

            // Container Style for Preview Area (Reset & Base Styles)
            container.css({
                'background-color': '#849ebf', // LINE Chat Background Color
                'padding': '20px',
                'min-height': '300px',
                'display': 'flex',
                'flex-direction': 'column',
                'align-items': 'flex-start', // Align bubbles to left
                'gap': '10px',
                'overflow-x': 'hidden', // Prevent container scroll, handle carousel inside
                'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif'
            });

            try {
                if (!flexObj) return;

                if (typeof flexObj === 'string') {
                    this.renderPlaceholder(flexObj, container);
                    return;
                }

                if (flexObj.type === 'flex') {
                    this.renderContainer(flexObj, container);
                } else if (flexObj.type === 'bubble' || flexObj.type === 'carousel') {
                    this.renderContainer(flexObj, container);
                } else {
                    // Handle raw bubble object without type: flex wrapper
                    this.renderContainer(Object.assign({ type: 'bubble' }, flexObj), container);
                }
            } catch (e) {
                console.error('Flex Render Error:', e);
                container.html('<div class="moksa-error-message" style="background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 12px; border-radius: 8px; font-size: 13px; line-height: 1.5;"><strong>Render Error:</strong> ' + this.escapeHtml(e.message) + '</div>');
            }
        },

        renderContainer: function (obj, container) {
            if (!obj) return;

            if (typeof obj === 'string') {
                container.append(this.createPlaceholder(obj));
                return;
            }

            if (obj.type === 'bubble') {
                container.append(this.renderBubble(obj));
            } else if (obj.type === 'carousel') {
                var carousel = $('<div class="flex-carousel" style="display: flex; overflow-x: auto; gap: 10px; padding-bottom: 10px; width: 100%; scroll-snap-type: x mandatory;"></div>');

                // Scrollbar styling for webkit
                var style = $('<style>.flex-carousel::-webkit-scrollbar { height: 6px; } .flex-carousel::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.2); border-radius: 3px; }</style>');
                container.append(style);

                if (obj.contents && Array.isArray(obj.contents)) {
                    var self = this;
                    obj.contents.forEach(function (bubbleObj) {
                        var bubbleWrapper = $('<div style="flex: 0 0 auto; width: 300px; scroll-snap-align: start;"></div>');
                        if (typeof bubbleObj === 'string') {
                            bubbleWrapper.append(self.createPlaceholder(bubbleObj));
                        } else {
                            bubbleWrapper.append(self.renderBubble(bubbleObj));
                        }
                        carousel.append(bubbleWrapper);
                    });
                }
                container.append(carousel);
            } else if (obj.contents) {
                this.renderContainer(obj.contents, container);
            }
        },

        renderBubble: function (bubbleObj) {
            if (typeof bubbleObj === 'string') {
                return this.createPlaceholder(bubbleObj);
            }

            var bubble = $('<div class="flex-bubble" style="background: #fff; border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; position: relative; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"></div>');

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
                bubble.append(this.renderBox(bubbleObj.header, 'header'));
            }

            // Hero
            if (bubbleObj.hero) {
                bubble.append(this.renderImage(bubbleObj.hero, 'hero'));
            }

            // Body
            if (bubbleObj.body) {
                bubble.append(this.renderBox(bubbleObj.body, 'body'));
            }

            // Footer
            if (bubbleObj.footer) {
                bubble.append(this.renderBox(bubbleObj.footer, 'footer'));
            }

            // Styles (Block Styles)
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
        },

        renderBox: function (boxObj, blockType) {
            if (typeof boxObj === 'string') {
                return this.createPlaceholder(boxObj);
            }

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

            // Flex
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
            if (boxObj.borderWidth) {
                box.css('border-width', boxObj.borderWidth);
                box.css('border-style', 'solid');
            }
            if (boxObj.borderColor) box.css('border-color', boxObj.borderColor);
            if (boxObj.cornerRadius) box.css('border-radius', boxObj.cornerRadius);

            // Contents
            if (boxObj.contents && Array.isArray(boxObj.contents)) {
                var self = this;
                boxObj.contents.forEach(function (item) {
                    if (typeof item === 'string') {
                        box.append(self.createPlaceholder(item));
                    } else if (item.type === 'box') {
                        box.append(self.renderBox(item, 'child'));
                    } else if (item.type === 'text') {
                        box.append(self.renderText(item));
                    } else if (item.type === 'image') {
                        box.append(self.renderImage(item, 'child'));
                    } else if (item.type === 'button') {
                        box.append(self.renderButton(item));
                    } else if (item.type === 'separator') {
                        box.append(self.renderSeparator(item));
                    } else if (item.type === 'filler') {
                        box.append($('<div style="flex-grow: 1;"></div>'));
                    } else if (item.type === 'spacer') {
                        var sizeMap = { 'xs': '2px', 'sm': '4px', 'md': '8px', 'lg': '12px', 'xl': '16px', 'xxl': '20px' };
                        box.append($('<div style="height: ' + (sizeMap[item.size] || '8px') + ';"></div>'));
                    }
                });
            }

            return box;
        },

        renderText: function (textObj) {
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

            p.css('line-height', '1.5');
            return p;
        },

        renderImage: function (imgObj, blockType) {
            var wrapper = $('<div class="flex-image-wrapper flex-image-' + blockType + '" style="position: relative; overflow: hidden;"></div>');
            var img = $('<img style="display: block; width: 100%; height: 100%;">');
            img.attr('src', imgObj.url);

            var ratio = imgObj.aspectRatio || '1:1';
            wrapper.css('aspect-ratio', ratio.replace(':', '/'));

            if (imgObj.aspectMode === 'cover') {
                img.css('object-fit', 'cover');
            } else {
                img.css('object-fit', 'contain');
            }

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

            if (imgObj.backgroundColor) wrapper.css('background-color', imgObj.backgroundColor);

            if (imgObj.margin) {
                var marginMap = { 'none': '0', 'xs': '2px', 'sm': '4px', 'md': '8px', 'lg': '12px', 'xl': '16px', 'xxl': '20px' };
                wrapper.css('margin-top', marginMap[imgObj.margin] || imgObj.margin);
            }

            if (imgObj.flex !== undefined) wrapper.css('flex', imgObj.flex);

            wrapper.append(img);
            return wrapper;
        },

        renderButton: function (btnObj) {
            var btn = $('<button class="flex-button"></button>');
            btn.text(btnObj.action ? (btnObj.action.label || 'Button') : 'Button');

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

            if (btnObj.style === 'primary') {
                btn.css({ 'background-color': btnObj.color || '#17c950', 'color': '#ffffff' });
            } else if (btnObj.style === 'secondary') {
                btn.css({ 'background-color': btnObj.color || '#dcdfe5', 'color': '#111111' });
            } else if (btnObj.style === 'link') {
                btn.css({ 'background-color': 'transparent', 'color': btnObj.color || '#17c950' });
            } else {
                btn.css({ 'background-color': 'transparent', 'color': btnObj.color || '#17c950' });
            }

            if (btnObj.margin) {
                var marginMap = { 'none': '0', 'xs': '2px', 'sm': '4px', 'md': '8px', 'lg': '12px', 'xl': '16px', 'xxl': '20px' };
                btn.css('margin-top', marginMap[btnObj.margin] || btnObj.margin);
            }

            if (btnObj.flex !== undefined) btn.css('flex', btnObj.flex);

            return btn;
        },

        renderSeparator: function (sepObj) {
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
        },

        createPlaceholder: function (text) {
            return $('<div class="moksa-dynamic-placeholder" style="border: 1px dashed #94a3b8; padding: 8px; background: #f1f5f9; color: #64748b; text-align: center; font-size: 12px; margin: 5px 0; border-radius: 4px;">' + this.escapeHtml(text) + '</div>');
        },

        renderPlaceholder: function (text, container) {
            container.append(this.createPlaceholder(text));
        },

        escapeHtml: function (text) {
            if (!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    };

    // Expose to global scope
    window.MoksaFlexRenderer = MoksaFlexRenderer;

})(window);
