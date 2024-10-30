<?php
if (! defined('ABSPATH')) {
    exit();
}

/*******************************
 * enqueue styles and scripts for Front
 ******************************/

function rch_enqueue_frontend_styles()
{
    // Enqueue CSS styles
    wp_enqueue_style('rch-front-css-global', RCH_PLUGIN_ASSETS . 'css/rch-global.css', [], '1.0.0');
    wp_register_style('rch-swiper', RCH_PLUGIN_ASSETS . 'css/swiper-bundle.min.css', [], '8.4.5');
    wp_register_style('rch-rechat-listing', RCH_PLUGIN_ASSETS . 'css/rch-rechat-listing.css', [], '1.0.0');
    
    // Enqueue JavaScript files
    wp_enqueue_script('rch-ajax-front', RCH_PLUGIN_ASSETS . 'js/rch-ajax-front.js', ['jquery'], null, true);
    wp_enqueue_script('rch-swiper-js', RCH_PLUGIN_ASSETS . 'js/swiper-bundle.min.js', [], '8.4.5', true);
    wp_enqueue_script('rch-gutenberg-ajax', RCH_PLUGIN_ASSETS . 'js/rch-gutenberg-ajax.js', ['jquery'], '8.4.5', true);
    wp_enqueue_script('rch-gutenberg-agent-pagination', RCH_PLUGIN_ASSETS . 'js/rch-gutenberg-agent-pagination.js', ['jquery'], '8.4.5', true);
    // wp_enqueue_script('rch-rechat-houses', RCH_PLUGIN_ASSETS . 'js/rch-rechat-houses.js', ['jquery'], null, true);

    // Localize scripts for AJAX
    $ajax_url = admin_url('admin-ajax.php');
    wp_localize_script('rch-gutenberg-agent-pagination', 'rch_agents_params', ['ajax_url' => $ajax_url]);
    wp_localize_script('rch-gutenberg-ajax', 'rch_ajax_object', ['ajax_url' => $ajax_url]);
    wp_localize_script('rch-ajax-front', 'rch_ajax_front_params', [
        'ajax_url' => $ajax_url,
        'nonce'    => wp_create_nonce('rch_ajax_front_nonce'),
    ]);
    // wp_localize_script('rch-rechat-houses', 'rechat_ajax_object', ['ajax_url' => $ajax_url]);

    // Conditionally enqueue Swiper and listing styles when 'listing_id' is in the URL
    if (isset($_GET['listing_id'])) {
        wp_enqueue_style('rch-rechat-listing');
        wp_enqueue_style('rch-swiper');
        wp_enqueue_script('rch-swiper-js');
    }

    // Register Gutenberg block script (if used in the frontend)
    wp_register_script(
        'regions-block',
        get_template_directory_uri() . '/js/regions-block.js', // Path to your block JS file
        ['wp-blocks', 'wp-editor', 'wp-components', 'wp-element'],
        true
    );


}
add_action('wp_enqueue_scripts', 'rch_enqueue_frontend_styles');
