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
    $tagnames[] = 'rechat-listing-filters';
    $tagnames[] = 'rechat-map';
    $tagnames[] = 'rechat-listings-grid';
    return $tagnames;
}
add_filter('no_texturize_tags', 'rch_disable_wptexturize_on_rechat_tags');

/*******************************
 * Render callback function for Listing block
 ******************************/
function rch_render_listing_block($attributes)
{
    // Get URL parameters (if function exists from search_listing_shortcode.php)
    $url_params = function_exists('rch_get_url_parameters') 
        ? rch_get_url_parameters() 
        : rch_get_fallback_url_parameters();

    // Handle special parameter formatting
    if (isset($url_params['minimum_bathrooms'])) {
        $url_params['minimum_bathrooms'] = intval($url_params['minimum_bathrooms']);
    }

    // Sanitize and prepare data
    $listing_statuses_str = rch_sanitize_listing_statuses($attributes['listing_statuses'] ?? []);
    $map_default_center = rch_get_map_default_center(
        $attributes['map_latitude'] ?? '',
        $attributes['map_longitude'] ?? ''
    );
    $layout_style = isset($attributes['layout_style']) ? sanitize_text_field($attributes['layout_style']) : 'default';
    $agent_data = rch_sanitize_agent_data($attributes);
    $primary_color = get_option('_rch_primary_color');

    // Start output buffering
    ob_start();

    // Render styles
    echo rch_render_layout_styles($layout_style, $primary_color);

    // Render main content
    echo rch_render_listing_block_content($attributes, $agent_data, $layout_style, $map_default_center, $listing_statuses_str);

    return ob_get_clean();
}

/*******************************
 * Get fallback URL parameters if rch_get_url_parameters doesn't exist
 ******************************/
function rch_get_fallback_url_parameters()
{
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

    foreach ($allowed_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $url_params[$param] = sanitize_text_field($_GET[$param]);
        }
    }

    return $url_params;
}

/*******************************
 * Render the main listing block content
 ******************************/
function rch_render_listing_block_content($attributes, $agent_data, $layout_style, $map_default_center, $listing_statuses_str)
{
    $rechat_attrs = rch_get_rechat_root_attributes($attributes, $map_default_center, $listing_statuses_str);
    $agent_card_html = rch_render_agent_card($agent_data);

    ob_start();
    ?>
    <div class="rch-listing-block-gutenberg">
        <rechat-root <?php echo $rechat_attrs; ?>>
            <div class="container_listing_sdk">
                <div class="filters">
                    <rechat-listing-filters></rechat-listing-filters>
                </div>

                <?php if ($layout_style === 'layout2'): ?>
                    <?php echo rch_render_layout_wrapper($agent_card_html, 'layout2'); ?>
                <?php else: ?>
                    <?php echo rch_render_layout_wrapper($agent_card_html, 'default'); ?>
                <?php endif; ?>
            </div>
        </rechat-root>
    </div>
    <?php
    return ob_get_clean();
}

/*******************************
 * Render layout wrapper with map and listings
 ******************************/
function rch_render_layout_wrapper($agent_card_html, $layout_type)
{
    ob_start();
    ?>
    <div class="wrapper">
        <?php if ($layout_type === 'layout2'): ?>
            <div class="listings">
                <rechat-listings-grid></rechat-listings-grid>
            </div>
            <div class="map">
                <?php echo $agent_card_html; ?>
                <rechat-map></rechat-map>
            </div>
        <?php else: ?>
            <div class="map">
                <?php echo $agent_card_html; ?>
                <rechat-map></rechat-map>
            </div>
            <div class="listings">
                <rechat-listings-grid></rechat-listings-grid>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
