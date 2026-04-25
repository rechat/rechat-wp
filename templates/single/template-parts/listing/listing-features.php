<?php
/**
 * Template part for displaying listing features and amenities
 * 
 * This template displays:
 * - Facilities and Features (with icons): bedrooms, bathrooms, sqft, year built, security, parking, construction
 * - Property type: MLS property type and subtype when present
 * - Amenities & Utilities: heating/cooling, pool, utilities, utilities other, community amenities, green certs
 * - Interior Features: interior, flooring, security, fireplace, appliances, fireplace count, dining/living areas, basement beds, furnished
 * - Exterior Features: exterior feature list, construction, roof, lot features, stories, lot size/dimensions, style, foundation, fencing, fenced yard
 * - Parking: spaces, features, covered, garage dimensions, total spaces from property
 * - Schools & community: subdivision, district, school names when present
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

if (!function_exists('rch_listing_features_format_value')) {
    /**
     * Normalize API property values (scalar, array, boolean) for feature rows.
     *
     * @param mixed $value Raw property value.
     * @return string Non-empty string to display, or empty string when nothing to show.
     */
    function rch_listing_features_format_value($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            $parts = array_filter(array_map(function ($v) {
                if ($v === null || $v === '') {
                    return '';
                }
                return is_scalar($v) ? (string) $v : '';
            }, $value));

            return implode(', ', $parts);
        }
        if (is_numeric($value)) {
            return trim((string) $value);
        }

        return trim((string) $value);
    }
}

$rch_prop = isset($listing_detail['property']) && is_array($listing_detail['property'])
    ? $listing_detail['property']
    : array();
?>

<?php
// Check if Facilities and Features section has any data
$has_bedroom = isset($listing_detail['formatted']['bedroom_count']['text']) && strlen(trim((string) $listing_detail['formatted']['bedroom_count']['text'])) > 0;
$has_bathroom = isset($listing_detail['formatted']['bathrooms']['text']) && strlen(trim((string) $listing_detail['formatted']['bathrooms']['text'])) > 0;
$has_sqft = isset($listing_detail['formatted']['square_feet']['text']) && strlen(trim((string) $listing_detail['formatted']['square_feet']['text'])) > 0;
$has_year = isset($listing_detail['property']['year_built']) && strlen(trim((string) $listing_detail['property']['year_built'])) > 0;
$has_security = !empty($listing_detail['property']['security_features']);
$has_parking = isset($listing_detail['formatted']['parking_spaces']['text']) && strlen(trim((string) $listing_detail['formatted']['parking_spaces']['text'])) > 0;
$has_construction = isset($listing_detail['property']['construction_materials']) && strlen(trim((string) $listing_detail['property']['construction_materials'])) > 0;

if ($has_bedroom || $has_bathroom || $has_sqft || $has_year || $has_security || $has_parking || $has_construction) :
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


        </ul>
    </div>
<?php endif; ?>

<?php
// Property type (MLS classification)
$has_property_type = strlen(rch_listing_features_format_value($rch_prop['property_type'] ?? null)) > 0;
$has_property_subtype = strlen(rch_listing_features_format_value($rch_prop['property_subtype'] ?? null)) > 0;

if ($has_property_type || $has_property_subtype) :
?>
    <div class="facilities-in-single-houses" id="rch-property-type">
        <h2>
            Property type
        </h2>
        <ul class="rch_all_listing_data_features">
            <?php if ($has_property_type) : ?>
                <li>
                    <span>Type</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['property_type'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_property_subtype) : ?>
                <li>
                    <span>Subtype</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['property_subtype'])); ?></span>
                </li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// Check if Amenities & Utilities section has any data
