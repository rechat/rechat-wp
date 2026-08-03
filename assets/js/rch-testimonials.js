/**
 * [rch_testimonials] — fetch client testimonials from the Rechat SDK and render cards.
 *
 * The SDK exposes a global `Rechat` (UMD build, loaded as `rechat-sdk-js`). Testimonials is a
 * "portal-only" service: `new Rechat.Sdk().Testimonials.list()` resolves the brand from the
 * current domain. On domains without a registered portal (e.g. localhost) the promise rejects,
 * so we degrade to the empty state instead of throwing.
 *
 * SDK docs: https://sdk.rechat.com/documents/JavaScript_SDK.Testimonials.html
 */
(function () {
  'use strict';

  /** Wait for the global `Rechat` SDK to be available (script may still be parsing). */
  function whenSdkReady(cb) {
    var tries = 0;
    (function poll() {
      if (typeof window.Rechat !== 'undefined' && window.Rechat && typeof window.Rechat.Sdk === 'function') {
        cb(window.Rechat);
        return;
      }
      if (tries++ > 100) {
        cb(null); // ~10s elapsed — give up, show empty state
        return;
      }
      setTimeout(poll, 100);
    })();
  }

  function parseConfig(el) {
    var raw = el.getAttribute('data-rch-testimonials-config');
    if (!raw) {
      return {};
    }
    try {
      return JSON.parse(raw);
    } catch (e) {
      return {};
    }
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function contactName(contact) {
    if (!contact) {
      return 'Anonymous';
    }
    var name = ((contact.first_name || '') + ' ' + (contact.last_name || '')).trim();
    return name || 'Anonymous';
  }

  function initials(name) {
    var parts = name.split(/\s+/).filter(Boolean);
    if (!parts.length) {
      return '?';
    }
    if (parts.length === 1) {
      return parts[0].charAt(0).toUpperCase();
    }
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  function starsHtml(rating) {
    var r = Math.max(0, Math.min(5, Math.round(Number(rating) || 0)));
    if (!r) {
      return '';
    }
    var full = '';
    var i;
    for (i = 0; i < 5; i++) {
      full += '<span class="rch-testimonials__star' + (i < r ? ' is-filled' : '') + '" aria-hidden="true">★</span>';
    }
    return '<div class="rch-testimonials__rating" aria-label="' + r + ' out of 5 stars">' + full + '</div>';
  }

  function cardHtml(t, cfg) {
    var name = contactName(t.contact);
    var content = escapeHtml(t.content || '');
    var rating = cfg.showRating ? starsHtml(t.rating) : '';
    var avatar = cfg.showAvatar
      ? '<div class="rch-testimonials__avatar" aria-hidden="true">' + escapeHtml(initials(name)) + '</div>'
      : '';

    return (
      '<article class="rch-testimonials__card">' +
      rating +
      '<blockquote class="rch-testimonials__content">' + content + '</blockquote>' +
      '<footer class="rch-testimonials__author">' +
      avatar +
      '<cite class="rch-testimonials__name">' + escapeHtml(name) + '</cite>' +
      '</footer>' +
      '</article>'
    );
  }

  function setState(el, state) {
    el.setAttribute('data-rch-testimonials-state', state);
  }

  function showEmpty(el, cfg, statusEl) {
    setState(el, 'empty');
    if (statusEl) {
      statusEl.innerHTML = '<p class="rch-testimonials__empty">' + escapeHtml(cfg.emptyText || 'No testimonials yet.') + '</p>';
    }
  }

  function render(el, cfg, data) {
    var grid = el.querySelector('[data-rch-testimonials-grid]');
    var statusEl = el.querySelector('[data-rch-testimonials-status]');

    if (!Array.isArray(data) || !data.length) {
      showEmpty(el, cfg, statusEl);
      return;
    }

    grid.innerHTML = data
      .map(function (t) {
        return cardHtml(t, cfg);
      })
      .join('');

    if (statusEl) {
      statusEl.setAttribute('hidden', '');
    }
    grid.removeAttribute('hidden');
    setState(el, 'loaded');
  }

  function loadInstance(el) {
    var cfg = parseConfig(el);
    var statusEl = el.querySelector('[data-rch-testimonials-status]');

    whenSdkReady(function (Rechat) {
      if (!Rechat) {
        showEmpty(el, cfg, statusEl);
        return;
      }

      var sdk;
      try {
        sdk = new Rechat.Sdk();
      } catch (e) {
        showEmpty(el, cfg, statusEl);
        return;
      }

      sdk.Testimonials
        .list({ limit: cfg.limit || 20, start: cfg.start || 0 })
        .then(function (res) {
          render(el, cfg, res && res.data);
        })
        .catch(function () {
          // Portal not registered for this domain (e.g. localhost) or network error.
          showEmpty(el, cfg, statusEl);
        });
    });
  }

  function init() {
    var nodes = document.querySelectorAll('[data-rch-testimonials]');
    Array.prototype.forEach.call(nodes, function (el) {
      if (el.getAttribute('data-rch-testimonials-init') === '1') {
        return;
      }
      el.setAttribute('data-rch-testimonials-init', '1');
      loadInstance(el);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
