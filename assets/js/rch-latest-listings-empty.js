/**
 * Hide [rch_latest_listings] output (and optional theme section wrapper) when SDK returns no listings.
 *
 * SDK dispatches rechat-listings:fetched on window with detail = IListing[].
 * @see https://sdk.rechat.com/classes/Listings.html
 */
(function (window, document) {
  'use strict';

  var ROOT_SELECTOR = '[data-rch-latest-listings-instance]';
  var SECTION_SELECTOR = '[data-rechat-listings-section]';
  var RESOLVED_ATTR = 'data-rch-listings-resolved';
  var EMPTY_SELECTOR = '.map-listing-grid__empty-state';
  var EMPTY_TEXT = 'No listings found';
  var REAL_LISTING_SELECTORS = [
    '.listing-card',
    'a.listing-card__hyperlink',
    'a[href*="listing-detail"]',
  ].join(',');

  var rootByListingsEl = new WeakMap();

  function forEachDeepRoot(root, fn) {
    if (!root) {
      return;
    }
    fn(root);
    root.querySelectorAll('*').forEach(function (node) {
      if (node.shadowRoot) {
        forEachDeepRoot(node.shadowRoot, fn);
      }
    });
  }

  function queryDeep(root, selector) {
    var found = null;
    forEachDeepRoot(root, function (scope) {
      if (found || !scope.querySelector) {
        return;
      }
      found = scope.querySelector(selector);
    });
    return found;
  }

  function countDeep(root, selector) {
    var count = 0;
    forEachDeepRoot(root, function (scope) {
      if (scope.querySelectorAll) {
        count += scope.querySelectorAll(selector).length;
      }
    });
    return count;
  }

  function deepTextIncludes(root, needle) {
    var hit = false;
    forEachDeepRoot(root, function (scope) {
      if (hit) {
        return;
      }
      var text = scope.textContent || '';
      if (text.indexOf(needle) !== -1) {
        hit = true;
      }
    });
    return hit;
  }

  /** Empty UI wins over skeleton slides still in the DOM. */
  function hasEmptyState(root) {
    if (queryDeep(root, EMPTY_SELECTOR)) {
      return true;
    }
    return deepTextIncludes(root, EMPTY_TEXT);
  }

  function countRealListings(root) {
    return countDeep(root, REAL_LISTING_SELECTORS);
  }

  function isResolved(root) {
    var state = root.getAttribute(RESOLVED_ATTR);
    return state === 'loaded' || state === 'empty';
  }

  function registerRoot(root) {
    var rechatEl = root.querySelector('rechat-listings');
    if (rechatEl) {
      rootByListingsEl.set(rechatEl, root);
    }
  }

  function rootFromEvent(event) {
    if (!event) {
      return null;
    }

    var path = typeof event.composedPath === 'function' ? event.composedPath() : [event.target];
    for (var i = 0; i < path.length; i++) {
      var node = path[i];
      if (!node || !node.tagName) {
        continue;
      }
      var tag = node.tagName.toLowerCase();
      if (tag === 'rechat-listings') {
        return rootByListingsEl.get(node) || null;
      }
      if (node.matches && node.matches(ROOT_SELECTOR)) {
        return node;
      }
      if (node.closest) {
        var inRoot = node.closest(ROOT_SELECTOR);
        if (inRoot) {
          return inRoot;
        }
      }
    }

    return null;
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
    if (section) {
      section.style.display = '';
      section.removeAttribute('hidden');
      section.removeAttribute('aria-hidden');
      section.classList.remove('is-rechat-listings-section-hidden');
      section.setAttribute('data-rechat-listings-state', 'loaded');
      section.setAttribute('data-rch-listings-section-resolved', 'loaded');
    }
  }

  function markLoaded(root) {
    root.setAttribute(RESOLVED_ATTR, 'loaded');
    showTarget(root);
  }

  function markEmpty(root) {
    root.setAttribute(RESOLVED_ATTR, 'empty');
    hideTarget(root);
  }

  function evaluateRoot(root) {
    if (!root.querySelector('rechat-listings')) {
      return;
    }

    if (hasEmptyState(root)) {
      markEmpty(root);
      return;
    }

    if (isResolved(root)) {
      return;
    }

    if (countRealListings(root) > 0) {
      markLoaded(root);
    }
  }

  function scheduleRootResolve(root, listings) {
    [0, 50, 150, 400, 800, 1500, 2500].forEach(function (delay, index, delays) {
      window.setTimeout(function () {
        if (isResolved(root)) {
          return;
        }
        if (hasEmptyState(root)) {
          markEmpty(root);
          return;
        }
        if (countRealListings(root) > 0) {
          markLoaded(root);
          return;
        }
        if (Array.isArray(listings) && listings.length > 0 && index === delays.length - 1) {
          markLoaded(root);
        }
      }, delay);
    });
  }

  function resolveByDetail(root, listings) {
    if (!root || !Array.isArray(listings)) {
      if (root) {
        evaluateRoot(root);
      }
      return;
    }

    if (listings.length === 0) {
      markEmpty(root);
      return;
    }

    scheduleRootResolve(root, listings);
  }

  function resolveAllPending() {
    document.querySelectorAll(ROOT_SELECTOR).forEach(function (root) {
      if (hasEmptyState(root)) {
        markEmpty(root);
        return;
      }
      if (!isResolved(root)) {
        evaluateRoot(root);
      }
    });
  }

  function scheduleResolveAll() {
    [0, 50, 150, 400, 800, 1500, 2500].forEach(function (delay) {
      window.setTimeout(resolveAllPending, delay);
    });
  }

  function attachElementListener(root) {
    var rechatEl = root.querySelector('rechat-listings');
    if (!rechatEl || rechatEl.getAttribute('data-rch-empty-bound') === '1') {
      return;
    }
    rechatEl.setAttribute('data-rch-empty-bound', '1');
    registerRoot(root);

    rechatEl.addEventListener('rechat-listings:fetched', function (event) {
      var listings = Array.isArray(event.detail) ? event.detail : [];
      resolveByDetail(root, listings);
    });
  }

  function onWindowFetched(event) {
    var listings = null;
    if (event && event.type === 'rechat-error') {
      listings = [];
    } else if (event && Array.isArray(event.detail)) {
      listings = event.detail;
    }

    var root = rootFromEvent(event);
    if (root && listings !== null) {
      resolveByDetail(root, listings);
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
      registerRoot(root);
      attachElementListener(root);

      if (root.getAttribute('data-rch-listings-observed') === '1') {
        return;
      }
      root.setAttribute('data-rch-listings-observed', '1');

      var observer = new MutationObserver(function () {
        evaluateRoot(root);
        if (hasEmptyState(root)) {
          markEmpty(root);
        }
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
        if (++attempts < 24) {
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
