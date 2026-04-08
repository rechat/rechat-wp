/**
 * Fix listing-detail links where "#" in the street slug was parsed as a URL fragment.
 * Unencoded # makes the browser treat "5/uuid" as hash, not path.
 * We drop the # and join the path (e.g. ...-lot- + 5/uuid → ...-lot-5/uuid).
 *
 * Before: pathname /listing-detail/...-lot-  +  hash #5/{uuid}/
 * After:  /listing-detail/...-lot-5/{uuid}/
 */
(function () {
  'use strict';

  function shouldFixParsedUrl(u) {
    if (!u.hash || u.hash.length < 2) {
      return false;
    }
    if (u.pathname.indexOf('/listing-detail/') === -1) {
      return false;
    }
    var rest = u.hash.slice(1);
    var parts = rest.split('/');
    if (parts.length < 2) {
      return false;
    }
    var last = parts[parts.length - 1].replace(/\/$/, '');
    return /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(last);
  }

  function fixParsedUrl(u) {
    var pathRest = u.hash.slice(1);
    var fixedPath = u.pathname.replace(/\/$/, '') + pathRest;
    return u.origin + fixedPath + (u.search || '');
  }

  function fixAnchor(anchor) {
    if (!anchor || !anchor.getAttribute) {
      return;
    }
    var raw = anchor.getAttribute('href');
    if (!raw || raw.indexOf('/listing-detail/') === -1) {
      return;
    }

    try {
      var u = new URL(raw, window.location.origin);
      if (shouldFixParsedUrl(u)) {
        anchor.setAttribute('href', fixParsedUrl(u));
      }
    } catch (e) {
      /* ignore */
    }
  }

  function scan(root) {
    root = root || document;
    if (!root.querySelectorAll) {
      return;
    }
    var nodes = root.querySelectorAll('a[href*="listing-detail"]');
    for (var i = 0; i < nodes.length; i++) {
      fixAnchor(nodes[i]);
    }
  }

  function init() {
    scan(document);

    var obs = new MutationObserver(function (mutations) {
      for (var m = 0; m < mutations.length; m++) {
        var added = mutations[m].addedNodes;
        for (var n = 0; n < added.length; n++) {
          var node = added[n];
          if (node.nodeType !== 1) {
            continue;
          }
          if (node.tagName === 'A' && node.getAttribute('href') && node.getAttribute('href').indexOf('listing-detail') !== -1) {
            fixAnchor(node);
          }
          if (node.querySelectorAll) {
            scan(node);
          }
        }
      }
    });
    if (document.body) {
      obs.observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.addEventListener('rechat-listings:fetched', function () {
    requestAnimationFrame(function () {
      scan(document);
    });
  });
})();
