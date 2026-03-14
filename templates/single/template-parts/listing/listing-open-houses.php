<?php
/**
 * Listing Open Houses Template Part
 * 
 * Displays scheduled open houses for the property
 * 
 * @package Rechat
 * @param array $listing_detail The listing data
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if open_houses has data
if (isset($listing_detail['open_houses']) && is_array($listing_detail['open_houses']) && !empty($listing_detail['open_houses'])) :
?>
    <div class="openhouse-in-single-houses" id="rch-facilities">
        <h2>Open Houses</h2>
        <ul class="rch-open-houses-list">
            <?php foreach ($listing_detail['open_houses'] as $open_house) :
                // Get timezone (default to UTC if not provided)
                $timezone = isset($open_house['tz']) ? $open_house['tz'] : 'UTC';

                try {
                    $tz = new DateTimeZone($timezone);

                    // Format start time
                    $start_date = new DateTime();
                    $start_date->setTimestamp($open_house['start_time']);
                    $start_date->setTimezone($tz);

                    // Format end time
                    $end_date = new DateTime();
                    $end_date->setTimestamp($open_house['end_time']);
                    $end_date->setTimezone($tz);

                    // Format: "Saturday, January 4, 2025 at 1:00 PM - 4:00 PM"
                    $start_formatted = $start_date->format('l, F j, Y \a\t g:i A');
                    $end_formatted = $end_date->format('g:i A');

                    // If same day, combine
                    if ($start_date->format('Y-m-d') === $end_date->format('Y-m-d')) {
                        $display_text = $start_formatted . ' - ' . $end_formatted;
                    } else {
                        // Different days
                        $display_text = $start_formatted . ' to ' . $end_date->format('l, F j, Y \a\t g:i A');
                    }
                } catch (Exception $e) {
                    // Fallback if timezone is invalid
                    $display_text = date('l, F j, Y \a\t g:i A', $open_house['start_time']) . ' - ' . date('g:i A', $open_house['end_time']);
                }
            ?>
                <li class="rch-open-house-item">
                    <div class="rch-open-house-details">
                        <?php if (!empty($open_house['open_house_type'])) : ?>
                            <span class="rch-open-house-type"><?php echo esc_html($open_house['open_house_type']); ?></span>
                        <?php endif; ?>
                        <span class="rch-open-house-datetime"><?php echo esc_html($display_text); ?></span>
                        <?php if (!empty($open_house['description'])) : ?>
                            <p class="rch-open-house-description"><?php echo esc_html($open_house['description']); ?></p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
