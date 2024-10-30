<?php

if (! defined('ABSPATH')) {
    exit();
}

/*******************************
 * Fetch house listings with filters.
 ******************************/
function rch_fetch_listing($filters, $page, $housesPerPage)
{
    $brandId = get_option('rch_rechat_brand_id');
    $offset = ($page - 1) * $housesPerPage;

    $requestBody = array_merge([
        'brand' => $brandId,
        'limit' => $housesPerPage,
        'offset' => $offset,
    ], $filters);

    $response = wp_remote_post('https://api.rechat.com/valerts', [
        'method' => 'POST',
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($requestBody),
        'timeout' => 15, // Set the timeout to 15 seconds

    ]);

    if (is_wp_error($response)) {
        return 'Error: ' . $response->get_error_message();
    }
    return json_decode(wp_remote_retrieve_body($response), true);
}

/*******************************
 * Fetch total listing count for pagination.
 ******************************/
function rch_fetch_total_listing_count($filters = [])
{
    $brandId = get_option('rch_rechat_brand_id');

    $requestBody = array_merge(['brand' => $brandId], $filters);

    $response = wp_remote_post('https://api.rechat.com/valerts/count', [
        'method' => 'POST',
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode($requestBody),
        'timeout' => 15, // Set the timeout to 15 seco
    ]);

    if (is_wp_error($response)) {
        return 'Error: ' . $response->get_error_message();
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

/*******************************
 * Handle the AJAX request for fetching listings.
 ******************************/
function rch_fetch_listing_ajax()
{
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $housesPerPage = isset($_GET['houses_per_page']) ? intval($_GET['houses_per_page']) : 50;

    // Get filters from the AJAX request
    $filters = rch_get_filters($_GET);

    // Fetch houses
    $housesData = rch_fetch_listing($filters, $page, $housesPerPage);
    if (!empty($housesData['data'])) {
        ob_start();
        foreach ($housesData['data'] as $house) {
            // Use locate_template to check the theme first, then fall back to plugin template
            $template = locate_template('rechat/listing-item.php');

            if (!$template) {
                $template = RCH_PLUGIN_DIR . 'templates/archive/template-part/listing-item.php';
            }

            include $template;
        }
        echo ob_get_clean();
    } else {
        echo '<p>No listings found.</p>';
    }

    wp_die(); // Exit properly after AJAX
}
add_action('wp_ajax_rch_fetch_listing', 'rch_fetch_listing_ajax');
add_action('wp_ajax_nopriv_rch_fetch_listing', 'rch_fetch_listing_ajax');
