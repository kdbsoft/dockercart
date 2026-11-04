/**
 * DockerCart landing page builder (admin shell).
 *
 * The canvas is the real storefront rendered in an iframe
 * (index.php?route=landing/page&…&lp_builder=1). This script owns the page
 * model, the palette, the properties panel and autosave; the iframe script
 * (catalog/view/javascript/dockercart_landing_builder.js) owns dragging and
 * selection inside the page and talks to this script by postMessage.
 *
 * To the iframe:
 *   lp:select        {blockId}             highlight + scroll to a block
 *   lp:refreshBlock  {blockId, url}        re-fetch one rendered block
 * From the iframe:
 *   lp:ready    {order}                    iframe is interactive
 *   lp:selected {blockId}
 *   lp:add      {code, afterId}
 *   lp:move     {order: [blockId, …]}
 *   lp:action   {action, blockId}          duplicate|remove|up|down|settings
 */
(function () {
	'use strict';

	var CFG = window.DC_LANDING || {};
	var I18N = CFG.i18n || {};

	var WRAPPER_KEYS = [
		'bg_type', 'bg_color', 'bg_image', 'overlay', 'padding', 'container',
		'align', 'hide_desktop', 'hide_tablet', 'hide_mobile', 'anchor', 'custom_class'
	];

	var state = {
		page: {
			name: '',
			status: 0,
			chrome: 'full',
			is_home: 0,
			noindex: 0,
			custom_css: '',
			date_publish: '',
			date_unpublish: '',
			descriptions: {}
		},
		blocks: [],
		version: 1
	};

	var ui = {
		lang: CFG.defaultLanguageId || 1,
		selectedId: 0,
		viewport: 'desktop',
		iframeLang: null,
		iframeReady: false,
		history: [],
		future: [],
		dirty: false,
		saving: false,
		saveQueued: false,
		saveTimer: null,
		needReload: false,
		dirtyBlocks: {},
		pendingSelectIndex: null,
		names: {},
		previewBase: CFG.previewBase || '',
		tokenAt: Date.now(),
		templates: CFG.templates || []
	};

	/* ==================== helpers ==================== */

	function t(key, fallback) {
		return I18N[key] || fallback || key;
	}

	function $(id) {
		return document.getElementById(id);
	}

	function esc(value) {
		return String(value === null || value === undefined ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function clone(value) {
		if (value === null || typeof value !== 'object') return value;
		return JSON.parse(JSON.stringify(value));
	}

	function toast(message, type) {
		var container = $('lp-toast-container');
		if (!container) return;

		var el = document.createElement('div');
		el.className = 'lp-toast px-3 py-2 rounded shadow-lg text-white text-xs max-w-xs ' + (type === 'error' ? 'bg-red-600' : 'bg-gray-900');
		el.textContent = message;
		container.appendChild(el);

		setTimeout(function () {
			el.style.opacity = '0';
			el.style.transition = 'opacity .3s';
			setTimeout(function () { el.remove(); }, 320);
		}, type === 'error' ? 5000 : 2200);
	}

	function langCodeById(languageId) {
		for (var i = 0; i < (CFG.languages || []).length; i++) {
			if (Number(CFG.languages[i].language_id) === Number(languageId)) {
				return CFG.languages[i].code;
			}
		}

		return null;
	}

	function fieldDefinition(code, key) {
		var schema = CFG.schema[code];

		if (!schema) return null;

		for (var i = 0; i < schema.fields.length; i++) {
			if (schema.fields[i].key === key) return schema.fields[i];
		}

		return null;
	}

	function emptyContent() {
		var content = {};

		(CFG.languages || []).forEach(function (language) {
			content[Number(language.language_id)] = {};
		});

		return content;
	}

	function normalizeBlock(block) {
		block.block_id = Number(block.block_id || 0);
		block.status = block.status === undefined ? 1 : Number(block.status);
		block.settings = block.settings && typeof block.settings === 'object' ? block.settings : {};

		if (!block.content || typeof block.content !== 'object') {
			block.content = {};
		}

		(CFG.languages || []).forEach(function (language) {
			var id = Number(language.language_id);

			if (!block.content[id] || typeof block.content[id] !== 'object') {
				block.content[id] = {};
			}
		});

		return block;
	}

	function blockById(id) {
		id = Number(id);

		for (var i = 0; i < state.blocks.length; i++) {
			if (Number(state.blocks[i].block_id) === id) return state.blocks[i];
		}

		return null;
	}

	function blockIndexById(id) {
		id = Number(id);

		for (var i = 0; i < state.blocks.length; i++) {
			if (Number(state.blocks[i].block_id) === id) return i;
		}

		return -1;
	}

	function getValue(field, block) {
		if (field.localized) {
			var content = block.content[ui.lang] || {};

			return content[field.key] !== undefined ? content[field.key] : field.default;
		}

		return block.settings[field.key] !== undefined ? block.settings[field.key] : field.default;
	}

	function setValue(block, field, value) {
		if (field.localized) {
			if (!block.content[ui.lang]) {
				block.content[ui.lang] = {};
			}

			block.content[ui.lang][field.key] = value;
		} else {
			block.settings[field.key] = value;
		}
	}

	/* ==================== history ==================== */

	function snapshot() {
		return JSON.stringify({ page: state.page, blocks: state.blocks });
	}

	function pushHistory() {
		ui.history.push(snapshot());

		if (ui.history.length > 50) {
			ui.history.shift();
		}

		ui.future = [];
	}

	function restoreSnapshot(json) {
		var parsed = JSON.parse(json);

		state.page = parsed.page;
		state.blocks = (parsed.blocks || []).map(normalizeBlock);
		ui.dirty = true;
		updateSaveState();
		updateStatusBadge();
		renderSettings();
		renderProperties();
		touch({ structural: true });
	}

	function undo() {
		if (!ui.history.length) return;

		ui.future.push(snapshot());
		restoreSnapshot(ui.history.pop());
	}

	function redo() {
		if (!ui.future.length) return;

		ui.history.push(snapshot());
		restoreSnapshot(ui.future.pop());
	}

	/* ==================== dirty / saving ==================== */

	function touch(options) {
		options = options || {};

		if (options.structural) {
			ui.needReload = true;
		}

		if (options.blockId) {
			ui.dirtyBlocks[Number(options.blockId)] = true;
		}

		ui.dirty = true;
		updateSaveState();
		scheduleSave(options.delay);
	}

	function scheduleSave(delay) {
		if (ui.saveTimer) {
			clearTimeout(ui.saveTimer);
		}

		ui.saveTimer = setTimeout(function () {
			save(false);
		}, typeof delay === 'number' ? delay : 900);
	}

	function payload() {
		return {
			version: state.version,
			name: state.page.name,
			status: Number(state.page.status) || 0,
			chrome: state.page.chrome,
			is_home: Number(state.page.is_home) || 0,
			noindex: Number(state.page.noindex) || 0,
			custom_css: state.page.custom_css,
			date_publish: state.page.date_publish,
			date_unpublish: state.page.date_unpublish,
			descriptions: state.page.descriptions,
			blocks: state.blocks.map(function (block) {
				return {
					block_id: Number(block.block_id) || 0,
					code: block.code,
					status: Number(block.status),
					settings: block.settings,
					content: block.content
				};
			})
		};
	}

	function save(force) {
		if (ui.saving) {
			ui.saveQueued = true;
			return;
		}

		if (!ui.dirty && !force) {
			return;
		}

		ui.saving = true;
		ui.saveTimer = null;
		updateSaveState();

		fetch(CFG.urls.save, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload())
		})
			.then(function (response) { return response.json(); })
			.then(function (json) {
				ui.saving = false;

				if (json.error) {
					updateSaveState();
					toast(json.error, 'error');

					if (json.conflict) {
						if (window.confirm(json.error)) {
							window.location.reload();
						}
					}

					return;
				}

				ui.dirty = false;
				state.version = Number(json.version) || state.version + 1;

				// The server assigns ids to new blocks; keep the client list in sync
				// (the save endpoint preserves the submitted order).
				if (json.blocks && json.blocks.length === state.blocks.length) {
					json.blocks.forEach(function (saved, index) {
						state.blocks[index].block_id = Number(saved.block_id);
					});
				}

				var reload = ui.needReload;
				var changed = Object.keys(ui.dirtyBlocks);
				ui.needReload = false;
				ui.dirtyBlocks = {};

				updateSaveState();
				updateStatusBadge();
				updatePreviewLink();

				if (reload) {
					reloadIframe(ui.pendingSelectIndex);
					ui.pendingSelectIndex = null;
				} else {
					changed.forEach(function (blockId) {
						refreshBlock(blockId);
					});

					if (ui.selectedId) {
						postToIframe({ type: 'lp:select', blockId: ui.selectedId });
					}
				}

				if (ui.saveQueued) {
					ui.saveQueued = false;
					ui.dirty = true;
					scheduleSave(200);
				}
			})
			.catch(function (error) {
				ui.saving = false;
				updateSaveState();
				toast(t('text_error_save', 'Could not save') + ': ' + error.message, 'error');
			});
	}

	function updateSaveState() {
		var el = $('lp-save-state');
		if (!el) return;

		if (ui.saving) {
			el.textContent = t('text_saving', 'Saving…');
			el.className = 'text-[11px] text-blue-600';
		} else if (ui.dirty) {
			el.textContent = t('text_unsaved', 'Unsaved changes');
			el.className = 'text-[11px] text-amber-600';
		} else {
			el.textContent = t('text_saved', 'Saved');
			el.className = 'text-[11px] text-emerald-600';
		}
	}

	function updateStatusBadge() {
		var badge = $('lp-status-badge');
		if (!badge) return;

		badge.classList.remove('hidden');

		if (Number(state.page.status)) {
			badge.textContent = t('text_status_published', 'Published');
			badge.className = 'shrink-0 text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700';
		} else {
			badge.textContent = t('text_status_draft', 'Draft');
			badge.className = 'shrink-0 text-[11px] px-2 py-0.5 rounded-full bg-amber-50 text-amber-700';
		}
	}

	/* ==================== preview iframe ==================== */

	function previewBaseFor(languageId) {
		var bases = CFG.previewBases || {};

		return bases[languageId] || ui.previewBase;
	}

	function buildPreviewUrl(extra) {
		var url = previewBaseFor(ui.lang);

		// Language normally follows the keyword itself; switch_lang is only
		// needed when two languages share the same slug (or a slug is missing).
		var code = langCodeById(ui.lang);

		if (code && code !== ui.iframeLang) {
			url += '&switch_lang=' + encodeURIComponent(code);
			ui.iframeLang = code;
		}

		if (extra) {
			url += '&' + extra;
		}

		ui.previewBase = url.split('&switch_lang')[0];

		return url;
	}

	function postToIframe(message) {
		var frame = $('lp-canvas-frame');

		if (!frame || !frame.contentWindow) return;

		try {
			frame.contentWindow.postMessage(message, '*');
		} catch (error) {
			// iframe not ready yet
		}
	}

	function reloadIframe(selectIndex) {
		var frame = $('lp-canvas-frame');

		if (!frame) return;

		ui.iframeReady = false;

		refreshToken(function () {
			frame.src = buildPreviewUrl('_=' + Date.now());

			if (selectIndex === null || selectIndex === undefined) return;

			var tries = 0;
			var wait = setInterval(function () {
				tries++;

				if (ui.iframeReady) {
					clearInterval(wait);

					var block = stateFramesBlock(selectIndex);

					if (block && Number(block.block_id)) {
						selectBlock(Number(block.block_id));
					}
				}

				if (tries > 120) {
					clearInterval(wait);
				}
			}, 150);
		});
	}

	function stateFramesBlock(index) {
		return state.blocks[index] || null;
	}

	function refreshBlock(blockId) {
		blockId = Number(blockId);

		if (!blockId || !ui.iframeReady) return;

		postToIframe({
			type: 'lp:refreshBlock',
			blockId: blockId,
			url: previewBaseFor(ui.lang) + '&only_block=' + blockId + '&_=' + Date.now()
		});
	}

	function onIframeMessage(event) {
		var frame = $('lp-canvas-frame');

		if (frame && event.source !== frame.contentWindow) {
			return;
		}

		var data = event.data;

		if (!data || typeof data !== 'object' || typeof data.type !== 'string') {
			return;
		}

		switch (data.type) {
			case 'lp:ready':
				ui.iframeReady = true;

				// Open with the first section selected, so the properties panel is
				// never an empty dead end on the first visit.
				if (!ui.selectedId && state.blocks.length && Number(state.blocks[0].block_id)) {
					selectBlock(Number(state.blocks[0].block_id));
				}
				break;

			case 'lp:selected':
				selectBlock(Number(data.blockId), true);
				break;

			case 'lp:add':
				addBlock(data.code, Number(data.afterId) || 0);
				break;

			case 'lp:move':
				applyOrder((data.order || []).map(Number));
				break;

			case 'lp:action':
				performAction(data.action, Number(data.blockId) || ui.selectedId);
				break;
		}
	}

	/* ==================== selection & block actions ==================== */

	function selectBlock(blockId, fromIframe) {
		ui.selectedId = Number(blockId) || 0;

		if (!fromIframe) {
			postToIframe({ type: 'lp:select', blockId: ui.selectedId });
		}

		renderProperties();
	}

	function addBlock(code, afterId) {
		if (!CFG.schema[code]) return;

		pushHistory();

		var settings = {};
		var content = emptyContent();

		CFG.schema[code].fields.forEach(function (field) {
			if (field.localized) {
				(CFG.languages || []).forEach(function (language) {
					content[Number(language.language_id)][field.key] = clone(field.default);
				});
			} else {
				settings[field.key] = clone(field.default);
			}
		});

		var block = normalizeBlock({
			block_id: 0,
			code: code,
			status: 1,
			settings: settings,
			content: content
		});

		var index = blockIndexById(afterId);

		if (index >= 0) {
			state.blocks.splice(index + 1, 0, block);
			index = index + 1;
		} else {
			state.blocks.push(block);
			index = state.blocks.length - 1;
		}

		ui.pendingSelectIndex = index;
		ui.selectedId = 0;

		touch({ structural: true, delay: 150 });
		renderProperties();
	}

	function duplicateBlock(blockId) {
		var index = blockIndexById(blockId);

		if (index < 0) return;

		pushHistory();

		var copy = normalizeBlock(clone(state.blocks[index]));
		copy.block_id = 0;
		copy.status = 1;

		state.blocks.splice(index + 1, 0, copy);

		ui.pendingSelectIndex = index + 1;
		ui.selectedId = 0;

		touch({ structural: true, delay: 150 });
		renderProperties();
	}

	function removeBlock(blockId) {
		var index = blockIndexById(blockId);

		if (index < 0) return;

		pushHistory();

		state.blocks.splice(index, 1);
		ui.selectedId = 0;
		ui.dirtyBlocks = {};

		touch({ structural: true, delay: 150 });
		renderProperties();
	}

	function moveBlock(blockId, direction) {
		var index = blockIndexById(blockId);

		if (index < 0) return;

		var target = direction === 'up' ? index - 1 : index + 1;

		if (target < 0 || target >= state.blocks.length) return;

		pushHistory();

		var block = state.blocks.splice(index, 1)[0];
		state.blocks.splice(target, 0, block);

		touch({ structural: true, delay: 150 });
		postToIframe({ type: 'lp:select', blockId: blockId });
	}

	function applyOrder(order) {
		if (!order.length) return;

		var ordered = [];

		order.forEach(function (id) {
			var block = blockById(id);

			if (block) ordered.push(block);
		});

		if (ordered.length !== state.blocks.length) {
			reloadIframe();
			return;
		}

		pushHistory();
		state.blocks = ordered;

		touch({ structural: true, delay: 150 });
	}

	function performAction(action, blockId) {
		switch (action) {
			case 'duplicate':
				duplicateBlock(blockId);
				break;
			case 'remove':
				if (window.confirm(t('text_remove', 'Remove') + '?')) {
					removeBlock(blockId);
				}
				break;
			case 'up':
				moveBlock(blockId, 'up');
				break;
			case 'down':
				moveBlock(blockId, 'down');
				break;
			case 'settings':
				selectBlock(blockId, true);
				break;
		}
	}

	/* ==================== palette ==================== */

	function initPalette() {
		var palette = $('lp-palette');
		if (!palette) return;

		palette.addEventListener('dragstart', function (event) {
			var item = event.target.closest('.lp-palette-item');
			if (!item) return;

			event.dataTransfer.setData('application/x-lp-block', item.getAttribute('data-block-code'));
			event.dataTransfer.setData('text/plain', item.getAttribute('data-block-code'));
			event.dataTransfer.effectAllowed = 'copy';
			item.classList.add('dragging');
		});

		palette.addEventListener('dragend', function (event) {
			var item = event.target.closest('.lp-palette-item');
			if (item) item.classList.remove('dragging');
		});

		palette.addEventListener('click', function (event) {
			var item = event.target.closest('.lp-palette-item');
			if (!item) return;

			addBlock(item.getAttribute('data-block-code'), ui.selectedId);
		});

		var search = $('lp-palette-search');

		if (search) {
			search.addEventListener('input', function () {
				var query = search.value.toLowerCase().trim();

				palette.querySelectorAll('.lp-palette-group').forEach(function (group) {
					var visible = 0;

					group.querySelectorAll('.lp-palette-item').forEach(function (item) {
						var match = !query || (item.getAttribute('data-search') || '').indexOf(query) !== -1;
						item.style.display = match ? '' : 'none';
						if (match) visible++;
					});

					group.style.display = visible ? '' : 'none';
				});
			});
		}
	}

	function renderTemplates() {
		var list = $('lp-template-list');
		if (!list) return;

		if (!ui.templates.length) {
			list.innerHTML = '<div class="text-gray-400 text-[11px] px-1 py-1">' + esc(t('text_no_templates', 'No templates yet')) + '</div>';
			return;
		}

		list.innerHTML = ui.templates.map(function (template) {
			return '<div class="flex items-center gap-1">' +
				'<button type="button" class="flex-1 text-left px-2 py-1 rounded hover:bg-gray-100 truncate" data-template-id="' + Number(template.template_id) + '">' +
				esc(template.name) + ' <span class="text-gray-400">(' + (template.blocks || []).length + ')</span></button>' +
				'<button type="button" class="text-gray-300 hover:text-red-500 px-1" data-template-delete="' + Number(template.template_id) + '" title="' + esc(t('text_delete_template', 'Delete')) + '">' +
				'<i data-lucide="trash-2" class="w-3 h-3"></i></button></div>';
		}).join('');

		lucide.createIcons({ nodes: [list] });
	}

	function initTemplates() {
		var list = $('lp-template-list');
		if (!list) return;

		list.addEventListener('click', function (event) {
			var insert = event.target.closest('[data-template-id]');
			var remove = event.target.closest('[data-template-delete]');

			if (insert) {
				insertTemplate(Number(insert.getAttribute('data-template-id')));
			}

			if (remove) {
				deleteTemplate(Number(remove.getAttribute('data-template-delete')));
			}
		});

		$('lp-template-save').addEventListener('click', openTemplateDialog);
		$('lp-template-confirm').addEventListener('click', saveTemplateDialog);
	}

	function insertTemplate(templateId) {
		for (var i = 0; i < ui.templates.length; i++) {
			if (Number(ui.templates[i].template_id) !== Number(templateId)) continue;

			pushHistory();

			ui.templates[i].blocks.forEach(function (source) {
				var block = normalizeBlock(clone(source));
				block.block_id = 0;
				state.blocks.push(block);
			});

			ui.pendingSelectIndex = state.blocks.length - 1;

			touch({ structural: true, delay: 150 });
			toast(t('text_insert_template', 'Template inserted'));
			return;
		}
	}

	function deleteTemplate(templateId) {
		if (!window.confirm(t('text_delete_template', 'Delete template') + '?')) return;

		var body = new URLSearchParams();
		body.set('template_id', templateId);

		fetch(CFG.urls.templateDelete, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.then(function (response) { return response.json(); })
			.then(function (json) {
				if (json.error) {
					toast(json.error, 'error');
					return;
				}

				ui.templates = json.templates || [];
				renderTemplates();
			});
	}

	function openTemplateDialog() {
		var dialog = $('lp-template-dialog');
		if (!dialog) return;

		$('lp-template-name').value = '';
		$('lp-template-all').checked = !ui.selectedId;
		dialog.classList.remove('hidden');
		$('lp-template-name').focus();
	}

	function saveTemplateDialog() {
		var name = $('lp-template-name').value.trim();
		var all = $('lp-template-all').checked;

		if (!name) {
			toast(t('text_template_name', 'Template name'), 'error');
			return;
		}

		var blocks = all
			? state.blocks
			: state.blocks.filter(function (block) {
				return Number(block.block_id) === Number(ui.selectedId);
			});

		if (!blocks.length) {
			blocks = state.blocks;
		}

		var body = {
			name: name,
			blocks: blocks.map(function (block) {
				return { code: block.code, status: 1, settings: block.settings, content: block.content };
			})
		};

		fetch(CFG.urls.template, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(body)
		})
			.then(function (response) { return response.json(); })
			.then(function (json) {
				if (json.error) {
					toast(json.error, 'error');
					return;
				}

				ui.templates = json.templates || [];
				renderTemplates();
				$('lp-template-dialog').classList.add('hidden');
				toast(t('text_saved', 'Saved'));
			});
	}

	/* ==================== properties panel ==================== */

	function renderProperties() {
		var body = $('lp-props-body');
		var actions = $('lp-props-actions');
		var title = $('lp-props-title');
		var close = $('lp-props-close');

		if (!body) return;

		if (typeof tinymce !== 'undefined') {
			tinymce.remove();
		}

		var block = blockById(ui.selectedId);

		if (!block) {
			body.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-center text-gray-400">' +
				'<i data-lucide="mouse-pointer-click" class="w-8 h-8 mb-2 text-gray-300"></i>' +
				'<p class="text-xs">' + esc(t('text_select_block', 'Select a block to edit it')) + '</p></div>';
			actions.classList.add('hidden');

			if (close) close.classList.add('hidden');

			lucide.createIcons({ nodes: [body] });
			return;
		}

		var schema = CFG.schema[block.code] || { fields: [], name: block.code, icon: 'square' };

		title.innerHTML = '<i data-lucide="' + esc(schema.icon || 'square') + '" class="w-3.5 h-3.5"></i> ' + esc(t(schema.name, block.code));
		actions.classList.remove('hidden');

		if (close) close.classList.remove('hidden');

		var html = '<div class="mb-3 flex items-center justify-between">' +
			'<label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" data-block-status' + (Number(block.status) ? ' checked' : '') + ' /> ' + esc(t('entry_status', 'Status')) + '</label>' +
			'<span class="text-[10px] text-gray-400">#' + Number(block.block_id) + '</span></div>';

		html += '<div class="space-y-3">';

		schema.fields.forEach(function (field) {
			if (WRAPPER_KEYS.indexOf(field.key) !== -1) return;

			html += field.type === 'repeater' ? renderRepeater(block, field) : renderField(block, field);
		});

		html += '</div>';

		html += '<details class="mt-4 border-t border-gray-100 pt-3">' +
			'<summary class="cursor-pointer text-[11px] font-bold text-gray-400 uppercase tracking-wide">' + esc(t('text_section_settings', 'Section settings')) + '</summary>' +
			'<div class="space-y-3 mt-3">';

		schema.fields.forEach(function (field) {
			if (WRAPPER_KEYS.indexOf(field.key) === -1) return;

			html += renderField(block, field);
		});

		html += '</div></details>';

		body.innerHTML = html;

		lucide.createIcons({ nodes: [body] });
		bindProperties(body, block);
	}

	function fieldId(block, field) {
		return 'lp-f-' + Number(block.block_id) + '-' + field.key;
	}

	function renderField(block, field) {
		var id = fieldId(block, field);
		var value = getValue(field, block);
		var label = esc(t(field.label, field.key));
		var langBadge = field.localized ? ' <span class="text-[9px] text-gray-400">' + esc(String(langCodeById(ui.lang) || '').toUpperCase()) + '</span>' : '';
		var html = '<div class="lp-field" data-field-key="' + esc(field.key) + '" data-field-type="' + esc(field.type) + '">' +
			'<label class="lp-field-label" for="' + id + '">' + label + langBadge + '</label>';

		switch (field.type) {
			case 'textarea':
			case 'code':
				html += '<textarea id="' + id + '" rows="' + (field.type === 'code' ? 8 : 4) + '" class="lp-input' + (field.type === 'code' ? ' font-mono text-[11px]' : '') + '" data-input>' + esc(value) + '</textarea>';
				break;

			case 'richtext':
				html += '<textarea id="' + id + '" rows="6" class="lp-input" data-input data-richtext="1">' + esc(value) + '</textarea>';
				break;

			case 'number':
				html += '<input type="number" id="' + id + '" class="lp-input" data-input value="' + esc(value) + '"' +
					(field.min !== undefined ? ' min="' + Number(field.min) + '"' : '') +
					(field.max !== undefined ? ' max="' + Number(field.max) + '"' : '') + ' />';
				break;

			case 'toggle':
				html += '<label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" id="' + id + '" data-input' + (Number(value) ? ' checked' : '') + ' /> ' + label + '</label>';
				break;

			case 'select':
				html += '<select id="' + id + '" class="lp-input" data-input>';

				Object.keys(field.options || {}).forEach(function (option) {
					html += '<option value="' + esc(option) + '"' + (String(value) === String(option) ? ' selected' : '') + '>' +
						esc(t(field.options[option], option)) + '</option>';
				});

				html += '</select>';
				break;

			case 'color':
				html += '<div class="flex items-center gap-2">' +
					'<input type="color" id="' + id + '" data-input value="' + esc(value || '#ffffff') + '" />' +
					'<input type="text" class="lp-input" data-input-text value="' + esc(value) + '" placeholder="#ffffff" />' +
					'</div>';
				break;

			case 'image':
				html += '<div class="flex items-start gap-2">' +
					'<div class="w-16 h-16 rounded border border-gray-200 bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">' +
					(value ? '<img src="' + esc(CFG.imageUrl + value) + '" alt="" class="w-full h-full object-cover" data-image-preview />' : '<i data-lucide="image" class="w-5 h-5 text-gray-300"></i>') +
					'</div>' +
					'<div class="flex-1 min-w-0">' +
					'<input type="text" id="' + id + '" class="lp-input mb-1" data-input value="' + esc(value) + '" placeholder="catalog/…/image.jpg" />' +
					'<div class="flex gap-1">' +
					'<button type="button" class="px-2 py-1 text-[11px] bg-gray-100 hover:bg-gray-200 rounded" data-media-pick>' + esc(t('text_choose_image', 'Choose')) + '</button>' +
					'<button type="button" class="px-2 py-1 text-[11px] text-gray-500 hover:text-red-600 rounded" data-clear>' + esc(t('text_clear', 'Clear')) + '</button>' +
					'</div></div></div>';
				break;

			case 'products':
				html += renderMultiPicker(block, field, 'product');
				break;

			case 'categories':
				html += renderMultiPicker(block, field, 'category');
				break;

			case 'category':
				html += renderSinglePicker(block, field);
				break;

			case 'banner':
				html += renderBannerPicker(value);
				break;

			case 'link':
			case 'text':
			default:
				html += '<input type="text" id="' + id + '" class="lp-input" data-input value="' + esc(value) + '" />';
				break;
		}

		html += '</div>';

		return html;
	}

	function renderMultiPicker(block, field, type) {
		var values = getValue(field, block);
		values = Array.isArray(values) ? values : [];

		var html = '<div class="flex flex-wrap gap-1 mb-1" data-chips>';

		values.forEach(function (id) {
			var name = ui.names[type + ':' + id] || ('#' + id);
			html += '<span class="lp-chip" data-id="' + Number(id) + '">' +
				'<span class="truncate max-w-[130px]">' + esc(name) + '</span>' +
				'<button type="button" data-chip-up title="↑">↑</button>' +
				'<button type="button" data-chip-remove title="×">×</button></span>';
		});

		html += '</div><input type="text" class="lp-input" data-autocomplete="' + type + '" placeholder="' +
			esc(t(type === 'product' ? 'text_pick_products' : 'text_pick_category', 'Search…')) + '" autocomplete="off" />';

		return html;
	}

	function renderSinglePicker(block, field) {
		var value = Number(getValue(field, block)) || 0;
		var name = value ? (ui.names['category:' + value] || ('#' + value)) : '';

		return '<div class="flex items-center gap-1 mb-1" data-single>' +
			'<span class="lp-chip' + (value ? '' : ' hidden') + '" data-id="' + value + '">' +
			'<span class="truncate max-w-[150px]" data-single-label>' + esc(name) + '</span>' +
			'<button type="button" data-chip-remove title="×">×</button></span></div>' +
			'<input type="text" class="lp-input" data-autocomplete="category" placeholder="' +
			esc(t('text_pick_category', 'Search…')) + '" autocomplete="off" />';
	}

	function renderBannerPicker(value) {
		var html = '<select class="lp-input" data-input><option value="0">—</option>';

		(CFG.banners || []).forEach(function (banner) {
			html += '<option value="' + Number(banner.banner_id) + '"' + (Number(value) === Number(banner.banner_id) ? ' selected' : '') + '>' + esc(banner.name) + '</option>';
		});

		html += '</select>';

		if (!(CFG.banners || []).length) {
			html += '<div class="text-[11px] text-amber-600 mt-1">' + esc(t('text_no_banners', 'No banners yet — create one in Design → Banners.')) + '</div>';
		}

		return html;
	}

	function renderRepeater(block, field) {
		var rows = getValue(field, block);
		rows = Array.isArray(rows) ? rows : [];

		var html = '<div class="lp-field" data-field-key="' + esc(field.key) + '" data-field-type="repeater">' +
			'<label class="lp-field-label">' + esc(t(field.label, field.key)) + '</label>' +
			'<div class="space-y-2" data-repeater-rows>';

		rows.forEach(function (row, index) {
			html += '<div class="border border-gray-200 rounded p-2 space-y-1" data-repeater-row="' + index + '">';

			(field.fields || []).forEach(function (sub) {
				var value = row[sub.key] !== undefined ? row[sub.key] : sub.default;

				if (sub.type === 'select') {
					html += '<select class="lp-input" data-repeater-key="' + esc(sub.key) + '">';

					Object.keys(sub.options || {}).forEach(function (option) {
						html += '<option value="' + esc(option) + '"' + (String(value) === String(option) ? ' selected' : '') + '>' + esc(t(sub.options[option], option)) + '</option>';
					});

					html += '</select>';
				} else {
					html += '<input type="text" class="lp-input" data-repeater-key="' + esc(sub.key) + '" value="' + esc(value) + '" placeholder="' + esc(t(sub.label, sub.key)) + '" />';
				}
			});

			html += '<button type="button" class="text-[11px] text-red-500 hover:text-red-700" data-repeater-remove>' + esc(t('text_remove', 'Remove')) + '</button></div>';
		});

		html += '</div><button type="button" class="mt-1 text-[11px] text-blue-600 hover:text-blue-800" data-repeater-add>+ ' + esc(t('text_add_row', 'Add')) + '</button></div>';

		return html;
	}

	function bindProperties(container, block) {
		var statusInput = container.querySelector('[data-block-status]');

		if (statusInput) {
			statusInput.addEventListener('change', function () {
				pushHistory();
				block.status = statusInput.checked ? 1 : 0;
				touch({ structural: true });
			});
		}

		container.querySelectorAll('.lp-field').forEach(function (fieldEl) {
			var field = fieldDefinition(block.code, fieldEl.getAttribute('data-field-key'));

			if (!field) return;

			if (field.type === 'repeater') {
				bindRepeater(fieldEl, block, field);
				return;
			}

			fieldEl.querySelectorAll('[data-input]').forEach(function (input) {
				var read = function () {
					if (field.type === 'toggle') {
						return input.checked ? 1 : 0;
					}

					if (field.type === 'number') {
						return parseInt(input.value, 10) || 0;
					}

					if (field.type === 'category' || field.type === 'banner') {
						return parseInt(input.value, 10) || 0;
					}

					return input.value;
				};

				var apply = function () {
					var value = read();

					if (getValue(field, block) === value) return;

					pushHistory();
					setValue(block, field, value);

					if (field.type === 'color') {
						var text = fieldEl.querySelector('[data-input-text]');

						if (text) text.value = value;
					}

					touch({ blockId: block.block_id });
				};

				var eventName = ['text', 'textarea', 'code', 'link'].indexOf(field.type) !== -1 ? 'input' : 'change';

				input.addEventListener(eventName, apply);

				if (field.type === 'richtext') {
					initRichText(input, function (value) {
						var fieldBlock = block;

						if (getValue(field, fieldBlock) === value) return;

						setValue(fieldBlock, field, value);
						touch({ blockId: fieldBlock.block_id });
					});
				}
			});

			var colorText = fieldEl.querySelector('[data-input-text]');
			var colorPicker = fieldEl.querySelector('input[type="color"]');

			if (colorText && colorPicker) {
				colorText.addEventListener('change', function () {
					var value = colorText.value.trim();

					if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value)) {
						return;
					}

					pushHistory();
					colorPicker.value = value;
					setValue(block, field, value);
					touch({ blockId: block.block_id });
				});

				colorPicker.addEventListener('input', function () {
					colorText.value = colorPicker.value;
				});
			}

			var pick = fieldEl.querySelector('[data-media-pick]');
			var clear = fieldEl.querySelector('[data-clear]');

			if (pick) {
				pick.addEventListener('click', function () {
					openMediaPicker(function (path) {
						pushHistory();
						setValue(block, field, path);

						var input = fieldEl.querySelector('[data-input]');
						if (input) input.value = path;

						var preview = fieldEl.querySelector('[data-image-preview]');

						if (preview) {
							preview.src = CFG.imageUrl + path;
						} else {
							var box = fieldEl.querySelector('.w-16');

							if (box) {
								box.innerHTML = '<img src="' + esc(CFG.imageUrl + path) + '" alt="" class="w-full h-full object-cover" data-image-preview />';
							}
						}

						touch({ blockId: block.block_id });
					});
				});
			}

			if (clear) {
				clear.addEventListener('click', function () {
					pushHistory();
					setValue(block, field, '');

					var input = fieldEl.querySelector('[data-input]');
					if (input) input.value = '';

					var preview = fieldEl.querySelector('[data-image-preview]');
					if (preview) preview.remove();

					touch({ blockId: block.block_id });
				});
			}

			bindPickers(fieldEl, block, field);
		});
	}

	function bindPickers(fieldEl, block, field) {
		var chips = fieldEl.querySelector('[data-chips]');
		var single = fieldEl.querySelector('[data-single]');
		var search = fieldEl.querySelector('[data-autocomplete]');
		var type = search ? search.getAttribute('data-autocomplete') : 'product';

		if (chips) {
			chips.addEventListener('click', function (event) {
				var chip = event.target.closest('.lp-chip');

				if (!chip) return;

				var values = (getValue(field, block) || []).slice();
				var id = Number(chip.getAttribute('data-id'));

				if (event.target.closest('[data-chip-remove]')) {
					pushHistory();
					values = values.filter(function (value) { return Number(value) !== id; });
					setValue(block, field, values);
					chip.remove();
					touch({ blockId: block.block_id });
				}

				if (event.target.closest('[data-chip-up]')) {
					var index = values.indexOf(id);

					if (index <= 0) return;

					pushHistory();
					values.splice(index, 1);
					values.splice(index - 1, 0, id);
					setValue(block, field, values);
					renderProperties();
					touch({ blockId: block.block_id });
				}
			});
		}

		if (single) {
			single.addEventListener('click', function (event) {
				if (!event.target.closest('[data-chip-remove]')) return;

				pushHistory();
				setValue(block, field, 0);

				var chip = single.querySelector('.lp-chip');
				if (chip) chip.classList.add('hidden');

				touch({ blockId: block.block_id });
			});
		}

		if (!search) return;

		attachAutocomplete(search, type, function (item) {
			var id = Number(item.id);
			ui.names[type + ':' + id] = item.name;

			if (chips) {
				var values = (getValue(field, block) || []).slice();

				if (values.map(Number).indexOf(id) === -1) {
					pushHistory();
					values.push(id);
					setValue(block, field, values);
					touch({ blockId: block.block_id });
					renderProperties();
				}
			} else if (single) {
				pushHistory();
				setValue(block, field, id);

				var chip = single.querySelector('.lp-chip');
				chip.classList.remove('hidden');
				chip.setAttribute('data-id', id);
				chip.querySelector('[data-single-label]').textContent = item.name;

				touch({ blockId: block.block_id });
			}
		});
	}

	function bindRepeater(fieldEl, block, field) {
		var rowsEl = fieldEl.querySelector('[data-repeater-rows]');
		var timer = null;

		function sync() {
			var rows = [];

			rowsEl.querySelectorAll('[data-repeater-row]').forEach(function (rowEl) {
				var row = {};

				rowEl.querySelectorAll('[data-repeater-key]').forEach(function (input) {
					row[input.getAttribute('data-repeater-key')] = input.value;
				});

				rows.push(row);
			});

			setValue(block, field, rows);
			clearTimeout(timer);
			timer = setTimeout(function () {
				touch({ blockId: block.block_id });
			}, 700);
		}

		rowsEl.addEventListener('input', sync);
		rowsEl.addEventListener('change', sync);

		fieldEl.querySelector('[data-repeater-add]').addEventListener('click', function () {
			pushHistory();

			var rows = (getValue(field, block) || []).slice();
			var max = field.max_rows || 6;

			if (rows.length >= max) return;

			var row = {};

			(field.fields || []).forEach(function (sub) {
				row[sub.key] = sub.default;
			});

			rows.push(row);
			setValue(block, field, rows);
			renderProperties();
			touch({ blockId: block.block_id });
		});

		rowsEl.addEventListener('click', function (event) {
			if (!event.target.closest('[data-repeater-remove]')) return;

			pushHistory();

			var rowEl = event.target.closest('[data-repeater-row]');
			var index = Number(rowEl.getAttribute('data-repeater-row'));
			var rows = (getValue(field, block) || []).slice();

			rows.splice(index, 1);
			setValue(block, field, rows);
			renderProperties();
			touch({ blockId: block.block_id });
		});
	}

	/* ==================== page settings ==================== */

	function openSettings() {
		renderSettings();
		$('lp-settings').classList.remove('hidden');
	}

	function renderSettings() {
		var body = $('lp-settings-body');
		if (!body) return;

		var descriptions = state.page.descriptions || {};
		var html = '';

		html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_name', 'Name')) + '</label>' +
			'<input type="text" class="lp-input" data-page="name" value="' + esc(state.page.name) + '" /></div>';

		(CFG.languages || []).forEach(function (language) {
			var id = Number(language.language_id);
			var description = descriptions[id] || {};

			html += '<div class="border border-gray-100 rounded p-2 space-y-2">' +
				'<div class="text-[11px] font-semibold text-gray-500 flex items-center gap-1">' +
				'<i data-lucide="languages" class="w-3 h-3"></i> ' + esc(language.name) +
				' <span class="text-[9px] text-gray-400">' + esc(String(language.code).toUpperCase()) + '</span></div>' +
				'<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_slug', 'SEO keyword')) + '</label>' +
				'<div class="flex items-center gap-1"><span class="text-gray-400 text-xs">/</span>' +
				'<input type="text" class="lp-input" data-desc="' + id + '" data-key="slug" value="' + esc(description.slug || '') + '" /></div></div>' +
				'<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_meta_title', 'Meta title')) + '</label>' +
				'<input type="text" class="lp-input" data-desc="' + id + '" data-key="meta_title" value="' + esc(description.meta_title || '') + '" /></div>' +
				'<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_meta_description', 'Meta description')) + '</label>' +
				'<textarea rows="2" class="lp-input" data-desc="' + id + '" data-key="meta_description">' + esc(description.meta_description || '') + '</textarea></div>' +
				'<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_meta_keyword', 'Meta keywords')) + '</label>' +
				'<input type="text" class="lp-input" data-desc="' + id + '" data-key="meta_keyword" value="' + esc(description.meta_keyword || '') + '" /></div>' +
				'</div>';
		});

		html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_status', 'Status')) + '</label>' +
			'<select class="lp-input" data-page="status">' +
			'<option value="1"' + (Number(state.page.status) ? ' selected' : '') + '>' + esc(t('text_status_published', 'Published')) + '</option>' +
			'<option value="0"' + (!Number(state.page.status) ? ' selected' : '') + '>' + esc(t('text_status_draft', 'Draft')) + '</option></select></div>';

		html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_chrome', 'Header / footer')) + '</label>' +
			'<select class="lp-input" data-page="chrome">' +
			'<option value="full"' + (state.page.chrome !== 'bare' ? ' selected' : '') + '>' + esc(t('entry_chrome_full', 'Standard theme header & footer')) + '</option>' +
			'<option value="bare"' + (state.page.chrome === 'bare' ? ' selected' : '') + '>' + esc(t('entry_chrome_bare', 'No navigation (bare page)')) + '</option></select></div>';

		html += '<div class="lp-field"><label class="flex items-center gap-2 text-xs"><input type="checkbox" data-page="is_home"' + (Number(state.page.is_home) ? ' checked' : '') + ' /> ' + esc(t('entry_is_home', 'Use as store home page')) + '</label></div>';
		html += '<div class="lp-field"><label class="flex items-center gap-2 text-xs"><input type="checkbox" data-page="noindex"' + (Number(state.page.noindex) ? ' checked' : '') + ' /> ' + esc(t('entry_noindex', 'noindex (hide from search engines)')) + '</label></div>';

		html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_date_publish', 'Publish from')) + '</label>' +
			'<input type="datetime-local" class="lp-input" data-page="date_publish" value="' + esc(state.page.date_publish || '') + '" /></div>';
		html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_date_unpublish', 'Publish until')) + '</label>' +
			'<input type="datetime-local" class="lp-input" data-page="date_unpublish" value="' + esc(state.page.date_unpublish || '') + '" /></div>';

		html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_custom_css', 'Custom CSS')) + '</label>' +
			'<textarea rows="5" class="lp-input font-mono text-[11px]" data-page="custom_css">' + esc(state.page.custom_css || '') + '</textarea></div>';

		html += '<div class="text-[11px] text-gray-400">' + esc(t('entry_viewed', 'Views')) + ': ' + Number(CFG.landing.viewed || 0) + '</div>';

		body.innerHTML = html;

		lucide.createIcons({ nodes: [body] });

		body.querySelectorAll('[data-page]').forEach(function (input) {
			var key = input.getAttribute('data-page');
			var refresh = ['status', 'chrome', 'is_home', 'noindex', 'custom_css'].indexOf(key) !== -1;

			var handler = function () {
				var value = input.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value;

				if (String(state.page[key]) === String(value)) return;

				pushHistory();
				state.page[key] = value;

				if (key === 'name') {
					$('lp-name-input').value = value;
				}

				if (key === 'status') {
					updateStatusBadge();
				}

				touch({ structural: refresh, delay: 800 });
			};

			input.addEventListener((input.type === 'checkbox' || input.tagName === 'SELECT') ? 'change' : 'input', handler);
		});

		body.querySelectorAll('[data-desc]').forEach(function (input) {
			var languageId = Number(input.getAttribute('data-desc'));
			var key = input.getAttribute('data-key');

			input.addEventListener('input', function () {
				if (!state.page.descriptions[languageId]) {
					state.page.descriptions[languageId] = {};
				}

				if (String(state.page.descriptions[languageId][key] || '') === String(input.value)) return;

				pushHistory();
				state.page.descriptions[languageId][key] = input.value;
				touch({ delay: 1000 });
			});
		});
	}

	/* ==================== autocomplete ==================== */

	function attachAutocomplete(input, type, onSelect) {
		var list = null;
		var timer = null;

		function close() {
			if (list) {
				list.remove();
				list = null;
			}
		}

		input.addEventListener('input', function () {
			var term = input.value.trim();

			clearTimeout(timer);

			if (!term) {
				close();
				return;
			}

			timer = setTimeout(function () {
				fetch(CFG.urls.autocomplete + '&type=' + encodeURIComponent(type) + '&filter_name=' + encodeURIComponent(term))
					.then(function (response) { return response.json(); })
					.then(function (items) {
						close();

						if (!items || !items.length) return;

						list = document.createElement('div');
						list.className = 'absolute z-30 mt-1 w-56 max-h-56 overflow-y-auto bg-white border border-gray-200 rounded shadow-lg text-xs';

						items.forEach(function (item) {
							var button = document.createElement('button');
							button.type = 'button';
							button.className = 'block w-full text-left px-2 py-1.5 hover:bg-blue-50 truncate';
							button.textContent = item.name + (item.model ? ' (' + item.model + ')' : '');
							button.addEventListener('click', function () {
								onSelect(item);
								close();
								input.value = '';
							});
							list.appendChild(button);
						});

						input.parentNode.style.position = 'relative';
						input.parentNode.appendChild(list);
					})
					.catch(close);
			}, 220);
		});

		input.addEventListener('blur', function () {
			setTimeout(close, 200);
		});
	}

	/* ==================== rich text ==================== */

	function initRichText(textarea, onChange) {
		if (typeof tinymce === 'undefined' || !textarea.id) return;

		var timer = null;

		tinymce.init({
			target: textarea,
			license_key: 'gpl',
			height: 240,
			branding: false,
			promotion: false,
			menubar: false,
			plugins: 'advlist autolink lists link image media table code searchreplace visualblocks',
			toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image media table | code',
			sandbox_iframes: false,
			convert_urls: false,
			relative_urls: false,
			remove_script_host: false,
			file_picker_types: 'image media',
			file_picker_callback: function (callback) {
				openMediaPicker(function (path) {
					callback(CFG.imageUrl + path, { alt: '' });
				});
			},
			setup: function (editor) {
				editor.on('change keyup undo redo setcontent', function () {
					clearTimeout(timer);
					timer = setTimeout(function () {
						onChange(editor.getContent());
					}, 500);
				});
			}
		});
	}

	/* ==================== media picker (reuses common/filemanager) ==================== */

	function openMediaPicker(onSelect) {
		if (typeof jQuery === 'undefined') {
			var path = window.prompt(t('text_choose_image', 'Image path'), 'catalog/');

			if (path) onSelect(path);

			return;
		}

		var $ = jQuery;

		if (!$('#lp-media-field').length) {
			$('body').append('<input type="hidden" id="lp-media-field" value="" /><span id="lp-media-thumb" style="display:none"></span>');
		}

		$('#lp-media-field').val('');
		$('#modal-image').remove();
		$('.modal-backdrop').remove();

		$.ajax({
			url: CFG.urls.filemanager + '&target=lp-media-field&thumb=lp-media-thumb',
			dataType: 'html',
			success: function (html) {
				$('body').append('<div id="modal-image" class="modal" role="dialog">' + html + '</div>');

				$('#modal-image').one('hidden.bs.modal', function () {
					var value = $('#lp-media-field').val();

					$('#modal-image').remove();
					$('.modal-backdrop').remove();

					if (value) {
						onSelect(value);
					}
				});

				$('#modal-image').modal('show');

				if (typeof lucide !== 'undefined') {
					lucide.createIcons();
				}
			}
		});
	}

	/* ==================== language / viewport / token ==================== */

	function initLanguageTabs() {
		var tabs = $('lp-lang-tabs');
		if (!tabs) return;

		tabs.addEventListener('click', function (event) {
			var tab = event.target.closest('.lp-lang-tab');
			if (!tab) return;

			var languageId = Number(tab.getAttribute('data-lang-id'));

			if (languageId === ui.lang) return;

			ui.lang = languageId;

			tabs.querySelectorAll('.lp-lang-tab').forEach(function (button) {
				button.className = 'lp-lang-tab px-2 py-1 text-xs rounded text-gray-500 hover:text-gray-800 hover:bg-gray-100';
			});

			tab.className = 'lp-lang-tab px-2 py-1 text-xs rounded bg-blue-50 text-blue-700 font-medium';

			renderProperties();
			renderSettings();

			reloadIframe();
		});
	}

	function initViewport() {
		var switcher = $('lp-viewport');
		if (!switcher) return;

		switcher.addEventListener('click', function (event) {
			var button = event.target.closest('button[data-viewport]');
			if (!button) return;

			ui.viewport = button.getAttribute('data-viewport');

			switcher.querySelectorAll('button[data-viewport]').forEach(function (item) {
				item.className = 'px-2 py-1 rounded-md text-xs text-gray-500 hover:text-gray-700';
			});

			button.className = 'px-2 py-1 rounded-md text-xs bg-white shadow-sm text-gray-700';

			var wrap = $('lp-canvas-wrap');

			wrap.style.maxWidth = ui.viewport === 'tablet' ? '820px' : (ui.viewport === 'mobile' ? '420px' : '');
		});
	}

	function updatePreviewLink() {
		var link = $('lp-preview-link');

		if (link) link.href = ui.previewBase;
	}

	function refreshToken(callback) {
		if (Date.now() - ui.tokenAt < 20 * 60 * 1000) {
			callback();
			return;
		}

		var replace = function (url, token) {
			return url.replace(/preview_token=[^&]*/, 'preview_token=' + encodeURIComponent(token));
		};

		fetch(CFG.urls.token)
			.then(function (response) { return response.json(); })
			.then(function (json) {
				if (json.token) {
					Object.keys(CFG.previewBases || {}).forEach(function (languageId) {
						CFG.previewBases[languageId] = replace(CFG.previewBases[languageId], json.token);
					});

					ui.previewBase = replace(ui.previewBase, json.token);
					ui.tokenAt = Date.now();
					updatePreviewLink();
				}

				callback();
			})
			.catch(callback);
	}

	/* ==================== init ==================== */

	function init() {
		state.page = {
			name: CFG.landing.name || '',
			status: Number(CFG.landing.status) || 0,
			chrome: CFG.landing.chrome || 'full',
			is_home: Number(CFG.landing.is_home) || 0,
			noindex: Number(CFG.landing.noindex) || 0,
			custom_css: CFG.landing.custom_css || '',
			date_publish: CFG.landing.date_publish || '',
			date_unpublish: CFG.landing.date_unpublish || '',
			descriptions: CFG.landing.descriptions || {}
		};

		state.version = Number(CFG.landing.version) || 1;
		state.blocks = (CFG.blocks || []).map(normalizeBlock);

		initPalette();
		initLanguageTabs();
		initViewport();
		initTemplates();
		renderTemplates();
		renderProperties();
		renderSettings();
		updateStatusBadge();
		updateSaveState();
		updatePreviewLink();

		var frame = $('lp-canvas-frame');

		if (frame) {
			frame.src = buildPreviewUrl('_=' + Date.now());
		}

		var nameInput = $('lp-name-input');

		if (nameInput) {
			nameInput.addEventListener('input', function () {
				if (String(state.page.name) === nameInput.value) return;

				pushHistory();
				state.page.name = nameInput.value;
				touch({ delay: 900 });
			});
		}

		$('lp-save-btn').addEventListener('click', function () { save(true); });
		$('lp-undo').addEventListener('click', undo);
		$('lp-redo').addEventListener('click', redo);
		$('lp-settings-btn').addEventListener('click', openSettings);
		$('lp-props-close').addEventListener('click', function () { selectBlock(0); });

		document.querySelectorAll('[data-close]').forEach(function (element) {
			element.addEventListener('click', function () {
				var target = $('lp-' + element.getAttribute('data-close'));

				if (target) target.classList.add('hidden');
			});
		});

		$('lp-props-actions').addEventListener('click', function (event) {
			var button = event.target.closest('[data-action]');

			if (!button || !ui.selectedId) return;

			performAction(button.getAttribute('data-action'), ui.selectedId);
		});

		window.addEventListener('message', onIframeMessage);

		document.addEventListener('keydown', function (event) {
			var target = event.target;

			if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
				return;
			}

			var meta = event.ctrlKey || event.metaKey;

			if (meta && event.key.toLowerCase() === 'z') {
				event.preventDefault();
				event.shiftKey ? redo() : undo();
			}

			if (meta && event.key.toLowerCase() === 's') {
				event.preventDefault();
				save(true);
			}
		});

		window.addEventListener('beforeunload', function (event) {
			if (!ui.dirty && !ui.saving) return;

			event.preventDefault();
			event.returnValue = '';
		});

		window.addEventListener('focus', function () {
			if (ui.dirty && !ui.saving) {
				scheduleSave(200);
			}
		});

		window.addEventListener('online', function () {
			if (ui.dirty) save(false);
		});

		lucide.createIcons();
	}

	document.addEventListener('DOMContentLoaded', init);
})();
