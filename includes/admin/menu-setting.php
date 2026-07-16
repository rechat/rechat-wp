<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Multisite hub-only tabs (Multisite, Agent site wizard): main site Rechat settings only.
 */
function rch_rechat_settings_is_multisite_hub_admin_context(): bool
{
    return is_multisite() && (int) get_current_blog_id() === (int) get_main_site_id();
}

/*******************************
 * Register a custom menu settings page
 ******************************/
function rch_register_my_setting_menu_page()
{
    // Validate required constants
    if (!defined('RCH_PLUGIN_URL')) {
        return;
    }

    $rechat_cap = function_exists('rch_rechat_settings_capability') ? rch_rechat_settings_capability() : 'manage_options';

    add_menu_page(
        __('Rechat Settings', 'rechat-plugin'),
        __('Rechat', 'rechat-plugin'),
        $rechat_cap,
        'rechat-setting',
        'rch_rechat_menu_page',
        RCH_PLUGIN_URL . 'assets/images/favicon.png'
    );
}
add_action('admin_menu', 'rch_register_my_setting_menu_page');

/*******************************
 * Get sanitized active tab
 ******************************/
function rch_get_active_tab()
{
    $allowed_tabs = ['sync-data', 'connect-to-rechat', 'general-settings', 'local-logic', 'agent-import'];

    if (rch_rechat_settings_is_multisite_hub_admin_context()) {
        $allowed_tabs[] = 'multisite';
        $allowed_tabs[] = 'agent-site-wizard';
    }

    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'sync-data';

    // Lead Capture tab merged into General Settings (preserve old links).
    if ($tab === 'lead-capture') {
        $tab = 'general-settings';
    }

    return in_array($tab, $allowed_tabs, true) ? $tab : 'sync-data';
}

/*******************************
 * Render tab navigation
 ******************************/
function rch_render_tab_navigation($active_tab)
{
    $tabs = [
        'sync-data' => __('Sync Your Data', 'rechat-plugin'),
        'connect-to-rechat' => __('Connect To Rechat', 'rechat-plugin'),
        'general-settings' => __('General Settings', 'rechat-plugin'),
        'local-logic' => __('Local Logic and Google Map Setting', 'rechat-plugin'),
        'agent-import'  => __('Import / Export Agents', 'rechat-plugin'),
    ];

    if (rch_rechat_settings_is_multisite_hub_admin_context()) {
        $tabs['multisite']          = __('Multisite', 'rechat-plugin');
        $tabs['agent-site-wizard'] = __('Agent site wizard', 'rechat-plugin');
    }

    echo '<h2 class="nav-tab-wrapper">';
    
    foreach ($tabs as $tab_key => $tab_label) {
        $active_class = ($active_tab === $tab_key) ? 'nav-tab-active' : '';
        printf(
            '<a href="%s" class="nav-tab %s">%s</a>',
            esc_url(admin_url('admin.php?page=rechat-setting&tab=' . $tab_key)),
            esc_attr($active_class),
            esc_html($tab_label)
        );
    }
    
    echo '</h2>';
}

/*******************************
 * Display the custom menu page with settings form
 ******************************/
