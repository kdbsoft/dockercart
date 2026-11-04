/**
 * Landing page front-end behaviour.
 *
 * Kept in one file (and out of the block templates) so several blocks of the
 * same type on one page share the code instead of duplicating inline scripts:
 *   - banner slider: fade, arrows, dots, autoplay (blocks/banner)
 *
 * Product carousels are handled by the theme's dc-carousel.js, which
 * initialises every .dc-carousel on the page (landing blocks included).
 *   - smooth in-page anchors for section links
 */
(function () {
	'use strict';

	function initBanners() {
		document.querySelectorAll('[data-lp-banner]').forEach(function (banner) {
			var slides = Array.prototype.slice.call(banner.querySelectorAll('[data-lp-slide]'));
			var dots = Array.prototype.slice.call(banner.querySelectorAll('[data-lp-banner-dot]'));

			if (slides.length < 2) return;

			var index = 0;
			var autoplay = parseInt(banner.getAttribute('data-autoplay'), 10) || 0;
			var timer = null;

			function show(nextIndex) {
				index = (nextIndex + slides.length) % slides.length;

				slides.forEach(function (slide, position) {
					var active = position === index;
					slide.classList.toggle('opacity-100', active);
					slide.classList.toggle('opacity-0', !active);
					slide.classList.toggle('pointer-events-none', !active);
				});

				dots.forEach(function (dot, position) {
					dot.classList.toggle('bg-white', position === index);
					dot.classList.toggle('bg-white/40', position !== index);
				});
			}

			function start() {
				stop();

				if (autoplay > 0) {
					timer = setInterval(function () { show(index + 1); }, autoplay * 1000);
				}
			}

			function stop() {
				if (timer) {
					clearInterval(timer);
					timer = null;
				}
			}

			var previous = banner.querySelector('[data-lp-banner-prev]');
			var next = banner.querySelector('[data-lp-banner-next]');

			if (previous) {
				previous.addEventListener('click', function () { show(index - 1); start(); });
			}

			if (next) {
				next.addEventListener('click', function () { show(index + 1); start(); });
			}

			dots.forEach(function (dot) {
				dot.addEventListener('click', function () {
					show(parseInt(dot.getAttribute('data-lp-banner-dot'), 10) || 0);
					start();
				});
			});

			banner.addEventListener('mouseenter', stop);
			banner.addEventListener('mouseleave', start);

			start();
		});
	}

	function initAnchors() {
		document.addEventListener('click', function (event) {
			var link = event.target.closest ? event.target.closest('a[href^="#"]') : null;

			if (!link) return;

			var id = link.getAttribute('href').slice(1);

			if (!id) return;

			var target = document.getElementById(id);

			if (!target) return;

			event.preventDefault();
			target.scrollIntoView({ behavior: 'smooth', block: 'start' });
		});
	}

	function boot() {
		initBanners();
		initAnchors();

		if (typeof lucide !== 'undefined') {
			lucide.createIcons();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
