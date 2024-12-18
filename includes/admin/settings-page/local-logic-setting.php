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

    // Add a section for the settings
    add_settings_section(
        'rch_rechat_local_logic_section', // Section ID
        __('Local Logic Settings', 'rechat-plugin'), // Section title
        'rch_rechat_local_logic_section_description', // Callback function for description
        'local_logic_settings' // Page slug
    );

    // Add the Local Logic API Key field
    add_settings_field(
        'rch_rechat_local_logic_api_key', // Field ID
        __('Local Logic API Key', 'rechat-plugin'), // Field label
        'rch_rechat_render_api_key_field', // Callback function to render the field
        'local_logic_settings', // Page slug
        'rch_rechat_local_logic_section' // Section ID
    );

    // Add the Google Map API Key field
    add_settings_field(
        'rch_rechat_google_map_api_key', // Field ID
        __('Google Map API Key', 'rechat-plugin'), // Field label
        'rch_rechat_render_google_map_api_key_field', // Callback function to render the field
        'local_logic_settings', // Page slug
        'rch_rechat_local_logic_section' // Section ID
    );

    // Add the checkboxes for features
    add_settings_field(
        'rch_rechat_local_logic_features', // Field ID
        __('Features', 'rechat-plugin'), // Field label
        'rch_rechat_render_features_checkboxes', // Callback function to render the field
        'local_logic_settings', // Page slug
        'rch_rechat_local_logic_section' // Section ID
    );
}
// Hook the function to 'admin_init' so it runs when the admin interface initializes
add_action('admin_init', 'rch_rechat_register_local_logic_settings');

/*******************************
 * Render the local logic settings
 ******************************/
function rch_rechat_local_logic_section_description()
{
    echo '<p>' . esc_html__('Configure the API Keys and features for Local Logic integration.', 'rechat-plugin') . '</p>';
}

// Render the Local Logic API Key field
function rch_rechat_render_api_key_field()
{
    // Get the current value of the API Key
    $value = get_option('rch_rechat_local_logic_api_key', '');
    // Render the input field
    echo '<input type="text" name="rch_rechat_local_logic_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
}

// Render the Google Map API Key field
function rch_rechat_render_google_map_api_key_field()
{
    // Get the current value of the Google Map API Key
    $value = get_option('rch_rechat_google_map_api_key', '');
    // Render the input field
    echo '<input type="text" name="rch_rechat_google_map_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
}

/*******************************
 * Render the checkboxes for features
 ******************************/
function rch_rechat_render_features_checkboxes()
{
    // Get the currently selected features or default to an empty array
    $selected_features = get_option('rch_rechat_local_logic_features', []);
    // Define the available options
    $features = [
        'LocalContent' => __('Local Content', 'rechat-plugin'),
    ];
    // Render the checkboxes
    foreach ($features as $key => $label) {
        $checked = in_array($key, $selected_features) ? 'checked' : '';
        echo '<label>';
        echo '<input type="checkbox" name="rch_rechat_local_logic_features[]" value="' . esc_attr($key) . '" ' . esc_attr($checked) . ' />';
        echo ' ' . esc_html($label);
        echo '</label><br>';
    }
}
