/**
 * dc-icons.js  —  Font Awesome → Lucide automatic shim
 *
 * Replaces every <i class="fa fa-*"> element with an equivalent Lucide SVG
 * icon. Runs after DOMContentLoaded and re-runs after Bootstrap dropdowns open
 * (dynamic HTML injected by checkout.twig JS).
 *
 * Requires lucide UMD to be loaded before this script.
 */
(function () {
  'use strict';

  /* ── FA class → Lucide icon name map ─────────────────── */
  var MAP = {
    // Navigation / UI
    'fa-search':            'search',
    'fa-bars':              'menu',
    'fa-reorder':           'menu',
    'fa-navicon':           'menu',
    'fa-times':             'x',
    'fa-close':             'x',
    'fa-remove':            'x',
    'fa-times-circle':      'x-circle',
    'fa-check':             'check',
    'fa-check-circle':      'check-circle',
    'fa-exclamation-circle':'alert-circle',
    'fa-warning':           'alert-triangle',
    'fa-info-circle':       'info',
    'fa-caret-down':        'chevron-down',
    'fa-caret-up':          'chevron-up',
    'fa-caret-right':       'chevron-right',
    'fa-caret-left':        'chevron-left',
    'fa-angle-down':        'chevron-down',
    'fa-angle-up':          'chevron-up',
    'fa-angle-right':       'chevron-right',
    'fa-angle-left':        'chevron-left',
    'fa-chevron-down':      'chevron-down',
    'fa-chevron-up':        'chevron-up',
    'fa-chevron-right':     'chevron-right',
    'fa-chevron-left':      'chevron-left',
    // E-commerce
    'fa-shopping-cart':     'shopping-cart',
    'fa-share':             'arrow-right-from-line',
    'fa-reply':             'corner-up-left',
    'fa-refresh':           'refresh-cw',
    'fa-rotate-right':      'refresh-cw',
    'fa-exchange':          'arrows-left-right',
    'fa-gift':              'gift',
    'fa-tag':               'tag',
    'fa-tags':              'tags',
    'fa-ticket':            'ticket',
    // Account / Auth
    'fa-user':              'user',
    'fa-user-o':            'user',
    'fa-user-plus':         'user-plus',
    'fa-sign-in':           'log-in',
    'fa-sign-out':          'log-out',
    'fa-lock':              'lock',
    'fa-unlock':            'unlock',
    // Content
    'fa-heart':             'heart',
    'fa-heart-o':           'heart',
    'fa-star':              'star',
    'fa-star-o':            'star',
    'fa-eye':               'eye',
    'fa-eye-slash':         'eye-off',
    'fa-calendar':          'calendar',
    'fa-clock-o':           'clock',
    'fa-clock':             'clock',
    'fa-pencil':            'pencil',
    'fa-edit':              'pencil',
    'fa-trash':             'trash-2',
    'fa-trash-o':           'trash-2',
    // Communication
    'fa-phone':             'phone',
    'fa-envelope':          'mail',
    'fa-envelope-o':        'mail',
    // Files / Actions
    'fa-upload':            'upload',
    'fa-download':          'download',
    'fa-cloud-download':    'cloud-download',
    'fa-file':              'file',
    'fa-list-alt':          'clipboard-list',
    'fa-list':              'list',
    'fa-home':              'home',
    'fa-map-marker':        'map-pin',
    'fa-credit-card':       'credit-card',
    'fa-bank':              'landmark',
    'fa-tick':              'check',
    'fa-spinner':           'loader',
    'fa-print':             'printer',
  };

  /* Utility classes to skip when scanning */
  var SKIP = /^(fa|fa-fw|fa-lg|fa-2x|fa-3x|fa-4x|fa-5x|fa-spin|fa-pulse|fa-stack|fa-stack-1x|fa-stack-2x|fa-rotate-\d+|fa-flip-\w+|fa-inverse)$/;

  function iconNameFromClasses(classList) {
    for (var i = 0; i < classList.length; i++) {
      var c = classList[i];
      if (c.startsWith('fa-') && !SKIP.test(c) && MAP[c]) {
        return MAP[c];
      }
    }
    return null;
  }

  function replace(root) {
    var els = (root || document).querySelectorAll('i[class*="fa-"]');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      /* Skip if already converted */
      if (el.dataset && el.dataset.lucide) continue;

      var classes = Array.from ? Array.from(el.classList) : [].slice.call(el.classList);
      var name = iconNameFromClasses(classes);
      if (!name) continue;

      var svg = document.createElement('i');
      svg.setAttribute('data-lucide', name);

      /* Carry over sizing / utility classes that Lucide uses via CSS */
      if (classes.indexOf('dc-icon-xs') !== -1) svg.className = 'dc-icon-xs';
      else if (classes.indexOf('dc-icon-sm') !== -1) svg.className = 'dc-icon-sm';
      else if (classes.indexOf('dc-icon-md') !== -1) svg.className = 'dc-icon-md';

      el.replaceWith(svg);
    }

    if (window.lucide) {
      lucide.createIcons({ nameAttr: 'data-lucide' });
    }
  }

  /* Handle fa-stack star ratings: replace the whole span.fa-stack */
  function replaceStacks(root) {
    var stacks = (root || document).querySelectorAll('span.fa-stack');
    for (var i = 0; i < stacks.length; i++) {
      var stack = stacks[i];
      var hasFill = stack.querySelector('.fa-star:not(.fa-star-o)');
      var svg = document.createElement('i');
      svg.setAttribute('data-lucide', 'star');
      svg.className = 'dc-star-icon' + (hasFill ? ' dc-star-filled' : '');
      stack.replaceWith(svg);
    }
    if (window.lucide) lucide.createIcons({ nameAttr: 'data-lucide' });
  }

  function runAll(root) {
    replaceStacks(root);
    replace(root);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { runAll(); });
  } else {
    runAll();
  }

  /* Re-run when Bootstrap dropdowns inject dynamic HTML */
  document.addEventListener('shown.bs.dropdown', function (e) {
    if (e.target) runAll(e.target.closest('.dropdown') || e.target);
  });

  /* Expose for manual calls (e.g. after AJAX cart updates) */
  window.dcIcons = { refresh: runAll };
})();
