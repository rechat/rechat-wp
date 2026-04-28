<?php
if (! defined('ABSPATH')) {
    exit();
}

// Prefer rewrite-captured listing id; fall back to URL parsing (legacy safety)
$house_id = get_query_var('listing_id');
if (empty($house_id)) {
    $url_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $path_parts = explode('/', $url_path);
    // The listing ID should be the last segment of the path
    $house_id = end($path_parts);
}
$house_id = sanitize_text_field($house_id);
if ($house_id) {
    // Define the brand ID and API endpoint
    $api_url = 'https://api.rechat.com/listings/' . $house_id . '?associations[]=listing.mls_info';
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

            // Redirect legacy URL (/listing-detail/{street}/{id}/) to new URL (/listing-detail/{city}/{street}/{id}/)
            $rch_city_segment = get_query_var('listing_city');
            if (!is_admin() && empty($rch_city_segment)) {
                $rch_city_name = null;
                if (isset($listing_detail['property']['address']['city'])) {
                    $rch_city_name = $listing_detail['property']['address']['city'];
                } elseif (isset($listing_detail['address']['city'])) {
                    $rch_city_name = $listing_detail['address']['city'];
                } elseif (isset($listing_detail['formatted']['full_address']['text'])) {
                    // Fallback parse: "Street, City, ST ZIP"
                    $parts = array_map('trim', explode(',', $listing_detail['formatted']['full_address']['text']));
                    if (count($parts) >= 2) {
                        $rch_city_name = $parts[1];
                    }
                }

                if (is_array($rch_city_name)) {
                    $rch_city_name = $rch_city_name['name'] ?? $rch_city_name['title'] ?? $rch_city_name['text'] ?? null;
                }

                $rch_street_text = null;
                if (isset($listing_detail['formatted']['street_address']['text'])) {
                    $rch_street_text = $listing_detail['formatted']['street_address']['text'];
                } elseif (isset($listing_detail['formatted']['full_address']['text'])) {
                    $rch_street_text = $listing_detail['formatted']['full_address']['text'];
                }

                $rch_city_slug = !empty($rch_city_name) ? sanitize_title((string) $rch_city_name) : null;
                $rch_street_slug = !empty($rch_street_text) ? sanitize_title((string) $rch_street_text) : null;

                if (!empty($rch_city_slug) && !empty($rch_street_slug)) {
                    wp_safe_redirect(home_url('/listing-detail/' . $rch_city_slug . '/' . $rch_street_slug . '/' . $house_id . '/'), 301);
                    exit;
                }
            }

            // Check if the listing is deleted
            if (!empty($listing_detail['deleted_at'])) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part(404);
                exit;
            }

            // Expose listing payload for wp_head JSON-LD (RealEstateListing) before get_header().
            $GLOBALS['rch_listing_detail_for_jsonld'] = $listing_detail;
            $req_path                                  = isset($_SERVER['REQUEST_URI']) ? wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
            $GLOBALS['rch_listing_canonical_url']     = is_string($req_path) && $req_path !== ''
                ? home_url(user_trailingslashit($req_path))
                : home_url('/listing-detail/');

            // Check if the template exists in the child theme or theme's /rechat folder
            $theme_template = locate_template('rechat/listing-single-custom.php');

            if ($theme_template) {
                // If the template is found in the theme/child theme, load it
                include $theme_template;
            } else {
                // Fall back to the plugin's template
                include RCH_PLUGIN_DIR . 'templates/single/listing-single-custom.php';
            }

            unset($GLOBALS['rch_listing_detail_for_jsonld'], $GLOBALS['rch_listing_canonical_url']);
        } else {
            echo '<p>No listing details found for this ID.</p>';
        }
    }
} else {
    echo '<p>listing ID is missing.</p>';
}