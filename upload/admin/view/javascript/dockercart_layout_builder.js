(function() {
	'use strict';

	var config = window.DOCKERCART_BUILDER;
	var nextModuleIdx = config.layoutModules.length;
	var selectedCardEl = null;
	var sortables = [];

	function init() {
		lucide.createIcons();
		initSortables();
		initViewportSwitcher();
		initLangTabs();
		initModuleSearch();
		initSaveButton();
		initZoneAddButtons();
		initCardEventDelegation();
		initPropertiesPanel();
	}

	// ==================== SORTABLEJS ====================

	function initSortables() {
		var palette = document.getElementById('module-palette');
		if (palette) {
			var paletteSortable = new Sortable(palette, {
				group: { name: 'modules', pull: 'clone', put: false },
				sort: false,
				animation: 150,
				filter: '.palette-group-header',
				preventOnFilter: true
			});
			sortables.push(paletteSortable);
		}

		var zones = document.querySelectorAll('.zone-drop-area');
		zones.forEach(function(zone) {
			var zoneSortable = new Sortable(zone, {
				group: { name: 'modules', pull: true, put: true },
				animation: 150,
				ghostClass: 'sortable-ghost',
				chosenClass: 'sortable-chosen',
				handle: '.module-card',
				onAdd: function(evt) {
					handleDrop(evt);
				},
				onEnd: function() {
					updateEmptyStates();
				}
			});
			sortables.push(zoneSortable);
		});
	}

	function handleDrop(evt) {
		var item = evt.item;
		var paletteCode = item.getAttribute('data-palette-code');

		if (paletteCode) {
			var zoneEl = evt.to.closest('.zone-drop-area') || evt.to;
			var editUrl = buildEditUrl(paletteCode);
			var paletteName = item.getAttribute('data-palette-name');
			var paletteDisplay = item.getAttribute('data-palette-display') || paletteName;

			setTimeout(function() {
				var newCard = createModuleCard(paletteCode, paletteName, editUrl, paletteDisplay);
				item.replaceWith(newCard);
				lucide.createIcons();
				updateEmptyStates();
			}, 0);
		} else {
			updateEmptyStates();
		}
	}

	// ==================== MODULE CARD ====================

	function createModuleCard(code, name, editUrl, displayName) {
		var idx = nextModuleIdx;
		nextModuleIdx++;

		var div = document.createElement('div');
		div.className = 'module-card flex items-center gap-2 bg-white border border-gray-200 rounded px-2 py-1.5 mb-1';
		div.setAttribute('data-module-idx', idx);
		div.setAttribute('data-module-code', code);
		div.setAttribute('data-module-name', name);
		div.setAttribute('data-module-edit', editUrl);

		var gripIcon = document.createElement('i');
		gripIcon.setAttribute('data-lucide', 'grip-vertical');
		gripIcon.className = 'w-3.5 h-3.5 text-gray-300 shrink-0 cursor-grab';

		var nameSpan = document.createElement('span');
		nameSpan.className = 'flex-1 truncate text-xs font-medium';
		nameSpan.textContent = displayName || name;

		var editBtn = document.createElement('button');
		editBtn.type = 'button';
		editBtn.className = 'module-edit-btn text-gray-400 hover:text-blue-600 p-0.5';
		editBtn.title = config.texts.editModule;
		var editIcon = document.createElement('i');
		editIcon.setAttribute('data-lucide', 'settings');
		editIcon.className = 'w-3 h-3';
		editBtn.appendChild(editIcon);

		var removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'module-remove-btn text-gray-400 hover:text-red-600 p-0.5';
		removeBtn.title = config.texts.removeModule;
		var removeIcon = document.createElement('i');
		removeIcon.setAttribute('data-lucide', 'x');
		removeIcon.className = 'w-3 h-3';
		removeBtn.appendChild(removeIcon);

		div.appendChild(gripIcon);
		div.appendChild(nameSpan);
		div.appendChild(editBtn);
		div.appendChild(removeBtn);

		return div;
	}

	function buildEditUrl(code) {
		var parts = code.split('.');
		var ext = parts[0];
		var moduleId = parts[1];

		var url = 'index.php?route=extension/module/' + ext + '&user_token=' + config.userToken;
		if (moduleId) {
			url += '&module_id=' + moduleId;
		}
		return url;
	}

	// ==================== ZONE EMPTY STATES ====================

	function updateEmptyStates() {
		var zones = document.querySelectorAll('.zone-drop-area');
		zones.forEach(function(zone) {
			var cards = zone.querySelectorAll('.module-card');
			var existing = zone.querySelector('.zone-empty');

			if (cards.length === 0) {
				if (!existing) {
					var emptyDiv = document.createElement('div');
					emptyDiv.className = 'zone-empty flex items-center justify-center h-14 text-xs text-gray-400';
					emptyDiv.textContent = config.texts.dropZone;
					zone.appendChild(emptyDiv);
				}
			} else {
				if (existing) {
					existing.remove();
				}
			}
		});
	}

	// ==================== CARD EVENT DELEGATION ====================

	function initCardEventDelegation() {
		document.getElementById('page-canvas').addEventListener('click', function(e) {
			var card = e.target.closest('.module-card');
			if (!card) {
				if (e.target.closest('.zone-drop-area, .zone-add-btn')) {
					return;
				}
				deselectModule();
				return;
			}

			// Edit button
			if (e.target.closest('.module-edit-btn')) {
				var editUrl = card.getAttribute('data-module-edit');
				if (editUrl) window.open(editUrl, '_blank');
				return;
			}

			// Remove button
			if (e.target.closest('.module-remove-btn')) {
				removeModule(card);
				return;
			}

			// Select card
			selectModule(card);
		});
	}

	// ==================== MODULE SELECTION & PROPERTIES ====================

	function selectModule(cardEl) {
		if (selectedCardEl === cardEl) return;

		deselectModule();
		selectedCardEl = cardEl;
		cardEl.classList.add('selected');
		cardEl.style.boxShadow = '0 0 0 2px #3b82f6';
		cardEl.style.borderRadius = '5px';

		showProperties(cardEl);
	}

	function deselectModule() {
		if (selectedCardEl) {
			selectedCardEl.classList.remove('selected');
			selectedCardEl.style.boxShadow = '';
			selectedCardEl.style.borderRadius = '';
			selectedCardEl = null;
		}
		hideProperties();
	}

	function removeModule(cardEl) {
		if (selectedCardEl === cardEl) {
			deselectModule();
		}
		cardEl.remove();
		updateEmptyStates();
	}

	function showProperties(cardEl) {
		var placeholder = document.getElementById('properties-content');
		var form = document.getElementById('properties-form');

		placeholder.classList.add('hidden');
		form.classList.remove('hidden');

		var zone = cardEl.closest('.zone-drop-area');
		var currentZone = zone ? zone.getAttribute('data-zone') : 'content_top';

		var zoneCards = zone ? zone.querySelectorAll('.module-card') : [];
		var sortIdx = 0;
		zoneCards.forEach(function(c, i) {
			if (c === cardEl) sortIdx = i;
		});

		document.getElementById('prop-module-name').value = cardEl.getAttribute('data-module-name') || '';
		document.getElementById('prop-position').value = currentZone;
		document.getElementById('prop-sort-order').value = sortIdx;
		document.getElementById('prop-remove-btn').onclick = function() { removeModule(cardEl); };
		document.getElementById('prop-edit-btn').onclick = function() {
			var url = cardEl.getAttribute('data-module-edit');
			if (url) window.open(url, '_blank');
		};
	}

	function hideProperties() {
		document.getElementById('properties-content').classList.remove('hidden');
		document.getElementById('properties-form').classList.add('hidden');
	}

	function initPropertiesPanel() {
		// Filter position options based on active positions
		var propPosition = document.getElementById('prop-position');
		Array.from(propPosition.options).forEach(function(opt) {
			opt.style.display = config.activePositions.indexOf(opt.value) !== -1 ? '' : 'none';
		});

		propPosition.addEventListener('change', function() {
			if (!selectedCardEl) return;
			var newZone = this.value;
			var targetZone = document.querySelector('.zone-drop-area[data-zone="' + newZone + '"]');
			if (targetZone) {
				targetZone.appendChild(selectedCardEl);
				updateEmptyStates();
				updateModuleIndexes();
			}
		});

		document.getElementById('prop-sort-order').addEventListener('change', function() {
			if (!selectedCardEl) return;
			var targetIdx = parseInt(this.value, 10);
			if (isNaN(targetIdx) || targetIdx < 0) return;
			var zone = selectedCardEl.closest('.zone-drop-area');
			if (!zone) return;
			var cards = zone.querySelectorAll('.module-card');
			if (targetIdx >= cards.length) targetIdx = cards.length - 1;

			var cardArr = Array.from(cards);
			var currentIdx = cardArr.indexOf(selectedCardEl);
			if (currentIdx === -1) return;

			cardArr.splice(currentIdx, 1);
			cardArr.splice(targetIdx, 0, selectedCardEl);

			for (var i = 0; i < cardArr.length; i++) {
				zone.appendChild(cardArr[i]);
			}
			updateEmptyStates();
		});
	}

	// ==================== SAVE ====================

	function initSaveButton() {
		document.getElementById('btn-save').addEventListener('click', function() {
			saveLayout();
		});
	}

	function collectModules() {
		var modules = [];
		var zones = document.querySelectorAll('.zone-drop-area');
		zones.forEach(function(zone) {
			var position = zone.getAttribute('data-zone');
			var cards = zone.querySelectorAll('.module-card');
			cards.forEach(function(card, idx) {
				modules.push({
					code: card.getAttribute('data-module-code'),
					position: position,
					sort_order: idx
				});
			});
		});
		return modules;
	}

	function saveLayout() {
		var modules = collectModules();
		var body = new URLSearchParams();

		modules.forEach(function(m, i) {
			body.append('layout_module[' + i + '][code]', m.code);
			body.append('layout_module[' + i + '][position]', m.position);
			body.append('layout_module[' + i + '][sort_order]', m.sort_order);
		});

		// Preserve existing routes
		if (config.layoutRoutes) {
			config.layoutRoutes.forEach(function(route, i) {
				body.append('layout_route[' + i + '][store_id]', route.store_id);
				body.append('layout_route[' + i + '][route]', route.route);
			});
		}

		// Layout name (current language)
		var nameInput = document.getElementById('layout-name-input');
		var langId = nameInput.getAttribute('data-language-id');
		body.append('layout_description[' + langId + '][name]', nameInput.value);

		// Other language names
		var langTabs = document.querySelectorAll('.lang-tab');
		langTabs.forEach(function(tab) {
			var lid = tab.getAttribute('data-lang-id');
			var lname = tab.getAttribute('data-lang-name');
			if (lid && lid !== langId && lname) {
				body.append('layout_description[' + lid + '][name]', lname);
			}
		});

		var btnSave = document.getElementById('btn-save');
		btnSave.disabled = true;
		btnSave.classList.add('opacity-50');

		fetch(config.saveUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (data.error) {
				showToast(data.error, 'error');
			} else {
				showToast(config.texts.success, 'success');
			}
		})
		.catch(function(err) {
			showToast(config.texts.saveError + ': ' + err.message, 'error');
		})
		.finally(function() {
			btnSave.disabled = false;
			btnSave.classList.remove('opacity-50');
		});
	}

	// ==================== TOAST ====================

	function showToast(message, type) {
		var container = document.getElementById('toast-container');
		var toast = document.createElement('div');
		toast.className = 'toast px-4 py-2 rounded shadow-lg text-white text-sm max-w-xs ' +
			(type === 'error' ? 'bg-red-600' : 'bg-green-600');
		toast.textContent = message;
		container.appendChild(toast);

		setTimeout(function() {
			toast.style.opacity = '0';
			toast.style.transition = 'opacity 0.3s';
			setTimeout(function() { toast.remove(); }, 300);
		}, 3000);
	}

	// ==================== VIEWPORT SWITCHER ====================

	function initViewportSwitcher() {
		var container = document.getElementById('viewport-switcher');
		if (!container) return;

		container.addEventListener('click', function(e) {
			var btn = e.target.closest('button[data-viewport]');
			if (!btn) return;

			var viewport = btn.getAttribute('data-viewport');

			container.querySelectorAll('button[data-viewport]').forEach(function(b) {
				b.className = 'px-2 py-1 rounded-md text-xs font-medium text-gray-500 hover:text-gray-700';
			});
			btn.className = 'px-2 py-1 rounded-md text-xs font-medium bg-white shadow-sm text-gray-700';

			var canvasContainer = document.getElementById('canvas-container');
			canvasContainer.classList.remove('viewport-tablet', 'viewport-mobile');
			if (viewport !== 'desktop') {
				canvasContainer.classList.add('viewport-' + viewport);
			}
		});
	}

	// ==================== LANGUAGE TABS ====================

	function initLangTabs() {
		var tabsContainer = document.getElementById('lang-tabs');
		if (!tabsContainer) return;

		var nameInput = document.getElementById('layout-name-input');

		tabsContainer.addEventListener('click', function(e) {
			var tab = e.target.closest('.lang-tab');
			if (!tab) return;

			tabsContainer.querySelectorAll('.lang-tab').forEach(function(t) {
				t.classList.remove('active');
			});
			tab.classList.add('active');

			var langId = tab.getAttribute('data-lang-id');
			var langName = tab.getAttribute('data-lang-name');

			nameInput.setAttribute('data-language-id', langId);
			nameInput.value = langName || '';

			nameInput.focus();
		});

		nameInput.addEventListener('input', function() {
			var langId = nameInput.getAttribute('data-language-id');
			var activeTab = tabsContainer.querySelector('.lang-tab.active');
			if (activeTab) {
				activeTab.setAttribute('data-lang-name', nameInput.value);
			}
		});
	}

	// ==================== MODULE SEARCH ====================

	function initModuleSearch() {
		var searchInput = document.getElementById('module-search');
		if (!searchInput) return;

		searchInput.addEventListener('input', function() {
			var query = this.value.toLowerCase();
			var headers = document.querySelectorAll('.palette-group-header');

			headers.forEach(function(header) {
				var groupName = (header.getAttribute('data-group-name') || '').toLowerCase();
				var el = header.nextElementSibling;
				var items = [];
				while (el && !el.classList.contains('palette-group-header')) {
					if (el.classList.contains('palette-item')) {
						items.push(el);
					}
					el = el.nextElementSibling;
				}

				var hasVisible = false;
				var headerMatch = !query || groupName.indexOf(query) !== -1;

				items.forEach(function(item) {
					var code = (item.getAttribute('data-palette-code') || '').toLowerCase();
					var name = (item.getAttribute('data-palette-name') || '').toLowerCase();
					var match = !query || code.indexOf(query) !== -1 || name.indexOf(query) !== -1;
					item.style.display = match ? '' : 'none';
					if (match) hasVisible = true;
				});

				header.style.display = (headerMatch || hasVisible) ? '' : 'none';
			});
		});
	}

	// ==================== ZONE ADD BUTTONS ====================

	function initZoneAddButtons() {
		document.getElementById('page-canvas').addEventListener('click', function(e) {
			var btn = e.target.closest('.zone-add-btn');
			if (!btn) return;

			var zoneId = btn.getAttribute('data-zone');
			var searchInput = document.getElementById('module-search');
			if (searchInput) {
				searchInput.focus();
				searchInput.value = '';
				searchInput.dispatchEvent(new Event('input'));
			}

			// Highlight the search field briefly
			if (searchInput) {
				searchInput.style.borderColor = '#3b82f6';
				setTimeout(function() { searchInput.style.borderColor = ''; }, 1500);
			}
		});
	}

	// ==================== INIT ====================

	document.addEventListener('DOMContentLoaded', init);
})();