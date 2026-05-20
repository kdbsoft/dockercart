/**
 * DockerCart View Mode — grid / list / table switcher.
 * Saves preference to localStorage + cookie, reloads to render correct HTML.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'dc_view_mode';
    var VALID_MODES = ['grid', 'list', 'table'];

    function setCookie(mode) {
        document.cookie = STORAGE_KEY + '=' + mode + ';path=/;max-age=31536000;SameSite=Lax';
    }

    function navigateToMode(mode) {
        try { localStorage.setItem(STORAGE_KEY, mode); } catch (e) {}
        setCookie(mode);
        location.reload();
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
