/**
 * DockerCart Load More — shared AJAX product loader for listing pages.
 * Activates on any page that contains a .dc-load-more-btn button.
 */
(function () {
    'use strict';

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
        if (!btn) return;

        var grid = document.getElementById('dc-product-grid');
        if (!grid) return;

        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            btn.disabled = true;

            var iconEl = btn.querySelector('[data-lucide]');

            if (iconEl) {
                iconEl.setAttribute('data-lucide', 'loader-2');
                iconEl.classList.add('animate-spin');
                if (window.lucide) lucide.createIcons();
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
                        var wrap = btn.closest('.dc-load-more-wrap');
                        if (wrap) { wrap.remove(); } else { btn.remove(); }
                    } else {
                        if (iconEl) {
                            iconEl.innerHTML = ''; // Clear old SVG
                            iconEl.setAttribute('data-lucide', 'chevron-down');
                            iconEl.classList.remove('animate-spin');
                            if (window.lucide) lucide.createIcons();
                        }
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    if (iconEl) {
                        iconEl.setAttribute('data-lucide', 'chevron-down');
                        iconEl.classList.remove('animate-spin');
                        if (window.lucide) lucide.createIcons();
                    }
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
