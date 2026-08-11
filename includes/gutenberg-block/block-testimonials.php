<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Testimonials Gutenberg block.
 * Editor renders via ServerSideRender; front-end output is the
 * [rch_testimonials] shortcode (Rechat SDK <rechat-testimonials> web component).
 ******************************/

function rch_register_block_assets_testimonials()
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

    register_block_type('rch-rechat-plugin/testimonials-block', array(
        'editor_script' => 'rch-gutenberg-js',
        'attributes'    => array(
            'limit'     => array('type' => 'number', 'default' => 0),
            'title'     => array('type' => 'string', 'default' => ''),
            'colorMode' => array('type' => 'string', 'default' => ''),
        ),
        'render_callback' => 'rch_render_testimonials_block',
    ));
}
add_action('init', 'rch_register_block_assets_testimonials');

/**
 * Render callback → emits the [rch_testimonials] shortcode.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function rch_render_testimonials_block($attributes)
{
    $limit      = isset($attributes['limit']) ? (int) $attributes['limit'] : 0;
    $title      = isset($attributes['title']) ? sanitize_text_field((string) $attributes['title']) : '';
    $color_mode = isset($attributes['colorMode']) ? strtolower((string) $attributes['colorMode']) : '';

    $shortcode = '[rch_testimonials';

    // Only pass limit when set (>0); otherwise the SDK shows all testimonials.
    if ($limit > 0) {
        $shortcode .= ' limit="' . esc_attr($limit) . '"';
    }
    if ($title !== '') {
        $shortcode .= ' title="' . esc_attr($title) . '"';
    }
    if ($color_mode === 'light' || $color_mode === 'dark') {
        $shortcode .= ' color_mode="' . esc_attr($color_mode) . '"';
    }

    $shortcode .= ']';

    return do_shortcode($shortcode);
}
