<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
/*******************************
 * change to readable Date
 ******************************/
function get_token_expiry_date($seconds_until_expire)
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

function handle_disconnect_rechat()
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
add_action('init', 'handle_disconnect_rechat');
/*******************************
 * Helper function to filter brands by type
 ******************************/
function filter_brands_by_type($brands, $types)
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
            $filtered_brands = array_merge($filtered_brands, filter_brands_by_type([$brand['parent']], $types));
        }
    }
    return $filtered_brands;
}
/*******************************
 * Function to collect brands recursively
 ******************************/
function collect_brands($brand, &$regions, &$offices, &$processed_brands)
{
    if (in_array($brand['id'], $processed_brands)) {
        return;
    }
    $processed_brands[] = $brand['id'];
    if ($brand['brand_type'] == 'Region') {
        $regions[] = $brand;
    } elseif ($brand['brand_type'] == 'Office') {
        $offices[] = $brand;
    }
    if (isset($brand['parent']) && !empty($brand['parent'])) {
        collect_brands($brand['parent'], $regions, $offices, $processed_brands);
    }
}
/*******************************
 * call api
 ******************************/
function api_request($url, $token)
{
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
    ));
    if (is_wp_error($response)) {
        return array('success' => false, 'message' => 'Error fetching data');
    }
    return array('success' => true, 'data' => json_decode(wp_remote_retrieve_body($response), true));
}
/*******************************
 * Function to insert or update posts and save meta data
 ******************************/
function insert_or_update_post($post_type, $brand_name, $brand_id, $meta_key)
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
        return 'added';
    }
}
/*******************************
 * Function to delete outdated posts
 ******************************/
function delete_outdated_posts($post_type, $current_brand_ids, $meta_key)
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
function fetch_and_process_brands($api_url_base, $access_token)
{
    $regions = [];
    $offices = [];
    $processed_brands = [];
    $limit = 100; // Adjust the limit as needed
    $offset = 0;

    do {
        $api_url = $api_url_base . "&limit=$limit&start=$offset";
        $response = api_request($api_url, $access_token);
        if (!$response['success']) {
            return $response; // Return error if API request fails
        }
        $data = $response['data'];

        if (isset($data['data'])) {
            foreach ($data['data'] as $user_data) {
                if (isset($user_data['brands'])) {
                    foreach ($user_data['brands'] as $brand) {
                        collect_brands($brand, $regions, $offices, $processed_brands);
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
function process_agents_data($access_token, $api_url_base)
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
        $response = api_request($api_url, $access_token);
        
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
                $brands = isset($item['brands']) ? filter_brands_by_type($item['brands'], ["Region", "Office"]) : [];
                
                foreach ($brands as $brand) {
                    if ($brand['brand_type'] === 'Region') {
                        // Find region post by title
                        $region_query = new WP_Query(array(
                            'post_type' => 'regions',
                            'title' => $brand['name'],
                            'post_status' => 'publish',
                            'posts_per_page' => 1,
                        ));

                        if ($region_query->have_posts()) {
                            $region_query->the_post();
                            $regions_for_agent[] = get_the_ID(); // Store the ID of the region
                            wp_reset_postdata();
                        }
                    } elseif ($brand['brand_type'] === 'Office') {
                        // Find office post by title
                        $office_query = new WP_Query(array(
                            'post_type' => 'offices',
                            'title' => $brand['name'],
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
    delete_outdated_posts('agents', $new_api_ids, 'api_id');

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
        'minimum_price' => isset($atts['minimum_price']) ? intval($atts['minimum_price']) : '',
        'maximum_price' => isset($atts['maximum_price']) ? intval($atts['maximum_price']) : '',
        'minimum_lot_square_meters' => isset($atts['minimum_lot_square_meters']) ? intval($atts['minimum_lot_square_meters']) : '',
        'maximum_lot_square_meters' => isset($atts['maximum_lot_square_meters']) ? intval($atts['maximum_lot_square_meters']) : '',
        'minimum_bathrooms' => isset($atts['minimum_bathrooms']) ? intval($atts['minimum_bathrooms']) : '',
        'maximum_bathrooms' => isset($atts['maximum_bathrooms']) ? intval($atts['maximum_bathrooms']) : '',
        'minimum_square_meters' => isset($atts['minimum_square_meters']) ? intval($atts['minimum_square_meters']) : '',
        'maximum_square_meters' => isset($atts['maximum_square_meters']) ? intval($atts['maximum_square_meters']) : '',
        'minimum_year_built' => isset($atts['minimum_year_built']) ? intval($atts['minimum_year_built']) : '',
        'maximum_year_built' => isset($atts['maximum_year_built']) ? intval($atts['maximum_year_built']) : '',
        'minimum_bedrooms' => isset($atts['minimum_bedrooms']) ? intval($atts['minimum_bedrooms']) : '',
        'maximum_bedrooms' => isset($atts['maximum_bedrooms']) ? intval($atts['maximum_bedrooms']) : ''
    ]);
}
/*******************************
 *title for page house detail
 ******************************/
function custom_single_listing_title($title) {
    if (isset($_GET['house_id'])) {
        $house_id = sanitize_text_field($_GET['house_id']);
        // You can modify the title as per your needs
        $title = 'House Details - ' . $house_id;
    }
    return $title;
}
add_filter('wp_title', 'custom_single_listing_title');
function remove_404_for_house_listing() {
    if (isset($_GET['house_id'])) {
        global $wp_query;
        $wp_query->is_404 = false; // Mark this request as a valid one, not 404
    }
}
add_action('wp', 'remove_404_for_house_listing');
