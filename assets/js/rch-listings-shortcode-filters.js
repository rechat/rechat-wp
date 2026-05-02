/**
 * [listings] shortcode — URL filter restore and history sync for Rechat SDK.
 */
(function () {
  'use strict';

  var urlParams = new URLSearchParams(window.location.search);
  var rechatRoot = document.querySelector('rechat-root');
  var rechatListings = document.querySelector('rechat-listings');

  if (!rechatRoot || !rechatListings) {
    return;
  }

  var isNavigatingBack =
    window.performance &&
    window.performance.navigation &&
    window.performance.navigation.type === 2;

  var sessionKey = 'rechat_listing_page_' + window.location.pathname;

  function shouldRestoreFilters() {
    if (urlParams.toString() !== '') {
      return true;
    }
    return false;
  }

  if (urlParams.toString() === '' && !isNavigatingBack) {
    try {
      sessionStorage.removeItem(sessionKey);
    } catch (e) {
      /* ignore */
    }
  }

  if (!shouldRestoreFilters()) {
    return;
  }

  function restoreFilters() {
    var filterKeys = [
      'sort_by',
      'map_center',
      'map_zoom',
      'address',
      'filter_pagination_limit',
      'search_limit',
      'filter_search_limit',
      'filter_suggestions_limit',
      'filter_pagination_offset',
      'listing_statuses',
      'property_types',
      'minimum_price',
      'maximum_price',
      'minimum_bedrooms',
      'maximum_bedrooms',
      'maximum_bathrooms',
      'minimum_bathrooms',
      'minimum_parking_spaces',
      'minimum_square_feet',
      'maximum_square_feet',
      'minimum_lot_square_feet',
      'maximum_lot_square_feet',
      'minimum_year_built',
      'maximum_year_built',
      'minimum_sold_date',
      'property_subtypes',
      'architectural_styles',
      'baths',
      'open_house',
      'office_exclusive',
      'filter_pool',
      'pool',
      'agents',
      'list_offices',
      'filter_agents',
      'map_id',
      'filter_brand_id',
    ];

    var urlKeyToRechatListingsAttr = function (key) {
      var map = {
        search_limit: 'filter_pagination_limit',
        pool: 'filter_pool',
        address: 'filter_address',
        agents: 'filter_agents',
        list_offices: 'filter_list_offices',
        open_house: 'filter_open_houses',
        office_exclusive: 'filter_office_exclusives',
        sort_by: 'filter_sort_by',
        listing_statuses: 'filter_listing_statuses',
        property_types: 'filter_property_types',
        property_subtypes: 'filter_property_subtypes',
        architectural_styles: 'filter_architectural_styles',
        minimum_price: 'filter_minimum_price',
        maximum_price: 'filter_maximum_price',
        minimum_bedrooms: 'filter_minimum_bedrooms',
        maximum_bedrooms: 'filter_maximum_bedrooms',
        minimum_bathrooms: 'filter_minimum_bathrooms',
        maximum_bathrooms: 'filter_maximum_bathrooms',
        baths: 'filter_baths',
        minimum_parking_spaces: 'filter_minimum_parking_spaces',
        minimum_square_feet: 'filter_minimum_square_feet',
        maximum_square_feet: 'filter_maximum_square_feet',
        minimum_lot_square_feet: 'filter_minimum_lot_square_feet',
        maximum_lot_square_feet: 'filter_maximum_lot_square_feet',
        minimum_year_built: 'filter_minimum_year_built',
        maximum_year_built: 'filter_maximum_year_built',
        minimum_sold_date: 'filter_minimum_sold_date',
      };
      if (Object.prototype.hasOwnProperty.call(map, key)) {
        return map[key];
      }
      return key;
    };

    var filters = {};

    filterKeys.forEach(function (key) {
      if (!urlParams.has(key)) {
        return;
      }
      var value = urlParams.get(key);
      var outKey = urlKeyToRechatListingsAttr(key);

      if (
        [
          'listing_statuses',
          'property_types',
          'property_subtypes',
          'architectural_styles',
          'agents',
          'list_offices',
          'filter_agents',
        ].indexOf(key) !== -1
      ) {
        value = value.split(',').filter(function (v) {
          return v.trim() !== '';
        });
      } else if (key === 'map_center') {
        try {
          value = JSON.parse(value);
        } catch (err) {
          var coords = value.split(',');
          if (coords.length === 2) {
            value = {
              lat: parseFloat(coords[0]),
              lng: parseFloat(coords[1]),
            };
          }
        }
      } else if (['open_house', 'office_exclusive', 'filter_pool', 'pool'].indexOf(key) !== -1) {
        value = value === 'true' || value === '1';
      } else if (
        [
          'map_zoom',
          'search_limit',
          'filter_pagination_limit',
          'filter_search_limit',
          'filter_suggestions_limit',
          'filter_pagination_offset',
          'minimum_price',
          'maximum_price',
          'minimum_bedrooms',
          'maximum_bedrooms',
          'minimum_bathrooms',
          'maximum_bathrooms',
          'baths',
          'minimum_parking_spaces',
          'minimum_square_feet',
          'maximum_square_feet',
          'minimum_lot_square_feet',
          'maximum_lot_square_feet',
          'minimum_year_built',
          'maximum_year_built',
          'minimum_sold_date',
          'map_id',
          'filter_brand_id',
        ].indexOf(key) !== -1
      ) {
        var num = parseFloat(value);
        if (!isNaN(num)) {
          value = num;
        }
      }

      filters[outKey] = value;
    });

    Object.keys(filters).forEach(function (key) {
      var value = filters[key];
      var attrName = key.replace(/_/g, '-');

      if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
        rechatListings.setAttribute(attrName, JSON.stringify(value));
      } else if (Array.isArray(value)) {
        rechatListings.setAttribute(attrName, value.join(','));
      } else if (typeof value === 'boolean') {
        rechatListings.setAttribute(attrName, value ? 'true' : 'false');
      } else {
        rechatListings.setAttribute(attrName, value);
      }
    });
  }

  if (customElements.get('rechat-listings')) {
    setTimeout(restoreFilters, 100);
  } else {
    customElements.whenDefined('rechat-listings').then(function () {
      setTimeout(restoreFilters, 100);
    });
  }

  var isInitialized = false;
  setTimeout(function () {
    isInitialized = true;
  }, 1500);

  window.addEventListener('rechat-listing-filters:change', function (e) {
    if (!isInitialized) {
      return;
    }

    var keys = [
      'sort_by',
      'map_center',
      'map_zoom',
      'map_id',
      'address',
      'search_limit',
      'filter_pagination_limit',
      'filter_search_limit',
      'filter_suggestions_limit',
      'filter_pagination_offset',
      'listing_statuses',
      'property_types',
      'minimum_price',
      'maximum_price',
      'minimum_bedrooms',
      'maximum_bedrooms',
      'minimum_bathrooms',
      'maximum_bathrooms',
      'minimum_parking_spaces',
      'minimum_square_feet',
      'maximum_square_feet',
      'minimum_lot_square_feet',
      'maximum_lot_square_feet',
      'minimum_year_built',
      'maximum_year_built',
      'minimum_sold_date',
      'property_subtypes',
      'architectural_styles',
      'baths',
      'open_house',
      'office_exclusive',
      'filter_pool',
      'pool',
      'agents',
      'list_offices',
      'filter_agents',
      'filter_brand_id',
    ];

    var filters = keys.reduce(function (acc, key) {
      var value = e.detail[key];
      if (value === null || value === undefined) {
        return acc;
      }
      acc[key] = value;
      return acc;
    }, {});

    var params = new URLSearchParams();

    Object.keys(filters).forEach(function (key) {
      var value = filters[key];
      if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
        params.set(key, JSON.stringify(value));
      } else if (Array.isArray(value)) {
        params.set(key, value.join(','));
      } else if (typeof value === 'boolean') {
        params.set(key, value ? 'true' : 'false');
      } else {
        params.set(key, value);
      }
    });

    var url = new URL(window.location.href);
    url.search = params.toString();
    window.history.replaceState({}, '', url);
  });
})();
