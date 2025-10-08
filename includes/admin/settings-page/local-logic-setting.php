<?php

/*******************************
 * register local Logic setting
 ******************************/
// Register settings and fields for the 'local-logic' tab
function rch_rechat_register_local_logic_settings()
{
    // Register the Local Logic API Key field
    register_setting('local_logic_settings', 'rch_rechat_local_logic_api_key'); // API Key field

    // Register the Google Map API Key field
    register_setting('local_logic_settings', 'rch_rechat_google_map_api_key'); // Google Map API Key field

    // Register the checkboxes field (as an array to store multiple values)
    register_setting('local_logic_settings', 'rch_rechat_local_logic_features', [
        'default' => [] // Default value is an empty array
    ]);

    // Register Neighborhood API Key field
    register_setting('local_logic_settings', 'rch_rechat_neighborhood_api_key');

    // Register the neighborhood checkboxes field
    register_setting('local_logic_settings', 'rch_rechat_neighborhood_features', [
        'default' => []
    ]);

    // Add a section for the settings
    add_settings_section(
        'rch_rechat_local_logic_section',
        __('Listing Page Settings', 'rechat-plugin'),
        'rch_rechat_local_logic_section_description',
        'local_logic_settings'
    );

    add_settings_section(
        'rch_rechat_neighborhood_section',
        __('Neighborhood Page Settings', 'rechat-plugin'),
        'rch_rechat_neighborhood_section_description',
        'local_logic_settings'
    );

    // Add the Local Logic API Key field
    add_settings_field(
        'rch_rechat_local_logic_api_key',
        __('Local Logic API Key', 'rechat-plugin'),
        'rch_rechat_render_api_key_field',
        'local_logic_settings',
        'rch_rechat_local_logic_section'
    );

    // Add the Google Map API Key field
    add_settings_field(
        'rch_rechat_google_map_api_key',
        __('Google Map API Key', 'rechat-plugin'),
        'rch_rechat_render_google_map_api_key_field',
        'local_logic_settings',
        'rch_rechat_local_logic_section'
    );

    // Add the checkboxes for features
    add_settings_field(
        'rch_rechat_local_logic_features',
        __('Features', 'rechat-plugin'),
        'rch_rechat_render_features_checkboxes',
        'local_logic_settings',
        'rch_rechat_local_logic_section'
    );

    // Add the Neighborhood API Key field
    add_settings_field(
        'rch_rechat_neighborhood_api_key',
        __('Local Logic API Key', 'rechat-plugin'),
        'rch_rechat_render_neighborhood_api_key_field',
        'local_logic_settings',
        'rch_rechat_neighborhood_section'
    );

    // Add the neighborhood checkboxes
    add_settings_field(
        'rch_rechat_neighborhood_features',
        __('Neighborhood Features', 'rechat-plugin'),
        'rch_rechat_render_neighborhood_features_checkboxes',
        'local_logic_settings',
        'rch_rechat_neighborhood_section'
    );
}
add_action('admin_init', 'rch_rechat_register_local_logic_settings');

/*******************************
 * Render the local logic settings
 ******************************/
function rch_rechat_local_logic_section_description()
{
    echo '<p>' . esc_html__('Configure the API Keys and features for Local Logic integration.', 'rechat-plugin') . '</p>';
}

function rch_rechat_neighborhood_section_description()
{
    echo '<p>' . esc_html__('Configure the settings for the Neighborhood Page.', 'rechat-plugin') . '</p>';
}

// Render the Local Logic API Key field
function rch_rechat_render_api_key_field()
{
    $value = get_option('rch_rechat_local_logic_api_key', '');
    echo '<input type="text" name="rch_rechat_local_logic_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
}

// Render the Google Map API Key field
function rch_rechat_render_google_map_api_key_field()
{
    $value = get_option('rch_rechat_google_map_api_key', '');
    echo '<input type="text" name="rch_rechat_google_map_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
}

// Render the Neighborhood API Key field
function rch_rechat_render_neighborhood_api_key_field()
{
    $value = get_option('rch_rechat_neighborhood_api_key', '');
    echo '<input type="text" name="rch_rechat_neighborhood_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
}

/*******************************
 * Render the checkboxes for features
 ******************************/
function rch_rechat_render_features_checkboxes()
{
    $selected_features = get_option('rch_rechat_local_logic_features', []);
    $features = [
        'LocalContent' => __('Local Content', 'rechat-plugin'),
    ];
    foreach ($features as $key => $label) {
        $checked = in_array($key, (array) $selected_features) ? 'checked' : '';
        echo '<label>';
        echo '<input type="checkbox" name="rch_rechat_local_logic_features[]" value="' . esc_attr($key) . '" ' . esc_attr($checked) . ' />';
        echo ' ' . esc_html($label);
        echo '</label><br>';
    }
}

// Render the checkboxes for neighborhood features
function rch_rechat_render_neighborhood_features_checkboxes()
{
    $selected_features = get_option('rch_rechat_neighborhood_features', []);
    $features = [
        'Hero' => __('Neighborhood Hero', 'rechat-plugin'),
        'Map' => __('Neighborhood Map', 'rechat-plugin'),
        'Highlights' => __('Neighborhood Highlights', 'rechat-plugin'),
        'Characteristics' => __('Neighborhood Characteristics', 'rechat-plugin'),
        'Schools' => __('Neighborhood Schools', 'rechat-plugin'),
        'Demographics' => __('Neighborhood Demographics', 'rechat-plugin'),
        'PropertyValueDrivers' => __('Neighborhood Property Value Drivers', 'rechat-plugin'),
        'MarketTrends' => __('Neighborhood Market Trends', 'rechat-plugin'),
        'Match' => __('Neighborhood Match', 'rechat-plugin'),
    ];
    foreach ($features as $key => $label) {
        $checked = in_array($key, (array) $selected_features) ? 'checked' : '';
        echo '<label>';
        echo '<input type="checkbox" name="rch_rechat_neighborhood_features[]" value="' . esc_attr($key) . '" ' . esc_attr($checked) . ' />';
        echo ' ' . esc_html($label);
        echo '</label><br>';
    }
}
