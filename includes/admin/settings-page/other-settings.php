<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}



/*******************************
 * Sanitization callbacks
 ******************************/
/**
 * Store ISO country codes uppercase (matches Rechat boundaries API).
 */
function rch_sanitize_boundary_country_option($input)
{
    $v = sanitize_text_field($input);
    if ($v === '') {
        return '';
    }
    return strtoupper($v);
}

function rch_sanitize_lead_channel($input)
{
    return sanitize_text_field($input);
}

function rch_sanitize_tags($input)
{
    if (!is_string($input)) {
        return '[]';
    }

    $tags = json_decode($input, true);
    if (!is_array($tags)) {
        return '[]';
    }

    $sanitized_tags = array_map('sanitize_text_field', $tags);
    return wp_json_encode($sanitized_tags);
}    // Sanitize function for JSON array (used for tags)
function rch_sanitize_json_array($input)
{
    // Decode the JSON string
    $decoded = json_decode($input, true);

    // If it's not valid JSON or not an array, return empty JSON array
    if (!is_array($decoded)) {
        return '[]';
    }

    // Sanitize each item and re-encode
    $sanitized = array_map('sanitize_text_field', $decoded);
    return wp_json_encode($sanitized);
}

/*******************************
 * Define the setting fields
 ******************************/
function rch_appearance_setting()
{
    // Primary color setting
    register_setting(RCH_APPEARANCE_SETTINGS_GROUP, '_rch_primary_color', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => '#2271b1',
    ]);

    // Posts per page setting
    register_setting(RCH_APPEARANCE_SETTINGS_GROUP, '_rch_posts_per_page', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 10,
    ]);

    // Lead channel for listing page
    register_setting(RCH_APPEARANCE_SETTINGS_GROUP, 'rch_lead_channels', [
        'type' => 'string',
        'sanitize_callback' => 'rch_sanitize_lead_channel',
        'default' => '',
    ]);

    // Lead channel for agent page
    register_setting(RCH_APPEARANCE_SETTINGS_GROUP, 'rch_agents_lead_channels', [
        'type' => 'string',
        'sanitize_callback' => 'rch_sanitize_lead_channel',
        'default' => '',
    ]);

    // Tags for listing page
    register_setting(RCH_APPEARANCE_SETTINGS_GROUP, 'rch_selected_tags', [
        'type' => 'string',
        'sanitize_callback' => 'rch_sanitize_json_array',
        'default' => '[]',
    ]);

    // Tags for agent page
    register_setting(RCH_APPEARANCE_SETTINGS_GROUP, 'rch_agents_selected_tags', [
        'type' => 'string',
        'sanitize_callback' => 'rch_sanitize_json_array',
        'default' => '[]',
    ]);

    // Section for listing page lead capture
    add_settings_section(
        'rch_theme_appearance_setting',
        __('Listing Page Lead Capture', 'rechat-plugin'),
        null,
        'appearance_setting'
    );

    // Lead source field for listing page
    add_settings_field(
        'rch_lead_channels',
        __('Lead Source', 'rechat-plugin'),
        'rch_render_lead_capture_field',
        'appearance_setting',
        'rch_theme_appearance_setting',
        [
            'option_name' => 'rch_lead_channels',
            'field_type' => 'lead_channel',
        ]
    );

    // Tags field for listing page
    add_settings_field(
        'rch_select_tag',
        __('Tags', 'rechat-plugin'),
        'rch_render_lead_capture_field',
        'appearance_setting',
        'rch_theme_appearance_setting',
        [
            'option_name' => 'rch_selected_tags',
            'field_type' => 'tags',
            'select_id' => 'tag-select',
            'container_id' => 'selected-tags-container',
            'hidden_input_id' => 'rch_selected_tags_input',
        ]
    );

    // Section for agent page lead capture
    add_settings_section(
        'rch_agents_section',
        __('Agent Page Lead Capture', 'rechat-plugin'),
        null,
        'appearance_setting'
    );

    // Lead source field for agent page
    add_settings_field(
        'rch_agents_lead_channels',
        __('Lead Source', 'rechat-plugin'),
        'rch_render_lead_capture_field',
        'appearance_setting',
        'rch_agents_section',
        [
            'option_name' => 'rch_agents_lead_channels',
            'field_type' => 'lead_channel',
        ]
    );

    // Tags field for agent page
    add_settings_field(
        'rch_agents_select_tag',
        __('Tags', 'rechat-plugin'),
        'rch_render_lead_capture_field',
        'appearance_setting',
        'rch_agents_section',
        [
            'option_name' => 'rch_agents_selected_tags',
            'field_type' => 'tags',
            'select_id' => 'agent-tag-select',
            'container_id' => 'agent-selected-tags-container',
            'hidden_input_id' => 'rch_agents_selected_tags_input',
        ]
    );
}
add_action('admin_init', 'rch_appearance_setting');

