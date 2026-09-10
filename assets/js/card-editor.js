/**
 * Flex card and carousel editor.
 *
 * The Flex screen could render and validate a carousel but gave no way to
 * build one: the only route to a second card was hand-writing another bubble
 * into a nested array. A product carousel is the single most common thing a
 * shop wants from Flex, so it is now assembled from cards.
 *
 * Two levels of editing, deliberately:
 *
 * - Structure, which always works: add, reorder, duplicate, remove, and the
 *   switch between one card and a carousel.
 * - Fields, which only appear for a card shaped like the built-in ones -- a
 *   hero image, a headline, a line of text, a button. Flex can express far
 *   more than that, and quietly reshaping someone's custom layout into the
 *   four fields this form knows about would destroy it. Anything unrecognised
 *   says so and sends them to the JSON.
 *
 * The JSON textarea stays the source of truth that gets saved, as on the rich
 * menu and flow editors, so the two views can never disagree about what will
 * be sent.
 */

(function ($) {
	'use strict';

	/** LINE's limit. The validator enforces the same number server-side. */
	var MAX_BUBBLES = 12;

	function strings() {
		return (window.moksaLine && window.moksaLine.strings) || {};
	}

	function t(key, fallback) {
		var value = strings()[key];

		return undefined === value ? fallback : value;
	}

	/**
	 * A blank card in the shape the field editor understands.
	 */
	function blankBubble() {
		return {
			type: 'bubble',
			body: {
				type: 'box',
				layout: 'vertical',
				spacing: 'sm',
				contents: [
					{ type: 'text', text: t('cardNewTitle', 'New card'), weight: 'bold', size: 'xl', wrap: true },
					{ type: 'text', text: '', size: 'sm', color: '#767676', wrap: true }
				]
			}
		};
	}

	/**
	 * Locate the pieces the field editor edits, or report that it cannot.
	 *
	 * Returns null when the card is not shaped like a built-in one. Being
	 * strict here is the point: a false match would let the form overwrite
	 * part of a layout it does not understand.
	 */
	function readable(bubble) {
		if (!bubble || 'bubble' !== bubble.type) {
			return null;
		}

		var body = bubble.body;

		if (!body || !$.isArray(body.contents)) {
			return null;
		}

		var texts = body.contents.filter(function (item) {
			return item && 'text' === item.type;
		});

		if (!texts.length) {
			return null;
		}

		var hero = bubble.hero && 'image' === bubble.hero.type ? bubble.hero : null;
		var button = null;

		if (bubble.footer && $.isArray(bubble.footer.contents)) {
			$.each(bubble.footer.contents, function (i, item) {
				if (item && 'button' === item.type && item.action) {
					button = item;
					return false;
				}
			});
		}

		return {
			hero: hero,
			title: texts[0],
			body: texts.length > 1 ? texts[1] : null,
			button: button
		};
	}

	function CardEditor(root) {
		this.$root = $(root);
		this.$json = $(this.$root.data('moksa-card-editor'));
		this.$list = this.$root.find('[data-moksa-card-list]');
		this.$fields = this.$root.find('[data-moksa-card-fields]');
		this.$add = this.$root.find('[data-moksa-add-card]');
		this.$single = this.$root.find('[data-moksa-single-card]');

		this.bubbles = [];
		this.isCarousel = false;
		this.selected = 0;
		this.valid = true;

		this.bind();
		this.read();
	}

	/**
	 * Pull the cards out of the JSON.
	 *
	 * Unreadable JSON leaves the cards alone rather than emptying them: someone
	 * typing in the textarea is invalid most of the time they are typing, and
	 * wiping their work in response would be the worst thing this could do.
	 */
	CardEditor.prototype.read = function () {
		var raw = $.trim(this.$json.val() || '');
		var data;

		if ('' === raw) {
			this.bubbles = [];
			this.isCarousel = false;
			this.valid = true;
			this.render();
			return;
		}

		try {
			data = JSON.parse(raw);
		} catch (e) {
			this.valid = false;
			this.render();
			return;
		}

		this.valid = true;

		if (data && 'carousel' === data.type) {
			this.isCarousel = true;
			this.bubbles = $.isArray(data.contents) ? data.contents : [];
		} else if (data && 'bubble' === data.type) {
			this.isCarousel = false;
			this.bubbles = [data];
		} else {
			// Something that is neither: leave it to the JSON view entirely.
			this.valid = false;
			this.render();
			return;
		}

		if (this.selected >= this.bubbles.length) {
			this.selected = Math.max(0, this.bubbles.length - 1);
		}

		this.render();
	};

	/**
	 * Write the cards back out. This is what gets saved.
	 */
	CardEditor.prototype.write = function () {
		var payload = this.isCarousel
			? { type: 'carousel', contents: this.bubbles }
			: (this.bubbles[0] || blankBubble());

		this.$json.val(JSON.stringify(payload, null, 2)).trigger('input');
	};

	CardEditor.prototype.bind = function () {
		var self = this;

		this.$add.on('click', function () {
			if (self.bubbles.length >= MAX_BUBBLES) {
				window.alert(t('cardTooMany', 'A carousel holds at most 12 cards.'));
				return;
			}

			// The first extra card is what turns one card into a carousel.
			if (self.bubbles.length >= 1) {
				self.isCarousel = true;
			}

			self.bubbles.push(blankBubble());
			self.selected = self.bubbles.length - 1;
			self.write();
			self.render();
		});

		this.$single.on('click', function () {
			if (self.bubbles.length > 1 && !window.confirm(
				t('cardDropOthers', 'Keep only the card you are editing and remove the rest?')
			)) {
				return;
			}

			self.bubbles = [self.bubbles[self.selected] || blankBubble()];
			self.isCarousel = false;
			self.selected = 0;
			self.write();
			self.render();
		});

		this.$list.on('click', '[data-moksa-select-card]', function () {
			self.selected = parseInt($(this).data('moksa-select-card'), 10);
			self.render();
		});

		this.$list.on('click', '[data-moksa-card-action]', function (event) {
			event.stopPropagation();

			var $button = $(this);
			var index = parseInt($button.closest('.moksa-card-chip').data('index'), 10);
			var action = $button.data('moksa-card-action');

			if ('remove' === action) {
				if (!window.confirm(t('cardRemove', 'Remove this card?'))) {
					return;
				}

				self.bubbles.splice(index, 1);

				if (self.bubbles.length <= 1) {
					self.isCarousel = false;
				}

				self.selected = Math.max(0, Math.min(self.selected, self.bubbles.length - 1));
			} else if ('left' === action && index > 0) {
				self.bubbles.splice(index - 1, 0, self.bubbles.splice(index, 1)[0]);
				self.selected = index - 1;
			} else if ('right' === action && index < self.bubbles.length - 1) {
				self.bubbles.splice(index + 1, 0, self.bubbles.splice(index, 1)[0]);
				self.selected = index + 1;
			} else if ('duplicate' === action) {
				if (self.bubbles.length >= MAX_BUBBLES) {
					window.alert(t('cardTooMany', 'A carousel holds at most 12 cards.'));
					return;
				}

				self.bubbles.splice(index + 1, 0, $.extend(true, {}, self.bubbles[index]));
				self.isCarousel = true;
				self.selected = index + 1;
			} else {
				return;
			}

			self.write();
			self.render();
		});

		this.$fields.on('input change', '[data-card-field]', function () {
			self.apply($(this).data('card-field'), $(this).val());
		});

		// Hand-edited JSON has to flow back into the cards, or the two views
		// disagree and whichever is written last wins by accident.
		this.$json.on('input', function () {
			if (self.writing) {
				return;
			}

			self.read();
		});

		$(document).on('moksa:flex-loaded', function () {
			self.selected = 0;
			self.read();
		});
	};

	/**
	 * Write one field back into the selected card.
	 */
	CardEditor.prototype.apply = function (field, value) {
		var bubble = this.bubbles[this.selected];
		var parts = readable(bubble);

		if (!parts) {
			return;
		}

		value = String(value);

		if ('title' === field) {
			parts.title.text = value;
		} else if ('body' === field) {
			if (parts.body) {
				parts.body.text = value;
			} else if ('' !== value) {
				// The second line may not exist yet on a card that never had one.
				bubble.body.contents.push({ type: 'text', text: value, size: 'sm', color: '#767676', wrap: true });
			}
		} else if ('hero' === field) {
			if ('' === $.trim(value)) {
				delete bubble.hero;
			} else {
				bubble.hero = $.extend(
					{ type: 'image', size: 'full', aspectRatio: '20:13', aspectMode: 'cover' },
					bubble.hero || {},
					{ type: 'image', url: value }
				);
			}
		} else if ('buttonLabel' === field || 'buttonUri' === field) {
			this.applyButton(bubble, parts, field, value);
		}

		this.writing = true;
		this.write();
		this.writing = false;

		// Only the chip label can change from a field edit, so the whole list is
		// redrawn but the field inputs are left alone -- rebuilding them would
		// move the caret to the end on every keystroke.
		this.renderList();
	};

	CardEditor.prototype.applyButton = function (bubble, parts, field, value) {
		var button = parts.button;

		if (!button) {
			if ('' === $.trim(value)) {
				return;
			}

			button = {
				type: 'button',
				style: 'primary',
				// Same darker green as the starter templates: white on LINE's
				// brand green is 2.3:1, which is unreadable for small text.
				color: '#06843A',
				action: { type: 'uri', label: t('cardButton', 'Find out more'), uri: '' }
			};

			bubble.footer = bubble.footer || { type: 'box', layout: 'vertical', contents: [] };
			bubble.footer.contents = bubble.footer.contents || [];
			bubble.footer.contents.push(button);
		}

		if ('buttonLabel' === field) {
			button.action.label = value;
		} else {
			button.action.uri = value;
		}

		// A button with no label and no link is dead weight in the message.
		if ('' === $.trim(button.action.label || '') && '' === $.trim(button.action.uri || '')) {
			bubble.footer.contents = bubble.footer.contents.filter(function (item) {
				return item !== button;
			});

			if (!bubble.footer.contents.length) {
				delete bubble.footer;
			}
		}
	};

	CardEditor.prototype.render = function () {
		this.renderList();
		this.renderFields();

		this.$single.prop('hidden', !this.isCarousel);
		this.$add.prop('disabled', this.bubbles.length >= MAX_BUBBLES);
	};

	/**
	 * A short label for a card, so the strip is readable at a glance.
	 */
	function chipLabel(bubble, index) {
		var parts = readable(bubble);
		var text = parts && parts.title ? $.trim(parts.title.text || '') : '';

		if (!text) {
			return t('cardUntitled', 'Card %d').replace('%d', index + 1);
		}

		return text.length > 22 ? text.slice(0, 22) + '…' : text;
	}

	CardEditor.prototype.renderList = function () {
		var self = this;
		this.$list.empty();

		if (!this.valid) {
			this.$list.append(
				$('<p class="moksa-cards-note"></p>').text(
					t('cardUnreadable', 'This message is not a card or a carousel, so it can only be edited as JSON.')
				)
			);
			return;
		}

		if (!this.bubbles.length) {
			this.$list.append(
				$('<p class="moksa-cards-note"></p>').text(t('cardNone', 'No cards yet. Add the first one below.'))
			);
			return;
		}

		$.each(this.bubbles, function (index, bubble) {
			var $chip = $('<div class="moksa-card-chip"></div>')
				.attr('data-index', index)
				.attr('data-moksa-select-card', index)
				.toggleClass('is-selected', index === self.selected);

			$chip.append($('<span class="moksa-card-chip__number"></span>').text(index + 1));
			$chip.append($('<span class="moksa-card-chip__title"></span>').text(chipLabel(bubble, index)));

			var $actions = $('<span class="moksa-card-chip__actions"></span>');
			var buttons = [
				['left', '←', t('cardMoveLeft', 'Move left'), 0 === index],
				['right', '→', t('cardMoveRight', 'Move right'), index === self.bubbles.length - 1],
				['duplicate', '⧉', t('cardDuplicate', 'Duplicate'), false],
				['remove', '×', t('cardRemove', 'Remove this card?'), false]
			];

			$.each(buttons, function (i, spec) {
				$actions.append(
					$('<button type="button" class="button-link"></button>')
						.attr('data-moksa-card-action', spec[0])
						.attr('aria-label', spec[2])
						.attr('title', spec[2])
						.prop('disabled', spec[3])
						.text(spec[1])
				);
			});

			$chip.append($actions);
			self.$list.append($chip);
		});
	};

	CardEditor.prototype.renderFields = function () {
		this.$fields.empty();

		if (!this.valid || !this.bubbles.length) {
			return;
		}

		var bubble = this.bubbles[this.selected];
		var parts = readable(bubble);

		if (!parts) {
			this.$fields.append(
				$('<p class="moksa-cards-note"></p>').text(
					t('cardCustom', 'This card has a layout these fields cannot describe, so it is edited as JSON below. Nothing here will change it.')
				)
			);
			return;
		}

		var self = this;
		var rows = [
			['title', t('cardTitle', 'Headline'), parts.title.text || '', 'text'],
			['body', t('cardBody', 'Text under it'), parts.body ? (parts.body.text || '') : '', 'textarea'],
			['hero', t('cardHero', 'Image URL'), parts.hero ? (parts.hero.url || '') : '', 'url'],
			['buttonLabel', t('cardButtonLabel', 'Button label'), parts.button ? (parts.button.action.label || '') : '', 'text'],
			['buttonUri', t('cardButtonUri', 'Button link'), parts.button ? (parts.button.action.uri || '') : '', 'url']
		];

		var $grid = $('<div class="moksa-card-fields__grid"></div>');

		$.each(rows, function (i, row) {
			var $control = 'textarea' === row[3]
				? $('<textarea rows="2" class="widefat"></textarea>')
				: $('<input class="widefat" />').attr('type', row[3]);

			$control.attr('data-card-field', row[0]).val(row[2]);

			$grid.append(
				$('<label class="moksa-card-fields__label"></label>')
					.text(row[1])
					.append($control)
			);
		});

		this.$fields.append($grid);
		this.$fields.append(
			$('<p class="description"></p>').text(
				t('cardFieldsNote', 'These cover the common card. Anything else -- extra rows, colours, more buttons -- is edited in the JSON.')
			)
		);
	};

	$(function () {
		$('[data-moksa-card-editor]').each(function () {
			new CardEditor(this);
		});

		$(document).on('click', '[data-moksa-toggle-flex-json]', function () {
			var $json = $('[data-moksa-flex-json]');
			$json.prop('hidden', !$json.prop('hidden'));
		});
	});
}(jQuery));
