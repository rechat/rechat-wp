<?php

/**
 * Latest Listings Shortcode - Clean Functional Version
 * 
 * Modular implementation using functions for consistency with plugin architecture
 * 
 * @package Rechat
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue SDK, Swiper (when needed), and shortcode stylesheet — call only when the shortcode renders.
 */
function rch_latest_listings_enqueue_assets(array $atts)
{
    // Rechat SDK is enqueued globally on wp_enqueue_scripts (see enqueue-front.php) so it loads in <head>.
    wp_enqueue_style('rch-latest-listings-shortcode');

    if (($atts['display_type'] ?? '') === 'swiper') {
        wp_enqueue_style('rch-swiper');
        wp_enqueue_script('rch-swiper-js');
        wp_enqueue_script('rch-latest-listings-swiper');
    }
}

/**
 * Get default shortcode attributes
 * 
 * @return array Default attributes
 */
function rch_latest_listings_get_defaults()
{
    return [
        'display_type' => 'swiper',
        'listing_per_page' => '10',
        'limit' => '',
        'brand' => '',
        'listing_statuses' => '',
        'expand_status_aliases' => 'true',
        'own_listing' => false,
        'property_types' => '',
        'sort_by' => '-list_date',
        'order_by' => '',
        'open_houses_only' => false,
        'filter_open_houses' => false,
        'office_exclusive' => false,
        // Filters
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
        'filter_address' => '',
        'map_latitude' => '',
        'map_longitude' => '',
        'map_default_center' => '',
        'map_zoom' => '',
        'map_style' => '',
        'map_style_url' => '',
        'map_id' => '',
        // Pass-through to rch_get_rechat_listings_attributes (Rechat SDK on <rechat-listings>)
        'filter_search_limit' => '',
        'filter_suggestions_limit' => '',
        'filter_pagination_offset' => '',
        'property_subtypes' => '',
        'architectural_styles' => '',
        'filter_baths' => '',
        'minimum_parking_spaces' => '',
        'minimum_sold_date' => '',
        'filter_pool' => false,
        'filter_agents' => '',
        'list_offices' => '',
        'filter_brand_id' => '',
        'disable_filter_address' => false,
        'disable_filter_price' => false,
        'disable_filter_beds' => false,
        'disable_filter_baths' => false,
        'disable_filter_property_types' => false,
        'disable_filter_advanced' => false,
        'disable_filter_loading_indicator' => false,
        'listing_hyperlink_href' => '',
        'listing_hyperlink_target' => '',
        // Swiper configuration
        'slides_per_view' => 'auto',
        'space_between' => '32',
        'loop' => 'false',
        'centered_slides' => 'false',
        'speed' => '300',
        'effect' => 'slide',
        'grab_cursor' => 'true',
        'simulate_touch' => 'true',
        'autoplay' => '',
        'breakpoints' => '',
        'pagination' => 'false',
        'pagination_clickable' => 'false',
        'pagination_type' => 'bullets',
        'navigation' => 'false',
        // Advanced
        'template' => '',
        'content' => '',
        'map_points' => '',
    ];
}

/**
 * Get property type mappings
 * 
 * @return array Property type mappings
 */
function rch_latest_listings_get_property_type_mappings()
{
    return [
        'All Listings' => 'Residential,Residential Lease,Lots & Acreage,Commercial,Multi-Family',
        'Sale' => 'Residential,Lots & Acreage,Commercial,Multi-Family',
        'Lease' => 'Residential Lease',
        'Lots & Acreage' => 'Lots & Acreage',
        'Commercial' => 'Commercial',
        'Residential' => 'Residential',
    ];
}

/**
 * Get listing status mappings
 * 
 * @return array Listing status mappings
 */
function rch_latest_listings_get_status_mappings()
{
    if (! function_exists('rch_get_listing_status_group_mappings')) {
        return [
            'Active'   => 'Active, Active Contingent, Active Kick Out, Active Option Contract, Active Under Contract',
            'Pending'  => 'Pending',
            'Closed'   => 'Sold, Leased',
            'Archived' => 'Withdrawn, Expired',
        ];
    }

    $out = [];

    foreach (rch_get_listing_status_group_mappings() as $group => $statuses) {
        $out[ $group ] = implode(',', $statuses);
    }

    return $out;
}

/**
 * Get sort order mappings
 * 
 * @return array Sort order mappings
 */
