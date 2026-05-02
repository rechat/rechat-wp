<?php

if (! defined('ABSPATH')) {
  exit();
}

/**
 * Enqueue Rechat SDK, listing block layout CSS, and URL/history filter script when [listings] renders.
 */
function rch_listings_shortcode_enqueue_assets()
{
  wp_enqueue_style('rechat-sdk-css');
  wp_enqueue_script('rechat-sdk-js');
  wp_enqueue_style('rch-listing-block-css');
  wp_enqueue_script('rch-listings-shortcode-filters');
}

/**
 * Layout modifier for flex ratios (see assets/css/rch-listing-block.css).
 *
 * @param string $layout_style layout2 | layout3 | default
 * @return string Extra class names (space-prefixed safe for empty)
 */
function rch_listings_shortcode_layout_modifier_class($layout_style)
{
  switch ($layout_style) {
    case 'layout2':
      return 'rch-listing-shortcode--layout2';
    case 'layout3':
      return 'rch-listing-shortcode--layout3';
    default:
      return '';
  }
}

/**
 * Inline style attribute only for CSS variables (colors from theme options).
 *
 * @param string $primary_color Stored primary hex or empty
 * @return string HTML ` style="..."` or empty when no usable color
 */
function rch_listings_shortcode_wrapper_style_attr($primary_color)
{
  $primary = ($primary_color !== '' && $primary_color !== null) ? $primary_color : '#2563eb';
  $on_primary = rch_get_contrast_text_color($primary);
  $css = sprintf(
    '--rch-listings-primary:%s;--rch-listings-on-primary:%s;',
    $primary,
    $on_primary
  );

  return ' style="' . esc_attr($css) . '"';
}

/*******************************
 * Renders the listing as a shortcode based on Gutenberg block
 * Usage: [listings property_types="Residential" listing_statuses="Active" layout_style="layout2" filter_pool="true" filter_search_limit="200"]
 ******************************/
