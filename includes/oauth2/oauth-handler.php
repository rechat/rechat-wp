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
    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
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
    rch_oauth_save_refresh_log(
        true,
        __('Initial authorization: access and refresh tokens saved.', 'rechat-plugin'),
        null,
        'initial'
    );

    return true;
}

/*******************************
 * Log OAuth refresh attempts for the settings screen
 *
 * @param bool        $ok        Whether refresh succeeded.
 * @param string      $message   Human-readable result.
 * @param int|null    $http_code      HTTP response code if applicable.
 * @param string      $source         How refresh ran: wp_cron, manual, auto, other, initial.
 ******************************/
function rch_oauth_save_refresh_log($ok, $message, $http_code = null, $source = '')
{
    $entry = array(
        'ok'         => (bool) $ok,
        'message'    => (string) $message,
        'http_code'  => $http_code,
        'source'     => (string) $source,
        'time'       => current_time('mysql'),
        'time_gmt'   => gmdate('Y-m-d H:i:s'),
    );
    update_option('rch_oauth_last_refresh', $entry, false);
    $message_text = (string) $message;
    $src = $source !== '' ? '[' . $source . '] ' : '';
    if ($ok) {
        error_log('Rechat Plugin: OAuth token refresh OK ' . $src . '— ' . $message_text);
    } else {
        error_log('Rechat Plugin: OAuth token refresh FAILED ' . $src . '— ' . $message_text);
    }
}

/**
 * If access token is missing but a refresh token exists, get a new access token.
 * Safe to run on the Rechat settings page load; returns true on success, WP_Error when recovery failed.
 *
 * @return true|WP_Error
 */
function rch_ensure_valid_access_token()
{
    $access = (string) get_option('rch_rechat_access_token', '');
    if ($access !== '') {
        return true;
    }
    $refresh = (string) get_option('rch_rechat_refresh_token', '');
    if ($refresh === '') {
        rch_oauth_save_refresh_log(
            false,
            __('Access token is empty and no refresh token is stored. Reconnect to Rechat.', 'rechat-plugin'),
            null,
            'auto'
        );
        return new WP_Error('no_tokens', __('No access or refresh token.', 'rechat-plugin'));
    }

    return rch_refresh_access_token('auto');
}

/**
 * @param array|null $data Response JSON or null
 */
