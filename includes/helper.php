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

    // Format the expiry timestamp into a readable date
    return date('Y-m-d H:i:s', $expiry_timestamp);
}
/*******************************
 * disconnect handler from rechat oauth
 ******************************/

function rch_handle_disconnect_rechat()
{
    // Check if the form was submitted
    if (isset($_POST['action']) && $_POST['action'] === 'disconnect_rechat') {
        // Verify nonce for security
        if (!isset($_POST['disconnect_rechat_nonce_field']) || !wp_verify_nonce($_POST['disconnect_rechat_nonce_field'], 'disconnect_rechat_nonce')) {
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

        // Check parent brands until a 'Region' is found
        $current_parent = $brand['parent'] ?? null;
        while ($current_parent) {
            if ($current_parent['brand_type'] == 'Region') {
                // Add the ID of the Region to the region parent IDs
                $region_parent_ids[] = $current_parent['id'];
            }

            // Move up the hierarchy to the next parent
            $current_parent = $current_parent['parent'] ?? null;
        }

        // Add office data along with the collected region parent IDs
        $offices[] = [
            'id' => $brand['id'],
            'name' => $brand['name'], // Adjust this according to the structure of your brand
            'region_parent_ids' => $region_parent_ids, // Add the region parent IDs here
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
function rch_insert_or_update_post($post_type, $brand_name, $brand_id, $meta_key, $associated_regions = null)
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
            foreach ($data['data'] as $user_data) {
                if (isset($user_data['brands'])) {
                    foreach ($user_data['brands'] as $brand) {
                        rch_collect_brands($brand, $regions, $offices, $processed_brands);
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
                    'api_id' => $api_id,
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
    return array_filter([
        'minimum_price' => isset($atts['minimum_price']) && $atts['minimum_price'] !== '' ? intval($atts['minimum_price']) : null,
        'maximum_price' => isset($atts['maximum_price']) && $atts['maximum_price'] !== '' ? intval($atts['maximum_price']) : null,
        'minimum_lot_square_meters' => isset($atts['minimum_lot_square_meters']) && $atts['minimum_lot_square_meters'] !== '' ? intval($atts['minimum_lot_square_meters']) : null,
        'maximum_lot_square_meters' => isset($atts['maximum_lot_square_meters']) && $atts['maximum_lot_square_meters'] !== '' ? intval($atts['maximum_lot_square_meters']) : null,
        'minimum_bathrooms' => isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] !== '' ? intval($atts['minimum_bathrooms']) : null,
        'maximum_bathrooms' => isset($atts['maximum_bathrooms']) && $atts['maximum_bathrooms'] !== '' ? intval($atts['maximum_bathrooms']) : null,
        'minimum_square_meters' => isset($atts['minimum_square_meters']) && $atts['minimum_square_meters'] !== '' ? intval($atts['minimum_square_meters']) : null,
        'maximum_square_meters' => isset($atts['maximum_square_meters']) && $atts['maximum_square_meters'] !== '' ? intval($atts['maximum_square_meters']) : null,
        'minimum_year_built' => isset($atts['minimum_year_built']) && $atts['minimum_year_built'] !== '' ? intval($atts['minimum_year_built']) : null,
        'maximum_year_built' => isset($atts['maximum_year_built']) && $atts['maximum_year_built'] !== '' ? intval($atts['maximum_year_built']) : null,
        'minimum_bedrooms' => isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] !== '' ? intval($atts['minimum_bedrooms']) : null,
        'maximum_bedrooms' => isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] !== '' ? intval($atts['maximum_bedrooms']) : null,
        'brand' => isset($atts['brand']) ? $atts['brand'] : null,
        // Only include listing_statuses if it’s not empty
        'listing_statuses' => !empty($atts['listing_statuses']) ? explode(',', $atts['listing_statuses']) : null
    ], function ($value) {
        return $value !== null; // Filter out null values
    });
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
function rch_get_primary_color($brand_id)
{
    // API endpoint to fetch brand settings, including parent brands
    $palette_url = 'https://api.rechat.com/brands/' . $brand_id . '?associations[]=brand.parent&associations[]=brand.settings';

    // Make the API request
    $palette_response = wp_remote_get($palette_url);
    if (is_wp_error($palette_response)) {
        error_log('Error fetching marketing palette: ' . $palette_response->get_error_message());
        return '#2271b1'; // Return default color on error
    }

    // Parse the response body
    $palette_body = wp_remote_retrieve_body($palette_response);
    $palette_data = json_decode($palette_body, true);


    // Traverse the brand and its parents to find the color
    if (isset($palette_data['data'])) {
        $brand = $palette_data['data'];
        return rch_find_primary_color($brand);
    }

    // Return default color if no data is found
    return '#2271b1';
}

/*******************************
 *Helper function to find the button color by traversing the brand hierarchy.
 ******************************/
function rch_find_primary_color($brand)
{
    $primary_color = null;

    // Traverse the brand hierarchy to find the primary color
    do {
        if (isset($brand['settings']['marketing_palette']['colors'])) {
            $primary_color = $brand['settings']['marketing_palette']['inverted-container-bg-color'];
        }
        $brand = isset($brand['parent']) ? $brand['parent'] : null;
    } while (!$primary_color && $brand);

    // Sanitize the color or return a default color
    return $primary_color ? sanitize_hex_color($primary_color) : '#2271b1';
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

    // Return the option as a JSON response
    return new WP_REST_Response(array(
        'rch_rechat_brand_id' => $brand_id,
        'rch_rechat_access_token' => $access_token,
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

