/**
 * DockerCart View Mode — grid / list / table switcher.
 * Handles click on toggle buttons (navigates to ?view=).
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'dc_view_mode';
    var VALID_MODES = ['grid', 'list', 'table'];

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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initViewToggle);
    } else {
        initViewToggle();
    }

})();
