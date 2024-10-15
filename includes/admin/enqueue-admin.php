<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
/*******************************
 * enqueue styles and scripts for admin
 ******************************/
function rch_enqueue_admin_styles()
{

    if (is_admin()) {
        wp_enqueue_style('rch-admin-styles', RCH_PLUGIN_URL . 'assets/css/admin-styles.css');
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker', 'jQuery(document).ready(function($){$(".my-color-field").wpColorPicker();});');
    }
}
add_action('admin_enqueue_scripts', 'rch_enqueue_admin_styles');
// hookfor add ajax for refetch data
function rch_enqueue_admin_scripts($hook)
{
    // Ensure this only loads on your plugin settings page
    if ($hook !== 'toplevel_page_rechat-setting') {
        return;
    }

    wp_enqueue_script('rch-ajax-script', RCH_PLUGIN_URL . 'assets/js/rch-admin.js', array('jquery'), null, true);

    // Localize the script with new data
    wp_localize_script('rch-ajax-script', 'rch_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('rch_ajax_nonce'),
    ));

}
add_action('admin_enqueue_scripts', 'rch_enqueue_admin_scripts');