function rch_oauth_error_message_from_response($data, $default)
{
    if (!is_array($data)) {
        return $default;
    }
    if (!empty($data['message']) && is_string($data['message'])) {
        return $data['message'];
    }
    if (!empty($data['error_description']) && is_string($data['error_description'])) {
        return $data['error_description'];
    }
    if (!empty($data['error']) && is_string($data['error'])) {
        return $data['error'];
    }

    return $default;
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

    if (function_exists('rch_ensure_valid_access_token')) {
        rch_ensure_valid_access_token();
    }

    $expiry_date = get_option('rch_rechat_expires_in');

    if (empty($expiry_date)) {
        return;
    }

    $expiry_timestamp = strtotime($expiry_date . ' UTC');
    if ($expiry_timestamp === false) {
        $expiry_timestamp = strtotime($expiry_date);
    }

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
 *
 * @param string|null $source Optional: manual, auto, or omit to detect (wp_cron in cron context).
 * @return true|WP_Error
 ******************************/
function rch_refresh_access_token($source = null)
{
    if ($source === null || $source === '') {
        $source = wp_doing_cron() ? 'wp_cron' : 'other';
    }

    $refresh_token = get_option('rch_rechat_refresh_token');

    if (empty($refresh_token)) {
        $msg = __('No refresh token stored. Connect to Rechat again.', 'rechat-plugin');
        rch_oauth_save_refresh_log(false, $msg, null, $source);
        error_log('Rechat Plugin: Refresh token not found');
        return new WP_Error('no_refresh_token', $msg);
    }

    $body = array(
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh_token,
        'client_id'     => RCH_OAUTH_CLIENT_ID,
        'client_secret' => RCH_OAUTH_CLIENT_SECRET,
    );

    $response = wp_remote_post(
        RCH_OAUTH_TOKEN_URL,
        array(
            'body'    => $body,
            'timeout' => 15,
        )
    );

    if (is_wp_error($response)) {
        $err = $response->get_error_message();
        rch_oauth_save_refresh_log(
            false,
            sprintf(
                /* translators: %s: error message from HTTP client */
                __('Request failed: %s', 'rechat-plugin'),
                $err
            ),
            null,
            $source
        );
        error_log('Rechat Plugin: Error refreshing access token - ' . $err);
        return new WP_Error('oauth_request_failed', $err);
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $raw_body = wp_remote_retrieve_body($response);
    $data     = json_decode($raw_body, true);

    if ($response_code !== 200 || !is_array($data) || !isset($data['access_token'])) {
        $detail = rch_oauth_error_message_from_response(
            is_array($data) ? $data : null,
            $raw_body ? $raw_body : __('Unknown error', 'rechat-plugin')
        );
        $message = sprintf(
            /* translators: 1: HTTP code, 2: server message */
            __('Token endpoint returned an error (HTTP %1$s): %2$s', 'rechat-plugin'),
            (string) $response_code,
            $detail
        );
        rch_oauth_save_refresh_log(false, $message, $response_code, $source);
        error_log('Rechat Plugin: Failed to refresh access token. Response code: ' . $response_code . ' Body: ' . $raw_body);
        // Do not clear refresh token here; only access may be wrong — user can reconnect
        update_option('rch_rechat_access_token', '');
        return new WP_Error('oauth_refresh_failed', $message);
    }

    $new_access_token = sanitize_text_field($data['access_token']);
    $expires_in       = isset($data['expires_in']) ? absint($data['expires_in']) : 0;
    $expiry_date      = rch_get_token_expiry_date($expires_in);

    update_option('rch_rechat_access_token', $new_access_token);
    update_option('rch_rechat_expires_in', $expiry_date);

    if (!empty($data['refresh_token']) && is_string($data['refresh_token'])) {
        update_option('rch_rechat_refresh_token', sanitize_text_field($data['refresh_token']));
    }

    rch_oauth_save_refresh_log(
        true,
        __('Access token was refreshed successfully.', 'rechat-plugin'),
        $response_code,
        $source
    );
    error_log('Rechat Plugin: Access token successfully refreshed');
    rch_unschedule_token_refresh();
    return true;
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
    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'rechat-plugin'));
    }

    // Clear all OAuth related options
    delete_option('rch_rechat_access_token');
    delete_option('rch_rechat_brand_id');
    delete_option('rch_rechat_refresh_token');
    delete_option('rch_rechat_expires_in');

    // Unschedule token refresh
    rch_unschedule_token_refresh();

    delete_option('rch_oauth_last_refresh');

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

/**
 * Run refresh from the Connect to Rechat tab (button).
 */
function rch_handle_manual_oauth_refresh()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'rechat-plugin'));
    }
    check_admin_referer('rch_manual_oauth_refresh', 'rch_manual_oauth_refresh_nonce');

    $result = rch_refresh_access_token('manual');
    if (is_wp_error($result)) {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'           => 'rechat-setting',
                    'tab'            => 'connect-to-rechat',
                    'refresh_result' => '0',
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }
    wp_safe_redirect(
        add_query_arg(
            array(
                'page'           => 'rechat-setting',
                'tab'            => 'connect-to-rechat',
                'refresh_result' => '1',
            ),
            admin_url('admin.php')
        )
    );
    exit;
}
add_action('admin_post_rch_manual_oauth_refresh', 'rch_handle_manual_oauth_refresh');

/**
 * Show admin notice after manual refresh redirect.
 */
