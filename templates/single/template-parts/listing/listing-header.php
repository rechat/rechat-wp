<?php
/**
 * Listing Header Template Part
 * 
 * Displays the property price, address, and MLS number
 * 
 * @package Rechat
 * @param array $listing_detail The listing data
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rch-single-price-house">
    <?php echo sanitize_text_field($listing_detail['formatted']['price']['text']); ?>
</div>

<h1 class="rch-single-address">
    <?php
    // Check if address property exists
    if (isset($listing_detail['formatted']['full_address']['text'])) {
        $address = $listing_detail['formatted']['full_address']['text'];
        echo esc_html($address);

        // Display MLS number if available
        if (isset($listing_detail['mls_number']) && !empty($listing_detail['mls_number'])) {
            echo ' <span class="rch-mls-number">(MLS#: ' . esc_html($listing_detail['mls_number']) . ')</span>';
        }
    } else {
        echo '<p>Address information not available.</p>';
    }
    ?>
</h1>
