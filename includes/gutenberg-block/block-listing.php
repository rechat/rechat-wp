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
    // Map block attributes to shortcode parameters
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
    );
    // Build shortcode string
    $shortcode = '[listings ';
    foreach ($shortcode_params as $param => $value) {
        if ($value !== '') {
            $shortcode .= $param . '="' . esc_attr($value) . '" ';
        }
    }
    $shortcode .= ']';
    // Execute the shortcode and return the output
    return do_shortcode( $shortcode );
}


