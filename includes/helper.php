<?php
if (! defined('ABSPATH')) {
    exit();
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
 * disconnect handler from rechat oauth
 ******************************/

function rch_handle_disconnect_rechat()
{
    // Check if the form was submitted
    if (isset($_POST['action']) && $_POST['action'] === 'disconnect_rechat') {
        // Verify nonce for security
        // Unsash the value to make sure it is sanitized properly
        if (!isset($_POST['disconnect_rechat_nonce_field']) || !wp_verify_nonce(wp_unslash($_POST['disconnect_rechat_nonce_field']), 'disconnect_rechat_nonce')) {
            wp_die('Nonce verification failed');
        }

        // Delete the access token option
        if (false === delete_option('rch_rechat_access_token')) {
            wp_die('Failed to delete the access token');
        }

        // Optionally redirect to the same page or a different page
        $redirect_url = add_query_arg('status', 'disconnected', admin_url('admin.php?page=rechat-setting'));
        wp_redirect($redirect_url);
        exit;
    }
}

add_action('init', 'rch_handle_disconnect_rechat');
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

        // Extract address from brand or parent hierarchy
        $office_address = '';

        // Check if address.full exists in current brand settings
        if (isset($brand['settings']['marketing_palette']['address']['full']) && !empty($brand['settings']['marketing_palette']['address']['full'])) {
            $office_address = $brand['settings']['marketing_palette']['address']['full'];
        } else {
            // If not, traverse parent hierarchy to find address.full
            $current_parent = $brand['parent'] ?? null;
            while ($current_parent && empty($office_address)) {
                // Check if current parent has an address.full in settings
                if (isset($current_parent['settings']['marketing_palette']['address']['full']) && !empty($current_parent['settings']['marketing_palette']['address']['full'])) {
                    $office_address = $current_parent['settings']['marketing_palette']['address']['full'];
                    break;
                }

                // Also check if this parent has parents array
                if (empty($office_address) && isset($current_parent['parents']) && is_array($current_parent['parents'])) {
                    // Check all brands to find parents with addresses
                    foreach ($current_parent['parents'] as $grandparent_id) {
                        // Look for this grandparent in all_brands (if available) or regions
                        foreach ($regions as $region) {
                            if ($region['id'] === $grandparent_id && isset($region['settings']['marketing_palette']['address']['full']) && !empty($region['settings']['marketing_palette']['address']['full'])) {
                                $office_address = $region['settings']['marketing_palette']['address']['full'];
                                break 2; // Break out of both foreach loops
                            }
                        }
                    }
                }

                // Move up the hierarchy to the next parent
                $current_parent = $current_parent['parent'] ?? null;
            }
        }

        // Add office data along with the collected region parent IDs and address
        $offices[] = [
            'id' => $brand['id'],
            'name' => $brand['name'],
            'region_parent_ids' => $region_parent_ids,
            'address' => $office_address,
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
        return array('success' => false, 'message' => 'Error fetching data');
    }
    return array('success' => true, 'data' => json_decode(wp_remote_retrieve_body($response), true));
}
/*******************************
 * Function to insert or update posts and save meta data
 ******************************/
