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
                    // Shared card renderer (also used by the [rch_off_market] shortcode).
                    rch_off_market_render_card(get_the_ID());
                endwhile;
                ?>
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
