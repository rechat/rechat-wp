<?php

if (! defined('ABSPATH')) {
  exit();
}

/*******************************
 * Renders the listing as a shortcode based on Gutenberg block
 * Usage: [listings property_types="Residential" listing_statuses="Active" layout_style="layout2" filter_pool="true" filter_search_limit="200"]
 ******************************/
function rch_render_listing_list($atts)
{
  // Parse shortcode attributes with defaults
  $atts = shortcode_atts([
    'minimum_price' => '',
    'maximum_price' => '',
    'minimum_square_feet' => '',
    'maximum_square_feet' => '',
    'minimum_bathrooms' => '',
    'maximum_bathrooms' => '',
    'minimum_lot_square_feet' => '',
    'maximum_lot_square_feet' => '',
    'minimum_year_built' => '',
    'maximum_year_built' => '',
    'minimum_bedrooms' => '',
    'maximum_bedrooms' => '',
    'listing_per_page' => '5',
    'brand' => '',
    'listing_statuses' => '',
    'disable_filter_address' => false,
    'disable_filter_price' => false,
    'disable_filter_beds' => false,
    'disable_filter_baths' => false,
    'disable_filter_property_types' => false,
    'disable_filter_advanced' => false,
    'disable_filter_loading_indicator' => false,
    'layout_style' => 'default',
    'own_listing' => true,
    'property_types' => '',
    'filter_open_houses' => false,
    'office_exclusive' => false,
    'filter_pool' => false,
    'disable_sort' => false,
    'map_latitude' => '',
    'map_longitude' => '',
    'map_zoom' => '12',
    'map_id' => '',
    'filter_address' => '',
    'filter_search_limit' => '',
    'filter_suggestions_limit' => '',
    'filter_pagination_offset' => '',
    'property_subtypes' => '',
    'architectural_styles' => '',
    'filter_baths' => '',
    'minimum_parking_spaces' => '',
    'minimum_sold_date' => '',
    'filter_agents' => '',
    'list_offices' => '',
    'filter_brand_id' => '',
    'sort_by' => '-list_date',
  ], $atts);

  // Convert listing_statuses string to array if needed
  if (!empty($atts['listing_statuses']) && is_string($atts['listing_statuses'])) {
    $atts['listing_statuses'] = array_map('trim', explode(',', $atts['listing_statuses']));
  }
  // Convert boolean attributes from strings
  $atts['own_listing'] = filter_var($atts['own_listing'], FILTER_VALIDATE_BOOLEAN);

  // Always set brand_id from settings (rechat-root always needs it)
  $atts['brand'] = get_option('rch_rechat_brand_id');
  $atts['disable_filter_address'] = filter_var($atts['disable_filter_address'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_price'] = filter_var($atts['disable_filter_price'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_beds'] = filter_var($atts['disable_filter_beds'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_baths'] = filter_var($atts['disable_filter_baths'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_property_types'] = filter_var($atts['disable_filter_property_types'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_advanced'] = filter_var($atts['disable_filter_advanced'], FILTER_VALIDATE_BOOLEAN);
  $atts['filter_open_houses'] = filter_var($atts['filter_open_houses'], FILTER_VALIDATE_BOOLEAN);
  $atts['office_exclusive'] = filter_var($atts['office_exclusive'], FILTER_VALIDATE_BOOLEAN);
  $atts['filter_pool'] = filter_var($atts['filter_pool'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_sort'] = filter_var($atts['disable_sort'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_loading_indicator'] = filter_var($atts['disable_filter_loading_indicator'], FILTER_VALIDATE_BOOLEAN);

  // Sanitize and prepare data
  $listing_statuses_str = rch_sanitize_listing_statuses($atts['listing_statuses'] ?? []);
  $map_default_center = rch_get_map_default_center(
    $atts['map_latitude'] ?? '',
    $atts['map_longitude'] ?? ''
  );
  $layout_style = isset($atts['layout_style']) ? sanitize_text_field($atts['layout_style']) : 'default';
  $primary_color = get_option('_rch_primary_color');

  // Get URL parameters from search form
  $url_params = rch_get_fallback_url_parameters();

  // Merge URL parameters into attributes, giving priority to URL parameters
  if (!empty($url_params)) {
    if (isset($url_params['content'])) {
      $atts['filter_address'] = $url_params['content'];
    }
    if (isset($url_params['property_type'])) {
      $atts['property_types'] = $url_params['property_type'];
    }
    if (isset($url_params['minimum_price'])) {
      $atts['minimum_price'] = $url_params['minimum_price'];
    }
    if (isset($url_params['maximum_price']) && $url_params['maximum_price'] !== 'null') {
      $atts['maximum_price'] = $url_params['maximum_price'];
    }
    if (isset($url_params['minimum_bedrooms']) && $url_params['minimum_bedrooms'] !== 'null') {
      $atts['minimum_bedrooms'] = $url_params['minimum_bedrooms'];
    }
    if (isset($url_params['maximum_bedrooms']) && $url_params['maximum_bedrooms'] !== 'null') {
      $atts['maximum_bedrooms'] = $url_params['maximum_bedrooms'];
    }
    if (isset($url_params['minimum_bathrooms']) && $url_params['minimum_bathrooms'] !== 'null') {
      $atts['minimum_bathrooms'] = $url_params['minimum_bathrooms'];
    }
  }

  // Start output buffering
  ob_start();

  // Render styles
  echo rch_render_layout_styles($layout_style, $primary_color);

  // Get rechat root attributes (only brand_id in new SDK)
  $rechat_attrs = rch_get_rechat_root_attributes($atts, $map_default_center, $listing_statuses_str);
  
  // Get rechat-listings attributes (all filter/map attributes in new SDK)
  $rechat_listings_attrs = rch_get_rechat_listings_attributes($atts, $map_default_center, $listing_statuses_str);

  // Check if all filters are disabled
  $all_filters_disabled = $atts['disable_filter_address'] &&
    $atts['disable_filter_price'] &&
    $atts['disable_filter_beds'] &&
    $atts['disable_filter_baths'] &&
    $atts['disable_filter_property_types'] &&
    $atts['disable_filter_advanced'];

?>
  <div class="rch-listing-block-gutenberg">
    <rechat-root <?php echo $rechat_attrs; ?>>
      <rechat-listings <?php echo $rechat_listings_attrs; ?>>
        <div class="container_listing_sdk">
          <div class="filters">
            <?php if (!$all_filters_disabled): ?>
              <rechat-map-filter></rechat-map-filter>
            <?php endif; ?>
            <?php if (!$atts['disable_sort']): ?>
              <rechat-listings-sort></rechat-listings-sort>
            <?php endif; ?>
          </div>

          <?php if ($layout_style === 'layout2'): ?>
            <div class="wrapper">
              <div class="listings">
                <rechat-map-listings-grid></rechat-map-listings-grid>
              </div>
              <div class="map">
                <rechat-map></rechat-map>
              </div>
            </div>
          <?php else: ?>
            <div class="wrapper">
              <div class="map">
                <rechat-map></rechat-map>
              </div>
              <div class="listings">
                <rechat-map-listings-grid></rechat-map-listings-grid>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </rechat-listings>
    </rechat-root>
  </div>
  <script>
    // Handle filter restoration and persistence
    (function() {
      const urlParams = new URLSearchParams(window.location.search)
      const rechatRoot = document.querySelector('rechat-root')
      const rechatListings = document.querySelector('rechat-listings')

      if (!rechatRoot || !rechatListings) {
        return
      }

      // Mark if we're coming from a browser navigation (back/forward)
      const isNavigatingBack = window.performance &&
        window.performance.navigation &&
        window.performance.navigation.type === 2

      // Store the session key for this listing page
      const sessionKey = 'rechat_listing_page_' + window.location.pathname

      // Check if we should restore filters
      const shouldRestoreFilters = () => {
        // If URL has parameters, always restore from URL
        if (urlParams.toString() !== '') {
          return true
        }

        // If no URL parameters and we're navigating back, don't restore old filters
        // This handles the case where user visits clean URL after using filters
        return false
      }

      // Clear session storage if visiting clean URL
      if (urlParams.toString() === '' && !isNavigatingBack) {
        // Clear any stored filter state for this page
        try {
          sessionStorage.removeItem(sessionKey)
        } catch (e) {
          // Ignore storage errors
        }
      }

      // Only restore filters if appropriate
      if (!shouldRestoreFilters()) {
        return
      }

      // Wait for rechat-listings to be ready
      const restoreFilters = () => {
        const filterKeys = [
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
          'filter_brand_id'
        ]

        // Map URL / history keys to <rechat-listings> attribute names (see Rechat Listings SDK).
        const urlKeyToRechatListingsAttr = (key) => {
          const map = {
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
          }
          if (Object.prototype.hasOwnProperty.call(map, key)) {
            return map[key]
          }
          return key
        }

        const filters = {}

        filterKeys.forEach(key => {
          if (urlParams.has(key)) {
            let value = urlParams.get(key)
            const outKey = urlKeyToRechatListingsAttr(key)

            // Handle array values (comma-separated)
            if (['listing_statuses', 'property_types', 'property_subtypes', 'architectural_styles', 'agents', 'list_offices', 'filter_agents'].includes(key)) {
              value = value.split(',').filter(v => v.trim() !== '')
            }
            // Handle map_center (should be an object)
            else if (key === 'map_center') {
              try {
                value = JSON.parse(value)
              } catch (e) {
                // If not JSON, try to parse as "lat,lng"
                const coords = value.split(',')
                if (coords.length === 2) {
                  value = {
                    lat: parseFloat(coords[0]),
                    lng: parseFloat(coords[1])
                  }
                }
              }
            } else if (['open_house', 'office_exclusive', 'filter_pool', 'pool'].includes(key)) {
              value = value === 'true' || value === '1'
            } else if (['map_zoom', 'search_limit', 'filter_pagination_limit', 'filter_search_limit', 'filter_suggestions_limit', 'filter_pagination_offset', 'minimum_price', 'maximum_price', 'minimum_bedrooms', 'maximum_bedrooms', 'minimum_bathrooms', 'maximum_bathrooms', 'baths', 'minimum_parking_spaces', 'minimum_square_feet', 'maximum_square_feet', 'minimum_lot_square_feet', 'maximum_lot_square_feet', 'minimum_year_built', 'maximum_year_built', 'minimum_sold_date', 'map_id', 'filter_brand_id'].includes(key)) {
              const num = parseFloat(value)
              if (!isNaN(num)) {
                value = num
              }
            }

            filters[outKey] = value
          }
        })

        // Apply filters to rechat-listings (NEW SDK) by updating attributes
        Object.entries(filters).forEach(([key, value]) => {
          const attrName = key.replace(/_/g, '-')

          if (typeof value === 'object' && !Array.isArray(value)) {
            rechatListings.setAttribute(attrName, JSON.stringify(value))
          } else if (Array.isArray(value)) {
            rechatListings.setAttribute(attrName, value.join(','))
          } else if (typeof value === 'boolean') {
            rechatListings.setAttribute(attrName, value ? 'true' : 'false')
          } else {
            rechatListings.setAttribute(attrName, value)
          }
        })
      }

      // Check if rechat-listings is already defined/ready
      if (customElements.get('rechat-listings')) {
        setTimeout(restoreFilters, 100)
      } else {
        // Wait for custom element to be defined
        customElements.whenDefined('rechat-listings').then(() => {
          setTimeout(restoreFilters, 100)
        })
      }
    })()

    // Save filters to URL when they change
    // Flag to track if component has fully initialized to avoid saving initial state
    let isInitialized = false
    let initTimeout = null

    // Mark as initialized after a delay to allow component to mount
    initTimeout = setTimeout(() => {
      isInitialized = true
    }, 1500)

    window.addEventListener('rechat-listing-filters:change', (e) => {
      // Don't update URL during initial component mount/setup
      if (!isInitialized) {
        return
      }

      const keys = [
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
      ]

      const filters = keys.reduce((acc, key) => {
        const value = e.detail[key]

        return (value === null || value === undefined) ? acc : {
          ...acc,
          [key]: value
        }
      }, {})

      const params = new URLSearchParams()

      Object.entries(filters).forEach(([key, value]) => {
        if (typeof value === 'object' && !Array.isArray(value)) {
          params.set(key, JSON.stringify(value))
        } else if (Array.isArray(value)) {
          params.set(key, value.join(','))
        } else if (typeof value === 'boolean') {
          params.set(key, value ? 'true' : 'false')
        } else {
          params.set(key, value)
        }
      })

      const url = new URL(window.location.href)

      url.search = params.toString()
      window.history.replaceState({}, '', url)
    })
  </script>
<?php

  return ob_get_clean();
}
add_shortcode('listings', 'rch_render_listing_list');