function rch_maybe_show_oauth_manual_refresh_notice()
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    if (!isset($_GET['page'], $_GET['tab']) || $_GET['page'] !== 'rechat-setting' || $_GET['tab'] !== 'connect-to-rechat') {
        return;
    }
    if (!isset($_GET['refresh_result'])) {
        return;
    }
    if ($_GET['refresh_result'] === '1') {
        add_settings_error(
            'rechat_oauth',
            'refresh_ok',
            __('Access token refresh completed successfully.', 'rechat-plugin'),
            'success'
        );
    } elseif ($_GET['refresh_result'] === '0') {
        $log = get_option('rch_oauth_last_refresh', array());
        $msg = (is_array($log) && !empty($log['message']))
            ? $log['message']
            : __('Refresh failed. See “Last refresh message” below for details.', 'rechat-plugin');
        add_settings_error('rechat_oauth', 'refresh_err', esc_html($msg), 'error');
    }
}
add_action('admin_init', 'rch_maybe_show_oauth_manual_refresh_notice', 20);

/**
 * Dismiss the "last refresh failed" admin bar notice for the current log entry.
 */
function rch_oauth_handle_dismiss_fail_notice()
{
    if (!is_admin() || !isset($_GET['page'], $_GET['rch_oauth_dismiss_fail']) || $_GET['page'] !== 'rechat-setting') {
        return;
    }
    if ((string) $_GET['rch_oauth_dismiss_fail'] !== '1') {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'rch_dismiss_oauth_fail')) {
        return;
    }
    $log = get_option('rch_oauth_last_refresh', array());
    if (is_array($log) && !empty($log['time'])) {
        update_user_meta(get_current_user_id(), 'rch_oauth_dismissed_fail_log_time', (string) $log['time']);
    }
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'connect-to-rechat';
    wp_safe_redirect(admin_url('admin.php?page=rechat-setting&tab=' . $tab));
    exit;
}
add_action('admin_init', 'rch_oauth_handle_dismiss_fail_notice', 1);

/**
 * If the last token refresh failed (including when WordPress cron runs in the background),
 * show a dismissible error notice on the Rechat settings screen. The "Token & refresh status"
 * table is always updated from the same log; this surfaces failures without opening Connect.
 */
function rch_oauth_admin_notice_last_refresh_failed()
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    if (!isset($_GET['page']) || $_GET['page'] !== 'rechat-setting') {
        return;
    }
    $log = get_option('rch_oauth_last_refresh', array());
    if (!is_array($log) || !array_key_exists('ok', $log) || $log['ok'] === true) {
        return;
    }
    $log_time = isset($log['time']) ? (string) $log['time'] : '';
    $dismissed = (string) get_user_meta(get_current_user_id(), 'rch_oauth_dismissed_fail_log_time', true);
    if ($log_time !== '' && $dismissed === $log_time) {
        return;
    }
    $msg = !empty($log['message']) ? (string) $log['message'] : __('The last access token refresh failed.', 'rechat-plugin');
    $source = isset($log['source']) ? (string) $log['source'] : '';
    if ($source === 'wp_cron') {
        $context = ' ' . __('(ran as a background WordPress cron job; details are stored below.)', 'rechat-plugin');
    } else {
        $context = $source !== '' && $source !== 'other' ? ' [' . $source . ']' : '';
    }
    $dismiss = wp_nonce_url(
        add_query_arg(
            array(
                'page'                 => 'rechat-setting',
                'tab'                  => isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'connect-to-rechat',
                'rch_oauth_dismiss_fail' => '1',
            ),
            admin_url('admin.php')
        ),
        'rch_dismiss_oauth_fail',
        '_wpnonce'
    );
    echo '<div class="notice notice-error is-dismissible" style="position:relative;"><p><strong>' .
        esc_html__('Rechat OAuth token refresh failed', 'rechat-plugin') . '</strong>' . esc_html($context) . '</p>' .
        '<p>' . esc_html($msg) . '</p>' .
        '<p><a href="' . esc_url($dismiss) . '">' . esc_html__('Hide this message for this failure', 'rechat-plugin') . '</a> · ' .
        '<a href="' . esc_url(admin_url('admin.php?page=rechat-setting&tab=connect-to-rechat')) . '">' .
        esc_html__('Open Connect to Rechat', 'rechat-plugin') . '</a></p></div>';
}
add_action('admin_notices', 'rch_oauth_admin_notice_last_refresh_failed');
