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
    var hasPages = maxScroll >= getCardWidth(track);

    carousel.classList.toggle('dc-carousel--has-pages', hasPages);

    if (prev) {
      var atStart = !hasPages || scrollLeft <= 2;
      prev.style.opacity = atStart ? '0' : '';
      prev.style.pointerEvents = atStart ? 'none' : '';
    }
    if (next) {
      var atEnd = !hasPages || scrollLeft >= maxScroll - 2;
      next.style.opacity = atEnd ? '0' : '';
      next.style.pointerEvents = atEnd ? 'none' : '';
    }

    updatePage(carousel);
  }

  function updatePage(carousel) {
    var badge = carousel._page;
    if (!badge) return;

    var track = carousel._track;
    var maxScroll = track.scrollWidth - track.clientWidth;
    if (maxScroll <= 2) {
      badge.textContent = '1 / 1';
      return;
    }

    var total = Math.max(2, Math.ceil(track.scrollWidth / track.clientWidth));
    var current = Math.min(total, Math.round(track.scrollLeft / maxScroll * (total - 1)) + 1);

    badge.textContent = current + ' / ' + total;
  }

  function initCarousel(el) {
    var track = el.querySelector('.dc-carousel-track');
    if (!track) return;

    var prev = el.querySelector('.dc-carousel-prev');
    var next = el.querySelector('.dc-carousel-next');
    var page = el.querySelector('.dc-carousel-page');

    el._track = track;
    el._prev = prev;
    el._next = next;
    el._page = page;

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

  /**
   * Brand marquee drag (mouse + touch via Pointer Events).
   * Pauses the CSS marquee animation while dragging and scrubs position;
   * resumes auto-scroll on release.
   */
  function initBrandMarquee(track) {
    var anims = track.getAnimations();
    if (!anims.length) return;
    var anim = anims[0];

    var timing = anim.effect && anim.effect.getTiming ? anim.effect.getTiming() : null;
    var duration = timing && typeof timing.duration === 'number' && isFinite(timing.duration)
      ? timing.duration
      : 28000;
    var distance = Math.max(track.scrollWidth / 2, 1);

    var wrapper = track.parentElement;
    if (!wrapper) return;
    // keep vertical page scrolling on touch; horizontal handled here
    wrapper.style.touchAction = 'pan-y';

    var pointerId = null;
    var startX = 0;
    var baseTime = 0;
    var dragged = false;

    function onDown(e) {
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      pointerId = e.pointerId;
      startX = e.clientX;
      baseTime = anim.currentTime || 0;
      dragged = false;
      anim.pause();
    }

    function onMove(e) {
      if (e.pointerId !== pointerId) return;
      var dx = e.clientX - startX;
      if (!dragged && Math.abs(dx) > 8) {
        dragged = true;
        if (wrapper.setPointerCapture) {
          try { wrapper.setPointerCapture(e.pointerId); } catch (err) {}
        }
      }
      if (!dragged) return;
      var val = baseTime - dx * (duration / distance);
      val = Math.max(0, Math.min(duration, val));
      anim.currentTime = val;
    }

    function suppressClick(e) {
      e.preventDefault();
      e.stopPropagation();
      document.removeEventListener('click', suppressClick, true);
    }

    function onUp(e) {
      if (e.pointerId !== pointerId) return;
      pointerId = null;
      if (dragged) {
        document.addEventListener('click', suppressClick, true);
        setTimeout(function () { document.removeEventListener('click', suppressClick, true); }, 400);
      }
      anim.play();
    }

    wrapper.addEventListener('pointerdown', onDown);
    wrapper.addEventListener('pointermove', onMove, { passive: true });
    wrapper.addEventListener('pointerup', onUp);
    wrapper.addEventListener('pointercancel', onUp);
  }

  /**
   * Rating distribution popovers inside carousels.
   *
   * The popover lives in the DOM but is display:none by default, so it never
   * affects the track's scroll size (no scrollbars). On hover it is shown as
   * position: fixed, measured from the viewport, so the track's
   * overflow-x: auto cannot clip it.
   */
  function initRatingPopovers(carousel) {
    var track = carousel.querySelector('.dc-carousel-track');
    if (!track) return;

    track.querySelectorAll('.dc-rating-hover').forEach(function (hover) {
      var popover = hover.querySelector('.dc-rating-popover');
      if (!popover) return;

      // Where the popover lives in the card markup; always restored here on hide
      var home = popover.parentNode;
      var homeNext = popover.nextSibling;

      var restore = function () {
        if (popover.parentNode !== home) {
          if (homeNext) {
            home.insertBefore(popover, homeNext);
          } else {
            home.appendChild(popover);
          }
        }
      };

      var hideTimer = null;

      var cancelHide = function () {
        if (hideTimer) {
          clearTimeout(hideTimer);
          hideTimer = null;
        }
      };

      var hide = function () {
        cancelHide();
        popover.classList.remove('dc-rating-popover--open');
        popover.style.position = '';
        popover.style.zIndex = '';
        popover.style.left = '';
        popover.style.top = '';
        popover.style.visibility = '';
        restore();
      };

      // The popover is detached into <body>, so moving from the stars onto it
      // fires mouseleave on the stars. Delay the hide and cancel it when the
      // pointer actually enters the popover.
      var scheduleHide = function () {
        cancelHide();
        hideTimer = setTimeout(hide, 150);
      };

      hover.addEventListener('mouseenter', function () {
        cancelHide();
        var rect = hover.getBoundingClientRect();

        // Detach into <body>: the card has a hover transform (scale), which
        // would otherwise turn position:fixed into a card-relative layout.
        // z-index must be inline: the popover leaves .dc-carousel, so the
        // scoped CSS rule no longer matches, and the hovered card (z-30)
        // would otherwise cover it.
        document.body.appendChild(popover);
        popover.style.position = 'fixed';
        popover.style.zIndex = '60';

        // Measure first: display:block with visibility:hidden at 0,0 (fixed
        // so it never affects the track), then position and reveal.
        popover.style.left = '0px';
        popover.style.top = '0px';
        popover.classList.add('dc-rating-popover--open');
        popover.style.visibility = 'hidden';

        var width = popover.offsetWidth || 240;
        var height = popover.offsetHeight || 200;

        // Popover sits below the stars, centered horizontally (like everywhere
        // else on the site); flip above only when there is no room below.
        var left = rect.left + rect.width / 2 - width / 2;
        var viewport = document.documentElement.clientWidth;
        left = Math.max(8, Math.min(left, viewport - width - 8));

        var top = rect.bottom + 8;
        if (top + height > document.documentElement.clientHeight - 8) {
          // Not enough room below: flip above the stars
          top = rect.top - height - 8;
        }

        popover.style.left = left + 'px';
        popover.style.top = Math.max(8, top) + 'px';
        popover.style.visibility = '';
      });

      hover.addEventListener('mouseleave', scheduleHide);
      popover.addEventListener('mouseenter', cancelHide);
      popover.addEventListener('mouseleave', scheduleHide);

      window.addEventListener('scroll', hide, { passive: true });
      window.addEventListener('resize', hide, { passive: true });
    });
  }

  function boot() {
    var carousels = document.querySelectorAll('.dc-carousel');
    carousels.forEach(initCarousel);
    carousels.forEach(initRatingPopovers);

    document.querySelectorAll('.brands-track').forEach(initBrandMarquee);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
