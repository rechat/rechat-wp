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

    register_setting('general_settings', 'rch_agent_active_listings_use_agent_brand', array(
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => 'rch_sanitize_general_settings_checkbox',
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

    add_settings_field(
        'rch_agent_active_listings_use_agent_brand',
        __('Active listings scope', 'rechat-plugin'),
        'rch_render_agent_active_listings_use_agent_brand_field',
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

/**
 * Sanitize checkbox values saved from General Settings (hidden 0 + checkbox 1).
 *
 * @param mixed $value Submitted value.
 * @return bool
 */
function rch_sanitize_general_settings_checkbox($value): bool
{
    return $value === '1' || $value === 1 || $value === true;
}

/**
 * Whether agent profile active listing sections should use the agent Rechat ID (api_id) for filter_agents.
 *
 * @return bool
 */
function rch_agent_active_listings_use_agent_brand_filter(): bool
{
    return (bool) get_option('rch_agent_active_listings_use_agent_brand', false);
}

/**
 * Agent post Rechat UUID from api_id meta (synced agents only).
 *
 * @param int $post_id Agent post ID.
 * @return string
 */
function rch_get_agent_rechat_api_id(int $post_id): string
{
    if ($post_id <= 0) {
        return '';
    }

    $raw = get_post_meta($post_id, 'api_id', true);

    return is_string($raw) ? trim($raw) : '';
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

/**
 * Checkbox: active agent-page listings use filter_agents = agent Rechat ID (api_id) instead of agents meta.
 */
function rch_render_agent_active_listings_use_agent_brand_field()
{
    $enabled = rch_agent_active_listings_use_agent_brand_filter();
    ?>
    <input type="hidden" name="rch_agent_active_listings_use_agent_brand" value="0" />
    <label>
        <input
            type="checkbox"
            name="rch_agent_active_listings_use_agent_brand"
            value="1"
            <?php checked($enabled); ?>
        />
        <?php esc_html_e('Show all active listings for the agent (not only brokerage)', 'rechat-plugin'); ?>
    </label>
    <p class="description">
        <?php esc_html_e('When enabled, active listing blocks use filter_agents with that agent’s Rechat ID (Agents → Rechat ID / api_id) instead of the agents post meta list. Sold sections still use agents meta. If Rechat ID is empty, agents meta is used.', 'rechat-plugin'); ?>
    </p>
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
    $field_id = esc_attr($option_name);
    $saved     = (string) get_option($option_name, '');
    ?>
    <div class="rch-async-lead-channel" data-rch-lead-channel-async="1" data-selected="<?php echo esc_attr($saved); ?>">
        <p class="description rch-lead-channel-loading-msg" style="margin:0 0 8px;">
            <span class="spinner is-active" style="float:none;margin:0 6px 0 0;vertical-align:middle;visibility:visible;"></span>
            <?php esc_html_e('Loading lead sources…', 'rechat-plugin'); ?>
        </p>
        <select id="<?php echo $field_id; ?>" name="<?php echo $field_id; ?>" class="rch-lead-channel-select regular-text" disabled style="display:none;"></select>
        <p class="description rch-lead-channel-selected-name" style="display:none;margin-top:6px;"></p>
        <p class="description rch-lead-channel-empty" style="display:none;margin-top:6px;"><?php esc_html_e('No lead sources available.', 'rechat-plugin'); ?></p>
        <p class="description rch-lead-channel-error" style="display:none;margin-top:6px;color:#b32d2d;"></p>
    </div>
    <?php
}

/*******************************
 * Render tags select field with chips
 ******************************/
function rch_render_tags_select($option_name, $select_id, $container_id, $hidden_input_id)
{
    $selected_tags_json = get_option($option_name, '[]');
    $selected_tags     = json_decode((string) $selected_tags_json, true);
    if (! is_array($selected_tags)) {
        $selected_tags = [];
    }
    $hidden_value = wp_json_encode($selected_tags, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <div
        class="rch-async-tags"
        data-rch-tags-async="1"
        data-select-id="<?php echo esc_attr($select_id); ?>"
        data-container-id="<?php echo esc_attr($container_id); ?>"
        data-hidden-input-id="<?php echo esc_attr($hidden_input_id); ?>"
    >
        <p class="description rch-tags-loading-msg" style="margin:0 0 8px;">
            <span class="spinner is-active" style="float:none;margin:0 6px 0 0;vertical-align:middle;visibility:visible;"></span>
            <?php esc_html_e('Loading tags…', 'rechat-plugin'); ?>
        </p>
        <select id="<?php echo esc_attr($select_id); ?>" style="width:100%;margin-bottom:10px;display:none;" disabled></select>
        <div id="<?php echo esc_attr($container_id); ?>" class="rch-tags-chip-wrap" style="margin-bottom:10px;display:none;"></div>
        <input type="hidden" name="<?php echo esc_attr($option_name); ?>" id="<?php echo esc_attr($hidden_input_id); ?>" value="<?php echo esc_attr($hidden_value); ?>">
        <p class="description rch-tags-empty" style="display:none;"><?php esc_html_e('No tags available.', 'rechat-plugin'); ?></p>
        <p class="description rch-tags-error" style="display:none;color:#b32d2d;"></p>
    </div>
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
 * AJAX: return state/province options for a country (Rechat boundaries/search with omit[]=boundary.geometry).
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
 * AJAX: return country options (Rechat boundaries/search with omit[]=boundary.geometry; transient-cached).
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

/**
 * AJAX: lead channels for Rechat settings (async UI).
 */
function rch_ajax_fetch_lead_channels_settings()
{
    if (! check_ajax_referer('rch_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'rechat-plugin')]);
    }

    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        wp_send_json_error(['message' => __('Insufficient permissions.', 'rechat-plugin')]);
    }

    $response = rch_fetch_lead_channels();
    if (empty($response['success'])) {
        $msg = isset($response['message']) ? (string) $response['message'] : __('Could not load lead sources.', 'rechat-plugin');
        wp_send_json_error(['message' => $msg]);
    }

    $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
    $rows = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
    $out  = [];

    foreach ($rows as $channel) {
        if (! is_array($channel)) {
            continue;
        }
        $id = isset($channel['id']) ? (string) $channel['id'] : '';
        $name = '';
        if (isset($channel['name'])) {
            $name = (string) $channel['name'];
        } elseif (isset($channel['title'])) {
            $name = (string) $channel['title'];
        }
        $out[] = [
            'id'    => $id,
            'name'  => $name,
            // Back-compat for older JS that expects "title"
            'title' => $name,
        ];
    }

    wp_send_json_success(['channels' => $out]);
}

add_action('wp_ajax_rch_fetch_lead_channels_settings', 'rch_ajax_fetch_lead_channels_settings');

/**
 * AJAX: contact tags for Rechat settings (async UI).
 */
function rch_ajax_fetch_tags_settings()
{
    if (! check_ajax_referer('rch_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'rechat-plugin')]);
    }

    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        wp_send_json_error(['message' => __('Insufficient permissions.', 'rechat-plugin')]);
    }

    $response = rch_fetch_tags();
    if (empty($response['success'])) {
        $msg = isset($response['message']) ? (string) $response['message'] : __('Could not load tags.', 'rechat-plugin');
        wp_send_json_error(['message' => $msg]);
    }

    $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
    $rows = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
    $out  = [];

    foreach ($rows as $tag) {
        if (! is_array($tag)) {
            continue;
        }
        $name = isset($tag['tag']) ? trim((string) $tag['tag']) : '';
        if ($name !== '') {
            $out[] = ['tag' => $name];
        }
    }

    wp_send_json_success(['tags' => $out]);
}

add_action('wp_ajax_rch_fetch_tags_settings', 'rch_ajax_fetch_tags_settings');
