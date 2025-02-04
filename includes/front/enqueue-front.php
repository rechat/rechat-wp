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

    // Enqueue JavaScript files with version
    wp_enqueue_script('rch-ajax-front', RCH_PLUGIN_ASSETS . 'js/rch-ajax-front.js', ['jquery'], '1.0.0', true);
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
        wp_enqueue_script('rch-swiper-js', [], '8.4.5', true);
    }

    // Register Gutenberg block script (if used in the frontend)
    wp_register_script(
        'regions-block',
        get_template_directory_uri() . '/js/regions-block.js', // Path to your block JS file
        ['wp-blocks', 'wp-editor', 'wp-components', 'wp-element'],
        '1.0.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'rch_enqueue_frontend_styles');
//enqueue assets of listing block
function rch_enqueue_block_assets() {
    // Register block script
    wp_register_script(
        'rch-listing-block-script',
        RCH_PLUGIN_ASSETS . '/js/rch-rechat-listings.js',
        ['wp-blocks', 'wp-element'],
        '1.0.0',
        true
    );

    // Register block style
    wp_register_style(
        'rch-listing-block-css',
        RCH_PLUGIN_ASSETS . '/css/rch-listing-block.css',
        [],
        '1.0.0'
    );

    // Automatically enqueue script/style only when block is present
    if ( has_block( 'rch-rechat-plugin/listing-block' ) ) {
        wp_enqueue_script( 'rch-listing-block-script' );
        wp_enqueue_style( 'rch-listing-block-css' );
    }
}
add_action( 'enqueue_block_assets', 'rch_enqueue_block_assets' );
function rch_script_block_editor_assets() {
    // Get the active theme's style.css
    $theme_style = get_stylesheet_directory_uri() . '/style.css';

    // Enqueue the theme's style.css in the block editor
    wp_enqueue_style(
        'theme-style-editor',
        $theme_style,
        [],
        filemtime( get_stylesheet_directory() . '/style.css' ) // Ensure latest version loads
    );
}
add_action( 'enqueue_block_editor_assets', 'rch_script_block_editor_assets' );
