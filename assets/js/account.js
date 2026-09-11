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

		/**
		 * Select the code, for when copying it is not on offer.
		 *
		 * The clipboard API needs a secure context and the browser's blessing,
		 * and it refuses often enough that "nothing happened" cannot be the
		 * answer. A selected code can be long-pressed and copied by hand.
		 */
		function selectText(el) {
			try {
				var range = document.createRange();
				var selection = window.getSelection();

				range.selectNodeContents(el);
				selection.removeAllRanges();
				selection.addRange(range);
			} catch (error) {
				// Nothing to do: the code is on the screen either way.
			}
		}

		// The membership code is the one thing on this page a shop assistant
		// reads off the screen, so make it easy to hand over.
		$(document).on('click', '[data-moksa-member-code]', function () {
			var code = String($(this).data('moksa-member-code') || '');
			var $el = $(this);
			var element = this;

			if (!code) {
				return;
			}

			function confirmCopied() {
				// A green border says nothing to somebody who cannot see it, and
				// not much to anyone who can. Say it, then put the code back.
				var original = $el.text();

				$el.addClass('is-copied').text(t('copied', 'Copied'));

				window.setTimeout(function () {
					$el.removeClass('is-copied').text(original);
				}, 1600);
			}

			if (!navigator.clipboard || !navigator.clipboard.writeText) {
				selectText(element);
				return;
			}

			navigator.clipboard.writeText(code).then(confirmCopied, function () {
				// Refused -- an insecure context, or a browser that wants a
				// permission this click did not earn. Hand it over the other way
				// rather than leaving a button that does nothing.
				selectText(element);
			});
		});

		/**
		 * Show the code full screen.
		 *
		 * The card sits in a page with the shop's header, footer and whatever
		 * else around it, at whatever size the panel leaves for it. A counter
		 * scanner wants it large, with white carrying on past its edges -- so
		 * that is what this draws, over everything else.
		 */
		$(document).on('click', '[data-moksa-qr-zoom]', function () {
			var svg = this.querySelector('svg');

			// No <dialog> support means no overlay. The code on the page is
			// still a working code, so there is nothing to apologise for.
			if (!svg || 'function' !== typeof HTMLDialogElement) {
				return;
			}

			if ('function' !== typeof document.createElement('dialog').showModal) {
				return;
			}

			var $code = $('[data-moksa-member-code]');
			var dialog = document.createElement('dialog');

			dialog.className = 'moksa-account__zoom';
			dialog.appendChild(svg.cloneNode(true));

			if ($code.length) {
				var line = document.createElement('p');

				line.className = 'moksa-account__zoom-code';
				line.textContent = $code.text().trim();
				dialog.appendChild(line);
			}

			var close = document.createElement('button');

			close.type = 'button';
			close.className = 'moksa-account__zoom-close';
			close.textContent = t('close', 'Close');
			dialog.appendChild(close);

			document.body.appendChild(dialog);
			dialog.showModal();

			// A phone that dims while its owner is queueing is the ordinary way
			// this fails: the cashier scans, nothing happens, and the customer
			// wakes the screen and holds it up again. Ask to stay awake for as
			// long as the code is showing. Not supported everywhere, refused
			// when the battery is low, and neither is a problem worth saying
			// anything about.
			var wakeLock = null;

			if (navigator.wakeLock && navigator.wakeLock.request) {
				navigator.wakeLock.request('screen').then(function (lock) {
					wakeLock = lock;
				}, function () {});
			}

			$(close).on('click', function () { dialog.close(); });

			// Tapping the white around the code closes it too, which is what
			// anybody who has just shown a code to a cashier tries first.
			$(dialog).on('click', function (event) {
				if (event.target === dialog) {
					dialog.close();
				}
			});

			// Escape closes it without this, but only removing it on close
			// keeps one dialog in the page rather than one per tap.
			$(dialog).on('close', function () {
				if (wakeLock) {
					wakeLock.release().catch(function () {});
					wakeLock = null;
				}

				dialog.remove();
			});
		});
	});
}(jQuery));
