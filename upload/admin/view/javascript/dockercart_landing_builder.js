/**
 * DockerCart landing page builder (admin shell).
 *
 * The canvas is the real storefront rendered in an iframe
 * (index.php?route=landing/page&…&lp_builder=1). This script owns the page
 * model, the palette, the properties panel, the structure navigator and
 * autosave; the iframe script (catalog/view/javascript/dockercart_landing_
 * builder.js) owns dragging and selection inside the page and talks to this
 * script by postMessage.
 *
 * Page model: a two-level tree. Top-level blocks render as full-width
 * sections; a `section` container block owns columns (structure presets) and
 * its children sit in one of them (parent_id + slot). `sort` is the position
 * inside the own (parent_id, slot) group, renumbered before every save.
 * New blocks carry negative temp ids so children can reference them before
 * the server assigns real ids; the save response carries client_id -> real
 * block_id for every row.
 *
 * Language safety: every field write goes to the language that was selected
 * when the input was bound (captured at bind time), never to whatever tab is
 * current when a debounced timer fires. Pending editor/repeater timers are
 * flushed (not dropped) before a panel re-renders, and a removed TinyMCE
 * editor never writes anything - this is what used to wipe neighbouring
 * languages on a fast tab switch.
 *
 * To the iframe:
 *   lp:select        {blockId, scroll}     highlight + scroll to a block
 *   lp:refreshBlock  {blockId, url}        re-fetch one rendered block
 *   lp:replace       {blockId, html}       swap one node for unsaved preview html
 *   lp:labels        {labels}              code -> block name for the toolbar
 *   lp:viewport      {viewport, label}     active device + localized hidden hint
 * From the iframe:
 *   lp:ready    {items}                    iframe is interactive
 *   lp:selected {blockId}
 *   lp:add      {code, parentId, slot, afterId}
 *   lp:moveTree {items: [{id, parent, slot, order}]}
 *   lp:action   {action, blockId}          duplicate|remove|up|down|settings
 */
