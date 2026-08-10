<?php

/**
 * Testimonials Shortcode
 *
 * Renders client testimonials via the Rechat SDK web component
 * (<rechat-root><rechat-testimonials>). brand_id comes from the site settings
 * (rch_rechat_brand_id) like every other Rechat web-component shortcode.
 *
 * Usage: [rch_testimonials limit="20" title="What our clients say"]
 *
 * SDK docs: https://sdk.rechat.com/documents/JavaScript_SDK.Testimonials.html
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Default shortcode attributes.
 *
 * @return array
 */
function rch_testimonials_get_defaults()
{
    return [
        'limit'      => '20',  // max testimonials the web component fetches
        'title'      => '',    // optional heading above the component
        'color_mode' => '',    // optional light|dark override for <rechat-root>
    ];
}

/**
 * Unique instance id (supports multiple shortcodes on one page).
 *
 * @return string
 */
function rch_testimonials_generate_id()
{
    static $instance_count = 0;
    $instance_count++;
    return 'rch-testimonials-' . $instance_count;
}

/**
 * [rch_testimonials] handler.
 *
 * @param array $atts
 * @return string
 */
function rch_display_testimonials_shortcode($atts)
{
    $atts = shortcode_atts(rch_testimonials_get_defaults(), $atts, 'rch_testimonials');

    // Ensure the Rechat SDK (which defines the web components) is loaded.
    if (function_exists('rch_register_rechat_sdk_assets')) {
        rch_register_rechat_sdk_assets();
    }
    wp_enqueue_style('rechat-sdk-css');
    wp_enqueue_script('rechat-sdk-js');

    $unique_id = rch_testimonials_generate_id();
    $limit     = max(1, (int) $atts['limit']);

    // brand_id from settings, like every other Rechat web-component shortcode.
    $brand = get_option('rch_rechat_brand_id');

    // Reuse the shared <rechat-root> attribute builder (brand_id + color-mode + theme).
    if (function_exists('rch_get_rechat_root_attributes')) {
        $root_attrs = rch_get_rechat_root_attributes(
            ['brand' => $brand, 'color_mode' => $atts['color_mode']],
            '',
            ''
        );
    } else {
        $root_attrs = 'brand_id="' . esc_attr((string) $brand) . '"';
    }

    ob_start();
    ?>
    <div id="<?php echo esc_attr($unique_id); ?>" class="rch-testimonials">
        <?php if ($atts['title'] !== '') : ?>
            <h2 class="rch-testimonials__title"><?php echo esc_html($atts['title']); ?></h2>
        <?php endif; ?>

        <rechat-root <?php echo $root_attrs; ?>>
            <rechat-testimonials limit="<?php echo (int) $limit; ?>"></rechat-testimonials>
        </rechat-root>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('rch_testimonials', 'rch_display_testimonials_shortcode');