/*******************************
 * Define the General settings fields
 ******************************/
function rch_general_setting()
{
    // Register listing display mode setting
    register_setting('general_settings', 'rch_listing_display_mode', array(
        'type' => 'string',
        'default' => 'combined',
        'sanitize_callback' => 'sanitize_text_field'
    ));

    register_setting('general_settings', 'rch_selected_country', array(
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'rch_sanitize_boundary_country_option',
    ));

    register_setting('general_settings', 'rch_selected_state', array(
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field',
    ));

    // Section for agent page listing display
    add_settings_section(
        'rch_agents_listing_display_section',
        __('Agent Page Listing Display', 'rechat-plugin'),
        'rch_render_agents_listing_display_description',
        'general_settings'
    );

    // Add radio buttons for listing display mode
    add_settings_field(
        'rch_listing_display_mode',
        __('Listing Display Mode', 'rechat-plugin'),
        'rch_render_listing_display_mode_field',
        'general_settings',
        'rch_agents_listing_display_section'
    );

    add_settings_section(
        'rch_country_state_section',
        __('Country & State', 'rechat-plugin'),
        'rch_render_country_state_section_description',
        'general_settings'
    );

    add_settings_field(
        'rch_selected_country',
        __('Country', 'rechat-plugin'),
        'rch_render_general_country_field',
        'general_settings',
        'rch_country_state_section'
    );

    add_settings_field(
        'rch_selected_state',
        __('State / Province', 'rechat-plugin'),
        'rch_render_general_state_field',
        'general_settings',
        'rch_country_state_section'
    );
}

add_action('admin_init', 'rch_general_setting');

/**
 * Section blurb for country / state (General Settings).
 */
function rch_render_country_state_section_description()
{
    echo '<p>' . esc_html__('Choose Any to search all countries, or pick a country and then a state or province. States load from Rechat after you pick a country.', 'rechat-plugin') . '</p>';
}

/**
 * Country: hidden field (submitted on save) + display select filled via AJAX (fast page load + cache).
 */
function rch_render_general_country_field()
{
    $selected = (string) get_option('rch_selected_country', '');

    printf(
        '<input type="hidden" name="rch_selected_country" id="rch_selected_country" value="%s" autocomplete="off" />',
        esc_attr($selected)
    );
    echo '<p class="description rch-boundary-country-status" style="margin:0 0 6px;">' . esc_html__('Loading countries…', 'rechat-plugin') . '</p>';
    echo '<select id="rch_selected_country_display" class="regular-text" autocomplete="off" aria-busy="true">';
    printf('<option value="">%s</option>', esc_html__('Any', 'rechat-plugin'));
    echo '</select>';
    echo '<span class="rch-boundary-country-loading" style="display:inline-block;margin-inline-start:8px;vertical-align:middle;" role="status" aria-live="polite">';
    echo '<span class="spinner is-active" style="float:none;margin:0 4px 0 0;visibility:visible;"></span>';
    echo '<span class="rch-boundary-country-loading-text"></span>';
    echo '</span>';
}

/**
 * State: hidden field (always submitted) + display select (no name — synced by JS; avoids disabled fields missing from POST).
 */
