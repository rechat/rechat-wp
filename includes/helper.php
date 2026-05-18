<?php
if (! defined('ABSPATH')) {
    exit();
}

/**
 * True while rch_process_agents_data() is running (same HTTP request).
 * Used to avoid duplicate side effects on save_post during API sync.
 */
function rch_is_doing_agent_sync(): bool
{
    return ! empty($GLOBALS['rch_doing_agent_sync']);
}

/**
 * True while rch_update_agents_offices_regions_data() is processing regions/offices
 * (before agent sync). Avoids duplicate multisite side effects on save_post.
 */
function rch_is_doing_rechat_sync(): bool
{
    return ! empty($GLOBALS['rch_doing_rechat_sync']);
}

/**
 * Cache-busting ?ver= for Rechat SDK handles — uses plugin RCH_VERSION (e.g. 6.3.24).
 */
function rch_get_rechat_sdk_asset_version(): string
{
    if (defined('RCH_RECHAT_SDK_CACHE_VERSION') && (string) RCH_RECHAT_SDK_CACHE_VERSION !== '') {
        return (string) RCH_RECHAT_SDK_CACHE_VERSION;
    }

    return defined('RCH_VERSION') ? (string) RCH_VERSION : '1.0.0';
}

/**
 * Register Rechat SDK CSS/JS once (external CDN). Call before wp_enqueue_style/script('rechat-sdk-*').
 */
function rch_register_rechat_sdk_assets(): void
{
    if (wp_style_is('rechat-sdk-css', 'registered') || wp_script_is('rechat-sdk-js', 'registered')) {
        return;
    }

    $ver = rch_get_rechat_sdk_asset_version();
    $css = defined('RCH_RECHAT_SDK_CSS_URL') ? RCH_RECHAT_SDK_CSS_URL : 'https://unpkg.com/@rechat/sdk@1.4.0/dist/rechat.min.css';
    $js  = defined('RCH_RECHAT_SDK_JS_URL') ? RCH_RECHAT_SDK_JS_URL : 'https://unpkg.com/@rechat/sdk@1.4.0/dist/rechat.min.js';

    wp_register_style('rechat-sdk-css', $css, [], $ver);
    wp_register_script('rechat-sdk-js', $js, [], $ver, false);
}

/*******************************
 * change to readable Date
 ******************************/
function rch_get_token_expiry_date($seconds_until_expire)
{
    // Get the current timestamp
    $current_timestamp = time();

    // Calculate the expiry timestamp
    $expiry_timestamp = $current_timestamp + $seconds_until_expire;

    // Format the expiry timestamp into a readable date in UTC
    return gmdate('Y-m-d H:i:s', $expiry_timestamp);
}

/*******************************
 * OAuth disconnect: handled in includes/oauth2/oauth-handler.php (admin_init).
 ******************************/

/*******************************
 * Helper function to filter brands by type
 ******************************/
function rch_filter_brands_by_type($brands, $types)
{
    $filtered_brands = [];
    foreach ($brands as $brand) {
        if (in_array($brand['brand_type'], $types)) {
            $filtered_brands[] = [
                'id' => $brand['id'],
                'name' => $brand['name'],
                'brand_type' => $brand['brand_type'],
                'base_url' => $brand['base_url'],
                'member_count' => $brand['member_count']
            ];
        }
        // Recursively filter parent brands
        if (isset($brand['parent'])) {
            $filtered_brands = array_merge($filtered_brands, rch_filter_brands_by_type([$brand['parent']], $types));
        }
    }
    return $filtered_brands;
}

/*******************************
 * Calculate bounding box based on center coordinates and zoom level
 ******************************/
function rch_calculate_bounding_box($lat, $lng, $zoom)
{
    // Use a more expansive calculation that matches the JavaScript viewport bounds

    // Calculate zoom scale factor - the lower the zoom level, the larger the area
    // This formula creates a similar view to what Google Maps JavaScript API generates
    // For zoom levels 10-12 (city level), we want a narrower viewport
    $zoomScale = pow(2, 15 - $zoom); // Adjust base value for appropriate coverage

    // For higher zoom levels (>10), use a smaller factor to create a closer view
    $factor = ($zoom >= 10) ? 0.04 : 0.08;

    // Calculate degree offsets based on zoom scale and factor
    // These values are calibrated to create viewport bounds similar to Google Maps
    $latOffset = $zoomScale * $factor; // latitude offset in degrees
    $lngOffset = $zoomScale * $factor / cos(deg2rad($lat)); // longitude offset adjusted for latitude

    // Calculate the four corners
    $north = $lat + $latOffset;
    $south = $lat - $latOffset;
    $east = $lng + $lngOffset;
    $west = $lng - $lngOffset;

    return [
        'northeast' => [$north, $east],
        'southeast' => [$south, $east],
        'southwest' => [$south, $west],
        'northwest' => [$north, $west]
    ];
}

/*******************************
 * Generate polygon string from bounding box coordinates
 ******************************/
function rch_generate_polygon_string($boundingBox)
{
    // Extract coordinates
    $north = $boundingBox['northeast'][0]; // North latitude
    $east = $boundingBox['northeast'][1];  // East longitude
    $south = $boundingBox['southwest'][0]; // South latitude
    $west = $boundingBox['southwest'][1];  // West longitude

    // Create points in exactly the same order as in the JavaScript implementation
    // The order is: NW -> NE -> SE -> SW -> NW (to close the polygon)
    $points = [
        // North-West point
        [$north, $west],

        // North-East point
        [$north, $east],

        // South-East point
        [$south, $east],

        // South-West point
        [$south, $west],

        // North-West point again to close the polygon
        [$north, $west]
    ];

    // Format points as "lat,lng|lat,lng|..." exactly as the JavaScript does
    $polygonString = '';
    foreach ($points as $point) {
        $polygonString .= $point[0] . ',' . $point[1] . '|';
    }

    // Remove the trailing pipe character
    return rtrim($polygonString, '|');
}

/**
 * AJAX handler for calculating polygon from place coordinates
 */
function rch_calculate_polygon_from_place()
{
    // Verify if request has required parameters
    if (!isset($_POST['lat']) || !isset($_POST['lng']) || !isset($_POST['zoom'])) {
        wp_send_json_error(['message' => 'Missing required parameters']);
        return;
    }

    // Get parameters from request
    $lat = floatval($_POST['lat']);
    $lng = floatval($_POST['lng']);
    $zoom = intval($_POST['zoom']);

    // Calculate bounding box and polygon string
    $boundingBox = rch_calculate_bounding_box($lat, $lng, $zoom);
    $polygonString = rch_generate_polygon_string($boundingBox);

    // Send response
    wp_send_json_success([
        'boundingBox' => $boundingBox,
        'polygonString' => $polygonString
    ]);
}

// Register AJAX handlers
add_action('wp_ajax_rch_calculate_polygon_from_place', 'rch_calculate_polygon_from_place');
add_action('wp_ajax_nopriv_rch_calculate_polygon_from_place', 'rch_calculate_polygon_from_place');

/*******************************
 * Function to collect brands recursively
 ******************************/
