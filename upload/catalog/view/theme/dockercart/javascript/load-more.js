/**
 * DockerCart Load More — shared AJAX product loader for listing pages.
 * Activates on any page that contains a .dc-load-more-btn button.
 */
(function () {
    'use strict';

    /**
     * Replace loader icon with chevron-down SVG
     */
    function showChevronIcon(btn) {
        var oldIcon = btn.querySelector('[data-lucide]');
        if (!oldIcon) return;
        
        console.log('[LoadMore] Replacing icon, old:', oldIcon.outerHTML);
        
        // Create new icon element with inline SVG
        var newIcon = document.createElement('i');
        newIcon.className = oldIcon.className;
        newIcon.classList.remove('animate-spin');
        
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
        newIcon.innerHTML = svg;
        
        // Replace old icon with new
        oldIcon.parentNode.replaceChild(newIcon, oldIcon);
        console.log('[LoadMore] Icon replaced, new:', newIcon.outerHTML);
    }

    /**
     * Show loader icon using lucide
     */
    function showLoaderIcon(iconEl) {
        if (!iconEl) return;
        console.log('[LoadMore] Showing loader, iconEl before:', iconEl.outerHTML);
        iconEl.setAttribute('data-lucide', 'loader-2');
        if (window.lucide) window.lucide.createIcons();
        console.log('[LoadMore] Showing loader, iconEl after:', iconEl.outerHTML);
    }

    /**
     * Move the active highlight in .dc-pagination to the given page number.
     * OpenCart renders active page as <li class="active"><span>N</span></li>
     * and other pages as <li><a href="...&page=N">N</a></li>.
     * We only toggle the `active` class; CSS handles both span and a.
     */
    function updatePaginationActive(newPage) {
        var ul = document.querySelector('.dc-pagination ul');
        if (!ul) return;

        // Remove active from all items
        ul.querySelectorAll('li.active').forEach(function (li) {
            li.classList.remove('active');
        });

        // Activate the li whose link points to newPage
        ul.querySelectorAll('li a').forEach(function (a) {
            try {
                var p = new URL(a.href).searchParams.get('page');
                if (parseInt(p, 10) === newPage) {
                    a.parentElement.classList.add('active');
                }
            } catch (e) {}
        });
    }

    function initLoadMore() {
        var btn = document.querySelector('.dc-load-more-btn');
        if (!btn) {
            console.log('[LoadMore] Button not found');
            return;
        }
        console.log('[LoadMore] Button found:', btn);

        var grid = document.getElementById('dc-product-grid');
        if (!grid) {
            console.log('[LoadMore] Grid not found');
            return;
        }
        console.log('[LoadMore] Grid found:', grid);

        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            btn.disabled = true;

            var iconEl = btn.querySelector('[data-lucide]');
            console.log('[LoadMore] Icon element:', iconEl);

            if (iconEl) {
                showLoaderIcon(iconEl);
                iconEl.classList.add('animate-spin');
            }

            var page = parseInt(btn.dataset.page, 10);
            var url  = btn.dataset.url + '&page=' + page;

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    if (data.html) {
                        grid.insertAdjacentHTML('beforeend', data.html);
                        if (window.lucide) lucide.createIcons();
                    }

                    var loaded = parseInt(btn.dataset.loaded, 10) + (parseInt(data.count, 10) || 0);
                    btn.dataset.loaded  = loaded;
                    btn.dataset.page    = page + 1;

                    // Highlight the page we just loaded in pagination
                    updatePaginationActive(page);

                    // Update count badge
                    var countEl = btn.querySelector('.dc-lm-count');
                    if (countEl) {
                        countEl.textContent = '(' + loaded + ' / ' + btn.dataset.total + ')';
                    }

                    if (loaded >= parseInt(btn.dataset.total, 10)) {
                        console.log('[LoadMore] All products loaded, removing button');
                        var wrap = btn.closest('.dc-load-more-wrap');
                        if (wrap) { wrap.remove(); } else { btn.remove(); }
                    } else {
                        console.log('[LoadMore] More products to load, updating icon');
                        showChevronIcon(btn);
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    console.log('[LoadMore] Error loading products');
                    showChevronIcon(btn);
                    btn.disabled = false;
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoadMore);
    } else {
        initLoadMore();
    }
})();
