<?php
/**
 * Template part for displaying listing and seller agents
 * 
 * This template displays:
 * - Listing agents loop with photo, contact info, and license number
 * - Seller agents loop with photo, contact info, and license number
 * - Local Logic widgets section
 * - Courtesy text (if no agents found)
 * - MLS disclaimer with dynamic year replacement
 * 
 * @package Rechat_Plugin
 * @var array $listing_detail The listing detail array containing all property information
 * @var array $agent_posts Array of agent posts from the custom post type
 * @var array $seller_agent_posts Array of seller agent posts from the custom post type
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Ensure required variables are available
if (!isset($listing_detail) || !is_array($listing_detail)) {
    return;
}
?>

<?php
// Output result - only render if $agent_posts is a non-empty array
if (is_array($agent_posts) && count($agent_posts) > 0):
?>
    <div class="rch-agent-exists rch-agent-info">
        <h2><?php echo count($agent_posts) > 1 ? 'Listing Agents' : 'Listing Agent'; ?></h2>
        <ul class="rch-agent-list">
            <?php foreach ($agent_posts as $agent_post) :
                if (empty($agent_post) || !isset($agent_post->ID)) continue;
                $agent_title = get_the_title($agent_post->ID);
                $agent_url = get_permalink($agent_post->ID);
                $agent_img = get_post_meta($agent_post->ID, 'profile_image_url', true);
                $licence_number = get_post_meta($agent_post->ID, 'license_number', true);
                $phone_number = get_post_meta($agent_post->ID, 'phone_number', true);
                $email = get_post_meta($agent_post->ID, 'email', true);
            ?>
                <li class="rch-agent-item">
                    <a href="<?php echo esc_url($agent_url); ?>" class="rch-agent-link">
                        <?php if ($agent_img) : ?>
                            <img src="<?php echo esc_url($agent_img); ?>" alt="<?php echo esc_attr($agent_title); ?>" class="rch-agent-photo">
                        <?php endif; ?>
                    </a>
                    <div class="rch-listing-agent-info">
                        <span class="rch-agent-name">
                            <a href="<?php echo esc_url($agent_url); ?>">
                                <?php echo esc_html($agent_title); ?>
                            </a>

                            <div class="rch_main_listing_agent_data">
                                <?php if ($licence_number) : ?>
                                    <div class="rch-agent-license">
                                        <span>
                                            Licence number:
                                        </span>
                                        <span class="rch_agent_data_listing">
                                            <?php echo esc_html($licence_number); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($phone_number) : ?>
                                    <div class="rch-agent-phone">
                                        <span>
                                            Phone:
                                        </span>
                                        <span class="rch_agent_data_listing">
                                            <a href="tel:<?php echo esc_html($phone_number); ?>">
                                                <?php echo esc_html($phone_number); ?>
                                            </a>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($email) : ?>
                                    <div class="rch-agent-email">
                                        <span>

                                            Email:
                                        </span>
                                        <span class="rch_agent_data_listing">
                                            <a href="mailto:<?php echo esc_html($email); ?>">
                                                <?php echo esc_html($email); ?>
                                            </a>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                    </div>

                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
// Output result - seller agent posts
if (is_array($seller_agent_posts) && count($seller_agent_posts) > 0):
?>
    <div class="rch-agent-exists rch-agent-info">
        <h2><?php echo count($seller_agent_posts) > 1 ? 'Seller Agents' : 'Seller Agent'; ?></h2>
        <ul class="rch-agent-list">
            <?php foreach ($seller_agent_posts as $seller_agent_post) :
                if (empty($seller_agent_post) || !isset($seller_agent_post->ID)) continue;
                $seller_agent_title = get_the_title($seller_agent_post->ID);
                $seller_agent_url = get_permalink($seller_agent_post->ID);
                $seller_agent_img = get_post_meta($seller_agent_post->ID, 'profile_image_url', true);
                $seller_licence_number = get_post_meta($seller_agent_post->ID, 'license_number', true);
                $seller_phone_number = get_post_meta($seller_agent_post->ID, 'phone_number', true);
                $seller_email = get_post_meta($seller_agent_post->ID, 'email', true);
            ?>
                <li class="rch-agent-item">
                    <a href="<?php echo esc_url($seller_agent_url); ?>" class="rch-agent-link">
                        <?php if ($seller_agent_img) : ?>
                            <img src="<?php echo esc_url($seller_agent_img); ?>" alt="<?php echo esc_attr($seller_agent_title); ?>" class="rch-agent-photo">
                        <?php endif; ?>
                    </a>
                    <div class="rch-listing-agent-info">
                        <span class="rch-agent-name">
                            <a href="<?php echo esc_url($seller_agent_url); ?>">
                                <?php echo esc_html($seller_agent_title); ?>
                            </a>

                            <div class="rch_main_listing_agent_data">
                                <?php if ($seller_licence_number) : ?>
                                    <div class="rch-agent-license">
                                        <span>
                                            Licence number:
                                        </span>
                                        <span class="rch_agent_data_listing">
                                            <?php echo esc_html($seller_licence_number); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($seller_phone_number) : ?>
                                    <div class="rch-agent-phone">
                                        <span>
                                            Phone:
                                        </span>
                                        <span class="rch_agent_data_listing">
                                            <a href="tel:<?php echo esc_html($seller_phone_number); ?>">
                                                <?php echo esc_html($seller_phone_number); ?>
                                            </a>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($seller_email) : ?>
                                    <div class="rch-agent-email">
                                        <span>

                                            Email:
                                        </span>
                                        <span class="rch_agent_data_listing">
                                            <a href="mailto:<?php echo esc_html($seller_email); ?>">
                                                <?php echo esc_html($seller_email); ?>
                                            </a>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                    </div>

                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class=rch_local_logic id="rch-location">
    <?php
    // Retrieve the selected features from the settings
    $selected_features = get_option('rch_rechat_local_logic_features', []);
    $google_map_api = get_option('rch_rechat_google_map_api_key');
    // Define the available template parts corresponding to each feature
    $feature_templates = [
        // 'Hero' => 'hero', // Template part for Hero feature
        // 'Map' => 'map', // Template part for Map feature
        // 'Highlights' => 'highlights', // Template part for Highlights feature
        // 'Characteristics' => 'characteristics', // Template part for Characteristics feature
        // 'Schools' => 'schools', // Template part for Schools feature
        // 'Demographics' => 'demographics', // Template part for Demographics feature
        // 'PropertyValueDrivers' => 'property-value-drivers', // Template part for PropertyValueDrivers feature
        // 'MarketTrends' => 'market-trends', // Template part for MarketTrends feature
        // 'Match' => 'match', // Template part for Match feature
        'LocalContent' => 'widgets/local-content', // Template part for Match feature
    ];

    // Loop through the selected features and include the corresponding template part from the plugin
    foreach ($selected_features as $feature) {
        if (array_key_exists($feature, $feature_templates)) {
            // Get the plugin directory path
            $plugin_dir = RCH_PLUGIN_INCLUDES; // Adjust if your plugin files are in a subfolder

            // Construct the template part file path
            $template_part_path = $plugin_dir . 'local-logic/' . $feature_templates[$feature] . '.php';            // Check if the template part file exists, then include it
            if (file_exists($template_part_path)) {
                include $template_part_path;
            }
        }
    }
    ?>
</div>

<?php if (empty($agent_posts)):
    $courtesy_text = '';
    if (isset($listing_detail['formatted']['courtesy']['text'])) {
        // Sanitize then convert newlines to <br>
        $courtesy_text = nl2br(esc_html($listing_detail['formatted']['courtesy']['text']));
    }
    echo '<div class="rch-agent-no-exists">' . $courtesy_text . '</div>';
endif;
?>

<?php if (!empty($listing_detail['mls_info'])) { ?>
    <div class="rch-disclaimer-show">
        <h2>
            Disclaimer
        </h2>
        <?php
        $currentYear = date('Y'); // Get the current year

        // Check if mls_info is an array
        $mls_info = $listing_detail['mls_info']['disclaimer'];

        // Replace {{currentYear}} with the actual current year
        $mls_info = str_replace('{{currentYear}}', $currentYear, $mls_info);

        // Safely output the HTML content
        ?>
        <div class="rch-inside-disclaimer">
            <?php echo wp_kses_post($mls_info); ?>
        </div>

        <?php
        ?>
    </div>
<?php } ?>