function rch_collect_brands($brand, &$regions, &$offices, &$processed_brands)
{
    if (in_array($brand['id'], $processed_brands)) {
        return;
    }
    $processed_brands[] = $brand['id'];

    // If the brand is of type 'Region', add it to regions
    if ($brand['brand_type'] == 'Region') {
        $regions[] = $brand;
    }
    // If the brand is of type 'Office'
    elseif ($brand['brand_type'] == 'Office') {
        // Initialize an array to hold region parent IDs
        $region_parent_ids = [];

        // Check if parents array is available and contains region IDs
        if (isset($brand['parents']) && is_array($brand['parents'])) {
            // Loop through all parents in the hierarchy
            foreach ($brand['parents'] as $parent_id) {
                // Check if this parent is already identified as a region
                foreach ($regions as $region) {
                    if ($region['id'] === $parent_id) {
                        $region_parent_ids[] = $parent_id;
                        break;
                    }
                }
            }
        }

        // If no regions found in parents array or parents array doesn't exist,
        // fall back to checking direct parent hierarchy
        if (empty($region_parent_ids)) {
            $current_parent = $brand['parent'] ?? null;
            while ($current_parent) {
                if ($current_parent['brand_type'] == 'Region') {
                    // Add the ID of the Region to the region parent IDs
                    $region_parent_ids[] = $current_parent['id'];
                }

                // Also check if this parent has parents array
                if (isset($current_parent['parents']) && is_array($current_parent['parents'])) {
                    foreach ($current_parent['parents'] as $grandparent_id) {
                        // Check if this grandparent is identified as a region
                        foreach ($regions as $region) {
                            if ($region['id'] === $grandparent_id) {
                                $region_parent_ids[] = $grandparent_id;
                                break;
                            }
                        }
                    }
                }

                // Move up the hierarchy to the next parent
                $current_parent = $current_parent['parent'] ?? null;
            }
        }

        // Extract address and phone from brand or parent hierarchy
        $office_address = '';
        $office_phone = '';

        // Check if address.full and phone_number exist in current brand settings
        if (isset($brand['settings']['marketing_palette']['address']['full']) && !empty($brand['settings']['marketing_palette']['address']['full'])) {
            $office_address = $brand['settings']['marketing_palette']['address']['full'];
        }
        if (isset($brand['settings']['marketing_palette']['phone_number']) && !empty($brand['settings']['marketing_palette']['phone_number'])) {
            $office_phone = $brand['settings']['marketing_palette']['phone_number'];
        }

        // If not found, traverse parent hierarchy
        if (empty($office_address) || empty($office_phone)) {
            $current_parent = $brand['parent'] ?? null;
            while ($current_parent && (empty($office_address) || empty($office_phone))) {
                // Check if current parent has an address.full in settings
                if (empty($office_address) && isset($current_parent['settings']['marketing_palette']['address']['full']) && !empty($current_parent['settings']['marketing_palette']['address']['full'])) {
                    $office_address = $current_parent['settings']['marketing_palette']['address']['full'];
                }
                // Check if current parent has a phone_number in settings
                if (empty($office_phone) && isset($current_parent['settings']['marketing_palette']['phone_number']) && !empty($current_parent['settings']['marketing_palette']['phone_number'])) {
                    $office_phone = $current_parent['settings']['marketing_palette']['phone_number'];
                }

                if (!empty($office_address) && !empty($office_phone)) {
                    break;
                }

                // Also check if this parent has parents array
                if ((empty($office_address) || empty($office_phone)) && isset($current_parent['parents']) && is_array($current_parent['parents'])) {
                    // Check all brands to find parents with addresses and phone numbers
                    foreach ($current_parent['parents'] as $grandparent_id) {
                        // Look for this grandparent in all_brands (if available) or regions
                        foreach ($regions as $region) {
                            if ($region['id'] === $grandparent_id) {
                                if (empty($office_address) && isset($region['settings']['marketing_palette']['address']['full']) && !empty($region['settings']['marketing_palette']['address']['full'])) {
                                    $office_address = $region['settings']['marketing_palette']['address']['full'];
                                }
                                if (empty($office_phone) && isset($region['settings']['marketing_palette']['phone_number']) && !empty($region['settings']['marketing_palette']['phone_number'])) {
                                    $office_phone = $region['settings']['marketing_palette']['phone_number'];
                                }
                                if (!empty($office_address) && !empty($office_phone)) {
                                    break 2; // Break out of both foreach loops
                                }
                            }
                        }
                    }
                }

                // Move up the hierarchy to the next parent
                $current_parent = $current_parent['parent'] ?? null;
            }
        }

        // Add office data along with the collected region parent IDs, address, and phone
        $offices[] = [
            'id' => $brand['id'],
            'name' => $brand['name'],
            'region_parent_ids' => $region_parent_ids,
            'address' => $office_address,
            'phone' => $office_phone,
        ];
    }
}
/*******************************
 * call api
 ******************************/
function rch_api_request($url, $token, $brand = null)
{
    // Initialize headers with Authorization
    $headers = array(
        'Authorization' => 'Bearer ' . $token,
    );

    // Conditionally add X-RECHAT-BRAND if brand is provided
    if (!empty($brand)) {
        $headers['X-RECHAT-BRAND'] = $brand;
    }
    $response = wp_remote_get($url, array('headers' => $headers));
    if (is_wp_error($response)) {
        return array(
            'success'         => false,
            'message'         => 'Error fetching data',
            'response_code'   => 0,
        );
    }
    $response_code = (int) wp_remote_retrieve_response_code($response);
    $body          = wp_remote_retrieve_body($response);
    $decoded       = json_decode($body, true);

    return array(
        'success'         => true,
        'data'            => is_array($decoded) ? $decoded : null,
        'response_code'   => $response_code,
    );
}

/**
 * GET a Rechat API endpoint (e.g. boundaries/search).
 *
 * When {@see get_option()} `rch_rechat_access_token` is non-empty, sends
 * `Authorization: Bearer <token>` (same pattern as {@see rch_api_request()}).
 *
 * For `boundaries/search`, appends `omit[]=boundary.geometry` to the query string
 * (after $params) so country/state picker payloads stay small.
 *
 * @param string $endpoint_path Path after host, e.g. 'boundaries/search'.
 * @param array  $params        Query string parameters.
 * @return array{success:bool, data:?array, message?:string, response_code:int}
 */
function rch_rechat_public_api_get($endpoint_path, $params = array())
{
    $base = defined('RECHAT_API_BASE_URL') ? RECHAT_API_BASE_URL : 'https://api.rechat.com';
    $path = '/' . ltrim((string) $endpoint_path, '/');
    $path_lower = strtolower($path);
    $url = rtrim($base, '/') . $path;

    $query_segments = array();
    if (! empty($params) && is_array($params)) {
        $built = http_build_query($params, '', '&');
        if (is_string($built) && $built !== '') {
            $query_segments[] = $built;
        }
    }
    if (strpos($path_lower, 'boundaries/search') !== false) {
        $query_segments[] = 'omit[]=' . rawurlencode('boundary.geometry');
    }
    if (! empty($query_segments)) {
        $url .= '?' . implode('&', $query_segments);
    }

    $headers = array(
        'Accept' => 'application/json',
    );
    $access_token = (string) get_option('rch_rechat_access_token', '');
    if ($access_token !== '') {
        $headers['Authorization'] = 'Bearer ' . $access_token;
    }

    $response = wp_remote_get(
        $url,
        array(
            'timeout' => 20,
            'headers' => $headers,
        )
    );

    if (is_wp_error($response)) {
        return array(
            'success'       => false,
            'data'          => null,
            'message'       => $response->get_error_message(),
            'response_code' => 0,
        );
    }

    $response_code = (int) wp_remote_retrieve_response_code($response);
    $body          = wp_remote_retrieve_body($response);

    $decoded = json_decode($body, true);

    if ($response_code < 200 || $response_code >= 300) {
        return array(
            'success'       => false,
            'data'          => is_array($decoded) ? $decoded : null,
            'message'       => __('Rechat API request failed.', 'rechat-plugin'),
            'response_code' => $response_code,
        );
    }

    return array(
        'success'       => true,
        'data'          => is_array($decoded) ? $decoded : null,
        'response_code' => $response_code,
    );
}

/**
 * Normalize boundaries/search items to value + label for HTML selects.
 *
 * @param array $items Raw list from API `data` array.
 * @param string $boundary_type 'country' or 'state'.
 * @return array<int, array{value:string, label:string}>
 */
function rch_rechat_normalize_boundary_options($items, $boundary_type = 'country')
{
    if (! is_array($items)) {
        return array();
    }

    $out = array();
    foreach ($items as $row) {
        if (! is_array($row)) {
            continue;
        }
        $label = '';
        if (isset($row['title']) && $row['title'] !== '') {
            $label = (string) $row['title'];
        } elseif (isset($row['state']) && $row['state'] !== '') {
            $label = (string) $row['state'];
        }

        $value = '';
        if (isset($row['value']) && $row['value'] !== '') {
            $value = (string) $row['value'];
        } elseif ($boundary_type === 'country' && ! empty($row['country'])) {
            $value = strtoupper((string) $row['country']);
        } elseif ($boundary_type === 'state' && ! empty($row['title'])) {
            // SDK filter_boundary_state expects the human-readable name (e.g. "Arkansas").
            $value = (string) $row['title'];
        } elseif (! empty($row['state'])) {
            $value = (string) $row['state'];
        } elseif (! empty($row['id'])) {
            $value = (string) $row['id'];
        }

        if ($value === '' || $label === '') {
            continue;
        }

        $out[] = array(
            'value' => $value,
            'label' => $label,
        );
    }

    usort(
        $out,
        static function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        }
    );

    return $out;
}

/**
 * Fetch boundary rows from Rechat (countries or states).
 *
 * Calls `boundaries/search` with `omit[]=boundary.geometry` via {@see rch_rechat_public_api_get()}.
 * Results are cached in transients to keep General Settings responsive.
 *
 * @param string $boundary_type   'country' or 'state'.
 * @param string $country_iso     Required when $boundary_type is 'state' (e.g. US).
 * @param bool   $force_refresh   When true, bypass cache and refresh from API.
 * @return array<int, array{value:string, label:string}>
 */
function rch_rechat_fetch_boundaries_for_settings($boundary_type, $country_iso = '', $force_refresh = false)
{
    $boundary_type = sanitize_key($boundary_type);
    if ($boundary_type !== 'country' && $boundary_type !== 'state') {
        return array();
    }

    $params = array('boundary_type' => $boundary_type);
    if ($boundary_type === 'state') {
        $country_iso = strtoupper(sanitize_text_field($country_iso));
        if ($country_iso === '') {
            return array();
        }
        $params['country'] = $country_iso;
    }

    $cache_suffix = $boundary_type === 'country'
        ? 'country'
        : 'state_' . $country_iso;
    // v2: ignore empty cached arrays (v1 could return [] and skip the API forever).
    $cache_key = 'rch_boundary_opts_v2_' . $cache_suffix;

    if (! $force_refresh) {
        $cached = get_transient($cache_key);
        if (is_array($cached) && count($cached) > 0) {
            return $cached;
        }
        if ($cached !== false) {
            delete_transient($cache_key);
        }
    }

    $res = rch_rechat_public_api_get('boundaries/search', $params);
    if (empty($res['success']) || ! is_array($res['data'])) {
        return array();
    }

    $list = array();
    if (isset($res['data']['data']) && is_array($res['data']['data'])) {
        $list = $res['data']['data'];
    }

    $out = rch_rechat_normalize_boundary_options($list, $boundary_type);

    $ttl = (int) apply_filters('rch_boundary_transient_ttl', WEEK_IN_SECONDS, $boundary_type, $country_iso);
    if ($ttl > 0 && ! empty($out)) {
        set_transient($cache_key, $out, $ttl);
    }

    return $out;
}

