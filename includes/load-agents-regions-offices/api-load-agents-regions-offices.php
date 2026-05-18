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
    $brand_token = get_option('rch_rechat_brand_id');
    $api_url_base = rtrim(RECHAT_API_BASE_URL, '/') . '/brands/' . rawurlencode((string) $brand_token) . '/users?associations[]=brand.parents&associations[]=brand.settings';

    /*******************************
     * Fetch and process regions and offices
     ******************************/
    $brands_result = rch_fetch_and_process_brands($api_url_base, $access_token);

    if (!$brands_result['success']) {
        $fail_message = isset($brands_result['message']) ? (string) $brands_result['message'] : __('Could not fetch brands data.', 'rechat-plugin');

        return array(
            'success' => false,
            'message' => $fail_message,
        );
    }

    // Debug log - Log the number of regions and offices found
    error_log('Rechat Plugin - Found ' . count($brands_result['regions']) . ' regions');
    error_log('Rechat Plugin - Found ' . count($brands_result['offices']) . ' offices');

    // Debug log - Log the first few offices to verify structure
    if (!empty($brands_result['offices'])) {
        $sample_offices = array_slice($brands_result['offices'], 0, 3);
        foreach ($sample_offices as $index => $office) {
            error_log('Rechat Plugin - Office ' . ($index + 1) . ': ' . $office['name'] . ', ID: ' . $office['id'] .
                ', Associated Regions: ' . implode(', ', $office['region_parent_ids']));
        }
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

    $GLOBALS['rch_doing_rechat_sync'] = true;

    foreach ($offices as $office) {
        // Insert or update the office post with regions, address, and phone
        $result = rch_insert_or_update_post(
            'offices',
            $office['name'],
            $office['id'],
            'office_id',
            $office['region_parent_ids'],
            $office['address'] ?? '',
            $office['phone'] ?? ''
        );
        if ($result === 'added') {
            $office_add_count++;
        } elseif ($result === 'updated') {
            $office_update_count++;
        }

        // Store the office ID for later use
        $current_office_ids[] = $office['id'];
    }

    $GLOBALS['rch_doing_rechat_sync'] = false;

    // Delete outdated Region and Office posts
    rch_delete_outdated_posts('regions', $current_region_ids, 'region_id');
    rch_delete_outdated_posts('offices', $current_office_ids, 'office_id');



    // Process agents (user.first_name, user.last_name, email, etc. → agent post meta / metabox).
    $agents_result = rch_process_agents_data($access_token, $api_url_base);
    // Get current agent IDs
    $current_agent_ids = get_posts(array(
        'post_type' => 'agents',
        'numberposts' => -1,
        'fields' => 'ids',
    ));
    if (!$agents_result) {
        return array(
            'success' => false,
            'message' => __('Error processing agents', 'rechat-plugin'),
        );
    }
    // Assemble the sync result data.
    $sync_data = array(
        'agents'  => "<b>Agents</b></br> added: {$agents_result['agent_add_count']}, updated: {$agents_result['agent_update_count']}",
        'regions' => "<b>Regions</b></br> added: $region_add_count, updated: $region_update_count",
        'offices' => "<b>Offices</b></br> added: $office_add_count, updated: $office_update_count",
        'branding' => "<b>Branding</b></br> Updated primary color and logos",
    );

    /**
     * Allow other modules (e.g. the Multisite module) to append extra entries
     * to the sync-result data that is returned to the admin JS.
     *
     * @param array $sync_data  Associative array of result strings shown in the UI.
     */
    $sync_data = apply_filters('rch_sync_response_data', $sync_data);

    // Return sync summary for callers (AJAX handler sends JSON; cron logs result).
    return array(
        'success' => true,
        'data'    => $sync_data,
    );
}
