<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
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

    add_menu_page(
        __('Rechat Settings', 'rechat-plugin'),
        __('Rechat', 'rechat-plugin'),
        'manage_options',
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
    $allowed_tabs = ['sync-data', 'connect-to-rechat', 'lead-capture', 'general-settings', 'local-logic'];
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'sync-data';
    
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
        'lead-capture' => __('Lead Capture', 'rechat-plugin'),
        'general-settings' => __('General Settings', 'rechat-plugin'),
        'local-logic' => __('Local Logic Settings', 'rechat-plugin'),
    ];

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
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rechat-plugin'));
    }

    // Validate required constants
    if (!defined('RCH_PLUGIN_ASSETS_URL_IMG')) {
        return;
    }

    $auth_url = rch_get_oauth_authorization_url();
    $active_tab = rch_get_active_tab();
    $access_token_exists = (bool) get_option('rch_rechat_access_token');

    ?>
    <div class="wrap wrap-for-rechat">
        <div class="rch-setting-header">
            <h1 class="rch-main-title"><?php echo esc_html(get_admin_page_title()); ?></h1>
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
                    rch_render_connect_tab($auth_url, $access_token_exists);
                    break;
                    
                case 'lead-capture':
                    rch_render_lead_capture_tab();
                    break;
                    
                case 'general-settings':
                    rch_render_general_settings_tab();
                    break;
                    
                case 'local-logic':
                    rch_render_local_logic_tab();
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
        <table class="form-table">
            <tr valign="top">
                <th scope="row" style="padding-block-end: 0;">
                    <?php esc_html_e('Update Your Data From API:', 'rechat-plugin'); ?>
                </th>
                <td style="padding-block-end: 0;">
                    <button 
                        id="update_agents_data" 
                        type="button" 
                        class="button button-primary rch-button-sync"
                        <?php disabled(!$access_token_exists); ?>
                    >
                        <?php esc_html_e('Sync', 'rechat-plugin'); ?>
                    </button>

                    <div id="progress-container" class="rch-progress-container" style="display: none;">
                        <div id="progress-bar"></div>
                    </div>

                    <div id="agents_update_status" style="margin-top: 20px;"></div>

                    <?php if (!$access_token_exists) : ?>
                        <p style="display: flex; align-items: center;">
                            <img 
                                src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'ph_info.png'); ?>" 
                                alt="<?php esc_attr_e('Info', 'rechat-plugin'); ?>" 
                                style="margin-inline-end: 5px;"
                            >
                            <?php esc_html_e('You are not yet connected to Rechat. Please connect your Rechat account to enable syncing your data.', 'rechat-plugin'); ?>
                            <a 
                                href="<?php echo esc_url(admin_url('admin.php?page=rechat-setting&tab=connect-to-rechat')); ?>" 
                                class="nav-tab-link" 
                                style="font-weight: bold; margin-inline-start: 3px;"
                            >
                                <?php esc_html_e('Connect To Rechat', 'rechat-plugin'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

/*******************************
 * Render Connect to Rechat tab
 ******************************/
function rch_render_connect_tab($auth_url, $access_token_exists)
{
    if (!defined('RCH_PLUGIN_ASSETS_URL_IMG')) {
        return;
    }

    ?>
    <div class="tab-content">
        <h2 class="rch-title-connect">
            <?php esc_html_e('Connect to Rechat (OAuth)', 'rechat-plugin'); ?>
        </h2>
        <p>
            <?php esc_html_e('Connecting to Rechat is necessary to ensure your information is securely retrieved from the Rechat platform. You will be redirected to the Rechat platform where you can authorize this plugin to access your data safely.', 'rechat-plugin'); ?>
        </p>

        <div class="rch-container-connect-rechat">
            <?php if (!$access_token_exists) : ?>
                <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary">
                    <?php esc_html_e('Connect to Rechat', 'rechat-plugin'); ?>
                </a>
                <p>
                    <?php esc_html_e('To sync your data with Rechat, please connect your Rechat account.', 'rechat-plugin'); ?>
                </p>
            <?php else : ?>
                <form id="disconnect-form" method="post" action="">
                    <input type="hidden" name="action" value="disconnect_rechat">
                    <?php wp_nonce_field('disconnect_rechat_nonce', 'disconnect_rechat_nonce_field'); ?>
                    <button type="button" class="button rch-disconnect-rechat" id="show-disconnect-modal">
                        <?php esc_html_e('Disconnect from Rechat', 'rechat-plugin'); ?>
                    </button>
                </form>
                <p class="rch-connected-text">
                    <img 
                        src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'ph_check.svg'); ?>" 
                        alt="<?php esc_attr_e('Connected', 'rechat-plugin'); ?>"
                    >
                    <?php esc_html_e('You are connected to Rechat.', 'rechat-plugin'); ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($access_token_exists) : ?>
            <?php rch_render_token_information(); ?>
        <?php endif; ?>

        <?php rch_render_disconnect_modal(); ?>
    </div>
    <?php
}

/*******************************
 * Render token information section
 ******************************/
function rch_render_token_information()
{
    if (!defined('RCH_PLUGIN_ASSETS_URL_IMG')) {
        return;
    }

    $expires_in = absint(get_option('rch_rechat_expires_in', 0));
    $current_timestamp = time();
    $expiry_timestamp = $current_timestamp + $expires_in;
    $has_expired = $current_timestamp > $expiry_timestamp;
    $status_class = $has_expired ? 'rch-expired' : '';

    ?>
    <div class="rch-information-token">
        <h2><?php esc_html_e('Token Information', 'rechat-plugin'); ?></h2>
        <p>
            <?php esc_html_e('Here you can view your OAuth token details. These include the expiration date and permissions granted to this plugin.', 'rechat-plugin'); ?>
        </p>

        <table class="form-table">
            <tr valign="top">
                <th scope="row">
                    <?php esc_html_e('Token status', 'rechat-plugin'); ?>
                </th>
                <td class="<?php echo esc_attr($status_class); ?> rch-token-status-text">
                    <img 
                        src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'ph_check.svg'); ?>" 
                        alt="<?php esc_attr_e('Active', 'rechat-plugin'); ?>" 
                        style="position: relative; top: 4px;"
                    >
                    <?php esc_html_e('Your OAuth token is active and valid.', 'rechat-plugin'); ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php esc_html_e('Token expires on', 'rechat-plugin'); ?>
                </th>
                <td class="<?php echo esc_attr($status_class); ?>">
                    <?php echo esc_html($expires_in); ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <?php esc_html_e('Permissions', 'rechat-plugin'); ?>
                </th>
                <td>
                    <?php esc_html_e('Agents, Regions, Offices, Listings, Leads, and more.', 'rechat-plugin'); ?>
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
 * Render Lead Capture tab
 ******************************/
function rch_render_lead_capture_tab()
{
    ?>
    <div class="tab-content">
        <form method="POST" action="options.php">
            <?php
            settings_fields('appearance_settings');
            do_settings_sections('appearance_setting');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/*******************************
 * Render General Settings tab
 ******************************/
function rch_render_general_settings_tab()
{
    ?>
    <div class="tab-content">
        <form method="POST" action="options.php">
            <?php
            settings_fields('general_settings');
            do_settings_sections('general_settings');
            submit_button();
            ?>
        </form>
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
        <form method="POST" action="options.php">
            <?php
            settings_fields('local_logic_settings');
            do_settings_sections('local_logic_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