/**
 * Fill empty filter_boundary_country / filter_boundary_state from General Settings options.
 *
 * @param array<string, mixed> $atts
 * @return array<string, mixed>
 */
function rch_apply_listing_boundary_site_defaults($atts)
{
    if (! is_array($atts)) {
        return $atts;
    }

    if (empty($atts['filter_boundary_country'])) {
        $country = (string) get_option('rch_selected_country', '');
        if ($country !== '') {
            $atts['filter_boundary_country'] = strtoupper($country);
        }
    }

    if (empty($atts['filter_boundary_state'])) {
        $state = (string) get_option('rch_selected_state', '');
        if ($state !== '' && ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $state)) {
            $atts['filter_boundary_state'] = $state;
        }
    }

    return $atts;
}

/**
 * Whether a listings API response likely indicates an invalid/expired access token
 * (used to trigger OAuth refresh before showing a generic “not found” message).
 *
 * @param array $response Return value from {@see rch_api_request()}.
 * @return bool
 */
function rch_single_listing_response_suggests_invalid_token($response)
{
    if (! is_array($response) || empty($response['success'])) {
        return false;
    }
    $rc = isset($response['response_code']) ? (int) $response['response_code'] : 0;
    if (in_array($rc, array(401, 403), true)) {
        return true;
    }
    $d = isset($response['data']) && is_array($response['data']) ? $response['data'] : null;
    if ($d === null) {
        return (bool) apply_filters('rch_single_listing_response_suggests_invalid_token', false, $response);
    }
    $inner = isset($d['http']) ? (int) $d['http'] : 0;
    if (in_array($inner, array(401, 403), true)) {
        return true;
    }
    // Some Rechat errors use HTTP 400 in the envelope with a non-Validation code/message for auth issues.
    if ($inner === 400 && (! isset($d['code']) || $d['code'] !== 'Validation')) {
        $parts = array();
        if (isset($d['code'])) {
            $parts[] = (string) $d['code'];
        }
        if (isset($d['message'])) {
            $parts[] = (string) $d['message'];
        }
        $blob = strtolower(implode(' ', $parts));
        foreach (array('token', 'unauthorized', 'authentication', 'forbidden', 'expired', 'invalid grant', 'oauth') as $needle) {
            if ($blob !== '' && strpos($blob, $needle) !== false) {
                return true;
            }
        }
    }

    return (bool) apply_filters('rch_single_listing_response_suggests_invalid_token', false, $response);
}
/*******************************
 * Function to insert or update posts and save meta data
 ******************************/
function rch_insert_or_update_post($post_type, $brand_name, $brand_id, $meta_key, $associated_regions = null, $address = null, $phone = null)
{
    $existing_posts = get_posts(array(
        'post_type'   => $post_type,
        'meta_key'    => $meta_key,
        'meta_value'  => $brand_id,
        'post_status' => 'publish',
        'numberposts' => 1,
    ));

    if (!empty($existing_posts)) {
        // Update existing post
        $post_id = $existing_posts[0]->ID;
        wp_update_post(array(
            'ID'         => $post_id,
            'post_title' => $brand_name,
        ));

        // Update the associated regions if provided
        if (!empty($associated_regions) && is_array($associated_regions)) {
            $region_post_ids = array(); // To store all region post IDs
            foreach ($associated_regions as $region_id) {
                // Find the region post ID for each region
                $region_post_id = rch_get_region_post_id_by_region_id($region_id);
                if ($region_post_id) {
                    $region_post_ids[] = $region_post_id; // Collect region post ID
                }
            }

            // Update meta field with all region IDs
            if (!empty($region_post_ids)) {
                update_post_meta($post_id, 'rch_associated_regions_to_office', $region_post_ids);
            }
        }

        // Update office address if provided and post type is offices
        if ($post_type === 'offices' && !empty($address)) {
            update_post_meta($post_id, 'office_address', sanitize_text_field($address));
        }

        // Update office phone if provided and post type is offices
        if ($post_type === 'offices' && !empty($phone)) {
            update_post_meta($post_id, 'office_phone', sanitize_text_field($phone));
        }

        if ($post_type === 'offices') {
            /**
             * Fires after an office post is inserted or updated via Rechat sync.
             *
             * @param int    $post_id     Office post ID.
             * @param string $brand_name Office display name.
             */
            do_action('rch_after_office_synced', (int) $post_id, (string) $brand_name);
        }

        return 'updated';
    } else {
        // Insert new post
        $post_data = array(
            'post_title'  => $brand_name,
            'post_type'   => $post_type,
            'post_status' => 'publish',
        );
        $post_id = wp_insert_post($post_data);
        update_post_meta($post_id, $meta_key, $brand_id);

        // Insert associated regions if provided
        if (!empty($associated_regions) && is_array($associated_regions)) {
            $region_post_ids = array(); // To store all region post IDs
            foreach ($associated_regions as $region_id) {
                // Find the region post ID for each region
                $region_post_id = rch_get_region_post_id_by_region_id($region_id);
                if ($region_post_id) {
                    $region_post_ids[] = $region_post_id; // Collect region post ID
                }
            }

            // Update meta field with all region IDs
            if (!empty($region_post_ids)) {
                update_post_meta($post_id, 'rch_associated_regions_to_office', $region_post_ids);
            }
        }

        // Insert office address if provided and post type is offices
        if ($post_type === 'offices' && !empty($address)) {
            update_post_meta($post_id, 'office_address', sanitize_text_field($address));
        }

        // Insert office phone if provided and post type is offices
        if ($post_type === 'offices' && !empty($phone)) {
            update_post_meta($post_id, 'office_phone', sanitize_text_field($phone));
        }

        if ($post_type === 'offices') {
            /**
             * Fires after an office post is inserted or updated via Rechat sync.
             *
             * @param int    $post_id     Office post ID.
             * @param string $brand_name Office display name.
             */
            do_action('rch_after_office_synced', (int) $post_id, (string) $brand_name);
        }

        return 'added';
    }
}

/*******************************
 * Function to get the region post ID by its region ID
 ******************************/
function rch_get_region_post_id_by_region_id($region_id)
{
    $regions = get_posts(array(
        'post_type'   => 'regions', // Replace with your actual custom post type
        'meta_key'    => 'region_id', // Replace with the actual meta key for region ID
        'meta_value'  => $region_id,
        'post_status' => 'publish',
        'numberposts' => 1,
    ));

    return !empty($regions) ? $regions[0]->ID : null;
}

/*******************************
 * Function to delete outdated posts
 ******************************/
function rch_delete_outdated_posts($post_type, $current_brand_ids, $meta_key)
{
    $existing_posts = get_posts(array(
        'post_type'   => $post_type,
        'post_status' => 'publish',
        'numberposts' => -1,
    ));
    foreach ($existing_posts as $post) {
        $stored_brand_id = get_post_meta($post->ID, $meta_key, true);
        
        // Skip manually added posts (posts without Rechat ID)
        if (empty($stored_brand_id)) {
            continue;
        }
        
        // Only delete posts that came from API but are no longer in the current response
        if (!in_array($stored_brand_id, $current_brand_ids)) {
            wp_delete_post($post->ID, true);
        }
    }
}
/*******************************
 * Fetch and process data with pagination for regions and offices
 ******************************/
function rch_fetch_and_process_brands($api_url_base, $access_token)
{
    $regions = [];
    $offices = [];
    $processed_brands = [];
    $all_brands = []; // Store all brands for reference
    $limit = 100; // Adjust the limit as needed
    $offset = 0;

    do {
        $api_url = $api_url_base . "&limit=$limit&start=$offset";
        $response = rch_api_request($api_url, $access_token);
        if (!$response['success']) {
            return $response; // Return error if API request fails
        }
        $data = $response['data'];

        if (isset($data['data'])) {
            // First pass: collect all brands
            foreach ($data['data'] as $user_data) {
                if (isset($user_data['brands'])) {
                    foreach ($user_data['brands'] as $brand) {
                        if (!isset($all_brands[$brand['id']])) {
                            $all_brands[$brand['id']] = $brand;
                        }

                        // Add all parent brands to our collection for processing
                        $current_parent = $brand['parent'] ?? null;
                        while ($current_parent) {
                            if (!isset($all_brands[$current_parent['id']])) {
                                $all_brands[$current_parent['id']] = $current_parent;
                            }
                            $current_parent = $current_parent['parent'] ?? null;
                        }
                    }
                }
            }

            // First identify all regions
            foreach ($all_brands as $brand) {
                if ($brand['brand_type'] == 'Region') {
                    $regions[] = $brand;
                    $processed_brands[] = $brand['id'];
                }
            }

            // Then process all offices
            foreach ($all_brands as $brand) {
                if ($brand['brand_type'] == 'Office' && !in_array($brand['id'], $processed_brands)) {
                    rch_collect_brands($brand, $regions, $offices, $processed_brands);
                }
            }

            // Process any remaining brands
            foreach ($data['data'] as $user_data) {
                if (isset($user_data['brands'])) {
                    foreach ($user_data['brands'] as $brand) {
                        if (!in_array($brand['id'], $processed_brands)) {
                            rch_collect_brands($brand, $regions, $offices, $processed_brands);
                        }
                    }
                }
            }
        }

        $offset += $limit;
    } while (count($data['data']) === $limit);

    return array('success' => true, 'regions' => $regions, 'offices' => $offices);
}
/*******************************
 * Fetch and process agents data with pagination
 ******************************/
