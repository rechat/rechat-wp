/**
 * Hide [rch_latest_listings] output (and optional theme section wrapper) when SDK returns no listings.
 *
 * SDK dispatches rechat-listings:fetched on window (see https://sdk.rechat.com/classes/Listings.html).
 * Script must register before fetch completes — enqueued in <head> after rechat-sdk-js.
 */
(function (window, document) {
  'use strict';

  var ROOT_SELECTOR = '[data-rch-latest-listings-instance]';
  var SECTION_SELECTOR = '[data-rechat-listings-section]';
  var RESOLVED_ATTR = 'data-rch-listings-resolved';
  var SLIDE_SELECTOR = '.rechat-listings-list__item';
  var EMPTY_SELECTOR = '.map-listing-grid__empty-state';
  var EMPTY_TEXT = 'No listings found';

  var pollTimers = [];

  function queryDeep(root, selector) {
    if (!root || !root.querySelector) {
      return null;
    }
    var match = root.querySelector(selector);
    if (match) {
      return match;
    }
    var nodes = root.querySelectorAll('*');
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].shadowRoot) {
        match = queryDeep(nodes[i].shadowRoot, selector);
        if (match) {
          return match;
        }
      }
    }
    return null;
  }

  function countDeep(root, selector) {
    if (!root || !root.querySelectorAll) {
      return 0;
    }
    var count = root.querySelectorAll(selector).length;
    var nodes = root.querySelectorAll('*');
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].shadowRoot) {
        count += countDeep(nodes[i].shadowRoot, selector);
      }
    }
    return count;
  }

  function mountShowsEmptyMessage(root) {
    if (countDeep(root, SLIDE_SELECTOR) > 0) {
      return false;
    }
    if (queryDeep(root, EMPTY_SELECTOR)) {
      return true;
    }
    var roots = [root];
    root.querySelectorAll('*').forEach(function (node) {
      if (node.shadowRoot) {
        roots.push(node.shadowRoot);
      }
    });
    for (var i = 0; i < roots.length; i++) {
      var text = roots[i].textContent || '';
      if (text.indexOf(EMPTY_TEXT) !== -1) {
        return true;
      }
    }
    return false;
  }

  function isResolved(root) {
    return root.getAttribute(RESOLVED_ATTR) === 'loaded' || root.getAttribute(RESOLVED_ATTR) === 'empty';
  }

  function hideTarget(root) {
    root.classList.add('rch-latest-listings-is-empty');
    root.setAttribute('hidden', '');
    root.setAttribute('aria-hidden', 'true');

    var section = root.closest(SECTION_SELECTOR);
    if (section) {
      section.style.display = 'none';
      section.setAttribute('hidden', '');
      section.setAttribute('aria-hidden', 'true');
      section.classList.add('is-rechat-listings-section-hidden');
      section.setAttribute('data-rechat-listings-state', 'empty');
      section.setAttribute('data-rch-listings-section-resolved', 'empty');
    }
  }

  function showTarget(root) {
    root.classList.remove('rch-latest-listings-is-empty');
    root.removeAttribute('hidden');
    root.removeAttribute('aria-hidden');

    var section = root.closest(SECTION_SELECTOR);
    if (section && section.getAttribute('data-rch-listings-section-resolved') !== 'empty') {
      section.style.display = '';
      section.removeAttribute('hidden');
      section.removeAttribute('aria-hidden');
      section.classList.remove('is-rechat-listings-section-hidden');
      section.setAttribute('data-rechat-listings-state', 'loaded');
    }
  }

  function markLoaded(root) {
    root.setAttribute(RESOLVED_ATTR, 'loaded');
    showTarget(root);
    var section = root.closest(SECTION_SELECTOR);
    if (section) {
      section.setAttribute('data-rechat-listings-state', 'loaded');
      section.setAttribute('data-rch-listings-section-resolved', 'loaded');
    }
  }

  function markEmpty(root) {
    root.setAttribute(RESOLVED_ATTR, 'empty');
    hideTarget(root);
  }

  function evaluateRoot(root) {
    if (isResolved(root)) {
      return;
    }
    if (!root.querySelector('rechat-listings')) {
      return;
    }
    if (countDeep(root, SLIDE_SELECTOR) > 0) {
      markLoaded(root);
      return;
    }
    if (mountShowsEmptyMessage(root)) {
      markEmpty(root);
    }
  }

  function resolveByDetail(root, listings) {
    if (isResolved(root)) {
      return;
    }
    if (!Array.isArray(listings)) {
      evaluateRoot(root);
      return;
    }
    if (listings.length === 0) {
      markEmpty(root);
      return;
    }
    window.requestAnimationFrame(function () {
      if (countDeep(root, SLIDE_SELECTOR) > 0) {
        markLoaded(root);
      } else {
        evaluateRoot(root);
      }
    });
  }

  function resolveAllPending() {
    document.querySelectorAll(ROOT_SELECTOR).forEach(evaluateRoot);
  }

  function scheduleResolveAll() {
    [0, 50, 150, 400, 800, 1500].forEach(function (delay) {
      window.setTimeout(resolveAllPending, delay);
    });
  }

  function onWindowFetched(event) {
    var listings = null;
    if (event && event.type === 'rechat-error') {
      listings = [];
    } else if (event && Array.isArray(event.detail)) {
      listings = event.detail;
    }

    var pending = Array.prototype.filter.call(
      document.querySelectorAll(ROOT_SELECTOR),
      function (root) {
        return !isResolved(root) && root.querySelector('rechat-listings');
      }
    );

    if (pending.length === 1 && listings !== null) {
      resolveByDetail(pending[0], listings);
      return;
    }

    if (listings !== null && pending.length > 1) {
      pending.forEach(function (root) {
        evaluateRoot(root);
      });
    }

    scheduleResolveAll();
  }

  function observeShadowRoots(root, observer) {
    root.querySelectorAll('rechat-root, rechat-listings, rechat-listings-list').forEach(function (el) {
      if (el.shadowRoot) {
        observer.observe(el.shadowRoot, {
          childList: true,
          subtree: true,
          characterData: true,
        });
      }
    });
  }

  function setupObservers() {
    document.querySelectorAll(ROOT_SELECTOR).forEach(function (root) {
      if (root.getAttribute('data-rch-listings-observed') === '1') {
        return;
      }
      root.setAttribute('data-rch-listings-observed', '1');

      var observer = new MutationObserver(function () {
        evaluateRoot(root);
      });
      observer.observe(root, {
        childList: true,
        subtree: true,
        characterData: true,
      });

      var attempts = 0;
      function pollShadow() {
        observeShadowRoots(root, observer);
        evaluateRoot(root);
        if (++attempts < 20) {
          window.setTimeout(pollShadow, 250);
        }
      }
      pollShadow();
    });
  }

  function bootstrap() {
    if (!document.querySelector(ROOT_SELECTOR)) {
      return;
    }
    setupObservers();
    resolveAllPending();
    scheduleResolveAll();
  }

  if (!window.__rchLatestListingsEmptyBound) {
    window.__rchLatestListingsEmptyBound = true;
    window.addEventListener('rechat-listings:fetched', onWindowFetched);
    window.addEventListener('rechat-error', onWindowFetched);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})(window, document);
