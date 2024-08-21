<?php

/**
 * Register a custom menu settings page.
 */
function rch_register_my_setting_menu_page()
{
    add_menu_page(
        __('Rechat Settings', 'rch_agents'),
        'Rechat',
        'manage_options',
        'rechat-setting',
        'rch_rechat_menu_page',
        RCH_PLUGIN_URL . 'assets/images/favicon.png'
    );
}
add_action('admin_menu', 'rch_register_my_setting_menu_page');

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
        'Appearance Section',
        null,
        'appearance_setting'
    );

    add_settings_field(
        'rch_primary_color',
        __('Primary Color', 'rch_agents'),
        'rch_render_color_picker',
        'appearance_setting',
        'rch_theme_appearance_setting'
    );

    // Add the field for posts per page
    add_settings_field(
        'rch_posts_per_page',
        __('Posts Per Page', 'rch_agents'),
        'rch_render_posts_per_page_field',
        'appearance_setting',
        'rch_theme_appearance_setting'
    );
}
add_action('admin_init', 'rch_appearance_setting');

/**
 * Render the Posts Per Page input field.
 */
function rch_render_posts_per_page_field()
{
    $posts_per_page = get_option('_rch_posts_per_page', 12); // Default to 10
    echo '<input type="number" id="rch_posts_per_page" name="_rch_posts_per_page" value="' . esc_attr($posts_per_page) . '" min="1" />';
}

/**
 * function for Color Picker
 */
function rch_render_color_picker()
{
    $color = get_option('_rch_primary_color', '#2271b1'); // Default to white
    echo '<input type="text" id="rch_primary_color" name="_rch_primary_color" value="' . esc_attr($color) . '" class="my-color-field" data-default-color="#2271b1" />';
}
/**
 *  AJAX handler that will be called when the button is clicked
 */
function rch_update_agents_data()
{
    // Verify nonce for security
    check_ajax_referer('rch_ajax_nonce', 'nonce');

    // Call the function to update agents data
    get_agents_data();

    // Return a success message
    wp_send_json_success(__('Agents data updated successfully.', 'rch_agents'));
}
add_action('wp_ajax_rch_update_agents_data', 'rch_update_agents_data');
/**
 * Display the custom menu page with settings form.
 */
function rch_rechat_menu_page()
{
?>
    <div class="wrap">

        <?php settings_errors(); ?>
        <h1><?php esc_html_e(get_admin_page_title(), 'rch_agents'); ?></h1>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">User Guide:</th>
                <td>
                    <p><strong>Overview:</strong> The Rechat Agents Plugin fetches agent data from Rechat and updates your site every 12 hours automatically.</p>
                    <p><strong>Manual Update:</strong> Click the "Update Agents Data" button to fetch the latest data on demand.</p>
                    <p><strong>Custom Templates:</strong>
                        If you create a file named <code>agents-archive-custom.php</code> in your theme's root directory, it will overwrite the default archive template. Similarly, if you create a file named <code>agents-single-custom.php</code> in the root directory, it will overwrite the default single agent template.
                    </p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">Update Your Data From API:</th>
                <td>
                    <!-- Add the button to trigger the AJAX request -->
                    <button id="update_agents_data" type="button" class="button button-primary">
                        <?php _e('Update Agents Data', 'rch_agents'); ?>
                    </button>
                </td>
            </tr>

        </table>


        <!-- This will display the status message after AJAX request -->
        <div id="agents_update_status" style="margin-top: 20px;"></div>
        <form method="post" action="options.php">
            <?php
            settings_fields('appearance_settings');
            do_settings_sections('appearance_setting');
            submit_button();
            ?>
        </form>

    </div>
<?php
}
/**
 * change the Preview Image in admin Page
 */
function rch_add_custom_js()
{
?>
    <script type="text/javascript">
        function updatePreview(selectElement, type) {
            var selectedValue = selectElement.value;
            var previewImageId = 'rch_' + type + '_image_preview';
            var imageFile = selectedValue;
            var imageUrl = '<?php echo RCH_PLUGIN_URL; ?>assets/images/' + imageFile + '.png';
            document.getElementById(previewImageId).src = imageUrl;
        }
    </script>
<?php
}
add_action('admin_footer', 'rch_add_custom_js');
?>