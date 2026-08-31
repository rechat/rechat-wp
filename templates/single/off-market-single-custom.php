<?php
/**
 * Single Off Market Listing template.
 *
 * Reuses the SAME listing detail template parts as the API-backed listing
 * detail page. The only difference is the data source: instead of the Rechat
 * API, `$listing_detail` is assembled from post meta by
 * rch_off_market_build_listing_detail(). No API dependency.
 *
 * Theme override: copy this file to your theme's `rechat/off-market-single-custom.php`.
 * The template parts themselves stay in the plugin so they keep updating.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

while (have_posts()) :
    the_post();

    $post_id = get_the_ID();

    // Build the API-shaped listing array from post meta (empty keys omitted).
    $listing_detail = rch_off_market_build_listing_detail($post_id);

    // Agent lookup — same contract the listing parts expect.
    $agent_api_id        = isset($listing_detail['list_agent']['id']) ? $listing_detail['list_agent']['id'] : '';
    $seller_agent_api_id = '';
    $agent_posts         = function_exists('rch_check_agent_exists') ? rch_check_agent_exists($agent_api_id) : array();
    $seller_agent_posts  = array();

    get_header();
    ?>

    <div class="container">
        <div id="primary" class="content-area rch-primary-content">
            <main id="main" class="site-main content-container site-container">
                <div id="rch-house-detail" class="rch-house-main-details rch-off-market-detail">

                    <?php
                    // Gallery (guards empty internally).
                    include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-gallery.php';
                    ?>

                    <?php
                    /**
                     * Header — off-market variant.
                     * Guards empty price / address so no empty container renders
                     * (the API header echoes price unconditionally).
                     */
                    $om_price   = isset($listing_detail['formatted']['price']['text']) ? trim((string) $listing_detail['formatted']['price']['text']) : '';
                    $om_address = isset($listing_detail['formatted']['full_address']['text']) ? trim((string) $listing_detail['formatted']['full_address']['text']) : '';
                    ?>
                    <?php if ($om_price !== '') : ?>
                        <div class="rch-single-price-house"><?php echo esc_html($om_price); ?></div>
                    <?php endif; ?>
                    <?php if ($om_address !== '') : ?>
                        <h1 class="rch-single-address"><?php echo esc_html($om_address); ?></h1>
                    <?php elseif (get_the_title()) : ?>
                        <h1 class="rch-single-address"><?php echo esc_html(get_the_title()); ?></h1>
                    <?php endif; ?>

                    <div class="rch-single-house-main-layout">
                        <div class="rch-single-left-main-layout">

                            <?php include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-summary.php'; ?>
                            <?php include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-description.php'; ?>
                            <?php include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-open-houses.php'; ?>
                            <?php include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-features.php'; ?>
                            <?php include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-agents.php'; ?>

                        </div>

                        <div class="rch-single-right-main-layout">
                            <?php include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-contact-form.php'; ?>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <?php include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-modal.php'; ?>

    <?php get_footer(); ?>

    <?php include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-scripts.php'; ?>

<?php endwhile; ?>