function rch_render_general_state_field()
{
    $country        = (string) get_option('rch_selected_country', '');
    $selected_state = (string) get_option('rch_selected_state', '');

    printf(
        '<input type="hidden" name="rch_selected_state" id="rch_selected_state" value="%s" autocomplete="off" />',
        esc_attr($selected_state)
    );

    $disabled_attr = ($country === '') ? ' disabled="disabled"' : '';

    printf('<select id="rch_selected_state_display" class="regular-text" autocomplete="off"%s>', $disabled_attr);
    printf(
        '<option value="">%s</option>',
        esc_html__('Select a state / province', 'rechat-plugin')
    );
    if ($country !== '' && $selected_state !== '') {
        printf(
            '<option value="%1$s" selected="selected">%2$s</option>',
            esc_attr($selected_state),
            esc_html($selected_state)
        );
    }
    echo '</select>';
    echo '<span class="rch-boundary-state-loading" style="display:none;margin-inline-start:8px;vertical-align:middle;" role="status" aria-live="polite">';
    echo '<span class="spinner is-active" style="float:none;margin:0 4px 0 0;visibility:visible;"></span>';
    echo '<span class="rch-boundary-state-loading-text"></span>';
    echo '</span>';
    echo '<p class="rch-boundary-state-error description" style="display:none;margin-top:8px;color:#b32d2d;"></p>';
}

/*******************************
 * Render agents listing display description
 ******************************/
function rch_render_agents_listing_display_description()
{
    echo '<p>' . __('Choose how listings should be displayed on agent pages.', 'rechat-plugin') . '</p>';
}

/*******************************
 * Render listing display mode field
 ******************************/
function rch_render_listing_display_mode_field()
{
    $display_mode = get_option('rch_listing_display_mode', 'combined');
    ?>
    <fieldset>
        <label>
            <input type="radio" name="rch_listing_display_mode" value="combined" <?php checked('combined', $display_mode); ?> />
            <strong><?php _e('Combined (Default)', 'rechat-plugin'); ?></strong>
            <p class="description" style="margin-left: 25px;"><?php _e('Show all listings (Active + Sold) together in a single section', 'rechat-plugin'); ?></p>
        </label>
        <br><br>
        <label>
            <input type="radio" name="rch_listing_display_mode" value="separate" <?php checked('separate', $display_mode); ?> />
            <strong><?php _e('Separate Sections', 'rechat-plugin'); ?></strong>
            <p class="description" style="margin-left: 25px;"><?php _e('Show Active and Sold listings in two separate sections with independent pagination', 'rechat-plugin'); ?></p>
        </label>
        <br><br>
        <label>
            <input type="radio" name="rch_listing_display_mode" value="active-only" <?php checked('active-only', $display_mode); ?> />
            <strong><?php _e('Active Only', 'rechat-plugin'); ?></strong>
            <p class="description" style="margin-left: 25px;"><?php _e('Show only Active listings (Active, Incoming, Coming Soon, Active Under Contract, Active Option Contract, Active Contingent, Active Kick Out, Pending)', 'rechat-plugin'); ?></p>
        </label>
        <br><br>
        <label>
            <input type="radio" name="rch_listing_display_mode" value="sold-only" <?php checked('sold-only', $display_mode); ?> />
            <strong><?php _e('Sold Only', 'rechat-plugin'); ?></strong>
            <p class="description" style="margin-left: 25px;"><?php _e('Show only Sold listings (Sold, Leased)', 'rechat-plugin'); ?></p>
        </label>
    </fieldset>
    <?php
}

/*******************************
 * Helper function to fetch API data
 ******************************/
function rch_fetch_lead_channels()
{
    $brand_id = get_option('rch_rechat_brand_id');
    if (empty($brand_id)) {
        return ['success' => false, 'message' => __('Brand ID not configured.', 'rechat-plugin')];
    }

    $api_url = rtrim(RECHAT_API_BASE_URL, '/') . '/brands/' . rawurlencode((string) $brand_id) . '/leads/channels';
    $access_token = get_option('rch_rechat_access_token');

    return rch_api_request($api_url, $access_token);
}

function rch_fetch_tags()
{
    $api_url = rtrim(RECHAT_API_BASE_URL, '/') . '/contacts/tags';
    $access_token = get_option('rch_rechat_access_token');
    $brand_id = get_option('rch_rechat_brand_id');

    if (empty($brand_id)) {
        return ['success' => false, 'message' => __('Brand ID not configured.', 'rechat-plugin')];
    }

    return rch_api_request($api_url, $access_token, $brand_id);
}

/*******************************
 * Unified field render callback
 ******************************/
