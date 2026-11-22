/**
 * DockerCart landing page builder bridge (iframe side).
 *
 * Loaded by catalog controller landing/page only when the request carries a
 * valid preview token and lp_builder=1, i.e. when the page is displayed inside
 * the admin builder's canvas. It makes the real storefront page editable:
 *
 *   - drag handle per block (SortableJS): top-level sections reorder at the
 *     page root, widgets move between the columns of their section,
 *   - hover toolbar (move up/down, duplicate, delete, settings),
 *   - click selects a block (links and buttons inside are neutralised),
 *   - palette drops from the admin shell insert a new section (page level)
 *     or a new widget (into a section column or at page level),
 *   - one block can be re-rendered in place after a property change,
 *   - the parent's active device viewport is mirrored via lp:viewport so blocks
 *     hidden on that device are dimmed and badged but stay selectable,
 *   - the scroll offset survives the reloads the parent triggers.
 *
 * Tree layout: the page root (#lp-blocks) holds top-level blocks; a section
 * container (data-lp-container) holds columns ([data-lp-slot]); a column
 * holds widgets. Every structural change reports the full tree to the parent
 * (lp:moveTree), which owns the model and saves it.
 *
 * The parent (admin) is same-origin and speaks the lp:* protocol documented in
 * admin/view/javascript/dockercart_landing_builder.js.
 */