$has_heating = isset($listing_detail['property']['heating']) && !empty($listing_detail['property']['heating']);
$has_pool = isset($listing_detail['property']['pool_features']) && !empty($listing_detail['property']['pool_features']);
$has_utilities = isset($listing_detail['property']['utilities']) && !empty($listing_detail['property']['utilities']);
$has_utilities_other = strlen(rch_listing_features_format_value($rch_prop['utilities_other'] ?? null)) > 0;
$has_amenities = strlen(rch_listing_features_format_value($rch_prop['amenities'] ?? null)) > 0;
$has_green_cert = strlen(rch_listing_features_format_value($rch_prop['green_building_certification'] ?? null)) > 0;
$has_green_energy = strlen(rch_listing_features_format_value($rch_prop['green_energy_efficient'] ?? null)) > 0;
$has_pets_yn = array_key_exists('pets_yn', $rch_prop) && $rch_prop['pets_yn'] !== null && $rch_prop['pets_yn'] !== '';
$has_handicap_yn = array_key_exists('handicap_yn', $rch_prop) && $rch_prop['handicap_yn'] !== null && $rch_prop['handicap_yn'] !== '';
$has_appliances_yn = array_key_exists('appliances_yn', $rch_prop) && $rch_prop['appliances_yn'] !== null && $rch_prop['appliances_yn'] !== ''
    && empty($listing_detail['property']['appliances']);
$has_pool_yn = array_key_exists('pool_yn', $rch_prop) && $rch_prop['pool_yn'] !== null && $rch_prop['pool_yn'] !== '';

if ($has_heating || $has_pool || $has_pool_yn || $has_utilities || $has_utilities_other || $has_amenities || $has_green_cert || $has_green_energy || $has_pets_yn || $has_handicap_yn || $has_appliances_yn) :
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
            // Pool
            if (isset($listing_detail['property']['pool_features']) && !empty($listing_detail['property']['pool_features'])) :
                $pool_features = $listing_detail['property']['pool_features'];
                if (is_array($pool_features)) {
                    $pool_features = implode(', ', array_filter($pool_features));
                }
                if (!empty($pool_features)) :
            ?>
                    <li>
                        <span>Pool</span>
                        <span><?php echo esc_html($pool_features); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php if ($has_pool_yn) : ?>
                <li>
                    <span>Pool (MLS)</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['pool_yn'])); ?></span>
                </li>
            <?php endif; ?>
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
                        <span>Utilities</span>
                        <span><?php echo esc_html($utilities); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
            <?php if ($has_utilities_other) : ?>
                <li>
                    <span>Utilities (other)</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['utilities_other'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_amenities) : ?>
                <li>
                    <span>Community amenities</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['amenities'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_green_cert) : ?>
                <li>
                    <span>Green building certification</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['green_building_certification'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_green_energy) : ?>
                <li>
                    <span>Green / energy efficient</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['green_energy_efficient'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_pets_yn) : ?>
                <li>
                    <span>Pets allowed</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['pets_yn'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if (strlen(rch_listing_features_format_value($rch_prop['pets_policy'] ?? null)) > 0) : ?>
                <li>
                    <span>Pets policy</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['pets_policy'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_handicap_yn) : ?>
                <li>
                    <span>Handicap accessible</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['handicap_yn'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_appliances_yn) : ?>
                <li>
                    <span>Appliances</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['appliances_yn'])); ?></span>
                </li>
            <?php endif; ?>
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
$has_fireplaces_total = array_key_exists('fireplaces_total', $rch_prop) && $rch_prop['fireplaces_total'] !== null && $rch_prop['fireplaces_total'] !== '';
$has_dining_areas = array_key_exists('number_of_dining_areas', $rch_prop) && $rch_prop['number_of_dining_areas'] !== null && $rch_prop['number_of_dining_areas'] !== '';
$has_living_areas = array_key_exists('number_of_living_areas', $rch_prop) && $rch_prop['number_of_living_areas'] !== null && $rch_prop['number_of_living_areas'] !== '';
$has_basement_beds = array_key_exists('basement_bedroom_count', $rch_prop) && $rch_prop['basement_bedroom_count'] !== null && $rch_prop['basement_bedroom_count'] !== '';
$has_furnished_yn = array_key_exists('furnished_yn', $rch_prop) && $rch_prop['furnished_yn'] !== null && $rch_prop['furnished_yn'] !== '';
$has_number_of_units = array_key_exists('number_of_units', $rch_prop) && $rch_prop['number_of_units'] !== null && $rch_prop['number_of_units'] !== '';

