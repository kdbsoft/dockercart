/**
 * Advanced Description - lightweight section editor.
 *
 * This is deliberately not Page Builder: a flat list of six descriptive
 * section types, shared structure, per-language text, and six starting
 * templates. State lives in one editor document; a hidden input serialises
 * it for the ordinary entity-form POST. The server normalises it through
 * Dockercart\Description\Sections before anything is stored.
 */
(function () {
	'use strict';

	function initEditor(root) {
		var config = JSON.parse(root.getAttribute('data-config') || '{}');
		var labels = config.labels || {};
		var languages = config.languages || [];
		var state = JSON.parse(root.getAttribute('data-document') || '{"sections":[]}');
		var activeLanguage = parseInt(root.getAttribute('data-active-language'), 10) || (languages[0] ? languages[0].id : 0);
		var modeToggle = root.querySelector('[data-description-mode-toggle]');
		var modeLabel = root.querySelector('[data-description-mode-label]');
		var modeInput = root.querySelector('input[name="use_advanced_description"]');
		var workspace = root.querySelector('[data-description-workspace]');
		var sectionsRoot = root.querySelector('[data-description-sections]');
		var hidden = root.querySelector('input[name="advanced_description"]');
		var templateCards = root.querySelector('[data-description-template-cards]');
		var addToggle = root.querySelector('[data-description-add-toggle]');
		var addPanel = root.querySelector('[data-description-add-panel]');
		var addCards = root.querySelector('[data-description-add-cards]');
		var imageSequence = 0;

		function t(key, fallback) {
			return labels[key] || fallback || key;
		}

		function esc(value) {
			return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
				return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
			});
		}

		function get(path) {
			return path.split('.').reduce(function (value, key) {
				return value == null ? undefined : value[key];
			}, state);
		}

		function set(path, value) {
			var keys = path.split('.');
			var target = state;

			for (var index = 0; index < keys.length - 1; index++) {
				var key = keys[index];
				var nextIsIndex = /^\d+$/.test(keys[index + 1]);
				if (target[key] == null) target[key] = nextIsIndex ? [] : {};
				target = target[key];
			}
			target[keys[keys.length - 1]] = value;
			serialize();
		}

		function serialize() {
			hidden.value = JSON.stringify(state);
			updateSectionChoiceState();
		}

		function updateSectionChoiceState() {
			var atLimit = (state.sections || []).length >= (config.max_sections || 10);

			addCards.querySelectorAll('[data-description-add-type]').forEach(function (button) {
				button.classList.toggle('is-disabled', atLimit);
				button.setAttribute('aria-disabled', atLimit ? 'true' : 'false');
			});
		}

		function ensureSectionShape(section) {
			section.sid = section.sid || ('s' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6));
			section.settings = section.settings || {};
			section.content = section.content || {};
			section.rows = section.rows || [];

			languages.forEach(function (language) {
				if (!section.content[language.id]) section.content[language.id] = {};

				section.rows.forEach(function (row) {
					if (!row.content) row.content = {};
					if (!row.content[language.id]) row.content[language.id] = {};
				});
			});
		}

		function sectionDefaults(type) {
			var definition = (config.types || {})[type] || { fields: [] };

			return definition.fields.reduce(function (section, field) {
				if (field.type === 'repeater') {
					section.rows = [];
				} else if (field.localized) {
					languages.forEach(function (language) {
						if (!section.content[language.id]) section.content[language.id] = {};
						section.content[language.id][field.key] = field.default || '';
					});
				} else {
					section.settings[field.key] = field.default || '';
				}
				return section;
			}, { settings: {}, content: {} });
		}

		function makeSection(type) {
			var section = sectionDefaults(type);
			section.type = type;
			ensureSectionShape(section);
			return section;
		}

		function removeRichText(id) {
			if (window.tinymce && tinymce.get(id)) tinymce.get(id).remove();
		}

		function render() {
			removeAllRichText();
			sectionsRoot.innerHTML = '';

			if (!state.sections || !state.sections.length) {
				var empty = document.createElement('div');
				empty.className = 'dc-ad-empty';
				empty.setAttribute('data-description-empty', '');
				empty.textContent = t('text_empty_sections', 'Choose a template or add a section');
				sectionsRoot.appendChild(empty);
			} else {
				state.sections.forEach(function (section, index) {
					ensureSectionShape(section);
					sectionsRoot.insertAdjacentHTML('beforeend', renderSection(section, index));
				});
			}

			serialize();

			if (window.lucide) lucide.createIcons();
			if (window.jQuery) jQuery(sectionsRoot).find('[data-toggle="tooltip"]').tooltip();
		}

		function removeAllRichText() {
			sectionsRoot.querySelectorAll('textarea[data-description-richtext]').forEach(function (textarea) {
				removeRichText(textarea.id);
			});
		}

		function fieldValue(field, section, row, rowIndex) {
			if (field.type === 'repeater') return '';
			if (row) {
				return field.localized ? (row.content[activeLanguage] || {})[field.key] : row[field.key];
			}
			return field.localized ? (section.content[activeLanguage] || {})[field.key] : section.settings[field.key];
		}

		function fieldPath(field, sectionIndex, row, rowIndex) {
			if (row) {
				return 'sections.' + sectionIndex + '.rows.' + rowIndex + (field.localized ? '.content.' + activeLanguage : '') + '.' + field.key;
			}
			return 'sections.' + sectionIndex + (field.localized ? '.content.' + activeLanguage : '.settings') + '.' + field.key;
		}

		function inputId(sectionIndex, rowIndex, key) {
			return 'dc-desc-' + sectionIndex + '-' + (rowIndex == null ? 's' : 'r' + rowIndex) + '-' + key;
		}

		function renderField(field, section, sectionIndex, row, rowIndex) {
			var value = fieldValue(field, section, row, rowIndex);
			var path = fieldPath(field, sectionIndex, row, rowIndex);
			var id = inputId(sectionIndex, rowIndex, field.key);
			var control = '';

			if (field.type === 'select' && field.key === 'columns') {
				control = '<div class="dc-ad-columns">';

				Object.keys(field.options || {}).forEach(function (option) {
					var optionId = id + '-' + option;
					control += '<label class="dc-ad-columns__option" for="' + esc(optionId) + '">' +
						'<input type="radio" id="' + esc(optionId) + '" name="' + esc(id) + '" class="dc-ad-columns__input" value="' + esc(option) + '" data-description-path="' + path + '"' + (String(value) === option ? ' checked' : '') + ' />' +
						'<span class="dc-ad-columns__text">' + esc((field.options || {})[option]) + '</span></label>';
				});

				control += '</div>';
				return '<fieldset class="dc-ad-field dc-ad-columns-field"><legend>' + esc(field.label || field.key) + '</legend>' + control + '</fieldset>';
			}

			if (field.type === 'select') {
				control = '<select id="' + id + '" class="form-control" data-description-path="' + path + '">';
				Object.keys(field.options || {}).forEach(function (option) {
					control += '<option value="' + esc(option) + '"' + (String(value) === option ? ' selected' : '') + '>' + esc((field.options || {})[option]) + '</option>';
				});
				control += '</select>';
			} else if (field.type === 'richtext') {
				control = '<textarea id="' + id + '" class="form-control" rows="6" data-description-richtext data-description-path="' + path + '">' + esc(value || '') + '</textarea>';
			} else if (field.type === 'textarea') {
				control = '<textarea id="' + id + '" class="form-control" rows="3" data-description-path="' + path + '">' + esc(value || '') + '</textarea>';
			} else if (field.type === 'image') {
				control = renderImage(value || '', path, id);
			} else {
				control = '<input type="text" id="' + id + '" class="form-control" value="' + esc(value || '') + '" data-description-path="' + path + '" />';
			}

			return '<div class="dc-ad-field"><label for="' + id + '">' + esc(field.label || field.key) + '</label>' + control + '</div>';
		}

		function renderImage(value, path, id) {
			imageSequence++;
			var inputId = id + '-image-' + imageSequence;
			var thumbId = id + '-thumb-' + imageSequence;
			var preview = value ? '<img src="' + esc(value) + '" alt=""  />' : '<span class="text-xs text-gray-400">—</span>';

			return '<div class="dc-ad-image" data-description-image-wrap>' +
				'<input type="hidden" id="' + inputId + '" value="' + esc(value) + '" data-description-image-input data-description-path="' + path + '" />' +
				'<span id="' + thumbId + '" class="dc-ad-image__preview" data-description-image-thumb>' + preview + '</span>' +
				'<button type="button" class="btn btn-default btn-xs" data-description-image-select data-target="' + inputId + '" data-thumb="' + thumbId + '">' + esc(t('text_select_image', 'Select image')) + '</button>' +
				'<button type="button" class="btn btn-default btn-xs text-danger" data-description-image-clear data-target="' + inputId + '" data-thumb="' + thumbId + '">' + esc(t('text_remove_image', 'Remove')) + '</button>' +
				'</div>';
		}

		function renderSection(section, index) {
			var definition = (config.types || {})[section.type] || {};
			var fields = definition.fields || [];
			var repeater = fields.filter(function (field) { return field.type === 'repeater'; })[0];
			var html = '<article class="dc-ad-section" data-description-section="' + index + '">' +
				'<header class="dc-ad-section__head">' +
				'<i data-lucide="' + esc(definition.icon || 'file-text') + '" width="16" height="16" class="text-blue-600"></i>' +
				'<strong class="dc-ad-section__title">' + esc(definition.name || section.type) + '</strong>' +
				'<div class="dc-ad-section__actions">' +
				'<button type="button" class="btn btn-default btn-xs" data-description-move="up"' + (index === 0 ? ' disabled' : '') + ' data-toggle="tooltip" title="' + esc(t('text_move_up', 'Move up')) + '"><i data-lucide="arrow-up" width="13" height="13"></i></button>' +
				'<button type="button" class="btn btn-default btn-xs" data-description-move="down"' + (index === state.sections.length - 1 ? ' disabled' : '') + ' data-toggle="tooltip" title="' + esc(t('text_move_down', 'Move down')) + '"><i data-lucide="arrow-down" width="13" height="13"></i></button>' +
				'<button type="button" class="btn btn-default btn-xs text-danger" data-description-remove data-toggle="tooltip" title="' + esc(t('text_remove_section', 'Remove section')) + '"><i data-lucide="trash-2" width="13" height="13"></i></button>' +
				'</div></header><div class="dc-ad-section__body">';

			fields.forEach(function (field) {
				if (field.type !== 'repeater') html += renderField(field, section, index);
			});

			if (repeater) {
				html += '<div class="dc-ad-rows" data-description-rows>';
				(section.rows || []).forEach(function (row, rowIndex) {
					html += '<div class="dc-ad-row" data-description-row="' + rowIndex + '">' +
						'<div class="dc-ad-row__head"><span>#' + (rowIndex + 1) + '</span>' +
						'<button type="button" class="btn btn-default btn-xs text-danger" data-description-row-remove>' + esc(t('text_remove_row', 'Remove row')) + '</button></div>';

					(repeater.fields || []).forEach(function (field) {
						html += renderField(field, section, index, row, rowIndex);
					});
					html += '</div>';
				});
				html += '</div><button type="button" class="btn btn-default btn-xs dc-ad-row-add" data-description-row-add' +
					((section.rows || []).length >= (repeater.max_rows || config.max_rows || 12) ? ' disabled' : '') + '><i data-lucide="plus" width="13" height="13"></i> ' + esc(t('text_add_row', 'Add row')) + '</button>';
			}

			html += '</div></article>';

			return html;
		}

		function initRichText() {
			if (!window.tinymce) return;

			sectionsRoot.querySelectorAll('textarea[data-description-richtext]').forEach(function (textarea) {
				tinymce.init({
					selector: '#' + textarea.id,
					license_key: 'gpl',
					height: 220,
					menubar: false,
					branding: false,
					promotion: false,
					plugins: 'advlist autolink lists link image table code',
					toolbar: 'undo redo | blocks | bold italic | bullist numlist | link image table | code',
					convert_urls: false,
					relative_urls: false,
					setup: function (editor) {
						editor.on('init', function () {
							editor.setContent(textarea.value);
						});
						editor.on('change keyup Undo Redo SetContent', function () {
							editor.save();
							textarea.dispatchEvent(new Event('input', { bubbles: true }));
						});
					}
				});
			});
		}

		function miniText(lines) {
			var html = '';

			for (var index = 0; index < lines; index++) {
				html += '<span class="dc-ad-mini__line' + (index === 0 ? ' dc-ad-mini__line--short' : '') + '"></span>';
			}

			return '<span class="dc-ad-mini__text">' + html + '</span>';
		}

		function miniHeading() {
			return '<span class="dc-ad-mini__heading"></span>';
		}

		function miniImage() {
			return '<span class="dc-ad-mini__image"><i data-lucide="image" width="14" height="14"></i></span>';
		}

		function miniGrid(kind, columns) {
			var count = parseInt(columns, 10);
			var fallback = kind === 'features' ? 4 : 3;

			if (isNaN(count) || count < 2 || count > 4) count = fallback;

			var html = '<span class="dc-ad-mini__grid dc-ad-mini__grid--' + kind + '" style="--dc-ad-mini-columns: ' + count + '">';

			for (var index = 0; index < count; index++) {
				if (kind === 'features') {
					html += '<span class="dc-ad-mini__feature"><span class="dc-ad-mini__dot"></span><span class="dc-ad-mini__line"></span></span>';
				} else if (kind === 'gallery') {
					html += '<span class="dc-ad-mini__tile"><i data-lucide="image" width="12" height="12"></i></span>';
				} else {
					html += '<span class="dc-ad-mini__stat"><span class="dc-ad-mini__value"></span><span class="dc-ad-mini__line"></span></span>';
				}
			}

			return html + '</span>';
		}

		function miniSplit(settings, compact) {
			var text = miniText(compact ? 1 : 3);
			var image = miniImage();
			var first = settings.position === 'left' ? image : text;
			var second = settings.position === 'left' ? text : image;

			return '<span class="dc-ad-mini__split">' + first + second + '</span>';
		}

		// Structural thumbnails mirror the six registry types. They deliberately
		// use neutral blocks rather than product imagery: the silhouette is what
		// helps an operator choose the right section before filling it in.
		function renderSectionPreview(type, section, compact) {
			var settings = (section && section.settings) || {};
			var body = '';

			switch (type) {
				case 'text':
					body = (compact ? '' : miniHeading()) + miniText(compact ? 1 : 3);
					break;
				case 'text_image':
					body = (compact ? '' : miniHeading()) + miniSplit(settings, compact);
					break;
				case 'features':
					body = (compact ? '' : miniHeading()) + miniGrid('features', settings.columns);
					break;
				case 'gallery':
					body = miniGrid('gallery', settings.columns);
					break;
				case 'stats':
					body = miniGrid('stats', settings.columns);
					break;
				case 'statement':
					body = '<span class="dc-ad-mini__statement"><span class="dc-ad-mini__quote">“</span>' + miniText(compact ? 1 : 3) + '</span>';
					if (!compact) body += '<span class="dc-ad-mini__footnote"></span>';
					break;
				default:
					body = miniText(compact ? 1 : 3);
			}

			return '<span class="dc-ad-mini' + (compact ? ' dc-ad-mini--compact' : '') + '" aria-hidden="true">' + body + '</span>';
		}

		function renderPresetPreview(document) {
			var html = '<span class="dc-ad-mini dc-ad-mini--preset" aria-hidden="true">';

			((document && document.sections) || []).forEach(function (section) {
				html += '<span class="dc-ad-mini__preset-section">' + renderSectionPreview(section.type, section, true) + '</span>';
			});

			return html + '</span>';
		}

		function appendTemplateCard(code, preset, name, number) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'dc-ad-choice';
			button.setAttribute('data-description-template', code);
			button.innerHTML = '<span class="dc-ad-choice__preview">' + renderPresetPreview(preset) + '</span>' +
				'<span class="dc-ad-choice__body"><span class="dc-ad-choice__number" aria-hidden="true">' + number + '</span>' +
				'<span class="dc-ad-choice__name">' + esc(name) + '</span></span>';
			templateCards.appendChild(button);
		}

		function buildTemplates() {
			templateCards.innerHTML = '';

			Object.keys(config.presets || {}).forEach(function (code, index) {
				var preset = (config.preset_documents || {})[code];
				if (!preset) return;

				var position = index + 1;
				var number = position < 10 ? '0' + position : String(position);
				appendTemplateCard(code, preset, config.presets[code], number);
			});
		}

		function appendSectionCard(type, definition) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'dc-ad-choice';
			button.setAttribute('data-description-add-type', type);
			button.innerHTML = '<span class="dc-ad-choice__preview">' + renderSectionPreview(type, {}, false) + '</span>' +
				'<span class="dc-ad-choice__body"><i data-lucide="' + esc(definition.icon || 'file-text') + '" width="15" height="15" aria-hidden="true"></i>' +
				'<span class="dc-ad-choice__name">' + esc(definition.name || type) + '</span></span>';
			addCards.appendChild(button);
		}

		function buildSectionChoices() {
			addCards.innerHTML = '';

			Object.keys(config.types || {}).forEach(function (type) {
				appendSectionCard(type, config.types[type]);
			});
		}

		function setAddSectionOpen(open) {
			addPanel.hidden = !open;
			addToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		}

		function setMode(value) {
			var advanced = value === '1';

			modeInput.value = advanced ? 1 : 0;
			modeLabel.className = 'label ' + (advanced ? 'label-primary' : 'label-default');
			modeLabel.innerHTML = '<i data-lucide="circle" width="10" height="10"></i> ' + (advanced ? t('text_mode_advanced') : t('text_mode_classic'));
			modeToggle.innerHTML = advanced
				? '<i data-lucide="arrow-left" width="13" height="13"></i> ' + t('text_switch_to_classic')
				: t('text_switch_to_advanced') + ' <i data-lucide="arrow-right" width="13" height="13"></i>';

			workspace.style.display = advanced ? '' : 'none';

			if (advanced) {
				render();
				initRichText();
			} else {
				removeAllRichText();
			}

			if (window.lucide) lucide.createIcons();
		}

		root.addEventListener('change', function (event) {
			var target = event.target;

			if (target.matches('[data-description-path]')) {
				set(target.getAttribute('data-description-path'), target.value);
			}
		});

		root.addEventListener('input', function (event) {
			var target = event.target;
			if (target.matches('input[data-description-path], textarea[data-description-path]')) {
				set(target.getAttribute('data-description-path'), target.value);
			}
		});

		root.addEventListener('click', function (event) {
			var button = event.target.closest('button');
			if (!button) return;

			if (button.hasAttribute('data-description-add-toggle')) {
				setAddSectionOpen(addPanel.hidden);
				return;
			}

			var sectionNode = button.closest('[data-description-section]');
			var index = sectionNode ? parseInt(sectionNode.getAttribute('data-description-section'), 10) : -1;

			if (button.hasAttribute('data-description-move')) {
				var direction = button.getAttribute('data-description-move') === 'up' ? -1 : 1;
				var next = index + direction;
				if (next < 0 || next >= state.sections.length) return;
				var moved = state.sections.splice(index, 1)[0];
				state.sections.splice(next, 0, moved);
				render();
				initRichText();
			} else if (button.hasAttribute('data-description-remove')) {
				state.sections.splice(index, 1);
				render();
				initRichText();
			} else if (button.hasAttribute('data-description-add-type')) {
				if ((state.sections || []).length >= (config.max_sections || 10)) {
					alert(t('text_section_limit', 'Section limit reached'));
					return;
				}
				state.sections.push(makeSection(button.getAttribute('data-description-add-type')));
				render();
				initRichText();
			} else if (button.hasAttribute('data-description-template')) {
				if ((state.sections || []).length && !window.confirm(t('text_confirm_template', 'Replace the current sections with this template?'))) return;
				var preset = (config.preset_documents || {})[button.getAttribute('data-description-template')];
				if (preset) {
					state = JSON.parse(JSON.stringify(preset));
					state.sections.forEach(ensureSectionShape);
					render();
					initRichText();
					setAddSectionOpen(false);
				}
			} else if (button.hasAttribute('data-description-row-add')) {
				var section = state.sections[index];
				var definition = (config.types || {})[section.type] || {};
				var repeater = (definition.fields || []).filter(function (field) { return field.type === 'repeater'; })[0];
				var max = repeater ? (repeater.max_rows || config.max_rows || 12) : (config.max_rows || 12);
				if (section.rows.length >= max) {
					alert(t('text_row_limit', 'Row limit reached'));
					return;
				}
				var row = { content: {} };
				(repeater.fields || []).forEach(function (field) {
					if (field.localized) {
						languages.forEach(function (language) {
							if (!row.content[language.id]) row.content[language.id] = {};
							row.content[language.id][field.key] = field.default || '';
						});
					} else {
						row[field.key] = field.default || '';
					}
				});
				section.rows.push(row);
				render();
				initRichText();
			} else if (button.hasAttribute('data-description-row-remove')) {
				var rowNode = button.closest('[data-description-row]');
				state.sections[index].rows.splice(parseInt(rowNode.getAttribute('data-description-row'), 10), 1);
				render();
				initRichText();
			} else if (button.hasAttribute('data-description-image-select')) {
				openImageManager(button.getAttribute('data-target'), button.getAttribute('data-thumb'));
			} else if (button.hasAttribute('data-description-image-clear')) {
				var clearTarget = document.getElementById(button.getAttribute('data-target'));
				var clearThumb = document.getElementById(button.getAttribute('data-thumb'));
				clearTarget.value = '';
				clearThumb.innerHTML = '<span>—</span>';
				clearTarget.dispatchEvent(new Event('change', { bubbles: true }));
			}
		});

		function openImageManager(targetId, thumbId) {
			$('#modal-image').remove();

			$.ajax({
				url: 'index.php?route=common/filemanager&user_token=' + getURLVar('user_token') + '&target=' + encodeURIComponent(targetId) + '&thumb=' + encodeURIComponent(thumbId),
				dataType: 'html',
				success: function (html) {
					$('body').append('<div id="modal-image" class="modal">' + html + '</div>');
					$('#modal-image').modal('show');
						$('#modal-image').one('hidden.bs.modal', function () {
						var input = document.getElementById(targetId);
						var thumb = document.getElementById(thumbId);
						if (input && input.value) {
							if (thumb) thumb.innerHTML = '<img src="' + esc(input.value) + '" alt=""  />';
							input.dispatchEvent(new Event('change', { bubbles: true }));
						}
					});
				}
			});
		}

		modeToggle.addEventListener('click', function () {
			setMode(modeInput.value === '1' ? '0' : '1');
		});

		function refreshLanguage(previousLanguage) {
			var previousMarker = '.content.' + previousLanguage + '.';

			sectionsRoot.querySelectorAll('[data-description-path]').forEach(function (control) {
				var path = control.getAttribute('data-description-path');

				if (previousLanguage && path.indexOf(previousMarker) !== -1) {
					path = path.replace(previousMarker, '.content.' + activeLanguage + '.');
					control.setAttribute('data-description-path', path);
				}

				var value = get(path);

				if (control.matches('input[type="radio"]')) {
					control.checked = control.value === String(value);
					return;
				}

				if (control.hasAttribute('data-description-image-input')) {
					control.value = value || '';
					var wrap = control.closest('[data-description-image-wrap]');
					var thumb = wrap ? wrap.querySelector('[data-description-image-thumb]') : null;

					if (thumb) {
						thumb.innerHTML = value
							? '<img src="' + esc(value) + '" alt="" />'
							: '<span class="text-xs text-gray-400">—</span>';
					}
				} else if (control.matches('textarea[data-description-richtext]') && window.tinymce) {
					var editor = tinymce.get(control.id);

					if (editor && editor.initialized) {
						editor.setContent(value || '');
					} else {
						control.value = value || '';
					}
				} else {
					control.value = value == null ? '' : value;
				}
			});

			serialize();
		}

		function onLanguageTabShown(event) {
			var match = (event.target.getAttribute('href') || '').match(/^#language(\d+)$/);
			if (!match) return;
			var languageId = parseInt(match[1], 10);
			if (languageId === activeLanguage) return;

			if (window.tinymce) tinymce.triggerSave();

			var previousLanguage = activeLanguage;
			activeLanguage = languageId;
			root.setAttribute('data-active-language', String(languageId));
			refreshLanguage(previousLanguage);
		}

		if (window.jQuery) {
			jQuery(document).on('shown.bs.tab', onLanguageTabShown);
		} else {
			document.addEventListener('shown.bs.tab', onLanguageTabShown);
		}

		buildTemplates();
		buildSectionChoices();
		setAddSectionOpen(false);
		setMode(modeInput.value);

		if (window.lucide) lucide.createIcons();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-description-editor]').forEach(initEditor);
	});
})();
