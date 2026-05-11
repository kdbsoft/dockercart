/**
 * DockerCart NovaPost Checkout Integration
 * Enhances #input-city and #input-address-1 with AJAX autocomplete + validation.
 */
(function () {
  'use strict';

  var npConfig = window._novapost || null;
  var initialized = false;
  var cityDropdown = null;
  var divisionWrapper = null;
  var divisionSelect = null;
  var cityTimer = null;
  var cityValid = false;
  var divisionValid = false;
  var lastValidCity = '';

  function label(key, fallback) {
    if (npConfig && npConfig.labels && npConfig.labels[key]) return npConfig.labels[key];
    return fallback || key;
  }

  function init() {
    if (initialized) return;
    initialized = true;
    interceptFetchResponses();
    waitForFields();
  }

  function waitForFields() {
    var cityInput = document.getElementById('input-city');
    if (!cityInput) { setTimeout(waitForFields, 300); return; }
    buildCityAutocomplete();
    buildDivisionSelect();
    bindShippingMethodChange();
    bindCountryChange();
  }

  function interceptFetchResponses() {
    var orig = window.fetch;
    window.fetch = function (url, options) {
      var s = typeof url === 'string' ? url : (url.url || '');
      return orig.call(this, url, options).then(function (r) {
        if (s.includes('shipping_address') || s.includes('shipping_method')) {
          r.clone().json().then(function (json) {
            if (json && json.novapost && npConfig) {
              npConfig.country_code = json.novapost.country_code || npConfig.country_code;
              npConfig.zone_id = json.novapost.zone_id || npConfig.zone_id;
              npConfig.delivery_types = json.novapost.delivery_types || npConfig.delivery_types;
              npConfig.search_url = json.novapost.search_url || npConfig.search_url;
              npConfig.save_url = json.novapost.save_url || npConfig.save_url;
              npConfig.labels = json.novapost.labels || npConfig.labels;
            }
          }).catch(function () {});
        }
        return r;
      });
    };
  }

  // ── Country change ───────────────────────────────────────────────────

  function bindCountryChange() {
    var countrySelect = document.getElementById('input-country');
    if (!countrySelect) return;
    countrySelect.addEventListener('change', function () {
      lastValidCity = '';
      var cityInput = document.getElementById('input-city');
      cityValid = false;
      divisionValid = false;
      if (cityInput) {
        cityInput.classList.remove('np-valid', 'np-invalid');
      }
      var addr = document.getElementById('input-address-1');
      if (addr) {
        addr.value = '';
        addr.classList.remove('np-valid', 'np-invalid');
      }
      if (divisionSelect) {
        divisionSelect.innerHTML = '<option value="">' + label('select_division', '\u2014') + '</option>';
        divisionSelect.classList.remove('np-valid', 'np-invalid');
      }
      if (divisionWrapper) divisionWrapper.style.display = 'none';
      clearNPSession();
    });
  }

  function clearNPSession() {
    if (!npConfig || !npConfig.save_url) return;
    fetch(npConfig.save_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        novapost_city: '',
        novapost_division: '',
        novapost_division_name: ''
      })
    }).catch(function () {});
  }

  // ── City autocomplete ───────────────────────────────────────────────────

  function buildCityAutocomplete() {
    if (document.getElementById('np-city-dropdown')) return;
    var input = document.getElementById('input-city');
    if (!input) return;

    cityDropdown = document.createElement('div');
    cityDropdown.id = 'np-city-dropdown';
    cityDropdown.className = 'np-dropdown';
    cityDropdown.style.display = 'none';
    var wrapper = input.closest('div');
    if (wrapper) { wrapper.style.position = 'relative'; wrapper.appendChild(cityDropdown); }
    input.setAttribute('autocomplete', 'off');

    input.addEventListener('input', function () {
      clearTimeout(cityTimer);
      cityValid = false;
      input.classList.remove('np-valid', 'np-invalid');
      resetDivision();

      var val = input.value.trim();
      if (val.length < 2 || !isNP()) { cityDropdown.style.display = 'none'; return; }

      cityTimer = setTimeout(function () {
        searchCities(val);
      }, 300);
    });

    input.addEventListener('blur', function () {
      setTimeout(function () { cityDropdown.style.display = 'none'; }, 200);
    });

    input.addEventListener('focus', function () {
      if (input.value.trim().length >= 2 && isNP()) cityDropdown.style.display = 'block';
    });
  }

  function searchCities(query) {
    if (!npConfig) return;
    var countryId = getDomValue('input-country');
    var zoneId = getDomValue('input-zone');
    var url = npConfig.search_url + '&city_query=' + encodeURIComponent(query) + '&country_id=' + encodeURIComponent(countryId) + '&zone_id=' + encodeURIComponent(zoneId);
    fetch(url).then(function (r) { return r.json(); }).then(function (cities) {
      cityDropdown.innerHTML = '';
      if (!(cities && cities.length)) {
        cityDropdown.innerHTML = '<div class="np-dropdown-item np-dropdown-empty">' + label('nothing_found', '\u041D\u0438\u0447\u0435\u0433\u043E \u043D\u0435 \u043D\u0430\u0439\u0434\u0435\u043D\u043E') + '</div>';
        cityDropdown.style.display = 'block';
        return;
      }
      cities.forEach(function (c) {
        var item = document.createElement('div');
        item.className = 'np-dropdown-item';
        item.textContent = c.city_name;
        item.addEventListener('mousedown', function (e) {
          e.preventDefault();
          selectCity(c.city_name);
        });
        cityDropdown.appendChild(item);
      });
      cityDropdown.style.display = 'block';
    }).catch(function () {});
  }

  function selectCity(name) {
    var input = document.getElementById('input-city');
    if (input) {
      input.value = name;
      input.classList.add('np-valid');
      input.classList.remove('np-invalid');
    }
    cityValid = true;
    lastValidCity = name;
    cityDropdown.style.display = 'none';
    saveNP({ novapost_city: name });
    loadDivisions(name);
  }

  function resetDivision() {
    divisionValid = false;
    if (divisionSelect) {
      divisionSelect.innerHTML = '<option value="">' + label('select_division', '\u2014') + '</option>';
      divisionSelect.classList.remove('np-valid', 'np-invalid');
    }
    var addr = document.getElementById('input-address-1');
    if (addr) {
      addr.value = '';
      addr.classList.remove('np-valid', 'np-invalid');
      addr.style.display = '';
      var lbl = addr.parentElement.querySelector('label');
      if (lbl) lbl.style.display = '';
    }
    if (divisionWrapper) divisionWrapper.style.display = 'none';
  }

  // ── Division select ─────────────────────────────────────────────────────

  function buildDivisionSelect() {
    if (document.getElementById('np-division-select')) return;
    var addrInput = document.getElementById('input-address-1');
    if (!addrInput || !addrInput.parentElement) return;

    divisionWrapper = document.createElement('div');
    divisionWrapper.id = 'np-division-wrapper';
    divisionWrapper.className = 'np-division-wrapper';
    divisionWrapper.style.display = 'none';
    divisionWrapper.innerHTML =
      '<label class="block text-sm font-medium text-text-primary mb-2">' + label('division', '\u041E\u0442\u0434\u0435\u043B\u0435\u043D\u0438\u0435') + '</label>' +
      '<select id="np-division-select" class="w-full h-14 px-4 bg-white border-2 border-gray-300 rounded-xl text-base text-text-primary input-focus transition appearance-none bg-no-repeat bg-right" style="background-image: url(\'data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e\'); background-position: right 1rem center; background-size: 1.5em 1.5em;"><option value="">' + label('select_division', '\u2014') + '</option></select>';

    addrInput.parentElement.insertBefore(divisionWrapper, addrInput.nextSibling);
    divisionSelect = document.getElementById('np-division-select');

    divisionSelect.addEventListener('change', function () {
      var opt = divisionSelect.selectedOptions[0];
      var addr = document.getElementById('input-address-1');
      if (opt && opt.value) {
        divisionValid = true;
        if (addr) {
          addr.value = opt.textContent;
          addr.classList.add('np-valid');
          addr.classList.remove('np-invalid');
        }
        saveNP({
          novapost_division: opt.value,
          novapost_division_name: opt.textContent,
          novapost_city: document.getElementById('input-city').value
        });
      } else {
        divisionValid = false;
        if (addr) { addr.value = ''; addr.classList.remove('np-valid', 'np-invalid'); }
      }
    });

  }

  function loadDivisions(cityName) {
    if (!npConfig) return;
    var type = getType();
    var addr = document.getElementById('input-address-1');

    if (type === 'courier') {
      if (addr) {
        addr.parentElement.style.display = '';
        addr.style.display = '';
        var lbl = addr.parentElement.querySelector('label');
        if (lbl) lbl.style.display = '';
      }
      if (divisionWrapper) divisionWrapper.style.display = 'none';
      return;
    }

    var countryId = getDomValue('input-country');
    var url = npConfig.search_url + '&city=' + encodeURIComponent(cityName) + '&country_id=' + encodeURIComponent(countryId) + '&delivery_type=' + encodeURIComponent(type);

    fetch(url).then(function (r) { return r.json(); }).then(function (divs) {
      if (!divisionSelect) return;
      divisionSelect.innerHTML = '<option value="">' + label('select_division', '\u2014') + '</option>';
      (divs || []).forEach(function (d) {
        var opt = document.createElement('option');
        opt.value = d.site_key || d.division_id;
        opt.textContent = (d.name || '') + ', ' + (d.short_address || '');
        divisionSelect.appendChild(opt);
      });
      if (divs && divs.length > 0) {
        if (addr) {
          addr.style.display = 'none';
          var lbl = addr.parentElement.querySelector('label');
          if (lbl) lbl.style.display = 'none';
        }
        divisionWrapper.style.display = 'block';
        divisionSelect.focus();
      } else {
        divisionSelect.innerHTML = '<option value="">' + label('no_divisions_for_city', '\u2014') + '</option>';
      }
    }).catch(function () {});
  }

  // ── Validation ──────────────────────────────────────────────────────

  window._novapostValidate = function () {
    if (!isNP()) return { valid: true, errors: [] };
    var errors = [];
    var type = getType();
    var cityInput = document.getElementById('input-city');
    var cityValue = cityInput ? cityInput.value.trim() : '';

    if (!cityValue) {
      errors.push(label('city_required', 'City is required'));
      if (cityInput) {
        cityInput.classList.add('np-invalid');
        cityInput.classList.remove('np-valid');
      }
    } else if (!cityValid) {
      errors.push(label('city_invalid', 'Select a city from the list'));
      if (cityInput) {
        cityInput.classList.add('np-invalid');
        cityInput.classList.remove('np-valid');
      }
    }

    if (type === 'courier') {
      var addr = document.getElementById('input-address-1');
      var addrValue = addr ? addr.value.trim() : '';
      if (!addrValue) {
        errors.push(label('address_required', 'Address is required'));
        if (addr) {
          addr.classList.add('np-invalid');
          addr.classList.remove('np-valid');
        }
      }
    } else if (type !== 'courier') {
      if (!divisionValid) {
        errors.push(label('division_invalid', 'Select a division from the list'));
        if (divisionSelect) {
          divisionSelect.classList.add('np-invalid');
          divisionSelect.classList.remove('np-valid');
        }
      }
    }

    return { valid: errors.length === 0, errors: errors };
  };

  // ── Helpers ──────────────────────────────────────────────────────────────

  function getDomValue(id) {
    var el = document.getElementById(id);
    return el ? el.value : '';
  }

  function isNP() {
    var c = document.querySelector('input[name="shipping_method"]:checked');
    return c && c.value.startsWith('dockercart_novapost.');
  }

  function getType() {
    var c = document.querySelector('input[name="shipping_method"]:checked');
    if (c) { var p = c.value.split('.'); return p.length >= 2 ? p[1] : ''; }
    return '';
  }

  function saveNP(fields) {
    if (!npConfig || !npConfig.save_url) return;
    fields.shipping_method = 'dockercart_novapost.' + getType();
    fetch(npConfig.save_url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(fields) }).catch(function () {});
  }

  function bindShippingMethodChange() {
    var sm = document.getElementById('shipping-methods');
    if (!sm) return;
    sm.addEventListener('change', function (e) {
      if (e.target && e.target.name === 'shipping_method') handleChange(e.target.value);
    });
    setTimeout(function () {
      var c = sm.querySelector('input[name="shipping_method"]:checked');
      if (c) handleChange(c.value);
    }, 200);
  }

  function handleChange(value) {
    var isNPVal = value && value.startsWith('dockercart_novapost.');
    var type = isNPVal ? value.split('.')[1] : '';
    var addr = document.getElementById('input-address-1');
    var cityEl = document.getElementById('input-city');

    // Always clear all NovaPost validation state regardless of method
    if (cityDropdown) cityDropdown.style.display = 'none';
    if (cityEl) cityEl.classList.remove('np-valid', 'np-invalid');
    if (addr) addr.classList.remove('np-valid', 'np-invalid');
    if (divisionSelect) divisionSelect.classList.remove('np-valid', 'np-invalid');
    if (divisionWrapper) divisionWrapper.style.display = 'none';
    cityValid = false;
    divisionValid = false;

    if (isNPVal) {
      if (cityEl) {
        cityEl.placeholder = label('search_city', '\u0412\u0432\u0435\u0434\u0438\u0442\u0435 \u0433\u043E\u0440\u043E\u0434...');
        if (cityEl.value.trim().length > 0 && cityEl.value.trim() === lastValidCity) {
          cityEl.classList.add('np-valid');
          cityValid = true;
          saveNP({ novapost_city: cityEl.value.trim() });
        }
      }

      if (type === 'courier') {
        if (addr) {
          addr.parentElement.style.display = '';
          addr.style.display = '';
          var lbl = addr.parentElement.querySelector('label');
          if (lbl) lbl.style.display = '';
          addr.placeholder = label('delivery_address', '\u0410\u0434\u0440\u0435\u0441 \u0434\u043E\u0441\u0442\u0430\u0432\u043A\u0438');
        }
      } else {
        if (addr) addr.placeholder = label('select_division', '\u0412\u044B\u0431\u0435\u0440\u0438\u0442\u0435 \u043E\u0442\u0434\u0435\u043B\u0435\u043D\u0438\u0435');
        if (cityEl && cityEl.value.trim().length >= 2) {
          loadDivisions(cityEl.value.trim());
        }
      }
    } else {
      if (addr) {
        addr.parentElement.style.display = '';
        addr.style.display = '';
        var lbl = addr.parentElement.querySelector('label');
        if (lbl) lbl.style.display = '';
        addr.placeholder = '';
      }
      saveNP({ shipping_method: value });
    }
  }

  document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 100); });
})();
