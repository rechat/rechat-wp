<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*******************************
 * Get OAuth2 authorization URL
 ******************************/
function rch_get_oauth_authorization_url()
{
    $redirect_uri = admin_url('admin.php?page=rechat-setting&tab=connect-to-rechat');
    
    $params = [
        'response_type' => 'code',
        'client_id' => RCH_OAUTH_CLIENT_ID,
        'redirect_uri' => $redirect_uri,
    ];
    
    return RCH_OAUTH_AUTH_URL . '?' . http_build_query($params);
}

/*******************************
 * Handle OAuth2 callback and exchange code for tokens
 ******************************/
function rch_handle_oauth_callback()
{
    // Only process if we have an authorization code
    if (!isset($_GET['code']) || !isset($_GET['page']) || $_GET['page'] !== 'rechat-setting') {
        return;
    }

    // Verify user has permission
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rechat-plugin'));
    }

    $code = sanitize_text_field(wp_unslash($_GET['code']));
    
    if (empty($code)) {
        add_settings_error(
            'rechat_oauth',
            'oauth_error',
            __('Invalid authorization code received.', 'rechat-plugin'),
            'error'
        );
        return;
    }

    // Exchange authorization code for access token
    $tokens = rch_exchange_code_for_tokens($code);
    
    if (is_wp_error($tokens)) {
        add_settings_error(
            'rechat_oauth',
            'oauth_error',
            $tokens->get_error_message(),
            'error'
        );
        return;
    }

    // Save tokens
    $saved = rch_save_oauth_tokens($tokens);
    
    if ($saved) {
        // Fetch and save primary color and logo
        rch_get_primary_color_and_logo();
        
        add_settings_error(
            'rechat_oauth',
            'oauth_success',
            __('Successfully connected to Rechat!', 'rechat-plugin'),
            'success'
        );
    }
}
add_action('admin_init', 'rch_handle_oauth_callback');

/*******************************
 * Exchange authorization code for access tokens
 ******************************/
function rch_exchange_code_for_tokens($code)
{
    $redirect_uri = admin_url('admin.php?page=rechat-setting&tab=connect-to-rechat');
    
    $body = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri,
        'client_id' => RCH_OAUTH_CLIENT_ID,
        'client_secret' => RCH_OAUTH_CLIENT_SECRET,
    ];

    $response = wp_remote_post(RCH_OAUTH_TOKEN_URL, [
        'body' => $body,
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        return new WP_Error(
            'oauth_request_failed',
            sprintf(
                /* translators: %s: error message */
                __('Failed to connect to Rechat: %s', 'rechat-plugin'),
                $response->get_error_message()
            )
        );
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($response_code !== 200) {
        $error_message = isset($data['message']) ? $data['message'] : __('Unknown error occurred', 'rechat-plugin');
        return new WP_Error('oauth_error', $error_message);
    }

    if (!isset($data['access_token'])) {
        return new WP_Error(
            'oauth_invalid_response',
            __('Access token not found in the response.', 'rechat-plugin')
        );
    }

    return $data;
}

/*******************************
 * Save OAuth tokens to database
 ******************************/
function rch_save_oauth_tokens($data)
{
    if (!is_array($data)) {
        return false;
    }

    // Validate required fields
    $required_fields = ['access_token', 'refresh_token', 'brand', 'expires_in'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            error_log('Rechat Plugin: Missing required OAuth field - ' . $field);
            return false;
        }
    }

    // Sanitize and save tokens
    $access_token = sanitize_text_field($data['access_token']);
    $refresh_token = sanitize_text_field($data['refresh_token']);
    $brand_id = sanitize_text_field($data['brand']);
    $expires_in = absint($data['expires_in']);
    $expiry_date = rch_get_token_expiry_date($expires_in);

    update_option('rch_rechat_access_token', $access_token);
    update_option('rch_rechat_brand_id', $brand_id);
    update_option('rch_rechat_refresh_token', $refresh_token);
    update_option('rch_rechat_expires_in', $expiry_date);

    error_log('Rechat Plugin: OAuth tokens saved successfully');

    return true;
}

/*******************************
 * Check if access token has expired
 ******************************/
