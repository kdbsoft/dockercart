(function() {
    'use strict';

    const body = document.body || document.documentElement;
    const userToken = body ? (body.getAttribute('data-user-token') || '') : '';
    const defaultLanguageId = parseInt(body ? (body.getAttribute('data-default-language-id') || '0') : '0', 10);
    const isModuleEnabled = body ? (body.getAttribute('data-google-translation-enabled') === '1') : false;

    if (!userToken || !defaultLanguageId || !isModuleEnabled) {
        return;
    }

    const translatableSelector = 'input[type="text"], textarea';
    const excludedNamePattern = /(image|image_portrait|image_mobile|thumb|icon|logo|link|url|path|file|filename|route|code|token|hash|json|xml|yaml|yml|css|js|date|time|sort_order|model|sku|upc|ean|jan|isbn|mpn)/i;
    const knownLanguageIds = new Set();

    function collectLanguageIds() {
        document.querySelectorAll('[href^="#language"], [href*="lang-"]').forEach(function(anchor) {
            const href = anchor.getAttribute('href') || '';
            const match = href.match(/(\d+)$/);
            if (match) {
                knownLanguageIds.add(parseInt(match[1], 10));
            }
        });

        if (defaultLanguageId) {
            knownLanguageIds.add(defaultLanguageId);
        }
    }

    function detectLanguageIdForField(field) {
        const pane = field.closest('.tab-pane');

        if (pane && pane.id) {
            let match = pane.id.match(/(?:language|lang)[^0-9]*(\d+)$/i);
            if (match) {
                return parseInt(match[1], 10);
            }

            match = pane.id.match(/-(\d+)$/);
            if (match) {
                return parseInt(match[1], 10);
            }
        }

        const name = field.getAttribute('name') || '';
        const allIds = name.match(/\[(\d+)\]/g);

        if (allIds && allIds.length) {
            // Try to resolve using known language IDs first.
            for (let i = allIds.length - 1; i >= 0; i--) {
                const candidate = parseInt(allIds[i].replace(/\[|\]/g, ''), 10);
                if (knownLanguageIds.has(candidate)) {
                    return candidate;
                }
            }

            // Fallback: last numeric token in name.
            const last = allIds[allIds.length - 1].replace(/\[|\]/g, '');
            return parseInt(last, 10);
        }

        return 0;
    }

    function buildSourceFieldName(targetName, sourceLanguageId, targetLanguageId) {
        if (!targetName) {
            return '';
        }

        const targetToken = '[' + targetLanguageId + ']';
        const sourceToken = '[' + sourceLanguageId + ']';

        // Try all possible occurrences (for nested arrays where first [id] can be row id).
        let index = targetName.indexOf(targetToken);
        while (index !== -1) {
            const candidate = targetName.substring(0, index) + sourceToken + targetName.substring(index + targetToken.length);
            if (document.querySelector('[name="' + CSS.escape(candidate) + '"]')) {
                return candidate;
            }

            index = targetName.indexOf(targetToken, index + targetToken.length);
        }

        // Fallback: naive first replacement.
        if (targetName.indexOf(targetToken) !== -1) {
            return targetName.replace(targetToken, sourceToken);
        }

        return '';
    }

    function placeButton(field, btn) {
        const inputGroup = field.closest('.input-group');

        if (inputGroup && field.tagName.toLowerCase() === 'input') {
            const btnGroup = document.createElement('span');
            btnGroup.className = 'input-group-btn gt-inline-translate-wrap';
            btnGroup.appendChild(btn);
            inputGroup.appendChild(btnGroup);
            return;
        }

        if (field.tagName.toLowerCase() === 'textarea') {
            const toolbar = document.createElement('div');
            toolbar.className = 'gt-inline-translate-wrap';
            toolbar.style.margin = '0 0 4px 0';
            toolbar.appendChild(btn);
            field.parentNode.insertBefore(toolbar, field);
            return;
        }

        const holder = document.createElement('span');
        holder.className = 'gt-inline-translate-wrap';
        holder.style.display = 'inline-flex';
        holder.style.marginLeft = '6px';
        holder.style.verticalAlign = 'middle';
        holder.appendChild(btn);

        if (field.nextSibling) {
            field.parentNode.insertBefore(holder, field.nextSibling);
        } else {
            field.parentNode.appendChild(holder);
        }
    }

    function shouldDecorateField(field) {
        if (!field || field.dataset.gtInlineBound === '1') {
            return false;
        }

        if (field.disabled || field.readOnly) {
            return false;
        }

        const type = (field.getAttribute('type') || '').toLowerCase();
        if (type && ['hidden', 'number', 'email', 'password', 'url', 'tel', 'search', 'date', 'datetime-local', 'time', 'color'].includes(type)) {
            return false;
        }

        const name = field.getAttribute('name') || '';
        if (!name || excludedNamePattern.test(name)) {
            return false;
        }

        const targetLanguageId = detectLanguageIdForField(field);
        if (!targetLanguageId || targetLanguageId === defaultLanguageId) {
            return false;
        }

        const sourceName = buildSourceFieldName(name, defaultLanguageId, targetLanguageId);
        if (!sourceName) {
            return false;
        }

        const sourceField = document.querySelector('[name="' + CSS.escape(sourceName) + '"]');
        if (!sourceField) {
            return false;
        }

        return true;
    }

    async function translateText(text, sourceLanguageId, targetLanguageId) {
        const response = await fetch('index.php?route=extension/module/dockercart_google_translation/quickTranslate&user_token=' + encodeURIComponent(userToken), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                text: text,
                source_language_id: sourceLanguageId,
                target_language_id: targetLanguageId
            })
        });

        const json = await response.json();

        if (json.error) {
            throw new Error(json.error);
        }

        return json.translated_text || '';
    }

    function decorateField(field) {
        const targetLanguageId = detectLanguageIdForField(field);
        const name = field.getAttribute('name') || '';
        const sourceName = buildSourceFieldName(name, defaultLanguageId, targetLanguageId);

        if (!sourceName) {
            return;
        }

        const sourceField = document.querySelector('[name="' + CSS.escape(sourceName) + '"]');
        if (!sourceField) {
            return;
        }

        field.dataset.gtInlineBound = '1';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-default btn-xs gt-inline-translate-btn';
        btn.innerHTML = '<i class="fa fa-language"></i>';
        btn.title = 'Translate from default language';

        placeButton(field, btn);

        btn.addEventListener('click', async function() {
            const sourceText = (sourceField.value || '').trim();

            if (!sourceText) {
                return;
            }

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

            try {
                const translated = await translateText(sourceText, defaultLanguageId, targetLanguageId);
                field.value = translated;

                if (window.jQuery) {
                    window.jQuery(field).trigger('change');
                } else {
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }

                btn.classList.remove('btn-default');
                btn.classList.add('btn-success');
                setTimeout(function() {
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-default');
                }, 1200);
            } catch (e) {
                btn.classList.remove('btn-default');
                btn.classList.add('btn-danger');
                setTimeout(function() {
                    btn.classList.remove('btn-danger');
                    btn.classList.add('btn-default');
                }, 1500);
                // keep silent in UI; leave diagnostics in console
                // eslint-disable-next-line no-console
                console.error('Inline translation error:', e);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    }

    function scanAndDecorate(root) {
        const ctx = root || document;
        const fields = ctx.querySelectorAll(translatableSelector);

        fields.forEach(function(field) {
            if (shouldDecorateField(field)) {
                decorateField(field);
            }
        });
    }

    function init() {
        collectLanguageIds();
        scanAndDecorate(document);

        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        scanAndDecorate(node);
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });

        document.addEventListener('shown.bs.tab', function() {
            scanAndDecorate(document);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