function rch_latest_listings_get_sort_mappings()
{
    return [
        'Date' => '-list_date',
        'list_date' => '-list_date',
        '-list_date' => '-list_date',
        'Price' => '-price',
        'price' => '-price',
        '-price' => '-price',
    ];
}

/**
 * Generate unique ID for shortcode instance
 * 
 * @return string Unique ID
 */
function rch_latest_listings_generate_id()
{
    static $instance_count = 0;
    $instance_count++;
    return 'rch-latest-listings-' . $instance_count;
}

/**
 * Normalize boolean attributes
 * 
 * @param array $atts Attributes to normalize
 * @return array Normalized attributes
 */
function rch_latest_listings_normalize_booleans($atts)
{
    $boolean_fields = [
        'own_listing',
        'open_houses_only',
        'filter_open_houses',
        'office_exclusive',
        'filter_pool',
        'loop',
        'centered_slides',
        'grab_cursor',
        'simulate_touch',
        'pagination',
        'pagination_clickable',
        'navigation',
        'disable_filter_address',
        'disable_filter_price',
        'disable_filter_beds',
        'disable_filter_baths',
        'disable_filter_property_types',
        'disable_filter_advanced',
        'disable_filter_loading_indicator',
        'expand_status_aliases',
    ];

    foreach ($boolean_fields as $field) {
        if (isset($atts[$field])) {
            $atts[$field] = filter_var($atts[$field], FILTER_VALIDATE_BOOLEAN);
        }
    }

    return $atts;
}

/**
 * Process property type mappings
 * 
 * @param array $atts Attributes
 * @return array Processed attributes
 */
function rch_latest_listings_process_property_types($atts)
{
    $raw_value = trim($atts['property_types']);

    if (empty($raw_value)) {
        return $atts;
    }

    $mappings = rch_latest_listings_get_property_type_mappings();

    if (isset($mappings[$raw_value])) {
        $atts['property_types'] = $mappings[$raw_value];
    }

    return $atts;
}

/**
 * Process listing status mappings
 * 
 * @param array $atts Attributes
 * @return array Processed attributes
 */
function rch_latest_listings_process_statuses($atts)
{
    $raw_value = trim($atts['listing_statuses']);

    if (empty($raw_value)) {
        return $atts;
    }

    $expand_aliases = filter_var($atts['expand_status_aliases'] ?? true, FILTER_VALIDATE_BOOLEAN);

    if ($expand_aliases) {
        $mappings = rch_latest_listings_get_status_mappings();

        if (isset($mappings[$raw_value])) {
            $atts['listing_statuses'] = $mappings[$raw_value];
        }
    }

    // Convert string to array
    if (is_string($atts['listing_statuses'])) {
        $atts['listing_statuses'] = array_map('trim', explode(',', $atts['listing_statuses']));
    }

    return $atts;
}

/**
 * Process sort order mappings
 * 
 * @param array $atts Attributes
 * @return array Processed attributes
 */
function rch_latest_listings_process_sort_order($atts)
{
    if (empty($atts['order_by'])) {
        return $atts;
    }

    $raw_value = trim($atts['order_by']);
    $mappings = rch_latest_listings_get_sort_mappings();

    // Try case-insensitive match
    $key = null;
    foreach ($mappings as $map_key => $map_value) {
        if (strcasecmp($raw_value, $map_key) === 0) {
            $key = $map_key;
            break;
        }
    }

    if ($key && isset($mappings[$key])) {
        $atts['sort_by'] = $mappings[$key];
    } else {
        $atts['sort_by'] = $raw_value;
    }

    return $atts;
}

/**
 * Parse and normalize shortcode attributes
 * 
 * @param array $atts Raw shortcode attributes
 * @return array Processed attributes
 */
function rch_latest_listings_parse_attributes($atts)
{
    // Merge with defaults
    $atts = shortcode_atts(rch_latest_listings_get_defaults(), $atts);

    $atts['display_type'] = strtolower(trim((string) $atts['display_type']));
    $allowed_display = ['swiper', 'normal', 'grid'];
    if (!in_array($atts['display_type'], $allowed_display, true)) {
        $atts['display_type'] = 'swiper';
    }

    // Process aliases (limit → listing_per_page)
    if (!empty($atts['limit'])) {
        $atts['listing_per_page'] = $atts['limit'];
    }

    // Normalize boolean values
    $atts = rch_latest_listings_normalize_booleans($atts);

    // Legacy open_houses_only and explicit filter_open_houses both drive the SDK attribute
    $atts['filter_open_houses'] = $atts['filter_open_houses'] || $atts['open_houses_only'];

    // Process mappings
    $atts = rch_latest_listings_process_property_types($atts);
    $atts = rch_latest_listings_process_statuses($atts);
    $atts = rch_latest_listings_process_sort_order($atts);

    // Set brand from settings
    $atts['brand'] = get_option('rch_rechat_brand_id');

    return $atts;
}

