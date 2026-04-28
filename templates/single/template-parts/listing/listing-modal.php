<?php
/**
 * Template part for displaying the image gallery modal with Swiper sliders
 * 
 * This template displays:
 * - Modal overlay container
 * - Swiper main image slider (rch-houses-mySwiper2) with navigation arrows
 * - Swiper thumbnail slider (rch-houses-mySwiper)
 * - Modal close button
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
    ? $listing_detail['gallery_image_urls']
    : [];
$rch_modal_gallery_total = count($rch_modal_gallery_urls);
?>

<div id="myModal" class="rch-imgs-modal">
    <!-- Modal content -->
    <div class="rch-modal-content">
        <span class="rch-img-modal-close">&times;</span>
        <div class="swiper rch-houses-mySwiper2">
            <div class="swiper-wrapper">
                <?php
                $rch_modal_i = 0;
                foreach ($rch_modal_gallery_urls as $attachment_url) {
                    ++$rch_modal_i;
                    ?>
                    <div class="swiper-slide">
                        <picture>
                            <img
                                src="<?php echo esc_url($attachment_url); ?>"
                                alt="<?php echo esc_attr(rch_listing_gallery_image_alt($listing_detail, $rch_modal_i, $rch_modal_gallery_total)); ?>"
                                width="1200"
                                height="800"
                                decoding="async"
                                loading="lazy"
                            >
                        </picture>
                    </div>
                <?php } ?>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
        <div thumbsSlider="" class="swiper rch-houses-mySwiper">
            <div class="swiper-wrapper">
                <?php
                $rch_modal_t = 0;
                foreach ($rch_modal_gallery_urls as $attachment_url) {
                    ++$rch_modal_t;
                    ?>
                    <div class="swiper-slide">
                        <picture>
                            <img
                                src="<?php echo esc_url($attachment_url); ?>"
                                alt="<?php echo esc_attr(rch_listing_gallery_image_alt($listing_detail, $rch_modal_t, $rch_modal_gallery_total)); ?>"
                                width="200"
                                height="150"
                                decoding="async"
                                loading="lazy"
                            >
                        </picture>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

</div>
