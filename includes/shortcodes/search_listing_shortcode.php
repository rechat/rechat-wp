<?php

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Creates a reusable search form for listings that can be placed anywhere on the site
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
        'title' => 'Find Your Perfect Home', // Optional title
        'show_title' => true, // Whether to show the title
        'compact' => false, // Whether to use a compact layout
        // Field visibility controls
        'show_search' => true, // City search field
        'show_property_type' => true, // Property type dropdown
        'show_bathrooms' => true, // Min bathrooms dropdown
        'show_min_bedrooms' => true, // Min bedrooms dropdown
        'show_max_bedrooms' => true, // Max bedrooms dropdown
        'show_min_price' => true, // Min price dropdown
        'show_max_price' => true, // Max price dropdown
    ], $atts);

    // Sanitize attributes
    $target_page = esc_url($atts['target_page']);
    $title = sanitize_text_field($atts['title']);
    $show_title = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);
    $compact = filter_var($atts['compact'], FILTER_VALIDATE_BOOLEAN);
    
    // Sanitize field visibility controls
    $show_search = filter_var($atts['show_search'], FILTER_VALIDATE_BOOLEAN);
    $show_property_type = filter_var($atts['show_property_type'], FILTER_VALIDATE_BOOLEAN);
    $show_bathrooms = filter_var($atts['show_bathrooms'], FILTER_VALIDATE_BOOLEAN);
    $show_min_bedrooms = filter_var($atts['show_min_bedrooms'], FILTER_VALIDATE_BOOLEAN);
    $show_max_bedrooms = filter_var($atts['show_max_bedrooms'], FILTER_VALIDATE_BOOLEAN);
    $show_min_price = filter_var($atts['show_min_price'], FILTER_VALIDATE_BOOLEAN);
    $show_max_price = filter_var($atts['show_max_price'], FILTER_VALIDATE_BOOLEAN);

    // Start output buffering
    ob_start();

    // Add custom CSS class for the search form
    $form_class = $compact ? 'rch-search-form-compact' : 'rch-search-form-full';
?>
    <div class="rch-search-listing-form <?php echo esc_attr($form_class); ?>">
        <?php if ($show_title): ?>
            <h3 class="rch-search-form-title"><?php echo esc_html($title); ?></h3>
        <?php endif; ?>

        <form id="rch-search-form" action="<?php echo esc_url($target_page); ?>" method="get" class="rch-search-form-widget">
            <?php
            // Set a flag to indicate we're rendering the search form, not the main filters
            $is_search_form = true;

            // Include the filters template with our custom flag and field visibility controls
            include RCH_PLUGIN_DIR . 'templates/search/search-form-filters.php';
            ?>

            <div class="rch-search-form-submit">
                <button type="submit" class="rch-search-submit-btn">Search</button>
            </div>
        </form>
    </div>

<?php
    // Enqueue the necessary scripts and styles
    wp_enqueue_script('rechat-search-form', RCH_PLUGIN_URL . 'assets/js/rch-search-form.js', array('jquery'), RCH_VERSION, true);

    // Enqueue Google Maps API and Places Autocomplete scripts
    wp_enqueue_script('rch-google-maps-api'); // Use the consolidated Google Maps API script
    wp_enqueue_script('rechat-search-form-autocomplete', RCH_PLUGIN_URL . 'assets/js/rch-search-form-autocomplete.js', array('jquery', 'rch-google-maps-api'), RCH_VERSION, true);
    wp_enqueue_style('rch-places-autocomplete');

    return ob_get_clean();
}
add_shortcode('rch_search_listing_form', 'rch_search_listing_form_shortcode');

/**
 * Register custom styles for the search form
 */
function rch_search_form_styles()
{
    wp_enqueue_style('rechat-listings-filter', RCH_PLUGIN_URL . 'assets/css/search_bar_listing_shortcode.css', array(), RCH_VERSION);
}
add_action('wp_enqueue_scripts', 'rch_search_form_styles');

/**
 * Modify the existing listings shortcode to accept URL parameters
 */
