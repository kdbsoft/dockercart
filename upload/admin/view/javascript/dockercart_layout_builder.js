(function() {
	'use strict';

	var config = window.DOCKERCART_BUILDER;
	var nextModuleIdx = config.layoutModules.length;
	var selectedCardEl = null;
	var sortables = [];
	var dirty = false;
	var saving = false;
	var leaving = false;
	var pendingZone = null;
	var routes = (config.layoutRoutes || []).map(function(route) {
		return {
			store_id: String(route.store_id !== undefined ? route.store_id : 0),
			route: route.route || ''
		};
	});

	function init() {
		lucide.createIcons();
		initSortables();
		initViewportSwitcher();
		initModuleSearch();
		initSaveButton();
		initZoneAddButtons();
		initPaletteAdd();
		initDirtyTracking();
		initShortcuts();
		initCardEventDelegation();
		initPropertiesPanel();
		initRoutes();
	}

	// ==================== SORTABLEJS ====================

	function initSortables() {
		// One Sortable per palette group: Sortable only drags direct children
		// of its container, and the cards live inside details > .lb-grid.
		document.querySelectorAll('#module-palette .lb-grid').forEach(function(grid) {
			var gridSortable = new Sortable(grid, {
				group: { name: 'modules', pull: 'clone', put: false },
				sort: false,
				animation: 150,
				draggable: '.palette-item'
			});
			sortables.push(gridSortable);
		});

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
					markDirty();
				}
			});
			sortables.push(zoneSortable);
		});
	}

	function handleDrop(evt) {
		var item = evt.item;

		if (!item.hasAttribute('data-palette-code')) {
			updateEmptyStates();
			return;
		}

		setTimeout(function() {
			var meta = paletteMeta(item);
			if (!meta) return;

			var newCard = createModuleCard(meta.code, meta.name, buildEditUrl(meta.code), meta.display, meta.icon);
			item.replaceWith(newCard);
			lucide.createIcons();
			updateEmptyStates();
			markDirty();
		}, 0);
	}

	// ==================== MODULE CARD ====================

	function createModuleCard(code, name, editUrl, displayName, icon) {
		var idx = nextModuleIdx;
		nextModuleIdx++;

		var div = document.createElement('div');
		div.className = 'module-card flex items-center gap-3 bg-white border border-gray-200 rounded px-3 py-2 mb-1.5';
		div.setAttribute('data-module-idx', idx);
		div.setAttribute('data-module-code', code);
		div.setAttribute('data-module-name', name);
		div.setAttribute('data-module-edit', editUrl);

		var gripIcon = document.createElement('i');
		gripIcon.setAttribute('data-lucide', 'grip-vertical');
		gripIcon.className = 'w-3.5 h-3.5 text-gray-300 shrink-0 cursor-grab';

		var typeIcon = document.createElement('i');
		typeIcon.setAttribute('data-lucide', icon || 'box');
		typeIcon.className = 'w-4 h-4 text-blue-500 shrink-0';

		var nameSpan = document.createElement('span');
		nameSpan.className = 'flex-1 truncate text-sm font-medium';
		nameSpan.textContent = displayName || name;

		var editBtn = document.createElement('button');
		editBtn.type = 'button';
		editBtn.className = 'module-edit-btn text-gray-400 hover:text-blue-600 p-1';
		editBtn.setAttribute('aria-label', config.texts.editModule);
		var editIcon = document.createElement('i');
		editIcon.setAttribute('data-lucide', 'settings');
		editIcon.className = 'w-3.5 h-3.5';
		editBtn.appendChild(editIcon);

		var removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'module-remove-btn text-gray-400 hover:text-red-600 p-1';
		removeBtn.setAttribute('aria-label', config.texts.removeModule);
		var removeIcon = document.createElement('i');
		removeIcon.setAttribute('data-lucide', 'x');
		removeIcon.className = 'w-3.5 h-3.5';
		removeBtn.appendChild(removeIcon);

		div.appendChild(gripIcon);
		div.appendChild(typeIcon);
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

	// Shared by drag&drop and click-to-add: both read the same palette data.
	function paletteMeta(card) {
		var code = card.getAttribute('data-palette-code');
		if (!code) return null;

		var name = card.getAttribute('data-palette-name') || code;

		return {
			code: code,
			name: name,
			display: card.getAttribute('data-palette-display') || name,
			icon: card.getAttribute('data-extension-icon') || 'box'
		};
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
					emptyDiv.className = 'zone-empty flex items-center justify-center gap-2 h-16 text-xs text-gray-400';
					emptyDiv.innerHTML = '<i data-lucide="plus" class="w-3.5 h-3.5"></i> ' + config.texts.dropZone;
					zone.appendChild(emptyDiv);
					lucide.createIcons();
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
		cardEl.style.boxShadow = '0 0 0 2px #5b7ea8';
		cardEl.style.borderRadius = '6px';

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
		markDirty();
	}

	function setPanelMode(mode) {
		var properties = mode === 'properties';

		document.getElementById('properties-content').classList.toggle('hidden', properties);
		document.getElementById('properties-form').classList.toggle('hidden', !properties);
		document.getElementById('panel-title-routes').classList.toggle('hidden', properties);
		document.getElementById('panel-title-properties').classList.toggle('hidden', !properties);
	}

	function showProperties(cardEl) {
		setPanelMode('properties');

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
		setPanelMode('routes');
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
		if (saving) return;

		var modules = collectModules();
		var body = new URLSearchParams();

		modules.forEach(function(m, i) {
			body.append('layout_module[' + i + '][code]', m.code);
			body.append('layout_module[' + i + '][position]', m.position);
			body.append('layout_module[' + i + '][sort_order]', m.sort_order);
		});

		// Routes (edited in the Routes modal). The marker is always sent so that
		// an emptied list clears the stored routes instead of preserving them.
		body.append('layout_routes_sent', '1');
		routes.forEach(function(route, i) {
			body.append('layout_route[' + i + '][store_id]', route.store_id);
			body.append('layout_route[' + i + '][route]', route.route);
		});

		// Layout name per language (Page Settings rows)
		document.querySelectorAll('.layout-name-row input').forEach(function(input) {
			var lid = input.getAttribute('data-language-id');
			if (lid) {
				body.append('layout_description[' + lid + '][name]', input.value);
			}
		});

		// Modules outside this route's canvas are posted back untouched.
		document.querySelectorAll('#hidden-modules input[name]').forEach(function(input) {
			body.append(input.name, input.value);
		});

		var btnSave = document.getElementById('btn-save');
		var btnLabel = btnSave.querySelector('span');
		var saveLabel = btnLabel ? btnLabel.textContent : '';
		saving = true;
		btnSave.disabled = true;
		btnSave.classList.add('opacity-50');
		if (btnLabel) btnLabel.textContent = config.texts.saving;

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
				markClean();
				showToast(config.texts.success, 'success');
			}
		})
		.catch(function(err) {
			showToast(config.texts.saveError + ': ' + err.message, 'error');
		})
		.finally(function() {
			saving = false;
			btnSave.disabled = false;
			btnSave.classList.remove('opacity-50');
			if (btnLabel) btnLabel.textContent = saveLabel;
		});
	}

	// ==================== ROUTES (inline in the Properties panel) ====================

	function initRoutes() {
		document.getElementById('btn-route-add').addEventListener('click', function() {
			routes.push({ store_id: '0', route: '' });
			renderRoutes(true);
			markDirty();
		});

		document.getElementById('routes-list').addEventListener('input', onRouteInput);
		document.getElementById('routes-list').addEventListener('change', onRouteInput);
		document.getElementById('routes-list').addEventListener('click', function(e) {
			var btn = e.target.closest('[data-route-remove]');
			if (!btn) return;
			routes.splice(parseInt(btn.getAttribute('data-route-remove'), 10), 1);
			renderRoutes(false);
			markDirty();
		});

		renderRoutes(false);
	}

	function onRouteInput(e) {
		var field = e.target.closest('[data-route-idx]');
		if (!field) return;
		var idx = parseInt(field.getAttribute('data-route-idx'), 10);
		if (!routes[idx]) return;
		if (field.hasAttribute('data-route-store')) {
			routes[idx].store_id = field.value;
		} else {
			routes[idx].route = field.value;
		}
		updateRoutesCount();
	}

	function renderRoutes(focusLast) {
		var list = document.getElementById('routes-list');
		list.innerHTML = '';

		if (routes.length === 0) {
			var empty = document.createElement('div');
			empty.className = 'text-center text-xs text-gray-400 py-4';
			empty.textContent = config.texts.noRoutes;
			list.appendChild(empty);
		} else {
			routes.forEach(function(route, i) {
				list.appendChild(buildRouteRow(route, i));
			});
		}

		lucide.createIcons();
		updateRoutesCount();

		if (focusLast) {
			var inputs = list.querySelectorAll('input[data-route-idx]');
			if (inputs.length) inputs[inputs.length - 1].focus();
		}
	}

	function buildRouteRow(route, idx) {
		var row = document.createElement('div');
		row.className = 'border border-gray-200 rounded p-2 flex flex-col gap-1.5 bg-gray-50';

		var storeSelect = document.createElement('select');
		storeSelect.className = 'lb-input';
		storeSelect.setAttribute('data-route-idx', idx);
		storeSelect.setAttribute('data-route-store', '1');
		storeSelect.setAttribute('aria-label', config.texts.store);

		var defaultOpt = document.createElement('option');
		defaultOpt.value = '0';
		defaultOpt.textContent = config.texts.defaultStore;
		storeSelect.appendChild(defaultOpt);

		(config.stores || []).forEach(function(store) {
			var opt = document.createElement('option');
			opt.value = String(store.store_id);
			opt.textContent = store.name;
			storeSelect.appendChild(opt);
		});
		storeSelect.value = route.store_id;

		var routeLine = document.createElement('div');
		routeLine.className = 'flex gap-1';

		var routeInput = document.createElement('input');
		routeInput.type = 'text';
		routeInput.className = 'flex-1 min-w-0 lb-input';
		routeInput.placeholder = 'common/home';
		routeInput.setAttribute('aria-label', config.texts.route);
		routeInput.value = route.route;
		routeInput.setAttribute('data-route-idx', idx);

		var removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'text-gray-400 hover:text-red-600 p-1 shrink-0';
		removeBtn.setAttribute('aria-label', config.texts.remove);
		removeBtn.setAttribute('data-route-remove', idx);
		var removeIcon = document.createElement('i');
		removeIcon.setAttribute('data-lucide', 'x');
		removeIcon.className = 'w-3.5 h-3.5 pointer-events-none';
		removeBtn.appendChild(removeIcon);

		routeLine.appendChild(routeInput);
		routeLine.appendChild(removeBtn);

		row.appendChild(storeSelect);
		row.appendChild(routeLine);

		return row;
	}

	function updateRoutesCount() {
		document.getElementById('routes-count').textContent = routes.length;
	}

	// ==================== TOAST ====================

	function showToast(message, type) {
		var container = document.getElementById('toast-container');
		var toast = document.createElement('div');
		toast.className = 'toast px-4 py-2 rounded shadow-lg text-white text-sm max-w-xs ' +
			(type === 'error' ? 'bg-red-600' : 'bg-gray-900');
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
				b.setAttribute('aria-pressed', 'false');
			});
			btn.className = 'px-2 py-1 rounded-md text-xs font-medium bg-white shadow-sm text-gray-700';
			btn.setAttribute('aria-pressed', 'true');

			var canvasContainer = document.getElementById('canvas-container');
			canvasContainer.classList.remove('viewport-tablet', 'viewport-mobile');
			if (viewport !== 'desktop') {
				canvasContainer.classList.add('viewport-' + viewport);
			}
		});
	}

	// ==================== MODULE SEARCH ====================

	function initModuleSearch() {
		var searchInput = document.getElementById('module-search');
		if (!searchInput) return;

		searchInput.addEventListener('input', function() {
			var query = this.value.toLowerCase().trim();

			document.querySelectorAll('#module-palette .lb-group').forEach(function(group) {
				var visible = 0;

				group.querySelectorAll('.palette-item').forEach(function(item) {
					var haystack = item.getAttribute('data-search') || '';
					var match = !query || haystack.indexOf(query) !== -1;
					item.style.display = match ? '' : 'none';
					if (match) visible++;
				});

				// Open the group while searching so matches stay visible.
				if (query && visible) {
					group.open = true;
				}

				group.style.display = visible ? '' : 'none';
				});
		});
	}

	// ==================== UNSAVED CHANGES ====================

	function initDirtyTracking() {
		document.addEventListener('input', onEditableChange);
		document.addEventListener('change', onEditableChange);

		window.addEventListener('beforeunload', function(e) {
			if (!dirty || leaving) return;
			e.preventDefault();
			e.returnValue = '';
		});

		var backLink = document.querySelector('header a[href]');
		if (backLink) {
			backLink.addEventListener('click', function(e) {
				if (!dirty) return;

				if (window.confirm(config.texts.unsavedConfirm || 'You have unsaved changes. Leave the builder?')) {
					leaving = true;
				} else {
					e.preventDefault();
				}
			});
		}
	}

	function onEditableChange(e) {
		var target = e.target;
		if (!target || !target.matches) return;

		if (target.matches('.layout-name-row input, #routes-list input, #routes-list select, #prop-position, #prop-sort-order')) {
			markDirty();
		}
	}

	function markDirty() {
		if (dirty) return;

		dirty = true;
		updateSaveState();
	}

	function markClean() {
		dirty = false;
		updateSaveState();
	}

	function updateSaveState() {
		var el = document.getElementById('save-state');
		if (!el) return;

		el.textContent = dirty ? (config.texts.unsaved || '') : (config.texts.saved || '');
		el.classList.toggle('text-amber-700', dirty);
		el.classList.toggle('text-gray-400', !dirty);
	}

	// ==================== ZONE ADD BUTTONS / PALETTE CLICK ====================

	function initZoneAddButtons() {
		document.getElementById('page-canvas').addEventListener('click', function(e) {
			var btn = e.target.closest('.zone-add-btn');
			if (!btn) return;

			pendingZone = btn.getAttribute('data-zone');
			clearZoneHighlight();
			var targetZone = document.querySelector('.zone-drop-area[data-zone="' + pendingZone + '"]');
			if (targetZone) targetZone.classList.add('zone-targeted');

			var searchInput = document.getElementById('module-search');
			if (searchInput) {
				searchInput.value = '';
				searchInput.dispatchEvent(new Event('input'));
				searchInput.focus();
			}

			var zoneTitle = getZoneTitle(pendingZone);
			if (zoneTitle && config.texts.pickModule) {
				showToast(config.texts.pickModule.replace('%s', zoneTitle), 'success');
			}
		});
	}

	function clearZoneHighlight() {
		document.querySelectorAll('.zone-drop-area.zone-targeted').forEach(function(zone) {
			zone.classList.remove('zone-targeted');
		});
	}

	function clearPendingZone() {
		pendingZone = null;
		clearZoneHighlight();
	}

	function getZoneTitle(zoneId) {
		var zone = document.querySelector('.zone-drop-area[data-zone="' + zoneId + '"]');
		var label = zone ? zone.parentElement.querySelector('.zone-head > span') : null;
		return label ? label.textContent.trim() : '';
	}

	function initPaletteAdd() {
		var palette = document.getElementById('module-palette');
		if (!palette) return;

		palette.addEventListener('click', function(e) {
			var card = e.target.closest('.palette-item');
			if (card) addModuleFromPalette(card);
		});

		palette.addEventListener('keydown', function(e) {
			if (e.key !== 'Enter' && e.key !== ' ') return;
			var card = e.target.closest('.palette-item');
			if (!card) return;

			e.preventDefault();
			addModuleFromPalette(card);
		});
	}

	function addModuleFromPalette(card) {
		var zoneId = pendingZone || 'content_top';
		var targetZone = document.querySelector('.zone-drop-area[data-zone="' + zoneId + '"]');
		if (!targetZone) return;

		var meta = paletteMeta(card);
		if (!meta) return;

		var newCard = createModuleCard(meta.code, meta.name, buildEditUrl(meta.code), meta.display, meta.icon);

		targetZone.appendChild(newCard);
		lucide.createIcons();
		updateEmptyStates();
		markDirty();
		clearPendingZone();

		var zoneTitle = getZoneTitle(zoneId);
		if (zoneTitle && config.texts.moduleAdded) {
			showToast(config.texts.moduleAdded.replace('%s', zoneTitle), 'success');
		}

		newCard.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		selectModule(newCard);
	}

	function initShortcuts() {
		// Same as Page Builder: Ctrl+S saves without leaving the keyboard.
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && pendingZone) {
				clearPendingZone();
				return;
			}

			var target = e.target;

			if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable)) {
				return;
			}

			// event.code (not key) so it fires on any keyboard layout.
			if ((e.ctrlKey || e.metaKey) && e.code === 'KeyS') {
				e.preventDefault();
				saveLayout();
			}
		});
	}

	// ==================== INIT ====================

	document.addEventListener('DOMContentLoaded', init);
})();