/**
 * Build Swiper configuration
 * 
 * @param array $atts Shortcode attributes
 * @return array Swiper configuration
 */
function rch_latest_listings_build_swiper_config($atts)
{
    $config = [
        'loop' => $atts['loop'],
        'centeredSlides' => $atts['centered_slides'],
        'grabCursor' => $atts['grab_cursor'],
        'simulateTouch' => $atts['simulate_touch'],
        'speed' => intval($atts['speed']),
        'effect' => esc_js($atts['effect']),
        'spaceBetween' => is_numeric($atts['space_between']) ? intval($atts['space_between']) : 32,
    ];

    // Handle slidesPerView
    $config['slidesPerView'] = $atts['slides_per_view'] === 'auto' ? 'auto' : (is_numeric($atts['slides_per_view']) ? floatval($atts['slides_per_view']) : 'auto');

    // Parse autoplay JSON
    if (!empty($atts['autoplay'])) {
        $autoplay = json_decode(html_entity_decode($atts['autoplay']), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($autoplay)) {
            $config['autoplay'] = $autoplay;
        }
    }

    // Parse breakpoints JSON
    if (!empty($atts['breakpoints'])) {
        $breakpoints = json_decode(html_entity_decode($atts['breakpoints']), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($breakpoints)) {
            $config['breakpoints'] = $breakpoints;
        }
    }

    // Add pagination config
    if ($atts['pagination']) {
        $config['pagination'] = [
            'el' => '.swiper-pagination',
            'clickable' => $atts['pagination_clickable'],
            'type' => esc_js($atts['pagination_type']),
        ];
    }

    // Add navigation config
    if ($atts['navigation']) {
        $config['navigation'] = [
            'nextEl' => '.swiper-button-next',
            'prevEl' => '.swiper-button-prev',
        ];
    }

    // Add coverflow effect settings
    if ($config['effect'] === 'coverflow') {
        $config['coverflowEffect'] = [
            'rotate' => 0,
            'stretch' => 0,
            'depth' => 40,
            'modifier' => 2,
            'slideShadows' => false,
        ];
    }

    return $config;
}

/**
 * Render Swiper layout HTML
 * 
 * @param array $atts Attributes
 * @param string $unique_id Unique ID
 * @param string $rechat_attrs Rechat root attributes
 * @param string $rechat_listings_attrs Rechat listings attributes
 */
function rch_latest_listings_render_swiper($atts, $unique_id, $rechat_attrs, $rechat_listings_attrs)
{
?>
    <div class="main-listing-sdk rch-latest-listings-shortcode-swiper" id="<?php echo esc_attr($unique_id); ?>">
        <rechat-root <?php echo $rechat_attrs; ?>>
            <rechat-listings <?php echo $rechat_listings_attrs; ?>>
                <div class="swiper">
                    <rechat-listings-list class="swiper-wrapper"></rechat-listings-list>

                    <?php if ($atts['pagination']): ?>
                        <div class="swiper-pagination"></div>
                    <?php endif; ?>

                    <?php if ($atts['navigation']): ?>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    <?php endif; ?>
                </div>
            </rechat-listings>
        </rechat-root>
    </div>
<?php
}

/**
 * Render normal list + SDK pagination (same structure as agent listings section)
 *
 * @param array  $atts                  Attributes
 * @param string $unique_id             Unique ID
 * @param string $rechat_attrs          Rechat root attributes
 * @param string $rechat_listings_attrs Rechat listings attributes
 */
function rch_latest_listings_render_normal($atts, $unique_id, $rechat_attrs, $rechat_listings_attrs)
{
    // filter_pagination_limit is already set from listing_per_page in rch_get_rechat_listings_attributes()
?>
    <div class="main-listing-sdk rch-latest-listings-shortcode-normal" id="<?php echo esc_attr($unique_id); ?>">
        <div class="rch-latest-listings-normal-inner">
            <rechat-root <?php echo $rechat_attrs; ?>>
                <rechat-listings <?php echo $rechat_listings_attrs; ?>>
                    <div>
                        <rechat-listings-list />
                    </div>

                    <div class="pagination">
                        <rechat-listings-pagination />
                    </div>
                </rechat-listings>
            </rechat-root>
        </div>
    </div>
<?php
}

