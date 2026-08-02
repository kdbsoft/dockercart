/**
 * DcxUI — unified UI component library.
 *
 * Contains:
 *   DcxUI.Picker       — AJAX search picker with thumbnails and multi-select
 *   DcxUI.TreeSelect   — reusable tree-select with single/multi modes
 *   $.fn.autocomplete   — drop-in replacement for jQuery autocomplete
 *
 * Auto-initializes all .dcx-picker and .dcx-tree-select elements on DOM ready.
 * $.fn.autocomplete is registered globally (called explicitly per input).
 *
 * Requires jQuery and Lucide icons (data-lucide).
 */
var DcxUI = (function() {
	'use strict';

	/* ==================== Shared utilities ==================== */

	function _renderIcons(context) {
		if (typeof lucide === 'undefined') return;
		if (context) {
			lucide.createIcons({ nodes: Array.isArray(context) ? context : [context] });
		} else {
			lucide.createIcons();
		}
	}

	function _debounced(self, fn, delay) {
		clearTimeout(self._timer);
		self._timer = setTimeout(fn, delay || 200);
	}

	function _bindClickOutside($el, closeFn, ctx) {
		$(document).on('click', function(e) {
			if (!$el[0].contains(e.target)) closeFn.call(ctx);
		});
	}

	function _renderWell($well, selectedItems, opts) {
		if (!$well) return;
		var p = opts.prefix;
		$well.empty();
		$.each(selectedItems, function(id, name) {
			$well.append(
				'<div class="' + p + '__well-item" data-id="' + id + '">' +
				'<i data-lucide="circle-minus" width="14" height="14" class="' + p + '__well-remove"></i> ' +
				'<span class="' + p + '__well-label">' + name + '</span>' +
				(opts.inputName ? '<input type="hidden" name="' + opts.inputName + '" value="' + id + '"/>' : '') +
				'</div>'
			);
		});
		_renderIcons();
		$well.toggle($well.find('.' + p + '__well-item').length > 0);
	}

	function _syncWellDelta($well, selectedItems, opts) {
		if (!$well) return;
		var p = opts.prefix;
		$well.find('.' + p + '__well-item').each(function() {
			if (!selectedItems.hasOwnProperty($(this).data('id'))) {
				$(this).remove();
			}
		});
		$.each(selectedItems, function(id, name) {
			if (!$well.find('.' + p + '__well-item[data-id="' + id + '"]').length) {
				$well.append(
					'<div class="' + p + '__well-item" data-id="' + id + '">' +
					'<i data-lucide="circle-minus" width="14" height="14" class="' + p + '__well-remove"></i> ' +
					'<span class="' + p + '__well-label">' + name + '</span>' +
					(opts.inputName ? '<input type="hidden" name="' + opts.inputName + '" value="' + id + '"/>' : '') +
					'</div>'
				);
			}
		});
		_renderIcons();
		$well.toggle($well.find('.' + p + '__well-item').length > 0);
	}

	/* ==================== DcxUI.Picker ==================== */

	function initPickers(selector) {
		selector = selector || '.dcx-picker';
		$(selector).each(function() {
			if ($(this).data('dcx-picker')) return;
			new Picker($(this));
		});
	}

	function Picker($el) {
		$el.data('dcx-picker', this);

		this.$el = $el;
		this.url = $el.data('url');
		this.placeholder = $el.data('placeholder') || 'Search...';
		this.inputName = $el.data('input-name') || '';
		this.idKey = $el.data('id-key') || 'value';
		this.nameKey = $el.data('name-key') || 'label';
		this.thumbKey = $el.data('thumb-key') || 'thumb';
		this.subKey = $el.data('sub-key') || 'model';
		this.max = parseInt($el.data('max') || 0, 10);
		var rawMinChars = $el.data('min-chars');
		this.minChars = (rawMinChars !== undefined && rawMinChars !== '') ? parseInt(rawMinChars, 10) : 1;

		this.textTypeToSearch = $el.data('text-type-to-search') || 'Type to search...';
		this.textNoResults = $el.data('text-no-results') || 'No results found';
		this.textError = $el.data('text-error') || 'Error loading results';

		this.$input = $el.find('.dcx-picker__input');
		this.$dropdown = null;
		this.$results = null;

		this._timer = null;
		this.selectedItems = {};
		this.isOpen = false;

		this.$well = null;
		if ($el.data('well')) {
			this.$well = $($el.data('well'));
		}

		this._build();
		this._bindEvents();
		this._syncFromWell();
	}

	Picker.prototype._build = function() {
		this.$dropdown = $('<div class="dcx-picker__dropdown"></div>');

		this.$searchWrap = $('<div class="dcx-picker__search"></div>');
		this.$searchInput = $('<input type="text" class="form-control" />');
		this.$searchIcon = $('<i data-lucide="search" width="14" height="14"></i>');
		this.$searchWrap.append(this.$searchInput).append(this.$searchIcon);

		this.$results = $('<div class="dcx-picker__results"></div>');
		this.$dropdown.append(this.$searchWrap).append(this.$results);
		this.$el.append(this.$dropdown);

		this.$input.attr('placeholder', this.placeholder).attr('autocomplete', 'off').attr('readonly', true);
	};

	Picker.prototype._bindEvents = function() {
		var self = this;

		this.$input.on('focus', function() {
			self.isOpen = true;
			self.$dropdown.show();
			self.$searchInput.val('').focus();
			self._search('');
		});

		this.$searchInput.on('input', function() {
			var val = $(this).val();
			_debounced(self, function() {
				self._search(val);
			}, 300);
		});

		this.$results.on('click', '.dcx-picker__item', function(e) {
			e.preventDefault();
			e.stopPropagation();
			self.toggleItem($(this));
		});

		this.$results.on('click', '.dcx-picker__item-check', function(e) {
			e.preventDefault();
			e.stopPropagation();
			self.toggleItem($(this).closest('.dcx-picker__item'));
		});

		if (this.$well) {
			this.$well.on('click', '.dcx-picker__well-remove', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var id = String($(this).closest('.dcx-picker__well-item').data('id'));
				self.deselectItem(id);
			});
		}

		_bindClickOutside(this.$el, this.close, this);
	};

	Picker.prototype._syncFromWell = function() {
		var self = this;
		if (!this.$well) return;
		this.selectedItems = {};
		this.$well.find('.dcx-picker__well-item').each(function() {
			var id = String($(this).data('id'));
			var name = $(this).find('.dcx-picker__well-label').text().trim();
			if (id) self.selectedItems[id] = name;
		});
		this._updateInputValue();
	};

	Picker.prototype._updateInputValue = function() {
		this.$input.val('').removeAttr('readonly');
		if (this.$searchInput) this.$searchInput.val('');
	};

	Picker.prototype._search = function(query) {
		var self = this;
		if (query.length < this.minChars) {
			this.$results.empty().append('<div class="dcx-picker__empty">' + this.textTypeToSearch + '</div>');
			return;
		}

		this.$results.empty().append('<div class="dcx-picker__loading"><i data-lucide="loader" width="16" height="16"></i></div>');
		_renderIcons();

		var separator = this.url.indexOf('?') !== -1 ? '&' : '?';
		var searchUrl = this.url + separator + 'filter_name=' + encodeURIComponent(query);

		$.ajax({
			url: searchUrl,
			dataType: 'json',
			success: function(json) {
				self.renderResults(json);
			},
			error: function() {
				self.$results.empty().append('<div class="dcx-picker__empty">' + self.textError + '</div>');
			}
		});
	};

	Picker.prototype.renderResults = function(items) {
		var self = this;
		this.$results.empty();

		if (!items || !items.length) {
			this.$results.append('<div class="dcx-picker__empty">' + this.textNoResults + '</div>');
			return;
		}

		$.each(items, function(i, item) {
			var id = String(item[self.idKey]);
			var name = item[self.nameKey] || '';
			var thumb = item[self.thumbKey] || '';
			var sub = item[self.subKey] || '';
			var isChecked = self.selectedItems.hasOwnProperty(id);

			var $item = $('<div class="dcx-picker__item' + (isChecked ? ' dcx-picker__item--selected' : '') + '" data-id="' + id + '" data-name="' + name.replace(/"/g, '&quot;') + '"></div>');

			var checkIcon = isChecked ? 'square-check' : 'square';
			$item.append('<span class="dcx-picker__item-check"><i data-lucide="' + checkIcon + '" width="16" height="16"></i></span>');

			if (thumb) {
				$item.append('<img class="dcx-picker__thumb" src="' + thumb + '" alt="" />');
			} else {
				$item.append('<div class="dcx-picker__thumb dcx-picker__thumb--empty"><i data-lucide="package" width="16" height="16"></i></div>');
			}

			var $info = $('<div class="dcx-picker__info"></div>');
			$info.append('<div class="dcx-picker__name">' + name + '</div>');
			if (sub) {
				$info.append('<div class="dcx-picker__sub">' + sub + '</div>');
			}
			$item.append($info);

			self.$results.append($item);
		});

		_renderIcons();
	};

	Picker.prototype.toggleItem = function($item) {
		var id = $item.data('id');
		if (!id) return;

		id = String(id);
		var name = $item.data('name');
		var $icon = $item.find('.dcx-picker__item-check [data-lucide]');

		if (this.selectedItems.hasOwnProperty(id)) {
			delete this.selectedItems[id];
			$item.removeClass('dcx-picker__item--selected');
			$icon.attr('data-lucide', 'square');
		} else {
			if (this.max > 0) {
				var count = Object.keys(this.selectedItems).length;
				if (count >= this.max) {
					var self = this;
					var previousIds = Object.keys(this.selectedItems);
					this.selectedItems = {};
					$.each(previousIds, function(i, prevId) {
						var $prevItem = self.$results.find('.dcx-picker__item[data-id="' + prevId + '"]');
						$prevItem.removeClass('dcx-picker__item--selected');
						var $prevIcon = $prevItem.find('.dcx-picker__item-check [data-lucide]');
						if ($prevIcon.length) {
							$prevIcon.attr('data-lucide', 'square');
							_renderIcons($prevIcon[0]);
						}
					});
				}
			}
			this.selectedItems[id] = name;
			$item.addClass('dcx-picker__item--selected');
			$icon.attr('data-lucide', 'square-check');
		}

		_renderIcons($icon[0]);
		_renderWell(this.$well, this.selectedItems, { prefix: 'dcx-picker', inputName: this.inputName });
		this._updateInputValue();
	};

	Picker.prototype.deselectItem = function(id) {
		delete this.selectedItems[id];
		_renderWell(this.$well, this.selectedItems, { prefix: 'dcx-picker', inputName: this.inputName });
		this._updateInputValue();

		this.$results.find('.dcx-picker__item[data-id="' + id + '"]').removeClass('dcx-picker__item--selected');
		var $icon = this.$results.find('.dcx-picker__item[data-id="' + id + '"] .dcx-picker__item-check [data-lucide]');
		if ($icon.length) {
			$icon.attr('data-lucide', 'square');
			_renderIcons($icon[0]);
		}
	};

	Picker.prototype.close = function() {
		if (!this.isOpen) return;
		this.isOpen = false;
		this.$dropdown.hide();
		if (this.$searchInput) this.$searchInput.val('');
		this.$input.attr('readonly', true);
	};

	/* ==================== DcxUI.TreeSelect ==================== */

	function initTreeSelects(selector) {
		selector = selector || '.dcx-tree-select';
		$(selector).each(function() {
			if ($(this).data('dcx-tree-select')) return;
			new TreeSelect($(this));
		});
	}

	function TreeSelect($el) {
		$el.data('dcx-tree-select', this);

		this.$el = $el;
		this.url = $el.data('url');
		this.noneText = $el.data('none-text') || '\u2014';
		this.placeholder = $el.data('placeholder') || '';
		this.idKey = $el.data('id-key') || 'category_id';
		this.mode = $el.data('mode') || 'single';
		this.wellSelector = $el.data('well');
		this.inputName = $el.data('input-name');
		this.modalSelector = $el.data('modal');
		this.cache = null;
		this._timer = null;
		this.selectedItems = {};
		this.filterCallback = null;

		this.$input = $el.find('.dcx-tree-select__input');
		this.$hidden = $el.find('input[type="hidden"]');
		this.$dropdown = $el.find('.dcx-tree-select__dropdown');
		this.$list = $el.find('.dcx-tree-select__list');
		this.$search = $el.find('.dcx-tree-select__search input');
		this.$toggle = $el.find('.dcx-tree-select__toggle');
		this.$well = this.wellSelector ? $(this.wellSelector) : null;

		if (this.mode === 'multi') {
			this._initMulti();
		}

		this._bindEvents();
	}

	TreeSelect.prototype._initMulti = function() {
		if (this.$well) {
			var self = this;
			this.$well.find('.dcx-tree-select__well-item').each(function() {
				var id = $(this).data('id');
				var name = $(this).find('.dcx-tree-select__well-label').text();
				if (id) self.selectedItems[id] = name;
			});
		}
	};

	TreeSelect.prototype._bindEvents = function() {
		var self = this;

		this.$toggle.on('click', function(e) {
			e.stopPropagation();
			if (self.$dropdown.is(':visible')) {
				self.close();
			} else {
				self.open();
			}
		});

		this.$input.on('focus click', function() {
			if (self.mode !== 'multi') {
				self.open();
			}
		});

		this.$search.on('input', function() {
			var val = $(this).val();
			_debounced(self, function() {
				if (self.cache) {
					if (val) {
						self.filterList(val);
					} else {
						self.getVisibleIds();
					}
				}
			}, 200);
		});

		this.$list.on('click', '.dcx-tree-select__arrow:not(.dcx-tree-select__arrow--hidden)', function(e) {
			e.stopPropagation();
			self.toggleNode($(this));
		});

		this.$list.on('click', '.dcx-tree-select__item', function(e) {
			if ($(e.target).closest('.dcx-tree-select__arrow').length) return;
			self.selectItem($(this));
		});

		this.$list.on('change', '.dcx-tree-select__checkbox input', function(e) {
			e.stopPropagation();
			var $item = $(this).closest('.dcx-tree-select__item');
			self.toggleMultiItem($item, $(this).is(':checked'));
		});

		this.$input.on('input', function() {
			if (self.mode !== 'multi') {
				self.$hidden.val('');
			}
		});

		_bindClickOutside(this.$el, this.close, this);

		if (this.$well) {
			this.$well.on('click', '.dcx-tree-select__well-remove', function(e) {
				e.stopPropagation();
				var $item = $(this).closest('.dcx-tree-select__well-item');
				var id = $item.data('id');
				delete self.selectedItems[id];
				$item.remove();
				self.syncCheckboxes();
				self.syncHiddenInputs();
			});
		}
	};

	TreeSelect.prototype.fetchTree = function() {
		var self = this;
		if (this.cache) return Promise.resolve(this.cache);
		return $.ajax({
			url: this.url,
			dataType: 'json'
		}).then(function(json) {
			self.cache = json;
			return json;
		});
	};

	TreeSelect.prototype.renderList = function(items) {
		var self = this;
		this.$list.find('.dcx-tree-select__item:not(.dcx-tree-select__item--none)').remove();

		$.each(items, function(i, item) {
			var indent = item.level * 16;
			var hasKids = item.has_children;
			var name = (item.name || '').replace(/"/g, '&quot;');
			var path = (item.path || item.name || '').replace(/"/g, '&quot;');
			var idKey = self.idKey;
			var id = item[idKey] !== undefined ? item[idKey] : item.category_id;
			var isGroup = item.is_group ? 'true' : 'false';
			var isChecked = self.selectedItems.hasOwnProperty(id);

			var $item = $('<div class="dcx-tree-select__item" ' +
				'data-id="' + id + '" ' +
				'data-name="' + name + '" ' +
				'data-path="' + path + '" ' +
				'data-level="' + item.level + '" ' +
				'data-has-children="' + (hasKids ? '1' : '0') + '" ' +
				'data-group="' + isGroup + '"></div>');
			$item.css('padding-left', (12 + indent) + 'px');

			if (hasKids) {
				$item.append('<span class="dcx-tree-select__arrow" data-expanded="false"><i data-lucide="chevron-right" width="14" height="14"></i></span>');
			} else {
				$item.append('<span class="dcx-tree-select__arrow dcx-tree-select__arrow--hidden"><i data-lucide="chevron-right" width="14" height="14"></i></span>');
			}

			if (self.mode === 'multi') {
				var checkIcon = isChecked ? 'square-check' : 'square';
				$item.append('<label class="dcx-tree-select__checkbox"><i data-lucide="' + checkIcon + '" width="16" height="16"></i><input type="checkbox"' + (isChecked ? ' checked' : '') + ' style="display:none;"/></label>');
			}

			$item.append('<span class="dcx-tree-select__label">' + item.name + '</span>');
			self.$list.append($item);
		});

		_renderIcons();
	};

	TreeSelect.prototype.getVisibleIds = function() {
		var visibleIds = [];
		var $items = this.$list.find('.dcx-tree-select__item:not(.dcx-tree-select__item--none)');
		$items.removeClass('dcx-tree-select__item--hidden');
		var hideStack = [];

		$items.each(function() {
			var $item = $(this);
			var level = parseInt($item.data('level'), 10);
			while (hideStack.length && hideStack[hideStack.length - 1] >= level) {
				hideStack.pop();
			}
			if (hideStack.length) {
				$item.hide();
			} else {
				$item.show();
				visibleIds.push($item.data('id'));
			}
			if ($item.data('has-children') && $item.find('.dcx-tree-select__arrow').attr('data-expanded') !== 'true') {
				hideStack.push(level);
			}
		});

		return visibleIds;
	};

	TreeSelect.prototype.filterList = function(query) {
		var q = query.toLowerCase();
		this.$list.find('.dcx-tree-select__item:not(.dcx-tree-select__item--none)').each(function() {
			var name = ($(this).data('name') + '').toLowerCase();
			$(this).toggleClass('dcx-tree-select__item--hidden', q && name.indexOf(q) === -1);
		});
	};

	TreeSelect.prototype.toggleNode = function($arrow) {
		var $item = $arrow.closest('.dcx-tree-select__item');
		var level = parseInt($item.data('level'), 10);
		var expanded = $arrow.attr('data-expanded') === 'true';

		$arrow.attr('data-expanded', expanded ? 'false' : 'true');
		$arrow.find('i').attr('data-lucide', expanded ? 'chevron-right' : 'chevron-down');
		_renderIcons();

		var nextLevel = level + 1;
		var $next = $item.next();
		while ($next.length && parseInt($next.data('level'), 10) >= nextLevel) {
			if (expanded) {
				$next.hide();
				$next.find('.dcx-tree-select__arrow').attr('data-expanded', 'false');
			} else {
				if (parseInt($next.data('level'), 10) === nextLevel) {
					$next.show();
				}
			}
			$next = $next.next();
		}
	};

	TreeSelect.prototype.selectItem = function($item) {
		if ($item.data('group') === true || $item.data('group') === 'true') {
			var $arrow = $item.find('.dcx-tree-select__arrow:not(.dcx-tree-select__arrow--hidden)');
			if ($arrow.length) {
				this.toggleNode($arrow);
			}
			return;
		}

		if (this.mode === 'multi') {
			var $cb = $item.find('.dcx-tree-select__checkbox input');
			var isChecked = !$cb.is(':checked');
			$cb.prop('checked', isChecked);
			this.toggleMultiItem($item, isChecked);
			return;
		}

		var id = $item.data('id');
		if (id === 0 || id === '0') {
			this.$input.val('');
			this.$hidden.val('');
		} else {
			var path = $item.data('path') || $item.data('name');
			this.$input.val(path);
			this.$hidden.val(id);
		}
		this.$hidden.trigger('change');
		this.close();
	};

	TreeSelect.prototype.toggleMultiItem = function($item, checked) {
		var id = $item.data('id');
		var name = $item.data('name');

		if (id === 0 || id === '0') return;

		if (checked) {
			this.selectedItems[id] = name;
		} else {
			delete this.selectedItems[id];
		}

		var $icon = $item.find('.dcx-tree-select__checkbox [data-lucide]');
		if ($icon.length) {
			$icon.attr('data-lucide', checked ? 'square-check' : 'square');
			_renderIcons($icon.toArray());
		}

		_syncWellDelta(this.$well, this.selectedItems, { prefix: 'dcx-tree-select', inputName: this.inputName });
		this.syncHiddenInputs();
		this.$hidden.trigger('change');
	};

	TreeSelect.prototype.syncHiddenInputs = function() {
		if (!this.inputName || this.$well) return;
		var self = this;
		this.$el.find('input[type="hidden"][name="' + this.inputName + '"]').remove();
		$.each(this.selectedItems, function(id) {
			self.$el.append('<input type="hidden" name="' + self.inputName + '" value="' + id + '"/>');
		});
	};

	TreeSelect.prototype.syncCheckboxes = function() {
		var self = this;
		this.$list.find('.dcx-tree-select__item').each(function() {
			var id = $(this).data('id');
			var isChecked = self.selectedItems.hasOwnProperty(id);
			$(this).find('.dcx-tree-select__checkbox input').prop('checked', isChecked);
			var $icon = $(this).find('.dcx-tree-select__checkbox [data-lucide]');
			if ($icon.length) {
				$icon.attr('data-lucide', isChecked ? 'square-check' : 'square');
			}
		});
		_renderIcons();
	};

	TreeSelect.prototype.getSelectedIds = function() {
		return Object.keys(this.selectedItems);
	};

	TreeSelect.prototype.setSelected = function(ids) {
		this.selectedItems = {};
		var self = this;
		this.fetchTree().then(function(data) {
			if (ids && ids.length) {
				$.each(ids, function(i, id) {
					for (var j = 0; j < data.length; j++) {
						var item = data[j];
						var itemId = item[self.idKey] !== undefined ? item[self.idKey] : item.category_id;
						if (String(itemId) === String(id)) {
							self.selectedItems[id] = item.name;
							break;
						}
					}
				});
			}
			var filtered = self.filterCallback ? self.filterCallback(data) : data;
			self.renderList(filtered);
			self.$list.find('.dcx-tree-select__item').show();
			self.$list.find('.dcx-tree-select__arrow').each(function() {
				$(this).attr('data-expanded', 'true');
				$(this).find('i').attr('data-lucide', 'chevron-down');
			});
			_renderIcons();
			self.syncCheckboxes();
		});
	};

	TreeSelect.prototype.open = function() {
		var self = this;
		this.fetchTree().then(function(data) {
			var filtered = self.filterCallback ? self.filterCallback(data) : data;
			self.renderList(filtered);
			self.getVisibleIds();
			self.syncCheckboxes();
			self.$dropdown.show();
			self.$search.attr('placeholder', self.placeholder).val('').focus();
		});
	};

	TreeSelect.prototype.close = function() {
		this.$dropdown.hide();
	};

	/* ==================== Public API ==================== */

	return {
		init: function() {
			initPickers();
			initTreeSelects();
		},
		Picker: { init: initPickers },
		TreeSelect: { init: initTreeSelects }
	};
})();

/* Register jQuery autocomplete plugin (immediate, like the original dcx-autocomplete.js) */
(function($) {
	'use strict';
	if (!$) return;

	$.fn.autocomplete = function(option) {
		return this.each(function() {
			var $this = $(this);
			var el = this;

			el.timer = null;
			el.items = [];

			$.extend(el, option);

			$this.attr('autocomplete', 'off');

			var $wrapper = $('<div class="dc-autocomplete-wrap" />');
			$this.wrap($wrapper);
			var $wrapperEl = $this.closest('.dc-autocomplete-wrap');
			$wrapperEl.toggleClass('has-value', !!$this.val());

			var $clearBtn = $('<button type="button" class="dc-autocomplete-clear" tabindex="-1"><i data-lucide="x" width="12" height="12"></i></button>');
			$this.after($clearBtn);
			if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [$clearBtn.find('i')[0]] });

			var $dropdown = $('<div class="dcx-tree-select__dropdown" style="position:absolute;z-index:1050"></div>');
			var $list = $('<div class="dcx-tree-select__list"></div>');
			$dropdown.append($list);
			$wrapperEl.after($dropdown);

			$clearBtn.on('mousedown', function(e) {
				e.preventDefault();
			});

			$clearBtn.on('click', function() {
				$this.val('').trigger('change');
				$wrapperEl.removeClass('has-value');
				el.hide();
				$this.focus();
			});

			$this.on('focus', function() {
				el.request();
			});

			$this.on('blur', function() {
				setTimeout(function() {
					el.hide();
				}, 200);
			});

			$this.on('keydown', function(event) {
				switch (event.keyCode) {
					case 27:
						el.hide();
						break;
					default:
						el.request();
						break;
				}
			});

			$this.on('input change', function() {
				$wrapperEl.toggleClass('has-value', !!$this.val());
				if (!$this.val()) el.hide();
			});

			el.click = function(event) {
				event.preventDefault();
				var $item = $(event.target).closest('.dcx-tree-select__item');
				var value = $item.attr('data-value');
				if (value && el.items[value]) {
					el.select(el.items[value]);
					el.hide();
					$wrapperEl.toggleClass('has-value', !!$this.val());
				}
			};

			el.show = function() {
				var pos = $wrapperEl.position();
				$dropdown.css({
					top: pos.top + $wrapperEl.outerHeight(),
					left: pos.left,
					minWidth: $wrapperEl.outerWidth()
				});
				$dropdown.show();
			};

			el.hide = function() {
				$dropdown.hide();
			};

			el.request = function() {
				clearTimeout(el.timer);
				el.timer = setTimeout(function() {
					var val = $this.val();
					if (!val) return;
					el.source(val, $.proxy(el.response, el));
				}, 200);
			};

			el.response = function(json) {
				var category = {};
				var name;
				var i, j;

				$list.empty();
				el.items = {};

				if (json.length) {
					for (i = 0; i < json.length; i++) {
						el.items[json[i]['value']] = json[i];

						if (!json[i]['category']) {
							var label = $('<span>').text(json[i]['label']).html();
							$list.append('<div class="dcx-tree-select__item" data-value="' + json[i]['value'] + '"><span class="dcx-tree-select__label">' + label + '</span></div>');
						} else {
							name = json[i]['category'];
							if (!category[name]) {
								category[name] = [];
							}
							category[name].push(json[i]);
						}
					}

					for (name in category) {
						$list.append('<div class="dcx-tree-select__item--header">' + $('<span>').text(name).html() + '</div>');
						for (j = 0; j < category[name].length; j++) {
							var catLabel = $('<span>').text(category[name][j]['label']).html();
							$list.append('<div class="dcx-tree-select__item" data-value="' + category[name][j]['value'] + '"><span class="dcx-tree-select__label">' + catLabel + '</span></div>');
						}
					}
				}

				if (json.length) {
					el.show();
				} else {
					el.hide();
				}
			};

			$list.on('click', '.dcx-tree-select__item', $.proxy(el.click, el));
		});
	};
})(window.jQuery);

/* Auto-init on DOM ready */
$(function() {
	DcxUI.init();
});

/* Backward-compat aliases */
var DcxPicker = { init: function(s) { DcxUI.Picker.init(s); } };
var DcxTreeSelect = { init: function(s) { DcxUI.TreeSelect.init(s); } };