if ($has_interior || $has_flooring || $has_security || $has_fireplace || $has_appliances || $has_fireplaces_total || $has_dining_areas || $has_living_areas || $has_basement_beds || $has_furnished_yn || $has_number_of_units) :
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
            <?php if ($has_fireplaces_total) : ?>
                <li>
                    <span>Fireplaces (total)</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['fireplaces_total'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_dining_areas) : ?>
                <li>
                    <span>Dining areas</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['number_of_dining_areas'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_living_areas) : ?>
                <li>
                    <span>Living areas</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['number_of_living_areas'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_basement_beds) : ?>
                <li>
                    <span>Basement bedrooms</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['basement_bedroom_count'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_furnished_yn) : ?>
                <li>
                    <span>Furnished</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['furnished_yn'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_number_of_units) : ?>
                <li>
                    <span>Number of units</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['number_of_units'])); ?></span>
                </li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// Lot size line for exterior (API: lot_size_area + lot_size_area_unit, or lot_size string)
$rch_lot_size_line = '';
if (isset($rch_prop['lot_size_area']) && $rch_prop['lot_size_area'] !== null && $rch_prop['lot_size_area'] !== '') {
    $rch_unit = rch_listing_features_format_value($rch_prop['lot_size_area_unit'] ?? null);
    $rch_lot_size_line = trim(rch_listing_features_format_value($rch_prop['lot_size_area']) . ($rch_unit !== '' ? ' ' . $rch_unit : ''));
}
if ($rch_lot_size_line === '' && strlen(rch_listing_features_format_value($rch_prop['lot_size'] ?? null)) > 0) {
    $rch_lot_size_line = rch_listing_features_format_value($rch_prop['lot_size']);
}

// Check if Exterior Features section has any data
$has_exterior_features = isset($listing_detail['property']['exterior_features']) && !empty($listing_detail['property']['exterior_features']);
$has_construction = isset($listing_detail['property']['construction_materials']) && !empty($listing_detail['property']['construction_materials']);
$has_roof = isset($listing_detail['property']['roof']) && !empty($listing_detail['property']['roof']);
$has_lot_features = isset($listing_detail['property']['lot_features']) && !empty($listing_detail['property']['lot_features']);
$has_architectural_style = isset($listing_detail['property']['architectural_style']) && !empty($listing_detail['property']['architectural_style']);
$has_foundation = isset($listing_detail['property']['foundation_details']) && !empty($listing_detail['property']['foundation_details']);
$has_fencing = isset($listing_detail['property']['fencing']) && !empty($listing_detail['property']['fencing']);
$has_number_of_stories = array_key_exists('number_of_stories', $rch_prop) && $rch_prop['number_of_stories'] !== null && $rch_prop['number_of_stories'] !== '';
$has_lot_size_line = strlen($rch_lot_size_line) > 0;
$has_lot_size_dimensions = strlen(rch_listing_features_format_value($rch_prop['lot_size_dimensions'] ?? null)) > 0;
$has_fenced_yard_yn = array_key_exists('fenced_yard_yn', $rch_prop) && $rch_prop['fenced_yard_yn'] !== null && $rch_prop['fenced_yard_yn'] !== '';

if ($has_exterior_features || $has_construction || $has_roof || $has_lot_features || $has_architectural_style || $has_foundation || $has_fencing || $has_number_of_stories || $has_lot_size_line || $has_lot_size_dimensions || $has_fenced_yard_yn) :
?>
    <div class="facilities-in-single-houses" id="rch-amenities">
        <h2>
            Exterior Features
        </h2>
        <ul class="rch_all_listing_data_features">
            <?php
            // Exterior features (e.g. gutters, patio)
            if (isset($listing_detail['property']['exterior_features']) && !empty($listing_detail['property']['exterior_features'])) :
                $exterior_features = $listing_detail['property']['exterior_features'];
                if (is_array($exterior_features)) {
                    $exterior_features = implode(', ', array_filter($exterior_features));
                }
                if (!empty($exterior_features)) :
            ?>
                    <li>
                        <span>Exterior</span>
                        <span><?php echo esc_html($exterior_features); ?></span>
                    </li>
            <?php
                endif;
            endif;
            ?>
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
            <?php if ($has_number_of_stories) : ?>
                <li>
                    <span>Stories</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['number_of_stories'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_lot_size_line) : ?>
                <li>
                    <span>Lot size</span>
                    <span><?php echo esc_html($rch_lot_size_line); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_lot_size_dimensions) : ?>
                <li>
                    <span>Lot dimensions</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['lot_size_dimensions'])); ?></span>
                </li>
            <?php endif; ?>
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
            <?php if ($has_fenced_yard_yn) : ?>
                <li>
                    <span>Fenced yard</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['fenced_yard_yn'])); ?></span>
                </li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// Check if Parking section has any data
