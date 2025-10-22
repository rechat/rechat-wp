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
 * Disable wptexturize for custom web components
 ******************************/
function rch_disable_wptexturize_on_rechat_tags($tagnames) {
    $tagnames[] = 'rechat-root';
    $tagnames[] = 'rechat-listing-filters';
    $tagnames[] = 'rechat-map';
    $tagnames[] = 'rechat-listings-grid';
    return $tagnames;
}
add_filter('no_texturize_tags', 'rch_disable_wptexturize_on_rechat_tags');

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
    // $shortcode_params = array(
    //     'minimum_price' => isset($attributes['minimum_price']) ? $attributes['minimum_price'] : '',
    //     'maximum_price' => isset($attributes['maximum_price']) ? $attributes['maximum_price'] : '',
    //     'minimum_lot_square_meters' => isset($attributes['minimum_lot_square_meters']) ? $attributes['minimum_lot_square_meters'] : '',
    //     'maximum_lot_square_meters' => isset($attributes['maximum_lot_square_meters']) ? $attributes['maximum_lot_square_meters'] : '',
    //     'minimum_bathrooms' => isset($attributes['minimum_bathrooms']) ? $attributes['minimum_bathrooms'] : '',
    //     'maximum_bathrooms' => isset($attributes['maximum_bathrooms']) ? $attributes['maximum_bathrooms'] : '',
    //     'minimum_square_meters' => isset($attributes['minimum_square_meters']) ? $attributes['minimum_square_meters'] : '',
    //     'maximum_square_meters' => isset($attributes['maximum_square_meters']) ? $attributes['maximum_square_meters'] : '',
    //     'minimum_year_built' => isset($attributes['minimum_year_built']) ? $attributes['minimum_year_built'] : '',
    //     'maximum_year_built' => isset($attributes['maximum_year_built']) ? $attributes['maximum_year_built'] : '',
    //     'minimum_bedrooms' => isset($attributes['minimum_bedrooms']) ? $attributes['minimum_bedrooms'] : '',
    //     'maximum_bedrooms' => isset($attributes['maximum_bedrooms']) ? $attributes['maximum_bedrooms'] : '',
    //     'listing_per_page' => isset($attributes['listing_per_page']) ? $attributes['listing_per_page'] : 5,
    //     'brand' => isset($attributes['brand']) ? $attributes['brand'] : get_option('rch_rechat_brand_id'),
    //     'listing_statuses' => isset($attributes['listing_statuses']) ? implode(',', $attributes['listing_statuses']) : '',
    //     'show_filter_bar' => isset($attributes['show_filter_bar']) ? $attributes['show_filter_bar'] : '',
    //     'own_listing' => isset($attributes['own_listing']) ? $attributes['own_listing'] : false,
    //     'property_types' => isset($attributes['property_types']) ?  $attributes['property_types'] : '',
    //     'map_latitude' => isset($attributes['map_latitude']) ? $attributes['map_latitude'] : '',
    //     'map_longitude' => isset($attributes['map_longitude']) ? $attributes['map_longitude'] : '',
    //     'map_zoom' => isset($attributes['map_zoom']) ? $attributes['map_zoom'] : '12',
    // );
    // Ensure output buffering is active so we can return the rendered HTML

    // Convert listing_statuses attribute (array) into a sanitized, comma-separated string
    $listing_statuses_str = '';
    if (isset($attributes['listing_statuses']) && is_array($attributes['listing_statuses'])) {
        // Sanitize each status and remove empty values
        $sanitized = array();
        foreach ($attributes['listing_statuses'] as $status) {
            $s = sanitize_text_field($status);
            if ($s !== '') {
                $sanitized[] = $s;
            }
        }
        // Join with commas
        $listing_statuses_str = implode(',', $sanitized);
    } elseif (isset($attributes['listing_statuses'])) {
        // If it's not an array, cast to string safely
        $listing_statuses_str = sanitize_text_field((string) $attributes['listing_statuses']);
    }

    // Determine map default center: use provided attributes if both latitude and longitude are set,
    // otherwise fall back to Dallas coordinates (32.7767, -96.797)
    $default_center = '32.7767, -96.797';
    if (!empty($attributes['map_latitude']) && !empty($attributes['map_longitude'])) {
        // sanitize and trim
        $lat = sanitize_text_field($attributes['map_latitude']);
        $lng = sanitize_text_field($attributes['map_longitude']);
        // basic validation: ensure they're numeric
        if (is_numeric($lat) && is_numeric($lng)) {
            $default_center = $lat . ', ' . $lng;
        }
    }
     ob_start();
?>
    <rechat-root
        brand_id="<?php echo esc_attr($attributes['brand']); ?>"
        map_zoom="<?php echo esc_attr($attributes['map_zoom']); ?>"
        map_api_key="<?php echo esc_attr(get_option('rch_rechat_google_map_api_key')); ?>"
        map_default_center="<?php echo esc_attr($default_center); ?>"
        filter_address=""
        disable_price="true"
        filter_minimum_price="<?php echo esc_attr($attributes['minimum_price']); ?>"
        filter_minimum_bathrooms="<?php echo esc_attr($attributes['minimum_bathrooms']); ?>"
        filter_minimum_bedrooms="<?php echo esc_attr($attributes['minimum_bedrooms']); ?>"
        filter_maximum_bedrooms="<?php echo esc_attr($attributes['maximum_bedrooms']); ?>"
        filter_maximum_year_built="<?php echo esc_attr($attributes['maximum_year_built']); ?>"
        >
        <div class="container_sdk">
            <div class="filters">
                <rechat-listing-filters></rechat-listing-filters>
            </div>

            <div class="wrapper">
                <div class="map">
                    <rechat-map></rechat-map>
                </div>

                <div class="listings">
                    <rechat-listings-grid></rechat-listings-grid>
                </div>
            </div>
        </div>
    </rechat-root>
<?php
    return ob_get_clean();
}
