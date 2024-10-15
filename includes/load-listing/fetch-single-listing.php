<?php
if (! defined('ABSPATH')) {
    exit();
}

// Extract the house ID from the URL using PHP
$house_id = isset($_GET['house_id']) ? sanitize_text_field($_GET['house_id']) : null;

if ($house_id) {
    // Define the brand ID and API endpoint
    $api_url = 'https://api.rechat.com/listings/' . $house_id;
    $access_token = get_option('rch_rechat_access_token');
    $response = api_request($api_url, $access_token);

    // Check if the API request was successful
    if (isset($response['data']['http']) && $response['data']['http'] === 400) {
        // Check for validation errors
        if (isset($response['data']['code']) && $response['data']['code'] === 'Validation') {
            echo '<p>Invalid House ID provided. Please check the ID and try again.</p>';
        } else {
            echo '<p>No house details found for this ID.</p>';
        }
    } elseif (!$response['success']) {
        // Return error if API request fails for other reasons
        echo '<p>An error occurred while fetching the house details. Please try again later.</p>';
    } else {
        // Proceed to display house details if the response was successful
        $data = $response['data']['data'];
        if ($data) {
            $house_detail = $data;

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
            echo '<p>No house details found for this ID.</p>';
        }
    }
} else {
    echo '<p>House ID is missing.</p>';
}
