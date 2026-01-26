<?php
if (! defined('ABSPATH')) {
  exit();
}
/*******************************
 * this code for Listing gutenbberg block
 ******************************/
/*******************************
 * Register Listing block
 ******************************/
function rch_register_block_assets_listing()
{
    register_block_type('rch-rechat-plugin/listing-block', array(
        'editor_script' => 'rch-gutenberg-js',
        'attributes' => rch_get_listing_block_attributes(),
        'render_callback' => 'rch_render_listing_block',
    ));
}
add_action('init', 'rch_register_block_assets_listing');

/*******************************
 * Disable wptexturize for custom web components
 ******************************/
function rch_disable_wptexturize_on_rechat_tags($tagnames)
{
    $tagnames[] = 'rechat-root';
    $tagnames[] = 'rechat-map-filter';
    $tagnames[] = 'rechat-map';
    $tagnames[] = 'rechat-map-listings-grid';
    return $tagnames;
}
add_filter('no_texturize_tags', 'rch_disable_wptexturize_on_rechat_tags');

/*******************************
 * Render callback function for Listing block
 ******************************/
function rch_render_listing_block($attributes)
{
    // Build shortcode attributes string from block attributes
    $shortcode_atts = array();
    
    foreach ($attributes as $key => $value) {
        // Always include boolean values (even if false)
        if (is_bool($value)) {
            $shortcode_atts[] = $key . '="' . ($value ? 'true' : 'false') . '"';
        } elseif (!empty($value)) {
            if (is_array($value)) {
                $shortcode_atts[] = $key . '="' . esc_attr(implode(',', $value)) . '"';
            } else {
                $shortcode_atts[] = $key . '="' . esc_attr($value) . '"';
            }
        }
    }
    
    $shortcode_string = '[listings ' . implode(' ', $shortcode_atts) . ']';
    
    return do_shortcode($shortcode_string);
}

/*******************************
 * Get fallback URL parameters if rch_get_url_parameters doesn't exist
 ******************************/
function rch_get_fallback_url_parameters()
{
    $url_params = array();
    $allowed_params = array(
        'content',
        'property_type',
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

    foreach ($allowed_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $url_params[$param] = sanitize_text_field($_GET[$param]);
        }
    }

    return $url_params;
}
