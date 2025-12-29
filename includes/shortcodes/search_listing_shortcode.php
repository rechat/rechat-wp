<?php

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Creates a reusable search form for listings using Rechat web components
 * When submitted, it sends the selected filter values via GET to a listings page
 * 
 * @param array $atts Shortcode attributes
 * @return string The HTML output of the search form
 */
function rch_search_listing_form_shortcode($atts)
{
    // Parse attributes with defaults
    $atts = shortcode_atts([
        'target_page' => '/listings/', // Default target page URL
        'brand_id' => '', // Rechat brand ID
        'map_zoom' => '', // Map zoom level
        'map_api_key' => get_option('rch_rechat_google_map_api_key'), // Google Maps API key
        'map_default_center' => '', // Default map center coordinates
        'filter_address' => '', // Initial address filter
        'disable_filter_price' => 'false', // Disable price filter
        'disable_filter_beds' => 'false', // Disable beds filter
        'filter_minimum_price' => '', // Minimum price filter
        'filter_minimum_bathrooms' => '', // Minimum bathrooms
        'filter_minimum_bedrooms' => '', // Minimum bedrooms
        'filter_maximum_bedrooms' => '', // Maximum bedrooms
        'filter_maximum_year_built' => '', // Maximum year built
        'filter_listing_statuses' => '', // Listing statuses
        'show_background' => 'false', // Show background image
        'background_image' => '', // Background image URL
    ], $atts);

    // Sanitize attributes
    $target_page = esc_url($atts['target_page']);
    $brand_id = sanitize_text_field($atts['brand_id']);
    $map_zoom = sanitize_text_field($atts['map_zoom']);
    $map_api_key = sanitize_text_field($atts['map_api_key']);
    $map_default_center = sanitize_text_field($atts['map_default_center']);
    $filter_address = sanitize_text_field($atts['filter_address']);
    $disable_filter_price = sanitize_text_field($atts['disable_filter_price']);
    $disable_filter_beds = sanitize_text_field($atts['disable_filter_beds']);
    $filter_minimum_price = sanitize_text_field($atts['filter_minimum_price']);
    $filter_minimum_bathrooms = sanitize_text_field($atts['filter_minimum_bathrooms']);
    $filter_minimum_bedrooms = sanitize_text_field($atts['filter_minimum_bedrooms']);
    $filter_maximum_bedrooms = sanitize_text_field($atts['filter_maximum_bedrooms']);
    $filter_maximum_year_built = sanitize_text_field($atts['filter_maximum_year_built']);
    $filter_listing_statuses = sanitize_text_field($atts['filter_listing_statuses']);
    $show_background = filter_var($atts['show_background'], FILTER_VALIDATE_BOOLEAN);
    $background_image = esc_url($atts['background_image']);

    // Prepare attributes for helper function
    $attributes = array(
        'brand' => $brand_id,
        'map_zoom' => $map_zoom,
        'map_api_key' => $map_api_key,
        'filter_address' => $filter_address,
        'minimum_price' => $filter_minimum_price,
        'minimum_bathrooms' => $filter_minimum_bathrooms,
        'minimum_bedrooms' => $filter_minimum_bedrooms,
        'maximum_bedrooms' => $filter_maximum_bedrooms,
        'maximum_year_built' => $filter_maximum_year_built,
        'disable_filter_price' => $disable_filter_price,
        'disable_filter_beds' => $disable_filter_beds,
    );

    // Get rechat-root attributes using helper function
    $rechat_attrs = rch_get_rechat_root_attributes($attributes, $map_default_center, $filter_listing_statuses);

    // Generate unique ID for this form instance
    $form_id = 'rch-search-form-' . uniqid();
$primary_color = get_option('_rch_primary_color');
    // Start output buffering
    ob_start();

    // Render styles
    echo rch_render_search_form_styles($form_id, $show_background, $background_image, $primary_color);
?>
    <div id="<?php echo $form_id; ?>" class="rch-search-listing-form">
        <rechat-root 
      <?php echo $rechat_attrs; ?>
        >
            <div class="container_listing_sdk rch-search-container">
                <?php if ($show_background && $background_image): ?>
                    <div class="rch-search-background"></div>
                <?php endif; ?>
                <rechat-property-search-form></rechat-property-search-form>
            </div>
        </rechat-root>
    </div>

    <script>
    window.addEventListener('rechat-property-search-form:submit', (e) => {
      const filters = e.detail.filters

      const params = new URLSearchParams({
        content: filters.address,
        property_type: filters.property_types[0],
        minimum_price: filters.minimum_price,
        maximum_price: filters.maximum_price,
        minimum_bedrooms: filters.minimum_bedrooms,
        maximum_bedrooms: filters.maximum_bedrooms,
        minimum_bathrooms: filters.minimum_bathrooms,
      })

      const url = `<?php echo esc_url(home_url($target_page)); ?>?${params.toString()}`

      window.location.href = url
    })
    </script>

<?php

    return ob_get_clean();
}
add_shortcode('rch_search_listing_form', 'rch_search_listing_form_shortcode');

/**
 * Register custom styles for the search form
 */
function rch_search_form_styles()
{
        wp_enqueue_style('rechat-sdk-css');
        wp_enqueue_script('rechat-sdk-js');
    }
add_action('wp_enqueue_scripts', 'rch_search_form_styles');