/**
 * Conversation flow editor.
 *
 * The flow used to be a raw JSON textarea. Shop owners are the people who know
 * what to ask their customers and are not the people who can be asked to keep
 * a nested array valid while doing it, so the questions are edited as
 * questions here and the JSON is kept underneath, still reachable for anyone
 * who prefers it.
 *
 * The rule this file follows: the JSON textarea is the single source of truth
 * that gets saved. Every edit writes back to it immediately, so the form has
 * nothing to reconcile at submit time and the JSON view is never stale.
 *
 * LINE's own limits shape the editing rules, not our preferences: at most 13
 * quick reply buttons on a message, and the plugin spends one of those on
 * Cancel, so a question can offer 12 choices; a button label is cut at 20
 * characters. Both are enforced by the sender silently, which is why they are
 * surfaced here instead.
 */

(function ($) {
	'use strict';

	var MAX_CHOICES = 12;
	var MAX_LABEL = 20;

	function strings() {
		return (window.moksaLine && window.moksaLine.strings) || {};
	}

	function t(key, fallback) {
		var value = strings()[key];

		return undefined === value ? fallback : value;
	}

	/**
	 * A key derived from the prompt.
	 *
	 * The key is a column name in the submissions table and in the notification
	 * email, so it wants to be readable. Non-ASCII prompts are common here and
	 * would slugify to nothing, so those fall back to a numbered key rather
	 * than an empty one.
	 */
	function keyFrom(prompt, index) {
		var slug = String(prompt || '')
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '_')
			.replace(/^_+|_+$/g, '')
			.slice(0, 24);

		return slug || 'field_' + (index + 1);
	}

	function parse(raw) {
		var data;

		try {
			data = JSON.parse(raw);
		} catch (e) {
			return null;
		}

		if (!data || typeof data !== 'object') {
			return null;
		}

		return {
			steps: $.isArray(data.steps) ? data.steps : [],
			complete_message: data.complete_message || ''
		};
	}

	function FlowEditor(root) {
		this.$root = $(root);
		this.$json = $(this.$root.data('moksa-flow-editor'));
		this.$steps = this.$root.find('[data-moksa-flow-steps]');
		this.$warnings = this.$root.find('[data-moksa-flow-warnings]');
		this.$preview = $('[data-moksa-flow-preview]');
		this.$complete = $('[data-moksa-flow-complete]');
		this.types = this.$root.data('types') || { text: 'Text' };

		this.model = { steps: [], complete_message: '' };
		this.bind();
		this.read();
	}

	/**
	 * Pull the model out of the JSON textarea.
	 *
	 * Invalid JSON is left alone rather than replaced: someone editing by hand
	 * passes through invalid states constantly, and silently resetting their
	 * work to an empty flow would be the worst possible response.
	 */
	FlowEditor.prototype.read = function () {
		var parsed = parse(this.$json.val() || '{}');

		if (null === parsed) {
			this.$warnings.html($('<ul class="moksa-preview-warnings__list"></ul>').append(
				$('<li></li>').text(t('flowBadJson', 'That JSON cannot be read, so the questions below are not showing it.'))
			));
			return;
		}

		this.model = parsed;
		this.$complete.val(this.model.complete_message);
		this.render();

		// Refresh what is derived from the model, but do not write back: this
		// path runs while someone is typing in the JSON, and reformatting their
		// text under the caret would make it unusable. Without this the
		// warnings and the preview keep describing the previous flow.
		this.renderPreview();
		this.renderWarnings();
	};

	/**
	 * Write the model back out. This is what actually gets saved.
	 */
	FlowEditor.prototype.write = function () {
		this.model.complete_message = this.$complete.val() || '';
		this.$json.val(JSON.stringify(this.model, null, 2)).trigger('change');
		this.renderPreview();
		this.renderWarnings();
	};

	FlowEditor.prototype.bind = function () {
		var self = this;

		this.$root.on('click', '[data-moksa-add-step]', function () {
			self.model.steps.push({ key: '', prompt: '', type: 'text' });
			self.render();
			self.write();
			// Land the caret in the new question rather than making them find it.
			self.$steps.find('.moksa-flow-step').last().find('[data-field="prompt"]').trigger('focus');
		});

		this.$root.on('click', '[data-moksa-step-action]', function () {
			var $button = $(this);
			var index = parseInt($button.closest('.moksa-flow-step').data('index'), 10);
			var action = $button.data('moksa-step-action');
			var steps = self.model.steps;

			if ('delete' === action) {
				moksaConfirm(t('flowDeleteStep', 'Remove this question?'), { danger: true, confirmLabel: t('confirmDeleteAction', 'Delete') }).then(function (confirmed) {
					if (!confirmed) {
						return;
					}

					steps.splice(index, 1);
				});
			} else if ('up' === action && index > 0) {
				steps.splice(index - 1, 0, steps.splice(index, 1)[0]);
			} else if ('down' === action && index < steps.length - 1) {
				steps.splice(index + 1, 0, steps.splice(index, 1)[0]);
			} else if ('duplicate' === action) {
				var copy = $.extend(true, {}, steps[index]);
				// A duplicated key would silently overwrite the first answer.
				copy.key = '';
				steps.splice(index + 1, 0, copy);
			} else {
				return;
			}

			self.render();
			self.write();
		});

		this.$root.on('input change', '[data-field]', function () {
			var $field = $(this);
			var index = parseInt($field.closest('.moksa-flow-step').data('index'), 10);
			var step = self.model.steps[index];

			if (!step) {
				return;
			}

			var field = $field.data('field');

			if ('choices' === field) {
				step.choices = String($field.val() || '')
					.split('\n')
					.map(function (line) { return $.trim(line); })
					.filter(function (line) { return '' !== line; });
			} else if ('key' === field) {
				step.key = $.trim($field.val() || '');
			} else {
				step[field] = $field.val();
			}

			if ('type' === field) {
				// Choices only mean anything on a choice step; carrying them on
				// a text step would put them back the next time it is switched.
				if ('choice' !== step.type) {
					delete step.choices;
				}

				self.render();
			}

			if ('prompt' === field && '' === step.key) {
				self.$steps.find('.moksa-flow-step[data-index="' + index + '"] [data-field="key"]')
					.attr('placeholder', keyFrom(step.prompt, index));
			}

			self.write();
		});

		this.$complete.on('input', function () {
			self.write();
		});

		// Hand-edited JSON has to flow back into the cards, or the two views
		// disagree and whichever is written last wins by accident.
		this.$json.on('input', function () {
			self.read();
		});

		$('#moksa-flow-trigger, #moksa-flow-trigger-type').on('input change', function () {
			self.renderPreview();
		});

		$(document).on('moksa:flow-loaded', function () {
			self.read();
		});
	};

	FlowEditor.prototype.render = function () {
		var self = this;
		this.$steps.empty();

		if (!this.model.steps.length) {
			this.$steps.append(
				$('<p class="moksa-flow-empty"></p>').text(
					t('flowNoSteps', 'No questions yet. Add the first one below.')
				)
			);
			return;
		}

		$.each(this.model.steps, function (index, step) {
			self.$steps.append(self.card(index, step));
		});
	};

	FlowEditor.prototype.card = function (index, step) {
		var self = this;
		var $card = $('<div class="moksa-flow-step"></div>').attr('data-index', index);

		var $head = $('<div class="moksa-flow-step__head"></div>')
			.append($('<span class="moksa-flow-step__number"></span>').text(index + 1));

		var buttons = [
			['up', '↑', t('flowMoveUp', 'Move up'), 0 === index],
			['down', '↓', t('flowMoveDown', 'Move down'), index === this.model.steps.length - 1],
			['duplicate', '⧉', t('flowDuplicate', 'Duplicate'), false],
			['delete', '×', t('flowDeleteStep', 'Remove this question?'), false]
		];

		var $actions = $('<span class="moksa-flow-step__actions"></span>');

		$.each(buttons, function (i, spec) {
			$actions.append(
				$('<button type="button" class="button-link"></button>')
					.attr('data-moksa-step-action', spec[0])
					.attr('aria-label', spec[2])
					.attr('title', spec[2])
					.prop('disabled', spec[3])
					.text(spec[1])
			);
		});

		$head.append($actions);
		$card.append($head);

		$card.append(
			$('<label class="moksa-flow-step__label"></label>')
				.text(t('flowPrompt', 'What the bot asks'))
				.append(
					$('<textarea rows="2" class="widefat"></textarea>')
						.attr('data-field', 'prompt')
						.val(step.prompt || '')
				)
		);

		var $type = $('<select class="widefat"></select>').attr('data-field', 'type');

		$.each(this.types, function (value, label) {
			$type.append($('<option></option>').attr('value', value).text(label).prop('selected', (step.type || 'text') === value));
		});

		var $row = $('<div class="moksa-flow-step__row"></div>')
			.append($('<label class="moksa-flow-step__label"></label>').text(t('flowAnswerType', 'Answer')).append($type))
			.append(
				$('<label class="moksa-flow-step__label"></label>')
					.text(t('flowKey', 'Stored as'))
					.append(
						$('<input type="text" class="widefat" />')
							.attr('data-field', 'key')
							.attr('placeholder', keyFrom(step.prompt, index))
							.val(step.key || '')
					)
			);

		$card.append($row);

		if ('choice' === step.type) {
			$card.append(
				$('<label class="moksa-flow-step__label"></label>')
					.text(t('flowChoices', 'Buttons, one per line'))
					.append(
						$('<textarea rows="3" class="widefat"></textarea>')
							.attr('data-field', 'choices')
							.val(($.isArray(step.choices) ? step.choices : []).join('\n'))
					)
			);
		}

		return $card;
	};

	/**
	 * Problems LINE would enforce silently, or that would quietly lose data.
	 */
	FlowEditor.prototype.renderWarnings = function () {
		var problems = [];
		var seenKeys = {};

		$.each(this.model.steps, function (index, step) {
			var number = index + 1;
			var key = step.key || keyFrom(step.prompt, index);

			if (!$.trim(step.prompt || '')) {
				problems.push(t('flowNoPrompt', 'Question %d has nothing to ask.').replace('%d', number));
			}

			if (seenKeys[key]) {
				problems.push(
					t('flowDuplicateKey', 'Questions %1$d and %2$d both store their answer as "%3$s", so the second overwrites the first.')
						.replace('%1$d', seenKeys[key]).replace('%2$d', number).replace('%3$s', key)
				);
			} else {
				seenKeys[key] = number;
			}

			if ('choice' === step.type) {
				var choices = $.isArray(step.choices) ? step.choices : [];

				if (!choices.length) {
					problems.push(t('flowNoChoices', 'Question %d offers buttons but none are listed.').replace('%d', number));
				}

				if (choices.length > MAX_CHOICES) {
					problems.push(
						t('flowTooManyChoices', 'Question %1$d has %2$d buttons. LINE allows 13 including the Cancel button, so only the first %3$d are sent.')
							.replace('%1$d', number).replace('%2$d', choices.length).replace('%3$d', MAX_CHOICES)
					);
				}

				$.each(choices, function (i, choice) {
					var length = window.Array && Array.from ? Array.from(choice).length : choice.length;

					if (length > MAX_LABEL) {
						problems.push(
							t('flowLongChoice', 'Button "%1$s" on question %2$d is longer than the %3$d characters LINE shows, and is cut.')
								.replace('%1$s', choice).replace('%2$d', number).replace('%3$d', MAX_LABEL)
						);
					}
				});
			}
		});

		if (!problems.length) {
			this.$warnings.empty();
			return;
		}

		var $list = $('<ul class="moksa-preview-warnings__list"></ul>');

		$.each(problems, function (i, problem) {
			$list.append($('<li></li>').text(problem));
		});

		this.$warnings.empty().append($list);
	};

	/**
	 * The conversation as the customer walks through it.
	 *
	 * Answers are shown as the sample the validator would accept, so the shop
	 * can see that asking for a date does in fact produce a date.
	 */
	FlowEditor.prototype.renderPreview = function () {
		if (!this.$preview.length) {
			return;
		}

		var self = this;
		this.$preview.empty();

		var trigger = $.trim($('#moksa-flow-trigger').val() || '');

		if (trigger) {
			this.$preview.append($('<div class="moksa-bubble moksa-bubble--sent"></div>').text(trigger));
		}

		if (!this.model.steps.length) {
			this.$preview.append(
				$('<p class="moksa-bubble moksa-bubble--empty"></p>').text(
					t('flowNoSteps', 'No questions yet. Add the first one below.')
				)
			);
			return;
		}

		var samples = {
			text: t('flowSampleText', 'Their answer'),
			number: '2',
			phone: '0912345678',
			email: 'someone@example.com',
			date: '2026-03-15'
		};

		$.each(this.model.steps, function (index, step) {
			var prompt = $.trim(step.prompt || '') || t('flowPromptMissing', '(no question yet)');
			var $bubble = $('<div class="moksa-bubble"></div>').text(prompt);
			var $row = $('<div class="moksa-bubble-row"></div>')
				.append($('<span class="moksa-phone-chat__avatar" aria-hidden="true"></span>'));

			var $wrap = $('<div class="moksa-bubble-stack"></div>').append($bubble);

			// Quick replies sit under the message in LINE, not inside it.
			var choices = 'choice' === step.type && $.isArray(step.choices) ? step.choices : [];
			var shown = choices.slice(0, MAX_CHOICES);

			if ('choice' === step.type) {
				var $quick = $('<div class="moksa-quick"></div>');

				$.each(shown, function (i, choice) {
					$quick.append($('<span class="moksa-quick__button"></span>').text(choice));
				});

				$quick.append(
					$('<span class="moksa-quick__button moksa-quick__button--cancel"></span>')
						.text(t('flowCancel', 'Cancel'))
				);

				$wrap.append($quick);
			}

			$row.append($wrap);
			self.$preview.append($row);

			var answer = 'choice' === step.type
				? (shown.length ? shown[0] : t('flowSampleText', 'Their answer'))
				: (samples[step.type] || samples.text);

			self.$preview.append($('<div class="moksa-bubble moksa-bubble--sent"></div>').text(answer));
		});

		var done = $.trim(this.$complete.val() || '');

		if (done) {
			this.$preview.append(
				$('<div class="moksa-bubble-row"></div>')
					.append($('<span class="moksa-phone-chat__avatar" aria-hidden="true"></span>'))
					.append($('<div class="moksa-bubble"></div>').text(
						done
							.split('{display_name}').join(t('sampleName', 'Ming'))
							.split('{site_name}').join((window.moksaLine && window.moksaLine.siteName) || '')
							.split('{site_url}').join((window.moksaLine && window.moksaLine.siteUrl) || '')
					))
			);
		}
	};

	$(function () {
		$('[data-moksa-flow-editor]').each(function () {
			var editor = new FlowEditor(this);
			editor.write();
		});

		$(document).on('click', '[data-moksa-toggle-flow-json]', function () {
			var $json = $('[data-moksa-flow-definition]');
			$json.prop('hidden', !$json.prop('hidden'));
		});
	});
}(jQuery));