function rch_check_token_expiry()
{
    // Don't check on every admin page load - only on settings page
    if (!isset($_GET['page']) || $_GET['page'] !== 'rechat-setting') {
        return;
    }

    $expiry_date = get_option('rch_rechat_expires_in');
    
    if (empty($expiry_date)) {
        return;
    }

    $expiry_timestamp = strtotime($expiry_date);
    
    if ($expiry_timestamp === false) {
        error_log('Rechat Plugin: Invalid expiry date format - ' . $expiry_date);
        return;
    }

    if ($expiry_timestamp < time()) {
        // Token has expired, schedule refresh
        rch_schedule_token_refresh();
    } else {
        // Token is still valid, unschedule refresh
        rch_unschedule_token_refresh();
    }
}
add_action('admin_init', 'rch_check_token_expiry');

/*******************************
 * Refresh access token using refresh token
 ******************************/
function rch_refresh_access_token()
{
    $refresh_token = get_option('rch_rechat_refresh_token');

    if (empty($refresh_token)) {
        error_log('Rechat Plugin: Refresh token not found');
        return;
    }

    $body = [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token,
        'client_id' => RCH_OAUTH_CLIENT_ID,
        'client_secret' => RCH_OAUTH_CLIENT_SECRET,
    ];

    $response = wp_remote_post(RCH_OAUTH_TOKEN_URL, [
        'body' => $body,
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        error_log('Rechat Plugin: Error refreshing access token - ' . $response->get_error_message());
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($response_code !== 200 || !isset($data['access_token'])) {
        error_log('Rechat Plugin: Failed to refresh access token. Response code: ' . $response_code);
        
        // Clear invalid token
        update_option('rch_rechat_access_token', '');
        
        return;
    }

    // Save new tokens
    $new_access_token = sanitize_text_field($data['access_token']);
    $new_refresh_token = sanitize_text_field($data['refresh_token']);
    $expires_in = isset($data['expires_in']) ? absint($data['expires_in']) : 0;
    $expiry_date = rch_get_token_expiry_date($expires_in);

    update_option('rch_rechat_access_token', $new_access_token);
    update_option('rch_rechat_refresh_token', $new_refresh_token);
    update_option('rch_rechat_expires_in', $expiry_date);

    error_log('Rechat Plugin: Access token successfully refreshed');
}
add_action(RCH_TOKEN_REFRESH_HOOK, 'rch_refresh_access_token');

/*******************************
 * Schedule token refresh cron job
 ******************************/
function rch_schedule_token_refresh()
{
    if (!wp_next_scheduled(RCH_TOKEN_REFRESH_HOOK)) {
        $scheduled = wp_schedule_event(time(), 'hourly', RCH_TOKEN_REFRESH_HOOK);
        
        if ($scheduled === false) {
            error_log('Rechat Plugin: Failed to schedule token refresh');
        }
    }
}

/*******************************
 * Unschedule token refresh cron job
 ******************************/
function rch_unschedule_token_refresh()
{
    $timestamp = wp_next_scheduled(RCH_TOKEN_REFRESH_HOOK);
    
    if ($timestamp) {
        wp_unschedule_event($timestamp, RCH_TOKEN_REFRESH_HOOK);
    }
    
    // Clear all scheduled events for this hook
    wp_clear_scheduled_hook(RCH_TOKEN_REFRESH_HOOK);
}

/*******************************
 * Clear OAuth tokens and disconnect
 ******************************/
function rch_disconnect_oauth()
{
    // Verify nonce
    if (!isset($_POST['disconnect_rechat_nonce_field']) || 
        !wp_verify_nonce($_POST['disconnect_rechat_nonce_field'], 'disconnect_rechat_nonce')) {
        wp_die(esc_html__('Security check failed.', 'rechat-plugin'));
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'rechat-plugin'));
    }

    // Clear all OAuth related options
    delete_option('rch_rechat_access_token');
    delete_option('rch_rechat_brand_id');
    delete_option('rch_rechat_refresh_token');
    delete_option('rch_rechat_expires_in');

    // Unschedule token refresh
    rch_unschedule_token_refresh();

    error_log('Rechat Plugin: OAuth disconnected successfully');

    // Redirect back to settings page
    wp_safe_redirect(admin_url('admin.php?page=rechat-setting&tab=connect-to-rechat&disconnected=1'));
    exit;
}

// Handle disconnect request
if (isset($_POST['action']) && $_POST['action'] === 'disconnect_rechat') {
    add_action('admin_init', 'rch_disconnect_oauth');
}

/*******************************
 * Register activation/deactivation hooks
 * Note: These should ideally be called from the main plugin file
 ******************************/
if (defined('RCH_PLUGIN_DIR')) {
    register_activation_hook(RCH_PLUGIN_DIR . 'index.php', 'rch_check_token_expiry');
    register_deactivation_hook(RCH_PLUGIN_DIR . 'index.php', 'rch_unschedule_token_refresh');
}
