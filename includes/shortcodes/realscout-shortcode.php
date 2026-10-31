<?php

/**
 * RealScout Home Value Shortcode
 *
 * Renders RealScout's home value widget (<realscout-home-value>) with the same ID
 * resolution as the RealScout Home Value block: on an agent sub-site the agent's
 * RealScout number from the main site, otherwise the Main Website ID from
 * wp-admin/admin.php?page=realscout. Nothing is rendered when no ID is available.
 *
 * Usage: [rch_realscout_home_value title="What's my home worth?" subtitle="Free instant estimate"]
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Default shortcode attributes.
 *
 * Color / width attributes are the block's style settings in snake_case
 * (see rch_realscout_block_style_map()); empty = RealScout default.
 *
 * @return array
 */
function rch_realscout_shortcode_get_defaults()
{
    $defaults = [
        'title'         => '',      // heading rendered by the plugin instead of the widget's own title
        'subtitle'      => '',      // text rendered by the plugin instead of the widget's own subtitle
        'hide_title'    => '',      // "true" hides the widget's own title (automatic when `title` is set)
        'hide_subtitle' => '',      // "true" hides the widget's own subtitle (automatic when `subtitle` is set)
        'shadow_dom'    => 'true',  // "false" renders the widget without shadow DOM so theme CSS can style it
        'class'         => '',      // extra CSS class on the wrapper
    ];

    foreach (array_keys(rch_realscout_block_style_map()) as $key) {
        $defaults[rch_realscout_shortcode_snake_case($key)] = '';
    }

    return $defaults;
}

/**
 * backgroundColor → background_color (shortcode attributes are snake_case).
 *
 * @param string $camel
 * @return string
 */
function rch_realscout_shortcode_snake_case($camel)
{
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $camel));
}

/**
 * Shortcode handler.
 *
 * @param array|string $atts
 * @return string
 */
function rch_realscout_home_value_shortcode($atts)
{
    $atts = shortcode_atts(rch_realscout_shortcode_get_defaults(), $atts, 'rch_realscout_home_value');

    $title    = trim((string) $atts['title']);
    $subtitle = trim((string) $atts['subtitle']);

    $style_attributes = [];
    foreach (array_keys(rch_realscout_block_style_map()) as $key) {
        $style_attributes[$key] = (string) $atts[rch_realscout_shortcode_snake_case($key)];
    }

    $widget_attributes = [];
    if ($title !== '' || rch_realscout_shortcode_is_true($atts['hide_title'])) {
        $widget_attributes['remove-title'] = 'true';
    }
    if ($subtitle !== '' || rch_realscout_shortcode_is_true($atts['hide_subtitle'])) {
        $widget_attributes['remove-subtitle'] = 'true';
    }
    if (rch_realscout_shortcode_is_false($atts['shadow_dom'])) {
        $widget_attributes['disable-shadow-dom'] = 'true';
    }

    $widget = rch_realscout_widget_html($style_attributes, $widget_attributes);
    if ($widget === '') {
        return '';
    }

    $classes = 'rch-realscout-home-value rch-realscout-home-value--shortcode';
    if (trim((string) $atts['class']) !== '') {
        $classes .= ' ' . implode(' ', array_map('sanitize_html_class', preg_split('/\s+/', trim((string) $atts['class']))));
    }

    ob_start();
    ?>
    <div class="<?php echo esc_attr($classes); ?>">
        <?php if ($title !== '') : ?>
            <h2 class="rch-realscout-home-value__title"><?php echo wp_kses_post($title); ?></h2>
        <?php endif; ?>
        <?php if ($subtitle !== '') : ?>
            <p class="rch-realscout-home-value__subtitle"><?php echo wp_kses_post($subtitle); ?></p>
        <?php endif; ?>
        <?php echo $widget; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('rch_realscout_home_value', 'rch_realscout_home_value_shortcode');

/**
 * "true" / "1" / "yes" → true.
 *
 * @param mixed $value
 * @return bool
 */
function rch_realscout_shortcode_is_true($value)
{
    return in_array(strtolower(trim((string) $value)), ['true', '1', 'yes'], true);
}

/**
 * "false" / "0" / "no" → true.
 *
 * @param mixed $value
 * @return bool
 */
function rch_realscout_shortcode_is_false($value)
{
    return in_array(strtolower(trim((string) $value)), ['false', '0', 'no'], true);
}
