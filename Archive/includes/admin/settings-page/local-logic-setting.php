<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*******************************
 * Register local Logic settings
 ******************************/
function rch_rechat_register_local_logic_settings()
{
    // Register the Local Logic API Key field with sanitization
    register_setting(RCH_LOCAL_LOGIC_SETTINGS_GROUP, 'rch_rechat_local_logic_api_key', [
        'type' => 'string',
        'sanitize_callback' => 'rch_sanitize_api_key',
        'default' => '',
    ]);

    // Register the Google Map API Key field with sanitization
    register_setting(RCH_LOCAL_LOGIC_SETTINGS_GROUP, 'rch_rechat_google_map_api_key', [
        'type' => 'string',
        'sanitize_callback' => 'rch_sanitize_api_key',
        'default' => '',
    ]);

    // Register the checkboxes field (as an array to store multiple values)
    register_setting(RCH_LOCAL_LOGIC_SETTINGS_GROUP, 'rch_rechat_local_logic_features', [
        'type' => 'array',
        'sanitize_callback' => 'rch_sanitize_features',
        'default' => [],
    ]);

    // Register Neighborhood API Key field with sanitization
    register_setting(RCH_LOCAL_LOGIC_SETTINGS_GROUP, 'rch_rechat_neighborhood_api_key', [
        'type' => 'string',
        'sanitize_callback' => 'rch_sanitize_api_key',
        'default' => '',
    ]);

    // Register the neighborhood checkboxes field with sanitization
    register_setting(RCH_LOCAL_LOGIC_SETTINGS_GROUP, 'rch_rechat_neighborhood_features', [
        'type' => 'array',
        'sanitize_callback' => 'rch_sanitize_neighborhood_features',
        'default' => [],
    ]);

    // Add a section for the listing page settings
    add_settings_section(
        'rch_rechat_local_logic_section',
        __('Listing Page Settings', 'rechat-plugin'),
        'rch_rechat_local_logic_section_description',
        RCH_LOCAL_LOGIC_SETTINGS_GROUP
    );

    // Add a section for the neighborhood page settings
    add_settings_section(
        'rch_rechat_neighborhood_section',
        __('Neighborhood Page Settings', 'rechat-plugin'),
        'rch_rechat_neighborhood_section_description',
        RCH_LOCAL_LOGIC_SETTINGS_GROUP
    );

    // Add the Local Logic API Key field
    add_settings_field(
        'rch_rechat_local_logic_api_key',
        __('Local Logic API Key', 'rechat-plugin'),
        'rch_rechat_render_api_key_field',
        RCH_LOCAL_LOGIC_SETTINGS_GROUP,
        'rch_rechat_local_logic_section',
        [
            'option_name' => 'rch_rechat_local_logic_api_key',
            'description' => __('Enter your Local Logic API key for listing page integration.', 'rechat-plugin'),
        ]
    );

    // Add the Google Map API Key field
    add_settings_field(
        'rch_rechat_google_map_api_key',
        __('Google Map API Key', 'rechat-plugin'),
        'rch_rechat_render_api_key_field',
        RCH_LOCAL_LOGIC_SETTINGS_GROUP,
        'rch_rechat_local_logic_section',
        [
            'option_name' => 'rch_rechat_google_map_api_key',
            'description' => __('Enter your Google Maps API key for map functionality.', 'rechat-plugin'),
        ]
    );

    // Add the checkboxes for features
    add_settings_field(
        'rch_rechat_local_logic_features',
        __('Features', 'rechat-plugin'),
        'rch_rechat_render_features_checkboxes',
        RCH_LOCAL_LOGIC_SETTINGS_GROUP,
        'rch_rechat_local_logic_section',
        [
            'option_name' => 'rch_rechat_local_logic_features',
            'features' => RCH_LOCAL_LOGIC_FEATURES,
        ]
    );

    // Add the Neighborhood API Key field
    add_settings_field(
        'rch_rechat_neighborhood_api_key',
        __('Local Logic API Key', 'rechat-plugin'),
        'rch_rechat_render_api_key_field',
        RCH_LOCAL_LOGIC_SETTINGS_GROUP,
        'rch_rechat_neighborhood_section',
        [
            'option_name' => 'rch_rechat_neighborhood_api_key',
            'description' => __('Enter your Local Logic API key for neighborhood page integration.', 'rechat-plugin'),
        ]
    );

    // Add the neighborhood checkboxes
    add_settings_field(
        'rch_rechat_neighborhood_features',
        __('Neighborhood Features', 'rechat-plugin'),
        'rch_rechat_render_features_checkboxes',
        RCH_LOCAL_LOGIC_SETTINGS_GROUP,
        'rch_rechat_neighborhood_section',
        [
            'option_name' => 'rch_rechat_neighborhood_features',
            'features' => RCH_NEIGHBORHOOD_FEATURES,
        ]
    );
}
add_action('admin_init', 'rch_rechat_register_local_logic_settings');

/*******************************
 * Sanitization callbacks
 ******************************/
function rch_sanitize_api_key($input)
{
    return sanitize_text_field(trim($input));
}

function rch_sanitize_features($input)
{
    if (!is_array($input)) {
        return [];
    }
    
    $allowed_features = array_keys(RCH_LOCAL_LOGIC_FEATURES);
    return array_filter($input, function ($value) use ($allowed_features) {
        return in_array($value, $allowed_features, true);
    });
}

function rch_sanitize_neighborhood_features($input)
{
    if (!is_array($input)) {
        return [];
    }
    
    $allowed_features = array_keys(RCH_NEIGHBORHOOD_FEATURES);
    return array_filter($input, function ($value) use ($allowed_features) {
        return in_array($value, $allowed_features, true);
    });
}

/*******************************
 * Section descriptions
 ******************************/
function rch_rechat_local_logic_section_description()
{
    echo '<p>' . esc_html__('Configure the API Keys and features for Local Logic integration.', 'rechat-plugin') . '</p>';
}

function rch_rechat_neighborhood_section_description()
{
    echo '<p>' . esc_html__('Configure the settings for the Neighborhood Page.', 'rechat-plugin') . '</p>';
}

/*******************************
 * Field render callbacks
 ******************************/
function rch_rechat_render_api_key_field($args)
{
    $option_name = isset($args['option_name']) ? $args['option_name'] : '';
    $description = isset($args['description']) ? $args['description'] : '';
    
    if (empty($option_name)) {
        return;
    }
    
    $value = get_option($option_name, '');
    $field_id = esc_attr($option_name);
    
    printf(
        '<input type="password" id="%1$s" name="%1$s" value="%2$s" class="regular-text" autocomplete="off" />',
        $field_id,
        esc_attr($value)
    );
    
    if (!empty($description)) {
        printf('<p class="description">%s</p>', esc_html($description));
    }
}

function rch_rechat_render_features_checkboxes($args)
{
    $option_name = isset($args['option_name']) ? $args['option_name'] : '';
    $features = isset($args['features']) ? $args['features'] : [];
    
    if (empty($option_name) || empty($features)) {
        return;
    }
    
    $selected_features = get_option($option_name, []);
    
    foreach ($features as $key => $label) {
        $checked = in_array($key, (array) $selected_features, true);
        $field_id = esc_attr($option_name . '_' . $key);
        
        printf(
            '<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[]" value="%3$s"%4$s /> %5$s</label><br />',
            $field_id,
            esc_attr($option_name),
            esc_attr($key),
            checked($checked, true, false),
            esc_html__($label, 'rechat-plugin')
        );
    }
}
