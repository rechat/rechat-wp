<?php

if (! defined('ABSPATH')) {
  exit();
}

/**
 * Enqueue Rechat SDK and listing block layout CSS when [listings] renders.
 * Query-string filters (e.g. filter_boundary_ids) are applied server-side via {@see rch_get_fallback_url_parameters()}.
 */
function rch_listings_shortcode_enqueue_assets()
{
  wp_enqueue_style('rechat-sdk-css');
  wp_enqueue_script('rechat-sdk-js');
  wp_enqueue_style('rch-listing-block-css');
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

/**
 * Default attribute keys/values for [listings] and the Listing Gutenberg block.
 *
 * @return array<string, mixed>
 */
function rch_get_listings_default_atts()
{
  return [
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
    'own_listing' => false,
    'property_types' => '',
    'filter_open_houses' => false,
    'office_exclusive' => false,
    'filter_pool' => false,
    'disable_sort' => false,
    'map_latitude' => '',
    'map_longitude' => '',
    'map_zoom' => '12',
    'map_style' => '',
    'map_style_url' => '',
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
    'filter_boundary_country' => '',
    'filter_boundary_state' => '',
  ];
}

/**
 * Map Listing block attributes to [listings] atts (no shortcode string — avoids parse loss).
 *
 * @param array<string, mixed> $block_attributes Block attrs after URL/boundary merge.
 * @return array<string, mixed>
 */
function rch_prepare_listing_atts_from_block(array $block_attributes)
{
  $defaults = rch_get_listings_default_atts();
  $out = $defaults;

  $bool_keys = array(
    'own_listing',
    'disable_filter_address',
    'disable_filter_price',
    'disable_filter_beds',
    'disable_filter_baths',
    'disable_filter_property_types',
    'disable_filter_advanced',
    'disable_filter_loading_indicator',
    'filter_open_houses',
    'office_exclusive',
    'filter_pool',
    'disable_sort',
  );

  foreach ($block_attributes as $key => $value) {
    if (! array_key_exists($key, $defaults)) {
      continue;
    }

    if (in_array($key, $bool_keys, true)) {
      $out[$key] = rch_attr_to_bool($value);
      continue;
    }

    if (is_array($value)) {
      $out[$key] = $value;
      continue;
    }

    if ($value === '' || $value === null) {
      continue;
    }

    $out[$key] = is_scalar($value) ? (string) $value : $value;
  }

  if (
    empty($out['listing_statuses'])
    && ! empty($block_attributes['selectedStatuses'])
    && is_array($block_attributes['selectedStatuses'])
  ) {
    $out['listing_statuses'] = function_exists('rch_expand_listing_status_groups')
      ? rch_expand_listing_status_groups($block_attributes['selectedStatuses'])
      : $block_attributes['selectedStatuses'];
  }

  if (! empty($out['listing_statuses']) && function_exists('rch_expand_listing_status_groups')) {
    $out['listing_statuses'] = rch_expand_listing_status_groups($out['listing_statuses']);
  }

  if (array_key_exists('own_listing', $block_attributes)) {
    $out['own_listing'] = rch_attr_to_bool($block_attributes['own_listing']);
  }

  return $out;
}

/*******************************
 * Renders the listing as a shortcode based on Gutenberg block
 * Usage: [listings property_types="Residential" listing_statuses="Active" filter_pool="true" filter_search_limit="200"]
 ******************************/
function rch_render_listing_list($atts)
{
  // Parse shortcode attributes with defaults
  $atts = shortcode_atts(rch_get_listings_default_atts(), $atts);

  if (function_exists('rch_multisite_merge_subsite_listing_scope_atts')) {
    $atts = rch_multisite_merge_subsite_listing_scope_atts($atts);
  }

  $atts['own_listing'] = rch_attr_to_bool($atts['own_listing']);

  // brand for rechat-root brand_id; own_listing / filter_brand_id set filter_brand_id on <rechat-listings> via rch_get_rechat_listings_attributes()
  $atts['brand'] = get_option('rch_rechat_brand_id');
  $atts['disable_filter_address'] = rch_attr_to_bool($atts['disable_filter_address']);
  $atts['disable_filter_price'] = rch_attr_to_bool($atts['disable_filter_price']);
  $atts['disable_filter_beds'] = rch_attr_to_bool($atts['disable_filter_beds']);
  $atts['disable_filter_baths'] = rch_attr_to_bool($atts['disable_filter_baths']);
  $atts['disable_filter_property_types'] = rch_attr_to_bool($atts['disable_filter_property_types']);
  $atts['disable_filter_advanced'] = rch_attr_to_bool($atts['disable_filter_advanced']);
  $atts['filter_open_houses'] = rch_attr_to_bool($atts['filter_open_houses']);
  $atts['office_exclusive'] = rch_attr_to_bool($atts['office_exclusive']);
  $atts['filter_pool'] = rch_attr_to_bool($atts['filter_pool']);
  $atts['disable_sort'] = rch_attr_to_bool($atts['disable_sort']);
  $atts['disable_filter_loading_indicator'] = rch_attr_to_bool($atts['disable_filter_loading_indicator']);

  rch_listings_shortcode_enqueue_assets();

  $primary_color = get_option('_rch_primary_color');

  // Merge GET query into shortcode atts (search redirect, bookmarks) — replaces former JS restore.
  $url_params = function_exists('rch_get_fallback_url_parameters') ? rch_get_fallback_url_parameters() : array();
  if (! is_array($url_params)) {
    $url_params = array();
  }

  if (! empty($url_params)) {
    if (isset($url_params['content'])) {
      $atts['filter_address'] = $url_params['content'];
    }
    if (isset($url_params['property_type'])) {
      $atts['property_types'] = $url_params['property_type'];
    }
    if (isset($url_params['property_types'])) {
      $atts['property_types'] = $url_params['property_types'];
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
    if (isset($url_params['maximum_bathrooms']) && $url_params['maximum_bathrooms'] !== 'null') {
      $atts['maximum_bathrooms'] = $url_params['maximum_bathrooms'];
    }
    if (isset($url_params['filter_boundary_ids'])) {
      $atts['filter_boundary_ids'] = $url_params['filter_boundary_ids'];
    }
    if (isset($url_params['filter_boundary_country'])) {
      $atts['filter_boundary_country'] = $url_params['filter_boundary_country'];
    }
    if (isset($url_params['filter_boundary_state'])) {
      $atts['filter_boundary_state'] = $url_params['filter_boundary_state'];
    }
    if (isset($url_params['sort_by'])) {
      $atts['sort_by'] = $url_params['sort_by'];
    }
    if (isset($url_params['listing_statuses'])) {
      $atts['listing_statuses'] = $url_params['listing_statuses'];
    }
    if (isset($url_params['map_zoom'])) {
      $atts['map_zoom'] = $url_params['map_zoom'];
    }
    if (isset($url_params['map_latitude'])) {
      $atts['map_latitude'] = $url_params['map_latitude'];
    }
    if (isset($url_params['map_longitude'])) {
      $atts['map_longitude'] = $url_params['map_longitude'];
    }
  }

  $atts = rch_apply_listing_boundary_site_defaults($atts);

  // listing_statuses: explode comma string to array for rch_sanitize_listing_statuses()
  if (! empty($atts['listing_statuses']) && is_string($atts['listing_statuses'])) {
    $atts['listing_statuses'] = array_map('trim', explode(',', $atts['listing_statuses']));
  }

  $listing_statuses_str = rch_sanitize_listing_statuses($atts['listing_statuses'] ?? array());
  $map_default_center = rch_get_map_default_center(
    $atts['map_latitude'] ?? '',
    $atts['map_longitude'] ?? ''
  );
  if (! empty($url_params['map_center'])) {
    $map_default_center = sanitize_text_field($url_params['map_center']);
  }

  $wrapper_classes = 'rch-listing-block-gutenberg';
  $wrapper_style_attr = rch_listings_shortcode_wrapper_style_attr($primary_color);

  // Get rechat root attributes (only brand_id in new SDK)
  $rechat_attrs = rch_get_rechat_root_attributes($atts, $map_default_center, $listing_statuses_str);

  // Get rechat-listings attributes (all filter/map attributes in new SDK)
  $rechat_listings_attrs = rch_get_rechat_listings_attributes($atts, $map_default_center, $listing_statuses_str);

  // MapLibre + viewport on <rechat-map>
  $rechat_map_attrs = rch_get_rechat_map_attributes($atts, $map_default_center);

  // Check if all filters are disabled
  $all_filters_disabled = $atts['disable_filter_address'] &&
    $atts['disable_filter_price'] &&
    $atts['disable_filter_beds'] &&
    $atts['disable_filter_baths'] &&
    $atts['disable_filter_property_types'] &&
    $atts['disable_filter_advanced'];

  ob_start();

?>
  <div class="<?php echo esc_attr($wrapper_classes); ?>" <?php echo $wrapper_style_attr; ?>>
    <rechat-root <?php echo $rechat_attrs; ?>>
      <rechat-listings <?php echo $rechat_listings_attrs; ?>>
        <div class="rechat-shell">
          <?php if (!$all_filters_disabled): ?>
              <?php echo rch_render_listing_filters_html($atts); ?>
          <?php endif; ?>
          <?php if (!$atts['disable_sort']): ?>
            <rechat-listings-sort></rechat-listings-sort>
          <?php endif; ?>
          <rechat-map<?php echo $rechat_map_attrs !== '' ? ' ' . $rechat_map_attrs : ''; ?>></rechat-map>
            <rechat-map-listings-grid></rechat-map-listings-grid>
            <rechat-listings-pagination></rechat-listings-pagination>
        </div>
      </rechat-listings>
    </rechat-root>
  </div>
<?php

  return ob_get_clean();
}
add_shortcode('listings', 'rch_render_listing_list');
