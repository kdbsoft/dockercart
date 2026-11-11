/**
 * Blog post autosave.
 *
 * The blog post form is the panel's one long-form description editor, so it is
 * the only form that opts into autosave. It opts in from its template:
 *
 *   <form … data-autosave="1" data-autosave-interval="60" data-autosave-*="…">
 *
 * and the template, the controller and this file are only wired up when the
 * switch in Extensions → Modules → DockerCart Blog ("Autosave article
 * descriptions") is on.
 *
 * Once a minute the module compares the serialized form with the last saved
 * state and, if it changed, re-posts the form to its own `action` URL — the exact
 * request the Save button sends. No controller has an "autosave" branch: a
 * successful save is a redirect (Response::redirect), a validation error renders
 * the form again with `.text-danger` messages, and that difference is all the
 * client needs.
 *
 * Rules:
 *   - only an existing article is saved (`route` must not end with `/add`, and
 *     the action must carry an `*_id` parameter) — autosaving the "new article"
 *     form would create a record per tick;
 *   - file inputs are never re-uploaded (they are stripped from the payload);
 *   - the payload is compared by hash, so an idle form is never re-posted;
 *   - a failed save backs off (1×, 2×, 4×, max 5× the interval) and shows the
 *     first validation message;
 *   - the operator always sees the outcome in a label inserted right before the
 *     form's Save button: "Unsaved changes" → "Saving..." → "Autosaved at 14:03",
 *     red "Not saved: <message>" when the panel refuses the save.
 *
 * @package DockerCart
 */
