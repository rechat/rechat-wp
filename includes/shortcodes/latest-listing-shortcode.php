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
 * Enqueue required assets for latest listings
 */
function rch_latest_listings_enqueue_assets() {
    wp_enqueue_style('rechat-sdk-css');
    wp_enqueue_script('rechat-sdk-js');
    wp_enqueue_style('rch-swiper');
    wp_enqueue_script('rch-swiper-js');
}
add_action('wp_enqueue_scripts', 'rch_latest_listings_enqueue_assets');

/**
 * Get default shortcode attributes
 * 
 * @return array Default attributes
 */
function rch_latest_listings_get_defaults() {
    return [
        'display_type' => 'swiper',
        'listing_per_page' => '10',
        'limit' => '',
        'brand' => '',
        'listing_statuses' => '',
        'own_listing' => false,
        'property_types' => '',
        'sort_by' => '-list_date',
        'order_by' => '',
        'open_houses_only' => false,
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
        // Swiper configuration
        'slides_per_view' => 'auto',
        'space_between' => '32',
        'loop' => 'false',
        'centered_slides' => 'false',
        'speed' => '300',
        'effect' => 'coverflow',
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
function rch_latest_listings_get_property_type_mappings() {
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
function rch_latest_listings_get_status_mappings() {
    return [
        'Active' => 'Active,Incoming,Coming Soon,Pending',
        'Closed' => 'Sold,Leased',
        'Archived' => 'Withdrawn,Expired',
    ];
}

/**
 * Get sort order mappings
 * 
 * @return array Sort order mappings
 */
function rch_latest_listings_get_sort_mappings() {
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
function rch_latest_listings_generate_id() {
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
function rch_latest_listings_normalize_booleans($atts) {
    $boolean_fields = [
        'own_listing',
        'open_houses_only',
        'loop',
        'centered_slides',
        'grab_cursor',
        'simulate_touch',
        'pagination',
        'pagination_clickable',
        'navigation',
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
function rch_latest_listings_process_property_types($atts) {
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
function rch_latest_listings_process_statuses($atts) {
    $raw_value = trim($atts['listing_statuses']);
    
    if (empty($raw_value)) {
        return $atts;
    }
    
    $mappings = rch_latest_listings_get_status_mappings();
    
    if (isset($mappings[$raw_value])) {
        $atts['listing_statuses'] = $mappings[$raw_value];
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
function rch_latest_listings_process_sort_order($atts) {
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
function rch_latest_listings_parse_attributes($atts) {
    // Merge with defaults
    $atts = shortcode_atts(rch_latest_listings_get_defaults(), $atts);
    
    // Process aliases (limit → listing_per_page)
    if (!empty($atts['limit'])) {
        $atts['listing_per_page'] = $atts['limit'];
    }
    
    // Normalize boolean values
    $atts = rch_latest_listings_normalize_booleans($atts);
    
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
function rch_latest_listings_build_swiper_config($atts) {
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
    $config['slidesPerView'] = $atts['slides_per_view'] === 'auto' ? 'auto' : 
        (is_numeric($atts['slides_per_view']) ? floatval($atts['slides_per_view']) : 'auto');
    
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
 * Render inline styles
 */
function rch_latest_listings_render_styles() {
    ?>
    <style>
    .rch-latest-listings-shortcode-swiper rechat-root {
        display: block;
        width: 100%;
        overflow: hidden;
    }
    
    .rch-latest-listings-shortcode-swiper .swiper {
        width: 100%;
        overflow: hidden;
        padding: 16px 0 40px;
    }
    
    .rch-latest-listings-shortcode-swiper rechat-listings-list.swiper-wrapper {
        display: flex;
    }
    
    .rch-latest-listings-shortcode-swiper .rechat-listings-list__item {
        width: 400px;
        height: auto;
        box-sizing: border-box;
        flex-shrink: 0;
    }
    
    .rch-latest-listings-shortcode-swiper .listing-card {
        max-width: auto;
        border: none;
    }
    
    .rch-latest-listings-shortcode-swiper .swiper-button-prev,
    .rch-latest-listings-shortcode-swiper .swiper-button-next {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: white;
        border: 1px solid #ddd;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .rch-latest-listings-shortcode-swiper .swiper-button-prev:hover,
    .rch-latest-listings-shortcode-swiper .swiper-button-next:hover {
        background: #f5f5f5;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    .rch-latest-listings-shortcode-swiper .swiper-button-prev::after,
    .rch-latest-listings-shortcode-swiper .swiper-button-next::after {
        font-size: 12px;
        font-weight: bold;
        color: #333;
    }
    
    .rch-latest-listings-shortcode-swiper .swiper-pagination {
        bottom: 0 !important;
    }
    
    .rch-latest-listings-shortcode-swiper .swiper-pagination-bullet-active {
        background: #333;
    }
    
    .rch-grid-container {
        display: grid;
        gap: 20px;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
    
    .rch-no-listings-message {
        padding: 40px;
        text-align: center;
        color: #666;
        font-size: 16px;
    }
    </style>
    <?php
}

/**
 * Render Swiper layout HTML
 * 
 * @param array $atts Attributes
 * @param string $unique_id Unique ID
 * @param string $rechat_attrs Rechat root attributes
 * @param string $rechat_listings_attrs Rechat listings attributes
 */
function rch_latest_listings_render_swiper($atts, $unique_id, $rechat_attrs, $rechat_listings_attrs) {
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
 * Render Grid layout HTML
 * 
 * @param array $atts Attributes
 * @param string $unique_id Unique ID
 * @param string $rechat_attrs Rechat root attributes
 * @param string $rechat_listings_attrs Rechat listings attributes
 */
function rch_latest_listings_render_grid($atts, $unique_id, $rechat_attrs, $rechat_listings_attrs) {
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
 * Render initialization scripts
 * 
 * @param array $atts Processed attributes
 * @param string $unique_id Unique instance ID
 */
function rch_latest_listings_render_scripts($atts, $unique_id) {
    // Only initialize Swiper for swiper display type
    if ($atts['display_type'] !== 'swiper') {
        return;
    }
    
    $swiper_config = rch_latest_listings_build_swiper_config($atts);
    $swiper_config_json = wp_json_encode($swiper_config);
    ?>
    <script>
    (function() {
        const uniqueId = <?php echo wp_json_encode($unique_id); ?>;
        const swiperConfig = <?php echo $swiper_config_json; ?>;
        
        function initSwiperInstance() {
            const container = document.getElementById(uniqueId);
            if (!container) return;
            
            const swiperEl = container.querySelector('.swiper');
            if (!swiperEl) return;
            
            // Check if Swiper is available
            if (typeof Swiper === 'undefined') {
                console.warn('Swiper library not loaded yet. Retrying...');
                setTimeout(initSwiperInstance, 100);
                return;
            }
            
            // Add required config
            swiperConfig.slideClass = 'rechat-listings-list__item';
            
            // Update selectors to be instance-specific
            const paginationEl = container.querySelector('.swiper-pagination');
            if (paginationEl && swiperConfig.pagination) {
                swiperConfig.pagination.el = paginationEl;
            }
            
            const nextEl = container.querySelector('.swiper-button-next');
            const prevEl = container.querySelector('.swiper-button-prev');
            if (nextEl && prevEl && swiperConfig.navigation) {
                swiperConfig.navigation.nextEl = nextEl;
                swiperConfig.navigation.prevEl = prevEl;
            }
            
            new Swiper(swiperEl, swiperConfig);
        }
        
        window.addEventListener('rechat-listings:fetched', function() {
            requestAnimationFrame(initSwiperInstance);
        });
    })();
    </script>
    <?php
}

/**
 * Latest Listings Shortcode Handler
 * 
 * Usage: [rch_latest_listings property_types="Residential" listing_statuses="Active"]
 * 
 * @param array $atts Shortcode attributes
 * @return string Rendered HTML
 */
function rch_display_latest_listings_shortcode($atts) {
    // Parse and normalize attributes
    $atts = rch_latest_listings_parse_attributes($atts);
    
    // Generate unique ID for this instance
    $unique_id = rch_latest_listings_generate_id();
    
    // Prepare data for rendering
    $listing_statuses_str = rch_sanitize_listing_statuses($atts['listing_statuses'] ?? []);
    $map_default_center = rch_get_map_default_center(
        $atts['map_latitude'] ?? '',
        $atts['map_longitude'] ?? ''
    );
    
    // Get rechat attributes using helper functions
    $rechat_attrs = rch_get_rechat_root_attributes($atts, $map_default_center, $listing_statuses_str);
    $rechat_listings_attrs = rch_get_rechat_listings_attributes($atts, $map_default_center, $listing_statuses_str);
    
    // Start output buffering
    ob_start();
    
    // Render components
    rch_latest_listings_render_styles();
    
    if ($atts['display_type'] === 'swiper') {
        rch_latest_listings_render_swiper($atts, $unique_id, $rechat_attrs, $rechat_listings_attrs);
    } else {
        rch_latest_listings_render_grid($atts, $unique_id, $rechat_attrs, $rechat_listings_attrs);
    }
    
    rch_latest_listings_render_scripts($atts, $unique_id);
    
    return ob_get_clean();
}
add_shortcode('rch_latest_listings', 'rch_display_latest_listings_shortcode');
