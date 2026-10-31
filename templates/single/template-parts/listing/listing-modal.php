<?php
/**
 * Template part for the full-screen photo lightbox (Swiper)
 *
 * Markup:
 * - #myModal.rch-imgs-modal.rch-lightbox  (dialog; opened by any [data-slider] in .rch-top-img-slider)
 * - .rch-lightbox__bar     address, "current / total" counter, close button
 * - .rch-lightbox__stage   main Swiper (.rch-lightbox__main, pinch/double-tap zoom) + prev/next buttons
 * - .rch-lightbox__thumbs  thumbnail Swiper (Swiper thumbs module)
 * Behaviour (open/close, keyboard, scroll lock, preloading) lives in listing-scripts.php.
 *
 * @package Rechat_Plugin
 * @var array $listing_detail The listing detail array containing all property information
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Ensure $listing_detail is available
if (!isset($listing_detail) || !is_array($listing_detail)) {
    return;
}

$rch_modal_gallery_urls = isset($listing_detail['gallery_image_urls']) && is_array($listing_detail['gallery_image_urls'])
    ? array_values($listing_detail['gallery_image_urls'])
    : [];
$rch_modal_gallery_total = count($rch_modal_gallery_urls);

if ($rch_modal_gallery_total === 0) {
    return;
}

$rch_modal_title = isset($listing_detail['formatted']['full_address']['text'])
    ? (string) $listing_detail['formatted']['full_address']['text']
    : '';
?>

<div id="myModal" class="rch-imgs-modal rch-lightbox" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Photo gallery', 'rechat-plugin'); ?>">
    <div class="rch-lightbox__bar">
        <div class="rch-lightbox__title"><?php echo esc_html($rch_modal_title); ?></div>
        <div class="rch-lightbox__count" aria-live="polite">
            <span class="rch-lightbox__current">1</span>
            <span class="rch-lightbox__sep">/</span>
            <span class="rch-lightbox__total"><?php echo (int) $rch_modal_gallery_total; ?></span>
        </div>
        <button type="button" class="rch-lightbox__close" aria-label="<?php echo esc_attr__('Close gallery', 'rechat-plugin'); ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    <div class="rch-lightbox__stage">
        <div class="swiper rch-lightbox__main">
            <div class="swiper-wrapper">
                <?php foreach ($rch_modal_gallery_urls as $rch_modal_i => $attachment_url) : ?>
                    <div class="swiper-slide">
                        <div class="swiper-zoom-container">
                            <img
                                src="<?php echo esc_url($attachment_url); ?>"
                                alt="<?php echo esc_attr(rch_listing_gallery_image_alt($listing_detail, $rch_modal_i + 1, $rch_modal_gallery_total)); ?>"
                                width="1600"
                                height="1067"
                                decoding="async"
                                loading="lazy"
                                draggable="false"
                            >
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="button" class="rch-lightbox__nav rch-lightbox__nav--prev" aria-label="<?php echo esc_attr__('Previous photo', 'rechat-plugin'); ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7"/></svg>
        </button>
        <button type="button" class="rch-lightbox__nav rch-lightbox__nav--next" aria-label="<?php echo esc_attr__('Next photo', 'rechat-plugin'); ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    <div class="swiper rch-lightbox__thumbs" aria-hidden="true">
        <div class="swiper-wrapper">
            <?php foreach ($rch_modal_gallery_urls as $rch_modal_t => $attachment_url) : ?>
                <div class="swiper-slide">
                    <img
                        src="<?php echo esc_url($attachment_url); ?>"
                        alt=""
                        width="160"
                        height="107"
                        decoding="async"
                        loading="lazy"
                        draggable="false"
                    >
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