/**
 * Render Grid layout HTML
 * 
 * @param array $atts Attributes
 * @param string $unique_id Unique ID
 * @param string $rechat_attrs Rechat root attributes
 * @param string $rechat_listings_attrs Rechat listings attributes
 */
function rch_latest_listings_render_grid($atts, $unique_id, $rechat_attrs, $rechat_listings_attrs)
{
    $template_class = !empty($atts['template']) ? esc_attr($atts['template']) : 'default';
?>
    <div class="rch-grid-container <?php echo $template_class; ?>-grid" id="<?php echo esc_attr($unique_id); ?>">
        <rechat-root <?php echo $rechat_attrs; ?>>
            <rechat-listings <?php echo $rechat_listings_attrs; ?>>
                <rechat-listings-list></rechat-listings-list>
            </rechat-listings>
        </rechat-root>
    </div>
<?php
}

/**
 * Queue Swiper init for this shortcode instance (inline bootstrap after main script).
 *
 * @param string $unique_id Unique instance ID.
 * @param array  $atts      Processed attributes.
 */
function rch_latest_listings_enqueue_swiper_instance_script($unique_id, array $atts)
{
    if ($atts['display_type'] !== 'swiper') {
        return;
    }

    if (!wp_script_is('rch-latest-listings-swiper', 'enqueued')) {
        wp_enqueue_script('rch-latest-listings-swiper');
    }

    $swiper_config = rch_latest_listings_build_swiper_config($atts);
    $bootstrap = sprintf(
        'window.rchLatestListingsSwiperRegister(%s,%s);',
        wp_json_encode($unique_id),
        wp_json_encode($swiper_config)
    );

    wp_add_inline_script('rch-latest-listings-swiper', $bootstrap, 'after');
}

/**
 * Latest Listings Shortcode Handler
 * 
 * Usage: [rch_latest_listings property_types="Residential" listing_statuses="Active" filter_search_limit="200"]
 * display_type: swiper (default) | normal (list + pagination) | grid (simple grid, no Swiper)
 * map_default_center: optional "lat, lng" string passed to rechat-listings (overrides map_latitude/map_longitude when set).
 * All optional filters supported by rch_get_rechat_listings_attributes (e.g. filter_search_limit, filter_pool) may be passed; see main [listings] shortcode for the full set.
 * 
 * @param array $atts Shortcode attributes
 * @return string Rendered HTML
 */
function rch_display_latest_listings_shortcode($atts)
{
    // Parse and normalize attributes
    $atts = rch_latest_listings_parse_attributes($atts);

    rch_latest_listings_enqueue_assets($atts);

    // Generate unique ID for this instance
    $unique_id = rch_latest_listings_generate_id();

    // Prepare data for rendering
    $listing_statuses_str = rch_sanitize_listing_statuses($atts['listing_statuses'] ?? []);
    $map_default_center = '';
    $raw_center = trim((string) ($atts['map_default_center'] ?? ''));
    if ($raw_center !== '') {
        $map_default_center = sanitize_text_field($raw_center);
    } else {
        $map_default_center = rch_get_map_default_center(
            $atts['map_latitude'] ?? '',
            $atts['map_longitude'] ?? ''
        );
    }

    // Get rechat attributes using helper functions
    $rechat_attrs = rch_get_rechat_root_attributes($atts, $map_default_center, $listing_statuses_str);
    $rechat_listings_attrs = rch_get_rechat_listings_attributes($atts, $map_default_center, $listing_statuses_str);

    // Start output buffering
    ob_start();

    if ($atts['display_type'] === 'swiper') {
        rch_latest_listings_render_swiper($atts, $unique_id, $rechat_attrs, $rechat_listings_attrs);
    } elseif ($atts['display_type'] === 'normal') {
        rch_latest_listings_render_normal($atts, $unique_id, $rechat_attrs, $rechat_listings_attrs);
    } else {
        rch_latest_listings_render_grid($atts, $unique_id, $rechat_attrs, $rechat_listings_attrs);
    }

    $output = ob_get_clean();
    rch_latest_listings_enqueue_swiper_instance_script($unique_id, $atts);

    return $output;
}
add_shortcode('rch_latest_listings', 'rch_display_latest_listings_shortcode');
