<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Register a custom menu settings page.
 ******************************/
function rch_register_my_setting_menu_page()
{
    add_menu_page(
        __('Rechat Settings', 'rechat-plugin'),
        'Rechat',
        'manage_options',
        'rechat-setting',
        'rch_rechat_menu_page',
        RCH_PLUGIN_URL . 'assets/images/favicon.png'
    );
}
add_action('admin_menu', 'rch_register_my_setting_menu_page');

/*******************************
 * Display the custom menu page with settings form.
 ******************************/
function rch_rechat_menu_page()
{
    $auth_url = rch_get_oauth_authorization_url();
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'sync-data'; // Get the current tab from URL
    $access_token_exists = get_option('rch_rechat_access_token')
?>
    <div class="wrap wrap-for-rechat">

        <div class="rch-setting-header">
            <h1 class="rch-main-title"><?php echo esc_html(get_admin_page_title()); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=rechat-setting&tab=sync-data" class="nav-tab <?php echo $active_tab === 'sync-data' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Sync Your Data', 'rechat-plugin'); ?></a>
                <a href="?page=rechat-setting&tab=connect-to-rechat" class="nav-tab <?php echo $active_tab === 'connect-to-rechat' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Connect To Rechat', 'rechat-plugin'); ?></a>
                <a href="?page=rechat-setting&tab=lead-capture" class="nav-tab <?php echo $active_tab === 'lead-capture' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Lead Capture', 'rechat-plugin'); ?></a>
                <a href="?page=rechat-setting&tab=local-logic" class="nav-tab <?php echo $active_tab === 'lead-logic' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Local Logic Settings', 'rechat-plugin'); ?></a>
            </h2>
        </div>
        <?php settings_errors(); ?>
        <div id="tab-content">
            <div id="sync-data" class="tab-content">
                <?php if ($active_tab === 'sync-data') : ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row" style="padding-block-end: 0;"><?php esc_html_e('Update Your Data From API:', 'rechat-plugin'); ?></th>
                            <td style="padding-block-end: 0;">
                                <?php $access_token_exists ? true : false; ?>
                                <!-- Add the button to trigger the AJAX request -->
                                <button id="update_agents_data" type="button" class="button button-primary rch-button-sync" <?php if (!$access_token_exists) echo 'disabled'; ?>>
                                    <?php esc_html_e('Sync', 'rechat-plugin'); ?>
                                </button>
                                <div id="progress-container" class="rch-progress-container" style="display: none;">
                                    <div id="progress-bar"></div>
                                </div>
                                <!-- This will display the status message after AJAX request -->
                                <div id="agents_update_status" style="margin-top: 20px;"></div>

                                <?php if (!$access_token_exists): ?>
                                    <p style="display: flex;align-items: center;">
                                        <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'ph_info.png'); ?>" alt="info" style="margin-inline-end: 5px;">
                                        <?php esc_html_e('You are not yet connected to Rechat. Please connect your Rechat account to enable syncing your data.', 'rechat-plugin'); ?>
                                        <a href="?page=rechat-setting&tab=connect-to-rechat" class="nav-tab-link" style="font-weight: bold; margin-inline-start: 3px;">
                                            <?php esc_html_e('Connect To Rechat', 'rechat-plugin'); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e('User Guide:', 'rechat-plugin'); ?></th>
                            <td>
                                <p><strong><?php esc_html_e('Overview:', 'rechat-plugin'); ?></strong> <?php esc_html_e('The Rechat Plugin fetches agents, regions, and offices from Rechat and updates your site every 12 hours automatically', 'rechat-plugin'); ?></p>
                                <p><strong><?php esc_html_e('Manual Update:', 'rechat-plugin'); ?></strong> <?php esc_html_e('Click the "Sync" button to fetch the latest data on demand.', 'rechat-plugin'); ?></p>
                                <p><strong><?php esc_html_e('Custom Templates:', 'rechat-plugin'); ?></strong>
                                    <?php esc_html_e('To customize the templates, create a folder named', 'rechat-plugin'); ?> <code>rechat</code> <?php esc_html_e('in your theme or child theme directory. You can add the following files to this folder to overwrite the default templates:', 'rechat-plugin'); ?>
                                <ul>
                                    <li><code><?php esc_html_e('agents-archive-custom.php', 'rechat-plugin'); ?></code> <?php esc_html_e('to overwrite the default archive template for agents.', 'rechat-plugin'); ?></li>
                                    <li><code><?php esc_html_e('agents-single-custom.php', 'rechat-plugin'); ?></code> <?php esc_html_e('to overwrite the default single agent template.', 'rechat-plugin'); ?></li>
                                    <li><code><?php esc_html_e('regions-archive-custom.php', 'rechat-plugin'); ?></code> <?php esc_html_e('to overwrite the default archive template for regions.', 'rechat-plugin'); ?></li>
                                    <li><code><?php esc_html_e('regions-single-custom.php', 'rechat-plugin'); ?></code> <?php esc_html_e('to overwrite the default single region template.', 'rechat-plugin'); ?></li>
                                    <li><code><?php esc_html_e('offices-archive-custom.php', 'rechat-plugin'); ?></code> <?php esc_html_e('to overwrite the default archive template for offices.', 'rechat-plugin'); ?></li>
                                    <li><code><?php esc_html_e('offices-single-custom.php', 'rechat-plugin'); ?></code> <?php esc_html_e('to overwrite the default single office template.', 'rechat-plugin'); ?></li>
                                    <li><code><?php esc_html_e('listing-item.php', 'rechat-plugin'); ?></code> <?php esc_html_e('for customizing the listing box.', 'rechat-plugin'); ?></li>
                                    <li><code><?php esc_html_e('listing-single-custom.php', 'rechat-plugin'); ?></code> <?php esc_html_e('to customize the single listing template.', 'rechat-plugin'); ?></li>
                                </ul>
                                <?php esc_html_e('Remember, these files should be placed inside the', 'rechat-plugin'); ?> <code>rechat</code> <?php esc_html_e('folder in your theme or child theme, not in the root directory.', 'rechat-plugin'); ?>

                                <br /><br />
                                <strong><?php esc_html_e('Important:', 'rechat-plugin'); ?></strong> <?php esc_html_e('The best solution is to copy these files from the templates folder in the plugin and edit them as needed.', 'rechat-plugin'); ?>
                                </p>



                                <strong><?php esc_html_e('Shortcode:', 'rechat-plugin'); ?></strong>
                                <?php esc_html_e('To display listings anywhere on your site, simply use the shortcode', 'rechat-plugin'); ?> <code>[listings]</code>.
                                <?php esc_html_e('You can also filter the listings data using the following attributes:', 'rechat-plugin'); ?>
                                <ul>
                                    <li><code>minimum_price</code></li>
                                    <li><code>maximum_price</code></li>
                                    <li><code>minimum_lot_square_meters</code></li>
                                    <li><code>maximum_lot_square_meters</code></li>
                                    <li><code>minimum_bathrooms</code></li>
                                    <li><code>maximum_bathrooms</code></li>
                                    <li><code>minimum_square_meters</code></li>
                                    <li><code>maximum_square_meters</code></li>
                                    <li><code>minimum_year_built</code></li>
                                    <li><code>maximum_year_built</code></li>
                                    <li><code>minimum_bedrooms</code></li>
                                    <li><code>maximum_bedrooms</code></li>
                                    <li><code>houses_per_page</code></li>
                                </ul>
                                <?php esc_html_e('You can combine these attributes to filter the listings as needed.', 'rechat-plugin'); ?>
                                <br /><br />
                                <?php esc_html_e('For example, to display listings with a minimum price of $100,000 and a maximum of $500,000, you would use:', 'rechat-plugin'); ?>
                                <code>[listings minimum_price="100000" maximum_price="500000"]</code>
                                </p>


                            </td>
                        </tr>


                    </table>

                <?php elseif ($active_tab === 'connect-to-rechat') : ?>

                    <h2 class="rch-title-connect"><?php esc_html_e('Connect to Rechat (OAuth)', 'rechat-plugin'); ?></h2>
                    <p><?php esc_html_e('Connecting to Rechat is necessary to ensure your information is securely retrieved from the Rechat platform. You will be redirected to the Rechat platform where you can authorize this plugin to access your data safely.', 'rechat-plugin'); ?></p>
                    <div class="rch-container-connect-rechat">
                        <?php if (!$access_token_exists): ?>
                            <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary">
                                <?php esc_html_e('Connect to Rechat', 'rechat-plugin'); ?>
                            </a>
                            <p>
                                To sync your data with Rechat, please connect your Rechat account.
                            </p>
                        <?php else: ?>
                            <form id="disconnect-form" method="post" action="">
                                <input type="hidden" name="action" value="disconnect_rechat">
                                <?php wp_nonce_field('disconnect_rechat_nonce', 'disconnect_rechat_nonce_field'); ?>
                                <button type="button" class="button rch-disconnect-rechat" id="show-disconnect-modal">
                                    <?php esc_html_e('Disconnect from Rechat', 'rechat-plugin'); ?>
                                </button>
                            </form>
                            <p class="rch-connected-text">
                                <img src=<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'ph_check.svg') ?> alt="">
                                You are connected to Rechat.
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php if ($access_token_exists): ?>
                        <div class="rch-information-token">
                            <?php
                            // Get the expiration time from the option
                            $expires_in = get_option('rch_rechat_expires_in');
                            $expires_in = absint($expires_in); // Sanitize as an integer

                            // Calculate the expiry timestamp
                            $current_timestamp = time();
                            $expiry_timestamp = $current_timestamp + $expires_in;

                            // Determine if the expiration date has passed
                            $has_expired = $current_timestamp > $expiry_timestamp;

                            // Add a class if expired
                            $class = $has_expired ? 'rch-expired' : '';
                            ?>
                            <h2>
                                Token Information
                            </h2>
                            <p>
                                Here you can view your OAuth token details. These include the expiration date and permissions granted to this plugin.
                            </p>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row">
                                        Token status
                                    </th>
                                    <td class="<?php echo esc_attr($class); ?> rch-token-status-text">
                                        <img src=<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'ph_check.svg') ?> alt="" style="position: relative;top: 4px;">
                                        Your OAuth token is active and valid.
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        Token expires on
                                    </th>
                                    <td class="<?php echo esc_attr($class); ?> ">

                                        <?php
                                        echo esc_html(get_option('rch_rechat_expires_in'));
                                        ?>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        Permissions
                                    </th>
                                    <td>
                                        Agents, Regions, Offices
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endif; ?>
                    <!-- Modal HTML -->
                    <div id="disconnect-modal" class="disconnect-modal">
                        <div class="disconnect-modal-content">
                            <span class="disconnect-close">&times;</span>
                            <h2><?php esc_html_e('Are you sure you want to disconnect your Rechat account?', 'rechat-plugin'); ?></h2>
                            <p><?php esc_html_e('Disconnecting will remove your access to Rechat data and revoke your OAuth token. You will need to reconnect to retrieve your data again.', 'rechat-plugin'); ?></p>
                            <button id="confirm-disconnect" class="button button-primary"><?php esc_html_e('Yes, Disconnect', 'rechat-plugin'); ?></button>
                            <button id="cancel-disconnect" class="button"><?php esc_html_e('Cancel', 'rechat-plugin'); ?></button>
                        </div>
                    </div>
                <?php elseif ($active_tab === 'lead-capture') : ?>
                    <form method="POST" action="options.php">
                        <?php
                        settings_fields('appearance_settings');
                        do_settings_sections('appearance_setting');
                        submit_button();
                        ?>
                    </form>
                <?php elseif ($active_tab === 'local-logic') : ?>
                    <form method="POST" action="options.php">
                        <?php
                        // Register and render the settings fields for 'local_logic_settings'
                        settings_fields('local_logic_settings');
                        do_settings_sections('local_logic_settings');
                        submit_button();
                        ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php
}
