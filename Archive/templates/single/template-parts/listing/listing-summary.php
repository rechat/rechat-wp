<?php
/**
 * Listing Summary Template Part
 * 
 * Displays the property key details (bedrooms, bathrooms, sqft, etc.)
 * 
 * @package Rechat
 * @param array $listing_detail The listing data
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rch-formatted-data-summary">
    <ul>
        <?php // Bedroom count
        if (isset($listing_detail['formatted']['bedroom_count']['text_no_label']) && strlen(trim((string) $listing_detail['formatted']['bedroom_count']['text_no_label'])) > 0) : ?>
            <li>
                <b><?php echo esc_html($listing_detail['formatted']['bedroom_count']['text_no_label']); ?></b>
                <span>Total Bedrooms</span>
            </li>
        <?php endif; ?>

        <?php // Bathroom count
        if (isset($listing_detail['formatted']['total_bathroom_count']['text_no_label']) && strlen(trim((string) $listing_detail['formatted']['total_bathroom_count']['text_no_label'])) > 0) : ?>
            <li>
                <b><?php echo esc_html($listing_detail['formatted']['total_bathroom_count']['text_no_label']); ?></b>
                <span>Total Bathrooms</span>
            </li>
        <?php endif; ?>

        <?php // Year built
        if (isset($listing_detail['property']['year_built']) && strlen(trim((string) $listing_detail['property']['year_built'])) > 0) : ?>
            <li>
                <b><?php echo esc_html($listing_detail['property']['year_built']); ?></b>
                <span>Year Built</span>
            </li>
        <?php endif; ?>

        <?php 
        // Lot size - show acres if lot size (sqft) > 43,560
        $show_lot = false;
        $lot_sqft_value = null;
        if (isset($listing_detail['formatted']['lot_size_square_feet']['value']) && is_numeric($listing_detail['formatted']['lot_size_square_feet']['value'])) {
            $lot_sqft_value = floatval($listing_detail['formatted']['lot_size_square_feet']['value']);
        } elseif (isset($listing_detail['formatted']['lot_size_square_feet']['text_no_label']) && strlen(trim((string) $listing_detail['formatted']['lot_size_square_feet']['text_no_label'])) > 0) {
            $raw = $listing_detail['formatted']['lot_size_square_feet']['text_no_label'];
            $raw_digits = preg_replace('/[^0-9\.]/', '', str_replace(',', '', $raw));
            if (strlen($raw_digits) > 0) {
                $lot_sqft_value = floatval($raw_digits);
            }
        }

        if ($lot_sqft_value !== null) {
            if ($lot_sqft_value > 43560 && isset($listing_detail['formatted']['lot_size_acres']['text_no_label']) && strlen(trim((string) $listing_detail['formatted']['lot_size_acres']['text_no_label'])) > 0) {
                $show_lot = 'acres';
            } else {
                $show_lot = 'sqft';
            }
        } else {
            if (isset($listing_detail['formatted']['lot_size_square_feet']['text_no_label']) && strlen(trim((string) $listing_detail['formatted']['lot_size_square_feet']['text_no_label'])) > 0) {
                $show_lot = 'sqft';
            }
        }

        if ($show_lot === 'acres') : ?>
            <li>
                <b><?php echo esc_html($listing_detail['formatted']['lot_size_acres']['text_no_label']); ?></b>
                <span>Lot size/Acres</span>
            </li>
        <?php elseif ($show_lot === 'sqft') : ?>
            <li>
                <b><?php echo esc_html($listing_detail['formatted']['lot_size_square_feet']['text_no_label']); ?></b>
                <span>Lot size/SQ.FT</span>
            </li>
        <?php endif; ?>

        <?php // Living space
        if (isset($listing_detail['formatted']['square_feet']['text_no_label']) && strlen(trim((string) $listing_detail['formatted']['square_feet']['text_no_label'])) > 0) : ?>
            <li>
                <b><?php echo esc_html($listing_detail['formatted']['square_feet']['text_no_label']); ?></b>
                <span>Living Space/SQ.FT</span>
            </li>
        <?php endif; ?>

        <?php
        // Calculate Price per SQ.FT
        $price_value = isset($listing_detail['formatted']['price']['value']) ? $listing_detail['formatted']['price']['value'] : null;
        $sqft_value = isset($listing_detail['formatted']['square_feet']['value']) ? $listing_detail['formatted']['square_feet']['value'] : null;

        if (is_numeric($price_value) && is_numeric($sqft_value) && floatval($sqft_value) > 0) :
            $pps = floatval($price_value) / floatval($sqft_value);
            $pps_text = '$' . number_format(round($pps));
        ?>
            <li>
                <b><?php echo esc_html($pps_text); ?></b>
                <span>Price/SQ.FT</span>
            </li>
        <?php endif; ?>
    </ul>
</div>
