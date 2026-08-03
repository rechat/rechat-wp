<?php

/**
 * Testimonials Shortcode
 *
 * Renders client testimonials pulled from the Rechat SDK (Testimonials service).
 * The SDK method is "portal-only": the portal resolves the brand from the current
 * domain, so no brand_id is passed here — data is fetched client-side in the browser.
 *
 * Usage: [rch_testimonials limit="20" columns="3" title="What our clients say" show_rating="true" show_avatar="true"]
 *
 * SDK docs: https://sdk.rechat.com/documents/JavaScript_SDK.Testimonials.html
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue testimonials assets — call only when the shortcode renders.
 * The Rechat SDK itself is enqueued globally on wp_enqueue_scripts (see enqueue-front.php).
 */
function rch_testimonials_enqueue_assets()
{
    wp_enqueue_style('rch-testimonials-shortcode');
    wp_enqueue_script('rch-testimonials-shortcode');
}

/**
 * Default shortcode attributes.
 *
 * @return array
 */
function rch_testimonials_get_defaults()
{
    return [
        'limit'       => '20',   // max testimonials to fetch (SDK `limit`)
        'start'       => '0',    // pagination cursor (SDK `start`)
        'columns'     => '3',    // grid columns on desktop
        'title'       => '',     // optional heading above the grid
        'show_rating' => 'true', // render star rating when present
        'show_avatar' => 'true', // render author initials avatar
        'empty_text'  => 'No testimonials yet.',
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

    rch_testimonials_enqueue_assets();

    $unique_id = rch_testimonials_generate_id();

    $limit       = max(1, (int) $atts['limit']);
    $start       = max(0, (int) $atts['start']);
    $columns     = min(6, max(1, (int) $atts['columns']));
    $show_rating = filter_var($atts['show_rating'], FILTER_VALIDATE_BOOLEAN);
    $show_avatar = filter_var($atts['show_avatar'], FILTER_VALIDATE_BOOLEAN);

    // Per-instance config consumed by rch-testimonials.js.
    $config = [
        'limit'      => $limit,
        'start'      => $start,
        'showRating' => $show_rating,
        'showAvatar' => $show_avatar,
        'emptyText'  => (string) $atts['empty_text'],
    ];

    ob_start();
    ?>
    <div
        id="<?php echo esc_attr($unique_id); ?>"
        class="rch-testimonials"
        data-rch-testimonials
        data-rch-testimonials-config="<?php echo esc_attr(wp_json_encode($config)); ?>"
        style="--rch-testimonials-columns: <?php echo (int) $columns; ?>;"
        data-rch-testimonials-state="loading"
    >
        <?php if ($atts['title'] !== '') : ?>
            <h2 class="rch-testimonials__title"><?php echo esc_html($atts['title']); ?></h2>
        <?php endif; ?>

        <div class="rch-testimonials__status" data-rch-testimonials-status>
            <span class="rch-testimonials__spinner" aria-hidden="true"></span>
            <span class="screen-reader-text"><?php esc_html_e('Loading testimonials…', 'rechat-plugin'); ?></span>
        </div>

        <div class="rch-testimonials__grid" data-rch-testimonials-grid hidden></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('rch_testimonials', 'rch_display_testimonials_shortcode');
