/**
 * DockerCart Product Carousel
 * Vanilla JS — scroll-snap based horizontal carousel with arrow navigation.
 * Touch scrolling is handled natively by the browser via overflow-x: auto.
 */
(function () {
  'use strict';

  var SCROLL_STEP = 2; // cards to scroll per arrow click

  function getCardWidth(track) {
    var first = track.firstElementChild;
    if (!first) return 200;
    return first.offsetWidth + parseFloat(getComputedStyle(track).gap || 0);
  }

  function updateState(carousel) {
    var track = carousel._track;
    var prev = carousel._prev;
    var next = carousel._next;
    var scrollLeft = track.scrollLeft;
    var maxScroll = track.scrollWidth - track.clientWidth;

    if (prev) {
      var atStart = scrollLeft <= 2;
      prev.style.opacity = atStart ? '0' : '';
      prev.style.pointerEvents = atStart ? 'none' : '';
    }
    if (next) {
      var atEnd = scrollLeft >= maxScroll - 2;
      next.style.opacity = atEnd ? '0' : '';
      next.style.pointerEvents = atEnd ? 'none' : '';
    }
  }

  function initCarousel(el) {
    var track = el.querySelector('.dc-carousel-track');
    if (!track) return;

    var prev = el.querySelector('.dc-carousel-prev');
    var next = el.querySelector('.dc-carousel-next');

    el._track = track;
    el._prev = prev;
    el._next = next;

    if (prev) {
      prev.addEventListener('click', function () {
        var step = getCardWidth(track) * SCROLL_STEP;
        track.scrollBy({ left: -step, behavior: 'smooth' });
      });
    }

    if (next) {
      next.addEventListener('click', function () {
        var step = getCardWidth(track) * SCROLL_STEP;
        track.scrollBy({ left: step, behavior: 'smooth' });
      });
    }

    track.addEventListener('scroll', function () {
      updateState(el);
    }, { passive: true });

    // Recalculate on resize
    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        updateState(el);
      }, 150);
    });

    // Keyboard navigation when focused inside carousel
    track.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') {
        e.preventDefault();
        if (prev) prev.click();
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        if (next) next.click();
      }
    });

    updateState(el);
  }

  function boot() {
    var carousels = document.querySelectorAll('.dc-carousel');
    carousels.forEach(initCarousel);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
