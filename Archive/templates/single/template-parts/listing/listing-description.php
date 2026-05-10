<?php
/**
 * Listing Description Template Part
 * 
 * Displays the property description with show more/less functionality
 * 
 * @package Rechat
 * @param array $listing_detail The listing data
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<?php if (!empty($listing_detail['property']['description'])) : ?>
    <div class="rch-main-description-single-house">
        <div class="main-des-single-house" id="rch-overview">
            <h2>About the Property</h2>
            <div class="rch-main-description-listing" id="rch-description-text">
                <?php echo wp_kses_post($listing_detail['property']['description']); ?>
            </div>
            <button class="rch-show-more-btn" id="rch-show-more-btn" style="display: none;">Show More</button>
        </div>
    </div>
<?php endif; ?>
