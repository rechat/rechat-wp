<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * enqueue styles and scripts for admin
 ******************************/
function rch_enqueue_admin_styles()
{
    if (is_admin()) {
        $version = '1.0.0';
        wp_enqueue_style('rch-admin-styles', RCH_PLUGIN_URL . 'assets/css/admin-styles.css', [], $version);
        wp_enqueue_style('rch-front-css-global', RCH_PLUGIN_URL . 'assets/css/rch-global.css', [], $version);
        wp_enqueue_script('rch-ajax-front', RCH_PLUGIN_URL . 'assets/js/rch-ajax-front.js', array('jquery'), null, true);
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker', 'jQuery(document).ready(function($){$(".my-color-field").wpColorPicker();});');
    }
}

add_action('admin_enqueue_scripts', 'rch_enqueue_admin_styles');

// hook for add ajax for refetch data
function rch_enqueue_admin_scripts($hook)
{
    // Ensure this only loads on your plugin settings page
    if ($hook !== 'toplevel_page_rechat-setting') {
        return;
    }

    $version = '1.0.0';
    wp_enqueue_script('rch-ajax-script', RCH_PLUGIN_URL . 'assets/js/rch-admin.js', array('jquery'), $version, true);

    // Localize the script with new data
    wp_localize_script('rch-ajax-script', 'rch_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('rch_ajax_nonce'),
    ));
    //this assets for neighborhoods

}
add_action('admin_enqueue_scripts', 'rch_enqueue_admin_scripts');

function rch_enqueue_custom_gutenberg_assets()
{
    // Only enqueue for the Gutenberg editor
    if (!is_admin()) {
        return;
    }
    $version = '1.0.0';
    wp_enqueue_style('rch-editor-css', RCH_PLUGIN_URL . 'assets/css/rch-editor.css', [], $version);
    wp_enqueue_style(
        'rch-listing-block-css',
        RCH_PLUGIN_ASSETS . '/css/rch-listing-block.css',
        [],
        RCH_VERSION
    );}

add_action('enqueue_block_editor_assets', 'rch_enqueue_custom_gutenberg_assets');
