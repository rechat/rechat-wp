<?php
if (! defined('ABSPATH')) {
    exit();
}

/*******************************
 * enqueue styles and scripts for Front
 ******************************/
function rch_enqueue_frontend_styles()
{
    wp_enqueue_style('rch-front-css-global', RCH_PLUGIN_URL . 'assets/css/rch-global.css', [], '1.0.0');
    wp_register_style('rch-front-css-agents', RCH_PLUGIN_URL . 'assets/css/frontend-styles.css', [], '1.0.0');
    wp_register_style('rch-swiper',  RCH_PLUGIN_URL . '/assets/css/swiper-bundle.min.css',[],'8.4.5');
    wp_register_script('rch-ajax-front', RCH_PLUGIN_URL . 'assets/js/rch-ajax-front.js', array('jquery'), null, true);
    wp_enqueue_script('rch-swiper-js', RCH_PLUGIN_URL . '/assets/js/swiper-bundle.min.js',[], '8.4.5', true);

    wp_localize_script('rch-ajax-front', 'rch_ajax_front_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('rch_ajax_front_nonce'),

    ));
    wp_enqueue_script(
        'rch-rechat-houses',
        RCH_PLUGIN_URL . 'assets/js/rch-rechat-houses.js',
        array('jquery'),
        null,
        true
    );
    wp_localize_script('rch-rechat-houses', 'rechat_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
    wp_register_style('rch-rechat-listing', RCH_PLUGIN_URL . 'assets/css/rch-rechat-listing.css', [], '1.0.0');

    // Check if we are on the Agents, Offices, Regions, or the Rechat Houses template
    if (
        is_post_type_archive('agents') ||
        is_singular('agents') ||
        is_post_type_archive('offices') ||
        is_singular('offices') ||
        is_post_type_archive('regions') ||
        is_singular('regions')
    ) {
        wp_enqueue_style('rch-front-css-agents');
        wp_enqueue_script('rch-ajax-front');
    }
    if (isset($_GET['house_id'])) {
        // Enqueue the registered CSS and JS only when 'house_id' is present in the URL
        wp_enqueue_style('rch-rechat-listing');
        wp_enqueue_style('rch-swiper');
        wp_enqueue_script('rch-swiper-js');
    }
}
add_action('wp_enqueue_scripts', 'rch_enqueue_frontend_styles');
