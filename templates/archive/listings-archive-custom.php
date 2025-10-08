<?php
$property_types_array = !empty($atts['property_types']) ? explode(',', $atts['property_types']) : [];
if (isset($atts['show_filter_bar']) && $atts['show_filter_bar'] === '1') {
    // Include the template if the condition is met
    include RCH_PLUGIN_DIR . 'templates/archive/template-part/listing-filters.php';
}

// Get map coordinates and zoom level from attributes
$latitude = isset($atts['map_latitude']) ? floatval($atts['map_latitude']) : 0;
$longitude = isset($atts['map_longitude']) ? floatval($atts['map_longitude']) : 0;
$zoom = isset($atts['map_zoom']) ? intval($atts['map_zoom']) : 10;

// Only calculate bounding box and polygon string if we have valid coordinates
$boundingBox = null;
$polygonString = '';
if ($latitude != 0 && $longitude != 0) {
    // Calculate the bounding box and polygon string using helper functions
    $boundingBox = rch_calculate_bounding_box($latitude, $longitude, $zoom);
    $polygonString = rch_generate_polygon_string($boundingBox);
}
// For debugging purposes only, you can remove this in production
/*
echo '<div style="display:none;">';
echo 'Map Center: ' . $latitude . ', ' . $longitude . ' (Zoom: ' . $zoom . ')<br>';
if ($boundingBox) {
    echo 'Bounding Box:<br>';
    echo 'NE: ' . $boundingBox['northeast'][0] . ', ' . $boundingBox['northeast'][1] . '<br>';
    echo 'SE: ' . $boundingBox['southeast'][0] . ', ' . $boundingBox['southeast'][1] . '<br>';
    echo 'SW: ' . $boundingBox['southwest'][0] . ', ' . $boundingBox['southwest'][1] . '<br>';
    echo 'NW: ' . $boundingBox['northwest'][0] . ', ' . $boundingBox['northwest'][1] . '<br>';
    echo 'Polygon String: ' . $polygonString;
}
echo '</div>';
*/
?>

<div class="rch-container-listing-list">
    <div id="map" class="rch-map-listing-list"></div>
    <div class="rch-under-main-listing">
        <div id="listing-list" class="rch-listing-list rch-with-map">
        </div>

        <div id="rch-loading-listing" style="display: none;" class="rch-listing-skeleton-loader">
            <?php
            // Loop to display 6 skeleton items
            for ($i = 0; $i < 6; $i++) : ?>
                <div class="rch-listing-item-skeleton">
                    <div class="rch-skeleton-image"></div>
                    <h3 class="rch-skeleton-text rch-skeleton-price"></h3>
                    <p class="rch-skeleton-text rch-skeleton-address"></p>
                    <ul>
                        <li class="rch-skeleton-text rch-skeleton-list-item"></li>
                        <li class="rch-skeleton-text rch-skeleton-list-item"></li>
                        <li class="rch-skeleton-text rch-skeleton-list-item"></li>
                    </ul>
                </div>
            <?php endfor; ?>
        </div>
        <?php include RCH_PLUGIN_DIR . 'templates/archive/template-part/listing-pagination.php'; ?>

    </div>

    <input type="hidden" id="query-string" name="query-string" value="<?php echo esc_attr($polygonString); ?>">
    <button id="rch-map-toggle-mobile" class="rch-map-toggle-btn">
        <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG . 'map-view.svg'; ?>" alt="Map Icon">
        <span>
            View Map
        </span>
    </button>
</div>

<!-- Loading spinner -->


</div>
<?php

// Enqueue the new JavaScript file
wp_enqueue_script('rechat-listings-request', RCH_PLUGIN_URL . 'assets/js/rch-listing-request.js', array('jquery'), RCH_VERSION, true);
wp_localize_script('rechat-listings-request', 'rchListingData', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'listingPerPage' => $listingPerPage,
    'totalListing' => $totalLisitng,
    'propertyTypes' => $property_types_array,
    'mapCoordinates' => [
        'latitude' => $latitude,
        'longitude' => $longitude,
        'zoom' => $zoom,
        'boundingBox' => $boundingBox,
        'polygonString' => $polygonString,
        'hasValidCoordinates' => ($latitude != 0 && $longitude != 0)
    ],
    'filters' => array(
        'brand' => $atts['own_listing'] == 1 ? $atts['brand'] : '',
        'minimum_price' => $atts['minimum_price'],
        'maximum_price' => $atts['maximum_price'],
        'minimum_lot_square_meters' => $atts['minimum_lot_square_meters'],
        'maximum_lot_square_meters' => $atts['maximum_lot_square_meters'],
        'minimum_bathrooms' => $atts['minimum_bathrooms'],
        'maximum_bathrooms' => $atts['maximum_bathrooms'],
        'minimum_square_meters' => $atts['minimum_square_meters'],
        'maximum_square_meters' => $atts['maximum_square_meters'],
        'minimum_year_built' => $atts['minimum_year_built'],
        'maximum_year_built' => $atts['maximum_year_built'],
        'minimum_bedrooms' => $atts['minimum_bedrooms'],
        'maximum_bedrooms' => $atts['maximum_bedrooms'],
        'listing_statuses' => $atts['listing_statuses'],
    )
));

// Enqueue the map JavaScript file
wp_enqueue_script('rechat-listings-map', RCH_PLUGIN_URL . 'assets/js/rch-rechat-listings-map.js', array('jquery'), RCH_VERSION, true);
// Make sure we pass the same data to the map script
wp_localize_script('rechat-listings-map', 'rchListingData', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'listingPerPage' => $listingPerPage,
    'totalListing' => $totalLisitng,
    'propertyTypes' => $property_types_array,
    'mapCoordinates' => [
        'latitude' => $latitude,
        'longitude' => $longitude,
        'zoom' => $zoom,
        'boundingBox' => $boundingBox,
        'polygonString' => $polygonString,
        'hasValidCoordinates' => ($latitude != 0 && $longitude != 0)
    ],
    'filters' => array(
        'brand' => $atts['own_listing'] == 1 ? $atts['brand'] : '',
        'minimum_price' => $atts['minimum_price'],
        'maximum_price' => $atts['maximum_price'],
        'minimum_lot_square_meters' => $atts['minimum_lot_square_meters'],
        'maximum_lot_square_meters' => $atts['maximum_lot_square_meters'],
        'minimum_bathrooms' => $atts['minimum_bathrooms'],
        'maximum_bathrooms' => $atts['maximum_bathrooms'],
        'minimum_square_meters' => $atts['minimum_square_meters'],
        'maximum_square_meters' => $atts['maximum_square_meters'],
        'minimum_year_built' => $atts['minimum_year_built'],
        'maximum_year_built' => $atts['maximum_year_built'],
        'minimum_bedrooms' => $atts['minimum_bedrooms'],
        'maximum_bedrooms' => $atts['maximum_bedrooms'],
        'listing_statuses' => $atts['listing_statuses'],
    )
));

// Enqueue Places Autocomplete Script and CSS
wp_enqueue_script('rechat-places-autocomplete', RCH_PLUGIN_URL . 'assets/js/rch-places-autocomplete.js', array('jquery', 'rch-google-maps-api', 'rechat-listings-map'), RCH_VERSION, true);
wp_enqueue_style('rch-places-autocomplete');
?>