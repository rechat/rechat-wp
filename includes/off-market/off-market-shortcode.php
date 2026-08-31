<?php

/**
 * Off Market — reusable card renderer + [rch_off_market] shortcode.
 *
 * The card markup lives in ONE place (rch_off_market_render_card) shared by the
 * archive template and the shortcode, so both stay in sync.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit();
}

/**
 * Render a single Off Market card. Echoes markup.
 * Every field is guarded so empties render nothing.
 *
 * @param int|WP_Post $post
 * @param array       $args {
 *     @type string $extra_class Extra class on the <li> root (e.g. 'swiper-slide').
 * }
 * @return void
 */
function rch_off_market_render_card($post = null, $args = array())
{
    $post = get_post($post);
    if (! $post) {
        return;
    }
    $pid = $post->ID;
    $extra_class = isset($args['extra_class']) ? trim((string) $args['extra_class']) : '';
    $li_class = 'rch-off-market-card' . ($extra_class !== '' ? ' ' . $extra_class : '');

    $gallery = rch_off_market_gallery_urls($pid);
    $cover   = ! empty($gallery) ? $gallery[0] : '';

    $price_raw = rch_off_market_meta($pid, 'price');
    $price = '';
    if ($price_raw !== '' && is_numeric(str_replace(',', '', $price_raw))) {
        $sym = rch_off_market_meta($pid, 'currency');
        $sym = $sym !== '' ? $sym : '$';
        $price = $sym . number_format(floatval(str_replace(',', '', $price_raw)));
    }

    $facts = array();
    $beds = rch_off_market_meta($pid, 'bedrooms');
    if ($beds !== '') {
        $facts[] = $beds . ' ' . _n('Bed', 'Beds', (int) $beds, 'rechat-plugin');
    }
    $baths = rch_off_market_meta($pid, 'bathrooms');
    if ($baths !== '') {
        $facts[] = $baths . ' ' . ((float) $baths === 1.0 ? __('Bath', 'rechat-plugin') : __('Baths', 'rechat-plugin'));
    }
    $sqft = rch_off_market_meta($pid, 'square_feet');
    if ($sqft !== '' && is_numeric(str_replace(',', '', $sqft))) {
        $facts[] = number_format(floatval(str_replace(',', '', $sqft))) . ' ' . __('Sqft', 'rechat-plugin');
    }

    $address_line = rch_off_market_meta($pid, 'address_line');
    $locality = trim(implode(', ', array_filter(array(
        rch_off_market_meta($pid, 'city'),
        trim(rch_off_market_meta($pid, 'state') . ' ' . rch_off_market_meta($pid, 'postal_code')),
    ))));
    $status = rch_off_market_meta($pid, 'status');
    $permalink = get_permalink($pid);
    $title = get_the_title($pid);
    ?>
    <li class="<?php echo esc_attr($li_class); ?>">
        <a class="rch-off-market-card__link" href="<?php echo esc_url($permalink); ?>">
            <div class="rch-off-market-card__media">
                <?php if ($cover !== '') : ?>
                    <img src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" />
                <?php else : ?>
                    <span class="rch-off-market-card__noimg" aria-hidden="true"></span>
                <?php endif; ?>
                <?php if ($status !== '') : ?>
                    <span class="rch-off-market-card__status"><?php echo esc_html($status); ?></span>
                <?php endif; ?>
            </div>

            <div class="rch-off-market-card__body">
                <?php if ($price !== '') : ?>
                    <div class="rch-off-market-card__price"><?php echo esc_html($price); ?></div>
                <?php endif; ?>
                <?php if (! empty($facts)) : ?>
                    <div class="rch-off-market-card__facts"><?php echo esc_html(implode(' · ', $facts)); ?></div>
                <?php endif; ?>
                <?php if ($address_line !== '') : ?>
                    <div class="rch-off-market-card__address"><?php echo esc_html($address_line); ?></div>
                <?php endif; ?>
                <?php if ($locality !== '') : ?>
                    <div class="rch-off-market-card__locality"><?php echo esc_html($locality); ?></div>
                <?php endif; ?>
                <?php if ($address_line === '' && $locality === '') : ?>
                    <div class="rch-off-market-card__address"><?php echo esc_html($title); ?></div>
                <?php endif; ?>
            </div>
        </a>
    </li>
    <?php
}

/**
 * [rch_off_market] — grid of Off Market listings, filterable.
 *
 * Attributes:
 *   display_type   normal | swiper. Default normal (responsive grid).
 *   status         Comma list. Keywords (active, pending, sold, coming) or full
 *                  text ("Sold Privately"). Empty = all. e.g. status="sold"
 *   limit          Max listings. -1 = all. Default 6.
 *   columns        Grid columns / swiper slides per view (desktop). Default 3.
 *   orderby        date | price | title. Default date.
 *   order          ASC | DESC. Default DESC.
 *   title          Optional heading above the grid.
 *   space_between  Swiper gap in px. Default 24.
 *   loop           Swiper loop. Default true.
 *   autoplay       Swiper autoplay. Default false.
 *   autoplay_delay Autoplay delay ms. Default 3500.
 *
 * @param array $atts
 * @return string
 */
