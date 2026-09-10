/**
 * Template message editor.
 *
 * LINE's template messages are the fixed-layout counterpart to Flex: a card,
 * a question, or a row of cards, each with buttons. They exist so a shop can
 * get a card with two buttons without learning a layout language, so the
 * editor must not hand the JSON back to them either.
 *
 * The shape switches -- image, title, how many buttons -- belong to the whole
 * carousel rather than to each card, and that is not a simplification. LINE
 * refuses a carousel whose columns disagree:
 *
 *   "The use of image, title and the number of actions should be consistent
 *    for all columns."
 *
 * Modelling it per card would let someone build something that cannot be sent
 * and only find out at the end. Here, changing a switch reshapes every card at
 * once, so the thing on screen is always sendable.
 *
 * As elsewhere in this plugin, the JSON textarea is what actually gets saved.
 */

(function ($) {
	'use strict';

	var MAX_COLUMNS = 10;

	function strings() {
		return (window.moksaLine && window.moksaLine.strings) || {};
	}

	function t(key, fallback) {
		var value = strings()[key];

		return undefined === value ? fallback : value;
	}

	function action(label) {
		return { type: 'message', label: label || t('templateButton', 'Button'), text: '' };
	}

	function TemplateEditor(form) {
		this.$form = $(form);
		this.$json = this.$form.find('[data-moksa-template-json]');
		this.$kind = this.$form.find('[data-moksa-template-kind]');
		this.$shape = this.$form.find('[data-moksa-template-shape]');
		this.$cards = this.$form.find('[data-moksa-template-cards]');
		this.$add = this.$form.find('[data-moksa-template-add]');
		this.$warnings = this.$form.find('[data-moksa-template-warnings]');
		this.$preview = $('[data-moksa-template-preview]');
		this.$count = $('[data-moksa-template-count]');

		this.model = this.blank('buttons');
		this.bind();
		this.render();
		this.write();
	}

	/** A usable starting point for each kind. */
	TemplateEditor.prototype.blank = function (kind) {
		if ('confirm' === kind) {
			return {
				type: 'confirm',
				text: '',
				// Exactly two, always: LINE refuses one or three.
				actions: [action(t('templateYes', 'Yes')), action(t('templateNo', 'No'))]
			};
		}

		if ('image_carousel' === kind) {
			return { type: 'image_carousel', columns: [{ imageUrl: '', action: action() }] };
		}

		if ('carousel' === kind) {
			return { type: 'carousel', columns: [this.blankColumn(), this.blankColumn()] };
		}

		return { type: 'buttons', title: '', text: '', actions: [action()] };
	};

	TemplateEditor.prototype.blankColumn = function () {
		return { title: '', text: '', actions: [action()] };
	};

	/** The shape the carousel's cards currently share. */
	TemplateEditor.prototype.shape = function () {
		var first = (this.model.columns || [])[0] || {};

		return {
			image: undefined !== first.thumbnailImageUrl,
			title: undefined !== first.title,
			buttons: (first.actions || []).length || 1
		};
	};

	/**
	 * Force every card into the same shape.
	 *
	 * This is the whole reason the switches are shared, so it runs on every
	 * change rather than only when a switch is touched -- adding a card must
	 * not be able to introduce a mismatch either.
	 */
	TemplateEditor.prototype.reshape = function (shape) {
		var self = this;

		$.each(this.model.columns || [], function (i, column) {
			if (shape.image) {
				if (undefined === column.thumbnailImageUrl) {
					column.thumbnailImageUrl = '';
				}
			} else {
				delete column.thumbnailImageUrl;
			}

			if (shape.title) {
				if (undefined === column.title) {
					column.title = '';
				}
			} else {
				delete column.title;
			}

			column.actions = column.actions || [];

			while (column.actions.length < shape.buttons) {
				column.actions.push(action());
			}

			column.actions.length = shape.buttons;
			self.model.columns[i] = column;
		});
	};

	TemplateEditor.prototype.bind = function () {
		var self = this;

		this.$kind.on('change', function () {
			self.model = self.blank($(this).val());
			self.render();
			self.write();
		});

		this.$shape.on('change', '[data-moksa-shape]', function () {
			self.reshape({
				image: self.$shape.find('[data-moksa-shape="image"]').is(':checked'),
				title: self.$shape.find('[data-moksa-shape="title"]').is(':checked'),
				buttons: parseInt(self.$shape.find('[data-moksa-shape="buttons"]').val(), 10) || 1
			});
			self.render();
			self.write();
		});

		this.$form.on('click', '[data-moksa-add-template-card]', function () {
			var columns = self.model.columns || [];

			if (columns.length >= MAX_COLUMNS) {
				window.alert(t('templateTooMany', 'A carousel holds at most 10 cards.'));
				return;
			}

			columns.push('image_carousel' === self.model.type
				? { imageUrl: '', action: action() }
				: self.blankColumn());

			self.model.columns = columns;
			// A new card must match the others or LINE refuses the lot.
			if ('carousel' === self.model.type) {
				self.reshape(self.shape());
			}

			self.render();
			self.write();
		});

		this.$cards.on('click', '[data-moksa-card-op]', function () {
			var $button = $(this);
			var index = parseInt($button.closest('[data-card]').data('card'), 10);
			var op = $button.data('moksa-card-op');
			var columns = self.model.columns || [];

			if ('remove' === op) {
				if (columns.length <= 1) {
					window.alert(t('templateLastCard', 'A template needs at least one card.'));
					return;
				}

				columns.splice(index, 1);
			} else if ('left' === op && index > 0) {
				columns.splice(index - 1, 0, columns.splice(index, 1)[0]);
			} else if ('right' === op && index < columns.length - 1) {
				columns.splice(index + 1, 0, columns.splice(index, 1)[0]);
			} else {
				return;
			}

			self.render();
			self.write();
		});

		this.$form.on('input change', '[data-tpl]', function () {
			self.apply($(this));
			self.write();
		});

		this.$form.on('click', '[data-moksa-toggle-template-json]', function () {
			self.$json.prop('hidden', !self.$json.prop('hidden'));
		});

		// Hand-edited JSON flows back, as on the other editors here.
		this.$json.on('input', function () {
			if (self.writing) {
				return;
			}

			try {
				var parsed = JSON.parse($(this).val());

				if (parsed && parsed.type) {
					self.model = parsed;
					self.$kind.val(parsed.type);
					self.render();
					self.renderPreview();
					self.renderWarnings();
				}
			} catch (e) {
				// Mid-typing is invalid most of the time; leave the cards alone.
			}
		});

		$(document).on('moksa:template-loaded', function () {
			self.read();
		});
	};

	/** Read one field back into the model. */
	TemplateEditor.prototype.apply = function ($field) {
		var path = String($field.data('tpl')).split('.');
		var value = $field.val();
		var node = this.model;

		for (var i = 0; i < path.length - 1; i++) {
			var key = path[i];

			if (/^\d+$/.test(key)) {
				key = parseInt(key, 10);
			}

			if (undefined === node[key]) {
				return;
			}

			node = node[key];
		}

		node[path[path.length - 1]] = value;
	};

	TemplateEditor.prototype.read = function () {
		try {
			var parsed = JSON.parse(this.$json.val() || '{}');

			if (parsed && parsed.type) {
				this.model = parsed;
				this.$kind.val(parsed.type);
			}
		} catch (e) {
			return;
		}

		this.render();
		this.renderPreview();
		this.renderWarnings();
	};

	TemplateEditor.prototype.write = function () {
		this.writing = true;
		this.$json.val(JSON.stringify(this.model, null, 2));
		this.writing = false;
		this.renderPreview();
		this.renderWarnings();
	};

	function field(label, path, value, type) {
		var $control = 'textarea' === type
			? $('<textarea rows="2" class="widefat"></textarea>')
			: $('<input class="widefat" />').attr('type', type || 'text');

		return $('<label class="moksa-tpl-field"></label>')
			.text(label)
			.append($control.attr('data-tpl', path).val(value || ''));
	}

	TemplateEditor.prototype.render = function () {
		var self = this;
		var kind = this.model.type;

		this.$cards.empty();
		this.$shape.prop('hidden', 'carousel' !== kind);
		this.$add.prop('hidden', 'carousel' !== kind && 'image_carousel' !== kind);

		if ('carousel' === kind) {
			var shape = this.shape();
			this.$shape.find('[data-moksa-shape="image"]').prop('checked', shape.image);
			this.$shape.find('[data-moksa-shape="title"]').prop('checked', shape.title);
			this.$shape.find('[data-moksa-shape="buttons"]').val(String(shape.buttons));
		}

		if ('confirm' === kind) {
			this.$cards.append(field(t('templateQuestion', 'The question'), 'text', this.model.text, 'textarea'));
			this.$cards.append(this.actionFields(this.model.actions, 'actions'));
			return;
		}

		if ('buttons' === kind) {
			this.$cards.append(field(t('cardHero', 'Image URL'), 'thumbnailImageUrl', this.model.thumbnailImageUrl, 'url'));
			this.$cards.append(field(t('cardTitle', 'Headline'), 'title', this.model.title, 'text'));
			this.$cards.append(field(t('templateText', 'Text'), 'text', this.model.text, 'textarea'));
			this.$cards.append(this.actionFields(this.model.actions, 'actions', 4));
			return;
		}

		$.each(this.model.columns || [], function (index, column) {
			var $card = $('<div class="moksa-tpl-card"></div>').attr('data-card', index);

			$card.append(
				$('<div class="moksa-tpl-card__head"></div>')
					.append($('<span class="moksa-flow-step__number"></span>').text(index + 1))
					.append(
						$('<span class="moksa-flow-step__actions"></span>')
							.append(self.op('left', '←', t('cardMoveLeft', 'Move left'), 0 === index))
							.append(self.op('right', '→', t('cardMoveRight', 'Move right'), index === (self.model.columns.length - 1)))
							.append(self.op('remove', '×', t('cardRemove', 'Remove this card?'), false))
					)
			);

			if ('image_carousel' === kind) {
				$card.append(field(t('cardHero', 'Image URL'), 'columns.' + index + '.imageUrl', column.imageUrl, 'url'));
				$card.append(self.actionFields([column.action], 'columns.' + index + '.action', 1, true));
			} else {
				if (undefined !== column.thumbnailImageUrl) {
					$card.append(field(t('cardHero', 'Image URL'), 'columns.' + index + '.thumbnailImageUrl', column.thumbnailImageUrl, 'url'));
				}

				if (undefined !== column.title) {
					$card.append(field(t('cardTitle', 'Headline'), 'columns.' + index + '.title', column.title, 'text'));
				}

				$card.append(field(t('templateText', 'Text'), 'columns.' + index + '.text', column.text, 'textarea'));
				$card.append(self.actionFields(column.actions, 'columns.' + index + '.actions'));
			}

			self.$cards.append($card);
		});
	};

	TemplateEditor.prototype.op = function (name, glyph, label, disabled) {
		return $('<button type="button" class="button-link"></button>')
			.attr('data-moksa-card-op', name)
			.attr('aria-label', label)
			.attr('title', label)
			.prop('disabled', disabled)
			.text(glyph);
	};

	/**
	 * Fields for a set of buttons.
	 *
	 * @param {Array}   actions Action objects.
	 * @param {string}  path    Where they live in the model.
	 * @param {number}  max     How many the kind allows, when it can vary.
	 * @param {boolean} single  Whether the path points at one action, not a list.
	 */
	TemplateEditor.prototype.actionFields = function (actions, path, max, single) {
		var self = this;
		var $wrap = $('<div class="moksa-tpl-actions"></div>');

		$.each(actions || [], function (index, act) {
			act = act || {};
			var base = single ? path : path + '.' + index;
			var $row = $('<div class="moksa-tpl-action"></div>');

			var $type = $('<select></select>').attr('data-tpl', base + '.type');

			$.each(
				{
					message: t('actionMessage', 'Send a message'),
					uri: t('actionUri', 'Open a link'),
					postback: t('actionPostback', 'Postback')
				},
				function (value, label) {
					$type.append($('<option></option>').attr('value', value).text(label).prop('selected', (act.type || 'message') === value));
				}
			);

			$row.append($('<label class="moksa-tpl-field"></label>').text(t('actionLabel', 'Label'))
				.append($('<input type="text" class="widefat" />').attr('data-tpl', base + '.label').val(act.label || '')));
			$row.append($('<label class="moksa-tpl-field"></label>').text(t('actionType', 'When tapped')).append($type));

			if ('uri' === act.type) {
				$row.append($('<label class="moksa-tpl-field"></label>').text(t('actionUriValue', 'Link'))
					.append($('<input type="url" class="widefat" />').attr('data-tpl', base + '.uri').val(act.uri || '')));
			} else if ('postback' === act.type) {
				$row.append($('<label class="moksa-tpl-field"></label>').text(t('actionData', 'Postback data'))
					.append($('<input type="text" class="widefat" />').attr('data-tpl', base + '.data').val(act.data || '')));
			} else {
				$row.append($('<label class="moksa-tpl-field"></label>').text(t('actionText', 'Message the customer sends'))
					.append($('<input type="text" class="widefat" />').attr('data-tpl', base + '.text').val(act.text || '')));
			}

			$wrap.append($row);
		});

		// Buttons templates are the only kind whose button count is free.
		if (max && !single && (actions || []).length < max) {
			$wrap.append(
				$('<button type="button" class="button button-small"></button>')
					.text(t('templateAddButton', '+ Add a button'))
					.on('click', function () {
						self.model.actions.push(action());
						self.render();
						self.write();
					})
			);
		}

		return $wrap;
	};

	/** Draw it the way the customer will see it. */
	TemplateEditor.prototype.renderPreview = function () {
		if (!this.$preview.length) {
			return;
		}

		var kind = this.model.type;
		var self = this;
		this.$preview.empty();

		function card(column, imageOnly) {
            var $card = $('<div class="moksa-tpl-preview-card"></div>');
			var image = imageOnly ? column.imageUrl : column.thumbnailImageUrl;

			if (image) {
				$card.append($('<div class="moksa-tpl-preview-card__image"></div>')
					.append($('<img alt="" />').attr('src', image)));
			}

			if (!imageOnly) {
				var $body = $('<div class="moksa-tpl-preview-card__body"></div>');

				if (column.title) {
					$body.append($('<strong></strong>').text(column.title));
				}

				$body.append($('<span></span>').text(column.text || ''));
				$card.append($body);

				var $buttons = $('<div class="moksa-tpl-preview-card__buttons"></div>');

				$.each(column.actions || [], function (i, act) {
					$buttons.append($('<span></span>').text((act && act.label) || ''));
				});

				$card.append($buttons);
			}

			return $card;
		}

		if ('confirm' === kind) {
			var $confirm = $('<div class="moksa-tpl-preview-card"></div>');
			$confirm.append($('<div class="moksa-tpl-preview-card__body"></div>')
				.append($('<span></span>').text(this.model.text || '')));
			var $row = $('<div class="moksa-tpl-preview-card__buttons moksa-tpl-preview-card__buttons--split"></div>');

			$.each(this.model.actions || [], function (i, act) {
				$row.append($('<span></span>').text((act && act.label) || ''));
			});

			$confirm.append($row);
			this.$preview.append($confirm);
		} else if ('buttons' === kind) {
			this.$preview.append(card(this.model, false));
		} else {
			var $strip = $('<div class="moksa-tpl-preview-strip"></div>');

			$.each(this.model.columns || [], function (i, column) {
				$strip.append(card(column || {}, 'image_carousel' === kind));
			});

			this.$preview.append($strip);
		}

		var count = (this.model.columns || []).length;

		this.$count
			.text(count > 1 ? t('carouselCount', '%d cards.').replace('%d', count) : '')
			.prop('hidden', count < 2);
	};

	/** The same problems the server refuses to save, shown while typing. */
	TemplateEditor.prototype.renderWarnings = function () {
		var self = this;

		if (!this.$warnings.length) {
			return;
		}

		clearTimeout(this.warnTimer);

		this.warnTimer = setTimeout(function () {
			$.post(moksaLine.ajaxUrl, {
				action: 'moksa_line_template_warnings',
				nonce: moksaLine.nonce,
				definition: JSON.stringify(self.model)
			}).done(function (response) {
				var problems = (response && response.data && response.data.problems) || [];

				if (!problems.length) {
					self.$warnings.empty();
					return;
				}

				var $list = $('<ul class="moksa-preview-warnings__list"></ul>');

				$.each(problems, function (i, problem) {
					$list.append($('<li></li>').text(problem));
				});

				self.$warnings.empty().append($list);
			});
		}, 500);
	};

	$(function () {
		$('[data-moksa-template-form]').each(function () {
			$(this).data('moksaTemplateEditor', new TemplateEditor(this));
		});
	});
}(jQuery));