$has_parking_spaces = isset($listing_detail['formatted']['parking_spaces']['text_no_label']) && !empty($listing_detail['formatted']['parking_spaces']['text_no_label']);
$has_parking_features = isset($listing_detail['property']['parking_features']) && !empty($listing_detail['property']['parking_features']);
$has_covered_parking = isset($listing_detail['property']['parking_spaces_covered_total']) && !empty($listing_detail['property']['parking_spaces_covered_total']);
$has_total_parking = isset($listing_detail['property']['number_of_parking_spaces']) && $listing_detail['property']['number_of_parking_spaces'] !== null && $listing_detail['property']['number_of_parking_spaces'] !== '';
$has_garage_length = strlen(rch_listing_features_format_value($rch_prop['garage_length'] ?? null)) > 0;
$has_garage_width = strlen(rch_listing_features_format_value($rch_prop['garage_width'] ?? null)) > 0;

if ($has_parking_spaces || $has_parking_features || $has_covered_parking || $has_total_parking || $has_garage_length || $has_garage_width) :
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
            <?php if ($has_total_parking) : ?>
                <li>
                    <span><?php echo $has_parking_spaces ? 'Parking spaces (count)' : 'Parking spaces'; ?></span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['number_of_parking_spaces'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_garage_length) : ?>
                <li>
                    <span>Garage length</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['garage_length'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_garage_width) : ?>
                <li>
                    <span>Garage width</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['garage_width'])); ?></span>
                </li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// Schools & community (only when API sends values)
$has_subdivision = strlen(rch_listing_features_format_value($rch_prop['subdivision_name'] ?? null)) > 0;
$has_school_district = strlen(rch_listing_features_format_value($rch_prop['school_district'] ?? null)) > 0;
$has_primary_school = strlen(rch_listing_features_format_value($rch_prop['primary_school_name'] ?? null)) > 0;
$has_elementary_school = strlen(rch_listing_features_format_value($rch_prop['elementary_school_name'] ?? null)) > 0;
$has_intermediate_school = strlen(rch_listing_features_format_value($rch_prop['intermediate_school_name'] ?? null)) > 0;
$has_middle_school = strlen(rch_listing_features_format_value($rch_prop['middle_school_name'] ?? null)) > 0;
$has_junior_high = strlen(rch_listing_features_format_value($rch_prop['junior_high_school_name'] ?? null)) > 0;
$has_high_school = strlen(rch_listing_features_format_value($rch_prop['high_school_name'] ?? null)) > 0;
$has_senior_high = strlen(rch_listing_features_format_value($rch_prop['senior_high_school_name'] ?? null)) > 0;

if ($has_subdivision || $has_school_district || $has_primary_school || $has_elementary_school || $has_intermediate_school || $has_middle_school || $has_junior_high || $has_high_school || $has_senior_high) :
?>
    <div class="facilities-in-single-houses" id="rch-schools">
        <h2>
            Schools &amp; community
        </h2>
        <ul class="rch_all_listing_data_features">
            <?php if ($has_subdivision) : ?>
                <li>
                    <span>Subdivision</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['subdivision_name'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_school_district) : ?>
                <li>
                    <span>School district</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['school_district'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_primary_school) : ?>
                <li>
                    <span>Primary school</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['primary_school_name'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_elementary_school) : ?>
                <li>
                    <span>Elementary school</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['elementary_school_name'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_intermediate_school) : ?>
                <li>
                    <span>Intermediate school</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['intermediate_school_name'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_middle_school) : ?>
                <li>
                    <span>Middle school</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['middle_school_name'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_junior_high) : ?>
                <li>
                    <span>Junior high</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['junior_high_school_name'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_high_school) : ?>
                <li>
                    <span>High school</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['high_school_name'])); ?></span>
                </li>
            <?php endif; ?>
            <?php if ($has_senior_high) : ?>
                <li>
                    <span>Senior high school</span>
                    <span><?php echo esc_html(rch_listing_features_format_value($rch_prop['senior_high_school_name'])); ?></span>
                </li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>
