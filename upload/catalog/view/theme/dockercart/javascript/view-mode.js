/**
 * DockerCart View Mode — grid / list / table switcher.
 * Persists choice in localStorage, navigates to ?view= on click
 * (full page reload to render correct card HTML).
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'dc_view_mode';
    var VALID_MODES = ['grid', 'list', 'table'];

    function getViewMode() {
        var params = new URLSearchParams(window.location.search);
        var fromUrl = params.get('view');
        if (VALID_MODES.indexOf(fromUrl) !== -1) {
            try { localStorage.setItem(STORAGE_KEY, fromUrl); } catch (e) {}
            return fromUrl;
        }
        try {
            var stored = localStorage.getItem(STORAGE_KEY);
            if (VALID_MODES.indexOf(stored) !== -1) return stored;
        } catch (e) {}
        return 'grid';
    }

    function navigateToMode(mode) {
        try { localStorage.setItem(STORAGE_KEY, mode); } catch (e) {}
        var url = new URL(window.location.href);
        if (mode === 'grid') {
            url.searchParams.delete('view');
        } else {
            url.searchParams.set('view', mode);
        }
        window.location.href = url;
    }

    function initViewToggle() {
        var toggleContainer = document.querySelector('.dc-view-toggle');
        if (!toggleContainer) return;

        toggleContainer.addEventListener('click', function (e) {
            var btn = e.target.closest('.dc-view-btn');
            if (!btn) return;
            if (btn.classList.contains('is-active')) return;
            var mode = btn.dataset.view;
            if (VALID_MODES.indexOf(mode) === -1) return;
            navigateToMode(mode);
        });
    }

    function redirectFromStorage() {
        var url = new URL(window.location.href);
        var fromUrl = url.searchParams.get('view');
        // Only redirect if no URL param but localStorage has a non-default value
        if (VALID_MODES.indexOf(fromUrl) === -1) {
            try {
                var stored = localStorage.getItem(STORAGE_KEY);
                if (VALID_MODES.indexOf(stored) !== -1 && stored !== 'grid') {
                    url.searchParams.set('view', stored);
                    window.location.href = url;
                }
            } catch (e) {}
        }
    }

    function init() {
        initViewToggle();
        redirectFromStorage();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
