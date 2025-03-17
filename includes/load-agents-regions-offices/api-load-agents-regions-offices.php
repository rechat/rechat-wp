<?php
if (! defined('ABSPATH')) {
    exit();
}
// add_action('init','rch_update_agents_offices_regions_data');
function rch_update_agents_offices_regions_data()
{
    /*******************************
     * define data for api and call api
     ******************************/

    $access_token = get_option('rch_rechat_access_token');
    // $access_token = 'ZjU1NTBhYWUtNWJhYS0xMWVmLTg1MGQtMGU0YmI5NTMxYmQ5';
    $brand_token = get_option('rch_rechat_brand_id');
    // $brand_token = '2f082158-e865-11eb-899f-0271a4acc769';
    $api_url_base = 'https://api.rechat.com/brands/' . $brand_token . '/users?associations[]=brand.parents'; // Adjust the URL as needed

    /*******************************
     * Fetch and process regions and offices
     ******************************/
    $brands_result = rch_fetch_and_process_brands($api_url_base, $access_token);

    if (!$brands_result['success']) {
        wp_send_json(array('success' => false, 'data' => $brands_result['message']));
    }

    $regions = $brands_result['regions'];
    $offices = $brands_result['offices'];
    // Insert or update Region and Office posts
    $current_region_ids = [];
    $region_add_count = 0;
    $region_update_count = 0;
    foreach ($regions as $region) {
        $result = rch_insert_or_update_post('regions', $region['name'], $region['id'], 'region_id');
        if ($result === 'added') {
            $region_add_count++;
        } elseif ($result === 'updated') {
            $region_update_count++;
        }
        $current_region_ids[] = $region['id'];
    }

    $current_office_ids = [];
    $office_add_count = 0;
    $office_update_count = 0;
    foreach ($offices as $office) {
        // Insert or update the office post with regions
        $result = rch_insert_or_update_post('offices', $office['name'], $office['id'], 'office_id', $office['region_parent_ids']);
        
        if ($result === 'added') {
            $office_add_count++;
        } elseif ($result === 'updated') {
            $office_update_count++;
        }
    
        // Store the office ID for later use
        $current_office_ids[] = $office['id'];
    }

    // Delete outdated Region and Office posts
    rch_delete_outdated_posts('regions', $current_region_ids, 'region_id');
    rch_delete_outdated_posts('offices', $current_office_ids, 'office_id');



    // Process agents
    $agents_result = rch_process_agents_data($access_token, $api_url_base);
    // Get current agent IDs
    $current_agent_ids = get_posts(array(
        'post_type' => 'agents',
        'numberposts' => -1,
        'fields' => 'ids',
    ));
    if (!$agents_result) {
        wp_send_json(array('success' => false, 'message' => 'Error processing agents'));
    }
    // Return success message with counts for agents, regions, and offices
    wp_send_json(array(
        'success' => true,
        'data'    => array(
            'agents'  => "<b>Agents</b></br> added: {$agents_result['agent_add_count']}, updated: {$agents_result['agent_update_count']}",
            'regions' => "<b>Regions</b></br> added: $region_add_count, updated: $region_update_count",
            'offices' => "<b>Offices</b></br> added: $office_add_count, updated: $office_update_count"
        )
    ));
}
