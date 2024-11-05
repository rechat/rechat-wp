<?php

if (! defined('ABSPATH')) {
    exit();
}

/*******************************
 * Fetch listing listings with filters.
 ******************************/
function rch_fetch_listing($filters, $page, $listingPerPage)
{
    // $brandId = get_option('rch_rechat_brand_id');
    $offset = ($page - 1) * $listingPerPage;
    // Extract the brand value from the filters
    $brand = isset($filters['brand']) ? $filters['brand'] : get_option('rch_rechat_brand_id');
    $requestBody = array_merge([
        // 'brand' => $brandId,
        'limit' => $listingPerPage,
        'offset' => $offset,
    ], $filters);
    $headers = [
        'Content-Type' => 'application/json',
        'X-RECHAT-BRAND' => $brand,
    ];
    $response = wp_remote_post('https://api.rechat.com/valerts', [
        'method' => 'POST',
        'headers' => $headers,
        'body' => json_encode($requestBody),
        'timeout' => 20, // Set the timeout to 15 seconds

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
    // Extract the brand value from the filters
    $brand = isset($filters['brand']) ? $filters['brand'] : '';

    // Prepare the request body
    $requestBody = array_merge($filters);

    // Prepare the headers, including the custom X-RECHAT-BRAND header
    $headers = [
        'Content-Type' => 'application/json',
        'X-RECHAT-BRAND' => $brand,
    ];

    // Make the API request
    $response = wp_remote_post('https://api.rechat.com/valerts/count', [
        'method' => 'POST',
        'headers' => $headers,
        'body' => json_encode($requestBody),
        'timeout' => 20, // Set the timeout to 15 seconds
    ]);
    // Check for errors and return the response
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
    $listingPerPage = isset($_GET['listing_per_page']) ? intval($_GET['listing_per_page']) : 50;

    // Get filters from the AJAX request
    $filters = rch_get_filters($_GET);
    // Fetch listing
    $listingData = rch_fetch_listing($filters, $page, $listingPerPage);
    if (!empty($listingData['data'])) {
        ob_start();
        foreach ($listingData['data'] as $listing) {
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