(function () {
	'use strict';

	var params = new URLSearchParams(window.location.search);

	if (params.get('lp_builder') !== '1') {
		return;
	}

	var SCROLL_KEY = 'lp_scroll:' + window.location.pathname;
	// Resolved lazily: this script is loaded in <head>, where document.body is null.
	var blocksRoot = null;
	var toolbar = null;
	var toolbarBlock = null;
	var hideTimer = null;
	var currentBlock = null;
	var sortables = [];
	var insertLine = null;
	var dropHint = null;
	var hoverColumn = null;
	var LABELS = {};
	var deviceViewport = 'desktop';
	var hiddenDeviceLabel = 'Hidden on this device';

	/* ---------------- styles ---------------- */

	function injectStyles() {
		var style = document.createElement('style');
		style.textContent = [
			'html.lp-builder-mode { scroll-behavior: auto !important; }',
			'html.lp-builder-mode [data-landing-block] { position: relative; }',
			'html.lp-builder-mode [data-landing-block]::after { content: ""; position: absolute; inset: 0; pointer-events: none; border: 1px dashed transparent; z-index: 40; }',
			'html.lp-builder-mode [data-landing-block]:hover::after { border-color: rgba(91, 126, 168, .55); }',
			'html.lp-builder-mode [data-landing-block].lp-selected::after { border: 2px solid #5b7ea8; }',
			'html.lp-builder-mode [data-landing-block][data-lp-hidden="1"] { opacity: .55; }',
			'html.lp-builder-mode [data-landing-block].lp-hidden-on-device { opacity: .5; }',
			'.lp-device-hidden-badge { position: absolute; top: 8px; right: 8px; z-index: 45; display: inline-flex; align-items: center; max-width: calc(100% - 16px); padding: 4px 9px; border-radius: 999px; background: rgba(15, 23, 42, .9); color: #fff; font: 600 10px/1.35 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; box-shadow: 0 4px 12px rgba(15, 23, 42, .25); pointer-events: none; }',
			'html.lp-builder-mode a, html.lp-builder-mode button, html.lp-builder-mode input, html.lp-builder-mode select, html.lp-builder-mode textarea { cursor: default !important; }',
			'.lp-block-handle { position: absolute; top: 6px; left: 6px; z-index: 70; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background: rgba(17, 24, 39, .82); color: #fff; cursor: grab !important; opacity: 0; transition: opacity .15s; }',
			'[data-landing-block]:hover > .lp-block-handle { opacity: 1; }',
			'.lp-block-handle:active { cursor: grabbing !important; }',
			'#lp-toolbar { position: fixed; z-index: 2147483000; display: none; align-items: center; gap: 2px; padding: 3px; border-radius: 8px; background: rgba(17, 24, 39, .94); color: #fff; font: 600 11px/1 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; box-shadow: 0 8px 22px rgba(0,0,0,.28); }',
			'#lp-toolbar.lp-open { display: flex; }',
			'#lp-toolbar .lp-toolbar-label { padding: 0 6px; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; font-size: 10px; color: #cbd5e1; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }',
			'#lp-toolbar button { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border: 0; border-radius: 5px; background: transparent; color: #e5e7eb; cursor: pointer !important; }',
			'#lp-toolbar button:hover { background: rgba(255,255,255,.16); color: #fff; }',
			'#lp-toolbar button.lp-danger:hover { background: rgba(220, 38, 38, .9); }',
			'#lp-insert-line { position: fixed; z-index: 2147482999; height: 3px; background: #5b7ea8; border-radius: 2px; display: none; pointer-events: none; box-shadow: 0 0 0 3px rgba(91,126,168,.25); }',
			'#lp-drop-hint { position: fixed; z-index: 2147482998; display: none; padding: 4px 10px; border-radius: 999px; background: rgba(91,126,168,.95); color: #fff; font: 600 11px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; pointer-events: none; }',
			'html.lp-builder-mode body { padding-bottom: 80px; }',
			'.lp-block-hint { position: absolute; inset: 10px; z-index: 44; display: flex; align-items: center; justify-content: center; border: 2px dashed rgba(148, 163, 184, .75); border-radius: 14px; pointer-events: none; text-align: center; padding: 10px; }',
			'.lp-block-hint > span { max-width: 80%; padding: 6px 14px; border-radius: 999px; background: rgba(255, 255, 255, .96); color: #334155; font: 600 12px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; box-shadow: 0 6px 18px rgba(15, 23, 42, .16); }',
			'html.lp-builder-mode [data-lp-slot] { position: relative; }',
			'html.lp-builder-mode [data-lp-slot].lp-slot-empty { min-height: 84px; }',
			'html.lp-builder-mode [data-lp-slot].lp-slot-hover { outline: 2px dashed rgba(91, 126, 168, .8); outline-offset: -2px; }',
			'.lp-slot-hint { position: absolute; inset: 6px; z-index: 44; display: flex; align-items: center; justify-content: center; border: 2px dashed rgba(148, 163, 184, .6); border-radius: 10px; pointer-events: none; margin: 4px 0; }',
			'.lp-slot-hint > span { padding: 4px 12px; border-radius: 999px; background: rgba(255, 255, 255, .96); color: #64748b; font: 600 11px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; box-shadow: 0 4px 12px rgba(15, 23, 42, .12); }',
			'.lp-sortable-ghost { opacity: .4; }'
		].join('\n');

		document.head.appendChild(style);
		document.documentElement.classList.add('lp-builder-mode');
	}

	/* ---------------- toolbar ---------------- */

	var ICONS = {
		up: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>',
		down: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>',
		copy: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>',
		trash: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
		settings: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
		grip: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/></svg>'
	};

	function buildToolbar() {
		toolbar = document.createElement('div');
		toolbar.id = 'lp-toolbar';
		toolbar.innerHTML =
			'<span class="lp-toolbar-label" data-role="label"></span>' +
			'<button type="button" data-lp-action="up" title="Move up">' + ICONS.up + '</button>' +
			'<button type="button" data-lp-action="down" title="Move down">' + ICONS.down + '</button>' +
			'<button type="button" data-lp-action="duplicate" title="Duplicate">' + ICONS.copy + '</button>' +
			'<button type="button" data-lp-action="settings" title="Settings">' + ICONS.settings + '</button>' +
			'<button type="button" class="lp-danger" data-lp-action="remove" title="Delete">' + ICONS.trash + '</button>';

		document.body.appendChild(toolbar);

		toolbar.addEventListener('mouseenter', function () {
			clearTimeout(hideTimer);
		});

		toolbar.addEventListener('mouseleave', function () {
			scheduleHide();
		});

		toolbar.addEventListener('click', function (event) {
			var button = event.target.closest('[data-lp-action]');

			if (!button || !toolbarBlock) return;

			event.preventDefault();
			event.stopPropagation();

			post({ type: 'lp:action', action: button.getAttribute('data-lp-action'), blockId: blockIdOf(toolbarBlock) });
		});

		insertLine = document.createElement('div');
		insertLine.id = 'lp-insert-line';
		document.body.appendChild(insertLine);

		dropHint = document.createElement('div');
		dropHint.id = 'lp-drop-hint';
		document.body.appendChild(dropHint);
	}

	function showToolbar(block) {
		if (!toolbar || !block) return;

		clearTimeout(hideTimer);

		toolbarBlock = block;

		var rect = block.getBoundingClientRect();

		toolbar.querySelector('[data-role="label"]').textContent = blockLabel(block);
		toolbar.classList.add('lp-open');

		var top = rect.top + 6;
		var right = Math.max(8, window.innerWidth - rect.right + 6);

		if (top < 58) {
			top = rect.top + 34;
		}

		toolbar.style.top = top + 'px';
		toolbar.style.right = right + 'px';
	}

	function scheduleHide() {
		clearTimeout(hideTimer);
		hideTimer = setTimeout(function () {
			if (toolbar) toolbar.classList.remove('lp-open');
			toolbarBlock = null;
		}, 250);
	}

	function blockLabel(block) {
		var code = block.getAttribute('data-block-code') || 'block';

		return LABELS[code] || code;
	}

	/* ---------------- block helpers ---------------- */

	function root() {
		if (!blocksRoot) {
			blocksRoot = document.getElementById('lp-blocks') || document.querySelector('main') || document.body;
		}

		return blocksRoot;
	}

	function blockIdOf(block) {
		return Number(block.getAttribute('data-landing-block')) || 0;
	}

	function blocks() {
		return Array.prototype.slice.call(root().querySelectorAll('[data-landing-block]'));
	}

	function topBlocks() {
		return Array.prototype.slice.call(root().querySelectorAll(':scope > [data-landing-block]'));
	}

	function columns() {
		return Array.prototype.slice.call(root().querySelectorAll('[data-lp-slot]'));
	}

	function columnWidgets(column) {
		return Array.prototype.slice.call(column.querySelectorAll(':scope > [data-landing-block]'));
	}

	function isContainer(block) {
		return block && block.hasAttribute('data-lp-container');
	}

	/** Builder feedback for a device: server shell sets data-lp-hide. */
	function hiddenOnDevice(block, device) {
		return (block.getAttribute('data-lp-hide') || '')
			.split(',')
			.some(function (item) { return item.trim() === device; });
	}

	/**
	 * Dim and badge blocks hidden on the active viewport. The block itself is
	 * never display:none here (it must stay selectable); descendants of an
	 * already hidden section are skipped so one toggle produces one badge.
	 */
	function syncDeviceHidden() {
		blocks().forEach(function (block) {
			var hidden = hiddenOnDevice(block, deviceViewport)
				&& !(block.parentElement && block.parentElement.closest('[data-landing-block].lp-hidden-on-device'));
			var badge = block.querySelector(':scope > .lp-device-hidden-badge');

			block.classList.toggle('lp-hidden-on-device', hidden);

			if (!hidden) {
				if (badge) badge.remove();

				return;
			}

			if (!badge) {
				badge = document.createElement('span');
				badge.className = 'lp-device-hidden-badge';
				block.appendChild(badge);
			}

			badge.textContent = hiddenDeviceLabel;
		});
	}

	function selectBlock(blockId, scrollIntoView) {
		blocks().forEach(function (block) {
			block.classList.toggle('lp-selected', blockIdOf(block) === Number(blockId));
		});

		if (!blockId) {
			if (toolbar) toolbar.classList.remove('lp-open');
			return;
		}

		var target = root().querySelector('[data-landing-block="' + Number(blockId) + '"]');

		if (!target) return;

		if (scrollIntoView) {
			var top = target.getBoundingClientRect().top + window.scrollY - 90;
			window.scrollTo({ top: top, behavior: 'auto' });
		}

		showToolbar(target);
	}

	/* ---------------- empty state hints ---------------- */

	var EMPTY_HINTS = {
		hero: 'Empty hero — select it and add a heading, image or buttons',
		text: 'Empty text block — add content in the right panel',
		cta: 'Empty call to action — add a heading and a button',
		products: 'No products selected — pick a source in the right panel',
		products_carousel: 'No products selected — pick a source in the right panel',
		categories: 'No categories selected — pick categories in the right panel',
		image: 'Empty image block — choose an image',
		image_text: 'Empty image + text block — add content',
		banner: 'No banner selected — choose one in the right panel',
		html: 'Empty HTML block — paste your markup'
	};

	function markEmptyBlocks() {
		blocks().forEach(function (block) {
			var code = block.getAttribute('data-block-code') || '';
			var hint = EMPTY_HINTS[code];
			var existing = block.querySelector(':scope > .lp-block-hint');

			if (!hint || code === 'spacer' || isContainer(block)) {
				if (existing) existing.remove();
				return;
			}

			var body = block.querySelector('.lp-block__body') || block;
			var text = (body.textContent || '').replace(/\s+/g, ' ').trim();
			var media = body.querySelector('img, video, iframe, picture, svg, .dc-product-item');
			var empty = text === '' && !media;

			if (!empty) {
				if (existing) existing.remove();
				return;
			}

			if (!existing) {
				existing = document.createElement('div');
				existing.className = 'lp-block-hint';
				existing.appendChild(document.createElement('span'));
				block.appendChild(existing);
			}

			existing.firstChild.textContent = hint;
		});

		// Columns without widgets show a drop hint (sections are empty frames
		// until something is dragged into them). The marker class gives the
		// empty slot a min-height: a zero-height column is invisible and can
		// never be a drop target.
		columns().forEach(function (column) {
			var existing = column.querySelector(':scope > .lp-slot-hint');
			var empty = columnWidgets(column).length === 0;

			if (!empty) {
				if (existing) existing.remove();
				column.classList.remove('lp-slot-empty');
				return;
			}

			if (!existing) {
				existing = document.createElement('div');
				existing.className = 'lp-slot-hint';
				existing.appendChild(document.createElement('span'));
				column.appendChild(existing);
			}

			if (LABELS._drop) {
				existing.firstChild.textContent = LABELS._drop;
			}

			column.classList.add('lp-slot-empty');
		});
	}

	/* ---------------- drag handles + sortable ---------------- */

	function attachHandles() {
		blocks().forEach(function (block) {
			if (block.querySelector(':scope > .lp-block-handle')) return;

			var handle = document.createElement('span');
			handle.className = 'lp-block-handle';
			handle.innerHTML = ICONS.grip;
			handle.title = 'Drag';
			block.appendChild(handle);
		});
	}

	function destroySortables() {
		sortables.forEach(function (instance) {
			instance.destroy();
		});

		sortables = [];
	}

	function initSortables() {
		destroySortables();

		if (typeof Sortable === 'undefined') return;

		// Page root: sections and loose widgets reorder here, and widgets may
		// be dragged OUT of a column onto the page level (put accepts anyone,
		// the section filter only guards the column lists below). Root and
		// columns share one group: with different names Sortable silently
		// refuses every cross-list drop.
		sortables.push(new Sortable(root(), {
			animation: 160,
			handle: '.lp-block-handle',
			draggable: '[data-landing-block]',
			ghostClass: 'lp-sortable-ghost',
			group: { name: 'lp-canvas', pull: true, put: true },
			onStart: function () {
				document.body.style.userSelect = 'none';
			},
			onEnd: reportStructure
		}));

		// Columns: widgets move between the columns of a section (and out to
		// the page level, same group as the root). Sections are never
		// accepted into a column (they are page-level only).
		columns().forEach(function (column) {
			sortables.push(new Sortable(column, {
				animation: 160,
				handle: '.lp-block-handle',
				draggable: '[data-landing-block]',
				ghostClass: 'lp-sortable-ghost',
				group: {
					name: 'lp-canvas',
					pull: true,
					put: function (to, from, dragEl) {
						return !isContainer(dragEl);
					}
				},
				onStart: function () {
					document.body.style.userSelect = 'none';
				},
				onEnd: reportStructure
			}));
		});
	}

	/**
	 * The full tree, as the parent's model expects it:
	 *   [{id, parent: 0, slot: 0, order}, {id, parent: <section id>, slot, order}, …]
	 */
	function structureItems() {
		var items = [];

		topBlocks().forEach(function (block, index) {
			items.push({ id: blockIdOf(block), parent: 0, slot: 0, order: index });

			if (!isContainer(block)) return;

			block.querySelectorAll('[data-lp-slot]').forEach(function (column) {
				var slot = Number(column.getAttribute('data-lp-slot')) || 0;

				columnWidgets(column).forEach(function (widget, order) {
					items.push({ id: blockIdOf(widget), parent: blockIdOf(block), slot: slot, order: order });
				});
			});
		});

		return items;
	}

	function reportStructure() {
		document.body.style.userSelect = '';
		post({ type: 'lp:moveTree', items: structureItems() });
	}

	/* ---------------- palette drops ---------------- */

	function payloadType(event) {
		if (!event.dataTransfer) return '';

		var types = Array.prototype.slice.call(event.dataTransfer.types || []);

		if (types.indexOf('application/x-lp-section') !== -1) return 'section';
		if (types.indexOf('application/x-lp-block') !== -1) return 'widget';

		return '';
	}

	function columnUnder(event) {
		var target = event.target;

		if (!target || !target.closest) return null;

		return target.closest('[data-lp-slot]');
	}

	/**
	 * The top-level block under the pointer. A pointer over a widget inside a
	 * column belongs to the column path, not the page path, so the walk
	 * climbs out of anything nested deeper than the page root.
	 */
	function topLevelTarget(event) {
		var target = event.target.closest ? event.target.closest('[data-landing-block]') : null;

		while (target && target.parentElement !== root()) {
			target = target.parentElement ? target.parentElement.closest('[data-landing-block]') : null;
		}

		return target;
	}

	/**
	 * The direct widget of `column` under the pointer (its own child, not a
	 * deeper descendant).
	 */
	function columnTarget(column, event) {
		var target = event.target.closest ? event.target.closest('[data-landing-block]') : null;

		while (target && target.parentElement !== column) {
			target = target.parentElement ? target.parentElement.closest('[data-landing-block]') : null;
		}

		return target;
	}

	function previousBlock(block) {
		var previous = block.previousElementSibling;

		while (previous && !previous.hasAttribute('data-landing-block')) {
			previous = previous.previousElementSibling;
		}

		return previous;
	}

	/**
	 * The block id an insert lands after, judged by the pointer position
	 * against the target's middle; 0 inserts before the first block.
	 */
	function insertAfter(target, event) {
		if (!target) {
			return 0;
		}

		var rect = target.getBoundingClientRect();

		if (event.clientY <= rect.top + rect.height / 2) {
			var previous = previousBlock(target);

			return previous ? blockIdOf(previous) : 0;
		}

		return blockIdOf(target);
	}

	function dropAfterTopLevel(event) {
		var target = topLevelTarget(event);

		if (!target) {
			var all = topBlocks();

			return all.length ? blockIdOf(all[all.length - 1]) : 0;
		}

		return insertAfter(target, event);
	}

	function dropInColumn(column, event) {
		var section = column.closest('[data-landing-block]');

		return {
			parentId: section ? blockIdOf(section) : 0,
			slot: Number(column.getAttribute('data-lp-slot')) || 0,
			afterId: insertAfter(columnTarget(column, event), event)
		};
	}

	function positionInsertLineTop(event) {
		if (!insertLine) return;

		var target = topLevelTarget(event);

		var rect;
		var y;

		if (!target) {
			var all = topBlocks();

			if (!all.length) {
				insertLine.style.display = 'none';
				return;
			}

			rect = all[all.length - 1].getBoundingClientRect();
			y = rect.bottom;
		} else {
			rect = target.getBoundingClientRect();
			y = event.clientY <= rect.top + rect.height / 2 ? rect.top : rect.bottom;
		}

		insertLine.style.display = 'block';
		insertLine.style.top = y + 'px';
		insertLine.style.left = rect.left + 'px';
		insertLine.style.width = rect.width + 'px';
	}

	function positionInsertLineColumn(column, event) {
		if (!insertLine) return;

		var rect = column.getBoundingClientRect();
		var y = rect.top + 8;

		var target = columnTarget(column, event);

		if (target) {
			var targetRect = target.getBoundingClientRect();
			y = event.clientY <= targetRect.top + targetRect.height / 2 ? targetRect.top : targetRect.bottom;
		}

		insertLine.style.display = 'block';
		insertLine.style.top = y + 'px';
		insertLine.style.left = rect.left + 'px';
		insertLine.style.width = rect.width + 'px';
	}

	function clearDropHighlights() {
		if (hoverColumn) {
			hoverColumn.classList.remove('lp-slot-hover');
			hoverColumn = null;
		}

		if (insertLine) insertLine.style.display = 'none';
	}

	function initDrops() {
		document.addEventListener('dragover', function (event) {
			var type = payloadType(event);

			if (!type) return;

			event.preventDefault();
			event.dataTransfer.dropEffect = 'copy';

			var column = (type === 'widget') ? columnUnder(event) : null;

			if (hoverColumn && hoverColumn !== column) {
				hoverColumn.classList.remove('lp-slot-hover');
				hoverColumn = null;
			}

			if (column) {
				column.classList.add('lp-slot-hover');
				hoverColumn = column;
				positionInsertLineColumn(column, event);
			} else {
				positionInsertLineTop(event);
			}
		});

		document.addEventListener('dragleave', function (event) {
			if (event.target === document.documentElement) {
				clearDropHighlights();
			}
		});

		document.addEventListener('drop', function (event) {
			var type = payloadType(event);

			if (!type) return;

			event.preventDefault();

			var column = (type === 'widget') ? columnUnder(event) : null;
			var code = event.dataTransfer.getData('application/x-lp-' + (type === 'section' ? 'section' : 'block')) || event.dataTransfer.getData('text/plain');

			clearDropHighlights();

			if (!code) return;

			if (column) {
				var target = dropInColumn(column, event);

				post({ type: 'lp:add', code: code, parentId: target.parentId, slot: target.slot, afterId: target.afterId });
			} else {
				post({ type: 'lp:add', code: code, parentId: 0, slot: 0, afterId: dropAfterTopLevel(event) });
			}
		});
	}

	/* ---------------- selection / neutralised interaction ---------------- */

	function initSelection() {
		document.addEventListener('click', function (event) {
			var block = event.target.closest ? event.target.closest('[data-landing-block]') : null;

			if (block) {
				event.preventDefault();
				event.stopPropagation();
				selectBlock(blockIdOf(block), false);
				post({ type: 'lp:selected', blockId: blockIdOf(block) });
				return;
			}

			if (!event.target.closest('#lp-toolbar')) {
				selectBlock(0, false);
				post({ type: 'lp:selected', blockId: 0 });
			}
		}, true);

		document.addEventListener('submit', function (event) {
			event.preventDefault();
		}, true);

		document.addEventListener('mouseover', function (event) {
			var block = event.target.closest ? event.target.closest('[data-landing-block]') : null;

			if (block) {
				currentBlock = block;
				showToolbar(block);
				return;
			}

			if (event.target.closest && event.target.closest('#lp-toolbar')) {
				clearTimeout(hideTimer);
				return;
			}

			if (currentBlock) {
				currentBlock = null;
				scheduleHide();
			}
		});
	}

	/* ---------------- messages from the parent ---------------- */

	function initMessages() {
		window.addEventListener('message', function (event) {
			// Only the admin shell drives the canvas: anything embedded in the
			// page (an html block iframe, a video embed) must not be able to
			// post lp:replace/lp:select into the builder.
			if (event.source !== window.parent) {
				return;
			}

			var data = event.data;

			if (!data || typeof data !== 'object' || typeof data.type !== 'string') {
				return;
			}

			if (data.type === 'lp:select') {
				currentBlock = null;
				selectBlock(Number(data.blockId) || 0, Boolean(data.scroll));
			}

			if (data.type === 'lp:labels') {
				LABELS = data.labels || {};
				markEmptyBlocks();
			}

			if (data.type === 'lp:viewport') {
				deviceViewport = typeof data.viewport === 'string' ? data.viewport : 'desktop';
				hiddenDeviceLabel = typeof data.label === 'string' && data.label !== '' ? data.label : hiddenDeviceLabel;
				syncDeviceHidden();
			}

			if (data.type === 'lp:refreshBlock') {
				replaceBlock(Number(data.blockId), data.url);
			}

			if (data.type === 'lp:replace') {
				replaceNode(Number(data.blockId), data.html);
			}
		});
	}

	/**
	 * Swaps one block node for fresh markup (server preview or re-render),
	 * keeping the drag handles, empty-state hints and sortables alive.
	 *
	 * @return {boolean} false when there is nothing to swap.
	 */
	function replaceNode(blockId, html) {
		var block = root().querySelector('[data-landing-block="' + Number(blockId) + '"]');

		if (!block || !html) return false;

		var template = document.createElement('div');
		template.innerHTML = String(html).trim();

		var replacement = template.firstElementChild;

		if (!replacement || !replacement.hasAttribute('data-landing-block')) return false;

		var selected = block.classList.contains('lp-selected');

		block.replaceWith(replacement);
		attachHandles();
		markEmptyBlocks();
		syncDeviceHidden();
		initSortables();

		if (selected) {
			replacement.classList.add('lp-selected');
			showToolbar(replacement);
		}

		return true;
	}

	function replaceBlock(blockId, url) {
		if (!url) return;

		fetch(url, { credentials: 'same-origin' })
			.then(function (response) { return response.text(); })
			.then(function (html) {
				replaceNode(blockId, html);
			})
			.catch(function () {
				// keep the old markup when the refresh fails
			});
	}

	/* ---------------- scroll persistence ---------------- */

	function initScroll() {
		var saved = null;

		try {
			saved = window.sessionStorage.getItem(SCROLL_KEY);
		} catch (e) {
			saved = null;
		}

		if (saved !== null) {
			window.scrollTo(0, parseInt(saved, 10) || 0);
		}

		var ticking = false;

		window.addEventListener('scroll', function () {
			if (ticking) return;

			ticking = true;

			window.requestAnimationFrame(function () {
				try {
					window.sessionStorage.setItem(SCROLL_KEY, String(window.scrollY));
				} catch (e) {
					// ignore quota errors
				}

				ticking = false;
			});
		});
	}

	/* ---------------- keyboard: forward builder shortcuts ---------------- */

	// The parent shell owns the model, so canvas keystrokes are neutralised
	// here and forwarded as combos (lp:key). event.code keeps them working
	// on any keyboard layout; editable targets keep their native behaviour.
	function initKeys() {
		document.addEventListener('keydown', function (event) {
			var target = event.target;

			if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable)) {
				return;
			}

			var meta = event.ctrlKey || event.metaKey;
			var combo = '';

			if (meta && event.code === 'KeyS') {
				combo = 'save';
			} else if (meta && event.code === 'KeyZ') {
				combo = event.shiftKey ? 'redo' : 'undo';
			} else if (meta && event.code === 'KeyD') {
				combo = 'duplicate';
			} else if (event.key === 'Delete' || event.key === 'Backspace') {
				combo = 'remove';
			} else if (event.key === 'Escape') {
				combo = 'escape';
			}

			if (!combo) return;

			event.preventDefault();
			post({ type: 'lp:key', combo: combo });
		});
	}

	/* ---------------- post ---------------- */

	function post(message) {
		try {
			window.parent.postMessage(message, '*');
		} catch (error) {
			// parent unavailable
		}
	}

	/* ---------------- boot ---------------- */

	function boot() {
		if (!root()) {
			return;
		}

		injectStyles();
		buildToolbar();
		initSelection();
		initMessages();
		initDrops();
		attachHandles();
		markEmptyBlocks();
		initSortables();
		initScroll();
		initKeys();

		var onlyBlock = params.get('only_block');

		if (onlyBlock) {
			return;
		}

		post({ type: 'lp:ready', items: structureItems() });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
