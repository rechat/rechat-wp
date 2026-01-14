<?php

if (! defined('ABSPATH')) {
    exit();
}

/*******************************
 * Renders the listing as a shortcode based on Gutenberg block
 * Usage: [listings property_types="Residential" listing_statuses="Active" layout_style="layout2"]
 ******************************/
function rch_render_listing_list($atts)
{
    // Parse shortcode attributes with defaults
    $atts = shortcode_atts([
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
        'listing_per_page' => '5',
        'brand' => get_option('rch_rechat_brand_id'),
        'listing_statuses' => '',
        'disable_filter_address' => false,
        'disable_filter_price' => false,
        'disable_filter_beds' => false,
        'disable_filter_baths' => false,
        'disable_filter_property_types' => false,
        'disable_filter_advanced' => false,
        'layout_style' => 'default',
        'show_agent_card' => false,
        'agent_image' => '',
        'agent_name' => '',
        'agent_title' => '',
        'agent_phone' => '',
        'agent_email' => '',
        'agent_address' => '',
        'own_listing' => true,
        'property_types' => '',
        'map_latitude' => '',
        'map_longitude' => '',
        'map_zoom' => '12',
        'sort_by' => '-list_date',
    ], $atts);

    // Convert listing_statuses string to array if needed
    if (!empty($atts['listing_statuses']) && is_string($atts['listing_statuses'])) {
        $atts['listing_statuses'] = array_map('trim', explode(',', $atts['listing_statuses']));
    }
    // Convert boolean attributes from strings
    $atts['own_listing'] = filter_var($atts['own_listing'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_agent_card'] = filter_var($atts['show_agent_card'], FILTER_VALIDATE_BOOLEAN);
    $atts['disable_filter_address'] = filter_var($atts['disable_filter_address'], FILTER_VALIDATE_BOOLEAN);
    $atts['disable_filter_price'] = filter_var($atts['disable_filter_price'], FILTER_VALIDATE_BOOLEAN);
    $atts['disable_filter_beds'] = filter_var($atts['disable_filter_beds'], FILTER_VALIDATE_BOOLEAN);
    $atts['disable_filter_baths'] = filter_var($atts['disable_filter_baths'], FILTER_VALIDATE_BOOLEAN);
    $atts['disable_filter_property_types'] = filter_var($atts['disable_filter_property_types'], FILTER_VALIDATE_BOOLEAN);
    $atts['disable_filter_advanced'] = filter_var($atts['disable_filter_advanced'], FILTER_VALIDATE_BOOLEAN);

    // Sanitize and prepare data
    $listing_statuses_str = rch_sanitize_listing_statuses($atts['listing_statuses'] ?? []);
    $map_default_center = rch_get_map_default_center(
        $atts['map_latitude'] ?? '',
        $atts['map_longitude'] ?? ''
    );
    $layout_style = isset($atts['layout_style']) ? sanitize_text_field($atts['layout_style']) : 'default';
    $agent_data = rch_sanitize_agent_data($atts);
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

    // Get rechat root attributes
    $rechat_attrs = rch_get_rechat_root_attributes($atts, $map_default_center, $listing_statuses_str);
    $agent_card_html = rch_render_agent_card($agent_data);

    ?>
    <div class="rch-listing-block-gutenberg">
        <rechat-root <?php echo $rechat_attrs; ?>>
            <div class="container_listing_sdk">
                <div class="filters">
                    <rechat-map-filter></rechat-map-filter>
                    <rechat-listings-sort></rechat-listings-sort>
                </div>

                <?php if ($layout_style === 'layout2'): ?>
                    <div class="wrapper">
                        <div class="listings">
                            <rechat-map-listings-grid></rechat-map-listings-grid>
                        </div>
                        <div class="map">
                            <?php echo $agent_card_html; ?>
                            <rechat-map></rechat-map>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="wrapper">
                        <div class="map">
                            <?php echo $agent_card_html; ?>
                            <rechat-map></rechat-map>
                        </div>
                        <div class="listings">
                            <rechat-map-listings-grid></rechat-map-listings-grid>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </rechat-root>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('listings', 'rch_render_listing_list');
