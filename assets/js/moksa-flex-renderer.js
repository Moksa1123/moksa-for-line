/**
 * Moksa Advanced Flex Message Renderer
 * A robust, standalone renderer for LINE Flex Messages.
 * Mimics LINE's official rendering logic using CSS Flexbox.
 */

(function ($) {
    'use strict';


    var MoksaFlexRenderer = {

        render: function (flexObj, container) {
            container.empty();

            // Base Container Styles (Reset)
            container.css({
                'background-color': '#849ebf', // Default LINE Chat BG
                'padding': '20px',
                'min-height': '100%',
                'display': 'flex',
                'flex-direction': 'column',
                'align-items': 'flex-start',
                'gap': '10px',
                'overflow-y': 'auto',
                'overflow-x': 'hidden',
                'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
                'box-sizing': 'border-box'
            });

            try {
                if (!flexObj) return;

                // Handle String (Dynamic Parameter)
                if (typeof flexObj === 'string') {
                    this.renderPlaceholder(flexObj, container);
                    return;
                }

                // Normalize Input
                var root = flexObj;
                if (root.type === 'flex') {
                    root = root.contents;
                }

                if (root.type === 'bubble') {
                    container.append(this.createBubble(root));
                } else if (root.type === 'carousel') {
                    container.append(this.createCarousel(root));
                } else {
                    // Fallback: Try to render as bubble if it looks like one
                    container.append(this.createBubble(Object.assign({ type: 'bubble' }, root)));
                }

            } catch (e) {
                console.error('Flex Render Error:', e);
                container.html(this.createError(e.message));
            }
        },

        // --- Components ---

        createBubble: function (bubble) {
            var el = $('<div>').addClass('flex-bubble');
            el.css({
                'display': 'flex',
                'flex-direction': 'column',
                'overflow': 'hidden',
                'background-color': '#ffffff',
                'border-radius': '10px',
                'box-shadow': '0 2px 6px rgba(0,0,0,0.1)',
                'position': 'relative'
            });

            // Size
            var width = '300px'; // Default (Mega)
            if (bubble.size === 'giga') width = '100%';
            else if (bubble.size === 'kilo') width = '260px';
            else if (bubble.size === 'micro') width = '160px';
            else if (bubble.size === 'nano') width = '120px';
            el.css('width', width);
            el.css('max-width', '100%');

            // Direction
            el.css('direction', bubble.direction || 'ltr');

            // Header
            if (bubble.header) el.append(this.createBox(bubble.header, 'header', bubble.styles));
            // Hero
            if (bubble.hero) el.append(this.createImage(bubble.hero, 'hero', bubble.styles));
            // Body
            if (bubble.body) el.append(this.createBox(bubble.body, 'body', bubble.styles));
            // Footer
            if (bubble.footer) el.append(this.createBox(bubble.footer, 'footer', bubble.styles));

            // Block Styles (Backgrounds)
            if (bubble.styles && bubble.styles.body && bubble.styles.body.backgroundColor) {
                // Applied in createBox, but if body is missing?
            }

            return el;
        },

        createCarousel: function (carousel) {
            var el = $('<div>').addClass('flex-carousel');
            el.css({
                'display': 'flex',
                'overflow-x': 'auto',
                'gap': '10px',
                'padding-bottom': '10px',
                'width': '100%',
                'scroll-snap-type': 'x mandatory'
            });

            var self = this;
            if (carousel.contents && Array.isArray(carousel.contents)) {
                carousel.contents.forEach(function (bubble) {
                    var wrapper = $('<div>').css({
                        'flex': '0 0 auto',
                        'scroll-snap-align': 'start'
                    });
                    wrapper.append(self.createBubble(bubble));
                    el.append(wrapper);
                });
            }
            return el;
        },

        createBox: function (box, blockType, globalStyles) {
            var el = $('<div>').addClass('flex-box');

            // Layout
            var isHorizontal = box.layout === 'horizontal' || box.layout === 'baseline';
            el.css({
                'display': 'flex',
                'flex-direction': isHorizontal ? 'row' : 'column'
            });

            if (box.layout === 'baseline') {
                el.css('align-items', 'baseline');
            }

            // Background
            if (box.backgroundColor) el.css('background-color', box.backgroundColor);
            else if (globalStyles && globalStyles[blockType] && globalStyles[blockType].backgroundColor) {
                el.css('background-color', globalStyles[blockType].backgroundColor);
            }

            // Padding
            this.applyPadding(el, box, blockType);

            // Spacing (Gap)
            var gap = this.getSize(box.spacing || 'none');
            el.css('gap', gap);

            // Margin
            if (box.margin) el.css('margin-top', this.getSize(box.margin));

            // Flex
            if (box.flex !== undefined) el.css('flex-grow', box.flex);

            // Width/Height
            if (box.width) el.css('width', box.width);
            if (box.height) el.css('height', box.height);
            if (box.maxWidth) el.css('max-width', box.maxWidth);
            if (box.maxHeight) el.css('max-height', box.maxHeight);

            // Justify & Align
            if (box.justifyContent) el.css('justify-content', box.justifyContent);
            if (box.alignItems) el.css('align-items', box.alignItems);

            // Border
            if (box.borderWidth) {
                el.css('border-width', box.borderWidth);
                el.css('border-style', 'solid');
                el.css('border-color', box.borderColor || '#000000');
            }
            if (box.cornerRadius) el.css('border-radius', box.cornerRadius);

            // Contents
            if (box.contents && Array.isArray(box.contents)) {
                var self = this;
                box.contents.forEach(function (item) {
                    var child = self.createComponent(item);
                    if (child) el.append(child);
                });
            }

            // Action
            if (box.action) {
                el.css('cursor', 'pointer');
                // In a real app, bind click. Here, maybe just hover effect.
                el.hover(function () { $(this).css('opacity', 0.8); }, function () { $(this).css('opacity', 1); });
            }

            return el;
        },

        createComponent: function (component) {
            if (typeof component === 'string') return this.createPlaceholder(component);
            if (!component.type) return null;

            switch (component.type) {
                case 'box': return this.createBox(component);
                case 'text': return this.createText(component);
                case 'image': return this.createImage(component);
                case 'button': return this.createButton(component);
                case 'separator': return this.createSeparator(component);
                case 'spacer': return this.createSpacer(component);
                case 'filler': return $('<div>').css('flex-grow', 1);
                case 'icon': return this.createIcon(component);
                default: return null;
            }
        },

        createText: function (text) {
            var el = $('<div>').addClass('flex-text').text(text.text || '');

            // Style
            el.css('color', text.color || '#000000');
            el.css('font-size', this.getFontSize(text.size));
            if (text.weight === 'bold') el.css('font-weight', '700');
            if (text.style === 'italic') el.css('font-style', 'italic');
            if (text.decoration === 'underline') el.css('text-decoration', 'underline');
            if (text.decoration === 'line-through') el.css('text-decoration', 'line-through');

            // Layout
            el.css('text-align', text.align || 'left');
            if (text.wrap) {
                el.css('white-space', 'pre-wrap');
                el.css('word-break', 'break-word');
            } else {
                el.css('white-space', 'nowrap');
                el.css('overflow', 'hidden');
                el.css('text-overflow', 'ellipsis');
            }

            // Flex
            if (text.flex !== undefined) el.css('flex-grow', text.flex);
            else el.css('flex-grow', 0); // Text defaults to 0 unlike box? Actually depends.

            // Margin
            if (text.margin) el.css('margin-top', this.getSize(text.margin));

            return el;
        },

        createImage: function (img, blockType, globalStyles) {
            var wrapper = $('<div>').addClass('flex-image-wrapper');
            var el = $('<img>').attr('src', img.url);

            wrapper.css({
                'position': 'relative',
                'overflow': 'hidden',
                'display': 'flex' // To align img
            });

            // Size
            var size = img.size || 'md';
            if (size.endsWith('%') || size.endsWith('px')) {
                wrapper.css('width', size);
            } else {
                var sizeMap = { 'xxs': '15%', 'xs': '20%', 'sm': '24%', 'md': '30%', 'lg': '46%', 'xl': '50%', 'xxl': '70%', '3xl': '80%', '4xl': '90%', '5xl': '100%', 'full': '100%' };
                wrapper.css('width', sizeMap[size] || '100%');
            }

            // Aspect Ratio
            var ratio = img.aspectRatio || '1:1';
            wrapper.css('aspect-ratio', ratio.replace(':', '/'));

            // Mode
            if (img.aspectMode === 'cover') {
                el.css({ 'width': '100%', 'height': '100%', 'object-fit': 'cover' });
            } else {
                el.css({ 'width': '100%', 'height': '100%', 'object-fit': 'contain' });
            }

            // Background
            if (img.backgroundColor) wrapper.css('background-color', img.backgroundColor);
            else if (blockType === 'hero' && globalStyles && globalStyles.hero && globalStyles.hero.backgroundColor) {
                wrapper.css('background-color', globalStyles.hero.backgroundColor);
            }

            // Align
            if (img.align === 'center') wrapper.css('margin', '0 auto');
            else if (img.align === 'end') wrapper.css('margin-left', 'auto');

            // Margin
            if (img.margin) wrapper.css('margin-top', this.getSize(img.margin));

            wrapper.append(el);
            return wrapper;
        },

        createButton: function (btn) {
            var el = $('<button>').addClass('flex-button').text(btn.action ? (btn.action.label || 'Button') : 'Button');

            // Base Style
            el.css({
                'display': 'block',
                'width': '100%',
                'border': 'none',
                'cursor': 'pointer',
                'font-family': 'inherit',
                'text-align': 'center',
                'box-sizing': 'border-box',
                'outline': 'none'
            });

            // Height
            var height = btn.height === 'sm' ? '30px' : '40px';
            el.css('height', height);
            el.css('line-height', height); // Center text vertically

            // Style & Color
            var style = btn.style || 'link';
            var color = btn.color || '#17c950'; // Default LINE Green

            if (style === 'primary') {
                el.css({ 'background-color': color, 'color': '#ffffff' });
            } else if (style === 'secondary') {
                el.css({ 'background-color': color || '#dcdfe5', 'color': '#111111' });
            } else { // link
                el.css({ 'background-color': 'transparent', 'color': color });
            }

            // Margin
            if (btn.margin) el.css('margin-top', this.getSize(btn.margin));

            // Flex
            if (btn.flex !== undefined) el.css('flex-grow', btn.flex);

            return el;
        },

        createSeparator: function (sep) {
            var el = $('<div>').addClass('flex-separator');
            el.css({
                'width': '100%',
                'height': '1px',
                'background-color': sep.color || '#E2E5E8'
            });
            if (sep.margin) {
                var m = this.getSize(sep.margin);
                el.css({ 'margin-top': m, 'margin-bottom': m });
            }
            return el;
        },

        createSpacer: function (spacer) {
            var el = $('<div>').addClass('flex-spacer');
            var size = this.getSize(spacer.size || 'md');
            el.css('height', size);
            return el;
        },

        createIcon: function (icon) {
            var el = $('<img>').attr('src', icon.url);
            var size = this.getFontSize(icon.size || 'md'); // Icons use font size scale usually
            el.css({
                'width': size,
                'height': size,
                'object-fit': 'contain'
            });
            if (icon.margin) el.css('margin-left', this.getSize(icon.margin)); // Icons usually inline
            return el;
        },

        // --- Helpers ---

        applyPadding: function (el, box, blockType) {
            if (box.paddingAll) el.css('padding', box.paddingAll);
            else {
                // Default padding for blocks
                if (blockType && !box.paddingTop && !box.paddingBottom && !box.paddingStart && !box.paddingEnd) {
                    el.css('padding', '20px');
                } else {
                    if (box.paddingTop) el.css('padding-top', box.paddingTop);
                    if (box.paddingBottom) el.css('padding-bottom', box.paddingBottom);
                    if (box.paddingStart) el.css('padding-left', box.paddingStart);
                    if (box.paddingEnd) el.css('padding-right', box.paddingEnd);
                }
            }
        },

        getSize: function (size) {
            var map = { 'none': '0px', 'xs': '2px', 'sm': '4px', 'md': '8px', 'lg': '12px', 'xl': '16px', 'xxl': '20px' };
            return map[size] || size || '0px';
        },

        getFontSize: function (size) {
            var map = { 'xxs': '11px', 'xs': '13px', 'sm': '14px', 'md': '16px', 'lg': '19px', 'xl': '22px', 'xxl': '29px', '3xl': '35px', '4xl': '48px', '5xl': '74px' };
            return map[size] || '16px';
        },

        renderPlaceholder: function (text, container) {
            var el = $('<div>').addClass('moksa-dynamic-placeholder').text(text);
            el.css({
                'border': '1px dashed #94a3b8',
                'padding': '8px',
                'background': '#f1f5f9',
                'color': '#64748b',
                'text-align': 'center',
                'font-size': '12px',
                'margin': '5px 0',
                'border-radius': '4px',
                'width': '100%'
            });
            if (container) container.append(el);
            return el;
        },

        createPlaceholder: function (text) {
            return this.renderPlaceholder(text);
        },

        createError: function (msg) {
            return '<div style="background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 12px; border-radius: 8px; font-size: 13px; line-height: 1.5;"><strong>Render Error:</strong> ' + this.escapeHtml(msg) + '</div>';
        },

        escapeHtml: function (text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }
    };

    // Expose
    window.MoksaFlexRenderer = MoksaFlexRenderer;
    // Also expose as simpler name just in case
    window.MoksaFlex = MoksaFlexRenderer;

})(jQuery);
