/**
 * DockerCart Gallery — masonry layout with GLightbox and AJAX lazy load.
 */
(function () {
	'use strict';

	function initGalleryLightbox() {
		if (typeof GLightbox !== 'function') {
			return;
		}
		if (window.dcGalleryLightbox) {
			window.dcGalleryLightbox.destroy();
		}
		window.dcGalleryLightbox = GLightbox({
			selector: '.dc-gallery-lightbox',
			loop: true,
			touchNavigation: true,
			zoomable: true,
			openEffect: 'zoom',
			closeEffect: 'fade'
		});
	}

	function initLoadMore() {
		var btn = document.querySelector('.dc-load-more-btn');
		if (!btn) {
			return;
		}

		var grid = document.getElementById('dc-gallery-grid');
		if (!grid) {
			return;
		}

		btn.addEventListener('click', function () {
			if (btn.disabled) {
				return;
			}
			btn.disabled = true;

			var page = parseInt(btn.dataset.page, 10);
			var url = btn.dataset.url + '&page=' + page;

			fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (r) {
					if (!r.ok) {
						throw new Error('HTTP ' + r.status);
					}
					return r.json();
				})
				.then(function (data) {
					if (data.html) {
						grid.insertAdjacentHTML('beforeend', data.html);
						if (window.lucide) {
							lucide.createIcons();
						}
						initGalleryLightbox();
					}

					var loaded = parseInt(btn.dataset.loaded, 10) + (parseInt(data.count, 10) || 0);
					btn.dataset.loaded = loaded;
					btn.dataset.page = page + 1;

					var countEl = btn.querySelector('.dc-lm-count');
					if (countEl) {
						countEl.textContent = '(' + loaded + ' / ' + data.total + ')';
					}

					if (loaded >= parseInt(data.total, 10)) {
						var wrap = btn.closest('.dc-load-more-wrap');
						if (wrap) {
							wrap.remove();
						} else {
							btn.remove();
						}
					} else {
						btn.disabled = false;
					}
				})
				.catch(function () {
					btn.disabled = false;
				});
		});
	}

	function init() {
		initGalleryLightbox();
		initLoadMore();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
