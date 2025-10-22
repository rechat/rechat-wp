<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Enqueue styles and scripts for Frontend
 ******************************/
function rch_enqueue_frontend_styles()
{
    // Enqueue CSS styles
    wp_enqueue_style('rch-front-css-global', RCH_PLUGIN_ASSETS . 'css/rch-global.css', [], RCH_VERSION);
    wp_register_style('rch-swiper', RCH_PLUGIN_ASSETS . 'css/swiper-bundle.min.css', [], RCH_VERSION_SWIPER);
    wp_register_style('rch-rechat-listing', RCH_PLUGIN_ASSETS . 'css/rch-rechat-listing.css', [], RCH_VERSION);
    wp_register_style('rch-rechat-search_listing_shortcode', RCH_PLUGIN_ASSETS . 'css/search_bar_listing_shortcode.css', [], RCH_VERSION);
    // Enqueue JavaScript files with version
    wp_enqueue_script('rch-ajax-front', RCH_PLUGIN_ASSETS . 'js/rch-ajax-front.js', ['jquery'], RCH_VERSION, true);
    wp_enqueue_script('rch-swiper-js', RCH_PLUGIN_ASSETS . 'js/swiper-bundle.min.js', [], RCH_VERSION_SWIPER, true);
    wp_enqueue_script('rch-gutenberg-ajax', RCH_PLUGIN_ASSETS . 'js/rch-gutenberg-ajax.js', ['jquery'], RCH_VERSION, true);
    wp_enqueue_script('rch-gutenberg-agent-pagination', RCH_PLUGIN_ASSETS . 'js/rch-gutenberg-agent-pagination.js', ['jquery'], RCH_VERSION, true);

    // Localize scripts for AJAX
    $ajax_url = admin_url('admin-ajax.php');
    wp_localize_script('rch-gutenberg-agent-pagination', 'rch_agents_params', ['ajax_url' => $ajax_url]);
    wp_localize_script('rch-gutenberg-ajax', 'rch_ajax_object', ['ajax_url' => $ajax_url]);
    wp_localize_script('rch-ajax-front', 'rch_ajax_front_params', [
        'ajax_url' => $ajax_url,
        'nonce'    => wp_create_nonce('rch_ajax_front_nonce'),
    ]);

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
        RCH_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'rch_enqueue_frontend_styles');

/*******************************
 * Enqueue assets for listing block
 ******************************/
function rch_enqueue_block_assets()
{
    $primary_color = get_option('_rch_primary_color', '#2271b1'); // Default to red if not set


    // Register block style
    wp_register_style(
        'rch-listing-block-css',
        RCH_PLUGIN_ASSETS . '/css/rch-listing-block.css',
        [],
        RCH_VERSION
    );

    // Register Places Autocomplete CSS
    wp_register_style(
        'rch-places-autocomplete',
        RCH_PLUGIN_URL . 'assets/css/rch-places-autocomplete.css',
        [],
        RCH_VERSION
    );

    // Register Rechat SDK CSS and JS
    wp_register_style(
        'rechat-sdk-css',
        'https://sdk.rechat.com/examples/dist/rechat.min.css',
        [],
        null
    );

    wp_register_script(
        'rechat-sdk-js',
        'https://sdk.rechat.com/examples/dist/rechat.min.js',
        [],
        null,
        false
    );

    // Automatically enqueue script/style only when block is present
    if (has_block('rch-rechat-plugin/listing-block')) {
        wp_enqueue_style('rch-listing-block-css');
        wp_enqueue_style('rechat-sdk-css');
        wp_enqueue_script('rechat-sdk-js');
    }
}
add_action('enqueue_block_assets', 'rch_enqueue_block_assets');
/*******************************
 * Enqueue theme styles in block editor
 ******************************/
function rch_script_block_editor_assets()
{
    // Get the active theme's style.css
    $theme_style = get_stylesheet_directory_uri() . '/style.css';

    // Enqueue the theme's style.css in the block editor
    wp_enqueue_style(
        'theme-style-editor',
        $theme_style,
        [],
        filemtime(get_stylesheet_directory() . '/style.css') // Ensure latest version loads
    );
}
add_action('enqueue_block_editor_assets', 'rch_script_block_editor_assets');
