/**
 * [rch_search_listing_form] — redirect on Rechat property search submit (supports multiple instances).
 * Matches Rechat SDK detail shape: https://sdk.rechat.com/examples/webcomponents/full-property-search-form/
 */
(function () {
  'use strict';

  function findSearchFormWrapper(node) {
    if (!node || !node.closest) {
      return null;
    }
    return node.closest('.rch-search-listing-form');
  }

  function resolveWrapperFromEvent(e) {
    var path = typeof e.composedPath === 'function' ? e.composedPath() : [];
    var i;
    var w;
    for (i = 0; i < path.length; i++) {
      w = findSearchFormWrapper(path[i]);
      if (w) {
        return w;
      }
    }
    return document.querySelector('.rch-search-listing-form');
  }

  function firstPropertyType(filters) {
    if (!filters || !filters.property_types) {
      return '';
    }
    if (Array.isArray(filters.property_types)) {
      return filters.property_types[0] || '';
    }
    return String(filters.property_types);
  }

  function mapCenterString(map) {
    if (!map || !map.center || !Array.isArray(map.center)) {
      return null;
    }
    return map.center.join(',');
  }

  function mapZoomValue(map) {
    if (!map || map.zoom == null) {
      return null;
    }
    return map.zoom;
  }

  /**
   * Coerce one entry to a boundary id string (SDK may send strings or { boundaryId } / { id }).
   * @param {unknown} item
   * @returns {string}
   */
  function boundaryIdFromItem(item) {
    if (item == null) {
      return '';
    }
    if (typeof item === 'object') {
      if (item.boundaryId != null) {
        return String(item.boundaryId).trim();
      }
      if (item.id != null) {
        return String(item.id).trim();
      }
      return '';
    }
    return String(item).trim();
  }

  /**
   * @param {unknown} raw
   * @returns {string[]}
   */
  function normalizeBoundaryIds(raw) {
    if (raw == null) {
      return [];
    }
    if (Array.isArray(raw)) {
      return raw
        .map(boundaryIdFromItem)
        .filter(function (id) {
          return id !== '';
        });
    }
    if (typeof raw === 'string' && raw.trim() !== '') {
      return raw
        .split(',')
        .map(function (s) {
          return s.trim();
        })
        .filter(function (s) {
          return s !== '';
        });
    }
    return [];
  }

  /**
   * Collect boundary ids from all known SDK shapes on filters / event detail.
   * @param {Record<string, unknown>} filters
   * @param {Record<string, unknown>|null} detail
   */
  function collectBoundaryIds(filters, detail) {
    var merged = [];
    function pushAll(raw) {
      normalizeBoundaryIds(raw).forEach(function (id) {
        if (merged.indexOf(id) === -1) {
          merged.push(id);
        }
      });
    }
    pushAll(filters.boundary_ids);
    pushAll(filters.boundaryIds);
    if (detail) {
      pushAll(detail.boundary_ids);
      pushAll(detail.boundaryIds);
    }
    return merged;
  }

  function serializeListingStatuses(v) {
    if (v == null) {
      return null;
    }
    if (Array.isArray(v)) {
      var s = v
        .filter(function (x) {
          return x != null && String(x) !== '';
        })
        .join(',');
      return s || null;
    }
    var t = String(v).trim();
    return t || null;
  }

  /**
   * Read geographic filters already set on <rechat-listings> (e.g. from WP options).
   * @param {Element|null} el
   */
  function readListingsBoundaryAttrs(el) {
    if (!el || !el.getAttribute) {
      return { country: null, state: null, ids: null };
    }
    var country = el.getAttribute('filter-boundary-country');
    var state = el.getAttribute('filter-boundary-state');
    var ids = el.getAttribute('filter-boundary-ids');
    return {
      country: country && String(country).trim() !== '' ? country : null,
      state: state && String(state).trim() !== '' ? state : null,
      ids: ids && String(ids).trim() !== '' ? ids : null,
    };
  }

  window.addEventListener('rechat-property-search-form:submit', function (e) {
    if (!e || !e.detail) {
      return;
    }

    var wrapper = resolveWrapperFromEvent(e);
    var base = wrapper && wrapper.getAttribute('data-search-redirect-base');
    if (!base) {
      return;
    }

    var filters = e.detail.filters || {};
    var map = e.detail.map || {};
    var listingsEl = wrapper && wrapper.querySelector('rechat-listings');
    var domBound = readListingsBoundaryAttrs(listingsEl);

    var boundaryIds = collectBoundaryIds(filters, e.detail);
    if (boundaryIds.length === 0 && domBound.ids) {
      boundaryIds = normalizeBoundaryIds(domBound.ids);
    }

    var addressText =
      filters.address != null && String(filters.address) !== ''
        ? String(filters.address)
        : '';

    var entries = Object.assign(
      boundaryIds.length > 0
        ? { filter_boundary_ids: boundaryIds.join(',') }
        : addressText !== ''
          ? { content: addressText }
          : {},
      {
        sort_by: filters.sort_by != null && filters.sort_by !== '' ? String(filters.sort_by) : null,
        listing_statuses: serializeListingStatuses(filters.listing_statuses),
        property_type: firstPropertyType(filters) || null,
        minimum_price: filters.minimum_price != null ? filters.minimum_price : null,
        maximum_price: filters.maximum_price != null ? filters.maximum_price : null,
        minimum_bedrooms: filters.minimum_bedrooms != null ? filters.minimum_bedrooms : null,
        maximum_bedrooms: filters.maximum_bedrooms != null ? filters.maximum_bedrooms : null,
        minimum_bathrooms: filters.minimum_bathrooms != null ? filters.minimum_bathrooms : null,
        map_center: mapCenterString(map),
        map_zoom: mapZoomValue(map),
        filter_boundary_country: domBound.country,
        filter_boundary_state: domBound.state,
      }
    );

    var params = new URLSearchParams();
    Object.keys(entries).forEach(function (key) {
      var value = entries[key];
      if (value != null && String(value) !== '') {
        params.set(key, String(value));
      }
    });

    var sep = base.indexOf('?') === -1 ? '?' : '&';
    window.location.href = base + sep + params.toString();
  });
})();
