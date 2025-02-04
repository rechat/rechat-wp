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
    $response = wp_remote_post('https://api.rechat.com/valerts?order_by[]=-price', [
        'method' => 'POST',
        'headers' => $headers,
        'body' => wp_json_encode($requestBody),
        'timeout' => 20, // Set the timeout to 15 seconds

    ]);
    var_dump($response);

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
    $brand = !empty($filters['brand']) ? $filters['brand'] : get_option('rch_rechat_brand_id');

    // Prepare the request body
    $requestBody = array_merge($filters);
    // Prepare the headers, including the custom X-RECHAT-BRAND header
    $headers = [
        'Content-Type' => 'application/json',
        'X-RECHAT-BRAND' =>$brand,
    ];
    // Make the API request
    $response = wp_remote_post('https://api.rechat.com/valerts/count', [
        'method' => 'POST',
        'headers' => $headers,
        'body' => wp_json_encode($requestBody),
        'timeout' => 30, // Set the timeout to 15 seconds
    ]);
    // Check for errors and return the response
    if (is_wp_error($response)) {
        return 'Error: ' . $response->get_error_message();
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}
function rch_fetch_listing_ajax()
{
    // Start output buffering to catch any unexpected output
    ob_start();

    // Get request parameters
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $listingPerPage = isset($_POST['listing_per_page']) ? intval($_POST['listing_per_page']) : 50;
    $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';
    
    // Capture filters for debugging
    $filters = rch_get_filters($_POST);



    // Fetch listing data
    $listingData = rch_fetch_listing($filters, $page, $listingPerPage);
    $totalListingData = rch_fetch_total_listing_count($filters);
    $totalListing = $totalListingData['info']['total'] ?? 0;
    // Debugging: Capture var_dump output
    ob_start();
    $debugOutput = ob_get_clean(); // Store the var_dump output in a variable
    // Prepare the response
    $response = [
        'total' => $totalListing,
        'listings' => [],
        'listingPerPage' => $listingPerPage,
        'debug' => $debugOutput, // Add debug output to the response
    ];

    if (!empty($listingData['data'])) {
        foreach ($listingData['data'] as $listing) {
            if ($template) {
                $templateFile = locate_template("rechat/shortcodes/{$template}.php");
            } else {
                $templateFile = locate_template('rechat/listing-item.php');
            }

            if (!$templateFile) {
                $templateFile = RCH_PLUGIN_DIR . 'templates/archive/template-part/listing-item.php';
            }

            ob_start();
            include $templateFile;
            $listingContent = ob_get_clean();

            // Add latitude and longitude to the response
            $response['listings'][] = [
                'content' => $listingContent,
                'lat' => $listing['location']['latitude'], // Assuming lat is in the API response
                'lng' => $listing['location']['longitude'], // Assuming lng is in the API response
            ];
        }
    } else {
        $response['message'] = 'No listings found.';
    }

    // Ensure no unexpected output corrupts JSON
    $unexpectedOutput = ob_get_clean();
    if (!empty($unexpectedOutput)) {
        error_log($unexpectedOutput);
        $response['debug'] .= $unexpectedOutput; // Append unexpected output to debug info
    }

    // Send the response as JSON
    wp_send_json_success($response);

    // Properly terminate the AJAX request
    wp_die();
}
add_action('wp_ajax_rch_fetch_listing', 'rch_fetch_listing_ajax');
add_action('wp_ajax_nopriv_rch_fetch_listing', 'rch_fetch_listing_ajax');


