/**
 * LINE Pay as an option in the block checkout.
 *
 * The block registry wants a description of the method -- a label, something
 * to draw when it is selected, and what it can do. The payment itself is
 * handled after the order is placed, on the server, the same as the shortcode
 * checkout; nothing here talks to LINE.
 */
(function () {
	'use strict';

	if (!window.wc || !window.wc.wcBlocksRegistry || !window.wc.wcSettings) {
		return;
	}

	var settings = window.wc.wcSettings.getSetting('mofoline_pay_data', {});
	var decode = window.wp.htmlEntities.decodeEntities;
	var el = window.wp.element.createElement;
	var __ = window.wp.i18n.__;

	var label = decode(settings.title || '') || __('LINE Pay', 'moksa-for-line');

	// The label with the LINE Pay mark beside it. The icon is theirs, so it is
	// shown as they supply it rather than restyled.
	var Label = function () {
		var parts = [el('span', { key: 'text' }, label)];

		if (settings.icon) {
			parts.push(el('img', {
				key: 'icon',
				src: settings.icon,
				alt: '',
				style: { marginLeft: '8px', height: '20px', verticalAlign: 'middle' }
			}));
		}

		return el('span', { style: { display: 'inline-flex', alignItems: 'center' } }, parts);
	};

	var Content = function () {
		return decode(settings.description || '');
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod({
		name: 'mofoline_pay',
		label: el(Label, null),
		content: el(Content, null),
		edit: el(Content, null),
		ariaLabel: label,
		canMakePayment: function () {
			return true;
		},
		supports: {
			features: settings.supports || ['products']
		}
	});
}());
