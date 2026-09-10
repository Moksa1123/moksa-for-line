/**
 * In-page confirmation, in place of window.confirm.
 *
 * The browser dialog cannot be styled, prefixes every question with the site's
 * hostname, and on some platforms puts the destructive answer under the
 * cursor's resting position. It also blocks the whole tab: while one is open
 * nothing on the page updates, which matters here because several of these
 * questions are asked about something the page is still loading.
 *
 * Uses <dialog>, so focus trapping, the backdrop, Escape, and returning focus
 * to the element that opened it are the browser's job rather than ours.
 */
(function ($) {
	'use strict';

	function strings() {
		return (window.moksaLine && window.moksaLine.strings) || {};
	}

	function t(key, fallback) {
		var value = strings()[key];

		return undefined === value ? fallback : value;
	}

	var $dialog = null;

	function build() {
		if ($dialog) {
			return $dialog;
		}

		// Deliberately not a <form method="dialog">. Submitting one closes the
		// dialog, but the close event that would carry the answer back did not
		// fire in testing, leaving the promise pending and the action silently
		// dropped. Plain buttons and an explicit close are answerable for
		// themselves.
		$dialog = $(
			'<dialog class="moksa-confirm">' +
				'<div class="moksa-confirm__form">' +
					'<p class="moksa-confirm__message"></p>' +
					'<div class="moksa-confirm__actions">' +
						'<button type="button" class="button" data-moksa-confirm-cancel></button>' +
						'<button type="button" class="button" data-moksa-confirm-ok></button>' +
					'</div>' +
				'</div>' +
			'</dialog>'
		);

		$('body').append($dialog);

		return $dialog;
	}

	/**
	 * Ask a yes/no question.
	 *
	 * @param {string} message  What is being confirmed.
	 * @param {Object} [options] danger: style the confirm button as destructive.
	 *                           confirmLabel / cancelLabel: override the buttons.
	 * @return {Promise<boolean>} True only when the person confirmed.
	 */
	window.moksaConfirm = function (message, options) {
		var settings = options || {};
		var $el = build();
		var el = $el[0];

		// No <dialog> support: ask the browser rather than silently doing
		// nothing, which would be worse than an unstyled prompt.
		if (!el || typeof el.showModal !== 'function') {
			return Promise.resolve(window.confirm(message));
		}

		$el.find('.moksa-confirm__message').text(message);
		$el.find('[data-moksa-confirm-ok]')
			.text(settings.confirmLabel || t('confirmYes', 'Yes, do it'))
			.toggleClass('button-primary', !settings.danger)
			.toggleClass('moksa-button-danger', !!settings.danger);
		$el.find('[data-moksa-confirm-cancel]').text(settings.cancelLabel || t('confirmNo', 'Cancel'));

		return new Promise(function (resolve) {
			var settled = false;

			function finish(answer) {
				if (settled) {
					return;
				}

				settled = true;
				$el.off('.moksaConfirm');
				el.removeEventListener('cancel', onCancel);

				if (el.open) {
					el.close();
				}

				resolve(answer);
			}

			function onCancel(event) {
				// Escape. Handled here so it resolves like the Cancel button
				// rather than leaving the promise hanging.
				event.preventDefault();
				finish(false);
			}

			$el.on('click.moksaConfirm', '[data-moksa-confirm-ok]', function () { finish(true); });
			$el.on('click.moksaConfirm', '[data-moksa-confirm-cancel]', function () { finish(false); });

			// A click that lands on the dialog itself is a click on the
			// backdrop, since the content sits in a child element.
			$el.on('click.moksaConfirm', function (event) {
				if (event.target === el) {
					finish(false);
				}
			});

			el.addEventListener('cancel', onCancel);
			el.showModal();

			// Cancel takes the focus, so Enter and a stray double-click land on
			// the harmless answer rather than the one that cannot be undone.
			$el.find('[data-moksa-confirm-cancel]').trigger('focus');
		});
	};

	/**
	 * Say something without blocking the page.
	 *
	 * The replacement for window.alert, which stops every timer and every
	 * in-flight response in the tab until it is dismissed, and which several of
	 * these call sites were firing from inside an ajax handler.
	 *
	 * @param {string} message What to say.
	 * @param {string} [kind]  'bad' (default) or 'ok'.
	 */
	window.moksaNotify = function (message, kind) {
		var $host = $('.moksa-line-wrap').first();

		if (!$host.length) {
			$host = $('body');
		}

		var $box = $host.find('> .moksa-toasts').first();

		if (!$box.length) {
			$box = $('<div class="moksa-toasts" aria-live="polite"></div>');

			var $heading = $host.children('h1').first();

			// Under the page title, where a WordPress admin notice would go --
			// prepending put it above the heading, which reads as part of the
			// chrome rather than as a response to what was just done.
			if ($heading.length) {
				$box.insertAfter($heading);
			} else {
				$host.prepend($box);
			}
		}

		var $toast = $('<div class="moksa-toast"></div>')
			.addClass('bad' === (kind || 'bad') ? 'moksa-toast--bad' : 'moksa-toast--ok')
			.text(message)
			.append(
				$('<button type="button" class="moksa-toast__close" aria-label="' + t('dismiss', 'Dismiss') + '">&times;</button>')
					.on('click', function () { $toast.remove(); })
			);

		$box.append($toast);

		// Good news goes away on its own; a failure stays until it is read.
		if ('ok' === kind) {
			window.setTimeout(function () { $toast.fadeOut(200, function () { $toast.remove(); }); }, 4000);
		}
	};
}(jQuery));
