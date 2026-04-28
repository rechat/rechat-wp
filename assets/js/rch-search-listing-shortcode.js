/**
 * [rch_search_listing_form] — redirect on Rechat property search submit (supports multiple instances).
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
      return '';
    }
    return map.center.join(',');
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

    var params = new URLSearchParams({
      content: filters.address || '',
      property_type: firstPropertyType(filters),
      minimum_price: filters.minimum_price || '',
      maximum_price: filters.maximum_price || '',
      minimum_bedrooms: filters.minimum_bedrooms || '',
      maximum_bedrooms: filters.maximum_bedrooms || '',
      minimum_bathrooms: filters.minimum_bathrooms || '',
      map_center: mapCenterString(map),
    });

    var sep = base.indexOf('?') === -1 ? '?' : '&';
    window.location.href = base + sep + params.toString();
  });
})();
