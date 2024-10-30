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
        __('Rechat Settings', 'rch_rechat_plugin'),
        'Rechat',
        'manage_options',
        'rechat-setting',
        'rch_rechat_menu_page',
        RCH_PLUGIN_URL . 'assets/images/favicon.png'
    );
}
add_action('admin_menu', 'rch_register_my_setting_menu_page');

/*******************************
 * Define the setting fields
 ******************************/
function rch_appearance_setting()
{
    // Existing primary color setting
    $args = array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => '#2271b1',
    );
    register_setting('appearance_settings', '_rch_primary_color', $args);

    // New posts per page setting
    $posts_per_page_args = array(
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 10,
    );
    register_setting('appearance_settings', '_rch_posts_per_page', $posts_per_page_args);

    add_settings_section(
        'rch_theme_appearance_setting',
        __('Appearance Section', 'rch_rechat_plugin'),
        null,
        'appearance_setting'
    );

    // add_settings_field(
    //     'rch_primary_color',
    //     __('Primary Color', 'rch_rechat_plugin'),
    //     'rch_render_color_picker',
    //     'appearance_setting',
    //     'rch_theme_appearance_setting'
    // );

    // Add the field for posts per page
    add_settings_field(
        'rch_posts_per_page',
        __('Posts Per Page', 'rch_rechat_plugin'),
        'rch_render_posts_per_page_field',
        'appearance_setting',
        'rch_theme_appearance_setting'
    );
}
add_action('admin_init', 'rch_appearance_setting');

/*******************************
 * Render the Posts Per Page input field.
 ******************************/
function rch_render_posts_per_page_field()
{
    $posts_per_page = get_option('_rch_posts_per_page', 12); // Default to 10
    echo '<input type="number" id="rch_posts_per_page" name="_rch_posts_per_page" value="' . esc_attr($posts_per_page) . '" min="1" />';
}

/*******************************
 * function for Color Picker
 ******************************/
// function rch_render_color_picker()
// {
//     $color = get_option('_rch_primary_color', '#2271b1'); // Default to white
//     echo '<input type="text" id="rch_primary_color" name="_rch_primary_color" value="' . esc_attr($color) . '" class="my-color-field" data-default-color="#2271b1" />';
// }

/*******************************
 * AJAX handler for updating all data
 ******************************/
