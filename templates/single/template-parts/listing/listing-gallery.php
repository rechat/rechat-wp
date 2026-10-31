<?php
/**
 * Listing Gallery Template Part
 * 
 * Displays the property image gallery with modal viewer
 * 
 * @package Rechat
 * @param array $listing_detail The listing data
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if we have images and determine layout
$has_images = is_array($listing_detail['gallery_image_urls']) && !empty($listing_detail['gallery_image_urls']);
$image_count = $has_images ? count($listing_detail['gallery_image_urls']) : 0;
$is_single_image = ($image_count === 1);
// Photos beyond the 5 shown in the grid (cover + 4 tiles) = "+N" badge on the last tile.
$rch_hidden_count = max(0, $image_count - 5);
$rch_view_all_label = $image_count > 1
    /* translators: %s: number of photos */
    ? sprintf(__('View all %s photos', 'rechat-plugin'), number_format_i18n($image_count))
    : __('View photo', 'rechat-plugin');
$rch_cover_alt = rch_listing_format_image_alt($listing_detail, __('Main listing photo', 'rechat-plugin'));
?>

<div class="rch-top-img-slider <?php echo $is_single_image ? 'rch-single-image-layout' : ''; ?>">
    <div class="rch-left-top-slider">
        <?php if ($has_images) : ?>
            <picture class="rch-gallery-open" data-slider="0" tabindex="0" role="button" aria-label="<?php echo esc_attr__('Open photo gallery', 'rechat-plugin'); ?>">
                <img
                    src="<?php echo esc_url($listing_detail['cover_image_url']); ?>"
                    alt="<?php echo esc_attr($rch_cover_alt); ?>"
                    width="1200"
                    height="800"
                    decoding="async"
                    fetchpriority="high"
                    loading="eager"
                >
            </picture>
            <button type="button" id="myBtn" data-slider="0" class="rch-load-images">
                <img
                    src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'gallery.svg'); ?>"
                    alt=""
                    width="24"
                    height="24"
                    decoding="async"
                    loading="lazy"
                    aria-hidden="true"
                >
                <?php echo esc_html($rch_view_all_label); ?>
            </button>
            <span class="<?php echo esc_attr($listing_detail['status']); ?>">
                <?php echo esc_html($listing_detail['status']); ?>
            </span>
        <?php endif; ?>
    </div>
    
    <?php if (!$is_single_image) : ?>
        <div class="rch-right-top-slider">
            <?php
            // Ensure the gallery_image_urls is an array and has values with more than 1 image
            if ($has_images && $image_count > 1) {
                // Loop through the first 4 images
                $i = 1;
                $rch_grid_urls = array_slice($listing_detail['gallery_image_urls'], 1, 4);
                $rch_grid_last = count($rch_grid_urls);
                foreach ($rch_grid_urls as $image_url) :
                    $rch_is_last_tile = ($i === $rch_grid_last && $rch_hidden_count > 0);
            ?>
                    <picture class="rch-gallery-open" data-slider="<?php echo esc_attr($i); ?>" tabindex="0" role="button" aria-label="<?php echo esc_attr(sprintf(/* translators: %d: photo number */ __('Open photo %d', 'rechat-plugin'), $i + 1)); ?>"<?php echo $rch_is_last_tile ? ' data-more="+' . (int) $rch_hidden_count . '"' : ''; ?>>
                        <img
                            src="<?php echo esc_url($image_url); ?>"
                            alt="<?php echo esc_attr(rch_listing_gallery_image_alt($listing_detail, $i + 1, $image_count)); ?>"
                            width="640"
                            height="480"
                            decoding="async"
                            loading="lazy"
                        >
                    </picture>
            <?php
                    $i++;
                endforeach;
            }
            ?>
        </div>
    <?php endif; ?>
</div>
