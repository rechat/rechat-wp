<?php
/*******************************
 *Sent to oauth and get access token brand id and refreshtoken
 ******************************/
function rch_get_oauth_authorization_url() {
    $client_id = '65230631-97a6-4fb5-bf32-54aafb1e1b54';
    $redirect_uri = admin_url('admin.php?page=rechat-setting');
    //autorize endpoint
    $auth_url = 'https://app.rechat.com/oauth2/auth';
    // return $auth_url . '?response_type=code&client_id=' . $client_id;
    return $auth_url . '?response_type=code&client_id=' . $client_id . '&redirect_uri=' . urlencode($redirect_uri);

}
/*******************************
 *oauth get access token callback
 ******************************/
function rch_handle_oauth_callback() {
    if (isset($_GET['code'])) {
        $code = sanitize_text_field($_GET['code']);
        $client_id = '65230631-97a6-4fb5-bf32-54aafb1e1b54';
        $client_secret = 'secret';
        $redirect_uri = admin_url('admin.php?page=rechat-setting');
        $token_url = 'https://api.rechat.com/oauth2/token';

        $response = wp_remote_post($token_url, array(
            'body' => array(
                'grant_type' => 'authorization_code', // Correct grant type
                'code' => $code,
                'redirect_uri' => $redirect_uri,
                'client_id' => $client_id,
                'client_secret' => $client_secret
            )
        ));

        if (is_wp_error($response)) {
            var_dump($response->get_error_message());
            exit;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['access_token'])) {
            $access_token = sanitize_text_field($data['access_token']);
            $refresh_token = sanitize_text_field($data['refresh_token']);
            $brand_id = sanitize_text_field($data['brand']);
            if (is_numeric($data['expires_in'])) {
                $expires_in = absint($data['expires_in']);
            } else {
                // Handle the case where it's not numeric
                $expires_in = 0; // or another default value
            }
            $expiry_date = get_token_expiry_date($expires_in);
            update_option('rch_rechat_access_token', $access_token);
            update_option('rch_rechat_brand_id', $brand_id);
            update_option('rch_rechat_refresh_token', $refresh_token);
            update_option('rch_rechat_expires_in', $expiry_date);
            
        } else {
            error_log('Access token not found in response.');
        }
    }
}
add_action('admin_init', 'rch_handle_oauth_callback');
/*******************************
 *Function to check if the access token has expired
 ******************************/
function rch_check_token_expiry() {
    $expiry_date = get_option('rch_rechat_expires_in');
    if ($expiry_date && strtotime($expiry_date) < time()) {
        // Token has expired, schedule the refresh job
        rch_schedule_token_refresh();
    } else {
        // If the token is still valid, unschedule the job
        rch_unschedule_token_refresh();
    }
}
add_action('admin_init', 'rch_check_token_expiry');
/*******************************
 *Function to refresh the access token using the refresh token
 ******************************/
function rch_refresh_access_token() {
    // Get the current refresh token from the options
    $refresh_token = get_option('rch_rechat_refresh_token');
    $client_id = '65230631-97a6-4fb5-bf32-54aafb1e1b54';
    $client_secret = 'secret';
    $token_url = 'https://api.rechat.com/oauth2/token';

    // Make sure the refresh token exists
    if (!$refresh_token) {
        error_log('Refresh token not found.');
        return;
    }

    // Request new access token using refresh token
    $response = wp_remote_post($token_url, array(
        'body' => array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id' => $client_id,
            'client_secret' => $client_secret
        )
    ));

    if (is_wp_error($response)) {
        error_log('Error refreshing access token: ' . $response->get_error_message());
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Update the access token, refresh token and expiry date if available
    if (isset($data['access_token'])) {
        $new_access_token = sanitize_text_field($data['access_token']);
        $new_refresh_token = sanitize_text_field($data['refresh_token']);
        if (is_numeric($data['expires_in'])) {
            $expires_in = absint($data['expires_in']);
        } else {
            $expires_in = 0; // Default value in case of invalid expiry time
        }
        $expiry_date = get_token_expiry_date($expires_in);

        // Update the options with new token values
        update_option('rch_rechat_access_token', $new_access_token);
        update_option('rch_rechat_refresh_token', $new_refresh_token);
        update_option('rch_rechat_expires_in', $expiry_date);

        error_log('Access token successfully refreshed.');
    } else {
        update_option('rch_rechat_access_token', '');
        error_log('Failed to refresh access token. Response: ' . $body);
    }
}
add_action('rch_refresh_token_event', 'rch_refresh_access_token');
/*******************************
 *Function to schedule the cron job for refreshing the token
 ******************************/
function rch_schedule_token_refresh() {
    if (!wp_next_scheduled('rch_refresh_token_event')) {
        wp_schedule_event(time(), 'hourly', 'rch_refresh_token_event'); // Set to hourly
    }
}
/*******************************
 *Function to unschedule the cron job when the token is still valid
 ******************************/
function rch_unschedule_token_refresh() {
    $timestamp = wp_next_scheduled('rch_refresh_token_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'rch_refresh_token_event');
    }
}

/**
 * Schedule the cron job when plugin is activated
 */
register_activation_hook(__FILE__, 'rch_check_token_expiry');

/**
 * Unschedule the cron job when plugin is deactivated
 */
register_deactivation_hook(__FILE__, 'rch_unschedule_token_refresh');