function rch_render_lead_capture_field($args)
{
    $option_name = isset($args['option_name']) ? $args['option_name'] : '';
    $field_type = isset($args['field_type']) ? $args['field_type'] : '';

    if (empty($option_name) || empty($field_type)) {
        return;
    }

    if ($field_type === 'lead_channel') {
        rch_render_lead_channel_select($option_name);
    } elseif ($field_type === 'tags') {
        $select_id = isset($args['select_id']) ? $args['select_id'] : 'tag-select';
        $container_id = isset($args['container_id']) ? $args['container_id'] : 'selected-tags-container';
        $hidden_input_id = isset($args['hidden_input_id']) ? $args['hidden_input_id'] : 'rch_selected_tags_input';

        rch_render_tags_select($option_name, $select_id, $container_id, $hidden_input_id);
    }
}

/*******************************
 * Render lead channel select field
 ******************************/
function rch_render_lead_channel_select($option_name)
{
    $response = rch_fetch_lead_channels();

    if (!$response['success']) {
        echo '<p class="description error">' . esc_html($response['message']) . '</p>';
        return;
    }

    $data = $response['data'];
    if (empty($data['data'])) {
        echo '<p class="description">' . esc_html__('No channels available', 'rechat-plugin') . '</p>';
        return;
    }

    $selected_channel = get_option($option_name, '');
    $field_id = esc_attr($option_name);

    printf('<select id="%s" name="%s">', $field_id, $field_id);
    printf('<option value="">%s</option>', esc_html__('Select Lead Channel', 'rechat-plugin'));

    foreach ($data['data'] as $channel) {
        $id = esc_attr($channel['id']);
        $title = !empty($channel['title']) ? esc_html($channel['title']) : esc_html__('Unnamed', 'rechat-plugin');

        printf(
            '<option value="%s"%s>%s</option>',
            $id,
            selected($id, $selected_channel, false),
            $title
        );
    }

    echo '</select>';
}

/*******************************
 * Render tags select field with chips
 ******************************/
function rch_render_tags_select($option_name, $select_id, $container_id, $hidden_input_id)
{
    $response = rch_fetch_tags();

    if (!$response['success']) {
        echo '<p class="description error">' . esc_html($response['message']) . '</p>';
        return;
    }

    $data = $response['data'];
    if (empty($data['data'])) {
        echo '<p class="description">' . esc_html__('No tags available', 'rechat-plugin') . '</p>';
        return;
    }

    $selected_tags_json = get_option($option_name, '[]');
    $selected_tags = json_decode($selected_tags_json, true);

    if (!is_array($selected_tags)) {
        $selected_tags = [];
    }

    // Render select dropdown
    printf('<select id="%s" style="width:100%%; margin-bottom:10px;">', esc_attr($select_id));
    printf('<option value="" disabled selected>%s</option>', esc_html__('Please select a tag', 'rechat-plugin'));

    foreach ($data['data'] as $tag) {
        $name = !empty($tag['tag']) ? esc_html($tag['tag']) : esc_html__('Unnamed', 'rechat-plugin');
        printf('<option value="%s">%s</option>', esc_attr($name), $name);
    }

    echo '</select>';

    // Container for selected tags chips
    printf('<div id="%s" style="margin-bottom:10px;"></div>', esc_attr($container_id));

    // Hidden input to store selected tags
    printf(
        '<input type="hidden" name="%s" id="%s" value="%s">',
        esc_attr($option_name),
        esc_attr($hidden_input_id),
        esc_attr(wp_json_encode($selected_tags))
    );

    // Enqueue inline JavaScript for tag chips functionality
    rch_render_tags_script($select_id, $container_id, $hidden_input_id);
}

/*******************************
 * Render JavaScript for tag chips
 ******************************/
