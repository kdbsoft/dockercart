/**
 * DockerCart landing page builder bridge (iframe side).
 *
 * Loaded by catalog controller landing/page only when the request carries a
 * valid preview token and lp_builder=1, i.e. when the page is displayed inside
 * the admin builder's canvas. It makes the real storefront page editable:
 *
 *   - drag handle per section (SortableJS reorders the DOM, then the parent
 *     saves the new order),
 *   - hover toolbar (move up/down, duplicate, delete, settings),
 *   - click selects a section (links and buttons inside are neutralised),
 *   - palette drops from the admin shell insert a new section,
 *   - one block can be re-rendered in place after a property change,
 *   - the scroll offset survives the reloads the parent triggers.
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
	var sortable = null;
	var insertLine = null;

	/* ---------------- styles ---------------- */

	function injectStyles() {
		var style = document.createElement('style');
		style.textContent = [
			'html.lp-builder-mode { scroll-behavior: auto !important; }',
			'html.lp-builder-mode [data-landing-block] { position: relative; }',
			'html.lp-builder-mode [data-landing-block]::after { content: ""; position: absolute; inset: 0; pointer-events: none; border: 1px dashed transparent; z-index: 40; }',
			'html.lp-builder-mode [data-landing-block]:hover::after { border-color: rgba(59, 130, 246, .55); }',
			'html.lp-builder-mode [data-landing-block].lp-selected::after { border: 2px solid #3b82f6; }',
			'html.lp-builder-mode [data-landing-block][data-lp-hidden="1"] { opacity: .55; }',
			'html.lp-builder-mode a, html.lp-builder-mode button, html.lp-builder-mode input, html.lp-builder-mode select, html.lp-builder-mode textarea { cursor: default !important; }',
			'.lp-block-handle { position: absolute; top: 6px; left: 6px; z-index: 70; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; background: rgba(17, 24, 39, .82); color: #fff; cursor: grab !important; opacity: 0; transition: opacity .15s; }',
			'[data-landing-block]:hover > .lp-block-handle { opacity: 1; }',
			'.lp-block-handle:active { cursor: grabbing !important; }',
			'#lp-toolbar { position: fixed; z-index: 2147483000; display: none; align-items: center; gap: 2px; padding: 3px; border-radius: 8px; background: rgba(17, 24, 39, .94); color: #fff; font: 600 11px/1 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; box-shadow: 0 8px 22px rgba(0,0,0,.28); }',
			'#lp-toolbar.lp-open { display: flex; }',
			'#lp-toolbar .lp-toolbar-label { padding: 0 6px; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; font-size: 10px; color: #cbd5e1; }',
			'#lp-toolbar button { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border: 0; border-radius: 5px; background: transparent; color: #e5e7eb; cursor: pointer !important; }',
			'#lp-toolbar button:hover { background: rgba(255,255,255,.16); color: #fff; }',
			'#lp-toolbar button.lp-danger:hover { background: rgba(220, 38, 38, .9); }',
			'#lp-insert-line { position: fixed; z-index: 2147482999; height: 3px; background: #3b82f6; border-radius: 2px; display: none; pointer-events: none; box-shadow: 0 0 0 3px rgba(59,130,246,.25); }',
			'#lp-drop-hint { position: fixed; z-index: 2147482998; display: none; padding: 4px 10px; border-radius: 999px; background: rgba(59,130,246,.95); color: #fff; font: 600 11px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; pointer-events: none; }',
			'html.lp-builder-mode body { padding-bottom: 80px; }',
			'.lp-block-hint { position: absolute; inset: 10px; z-index: 44; display: flex; align-items: center; justify-content: center; border: 2px dashed rgba(148, 163, 184, .75); border-radius: 14px; pointer-events: none; text-align: center; padding: 10px; }',
			'.lp-block-hint > span { max-width: 80%; padding: 6px 14px; border-radius: 999px; background: rgba(255, 255, 255, .96); color: #334155; font: 600 12px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; box-shadow: 0 6px 18px rgba(15, 23, 42, .16); }'
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
	}

	function showToolbar(block) {
		if (!toolbar || !block) return;

		clearTimeout(hideTimer);

		toolbarBlock = block;

		var rect = block.getBoundingClientRect();

		toolbar.querySelector('[data-role="label"]').textContent = block.getAttribute('data-block-code') || 'block';
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

	/* ---------------- drag handles + sortable ---------------- */

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

			if (!hint || code === 'spacer') {
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
	}

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

	function initSortable() {
		if (typeof Sortable === 'undefined') return;

		sortable = new Sortable(root(), {
			animation: 160,
			handle: '.lp-block-handle',
			draggable: '[data-landing-block]',
			ghostClass: 'lp-sortable-ghost',
			onStart: function () {
				document.body.style.userSelect = 'none';
			},
			onEnd: function () {
				document.body.style.userSelect = '';

				var order = blocks().map(blockIdOf).filter(Boolean);

				post({ type: 'lp:move', order: order });
			}
		});

		var style = document.createElement('style');
		style.textContent = '.lp-sortable-ghost { opacity: .4; }';
		document.head.appendChild(style);
	}

	/* ---------------- palette drops ---------------- */

	function dropAfterBlock(event) {
		var target = event.target.closest ? event.target.closest('[data-landing-block]') : null;

		if (!target) {
			var all = blocks();

			return all.length ? blockIdOf(all[all.length - 1]) : 0;
		}

		var rect = target.getBoundingClientRect();
		var middle = rect.top + rect.height / 2;

		if (event.clientY <= middle) {
			var previous = target.previousElementSibling;

			while (previous && !previous.hasAttribute('data-landing-block')) {
				previous = previous.previousElementSibling;
			}

			return previous ? blockIdOf(previous) : 0;
		}

		return blockIdOf(target);
	}

	function positionInsertLine(event) {
		if (!insertLine) return;

		var target = event.target.closest ? event.target.closest('[data-landing-block]') : null;
		var rect;
		var y;

		if (!target) {
			var all = blocks();

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

	function initDrops() {
		document.addEventListener('dragover', function (event) {
			if (!hasBlockPayload(event)) return;

			event.preventDefault();
			event.dataTransfer.dropEffect = 'copy';
			positionInsertLine(event);
		});

		document.addEventListener('dragleave', function (event) {
			if (event.target === document.documentElement) {
				insertLine.style.display = 'none';
			}
		});

		document.addEventListener('drop', function (event) {
			if (!hasBlockPayload(event)) return;

			event.preventDefault();

			insertLine.style.display = 'none';

			var code = event.dataTransfer.getData('application/x-lp-block') || event.dataTransfer.getData('text/plain');

			if (!code) return;

			post({ type: 'lp:add', code: code, afterId: dropAfterBlock(event) });
		});
	}

	function hasBlockPayload(event) {
		if (!event.dataTransfer) return false;

		var types = Array.prototype.slice.call(event.dataTransfer.types || []);

		return types.indexOf('application/x-lp-block') !== -1;
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
			var data = event.data;

			if (!data || typeof data !== 'object' || typeof data.type !== 'string') {
				return;
			}

			if (data.type === 'lp:select') {
				currentBlock = null;
				selectBlock(Number(data.blockId) || 0, Boolean(data.scroll));
			}

			if (data.type === 'lp:refreshBlock') {
				replaceBlock(Number(data.blockId), data.url);
			}
		});
	}

	function replaceBlock(blockId, url) {
		var block = root().querySelector('[data-landing-block="' + Number(blockId) + '"]');

		if (!block || !url) return;

		fetch(url, { credentials: 'same-origin' })
			.then(function (response) { return response.text(); })
			.then(function (html) {
				if (!html) return;

				var template = document.createElement('div');
				template.innerHTML = html.trim();

				var replacement = template.firstElementChild;

				if (!replacement || !replacement.hasAttribute('data-landing-block')) return;

				block.replaceWith(replacement);
				attachHandles();
				markEmptyBlocks();

				if (sortable) {
					sortable.destroy();
				}

				initSortable();
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
		initSortable();
		initScroll();

		var onlyBlock = params.get('only_block');

		if (onlyBlock) {
			return;
		}

		post({ type: 'lp:ready', order: blocks().map(blockIdOf) });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