function rch_insert_or_update_post($post_type, $brand_name, $brand_id, $meta_key, $associated_regions = null, $address = null)
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


                // Prepare other fields
                $user = $item['user'];
                $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $custom_fields = array(
                    'website' => $user['website'] ?? '',
                    'instagram' => $user['instagram'] ?? '',
                    'twitter' => $user['twitter'] ?? '',
                    'linkedin' => $user['linkedin'] ?? '',
                    'youtube' => $user['youtube'] ?? '',
                    'facebook' => $user['facebook'] ?? '',
                    'profile_image_url' => !empty($user['profile_image_url']) ? $user['profile_image_url'] : $default_profile_image_url,
                    'phone_number' => $user['phone_number'] ?? '',
                    'email' => $user['email'] ?? '',
                    'timezone' => $user['timezone'] ?? '',
                    'designation' => $user['designation'] ?? '',
                    'license_number' => $user['agents'][0]['license_number'] ?? '',
                    'agents' => isset($user['agents']) && is_array($user['agents']) ?
                        array_map(function ($agent) {
                            return $agent['id'] ?? null;
                        }, array_filter($user['agents'], function ($agent) {
                            return isset($agent['id']);
                        })) : array(),
                    'api_id' => $api_id,
                    'last_name' => $user['last_name'] ?? '',
                    '_rch_agent_regions' => $regions_for_agent,
                    '_rch_agent_offices' => $offices_for_agent,
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
    $palette_url = 'https://api.rechat.com/brands/' . $brand_id . '?associations[]=brand.parent&associations[]=brand.settings';

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

    // Return the option as a JSON response
    return new WP_REST_Response(array(
        'rch_rechat_brand_id' => $brand_id,
        'rch_rechat_access_token' => $access_token,
        'rch_rechat_google_map_api_key' => $google_map_api_key,
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
        'listing_per_page' => array('type' => 'string', 'default' => 5),
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
        'layout_style' => array('type' => 'string', 'default' => 'default'),
        'own_listing' => array('type' => 'boolean', 'default' => true),
        'property_types' => array('type' => 'string', 'default' => ''),
        'filter_open_houses' => array('type' => 'boolean', 'default' => false),
        'office_exclusive' => array('type' => 'boolean', 'default' => false),
        'disable_sort' => array('type' => 'boolean', 'default' => false),
        'map_latitude' => array('type' => 'string', 'default' => ''),
        'map_longitude' => array('type' => 'string', 'default' => ''),
        'map_zoom' => array('type' => 'string', 'default' => '12'),
        'sort_by' => array('type' => 'string', 'default' => '-list_date'),
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
 * Render listing block layout styles
 ******************************/
function rch_render_layout_styles($layout_style, $primary_color)
{
    $text_color = rch_get_contrast_text_color($primary_color);

    ob_start();
?>
    <style>
        .rechat-component.map__marker {
            background-color: <?php echo esc_attr($primary_color); ?> !important;
            color: <?php echo esc_attr($text_color); ?> !important;
            box-sizing: content-box;
        }
    </style>

    <?php if ($layout_style === 'layout2' || $layout_style === 'layout3'): ?>
        <style>
            <?php if ($layout_style === 'layout2'): ?>.map {
                flex: 3;
            }

            .listings {
                flex: 7;
                min-height: 0;
                overflow: auto;
            }

            <?php elseif ($layout_style === 'layout3'): ?>.map {
                flex: 9;
            }

            .listings {
                flex: 3;
                min-height: 0;
                overflow: auto;
            }

            <?php endif; ?>
        </style>
    <?php endif; ?>
<?php
    return ob_get_clean();
}

/*******************************
 * Render search form styles
 ******************************/
function rch_render_search_form_styles($form_id, $show_background, $background_image, $primary_color)
{
    ob_start();
?>
    <style>
        .rch-search-listing-form {
            position: relative;
            z-index: 100;
        }

        #<?php echo $form_id; ?> {
            padding: 0;
            margin: 0;
        }

        #<?php echo $form_id; ?>.listing-filter__dropdown_trigger,
        #<?php echo $form_id; ?>.search_address_input {
            background-color: #fff;
        }

        #<?php echo $form_id; ?>.listing-filter__dropdown_trigger:hover,
        #<?php echo $form_id; ?>.search_address_input:hover {
            background-color: #fff;
        }

        #<?php echo $form_id; ?>.rch-search-container {
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            max-width: 100% !important;
            align-items: center;
            justify-content: center;
        }

        .rechat-button.color-accent {
            background-color: <?php echo esc_attr($primary_color); ?> !important;
            border-color: <?php echo esc_attr($primary_color); ?> !important;
            color: <?php echo esc_attr(rch_get_contrast_text_color($primary_color)); ?> !important;
        }

        .rechat-button.color-accent p {
            color: <?php echo esc_attr(rch_get_contrast_text_color($primary_color)); ?> !important;
        }

        .rechat-switch-button input:checked+.rechat-switch-button__slider {
            background: <?php echo esc_attr($primary_color); ?>;
        }

        <?php if ($show_background && $background_image): ?>#<?php echo $form_id; ?>.rch-search-background {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            background-image: url('<?php echo $background_image; ?>');
            background-size: contain;
            filter: blur(3px) grayscale(0.1) brightness(70%);
        }

        <?php endif; ?>
    </style>
