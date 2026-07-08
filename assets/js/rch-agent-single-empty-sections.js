/**
 * Hide theme listing sections when a Rechat shortcode returns no listings.
 *
 * Primary path: element-level rechat-listings:fetched listener (works when SDK
 * dispatches on the element with bubbles:true). This gives exact section identity.
 *
 * Fallback: window-level listener. When e.target is the element, use closest().
 * When only one section is still pending, e.detail is unambiguous. Otherwise,
 * fall back to staggered DOM polling — WITHOUT timer cancellation so concurrent
 * events do not kill each other's checks.
 */
(function (window, document) {
  'use strict';

  var SECTION_SELECTOR = '[data-rechat-listings-section]';
  var MOUNT_SELECTOR = '[data-rechat-listings-mount]';
  var STATE_ATTR = 'data-rechat-listings-state';
  var SLIDE_SELECTOR = '.rechat-listings-list__item';
  var EMPTY_SELECTOR = '.map-listing-grid__empty-state';
  var EMPTY_TEXT = 'No listings found';

  // ── DOM helpers ───────────────────────────────────────────────────────────

  function queryDeep(root, selector) {
    if (!root || !('querySelector' in root)) return null;
    var match = root.querySelector(selector);
    if (match) return match;
    var nodes = root.querySelectorAll('*');
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].shadowRoot) {
        match = queryDeep(nodes[i].shadowRoot, selector);
        if (match) return match;
      }
    }
    return null;
  }

  function countDeep(root, selector) {
    if (!root || !('querySelectorAll' in root)) return 0;
    var count = root.querySelectorAll(selector).length;
    var nodes = root.querySelectorAll('*');
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].shadowRoot) {
        count += countDeep(nodes[i].shadowRoot, selector);
      }
    }
    return count;
  }

  function deepTextIncludes(root, needle) {
    var roots = [root];
    root.querySelectorAll('*').forEach(function (node) {
      if (node.shadowRoot) roots.push(node.shadowRoot);
    });
    for (var i = 0; i < roots.length; i++) {
      if ((roots[i].textContent || '').indexOf(needle) !== -1) return true;
    }
    return false;
  }

  function mountShowsEmptyMessage(mount) {
    if (queryDeep(mount, EMPTY_SELECTOR)) return true;
    return deepTextIncludes(mount, EMPTY_TEXT);
  }

  function countRealListings(mount) {
    var selectors = ['.listing-card', 'a.listing-card__hyperlink', 'a[href*="listing-detail"]'];
    var count = 0;
    selectors.forEach(function (sel) {
      count += countDeep(mount, sel);
    });
    return count;
  }

  // ── section state ─────────────────────────────────────────────────────────

  function isResolved(section) {
    if (section.getAttribute('data-rch-listings-section-resolved')) {
      return true;
    }
    var pluginRoot = section.querySelector('[data-rch-latest-listings-instance]');
    if (pluginRoot) {
      var pluginState = pluginRoot.getAttribute('data-rch-listings-resolved');
      if (pluginState === 'loaded' || pluginState === 'empty') {
        return true;
      }
    }
    return !!section.getAttribute(STATE_ATTR);
  }

  function hideSection(section) {
    section.style.display = 'none';
    section.setAttribute('hidden', '');
    section.setAttribute('aria-hidden', 'true');
    section.classList.add('is-rechat-listings-section-hidden');
    section.setAttribute('data-rch-listings-section-resolved', 'empty');
  }

  function showSection(section) {
    section.style.display = '';
    section.removeAttribute('hidden');
    section.removeAttribute('aria-hidden');
    section.classList.remove('is-rechat-listings-section-hidden');
    section.setAttribute('data-rch-listings-section-resolved', 'loaded');
  }

  function markSectionState(section, state) {
    section.setAttribute(STATE_ATTR, state);
  }

  function sectionFromEvent(event) {
    if (!event) return null;
    var path = typeof event.composedPath === 'function' ? event.composedPath() : [event.target];
    for (var i = 0; i < path.length; i++) {
      var node = path[i];
      if (!node || !node.closest) continue;
      var section = node.closest(SECTION_SELECTOR);
      if (section) return section;
    }
    return null;
  }

  function resolveByDetail(section, listings) {
    if (listings.length === 0) {
      hideSection(section);
      markSectionState(section, 'empty');
      return;
    }
    if (isResolved(section)) return;
    showSection(section);
    markSectionState(section, 'loaded');
  }

  function evaluateSection(section) {
    var mount = section.querySelector(MOUNT_SELECTOR);
    if (!mount || !mount.querySelector('rechat-listings')) return 'skip';
    if (mountShowsEmptyMessage(mount)) return 'empty';
    if (countRealListings(mount) > 0) return 'loaded';
    return 'pending';
  }

  function resolveSections() {
    document.querySelectorAll(SECTION_SELECTOR).forEach(function (section) {
      var result = evaluateSection(section);
      if (result === 'empty') {
        hideSection(section);
        markSectionState(section, 'empty');
        section.setAttribute('data-rch-listings-section-resolved', 'empty');
        return;
      }
      if (isResolved(section)) return;
      if (result === 'loaded') {
        showSection(section);
        markSectionState(section, 'loaded');
      }
    });
  }

  // ── element-level listener (primary — exact section identity) ─────────────
  //
  // If the SDK dispatches rechat-listings:fetched on the <rechat-listings> element
  // with bubbles:true, this fires before the window listener and resolves the
  // correct section directly from e.detail without any DOM inspection.

  function attachElementListener(section) {
    var mount = section.querySelector(MOUNT_SELECTOR);
    if (!mount) return;
    var rechatEl = mount.querySelector('rechat-listings');
    if (!rechatEl) return;

    rechatEl.addEventListener('rechat-listings:fetched', function (e) {
      var listings = Array.isArray(e.detail) ? e.detail : [];
      window.requestAnimationFrame(function () {
        resolveByDetail(section, listings);
      });
    });
  }

  // ── window-level listener (fallback) ─────────────────────────────────────
  //
  // Three resolution strategies tried in order:
  //   1. e.target.closest() — works when SDK bubbles from element (catches it
  //      even if element listener above already ran, isResolved guard is a no-op)
  //   2. Only 1 pending section — e.detail is unambiguous
  //   3. Staggered DOM poll — NO timer cancellation so concurrent events do not
  //      kill each other's scheduled checks

  function onWindowFetched(event) {
    var isError = event.type === 'rechat-error';
    var listings = isError
      ? []
      : Array.isArray(event && event.detail) ? event.detail : null;

    window.requestAnimationFrame(function () {
      var targetSection = sectionFromEvent(event);
      if (targetSection && listings !== null) {
        resolveByDetail(targetSection, listings);
        return;
      }

      [0, 50, 150, 400, 800].forEach(function (delay) {
        window.setTimeout(resolveSections, delay);
      });
    });
  }

  // ── MutationObserver (belt-and-suspenders) ────────────────────────────────

  function observeShadowRoots(mount, observer) {
    mount.querySelectorAll('rechat-root, rechat-listings, rechat-listings-list').forEach(function (el) {
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
    document.querySelectorAll(MOUNT_SELECTOR).forEach(function (mount) {
      var observer = new MutationObserver(function () {
        resolveSections();
      });

      observer.observe(mount, {
        childList: true,
        subtree: true,
        characterData: true,
      });

      var pollAttempts = 0;
      function pollShadowRoots() {
        observeShadowRoots(mount, observer);
        if (++pollAttempts < 16) window.setTimeout(pollShadowRoots, 250);
      }
      pollShadowRoots();
    });
  }

  // ── bootstrap ─────────────────────────────────────────────────────────────

  function bootstrap() {
    if (!document.querySelector(SECTION_SELECTOR)) return;

    document.querySelectorAll(SECTION_SELECTOR).forEach(attachElementListener);
    setupObservers();
    resolveSections();
  }

  window.addEventListener('rechat-listings:fetched', onWindowFetched);
  window.addEventListener('rechat-error', onWindowFetched);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})(window, document);
