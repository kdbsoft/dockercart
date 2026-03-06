
/*
 * DockerCart Filter Module for DockerCart
 * 
 * @author Mykyta Tkachenko
 * @license MIT
 * @version 2.0.0
 * 
 * FEATURES:
 * - Preserve sort, order, limit, page parameters when filtering
 * - Support for instant and button-based filter modes
 * - Mobile-responsive filter interface
 * - Price range slider with min/max inputs
 * - URL-based filter state management
 * 
 * PARAMETER PRESERVATION:
 * When applying, removing, or resetting filters, the following parameters are preserved:
 * - Navigation: route, path
 * - Sorting: sort, order
 * - Pagination: limit, page
 * - Search: search, tag, manufacturer_id
 */

(function () {
  'use strict';

  const STORAGE_KEY = 'dockercart_filter_state';

  const DockercartFilter = {
    config: {
      formSelector: '#dockercart-filter-form',
      applyBtnSelector: '#button-filter',
      resetBtnSelector: '#button-reset',
      toggleBtnSelector: '.filter-toggle',
      filterGroupSelector: '.filter-group',
      linkSelector: '.filter-link',
      productContainerSelector: '.product-layout',
      contentSelector: '#content',
      filterMode: 'button',
      seoMode: false,
      debugMode: true
    },

    log: function() {
      if (this.config.debugMode) {
        console.log.apply(console, arguments);
      }
    },

    warn: function() {
      if (this.config.debugMode) {
        console.warn.apply(console, arguments);
      }
    },

    error: function() {
      if (this.config.debugMode) {
        console.error.apply(console, arguments);
      }
    },

    init: function () {

      const filterContainer = document.querySelector('.dockercart-filter');
      if (filterContainer) {
        this.config.debugMode = filterContainer.getAttribute('data-debug') === '1';
        DockercartFilter.log('Debug mode enabled');
      }

      const filterForm = document.querySelector(this.config.formSelector);
      if (filterForm) {
        this.config.filterMode = filterForm.getAttribute('data-filter-mode') || window.dockercartFilterMode || 'button';
        this.config.seoMode = filterForm.getAttribute('data-seo-mode') === '1' || window.dockercartSeoMode || false;
      }

      this.loadState();
      this.bindEvents();
      this.applyToggleStates();
      this.restoreCheckboxesFromUrl();
      this.initShowMore();
      this.updateDynamicHeading();

      if (this.config.debugMode) {
        setTimeout(() => {
          const params = new URLSearchParams(window.location.search);
          params.forEach((value, key) => {
            if (key.startsWith('attribute[')) {
              const match = key.match(/attribute\[(\d+)\]/);
              if (match) {
                const attrId = match[1];
                const values = value.split(',');
                values.forEach(val => {
                  const checkbox = document.querySelector(
                    this.config.formSelector + ' input[name="attribute[' + attrId + ']"][value="' + val.replace(/"/g, '\\"') + '"]'
                  );
                  if (checkbox) {
                    DockercartFilter.log('AFTER 1 SEC - Checkbox state:', {
                      selector: 'attribute[' + attrId + '][value="' + val + '"]',
                      checked: checkbox.checked,
                      hasAttribute: checkbox.hasAttribute('checked'),
                      visible: checkbox.offsetParent !== null
                    });
                  }
                });
              }
            }
          });
        }, 1000);
      }
    },

    bindEvents: function () {
      const self = this;

      document.addEventListener('click', function (e) {
        let target = e.target;

        while (target && target !== document) {
          if (target.id === 'button-filter') {
            e.preventDefault();
            self.applyFilter();
            return;
          }

          if (target.id === 'button-reset') {
            e.preventDefault();
            self.resetFilter();
            return;
          }

          if (target.id === 'button-reset-filters') {
            e.preventDefault();
            self.resetFilter();
            return;
          }

          if (target.classList && target.classList.contains('filter-toggle')) {
            e.preventDefault();
            self.toggleFilterGroup(target);
            return;
          }

          if (target.classList && target.classList.contains('filter-remove')) {
            e.preventDefault();

            if (target.href && target.href !== '#' && !target.getAttribute('data-filter-type')) {
              window.location.href = target.href;
            } else {

              const filterType = target.getAttribute('data-filter-type');
              const filterId = target.getAttribute('data-filter-id');
              const filterValue = target.getAttribute('data-filter-value');

              if (filterType && filterId) {
                self.removeFilter(filterType, filterId, filterValue);
              }
            }
            return;
          }

          if (target.classList && target.classList.contains('filter-show-more')) {
            e.preventDefault();
            self.toggleShowMore(target);
            return;
          }

          target = target.parentNode;
        }
      });

      const form = document.querySelector(this.config.formSelector);
      if (form) {
        form.addEventListener('change', function (e) {
          const target = e.target;

          if (self.config.filterMode === 'instant') {

            if (target.type === 'checkbox') {

              self.toggleFilter(target);
              return;
            }

            else {
              self.applyFilter();
              return;
            }
          }
        });

        const links = document.querySelectorAll(this.config.linkSelector);
        if (links) {
          links.forEach((link) => { 
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const label = e.target.closest('label');
                if (label) {
                  const checkbox = label.querySelector('input[type="checkbox"]');
                  if (checkbox) {
                    checkbox.checked = !checkbox.checked;

                    const event = new Event('change', { bubbles: true });
                    checkbox.dispatchEvent(event);
                  }
                }
                return false;
            });
          });
        }
      }
    },

    toggleFilterGroup: function (btn) {
      const groupId = btn.getAttribute('data-group-id') || btn.closest(this.config.filterGroupSelector).id;
      const group = document.getElementById(groupId);

      if (!group) return;

      const content = group.querySelector('[data-group-content]');
      if (!content) return;

      const isVisible = content.style.display !== 'none';
      content.style.display = isVisible ? 'none' : 'block';

      if (isVisible) {
        btn.classList.add('collapsed');
      } else {
        btn.classList.remove('collapsed');
      }

      this.saveToggleState(groupId, !isVisible);
    },

    saveToggleState: function (groupId, isVisible) {
      try {
        const state = this.getStorageState();
        state[groupId] = isVisible;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
      } catch (err) {
        DockercartFilter.warn('localStorage not available:', err);
      }
    },

    loadState: function () {
      try {
        const state = this.getStorageState();

      } catch (err) {
        DockercartFilter.warn('Error loading filter state:', err);
      }
    },

    getStorageState: function () {
      try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return stored ? JSON.parse(stored) : {};
      } catch (err) {
        return {};
      }
    },

    applyToggleStates: function () {
      const state = this.getStorageState();

      Object.entries(state).forEach(([groupId, isVisible]) => {
        const group = document.getElementById(groupId);
        if (!group) return;

        const content = group.querySelector('[data-group-content]');
        const btn = group.querySelector('.filter-toggle');

        if (content) {
          content.style.display = isVisible ? 'block' : 'none';
        }

        if (btn) {
          btn.classList.toggle('collapsed', !isVisible);
        }
      });
    },

    restoreCheckboxesFromUrl: function () {
      const params = new URLSearchParams(window.location.search);

      DockercartFilter.log('=== RESTORE FROM URL DEBUG ===');
      DockercartFilter.log('URL:', window.location.search);
      DockercartFilter.log('All params:', Array.from(params.entries()));

      document.querySelectorAll(this.config.formSelector + ' input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = false;
        checkbox.removeAttribute('checked');
      });

      document.querySelectorAll(this.config.formSelector + ' select').forEach(select => {
        select.querySelectorAll('option').forEach((option, index) => {
          option.selected = (index === 0);
        });
      });

      if (params.has('dcf')) {
        try {

          const dcfValue = params.get('dcf');
          const hexBytes = dcfValue.match(/.{1,2}/g);
          if (!hexBytes) {
            throw new Error('Invalid HEX format');
          }

          // Convert hex byte pairs to a Uint8Array, then decode as UTF-8.
          // Using TextDecoder ensures proper decoding for non-Latin scripts (Cyrillic/Arabic/etc.).
          let jsonString;
          try {
            const bytes = new Uint8Array(hexBytes.map(h => parseInt(h, 16)));

            if (typeof TextDecoder !== 'undefined') {
              jsonString = new TextDecoder('utf-8').decode(bytes);
            } else {
              // Fallback for older browsers: build binary string and decode
              let binary = '';
              for (let i = 0; i < bytes.length; i++) {
                binary += String.fromCharCode(bytes[i]);
              }
              try {
                // decodeURIComponent(escape(...)) will convert binary string -> proper UTF-8 string
                jsonString = decodeURIComponent(escape(binary));
              } catch (e) {
                // If fallback fails, fall back to binary (may be mojibake)
                jsonString = binary;
              }
            }
          } catch (e) {
            throw new Error('Failed to decode dcf hex to string: ' + e.message);
          }

          DockercartFilter.log('Decoded JSON:', jsonString);

          const data = JSON.parse(jsonString);
          DockercartFilter.log('Parsed data:', data);

          if (data.price_min) {
            const input = document.querySelector(this.config.formSelector + ' input[name="price_min"]');
            if (input) input.value = data.price_min;
          }
          if (data.price_max) {
            const input = document.querySelector(this.config.formSelector + ' input[name="price_max"]');
            if (input) input.value = data.price_max;
          }

          if (data.manufacturers && Array.isArray(data.manufacturers)) {
            data.manufacturers.forEach(manId => {
              const checkbox = document.querySelector(
                this.config.formSelector + ' input[name="manufacturer[]"][value="' + manId + '"]'
              );
              if (checkbox) {
                checkbox.checked = true;
                checkbox.setAttribute('checked', 'checked');
                DockercartFilter.log('Checked manufacturer:', manId);
              }
            });
          }

          if (data.attributes && typeof data.attributes === 'object') {
            Object.entries(data.attributes).forEach(([attrId, values]) => {
              if (Array.isArray(values)) {
                values.forEach(val => {
                  this.checkAttributeCheckbox(attrId, val);
                  DockercartFilter.log('Checked attribute:', attrId, 'value:', val);
                });
              }
            });
          }

          if (data.options && typeof data.options === 'object') {
            Object.entries(data.options).forEach(([optId, values]) => {
              if (Array.isArray(values)) {
                values.forEach(valId => {
                  const checkbox = document.querySelector(
                    this.config.formSelector + ' input[name="option[' + optId + ']"][value="' + valId + '"]'
                  );
                  if (checkbox) {
                    checkbox.checked = true;
                    checkbox.setAttribute('checked', 'checked');
                    DockercartFilter.log('Checked option:', optId, 'value:', valId);
                  }
                });
              }
            });
          }
        } catch (e) {
          console.error('Error decoding dcf parameter:', e);
        }

        return;
      }
    },

    checkAttributeCheckbox: function(attrId, value) {
console.log('Checking attribute checkbox - attrId:', attrId, 'value:', value);
      const valueStr = String(value);
      console.log(valueStr)
      const selector = this.config.formSelector + ' input[name="attribute[' + attrId + ']"][value="' + valueStr.replace(/"/g, '\\"') + '"]';
      DockercartFilter.log('Looking for checkbox:', selector);
      const checkbox = document.querySelector(selector);
      if (checkbox) {
        DockercartFilter.log('Found checkbox, checking it');
        checkbox.checked = true;
        checkbox.setAttribute('checked', 'checked');
      } else {

        const allCheckboxes = document.querySelectorAll(
          this.config.formSelector + ' input[name="attribute[' + attrId + ']"]'
        );
        DockercartFilter.log('Found ' + allCheckboxes.length + ' checkboxes for attribute[' + attrId + ']');
        allCheckboxes.forEach(cb => {
          DockercartFilter.log('  Checkbox value:', cb.value, '(type: ' + typeof cb.value + '), looking for:', valueStr, '(type: ' + typeof valueStr + ')');
          if (String(cb.value).toLowerCase() === valueStr.toLowerCase()) {
            DockercartFilter.log('  ✓ MATCH! Checking this checkbox');
            cb.checked = true;
            cb.setAttribute('checked', 'checked');
          }
        });
      }

      const select = document.querySelector(
        this.config.formSelector + ' select[name="attribute[' + attrId + ']"]'
      );
      if (select) {
        const allOptions = select.querySelectorAll('option');
        allOptions.forEach(opt => {
          if (String(opt.value).toLowerCase() === valueStr.toLowerCase()) {
            opt.selected = true;
          }
        });
      }
    },

    checkAttributeCheckboxByHash: function(attrId, hash) {

      attrId = String(attrId).trim();
      hash = String(hash).trim();

      DockercartFilter.log('Checking attribute by hash - attrId:', attrId, 'hash:', hash);

      const selector = this.config.formSelector + ' input[name="attribute[' + attrId + ']"]';
      DockercartFilter.log('Looking for checkboxes with selector:', selector);

      const allCheckboxes = document.querySelectorAll(selector);
      DockercartFilter.log('Found ' + allCheckboxes.length + ' checkboxes for attribute ' + attrId);

      if (allCheckboxes.length === 0) {
        DockercartFilter.warn('No checkboxes found for attribute ' + attrId);
        return;
      }

      for (let checkbox of allCheckboxes) {
        const cbValue = String(checkbox.value).trim();
        const cbHash = this.md5(cbValue).substring(0, 7);
        DockercartFilter.log('Checkbox value:', cbValue, '-> MD5:', cbHash, '(looking for:', hash + ')');

        if (cbHash === hash) {
          checkbox.checked = true;
          checkbox.setAttribute('checked', 'checked');
          DockercartFilter.log('✓ Checked checkbox for attribute:', attrId, 'value:', cbValue);
          return;
        }
      }

      DockercartFilter.warn('No checkbox found for attribute ' + attrId + ' with hash ' + hash);
    },

    md5: function(str) {

      function md5core(x, len) {
        x[len >> 5] |= 0x80 << (len % 32);
        x[(((len + 64) >>> 9) << 4) + 14] = len;
        let a = 1732584193, b = -271733879, c = -1732584194, d = 271733878;
        for (let i = 0; i < x.length; i += 16) {
          let olda = a, oldb = b, oldc = c, oldd = d;
          a = md5ff(a, b, c, d, x[i], 7, -680876936);
          d = md5ff(d, a, b, c, x[i + 1], 12, -389564586);
          c = md5ff(c, d, a, b, x[i + 2], 17, 606105819);
          b = md5ff(b, c, d, a, x[i + 3], 22, -1044525330);
          a = md5ff(a, b, c, d, x[i + 4], 7, -176418552);
          d = md5ff(d, a, b, c, x[i + 5], 12, 1200080426);
          c = md5ff(c, d, a, b, x[i + 6], 17, -1473231341);
          b = md5ff(b, c, d, a, x[i + 7], 22, -45705983);
          a = md5ff(a, b, c, d, x[i + 8], 7, 1770035416);
          d = md5ff(d, a, b, c, x[i + 9], 12, -1958414417);
          c = md5ff(c, d, a, b, x[i + 10], 17, -42063);
          b = md5ff(b, c, d, a, x[i + 11], 22, -1990404162);
          a = md5ff(a, b, c, d, x[i + 12], 7, 1804603682);
          d = md5ff(d, a, b, c, x[i + 13], 12, -40341101);
          c = md5ff(c, d, a, b, x[i + 14], 17, -1502002290);
          b = md5ff(b, c, d, a, x[i + 15], 22, 1236535329);
          a = md5gg(a, b, c, d, x[i + 1], 5, -165796510);
          d = md5gg(d, a, b, c, x[i + 6], 9, -1069501632);
          c = md5gg(c, d, a, b, x[i + 11], 14, 643717713);
          b = md5gg(b, c, d, a, x[i + 0], 20, -373897302);
          a = md5gg(a, b, c, d, x[i + 5], 5, -701558691);
          d = md5gg(d, a, b, c, x[i + 10], 9, 38016083);
          c = md5gg(c, d, a, b, x[i + 15], 14, -660478335);
          b = md5gg(b, c, d, a, x[i + 4], 20, -405537848);
          a = md5gg(a, b, c, d, x[i + 9], 5, 568446438);
          d = md5gg(d, a, b, c, x[i + 14], 9, -1019803690);
          c = md5gg(c, d, a, b, x[i + 3], 14, -187363961);
          b = md5gg(b, c, d, a, x[i + 8], 20, 1163531501);
          a = md5gg(a, b, c, d, x[i + 13], 5, -1444681467);
          d = md5gg(d, a, b, c, x[i + 2], 9, -51403784);
          c = md5gg(c, d, a, b, x[i + 7], 14, 1735328473);
          b = md5gg(b, c, d, a, x[i + 12], 20, -1926607734);
          a = md5hh(a, b, c, d, x[i + 5], 4, -378558);
          d = md5hh(d, a, b, c, x[i + 8], 11, -2022574632);
          c = md5hh(c, d, a, b, x[i + 11], 16, 1839030562);
          b = md5hh(b, c, d, a, x[i + 14], 23, -35309556);
          a = md5hh(a, b, c, d, x[i + 1], 4, -1530992060);
          d = md5hh(d, a, b, c, x[i + 4], 11, 1272893353);
          c = md5hh(c, d, a, b, x[i + 7], 16, -155497632);
          b = md5hh(b, c, d, a, x[i + 10], 23, -1094730640);
          a = md5hh(a, b, c, d, x[i + 13], 4, 681279174);
          d = md5hh(d, a, b, c, x[i + 0], 11, -358537222);
          c = md5hh(c, d, a, b, x[i + 3], 16, -722521979);
          b = md5hh(b, c, d, a, x[i + 6], 23, 76029189);
          a = md5hh(a, b, c, d, x[i + 9], 4, -640364487);
          d = md5hh(d, a, b, c, x[i + 12], 11, -421815835);
          c = md5hh(c, d, a, b, x[i + 15], 16, 530742520);
          b = md5hh(b, c, d, a, x[i + 2], 23, -995338651);
          a = md5ii(a, b, c, d, x[i + 0], 6, -198630844);
          d = md5ii(d, a, b, c, x[i + 7], 10, 1126891415);
          c = md5ii(c, d, a, b, x[i + 14], 15, -1416354905);
          b = md5ii(b, c, d, a, x[i + 5], 21, -57434055);
          a = md5ii(a, b, c, d, x[i + 12], 6, 1700485571);
          d = md5ii(d, a, b, c, x[i + 3], 10, -1894986606);
          c = md5ii(c, d, a, b, x[i + 10], 15, -1051523);
          b = md5ii(b, c, d, a, x[i + 1], 21, -2054922799);
          a = md5ii(a, b, c, d, x[i + 8], 6, 1873313359);
          d = md5ii(d, a, b, c, x[i + 15], 10, -30611744);
          c = md5ii(c, d, a, b, x[i + 6], 15, -1560198380);
          b = md5ii(b, c, d, a, x[i + 13], 21, 1309151649);
          a = md5ii(a, b, c, d, x[i + 4], 6, -145523070);
          d = md5ii(d, a, b, c, x[i + 11], 10, -1120210379);
          c = md5ii(c, d, a, b, x[i + 2], 15, 718787259);
          b = md5ii(b, c, d, a, x[i + 9], 21, -343487606);
          a = ((a + olda) >>> 0);
          b = ((b + oldb) >>> 0);
          c = ((c + oldc) >>> 0);
          d = ((d + oldd) >>> 0);
        }
        return [a, b, c, d];
      }
      function md5cmn(q, a, b, x, s, t) {
        a = ((a + q) >>> 0);
        a = ((a + x) >>> 0);
        a = ((a + t) >>> 0);
        return (((a << s) | (a >>> (32 - s))) >>> 0);
      }
      function md5ff(a, b, c, d, x, s, t) {
        return md5cmn((b & c) | ((~b) & d), a, b, x, s, t);
      }
      function md5gg(a, b, c, d, x, s, t) {
        return md5cmn((b & d) | (c & (~d)), a, b, x, s, t);
      }
      function md5hh(a, b, c, d, x, s, t) {
        return md5cmn(b ^ c ^ d, a, b, x, s, t);
      }
      function md5ii(a, b, c, d, x, s, t) {
        return md5cmn(c ^ (b | (~d)), a, b, x, s, t);
      }
      function rh(x) {
        const bits = [];
        for (let i = 0; i < 32; i += 8) {
          bits.push((x >>> i) & 0xff);
        }
        return bits;
      }
      function rhex(x) {
        const bits = rh(x);
        let res = '';
        for (let i = 0; i < bits.length; i++) {
          const hex = bits[i].toString(16);
          res += (hex.length === 1 ? '0' : '') + hex;
        }
        return res;
      }

      const message = [];
      for (let i = 0; i < str.length; i++) {
        message.push(str.charCodeAt(i));
      }

      const x = [];
      for (let i = 0; i < message.length; i += 4) {
        x.push(
          (message[i] || 0) |
          ((message[i + 1] || 0) << 8) |
          ((message[i + 2] || 0) << 16) |
          ((message[i + 3] || 0) << 24)
        );
      }

      const result = md5core(x, str.length * 8);
      return rhex(result[0]) + rhex(result[1]) + rhex(result[2]) + rhex(result[3]);
    },

    checkOptionCheckbox: function(optId, value) {

      const valueStr = String(value);

      const selector = this.config.formSelector + ' input[name="option[' + optId + ']"][value="' + valueStr.replace(/"/g, '\\"') + '"]';
      DockercartFilter.log('Looking for option checkbox:', selector);
      const checkbox = document.querySelector(selector);
      if (checkbox) {
        DockercartFilter.log('Found option checkbox, checking it');
        checkbox.checked = true;
        checkbox.setAttribute('checked', 'checked');
      } else {

        const allCheckboxes = document.querySelectorAll(
          this.config.formSelector + ' input[name="option[' + optId + ']"]'
        );
        DockercartFilter.log('Found ' + allCheckboxes.length + ' checkboxes for option[' + optId + ']');
        allCheckboxes.forEach(cb => {
          DockercartFilter.log('  Checkbox value:', cb.value, '(type: ' + typeof cb.value + '), looking for:', valueStr, '(type: ' + typeof valueStr + ')');
          if (String(cb.value) === valueStr) {
            DockercartFilter.log('  ✓ MATCH! Checking this checkbox');
            cb.checked = true;
            cb.setAttribute('checked', 'checked');
          }
        });
      }

      const select = document.querySelector(
        this.config.formSelector + ' select[name="option[' + optId + ']"]'
      );
      if (select) {

        const allOptions = select.querySelectorAll('option');
        allOptions.forEach(opt => {
          if (String(opt.value) === valueStr) {
            opt.selected = true;
          }
        });
      }
    },

    applyFilter: function () {
      const formData = this.getFormData();
      const url = this.buildUrl(formData);
      window.location.href = url;
    },

    toggleFilter: function (checkbox) {

      const filterUrl = checkbox.getAttribute('data-filter-url');

      if (filterUrl && filterUrl !== '#' && filterUrl !== '') {
        DockercartFilter.log('Using pre-built SEO URL:', filterUrl);
        window.location.href = filterUrl;
        return;
      }

      const name = checkbox.getAttribute('name');
      let value = checkbox.value;
      const isChecked = checkbox.checked;

      DockercartFilter.log('Toggle filter (fallback):', name, '=', value, 'checked:', isChecked);

      let filterType = null;
      let filterId = null;

      if (name === 'manufacturer[]') {
        filterType = 'manufacturer';
        filterId = value;
      } else {
        const attrMatch = name.match(/attribute\[(\d+)\]/);
        const optMatch = name.match(/option\[(\d+)\]/);

        if (attrMatch) {
          filterType = 'attribute';
          filterId = attrMatch[1];
        } else if (optMatch) {
          filterType = 'option';
          filterId = optMatch[1];

          value = String(value);
        }
      }

      if (!filterType) {
        DockercartFilter.warn('Unknown filter type for:', name);
        return;
      }

      const params = new URLSearchParams(window.location.search);

      if (filterType === 'manufacturer') {
        const current = params.get('manufacturer');
        let manufacturers = current ? current.split(',') : [];

        if (isChecked) {
          if (!manufacturers.includes(filterId)) {
            manufacturers.push(filterId);
          }
        } else {
          manufacturers = manufacturers.filter(m => m !== filterId);
        }

        if (manufacturers.length > 0) {
          params.set('manufacturer', manufacturers.join(','));
        } else {
          params.delete('manufacturer');
        }
      } else if (filterType === 'attribute') {

        const key = 'attribute[' + filterId + ']';
        const current = params.get(key);
        let values = current ? current.split(',') : [];

        if (isChecked) {

          if (!values.some(v => String(v).toLowerCase() === String(value).toLowerCase())) {
            values.push(value);
          }
        } else {

          values = values.filter(v => String(v).toLowerCase() !== String(value).toLowerCase());
        }

        if (values.length > 0) {
          params.set(key, values.join(','));
        } else {
          params.delete(key);
        }
      } else if (filterType === 'option') {

        const key = 'option[' + filterId + ']';
        const current = params.get(key);
        let values = current ? current.split(',') : [];

        value = String(value);

        if (isChecked) {

          if (!values.includes(value)) {
            values.push(value);
          }
        } else {

          values = values.filter(v => String(v) !== value);
        }

        if (values.length > 0) {
          params.set(key, values.join(','));
        } else {
          params.delete(key);
        }
      }

      // Preserve other parameters (sort, limit, page, etc.)
      const currentParams = new URLSearchParams(window.location.search);
      const preserveParams = ['route', 'path', 'sort', 'order', 'limit', 'page', 'search', 'tag', 'manufacturer_id'];
      
      preserveParams.forEach(param => {
        if (!params.has(param) && currentParams.has(param)) {
          params.set(param, currentParams.get(param));
        }
      });

      const queryString = params.toString();
      const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
      DockercartFilter.log('Navigating to:', newUrl);
      window.location.href = newUrl;
    },

    removeFilter: function (filterType, filterId, filterValue) {
      const params = new URLSearchParams(window.location.search);

      filterId = String(filterId);
      if (filterValue) {
        filterValue = String(filterValue);
      }

      switch (filterType) {
        case 'manufacturer':
          if (params.has('manufacturer')) {
            const manufacturers = params.get('manufacturer').split(',');
            const updated = manufacturers.filter(m => m !== filterId);
            if (updated.length > 0) {
              params.set('manufacturer', updated.join(','));
            } else {
              params.delete('manufacturer');
            }
          }
          break;

        case 'attribute':
          const attrKey = 'attribute[' + filterId + ']';
          if (params.has(attrKey)) {
            const values = params.get(attrKey).split(',');
            const updated = values.filter(v => String(v).toLowerCase() !== String(filterValue).toLowerCase());
            if (updated.length > 0) {
              params.set(attrKey, updated.join(','));
            } else {
              params.delete(attrKey);
            }
          }
          break;

        case 'option':
          const optKey = 'option[' + filterId + ']';
          if (params.has(optKey)) {
            const values = params.get(optKey).split(',');
            const updated = values.filter(v => String(v) !== filterValue);
            if (updated.length > 0) {
              params.set(optKey, updated.join(','));
            } else {
              params.delete(optKey);
            }
          }
          break;

        case 'price':
          params.delete('price_min');
          params.delete('price_max');
          break;
      }

      if (!params.has('route')) {
        const currentParams = new URLSearchParams(window.location.search);
        if (currentParams.has('route')) {
          params.set('route', currentParams.get('route'));
        }
      }

      if (!params.has('path')) {
        const currentParams = new URLSearchParams(window.location.search);
        if (currentParams.has('path')) {
          params.set('path', currentParams.get('path'));
        }
      }

      // Preserve other parameters (sort, limit, page, etc.)
      const currentParams = new URLSearchParams(window.location.search);
      const preserveParams = ['sort', 'order', 'limit', 'page', 'search', 'tag', 'manufacturer_id'];
      
      preserveParams.forEach(param => {
        if (!params.has(param) && currentParams.has(param)) {
          params.set(param, currentParams.get(param));
        }
      });

      const queryString = params.toString();
      const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
      window.location.href = newUrl;
    },

    resetFilter: function () {
      const form = document.querySelector(this.config.formSelector);
      if (form) form.reset();

      document.querySelectorAll(this.config.formSelector + ' input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = false;
      });

      document.querySelectorAll(this.config.formSelector + ' select').forEach(select => {
        select.selectedIndex = 0;
      });

      const sliderMin = document.querySelector('.price-slider-min');
      const sliderMax = document.querySelector('.price-slider-max');
      const minInput = document.querySelector('input[name="price_min"]');
      const maxInput = document.querySelector('input[name="price_max"]');

      if (sliderMin && sliderMax && minInput && maxInput) {

        const originalMin = parseFloat(sliderMin.getAttribute('min')) || 0;
        const originalMax = parseFloat(sliderMax.getAttribute('max')) || 0;

        sliderMin.value = originalMin;
        sliderMax.value = originalMax;
        minInput.value = originalMin;
        maxInput.value = originalMax;

        this.log('Price sliders reset to original values:', originalMin, '-', originalMax);

        const displayMin = document.querySelector('.price-range-min-display');
        const displayMax = document.querySelector('.price-range-max-display');
        if (displayMin) displayMin.textContent = Math.round(originalMin);
        if (displayMax) displayMax.textContent = Math.round(originalMax);

        const sliderFill = document.querySelector('.price-slider-fill');
        if (sliderFill) {
          sliderFill.style.left = '0%';
          sliderFill.style.right = '0%';
        }
      }

      const base = window.location.pathname;
      const p = new URLSearchParams(window.location.search);
      const np = new URLSearchParams();

      // Preserve navigation and sorting parameters even when resetting filters
      const preserveParams = ['route', 'path', 'sort', 'order', 'limit', 'page', 'search', 'tag', 'manufacturer_id'];
      preserveParams.forEach(param => {
        if (p.has(param)) {
          np.set(param, p.get(param));
        }
      });

      const q = np.toString();
      window.location.href = base + (q ? ('?' + q) : '');
    },

    getFormData: function () {
      const data = {
        manufacturer: [],
        attribute: {},
        option: {}
      };

      // Handle price_min - include only if it differs from default
      const priceMinInput = document.querySelector(this.config.formSelector + ' input[name="price_min"]');
      if (priceMinInput) {
        const currentValue = this._getInputValue('input[name="price_min"]');
        const defaultValue = priceMinInput.getAttribute('min') || '0';
        
        if (parseFloat(currentValue) !== parseFloat(defaultValue)) {
          data.price_min = currentValue;
        }
      }

      // Handle price_max - include only if it differs from default
      const priceMaxInput = document.querySelector(this.config.formSelector + ' input[name="price_max"]');
      if (priceMaxInput) {
        const currentValue = this._getInputValue('input[name="price_max"]');
        const defaultValue = priceMaxInput.getAttribute('max') || '0';

        if (parseFloat(currentValue) !== parseFloat(defaultValue)) {
          data.price_max = currentValue;
        }
      }

      document.querySelectorAll(this.config.formSelector + ' input[name="manufacturer[]"]:checked').forEach(checkbox => {
        data.manufacturer.push(checkbox.value);
      });

      document.querySelectorAll(this.config.formSelector + ' select[name="manufacturer[]"] option:checked').forEach(option => {
        data.manufacturer.push(option.value);
      });

      document.querySelectorAll(
        this.config.formSelector + ' input[name^="attribute["]:checked, ' +
        this.config.formSelector + ' select[name^="attribute["] option:checked'
      ).forEach(el => {
        const name = el.name || el.getAttribute('name');
        if (!name) return;

        const match = name.match(/attribute\[(\d+)\]/);
        if (match) {
          const attrId = match[1];
          if (!data.attribute[attrId]) data.attribute[attrId] = [];
          data.attribute[attrId].push(el.value);
        }
      });

      document.querySelectorAll(
        this.config.formSelector + ' input[name^="option["]:checked, ' +
        this.config.formSelector + ' select[name^="option["] option:checked'
      ).forEach(el => {
        const name = el.name || el.getAttribute('name');
        if (!name) return;

        const match = name.match(/option\[(\d+)\]/);
        if (match) {
          const optId = match[1];
          if (!data.option[optId]) data.option[optId] = [];
          data.option[optId].push(el.value);
        }
      });

      return data;
    },

    buildUrl: function (data) {
      const params = new URLSearchParams(window.location.search);

      params.delete('dcf');

      const clean = new URLSearchParams();
      // Preserve navigation and sorting parameters
      const preserveParams = ['route', 'path', 'sort', 'order', 'limit', 'page', 'search', 'tag', 'manufacturer_id'];
      params.forEach((v, k) => {
        if (preserveParams.includes(k)) {
          clean.append(k, v);
        }
      });

      const filterData = {};

      if (data.price_min || data.price_max) {
        filterData.currency = window.dockercartCurrentCurrency || 'USD';
      }

      if (data.price_min) filterData.price_min = data.price_min;
      if (data.price_max) filterData.price_max = data.price_max;

      if (data.manufacturer && data.manufacturer.length) {

        filterData.manufacturers = data.manufacturer.map(v => parseInt(v)).sort((a, b) => a - b);
      }

      const attrs = {};
      Object.entries(data.attribute).forEach(([attrId, values]) => {
        if (values && values.length) {

          const sorted = values.sort();
          attrs[attrId] = sorted;
        }
      });
      if (Object.keys(attrs).length > 0) {
        filterData.attributes = attrs;
      }

      const opts = {};
      Object.entries(data.option).forEach(([optId, values]) => {
        if (values && values.length) {

          const sorted = values.map(v => parseInt(v)).sort((a, b) => a - b);
          opts[optId] = sorted;
        }
      });
      if (Object.keys(opts).length > 0) {
        filterData.options = opts;
      }

      if (Object.keys(filterData).length > 0) {
        const jsonString = JSON.stringify(filterData);
        const hexString = Array.from(jsonString)
          .map(char => char.charCodeAt(0).toString(16).padStart(2, '0'))
          .join('');
        clean.set('dcf', hexString);
      }

      const q = clean.toString();
      return window.location.pathname + (q ? ('?' + q) : '');
    },

    ajaxFilter: function () {
      const self = this;
      const data = this.getFormData();
      const params = new URLSearchParams(window.location.search);
      let categoryId = 0;

      if (params.has('path')) {
        const path = params.get('path').split('_');
        categoryId = path[path.length - 1];
      }

      const payload = {
        category_id: categoryId,
        price_min: data.price_min,
        price_max: data.price_max,
        manufacturer: data.manufacturer,
        attribute: data.attribute,
        option: data.option
      };

      const btn = document.querySelector(this.config.applyBtnSelector);
      if (btn) btn.disabled = true;

      fetch('index.php?route=extension/module/dockercart_filter/ajaxFilter', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(res => {
          if (!res.ok) {
            return res.text().then(text => {
              throw new Error(`HTTP ${res.status}: ${text}`);
            });
          }
          return res.json();
        })
        .then(json => {
          if (json.products) {
            self.updateProductList(json.products);
            if (typeof self.updatePagination === 'function') {
              self.updatePagination(json.total);
            }
          }
        })
        .catch(err => {
          this.error('AJAX Filter Error:', err);
        })
        .finally(() => {
          if (btn) btn.disabled = false;
        });
    },

    updateProductList: function (products) {
      let container = document.querySelector(this.config.productContainerSelector);

      if (container && container.parentNode) {
        container = container.parentNode;
      } else {
        container = document.querySelector(this.config.contentSelector);
      }

      if (!container) return;

      const html = products.map(p => `
        <div class="product-layout">
          <div class="product-thumb">
            <div class="image">
              <a href="${this._escape(p.href)}">
                <img src="${this._escape(p.image)}" alt="${this._escape(p.name)}" />
              </a>
            </div>
            <div class="caption">
              <h4><a href="${this._escape(p.href)}">${this._escape(p.name)}</a></h4>
              ${p.price ? `<p class="price">${p.price}</p>` : ''}
            </div>
          </div>
        </div>
      `).join('');

      container.innerHTML = html;
    },

    updatePagination: function (total) {

    },

    initShowMore: function () {
      const containers = document.querySelectorAll('.filter-items-container');

      containers.forEach(container => {
        const limit = parseInt(container.getAttribute('data-items-limit')) || 10;
        const items = container.querySelectorAll('.filter-item');

        if (items.length > limit && limit > 0) {
          container.classList.add('has-limit');

          items.forEach((item, index) => {
            if (index < limit) {
              item.classList.add('visible');
            }
          });
        }
      });
    },

    toggleShowMore: function (link) {

      let container = link.previousElementSibling;

      if (!container || !container.classList.contains('filter-items-container')) {
        container = link.closest('.filter-group')?.querySelector('.filter-items-container');
      }

      if (!container || !container.classList.contains('filter-items-container')) {
        container = link.parentElement?.querySelector('.filter-items-container');
      }

      if (!container || !container.classList.contains('filter-items-container')) {
        DockercartFilter.warn('Could not find filter-items-container for:', link);
        return;
      }

      const isExpanded = container.classList.contains('expanded');
      const showText = link.getAttribute('data-show-text') || 'Show more';
      const hideText = link.getAttribute('data-hide-text') || 'Show less';

      if (isExpanded) {

        container.classList.remove('expanded');
        link.textContent = showText;
        DockercartFilter.log('Show More collapsed');
      } else {

        container.classList.add('expanded');
        link.textContent = hideText;
        DockercartFilter.log('Show More expanded');
      }
    },

    _getInputValue: function (selector) {
      const el = document.querySelector(this.config.formSelector + ' ' + selector);
      return el ? el.value : '';
    },

    _escape: function (str) {
      if (!str) return '';
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return str.replace(/[&<>"']/g, function (char) {
        return map[char];
      });
    },

    updateDynamicHeading: function() {

      if (!window.dockercartFilterData) {
        return;
      }

      const params = new URLSearchParams(window.location.search);
      const headingElement = document.querySelector('.product-category h1, .product-category .page-title, h1');

      if (!headingElement) {
        return;
      }

      const activeFilters = [];

      if (params.has('manufacturer')) {
        const manIds = params.get('manufacturer').split(',');
        manIds.forEach(id => {
          if (window.dockercartFilterData.manufacturers) {
            const man = window.dockercartFilterData.manufacturers.find(m => m.manufacturer_id == id);
            if (man) {
              activeFilters.push(man.name);
            }
          }
        });
      }

      params.forEach((value, key) => {
        if (key.startsWith('attribute[')) {
          const match = key.match(/attribute\[(\d+)\]/);
          if (match && window.dockercartFilterData.attributes) {
            const attrId = parseInt(match[1]);
            const attr = window.dockercartFilterData.attributes.find(a => a.attribute_id === attrId);
            if (attr) {
              const values = value.split(',');
              activeFilters.push(attr.name + ': ' + values.join(', '));
            }
          }
        }
      });

      params.forEach((value, key) => {
        if (key.startsWith('option[')) {
          const match = key.match(/option\[(\d+)\]/);
          if (match && window.dockercartFilterData.options) {
            const optId = parseInt(match[1]);
            const opt = window.dockercartFilterData.options.find(o => o.option_id === optId);
            if (opt) {
              const valueIds = value.split(',').map(v => parseInt(v));
              const valueNames = [];
              valueIds.forEach(vid => {
                const optVal = opt.values.find(v => v.option_value_id === vid);
                if (optVal) {
                  valueNames.push(optVal.name);
                }
              });
              if (valueNames.length > 0) {
                activeFilters.push(opt.name + ': ' + valueNames.join(', '));
              }
            }
          }
        }
      });

      if (activeFilters.length > 0 && window.dockercartFilterData.categoryName) {
        const newHeading = window.dockercartFilterData.categoryName + ' - ' + activeFilters.join(' | ');
        headingElement.textContent = newHeading;
      }
    },

    initPriceSlider: function () {
      const sliderMin = document.querySelector('.price-slider-min');
      const sliderMax = document.querySelector('.price-slider-max');
      const minInput = document.querySelector('input[name="price_min"]');
      const maxInput = document.querySelector('input[name="price_max"]');
      const sliderFill = document.querySelector('.price-slider-fill');
      const displayMin = document.querySelector('.price-range-min-display');
      const displayMax = document.querySelector('.price-range-max-display');

      if (!sliderMin || !sliderMax || !minInput || !maxInput) {
        return;
      }

      try {

        const updateSliderFill = () => {
          const min = parseFloat(sliderMin.value);
          const max = parseFloat(sliderMax.value);
          const minPercent = ((min - sliderMin.min) / (sliderMin.max - sliderMin.min)) * 100;
          const maxPercent = ((max - sliderMax.min) / (sliderMax.max - sliderMax.min)) * 100;

          if (sliderFill) {
            sliderFill.style.left = minPercent + '%';
            sliderFill.style.right = (100 - maxPercent) + '%';
          }

          if (displayMin) displayMin.textContent = Math.round(min);
          if (displayMax) displayMax.textContent = Math.round(max);

          // Preserve fractional slider values in the number inputs (do not round)
          minInput.value = min;
          maxInput.value = max;
        };

        sliderMin.addEventListener('input', function () {
          if (parseFloat(this.value) > parseFloat(sliderMax.value)) {
            this.value = sliderMax.value;
          }
          updateSliderFill();
        });

        sliderMax.addEventListener('input', function () {
          if (parseFloat(this.value) < parseFloat(sliderMin.value)) {
            this.value = sliderMin.value;
          }
          updateSliderFill();
        });

        minInput.addEventListener('change', function () {
          const val = parseFloat(this.value) || 0;
          if (val < parseFloat(sliderMin.min)) this.value = sliderMin.min;
          if (val > parseFloat(sliderMax.value)) this.value = sliderMax.value;
          sliderMin.value = this.value;
          updateSliderFill();
        });

        maxInput.addEventListener('change', function () {
          const val = parseFloat(this.value) || 0;
          if (val > parseFloat(sliderMax.max)) this.value = sliderMax.max;
          if (val < parseFloat(sliderMin.value)) this.value = sliderMin.value;
          sliderMax.value = this.value;
          updateSliderFill();
        });

        updateSliderFill();

        DockercartFilter.log('Price slider initialized successfully');
      } catch (e) {
        this.error('Error initializing price slider:', e);
      }
    },

    createMobileFilterStructure: function(position) {
      DockercartFilter.log('Creating mobile filter structure, position:', position);

      const container = document.createElement('div');
      container.className = 'dockercart-mobile-filter-container';

      const trigger = document.createElement('button');
      trigger.type = 'button';
      trigger.className = 'mobile-filter-trigger';
      trigger.setAttribute('aria-label', 'Open Filters');
      trigger.style.display = 'none';
      trigger.innerHTML = '<i data-lucide="filter"></i>';

      if (position === 'left') {
        trigger.style.left = '20px';
        trigger.style.right = 'auto';
      } else {
        trigger.style.right = '20px';
        trigger.style.left = 'auto';
      }

      const overlay = document.createElement('div');
      overlay.className = 'mobile-filter-overlay';

      const panel = document.createElement('div');
      panel.className = 'mobile-filter-panel';

      const header = document.createElement('div');
      header.className = 'mobile-filter-header';

      const title = document.createElement('h3');
      title.className = 'mobile-filter-title';
      title.textContent = (window.dockercartFilterTexts && window.dockercartFilterTexts.heading_title) ? window.dockercartFilterTexts.heading_title : 'Filters';

      const closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'mobile-filter-close';
      closeBtn.setAttribute('aria-label', 'Close');
      closeBtn.innerHTML = '<i data-lucide="x"></i>';

      header.appendChild(title);
      header.appendChild(closeBtn);

      const content = document.createElement('div');
      content.className = 'mobile-filter-content';

      const formWrapper = document.createElement('div');
      formWrapper.id = 'mobile-filter-form-wrapper';
      content.appendChild(formWrapper);

      const footer = document.createElement('div');
      footer.className = 'mobile-filter-footer';

      const resetBtn = document.createElement('button');
      resetBtn.type = 'button';
      resetBtn.className = 'mobile-filter-reset';
      resetBtn.innerHTML = '<i data-lucide="x"></i> ' + ((window.dockercartFilterTexts && window.dockercartFilterTexts.text_reset) ? window.dockercartFilterTexts.text_reset : 'Reset');

      const applyBtn = document.createElement('button');
      applyBtn.type = 'button';
      applyBtn.className = 'mobile-filter-apply';
      applyBtn.innerHTML = '<i data-lucide="check"></i> ' + ((window.dockercartFilterTexts && window.dockercartFilterTexts.text_apply) ? window.dockercartFilterTexts.text_apply : 'Apply');

      footer.appendChild(resetBtn);
      footer.appendChild(applyBtn);

      panel.appendChild(header);
      panel.appendChild(content);
      panel.appendChild(footer);

      container.appendChild(trigger);
      container.appendChild(overlay);
      container.appendChild(panel);

      DockercartFilter.log('Mobile filter structure created');

      return {
        container: container,
        trigger: trigger,
        overlay: overlay,
        panel: panel,
        closeBtn: closeBtn,
        applyBtn: applyBtn,
        resetBtn: resetBtn,
        formWrapper: formWrapper
      };
    },

    initMobileFilter: function() {
      const self = this;

      const filterContainer = document.querySelector('.dockercart-filter-vertical[data-position="sidebar"]');

      if (!filterContainer) {
        DockercartFilter.log('No sidebar filter found, skipping mobile widget');
        return;
      }

      const isInSidebar = filterContainer.closest('#column-left') || filterContainer.closest('#column-right');

      if (!isInSidebar) {
        DockercartFilter.log('Filter not in sidebar, skipping mobile widget');
        return;
      }

      DockercartFilter.log('Initializing mobile filter widget for sidebar filter');

      const sidebarPosition = filterContainer.closest('#column-left') ? 'left' : 'right';
      DockercartFilter.log('Sidebar position:', sidebarPosition);

      const mobileStructure = this.createMobileFilterStructure(sidebarPosition);
      document.body.appendChild(mobileStructure.container);

      // Render lucide icons inside the newly appended mobile structure
      try {
        if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
          lucide.createIcons();
        }
      } catch (e) {
        DockercartFilter.warn('lucide.createIcons failed:', e);
      }

      const trigger = mobileStructure.trigger;
      const overlay = mobileStructure.overlay;
      const panel = mobileStructure.panel;
      const closeBtn = mobileStructure.closeBtn;
      const applyBtn = mobileStructure.applyBtn;
      const resetBtn = mobileStructure.resetBtn;
      const mobileWrapper = mobileStructure.formWrapper;

      const desktopForm = document.querySelector(this.config.formSelector);

      if (desktopForm && mobileWrapper) {
        const formClone = desktopForm.cloneNode(true);
        formClone.id = 'dockercart-filter-form-mobile';
        formClone.setAttribute('data-filter-mode', 'button');
        mobileWrapper.appendChild(formClone);
        DockercartFilter.log('Form cloned to mobile panel');

        const showMoreLinks = formClone.querySelectorAll('.filter-show-more');
        showMoreLinks.forEach(function(link) {
          link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            DockercartFilter.toggleShowMore(link);
          });
        });

        const linksInMobile = formClone.querySelectorAll('a');
        linksInMobile.forEach(function(link) {
          link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            DockercartFilter.log('Link clicked in mobile panel:', link.href);

            let checkbox = null;

            let parent = link.parentElement;
            while (parent) {
              if (parent.tagName === 'LABEL') {

                checkbox = parent.querySelector('input[type="checkbox"]');
                break;
              }
              parent = parent.parentElement;
            }

            if (!checkbox) {
              parent = link.closest('.checkbox, .filter-item');
              if (parent) {
                checkbox = parent.querySelector('input[type="checkbox"]');
              }
            }

            if (checkbox) {
              checkbox.checked = !checkbox.checked;
              DockercartFilter.log('Checkbox toggled:', checkbox.name, 'value:', checkbox.value, 'checked:', checkbox.checked);
            } else {
              DockercartFilter.warn('No checkbox found for link:', link.href);
            }
          });
        });

        const collapsibleHeaders = formClone.querySelectorAll('.filter-group-header');
        collapsibleHeaders.forEach(function(header) {
          header.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const content = header.nextElementSibling;
            if (content) {

              const isCollapsed = content.classList.contains('collapsed');

              if (isCollapsed) {

                content.classList.remove('collapsed');
                content.style.maxHeight = content.scrollHeight + 'px';
              } else {

                content.classList.add('collapsed');
                content.style.maxHeight = '0';
              }

              header.classList.toggle('expanded');
              DockercartFilter.log('Collapsible toggled:', header.textContent.trim());
            }
          });
        });

        const filterToggles = formClone.querySelectorAll('.filter-toggle');
        filterToggles.forEach(function(toggle) {
          toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const content = toggle.nextElementSibling;
            if (content && content.hasAttribute('data-group-content')) {
              const isCollapsed = content.classList.contains('collapsed');

              if (isCollapsed) {
                content.classList.remove('collapsed');
                content.style.maxHeight = content.scrollHeight + 'px';
              } else {
                content.classList.add('collapsed');
                content.style.maxHeight = '0';
              }

              toggle.classList.toggle('active');
              DockercartFilter.log('Filter group toggled:', toggle.textContent.trim());
            }
          });
        });

        setTimeout(function() {
          const mobileSliderMin = formClone.querySelector('.price-slider-min');
          const mobileSliderMax = formClone.querySelector('.price-slider-max');
          if (mobileSliderMin && mobileSliderMax) {
            DockercartFilter.initPriceSliderForElement(formClone);
          }

          DockercartFilter.initShowMore();
        }, 100);
      } else {
        DockercartFilter.warn('Desktop form not found', {
          desktopForm: !!desktopForm,
          mobileWrapper: !!mobileWrapper
        });
      }

      const checkMobileView = function() {
        const breakpoint = window.dockercartMobileBreakpoint || 992;
        const isMobile = window.innerWidth < breakpoint;

        if (isMobile) {
          trigger.style.display = 'flex';
          DockercartFilter.log('Mobile view - showing trigger button (breakpoint: ' + breakpoint + 'px)');
        } else {
          trigger.style.display = 'none';

          if (panel.classList.contains('active')) {
            closeFilter();
          }
          DockercartFilter.log('Desktop view - hiding trigger button (breakpoint: ' + breakpoint + 'px)');
        }
      };

      const openFilter = function() {
        panel.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        DockercartFilter.log('Mobile filter opened');
      };

      const closeFilter = function() {
        panel.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        DockercartFilter.log('Mobile filter closed');
      };

      trigger.addEventListener('click', function(e) {
        e.preventDefault();
        openFilter();
      });

      overlay.addEventListener('click', closeFilter);
      if (closeBtn) closeBtn.addEventListener('click', closeFilter);

      if (applyBtn) {
        applyBtn.addEventListener('click', function() {
          DockercartFilter.log('Apply button clicked in mobile panel');

          const mobileForm = document.querySelector('#dockercart-filter-form-mobile');
          if (mobileForm && desktopForm) {

            const mobileCheckboxes = mobileForm.querySelectorAll('input[type="checkbox"]');
            mobileCheckboxes.forEach(function(mobileCheckbox) {
              const name = mobileCheckbox.name;
              const value = mobileCheckbox.value;
              const desktopCheckbox = desktopForm.querySelector('input[name="' + name + '"][value="' + value + '"]');
              if (desktopCheckbox) {
                desktopCheckbox.checked = mobileCheckbox.checked;
              }
            });

            const mobilePriceMin = mobileForm.querySelector('input[name="price_min"]');
            const mobilePriceMax = mobileForm.querySelector('input[name="price_max"]');
            const desktopPriceMin = desktopForm.querySelector('input[name="price_min"]');
            const desktopPriceMax = desktopForm.querySelector('input[name="price_max"]');

            if (mobilePriceMin && desktopPriceMin) {
              desktopPriceMin.value = mobilePriceMin.value;
            }
            if (mobilePriceMax && desktopPriceMax) {
              desktopPriceMax.value = mobilePriceMax.value;
            }

            DockercartFilter.log('Synced mobile form to desktop form');
          }

          closeFilter();

          self.applyFilter();
        });
      }

      if (mobileWrapper) {
        mobileWrapper.addEventListener('click', function(e) {
          if (e.target.classList && e.target.classList.contains('filter-show-more')) {
            e.preventDefault();
            e.stopPropagation();
            DockercartFilter.toggleShowMore(e.target);
          }
        });
      }

      if (resetBtn) {
        resetBtn.addEventListener('click', function(e) {
          e.preventDefault();
          DockercartFilter.log('Reset button clicked in mobile panel');

          const mobileForm = document.querySelector('#dockercart-filter-form-mobile');
          if (mobileForm) {
            const allCheckboxes = mobileForm.querySelectorAll('input[type="checkbox"]');
            allCheckboxes.forEach(function(checkbox) {
              checkbox.checked = false;
            });

            const priceMin = mobileForm.querySelector('input[name="price_min"]');
            const priceMax = mobileForm.querySelector('input[name="price_max"]');
            if (priceMin) priceMin.value = priceMin.getAttribute('min') || 0;
            if (priceMax) priceMax.value = priceMax.getAttribute('max') || 0;

            DockercartFilter.log('Mobile filters reset');
          }
        });
      }

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && panel.classList.contains('active')) {
          closeFilter();
        }
      });

      let resizeTimer;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
          checkMobileView();
        }, 250);
      });

      checkMobileView();
    },

    initPriceSliderForElement: function(container) {
      try {
        const sliderMin = container.querySelector('.price-slider-min');
        const sliderMax = container.querySelector('.price-slider-max');
        const minInput = container.querySelector('input[name="price_min"]');
        const maxInput = container.querySelector('input[name="price_max"]');
        const sliderFill = container.querySelector('.price-slider-fill');
        const displayMin = container.querySelector('.price-range-min-display');
        const displayMax = container.querySelector('.price-range-max-display');

        if (!sliderMin || !sliderMax || !sliderFill) {
          return;
        }

        const updateSliderFill = function() {
          const min = parseFloat(sliderMin.value);
          const max = parseFloat(sliderMax.value);
          const rangeMin = parseFloat(sliderMin.min);
          const rangeMax = parseFloat(sliderMax.max);

          const minPercent = ((min - rangeMin) / (rangeMax - rangeMin)) * 100;
          const maxPercent = ((max - rangeMin) / (rangeMax - rangeMin)) * 100;

          sliderFill.style.left = minPercent + '%';
          sliderFill.style.right = (100 - maxPercent) + '%';

          if (displayMin) displayMin.textContent = Math.round(min);
          if (displayMax) displayMax.textContent = Math.round(max);
        };

        sliderMin.addEventListener('input', function() {
          let val = parseFloat(this.value);
          if (val > parseFloat(sliderMax.value)) {
            val = parseFloat(sliderMax.value);
            this.value = val;
          }
          if (minInput) minInput.value = val;
          updateSliderFill();
        });

        sliderMax.addEventListener('input', function() {
          let val = parseFloat(this.value);
          if (val < parseFloat(sliderMin.value)) {
            val = parseFloat(sliderMin.value);
            this.value = val;
          }
          if (maxInput) maxInput.value = val;
          updateSliderFill();
        });

        if (minInput) {
          minInput.addEventListener('change', function() {
            const val = parseFloat(this.value) || 0;
            if (val < parseFloat(sliderMin.min)) this.value = sliderMin.min;
            if (val > parseFloat(sliderMax.value)) this.value = sliderMax.value;
            sliderMin.value = this.value;
            updateSliderFill();
          });
        }

        if (maxInput) {
          maxInput.addEventListener('change', function() {
            const val = parseFloat(this.value) || 0;
            if (val > parseFloat(sliderMax.max)) this.value = sliderMax.max;
            if (val < parseFloat(sliderMin.value)) this.value = sliderMin.value;
            sliderMax.value = this.value;
            updateSliderFill();
          });
        }

        updateSliderFill();
      } catch (e) {
        this.error('Error initializing price slider for element:', e);
      }
    }
  };

  document.addEventListener('DOMContentLoaded', function () {

    const breakpoint = window.dockercartMobileBreakpoint || 768;

    const styleId = 'dockercart-filter-breakpoint-styles';
    let styleEl = document.getElementById(styleId);

    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = styleId;
      document.head.appendChild(styleEl);
    }

    const css = `
      @media (max-width: ${breakpoint - 1}px) {

        #column-left .dockercart-filter-vertical > h3,
        #column-left .dockercart-filter-vertical > form,
        #column-left .dockercart-filter-vertical > .active-filters,
        #column-right .dockercart-filter-vertical > h3,
        #column-right .dockercart-filter-vertical > form,
        #column-right .dockercart-filter-vertical > .active-filters {
            display: none;
        }

        .mobile-filter-panel .dockercart-filter-vertical {
            padding: 0;
        }

        .mobile-filter-panel .filter-checkbox {
            pointer-events: none;
        }
      }
    `;

    styleEl.textContent = css;
    DockercartFilter.log('Mobile breakpoint styles initialized with breakpoint: ' + breakpoint + 'px');

    DockercartFilter.log('DOMContentLoaded - initializing filter');
    DockercartFilter.init();
    DockercartFilter.initPriceSlider();
    DockercartFilter.initMobileFilter();
  });

  window.addEventListener('pageshow', function (event) {

    DockercartFilter.log('Page shown - reinitializing checkboxes from URL');

    DockercartFilter.restoreCheckboxesFromUrl();

    DockercartFilter.updateDynamicHeading();

    DockercartFilter.applyToggleStates();

    DockercartFilter.initPriceSlider();
  });

  window.DockercartFilter = DockercartFilter;
})();
