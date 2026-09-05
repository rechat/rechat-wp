<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Off Market Gutenberg block.
 * Editor renders via ServerSideRender; front-end output is the
 * [rch_off_market] shortcode (off_market CPT grid/swiper).
 ******************************/

function rch_register_block_assets_off_market()
{
    if (! wp_script_is('rch-gutenberg-js', 'registered')) {
        wp_register_script(
            'rch-gutenberg-js',
            RCH_PLUGIN_URL . 'build/index.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-api-fetch'),
            RCH_VERSION,
            true
        );
    }

    register_block_type('rch-rechat-plugin/off-market-block', array(
        'editor_script'   => 'rch-gutenberg-js',
        'attributes'      => array(
            'displayType'   => array('type' => 'string',  'default' => 'normal'),
            'status'        => array('type' => 'string',  'default' => ''),
            'limit'         => array('type' => 'number',  'default' => 6),
            'columns'       => array('type' => 'number',  'default' => 3),
            'orderby'       => array('type' => 'string',  'default' => 'date'),
            'order'         => array('type' => 'string',  'default' => 'DESC'),
            'title'         => array('type' => 'string',  'default' => ''),
            'spaceBetween'  => array('type' => 'number',  'default' => 24),
            'loop'          => array('type' => 'boolean', 'default' => true),
            'autoplay'      => array('type' => 'boolean', 'default' => false),
            'autoplayDelay' => array('type' => 'number',  'default' => 3500),
            'pagination'    => array('type' => 'boolean', 'default' => false),
        ),
        'render_callback' => 'rch_render_off_market_block',
    ));
}
add_action('init', 'rch_register_block_assets_off_market');

/**
 * Load Off Market front CSS inside the block editor so the ServerSideRender
 * preview is styled. The front handles register on wp_enqueue_scripts (front
 * only), so register + enqueue them directly here for the editor.
 */
function rch_enqueue_off_market_editor_assets()
{
    if (! defined('RCH_PLUGIN_ASSETS') || ! defined('RCH_VERSION')) {
        return;
    }
    if (! wp_style_is('rch-off-market', 'registered')) {
        wp_register_style('rch-off-market', RCH_PLUGIN_ASSETS . 'css/rch-off-market.css', array(), RCH_VERSION);
    }
    if (! wp_style_is('rch-swiper', 'registered')) {
        wp_register_style('rch-swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), '11');
    }
    wp_enqueue_style('rch-off-market');
    wp_enqueue_style('rch-swiper');
}
add_action('enqueue_block_editor_assets', 'rch_enqueue_off_market_editor_assets');

/**
 * Render callback → emits the [rch_off_market] shortcode.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function rch_render_off_market_block($attributes)
{
    $display_type = isset($attributes['displayType']) && strtolower((string) $attributes['displayType']) === 'swiper' ? 'swiper' : 'normal';
    $status       = isset($attributes['status']) ? sanitize_text_field((string) $attributes['status']) : '';
    $limit        = isset($attributes['limit']) ? (int) $attributes['limit'] : 6;
    $columns      = isset($attributes['columns']) ? max(1, (int) $attributes['columns']) : 3;
    $orderby      = isset($attributes['orderby']) ? strtolower((string) $attributes['orderby']) : 'date';
    $order        = isset($attributes['order']) && strtoupper((string) $attributes['order']) === 'ASC' ? 'ASC' : 'DESC';
    $title        = isset($attributes['title']) ? sanitize_text_field((string) $attributes['title']) : '';

    if (! in_array($orderby, array('date', 'price', 'title'), true)) {
        $orderby = 'date';
    }

    $shortcode  = '[rch_off_market';
    $shortcode .= ' display_type="' . esc_attr($display_type) . '"';
    if ($status !== '') {
        $shortcode .= ' status="' . esc_attr($status) . '"';
    }
    $shortcode .= ' limit="' . esc_attr($limit) . '"';
    $shortcode .= ' columns="' . esc_attr($columns) . '"';
    $shortcode .= ' orderby="' . esc_attr($orderby) . '"';
    $shortcode .= ' order="' . esc_attr($order) . '"';
    if ($title !== '') {
        $shortcode .= ' title="' . esc_attr($title) . '"';
    }

    // Grid-only pagination.
    if ($display_type === 'normal' && ! empty($attributes['pagination'])) {
        $shortcode .= ' pagination="true"';
    }

    // Swiper-only attributes.
    if ($display_type === 'swiper') {
        $space_between  = isset($attributes['spaceBetween']) ? max(0, (int) $attributes['spaceBetween']) : 24;
        $loop           = ! empty($attributes['loop']) ? 'true' : 'false';
        $autoplay       = ! empty($attributes['autoplay']) ? 'true' : 'false';
        $autoplay_delay = isset($attributes['autoplayDelay']) ? max(0, (int) $attributes['autoplayDelay']) : 3500;

        $shortcode .= ' space_between="' . esc_attr($space_between) . '"';
        $shortcode .= ' loop="' . esc_attr($loop) . '"';
        $shortcode .= ' autoplay="' . esc_attr($autoplay) . '"';
        $shortcode .= ' autoplay_delay="' . esc_attr($autoplay_delay) . '"';
    }

    $shortcode .= ']';

    return do_shortcode($shortcode);
}
