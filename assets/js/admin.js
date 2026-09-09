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

			post('rule_delete', { id: $(this).data('moksa-delete-rule') }).then(function () {
				window.location.reload();
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
		});

		$('[data-moksa-delete-flow]').on('click', function () {
			if (!window.confirm(strings.confirmDelete)) {
				return;
			}

			post('flow_delete', { id: $(this).data('moksa-delete-flow') }).then(function () {
				window.location.reload();
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

		function renderPreview() {
			if (!$preview.length || !window.MoksaFlexRenderer) {
				return;
			}

			try {
				window.MoksaFlexRenderer.render(JSON.parse($json.val()), $preview);
			} catch (e) {
				$preview.html('<p class="moksa-flex-preview__error">' + $('<div>').text(e.message).html() + '</p>');
			}
		}

		var previewTimer = null;

		$json.on('input', function () {
			window.clearTimeout(previewTimer);
			previewTimer = window.setTimeout(renderPreview, 400);
		});

		renderPreview();

		$form.find('[data-moksa-flex-starter]').on('change', function () {
			var contents = $(this).find('option:selected').data('contents');

			if (contents) {
				$json.val(typeof contents === 'string' ? contents : JSON.stringify(contents, null, 2));
				renderPreview();
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
		});

		$('[data-moksa-delete-flex]').on('click', function () {
			if (!window.confirm(strings.confirmDelete)) {
				return;
			}

			post('flex_delete', { id: $(this).data('moksa-delete-flex') }).then(function () {
				window.location.reload();
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
						'<img src="' + attachment.url + '" alt="" />'
						+ '<span>' + attachment.width + ' x ' + attachment.height + '</span>'
					);
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
		});

		$form.on('click', '[data-moksa-sync-menus]', function () {
			feedback($form, strings.working, 'busy');

			post('richmenu_sync', {}).then(function (result) {
				var lines = [
					'LINE has ' + result.remote_count + ' rich menus and ' + result.alias_count + ' aliases.',
					'This site tracks ' + result.local_count + '.'
				];

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
			if (!window.confirm(strings.confirmDelete)) {
				return;
			}

			post('richmenu_delete', { id: $(this).data('moksa-delete-menu') }).then(function () {
				window.location.reload();
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

		$actions.on('click', 'button', function () {
			if (!current) {
				return;
			}

			post('inbox_status', { conversation_id: current, status: $(this).data('moksa-status') }).then(function () {
				window.location.reload();
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

	function bindCopy() {
		$('.moksa-copyable').on('click', function () {
			var text = $(this).text();

			if (navigator.clipboard) {
				navigator.clipboard.writeText(text);
			}

			$(this).addClass('is-copied');

			window.setTimeout(function () {
				$('.moksa-copyable').removeClass('is-copied');
			}, 1200);
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
	});
}(jQuery));