function rch_rechat_menu_page()
{
    // Check user capabilities
    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rechat-plugin'));
    }

    // Validate required constants
    if (!defined('RCH_PLUGIN_ASSETS_URL_IMG')) {
        return;
    }

    $auth_url = rch_get_oauth_authorization_url();
    $active_tab = rch_get_active_tab();
    $access_token_exists  = function_exists('rch_multisite_oauth_is_effectively_connected')
        ? ((string) get_option('rch_rechat_access_token', '') !== '')
        : (bool) get_option('rch_rechat_access_token');
    $refresh_token_exists = (bool) get_option('rch_rechat_refresh_token');
    $has_oauth_credentials  = function_exists('rch_multisite_oauth_is_effectively_connected')
        ? rch_multisite_oauth_is_effectively_connected()
        : ($access_token_exists || $refresh_token_exists);
    $uses_hub_oauth         = function_exists('rch_multisite_subsite_uses_hub_oauth') && rch_multisite_subsite_uses_hub_oauth();
    $has_local_oauth        = function_exists('rch_multisite_subsite_has_local_oauth') && rch_multisite_subsite_has_local_oauth();

    ?>
    <div class="wrap wrap-for-rechat">
        <div class="rch-setting-header">
            <h1 class="rch-main-title">
                <?php if (defined('RCH_PLUGIN_URL')) : ?>
                    <img src="<?php echo esc_url(RCH_PLUGIN_URL . 'assets/images/favicon.png'); ?>" alt="" aria-hidden="true">
                <?php endif; ?>
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>
            <?php rch_render_tab_navigation($active_tab); ?>
        </div>

        <?php settings_errors(); ?>

        <div id="tab-content">
            <?php
            switch ($active_tab) {
                case 'sync-data':
                    rch_render_sync_data_tab($access_token_exists);
                    break;
                    
                case 'connect-to-rechat':
                    rch_render_connect_tab(
                        $auth_url,
                        $access_token_exists,
                        $refresh_token_exists,
                        $has_oauth_credentials,
                        $uses_hub_oauth,
                        $has_local_oauth
                    );
                    break;
                    
                case 'general-settings':
                    rch_render_general_settings_tab();
                    break;
                    
                case 'local-logic':
                    rch_render_local_logic_tab();
                    break;

                case 'agent-import':
                    if (function_exists('rch_agent_import_render_tab')) {
                        rch_agent_import_render_tab();
                    }
                    break;
                    
                case 'multisite':
                    if (is_multisite() && function_exists('rch_multisite_render_admin_tab')) {
                        rch_multisite_render_admin_tab();
                    }
                    break;

                case 'agent-site-wizard':
                    if (is_multisite() && function_exists('rch_agent_wizard_render_tab')) {
                        rch_agent_wizard_render_tab();
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}

/*******************************
 * Render Sync Data tab
 ******************************/
function rch_render_sync_data_tab($access_token_exists)
{
    if (!defined('RCH_PLUGIN_ASSETS_URL_IMG')) {
        return;
    }

    ?>
    <div class="tab-content">
        <div class="rch-tab-intro">
            <h2>
                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                <?php esc_html_e('Sync your data', 'rechat-plugin'); ?>
            </h2>
            <p><?php esc_html_e('Pull the latest agents, offices, regions, and listings from the Rechat API into this site. Safe to run any time; existing records are updated in place.', 'rechat-plugin'); ?></p>
        </div>

        <div class="rch-card">
            <div class="rch-card__head">
                <span class="dashicons dashicons-database-import" aria-hidden="true"></span>
                <h3><?php esc_html_e('Update from Rechat API', 'rechat-plugin'); ?></h3>
            </div>
            <div class="rch-card__body">
                <?php if (!$access_token_exists) : ?>
                    <div class="notice notice-warning inline" style="margin:0 0 16px;">
                        <p style="display:flex;align-items:center;gap:6px;">
                            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                            <?php esc_html_e('You are not yet connected to Rechat. Connect your account to enable syncing.', 'rechat-plugin'); ?>
                            <a
                                href="<?php echo esc_url(admin_url('admin.php?page=rechat-setting&tab=connect-to-rechat')); ?>"
                                style="font-weight:600;"
                            >
                                <?php esc_html_e('Connect to Rechat', 'rechat-plugin'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>

                <button
                    id="update_agents_data"
                    type="button"
                    class="button button-primary rch-button-sync"
                    <?php disabled(!$access_token_exists); ?>
                >
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                    <?php esc_html_e('Sync now', 'rechat-plugin'); ?>
                </button>

                <div id="progress-container" class="rch-progress-container" style="display: none;">
                    <div id="progress-bar"></div>
                </div>

                <div id="agents_update_status" style="margin-top: 16px;"></div>
            </div>
        </div>
    </div>
    <?php
}

/*******************************
 * Render Connect to Rechat tab
 ******************************/
function rch_render_connect_tab(
    $auth_url,
    $access_token_exists,
    $refresh_token_exists,
    $has_oauth_credentials,
    $uses_hub_oauth = false,
    $has_local_oauth = false
) {
    if (!defined('RCH_PLUGIN_ASSETS_URL_IMG')) {
        return;
    }

    ?>
    <div class="tab-content">
        <div class="rch-tab-intro">
            <h2>
                <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                <?php esc_html_e('Connect to Rechat (OAuth)', 'rechat-plugin'); ?>
            </h2>
            <p>
                <?php esc_html_e('Securely link this site to the Rechat platform. You will be redirected to Rechat to authorize access, then returned here.', 'rechat-plugin'); ?>
            </p>
        </div>

        <div class="rch-card">
            <div class="rch-card__head">
                <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
                <h3><?php esc_html_e('Connection', 'rechat-plugin'); ?></h3>
                <span style="margin-left:auto;">
                    <?php if ($access_token_exists) : ?>
                        <span class="rch-badge rch-badge--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e('Connected', 'rechat-plugin'); ?></span>
                    <?php elseif ($has_oauth_credentials) : ?>
                        <span class="rch-badge rch-badge--warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php esc_html_e('Token missing', 'rechat-plugin'); ?></span>
                    <?php else : ?>
                        <span class="rch-badge rch-badge--err"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span><?php esc_html_e('Not connected', 'rechat-plugin'); ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="rch-card__body">

        <?php if (isset($_GET['hub_oauth']) && $_GET['hub_oauth'] === '1') : ?>
            <div class="notice notice-info inline" style="margin:12px 0;">
                <p>
                    <?php esc_html_e('This site’s own Rechat connection was removed. The main network site’s connection still applies here until you connect this site separately.', 'rechat-plugin'); ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($uses_hub_oauth) : ?>
            <div class="notice notice-info inline" style="margin:12px 0;">
                <p>
                    <?php esc_html_e('This site is using the main network site’s Rechat connection (brand ID and tokens). Listings and API calls work without connecting here. Use “Connect to Rechat” below to sign in with a different Rechat account for this site only.', 'rechat-plugin'); ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="rch-container-connect-rechat">
            <?php if (!$has_oauth_credentials) : ?>
                <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary">
                    <?php esc_html_e('Connect to Rechat', 'rechat-plugin'); ?>
                </a>
                <p>
                    <?php esc_html_e('To sync your data with Rechat, please connect your Rechat account.', 'rechat-plugin'); ?>
                </p>
            <?php else : ?>
                <?php if ($uses_hub_oauth && ! $has_local_oauth) : ?>
                    <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary" style="margin-right:8px;">
                        <?php esc_html_e('Connect this site to Rechat', 'rechat-plugin'); ?>
                    </a>
                <?php endif; ?>
                <?php if ($has_local_oauth) : ?>
                <form id="disconnect-form" method="post" action="" style="display:inline-block;">
                    <input type="hidden" name="action" value="disconnect_rechat">
                    <?php wp_nonce_field('disconnect_rechat_nonce', 'disconnect_rechat_nonce_field'); ?>
                    <button type="button" class="button rch-disconnect-rechat" id="show-disconnect-modal">
                        <?php esc_html_e('Disconnect from Rechat', 'rechat-plugin'); ?>
                    </button>
                </form>
                <?php endif; ?>
                <?php if ($access_token_exists) : ?>
                    <p class="rch-connected-text">
                        <img 
                            src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'ph_check.svg'); ?>" 
                            alt="<?php esc_attr_e('Connected', 'rechat-plugin'); ?>"
                        >
                        <?php
                        if ($uses_hub_oauth) {
                            esc_html_e('Rechat is available via the main network site (access token from hub).', 'rechat-plugin');
                        } elseif ($has_local_oauth) {
                            esc_html_e('You are connected to Rechat on this site (access token present).', 'rechat-plugin');
                        } else {
                            esc_html_e('You are connected to Rechat (access token present).', 'rechat-plugin');
                        }
                        ?>
                    </p>
                <?php else : ?>
                    <p class="rch-connected-text" style="border-left:4px solid #d63638;padding-left:12px;">
                        <?php esc_html_e('Access token is missing, but a refresh token is stored. A new access token was requested when you opened this page. If problems persist, use “Refresh access token” below or reconnect.', 'rechat-plugin'); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

            </div><!-- .rch-card__body -->
        </div><!-- .rch-card (connection) -->

        <?php if ($has_oauth_credentials) : ?>
            <div class="rch-card">
                <div class="rch-card__head">
                    <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                    <h3><?php esc_html_e('Token & refresh status', 'rechat-plugin'); ?></h3>
                </div>
                <div class="rch-card__body">
                    <?php
                    rch_render_token_information(
                        $access_token_exists,
                        $refresh_token_exists
                    );
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <?php rch_render_oauth_alert_emails_card(); ?>

        <?php rch_render_disconnect_modal(); ?>
    </div>
    <?php
}

/*******************************
 * Render the "Token failure alerts" card (recipient emails)
 ******************************/
function rch_render_oauth_alert_emails_card()
{
    if (! function_exists('rch_oauth_get_alert_emails')) {
        return;
    }

    $emails      = rch_oauth_get_alert_emails();
    $stored       = (string) get_option(rch_oauth_alert_emails_option_name(), '');
    $textarea_val = $stored !== '' ? $stored : implode(', ', $emails);
    ?>
    <div class="rch-card">
        <div class="rch-card__head">
            <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
            <h3><?php esc_html_e('Token failure alerts', 'rechat-plugin'); ?></h3>
        </div>
        <div class="rch-card__body">
            <p>
                <?php esc_html_e('If the access token expires and the refresh token can no longer obtain a new one (so data sync stops), an email is sent to these recipients. Separate multiple emails with commas or new lines.', 'rechat-plugin'); ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="rch_save_oauth_alert_emails">
                <?php wp_nonce_field('rch_save_oauth_alert_emails', 'rch_oauth_alert_emails_nonce'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="rch_oauth_alert_emails"><?php esc_html_e('Alert recipients', 'rechat-plugin'); ?></label>
                        </th>
                        <td>
                            <textarea
                                id="rch_oauth_alert_emails"
                                name="rch_oauth_alert_emails"
                                rows="3"
                                class="large-text code"
                                placeholder="<?php echo esc_attr(implode(', ', rch_oauth_default_alert_emails())); ?>"
                            ><?php echo esc_textarea($textarea_val); ?></textarea>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: default recipient list */
                                    esc_html__('Leave empty to use the defaults: %s', 'rechat-plugin'),
                                    esc_html(implode(', ', rch_oauth_default_alert_emails()))
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save alert emails', 'rechat-plugin'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
    <?php
}

/*******************************
 * Render token information section
 *
 * @param bool $access_token_exists  Access token in options.
 * @param bool $refresh_token_exists Refresh token in options.
 ******************************/
function rch_render_token_information($access_token_exists, $refresh_token_exists)
{
    if (!defined('RCH_PLUGIN_ASSETS_URL_IMG')) {
        return;
    }

    $expiry_stored = (string) get_option('rch_rechat_expires_in', '');
    $expiry_ts      = false;
    if ($expiry_stored !== '') {
        $expiry_ts = strtotime($expiry_stored . ' UTC');
        if ($expiry_ts === false) {
            $expiry_ts = strtotime($expiry_stored);
        }
    }
    $now          = time();
    $has_expired  = ($expiry_ts !== false) && ($now > $expiry_ts);
    $status_class = $has_expired ? 'rch-expired' : '';
    $expiry_text  = ($expiry_ts !== false)
        ? get_date_from_gmt($expiry_stored, get_option('date_format') . ' ' . get_option('time_format'))
        : '—';
    if ($expiry_stored !== '' && '—' === $expiry_text) {
        $expiry_text = $expiry_stored . ' (UTC)';
    }

    $last = get_option('rch_oauth_last_refresh', array());
    if (!is_array($last)) {
        $last = array();
    }
    $log_ok  = array_key_exists('ok', $last) ? (bool) $last['ok'] : null;
    $log_msg = isset($last['message']) ? (string) $last['message'] : '';
    $log_t   = isset($last['time']) ? (string) $last['time'] : '';
    if ($log_msg === '') {
        $log_msg = __('No refresh has been recorded yet. Open this page with a stored refresh token, use “Refresh access token now”, or wait for the automatic refresh when the access token is empty or expired.', 'rechat-plugin');
    }

    $log_source = isset($last['source']) ? (string) $last['source'] : '';
    $source_labels = array(
        'wp_cron' => __('WordPress cron (background / scheduled)', 'rechat-plugin'),
        'manual'   => __('Manual “Refresh access token now” button', 'rechat-plugin'),
        'auto'     => __('Automatic when loading Rechat settings (empty access token)', 'rechat-plugin'),
        'listing_detail' => __('Single listing page (after API indicated invalid token)', 'rechat-plugin'),
        'other'    => __('Other context', 'rechat-plugin'),
        'initial'  => __('Initial OAuth connect (authorization code exchange)', 'rechat-plugin'),
    );
    $log_source_label = ($log_source !== '' && isset($source_labels[ $log_source ]))
        ? $source_labels[ $log_source ]
        : ($log_source !== '' ? $log_source : '—');

    $admin_refresh_url = wp_nonce_url(
        admin_url('admin-post.php?action=rch_manual_oauth_refresh'),
        'rch_manual_oauth_refresh',
        'rch_manual_oauth_refresh_nonce'
    );

    ?>
    <div class="rch-information-token">
        <p>
            <?php esc_html_e('OAuth access tokens expire. The plugin uses your refresh token to get a new access token (when you open this screen if the access token is empty, and on the scheduled WordPress cron job when the token is expired). The same log is updated for every attempt — including background cron — so you can see success, errors, and which trigger ran.', 'rechat-plugin'); ?>
        </p>

        <p>
            <a class="button button-secondary" href="<?php echo esc_url($admin_refresh_url); ?>">
                <?php esc_html_e('Refresh access token now', 'rechat-plugin'); ?>
            </a>
        </p>

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Access token', 'rechat-plugin'); ?></th>
                <td>
                    <?php echo $access_token_exists ? esc_html__('Present', 'rechat-plugin') : '<strong>' . esc_html__('Empty — a refresh is attempted when you load this page if a refresh token exists', 'rechat-plugin') . '</strong>'; ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Refresh token', 'rechat-plugin'); ?></th>
                <td>
                    <?php echo $refresh_token_exists ? esc_html__('Present', 'rechat-plugin') : esc_html__('Not stored', 'rechat-plugin'); ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php esc_html_e('Token status (expiry-based)', 'rechat-plugin'); ?>
                </th>
                <td class="<?php echo esc_attr($status_class); ?> rch-token-status-text">
                    <?php if (! $access_token_exists) : ?>
                        <strong><?php esc_html_e('No access token — see refresh log and use the button above.', 'rechat-plugin'); ?></strong>
                    <?php elseif ($expiry_ts === false) : ?>
                        <?php esc_html_e('Expiry not available.', 'rechat-plugin'); ?>
                    <?php elseif ($has_expired) : ?>
                        <img 
                            src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'ph_info.png'); ?>" 
                            alt=""
                            style="position: relative; top: 4px; width:16px;height:16px;"
                        >
                        <?php esc_html_e('Stored access token’s expiry is in the past. A new token should be obtained on refresh.', 'rechat-plugin'); ?>
                    <?php else : ?>
                        <img 
                            src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'ph_check.svg'); ?>" 
                            alt=""
                            style="position: relative; top: 4px;"
                        >
                        <?php esc_html_e('According to the saved expiry, the current access token has not yet expired.', 'rechat-plugin'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php esc_html_e('Access token expires (UTC stored)', 'rechat-plugin'); ?>
                </th>
                <td class="<?php echo esc_attr($status_class); ?>">
                    <?php echo esc_html($expiry_text); ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Last refresh result', 'rechat-plugin'); ?></th>
                <td>
                    <?php if (null !== $log_ok) : ?>
                        <span class="<?php echo $log_ok ? 'notice notice-success' : 'notice notice-error'; ?>" style="display:inline-block;padding:4px 8px;border-left-width:4px;">
                            <strong><?php echo $log_ok ? esc_html__('OK', 'rechat-plugin') : esc_html__('Failed', 'rechat-plugin'); ?></strong>
                        </span>
                    <?php else : ?>
                        <em><?php esc_html_e('Not yet logged', 'rechat-plugin'); ?></em>
                    <?php endif; ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('How last run was triggered', 'rechat-plugin'); ?></th>
                <td><?php echo esc_html($log_source_label); ?></td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Last refresh message', 'rechat-plugin'); ?></th>
                <td>
                    <code style="word-break:break-all;display:block;white-space:pre-wrap;"><?php echo esc_html($log_msg); ?></code>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Time (WordPress time)', 'rechat-plugin'); ?></th>
                <td><?php echo $log_t !== '' ? esc_html($log_t) : '—'; ?></td>
            </tr>
            <?php if (isset($last['http_code']) && $last['http_code'] !== null && $last['http_code'] !== '') : ?>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('HTTP code', 'rechat-plugin'); ?></th>
                <td><?php echo esc_html((string) $last['http_code']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (! empty($last['diagnostic'])) : ?>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Why refresh failed (hint)', 'rechat-plugin'); ?></th>
                <td><?php echo esc_html((string) $last['diagnostic']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (! empty($last['oauth_error'])) : ?>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('OAuth error code', 'rechat-plugin'); ?></th>
                <td><code><?php echo esc_html((string) $last['oauth_error']); ?></code></td>
            </tr>
            <?php endif; ?>
            <?php if (! empty($last['oauth_error_description'])) : ?>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('OAuth error description', 'rechat-plugin'); ?></th>
                <td><code style="word-break:break-all;display:block;white-space:pre-wrap;"><?php echo esc_html((string) $last['oauth_error_description']); ?></code></td>
            </tr>
            <?php endif; ?>
            <?php if (! empty($last['oauth_error_uri'])) : ?>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('OAuth error URI', 'rechat-plugin'); ?></th>
                <td><a href="<?php echo esc_url((string) $last['oauth_error_uri']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string) $last['oauth_error_uri']); ?></a></td>
            </tr>
            <?php endif; ?>
            <?php if (! empty($last['token_endpoint'])) : ?>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Token endpoint called', 'rechat-plugin'); ?></th>
                <td><code style="word-break:break-all;"><?php echo esc_html((string) $last['token_endpoint']); ?></code></td>
            </tr>
            <?php endif; ?>
            <?php if (! empty($last['response_preview'])) : ?>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Response body (snippet)', 'rechat-plugin'); ?></th>
                <td><code style="word-break:break-all;display:block;white-space:pre-wrap;max-height:12em;overflow:auto;"><?php echo esc_html((string) $last['response_preview']); ?></code></td>
            </tr>
            <?php endif; ?>
            <tr valign="top">
                <th scope="row">
                    <?php esc_html_e('Permissions', 'rechat-plugin'); ?>
                </th>
                <td>
                    <?php esc_html_e('Agents, Regions, Offices, Listings, Leads, and more (as granted in Rechat).', 'rechat-plugin'); ?>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

/*******************************
 * Render disconnect modal
 ******************************/
function rch_render_disconnect_modal()
{
    ?>
    <div id="disconnect-modal" class="disconnect-modal">
        <div class="disconnect-modal-content">
            <span class="disconnect-close">&times;</span>
            <h2>
                <?php esc_html_e('Are you sure you want to disconnect your Rechat account?', 'rechat-plugin'); ?>
            </h2>
            <p>
                <?php esc_html_e('Disconnecting will remove your access to Rechat data and revoke your OAuth token. You will need to reconnect to retrieve your data again.', 'rechat-plugin'); ?>
            </p>
            <button id="confirm-disconnect" class="button button-primary">
                <?php esc_html_e('Yes, Disconnect', 'rechat-plugin'); ?>
            </button>
            <button id="cancel-disconnect" class="button">
                <?php esc_html_e('Cancel', 'rechat-plugin'); ?>
            </button>
        </div>
    </div>
    <?php
}

/*******************************
 * Render General Settings tab (includes lead capture / appearance options)
 ******************************/
function rch_render_general_settings_tab()
{
    $appearance_group = defined('RCH_APPEARANCE_SETTINGS_GROUP') ? RCH_APPEARANCE_SETTINGS_GROUP : 'appearance_settings';
    ?>
    <div class="tab-content">
        <div class="rch-tab-intro">
            <h2>
                <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                <?php esc_html_e('General settings', 'rechat-plugin'); ?>
            </h2>
            <p><?php esc_html_e('Control how listings display, the default country/state boundary, and the lead-capture sources shown on listing and agent pages.', 'rechat-plugin'); ?></p>
        </div>

        <div class="rch-card">
            <div class="rch-card__head">
                <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                <h3><?php esc_html_e('Listings & location', 'rechat-plugin'); ?></h3>
            </div>
            <div class="rch-card__body">
                <form method="POST" action="options.php">
                    <?php
                    settings_fields('general_settings');
                    do_settings_sections('general_settings');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>

        <div class="rch-card">
            <div class="rch-card__head">
                <span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
                <h3><?php esc_html_e('Lead capture', 'rechat-plugin'); ?></h3>
            </div>
            <div class="rch-card__body">
                <form method="POST" action="options.php">
                    <?php
                    settings_fields($appearance_group);
                    do_settings_sections('appearance_setting');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}

/*******************************
 * Render Local Logic tab
 ******************************/
function rch_render_local_logic_tab()
{
    ?>
    <div class="tab-content">
        <div class="rch-tab-intro">
            <h2>
                <span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
                <?php esc_html_e('Local Logic & Google Maps', 'rechat-plugin'); ?>
            </h2>
            <p><?php esc_html_e('Add the API keys that power neighborhood widgets, maps, schools, and demographics across listing and neighborhood pages.', 'rechat-plugin'); ?></p>
        </div>

        <div class="rch-card">
            <div class="rch-card__head">
                <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                <h3><?php esc_html_e('API keys & features', 'rechat-plugin'); ?></h3>
            </div>
            <div class="rch-card__body">
                <form method="POST" action="options.php">
                    <?php
                    settings_fields('local_logic_settings');
                    do_settings_sections('local_logic_settings');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}
