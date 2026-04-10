/**
 * DockerCart Search - Autocomplete
 * Grouped suggestions: products, categories, manufacturers.
 *
 * @package    DockerCart
 * @subpackage Module
 * @author     DockerCart Team
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    2.0.0
 */

(function() {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        var searchInput = getActiveSearchInput();

        if (!searchInput) { return; }
        if (typeof dockercart_search_config === 'undefined') {
            console.warn('DockerCart Search: Configuration not loaded');
            return;
        }

        var config     = dockercart_search_config;
        var minChars   = config.min_chars || 3;
        var suggestUrl = config.suggest_url;

        // Read category context from the form's data attributes
        var searchForm = searchInput.closest('form');
        var categoryId = searchForm ? (searchForm.dataset.searchCategoryId || '0') : '0';

        var debounceTimer;
        var suggestBox;
        var currentQuery = '';

        createSuggestBox();

        searchInput.addEventListener('input',   handleInput);
        searchInput.addEventListener('keydown', handleKeydown);
        searchInput.addEventListener('blur',    handleBlur);

        document.addEventListener('click', function(e) {
            if (!suggestBox.contains(e.target) && e.target !== searchInput) {
                hideSuggestions();
            }
        });

        /* ------------------------------------------------------------------ */

        function createSuggestBox() {
            suggestBox = document.createElement('div');
            suggestBox.className = 'dockercart-search-suggest';
            suggestBox.style.cssText = [
                'position:fixed',
                'z-index:2147483000',
                'background:#fff',
                'border:1px solid #e5e7eb',
                'border-radius:12px',
                'max-height:440px',
                'overflow-y:auto',
                'display:none',
                'box-shadow:0 8px 30px rgba(0,0,0,0.12)',
                'left:0',
                'top:0'
            ].join(';');

            document.body.appendChild(suggestBox);

            window.addEventListener('resize', updatePosition);
            window.addEventListener('scroll', updatePosition, true);
        }

        function getActiveSearchInput() {
            var inputs = document.querySelectorAll('input[name="search"]');
            if (!inputs || !inputs.length) { return null; }

            // Prefer dedicated theme search forms when present.
            var preferred = [];
            for (var i = 0; i < inputs.length; i++) {
                if (inputs[i].closest('.dc-search-form')) {
                    preferred.push(inputs[i]);
                }
            }

            var pool = preferred.length ? preferred : Array.prototype.slice.call(inputs);
            var isMobileViewport = window.matchMedia && window.matchMedia('(max-width: 639.98px)').matches;

            // On mobile, try to bind to the mobile header search first.
            if (isMobileViewport) {
                for (var m = 0; m < pool.length; m++) {
                    if (isElementVisible(pool[m]) && pool[m].closest('.dc-search-mobile')) {
                        return pool[m];
                    }
                }
            }

            // Otherwise use the first visible input.
            for (var v = 0; v < pool.length; v++) {
                if (isElementVisible(pool[v])) {
                    return pool[v];
                }
            }

            // Fallback for edge cases where visibility can't be determined yet.
            return pool[0] || null;
        }

        function isElementVisible(el) {
            if (!el) { return false; }
            var style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden') { return false; }
            if (el.offsetWidth === 0 && el.offsetHeight === 0 && el.getClientRects().length === 0) { return false; }
            return true;
        }

        function updatePosition() {
            if (!suggestBox || suggestBox.style.display === 'none') { return; }
            var rect = searchInput.getBoundingClientRect();
            suggestBox.style.left  = Math.max(rect.left, 8) + 'px';
            suggestBox.style.top   = (rect.bottom + 6) + 'px';
            suggestBox.style.width = rect.width + 'px';
        }

        function handleInput(e) {
            var query = e.target.value.trim();
            clearTimeout(debounceTimer);
            if (query.length < minChars) { hideSuggestions(); return; }
            debounceTimer = setTimeout(function() { fetchSuggestions(query); }, 300);
        }

        function handleKeydown(e) {
            if (!suggestBox || suggestBox.style.display === 'none') { return; }
            var items  = suggestBox.querySelectorAll('.dc-si');
            var active = suggestBox.querySelector('.dc-si.active');

            if (e.keyCode === 38) { // Arrow up
                e.preventDefault();
                if (active && active.previousElementSibling && active.previousElementSibling.classList.contains('dc-si')) {
                    active.classList.remove('active');
                    active.previousElementSibling.classList.add('active');
                }
            } else if (e.keyCode === 40) { // Arrow down
                e.preventDefault();
                if (!active && items.length) {
                    items[0].classList.add('active');
                } else if (active && active.nextElementSibling && active.nextElementSibling.classList.contains('dc-si')) {
                    active.classList.remove('active');
                    active.nextElementSibling.classList.add('active');
                }
            } else if (e.keyCode === 13) { // Enter
                if (active) { e.preventDefault(); active.click(); }
            } else if (e.keyCode === 27) { // Escape
                hideSuggestions();
            }
        }

        function handleBlur() {
            setTimeout(function() { hideSuggestions(); }, 200);
        }

        function fetchSuggestions(query) {
            if (query === currentQuery) { return; }
            currentQuery = query;

            var url = suggestUrl + '&query=' + encodeURIComponent(query);
            if (categoryId && categoryId !== '0') {
                url += '&category_id=' + encodeURIComponent(categoryId);
            }

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) { displaySuggestions(data); })
                .catch(function(err) { console.error('DockerCart Search suggest error', err); });
        }

        /* ------------------------------------------------------------------ */

        function displaySuggestions(items) {
            suggestBox.innerHTML = '';

            if (!items || items.length === 0) { hideSuggestions(); return; }

            // Split by type
            var products      = items.filter(function(i) { return i.type === 'product'; });
            var categories    = items.filter(function(i) { return i.type === 'category'; });
            var manufacturers = items.filter(function(i) { return i.type === 'manufacturer'; });

            // ─ Categories section ────────────────────────────────────────────
            if (categories.length) {
                appendGroupHeader(suggestBox, svgFolder(), (config.labels && config.labels.categories) ? config.labels.categories : 'Categories');
                categories.forEach(function(item) {
                    var row = makeSimpleRow(item.name, item.href, svgFolder());
                    suggestBox.appendChild(row);
                });
            }

            // ─ Manufacturers section ──────────────────────────────────────────
            if (manufacturers.length) {
                appendGroupHeader(suggestBox, svgFactory(), (config.labels && config.labels.manufacturers) ? config.labels.manufacturers : 'Manufacturers');
                manufacturers.forEach(function(item) {
                    var row = makeSimpleRow(item.name, item.href, svgFactory());
                    suggestBox.appendChild(row);
                });
            }

            // ─ Products section ───────────────────────────────────────────────
            if (products.length) {
                appendGroupHeader(suggestBox, svgTag(), (config.labels && config.labels.products) ? config.labels.products : 'Products');
                products.forEach(function(item) {
                    var row = makeProductRow(item);
                    suggestBox.appendChild(row);
                });
            }

            showSuggestions();
        }

        /* ─── row builders ─────────────────────────────────────────────────── */

        function appendGroupHeader(box, iconSvg, label) {
            var hdr = document.createElement('div');
            hdr.style.cssText = 'display:flex;align-items:center;gap:6px;padding:8px 12px 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;pointer-events:none;';
            hdr.innerHTML = iconSvg + label;
            box.appendChild(hdr);
        }

        function makeSimpleRow(name, href, iconSvg) {
            var row = document.createElement('a');
            row.href = href;
            row.className = 'dc-si';
            row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:8px 14px;text-decoration:none;color:#1f2937;font-size:13px;transition:background .15s;cursor:pointer;';
            row.innerHTML = '<span style="flex-shrink:0;color:#6b7280;">' + iconSvg + '</span><span style="flex:1;font-weight:500;">' + escHtml(name) + '</span>';
            addRowHover(row);
            return row;
        }

        function makeProductRow(item) {
            var row = document.createElement('a');
            row.href = item.href;
            row.className = 'dc-si';
            row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:8px 14px;text-decoration:none;color:#1f2937;transition:background .15s;cursor:pointer;';

            var imgEl = document.createElement('img');
            imgEl.src = item.image;
            imgEl.alt = item.name;
            imgEl.style.cssText = 'width:44px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0;border:1px solid #f3f4f6;';

            var info = document.createElement('div');
            info.style.cssText = 'flex:1;min-width:0;';

            var name = document.createElement('div');
            name.style.cssText = 'font-size:13px;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
            name.textContent = item.name;

            if (item.model) {
                var model = document.createElement('div');
                model.style.cssText = 'font-size:11px;color:#9ca3af;margin-top:1px;';
                model.textContent = item.model;
                info.appendChild(name);
                info.appendChild(model);
            } else {
                info.appendChild(name);
            }

            var priceDiv = document.createElement('div');
            priceDiv.style.cssText = 'text-align:right;flex-shrink:0;';

            if (item.special) {
                var old = document.createElement('div');
                old.style.cssText = 'font-size:11px;color:#9ca3af;text-decoration:line-through;';
                old.textContent = item.price;
                var sp = document.createElement('div');
                sp.style.cssText = 'font-size:13px;font-weight:700;color:#ef4444;';
                sp.textContent = item.special;
                priceDiv.appendChild(old);
                priceDiv.appendChild(sp);
            } else if (item.price) {
                var pr = document.createElement('div');
                pr.style.cssText = 'font-size:13px;font-weight:700;color:#111827;';
                pr.textContent = item.price;
                priceDiv.appendChild(pr);
            }

            row.appendChild(imgEl);
            row.appendChild(info);
            row.appendChild(priceDiv);

            addRowHover(row);
            return row;
        }

        function addRowHover(row) {
            row.addEventListener('mouseenter', function() {
                suggestBox.querySelectorAll('.dc-si').forEach(function(el) { el.classList.remove('active'); el.style.background = ''; });
                row.classList.add('active');
                row.style.background = '#f0f9ff';
            });
            row.addEventListener('mouseleave', function() {
                row.style.background = '';
            });
        }

        /* ─── SVG icons (inline, no dependency) ─────────────────────────────── */

        function svgFolder() {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
        }

        function svgFactory() {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>';
        }

        function svgTag() {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>';
        }

        function escHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        /* ─── show / hide ────────────────────────────────────────────────────── */

        function showSuggestions() {
            suggestBox.style.display = 'block';
            updatePosition();
        }

        function hideSuggestions() {
            suggestBox.style.display = 'none';
            currentQuery = '';
        }
    }
})();