function rch_get_url_parameters()
{
    // List of allowed filter parameters to retrieve from URL
    $filter_params = array(
        'minimum_price',
        'maximum_price',
        'minimum_bathrooms',
        'maximum_bathrooms',
        'minimum_bedrooms',
        'maximum_bedrooms',
        'minimum_square_meters',
        'maximum_square_meters',
        'minimum_year_built',
        'maximum_year_built',
        'minimum_lot_square_meters',
        'maximum_lot_square_meters',
        'property_types',
        'listing_statuses',
        'content',
        'postal_codes',
        'place_coords',
        'place_polygon_string',
        'address'
    );

    $params = array();

    // Loop through allowed parameters and get values from URL
    foreach ($filter_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            // Special type handling for certain parameters
            switch ($param) {
                case 'minimum_price':
                case 'maximum_price':
                case 'minimum_bedrooms':
                case 'maximum_bedrooms':
                case 'minimum_bathrooms':
                case 'maximum_bathrooms':
                case 'minimum_year_built':
                case 'maximum_year_built':
                    $params[$param] = intval(sanitize_text_field($_GET[$param]));
                    break;

                case 'minimum_square_meters':
                case 'maximum_square_meters':
                case 'minimum_lot_square_meters':
                case 'maximum_lot_square_meters':
                    $params[$param] = floatval(sanitize_text_field($_GET[$param]));
                    break;

                case 'place_coords':
                    // Extract coordinates from place_coords parameter
                    $coords = sanitize_text_field($_GET[$param]);

                    // If coordinates are present, calculate bounds and generate polygon
                    if (strpos($coords, ',') !== false) {
                        list($lat, $lng) = explode(',', $coords, 2);

                        // Convert to floats
                        $lat = floatval($lat);
                        $lng = floatval($lng);

                        // Store coordinates
                        $params['map_latitude'] = $lat;
                        $params['map_longitude'] = $lng;
                        
                        // Check if we have a pre-calculated polygon string from the search form
                        if (isset($_GET['place_polygon_string']) && !empty($_GET['place_polygon_string'])) {
                            // Use the polygon string directly from the search form
                            $polygonString = sanitize_text_field($_GET['place_polygon_string']);
                            $params['map_points'] = $polygonString;
                            $params['map_zoom'] = 12;
                            
                            error_log('Using place_polygon_string from URL: ' . $polygonString);
                        } else {
                            // Fallback: Calculate polygon from coordinates using zoom
                            $zoom = 12;

                            // Calculate bounding box if the functions are available
                            if (function_exists('rch_calculate_bounding_box') && function_exists('rch_generate_polygon_string')) {
                                $boundingBox = rch_calculate_bounding_box($lat, $lng, $zoom);
                                $polygonString = rch_generate_polygon_string($boundingBox);

                                // Add these values to the parameters
                                $params['map_zoom'] = $zoom;
                                $params['map_points'] = $polygonString;
                                
                                error_log('Calculated polygon from coordinates: ' . $polygonString);
                            }
                        }
                    }
                    break;

                case 'place_polygon_string':
                    // This is handled in the place_coords case above
                    // Skip it here to avoid duplicate processing
                    break;

                default:
                    $params[$param] = sanitize_text_field($_GET[$param]);
                    break;
            }
        }
    }

    return $params;
}

/**
 * Filter the listings shortcode attributes to include URL parameters
 */
function rch_filter_listings_shortcode_atts($out, $pairs, $atts, $shortcode)
{
    if ('listings' !== $shortcode) {
        return $out;
    }

    // Get URL parameters
    $url_params = rch_get_url_parameters();

    // For debugging - you can remove this in production
    if (!empty($url_params) && defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Search Form URL Parameters: ' . print_r($url_params, true));
        error_log('Original Shortcode Attributes: ' . print_r($out, true));
        error_log('Merged Attributes: ' . print_r(array_merge($out, $url_params), true));
    }

    // Merge URL parameters with shortcode attributes, URL parameters take precedence
    return array_merge($out, $url_params);
}
add_filter('shortcode_atts_listings', 'rch_filter_listings_shortcode_atts', 10, 4);
