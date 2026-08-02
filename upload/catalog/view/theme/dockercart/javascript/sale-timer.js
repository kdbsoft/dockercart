/**
 * DockerCart Sale Timer — product page countdown for products with an active
 * special price. Vanilla JS, no dependencies.
 *
 * Renders into [data-countdown-until] elements (unix timestamp in seconds):
 *   [data-timer-days]  — remaining days
 *   [data-timer-unit]  — HH : MM : SS cells
 *
 * The widget is hidden once the countdown reaches zero.
 */
(function () {
  'use strict';

  var SECONDS_PER_DAY = 86400;
  var SECONDS_PER_HOUR = 3600;
  var SECONDS_PER_MINUTE = 60;

  function getEnd(el) {
    var value = parseInt(el.getAttribute('data-countdown-until'), 10);
    return (isNaN(value) || value <= 0) ? 0 : value * 1000;
  }

  function pad(value) {
    return value < 10 ? '0' + value : String(value);
  }

  function render(el, totalSeconds) {
    var days = Math.floor(totalSeconds / SECONDS_PER_DAY);
    totalSeconds -= days * SECONDS_PER_DAY;

    var hours = Math.floor(totalSeconds / SECONDS_PER_HOUR);
    totalSeconds -= hours * SECONDS_PER_HOUR;

    var minutes = Math.floor(totalSeconds / SECONDS_PER_MINUTE);
    totalSeconds -= minutes * SECONDS_PER_MINUTE;

    var seconds = totalSeconds;

    if (el._days) {
      el._days.textContent = String(days);
    }

    el._cells.forEach(function (cell, index) {
      cell.textContent = pad([hours, minutes, seconds][index]);
    });
  }

  function tick(el) {
    var remaining = el._end - Date.now();

    if (remaining <= 0) {
      render(el, 0);
      el.style.display = 'none';
      clearInterval(el._timerId);
      return;
    }

    render(el, Math.floor(remaining / 1000));
  }

  function initTimer(el) {
    if (!el || el._dcTimerStarted) {
      return;
    }

    el._end = getEnd(el);

    if (el._end <= 0) {
      el.style.display = 'none';
      return;
    }

    el._days = el.querySelector('[data-timer-days]');
    el._cells = el.querySelectorAll('[data-timer-unit]');

    el._dcTimerStarted = true;
    tick(el);
    el._timerId = setInterval(tick, 1000, el);
  }

  function initTimers(root) {
    var scope = root || document;

    if (!scope.querySelectorAll) {
      return;
    }

    scope.querySelectorAll('[data-countdown-until]').forEach(initTimer);
  }

  window.dcInitSaleTimers = initTimers;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initTimers(document);
    });
  } else {
    initTimers(document);
  }
})();
