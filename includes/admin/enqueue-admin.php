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

    // Enqueue Rechat SDK CSS
    wp_enqueue_style(
        'rechat-sdk-css',
        'https://sdk.rechat.com/examples/dist/rechat.min.css',
        [],
        null
    );

    // Enqueue Rechat SDK JavaScript
    wp_enqueue_script(
        'rechat-sdk-js',
        'https://sdk.rechat.com/examples/dist/rechat.min.js',
        [],
        null,
        false
    );
}
add_action('enqueue_block_editor_assets', 'rch_enqueue_custom_gutenberg_assets');
