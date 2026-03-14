<?php
/**
 * Template part for displaying listing features and amenities
 * 
 * This template displays:
 * - Facilities and Features (with icons): bedrooms, bathrooms, sqft, year built, pool, security, parking, construction
 * - Amenities & Utilities: heating/cooling, utilities
 * - Interior Features: interior features, flooring, security, fireplace, appliances
 * - Exterior Features: construction materials, roof, lot features, architectural style, foundation, fencing
 * - Parking: parking spaces, parking features, covered parking
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
?>

<?php
// Check if Facilities and Features section has any data
$has_bedroom = isset($listing_detail['formatted']['bedroom_count']['text']) && strlen(trim((string) $listing_detail['formatted']['bedroom_count']['text'])) > 0;
$has_bathroom = isset($listing_detail['formatted']['bathrooms']['text']) && strlen(trim((string) $listing_detail['formatted']['bathrooms']['text'])) > 0;
$has_sqft = isset($listing_detail['formatted']['square_feet']['text']) && strlen(trim((string) $listing_detail['formatted']['square_feet']['text'])) > 0;
$has_year = isset($listing_detail['property']['year_built']) && strlen(trim((string) $listing_detail['property']['year_built'])) > 0;
$has_pool = !empty($listing_detail['property']['pool_features']);
$has_security = !empty($listing_detail['property']['security_features']);
$has_parking = isset($listing_detail['formatted']['parking_spaces']['text']) && strlen(trim((string) $listing_detail['formatted']['parking_spaces']['text'])) > 0;
$has_construction = isset($listing_detail['property']['construction_materials']) && strlen(trim((string) $listing_detail['property']['construction_materials'])) > 0;

if ($has_bedroom || $has_bathroom || $has_sqft || $has_year || $has_pool || $has_security || $has_parking || $has_construction) :
?>
    <div class="facilities-in-single-houses" id="rch-facilities">
        <h2>
            Facilities and Features
        </h2>
        <ul>
            <?php // Bedrooms
            if (isset($listing_detail['formatted']['bedroom_count']['text']) && strlen(trim((string) $listing_detail['formatted']['bedroom_count']['text'])) > 0) : ?>
                <li>
                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'bedroomsingle.svg'); ?>" alt="Bedroom icon">
                    <?php echo esc_html($listing_detail['formatted']['bedroom_count']['text']); ?>
                </li>
            <?php endif; ?>

            <?php // Bathrooms
            if (isset($listing_detail['formatted']['bathrooms']['text']) && strlen(trim((string) $listing_detail['formatted']['bathrooms']['text'])) > 0) : ?>
                <li>
                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'fbathsingle.svg'); ?>" alt="Fullbath icon">
                    <?php echo esc_html($listing_detail['formatted']['bathrooms']['text']); ?>
                </li>
            <?php endif; ?>

            <?php // Area / square feet
            if (isset($listing_detail['formatted']['square_feet']['text']) && strlen(trim((string) $listing_detail['formatted']['square_feet']['text'])) > 0) : ?>
                <li>
                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'areasingle.svg'); ?>" alt="Area icon">
                    <?php echo esc_html($listing_detail['formatted']['square_feet']['text']); ?>
                </li>
            <?php endif; ?>

            <?php // Year built
            if (isset($listing_detail['property']['year_built']) && strlen(trim((string) $listing_detail['property']['year_built'])) > 0) : ?>
                <li>
                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'yearsingle.svg'); ?>" alt="">
                    <?php echo esc_html($listing_detail['property']['year_built']); ?>
                    Year Built
                </li>
            <?php endif; ?>

            <?php // Pool features
            if (!empty($listing_detail['property']['pool_features'])) : ?>
                <li>
                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'poolsingle.svg'); ?>" alt=" Pool icon">
                    Pool
                </li>
            <?php endif; ?>

            <?php // Security features
            if (!empty($listing_detail['property']['security_features'])) : ?>
                <li>
                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'securitysingle.svg'); ?>" alt="Security Icon">
                    Security
                </li>
            <?php endif; ?>

            <?php // Parking spaces
            if (isset($listing_detail['formatted']['parking_spaces']['text']) && strlen(trim((string) $listing_detail['formatted']['parking_spaces']['text'])) > 0) : ?>
                <li>
                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'garagesingle.svg'); ?>" alt="Garage Icon">
                    <?php echo esc_html($listing_detail['formatted']['parking_spaces']['text']); ?>
                </li>
            <?php endif; ?>

            <?php // Construction materials
            if (isset($listing_detail['property']['construction_materials']) && strlen(trim((string) $listing_detail['property']['construction_materials'])) > 0) : ?>
                <li>
                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'outdoor-activity.svg'); ?>" alt="Activity icon">
                    <?php echo esc_html($listing_detail['property']['construction_materials']); ?>
                </li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// Check if Amenities & Utilities section has any data
$has_heating = isset($listing_detail['property']['heating']) && !empty($listing_detail['property']['heating']);
$has_utilities = isset($listing_detail['property']['utilities']) && !empty($listing_detail['property']['utilities']);

if ($has_heating || $has_utilities) :
?>
    <div class="facilities-in-single-houses" id="rch-amenities">
        <h2>
            Amenities & Utilities
        </h2>
        <ul class="rch_all_listing_data_features">
            <?php
            // Heating/Cooling
            if (isset($listing_detail['property']['heating']) && !empty($listing_detail['property']['heating'])) :
                $heating = $listing_detail['property']['heating'];
                // Handle arrays - join with comma and space
                if (is_array($heating)) {
                    $heating = implode(', ', array_filter($heating));
                }
                if (!empty($heating)) :
            ?>
                    <li>
                        <span>Heating/Cooling</span>
                        <span><?php echo esc_html($heating); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Utilities
            if (isset($listing_detail['property']['utilities']) && !empty($listing_detail['property']['utilities'])) :
                $utilities = $listing_detail['property']['utilities'];
                // Handle arrays - join with comma and space
                if (is_array($utilities)) {
                    $utilities = implode(', ', array_filter($utilities));
                }
                if (!empty($utilities)) :
            ?>
                    <li>
                        <span>Others</span>
                        <span><?php echo esc_html($utilities); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// Check if Interior Features section has any data
$has_interior = isset($listing_detail['property']['interior_features']) && !empty($listing_detail['property']['interior_features']);
$has_flooring = isset($listing_detail['property']['flooring']) && !empty($listing_detail['property']['flooring']);
$has_security = isset($listing_detail['property']['security_features']) && !empty($listing_detail['property']['security_features']);
$has_fireplace = isset($listing_detail['property']['fireplace_features']) && !empty($listing_detail['property']['fireplace_features']);
$has_appliances = isset($listing_detail['property']['appliances']) && !empty($listing_detail['property']['appliances']);

if ($has_interior || $has_flooring || $has_security || $has_fireplace || $has_appliances) :
?>
    <div class="facilities-in-single-houses" id="rch-interior">
        <h2>
            Interior Features
        </h2>
        <ul class="rch_all_listing_data_features">
            <?php
            // Interior Features
            if (isset($listing_detail['property']['interior_features']) && !empty($listing_detail['property']['interior_features'])) :
                $interior_features = $listing_detail['property']['interior_features'];
                // Handle arrays - join with comma and space
                if (is_array($interior_features)) {
                    $interior_features = implode(', ', array_filter($interior_features));
                }
                if (!empty($interior_features)) :
            ?>
                    <li>
                        <span>Interior</span>
                        <span><?php echo esc_html($interior_features); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Flooring
            if (isset($listing_detail['property']['flooring']) && !empty($listing_detail['property']['flooring'])) :
                $flooring = $listing_detail['property']['flooring'];
                // Handle arrays - join with comma and space
                if (is_array($flooring)) {
                    $flooring = implode(', ', array_filter($flooring));
                }
                if (!empty($flooring)) :
            ?>
                    <li>
                        <span>Flooring</span>
                        <span><?php echo esc_html($flooring); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Alarm/Security
            if (isset($listing_detail['property']['security_features']) && !empty($listing_detail['property']['security_features'])) :
                $security_features = $listing_detail['property']['security_features'];
                // Handle arrays - join with comma and space
                if (is_array($security_features)) {
                    $security_features = implode(', ', array_filter($security_features));
                }
                if (!empty($security_features)) :
            ?>
                    <li>
                        <span>Alarm/Security</span>
                        <span><?php echo esc_html($security_features); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Fireplace Features
            if (isset($listing_detail['property']['fireplace_features']) && !empty($listing_detail['property']['fireplace_features'])) :
                $fireplace_features = $listing_detail['property']['fireplace_features'];
                // Handle arrays - join with comma and space
                if (is_array($fireplace_features)) {
                    $fireplace_features = implode(', ', array_filter($fireplace_features));
                }
                if (!empty($fireplace_features)) :
            ?>
                    <li>
                        <span>Fireplace</span>
                        <span><?php echo esc_html($fireplace_features); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Appliances
            if (isset($listing_detail['property']['appliances']) && !empty($listing_detail['property']['appliances'])) :
                $appliances = $listing_detail['property']['appliances'];
                // Handle arrays - join with comma and space
                if (is_array($appliances)) {
                    $appliances = implode(', ', array_filter($appliances));
                }
                if (!empty($appliances)) :
            ?>
                    <li>
                        <span>Appliances</span>
                        <span><?php echo esc_html($appliances); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// Check if Exterior Features section has any data
$has_construction = isset($listing_detail['property']['construction_materials']) && !empty($listing_detail['property']['construction_materials']);
$has_roof = isset($listing_detail['property']['roof']) && !empty($listing_detail['property']['roof']);
$has_lot_features = isset($listing_detail['property']['lot_features']) && !empty($listing_detail['property']['lot_features']);
$has_architectural_style = isset($listing_detail['property']['architectural_style']) && !empty($listing_detail['property']['architectural_style']);
$has_foundation = isset($listing_detail['property']['foundation_details']) && !empty($listing_detail['property']['foundation_details']);
$has_fencing = isset($listing_detail['property']['fencing']) && !empty($listing_detail['property']['fencing']);

if ($has_construction || $has_roof || $has_lot_features || $has_architectural_style || $has_foundation || $has_fencing) :
?>
    <div class="facilities-in-single-houses" id="rch-amenities">
        <h2>
            Exterior Features
        </h2>
        <ul class="rch_all_listing_data_features">
            <?php
            // Construction Materials
            if (isset($listing_detail['property']['construction_materials']) && !empty($listing_detail['property']['construction_materials'])) :
                $construction_materials = $listing_detail['property']['construction_materials'];
                // Handle arrays - join with comma and space
                if (is_array($construction_materials)) {
                    $construction_materials = implode(', ', array_filter($construction_materials));
                }
                if (!empty($construction_materials)) :
            ?>
                    <li>
                        <span>Construction Materials</span>
                        <span><?php echo esc_html($construction_materials); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Roof
            if (isset($listing_detail['property']['roof']) && !empty($listing_detail['property']['roof'])) :
                $roof = $listing_detail['property']['roof'];
                // Handle arrays - join with comma and space
                if (is_array($roof)) {
                    $roof = implode(', ', array_filter($roof));
                }
                if (!empty($roof)) :
            ?>
                    <li>
                        <span>Roof</span>
                        <span><?php echo esc_html($roof); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Lot Features
            if (isset($listing_detail['property']['lot_features']) && !empty($listing_detail['property']['lot_features'])) :
                $lot_features = $listing_detail['property']['lot_features'];
                // Handle arrays - join with comma and space
                if (is_array($lot_features)) {
                    $lot_features = implode(', ', array_filter($lot_features));
                }
                if (!empty($lot_features)) :
            ?>
                    <li>
                        <span>Lot Features</span>
                        <span><?php echo esc_html($lot_features); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Architectural Style
            if (isset($listing_detail['property']['architectural_style']) && !empty($listing_detail['property']['architectural_style'])) :
                $architectural_style = $listing_detail['property']['architectural_style'];
                // Handle arrays - join with comma and space
                if (is_array($architectural_style)) {
                    $architectural_style = implode(', ', array_filter($architectural_style));
                }
                if (!empty($architectural_style)) :
            ?>
                    <li>
                        <span>Architectural Style</span>
                        <span><?php echo esc_html($architectural_style); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Foundation Details
            if (isset($listing_detail['property']['foundation_details']) && !empty($listing_detail['property']['foundation_details'])) :
                $foundation_details = $listing_detail['property']['foundation_details'];
                // Handle arrays - join with comma and space
                if (is_array($foundation_details)) {
                    $foundation_details = implode(', ', array_filter($foundation_details));
                }
                if (!empty($foundation_details)) :
            ?>
                    <li>
                        <span>Foundation</span>
                        <span><?php echo esc_html($foundation_details); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Fencing
            if (isset($listing_detail['property']['fencing']) && !empty($listing_detail['property']['fencing'])) :
                $fencing = $listing_detail['property']['fencing'];
                // Handle arrays - join with comma and space
                if (is_array($fencing)) {
                    $fencing = implode(', ', array_filter($fencing));
                }
                if (!empty($fencing)) :
            ?>
                    <li>
                        <span>Fencing</span>
                        <span><?php echo esc_html($fencing); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// Check if Parking section has any data
$has_parking_spaces = isset($listing_detail['formatted']['parking_spaces']['text_no_label']) && !empty($listing_detail['formatted']['parking_spaces']['text_no_label']);
$has_parking_features = isset($listing_detail['property']['parking_features']) && !empty($listing_detail['property']['parking_features']);
$has_covered_parking = isset($listing_detail['property']['parking_spaces_covered_total']) && !empty($listing_detail['property']['parking_spaces_covered_total']);
$has_total_parking = isset($listing_detail['property']['number_of_parking_spaces']) && !empty($listing_detail['property']['number_of_parking_spaces']);

if ($has_parking_spaces || $has_parking_features || $has_covered_parking || $has_total_parking) :
?>
    <div class="facilities-in-single-houses" id="rch-amenities">
        <h2>
            Parking
        </h2>
        <ul class="rch_all_listing_data_features">
            <?php
            // Parking spaces
            if (isset($listing_detail['formatted']['parking_spaces']['text_no_label']) && !empty($listing_detail['formatted']['parking_spaces']['text_no_label'])) :
                $parking_spaces = $listing_detail['formatted']['parking_spaces']['text_no_label'];
                // Handle arrays - join with comma and space
                if (is_array($parking_spaces)) {
                    $parking_spaces = implode(', ', array_filter($parking_spaces));
                }
                if (!empty($parking_spaces)) :
            ?>
                    <li>
                        <span>Parking Spaces</span>
                        <span><?php echo esc_html($parking_spaces); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Parking features
            if (isset($listing_detail['property']['parking_features']) && !empty($listing_detail['property']['parking_features'])) :
                $parking_features = $listing_detail['property']['parking_features'];
                // Handle arrays - join with comma and space
                if (is_array($parking_features)) {
                    $parking_features = implode(', ', array_filter($parking_features));
                }
                if (!empty($parking_features)) :
            ?>
                    <li>
                        <span>Parking Features</span>
                        <span><?php echo esc_html($parking_features); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php
            // Covered Parking Spaces
            if (isset($listing_detail['property']['parking_spaces_covered_total']) && !empty($listing_detail['property']['parking_spaces_covered_total'])) :
            ?>
                <li>
                    <span>Covered Parking Spaces</span>
                    <span><?php echo esc_html($listing_detail['property']['parking_spaces_covered_total']); ?></span>
                </li>
            <?php
            endif;
            ?>
        </ul>
    </div>
<?php endif; ?>
