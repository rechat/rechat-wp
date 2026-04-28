<?php

if (! defined('ABSPATH')) {
    exit();
}

/**
 * Enqueue assets only when the search form shortcode is used (not site-wide).
 */
function rch_search_listing_form_enqueue_assets()
{
    wp_enqueue_style('rechat-sdk-css');
    wp_enqueue_script('rechat-sdk-js');
    wp_enqueue_style('rch-search-listing-shortcode');
    wp_enqueue_script('rch-search-listing-shortcode');
}

/**
 * Build `style` attribute for theme colors / optional background image (CSS variables).
 *
 * @param string $primary_color     Option `_rch_primary_color`
 * @param bool   $show_background   Whether background image is shown
 * @param string $background_image  Escaped URL
 * @return string HTML fragment starting with space + `style="..."` or empty
 */
function rch_search_listing_form_wrapper_style_attr($primary_color, $show_background, $background_image)
{
    $primary = ($primary_color !== '' && $primary_color !== null) ? $primary_color : '#2563eb';
    $on_primary = rch_get_contrast_text_color($primary);
    $parts = [
        '--rch-search-primary:' . $primary,
        '--rch-search-on-primary:' . $on_primary,
    ];

    if ($show_background && $background_image !== '') {
        $parts[] = '--rch-search-bg-image:url(' . esc_url($background_image) . ')';
    }

    return ' style="' . esc_attr(implode(';', $parts)) . '"';
}

/**
 * Reusable search form for listings (Rechat) — submits via GET to `target_page`.
 *
 * @param array $atts Shortcode attributes
 * @return string HTML
 */
function rch_search_listing_form_shortcode($atts)
{
    $atts = shortcode_atts([
        'target_page'               => '/listings/',
        'brand_id'                  => '',
        'map_zoom'                  => '',
        'map_api_key'               => get_option('rch_rechat_google_map_api_key'),
        'map_default_center'        => '',
        'filter_address'            => '',
        'disable_filter_price'      => 'false',
        'disable_filter_beds'       => 'false',
        'filter_minimum_price'      => '',
        'filter_minimum_bathrooms'  => '',
        'filter_minimum_bedrooms'   => '',
        'filter_maximum_bedrooms'   => '',
        'filter_maximum_year_built' => '',
        'filter_listing_statuses'   => '',
        'show_background'           => 'false',
        'background_image'          => '',
    ], $atts, 'rch_search_listing_form');

    rch_search_listing_form_enqueue_assets();

    $target_page = sanitize_text_field($atts['target_page']);
    $brand_id = sanitize_text_field($atts['brand_id']);
    if ($brand_id === '') {
        $brand_id = get_option('rch_rechat_brand_id');
    }

    $map_zoom = sanitize_text_field($atts['map_zoom']);
    $map_api_key = sanitize_text_field($atts['map_api_key']);
    $map_default_center = sanitize_text_field($atts['map_default_center']);
    $filter_address = sanitize_text_field($atts['filter_address']);
    $disable_filter_price = sanitize_text_field($atts['disable_filter_price']);
    $disable_filter_beds = sanitize_text_field($atts['disable_filter_beds']);
    $filter_minimum_price = sanitize_text_field($atts['filter_minimum_price']);
    $filter_minimum_bathrooms = sanitize_text_field($atts['filter_minimum_bathrooms']);
    $filter_minimum_bedrooms = sanitize_text_field($atts['filter_minimum_bedrooms']);
    $filter_maximum_bedrooms = sanitize_text_field($atts['filter_maximum_bedrooms']);
    $filter_maximum_year_built = sanitize_text_field($atts['filter_maximum_year_built']);
    $filter_listing_statuses = sanitize_text_field($atts['filter_listing_statuses']);
    $show_background = filter_var($atts['show_background'], FILTER_VALIDATE_BOOLEAN);
    $background_image = esc_url($atts['background_image']);

    $attributes = [
        'brand'                 => $brand_id,
        'map_zoom'              => $map_zoom,
        'map_api_key'           => $map_api_key,
        'filter_address'        => $filter_address,
        'minimum_price'         => $filter_minimum_price,
        'minimum_bathrooms'     => $filter_minimum_bathrooms,
        'minimum_bedrooms'      => $filter_minimum_bedrooms,
        'maximum_bedrooms'      => $filter_maximum_bedrooms,
        'maximum_year_built'    => $filter_maximum_year_built,
        'disable_filter_price'  => $disable_filter_price,
        'disable_filter_beds'   => $disable_filter_beds,
    ];

    $rechat_attrs = rch_get_rechat_root_attributes($attributes, $map_default_center, $filter_listing_statuses);
    $rechat_listings_attrs = rch_get_rechat_listings_attributes($attributes, $map_default_center, $filter_listing_statuses);

    $form_id = 'rch-search-form-' . uniqid('', false);
    $primary_color = get_option('_rch_primary_color');

    $redirect_base = esc_url(home_url($target_page));
    $wrapper_classes = ['rch-search-listing-form'];
    if ($show_background && $background_image !== '') {
        $wrapper_classes[] = 'rch-search-listing-form--with-bg';
    }
    $wrapper_style = rch_search_listing_form_wrapper_style_attr($primary_color, $show_background, $background_image);

    ob_start();
    ?>
    <div
        id="<?php echo esc_attr($form_id); ?>"
        class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>"
        data-search-redirect-base="<?php echo esc_attr($redirect_base); ?>"
        <?php
        echo $wrapper_style;
        ?>
    >
        <rechat-root <?php echo $rechat_attrs; ?>>
            <rechat-listings <?php echo $rechat_listings_attrs; ?>>
                <div class="container_listing_sdk rch-search-container">
                    <?php if ($show_background && $background_image !== '') : ?>
                        <div class="rch-search-background" aria-hidden="true"></div>
                    <?php endif; ?>
                    <div class="rch-search-inner">
                        <rechat-property-search-form />
                    </div>
                </div>
            </rechat-listings>
        </rechat-root>
    </div>
    <?php

    return ob_get_clean();
}

add_shortcode('rch_search_listing_form', 'rch_search_listing_form_shortcode');