function rch_process_agents_data($access_token, $api_url_base)
{
    $GLOBALS['rch_doing_agent_sync'] = true;
    try {
    $limit = 100;
    $offset = 0;
    $default_profile_image_url = RCH_PLUGIN_URL . 'assets/images/image-placeholder.svg';

    // Get existing posts and their API IDs
    $existing_posts = get_posts(array(
        'post_type'   => 'agents',
        'numberposts' => -1,
        'meta_key'    => 'api_id',
        'fields'      => 'ids',
    ));

    $existing_api_ids = array();
    foreach ($existing_posts as $post_id) {
        $api_id = get_post_meta($post_id, 'api_id', true);
        if ($api_id) {
            $existing_api_ids[$api_id] = $post_id;
        }
    }

    $max_menu_order = (int) get_posts(array(
        'post_type'      => 'agents',
        'numberposts'    => 1,
        'orderby'        => 'menu_order',
        'order'          => 'DESC',
        'fields'         => 'menu_order',
        'post_status'    => 'publish',
    ));

    $current_menu_order = $max_menu_order + 1;
    $agent_add_count = 0;
    $agent_update_count = 0;
    $new_api_ids = []; // Array to store new API IDs

    do {
        $api_url = $api_url_base . "&limit=$limit&start=$offset";
        $response = rch_api_request($api_url, $access_token);

        if (!$response['success']) {
            return $response; // Return error if API request fails
        }

        $data = $response['data'];
        if (isset($data['http']) && $data['http'] == 401) {
            return array('success' => false, 'message' => 'Expired Token. Please reconnect to Rechat.');
        }

        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $item) {
                $api_id = $item['id']; // Store API ID for later use
                $new_api_ids[] = $api_id; // Add to new API IDs

                $regions_for_agent = [];
                $offices_for_agent = [];
                $brands = isset($item['brands']) ? rch_filter_brands_by_type($item['brands'], ["Region", "Office"]) : [];
                foreach ($brands as $brand) {
                    if ($brand['brand_type'] === 'Region') {
                        // Find region post by custom meta field region_id
                        $region_query = new WP_Query(array(
                            'post_type' => 'regions',
                            'meta_query' => array(
                                array(
                                    'key' => 'region_id',
                                    'value' => $brand['id'], // Assuming 'id' is the custom field value in $brands
                                    'compare' => '='
                                )
                            ),
                            'post_status' => 'publish',
                            'posts_per_page' => 1,
                        ));

                        if ($region_query->have_posts()) {
                            $region_query->the_post();
                            $regions_for_agent[] = get_the_ID(); // Store the ID of the region
                            wp_reset_postdata();
                        }
                    } elseif ($brand['brand_type'] === 'Office') {
                        // Find office post by custom meta field office_id
                        $office_query = new WP_Query(array(
                            'post_type' => 'offices',
                            'meta_query' => array(
                                array(
                                    'key' => 'office_id',
                                    'value' => $brand['id'], // Assuming 'id' is the custom field value in $brands
                                    'compare' => '='
                                )
                            ),
                            'post_status' => 'publish',
                            'posts_per_page' => 1,
                        ));

                        if ($office_query->have_posts()) {
                            $office_query->the_post();
                            $offices_for_agent[] = get_the_ID(); // Store the ID of the office
                            wp_reset_postdata();
                        }
                    }
                }


                $user = $item['user'];
                $full_name = rch_agent_display_name_from_rechat_api_user($user);
                $custom_fields = rch_agent_meta_from_rechat_api_user(
                    $user,
                    $api_id,
                    $default_profile_image_url,
                    $regions_for_agent,
                    $offices_for_agent
                );

                if (isset($existing_api_ids[$api_id])) {
                    // Update existing post
                    $post_id = $existing_api_ids[$api_id];
                    $post_data = array(
                        'ID' => $post_id,
                        'post_title' => $full_name,
                        'menu_order' => $current_menu_order,
                    );
                    $updated_post_id = wp_update_post($post_data);
                    if (!is_wp_error($updated_post_id)) {
                        foreach ($custom_fields as $key => $value) {
                            update_post_meta($updated_post_id, $key, $value);
                        }
                        $agent_update_count++;
                        /**
                         * Fired after an agent post is updated during an API sync.
                         *
                         * @param int    $updated_post_id  Agent post ID.
                         * @param string $full_name        Agent display name.
                         */
                        do_action('rch_after_agent_synced', $updated_post_id, $full_name);
                    }
                } else {
                    // Insert new post
                    $post_data = array(
                        'post_title' => $full_name,
                        'post_type' => 'agents',
                        'post_status' => 'publish',
                        'menu_order' => $current_menu_order,
                    );
                    $post_id = wp_insert_post($post_data);
                    if (!is_wp_error($post_id)) {
                        foreach ($custom_fields as $key => $value) {
                            update_post_meta($post_id, $key, $value);
                        }
                        $agent_add_count++;
                        /**
                         * Fired after a new agent post is inserted during an API sync.
                         *
                         * @param int    $post_id    Agent post ID.
                         * @param string $full_name  Agent display name.
                         */
                        do_action('rch_after_agent_synced', $post_id, $full_name);
                    }
                }
                $current_menu_order++;
            }
            $offset += $limit;
        } else {
            break;
        }
    } while (count($data['data']) === $limit);

    // Delete outdated agents after processing
    rch_delete_outdated_posts('agents', $new_api_ids, 'api_id');

    return array(
        'agent_add_count' => $agent_add_count,
        'agent_update_count' => $agent_update_count,
    );
    } finally {
        $GLOBALS['rch_doing_agent_sync'] = false;
    }
}

/**
 * Whether agent_display_order meta counts as “unset” (no sort number).
 *
 * @param  mixed $raw Raw meta value.
 * @return bool
 */
function rch_agent_display_order_meta_is_empty($raw)
{
    if ($raw === null || $raw === false) {
        return true;
    }
    $s = trim((string) $raw);
    if ($s === '') {
        return true;
    }
    return (string) (int) $raw === (string) (int) RCH_AGENT_DISPLAY_ORDER_EMPTY_SORT;
}

/**
 * WP_Query args for agents: rows with numeric agent_display_order first (0,1,2…),
 * then posts with no meta / empty meta. Uses a scoped posts_clauses filter.
 *
 * @param  array  $args       WP_Query arguments (post_type should be agents).
 * @param  string $numeric_dir ASC or DESC for the numeric key among ordered posts.
 * @return \WP_Query
 */
function rch_wp_query_agents_display_order(array $args, $numeric_dir = 'ASC')
{
    $numeric_dir = strtoupper((string) $numeric_dir) === 'DESC' ? 'DESC' : 'ASC';
    $GLOBALS['rch_agents_display_order_sort_active'] = true;
    $GLOBALS['rch_agents_display_order_sort_dir'] = $numeric_dir;
    add_filter('posts_clauses', 'rch_agents_posts_clauses_display_order_sort', 10, 2);
    $query = new WP_Query($args);
    remove_filter('posts_clauses', 'rch_agents_posts_clauses_display_order_sort', 10, 2);
    unset($GLOBALS['rch_agents_display_order_sort_active'], $GLOBALS['rch_agents_display_order_sort_dir']);
    return $query;
}

/**
 * @param  array<string, string> $clauses SQL fragments (fields, join, where, groupby, orderby).
 * @param  \WP_Query              $query   Query object.
 * @return array<string, string>
 */
function rch_agents_posts_clauses_display_order_sort($clauses, $query)
{
    if (empty($GLOBALS['rch_agents_display_order_sort_active'])) {
        return $clauses;
    }
    $post_type = $query->get('post_type');
    if ($post_type !== 'agents' && (! is_array($post_type) || ! in_array('agents', $post_type, true))) {
        return $clauses;
    }

    global $wpdb;
    $alias = 'rch_ord_sort';
    $key = esc_sql(RCH_AGENT_DISPLAY_ORDER_META_KEY);
    $sentinel = esc_sql((string) (int) RCH_AGENT_DISPLAY_ORDER_EMPTY_SORT);

    if (strpos($clauses['join'], $alias) === false) {
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ({$wpdb->posts}.ID = {$alias}.post_id AND {$alias}.meta_key = '{$key}') ";
    }

    $dir = ! empty($GLOBALS['rch_agents_display_order_sort_dir']) && strtoupper((string) $GLOBALS['rch_agents_display_order_sort_dir']) === 'DESC'
        ? 'DESC'
        : 'ASC';

    // 0 = has a real order number first; 1 = missing/empty/legacy sentinel last.
    $bucket = "CASE WHEN ({$alias}.meta_id IS NULL OR TRIM(IFNULL({$alias}.meta_value,'')) = '' OR {$alias}.meta_value = '{$sentinel}') THEN 1 ELSE 0 END";

    $clauses['orderby'] = "{$bucket} ASC, CAST({$alias}.meta_value AS UNSIGNED) {$dir}, {$wpdb->posts}.post_title ASC";

    return $clauses;
}

/*******************************
 * this function get all filters that use in listing shortcode
 ******************************/