(function () {
	'use strict';

	var CFG = window.DC_LANDING || {};
	var I18N = CFG.i18n || {};

	var WRAPPER_KEYS = [
		'bg_color', 'bg_image', 'overlay', 'padding', 'container',
		'align', 'hide_desktop', 'hide_tablet', 'hide_mobile', 'anchor', 'custom_class'
	];

	// Elementor-style property tabs: every block exposes the same three tabs.
	// Content = the block's own fields, Style = backgrounds/spacing/width,
	// Advanced = device visibility, anchors and custom classes.
	var STYLE_KEYS = [
		'bg_color', 'bg_image', 'bg_image_mobile', 'overlay', 'padding', 'container', 'align'
	];

	var ADVANCED_KEYS = [
		'hide_desktop', 'hide_tablet', 'hide_mobile', 'anchor', 'custom_class'
	];

	// Link fields mirror the banner form: a type picker plus an entity
	// autocomplete. The stored value stays a plain URL string, so the
	// storefront templates and Blocks::sanitizePath() need no changes.
	var LINK_TYPES = [
		{ type: 'custom', label: 'text_link_custom' },
		{ type: 'product', label: 'text_link_product' },
		{ type: 'category', label: 'text_link_category' },
		{ type: 'manufacturer', label: 'text_link_manufacturer' },
		{ type: 'information', label: 'text_link_information' },
		{ type: 'blog', label: 'text_link_blog' },
		{ type: 'contact', label: 'text_link_contact' }
	];

	var LINK_ROUTES = {
		product:      { route: 'product/product', param: 'product_id' },
		category:     { route: 'product/category', param: 'path' },
		manufacturer: { route: 'product/manufacturer/info', param: 'manufacturer_id' },
		information:  { route: 'information/information', param: 'information_id' },
		blog:         { route: 'blog/post', param: 'blog_post_id' },
		// A storefront route without an entity id: no autocomplete, fixed URL.
		contact:      { route: 'information/contact', param: '' }
	};

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
		version: 1,
		// Owned landing: the owner entity's fields edited from Page Settings.
		owner: { status: 0, descriptions: {} },
		// Global landing design, edited in the builder's Site Settings panel.
		siteDesign: clone(CFG.siteDesign || {})
	};

	var ui = {
		lang: CFG.initialLanguageId || CFG.defaultLanguageId || 1,
		selectedId: 0,
		siteSettings: false,
		propsTab: 'content',
		viewport: 'desktop',
		iframeLang: null,
		iframeReady: false,
		history: [],
		future: [],
		dirty: false,
		saving: false,
		saveQueued: false,
		saveDone: [],
		pendingOwnerTitle: null,
		previewTimer: null,
		previewSeq: 0,
		previewState: null,
		needReload: false,
		dirtyBlocks: {},
		pendingSelectBlock: null,
		names: {},
		linkUrls: {},
		previewBase: CFG.previewBase || '',
		tokenAt: Date.now(),
		templates: CFG.templates || [],
		fieldTimers: [],
		navSortables: [],
		navigatorOpen: false,
		tempId: 0
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

	function languageById(languageId) {
		for (var i = 0; i < (CFG.languages || []).length; i++) {
			if (Number(CFG.languages[i].language_id) === Number(languageId)) {
				return CFG.languages[i];
			}
		}

		return { name: '', code: '' };
	}

	/**
	 * Deep-clones a block list with fresh temp ids: children keep pointing at
	 * their section through the id map, containers are forced to the top
	 * level. One helper for duplicate action and template insertion.
	 */
	function cloneBlocks(source, transform) {
		var idMap = {};
		var copies = [];

		(source || []).forEach(function (original) {
			var copy = normalizeBlock(clone(original));

			copy.block_id = nextTempId();
			idMap[Number(original.block_id)] = copy.block_id;

			if (isContainerBlock(copy)) {
				copy.parent_id = 0;
			} else if (Number(copy.parent_id)) {
				copy.parent_id = idMap[Number(copy.parent_id)] || 0;
			}

			if (typeof transform === 'function') {
				transform(copy, original);
			}

			copies.push(copy);
		});

		return copies;
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

	function nextTempId() {
		ui.tempId -= 1;

		return ui.tempId;
	}

	function normalizeBlock(block) {
		block.block_id = Number(block.block_id || 0);
		block.status = block.status === undefined ? 1 : Number(block.status);
		block.parent_id = Number(block.parent_id || 0);
		block.slot = Number(block.slot || 0);
		block.sort = Number(block.sort || 0);
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

	function isContainerBlock(block) {
		var schema = block ? CFG.schema[block.code] : null;

		return Boolean(schema && schema.container);
	}

	function columnCountOf(block) {
		var structure = String((block && block.settings && block.settings.structure) || '2');

		return Number((CFG.structures || {})[structure] || 2);
	}

	/**
	 * Top-level blocks in visual order.
	 */
	function topLevel() {
		return state.blocks
			.filter(function (block) { return !Number(block.parent_id); })
			.sort(function (a, b) { return Number(a.sort) - Number(b.sort); });
	}

	/**
	 * The widgets of one section column, in visual order.
	 */
	function childrenOf(parentId, slot) {
		return state.blocks
			.filter(function (block) {
				return Number(block.parent_id) === Number(parentId) && Number(block.slot) === Number(slot);
			})
			.sort(function (a, b) { return Number(a.sort) - Number(b.sort); });
	}

	/**
	 * The sibling group a block belongs to (its column, or the page level).
	 */
	function siblingsOf(block) {
		return Number(block.parent_id) ? childrenOf(block.parent_id, block.slot) : topLevel();
	}

	/**
	 * Normalises the tree before it is used: orphans (parent gone) are
	 * promoted to the page level, slots are clamped to the parent's current
	 * structure (a 4-column section changed to 1 folds its extra columns into
	 * the last one) and every `sort` is recomputed dense inside its group.
	 */
	function renumber() {
		state.blocks.forEach(function (block) {
			if (!Number(block.parent_id)) return;

			var parent = blockById(block.parent_id);

			if (!parent) {
				block.parent_id = 0;
				block.slot = 0;

				return;
			}

			if (Number(block.slot) >= columnCountOf(parent)) {
				block.slot = columnCountOf(parent) - 1;
			}
		});

		topLevel().forEach(function (block, index) {
			block.sort = index;
		});

		state.blocks.forEach(function (block) {
			if (!Number(block.parent_id)) return;

			var index = childrenOf(block.parent_id, block.slot).indexOf(block);

			block.sort = index < 0 ? 0 : index;
		});
	}

	/* ==================== field values (language-safe) ==================== */

	/**
	 * Reads a field for one language. `lang` defaults to the current tab but
	 * every bound input passes its own captured language, so a debounced
	 * write can never land in the tab that happens to be selected when the
	 * timer fires.
	 */
	function getValue(field, block, lang) {
		var languageId = lang === undefined ? ui.lang : lang;

		if (field.localized) {
			var content = block.content[languageId] || {};

			return content[field.key] !== undefined ? content[field.key] : field.default;
		}

		return block.settings[field.key] !== undefined ? block.settings[field.key] : field.default;
	}

	function setValue(block, field, value, lang) {
		var languageId = lang === undefined ? ui.lang : lang;

		if (field.localized) {
			if (!block.content[languageId]) {
				block.content[languageId] = {};
			}

			block.content[languageId][field.key] = value;
		} else {
			block.settings[field.key] = value;
		}
	}

	/**
	 * Registers a debounced field write so a panel re-render can flush it
	 * first (its captured language is still the right one) instead of
	 * silently dropping the edit or letting it fire against a removed editor.
	 */
	function trackFieldTimer(timer, flush) {
		var entry = { timer: timer, flush: flush };

		ui.fieldTimers.push(entry);

		return entry;
	}

	function flushFieldTimers() {
		ui.fieldTimers.forEach(function (entry) {
			clearTimeout(entry.timer);

			try {
				entry.flush();
			} catch (error) {
				// a removed editor or detached input must never break the panel
			}
		});

		ui.fieldTimers = [];
	}

	/* ==================== page title (owned landings) ==================== */

	/**
	 * An owned landing's name is its owner's title of the selected language
	 * (oc_information_description.title / oc_blog_post_description.name). It
	 * is edited in Page Settings — the top bar has no name field.
	 */
	function ownerTitle() {
		return CFG.ownerTitles ? String(CFG.ownerTitles[ui.lang] || '') : '';
	}

	/**
	 * No autosave for the owner title either: the edit waits in
	 * ui.pendingOwnerTitle and is flushed by the explicit save (or by the
	 * "save?" confirmation on language switch/exit).
	 */
	function markOwnerTitleDirty(value) {
		ui.pendingOwnerTitle = { lang: Number(ui.lang), value: String(value) };
		touch({});
	}

	/**
	 * @return {Promise<boolean>} true when nothing was pending or it saved.
	 */
	function flushOwnerTitle() {
		var pending = ui.pendingOwnerTitle;
		ui.pendingOwnerTitle = null;

		if (!pending || !CFG.urls.ownerNames) {
			return Promise.resolve(true);
		}

		var body = new URLSearchParams();

		body.set(CFG.ownerIdField, String(CFG.ownerId));
		body.set('names[' + pending.lang + ']', pending.value);

		return fetch(CFG.urls.ownerNames, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.then(function (response) { return response.json(); })
			.then(function (json) {
				if (json.error) {
					toast(json.error, 'error');
					ui.pendingOwnerTitle = pending;
					ui.dirty = true;
					updateSaveState();

					return false;
				}

				CFG.ownerTitles[pending.lang] = pending.value.trim();

				return true;
			})
			.catch(function () {
				toast(t('text_error_save', 'Could not save'), 'error');
				ui.pendingOwnerTitle = pending;
				ui.dirty = true;
				updateSaveState();

				return false;
			});
	}

	/* ==================== history ==================== */

	function snapshot() {
		return JSON.stringify({ page: state.page, blocks: state.blocks, owner: state.owner });
	}

	function pushHistory() {
		// Seal any open typing session first: its snapshot is older.
		commitTextEdit();

		ui.history.push(snapshot());

		if (ui.history.length > 50) {
			ui.history.shift();
		}

		ui.future = [];
	}

	// Coalesced typing history: while a text input is focused every keystroke
	// mutates the state directly; the pre-edit snapshot is committed as ONE
	// undo step when the field loses focus (or the panel re-renders). Typing
	// a word and hitting undo reverts the word, not one character. While
	// focused, Ctrl+Z stays native to the field (the keydown handler skips
	// editable targets), exactly like Elementor.
	var pendingTextSnapshot = null;

	function trackTextEdit() {
		if (pendingTextSnapshot === null) {
			pendingTextSnapshot = snapshot();
			ui.future = [];
		}
	}

	function commitTextEdit() {
		if (pendingTextSnapshot === null) return;

		ui.history.push(pendingTextSnapshot);

		if (ui.history.length > 50) {
			ui.history.shift();
		}

		pendingTextSnapshot = null;
	}

	function restoreSnapshot(json) {
		var parsed = JSON.parse(json);

		state.page = parsed.page;
		state.blocks = (parsed.blocks || []).map(normalizeBlock);
		state.owner = parsed.owner && typeof parsed.owner === 'object' ? parsed.owner : { status: 0, descriptions: {} };
		ui.dirty = true;
		ui.pendingSelectBlock = null;
		ui.selectedId = blockById(ui.selectedId) ? ui.selectedId : 0;
		updateSaveState();
		updateStatusBadge();
		renderProperties();
		renderNavigator();
		touch({ structural: true, save: true });
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

	/**
	 * Marks the page dirty. Nothing is sent to the server here: the page
	 * saves only on the explicit Save button (or Ctrl+S), on a structural
	 * action passed with `save: true` (drop, duplicate, remove, move,
	 * undo/redo - the canvas cannot stay consistent without it) and after
	 * a "save?" confirmation when the language tab changes.
	 */
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

		if (options.save) {
			save(false);
		}
	}

	/**
	 * The whole tree in model order (sections before their children), with
	 * dense per-group sort numbers.
	 */
	function payload() {
		renumber();

		var blocks = [];

		topLevel().forEach(function (block) {
			blocks.push(block);

			if (!isContainerBlock(block)) return;

			for (var slot = 0; slot < columnCountOf(block); slot++) {
				childrenOf(block.block_id, slot).forEach(function (child) {
					blocks.push(child);
				});
			}
		});

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
			owner: CFG.ownerId ? state.owner : undefined,
			blocks: blocks.map(function (block) {
				return {
					block_id: Number(block.block_id) || 0,
					code: block.code,
					status: Number(block.status),
					parent_id: Number(block.parent_id) || 0,
					slot: Number(block.slot) || 0,
					sort: Number(block.sort) || 0,
					settings: block.settings,
					content: block.content
				};
			})
		};
	}

	/**
	 * Runs every `done` callback queued by save() calls that arrived while
	 * another save was in flight (or that had nothing to do).
	 */
	function drainSaveDone() {
		var list = ui.saveDone || [];
		ui.saveDone = [];

		list.forEach(function (fn) {
			try {
				fn();
			} catch (error) {
				// a post-save UI switch must never break on a callback
			}
		});
	}

	function save(force, done) {
		if (done) {
			ui.saveDone = ui.saveDone || [];
			ui.saveDone.push(done);
		}

		if (ui.saving) {
			ui.saveQueued = true;
			return;
		}

		if (!ui.dirty && !force) {
			drainSaveDone();
			return;
		}

		ui.saving = true;
		updateSaveState();

		// a save re-renders from the server anyway - drop any pending preview
		clearTimeout(ui.previewTimer);
		ui.previewState = null;

		flushOwnerTitle().then(function () {
			proceedSave();
		});
	}

	function proceedSave() {
		if (!ui.dirty) {
			ui.saving = false;
			updateSaveState();
			drainSaveDone();

			return;
		}

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
					drainSaveDone();

					if (json.conflict) {
						if (window.confirm(json.error)) {
							window.location.reload();
						}
					}

					return;
				}

				ui.dirty = false;
				state.version = Number(json.version) || state.version + 1;

				// The server assigns ids to new blocks; sync them back through
				// client_id (temp or existing) so children stay attached.
				if (json.blocks) {
					json.blocks.forEach(function (saved) {
						var clientId = Number(saved.client_id);

						var block = clientId !== 0 ? findBlockByClientId(clientId) : null;

						if (!block) return;

						block.block_id = Number(saved.block_id);
						block.parent_id = Number(saved.parent_id) || 0;
						block.slot = Number(saved.slot) || 0;
						block.sort = Number(saved.sort) || 0;
					});
				}

				var reload = ui.needReload;
				var changed = Object.keys(ui.dirtyBlocks);
				ui.needReload = false;
				ui.dirtyBlocks = {};

				updateSaveState();
				updateStatusBadge();
				updatePreviewLink();

				// The server is the source of truth for the hierarchy (it
				// clamps slots to the section structure): re-render the tree
				// so a changed `structure` preset shows its new columns.
				renderNavigator();

				if (reload) {
					reloadIframe();
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
					save(false);
				} else {
					drainSaveDone();
				}
			})
			.catch(function (error) {
				ui.saving = false;
				updateSaveState();
				toast(t('text_error_save', 'Could not save') + ': ' + error.message, 'error');
				drainSaveDone();
			});
	}

	function findBlockByClientId(clientId) {
		clientId = Number(clientId);

		for (var i = 0; i < state.blocks.length; i++) {
			if (Number(state.blocks[i].block_id) === clientId) return state.blocks[i];
		}

		return null;
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

	function iframeLabels() {
		var labels = {
			_drop: t('text_drop_here', '')
		};

		Object.keys(CFG.schema || {}).forEach(function (code) {
			labels[code] = t(CFG.schema[code].name, code);
		});

		return labels;
	}

	function reloadIframe() {
		var frame = $('lp-canvas-frame');

		if (!frame) return;

		ui.iframeReady = false;

		refreshToken(function () {
			frame.src = buildPreviewUrl('_=' + Date.now());

			var tries = 0;
			var wait = setInterval(function () {
				tries++;

				if (ui.iframeReady) {
					clearInterval(wait);

					if (ui.pendingSelectBlock && Number(ui.pendingSelectBlock.block_id) > 0) {
						selectBlock(Number(ui.pendingSelectBlock.block_id));
						ui.pendingSelectBlock = null;
					}
				}

				if (tries > 120) {
					clearInterval(wait);
				}
			}, 150);
		});
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

	/**
	 * Unsaved live preview: re-renders ONE block from the in-memory state
	 * (POST preview_block, read-only on the server) and swaps just that node
	 * in the canvas. Debounced, so typing never hammers the server the way
	 * the old autosave did - and nothing is persisted until Save.
	 */
	function previewPayload(block) {
		var data = {
			code: block.code,
			block_id: Number(block.block_id) || 0,
			parent_id: Number(block.parent_id) || 0,
			status: Number(block.status) ? 1 : 0,
			settings: block.settings,
			language_id: ui.lang,
			content: (block.content && block.content[ui.lang]) || {}
		};

		if (isContainerBlock(block)) {
			data.children = [];

			for (var slot = 0; slot < columnCountOf(block); slot++) {
				childrenOf(block.block_id, slot).forEach(function (child) {
					data.children.push({
						code: child.code,
						block_id: Number(child.block_id) || 0,
						slot: slot,
						status: Number(child.status) ? 1 : 0,
						settings: child.settings,
						content: (child.content && child.content[ui.lang]) || {}
					});
				});
			}
		}

		return data;
	}

	function schedulePreview(block) {
		if (!block || !ui.iframeReady || ui.saving) return;

		clearTimeout(ui.previewTimer);

		var blockId = Number(block.block_id) || 0;

		ui.previewTimer = setTimeout(function () {
			previewBlock(blockId);
		}, 600);
	}

	function previewBlock(blockId) {
		var block = blockById(blockId);

		if (!block || !ui.iframeReady || ui.saving) return;

		var seq = ++ui.previewSeq;

		ui.previewState = { blockId: Number(blockId), lang: ui.lang, seq: seq };

		fetch(previewBaseFor(ui.lang) + '&preview_block=1&_=' + Date.now(), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(previewPayload(block))
		})
			.then(function (response) { return response.text(); })
			.then(function (html) {
				if (!ui.previewState || ui.previewState.seq !== seq || ui.previewState.lang !== ui.lang) return;
				if (!html) return;

				postToIframe({ type: 'lp:replace', blockId: Number(blockId), html: html });

				// A section preset change re-shapes the navigator columns too.
				if (isContainerBlock(block)) {
					renderNavigator();
				}
			})
			.catch(function () {
				// a failed preview is retried on the next keystroke; the
				// explicit save always re-renders from the server anyway
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

				postToIframe({ type: 'lp:labels', labels: iframeLabels() });
				// A reloaded iframe starts at desktop; restore the chosen device so
				// hidden-block feedback survives language switches and saves.
				sendViewport();
				break;

			case 'lp:selected':
				selectBlock(Number(data.blockId), true);
				break;

			case 'lp:add':
				addBlock(data.code, {
					parentId: Number(data.parentId) || 0,
					slot: Number(data.slot) || 0,
					afterId: Number(data.afterId) || 0
				});
				break;

			case 'lp:moveTree':
				applyTree(data.items || []);
				break;

			case 'lp:action':
				performAction(data.action, Number(data.blockId) || ui.selectedId);
				break;

			case 'lp:key':
				remoteKey(String(data.combo || ''));
				break;
		}
	}

	/* ==================== selection & block actions ==================== */

	function selectBlock(blockId, fromIframe) {
		ui.selectedId = Number(blockId) || 0;
		ui.siteSettings = false;

		if (!fromIframe) {
			postToIframe({ type: 'lp:select', blockId: ui.selectedId });
		}

		renderProperties();
		renderNavigator();
	}

	function addBlock(code, target) {
		if (!CFG.schema[code]) return;

		target = target || {};

		var parentId = Number(target.parentId) || 0;
		var afterId = Number(target.afterId) || 0;
		var slot = Number(target.slot) || 0;

		// Sections only live at the page level; widgets dropped at the page
		// level stay loose full-width sections (backwards compatible).
		if (isContainerBlock({ code: code })) {
			parentId = 0;
		} else if (parentId && !isContainerBlock(blockById(parentId))) {
			parentId = 0;
		}

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
			block_id: nextTempId(),
			code: code,
			status: 1,
			parent_id: parentId,
			slot: slot,
			settings: settings,
			content: content
		});

		var anchor = afterId ? blockById(afterId) : null;

		if (anchor && Number(anchor.parent_id) === parentId && Number(anchor.slot) === slot) {
			// Half-step sorts keep the block right after its anchor once
			// renumber() densifies the group.
			block.sort = Number(anchor.sort) + 0.5;
			state.blocks.splice(state.blocks.indexOf(anchor) + 1, 0, block);
		} else {
			var siblings = parentId ? childrenOf(parentId, slot) : topLevel();

			block.sort = siblings.length ? Number(siblings[siblings.length - 1].sort) + 1 : 0;
			state.blocks.push(block);
		}

		renumber();

		ui.pendingSelectBlock = block;
		ui.selectedId = 0;

		touch({ structural: true, save: true });
		renderProperties();
		renderNavigator();
	}

	function duplicateBlock(blockId) {
		var block = blockById(blockId);

		if (!block) return;

		pushHistory();

		var source = [block];

		// A section duplicates with its columns' widgets.
		if (isContainerBlock(block)) {
			for (var slot = 0; slot < columnCountOf(block); slot++) {
				childrenOf(block.block_id, slot).forEach(function (child) {
					source.push(child);
				});
			}
		}

		// Right after the original inside its own group (the half-step sort
		// densifies on renumber).
		var copies = cloneBlocks(source, function (copy, original) {
			copy.sort = Number(original.sort) + 0.5;
		});

		var anchorIndex = state.blocks.indexOf(block);

		if (anchorIndex >= 0) {
			var args = [anchorIndex + 1, 0].concat(copies);
			state.blocks.splice.apply(state.blocks, args);
		} else {
			copies.forEach(function (copy) {
				state.blocks.push(copy);
			});
		}

		renumber();

		ui.pendingSelectBlock = copies[0];
		ui.selectedId = 0;

		touch({ structural: true, save: true });
		renderProperties();
		renderNavigator();
	}

	function removeBlock(blockId) {
		var block = blockById(blockId);

		if (!block) return;

		pushHistory();

		var doomed = [block];

		if (isContainerBlock(block)) {
			state.blocks.forEach(function (other) {
				if (Number(other.parent_id) === Number(block.block_id)) {
					doomed.push(other);
				}
			});
		}

		state.blocks = state.blocks.filter(function (other) {
			return doomed.indexOf(other) === -1;
		});

		if (doomed.some(function (other) { return Number(other.block_id) === Number(ui.selectedId); })) {
			ui.selectedId = 0;
		}

		ui.dirtyBlocks = {};

		touch({ structural: true, save: true });
		renderProperties();
		renderNavigator();
	}

	/**
	 * Up/down within the block's own sibling group (its column, or the page).
	 */
	function moveBlock(blockId, direction) {
		var block = blockById(blockId);

		if (!block) return;

		var siblings = siblingsOf(block);
		var index = siblings.indexOf(block);
		var target = direction === 'up' ? index - 1 : index + 1;

		if (target < 0 || target >= siblings.length) return;

		pushHistory();

		var neighbour = siblings[target];
		var sort = Number(block.sort);

		block.sort = Number(neighbour.sort);
		neighbour.sort = sort;

		renumber();

		touch({ structural: true, save: true });
		postToIframe({ type: 'lp:select', blockId: blockId });
		renderNavigator();
	}

	/**
	 * Applies a full tree (from the iframe drag or the navigator) to the model.
	 *
	 * items: [{id, parent, slot, order}]
	 */
	function applyTree(items) {
		if (!items.length) return;

		var known = 0;

		items.forEach(function (item) {
			if (blockById(item.id)) known++;
		});

		// A report that matches nothing came from a stale canvas - applying it
		// would wipe the model, so it is dropped instead.
		if (!known) return;

		pushHistory();

		items.forEach(function (item) {
			var block = blockById(item.id);

			if (!block) return;

			block.parent_id = Number(item.parent) || 0;
			block.slot = Number(item.slot) || 0;
			block.sort = Number(item.order) || 0;
		});

		renumber();

		touch({ structural: true, save: true });
		renderNavigator();
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

	/**
	 * A shortcut pressed while the canvas iframe has focus: the iframe
	 * neutralises the browser default and forwards the combo here, so
	 * Ctrl+S / Del / Esc work the same on the canvas and in the panels.
	 */
	function remoteKey(combo) {
		switch (combo) {
			case 'save':
				save(true);
				break;
			case 'undo':
				undo();
				break;
			case 'redo':
				redo();
				break;
			case 'duplicate':
				if (ui.selectedId) performAction('duplicate', ui.selectedId);
				break;
			case 'remove':
				if (ui.selectedId) performAction('remove', ui.selectedId);
				break;
			case 'escape':
				selectBlock(0);
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

			var code = item.getAttribute('data-block-code');
			var isSection = isContainerBlock({ code: code });

			// Sections and widgets are two payload types: the iframe accepts
			// widgets into columns, sections only at the page level.
			event.dataTransfer.setData(isSection ? 'application/x-lp-section' : 'application/x-lp-block', code);
			event.dataTransfer.setData('text/plain', code);
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

			addBlock(item.getAttribute('data-block-code'), clickTarget());
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

					// A <details> group: open it while searching so matches stay visible.
					if (group.tagName === 'DETAILS' && query && visible) {
						group.open = true;
					}

					group.style.display = visible ? '' : 'none';
				});
			});
		}
	}

	/**
	 * Where a palette CLICK inserts: into the selected block's group after
	 * it, or at the end of the page (sections always at the page level).
	 */
	function clickTarget() {
		var selected = blockById(ui.selectedId);

		if (!selected) return { parentId: 0, slot: 0, afterId: 0 };

		if (isContainerBlock(selected)) {
			// A section is selected: insert after it at the page level.
			return { parentId: 0, slot: 0, afterId: Number(selected.block_id) };
		}

		return {
			parentId: Number(selected.parent_id) || 0,
			slot: Number(selected.slot) || 0,
			afterId: Number(selected.block_id) || 0
		};
	}

	/* ==================== templates ==================== */

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

	function templateSourceBlocks() {
		var selected = blockById(ui.selectedId);

		if (selected) {
			var blocks = [selected];

			if (isContainerBlock(selected)) {
				for (var slot = 0; slot < columnCountOf(selected); slot++) {
					childrenOf(selected.block_id, slot).forEach(function (child) {
						blocks.push(child);
					});
				}
			}

			return blocks;
		}

		return state.blocks.slice();
	}

	function insertTemplate(templateId) {
		for (var i = 0; i < ui.templates.length; i++) {
			if (Number(ui.templates[i].template_id) !== Number(templateId)) continue;

			pushHistory();

			var inserted = cloneBlocks(ui.templates[i].blocks || [], function (copy) {
				copy.status = 1;
			});

			inserted.forEach(function (copy) {
				state.blocks.push(copy);
			});

			renumber();

			ui.pendingSelectBlock = inserted[inserted.length - 1] || null;

			touch({ structural: true, save: true });
			toast(t('text_insert_template', 'Template inserted'));
			renderNavigator();
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

		var blocks = all ? state.blocks.slice() : templateSourceBlocks();

		if (!blocks.length) {
			blocks = state.blocks.slice();
		}

		var body = {
			name: name,
			blocks: blocks.map(function (block) {
				return {
					block_id: Number(block.block_id) || 0,
					code: block.code,
					status: 1,
					parent_id: Number(block.parent_id) || 0,
					slot: Number(block.slot) || 0,
					sort: Number(block.sort) || 0,
					settings: block.settings,
					content: block.content
				};
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

		updateSettingsMenu();

		// Flush pending debounced writes first - they still know their own
		// language and editor; after tinymce.remove() they would either write
		// to the wrong tab or read a dead editor (the old data-wiping race).
		flushFieldTimers();
		commitTextEdit();

		if (typeof tinymce !== 'undefined') {
			tinymce.remove();
		}

		var block = blockById(ui.selectedId);
		var tabs = $('lp-props-tabs');

		if (!block) {
			// No selection: the right panel edits either Site Settings (global
			// landing tokens) or the current page.
			if (ui.siteSettings) {
				title.innerHTML = '<i data-lucide="palette" class="w-3.5 h-3.5"></i> ' + esc(t('text_site_settings', 'Site settings'));
				body.innerHTML = '<div class="space-y-3">' + siteSettingsHtml() + '</div>';
				bindSiteSettings(body);
			} else {
				title.innerHTML = '<i data-lucide="settings-2" class="w-3.5 h-3.5"></i> ' + esc(t('text_page_settings', 'Page settings'));
				body.innerHTML = '<div class="space-y-3">' + settingsHtml() + '</div>';
				bindSettings(body);
			}
			actions.classList.add('hidden');

			if (tabs) tabs.classList.add('hidden');
			// Site Settings is the root panel: it has no close button.
			if (close) close.classList.toggle('hidden', ui.siteSettings);

			lucide.createIcons({ nodes: [body] });
			return;
		}

		if (tabs) {
			tabs.classList.remove('hidden');

			var tabButtons = tabs.querySelectorAll('[data-props-tab]');

			for (var ti = 0; ti < tabButtons.length; ti++) {
				tabButtons[ti].classList.toggle('lp-active', tabButtons[ti].getAttribute('data-props-tab') === ui.propsTab);
			}
		}

		var schema = CFG.schema[block.code] || { fields: [], name: block.code, icon: 'square' };

		title.innerHTML = '<i data-lucide="' + esc(schema.icon || 'square') + '" class="w-3.5 h-3.5"></i> ' + esc(t(schema.name, block.code));
		actions.classList.remove('hidden');

		if (close) close.classList.remove('hidden');

		var statusId = 'lp-status-' + Number(block.block_id);

		var html = '<div class="mb-3 flex items-center justify-between rounded-lg bg-gray-50 px-2.5 py-2">' +
			'<div class="flex items-center gap-2 text-xs font-medium text-gray-700">' +
				'<label class="lp-switch" for="' + statusId + '"><input type="checkbox" id="' + statusId + '" data-block-status' + (Number(block.status) ? ' checked' : '') + ' /><span class="lp-switch-track"></span></label>' +
				'<label for="' + statusId + '">' + esc(t('entry_status', 'Status')) + '</label>' +
			'</div>' +
			'<span class="text-[10px] text-gray-400">#' + Number(block.block_id) + '</span></div>';

		// Elementor-style tabs: Content holds the block's own fields, Style the
		// shared look (backgrounds, spacing, width) and Advanced the visibility
		// toggles, anchors and custom classes.
		var contentHtml = '';
		var styleHtml = '';
		var advancedHtml = '';

		schema.fields.forEach(function (field) {
			var rendered = field.type === 'repeater' ? renderRepeater(block, field) : renderField(block, field);

			if (STYLE_KEYS.indexOf(field.key) !== -1) {
				styleHtml += rendered;
			} else if (ADVANCED_KEYS.indexOf(field.key) !== -1) {
				advancedHtml += rendered;
			} else {
				contentHtml += rendered;
			}
		});

		var activeHtml = ui.propsTab === 'style' ? styleHtml : (ui.propsTab === 'advanced' ? advancedHtml : contentHtml);

		// Advanced fields live only in their own tab; duplicating them as a
		// <details> block under Content/Style made the same input appear twice
		// and confused which one was being edited.
		html += '<div class="space-y-3">' + activeHtml + '</div>';

		body.innerHTML = html;

		lucide.createIcons({ nodes: [body] });
		bindProperties(body, block);
		hydrateLinkLabels(body);
	}

	function fieldId(block, field) {
		return 'lp-f-' + Number(block.block_id) + '-' + field.key;
	}

	// Left/Centre/Right selects are edited as a segmented button group with
	// Lucide icons — the same idea as the banner form's content position.
	var ALIGN_OPTIONS = ['left', 'center', 'right'];

	var ALIGN_ICONS = {
		left: 'align-start-vertical',
		center: 'align-center-vertical',
		right: 'align-end-vertical'
	};

	function isAlignmentOptions(options) {
		var keys = Object.keys(options || {});

		return keys.length > 0 && keys.every(function (key) {
			return ALIGN_OPTIONS.indexOf(key) !== -1;
		});
	}

	function renderAlignmentOptions(id, field, value) {
		var options = Object.keys(field.options || {});
		var html = '<div id="' + esc(id) + '" class="lp-align-group" data-align-options>';

		options.forEach(function (option) {
			var active = String(value) === String(option);

			html += '<button type="button" data-align-value="' + esc(option) + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
				'<i data-lucide="' + ALIGN_ICONS[option] + '"></i>' +
				'<span>' + esc(t(field.options[option], option)) + '</span>' +
				'</button>';
		});

		return html + '</div>';
	}

	function renderField(block, field) {
		var id = fieldId(block, field);
		var value = getValue(field, block);
		var label = esc(t(field.label, field.key));
		var langBadge = field.localized ? ' <span class="text-[9px] text-gray-400">' + esc(String(langCodeById(ui.lang) || '').toUpperCase()) + '</span>' : '';
		var alignField = field.type === 'select' && isAlignmentOptions(field.options);
		var html = '<div class="lp-field" data-field-key="' + esc(field.key) + '" data-field-type="' + esc(field.type) + '">' +
			'<label class="lp-field-label"' + (alignField ? '' : ' for="' + id + '"') + '>' + label + langBadge + '</label>';

		switch (field.type) {
			case 'textarea':
			case 'code':
				html += '<textarea id="' + id + '" rows="' + (field.type === 'code' ? 8 : 4) + '" class="lp-input' + (field.type === 'code' ? ' font-mono text-[11px]' : '') + '" data-input>' + esc(value) + '</textarea>';
				break;

			case 'richtext':
				html += '<textarea id="' + id + '" rows="6" class="lp-input" data-input data-richtext="1">' + esc(value) + '</textarea>';
				break;

			case 'number':
				// Bounded numbers get an Elementor-style slider next to the input:
				// drag for a live preview, type for an exact value.
				if (field.min !== undefined && field.max !== undefined) {
					html += '<div class="flex items-center gap-2">' +
						'<input type="range" class="lp-range flex-1" data-input-range value="' + esc(value) + '" min="' + Number(field.min) + '" max="' + Number(field.max) + '" />' +
						'<input type="number" id="' + id + '" class="lp-input text-center" style="width:4rem" data-input value="' + esc(value) + '" min="' + Number(field.min) + '" max="' + Number(field.max) + '" /></div>';
				} else {
					html += '<input type="number" id="' + id + '" class="lp-input" data-input value="' + esc(value) + '" />';
				}
				break;

			case 'toggle':
				// The visible track must be inside a label: the checkbox itself is
				// visually hidden (0x0), so a plain <span> over it is not clickable.
				html += '<div class="flex items-center justify-between gap-2 py-0.5">' +
					'<label class="text-xs text-gray-700" for="' + id + '">' + label + '</label>' +
					'<label class="lp-switch" for="' + id + '"><input type="checkbox" id="' + id + '" data-input' + (Number(value) ? ' checked' : '') + ' /><span class="lp-switch-track"></span></label></div>';
				break;

			case 'select':
				if (isAlignmentOptions(field.options)) {
					html += renderAlignmentOptions(id, field, value);
				} else {
					html += '<select id="' + id + '" class="lp-input" data-input>';

					Object.keys(field.options || {}).forEach(function (option) {
						html += '<option value="' + esc(option) + '"' + (String(value) === String(option) ? ' selected' : '') + '>' +
							esc(t(field.options[option], option)) + '</option>';
					});

					html += '</select>';
				}
				break;

			case 'color':
				html += '<div class="flex items-center gap-2">' +
					'<input type="color" id="' + id + '" data-input value="' + esc(value || '#ffffff') + '" />' +
					'<input type="text" class="lp-input" data-input-text value="' + esc(value) + '" placeholder="#ffffff" />' +
					'</div>' +
					'<div class="flex items-center gap-1.5 mt-1.5" data-swatches>' +
					'<button type="button" class="lp-swatch" data-swatch="" title="—" style="background:linear-gradient(135deg,#fff 0 46%,#c77070 46% 54%,#fff 54% 100%)"></button>' +
					'<button type="button" class="lp-swatch" data-swatch="#ffffff" style="background:#ffffff"></button>' +
					'<button type="button" class="lp-swatch" data-swatch="#f1f5f9" style="background:#f1f5f9"></button>' +
					'<button type="button" class="lp-swatch" data-swatch="#111827" style="background:#111827"></button>' +
					'<button type="button" class="lp-swatch" data-swatch="#5b7ea8" style="background:#5b7ea8"></button>' +
					'<button type="button" class="lp-swatch" data-swatch="#059669" style="background:#059669"></button>' +
					'<button type="button" class="lp-swatch" data-swatch="#d97706" style="background:#d97706"></button>' +
					'<button type="button" class="lp-swatch" data-swatch="#dc2626" style="background:#dc2626"></button>' +
					'<button type="button" class="lp-swatch" data-swatch="#7c3aed" style="background:#7c3aed"></button>' +
					'</div>';
				break;

			case 'image':
				html += '<div class="flex items-start gap-2">' +
					'<div class="w-20 h-20 rounded-lg border border-gray-200 bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">' +
					(value ? '<img src="' + esc(CFG.imageUrl + value) + '" alt="" class="w-full h-full object-cover" data-image-preview />' : '<i data-lucide="image" class="w-5 h-5 text-gray-300"></i>') +
					'</div>' +
					'<div class="flex-1 min-w-0">' +
					'<input type="text" id="' + id + '" class="lp-input mb-1" data-input value="' + esc(value) + '" placeholder="catalog/…/image.jpg" />' +
					'<div class="flex gap-1">' +
					'<button type="button" class="inline-flex items-center px-2 py-1.5 text-[11px] font-medium leading-none text-blue-700 bg-white hover:bg-blue-50 border border-blue-200 rounded" data-media-pick>' + esc(t('text_choose_image', 'Choose')) + '</button>' +
					'<button type="button" class="inline-flex items-center px-2 py-1.5 text-[11px] font-medium leading-none text-gray-500 bg-white hover:text-red-600 hover:bg-red-50 hover:border-red-200 border border-gray-200 rounded" data-clear>' + esc(t('text_clear', 'Clear')) + '</button>' +
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
				html += renderLinkControl(value, 'data-input', id);
				break;

			case 'text':
			default:
				html += '<input type="text" id="' + id + '" class="lp-input" data-input value="' + esc(value) + '" />';
				break;
		}

		html += '</div>';

		return html;
	}

	/**
	 * Extracts the entity type and id from a stored URL. The canonical form is
	 * "route=..." (no index.php; shared with the banner form); legacy
	 * "index.php?route=..." links are still recognised; anything else stays a
	 * custom URL.
	 */
	function parseLinkUrl(value) {
		var original = String(value === null || value === undefined ? '' : value).trim();
		var query = '';
		var mark = original.indexOf('?');

		if (mark >= 0) {
			query = original.slice(mark + 1);
		} else if (/^route=/i.test(original)) {
			query = original;
		}

		if (!query) return { type: 'custom', value: original };

		var params = {};

		query.replace(/&amp;/g, '&').split('&').forEach(function (part) {
			var pos = part.indexOf('=');

			if (pos < 0) return;

			var raw = part.slice(pos + 1).replace(/\+/g, ' ');

			try {
				params[part.slice(0, pos)] = decodeURIComponent(raw);
			} catch (e) {
				params[part.slice(0, pos)] = raw;
			}
		});

		var route = params.route || '';
		var type = '';

		for (var key in LINK_ROUTES) {
			if (LINK_ROUTES[key].route === route) {
				type = key;
				break;
			}
		}

		// Route-only types (the built-in contact page) carry no id.
		if (type && LINK_ROUTES[type].param === '') {
			return { type: type, value: '' };
		}

		var id = type ? params[LINK_ROUTES[type].param] : '';

		if (!id || !/^[0-9_]+$/.test(String(id))) {
			return { type: 'custom', value: original };
		}

		return { type: type, value: String(id) };
	}

	function buildLinkUrl(type, value) {
		var def = LINK_ROUTES[type];

		if (!def) return '';
		if (def.param === '') return 'route=' + def.route;
		if (!value) return '';

		return 'route=' + def.route + '&' + def.param + '=' + encodeURIComponent(value);
	}

	/**
	 * One link control, used both by a plain field and by a repeater row.
	 * `inputAttrs` decides where the canonical URL is persisted: `data-input`
	 * for a field, `data-repeater-key` for a repeater sub-field. The URL input
	 * itself is part of the persisted payload; the type picker and the entity
	 * search are editor-only.
	 */
	function linkClearButton() {
		var label = esc(t('text_clear', 'Clear'));

		return '<button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-600 leading-none" data-link-clear title="' + label + '" aria-label="' + label + '"><i data-lucide="x" class="w-3 h-3"></i></button>';
	}

	function renderLinkControl(value, inputAttrs, id) {
		var parsed = parseLinkUrl(value);
		var options = '';

		LINK_TYPES.forEach(function (item) {
			options += '<option value="' + item.type + '"' + (parsed.type === item.type ? ' selected' : '') + '>' +
				esc(t(item.label, item.label)) + '</option>';
		});

		var isEntity = parsed.type !== 'custom';
		var isRouteOnly = parsed.type === 'contact';

		return '<div class="space-y-1" data-link-control data-link-lang="' + Number(ui.lang) + '">' +
			'<select ' + (id ? 'id="' + esc(id) + '" ' : '') + 'class="lp-input" data-link-type>' + options + '</select>' +
			'<div class="relative' + (isEntity ? ' hidden' : '') + '" data-link-custom>' +
				'<input type="text" class="lp-input pr-7" ' + (inputAttrs || 'data-input') + ' data-link-url data-link-stored="' + esc(value) + '" value="' + esc(value) + '" placeholder="https://…" autocomplete="off" />' +
				linkClearButton() +
			'</div>' +
			'<div' + (isEntity ? '' : ' class="hidden"') + ' data-link-entity>' +
				'<div class="relative">' +
					'<input type="text" class="lp-input pr-7" data-link-search data-link-lang="' + Number(ui.lang) + '" value="' + (isRouteOnly ? esc(t('text_link_contact', 'Contact page')) : '') + '" data-link-value="' + esc(parsed.value) + '" placeholder="' + esc(t('text_link_search', 'Search…')) + '" autocomplete="off"' + (isRouteOnly ? ' readonly' : '') + ' />' +
					linkClearButton() +
				'</div>' +
				'<div class="mt-1">' +
					'<span class="text-[10px] text-gray-400 truncate" data-link-preview>' + esc(value) + '</span>' +
				'</div>' +
			'</div>' +
		'</div>';
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

				if (sub.type === 'link') {
					html += renderLinkControl(value, 'data-repeater-key="' + esc(sub.key) + '"', '');
				} else if (sub.type === 'select') {
					html += '<select class="lp-input" data-repeater-key="' + esc(sub.key) + '">';

					Object.keys(sub.options || {}).forEach(function (option) {
						html += '<option value="' + esc(option) + '"' + (String(value) === String(option) ? ' selected' : '') + '>' + esc(t(sub.options[option], option)) + '</option>';
					});

					html += '</select>';
				} else if (sub.type === 'textarea') {
					html += '<textarea class="lp-input" rows="3" data-repeater-key="' + esc(sub.key) + '" placeholder="' + esc(t(sub.label, sub.key)) + '">' + esc(String(value === undefined || value === null ? '' : value)) + '</textarea>';
				} else {
					html += '<input type="text" class="lp-input" data-repeater-key="' + esc(sub.key) + '" value="' + esc(value) + '" placeholder="' + esc(t(sub.label, sub.key)) + '" />';
				}
			});

			html += '<button type="button" class="inline-flex items-center gap-1 px-2 py-1.5 text-[11px] font-medium leading-none text-red-600 bg-white hover:bg-red-50 border border-red-200 rounded" data-repeater-remove>' + esc(t('text_remove', 'Remove')) + '</button></div>';
		});

		html += '</div><button type="button" class="mt-1 inline-flex items-center gap-1 px-2 py-1.5 text-[11px] font-medium leading-none text-blue-700 bg-white hover:bg-blue-50 border border-blue-200 rounded" data-repeater-add>+ ' + esc(t('text_add_row', 'Add')) + '</button></div>';

		return html;
	}

	function bindProperties(container, block) {
		// Language captured at bind time: every write below goes to THIS tab,
		// no matter which tab is current when a debounce fires.
		var lang = ui.lang;

		var statusInput = container.querySelector('[data-block-status]');

		if (statusInput) {
			statusInput.addEventListener('change', function () {
				pushHistory();
				block.status = statusInput.checked ? 1 : 0;
				touch({ blockId: block.block_id });
				schedulePreview(block);
				renderNavigator();
			});
		}

		container.querySelectorAll('.lp-field').forEach(function (fieldEl) {
			var field = fieldDefinition(block.code, fieldEl.getAttribute('data-field-key'));

			if (!field) return;

			if (field.type === 'repeater') {
				bindRepeater(fieldEl, block, field, lang);
				return;
			}

			if (field.type === 'link') {
				bindLinkField(fieldEl, block, field, lang);
				return;
			}

			if (field.type === 'select' && isAlignmentOptions(field.options)) {
				bindAlignmentField(fieldEl, block, field, lang);
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

				var coalesce = ['text', 'textarea', 'code', 'link'].indexOf(field.type) !== -1;

				var apply = function () {
					var value = read();

					if (getValue(field, block, lang) === value) return;

					// Text writes coalesce into one undo step per focus session;
					// discrete controls keep one step per change.
					if (coalesce) {
						trackTextEdit();
					} else {
						pushHistory();
					}
					setValue(block, field, value, lang);

					if (field.type === 'color') {
						var text = fieldEl.querySelector('[data-input-text]');

						if (text) text.value = value;
					}

					touch({ blockId: block.block_id });
					schedulePreview(block);
				};

				var eventName = coalesce ? 'input' : 'change';

				input.addEventListener(eventName, apply);

				if (coalesce) {
					input.addEventListener('blur', commitTextEdit);
				}

				if (field.type === 'richtext') {
					initRichText(input, block, field, lang);
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
					setValue(block, field, value, lang);
					touch({ blockId: block.block_id });
				});

				colorPicker.addEventListener('input', function () {
					colorText.value = colorPicker.value;
				});
			}

			var swatches = fieldEl.querySelector('[data-swatches]');

			if (swatches && colorPicker) {
				swatches.addEventListener('click', function (event) {
					var swatch = event.target.closest('[data-swatch]');

					if (!swatch) return;

					var swatchValue = swatch.getAttribute('data-swatch') || '';

					pushHistory();
					colorPicker.value = swatchValue || '#ffffff';
					setValue(block, field, swatchValue, lang);

					if (colorText) colorText.value = swatchValue;

					touch({ blockId: block.block_id });
					schedulePreview(block);
				});
			}

			// The slider drags live (one history entry per drag), the number
				// input keeps the exact value with the usual change handling.
			var range = fieldEl.querySelector('[data-input-range]');

			if (range) {
				var numberInput = fieldEl.querySelector('input[type="number"][data-input]');
				var rangePushed = false;

				range.addEventListener('input', function () {
					if (!rangePushed) {
						pushHistory();
						rangePushed = true;
					}

					if (numberInput) numberInput.value = range.value;

					setValue(block, field, parseInt(range.value, 10) || 0, lang);
					touch({ blockId: block.block_id });
					schedulePreview(block);
				});

				range.addEventListener('change', function () {
					rangePushed = false;
				});
			}

			var pick = fieldEl.querySelector('[data-media-pick]');
			var clear = fieldEl.querySelector('[data-clear]');

			if (pick) {
				pick.addEventListener('click', function () {
					openMediaPicker(function (path) {
						pushHistory();
						setValue(block, field, path, lang);

						var input = fieldEl.querySelector('[data-input]');
						if (input) input.value = path;

						var preview = fieldEl.querySelector('[data-image-preview]');

						if (preview) {
							preview.src = CFG.imageUrl + path;
						} else {
							var box = fieldEl.querySelector('.w-20');

							if (box) {
								box.innerHTML = '<img src="' + esc(CFG.imageUrl + path) + '" alt="" class="w-full h-full object-cover" data-image-preview />';
							}
						}

						touch({ blockId: block.block_id });
						schedulePreview(block);
					});
				});
			}

			if (clear) {
				clear.addEventListener('click', function () {
					pushHistory();
					setValue(block, field, '', lang);

					var input = fieldEl.querySelector('[data-input]');
					if (input) input.value = '';

					var preview = fieldEl.querySelector('[data-image-preview]');
					if (preview) preview.remove();

					touch({ blockId: block.block_id });
					schedulePreview(block);
				});
			}

			bindPickers(fieldEl, block, field, lang);
		});
	}

	function bindPickers(fieldEl, block, field, lang) {
		var chips = fieldEl.querySelector('[data-chips]');
		var single = fieldEl.querySelector('[data-single]');
		var search = fieldEl.querySelector('[data-autocomplete]');
		var type = search ? search.getAttribute('data-autocomplete') : 'product';

		if (chips) {
			chips.addEventListener('click', function (event) {
				var chip = event.target.closest('.lp-chip');

				if (!chip) return;

				var values = (getValue(field, block, lang) || []).slice();
				var id = Number(chip.getAttribute('data-id'));

				if (event.target.closest('[data-chip-remove]')) {
					pushHistory();
					values = values.filter(function (value) { return Number(value) !== id; });
					setValue(block, field, values, lang);
					chip.remove();
					touch({ blockId: block.block_id });
					schedulePreview(block);
				}

				if (event.target.closest('[data-chip-up]')) {
					var index = values.indexOf(id);

					if (index <= 0) return;

					pushHistory();
					values.splice(index, 1);
					values.splice(index - 1, 0, id);
					setValue(block, field, values, lang);
					renderProperties();
					touch({ blockId: block.block_id });
					schedulePreview(block);
				}
			});
		}

		if (single) {
			single.addEventListener('click', function (event) {
				if (!event.target.closest('[data-chip-remove]')) return;

				pushHistory();
				setValue(block, field, 0, lang);
				schedulePreview(block);

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
				var values = (getValue(field, block, lang) || []).slice();

				if (values.map(Number).indexOf(id) === -1) {
					pushHistory();
					values.push(id);
					setValue(block, field, values, lang);
					touch({ blockId: block.block_id });
					renderProperties();
					schedulePreview(block);
				}
			} else if (single) {
				pushHistory();
				setValue(block, field, id, lang);
				schedulePreview(block);

				var chip = single.querySelector('.lp-chip');
				chip.classList.remove('hidden');
				chip.setAttribute('data-id', id);
				chip.querySelector('[data-single-label]').textContent = item.name;

				touch({ blockId: block.block_id });
			}
		});
	}

	function bindAlignmentField(fieldEl, block, field, lang) {
		var buttons = fieldEl.querySelectorAll('[data-align-value]');

		buttons.forEach(function (button) {
			button.addEventListener('click', function () {
				var value = button.getAttribute('data-align-value');

				if (getValue(field, block, lang) === value) return;

				pushHistory();
				setValue(block, field, value, lang);

				buttons.forEach(function (item) {
					item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
				});

				touch({ blockId: block.block_id });
				schedulePreview(block);
			});
		});
	}

	/* ==================== link fields ==================== */

	function bindLinkField(fieldEl, block, field, lang) {
		var control = fieldEl.querySelector('[data-link-control]');

		if (!control) return;

		bindLinkControl(control, function () {
			return getValue(field, block, lang);
		}, function (url, coalesce) {
			if (getValue(field, block, lang) === url) return;

			if (coalesce) {
				trackTextEdit();
			} else {
				pushHistory();
			}

			setValue(block, field, url, lang);
			touch({ blockId: block.block_id });
			schedulePreview(block);
		});
	}

	function bindRepeaterLinks(rowsEl, schedule) {
		rowsEl.querySelectorAll('[data-link-control]').forEach(function (control) {
			var urlInput = control.querySelector('[data-link-url]');

			if (!urlInput) return;

			bindLinkControl(control, function () {
				var stored = urlInput.getAttribute('data-link-stored');

				return stored !== null ? stored : urlInput.value;
			}, function (url) {
				var stored = urlInput.getAttribute('data-link-stored');

				if ((stored !== null ? stored : urlInput.value) === url) return;

				schedule();
			});
		});
	}

	/**
	 * Shared behaviour for the link control in a field and in a repeater row.
	 * Only the URL (the canonical storefront link) is persisted; the type
	 * picker and the entity search are editor state derived from that URL.
	 */
	function bindLinkControl(control, readUrl, writeUrl) {
		var select = control.querySelector('[data-link-type]');
		var custom = control.querySelector('[data-link-custom]');
		var entity = control.querySelector('[data-link-entity]');
		var urlInput = control.querySelector('[data-link-url]');
		var search = control.querySelector('[data-link-search]');
		var preview = control.querySelector('[data-link-preview]');
		var clears = control.querySelectorAll('[data-link-clear]');
		var lang = Number(control.getAttribute('data-link-lang')) || ui.lang;

		if (!select || !custom || !entity || !urlInput || !search) return;

		function updateMode() {
			var isEntity = select.value !== 'custom';

			custom.classList.toggle('hidden', isEntity);
			entity.classList.toggle('hidden', !isEntity);
		}

		function setUrl(url, coalesce, display) {
			if (readUrl() !== url) writeUrl(url, coalesce);

			var shown = display || url;

			urlInput.value = shown;
			urlInput.setAttribute('data-link-stored', url);

			if (preview) preview.textContent = shown;

			search.setAttribute('data-link-value', parseLinkUrl(url).value || '');
		}

		select.addEventListener('change', function () {
			search.value = '';
			search.dispatchEvent(new Event('input')); // drop a stale dropdown
			var fixed = buildLinkUrl(select.value, '');
			setUrl(fixed, false);
			if (select.value === 'contact') search.value = t('text_link_contact', 'Contact page');
			updateMode();
		});

		urlInput.addEventListener('input', function () {
			setUrl(urlInput.value, true);
		});

		clears.forEach(function (button) {
			button.addEventListener('click', function () {
				search.value = '';
				var fixed = buildLinkUrl(select.value, '');
				setUrl(fixed, false);
				if (select.value === 'contact') search.value = t('text_link_contact', 'Contact page');
			});
		});

		attachAutocomplete(search, function () { return select.value; }, function (item) {
			var type = select.value;
			var key = type + ':' + lang + ':' + item.id;

			ui.names[key] = item.name;
			ui.linkUrls[key] = item.url || '';
			search.value = item.name;
			// Persist the canonical route; show the SEO URL the shop will use.
			setUrl(buildLinkUrl(type, item.id), false, item.url);
		});

		updateMode();
	}

	function bindRepeater(fieldEl, block, field, lang) {
		var rowsEl = fieldEl.querySelector('[data-repeater-rows]');
		var entry = null;

		function sync() {
			var rows = [];

			rowsEl.querySelectorAll('[data-repeater-row]').forEach(function (rowEl) {
				var row = {};

				rowEl.querySelectorAll('[data-repeater-key]').forEach(function (input) {
					// A link input displays its SEO URL but persists the route.
					var stored = input.getAttribute('data-link-stored');

					row[input.getAttribute('data-repeater-key')] = stored !== null ? stored : input.value;
				});

				rows.push(row);
			});

			setValue(block, field, rows, lang);
		}

		function schedule() {
			trackTextEdit();

			if (entry) {
				clearTimeout(entry.timer);
				ui.fieldTimers = ui.fieldTimers.filter(function (item) { return item !== entry; });
			}

			var timer = setTimeout(function () {
				sync();
				touch({ blockId: block.block_id });
				schedulePreview(block);
				ui.fieldTimers = ui.fieldTimers.filter(function (item) { return item !== entry; });
			}, 700);

			entry = trackFieldTimer(timer, function () {
				sync();
				touch({ blockId: block.block_id });
				schedulePreview(block);
			});
		}

		// Autocomplete typing and the link type picker are editor-only:
		// bindLinkControl() writes the URL, which is the persisted value.
		rowsEl.addEventListener('input', function (event) {
			if (!event.target.closest('[data-link-search]')) schedule();
		});
		rowsEl.addEventListener('change', function (event) {
			if (!event.target.closest('[data-link-type]')) schedule();
		});
		rowsEl.addEventListener('focusout', commitTextEdit);

		fieldEl.querySelector('[data-repeater-add]').addEventListener('click', function () {
			pushHistory();

			var rows = (getValue(field, block, lang) || []).slice();
			var max = field.max_rows || 6;

			if (rows.length >= max) return;

			var row = {};

			(field.fields || []).forEach(function (sub) {
				row[sub.key] = sub.default;
			});

			rows.push(row);
			setValue(block, field, rows, lang);
			renderProperties();
			touch({ blockId: block.block_id });
			schedulePreview(block);
		});

		rowsEl.addEventListener('click', function (event) {
			if (!event.target.closest('[data-repeater-remove]')) return;

			pushHistory();

			var rowEl = event.target.closest('[data-repeater-row]');
			var index = Number(rowEl.getAttribute('data-repeater-row'));
			var rows = (getValue(field, block, lang) || []).slice();

			rows.splice(index, 1);
			setValue(block, field, rows, lang);
			renderProperties();
			touch({ blockId: block.block_id });
			schedulePreview(block);
		});

		bindRepeaterLinks(rowsEl, schedule);
	}

	/* ==================== autocomplete ==================== */

	function attachAutocomplete(input, type, onSelect) {
		var list = null;
		var timer = null;
		var resolveType = typeof type === 'function' ? type : function () { return type; };

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
				var entityType = resolveType();

				if (!entityType || entityType === 'custom') return;

				var lang = Number(input.getAttribute('data-link-lang')) || ui.lang;

			fetch(CFG.urls.autocomplete + '&type=' + encodeURIComponent(entityType) + '&language_id=' + encodeURIComponent(lang) + '&filter_name=' + encodeURIComponent(term))
					.then(function (response) { return response.json(); })
					.then(function (items) {
						close();

						if (!items || !items.length) return;

						list = document.createElement('div');
						// In the field flow, directly under the input and as wide as
						// the field itself (the old absolute w-56 popup floated over
						// neighbouring controls and was easy to miss).
						list.className = 'relative z-30 mt-1 w-full max-h-56 overflow-y-auto bg-white border border-gray-200 rounded shadow-lg text-xs';

						items.forEach(function (item) {
							var button = document.createElement('button');
							button.type = 'button';
							button.className = 'block w-full text-left px-2 py-1.5 hover:bg-blue-50 truncate';
							button.textContent = item.name + (item.model ? ' (' + item.model + ')' : '');
							button.addEventListener('click', function () {
								// Clear first: the link callback puts the chosen label back.
								input.value = '';
								onSelect(item);
								close();
							});
							list.appendChild(button);
						});

						input.parentNode.insertAdjacentElement('afterend', list);
					})
					.catch(close);
			}, 220);
		});

		input.addEventListener('blur', function () {
			setTimeout(close, 200);
		});
	}

	/**
	 * Restores the entity labels and SEO URLs of links loaded from the DB.
	 * The block keeps a route; the name and the SEO URL come from one request
	 * per entity type and are cached for the lifetime of the builder page.
	 */
	function linkStoredUrl(control) {
		var input = control.querySelector('[data-link-url]');

		if (!input) return '';

		var stored = input.getAttribute('data-link-stored');

		return stored !== null ? stored : input.value;
	}

	function showLinkSeoUrl(control, url) {
		var input = control.querySelector('[data-link-url]');
		var preview = control.querySelector('[data-link-preview]');

		if (input) input.value = url;
		if (preview) preview.textContent = url;
	}

	function hydrateLinkLabels(root) {
		if (!CFG.urls.autocomplete) return;

		var pending = {};

		root.querySelectorAll('[data-link-control]').forEach(function (control) {
			var select = control.querySelector('[data-link-type]');
			var type = select ? select.value : '';

			if (!type || type === 'custom') return;

			var lang = Number(control.getAttribute('data-link-lang')) || ui.lang;
			var search = control.querySelector('[data-link-search]');
			var value = parseLinkUrl(linkStoredUrl(control)).value;

			if (!value) return;

			var key = type + ':' + lang + ':' + value;

			if (ui.names[key]) {
				if (search) search.value = ui.names[key];
				if (ui.linkUrls[key]) showLinkSeoUrl(control, ui.linkUrls[key]);

				return;
			}

			var bucketKey = type + ':' + lang;

			if (!pending[bucketKey]) pending[bucketKey] = { type: type, lang: lang, ids: [] };

			if (pending[bucketKey].ids.indexOf(value) === -1) pending[bucketKey].ids.push(value);
		});

		Object.keys(pending).forEach(function (bucketKey) {
			var bucket = pending[bucketKey];

			fetch(CFG.urls.autocomplete + '&type=' + encodeURIComponent(bucket.type) + '&language_id=' + encodeURIComponent(bucket.lang) + '&ids=' + encodeURIComponent(bucket.ids.join(',')))
				.then(function (response) { return response.json(); })
				.then(function (items) {
					if (!items || !items.length) return;

					items.forEach(function (item) {
						var key = bucket.type + ':' + bucket.lang + ':' + item.id;

						ui.names[key] = item.name;
						ui.linkUrls[key] = item.url || '';
					});

					root.querySelectorAll('[data-link-control]').forEach(function (control) {
						var select = control.querySelector('[data-link-type]');
						var lang = Number(control.getAttribute('data-link-lang')) || ui.lang;

						if (!select || select.value !== bucket.type || lang !== bucket.lang || !document.body.contains(control)) return;

						var search = control.querySelector('[data-link-search]');
						var value = parseLinkUrl(linkStoredUrl(control)).value;
						var key = bucket.type + ':' + lang + ':' + value;

						// Never overwrite text the operator is typing in the search box.
						if (search && value && !search.value && ui.names[key]) {
							search.value = ui.names[key];
						}

						if (value && ui.linkUrls[key]) showLinkSeoUrl(control, ui.linkUrls[key]);
					});
				})
				.catch(function () {});
		});
	}

	/* ==================== rich text (language-safe) ==================== */

	function initRichText(textarea, block, field, lang) {
		if (typeof tinymce === 'undefined' || !textarea.id) return;

		var editor = null;
		var entry = null;

		var readEditor = function () {
			// A removed editor never writes: its content is gone with the
			// panel that owned it (this guard is what used to wipe languages).
			if (!editor || editor.removed) return null;

			return editor.getContent();
		};

		var flush = function () {
			var value = readEditor();

			if (value === null) return;
			if (getValue(field, block, lang) === value) return;

			setValue(block, field, value, lang);
			touch({ blockId: block.block_id });
			schedulePreview(block);

			ui.fieldTimers = ui.fieldTimers.filter(function (item) { return item !== entry; });
		};

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
			setup: function (initialized) {
				editor = initialized;

				initialized.on('change keyup undo redo setcontent', function () {
					trackTextEdit();

					if (entry) {
						clearTimeout(entry.timer);
						ui.fieldTimers = ui.fieldTimers.filter(function (item) { return item !== entry; });
					}

					// Debounced write into the language captured at bind time;
					// a panel re-render flushes it (same language) instead of
					// letting it fire against a removed editor.
					entry = trackFieldTimer(setTimeout(flush, 500), flush);
				});

				// One undo step per editing session: flush the last keystrokes,
				// then seal the snapshot taken on the first change.
				initialized.on('blur', function () {
					if (entry) {
						clearTimeout(entry.timer);
					flush();
					}

					commitTextEdit();
				});
			}
		});
	}

	/* ==================== navigator (structure tree) ==================== */

	function blockHint(block) {
		var code = block.code;
		var schema = CFG.schema[code] || {};
		var hintKey = schema.hint || (code === 'section' ? 'structure' : 'heading');
		var value = block.content && block.content[ui.lang] ? block.content[ui.lang][hintKey] : '';

		if (typeof value === 'string' && value !== '') {
			value = value.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();

			return value.length > 24 ? value.slice(0, 24) + '…' : value;
		}

		return '';
	}

	function navigatorRow(block) {
		var schema = CFG.schema[block.code] || { icon: 'square', name: block.code };
		var hint = blockHint(block);
		var hidden = Number(block.status) ? '' : ' opacity-50';

		return '<div class="lp-nav-row' + (Number(block.block_id) === Number(ui.selectedId) ? ' lp-nav-selected' : '') + hidden + '"' +
			' data-block-id="' + Number(block.block_id) + '"' +
			(isContainerBlock(block) ? ' data-lp-container="1"' : '') + '>' +
			'<span class="lp-nav-grip"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/></svg></span>' +
			'<span class="inline-flex items-center justify-center shrink-0 text-gray-400"><i data-lucide="' + esc(schema.icon || 'square') + '" class="w-3.5 h-3.5"></i></span>' +
			'<span class="lp-nav-label">' + esc(t(schema.name, block.code)) +
			(hint ? ' <span class="text-gray-400 font-normal">· ' + esc(hint) + '</span>' : '') + '</span>' +
			'<span class="lp-nav-actions">' +
			'<button type="button" data-nav-action="settings" title="' + esc(t('text_block_settings', 'Settings')) + '"><i data-lucide="settings-2" class="w-3 h-3"></i></button>' +
			'<button type="button" data-nav-action="duplicate" title="' + esc(t('text_duplicate', 'Duplicate')) + '"><i data-lucide="copy" class="w-3 h-3"></i></button>' +
			'<button type="button" class="lp-nav-danger" data-nav-action="remove" title="' + esc(t('text_remove', 'Remove')) + '"><i data-lucide="trash-2" class="w-3 h-3"></i></button>' +
			'</span></div>';
	}

	function renderNavigator() {
		var tree = $('lp-navigator-tree');
		if (!tree) return;

		var blocks = topLevel();
		var html = '';

		if (!blocks.length) {
			html = '<div class="text-gray-400 text-[11px] px-2 py-3 text-center">' + esc(t('text_no_blocks', 'The page is empty')) + '</div>';
		} else {
			blocks.forEach(function (block) {
				html += navigatorUnit(block);
			});
		}

		tree.innerHTML = '<div id="lp-nav-root" class="space-y-0.5">' + html + '</div>';

		lucide.createIcons({ nodes: [tree] });
		bindNavigator(tree);
		updateCanvasState();
	}

	/**
	 * One top-level unit: the block row plus (for a section) the drop lists of
	 * its columns. The unit is what dragging moves at the page level.
	 */
	function navigatorUnit(block) {
		var html = '<div class="lp-nav-item"' +
			' data-lp-container="' + (isContainerBlock(block) ? '1' : '0') + '"' +
			' data-block-id="' + Number(block.block_id) + '">' +
			navigatorRow(block);

		if (isContainerBlock(block)) {
			var columnLabel = t('text_column', 'Column %s');

			for (var slot = 0; slot < columnCountOf(block); slot++) {
				html += '<div class="lp-nav-slot">' +
					'<div class="lp-nav-col-title"><i data-lucide="columns-2" class="w-3 h-3"></i> ' + esc(columnLabel.replace('%s', String(slot + 1))) + '</div>' +
					'<div class="space-y-0.5" data-nav-slot="' + slot + '">';

				childrenOf(block.block_id, slot).forEach(function (child) {
					html += navigatorRow(child);
				});

				html += '</div></div>';
			}
		}

		return html + '</div>';
	}

	function bindNavigator(tree) {
		destroyNavigatorSortables();

		tree.querySelectorAll('.lp-nav-row').forEach(function (row) {
			row.addEventListener('click', function (event) {
				var action = event.target.closest('[data-nav-action]');
				var blockId = Number(row.getAttribute('data-block-id'));

				if (action) {
					event.stopPropagation();
					performAction(action.getAttribute('data-nav-action'), blockId);
					return;
				}

				selectBlock(blockId);
				postToIframe({ type: 'lp:select', blockId: blockId, scroll: true });
			});
		});

		if (typeof Sortable === 'undefined') return;

		// One shared group: rows move between the page level and any column.
		// Sections themselves stay at the page level - the column put-filter
		// refuses containers (Sortable needs the same group name on both
		// sides, otherwise cross-list drops are silently rejected).
		var NAV_GROUP = 'lp-nav';

		var rootList = tree.querySelector('#lp-nav-root');

		if (rootList) {
			ui.navSortables.push(new Sortable(rootList, {
				animation: 140,
				handle: '.lp-nav-grip',
				draggable: '.lp-nav-item',
				ghostClass: 'lp-nav-ghost',
				group: {
					name: NAV_GROUP,
					pull: true,
					put: true
				},
				onEnd: function () {
					applyTree(navigatorItems(tree));
				}
			}));
		}

		tree.querySelectorAll('[data-nav-slot]').forEach(function (list) {
			ui.navSortables.push(new Sortable(list, {
				animation: 140,
				handle: '.lp-nav-grip',
				draggable: '.lp-nav-row',
				ghostClass: 'lp-nav-ghost',
				group: {
					name: NAV_GROUP,
					pull: true,
					put: function (to, from, dragEl) {
						// Section units (.lp-nav-item) and section rows
						// (.lp-nav-row) both carry data-lp-container="1".
						return dragEl.getAttribute('data-lp-container') !== '1';
					}
				},
				onEnd: function () {
					applyTree(navigatorItems(tree));
				}
			}));
		});
	}

	function destroyNavigatorSortables() {
		(ui.navSortables || []).forEach(function (instance) {
			instance.destroy();
		});

		ui.navSortables = [];
	}

	/**
	 * The tree as the model expects it, read back from the navigator DOM
	 * after a drag (same shape the iframe reports).
	 */
	/**
	 * The tree as the model expects it, read back from the navigator DOM
	 * after a drag (same shape the iframe reports). Root children are units;
	 * a bare row may appear there after a cross-list drop.
	 */
	function navigatorItems(tree) {
		var items = [];
		var rootList = tree.querySelector('#lp-nav-root');

		if (!rootList) return items;

		Array.prototype.slice.call(rootList.children).forEach(function (unit, index) {
			var row = unit.classList && unit.classList.contains('lp-nav-row')
				? unit
				: unit.querySelector('.lp-nav-row');

			if (!row || !row.hasAttribute('data-block-id')) return;

			var id = Number(row.getAttribute('data-block-id'));

			items.push({ id: id, parent: 0, slot: 0, order: index });

			Array.prototype.slice.call(unit.querySelectorAll('[data-nav-slot]')).forEach(function (list) {
				var slot = Number(list.getAttribute('data-nav-slot')) || 0;

				Array.prototype.slice.call(list.querySelectorAll('.lp-nav-row')).forEach(function (childRow, order) {
					var childId = Number(childRow.getAttribute('data-block-id'));

					if (childId) {
						items.push({ id: childId, parent: id, slot: slot, order: order });
					}
				});
			});
		});

		return items;
	}

	/* ==================== canvas state ==================== */

	// Empty-state overlay, block counter and navigator badge.
	function updateCanvasState() {
		var empty = $('lp-empty');
		var count = $('lp-block-count');
		var navCount = $('lp-navigator-count');
		var total = state.blocks.length;

		if (empty) empty.classList.toggle('hidden', total > 0);
		if (count) count.textContent = String(total);
		if (navCount) navCount.textContent = String(total);
	}

	function initNavigator() {
		var button = $('lp-navigator-btn');
		var panel = $('lp-navigator');
		var close = $('lp-navigator-close');

		if (!button || !panel) return;

		var toggle = function (open) {
			ui.navigatorOpen = typeof open === 'boolean' ? open : !ui.navigatorOpen;

			panel.classList.toggle('lp-nav-open', ui.navigatorOpen);

			if (ui.navigatorOpen) {
				renderNavigator();
			}
		};

		button.addEventListener('click', function () { toggle(); });

		if (close) {
			close.addEventListener('click', function () { toggle(false); });
		}
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
			error: function () {
				toast(t('text_error_save', 'Could not open the image manager'), 'error');
			},
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

				// The standalone builder has no admin common.js, so Bootstrap
				// tooltips for the filemanager toolbar need wiring here (including
				// nodes added by its AJAX directory reloads).
				if ($.fn.tooltip) {
					$('#modal-image [data-toggle="tooltip"]').tooltip({ container: 'body' });

					$(document).off('mouseenter.lpFilemanager', '#modal-image [data-toggle="tooltip"]')
						.on('mouseenter.lpFilemanager', '#modal-image [data-toggle="tooltip"]', function () {
							$(this).tooltip({ container: 'body' }).tooltip('show');
						});
				}
			}
		});
	}

	/* ==================== page settings ==================== */

	// Page Settings live in the right panel whenever no block is selected
	// (renderProperties), not in a separate slide-over.
	function openSiteSettings() {
		selectBlock(0);
		ui.siteSettings = true;
		renderProperties();
		renderNavigator();
	}

	function openPageSettings() {
		selectBlock(0);
	}

	function updateSettingsMenu() {
		// The trigger always offers the *other* panel: Site -> Page, Page -> Site.
		var label = $('lp-settings-mode-label');
		var icon = $('lp-settings-mode-icon');
		var toggle = $('lp-site-settings-btn');
		var nextLabel = ui.siteSettings ? 'text_page_settings' : 'text_site_settings';
		var nextFallback = ui.siteSettings ? 'Page settings' : 'Site settings';
		var nextIcon = ui.siteSettings ? 'settings-2' : 'palette';

		if (label) label.textContent = t(nextLabel, nextFallback);

		if (icon) {
			icon.innerHTML = '<i data-lucide="' + nextIcon + '" class="w-3.5 h-3.5"></i>';

			if (window.lucide) lucide.createIcons({ nodes: [icon] });
		}

		if (toggle) {
			toggle.setAttribute('title', t(nextLabel, nextFallback));
			toggle.setAttribute('data-original-title', t(nextLabel, nextFallback));
		}

	}

	function siteSettingsHtml() {
		var schema = CFG.siteDesignSchema || {};
		var design = state.siteDesign || {};
		var html = '<p class="text-[11px] leading-relaxed text-gray-500 mb-3">' +
			esc(t('text_site_settings_hint', 'Styles shared by every landing page.')) + '</p>';

		Object.keys(schema).forEach(function (key) {
			var field = schema[key];
			var label = esc(t(field.label, key));
			var value = String(design[key] !== undefined ? design[key] : field.default);

			html += '<div class="lp-field"><label class="lp-field-label">' + label + '</label>';

			if (field.type === 'color') {
				html += '<div class="flex items-center gap-2">' +
					'<input type="color" class="w-9 h-9 p-0 border border-gray-200 rounded" data-site-color="' + esc(key) + '" value="' + esc(value) + '" />' +
					'<input type="text" class="lp-input font-mono text-[11px]" data-site="' + esc(key) + '" value="' + esc(value) + '" /></div>';
			} else {
				html += '<select class="lp-input" data-site="' + esc(key) + '">';

				Object.keys(field.options || {}).forEach(function (option) {
					html += '<option value="' + esc(option) + '"' + (value === option ? ' selected' : '') + '>' +
						esc(t(field.options[option], option)) + '</option>';
				});

				html += '</select>';
			}

			html += '</div>';
		});

		html += '<button type="button" id="lp-site-style-save" class="w-full mt-2 px-3 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded">' +
			esc(t('button_save_site_style', 'Save site style')) + '</button>';

		return html;
	}

	function bindSiteSettings(container) {
		container.querySelectorAll('[data-site]').forEach(function (input) {
			var key = input.getAttribute('data-site');
			var sync = function () {
				var value = input.value;

				state.siteDesign[key] = value;

				container.querySelectorAll('[data-site-color="' + key + '"]').forEach(function (picker) {
					picker.value = /^#[0-9a-f]{6}$/i.test(value) ? value : '#2563eb';
				});

				container.querySelectorAll('[data-site="' + key + '"]').forEach(function (field) {
					if (field !== input) field.value = value;
				});
			};

			input.addEventListener('input', sync);
			input.addEventListener('change', sync);
		});

		container.querySelectorAll('[data-site-color]').forEach(function (picker) {
			picker.addEventListener('input', function () {
				var key = picker.getAttribute('data-site-color');

				state.siteDesign[key] = picker.value;

				container.querySelectorAll('[data-site="' + key + '"]').forEach(function (field) {
					field.value = picker.value;
				});
			});
		});

		var save = container.querySelector('#lp-site-style-save');

		if (save) {
			save.addEventListener('click', function () {
				save.disabled = true;

				fetch(CFG.siteDesignUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(state.siteDesign)
				}).then(function (response) { return response.json(); }).then(function (json) {
					save.disabled = false;

					if (json.error) {
						toast(json.error);
						return;
					}

					if (json.site_design) state.siteDesign = json.site_design;

					toast(json.success || t('text_saved', 'Saved'));
					reloadIframe();
				}).catch(function () {
					save.disabled = false;
					toast(t('text_error_save', 'Could not save'));
				});
			});
		}
	}

	/**
	 * The published/draft select of the Page Settings panel, shared by the
	 * standalone page (data-page) and the owned information page
	 * (data-owner, disabled without modify catalog/information).
	 */
	function statusSelectHtml(attr, value) {
		return '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_status', 'Status')) + '</label>' +
			'<select class="lp-input" ' + attr + '>' +
			'<option value="1"' + (Number(value) ? ' selected' : '') + '>' + esc(t('text_status_published', 'Published')) + '</option>' +
			'<option value="0"' + (!Number(value) ? ' selected' : '') + '>' + esc(t('text_status_draft', 'Draft')) + '</option></select></div>';
	}

	/**
	 * The per-language SEO/meta group of the Page Settings panel, shared by
	 * the standalone page (data-desc) and the owned information page
	 * (data-owner-desc, read-only without modify catalog/information).
	 */
	function descriptionGroupHtml(attr, descriptions, disabled) {
		var language = languageById(ui.lang);
		var description = descriptions[Number(ui.lang)] || {};
		var dis = disabled ? ' disabled' : '';

		return '<div class="border border-gray-100 rounded p-2 space-y-2">' +
			'<div class="text-[11px] font-semibold text-gray-500 flex items-center gap-1">' +
			'<i data-lucide="languages" class="w-3 h-3"></i> ' + esc(language.name) +
			' <span class="text-[9px] text-gray-400">' + esc(String(language.code).toUpperCase()) + '</span></div>' +
			'<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_slug', 'SEO keyword')) + '</label>' +
			'<div class="flex items-center gap-1"><span class="text-gray-400 text-xs">/</span>' +
			'<input type="text" class="lp-input" ' + attr + '="' + Number(ui.lang) + '" data-key="slug" value="' + esc(description.slug || '') + '"' + dis + ' /></div></div>' +
			'<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_meta_title', 'Meta title')) + '</label>' +
			'<input type="text" class="lp-input" ' + attr + '="' + Number(ui.lang) + '" data-key="meta_title" value="' + esc(description.meta_title || '') + '"' + dis + ' /></div>' +
			'<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_meta_description', 'Meta description')) + '</label>' +
			'<textarea rows="2" class="lp-input" ' + attr + '="' + Number(ui.lang) + '" data-key="meta_description"' + dis + '>' + esc(description.meta_description || '') + '</textarea></div>' +
			'<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_meta_keyword', 'Meta keywords')) + '</label>' +
			'<input type="text" class="lp-input" ' + attr + '="' + Number(ui.lang) + '" data-key="meta_keyword" value="' + esc(description.meta_keyword || '') + '"' + dis + ' /></div>' +
			'</div>';
	}

	function settingsHtml() {
		var descriptions = state.page.descriptions || {};
		var html = '';

		// An owned landing is the composed body of its owner (information
		// page or blog post): its status, URL, meta tags and SEO keyword are
		// edited here, straight in the builder, instead of the owner module.
		if (CFG.ownerId) {
			html += statusSelectHtml('data-owner="status"' + (CFG.canEditOwner ? '' : ' disabled'), state.owner.status);
			html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_name', 'Name')) + '</label>' +
				'<input type="text" class="lp-input" data-owner-title value="' + esc(ownerTitle()) + '"' +
				(CFG.canEditOwnerTitles && CFG.urls.ownerNames ? '' : ' disabled') + ' /></div>';
			html += descriptionGroupHtml('data-owner-desc', state.owner.descriptions || {}, !CFG.canEditOwner);
		}

		if (!CFG.ownerId) {
			html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_name', 'Name')) + '</label>' +
				'<input type="text" class="lp-input" data-page="name" value="' + esc(state.page.name) + '" /></div>';

			html += descriptionGroupHtml('data-desc', descriptions, false);

			html += statusSelectHtml('data-page="status"', state.page.status);

			html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_chrome', 'Header / footer')) + '</label>' +
				'<select class="lp-input" data-page="chrome">' +
				'<option value="full"' + (state.page.chrome !== 'bare' ? ' selected' : '') + '>' + esc(t('entry_chrome_full', 'Standard theme header & footer')) + '</option>' +
				'<option value="bare"' + (state.page.chrome === 'bare' ? ' selected' : '') + '>' + esc(t('entry_chrome_bare', 'No navigation (bare page)')) + '</option></select></div>';

			html += '<div class="lp-field"><label class="flex items-center gap-2 text-xs"><input type="checkbox" data-page="is_home"' + (Number(state.page.is_home) ? ' checked' : '') + ' /> ' + esc(t('entry_is_home', 'Use as store home page')) + '</label></div>';
		}

		html += '<div class="lp-field"><label class="flex items-center gap-2 text-xs"><input type="checkbox" data-page="noindex"' + (Number(state.page.noindex) ? ' checked' : '') + ' /> ' + esc(t('entry_noindex', 'noindex (hide from search engines)')) + '</label></div>';

		if (!CFG.ownerId) {
			html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_date_publish', 'Publish from')) + '</label>' +
				'<input type="datetime-local" class="lp-input" data-page="date_publish" value="' + esc(state.page.date_publish || '') + '" /></div>';
			html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_date_unpublish', 'Publish until')) + '</label>' +
				'<input type="datetime-local" class="lp-input" data-page="date_unpublish" value="' + esc(state.page.date_unpublish || '') + '" /></div>';
		}

		html += '<div class="lp-field"><label class="lp-field-label">' + esc(t('entry_custom_css', 'Custom CSS')) + '</label>' +
			'<textarea rows="5" class="lp-input font-mono text-[11px]" data-page="custom_css">' + esc(state.page.custom_css || '') + '</textarea></div>';

		if (!CFG.ownerId) {
			html += '<div class="text-[11px] text-gray-400">' + esc(t('entry_viewed', 'Views')) + ': ' + Number(CFG.landing.viewed || 0) + '</div>';
		}

		return html;
	}

	function bindSettings(container) {
		var titleInput = container.querySelector('[data-owner-title]');

		if (titleInput) {
			titleInput.addEventListener('input', function () {
				if (String(ownerTitle()) === String(titleInput.value) && !ui.pendingOwnerTitle) return;

				markOwnerTitleDirty(titleInput.value);
			});
		}

		container.querySelectorAll('[data-page]').forEach(function (input) {
			var key = input.getAttribute('data-page');
			var refresh = ['status', 'chrome', 'is_home', 'noindex', 'custom_css'].indexOf(key) !== -1;

			var coalesce = !(input.type === 'checkbox' || input.tagName === 'SELECT');

			var handler = function () {
				var value = input.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value;

				if (String(state.page[key]) === String(value)) return;

				if (coalesce) {
					trackTextEdit();
				} else {
					pushHistory();
				}
				state.page[key] = value;

				if (key === 'status') {
					updateStatusBadge();
				}

				touch({ structural: refresh });
			};

			input.addEventListener(coalesce ? 'input' : 'change', handler);

			if (coalesce) {
				input.addEventListener('blur', commitTextEdit);
			}
		});

		bindDescriptionInputs(container, 'data-desc', state.page.descriptions);

		// Owned landing: status, URL and meta tags of the information page.
		// Single visible status — it also drives the top bar badge.
		container.querySelectorAll('[data-owner="status"]').forEach(function (input) {
			input.addEventListener('change', function () {
				var value = input.value === '1' ? 1 : 0;

				if (Number(state.owner.status) === value) return;

				pushHistory();
				state.owner.status = value;
				state.page.status = value;
				updateStatusBadge();
				touch();
			});
		});

		bindDescriptionInputs(container, 'data-owner-desc', state.owner.descriptions);
	}

	/**
	 * Per-language description inputs of the Page Settings panel (slug and
	 * meta fields). Writes go to the language named in the data attribute -
	 * the one the input was rendered for - never to the currently selected
	 * tab, and text edits coalesce into one undo step per focus session.
	 */
	function bindDescriptionInputs(container, attr, descriptions) {
		container.querySelectorAll('[' + attr + ']').forEach(function (input) {
			var languageId = Number(input.getAttribute(attr));
			var key = input.getAttribute('data-key');

			input.addEventListener('input', function () {
				if (!descriptions[languageId]) {
					descriptions[languageId] = {};
				}

				if (String(descriptions[languageId][key] || '') === String(input.value)) return;

				trackTextEdit();
				descriptions[languageId][key] = input.value;
				touch();
			});

			input.addEventListener('blur', commitTextEdit);
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

			var go = function () {
				// renderProperties() flushes pending edits into the language they
				// were typed in before the panel switches to the new one.
				ui.lang = languageId;

				tabs.querySelectorAll('.lp-lang-tab').forEach(function (button) {
					button.className = 'lp-lang-tab px-2 py-1 text-xs rounded text-gray-500 hover:text-gray-800 hover:bg-gray-100';
				});

				tab.className = 'lp-lang-tab px-2 py-1 text-xs rounded bg-blue-50 text-blue-700 font-medium';

				renderProperties();
				renderNavigator();

				reloadIframe();
			};

			// No autosave: switching the tab keeps the edits in memory, so an
			// explicit question replaces the silent background save.
			if (ui.dirty && !ui.saving) {
				if (window.confirm(t('text_confirm_unsaved', 'You have unsaved changes. Save them before switching?'))) {
					save(true, go);
				} else {
					go();
				}
			} else {
				go();
			}
		});
	}

	function sendViewport() {
		postToIframe({
			type: 'lp:viewport',
			viewport: ui.viewport,
			label: t('text_hidden_device', 'Hidden on this device')
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

			sendViewport();
		});
	}

	function publicPreviewBaseFor(languageId) {
		var bases = CFG.previewPublicBases || {};

		return bases[languageId] || CFG.previewPublicBase || ui.previewBase;
	}

	function updatePreviewLink() {
		var link = $('lp-preview-link');

		// The Preview tab is a clean storefront page, not another editor canvas.
		// A fresh URL also defeats browser caching of the preview HTML.
		if (link) link.href = publicPreviewBaseFor(ui.lang) + '&_=' + Date.now();
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

					Object.keys(CFG.previewPublicBases || {}).forEach(function (languageId) {
						CFG.previewPublicBases[languageId] = replace(CFG.previewPublicBases[languageId], json.token);
					});

					if (CFG.previewPublicBase) {
						CFG.previewPublicBase = replace(CFG.previewPublicBase, json.token);
					}

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
		state.owner = clone(CFG.owner && typeof CFG.owner === 'object' ? CFG.owner : { status: 0, descriptions: {} });

		if (!state.owner.descriptions || typeof state.owner.descriptions !== 'object' || Array.isArray(state.owner.descriptions)) {
			state.owner.descriptions = {};
		}

		initPalette();
		initLanguageTabs();
		initViewport();
		initTemplates();
		initNavigator();
		renderTemplates();
		renderProperties();
		renderNavigator();
		updateStatusBadge();
		updateSaveState();
		updatePreviewLink();

		var frame = $('lp-canvas-frame');

		if (frame) {
			frame.src = buildPreviewUrl('_=' + Date.now());
		}

		// Properties tabs (Content / Style / Advanced).
		var propsTabs = $('lp-props-tabs');

		if (propsTabs) {
			propsTabs.addEventListener('click', function (event) {
				var tab = event.target.closest('[data-props-tab]');

				if (!tab) return;

				ui.propsTab = tab.getAttribute('data-props-tab');
				renderProperties();
			});
		}

		// One-click section start: appends at the end of the page, like the
		// Elementor "+" between sections.
		var addSectionEnd = function () {
			var tops = topLevel();
			var afterId = tops.length ? Number(tops[tops.length - 1].block_id) || 0 : 0;

			addBlock('section', { parentId: 0, slot: 0, afterId: afterId });
		};

		if ($('lp-quickadd-section')) $('lp-quickadd-section').addEventListener('click', addSectionEnd);
		if ($('lp-empty-section')) $('lp-empty-section').addEventListener('click', addSectionEnd);

		if ($('lp-shortcuts-btn')) $('lp-shortcuts-btn').addEventListener('click', function () {
				$('lp-shortcuts').classList.remove('hidden');
		});

		if ($('lp-site-settings-btn')) {
			$('lp-site-settings-btn').addEventListener('click', function () {
				if (ui.siteSettings) {
					openPageSettings();
				} else {
					openSiteSettings();
				}
			});
		}

		$('lp-save-btn').addEventListener('click', function () { save(true); });
		$('lp-undo').addEventListener('click', undo);
		$('lp-redo').addEventListener('click', redo);
		$('lp-props-close').addEventListener('click', function () {
			if (ui.siteSettings || ui.selectedId) {
				openPageSettings();
			} else {
				openSiteSettings();
			}
		});

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

			if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable)) {
				return;
			}

			// Esc returns from a block to Page Settings, then to Site Settings.
			// Site Settings is the root panel and cannot be closed.
			if (event.key === 'Escape') {
				if (ui.selectedId) {
					selectBlock(0);
				} else if (!ui.siteSettings) {
					openSiteSettings();
				}

				return;
			}

			// Del removes the selected block (with the usual confirmation).
			if ((event.key === 'Delete' || event.key === 'Backspace') && ui.selectedId) {
				event.preventDefault();
				performAction('remove', ui.selectedId);
				return;
			}

			var meta = event.ctrlKey || event.metaKey;

			// event.code (not key) so shortcuts fire on any keyboard layout —
			// with event.key a Russian layout turns Ctrl+D into Ctrl+В.
			if (meta && event.code === 'KeyZ') {
				event.preventDefault();
				event.shiftKey ? redo() : undo();
			}

			if (meta && event.code === 'KeyS') {
				event.preventDefault();
				save(true);
			}

			if (meta && event.code === 'KeyD') {
				event.preventDefault();

				if (ui.selectedId) performAction('duplicate', ui.selectedId);
			}
		});

		// No autosave: the page is sent only by the explicit Save action
		// (button, Ctrl+S), by a structural change, or after the "save?"
		// confirmation. Leaving with unsaved changes asks via the native
		// dialog below.
		window.addEventListener('beforeunload', function (event) {
			if (!ui.dirty && !ui.saving && !ui.pendingOwnerTitle) return;

			event.preventDefault();
			event.returnValue = '';
		});

		lucide.createIcons();
	}

	document.addEventListener('DOMContentLoaded', init);
})();
