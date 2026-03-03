<?php

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Enqueue Rechat SDK and Swiper assets globally
 */
function rch_latest_listings_enqueue_assets()
{
    wp_enqueue_style('rechat-sdk-css');
    wp_enqueue_script('rechat-sdk-js');
    
    // Enqueue Swiper library for the shortcode
    wp_enqueue_style('rch-swiper');
    wp_enqueue_script('rch-swiper-js');
}
add_action('wp_enqueue_scripts', 'rch_latest_listings_enqueue_assets');

/**
 * Latest Listings Web Component Shortcode
 * Usage: [rch_latest_listings property_types="Residential" listing_statuses="Active"]
 */
function rch_display_latest_listings_shortcode($atts)
{
    // Generate unique ID for this shortcode instance
    static $instance = 0;
    $instance++;
    $unique_id = 'rch-latest-listings-' . $instance;

    // Parse shortcode attributes with defaults
    $atts = shortcode_atts([
        'display_type' => 'swiper', // 'swiper' or 'grid'
        'template' => '',
        'content' => '',
        'map_points' => '',
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
        'listing_per_page' => '10',
        'brand' => '',
        'listing_statuses' => '',
        'own_listing' => false,
        'property_types' => '',
        'map_latitude' => '',
        'map_longitude' => '',
        'sort_by' => '-list_date',
        'order_by' => '-price',
        'filter_address' => '',
        'open_houses_only' => false,
        // Swiper settings
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
        'pagination_render_bullet' => '',
        'navigation' => 'false',
    ], $atts);

    // Convert boolean attributes from strings
    $atts['own_listing'] = filter_var($atts['own_listing'], FILTER_VALIDATE_BOOLEAN);
    $atts['open_houses_only'] = filter_var($atts['open_houses_only'], FILTER_VALIDATE_BOOLEAN);

    // Process display_type
    $display_type = esc_attr($atts['display_type']);
    $template = esc_js($atts['template']);
    $map_points = esc_js($atts['map_points']);

    // Process Swiper boolean attributes
    $swiper_config = [
        'loop' => filter_var($atts['loop'], FILTER_VALIDATE_BOOLEAN),
        'centeredSlides' => filter_var($atts['centered_slides'], FILTER_VALIDATE_BOOLEAN),
        'grabCursor' => filter_var($atts['grab_cursor'], FILTER_VALIDATE_BOOLEAN),
        'simulateTouch' => filter_var($atts['simulate_touch'], FILTER_VALIDATE_BOOLEAN),
        'speed' => intval($atts['speed']),
        'effect' => esc_js($atts['effect']),
        'spaceBetween' => is_numeric($atts['space_between']) ? intval($atts['space_between']) : 32,
    ];

    // Handle slidesPerView (can be number or 'auto')
    if ($atts['slides_per_view'] === 'auto') {
        $swiper_config['slidesPerView'] = 'auto';
    } elseif (is_numeric($atts['slides_per_view'])) {
        $swiper_config['slidesPerView'] = floatval($atts['slides_per_view']);
    } else {
        $swiper_config['slidesPerView'] = 'auto';
    }

    // Parse autoplay if provided (expects JSON string)
    if (!empty($atts['autoplay'])) {
        $autoplay_decoded = json_decode(html_entity_decode($atts['autoplay']), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($autoplay_decoded)) {
            $swiper_config['autoplay'] = $autoplay_decoded;
        }
    }

    // Parse breakpoints if provided (expects JSON string)
    if (!empty($atts['breakpoints'])) {
        $breakpoints_decoded = json_decode(html_entity_decode($atts['breakpoints']), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($breakpoints_decoded)) {
            $swiper_config['breakpoints'] = $breakpoints_decoded;
        }
    }

    // Handle pagination
    if (filter_var($atts['pagination'], FILTER_VALIDATE_BOOLEAN)) {
        $swiper_config['pagination'] = [
            'el' => '.swiper-pagination',
            'clickable' => filter_var($atts['pagination_clickable'], FILTER_VALIDATE_BOOLEAN),
            'type' => esc_js($atts['pagination_type']),
        ];
    }

    // Handle navigation
    if (filter_var($atts['navigation'], FILTER_VALIDATE_BOOLEAN)) {
        $swiper_config['navigation'] = [
            'nextEl' => '.swiper-button-next',
            'prevEl' => '.swiper-button-prev',
        ];
    }

    // Add coverflow effect settings if effect is coverflow
    if ($swiper_config['effect'] === 'coverflow') {
        $swiper_config['coverflowEffect'] = [
            'rotate' => 0,
            'stretch' => 0,
            'depth' => 40,
            'modifier' => 2,
            'slideShadows' => false
        ];
    }

    // Convert swiper config to JSON for JavaScript
    $swiper_config_json = wp_json_encode($swiper_config);

    // Map order_by from human-friendly values to API values
    $order_by_raw = isset($atts['order_by']) ? trim($atts['order_by']) : $atts['sort_by'];
    if (strcasecmp($order_by_raw, 'Date') === 0 || strcasecmp($order_by_raw, 'list_date') === 0 || $order_by_raw === '-list_date') {
        $atts['sort_by'] = '-list_date';
    } elseif (strcasecmp($order_by_raw, 'Price') === 0 || strcasecmp($order_by_raw, 'price') === 0 || $order_by_raw === '-price') {
        $atts['sort_by'] = '-price';
    } else {
        $atts['sort_by'] = $order_by_raw;
    }

    // Set brand only if own_listing is true
    if ($atts['own_listing']) {
        $atts['brand'] = get_option('rch_rechat_brand_id');
    } else {
        $atts['brand'] = '';
    }

    // Map property_types based on user input
    $property_types_raw = trim($atts['property_types']);
    if (!empty($property_types_raw)) {
        switch ($property_types_raw) {
            case 'All Listings':
                $atts['property_types'] = 'Residential,Residential Lease,Lots & Acreage,Commercial,Multi-Family';
                break;
            case 'Sale':
                $atts['property_types'] = 'Residential,Lots & Acreage,Commercial,Multi-Family';
                break;
            case 'Lease':
                $atts['property_types'] = 'Residential Lease';
                break;
            case 'Lots & Acreage':
                $atts['property_types'] = 'Lots & Acreage';
                break;
            case 'Commercial':
                $atts['property_types'] = 'Commercial';
                break;
            case 'Residential':
                $atts['property_types'] = 'Residential';
                break;
            default:
                // If it doesn't match any predefined values, use it as-is
                $atts['property_types'] = $property_types_raw;
                break;
        }
    }

    // Process listing_statuses attribute with extended mapping
    $listing_statuses_raw = trim($atts['listing_statuses']);
    if (!empty($listing_statuses_raw)) {
        switch ($listing_statuses_raw) {
            case 'Active':
                $atts['listing_statuses'] = 'Active,Incoming,Coming Soon,Pending';
                break;
            case 'Closed':
                $atts['listing_statuses'] = 'Sold,Leased';
                break;
            case 'Archived':
                $atts['listing_statuses'] = 'Withdrawn,Expired';
                break;
            default:
                // If it doesn't match any predefined values, use it as-is
                $atts['listing_statuses'] = $listing_statuses_raw;
                break;
        }
    }

    // Convert listing_statuses string to array if needed
    if (!empty($atts['listing_statuses']) && is_string($atts['listing_statuses'])) {
        $atts['listing_statuses'] = array_map('trim', explode(',', $atts['listing_statuses']));
    }

    // Sanitize and prepare data
    $listing_statuses_str = rch_sanitize_listing_statuses($atts['listing_statuses'] ?? []);
    $map_default_center = rch_get_map_default_center(
        $atts['map_latitude'] ?? '',
        $atts['map_longitude'] ?? ''
    );

    // Get rechat root attributes using helper function
    $rechat_attrs = rch_get_rechat_root_attributes($atts, $map_default_center, $listing_statuses_str);

    // Start output buffering
    ob_start();
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

    .rch-latest-listings-shortcode-swiper .swiper-button-prev svg,
    .rch-latest-listings-shortcode-swiper .swiper-button-next svg {
      width: 12px;
      height: 12px;
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

<?php if ($display_type === 'swiper'): ?>
<div class="main-listing-sdk rch-latest-listings-shortcode-swiper" id="<?php echo esc_attr($unique_id); ?>">
    <rechat-root <?php echo $rechat_attrs; ?>>
      <div class="swiper">
        <rechat-listings-list class="swiper-wrapper"></rechat-listings-list>

        <?php if (filter_var($atts['pagination'], FILTER_VALIDATE_BOOLEAN)): ?>
        <div class="swiper-pagination"></div>
        <?php endif; ?>

        <?php if (filter_var($atts['navigation'], FILTER_VALIDATE_BOOLEAN)): ?>
        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
        <?php endif; ?>
      </div>
    </rechat-root>
</div>
<?php else: ?>
<div class="rch-grid-container <?php echo esc_attr($template); ?>-grid" id="<?php echo esc_attr($unique_id); ?>">
    <rechat-root <?php echo $rechat_attrs; ?>>
      <rechat-listings-list></rechat-listings-list>
    </rechat-root>
</div>
<?php endif; ?>

  <script>
    (function() {
      const uniqueId = '<?php echo esc_js($unique_id); ?>';
      const displayType = '<?php echo esc_js($display_type); ?>';
      const swiperConfig = <?php echo $swiper_config_json; ?>;
      
      function initSwiperInstance() {
        // Only initialize Swiper if display_type is swiper
        if (displayType !== 'swiper') return;

        const container = document.getElementById(uniqueId);
        if (!container) return;
        
        const swiperEl = container.querySelector('.swiper');
        if (!swiperEl) return;
        
        // Check if Swiper is available
        if (typeof Swiper === 'undefined') {
          console.warn('Swiper library is not loaded yet. Retrying...');
          setTimeout(initSwiperInstance, 100);
          return;
        }
        
        // Add common settings
        swiperConfig.slideClass = 'rechat-listings-list__item';
        
        // Only add pagination if element exists
        const paginationEl = container.querySelector('.swiper-pagination');
        if (paginationEl && swiperConfig.pagination) {
          swiperConfig.pagination.el = paginationEl;
        }
        
        // Only add navigation if elements exist
        const nextEl = container.querySelector('.swiper-button-next');
        const prevEl = container.querySelector('.swiper-button-prev');
        if (nextEl && prevEl && swiperConfig.navigation) {
          swiperConfig.navigation.nextEl = nextEl;
          swiperConfig.navigation.prevEl = prevEl;
        }

        new Swiper(swiperEl, swiperConfig);
      }

      window.addEventListener('rechat-listings:fetched', () => {
        requestAnimationFrame(() => initSwiperInstance())
      });
    })();
  </script>
<?php

    return ob_get_clean();
}
add_shortcode('rch_latest_listings', 'rch_display_latest_listings_shortcode');