(function () {
	'use strict';

	var fallbackInterval = 60;
	var baselineDelay = 2500;
	var saveFlag = '_dc_autosave';

	var states = [];
	var period = fallbackInterval * 1000;

	/**
	 * Read a per-form option (`data-autosave-<name>`) with a fallback.
	 */
	function option(form, name, fallback) {
		var value = form.getAttribute('data-autosave-' + name);

		return value === null || value === '' ? fallback : value;
	}

	/**
	 * Does this form edit an existing record?
	 */
	function eligible(form) {
		var url;

		if (!form.action) {
			return false;
		}

		try {
			url = new URL(form.action, window.location.href);
		} catch (e) {
			return false;
		}

		if (url.origin !== window.location.origin) {
			return false;
		}

		var route = url.searchParams.get('route') || '';

		if (!route) {
			return false;
		}

		// Never create records: /add routes build a new entity on every POST.
		if (/\/add$/.test(route)) {
			return false;
		}

		// Edit mode always carries the record id in the action URL.
		var keys = url.searchParams.keys();
		var key;

		while (!(key = keys.next()).done) {
			if (key.value !== 'user_token' && /_id$/.test(key.value)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * FNV-1a over the sorted "name=value" list — cheap and stable.
	 */
	function hash(text) {
		var result = 0x811c9dc5;

		for (var i = 0; i < text.length; i++) {
			result ^= text.charCodeAt(i);
			result = (result + (result << 1) + (result << 4) + (result << 7) + (result << 8) + (result << 24)) >>> 0;
		}

		return result.toString(16);
	}

	/**
	 * Serialize the form without file inputs (a re-upload per minute is not
	 * wanted) and hash the result for change detection.
	 */
	function serialize(form) {
		var source = new FormData(form);
		var payload = new FormData();
		var pairs = [];

		source.forEach(function (value, key) {
			if (typeof value !== 'string') {
				return;
			}

			payload.append(key, value);
			pairs.push(key + '\u0000' + value);
		});

		payload.append(saveFlag, '1');

		pairs.sort();

		return { body: payload, hash: hash(pairs.join('\u0001')) };
	}

	/**
	 * Push every TinyMCE editor's content back into its textarea.
	 */
	function syncEditors() {
		if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
			try {
				window.tinymce.triggerSave();
			} catch (e) {
				// editor mid-teardown / fullscreen — retry on the next tick
			}
		}
	}

	/**
	 * "14:03" in the operator's own locale — the language file only owns the
	 * sentence around it, so a panel language and a browser locale disagreeing
	 * does not look broken.
	 */
	function nowLabel() {
		try {
			return new Date().toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
		} catch (e) {
			return new Date().toTimeString().slice(0, 5);
		}
	}

	/**
	 * Put a status label right before the page's Save button. The button lives
	 * outside the form (`<button type="submit" form="form-…">`), so it is found by
	 * its `form` attribute; that is where the operator looks after editing.
	 */
	function createStatusNode(form) {
		if (!form.id) {
			return null;
		}

		var button = document.querySelector('button[form="' + form.id + '"].btn-primary') ||
			document.querySelector('button[form="' + form.id + '"]');

		if (!button || !button.parentNode) {
			return null;
		}

		var node = document.createElement('span');

		node.className = 'dc-autosave-inline';
		node.setAttribute('role', 'status');
		node.setAttribute('aria-live', 'polite');

		button.parentNode.insertBefore(node, button);

		return node;
	}

	function stateFor(form) {
		for (var i = 0; i < states.length; i++) {
			if (states[i].form === form) {
				return states[i];
			}
		}

		return null;
	}

	function showStatus(state, text, kind) {
		if (!state.node) {
			return;
		}

		state.node.textContent = text;
		state.node.title = text;
		state.node.className = 'dc-autosave-inline dc-autosave-inline--' + kind + ' is-visible';
	}

	/**
	 * The operator touched the form: flag it and say so right away (the tick is up
	 * to a minute away, the label is the only hint that work is pending).
	 */
	function markChanged(state) {
		state.dirty = true;
		state.nextAt = 0;

		// Never overwrite an in-flight or failed status — the operator would lose
		// the validation message.
		if (!state.inFlight && state.failures === 0) {
			showStatus(state, state.labels.pending, 'pending');
		}
	}

	function markEditor(editor) {
		var element = editor && typeof editor.getElement === 'function' ? editor.getElement() : null;
		var form = element && element.closest ? element.closest('form') : null;
		var state = form ? stateFor(form) : null;

		if (state) {
			markChanged(state);
		}
	}

	function bindEditors() {
		if (!window.tinymce) {
			return;
		}

		var attach = function (editor) {
			if (!editor || editor.__dcAutosaveBound) {
				return;
			}

			editor.__dcAutosaveBound = true;

			['input', 'change', 'keyup', 'undo', 'redo', 'SetContent'].forEach(function (event) {
				editor.on(event, function () {
					markEditor(editor);
				});
			});
		};

		if (Array.isArray(window.tinymce.editors)) {
			window.tinymce.editors.forEach(attach);
		}

		if (typeof window.tinymce.on === 'function') {
			window.tinymce.on('AddEditor', function (event) {
				attach(event.editor);
			});
		}
	}

	/**
	 * The first validation message of a re-rendered form.
	 */
	function firstError(html) {
		if (!html) {
			return '';
		}

		var doc = new DOMParser().parseFromString(html, 'text/html');
		var nodes = doc.querySelectorAll('.text-danger, .alert-danger, .invalid-feedback, .dcx-form-error');

		for (var i = 0; i < nodes.length; i++) {
			var text = (nodes[i].textContent || '').replace(/\s+/g, ' ').trim();

			if (text) {
				return text.length > 160 ? text.slice(0, 157) + '...' : text;
			}
		}

		return '';
	}

	function succeed(state, payload) {
		state.lastSent = payload.hash;
		state.baseline = payload.hash;
		state.dirty = false;
		state.failures = 0;
		state.nextAt = 0;
		state.inFlight = false;

		showStatus(state, state.labels.saved.replace('%s', nowLabel()), 'saved');
	}

	function fail(state, message) {
		state.failures += 1;
		state.inFlight = false;
		state.nextAt = Date.now() + Math.min(period * Math.pow(2, state.failures), period * 5);

		showStatus(state, message ? state.labels.failed + ' ' + message : state.labels.failed, 'error');
	}

	function send(state, payload) {
		state.inFlight = true;

		showStatus(state, state.labels.saving, 'saving');

		fetch(state.form.action, {
			method: 'POST',
			body: payload.body,
			credentials: 'same-origin',
			redirect: 'manual',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (response) {
			// Saved: the controller redirected. `redirect: 'manual'` keeps the
			// browser from following it, so no extra page render happens.
			if (response.type === 'opaqueredirect' || response.status === 0 || response.redirected) {
				succeed(state, payload);

				return;
			}

			if (!response.ok) {
				fail(state, 'HTTP ' + response.status);

				return;
			}

			// 200: the form was rendered again with validation messages.
			return response.text().then(function (html) {
				var message = firstError(html);

				if (message) {
					fail(state, message);
				} else {
					succeed(state, payload);
				}
			});
		}).catch(function () {
			fail(state, '');
		});
	}

	function tick() {
		if (document.hidden) {
			return;
		}

		var now = Date.now();

		states.forEach(function (state) {
			if (!state.form.isConnected || state.inFlight || state.suspendedUntil > now || state.nextAt > now) {
				return;
			}

			syncEditors();

			var payload = serialize(state.form);

			if (state.baseline === null && !state.dirty) {
				state.baseline = payload.hash;

				return;
			}

			if (!state.dirty && payload.hash === state.baseline) {
				return;
			}

			if (payload.hash === state.lastSent) {
				state.dirty = false;
				state.baseline = payload.hash;

				return;
			}

			send(state, payload);
		});
	}

	function register(form) {
		var state = {
			form: form,
			node: createStatusNode(form),
			labels: {
				pending: option(form, 'pending', 'Unsaved changes'),
				saving: option(form, 'saving', 'Saving...'),
				saved: option(form, 'saved', 'Autosaved at %s'),
				failed: option(form, 'failed', 'Not saved')
			},
			baseline: null,
			lastSent: null,
			dirty: false,
			inFlight: false,
			failures: 0,
			nextAt: 0,
			suspendedUntil: 0
		};

		states.push(state);

		var mark = function () {
			markChanged(state);
		};

		form.addEventListener('input', mark, true);
		form.addEventListener('change', mark, true);

		// A real save is in progress — do not race it.
		form.addEventListener('submit', function () {
			state.suspendedUntil = Date.now() + 10000;
		}, true);

		return state;
	}

	function init() {
		var forms = document.querySelectorAll('form[data-autosave="1"]');
		var registered = [];
		var i;

		for (i = 0; i < forms.length; i++) {
			if (eligible(forms[i])) {
				registered.push(register(forms[i]));
			}
		}

		if (!registered.length) {
			return;
		}

		var interval = parseInt(option(registered[0].form, 'interval', fallbackInterval), 10);

		if (!isFinite(interval) || interval < 15) {
			interval = fallbackInterval;
		}

		period = interval * 1000;

		// Snapshot the baseline once the editors have finished bootstrapping, so the
		// HTML normalization TinyMCE does on init is not mistaken for an edit.
		window.setTimeout(function () {
			syncEditors();

			states.forEach(function (state) {
				if (state.baseline === null) {
					state.baseline = serialize(state.form).hash;
				}
			});
		}, baselineDelay);

		bindEditors();

		window.setInterval(tick, period);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
