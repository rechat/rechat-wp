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
    if (! wp_script_is('rch-gutenberg-js', 'registered')) {
        wp_register_script(
            'rch-gutenberg-js',
            RCH_PLUGIN_URL . 'build/index.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-api-fetch'),
            RCH_VERSION,
            true
        );
    }

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
    $tagnames[] = 'rechat-listings';
    $tagnames[] = 'rechat-map-filter';
    $tagnames[] = 'rechat-filter-search';
    $tagnames[] = 'rechat-filter-price';
    $tagnames[] = 'rechat-filter-beds';
    $tagnames[] = 'rechat-filter-baths';
    $tagnames[] = 'rechat-filter-property-type';
    $tagnames[] = 'rechat-filter-advanced';
    $tagnames[] = 'rechat-filter-loading';
    $tagnames[] = 'rechat-listings-sort';
    $tagnames[] = 'rechat-map';
    $tagnames[] = 'rechat-map-listings-grid';
    return $tagnames;
}
add_filter('no_texturize_tags', 'rch_disable_wptexturize_on_rechat_tags');

/*******************************
 * Render callback function for Listing block
 ******************************/
function rch_render_listing_block($attributes)
{
    // Get URL parameters and merge with block attributes
    $url_params = rch_get_fallback_url_parameters();
    
    // Handle map_center from URL - convert to map_latitude and map_longitude
    if (!empty($url_params['map_center'])) {
        $coords = explode(',', $url_params['map_center']);
        if (count($coords) === 2) {
            $url_params['map_latitude'] = trim($coords[0]);
            $url_params['map_longitude'] = trim($coords[1]);
        }
        unset($url_params['map_center']);
    }
    
    // Merge URL parameters with block attributes (URL params take precedence)
    $attributes = array_merge($attributes, array_filter($url_params, function($value) {
        return $value !== '' && $value !== null;
    }));

    $attributes = rch_apply_listing_boundary_site_defaults($attributes);

    $listing_atts = rch_prepare_listing_atts_from_block($attributes);

    return rch_render_listing_list($listing_atts);
}

/*******************************
 * Get fallback URL parameters if rch_get_url_parameters doesn't exist
 ******************************/
function rch_get_fallback_url_parameters()
{
    $url_params = array();
    $allowed_params = array(
        'filter_boundary_ids',
        'filter_boundary_country',
        'filter_boundary_state',
        'sort_by',
        'content',
        'property_type',
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
        'postal_codes',
        'map_center',
        'map_latitude',
        'map_longitude',
        'map_zoom',
    );

    foreach ($allowed_params as $param) {
        if (! isset($_GET[$param])) {
            continue;
        }
        $raw = wp_unslash($_GET[$param]);
        if ($raw === '') {
            continue;
        }
        $url_params[$param] = sanitize_text_field((string) $raw);
    }

    return $url_params;
}

/**
 * REST: country options for block editor (cached Rechat boundaries/search + omit[]=boundary.geometry).
 *
 * @return WP_REST_Response|WP_Error
 */
function rch_rest_boundary_countries()
{
    if (! function_exists('rch_rechat_fetch_boundaries_for_settings')) {
        return new WP_Error('rch_no_helper', __('Rechat helpers are not available.', 'rechat-plugin'), array('status' => 500));
    }

    $options = rch_rechat_fetch_boundaries_for_settings('country', '', false);

    return rest_ensure_response(array('options' => $options));
}

/**
 * REST: state/province options for a country (block editor; same boundaries/search omit as countries).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function rch_rest_boundary_states(WP_REST_Request $request)
{
    if (! function_exists('rch_rechat_fetch_boundaries_for_settings')) {
        return new WP_Error('rch_no_helper', __('Rechat helpers are not available.', 'rechat-plugin'), array('status' => 500));
    }

    $country = strtoupper(sanitize_text_field((string) $request->get_param('country')));
    if ($country === '') {
        return new WP_Error('rch_bad_request', __('Country is required.', 'rechat-plugin'), array('status' => 400));
    }

    $options = rch_rechat_fetch_boundaries_for_settings('state', $country, false);

    return rest_ensure_response(array('options' => $options));
}

/**
 * REST: free-text boundary search for the block editor (neighborhoods, places, etc.).
 *
 * Proxies Rechat `boundaries/search?q=…&limit=…` through {@see rch_rechat_public_api_get()}
 * so the editor never hits api.rechat.com directly (avoids CORS + keeps the OAuth token server-side).
 * Returns `{ options: [{ label, value }] }` where `label` is the boundary title and
 * `value` is the boundary UUID (fed into filter_boundary_ids on <rechat-listings>).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function rch_rest_boundary_search(WP_REST_Request $request)
{
    if (! function_exists('rch_rechat_public_api_get')) {
        return new WP_Error('rch_no_helper', __('Rechat helpers are not available.', 'rechat-plugin'), array('status' => 500));
    }

    $q = trim(sanitize_text_field((string) $request->get_param('q')));
    if (strlen($q) < 2) {
        return rest_ensure_response(array('options' => array()));
    }

    $limit = (int) $request->get_param('limit');
    if ($limit <= 0 || $limit > 20) {
        $limit = 5;
    }

    $res = rch_rechat_public_api_get('boundaries/search', array('q' => $q, 'limit' => $limit));
    if (empty($res['success']) || ! is_array($res['data'])) {
        return rest_ensure_response(array('options' => array()));
    }

    $list = array();
    if (isset($res['data']['data']) && is_array($res['data']['data'])) {
        $list = $res['data']['data'];
    }

    $out  = array();
    $seen = array();
    foreach ($list as $row) {
        if (! is_array($row)) {
            continue;
        }
        $label = isset($row['title']) ? trim((string) $row['title']) : '';
        $id    = isset($row['id']) ? trim((string) $row['id']) : '';
        // value = boundary UUID (fed into filter_boundary_ids on <rechat-listings>);
        // label = human title shown in the editor.
        if ($label === '' || $id === '' || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $out[]     = array('label' => $label, 'value' => $id);
        if (count($out) >= $limit) {
            break;
        }
    }

    return rest_ensure_response(array('options' => $out));
}

/**
 * Register REST routes used by the listing block editor (boundary selects).
 */
function rch_register_listing_block_boundary_rest_routes()
{
    register_rest_route(
        'rch/v1',
        '/boundary-search',
        array(
            'methods'             => 'GET',
            'callback'            => 'rch_rest_boundary_search',
            'permission_callback' => static function () {
                return current_user_can('edit_posts');
            },
            'args'                => array(
                'q' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => static function ($param) {
                        return sanitize_text_field((string) $param);
                    },
                ),
                'limit' => array(
                    'required' => false,
                    'type'     => 'integer',
                ),
            ),
        )
    );

    register_rest_route(
        'rch/v1',
        '/boundary-countries',
        array(
            'methods'             => 'GET',
            'callback'            => 'rch_rest_boundary_countries',
            'permission_callback' => static function () {
                return current_user_can('edit_posts');
            },
        )
    );

    register_rest_route(
        'rch/v1',
        '/boundary-states',
        array(
            'methods'             => 'GET',
            'callback'            => 'rch_rest_boundary_states',
            'permission_callback' => static function () {
                return current_user_can('edit_posts');
            },
            'args'                => array(
                'country' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => static function ($param) {
                        return strtoupper(sanitize_text_field((string) $param));
                    },
                ),
            ),
        )
    );
}
add_action('rest_api_init', 'rch_register_listing_block_boundary_rest_routes');
