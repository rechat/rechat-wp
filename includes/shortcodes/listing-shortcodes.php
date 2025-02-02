<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
/*******************************
 * Renders the listing as a shortcode.
 ******************************/
function rch_render_listing_list($atts)
{
    $atts = shortcode_atts([
        'page' => 1,
        'listing_per_page' => 48,
        'minimum_price' => '',
        'maximum_price' => '',
        'minimum_lot_square_meters' => '',
        'maximum_lot_square_meters' => '',
        'minimum_bathrooms' => '',
        'maximum_bathrooms' => '',
        'minimum_square_meters' => '',
        'maximum_square_meters' => '',
        'minimum_year_built' => '',
        'maximum_year_built' => '',
        'minimum_bedrooms' => '',
        'maximum_bedrooms' => '',
        'brand' => '',
        'listing_statuses' => '',
        'show_filter_bar' => true,
        'own_listing' => true,
        'property_types' => '',
    ], $atts);
    // Get sanitized filters
    $filters = rch_get_filters($atts);
    $page = intval($atts['page']);
    $listingPerPage = intval($atts['listing_per_page']);
    // Fetch total houses for pagination
    $totalListingData = rch_fetch_total_listing_count($filters);
    $totalLisitng = $totalListingData['info']['total'] ?? 0; // Ensure default if empty
    ob_start();
    include RCH_PLUGIN_DIR . 'templates/archive/listings-archive-custom.php';
    return ob_get_clean();
}
add_shortcode('listings', 'rch_render_listing_list');
