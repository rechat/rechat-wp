<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*******************************
 * Enqueue styles and scripts for admin
 ******************************/
function rch_enqueue_admin_styles()
{
    // Validate required constants are defined
    if (!defined('RCH_PLUGIN_URL') || !defined('RCH_VERSION')) {
        return;
    }

    if (!is_admin()) {
        return;
    }

    // Enqueue admin styles
    wp_enqueue_style(
        'rch-admin-styles',
        RCH_PLUGIN_URL . 'assets/css/admin-styles.css',
        [],
        RCH_VERSION
    );

    wp_enqueue_style(
        'rch-front-css-global',
        RCH_PLUGIN_URL . 'assets/css/rch-global.css',
        [],
        RCH_VERSION
    );

    // Enqueue admin scripts
    wp_enqueue_script(
        'rch-ajax-front',
        RCH_PLUGIN_URL . 'assets/js/rch-ajax-front.js',
        ['jquery'],
        RCH_VERSION,
        true
    );

    // Enqueue WordPress color picker
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    // Initialize color picker
    wp_add_inline_script(
        'wp-color-picker',
        'jQuery(document).ready(function($){$(".my-color-field").wpColorPicker();});'
    );
}
add_action('admin_enqueue_scripts', 'rch_enqueue_admin_styles');

/*******************************
 * Enqueue scripts for plugin settings page
 ******************************/
function rch_enqueue_admin_scripts($hook)
{
    // Validate required constants are defined
    if (!defined('RCH_PLUGIN_URL') || !defined('RCH_VERSION')) {
        return;
    }

    // Only load on plugin settings page
    if ($hook !== 'toplevel_page_rechat-setting') {
        return;
    }
    // Enqueue admin AJAX script
    wp_enqueue_script(
        'rch-ajax-script',
        RCH_PLUGIN_URL . 'assets/js/rch-admin.js',
        ['jquery'],
        RCH_VERSION,
        true
    );

    // Localize script with AJAX data
    wp_localize_script(
        'rch-ajax-script',
        'rch_ajax_object',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('rch_ajax_nonce'),
            'boundary_states' => array(
                'loading'             => __('Loading states…', 'rechat-plugin'),
                'loading_countries'   => __('Loading countries…', 'rechat-plugin'),
                'countries_failed'    => __('Could not load countries. Try again.', 'rechat-plugin'),
                'failed'              => __('Could not load states. Try again.', 'rechat-plugin'),
                'state_placeholder'   => __('Select a state / province', 'rechat-plugin'),
                'any_country'         => __('Any', 'rechat-plugin'),
            ),
            'lead_capture'    => array(
                'select_channel'   => __('Select Lead Channel', 'rechat-plugin'),
                'select_tag'       => __('Please select a tag', 'rechat-plugin'),
                'channels_failed'  => __('Could not load lead sources.', 'rechat-plugin'),
                'tags_failed'      => __('Could not load tags.', 'rechat-plugin'),
            ),
        ]
    );
    
}
add_action('admin_enqueue_scripts', 'rch_enqueue_admin_scripts');

/*******************************
 * Enqueue assets for Gutenberg block editor
 ******************************/
function rch_enqueue_custom_gutenberg_assets()
{
    // Validate required constants are defined
    if (!defined('RCH_PLUGIN_URL') || !defined('RCH_PLUGIN_ASSETS') || !defined('RCH_VERSION')) {
        return;
    }

    // Only enqueue in admin (Gutenberg editor)
    if (!is_admin()) {
        return;
    }

    // Enqueue custom editor styles
    wp_enqueue_style(
        'rch-editor-css',
        RCH_PLUGIN_URL . 'assets/css/rch-editor.css',
        [],
        RCH_VERSION
    );

    // Enqueue listing block styles
    wp_enqueue_style(
        'rch-listing-block-css',
        RCH_PLUGIN_ASSETS . 'css/rch-listing-block.css',
        [],
        RCH_VERSION
    );

    $rch_sdk_css = defined('RCH_RECHAT_SDK_CSS_URL') ? RCH_RECHAT_SDK_CSS_URL : 'https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.css';
    $rch_sdk_js = defined('RCH_RECHAT_SDK_JS_URL') ? RCH_RECHAT_SDK_JS_URL : 'https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.js';

    wp_enqueue_style(
        'rechat-sdk-css',
        $rch_sdk_css,
        [],
        null
    );

    wp_enqueue_script(
        'rechat-sdk-js',
        $rch_sdk_js,
        [],
        null,
        false
    );
}
add_action('enqueue_block_editor_assets', 'rch_enqueue_custom_gutenberg_assets');