function rch_get_filters($atts)
{
    $filters = [
        'property_types' => isset($atts['property_types']) && $atts['property_types'] !== ''
            ? (is_string($atts['property_types']) && json_decode($atts['property_types'], true) !== null
                ? array_map('htmlspecialchars_decode', json_decode($atts['property_types'], true)) // Decode JSON array and fix HTML entities
                : array_map('htmlspecialchars_decode', array_map('trim', explode(',', $atts['property_types']))) // Convert comma-separated string to array and fix HTML entities
            )
            : null,
        'minimum_price' => isset($atts['minimum_price']) && $atts['minimum_price'] !== '' ? intval($atts['minimum_price']) : null,
        'maximum_price' => isset($atts['maximum_price']) && $atts['maximum_price'] !== '' ? intval($atts['maximum_price']) : null,
        'minimum_lot_square_meters' => isset($atts['minimum_lot_square_meters']) && $atts['minimum_lot_square_meters'] !== '' ? intval($atts['minimum_lot_square_meters']) : null,
        'maximum_lot_square_meters' => isset($atts['maximum_lot_square_meters']) && $atts['maximum_lot_square_meters'] !== '' ? intval($atts['maximum_lot_square_meters']) : null,
        'minimum_bathrooms' => isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] !== '' ? intval($atts['minimum_bathrooms']) : null,
        'maximum_bathrooms' => isset($atts['maximum_bathrooms']) && $atts['maximum_bathrooms'] !== '' ? intval($atts['maximum_bathrooms']) : null,
        'minimum_square_meters' => isset($atts['minimum_square_meters']) && $atts['minimum_square_meters'] !== '' ? floatval($atts['minimum_square_meters']) : null,
        'maximum_square_meters' => isset($atts['maximum_square_meters']) && $atts['maximum_square_meters'] !== '' ? floatval($atts['maximum_square_meters']) : null,
        'minimum_year_built' => isset($atts['minimum_year_built']) && $atts['minimum_year_built'] !== '' ? intval($atts['minimum_year_built']) : null,
        'maximum_year_built' => isset($atts['maximum_year_built']) && $atts['maximum_year_built'] !== '' ? intval($atts['maximum_year_built']) : null,
        'minimum_bedrooms' => isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] !== '' ? intval($atts['minimum_bedrooms']) : null,
        'maximum_bedrooms' => isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] !== '' ? intval($atts['maximum_bedrooms']) : null,
        'minimum_parking_spaces' => isset($atts['minimum_parking_spaces']) && $atts['minimum_parking_spaces'] !== '' ? intval($atts['minimum_parking_spaces']) : null,
        'content' => isset($atts['content']) && $atts['content'] !== '' ? sanitize_text_field($atts['content']) : null,
        'show_filter_bar' => isset($atts['show_filter_bar']) && $atts['show_filter_bar'] !== '' ? boolval($atts['show_filter_bar']) : null,
        'postal_codes' => isset($atts['postal_codes']) && $atts['postal_codes'] !== ''
            ? array_map('trim', explode(',', $atts['postal_codes'])) // Split and trim each value
            : null,
        'brand' => isset($atts['brand']) ? $atts['brand'] : null,
        'listing_statuses' => isset($atts['listing_statuses']) && $atts['listing_statuses'] !== ''
            ? array_map('trim', explode(',', $atts['listing_statuses'])) // Split and trim each value
            : null,
        'points' => isset($atts['map_points']) && $atts['map_points'] !== ''
            ? array_map(function ($point) {
                $coords = explode(',', $point); // Split lat and lng
                return [
                    'longitude' => floatval($coords[1]), // Parse longitude
                    'latitude' => floatval($coords[0]), // Parse latitude
                ];
            }, explode('|', $atts['map_points'])) // Split points string into array of "lat,lng"
            : (isset($atts['points']) && $atts['points'] !== ''
                ? array_map(function ($point) {
                    $coords = explode(',', $point); // Split lat and lng
                    return [
                        'longitude' => floatval($coords[1]), // Parse longitude
                        'latitude' => floatval($coords[0]), // Parse latitude
                    ];
                }, explode('|', $atts['points'])) // Split points string into array of "lat,lng"
                : null),
        'agents' => isset($atts['agents']) && $atts['agents'] !== ''
            ? (is_string($atts['agents'])
                ? ((strpos($atts['agents'], '[') === 0)
                    ? json_decode($atts['agents'], true) // It's a JSON string
                    : array_map('trim', explode(',', $atts['agents'])) // It's a comma-separated string
                )
                : $atts['agents'] // It's already an array
            )
            : null,
    ];

    // Apply array_filter to remove null values
    $filtered = array_filter($filters, function ($value) {
        return $value !== null; // Keep non-null values
    });

    return $filtered;
}
/*******************************
 *title for page listing detail
 ******************************/
function rch_custom_single_listing_title($title)
{
    if (isset($_GET['listing_id'])) {
        $house_id = sanitize_text_field($_GET['listing_id']);
        // You can modify the title as per your needs
        $title = 'Listing Details - ' . $house_id;
    }
    return $title;
}
add_filter('wp_title', 'rch_custom_single_listing_title');
function rch_remove_404_for_house_listing()
{
    if (isset($_GET['listing_id'])) {
        global $wp_query;
        $wp_query->is_404 = false; // Mark this request as a valid one, not 404
    }
}
add_action('wp', 'rch_remove_404_for_house_listing');

/*******************************
 *Helper function to fetch the primary color from a brand or its parent.
 ******************************/
function rch_get_primary_color_and_logo()
{
    $brand_id = get_option('rch_rechat_brand_id');
    // API endpoint to fetch brand settings, including parent brands
    $api_base    = defined('RECHAT_API_BASE_URL') ? RECHAT_API_BASE_URL : 'https://api.rechat.com';
    $palette_url = rtrim($api_base, '/') . '/brands/' . rawurlencode((string) $brand_id) . '?associations[]=brand.parent&associations[]=brand.settings';

    // Make the API request
    $palette_response = wp_remote_get($palette_url);
    if (is_wp_error($palette_response)) {
        error_log('Error fetching marketing palette: ' . $palette_response->get_error_message());
        return array(
            'primary_color' => '#2271b1', // Default color on error
            'logo_url' => null // No logo URL on error
        );
    }

    // Parse the response body
    $palette_body = wp_remote_retrieve_body($palette_response);
    $palette_data = json_decode($palette_body, true);

    // Initialize default values
    $primary_color = '#2271b1';

    // Traverse the brand and its parents to find the color and logo URL
    if (isset($palette_data['data'])) {
        $brand = $palette_data['data'];

        $primary_color = rch_find_get_setting($brand, 'button-bg-color', '#2271b1');
        $container_logo_wide = rch_find_get_setting($brand, 'container-logo-wide', 'null');
        $container_logo_square = rch_find_get_setting($brand, 'container-logo-square', 'null');
        $container_team_logo_wide = rch_find_get_setting($brand, 'container-team-logo-wide', 'null');
        $container_team_logo_square = rch_find_get_setting($brand, 'container-team-logo-square', 'null');
        $inverted_container_logo_wide = rch_find_get_setting($brand, 'inverted-logo-wide', 'null');
        $inverted_container_logo_square = rch_find_get_setting($brand, 'inverted-logo-square', 'null');
        $inverted_team_logo_wide = rch_find_get_setting($brand, 'inverted-team-logo-wide', 'null');
        $inverted_team_logo_square = rch_find_get_setting($brand, 'inverted-team-logo-square', 'null');
    }
    update_option('_rch_primary_color', $primary_color);
    update_option('rch_container_logo_wide', $container_logo_wide);
    update_option('rch_container_logo_square', $container_logo_square);
    update_option('rch_container_team_logo_wide', $container_team_logo_wide);
    update_option('rch_container_team_logo_square', $container_team_logo_square);
    update_option('rch_inverted_container_logo_wide', $inverted_container_logo_wide);
    update_option('rch_inverted_container_logo_square', $inverted_container_logo_square);
    update_option('rch_inverted_team_logo_wide', $inverted_team_logo_wide);
    update_option('rch_inverted_team_logo_square', $inverted_team_logo_square);
}
/*******************************
 * Helper function to find the button color by traversing the brand hierarchy.
 ******************************/
function rch_find_get_setting($brand, $key, $default = '#2271b1')
{
    $value = null;

    // Traverse the brand hierarchy to find the setting
    do {
        if (isset($brand['settings']['marketing_palette'][$key])) {
            $value = $brand['settings']['marketing_palette'][$key];
            break;
        }
        $brand = isset($brand['parent']) ? $brand['parent'] : null;
    } while ($brand);

    // Sanitize the color or return a default color
    return $value ? $value : $default;
}
/*******************************
 *Helper function to create api for sending data to react
 ******************************/
function register_custom_options_route()
{
    register_rest_route('wp/v2', '/options', array(
        'methods' => 'GET',
        'callback' => 'get_custom_options',
        'permission_callback' => 'is_user_logged_in_permission', // Check if user is logged in
    ));
}

function is_user_logged_in_permission()
{
    // Allow access only if the user is logged in
    return is_user_logged_in();
}

function get_custom_options()
{
    // Retrieve the option stored in WordPress
    $brand_id = get_option('rch_rechat_brand_id');
    $access_token = get_option('rch_rechat_access_token');
    $google_map_api_key = get_option('rch_rechat_google_map_api_key');

    $selected_country = (string) get_option('rch_selected_country', '');
    $selected_state   = (string) get_option('rch_selected_state', '');

    // Return the option as a JSON response
    return new WP_REST_Response(array(
        'rch_rechat_brand_id' => $brand_id,
        'rch_rechat_access_token' => $access_token,
        'rch_rechat_google_map_api_key' => $google_map_api_key,
        'rch_selected_country' => $selected_country !== '' ? strtoupper($selected_country) : '',
        'rch_selected_state' => $selected_state,
    ), 200);
}

