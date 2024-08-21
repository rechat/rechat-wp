<?php
function get_agents_data()
{
    $brand_id = '2f082158-e865-11eb-899f-0271a4acc769';
    $access_token = 'YWQ2ZTQ3MzgtNGFkZC0xMWVmLTg4ODItMGU0YmI5NTMxYmQ5';
    $api_url_base = "https://api.rechat.com/brands/$brand_id/users?associations[]=brand.parents";

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
        ),
    );

    $limit = 20; // Set the number of records to fetch per page
    $offset = 0;  // Start from the first record

    // Define the default profile image URL
    $default_profile_image_url = RCH_PLUGIN_URL . 'assets/images/image-placeholder.svg';

    // Get existing posts and their API IDs
    $existing_posts = get_posts(array(
        'post_type'   => 'agents',
        'numberposts' => -1,
        'meta_key'    => 'api_id',
        'fields'      => 'ids'
    ));

    $existing_api_ids = array();
    foreach ($existing_posts as $post_id) {
        $api_id = get_post_meta($post_id, 'api_id', true);
        if ($api_id) {
            $existing_api_ids[$api_id] = $post_id;
        }
    }

    // Find the highest current menu_order value
    $max_menu_order = (int) get_posts(array(
        'post_type'      => 'agents',
        'numberposts'    => 1,
        'orderby'        => 'menu_order',
        'order'          => 'DESC',
        'fields'         => 'menu_order',
        'post_status'    => 'publish',
    ));

    // Set the initial value for menu_order
    $current_menu_order = $max_menu_order + 1;

    do {
        $api_url = $api_url_base . "&limit=$limit&start=$offset";

        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            echo "Something went wrong: $error_message";
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $item) {
                $api_id = $item['id'];
                $user = $item['user'];
                $first_name = $user['first_name'] ?? '';
                $last_name = $user['last_name'] ?? '';
                $profile_image_url = !empty($user['profile_image_url']) ? $user['profile_image_url'] : $default_profile_image_url;

                $website = $user['website'] ?? '';
                $instagram = $user['instagram'] ?? '';
                $twitter = $user['twitter'] ?? '';
                $linkedin = $user['linkedin'] ?? '';
                $youtube = $user['youtube'] ?? '';
                $facebook = $user['facebook'] ?? '';
                $phone_number = $user['phone_number'] ?? '';
                $email = $user['email'] ?? '';
                $timezone = $user['timezone'] ?? '';

                $full_name = trim($first_name . ' ' . $last_name);

                // Prepare custom field data
                $custom_fields = array(
                    'website' => $website,
                    'instagram' => $instagram,
                    'twitter' => $twitter,
                    'linkedin' => $linkedin,
                    'youtube' => $youtube,
                    'facebook' => $facebook,
                    'phone_number' => $phone_number,
                    'email' => $email,
                    'timezone' => $timezone,
                    'profile_image_url' => $profile_image_url // Store image URL in a custom field
                );

                if (isset($existing_api_ids[$api_id])) {
                    $post_id = $existing_api_ids[$api_id];

                    $post_data = array(
                        'ID'           => $post_id,
                        'post_title'   => $full_name,
                        'post_content' => '',
                        'post_status'  => 'publish',
                        'menu_order'   => $current_menu_order, // Set the menu_order
                    );

                    $updated_post_id = wp_update_post($post_data);

                    if (is_wp_error($updated_post_id)) {
                        echo "Error updating post: " . $updated_post_id->get_error_message();
                    } else {
                        // Update API ID and custom fields
                        update_post_meta($updated_post_id, 'api_id', $api_id);
                        foreach ($custom_fields as $key => $value) {
                            update_post_meta($updated_post_id, $key, $value);
                        }
                    }

                    unset($existing_api_ids[$api_id]);
                } else {
                    $post_data = array(
                        'post_title'   => $full_name,
                        'post_content' => '',
                        'post_status'  => 'publish',
                        'post_type'    => 'agents',
                        'menu_order'   => $current_menu_order, // Set the menu_order
                    );

                    $post_id = wp_insert_post($post_data);

                    if (is_wp_error($post_id)) {
                        echo "Error creating post: " . $post_id->get_error_message();
                    } else {
                        // Add API ID and custom fields
                        update_post_meta($post_id, 'api_id', $api_id);
                        foreach ($custom_fields as $key => $value) {
                            update_post_meta($post_id, $key, $value);
                        }
                    }
                }

                // Increment the menu_order for the next post
                $current_menu_order++;
            }

            // Update the offset for the next iteration
            $offset += $limit;
        } else {
            break; // No more data, exit the loop
        }
    } while (count($data['data']) === $limit); // Continue while we have data

    // Delete posts that were not in the current API data
    foreach ($existing_api_ids as $api_id => $post_id) {
        wp_delete_post($post_id, true);
    }
}
