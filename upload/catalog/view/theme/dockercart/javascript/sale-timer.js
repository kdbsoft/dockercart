/**
 * DockerCart Sale Timer — product page countdown for products with an active
 * special price. Vanilla JS, no dependencies.
 *
 * Renders into [data-countdown-until] elements (unix timestamp in seconds):
 *   [data-timer-cell="days|hours|minutes|seconds"]  — labelled unit cells
 *   [data-timer-unit="days|hours|minutes|seconds"]  — numeric values
 *
 * The days cell is hidden once the countdown drops below 24 hours, and the
 * whole widget is hidden once the countdown reaches zero.
 */
(function () {
  'use strict';

  var SECONDS_PER_DAY = 86400;
  var SECONDS_PER_HOUR = 3600;
  var SECONDS_PER_MINUTE = 60;

  var UNITS = ['days', 'hours', 'minutes', 'seconds'];

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

    UNITS.forEach(function (unit) {
      var entry = el._units[unit];

      if (!entry || !entry.value) {
        return;
      }

      if (unit === 'days') {
        entry.value.textContent = String(days);
        entry.cell.classList.toggle('hidden', days === 0);
      } else if (unit === 'hours') {
        entry.value.textContent = pad(hours);
      } else if (unit === 'minutes') {
        entry.value.textContent = pad(minutes);
      } else {
        entry.value.textContent = pad(seconds);
      }
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

    el._units = {};

    UNITS.forEach(function (unit) {
      var cell = el.querySelector('[data-timer-cell="' + unit + '"]');

      if (!cell) {
        return;
      }

      el._units[unit] = {
        cell: cell,
        value: cell.querySelector('[data-timer-unit="' + unit + '"]')
      };
    });

    el._dcTimerStarted = true;
    tick(el);
    el._timerId = setInterval(tick, 1000, el);
  }

  /**
   * (Re)start the countdown for an element with a possibly new deadline.
   * The variant-switching code updates data-countdown-until and calls this so
   * the timer picks up the current variant's special end date immediately —
   * the element may have been hidden/stopped before.
   */
  function resetTimer(el) {
    if (!el) {
      return;
    }

    if (el._timerId) {
      clearInterval(el._timerId);
      el._timerId = null;
    }

    el._dcTimerStarted = false;
    el.style.display = '';

    initTimer(el);
  }

  function initTimers(root) {
    var scope = root || document;

    if (!scope.querySelectorAll) {
      return;
    }

    scope.querySelectorAll('[data-countdown-until]').forEach(initTimer);
  }

  window.dcInitSaleTimers = initTimers;
  window.dcResetSaleTimer = resetTimer;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initTimers(document);
    });
  } else {
    initTimers(document);
  }
})();