add_action('rest_api_init', 'register_custom_options_route');
function register_check_user_logged_in_route()
{
    register_rest_route('wp/v2', '/check_logged_in', array(
        'methods' => 'GET',
        'callback' => 'check_logged_in_status',
        'permission_callback' => function () {
            return is_user_logged_in(); // Only proceed if the user is logged in
        },
    ));
}

function check_logged_in_status()
{
    // If the user is logged in, return success
    return new WP_REST_Response(
        array('status' => 'success', 'message' => 'User is logged in'),
        200
    );
}

add_action('rest_api_init', 'register_check_user_logged_in_route');
/*******************************
 *Helper function to Images
 ******************************/
function rch_render_image($src, $alt, $classes = '')
{
    if (empty($src)) {
        return '';
    }

    $alt = esc_attr($alt);
    $src = esc_url($src);
    $classes = esc_attr($classes);

    return "<img src=\"$src\" alt=\"$alt\" class=\"$classes\">";
}
// Helper function to check and return template
function get_custom_template($theme_path, $plugin_path)
{
    $theme_template = locate_template($theme_path);
    if ($theme_template) {
        return $theme_template;
    }
    if (file_exists($plugin_path)) {
        return $plugin_path;
    }
    return false;
}
/*******************************
 *Helper function For Related Neghborhoods
 ******************************/
function get_related_neighborhoods()
{
    ob_start();

    // Get current post ID
    $current_id = get_the_ID();

    // Define custom WP Query for related neighborhoods
    $related_neighbourhoods = new WP_Query(array(
        'post_type'      => 'neighborhoods', // Custom post type
        'posts_per_page' => 3, // Number of related posts to show
        'post__not_in'   => array($current_id), // Exclude current post
        'orderby'        => 'rand', // Random order (optional)
    ));

    // Check if custom template exists in the theme
    $custom_template = locate_template('rechat/neighborhoods-related.php');

    // Loop through related posts
    if ($related_neighbourhoods->have_posts()) :
        while ($related_neighbourhoods->have_posts()) : $related_neighbourhoods->the_post();
            if ($custom_template) {
                // Include custom template if it exists
                include($custom_template);
            } else {
                // Default HTML structure
?>
                <li class="blogs--content item">
                    <a href="<?php the_permalink(); ?>" class="related-item item-wrapper">
                        <div class="image-holder">
                            <?php if (has_post_thumbnail()) : ?>
                                <img src="<?php the_post_thumbnail_url(); ?>" alt="<?php the_title(); ?>">
                            <?php endif; ?>
                        </div>
                        <div class="overlay"></div>
                        <div class="content-container">
                            <h3 class="lp-h3 neighborhood-name"><?php the_title(); ?></h3>
                            <div class="button-wrapper">
                                <span class="btn">Learn More</span>
                            </div>
                        </div>
                    </a>
                </li>
    <?php
            }
        endwhile;
        wp_reset_postdata(); // Reset query
    else :
        echo '<li>No related neighborhoods found.</li>';
    endif;

    return ob_get_clean();
}
/*******************************
 *Helper function to Register REST API route to render listing templates
 ******************************/
