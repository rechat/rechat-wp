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
?>

<div class="rch-top-img-slider <?php echo $is_single_image ? 'rch-single-image-layout' : ''; ?>">
    <div class="rch-left-top-slider">
        <?php if ($has_images) : ?>
            <picture data-slider="0" id="myBtn">
                <img src="<?php echo esc_url($listing_detail['cover_image_url']); ?>" alt="Image of House">
            </picture>
            <button id="myBtn" data-slider="0" class="rch-load-images">
                <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'gallery.svg'); ?>" alt="gallery icon">
                View all Photos
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
                foreach (array_slice($listing_detail['gallery_image_urls'], 1, 4) as $image_url) :
            ?>
                    <picture data-slider="<?php echo esc_attr($i); ?>" id="myBtn">
                        <img src="<?php echo esc_url($image_url); ?>" alt="Gallery of House">
                    </picture>
            <?php
                    $i++;
                endforeach;
            }
            ?>
        </div>
    <?php endif; ?>
</div>
