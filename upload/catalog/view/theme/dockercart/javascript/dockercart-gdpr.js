(function () {
  'use strict';

  var COOKIE_NAME = 'dc_gdpr_consent';
  var COOKIE_DNS  = 'dc_gdpr_dns';

  function getCookie(name) {
    var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
  }

  function setCookie(name, value, days) {
    var expires = '';
    if (days) {
      var date = new Date();
      date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
      expires = '; expires=' + date.toUTCString();
    }
    document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
  }

  function getConsent() {
    var raw = getCookie(COOKIE_NAME);
    if (!raw) return null;
    try { return JSON.parse(raw); } catch (e) { return null; }
  }

  function setConsent(value, expiry) {
    setCookie(COOKIE_NAME, JSON.stringify(value), expiry);
  }

  function showBanner(banner) {
    if (!banner) return;
    banner.classList.add('dc-gdpr-banner--visible');
  }

  function hideBanner(banner) {
    if (!banner) return;
    banner.classList.remove('dc-gdpr-banner--visible');
    banner.classList.add('dc-gdpr-banner--hidden');
    setTimeout(function () {
      if (banner && banner.parentNode) {
        banner.parentNode.removeChild(banner);
      }
    }, 400);
  }

  function sendConsent(url) {
    if (!url) return;
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    }).catch(function () {});
  }

  function init() {
    var banner = document.querySelector('.dc-gdpr-banner');
    if (!banner) return;

    var existing = getConsent();
    if (existing) {
      hideBanner(banner);
      return;
    }

    var expiry = parseInt(banner.getAttribute('data-expiry'), 10) || 365;
    var acceptUrl = banner.getAttribute('data-accept-url') || '';
    var rejectUrl = banner.getAttribute('data-reject-url') || '';
    var dnsUrl = banner.getAttribute('data-dns-url') || '';

    showBanner(banner);

    var acceptBtn = banner.querySelector('[data-action="accept"]');
    var rejectBtn = banner.querySelector('[data-action="reject"]');
    var dnsBtn    = banner.querySelector('[data-action="do-not-sell"]');

    if (acceptBtn) {
      acceptBtn.addEventListener('click', function () {
        setConsent({ accepted: true, all: true, date: new Date().toISOString() }, expiry);
        sendConsent(acceptUrl);
        hideBanner(banner);
        window.location.reload();
      });
    }

    if (rejectBtn) {
      rejectBtn.addEventListener('click', function () {
        setConsent({ accepted: false, all: false, date: new Date().toISOString() }, expiry);
        sendConsent(rejectUrl);
        hideBanner(banner);
      });
    }

    if (dnsBtn) {
      dnsBtn.addEventListener('click', function () {
        setCookie(COOKIE_DNS, '1', expiry);
        sendConsent(dnsUrl);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