<?php
    return ob_get_clean();
}

/*******************************
 * Render rechat root attributes
 ******************************/
function rch_get_rechat_root_attributes($attributes, $map_default_center, $listing_statuses_str)
{
    $attrs = array();

    if (!empty($attributes['own_listing']) && filter_var($attributes['own_listing'], FILTER_VALIDATE_BOOLEAN)) {
        $attrs[] = 'brand_id="' . esc_attr($attributes['brand']) . '"';
    }

    if (!empty($attributes['map_zoom'])) {
        $attrs[] = 'map_zoom="' . esc_attr($attributes['map_zoom']) . '"';
    }

    if (!empty($attributes['map_id'])) {
        $attrs[] = 'map_id="' . esc_attr($attributes['map_id']) . '"';
    }

    $attrs[] = 'map_api_key="' . esc_attr(get_option('rch_rechat_google_map_api_key')) . '"';

    if (!empty($map_default_center)) {
        $attrs[] = 'map_default_center="' . esc_attr($map_default_center) . '"';
    }

    if (isset($attributes['filter_address'])) {
        $attrs[] = 'filter_address="' . esc_attr($attributes['filter_address']) . '"';
    }

if (!empty($attributes['property_types'])) {
    $attrs[] = 'filter_property_types="' . esc_attr(
        is_array($attributes['property_types'])
            ? implode(',', $attributes['property_types'])
            : $attributes['property_types']
    ) . '"';
} else {
    // property_types is empty → explicitly send empty property_subtypes
    $attrs[] = 'filter_property_types=""';
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

    // Always output disable filter attributes with proper boolean values
    $attrs[] = 'disable_filter_address="' . (isset($attributes['disable_filter_address']) && filter_var($attributes['disable_filter_address'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false') . '"';
    $attrs[] = 'disable_filter_price="' . (isset($attributes['disable_filter_price']) && filter_var($attributes['disable_filter_price'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false') . '"';
    $attrs[] = 'disable_filter_beds="' . (isset($attributes['disable_filter_beds']) && filter_var($attributes['disable_filter_beds'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false') . '"';
    $attrs[] = 'disable_filter_baths="' . (isset($attributes['disable_filter_baths']) && filter_var($attributes['disable_filter_baths'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false') . '"';
    $attrs[] = 'disable_filter_property_types="' . (isset($attributes['disable_filter_property_types']) && filter_var($attributes['disable_filter_property_types'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false') . '"';
    $attrs[] = 'disable_filter_advanced="' . (isset($attributes['disable_filter_advanced']) && filter_var($attributes['disable_filter_advanced'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false') . '"';

    if (!empty($attributes['listing_hyperlink_href'])) {
        $attrs[] = 'listing_hyperlink_href="' . esc_attr($attributes['listing_hyperlink_href']) . '"';
    } else {
        $attrs[] = 'listing_hyperlink_href="' . home_url() . '/listing-detail/{street_address}/{id}/"';
    }

    if (!empty($attributes['listing_hyperlink_target'])) {
        $attrs[] = 'listing_hyperlink_target="' . esc_attr($attributes['listing_hyperlink_target']) . '"';
    }

    if (!empty($attributes['sort_by'])) {
        $attrs[] = 'filter_sort_by="' . esc_attr($attributes['sort_by']) . '"';
    }

    // $attrs[] = 'authorization="' . esc_attr(get_option('rch_rechat_access_token')) . '"';
    return implode("\n      ", $attrs);
}