function rch_render_tags_script($select_id, $container_id, $hidden_input_id)
{
?>
    <script>
        (function() {
            'use strict';

            document.addEventListener('DOMContentLoaded', function() {
                const selectBox = document.getElementById(<?php echo wp_json_encode($select_id); ?>);
                const selectedTagsContainer = document.getElementById(<?php echo wp_json_encode($container_id); ?>);
                const hiddenInput = document.getElementById(<?php echo wp_json_encode($hidden_input_id); ?>);

                if (!selectBox || !selectedTagsContainer || !hiddenInput) {
                    return;
                }

                let selectedTagNames = [];

                try {
                    selectedTagNames = hiddenInput.value ? JSON.parse(hiddenInput.value) : [];
                } catch (e) {
                    selectedTagNames = [];
                }

                function renderChips() {
                    selectedTagsContainer.innerHTML = '';

                    selectedTagNames.forEach(function(tagName) {
                        const chip = document.createElement('span');
                        chip.textContent = tagName;
                        chip.className = 'tag-chip';
                        chip.style.cssText = 'display: inline-block; margin: 0 5px 5px 0; padding: 5px 10px; background-color: #ddd; border-radius: 3px; cursor: default;';

                        const closeBtn = document.createElement('span');
                        closeBtn.textContent = ' ×';
                        closeBtn.style.cssText = 'margin-left: 5px; cursor: pointer; font-weight: bold;';
                        closeBtn.setAttribute('aria-label', 'Remove tag');
                        closeBtn.onclick = function() {
                            selectedTagNames = selectedTagNames.filter(tag => tag !== tagName);
                            updateHiddenInput();
                            renderChips();
                        };

                        chip.appendChild(closeBtn);
                        selectedTagsContainer.appendChild(chip);
                    });
                }

                function updateHiddenInput() {
                    hiddenInput.value = JSON.stringify(selectedTagNames);
                }

                selectBox.addEventListener('change', function() {
                    const selectedTagName = selectBox.value;

                    if (selectedTagName && selectedTagNames.indexOf(selectedTagName) === -1) {
                        selectedTagNames.push(selectedTagName);
                        updateHiddenInput();
                        renderChips();
                    }

                    selectBox.value = '';
                });

                renderChips();
            });
        })();
    </script>
<?php
}

/*******************************
 * AJAX handler for updating all data
 ******************************/
function rch_update_all_data()
{
    // Verify nonce for security
    if (!check_ajax_referer('rch_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(__('Security check failed.', 'rechat-plugin'));
        return;
    }

    // Verify user capabilities
    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    // Get primary color and logo
    rch_get_primary_color_and_logo();

    // Call the function to fetch and update data
    $result = rch_update_agents_offices_regions_data();

    if (!is_array($result) || !array_key_exists('success', $result)) {
        wp_send_json_error(__('Unexpected sync response.', 'rechat-plugin'));
        return;
    }

    if ($result['success']) {
        wp_send_json_success($result['data'] ?? array());
        return;
    }

    $error_message = isset($result['message']) ? (string) $result['message'] : __('Sync failed.', 'rechat-plugin');
    wp_send_json_error($error_message);
}
add_action('wp_ajax_rch_update_all_data', 'rch_update_all_data');

/**
 * AJAX: return state/province options for a country (Rechat boundaries API).
 */
function rch_ajax_fetch_boundary_states()
{
    if (! check_ajax_referer('rch_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Security check failed.', 'rechat-plugin')));
    }

    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        wp_send_json_error(array('message' => __('Insufficient permissions.', 'rechat-plugin')));
    }

    $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
    if ($country === '') {
        wp_send_json_error(array('message' => __('Select a country first.', 'rechat-plugin')));
    }

    if (! function_exists('rch_rechat_fetch_boundaries_for_settings')) {
        wp_send_json_error(array('message' => __('Rechat helpers are not available.', 'rechat-plugin')));
    }

    $force_refresh = ! empty($_POST['force_refresh']);
    $options         = rch_rechat_fetch_boundaries_for_settings('state', strtoupper($country), $force_refresh);
    wp_send_json_success(array('options' => $options));
}

add_action('wp_ajax_rch_fetch_boundary_states', 'rch_ajax_fetch_boundary_states');

/**
 * AJAX: return country options (Rechat boundaries API, transient-cached).
 */
function rch_ajax_fetch_boundary_countries()
{
    if (! check_ajax_referer('rch_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => __('Security check failed.', 'rechat-plugin')));
    }

    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        wp_send_json_error(array('message' => __('Insufficient permissions.', 'rechat-plugin')));
    }

    if (! function_exists('rch_rechat_fetch_boundaries_for_settings')) {
        wp_send_json_error(array('message' => __('Rechat helpers are not available.', 'rechat-plugin')));
    }

    $force_refresh = ! empty($_POST['force_refresh']);
    $options         = rch_rechat_fetch_boundaries_for_settings('country', '', $force_refresh);
    wp_send_json_success(array('options' => $options));
}

add_action('wp_ajax_rch_fetch_boundary_countries', 'rch_ajax_fetch_boundary_countries');