function rch_update_all_data()
{
    // Verify nonce for security
    check_ajax_referer('rch_ajax_nonce', 'nonce');

    // Call the function to fetch and update data
    $result = rch_update_agents_offices_regions_data();
    // Return the result
    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_rch_update_all_data', 'rch_update_all_data');

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
            <h1 class="rch-main-title"><?php esc_html_e(get_admin_page_title(), 'rch_rechat_plugin'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=rechat-setting&tab=sync-data" class="nav-tab <?php echo $active_tab === 'sync-data' ? 'nav-tab-active' : ''; ?>"><?php _e('Sync Your Data', 'rch_rechat_plugin'); ?></a>
                <a href="?page=rechat-setting&tab=connect-to-rechat" class="nav-tab <?php echo $active_tab === 'connect-to-rechat' ? 'nav-tab-active' : ''; ?>"><?php _e('Connect To Rechat', 'rch_rechat_plugin'); ?></a>
                <a href="?page=rechat-setting&tab=appearance" class="nav-tab <?php echo $active_tab === 'appearance' ? 'nav-tab-active' : ''; ?>"><?php _e('Appearance', 'rch_rechat_plugin'); ?></a>
            </h2>
        </div>
        <?php settings_errors(); ?>
        <div id="tab-content">
            <div id="sync-data" class="tab-content">
                <?php if ($active_tab === 'sync-data') : ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row" style="padding-block-end: 0;"><?php _e('Update Your Data From API:', 'rch_rechat_plugin'); ?></th>
                            <td style="padding-block-end: 0;">
                                <?php $access_token_exists ? true : false; ?>
                                <!-- Add the button to trigger the AJAX request -->
                                <button id="update_agents_data" type="button" class="button button-primary rch-button-sync" <?php if (!$access_token_exists) echo 'disabled'; ?>>
                                    <?php _e('Sync', 'rch_rechat_plugin'); ?>
                                </button>
                                <div id="progress-container" class="rch-progress-container" style="display: none;">
                                    <div id="progress-bar"></div>
                                </div>
                                <!-- This will display the status message after AJAX request -->
                                <div id="agents_update_status" style="margin-top: 20px;"></div>

                                <?php if (!$access_token_exists): ?>
                        <p style="display: flex;align-items: center;">
                            <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG . 'ph_info.png' ?>" alt="info" style="margin-inline-end: 5px;">
                            <?php _e('You are not yet connected to Rechat. Please connect your Rechat account to enable syncing your data.', 'rch_rechat_plugin'); ?>
                            <a href="?page=rechat-setting&tab=connect-to-rechat" class="nav-tab-link" style="font-weight: bold; margin-inline-start: 3px;">
                                <?php _e('Connect To Rechat', 'rch_rechat_plugin'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('User Guide:', 'rch_rechat_plugin'); ?></th>
                            <td>
                                <p><strong><?php _e('Overview:', 'rch_rechat_plugin'); ?></strong> <?php _e('The Rechat Plugin fetches agents, regions, and offices from Rechat and updates your site every 12 hours automatically', 'rch_rechat_plugin'); ?></p>
                                <p><strong><?php _e('Manual Update:', 'rch_rechat_plugin'); ?></strong> <?php _e('Click the "Sync" button to fetch the latest data on demand.', 'rch_rechat_plugin'); ?></p>
                                <p><strong><?php _e('Custom Templates:', 'rch_rechat_plugin'); ?></strong>
                                    <?php _e('To customize the templates, create a folder named', 'rch_rechat_plugin'); ?> <code>rechat</code> <?php _e('in your theme or child theme directory. You can add the following files to this folder to overwrite the default templates:', 'rch_rechat_plugin'); ?>
                                <ul>
                                    <li><code><?php _e('agents-archive-custom.php', 'rch_rechat_plugin'); ?></code> <?php _e('to overwrite the default archive template for agents.', 'rch_rechat_plugin'); ?></li>
                                    <li><code><?php _e('agents-single-custom.php', 'rch_rechat_plugin'); ?></code> <?php _e('to overwrite the default single agent template.', 'rch_rechat_plugin'); ?></li>
                                    <li><code><?php _e('regions-archive-custom.php', 'rch_rechat_plugin'); ?></code> <?php _e('to overwrite the default archive template for regions.', 'rch_rechat_plugin'); ?></li>
                                    <li><code><?php _e('regions-single-custom.php', 'rch_rechat_plugin'); ?></code> <?php _e('to overwrite the default single region template.', 'rch_rechat_plugin'); ?></li>
                                    <li><code><?php _e('offices-archive-custom.php', 'rch_rechat_plugin'); ?></code> <?php _e('to overwrite the default archive template for offices.', 'rch_rechat_plugin'); ?></li>
                                    <li><code><?php _e('offices-single-custom.php', 'rch_rechat_plugin'); ?></code> <?php _e('to overwrite the default single office template.', 'rch_rechat_plugin'); ?></li>
                                    <li><code><?php _e('listing-item.php', 'rch_rechat_plugin'); ?></code> <?php _e('for customizing the listing box.', 'rch_rechat_plugin'); ?></li>
                                    <li><code><?php _e('listing-single-custom.php', 'rch_rechat_plugin'); ?></code> <?php _e('to customize the single listing template.', 'rch_rechat_plugin'); ?></li>
                                </ul>
                                <?php _e('Remember, these files should be placed inside the', 'rch_rechat_plugin'); ?> <code>rechat</code> <?php _e('folder in your theme or child theme, not in the root directory.', 'rch_rechat_plugin'); ?>

                                <br /><br />
                                <strong><?php _e('Important:', 'rch_rechat_plugin'); ?></strong> <?php _e('The best solution is to copy these files from the templates folder in the plugin and edit them as needed.', 'rch_rechat_plugin'); ?>
                                </p>



                                <strong><?php _e('Shortcode:', 'rch_rechat_plugin'); ?></strong>
                                <?php _e('To display listings anywhere on your site, simply use the shortcode', 'rch_rechat_plugin'); ?> <code>[listings]</code>.
                                <?php _e('You can also filter the listings data using the following attributes:', 'rch_rechat_plugin'); ?>
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
                                <?php _e('You can combine these attributes to filter the listings as needed.', 'rch_rechat_plugin'); ?>
                                <br /><br />
                                <?php _e('For example, to display listings with a minimum price of $100,000 and a maximum of $500,000, you would use:', 'rch_rechat_plugin'); ?>
                                <code>[listings minimum_price="100000" maximum_price="500000"]</code>
                                </p>


                            </td>
                        </tr>


                    </table>

                <?php elseif ($active_tab === 'connect-to-rechat') : ?>

                    <h2 class="rch-title-connect"><?php _e('Connect to Rechat (OAuth)', 'rch_rechat_plugin'); ?></h2>
                    <p><?php _e('Connecting to Rechat is necessary to ensure your information is securely retrieved from the Rechat platform. You will be redirected to the Rechat platform where you can authorize this plugin to access your data safely.', 'rch_rechat_plugin'); ?></p>
                    <div class="rch-container-connect-rechat">
                        <?php if (!$access_token_exists): ?>
                            <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary">
                                <?php _e('Connect to Rechat', 'rch_rechat_plugin'); ?>
                            </a>
                            <p>
                                To sync your data with Rechat, please connect your Rechat account.
                            </p>
                        <?php else: ?>
                            <form id="disconnect-form" method="post" action="">
                                <input type="hidden" name="action" value="disconnect_rechat">
                                <?php wp_nonce_field('disconnect_rechat_nonce', 'disconnect_rechat_nonce_field'); ?>
                                <button type="button" class="button rch-disconnect-rechat" id="show-disconnect-modal">
                                    <?php _e('Disconnect from Rechat', 'rch_rechat_plugin'); ?>
                                </button>
                            </form>
                            <p class="rch-connected-text">
                                <img src=<?php echo RCH_PLUGIN_ASSETS_URL_IMG . 'ph_check.svg' ?> alt="">
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
                                        <img src=<?php echo RCH_PLUGIN_ASSETS_URL_IMG . 'ph_check.svg' ?> alt="" style="position: relative;top: 4px;">
                                        Your OAuth token is active and valid.
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        Token expires on
                                    </th>
                                    <td class="<?php echo esc_attr($class); ?> ">

                                        <?php
                                        echo get_option('rch_rechat_expires_in')
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
                            <h2><?php _e('Are you sure you want to disconnect your Rechat account?', 'rch_rechat_plugin'); ?></h2>
                            <p><?php _e('Disconnecting will remove your access to Rechat data and revoke your OAuth token. You will need to reconnect to retrieve your data again.', 'rch_rechat_plugin'); ?></p>
                            <button id="confirm-disconnect" class="button button-primary"><?php _e('Yes, Disconnect', 'rch_rechat_plugin'); ?></button>
                            <button id="cancel-disconnect" class="button"><?php _e('Cancel', 'rch_rechat_plugin'); ?></button>
                        </div>
                    </div>
                <?php elseif ($active_tab === 'appearance') : ?>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('appearance_settings');
                        do_settings_sections('appearance_setting');
                        submit_button();
                        ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php
}
