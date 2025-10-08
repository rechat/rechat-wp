<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * this code for Listing gutenbberg block
 ******************************/
/*******************************
 * Register Listing block in php
 ******************************/
function rch_register_block_assets_listing()
{
    register_block_type('rch-rechat-plugin/listing-block', array(
        'editor_script' => 'rch-gutenberg-js',
        'attributes' => array(
            'minimum_price' => array('type' => 'string', 'default' => ''),
            'maximum_price' => array('type' => 'string', 'default' => ''),
            'minimum_lot_square_meters' => array('type' => 'string', 'default' => ''),
            'maximum_lot_square_meters' => array('type' => 'string', 'default' => ''),
            'minimum_bathrooms' => array('type' => 'string', 'default' => ''),
            'maximum_bathrooms' => array('type' => 'string', 'default' => ''),
            'minimum_square_meters' => array('type' => 'string', 'default' => ''),
            'maximum_square_meters' => array('type' => 'string', 'default' => ''),
            'minimum_year_built' => array('type' => 'string', 'default' => ''),
            'maximum_year_built' => array('type' => 'string', 'default' => ''),
            'minimum_bedrooms' => array('type' => 'string', 'default' => ''),
            'maximum_bedrooms' => array('type' => 'string', 'default' => ''),
            'listing_per_page' => array('type' => 'string', 'default' => 5),
            'filterByRegions' => array('type' => 'string', 'default' => ''),
            'filterByOffices' => array('type' => 'string', 'default' => ''),
            'brand' => array('type' => 'string', 'default' => get_option('rch_rechat_brand_id')),
            'selectedStatuses' => array('type' => 'array', 'default' => []), // New attribute
            'listing_statuses' => array('type' => 'array', 'default' => []), // New attribute
            'show_filter_bar' => array('type' => 'boolean', 'default' => true), // New attribute
            'own_listing' => array('type' => 'boolean', 'default' => true), // New attribute
            'property_types' => array('type' => 'string', 'default' => ''), // New attribute
            'map_latitude' => array('type' => 'string', 'default' => ''), // Map location attribute
            'map_longitude' => array('type' => 'string', 'default' => ''), // Map location attribute
            'map_zoom' => array('type' => 'string', 'default' => '12'), // Map zoom attribute
        ),
        'render_callback' => 'rch_render_listing_block',
    ));
}
add_action('init', 'rch_register_block_assets_listing');

/*******************************
 * callback function for Lisitng block
 ******************************/
function rch_render_listing_block($attributes)
{
    // Get URL parameters first (if they exist)
    // Use the existing function from search_listing_shortcode.php if available
    if (function_exists('rch_get_url_parameters')) {
        $url_params = rch_get_url_parameters();
    } else {
        // Fallback if the function is not available
        $url_params = array();
        $allowed_params = array(
            'content',
            'minimum_price',
            'maximum_price',
            'minimum_lot_square_meters',
            'maximum_lot_square_meters',
            'minimum_bathrooms',
            'maximum_bathrooms',
            'minimum_square_meters',
            'maximum_square_meters',
            'minimum_year_built',
            'maximum_year_built',
            'minimum_bedrooms',
            'maximum_bedrooms',
            'property_types',
            'listing_statuses',
            'postal_codes'
        );

        // Check for URL parameters and sanitize them
        foreach ($allowed_params as $param) {
            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                $url_params[$param] = sanitize_text_field($_GET[$param]);
            }
        }
    }

    // Special handling for parameters that need to be properly formatted
    if (isset($url_params['minimum_bathrooms'])) {
        $url_params['minimum_bathrooms'] = intval($url_params['minimum_bathrooms']);
    }

    // Map block attributes to shortcode parameters (block attributes are the default values)
    $shortcode_params = array(
        'minimum_price' => isset($attributes['minimum_price']) ? $attributes['minimum_price'] : '',
        'maximum_price' => isset($attributes['maximum_price']) ? $attributes['maximum_price'] : '',
        'minimum_lot_square_meters' => isset($attributes['minimum_lot_square_meters']) ? $attributes['minimum_lot_square_meters'] : '',
        'maximum_lot_square_meters' => isset($attributes['maximum_lot_square_meters']) ? $attributes['maximum_lot_square_meters'] : '',
        'minimum_bathrooms' => isset($attributes['minimum_bathrooms']) ? $attributes['minimum_bathrooms'] : '',
        'maximum_bathrooms' => isset($attributes['maximum_bathrooms']) ? $attributes['maximum_bathrooms'] : '',
        'minimum_square_meters' => isset($attributes['minimum_square_meters']) ? $attributes['minimum_square_meters'] : '',
        'maximum_square_meters' => isset($attributes['maximum_square_meters']) ? $attributes['maximum_square_meters'] : '',
        'minimum_year_built' => isset($attributes['minimum_year_built']) ? $attributes['minimum_year_built'] : '',
        'maximum_year_built' => isset($attributes['maximum_year_built']) ? $attributes['maximum_year_built'] : '',
        'minimum_bedrooms' => isset($attributes['minimum_bedrooms']) ? $attributes['minimum_bedrooms'] : '',
        'maximum_bedrooms' => isset($attributes['maximum_bedrooms']) ? $attributes['maximum_bedrooms'] : '',
        'listing_per_page' => isset($attributes['listing_per_page']) ? $attributes['listing_per_page'] : 5,
        'brand' => isset($attributes['brand']) ? $attributes['brand'] : get_option('rch_rechat_brand_id'),
        'listing_statuses' => isset($attributes['listing_statuses']) ? implode(',', $attributes['listing_statuses']) : '',
        'show_filter_bar' => isset($attributes['show_filter_bar']) ? $attributes['show_filter_bar'] : '',
        'own_listing' => isset($attributes['own_listing']) ? $attributes['own_listing'] : false,
        'property_types' => isset($attributes['property_types']) ?  $attributes['property_types'] : '',
        'map_latitude' => isset($attributes['map_latitude']) ? $attributes['map_latitude'] : '',
        'map_longitude' => isset($attributes['map_longitude']) ? $attributes['map_longitude'] : '',
        'map_zoom' => isset($attributes['map_zoom']) ? $attributes['map_zoom'] : '12',
    );

    // Override default values with URL parameters if they exist
    $shortcode_params = array_merge($shortcode_params, $url_params);
    // Build shortcode string
    $shortcode = '[listings ';
    foreach ($shortcode_params as $param => $value) {
        if ($value !== '') {
            $shortcode .= $param . '="' . esc_attr($value) . '" ';
        }
    }
    $shortcode .= ']';
    // Execute the shortcode and return the output
    return do_shortcode($shortcode);
}
