/**
 * Admin behaviour for the LINE screens.
 *
 * Every request goes through post(), which is the only place the nonce and
 * the error shape are handled, so a failure always reaches the operator as a
 * message rather than a silent no-op.
 */
(function ($) {
	'use strict';

	var settings = window.moksaLine || {};
	var strings = settings.strings || {};

	/**
	 * Send an admin-ajax request and normalise the response.
	 *
	 * @param {string} action Action name without the moksa_line_ prefix.
	 * @param {Object} data   Payload.
	 * @return {Promise}
	 */
	function post(action, data) {
		return $.post(
			settings.ajaxUrl,
			$.extend({ action: 'moksa_line_' + action, nonce: settings.nonce }, data || {})
		).then(function (response) {
			if (!response || !response.success) {
				var message = (response && response.data && response.data.message) || strings.failed;
				var problems = (response && response.data && response.data.problems) || [];

				return $.Deferred().reject({ message: message, problems: problems }).promise();
			}

			return response.data || {};
		}, function () {
			return $.Deferred().reject({ message: strings.failed, problems: [] }).promise();
		});
	}

	/**
	 * Show a result next to the form that produced it.
	 *
	 * @param {jQuery} $scope Container holding a .moksa-feedback element.
	 * @param {string} message Message text.
	 * @param {string} kind    ok, bad or busy.
	 * @param {Array}  details Extra lines.
	 */
	function feedback($scope, message, kind, details) {
		var $box = $scope.find('[data-moksa-feedback]').first();

		if (!$box.length) {
			$box = $('.moksa-feedback').first();
		}

		var html = '<p class="moksa-feedback__message moksa-feedback__message--' + (kind || 'ok') + '">'
			+ $('<div>').text(message).html() + '</p>';

		if (details && details.length) {
			html += '<ul class="moksa-feedback__list">';
			details.forEach(function (line) {
				html += '<li>' + $('<div>').text(line).html() + '</li>';
			});
			html += '</ul>';
		}

		$box.html(html);
	}

	/**
	 * Serialise a form into a plain object.
	 *
	 * @param {jQuery} $form Form element.
	 * @return {Object}
	 */
	function formData($form) {
		var data = {};

		$form.serializeArray().forEach(function (field) {
			data[field.name] = field.value;
		});

		$form.find('input[type=checkbox]').each(function () {
			if (!this.name) {
				return;
			}

			data[this.name] = this.checked ? '1' : '';
		});

		return data;
	}

	// --- Auto reply rules ---------------------------------------------------

	function bindRules() {
		var $form = $('[data-moksa-rule-form]');

		if (!$form.length) {
			return;
		}

		function showReplyField() {
			var type = $form.find('[data-moksa-rule-type]').val();

			$form.find('[data-moksa-reply-field]').each(function () {
				var $field = $(this);
				$field.prop('hidden', $field.data('moksa-reply-field') !== type);
			});
		}

		$form.on('change', '[data-moksa-rule-type]', showReplyField);
		showReplyField();

		$form.on('submit', function (event) {
			event.preventDefault();

			var data = formData($form);
			var type = data.reply_type;

			data.reply_data = data['reply_data_' + type] || '';

			post('rule_save', data).then(function (result) {
				feedback($form, result.message, 'ok');
				window.location.reload();
			}, function (error) {
				feedback($form, error.message, 'bad', error.problems);
			});
		});

		$form.on('click', '[data-moksa-reset-rule]', function () {
			$form[0].reset();
			$form.find('input[name=id]').val('0');
			$form.removeClass('is-editing');
			showReplyField();
		});

		$('[data-moksa-edit-rule]').on('click', function () {
			var rule = $(this).closest('tr').data('rule');

			$form.find('input[name=id]').val(rule.id);
			$form.find('input[name=name]').val(rule.name);
			$form.find('input[name=keyword]').val(rule.keyword);
			$form.find('select[name=match_type]').val(rule.match_type);
			$form.find('[data-moksa-rule-type]').val(rule.reply_type);
			$form.find('input[name=priority]').val(rule.priority);
			$form.find('input[name=is_active]').prop('checked', rule.is_active === '1' || rule.is_active === 1);
			$form.find('[name=reply_data_' + rule.reply_type + ']').val(rule.reply_data);

			showReplyField();
			$form.addClass('is-editing');
			$('html, body').animate({ scrollTop: $form.offset().top - 40 }, 200);
		});

		$('[data-moksa-delete-rule]').on('click', function () {
			if (!window.confirm(strings.confirmDelete)) {
				return;
			}

			var $scope = $(this).closest('form, .moksa-panel, .wrap');

			post('rule_delete', { id: $(this).data('moksa-delete-rule') }).then(function () {
				window.location.reload();
			}, function (error) {
				// Without this the page reloaded either way, so a delete LINE or
				// the server refused was indistinguishable from one that worked.
				feedback($scope, error.message, 'bad');
			});
		});
	}

	// --- Flows ---------------------------------------------------------------

	function bindFlows() {
		var $form = $('[data-moksa-flow-form]');

		if (!$form.length) {
			return;
		}

		$form.on('submit', function (event) {
			event.preventDefault();

			post('flow_save', formData($form)).then(function (result) {
				feedback($form, result.message, 'ok');
				window.location.reload();
			}, function (error) {
				feedback($form, error.message, 'bad', error.problems);
			});
		});

		$form.on('click', '[data-moksa-reset-flow]', function () {
			$form[0].reset();
			$form.find('input[name=id]').val('0');
			$form.removeClass('is-editing');

			// form.reset() restores the textarea's original markup value, which
			// is the worked example, so the cards have to be re-read from it.
			$(document).trigger('moksa:flow-loaded');
		});

		$('[data-moksa-load-flow]').on('click', function () {
			var flow = $(this).closest('li').data('flow');

			$form.addClass('is-editing');
			$form.find('input[name=id]').val(flow.id);
			$form.find('input[name=name]').val(flow.name);
			$form.find('select[name=trigger_type]').val(flow.trigger_type);
			$form.find('input[name=trigger_value]').val(flow.trigger_value);
			$form.find('input[name=notify_email]').val(flow.notify_email);
			$form.find('input[name=is_active]').prop('checked', flow.is_active === '1' || flow.is_active === 1);

			try {
				$form.find('textarea[name=definition]').val(
					JSON.stringify(JSON.parse(flow.definition), null, 2)
				);
			} catch (e) {
				$form.find('textarea[name=definition]').val(flow.definition);
			}

			// The step cards read from that textarea, so they have to be told
			// it changed, or a loaded flow shows the previous one's questions.
			$(document).trigger('moksa:flow-loaded');
		});

		$('[data-moksa-delete-flow]').on('click', function () {
			if (!window.confirm(strings.confirmDelete)) {
				return;
			}

			var $scope = $(this).closest('form, .moksa-panel, .wrap');

			post('flow_delete', { id: $(this).data('moksa-delete-flow') }).then(function () {
				window.location.reload();
			}, function (error) {
				// Without this the page reloaded either way, so a delete LINE or
				// the server refused was indistinguishable from one that worked.
				feedback($scope, error.message, 'bad');
			});
		});
	}

	// --- Flex editor -----------------------------------------------------------

	function bindFlex() {
		var $form = $('[data-moksa-flex-form]');

		if (!$form.length) {
			return;
		}

		var $json = $form.find('[data-moksa-flex-json]');
		var $preview = $('[data-moksa-flex-preview]');
		var $alt = $form.find('input[name=alt_text]');
		var $notifBody = $('[data-moksa-notif-body]');
		var $warnings = $('[data-moksa-preview-warnings]');

		// LINE's own maximum. It was 400 here once, which quietly cut three
		// quarters off every notification without ever showing an error.
		var MAX_ALT_TEXT = 1500;

		function renderNotification() {
			if (!$notifBody.length) {
				return;
			}

			var text = $.trim($alt.val() || '');

			$notifBody
				.text(text || moksaLine.strings.altTextEmpty)
				.toggleClass('is-empty', '' === text);
		}

		// Contrast is checked on what was actually drawn rather than on the JSON,
		// because a colour can come from the component, the block style or the
		// default, and only the rendered result knows which won. The bubble is
		// white here on purpose: shops judge button contrast against the
		// background they are looking at, and getting that background wrong is
		// how unreadable buttons ship.
		function luminance(rgb) {
			var channels = rgb.map(function (value) {
				var c = value / 255;
				return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
			});

			return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
		}

		function parseColor(value) {
			var match = /rgba?\(([^)]+)\)/.exec(value || '');

			if (!match) {
				return null;
			}

			var parts = match[1].split(',').map(function (part) {
				return parseFloat(part);
			});

			// A transparent colour tells us nothing; the caller keeps walking up.
			if (parts.length > 3 && 0 === parts[3]) {
				return null;
			}

			return [parts[0], parts[1], parts[2]];
		}

		function backgroundBehind(el) {
			var node = el;

			while (node && node.nodeType === 1) {
				var color = parseColor(window.getComputedStyle(node).backgroundColor);

				if (color) {
					return color;
				}

				node = node.parentNode;
			}

			return [255, 255, 255];
		}

		function contrast(a, b) {
			var la = luminance(a);
			var lb = luminance(b);

			return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
		}

		function checkContrast() {
			var problems = [];
			var seen = {};

			$preview.find('*').each(function () {
				if (problems.length >= 5) {
					return false;
				}

				var el = this;

				// Only leaf elements that carry their own visible text.
				if (el.children.length || '' === $.trim(el.textContent || '')) {
					return;
				}

				var style = window.getComputedStyle(el);
				var fg = parseColor(style.color);

				if (!fg) {
					return;
				}

				var size = parseFloat(style.fontSize) || 16;
				var weight = parseInt(style.fontWeight, 10) || 400;
				var large = size >= 24 || (size >= 18.66 && weight >= 700);
				var needed = large ? 3 : 4.5;
				var ratio = contrast(fg, backgroundBehind(el));

				if (ratio >= needed) {
					return;
				}

				var label = $.trim(el.textContent).slice(0, 30);

				if (seen[label]) {
					return;
				}

				seen[label] = true;
				problems.push(
					moksaLine.strings.contrastWarning
						.replace('%1$s', label)
						.replace('%2$s', ratio.toFixed(1))
						.replace('%3$s', needed.toFixed(1))
				);
			});

			return problems;
		}

		function renderWarnings() {
			if (!$warnings.length) {
				return;
			}

			var problems = [];
			var alt = $.trim($alt.val() || '');

			// LINE counts characters, and PHP's mb_strlen agrees. A plain
			// String#length counts UTF-16 units, so one emoji would read as two
			// and the editor would disagree with the server that saves it.
			var altLength = window.Array && Array.from ? Array.from(alt).length : alt.length;

			if ('' === alt) {
				problems.push(moksaLine.strings.altTextMissing);
			} else if (altLength > MAX_ALT_TEXT) {
				problems.push(
					moksaLine.strings.altTextTooLong.replace('%d', String(altLength))
				);
			}

			problems = problems.concat(checkContrast());

			if (!problems.length) {
				$warnings.empty();
				return;
			}

			var $list = $('<ul class="moksa-preview-warnings__list"></ul>');

			$.each(problems, function (i, problem) {
				$list.append($('<li></li>').text(problem));
			});

			$warnings.empty().append($list);
		}

		var $carouselNote = $('[data-moksa-carousel-note]');

		// A carousel shows one card and an edge of the next, which is what a
		// phone does too -- but in a preview it reads as a broken layout rather
		// than as "there are more". So the count is stated.
		function renderCarouselNote(parsed) {
			if (!$carouselNote.length) {
				return;
			}

			var count = parsed && 'carousel' === parsed.type && parsed.contents
				? parsed.contents.length
				: 0;

			$carouselNote
				.text(count > 1 ? moksaLine.strings.carouselCount.replace('%d', count) : '')
				.prop('hidden', count < 2);
		}

		function renderPreview() {
			renderNotification();

			if (!$preview.length || !window.MoksaFlexRenderer) {
				return;
			}

			var parsed = null;

			try {
				parsed = JSON.parse($json.val());
				window.MoksaFlexRenderer.render(parsed, $preview);
			} catch (e) {
				$preview.html('<p class="moksa-flex-preview__error">' + $('<div>').text(e.message).html() + '</p>');
			}

			renderCarouselNote(parsed);
			renderWarnings();
		}

		var previewTimer = null;

		$json.on('input', function () {
			window.clearTimeout(previewTimer);
			previewTimer = window.setTimeout(renderPreview, 400);
		});

		$alt.on('input', function () {
			renderNotification();
			renderWarnings();
		});

		renderPreview();

		$form.find('[data-moksa-flex-starter]').on('change', function () {
			var contents = $(this).find('option:selected').data('contents');

			if (contents) {
				$json.val(typeof contents === 'string' ? contents : JSON.stringify(contents, null, 2));
				renderPreview();
				$(document).trigger('moksa:flex-loaded');
			}
		});

		$form.on('submit', function (event) {
			event.preventDefault();

			post('flex_save', formData($form)).then(function (result) {
				feedback($form, result.message, 'ok');
				window.location.reload();
			}, function (error) {
				feedback($form, error.message, 'bad', error.problems);
			});
		});

		$form.on('click', '[data-moksa-flex-check]', function () {
			feedback($form, strings.working, 'busy');

			post('flex_validate', {
				contents: $json.val(),
				alt_text: $form.find('input[name=alt_text]').val()
			}).then(function (result) {
				feedback($form, result.message, 'ok');
			}, function (error) {
				feedback($form, error.message, 'bad', error.problems);
			});
		});

		$form.on('click', '[data-moksa-flex-test]', function () {
			feedback($form, strings.working, 'busy');

			post('flex_send_test', {
				contents: $json.val(),
				alt_text: $form.find('input[name=alt_text]').val(),
				line_user_id: $form.find('[data-moksa-flex-test-target]').val()
			}).then(function (result) {
				feedback($form, result.message, 'ok');
			}, function (error) {
				feedback($form, error.message, 'bad', error.problems);
			});
		});

		$form.on('click', '[data-moksa-flex-reset]', function () {
			$form[0].reset();
			$form.find('input[name=id]').val('0');
			$form.removeClass('is-editing');
			renderPreview();
			$(document).trigger('moksa:flex-loaded');
		});

		$('[data-moksa-load-flex]').on('click', function () {
			var template = $(this).closest('li').data('template');

			$form.addClass('is-editing');
			$form.find('input[name=id]').val(template.id);
			$form.find('input[name=name]').val(template.name);
			$form.find('input[name=alt_text]').val(template.alt_text);

			try {
				$json.val(JSON.stringify(JSON.parse(template.contents), null, 2));
			} catch (e) {
				$json.val(template.contents);
			}

			renderPreview();

			// The card strip reads from that textarea, so it has to be told it
			// changed, or a loaded template shows the previous one's cards.
			$(document).trigger('moksa:flex-loaded');
		});

		$('[data-moksa-delete-flex]').on('click', function () {
			if (!window.confirm(strings.confirmDelete)) {
				return;
			}

			var $scope = $(this).closest('form, .moksa-panel, .wrap');

			post('flex_delete', { id: $(this).data('moksa-delete-flex') }).then(function () {
				window.location.reload();
			}, function (error) {
				// Without this the page reloaded either way, so a delete LINE or
				// the server refused was indistinguishable from one that worked.
				feedback($scope, error.message, 'bad');
			});
		});
	}

	// --- Rich menus ---------------------------------------------------------------

	function bindRichMenus() {
		var $form = $('[data-moksa-menu-form]');

		if (!$form.length) {
			return;
		}

		var frame = null;
		var $editorRoot = $('[data-moksa-area-editor]');

		/** The editor instance, once its own script has booted. */
		function editor() {
			return $editorRoot.data('moksaAreaEditor');
		}

		/** Point both the canvas and the phone preview at the same image. */
		function setEditorImage(url) {
			$editorRoot.find('[data-moksa-canvas-image], [data-moksa-phone-image]').attr('src', url || '');
			$editorRoot.toggleClass('has-image', !!url);

			var instance = editor();

			if (instance) {
				instance.render();
			}
		}

		/** A full menu is 2500x1686; a half menu is 2500x843. */
		function syncSize() {
			var instance = editor();

			if (instance) {
				instance.setSize(2500, 'half' === $form.find('select[name=size]').val() ? 843 : 1686);
			}
		}

		/** Tab switch targets come from the group the menu belongs to. */
		function syncSwitchTargets() {
			var group = $form.find('input[name=tab_group]').val();
			var targets = {};

            $('[data-menu]').each(function () {
                var menu = $(this).data('menu');

                if (menu && menu.tab_group === group && menu.alias_id
                    && String(menu.id) !== String($form.find('input[name=id]').val())) {
                    targets[menu.alias_id] = menu.name;
                }
            });

			$editorRoot.data('switch-targets', targets);

			var instance = editor();

			if (instance) {
				instance.render();
			}
		}

		$form.on('change', 'select[name=size]', syncSize);
		$form.on('change', 'input[name=tab_group]', syncSwitchTargets);

		$form.on('input change', 'input[name=chat_bar_text]', function () {
			var text = $(this).val();

			$editorRoot.find('[data-moksa-phone-chatbar]').text(text || (strings.menu || 'Menu'));
		});

		$form.on('click', '[data-moksa-toggle-json]', function () {
			var $json = $form.find('[data-moksa-menu-areas]');

			$json.prop('hidden', !$json.prop('hidden'));
		});

		syncSize();
		syncSwitchTargets();

		$form.on('click', '[data-moksa-pick-image]', function (event) {
			event.preventDefault();

			if (!frame) {
				frame = wp.media({
					title: strings.chooseImage || 'Rich menu image',
					library: { type: 'image' },
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();

					$form.find('input[name=image_attachment_id]').val(attachment.id);
					$form.find('[data-moksa-image-preview]').html(
						'<span>' + attachment.width + ' x ' + attachment.height + '</span>'
					);

					setEditorImage(attachment.url);
				});
			}

			frame.open();
		});

		$form.on('submit', function (event) {
			event.preventDefault();

			post('richmenu_save', formData($form)).then(function (result) {
				feedback($form, result.message, 'ok', result.warnings);

				if (!result.warnings || !result.warnings.length) {
					window.location.reload();
				} else {
					$form.find('input[name=id]').val(result.id);
				}
			}, function (error) {
				feedback($form, error.message, 'bad', error.problems);
			});
		});

		$form.on('click', '[data-moksa-reset-menu]', function () {
			$form[0].reset();
			$form.find('input[name=id]').val('0');
			$form.removeClass('is-editing');
			$form.find('[data-moksa-image-preview]').empty();
			setEditorImage('');

			var fresh = editor();

			if (fresh) {
				fresh.load();
				fresh.selected = null;
				fresh.render();
			}
		});

		$form.on('click', '[data-moksa-sync-menus]', function () {
			feedback($form, strings.working, 'busy');

			post('richmenu_sync', {}).then(function (result) {
				var lines = [
					'LINE has ' + result.remote_count + ' rich menus and ' + result.alias_count + ' aliases.',
					'This site tracks ' + result.local_count + '.'
				];

				if (result.default_note) {
					lines.push(result.default_note);
				}

				(result.orphans || []).forEach(function (orphan) {
					lines.push('Only on LINE: ' + (orphan.name || orphan.id));
				});

				feedback($form, 'Comparison complete.', 'ok', lines);
			}, function (error) {
				feedback($form, error.message, 'bad');
			});
		});

		$('[data-moksa-edit-menu]').on('click', function () {
			var menu = $(this).closest('tr').data('menu');

			$form.addClass('is-editing');
			$form.find('input[name=id]').val(menu.id);
			$form.find('input[name=name]').val(menu.name);
			$form.find('input[name=chat_bar_text]').val(menu.chat_bar_text);
			$form.find('select[name=size]').val(menu.size);
			$form.find('input[name=tab_group]').val(menu.tab_group);
			$form.find('input[name=tab_order]').val(menu.tab_order);
			$form.find('input[name=alias_id]').val(menu.alias_id);
			$form.find('input[name=image_attachment_id]').val(menu.image_attachment_id);
			$form.find('input[name=selected]').prop('checked', menu.selected === '1' || menu.selected === 1);
			$form.find('input[name=is_default]').prop('checked', menu.is_default === '1' || menu.is_default === 1);

			try {
				$form.find('[data-moksa-menu-areas]').val(JSON.stringify(JSON.parse(menu.areas), null, 2));
			} catch (e) {
				$form.find('[data-moksa-menu-areas]').val(menu.areas);
			}

			setEditorImage(menu.image_url || '');
			$form.find('input[name=chat_bar_text]').trigger('change');
			syncSize();
			syncSwitchTargets();

			var instance = editor();

			if (instance) {
				instance.load();
				instance.selected = null;
				instance.render();
			}

			$('html, body').animate({ scrollTop: $form.offset().top - 40 }, 200);
		});

		$('[data-moksa-publish-menu]').on('click', function () {
			var $button = $(this);

			$button.prop('disabled', true).text(strings.publishing);

			post('richmenu_publish', { id: $button.data('moksa-publish-menu') }).then(function () {
				window.location.reload();
			}, function (error) {
				$button.prop('disabled', false);
				feedback($form, error.message, 'bad');
			});
		});

		$('[data-moksa-publish-group]').on('click', function () {
			var $button = $(this);

			$button.prop('disabled', true);

			post('richmenu_publish', { tab_group: $button.data('moksa-publish-group') }).then(function () {
				window.location.reload();
			}, function (error) {
				$button.prop('disabled', false);
				feedback($form, error.message, 'bad');
			});
		});

		$('[data-moksa-default-menu]').on('click', function () {
			post('richmenu_default', { id: $(this).data('moksa-default-menu') }).then(function () {
				window.location.reload();
			}, function (error) {
				feedback($form, error.message, 'bad');
			});
		});

		$('[data-moksa-delete-menu]').on('click', function () {
			var $row = $(this).closest('tr');
			// Losing the default takes the menu away from every customer at
			// once, so ask that question rather than the generic one.
			var isDefault = 1 === parseInt($row.attr('data-is-default'), 10);

			if (!window.confirm(isDefault ? strings.confirmDeleteDefault : strings.confirmDelete)) {
				return;
			}

			post('richmenu_delete', { id: $(this).data('moksa-delete-menu') }).then(function (result) {
				// Drop the row rather than reloading, so the answer -- which may
				// be the warning that the channel now has no default -- survives
				// long enough to read.
				$row.remove();
				feedback($form, result.message, result.warning ? 'warn' : 'ok');
			}, function (error) {
				feedback($form, error.message, 'bad');
			});
		});
	}

	// --- Inbox -----------------------------------------------------------------------

	function bindInbox() {
		var $thread = $('[data-moksa-thread]');

		if (!$thread.length) {
			return;
		}

		var current = 0;
		var $reply = $('[data-moksa-reply]');
		var $actions = $('[data-moksa-thread-actions]');
		var $name = $('[data-moksa-thread-name]');

		function scrollToLatest() {
			$thread.scrollTop($thread.prop('scrollHeight'));
		}

		/**
		 * Reflect a conversation's handling state everywhere it is shown.
		 *
		 * The button for the state you are already in is disabled rather than
		 * hidden: a row of buttons that changes length as you click it is how
		 * you end up pressing the wrong one.
		 */
		function showStatus(status) {
			$actions.find('button').each(function () {
				var $button = $(this);
				var isCurrent = $button.data('moksa-status') === status;

				$button.prop('disabled', isCurrent).toggleClass('is-current', isCurrent);
			});

			var $pill = $('.moksa-conv.is-active').find('.moksa-conv__meta .moksa-pill');

			if ($pill.length) {
				$pill
					.removeClass('moksa-pill--ok moksa-pill--warn moksa-pill--bad')
					.addClass('human' === status ? 'moksa-pill--warn' : ('closed' === status ? 'moksa-pill--bad' : 'moksa-pill--ok'))
					.text((strings.conversationStatus || {})[status] || status);
			}
		}

		$('.moksa-conv').on('click', function () {
			var $conv = $(this);

			current = $conv.data('conversation');

			$('.moksa-conv').removeClass('is-active');
			$conv.addClass('is-active');

			post('inbox_thread', { conversation_id: current }).then(function (result) {
				$thread.html(result.thread);
				$name.text(result.name || '');
				$reply.prop('hidden', false);
				$actions.prop('hidden', false);
				$conv.find('.moksa-conv__unread').remove();
				showStatus(result.status);
				scrollToLatest();
			}, function (error) {
				$thread.html('<p class="moksa-feedback__message moksa-feedback__message--bad">'
					+ $('<div>').text(error.message).html() + '</p>');
			});
		});

		$reply.on('submit', function (event) {
			event.preventDefault();

			if (!current) {
				return;
			}

			var $text = $reply.find('textarea');
			var $button = $reply.find('button');

			$button.prop('disabled', true);

			post('inbox_send', { conversation_id: current, message: $text.val() }).then(function (result) {
				$thread.html(result.thread);
				$text.val('');
				$button.prop('disabled', false);
				scrollToLatest();
			}, function (error) {
				$button.prop('disabled', false);
				window.alert(error.message);
			});
		});

		// This used to reload the whole page on success, which closed the very
		// conversation you had just taken over -- press the button, and the
		// screen empties. Nothing was shown in the meantime either, and a
		// failure did nothing at all. It updates in place now.
		// Stickers. The picker is built server-side so the forty images per pack
		// are ordinary lazy-loaded <img>, not forty requests fired by script.
		var $stickers = $('[data-moksa-sticker-picker]');

		$reply.on('click', '[data-moksa-toggle-stickers]', function () {
			var $toggle = $(this);
			var open = $stickers.prop('hidden');

			$stickers.prop('hidden', !open);
			$toggle.attr('aria-expanded', open ? 'true' : 'false');
		});

		$stickers.on('click', '[data-moksa-sticker-pack]', function () {
			var pack = String($(this).data('moksa-sticker-pack'));

			$stickers.find('[data-moksa-sticker-pack]').removeClass('is-current');
			$(this).addClass('is-current');
			$stickers.find('[data-moksa-sticker-grid]').each(function () {
				$(this).prop('hidden', String($(this).data('moksa-sticker-grid')) !== pack);
			});
		});

		$stickers.on('click', '[data-moksa-send-sticker]', function () {
			if (!current) {
				return;
			}

			var $sticker = $(this);

			// A sticker cannot be unsent and is billed, so it is confirmed --
			// the grid is a wall of small targets and a mis-click is easy.
			if (!window.confirm(strings.confirmSendSticker)) {
				return;
			}

			$stickers.find('[data-moksa-send-sticker]').prop('disabled', true);

			post('inbox_send', {
				conversation_id: current,
				sticker_package: $sticker.data('moksa-send-sticker'),
				sticker_id: $sticker.data('sticker-id')
			}).then(function (result) {
				$stickers.find('[data-moksa-send-sticker]').prop('disabled', false);
				$stickers.prop('hidden', true);
				$reply.find('[data-moksa-toggle-stickers]').attr('aria-expanded', 'false');
				$thread.html(result.thread);
				scrollToLatest();
			}, function (error) {
				$stickers.find('[data-moksa-send-sticker]').prop('disabled', false);
				window.alert(error.message);
			});
		});

		$actions.on('click', 'button', function () {
			if (!current) {
				return;
			}

			var $button = $(this);
			var wanted = $button.data('moksa-status');
			var $all = $actions.find('button');

			$all.prop('disabled', true);

			post('inbox_status', { conversation_id: current, status: wanted }).then(function (result) {
				showStatus(result.status || wanted);
			}, function (error) {
				$all.prop('disabled', false);
				window.alert(error.message);
			});
		});
	}

	// --- Broadcast ---------------------------------------------------------------------

	function bindBroadcast() {
		var $form = $('[data-moksa-broadcast]');

		if (!$form.length) {
			return;
		}

		function toggleMode() {
			var mode = $form.find('select[name=mode]').val();

			$form.find('[data-moksa-broadcast-target]').prop('hidden', mode !== 'test');
			$form.find('[data-moksa-broadcast-confirm]').prop('hidden', mode === 'test');
		}

		$form.on('change', 'select[name=mode]', toggleMode);
		toggleMode();

		$form.on('submit', function (event) {
			event.preventDefault();

			var $button = $form.find('button[type=submit]');

			$button.prop('disabled', true);
			feedback($form, strings.working, 'busy');

			post('broadcast', formData($form)).then(function (result) {
				$button.prop('disabled', false);
				feedback($form, result.message, 'ok');
				$form.find('input[name=confirm]').val('');
			}, function (error) {
				$button.prop('disabled', false);
				feedback($form, error.message, 'bad', error.problems);
			});
		});
	}

	// --- Payment links -----------------------------------------------------------------

	function bindPayLinks() {
		var $form = $('[data-moksa-pay-link]');

		if (!$form.length) {
			return;
		}

		$form.on('submit', function (event) {
			event.preventDefault();

			feedback($form, strings.working, 'busy');

			post('pay_link', formData($form)).then(function (result) {
				feedback($form, result.message, 'ok', [result.url]);
			}, function (error) {
				feedback($form, error.message, 'bad');
			});
		});
	}

	// --- Profile unlink -------------------------------------------------------------------

	function bindUnlink() {
		$('[data-moksa-line-unlink]').on('click', function () {
			var $button = $(this);

			if (!window.confirm(strings.confirmDelete)) {
				return;
			}

			$.post(settings.ajaxUrl || window.ajaxurl, {
				action: 'moksa_line_unlink',
				nonce: $button.data('nonce'),
				user_id: $button.data('moksa-line-unlink')
			}).then(function () {
				window.location.reload();
			});
		});
	}

	// --- Order notification templates ------------------------------------------------------

	function bindNotifyTemplates() {
		var $rules = $('[data-moksa-rules]');
		var $testButton = $('[data-moksa-notify-test]');

		if (!$rules.length && !$testButton.length) {
			return;
		}

		// Only show the operators that belong to the chosen condition type,
		// so "order total is not" cannot be selected.
		function syncOperators($row) {
			var type = $row.find('select[name*="[type]"]').val();
			var $operator = $row.find('select[name*="[operator]"]');
			var current = $operator.val();
			var firstMatch = null;

			$operator.find('option').each(function () {
				var matches = $(this).data('for') === type;

				$(this).prop('hidden', !matches).prop('disabled', !matches);

				if (matches && firstMatch === null) {
					firstMatch = this.value;
				}
			});

			if (!$operator.find('option[value="' + current + '"]').filter(function () {
				return $(this).data('for') === type;
			}).length && firstMatch) {
				$operator.val(firstMatch);
			}
		}

		$rules.find('tr').each(function () {
			syncOperators($(this));
		});

		$rules.on('change', 'select[name*="[type]"]', function () {
			syncOperators($(this).closest('tr'));
		});

		$('[data-moksa-add-rule]').on('click', function () {
			var $last = $rules.find('tr').last();
			var $row = $last.clone();
			var index = $rules.find('tr').length;

			$row.find('select, input').each(function () {
				if (this.name) {
					this.name = this.name.replace(/\[\d+\]/, '[' + index + ']');
				}

				if (this.tagName === 'INPUT') {
					this.value = '';
				}
			});

			$rules.find('tbody').append($row);
			syncOperators($row);
		});

		$rules.on('click', '[data-moksa-remove-rule]', function () {
			if ($rules.find('tr').length > 1) {
				$(this).closest('tr').remove();
			} else {
				$(this).closest('tr').find('input').val('');
			}
		});

		$testButton.on('click', function () {
			var $button = $(this);
			var $scope = $button.closest('.postbox, body');

			feedback($scope, strings.working, 'busy');

			post('notify_test', {
				template_id: $button.data('moksa-notify-test'),
				line_user_id: $('[data-moksa-notify-test-to]').val()
			}).then(function (result) {
				feedback($scope, result.message, 'ok');
			}, function (error) {
				feedback($scope, error.message, 'bad', error.problems);
			});
		});
	}

	// --- Copy to clipboard ----------------------------------------------------------------

	// Copying used to be a bare cursor:pointer on a <code>, which meant the
	// affordance existed but nothing announced it -- no icon, no label, and no
	// way to reach it from the keyboard. These are the values people need most
	// often (the webhook URL, a LINE user id), so the control says what it does.
	function bindCopy() {
		$(document).on('click', '.moksa-copyable', function () {
			var $el = $(this);
			var text = $el.data('moksa-copy');

			if (undefined === text || '' === text) {
				text = $.trim($el.text());
			}

			function done(ok) {
				$('.moksa-copyable').removeClass('is-copied is-copy-failed');
				$el.addClass(ok ? 'is-copied' : 'is-copy-failed');
				$el.attr('aria-label', ok ? moksaLine.strings.copied : moksaLine.strings.copyFailed);

				window.setTimeout(function () {
					$el.removeClass('is-copied is-copy-failed').removeAttr('aria-label');
				}, 1600);
			}

			// The clipboard API is unavailable over plain http and can be
			// refused even over https, so the failure has to be visible rather
			// than looking like a successful copy of nothing.
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(String(text)).then(function () {
					done(true);
				}, function () {
					done(false);
				});
				return;
			}

			done(false);
		});
	}

	// A LINE profile URL can 404 long after it was stored. Without this the
	// browser paints its broken-image glyph on top of the initial that was put
	// there precisely to cover that case.
	function bindAvatarFallback() {
		$('.moksa-avatar img').each(function () {
			var img = this;

			function hide() {
				$(img).css('display', 'none');
			}

			$(img).on('error', hide);

			// A cached failure can land before this handler is attached.
			if (img.complete && 0 === img.naturalWidth) {
				hide();
			}
		});
	}

	function bindResendNotification() {
		$(document).on('click', '[data-moksa-resend-notification]', function () {
			var $button = $(this);

			if (!window.confirm(moksaLine.strings.confirmResend)) {
				return;
			}

			$button.prop('disabled', true).text(moksaLine.strings.working);

			$.post(moksaLine.ajaxUrl, {
				action: 'moksa_line_resend_notification',
				nonce: moksaLine.nonce,
				order_id: $button.data('order'),
				status: $button.data('status')
			}).done(function (response) {
				if (response && response.success) {
					// The new attempt is a new row, so the list has to be
					// re-read rather than patched in place.
					window.location.reload();
					return;
				}

				$button.prop('disabled', false).text(moksaLine.strings.sendAgain);
				window.alert((response && response.data && response.data.message) || moksaLine.strings.failed);
			}).fail(function () {
				$button.prop('disabled', false).text(moksaLine.strings.sendAgain);
				window.alert(moksaLine.strings.failed);
			});
		});
	}

	// Settings rows that only apply when another control is set a certain way.
	// A field captioned "only needed when the mode above is X" while the mode is
	// not X still reads as something to fill in, and a long-lived token typed
	// into a site issuing short-lived ones is a credential stored for nothing.
	function bindConditionalRows() {
		var $rows = $('[data-moksa-visible-when]');

		if (!$rows.length) {
			return;
		}

		function apply() {
			$rows.each(function () {
				var parts = String($(this).data('moksa-visible-when')).split('=');
				var $control = $('#moksa-' + parts[0] + ', [name="moksa_line[' + parts[0] + ']"]').first();

				// An unknown control must not hide the row: better an extra
				// field than a setting with no way to reach it.
				$(this).prop('hidden', $control.length ? $control.val() !== parts[1] : false);
			});
		}

		$(document).on('change', 'select, input', apply);
		apply();
	}

	// Character counters. LINE's own limit is 5000 characters, counted as
	// characters -- an emoji is one, not the two UTF-16 units String#length
	// reports, which is what the server counts too.
	function textLength(value) {
		return window.Array && Array.from ? Array.from(value).length : value.length;
	}

	function bindCounters() {
		$('[data-moksa-count-into]').each(function () {
			var $field = $(this);
			var $into = $($field.data('moksa-count-into'));
			var max = parseInt($field.attr('maxlength'), 10) || 0;

			if (!$into.length || !max) {
				return;
			}

			function update() {
				var used = textLength($field.val() || '');
				$into
					.text(moksaLine.strings.charactersUsed.replace('%1$s', used).replace('%2$s', max))
					.toggleClass('is-near-limit', used > max * 0.9);
			}

			$field.on('input', update);
			update();
		});
	}

	// The broadcast preview. Deliberately not the Flex renderer's job: a
	// broadcast is a plain text bubble, optionally alongside a Flex template,
	// and the thing worth showing is what a recipient sees before they can do
	// anything about it.
	function bindBroadcastPreview() {
		var $preview = $('[data-moksa-broadcast-preview]');

		if (!$preview.length) {
			return;
		}

		var $message = $('#moksa-broadcast-message');
		var $flex = $('#moksa-broadcast-flex');

		function render() {
			$preview.empty();

			var text = $.trim($message.val() || '');

			if (text) {
				$preview.append($('<div class="moksa-bubble"></div>').text(text));
			}

			var flexId = parseInt($flex.val(), 10) || 0;

			if (flexId > 0) {
				// Draw the actual card, carousel and all. Naming it was no use:
				// a broadcast cannot be recalled, and the attached template is
				// usually the part carrying the offer.
				var $option = $flex.find('option:selected');
				var raw = $option.data('contents');
				var $card = $('<div class="moksa-broadcast-card"></div>');
				var drawn = false;

				if (raw && window.MoksaFlexRenderer) {
					try {
						var parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
						window.MoksaFlexRenderer.render(parsed, $card);
						drawn = true;

						if ('carousel' === parsed.type && parsed.contents && parsed.contents.length > 1) {
							$card.append(
								$('<p class="moksa-bubble moksa-bubble--empty"></p>').text(
									moksaLine.strings.carouselCount.replace('%d', parsed.contents.length)
								)
							);
						}
					} catch (e) {
						drawn = false;
					}
				}

				// A template that will not parse is a real problem worth seeing
				// here, not a reason to silently show nothing.
				$preview.append(drawn
					? $card
					: $('<div class="moksa-bubble moksa-bubble--card"></div>')
						.text(moksaLine.strings.flexAttached.replace('%s', $.trim($option.text()))));
			}

			if (!$preview.children().length) {
				$preview.append($('<p class="moksa-bubble moksa-bubble--empty"></p>').text(moksaLine.strings.broadcastEmpty));
			}
		}

		$message.on('input', render);
		$flex.on('change', render);
		render();
	}

	// The auto-reply preview. Shows the exchange rather than the reply alone,
	// because a rule is a pair -- what the customer types and what comes back --
	// and the trigger is half of what there is to get wrong.
	function bindReplyPreview() {
		var $preview = $('[data-moksa-reply-preview]');

		if (!$preview.length) {
			return;
		}

		var $form = $('[data-moksa-rule-form]').length ? $('[data-moksa-rule-form]') : $preview.closest('.moksa-split__side');
		var $type = $('#moksa-rule-type');
		var $trigger = $('#moksa-rule-keyword');

		// Sample values, so the shop reads the sentence the customer reads
		// rather than a line of braces.
		var SAMPLE = {
			'{display_name}': moksaLine.strings.sampleName,
			'{site_name}': moksaLine.siteName,
			'{site_url}': moksaLine.siteUrl
		};

		function fill(text) {
			$.each(SAMPLE, function (token, value) {
				text = text.split(token).join(value);
			});

			return text;
		}

		function bubble(text, extraClass) {
			return $('<div class="moksa-bubble ' + (extraClass || '') + '"></div>').text(text);
		}

		function render() {
			$preview.empty();

			var trigger = $.trim($trigger.val() || '');

			if (trigger) {
				$preview.append(
					$('<div class="moksa-bubble moksa-bubble--sent"></div>').text(trigger)
				);
			}

			var type = $type.val();
			var reply;

			if ('text' === type) {
				var text = $.trim($('#moksa-rule-text').val() || '');
				reply = text ? bubble(fill(text)) : bubble(moksaLine.strings.replyEmpty, 'moksa-bubble--empty');
			} else if ('image' === type) {
				var url = $.trim($('#moksa-rule-image').val() || '');

				if (url) {
					reply = $('<div class="moksa-bubble moksa-bubble--media"></div>')
						.append($('<img alt="" />').attr('src', url));
				} else {
					reply = bubble(moksaLine.strings.replyEmpty, 'moksa-bubble--empty');
				}
			} else {
				// Everything else is a reference to something built elsewhere,
				// so the preview names it rather than pretending to render it.
				var label = $type.find('option:selected').text();
				var chosen = $('[data-moksa-reply-field="' + type + '"]:not([hidden])').find('select, input').first();
				var chosenLabel = chosen.length
					? (chosen.is('select') ? chosen.find('option:selected').text() : $.trim(chosen.val()))
					: '';

				reply = bubble(
					chosenLabel
						? moksaLine.strings.replyReference.replace('%1$s', label).replace('%2$s', chosenLabel)
						: label,
					'moksa-bubble--card'
				);
			}

			$preview.append($('<div class="moksa-bubble-row"></div>')
				.append($('<span class="moksa-phone-chat__avatar" aria-hidden="true"></span>'))
				.append(reply));
		}

		$form.on('input change', 'input, select, textarea', render);
		$(document).on('moksa:rule-loaded', render);
		render();
	}

	// Imagemaps. The area editor is the rich menu's, told a different
	// coordinate space, so all this has to do is the form around it.
	function bindImagemaps() {
		var $form = $('[data-moksa-imagemap-form]');

		if (!$form.length) {
			return;
		}

		var $areas = $form.find('[data-moksa-imagemap-areas]');
		var $image = $form.find('input[name=image_attachment_id]');
		var $preview = $form.find('[data-moksa-imagemap-image-preview]');
		var frame = null;

		var $editorRoot = $form.find('[data-moksa-area-editor]');

		function editor() {
			return $editorRoot.data('moksaAreaEditor');
		}

		function showImage(url) {
			$preview.empty();

			if (url) {
				$preview.append($('<img alt="" />').attr('src', url));
			}

			$editorRoot.find('[data-moksa-canvas-image]').attr('src', url || '');
			$editorRoot.toggleClass('has-image', !!url);

			var instance = editor();

			if (!instance) {
				return;
			}

			if (!url) {
				instance.render();
				return;
			}

			// An imagemap is always 1040 wide, and its height is whatever that
			// makes it. The editor has to be told, or regions drawn on a tall
			// banner land in the wrong place -- the coordinate space it clamps
			// to would not match the one LINE renders in.
			var probe = new window.Image();

			probe.onload = function () {
				var height = probe.naturalWidth
					? Math.round(probe.naturalHeight * (1040 / probe.naturalWidth))
					: 1040;

				instance.setSize(1040, height);
			};

			probe.onerror = function () {
				instance.render();
			};

			probe.src = url;
		}

		$form.on('click', '[data-moksa-pick-imagemap-image]', function () {
			if (!frame) {
				frame = wp.media({
					title: strings.chooseImage,
					library: { type: 'image' },
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					$image.val(attachment.id);
					showImage(attachment.url);
				});
			}

			frame.open();
		});

		$form.on('click', '[data-moksa-toggle-imagemap-json]', function () {
			$areas.prop('hidden', !$areas.prop('hidden'));
		});

		$form.on('submit', function (event) {
			event.preventDefault();

			post('imagemap_save', formData($form)).then(function (result) {
				feedback($form, result.message, 'ok');
				window.setTimeout(function () { window.location.reload(); }, 600);
			}, function (error) {
				feedback($form, error.message, 'bad');
			});
		});

		$form.on('click', '[data-moksa-reset-imagemap]', function () {
			$form[0].reset();
			$form.find('input[name=id]').val('0');
			$form.find('input[name=image_attachment_id]').val('0');
			reloadAreas('[]');
			showImage('');
			$form.removeClass('is-editing');
		});

		$form.on('click', '[data-moksa-imagemap-test]', function () {
			post('imagemap_send_test', {
				id: $form.find('input[name=id]').val(),
				line_user_id: $form.find('[data-moksa-imagemap-test-target]').val()
			}).then(function (result) {
				feedback($form, result.message, 'ok');
			}, function (error) {
				feedback($form, error.message, 'bad');
			});
		});

		// The editor ignores a programmatic change on its own field on purpose:
		// it writes there itself, and would otherwise reload on its own output.
		// So filling that field from outside has to tell it directly.
		function reloadAreas(json) {
			$areas.val(json || '[]');

			var instance = editor();

			if (instance) {
				instance.load();
				instance.render();
			}
		}

		$('[data-moksa-load-imagemap]').on('click', function () {
			var row = $(this).closest('li').data('imagemap');

			$form.addClass('is-editing');
			$form.find('input[name=id]').val(row.id);
			$form.find('input[name=name]').val(row.name);
			$form.find('input[name=alt_text]').val(row.alt_text);
			$image.val(row.image_attachment_id || 0);
			reloadAreas(row.actions);
			showImage(row.image_url || '');
		});

		$('[data-moksa-delete-imagemap]').on('click', function () {
			if (!window.confirm(strings.confirmDelete)) {
				return;
			}

			var $scope = $(this).closest('form, .moksa-panel, .wrap');

			post('imagemap_delete', { id: $(this).data('moksa-delete-imagemap') }).then(function () {
				window.location.reload();
			}, function (error) {
				// Without this the page reloaded either way, so a delete LINE or
				// the server refused was indistinguishable from one that worked.
				feedback($scope, error.message, 'bad');
			});
		});
	}

	function bindTemplates() {
		var $form = $('[data-moksa-template-form]');

		if (!$form.length) {
			return;
		}

		function editor() {
			return $form.data('moksaTemplateEditor');
		}

		$form.on('submit', function (event) {
			event.preventDefault();

			post('template_save', {
				id: $form.find('input[name=id]').val(),
				name: $form.find('input[name=name]').val(),
				alt_text: $form.find('input[name=alt_text]').val(),
				definition: $form.find('[data-moksa-template-json]').val()
			}).then(function (result) {
				feedback($form, result.message, 'ok');
				window.setTimeout(function () { window.location.reload(); }, 600);
			}, function (error) {
				feedback($form, error.message, 'bad', error.problems);
			});
		});

		$form.on('click', '[data-moksa-template-check]', function () {
			post('template_validate', {
				definition: $form.find('[data-moksa-template-json]').val(),
				alt_text: $form.find('input[name=alt_text]').val()
			}).then(function (result) {
				feedback($form, result.message, 'ok');
			}, function (error) {
				feedback($form, error.message, 'bad', error.problems);
			});
		});

		$form.on('click', '[data-moksa-template-test]', function () {
			post('template_send_test', {
				id: $form.find('input[name=id]').val(),
				line_user_id: $form.find('[data-moksa-template-test-target]').val()
			}).then(function (result) {
				feedback($form, result.message, 'ok');
			}, function (error) {
				feedback($form, error.message, 'bad');
			});
		});

		$form.on('click', '[data-moksa-reset-template]', function () {
			$form[0].reset();
			$form.find('input[name=id]').val('0');
			$form.removeClass('is-editing');
			$form.find('[data-moksa-template-kind]').trigger('change');
		});

		$('[data-moksa-load-template]').on('click', function () {
			var row = $(this).closest('li').data('template');

			$form.addClass('is-editing');
			$form.find('input[name=id]').val(row.id);
			$form.find('input[name=name]').val(row.name);
			$form.find('input[name=alt_text]').val(row.alt_text);
			$form.find('[data-moksa-template-json]').val(row.definition || '{}');

			// The editor owns the cards, so it has to be told rather than left
			// to notice a field it did not change itself.
			$(document).trigger('moksa:template-loaded');
		});

		$('[data-moksa-delete-template]').on('click', function () {
			if (!window.confirm(strings.confirmDelete)) {
				return;
			}

			var $scope = $(this).closest('form, .moksa-panel, .wrap');

			post('template_delete', { id: $(this).data('moksa-delete-template') }).then(function () {
				window.location.reload();
			}, function (error) {
				// Without this the page reloaded either way, so a delete LINE or
				// the server refused was indistinguishable from one that worked.
				feedback($scope, error.message, 'bad');
			});
		});
	}

	function bindWebhookCheck() {
		var $scope = $('[data-moksa-webhook]').closest('td');

		if (!$scope.length) {
			return;
		}

		function report(result, kind) {
			feedback($scope, result.message, kind || (result.ok ? 'ok' : 'warn'), result.lines);
		}

		$scope.on('click', '[data-moksa-webhook-check]', function () {
			var $button = $(this).prop('disabled', true);

			feedback($scope, strings.working, 'busy');

			post('webhook_check', {}).then(function (result) {
				report(result);
			}, function (error) {
				feedback($scope, error.message, 'bad');
			}).always(function () {
				$button.prop('disabled', false);
			});
		});

		$scope.on('click', '[data-moksa-webhook-set]', function () {
			var $button = $(this).prop('disabled', true);

			feedback($scope, strings.working, 'busy');

			post('webhook_set', {}).then(function (result) {
				// Setting the URL is only half of it, so read the state straight
				// back rather than claiming success on the write alone.
				post('webhook_check', {}).then(function (checked) {
					feedback($scope, result.message, checked.ok ? 'ok' : 'warn', checked.lines);
				}, function () {
					feedback($scope, result.message, 'ok');
				});
			}, function (error) {
				feedback($scope, error.message, 'bad');
			}).always(function () {
				$button.prop('disabled', false);
			});
		});
	}

	function bindClearLogs() {
		$(document).on('click', '[data-moksa-clear-logs]', function () {
			if (!window.confirm(moksaLine.strings.confirmClearLogs)) {
				return;
			}

			var $button = $(this).prop('disabled', true);

			$.post(moksaLine.ajaxUrl, {
				action: 'moksa_line_clear_logs',
				nonce: moksaLine.nonce
			}).done(function () {
				window.location.reload();
			}).fail(function () {
				$button.prop('disabled', false);
				window.alert(moksaLine.strings.failed);
			});
		});
	}

	$(function () {
		bindRules();
		bindFlows();
		bindFlex();
		bindRichMenus();
		bindInbox();
		bindBroadcast();
		bindPayLinks();
		bindNotifyTemplates();
		bindUnlink();
		bindCopy();
		bindImagemaps();
		bindTemplates();
		bindWebhookCheck();
		bindClearLogs();
		bindReplyPreview();
		bindCounters();
		bindBroadcastPreview();
		bindConditionalRows();
		bindResendNotification();
		bindAvatarFallback();
	});
}(jQuery));
