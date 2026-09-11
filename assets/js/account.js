/**
 * The LINE panel on the WooCommerce account page.
 *
 * Its own file rather than the admin bundle. The account page used to enqueue
 * assets/js/admin.js -- 53KB of inbox, rich menu and Flex editor bindings -- to
 * get one unlink button, and when that file gained a dependency on the
 * confirmation dialog the button silently stopped working for every customer,
 * because the front end never loaded it.
 */
(function ($) {
	'use strict';

	var settings = window.moksaLineAccount || {};

	function t(key, fallback) {
		var value = (settings.strings || {})[key];

		return undefined === value ? fallback : value;
	}

	function ask(message, options) {
		// The shared dialog when it is present, the browser's when it is not,
		// so a missing asset degrades to an ugly prompt rather than a button
		// that does nothing.
		if (window.moksaConfirm) {
			return window.moksaConfirm(message, options);
		}

		return Promise.resolve(window.confirm(message));
	}

	function say(message, kind) {
		if (window.moksaNotify) {
			window.moksaNotify(message, kind);
			return;
		}

		window.alert(message);
	}

	$(function () {
		$(document).on('click', '[data-moksa-line-unlink]', function (event) {
			event.preventDefault();

			var $button = $(this);

			// Both labels, not just the confirm one: the dialog falls back to the
			// admin string bag for anything it is not given, and that object
			// does not exist on the front end -- so Cancel stayed in English
			// next to two translated buttons.
			ask(t('confirmUnlink', 'Unlink your LINE account?'), {
				danger: true,
				confirmLabel: t('unlinkAction', 'Unlink'),
				cancelLabel: t('confirmNo', 'Cancel')
			}).then(function (confirmed) {
				if (!confirmed) {
					return;
				}

				$button.prop('disabled', true);

				$.post(settings.ajaxUrl, {
					action: 'moksa_line_unlink',
					nonce: $button.data('nonce'),
					user_id: $button.data('moksa-line-unlink')
				}).then(function (response) {
					if (response && response.success) {
						window.location.reload();
						return;
					}

					$button.prop('disabled', false);
					say((response && response.data && response.data.message) || t('failed', 'That did not work.'));
				}, function () {
					$button.prop('disabled', false);
					say(t('failed', 'That did not work.'));
				});
			});
		});

		// Replacing the card is the way out of a shared screenshot: the old
		// code stops working the moment a new one is issued, so it is worth
		// asking first.
		$(document).on('click', '[data-moksa-line-reissue]', function (event) {
			event.preventDefault();

			var $button = $(this);

			ask(t('confirmReissue', 'Replace your membership card? The code you have now will stop working.'), {
				danger: true,
				confirmLabel: t('reissueAction', 'Replace it'),
				cancelLabel: t('confirmNo', 'Cancel')
			}).then(function (confirmed) {
				if (!confirmed) {
					return;
				}

				$button.prop('disabled', true);

				$.post(settings.ajaxUrl, {
					action: 'moksa_line_member_reissue',
					nonce: $button.data('nonce')
				}).then(function (response) {
					if (response && response.success) {
						// The QR is rendered server-side, so the page has to
						// come back for it -- redrawing it here would mean a
						// second encoder in JavaScript.
						window.location.reload();
						return;
					}

					$button.prop('disabled', false);
					say((response && response.data && response.data.message) || t('failed', 'That did not work.'));
				}, function () {
					$button.prop('disabled', false);
					say(t('failed', 'That did not work.'));
				});
			});
		});

		// The membership code is the one thing on this page a shop assistant
		// reads off the screen, so make it easy to hand over.
		$(document).on('click', '[data-moksa-member-code]', function () {
			var code = String($(this).data('moksa-member-code') || '');

			if (!code || !navigator.clipboard || !navigator.clipboard.writeText) {
				return;
			}

			var $el = $(this);

			navigator.clipboard.writeText(code).then(function () {
				$el.addClass('is-copied');
				window.setTimeout(function () { $el.removeClass('is-copied'); }, 1600);
			});
		});
	});
}(jQuery));
