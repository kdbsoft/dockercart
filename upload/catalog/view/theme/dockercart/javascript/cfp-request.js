/**
 * DockerCart — Call for Price "Request" modal.
 *
 * Renders a one-click order modal for call-for-price products in
 * "request" mode: name, phone, optional email and comment. Submits to
 * extension/module/dockercart_cfp_request/request which creates a real
 * order without touching the customer's cart.
 *
 * Trigger: any element with [data-dc-cfp-request] and data-product-id.
 * Labels come from window.dcLang (published by common/header).
 */
(function () {
  'use strict';

  var L = window.dcLang || {};

  function t(key, fallback) {
    return (L[key] !== undefined && L[key] !== '') ? L[key] : fallback;
  }

  /* ── Modal DOM ─────────────────────────────────────────────── */

  var modal = null;
  var state = {
    productId: 0,
    submitting: false,
    qtyMin: 1,
    qtyStep: 1
  };

  function dcFormatQty(value) {
    var fixed = (Math.round(value * 100) / 100).toFixed(2);
    return fixed.replace(/\.00$/, '').replace(/(\.\d*[1-9])0$/, '$1');
  }

  function dcNormalizeQty(rawValue, fallback) {
    var normalized = String(rawValue).replace(',', '.').trim();
    var parsed = parseFloat(normalized);

    if (isNaN(parsed)) {
      parsed = (typeof fallback === 'number') ? fallback : state.qtyMin;
    }

    if (parsed < state.qtyMin) {
      parsed = state.qtyMin;
    }

    var stepUnits = Math.round((parsed * 100) / Math.round(state.qtyStep * 100));
    var snapped = stepUnits * state.qtyStep;

    if (snapped < state.qtyMin) {
      snapped = state.qtyMin;
    }

    return Math.round(snapped * 100) / 100;
  }

  function dcChangeModalQty(delta) {
    var inp = modal.querySelector('#dc-cfp-quantity');
    if (!inp) return;

    var current = dcNormalizeQty(inp.value, state.qtyMin);
    var next = current + ((delta < 0 ? -1 : 1) * state.qtyStep);

    if (next < state.qtyMin) {
      next = state.qtyMin;
    }

    inp.value = dcFormatQty(dcNormalizeQty(next, state.qtyMin));
  }

  function buildModal() {
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'dc-cfp-modal';
    modal.className = 'fixed inset-0 z-[10005] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-300';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-hidden', 'true');

    var overlay = document.createElement('div');
    overlay.className = 'absolute inset-0 bg-black/50 backdrop-blur-sm';
    overlay.addEventListener('click', closeModal);
    modal.appendChild(overlay);

    var panel = document.createElement('div');
    panel.className = 'relative w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 transform transition-transform duration-300 scale-95';
    panel.innerHTML =
      '<div class="flex items-start justify-between gap-3 mb-1">' +
        '<h3 class="text-lg font-extrabold text-gray-900 leading-tight" id="dc-cfp-title"></h3>' +
        '<button type="button" class="dc-cfp-close flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition" aria-label="' + t('cfp_request_close', 'Close') + '">' +
          '<i data-lucide="x" class="w-4 h-4 text-gray-500"></i>' +
        '</button>' +
      '</div>' +
      '<p class="text-sm text-gray-500 mb-4" id="dc-cfp-product"></p>' +
      '<form id="dc-cfp-form" novalidate>' +
        '<input type="hidden" name="product_id" value="0">' +
        '<input type="hidden" name="variant_id" value="0">' +
        '<div class="mb-3">' +
          '<label for="dc-cfp-quantity" class="block text-sm font-semibold text-gray-700 mb-1">' + t('cfp_request_quantity', 'Quantity') + '</label>' +
          '<div class="inline-flex items-center border border-gray-300 rounded-xl overflow-hidden">' +
            '<button type="button" class="dc-cfp-qty-btn w-10 h-10 text-lg font-bold text-gray-500 hover:bg-gray-50 transition" data-delta="-1">−</button>' +
            '<input type="number" name="quantity" id="dc-cfp-quantity" value="1" min="1" step="1" class="w-16 text-center text-sm font-semibold border-0 outline-none py-2 bg-transparent" />' +
            '<button type="button" class="dc-cfp-qty-btn w-10 h-10 text-lg font-bold text-gray-500 hover:bg-gray-50 transition" data-delta="1">+</button>' +
          '</div>' +
        '</div>' +
        '<div class="mb-3">' +
          '<label for="dc-cfp-name" class="block text-sm font-semibold text-gray-700 mb-1">' + t('cfp_request_name', 'Name') + ' *</label>' +
          '<input type="text" name="name" id="dc-cfp-name" autocomplete="name" class="dc-cfp-input w-full px-3.5 py-2.5 text-sm border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />' +
        '</div>' +
        '<div class="mb-3">' +
          '<label for="dc-cfp-telephone" class="block text-sm font-semibold text-gray-700 mb-1">' + t('cfp_request_phone', 'Phone') + ' *</label>' +
          '<input type="tel" name="telephone" id="dc-cfp-telephone" autocomplete="tel" class="dc-cfp-input w-full px-3.5 py-2.5 text-sm border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />' +
        '</div>' +
        '<div class="mb-3">' +
          '<label for="dc-cfp-email" class="block text-sm font-semibold text-gray-700 mb-1">' + t('cfp_request_email', 'E-mail') + '</label>' +
          '<input type="email" name="email" id="dc-cfp-email" autocomplete="email" class="dc-cfp-input w-full px-3.5 py-2.5 text-sm border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />' +
        '</div>' +
        '<div class="mb-4">' +
          '<label for="dc-cfp-comment" class="block text-sm font-semibold text-gray-700 mb-1">' + t('cfp_request_comment', 'Comment') + '</label>' +
          '<textarea name="comment" id="dc-cfp-comment" rows="3" class="dc-cfp-input w-full px-3.5 py-2.5 text-sm border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>' +
        '</div>' +
        '<div class="hidden mb-3 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm px-3.5 py-2.5" id="dc-cfp-error"></div>' +
        '<div class="hidden mb-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-3.5 py-2.5 font-semibold" id="dc-cfp-success"></div>' +
        '<button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold rounded-xl transition text-sm shadow-md shadow-blue-200" id="dc-cfp-submit">' +
          t('cfp_request_submit', 'Send request') +
        '</button>' +
      '</form>';
    modal.appendChild(panel);

    document.body.appendChild(modal);
    return modal;
  }

  function refreshIcons() {
    if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
      lucide.createIcons();
    }
  }

  /**
   * Attach a qty source to a trigger button (used by quick-view when it
   * builds a CFP button dynamically). Falls back to the product minimum.
   */
  function updateCfpQtySource(btn, minimum) {
    if (!btn) return;
    var qty = (typeof minimum === 'number' && minimum > 0) ? minimum : 1;
    var tempInput = document.createElement('input');
    tempInput.type = 'number';
    tempInput.value = String(qty);
    tempInput.setAttribute('min', String(qty));
    tempInput.setAttribute('step', '1');
    tempInput.id = 'dc-cfp-qty-source-' + (btn.getAttribute('data-product-id') || 'x');
    btn.setAttribute('data-dc-cfp-qty-source', '#' + tempInput.id);
    document.body.appendChild(tempInput);
  }

  window.dcUpdateCfpQtySource = updateCfpQtySource;

  function openModal() {
    var m = buildModal();
    m.classList.remove('opacity-0', 'pointer-events-none');
    m.classList.add('opacity-100');
    m.setAttribute('aria-hidden', 'false');
    var panel = m.querySelector('.transform');
    if (panel) {
      requestAnimationFrame(function () {
        panel.classList.remove('scale-95');
        panel.classList.add('scale-100');
      });
    }
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0', 'pointer-events-none');
    modal.setAttribute('aria-hidden', 'true');
  }

  function setError(msg) {
    var el = modal.querySelector('#dc-cfp-error');
    if (!el) return;
    if (msg) {
      el.textContent = msg;
      el.classList.remove('hidden');
    } else {
      el.classList.add('hidden');
    }
  }

  function clearFieldErrors() {
    var inputs = modal.querySelectorAll('.dc-cfp-input');
    inputs.forEach(function (inp) {
      inp.classList.remove('border-red-400', 'ring-red-200');
    });
  }

  /* ── Helpers ──────────────────────────────────────────────── */

  /**
   * Extract product id / variant id from a product URL:
   *   /variant-123   (configurable product card)
   *   ?product_id=42 or /product-42.html (fallback)
   */
  function parseProductHref(href) {
    var variantId = 0;
    var productId = 0;

    if (href) {
      var variantMatch = href.match(/\/variant-(\d+)/);
      if (variantMatch) {
        variantId = parseInt(variantMatch[1], 10) || 0;
      }
      var m = href.match(/[?&]product_id=(\d+)/);
      if (m) {
        productId = parseInt(m[1], 10) || 0;
      }
      if (!productId) {
        var slugMatch = href.match(/(?:product|product_id)[-_]?(\d+)(?:\.html|\/|$)/);
        if (slugMatch) {
          productId = parseInt(slugMatch[1], 10) || 0;
        }
      }
    }
    return { productId: productId, variantId: variantId };
  }

  /**
   * Prefill name/phone/email from the customer's session when the page
   * exposes them (common/header publishes dcCustomer).
   */
  function prefillCustomer() {
    var c = window.dcCustomer;
    if (!c) return;
    var form = modal.querySelector('#dc-cfp-form');
    if (!form) return;
    var name = form.querySelector('[name="name"]');
    var phone = form.querySelector('[name="telephone"]');
    var email = form.querySelector('[name="email"]');
    if (name && !name.value && c.firstname) name.value = c.firstname;
    if (phone && !phone.value && c.telephone) phone.value = c.telephone;
    if (email && !email.value && c.email) email.value = c.email;
  }

  function initPhoneMask() {
    var input = modal.querySelector('#dc-cfp-telephone');
    if (!input) return;
    var fmt = t('telephone_mask', '');
    if (fmt && typeof window.DockercartPhoneMask !== 'undefined') {
      window.DockercartPhoneMask.init(input, fmt);
    }
  }

  /**
   * A phone is valid when it matches the country mask (if one is active)
   * or, without a mask, contains at least 5 digits — mirroring the
   * controller-side validatePhone().
   */
  function isPhoneValid(value) {
    var input = modal.querySelector('#dc-cfp-telephone');
    var fmt = t('telephone_mask', '');
    if (input && input._phonemask_format) fmt = input._phonemask_format;
    if (fmt && typeof window.DockercartPhoneMask !== 'undefined') {
      return window.DockercartPhoneMask.validate(value, fmt);
    }
    return (value.replace(/\D/g, '').length >= 5);
  }

  /* ── Open from trigger ────────────────────────────────────── */

  function openFromTrigger(btn) {
    var productId = parseInt(btn.getAttribute('data-product-id') || '0', 10) || 0;
    var variantId = parseInt(btn.getAttribute('data-variant-id') || '0', 10) || 0;

    // Resolve variant from the surrounding card URL if not set explicitly.
    if (!variantId) {
      var card = btn.closest('.product-card');
      if (card) {
        var parsed = parseProductHref(card.getAttribute('data-href') || '');
        if (!productId && parsed.productId) productId = parsed.productId;
        if (parsed.variantId) variantId = parsed.variantId;
      }
    }

    // On the product page the current variant is exposed via #dc-variant-id
    // (set by product.twig for configurable products).
    if (!variantId) {
      var v = document.getElementById('dc-variant-id');
      if (v) variantId = parseInt(v.getAttribute('data-variant-id') || v.textContent || '0', 10) || 0;
    }

    if (!productId) return;

    state.productId = productId;

    var form = modal.querySelector('#dc-cfp-form');
    form.elements['product_id'].value = productId;
    form.elements['variant_id'].value = variantId;
    // Re-enable fields in case the previous submission disabled them.
    form.querySelectorAll('input, textarea').forEach(function (el) {
      el.disabled = false;
    });
    form.reset();
    clearFieldErrors();
    setError(null);

    // Product label: prefer the card's product name, else a generic label.
    var productName = '';
    var card = btn.closest('.product-card');
    if (card) {
      var nameEl = card.querySelector('h3 a, .dc-list-card h3 a');
      if (nameEl) productName = nameEl.textContent.trim();
    }
    if (!productName) {
      var pageName = document.getElementById('dc-product-name');
      if (pageName) productName = pageName.textContent.trim();
    }

    modal.querySelector('#dc-cfp-title').textContent = t('cfp_request_title', 'Price request');
    modal.querySelector('#dc-cfp-product').textContent = productName;

    // Qty source: when the trigger points at an external qty input
    // (product page), read it and prefill the modal field. Otherwise keep
    // the default (1). The modal lets the customer adjust the quantity.
    var qtyInput = form.elements['quantity'];
    var qtyMin = 1;
    var qtyStep = 1;

    if (qtyInput) {
      var qtySource = btn.getAttribute('data-dc-cfp-qty-source');
      if (qtySource) {
        var src = document.querySelector(qtySource);
        if (src) {
          var srcMin = parseFloat(src.getAttribute('min'));
          var srcStep = parseFloat(src.getAttribute('step'));
          if (!isNaN(srcMin) && srcMin > 0) qtyMin = srcMin;
          if (!isNaN(srcStep) && srcStep > 0) qtyStep = srcStep;
          var qty = parseFloat(src.value);
          if (!isNaN(qty) && qty >= qtyMin) {
            qtyInput.value = dcFormatQty(qty);
          }
        }
      }
    }

    // Expose min/step to the modal stepper so qty snaps to the product rules.
    state.qtyMin = qtyMin;
    state.qtyStep = qtyStep;
    var qtyModal = modal.querySelector('#dc-cfp-quantity');
    if (qtyModal) {
      qtyModal.setAttribute('min', String(qtyMin));
      qtyModal.setAttribute('step', String(qtyStep));
      if (!qtyInput.value || parseFloat(qtyInput.value) < qtyMin) {
        qtyInput.value = dcFormatQty(qtyMin);
      }
      qtyModal.value = qtyInput.value;
    }

    prefillCustomer();
    initPhoneMask();
    openModal();

    var nameInput = modal.querySelector('#dc-cfp-name');
    if (nameInput) nameInput.focus();
  }

  /* ── Submit ───────────────────────────────────────────────── */

  function handleSubmit(e) {
    e.preventDefault();
    if (state.submitting || !modal) return;

    var form = modal.querySelector('#dc-cfp-form');
    var submitBtn = modal.querySelector('#dc-cfp-submit');
    var successEl = modal.querySelector('#dc-cfp-success');

    // Client-side validation mirrors the controller rules.
    var name = (form.elements['name'].value || '').trim();
    var phone = (form.elements['telephone'].value || '').trim();
    var email = (form.elements['email'].value || '').trim();

    if (!name) {
      setError(t('cfp_request_error_name', 'Please enter your name'));
      return;
    }
    if (!isPhoneValid(phone)) {
      setError(t('cfp_request_error_phone', 'Please enter a valid phone number'));
      return;
    }
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setError(t('cfp_request_error_email', 'Please enter a valid e-mail'));
      return;
    }

    state.submitting = true;
    submitBtn.disabled = true;
    submitBtn.textContent = t('cfp_request_loading', 'Sending...');
    successEl.classList.add('hidden');

    // Carry the visible qty field value (normalized) into the form data.
    var qtyModal = modal.querySelector('#dc-cfp-quantity');
    if (qtyModal) {
      form.elements['quantity'].value = dcFormatQty(dcNormalizeQty(qtyModal.value, state.qtyMin));
    }

    var body = new FormData(form);

    fetch('index.php?route=extension/module/dockercart_cfp_request/request', {
      method: 'POST',
      body: body,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        state.submitting = false;
        submitBtn.disabled = false;
        submitBtn.textContent = t('cfp_request_submit', 'Send request');

        if (json && json.success) {
          successEl.textContent = json.success;
          successEl.classList.remove('hidden');
          setError(null);
          form.querySelectorAll('input, textarea').forEach(function (el) {
            el.disabled = true;
          });
          var qtyBtn = modal.querySelectorAll('.dc-cfp-qty-btn');
          if (qtyBtn) {
            qtyBtn.forEach(function (b) { b.disabled = true; });
          }
        } else if (json && json.error) {
          setError(json.error);
        } else {
          setError(t('cfp_request_error_general', 'Something went wrong. Please try again.'));
        }
      })
      .catch(function () {
        state.submitting = false;
        submitBtn.disabled = false;
        submitBtn.textContent = t('cfp_request_submit', 'Send request');
        setError(t('cfp_request_error_general', 'Something went wrong. Please try again.'));
      });
  }

  /* ── Init ─────────────────────────────────────────────────── */

  document.addEventListener('DOMContentLoaded', function () {
    buildModal();
    refreshIcons();

    // Modal qty stepper (the buttons are not inside the form element).
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.dc-cfp-qty-btn');
      if (!btn) return;
      var delta = parseInt(btn.getAttribute('data-delta'), 10);
      if (delta === 1 || delta === -1) {
        dcChangeModalQty(delta);
      }
    });

    // Capture phase: fire before inline onclick handlers (e.g. a card's
    // "go to product" navigation) so the modal opens instead of a redirect.
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-dc-cfp-request]');
      if (btn) {
        e.preventDefault();
        e.stopPropagation();
        openFromTrigger(btn);
        return;
      }
      var close = e.target.closest('.dc-cfp-close');
      if (close) {
        closeModal();
      }
    }, true);

    // Sync the modal qty input into the hidden quantity field on submit.
    var form = modal.querySelector('#dc-cfp-form');
    if (form) {
      form.addEventListener('submit', function () {
        var qtyModal = modal.querySelector('#dc-cfp-quantity');
        if (qtyModal) {
          form.elements['quantity'].value = dcFormatQty(dcNormalizeQty(qtyModal.value, state.qtyMin));
        }
      });
      form.addEventListener('submit', handleSubmit);
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' || e.keyCode === 27) {
        closeModal();
      }
    });
  });
})();
