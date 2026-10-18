<?php
if (! defined('ABSPATH')) {
    exit();
}

if (! function_exists('rch_render_listing_unavailable')) {
    /**
     * Render the "listing unavailable" page inside the active theme and stop.
     *
     * Sends a real HTTP status (404 for missing/removed/invalid listings, 503 for
     * temporary API errors so search engines retry instead of dropping the URL).
     * Theme override: rechat/listing-not-found.php
     *
     * @param string $reason not_found | removed | invalid | missing | error
     */
    function rch_render_listing_unavailable($reason)
    {
        $notices = [
            'not_found' => [
                __('This listing is no longer available', 'rechat-plugin'),
                __('The property you are looking for may have been sold, taken off the market, or the link may be out of date.', 'rechat-plugin'),
            ],
            'removed'   => [
                __('This listing has been removed', 'rechat-plugin'),
                __('The property you are looking for is no longer on the market.', 'rechat-plugin'),
            ],
            'invalid'   => [
                __('Listing not found', 'rechat-plugin'),
                __('This listing link is not valid. Please check the address and try again.', 'rechat-plugin'),
            ],
            'missing'   => [
                __('Listing not found', 'rechat-plugin'),
                __('No listing was specified in this link.', 'rechat-plugin'),
            ],
            'error'     => [
                __('We could not load this listing', 'rechat-plugin'),
                __('Something went wrong while loading the property details. Please try again in a moment.', 'rechat-plugin'),
            ],
        ];
        $reason = isset($notices[$reason]) ? $reason : 'not_found';

        $rch_notice_reason  = $reason;
        $rch_notice_title   = (string) apply_filters('rch_listing_unavailable_title', $notices[$reason][0], $reason);
        $rch_notice_message = (string) apply_filters('rch_listing_unavailable_message', $notices[$reason][1], $reason);

        // First existing page among common listings/search slugs; filterable per site.
        $rch_notice_search = '';
        foreach (['listings', 'search', 'properties', 'search-listings'] as $slug) {
            $page = get_page_by_path($slug);
            if ($page instanceof WP_Post && $page->post_status === 'publish') {
                $rch_notice_search = get_permalink($page);
                break;
            }
        }
        $rch_notice_search = (string) apply_filters('rch_listing_unavailable_search_url', $rch_notice_search, $reason);

        if ($reason === 'error') {
            status_header(503);
            header('Retry-After: 300');
            nocache_headers();
        } else {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
        }

        add_filter('document_title_parts', static function ($title) use ($rch_notice_title) {
            $title['title'] = $rch_notice_title;
            return $title;
        }, 99);

        $template = locate_template('rechat/listing-not-found.php');
        include $template ? $template : RCH_PLUGIN_DIR . 'templates/single/listing-not-found.php';
        exit;
    }
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
    $api_url = rtrim(RECHAT_API_BASE_URL, '/') . '/listings/' . rawurlencode((string) $house_id) . '?associations[]=listing.mls_info';
    $access_token = get_option('rch_rechat_access_token');
    $response = rch_api_request($api_url, $access_token);

    // Invalid/expired access token: refresh (same as “Refresh access token now” / cron) and retry once.
    if (function_exists('rch_single_listing_response_suggests_invalid_token')
        && function_exists('rch_refresh_access_token')
        && rch_single_listing_response_suggests_invalid_token($response)
    ) {
        $refresh_result = rch_refresh_access_token('listing_detail');
        if (! is_wp_error($refresh_result)) {
            $access_token = get_option('rch_rechat_access_token');
            $response      = rch_api_request($api_url, $access_token);
        }
    }

    // Check if the API request was successful
    if (isset($response['data']['http']) && $response['data']['http'] === 400) {
        // Check for validation errors
        if (isset($response['data']['code']) && $response['data']['code'] === 'Validation') {
            rch_render_listing_unavailable('invalid');
        } else {
            rch_render_listing_unavailable('not_found');
        }
    } elseif (!$response['success']) {
        // Return error if API request fails for other reasons
        rch_render_listing_unavailable('error');
    } else {
        // Proceed to display listing details if the response was successful
        $data = $response['data']['data'] ?? null;
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
                rch_render_listing_unavailable('removed');
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
            rch_render_listing_unavailable('not_found');
        }
    }
} else {
    rch_render_listing_unavailable('missing');
}