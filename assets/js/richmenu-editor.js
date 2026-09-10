/**
 * Visual editor for rich menu tap areas.
 *
 * The single most important rule here, and the one that is hardest to notice
 * when it is broken: the rectangle being dragged and the bounds sent to LINE
 * are the same data. Areas are stored in image pixels (2500 wide); the screen
 * is CSS pixels (maybe 700 wide), so there is a scale factor between them.
 * Keeping two copies, or storing CSS pixels, produces areas that look perfect
 * on screen and send the customer to the wrong function when tapped -- a fault
 * that is invisible until someone complains.
 *
 * Ported from the approach used in the kuokuo project's editor, which had
 * already learned these lessons the expensive way.
 */
(function ($) {
	'use strict';

	// Filled in at DOM ready, not here. wp_localize_script attaches moksaLine
	// to the admin handle, and this file is one of that handle's dependencies,
	// so it is printed BEFORE that data exists. Reading it at load time left
	// this object permanently empty, and every string in the area editor fell
	// back to its English default on a site running entirely in Chinese.
	var strings = {};

	/** Areas smaller than this are effectively untappable. */
	var MIN_AREA = 8;

	/** Snap when an edge is within this many image pixels of another edge. */
	var SNAP = 12;

	/** LINE allows at most this many areas on one menu. */
	var MAX_AREAS = 20;

	var HANDLES = [
		{ h: 'nw', left: 0, top: 0, cursor: 'nwse-resize' },
		{ h: 'n', left: 0.5, top: 0, cursor: 'ns-resize' },
		{ h: 'ne', left: 1, top: 0, cursor: 'nesw-resize' },
		{ h: 'e', left: 1, top: 0.5, cursor: 'ew-resize' },
		{ h: 'se', left: 1, top: 1, cursor: 'nwse-resize' },
		{ h: 's', left: 0.5, top: 1, cursor: 'ns-resize' },
		{ h: 'sw', left: 0, top: 1, cursor: 'nesw-resize' },
		{ h: 'w', left: 0, top: 0.5, cursor: 'ew-resize' }
	];

	var GRID_PRESETS = [
		{ cols: 1, rows: 1 },
		{ cols: 2, rows: 1 },
		{ cols: 3, rows: 1 },
		{ cols: 4, rows: 1 },
		{ cols: 2, rows: 2 },
		{ cols: 3, rows: 2 }
	];

	// --- Geometry ------------------------------------------------------------

	/**
	 * Constrain a rectangle to the image and guarantee a usable size.
	 *
	 * Dragging past the edge is natural -- the mouse leaves the image. Without
	 * clamping, the stored value is one LINE rejects with "invalid property",
	 * which says nothing about which area or which edge.
	 */
	function clamp(rect, imageWidth, imageHeight) {
		var x = Math.min(Math.max(0, Math.round(rect.x)), Math.max(0, imageWidth - 1));
		var y = Math.min(Math.max(0, Math.round(rect.y)), Math.max(0, imageHeight - 1));

		return {
			x: x,
			y: y,
			width: Math.min(Math.max(1, Math.round(rect.width)), imageWidth - x),
			height: Math.min(Math.max(1, Math.round(rect.height)), imageHeight - y)
		};
	}

	/** A rectangle from two corners, dragged in any direction. */
	function rectFromPoints(a, b) {
		return {
			x: Math.min(a.x, b.x),
			y: Math.min(a.y, b.y),
			width: Math.abs(a.x - b.x),
			height: Math.abs(a.y - b.y)
		};
	}

	/**
	 * Whether two areas overlap.
	 *
	 * Worth flagging: when areas overlap LINE uses whichever comes first in the
	 * list, which is not what anyone drawing them expects.
	 */
	function overlaps(a, b) {
		return a.x < b.x + b.width && b.x < a.x + a.width
			&& a.y < b.y + b.height && b.y < a.y + a.height;
	}

	function findOverlaps(rects) {
		var pairs = [];

		for (var i = 0; i < rects.length; i++) {
			for (var j = i + 1; j < rects.length; j++) {
				if (overlaps(rects[i], rects[j])) {
					pairs.push([i, j]);
				}
			}
		}

		return pairs;
	}

	/**
	 * Resize from a handle, allowing the rectangle to flip when dragged past
	 * itself. Blocking the flip is what makes a drawing tool feel stuck.
	 */
	function resizeRect(rect, handle, dx, dy, imageWidth, imageHeight) {
		var x = rect.x;
		var y = rect.y;
		var right = rect.x + rect.width;
		var bottom = rect.y + rect.height;

		if (handle.indexOf('w') !== -1) { x = rect.x + dx; }
		if (handle.indexOf('e') !== -1) { right = right + dx; }
		if (handle.indexOf('n') !== -1) { y = rect.y + dy; }
		if (handle.indexOf('s') !== -1) { bottom = bottom + dy; }

		var out = clamp({
			x: Math.min(x, right),
			y: Math.min(y, bottom),
			width: Math.abs(right - x),
			height: Math.abs(bottom - y)
		}, imageWidth, imageHeight);

		out.width = Math.max(MIN_AREA, Math.min(out.width, imageWidth - out.x));
		out.height = Math.max(MIN_AREA, Math.min(out.height, imageHeight - out.y));

		return out;
	}

	/**
	 * Snap edges to other areas and to the image bounds.
	 *
	 * Hand-drawn areas always leave a gap or overlap of a pixel or two. It is
	 * invisible on the image, but a tap that lands in the gap does nothing at
	 * all, and nobody can work out why. Snapping is what makes a seamless
	 * layout achievable by hand.
	 */
	function snapRect(rect, others, imageWidth, imageHeight) {
		var xs = [0, imageWidth];
		var ys = [0, imageHeight];

		others.forEach(function (o) {
			xs.push(o.x, o.x + o.width);
			ys.push(o.y, o.y + o.height);
		});

		function near(value, candidates) {
			var best = value;
			var bestDistance = SNAP + 1;

			candidates.forEach(function (c) {
				var d = Math.abs(c - value);

				if (d < bestDistance) {
					bestDistance = d;
					best = c;
				}
			});

			return bestDistance <= SNAP ? best : value;
		}

		var left = near(rect.x, xs);
		var top = near(rect.y, ys);
		var right = near(rect.x + rect.width, xs);
		var bottom = near(rect.y + rect.height, ys);

		return clamp({
			x: left,
			y: top,
			width: Math.max(MIN_AREA, right - left),
			height: Math.max(MIN_AREA, bottom - top)
		}, imageWidth, imageHeight);
	}

	/** Evenly divide the image into a grid of areas. */
	function gridAreas(cols, rows, imageWidth, imageHeight) {
		var out = [];
		var cellWidth = Math.floor(imageWidth / cols);
		var cellHeight = Math.floor(imageHeight / rows);

		for (var r = 0; r < rows; r++) {
			for (var c = 0; c < cols; c++) {
				out.push({
					bounds: {
						x: c * cellWidth,
						y: r * cellHeight,
						// The last column and row take the remainder, so the
						// grid covers the image exactly with no dead strip.
						width: c === cols - 1 ? imageWidth - c * cellWidth : cellWidth,
						height: r === rows - 1 ? imageHeight - r * cellHeight : cellHeight
					},
					action: { type: 'message', label: '', text: '' }
				});
			}
		}

		return out;
	}

	// --- Editor ---------------------------------------------------------------

	function AreaEditor(root) {
		this.$root = $(root);
		this.$canvas = this.$root.find('[data-moksa-canvas]');
		this.$image = this.$root.find('[data-moksa-canvas-image]');
		this.$layer = this.$root.find('[data-moksa-canvas-layer]');
		this.$inspector = this.$root.find('[data-moksa-inspector]');
		this.$warnings = this.$root.find('[data-moksa-area-warnings]');
		this.$field = $(this.$root.data('moksa-area-editor'));
		this.$phone = this.$root.find('[data-moksa-phone-areas]');

		// The coordinate space the areas are stored in. A rich menu is always
		// 2500 wide; an imagemap is always 1040 and any height. Everything else
		// in here derives from these two numbers, so the same editor drives
		// both once they stop being hardcoded.
		this.imageWidth = parseInt(this.$root.data('image-width'), 10) || 2500;
		this.imageHeight = parseInt(this.$root.data('image-height'), 10) || 1686;
		this.areas = [];
		this.selected = null;
		this.drag = null;

		this.load();
		this.bind();

		// Draw straight away. The rich menu screen only looked right because
		// its own binder happened to call render() while syncing the menu size;
		// the imagemap screen has no such call, so its inspector sat there as
		// an empty bordered box instead of telling anyone how to add an area.
		// Whether the editor has drawn itself is the editor's business.
		this.render();
	}

	/** Read the areas out of the hidden field the form actually submits. */
	AreaEditor.prototype.load = function () {
		var raw = this.$field.val();

		try {
			var parsed = JSON.parse(raw);
			this.areas = Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			this.areas = [];
		}

		this.areas = this.areas.filter(function (a) {
			return a && a.bounds;
		});
	};

	/** Write back to the field. This is the only place areas leave the editor. */
	AreaEditor.prototype.save = function () {
		this.$field.val(JSON.stringify(this.areas, null, 2)).trigger('change');
	};

	AreaEditor.prototype.scale = function () {
		var width = this.$image.width() || this.$canvas.width();

		return width > 0 && this.imageWidth > 0 ? width / this.imageWidth : 1;
	};

	/** Mouse position in image pixels. */
	AreaEditor.prototype.pointFromEvent = function (event) {
		var offset = this.$canvas.offset();
		var scale = this.scale();

		return {
			x: (event.pageX - offset.left) / scale,
			y: (event.pageY - offset.top) / scale
		};
	};

	AreaEditor.prototype.setSize = function (width, height) {
		this.imageWidth = width;
		this.imageHeight = height;
		this.render();
	};

	AreaEditor.prototype.setImage = function (url) {
		this.$image.attr('src', url || '');
		this.$root.toggleClass('has-image', !!url);
		this.render();
	};

	AreaEditor.prototype.render = function () {
		var self = this;
		var scale = this.scale();

		this.$layer.empty();

		this.areas.forEach(function (area, index) {
			var b = area.bounds;

			var $box = $('<div class="moksa-area"></div>')
				.toggleClass('is-selected', self.selected === index)
				.css({
					left: b.x * scale,
					top: b.y * scale,
					width: b.width * scale,
					height: b.height * scale
				})
				.attr('data-index', index);

			$box.append(
				$('<span class="moksa-area__label"></span>').text(
					(index + 1) + (area.action && area.action.label ? ' · ' + area.action.label : '')
				)
			);

			if (self.selected === index) {
				HANDLES.forEach(function (handle) {
					$('<span class="moksa-area__handle"></span>')
						.attr('data-handle', handle.h)
						.css({
							left: (handle.left * 100) + '%',
							top: (handle.top * 100) + '%',
							cursor: handle.cursor
						})
						.appendTo($box);
				});
			}

			self.$layer.append($box);
		});

		this.renderPhone();
		this.renderInspector();
		this.renderWarnings();
	};

	/**
	 * Mirror the areas onto the phone preview.
	 *
	 * The editor shows the image full width; on a phone it sits in the bottom
	 * portion of the screen under the conversation. A layout that reads well
	 * enlarged is often unreadable at the real size, and that only shows up
	 * next to something phone shaped.
	 */
	AreaEditor.prototype.renderPhone = function () {
		var self = this;

		if (!this.$phone.length) {
			return;
		}

		this.$phone.empty();

		this.areas.forEach(function (area, index) {
			var b = area.bounds;

			$('<span class="moksa-phone-area"></span>')
				.toggleClass('is-selected', self.selected === index)
				.css({
					left: (b.x / self.imageWidth * 100) + '%',
					top: (b.y / self.imageHeight * 100) + '%',
					width: (b.width / self.imageWidth * 100) + '%',
					height: (b.height / self.imageHeight * 100) + '%'
				})
				.appendTo(self.$phone);
		});
	};

	/** Overlaps and count limits, stated where they can be acted on. */
	AreaEditor.prototype.renderWarnings = function () {
		var messages = [];
		var rects = this.areas.map(function (a) {
			return a.bounds;
		});

		var pairs = findOverlaps(rects);

		if (pairs.length) {
			messages.push(
				(strings.areasOverlap || 'Areas %s overlap. LINE uses whichever comes first, which is rarely what you want.')
					.replace('%s', pairs.map(function (p) {
						return (p[0] + 1) + '/' + (p[1] + 1);
					}).join(', '))
			);
		}

		if (this.areas.length > MAX_AREAS) {
			messages.push(
				(strings.tooManyAreas || 'LINE allows at most %d areas; the extra ones will be dropped.')
					.replace('%d', MAX_AREAS)
			);
		}

		var missing = this.areas.filter(function (a) {
			var action = a.action || {};

			if ('uri' === action.type) { return !action.uri; }
			if ('message' === action.type) { return !action.text; }
			if ('richmenuswitch' === action.type) { return !action.richMenuAliasId; }

			return false;
		}).length;

		if (missing) {
			messages.push(
				(strings.areasIncomplete || '%d area(s) have no destination set yet.').replace('%d', missing)
			);
		}

		this.$warnings.html(
			messages.map(function (m) {
				return '<p class="moksa-area-warning">' + $('<div>').text(m).html() + '</p>';
			}).join('')
		);
	};

	/**
	 * The action form for the selected area.
	 *
	 * Deliberately beside the image rather than a list underneath: with more
	 * than three or four areas, matching a row in a list to a box in the image
	 * by its number is a puzzle nobody should have to solve.
	 */
	AreaEditor.prototype.renderInspector = function () {
		if (null === this.selected || !this.areas[this.selected]) {
			this.$inspector.html(
				'<p class="description">'
				+ $('<div>').text(strings.pickAnArea || 'Drag on the image to add an area, or click one to edit it.').html()
				+ '</p>'
			);

			return;
		}

		var area = this.areas[this.selected];
		var action = area.action || { type: 'message' };
		var b = area.bounds;
		var index = this.selected;

		var types = [
			['message', strings.actionMessage || 'Send a message'],
			['uri', strings.actionUri || 'Open a link'],
			['postback', strings.actionPostback || 'Postback'],
			['richmenuswitch', strings.actionSwitch || 'Switch to another tab']
		];

		var html = '<h3>' + (strings.area || 'Area') + ' ' + (index + 1) + '</h3>';

		html += '<p><label>' + (strings.actionType || 'When tapped') + '</label>'
			+ '<select class="widefat" data-moksa-action-type>';

		types.forEach(function (t) {
			html += '<option value="' + t[0] + '"' + (action.type === t[0] ? ' selected' : '') + '>'
				+ $('<div>').text(t[1]).html() + '</option>';
		});

		html += '</select></p>';

		html += '<p><label>' + (strings.actionLabel || 'Label') + '</label>'
			+ '<input type="text" class="widefat" maxlength="20" data-moksa-action-label value="'
			+ $('<div>').text(action.label || '').html() + '" /></p>';

		if ('uri' === action.type) {
			html += '<p><label>' + (strings.actionUriValue || 'Link') + '</label>'
				+ '<input type="url" class="widefat" data-moksa-action-value placeholder="https://" value="'
				+ $('<div>').text(action.uri || '').html() + '" /></p>';
		} else if ('message' === action.type) {
			html += '<p><label>' + (strings.actionText || 'Message the customer sends') + '</label>'
				+ '<input type="text" class="widefat" data-moksa-action-value value="'
				+ $('<div>').text(action.text || '').html() + '" /></p>';
		} else if ('richmenuswitch' === action.type) {
			html += '<p><label>' + (strings.actionAlias || 'Tab to switch to') + '</label>'
				+ '<select class="widefat" data-moksa-action-value>';

			var targets = this.$root.data('switch-targets') || {};
			var current = action.richMenuAliasId || '';
			var found = false;

			Object.keys(targets).forEach(function (alias) {
				if (alias === current) { found = true; }
				html += '<option value="' + $('<div>').text(alias).html() + '"'
					+ (alias === current ? ' selected' : '') + '>'
					+ $('<div>').text(targets[alias] + ' (' + alias + ')').html() + '</option>';
			});

			if (!Object.keys(targets).length) {
				html += '<option value="">' + (strings.noTabs || 'No other menus in this tab group yet') + '</option>';
			} else if (current && !found) {
				html += '<option value="' + $('<div>').text(current).html() + '" selected>'
					+ $('<div>').text(current).html() + '</option>';
			}

			html += '</select></p>';
		} else {
			html += '<p><label>' + (strings.actionData || 'Postback data') + '</label>'
				+ '<input type="text" class="widefat" data-moksa-action-value value="'
				+ $('<div>').text(action.data || '').html() + '" /></p>';
		}

		html += '<p class="moksa-coords">'
			+ '<span><label>X</label><input type="number" data-moksa-coord="x" value="' + b.x + '" /></span>'
			+ '<span><label>Y</label><input type="number" data-moksa-coord="y" value="' + b.y + '" /></span>'
			+ '<span><label>W</label><input type="number" data-moksa-coord="width" value="' + b.width + '" /></span>'
			+ '<span><label>H</label><input type="number" data-moksa-coord="height" value="' + b.height + '" /></span>'
			+ '</p>';

		html += '<p class="description">' + (strings.nudgeHint || 'Arrow keys nudge by 1 pixel, with Shift by 10.') + '</p>';

		html += '<p><button type="button" class="button-link delete" data-moksa-delete-area>'
			+ (strings.deleteArea || 'Delete this area') + '</button></p>';

		this.$inspector.html(html);
	};

	AreaEditor.prototype.select = function (index) {
		this.selected = index;
		this.render();
	};

	AreaEditor.prototype.updateSelected = function (patch) {
		if (null === this.selected || !this.areas[this.selected]) {
			return;
		}

		var area = this.areas[this.selected];

		if (patch.bounds) {
			area.bounds = clamp(patch.bounds, this.imageWidth, this.imageHeight);
		}

		if (patch.action) {
			area.action = $.extend({}, area.action, patch.action);
		}

		this.save();
		this.render();
	};

	AreaEditor.prototype.othersThan = function (index) {
		return this.areas.filter(function (a, i) {
			return i !== index;
		}).map(function (a) {
			return a.bounds;
		});
	};

	AreaEditor.prototype.bind = function () {
		var self = this;

		// --- Drawing, moving and resizing all run through one state machine,
		// so the edge cases are handled once rather than three times.
		this.$canvas.on('mousedown', function (event) {
			if (self.$root.hasClass('is-disabled')) {
				return;
			}

			event.preventDefault();

			var point = self.pointFromEvent(event);
			var $handle = $(event.target).closest('.moksa-area__handle');
			var $area = $(event.target).closest('.moksa-area');

			if ($handle.length && null !== self.selected) {
				self.drag = {
					mode: 'resize',
					index: self.selected,
					handle: $handle.data('handle'),
					start: point,
					startRect: $.extend({}, self.areas[self.selected].bounds)
				};

				return;
			}

			if ($area.length) {
				var index = parseInt($area.attr('data-index'), 10);

				self.select(index);

				self.drag = {
					mode: 'move',
					index: index,
					start: point,
					startRect: $.extend({}, self.areas[index].bounds)
				};

				return;
			}

			if (self.areas.length >= MAX_AREAS) {
				return;
			}

			self.drag = { mode: 'draw', start: point, to: point };
			self.selected = null;
		});

		$(document).on('mousemove.moksaArea', function (event) {
			if (!self.drag) {
				return;
			}

			var point = self.pointFromEvent(event);

			if ('draw' === self.drag.mode) {
				self.drag.to = point;
				self.previewRect(rectFromPoints(self.drag.start, point));

				return;
			}

			var dx = point.x - self.drag.start.x;
			var dy = point.y - self.drag.start.y;

			if ('move' === self.drag.mode) {
				var moved = clamp({
					x: self.drag.startRect.x + dx,
					y: self.drag.startRect.y + dy,
					width: self.drag.startRect.width,
					height: self.drag.startRect.height
				}, self.imageWidth, self.imageHeight);

				self.areas[self.drag.index].bounds = moved;
			} else {
				self.areas[self.drag.index].bounds = resizeRect(
					self.drag.startRect,
					self.drag.handle,
					dx,
					dy,
					self.imageWidth,
					self.imageHeight
				);
			}

			self.render();
		});

		$(document).on('mouseup.moksaArea', function () {
			if (!self.drag) {
				return;
			}

			var drag = self.drag;

			self.drag = null;
			self.$layer.find('.moksa-area--ghost').remove();

			if ('draw' === drag.mode) {
				var rect = clamp(rectFromPoints(drag.start, drag.to), self.imageWidth, self.imageHeight);

				// A click rather than a drag: not an area, just a deselect.
				if (rect.width < MIN_AREA || rect.height < MIN_AREA) {
					self.render();

					return;
				}

				self.areas.push({
					bounds: snapRect(rect, self.areas.map(function (a) { return a.bounds; }), self.imageWidth, self.imageHeight),
					action: { type: 'message', label: '', text: '' }
				});

				self.selected = self.areas.length - 1;
			} else {
				// Snap on release rather than during the drag, so the rectangle
				// follows the mouse and settles at the end.
				self.areas[drag.index].bounds = snapRect(
					self.areas[drag.index].bounds,
					self.othersThan(drag.index),
					self.imageWidth,
					self.imageHeight
				);
			}

			self.save();
			self.render();
		});

		// Arrow keys nudge the selection.
		this.$root.on('keydown', function (event) {
			if (null === self.selected || !self.areas[self.selected]) {
				return;
			}

			var map = { 37: [-1, 0], 38: [0, -1], 39: [1, 0], 40: [0, 1] };
			var delta = map[event.which];

			if (!delta) {
				return;
			}

			event.preventDefault();

			var step = event.shiftKey ? 10 : 1;
			var b = self.areas[self.selected].bounds;

			self.updateSelected({
				bounds: {
					x: b.x + delta[0] * step,
					y: b.y + delta[1] * step,
					width: b.width,
					height: b.height
				}
			});
		});

		// --- Inspector -------------------------------------------------------

		this.$inspector.on('change input', '[data-moksa-action-type]', function () {
			var type = $(this).val();
			var action = { type: type };

			// Clear the value fields that do not belong to the new type, so a
			// leftover uri cannot travel with a message action.
			action.uri = undefined;
			action.text = undefined;
			action.data = undefined;
			action.richMenuAliasId = undefined;

			self.updateSelected({ action: action });
		});

		this.$inspector.on('input change', '[data-moksa-action-label]', function () {
			self.updateSelected({ action: { label: $(this).val() } });
		});

		this.$inspector.on('input change', '[data-moksa-action-value]', function () {
			var area = self.areas[self.selected];
			var type = (area && area.action && area.action.type) || 'message';
			var value = $(this).val();
			var patch = {};

			if ('uri' === type) { patch.uri = value; }
			else if ('message' === type) { patch.text = value; }
			else if ('richmenuswitch' === type) {
				patch.richMenuAliasId = value;
				patch.data = 'moksa_tab=' + value;
			} else { patch.data = value; }

			self.updateSelected({ action: patch });
		});

		this.$inspector.on('change', '[data-moksa-coord]', function () {
			var b = $.extend({}, self.areas[self.selected].bounds);

			b[$(this).data('moksa-coord')] = parseInt($(this).val(), 10) || 0;

			self.updateSelected({ bounds: b });
		});

		this.$inspector.on('click', '[data-moksa-delete-area]', function () {
			self.areas.splice(self.selected, 1);
			self.selected = null;
			self.save();
			self.render();
		});

		// --- Toolbar ----------------------------------------------------------
		// The toolbar sits above the canvas as a sibling, not inside the editor
		// root, so delegating from the root would never see these clicks.
		var $scope = this.$root.closest('form').length ? this.$root.closest('form') : $(document);

		$scope.on('click', '[data-moksa-grid]', function () {
			var preset = String($(this).data('moksa-grid')).split('x');
			var cols = parseInt(preset[0], 10);
			var rows = parseInt(preset[1], 10);

			function apply() {
				self.areas = gridAreas(cols, rows, self.imageWidth, self.imageHeight);
				self.selected = 0;
				self.save();
				self.render();
			}

			// An empty canvas has nothing to replace.
			if (!self.areas.length) {
				apply();
				return;
			}

			moksaConfirm(
				strings.replaceAreas || 'Replace the current areas with this layout?',
				{ danger: true, confirmLabel: strings.confirmReplaceAction || 'Replace' }
			).then(function (confirmed) {
				if (confirmed) {
					apply();
				}
			});
		});

		$scope.on('click', '[data-moksa-clear-areas]', function () {
			if (!self.areas.length) {
				return;
			}

			moksaConfirm(
				strings.confirmClearAreas || 'Remove every area from this menu?',
				{ danger: true, confirmLabel: strings.confirmClearAction || 'Clear' }
			).then(function (confirmed) {
				if (!confirmed) {
					return;
				}

				self.areas = [];
				self.selected = null;
				self.save();
				self.render();
			});
		});

		// Editing the JSON directly stays available, and feeds back in.
		this.$field.on('change', function (event) {
			if (event.originalEvent) {
				self.load();
				self.render();
			}
		});

		$(window).on('resize.moksaArea', function () {
			self.render();
		});
	};

	/** The rectangle being drawn, before it becomes an area. */
	AreaEditor.prototype.previewRect = function (rect) {
		var scale = this.scale();
		var $ghost = this.$layer.find('.moksa-area--ghost');

		if (!$ghost.length) {
			$ghost = $('<div class="moksa-area moksa-area--ghost"></div>').appendTo(this.$layer);
		}

		$ghost.css({
			left: rect.x * scale,
			top: rect.y * scale,
			width: rect.width * scale,
			height: rect.height * scale
		});
	};

	// --- Bootstrap --------------------------------------------------------------

	$(function () {
		strings = (window.moksaLine && window.moksaLine.strings) || {};

		$('[data-moksa-area-editor]').each(function () {
			var editor = new AreaEditor(this);

			// Expose it so the menu form can tell the editor about a newly
			// chosen image or a changed menu size.
			$(this).data('moksaAreaEditor', editor);
		});
	});
}(jQuery));
