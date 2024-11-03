<?php
if (! defined('ABSPATH')) {
    exit();
}

// Extract the listing ID from the URL using PHP
$house_id = isset($_GET['listing_id']) ? sanitize_text_field($_GET['listing_id']) : null;

if ($house_id) {
    // Define the brand ID and API endpoint
    $api_url = 'https://api.rechat.com/listings/' . $house_id;
    $access_token = get_option('rch_rechat_access_token');
    $response = rch_api_request($api_url, $access_token);

    // Check if the API request was successful
    if (isset($response['data']['http']) && $response['data']['http'] === 400) {
        // Check for validation errors
        if (isset($response['data']['code']) && $response['data']['code'] === 'Validation') {
            echo '<p>Invalid listing ID provided. Please check the ID and try again.</p>';
        } else {
            echo '<p>No listing details found for this ID.</p>';
        }
    } elseif (!$response['success']) {
        // Return error if API request fails for other reasons
        echo '<p>An error occurred while fetching the listing details. Please try again later.</p>';
    } else {
        // Proceed to display listing details if the response was successful
        $data = $response['data']['data'];
        if ($data) {
            $listing_detail = $data;

            // Check if the template exists in the child theme or theme's /rechat folder
            $theme_template = locate_template('rechat/listing-single-custom.php');

            if ($theme_template) {
                // If the template is found in the theme/child theme, load it
                include $theme_template;
            } else {
                // Fall back to the plugin's template
                include RCH_PLUGIN_DIR . 'templates/single/listing-single-custom.php';
            }
        } else {
            echo '<p>No listing details found for this ID.</p>';
        }
    }
} else {
    echo '<p>listing ID is missing.</p>';
}