function rch_render_listing_list($atts)
{
  // Parse shortcode attributes with defaults
  $atts = shortcode_atts([
    'minimum_price' => '',
    'maximum_price' => '',
    'minimum_square_feet' => '',
    'maximum_square_feet' => '',
    'minimum_bathrooms' => '',
    'maximum_bathrooms' => '',
    'minimum_lot_square_feet' => '',
    'maximum_lot_square_feet' => '',
    'minimum_year_built' => '',
    'maximum_year_built' => '',
    'minimum_bedrooms' => '',
    'maximum_bedrooms' => '',
    'listing_per_page' => '',
    'brand' => '',
    'listing_statuses' => '',
    'disable_filter_address' => false,
    'disable_filter_price' => false,
    'disable_filter_beds' => false,
    'disable_filter_baths' => false,
    'disable_filter_property_types' => false,
    'disable_filter_advanced' => false,
    'disable_filter_loading_indicator' => false,
    'layout_style' => 'default',
    'own_listing' => true,
    'property_types' => '',
    'filter_open_houses' => false,
    'office_exclusive' => false,
    'filter_pool' => false,
    'disable_sort' => false,
    'map_latitude' => '',
    'map_longitude' => '',
    'map_zoom' => '12',
    'map_id' => '',
    'filter_address' => '',
    'filter_search_limit' => '',
    'filter_suggestions_limit' => '',
    'filter_pagination_offset' => '',
    'property_subtypes' => '',
    'architectural_styles' => '',
    'filter_baths' => '',
    'minimum_parking_spaces' => '',
    'minimum_sold_date' => '',
    'filter_agents' => '',
    'list_offices' => '',
    'filter_brand_id' => '',
    'sort_by' => '-list_date',
  ], $atts);

  // Convert listing_statuses string to array if needed
  if (!empty($atts['listing_statuses']) && is_string($atts['listing_statuses'])) {
    $atts['listing_statuses'] = array_map('trim', explode(',', $atts['listing_statuses']));
  }
  // Convert boolean attributes from strings
  $atts['own_listing'] = filter_var($atts['own_listing'], FILTER_VALIDATE_BOOLEAN);

  // Always set brand_id from settings (rechat-root always needs it)
  $atts['brand'] = get_option('rch_rechat_brand_id');
  $atts['disable_filter_address'] = filter_var($atts['disable_filter_address'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_price'] = filter_var($atts['disable_filter_price'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_beds'] = filter_var($atts['disable_filter_beds'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_baths'] = filter_var($atts['disable_filter_baths'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_property_types'] = filter_var($atts['disable_filter_property_types'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_advanced'] = filter_var($atts['disable_filter_advanced'], FILTER_VALIDATE_BOOLEAN);
  $atts['filter_open_houses'] = filter_var($atts['filter_open_houses'], FILTER_VALIDATE_BOOLEAN);
  $atts['office_exclusive'] = filter_var($atts['office_exclusive'], FILTER_VALIDATE_BOOLEAN);
  $atts['filter_pool'] = filter_var($atts['filter_pool'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_sort'] = filter_var($atts['disable_sort'], FILTER_VALIDATE_BOOLEAN);
  $atts['disable_filter_loading_indicator'] = filter_var($atts['disable_filter_loading_indicator'], FILTER_VALIDATE_BOOLEAN);

  rch_listings_shortcode_enqueue_assets();

  // Sanitize and prepare data
  $listing_statuses_str = rch_sanitize_listing_statuses($atts['listing_statuses'] ?? []);
  $map_default_center = rch_get_map_default_center(
    $atts['map_latitude'] ?? '',
    $atts['map_longitude'] ?? ''
  );
  $layout_style = isset($atts['layout_style']) ? sanitize_text_field($atts['layout_style']) : 'default';
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

  $layout_modifier = rch_listings_shortcode_layout_modifier_class($layout_style);
  $wrapper_classes = trim('rch-listing-block-gutenberg ' . $layout_modifier);
  $wrapper_style_attr = rch_listings_shortcode_wrapper_style_attr($primary_color);

  // Get rechat root attributes (only brand_id in new SDK)
  $rechat_attrs = rch_get_rechat_root_attributes($atts, $map_default_center, $listing_statuses_str);

  // Get rechat-listings attributes (all filter/map attributes in new SDK)
  $rechat_listings_attrs = rch_get_rechat_listings_attributes($atts, $map_default_center, $listing_statuses_str);

  // Check if all filters are disabled
  $all_filters_disabled = $atts['disable_filter_address'] &&
    $atts['disable_filter_price'] &&
    $atts['disable_filter_beds'] &&
    $atts['disable_filter_baths'] &&
    $atts['disable_filter_property_types'] &&
    $atts['disable_filter_advanced'];

  ob_start();

?>
  <div class="<?php echo esc_attr($wrapper_classes); ?>"<?php echo $wrapper_style_attr; ?>>
    <rechat-root <?php echo $rechat_attrs; ?>>
      <rechat-listings <?php echo $rechat_listings_attrs; ?>>
        <div class="container_listing_sdk">
          <div class="filters">
            <?php if (!$all_filters_disabled): ?>
              <rechat-map-filter></rechat-map-filter>
            <?php endif; ?>
            <?php if (!$atts['disable_sort']): ?>
              <rechat-listings-sort></rechat-listings-sort>
            <?php endif; ?>
          </div>

          <?php if ($layout_style === 'layout2'): ?>
            <div class="wrapper">
              <div class="listings">
                <rechat-map-listings-grid></rechat-map-listings-grid>
              </div>
              <div class="map">
                <rechat-map></rechat-map>
              </div>
            </div>
          <?php else: ?>
            <div class="wrapper">
              <div class="map">
                <rechat-map></rechat-map>
              </div>
              <div class="listings">
                <rechat-map-listings-grid></rechat-map-listings-grid>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </rechat-listings>
    </rechat-root>
  </div>
<?php

  return ob_get_clean();
}
add_shortcode('listings', 'rch_render_listing_list');
