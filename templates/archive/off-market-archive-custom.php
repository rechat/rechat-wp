<?php

/**
 * Off Market archive template.
 *
 * Static, server-rendered cards (no Rechat SDK / API). Each card links to the
 * single Off Market listing. Every field is guarded so empties render nothing.
 *
 * Theme override: copy to your theme's `rechat/off-market-archive-custom.php`.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$archive_title = post_type_archive_title('', false);
?>

<div id="primary" class="content-area rch-primary-content">
<div class="container">
        <main id="main" class="site-main content-container site-container rch-off-market-archive">
            <h1 class="rch-off-market-archive__title"><?php echo esc_html($archive_title ? $archive_title : __('Off Market', 'rechat-plugin')); ?></h1>

        <?php if (have_posts()) : ?>
            <ul class="rch-off-market-grid">
                <?php
                while (have_posts()) :
                    the_post();
                    $pid = get_the_ID();

                    // --- Card data (guarded) ---
                    $gallery = rch_off_market_gallery_urls($pid);
                    $cover   = ! empty($gallery) ? $gallery[0] : '';

                    $price_raw = rch_off_market_meta($pid, 'price');
                    $price = '';
                    if ($price_raw !== '' && is_numeric(str_replace(',', '', $price_raw))) {
                        $sym = rch_off_market_meta($pid, 'currency');
                        $sym = $sym !== '' ? $sym : '$';
                        $price = $sym . number_format(floatval(str_replace(',', '', $price_raw)));
                    }

                    // Beds · Baths · Sqft (only present parts)
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
                ?>
                    <li class="rch-off-market-card">
                        <a class="rch-off-market-card__link" href="<?php the_permalink(); ?>">
                            <div class="rch-off-market-card__media">
                                <?php if ($cover !== '') : ?>
                                    <img src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy" />
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
                                    <div class="rch-off-market-card__address"><?php echo esc_html(get_the_title()); ?></div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>

            <?php
            the_posts_pagination(array(
                'mid_size'  => 2,
                'prev_text' => '‹',
                'next_text' => '›',
                'screen_reader_text' => __('Off Market navigation', 'rechat-plugin'),
            ));
            ?>

        <?php else : ?>
            <p class="rch-off-market-empty"><?php esc_html_e('No off market listings found.', 'rechat-plugin'); ?></p>
        <?php endif; ?>
    </main><!-- #main -->
</div>
</div><!-- #primary -->

<?php
get_footer();