function rch_register_listing_template_endpoint()
{
    register_rest_route('rechat/v1', '/render-listing-template/', array(
        'methods' => 'POST',
        'callback' => 'rch_render_listing_template',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'rch_register_listing_template_endpoint');

// Callback function to render the template
function rch_render_listing_template($request)
{
    $listings = $request->get_param('listings');

    if (empty($listings) || !is_array($listings)) {
        return new WP_Error('no_listings', 'No listing data provided', array('status' => 400));
    }

    // Define paths
    $theme_template_path = 'rechat/listing-item.php';
    $plugin_template_path = RCH_PLUGIN_DIR . '/templates/archive/template-part/listing-item.php';

    // Check if theme template exists
    $template_path = locate_template($theme_template_path);
    if (!$template_path) {
        // If not found in theme, use the plugin's template
        $template_path = $plugin_template_path;
    }

    ob_start();
    foreach ($listings as $listing) {
        include $template_path;
    }
    $html = ob_get_clean();

    return array(
        'html' => $html
    );
}

/*******************************
 * Check if agent exists by checking if agent ID exists in 'agents' meta array
 * Returns an array of all matching WP_Post objects, or empty array if none found
 ******************************/
function rch_check_agent_exists($agent_api_id)
{
    // Return empty array if empty
    if (empty($agent_api_id)) {
        return array();
    }

    // Sanitize the agent API ID
    $agent_api_id = sanitize_text_field($agent_api_id);

    // Query all 'agents' custom post type posts
    $args = [
        'post_type'      => 'agents',
        'post_status'    => 'publish',
        'posts_per_page' => -1, // Get all agents posts
    ];

    $agent_posts = get_posts($args);
    $matching_agents = array();

    // Loop through each agent post and check if the agent_api_id exists in the 'agents' meta array
    foreach ($agent_posts as $agent_post) {
        $agents_meta = get_post_meta($agent_post->ID, 'agents', true);

        // Check if agents_meta is an array and contains the agent_api_id
        if (is_array($agents_meta) && in_array($agent_api_id, $agents_meta, true)) {
            $matching_agents[] = $agent_post;
        }
    }

    return $matching_agents;
}

/*******************************
 * Convert hexadecimal color to RGB array
 ******************************/
function rch_hex_to_rgb($hex)
{
    $hex = str_replace('#', '', $hex);

    if (strlen($hex) === 3) {
        $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
        $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
        $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }

    return [$r, $g, $b];
}

/*******************************
 * Determine if a color is dark based on brightness calculation
 ******************************/
function rch_is_color_dark($hex)
{
    list($r, $g, $b) = rch_hex_to_rgb($hex);

    // Calculate perceived brightness using standard luminance formula
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

    return $brightness < 150;
}

/*******************************
 * Get text color (black or white) that contrasts with background
 ******************************/
function rch_get_contrast_text_color($background_color)
{
    return rch_is_color_dark($background_color) ? '#ffffff' : '#000000';
}

/*******************************
 * Get block attributes configuration for listing block
 ******************************/
function rch_get_listing_block_attributes()
{
    return array(
        'minimum_price' => array('type' => 'string', 'default' => ''),
        'maximum_price' => array('type' => 'string', 'default' => ''),
        'minimum_square_feet' => array('type' => 'string', 'default' => ''),
        'maximum_square_feet' => array('type' => 'string', 'default' => ''),
        'minimum_bathrooms' => array('type' => 'string', 'default' => ''),
        'maximum_bathrooms' => array('type' => 'string', 'default' => ''),
        'minimum_lot_square_feet' => array('type' => 'string', 'default' => ''),
        'maximum_lot_square_feet' => array('type' => 'string', 'default' => ''),
        'minimum_year_built' => array('type' => 'string', 'default' => ''),
        'maximum_year_built' => array('type' => 'string', 'default' => ''),
        'minimum_bedrooms' => array('type' => 'string', 'default' => ''),
        'maximum_bedrooms' => array('type' => 'string', 'default' => ''),
        'listing_per_page' => array('type' => 'string', 'default' => ''),
        'filterByRegions' => array('type' => 'string', 'default' => ''),
        'filterByOffices' => array('type' => 'string', 'default' => ''),
        'brand' => array('type' => 'string', 'default' => get_option('rch_rechat_brand_id')),
        'selectedStatuses' => array('type' => 'array', 'default' => []),
        'listing_statuses' => array('type' => 'array', 'default' => []),
        'disable_filter_address' => array('type' => 'boolean', 'default' => false),
        'disable_filter_price' => array('type' => 'boolean', 'default' => false),
        'disable_filter_beds' => array('type' => 'boolean', 'default' => false),
        'disable_filter_baths' => array('type' => 'boolean', 'default' => false),
        'disable_filter_property_types' => array('type' => 'boolean', 'default' => false),
        'disable_filter_advanced' => array('type' => 'boolean', 'default' => false),
        'own_listing' => array('type' => 'boolean', 'default' => true),
        'property_types' => array('type' => 'string', 'default' => ''),
        'filter_open_houses' => array('type' => 'boolean', 'default' => false),
        'office_exclusive' => array('type' => 'boolean', 'default' => false),
        'disable_sort' => array('type' => 'boolean', 'default' => false),
        'map_latitude' => array('type' => 'string', 'default' => ''),
        'map_longitude' => array('type' => 'string', 'default' => ''),
        'map_zoom' => array('type' => 'string', 'default' => '12'),
        'map_style' => array('type' => 'string', 'default' => ''),
        'map_style_url' => array('type' => 'string', 'default' => ''),
        'sort_by' => array('type' => 'string', 'default' => '-list_date'),
        'map_id' => array('type' => 'string', 'default' => ''),
        'filter_address' => array('type' => 'string', 'default' => ''),
        'filter_search_limit' => array('type' => 'string', 'default' => ''),
        'filter_suggestions_limit' => array('type' => 'string', 'default' => ''),
        'filter_pagination_offset' => array('type' => 'string', 'default' => ''),
        'property_subtypes' => array('type' => 'string', 'default' => ''),
        'architectural_styles' => array('type' => 'string', 'default' => ''),
        'filter_baths' => array('type' => 'string', 'default' => ''),
        'minimum_parking_spaces' => array('type' => 'string', 'default' => ''),
        'minimum_sold_date' => array('type' => 'string', 'default' => ''),
        'filter_pool' => array('type' => 'boolean', 'default' => false),
        'filter_agents' => array('type' => 'string', 'default' => ''),
        'list_offices' => array('type' => 'string', 'default' => ''),
        'filter_brand_id' => array('type' => 'string', 'default' => ''),
        'disable_filter_loading_indicator' => array('type' => 'boolean', 'default' => false),
        'filter_boundary_country' => array('type' => 'string', 'default' => ''),
        'filter_boundary_state' => array('type' => 'string', 'default' => ''),
    );
}

/*******************************
 * Sanitize listing statuses array to comma-separated string
 ******************************/
function rch_sanitize_listing_statuses($listing_statuses)
{
    if (is_array($listing_statuses)) {
        $sanitized = array_filter(
            array_map('sanitize_text_field', $listing_statuses),
            function ($status) {
                return $status !== '';
            }
        );
        return implode(',', $sanitized);
    }

    return sanitize_text_field((string) $listing_statuses);
}

/*******************************
 * Get map default center coordinates
 ******************************/
function rch_get_map_default_center($latitude, $longitude)
{
    // Only return center if both latitude and longitude are provided
    if (!empty($latitude) && !empty($longitude)) {
        $lat = sanitize_text_field($latitude);
        $lng = sanitize_text_field($longitude);

        if (is_numeric($lat) && is_numeric($lng)) {
            return $lat . ', ' . $lng;
        }
    }

    // Return empty string if no valid coordinates provided
    return '';
}

/*******************************
 * Render rechat root attributes (NEW SDK - only brand_id)
 ******************************/
function rch_get_rechat_root_attributes($attributes, $map_default_center, $listing_statuses_str)
{
    $attrs = array();

    // In the new SDK, rechat-root ALWAYS gets brand_id from settings
    if (!empty($attributes['brand'])) {
        $attrs[] = 'brand_id="' . esc_attr($attributes['brand']) . '"';
    }

    return implode("\n      ", $attrs);
}

/*******************************
 * Built-in MapLibre presets for <rechat-map preset="…"> (SDK migration from map_style).
 ******************************/
function rch_get_rechat_map_preset_allowlist()
{
    return array('liberty', 'bright', 'positron', 'dark');
}

/*******************************
 * Render <rechat-map> attributes (preset / style_url / zoom / default_center).
 ******************************/
function rch_get_rechat_map_attributes($attributes, $map_default_center = '')
{
    $attrs = array();

    $style_url = '';
    if (! empty($attributes['map_style_url'])) {
        $style_url = esc_url((string) $attributes['map_style_url']);
    }

    if ($style_url !== '') {
        $attrs[] = 'style_url="' . esc_attr($style_url) . '"';
    } else {
        $raw_preset = '';
        if (! empty($attributes['map_style'])) {
            $raw_preset = sanitize_key((string) $attributes['map_style']);
        }

        if ($raw_preset !== '' && in_array($raw_preset, rch_get_rechat_map_preset_allowlist(), true)) {
            $attrs[] = 'preset="' . esc_attr($raw_preset) . '"';
        }
    }

    if (! empty($attributes['map_zoom'])) {
        $attrs[] = 'zoom="' . esc_attr($attributes['map_zoom']) . '"';
    }

    if (! empty($map_default_center)) {
        $attrs[] = 'default_center="' . esc_attr($map_default_center) . '"';
    }

    return implode(' ', $attrs);
}

/*******************************
 * Address-search seed attributes for <rechat-map-filter> / <rechat-filter-search>.
 ******************************/
function rch_get_rechat_map_filter_attributes($attributes)
{
    $attrs = array();

    if (isset($attributes['filter_address']) && (string) $attributes['filter_address'] !== '') {
        $attrs[] = 'address="' . esc_attr($attributes['filter_address']) . '"';
    }

    if (! empty($attributes['filter_boundary_country'])) {
        $attrs[] = 'boundary_country="' . esc_attr($attributes['filter_boundary_country']) . '"';
    }

    if (! empty($attributes['filter_boundary_state'])) {
        $attrs[] = 'boundary_state="' . esc_attr($attributes['filter_boundary_state']) . '"';
    }

    if (! empty($attributes['filter_suggestions_limit'])) {
        $attrs[] = 'suggestions_limit="' . esc_attr($attributes['filter_suggestions_limit']) . '"';
    }

    return implode(' ', $attrs);
}

/**
 * Attributes for <rechat-property-search-form> (disable_filter_* + address/boundary seeds).
 *
 * @param array $attributes Shortcode/block attributes.
 * @return string Space-separated HTML attributes.
 */
function rch_get_rechat_property_search_form_attributes($attributes)
{
    $attrs = array();
    $map_filter_attrs = rch_get_rechat_map_filter_attributes($attributes);
    if ($map_filter_attrs !== '') {
        $attrs[] = $map_filter_attrs;
    }
    rch_append_rechat_listings_disable_filter_attrs($attributes, $attrs);

    return implode(' ', $attrs);
}

/**
 * Rechat SDK disable_filter_* keys (deprecated on <rechat-listings>; still honored by SDK).
 *
 * @return list<string>
 */
function rch_rechat_listings_disable_filter_keys()
{
    return array(
        'disable_filter_address',
        'disable_filter_price',
        'disable_filter_beds',
        'disable_filter_baths',
        'disable_filter_property_types',
        'disable_filter_advanced',
        'disable_filter_loading_indicator',
    );
}

/**
 * Append deprecated disable_filter_*="true" attrs for <rechat-listings> (e.g. property-search-form parent).
 *
 * @param array        $attributes Shortcode/block attributes.
 * @param list<string> $attrs      Output attribute strings (by reference).
 */
function rch_append_rechat_listings_disable_filter_attrs($attributes, array &$attrs)
{
    foreach (rch_rechat_listings_disable_filter_keys() as $key) {
        if (! empty($attributes[ $key ]) && filter_var($attributes[ $key ], FILTER_VALIDATE_BOOLEAN)) {
            $attrs[] = $key . '="true"';
        }
    }
}

/**
 * disabled="true|false" for migrated <rechat-filter-*> tags.
 *
 * @param array  $attributes Shortcode/block attributes.
 * @param string $key        e.g. disable_filter_price
 * @return string
 */
function rch_rechat_filter_disabled_attr($attributes, $key)
{
    $disabled = isset($attributes[$key]) && filter_var($attributes[$key], FILTER_VALIDATE_BOOLEAN);

    return 'disabled="' . ($disabled ? 'true' : 'false') . '"';
}

/**
 * Whether any sub-filter disable flag is set (use individual <rechat-filter-*> tags).
 *
 * @param array $attributes Shortcode/block attributes.
 * @return bool
 */
function rch_listings_filters_use_individual_tags($attributes)
{
    foreach (rch_rechat_listings_disable_filter_keys() as $key) {
        if (! empty($attributes[$key]) && filter_var($attributes[$key], FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
    }

    return false;
}

/**
 * Filter row markup for [listings] (rechat-map-filter or decomposed rechat-filter-*).
 *
 * @param array $attributes Shortcode/block attributes.
 * @return string HTML
 */
function rch_render_listing_filters_html($attributes)
{
    $map_filter_attrs = rch_get_rechat_map_filter_attributes($attributes);

    if (! rch_listings_filters_use_individual_tags($attributes)) {
        return '<rechat-map-filter' . ($map_filter_attrs !== '' ? ' ' . $map_filter_attrs : '') . '></rechat-map-filter>';
    }

    $search_attrs = $map_filter_attrs !== '' ? $map_filter_attrs . ' ' : '';

    return ''
        . '<rechat-filter-search ' . $search_attrs . rch_rechat_filter_disabled_attr($attributes, 'disable_filter_address') . '></rechat-filter-search>'
        . '<rechat-filter-price ' . rch_rechat_filter_disabled_attr($attributes, 'disable_filter_price') . '></rechat-filter-price>'
        . '<rechat-filter-beds ' . rch_rechat_filter_disabled_attr($attributes, 'disable_filter_beds') . '></rechat-filter-beds>'
        . '<rechat-filter-baths ' . rch_rechat_filter_disabled_attr($attributes, 'disable_filter_baths') . '></rechat-filter-baths>'
        . '<rechat-filter-property-type ' . rch_rechat_filter_disabled_attr($attributes, 'disable_filter_property_types') . '></rechat-filter-property-type>'
        . '<rechat-filter-advanced ' . rch_rechat_filter_disabled_attr($attributes, 'disable_filter_advanced') . '></rechat-filter-advanced>'
        . '<rechat-filter-loading ' . rch_rechat_filter_disabled_attr($attributes, 'disable_filter_loading_indicator') . '></rechat-filter-loading>';
}

/*******************************
 * Render rechat-listings attributes (NEW SDK)
 ******************************/
function rch_get_rechat_listings_attributes($attributes, $map_default_center, $listing_statuses_str)
{
    $attrs = array();

    if (! empty($attributes['filter_boundary_ids'])) {
        $attrs[] = 'filter_boundary_ids="' . esc_attr($attributes['filter_boundary_ids']) . '"';
    }

    if (!empty($attributes['filter_search_limit'])) {
        $attrs[] = 'filter_search_limit="' . esc_attr($attributes['filter_search_limit']) . '"';
    }

    if (isset($attributes['filter_pagination_offset']) && (string) $attributes['filter_pagination_offset'] !== '') {
        $attrs[] = 'filter_pagination_offset="' . esc_attr($attributes['filter_pagination_offset']) . '"';
    }

    if (!empty($attributes['property_subtypes'])) {
        $attrs[] = 'filter_property_subtypes="' . esc_attr(
            is_array($attributes['property_subtypes'])
                ? implode(',', $attributes['property_subtypes'])
                : $attributes['property_subtypes']
        ) . '"';
    }

    if (!empty($attributes['architectural_styles'])) {
        $attrs[] = 'filter_architectural_styles="' . esc_attr(
            is_array($attributes['architectural_styles'])
                ? implode(',', $attributes['architectural_styles'])
                : $attributes['architectural_styles']
        ) . '"';
    }

    if (!empty($attributes['filter_baths'])) {
        $attrs[] = 'filter_baths="' . esc_attr($attributes['filter_baths']) . '"';
    }

    if (!empty($attributes['minimum_parking_spaces'])) {
        $attrs[] = 'filter_minimum_parking_spaces="' . esc_attr($attributes['minimum_parking_spaces']) . '"';
    }

    if (isset($attributes['minimum_sold_date']) && (string) $attributes['minimum_sold_date'] !== '') {
        $attrs[] = 'filter_minimum_sold_date="' . esc_attr($attributes['minimum_sold_date']) . '"';
    }

    if (!empty($attributes['filter_pool']) && filter_var($attributes['filter_pool'], FILTER_VALIDATE_BOOLEAN)) {
        $attrs[] = 'filter_pool="true"';
    }

    if (!empty($attributes['filter_agents'])) {
        $attrs[] = 'filter_agents="' . esc_attr(
            is_array($attributes['filter_agents'])
                ? implode(',', $attributes['filter_agents'])
                : $attributes['filter_agents']
        ) . '"';
    }

    if (!empty($attributes['list_offices'])) {
        $attrs[] = 'filter_list_offices="' . esc_attr(
            is_array($attributes['list_offices'])
                ? implode(',', $attributes['list_offices'])
                : $attributes['list_offices']
        ) . '"';
    }

    if (!empty($attributes['filter_brand_id'])) {
        $attrs[] = 'filter_brand_id="' . esc_attr($attributes['filter_brand_id']) . '"';
    }

    if (! empty($attributes['property_types'])) {
        $attrs[] = 'filter_property_types="' . esc_attr(
            is_array($attributes['property_types'])
                ? implode(',', $attributes['property_types'])
                : $attributes['property_types']
        ) . '"';
    }

    if (!empty($attributes['filter_open_houses']) && filter_var($attributes['filter_open_houses'], FILTER_VALIDATE_BOOLEAN)) {
        $attrs[] = 'filter_open_houses="true"';
    }

    if (!empty($attributes['office_exclusive']) && filter_var($attributes['office_exclusive'], FILTER_VALIDATE_BOOLEAN)) {
        $attrs[] = 'filter_office_exclusives="true"';
    }

    if (isset($attributes['disable_price']) && filter_var($attributes['disable_price'], FILTER_VALIDATE_BOOLEAN)) {
        $attrs[] = 'disable_price="true"';
    }

    if (!empty($attributes['minimum_price'])) {
        $attrs[] = 'filter_minimum_price="' . esc_attr($attributes['minimum_price']) . '"';
    }

    if (!empty($attributes['maximum_price'])) {
        $attrs[] = 'filter_maximum_price="' . esc_attr($attributes['maximum_price']) . '"';
    }

    if (!empty($attributes['minimum_bathrooms'])) {
        $attrs[] = 'filter_minimum_bathrooms="' . esc_attr($attributes['minimum_bathrooms']) . '"';
    }

    if (!empty($attributes['maximum_bathrooms'])) {
        $attrs[] = 'filter_maximum_bathrooms="' . esc_attr($attributes['maximum_bathrooms']) . '"';
    }

    if (!empty($attributes['minimum_square_feet'])) {
        $attrs[] = 'filter_minimum_square_feet="' . esc_attr($attributes['minimum_square_feet']) . '"';
    }

    if (!empty($attributes['maximum_square_feet'])) {
        $attrs[] = 'filter_maximum_square_feet="' . esc_attr($attributes['maximum_square_feet']) . '"';
    }

    if (!empty($attributes['minimum_lot_square_feet'])) {
        $attrs[] = 'filter_minimum_lot_square_feet="' . esc_attr($attributes['minimum_lot_square_feet']) . '"';
    }

    if (!empty($attributes['maximum_lot_square_feet'])) {
        $attrs[] = 'filter_maximum_lot_square_feet="' . esc_attr($attributes['maximum_lot_square_feet']) . '"';
    }

    if (!empty($attributes['minimum_year_built'])) {
        $attrs[] = 'filter_minimum_year_built="' . esc_attr($attributes['minimum_year_built']) . '"';
    }

    if (!empty($attributes['maximum_year_built'])) {
        $attrs[] = 'filter_maximum_year_built="' . esc_attr($attributes['maximum_year_built']) . '"';
    }

    if (!empty($attributes['minimum_bedrooms'])) {
        $attrs[] = 'filter_minimum_bedrooms="' . esc_attr($attributes['minimum_bedrooms']) . '"';
    }

    if (!empty($attributes['maximum_bedrooms'])) {
        $attrs[] = 'filter_maximum_bedrooms="' . esc_attr($attributes['maximum_bedrooms']) . '"';
    }

    if (!empty($listing_statuses_str)) {
        $attrs[] = 'filter_listing_statuses="' . esc_attr($listing_statuses_str) . '"';
    }

    // {street_address} is substituted by the SDK. A raw # in the path breaks the URL (fragment).
    // assets/js/rch-listing-hyperlink-fix.js rewrites those links by omitting the # in the slug.
    if (!empty($attributes['listing_hyperlink_href'])) {
        $attrs[] = 'listing_hyperlink_href="' . esc_attr($attributes['listing_hyperlink_href']) . '"';
    } else {
        $attrs[] = 'listing_hyperlink_href="' . home_url() . '/listing-detail/{city}/{street_address}/{id}/"';
    }

    if (!empty($attributes['listing_hyperlink_target'])) {
        $attrs[] = 'listing_hyperlink_target="' . esc_attr($attributes['listing_hyperlink_target']) . '"';
    }

    if (!empty($attributes['sort_by'])) {
        $attrs[] = 'filter_sort_by="' . esc_attr($attributes['sort_by']) . '"';
    }

    if (!empty($attributes['listing_per_page'])) {
        $attrs[] = 'filter_pagination_limit="' . esc_attr($attributes['listing_per_page']) . '"';
    }

    // Deprecated on <rechat-listings> but still works (SDK console.warn). Needed for <rechat-property-search-form>.
    rch_append_rechat_listings_disable_filter_attrs($attributes, $attrs);

    // $attrs[] = 'authorization="' . esc_attr(get_option('rch_rechat_access_token')) . '"';
    return implode("\n      ", $attrs);
}

/**
 * Human-readable listing label for image alt text (address + optional MLS).
 *
 * @param array $listing_detail Listing payload from API.
 * @return string
 */
function rch_listing_alt_base_label(array $listing_detail)
{
    $parts = [];
    if (! empty($listing_detail['formatted']['full_address']['text'])) {
        $parts[] = wp_strip_all_tags((string) $listing_detail['formatted']['full_address']['text']);
    } elseif (! empty($listing_detail['formatted']['street_address']['text'])) {
        $parts[] = wp_strip_all_tags((string) $listing_detail['formatted']['street_address']['text']);
    }
    if (! empty($listing_detail['mls_number']) && is_scalar($listing_detail['mls_number'])) {
        $parts[] = sprintf(
            /* translators: %s: MLS number */
            __('MLS #%s', 'rechat-plugin'),
            sanitize_text_field((string) $listing_detail['mls_number'])
        );
    }
    $label = implode(' — ', array_filter($parts));
    if ($label === '') {
        return __('Property listing', 'rechat-plugin');
    }

    return $label;
}

/**
 * Build listing image alt text with optional suffix (e.g. photo index).
 *
 * @param array       $listing_detail Listing payload.
 * @param string      $suffix         Optional fragment after an em dash.
 * @return string
 */
function rch_listing_format_image_alt(array $listing_detail, $suffix = '')
{
    $base   = rch_listing_alt_base_label($listing_detail);
    $suffix = is_string($suffix) ? trim($suffix) : '';
    $out    = $suffix !== '' ? $base . ' — ' . $suffix : $base;

    return apply_filters('rch_listing_image_alt', $out, $listing_detail, $suffix);
}

/**
 * Alt text for a gallery image by 1-based index and total count.
 *
 * @param array    $listing_detail Listing payload.
 * @param int      $index_1_based  Position in gallery (1 = first / cover).
 * @param int|null $total          Total photos, or null to omit “of N”.
 * @return string
 */
function rch_listing_gallery_image_alt(array $listing_detail, $index_1_based, $total = null)
{
    $index_1_based = max(1, (int) $index_1_based);
    $total         = $total !== null ? (int) $total : null;

    if ($total !== null && $total > 1) {
        $suffix = sprintf(
            /* translators: 1: current photo number, 2: total photos */
            __('photo %1$d of %2$d', 'rechat-plugin'),
            $index_1_based,
            $total
        );

        return rch_listing_format_image_alt($listing_detail, $suffix);
    }
    if ($index_1_based > 1) {
        return rch_listing_format_image_alt(
            $listing_detail,
            sprintf(
                /* translators: %d: photo number */
                __('photo %d', 'rechat-plugin'),
                $index_1_based
            )
        );
    }

    return rch_listing_format_image_alt($listing_detail, '');
}