function rch_off_market_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'display_type'   => 'normal',
        'status'         => '',
        'limit'          => 6,
        'columns'        => 3,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'title'          => '',
        'space_between'  => 24,
        'loop'           => 'true',
        'autoplay'       => 'false',
        'autoplay_delay' => 3500,
    ), $atts, 'rch_off_market');

    $is_swiper = strtolower((string) $atts['display_type']) === 'swiper';

    // Assets (registered in enqueue-front.php).
    if (wp_style_is('rch-off-market', 'registered')) {
        wp_enqueue_style('rch-off-market');
    }
    if ($is_swiper) {
        if (wp_style_is('rch-swiper', 'registered')) {
            wp_enqueue_style('rch-swiper');
        }
        if (wp_script_is('rch-off-market-swiper', 'registered')) {
            wp_enqueue_script('rch-off-market-swiper'); // pulls in rch-swiper (Swiper lib)
        }
    }

    $query_args = array(
        'post_type'      => 'off_market',
        'post_status'    => 'publish',
        'posts_per_page' => (int) $atts['limit'],
        'no_found_rows'  => true,
    );

    // Order.
    $orderby = strtolower((string) $atts['orderby']);
    $order   = strtoupper((string) $atts['order']) === 'ASC' ? 'ASC' : 'DESC';
    if ($orderby === 'price') {
        $query_args['orderby']  = 'meta_value_num';
        $query_args['meta_key'] = 'price';
    } elseif ($orderby === 'title') {
        $query_args['orderby'] = 'title';
    } else {
        $query_args['orderby'] = 'date';
    }
    $query_args['order'] = $order;

    // Status filter (keyword or full text; comma list; OR match, case-insensitive LIKE).
    $status_raw = trim((string) $atts['status']);
    if ($status_raw !== '') {
        $meta_query = array('relation' => 'OR');
        foreach (array_filter(array_map('trim', explode(',', $status_raw))) as $token) {
            $meta_query[] = array(
                'key'     => 'status',
                'value'   => $token,
                'compare' => 'LIKE',
            );
        }
        if (count($meta_query) > 1) {
            $query_args['meta_query'] = $meta_query;
        }
    }

    $q = new WP_Query($query_args);

    ob_start();

    if ($q->have_posts()) {
        $columns = max(1, (int) $atts['columns']);
        $heading = $atts['title'] !== '' ? '<h2 class="rch-off-market-shortcode__title">' . esc_html($atts['title']) . '</h2>' : '';

        if ($is_swiper) {
            $data = sprintf(
                ' data-spv="%d" data-space="%d" data-loop="%s" data-autoplay="%s" data-autoplay-delay="%d"',
                $columns,
                max(0, (int) $atts['space_between']),
                (strtolower((string) $atts['loop']) === 'false' ? 'false' : 'true'),
                (strtolower((string) $atts['autoplay']) === 'true' ? 'true' : 'false'),
                max(0, (int) $atts['autoplay_delay'])
            );

            echo '<div class="rch-off-market-shortcode rch-off-market-swiper"' . $data . '>';
            echo $heading; // already escaped
            echo '<div class="swiper">';
            echo '<ul class="swiper-wrapper rch-off-market-swiper__wrapper">';
            while ($q->have_posts()) {
                $q->the_post();
                rch_off_market_render_card(get_the_ID(), array('extra_class' => 'swiper-slide'));
            }
            echo '</ul>';
            echo '<div class="swiper-button-prev"></div>';
            echo '<div class="swiper-button-next"></div>';
            echo '<div class="swiper-pagination"></div>';
            echo '</div>'; // .swiper
            echo '</div>'; // wrapper
        } else {
            $style = '--om-cols:' . $columns . ';';
            echo '<div class="rch-off-market-shortcode">';
            echo $heading; // already escaped
            echo '<ul class="rch-off-market-grid" style="' . esc_attr($style) . '">';
            while ($q->have_posts()) {
                $q->the_post();
                rch_off_market_render_card(get_the_ID());
            }
            echo '</ul>';
            echo '</div>';
        }
        wp_reset_postdata();
    } else {
        echo '<p class="rch-off-market-empty">' . esc_html__('No off market listings found.', 'rechat-plugin') . '</p>';
    }

    return ob_get_clean();
}
add_shortcode('rch_off_market', 'rch_off_market_shortcode');
