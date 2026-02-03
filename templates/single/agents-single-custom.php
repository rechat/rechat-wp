<?php
get_header();

// Get the current post ID
$post_id = get_the_ID();

// Retrieve the meta values
$website = get_post_meta($post_id, 'website', true);
$instagram = get_post_meta($post_id, 'instagram', true);
$twitter = get_post_meta($post_id, 'twitter', true);
$linkedin = get_post_meta($post_id, 'linkedin', true);
$youtube = get_post_meta($post_id, 'youtube', true);
$facebook = get_post_meta($post_id, 'facebook', true);
$phone_number = get_post_meta($post_id, 'phone_number', true);
$email = get_post_meta($post_id, 'email', true);
$profile_image_url = get_post_meta($post_id, 'profile_image_url', true);
$timezone = get_post_meta($post_id, 'timezone', true);
$agents = get_post_meta($post_id, 'agents', true);
?>

<div id="primary" class="content-area rch-primary-content">
    <main id="main" class="site-main content-container site-container">

        <?php
        while (have_posts()) : the_post();
        ?>
            <div class="rch-main-layout-single-agent">
                <div class="rch-left-main-layout-single-agent">
                    <div class="rch-top-single-agent">
                        <div class="rch-left-top-single-agent">
                            <?php
                            // Use profile image URL if available, otherwise fall back to post thumbnail
                            $image_url = $profile_image_url ?: get_the_post_thumbnail_url(get_the_ID(), 'full');
                            if ($image_url) :
                            ?>
                                <div class="rch-image-container">
                                    <picture>
                                        <a href="<?php the_permalink() ?>">
                                            <div class="rch-loader"></div>
                                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="rch-profile-image">
                                        </a>
                                    </picture>
                                </div>
                            <?php endif; ?>
                            <div class="rch-data-agent">
                                <?php the_title('<h1>', '</h1>') ?>

                                <?php if ($phone_number) : ?>
                                    <span>
                                        Phone:
                                        <?php echo esc_html($phone_number); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($email) : ?>
                                    <span>
                                        Email:
                                        <?php echo esc_html($email); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($website) : ?>
                                    <span>
                                        Website:
                                        <a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($instagram || $twitter || $linkedin || $youtube || $facebook) : ?>
                                    <span>
                                        Social Media:
                                    </span>
                                    <ul class="rch-single-agents-social">
                                        <?php if ($instagram) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($instagram); ?>" target="_blank">
                                                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'instagram.svg'); ?>" alt="Instagram">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($twitter) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($twitter); ?>" target="_blank">
                                                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'x.svg'); ?>" alt="Twitter">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($linkedin) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($linkedin); ?>" target="_blank">
                                                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'linkedin.svg'); ?>" alt="LinkedIn">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($youtube) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($youtube); ?>" target="_blank">
                                                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'youtube.svg'); ?>" alt="YouTube">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($facebook) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($facebook); ?>" target="_blank">
                                                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'facebook.svg'); ?>" alt="Facebook">
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                    <div class="rch-main-content">
                        <?php the_content(); ?>
                    </div>
                </div>
                <div class="rch-right-main-layout-single-agent">
                    <div class="rch-inner-right-agents" id="leadCaptureForm">
                        <form action="" method="post">
                            <h2>Get in Touch with <?php echo esc_html(get_the_title()); ?></h2>
                            <!-- First Name -->
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" required>
                            </div>

                            <!-- Last Name -->
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" placeholder="Enter your last name" required>
                            </div>

                            <!-- Phone Number -->
                            <div class="form-group">
                                <label for="phone_number">Phone Number</label>
                                <input type="tel" id="phone_number" name="phone_number" placeholder="Enter your phone number" required>
                            </div>

                            <!-- Email Address -->
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                            </div>

                            <!-- Note -->
                            <div class="form-group">
                                <label for="note">Note</label>
                                <textarea id="note" name="note" placeholder="Write your note here" required></textarea>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit">Submit Request</button>
                            <div id="loading-spinner" class="rch-loading-spinner-form" style="display: none;"></div>
                            <div id="rch-listing-success-sdk" class="rch-success-box-listing">
                                Your information has been sent.
                            </div>
                            <div id="rch-listing-cancel-sdk" class="rch-error-box-listing">
                                Something went wrong. Please try again.
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php
            // Get admin settings for listing display mode
            $display_mode = get_option('rch_listing_display_mode', 'combined'); // Default: combined
            ?>

            <?php if ($display_mode === 'combined') : ?>
                <!-- Combined Listings Section -->
                <div class="rch-agents-list rch-agents-combined-section">
                    <h2><?php the_title(); ?>'s Properties</h2>
                    <!-- 
                    Combined Properties Section
                    Displays all listings: Active, Sold, and Leased properties together
                -->
                    <div class="rch-agents-list-items rch-listing-list" id="agent-combined-properties-list">
                        <!-- All properties will be loaded here via AJAX -->
                    </div>
                    <div id="loading-combined-properties" class="rch-loader" style="display: block;"></div>
                    <div id="agent-combined-pagination" class="rch-listing-pagination"></div>
                </div>

            <?php elseif ($display_mode === 'separate') : ?>
                <!-- Active Listings Section -->
                <div class="rch-agents-list rch-agents-active-section">
                    <h2><?php the_title(); ?>'s Active Listings</h2>
                    <!-- 
                    Active Properties Section
                    Displays: Active, Incoming, Coming Soon, Active Under Contract, Active Option Contract, 
                    Active Contingent, Active Kick Out, Pending
                -->
                    <div class="rch-agents-list-items rch-listing-list" id="agent-active-properties-list">
                        <!-- Active properties will be loaded here via AJAX -->
                    </div>
                    <div id="loading-active-properties" class="rch-loader" style="display: block;"></div>
                    <div id="agent-active-pagination" class="rch-listing-pagination"></div>
                </div>

                <!-- Sold Listings Section -->
                <div class="rch-agents-list rch-agents-sold-section">
                    <h2><?php the_title(); ?>'s Sold Listings</h2>
                    <!-- 
                    Sold Properties Section
                    Displays: Sold, Leased
                -->
                    <div class="rch-agents-list-items rch-listing-list" id="agent-sold-properties-list">
                        <!-- Sold properties will be loaded here via AJAX -->
                    </div>
                    <div id="loading-sold-properties" class="rch-loader" style="display: block;"></div>
                    <div id="agent-sold-pagination" class="rch-listing-pagination"></div>
                </div>

            <?php elseif ($display_mode === 'active-only') : ?>
                <!-- Active Listings Only Section -->
                <div class="rch-agents-list rch-agents-active-section">
                    <h2><?php the_title(); ?>'s Active Listings</h2>
                    <div class="rch-agents-list-items rch-listing-list" id="agent-active-properties-list">
                        <!-- Active properties will be loaded here via AJAX -->
                    </div>
                    <div id="loading-active-properties" class="rch-loader" style="display: block;"></div>
                    <div id="agent-active-pagination" class="rch-listing-pagination"></div>
                </div>

            <?php elseif ($display_mode === 'sold-only') : ?>
                <!-- Sold Listings Only Section -->
                <div class="rch-agents-list rch-agents-sold-section">
                    <h2><?php the_title(); ?>'s Sold Listings</h2>
                    <div class="rch-agents-list-items rch-listing-list" id="agent-sold-properties-list">
                        <!-- Sold properties will be loaded here via AJAX -->
                    </div>
                    <div id="loading-sold-properties" class="rch-loader" style="display: block;"></div>
                    <div id="agent-sold-pagination" class="rch-listing-pagination"></div>
                </div>
            <?php endif; ?>
        <?php endwhile; ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php
// Enqueue Rechat SDK
wp_enqueue_script('rechat-sdk', 'https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.js', [], null, true);

// Enqueue agent single page JavaScript
wp_enqueue_script('rch-agent-single', RCH_PLUGIN_ASSETS . 'js/rch-agent-single.js', ['jquery', 'rechat-sdk'], RCH_VERSION, true);

// Get sort order setting and convert to API parameter
$sort_order = get_option('rch_listing_sort_order', 'date');
$sort_by = ($sort_order === 'price') ? '-price' : '-list_date';
// Pass PHP data to JavaScript
wp_localize_script('rch-agent-single', 'rchAgentData', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'brandId' => get_option('rch_rechat_brand_id'),
    'agentMatrixIds' => $agents,
    'assignees' => [['email' => $email]],
    'sortBy' => $sort_by,
    'leadChannel' => get_option('rch_agents_lead_channels'),
    'tags' => json_decode(get_option('rch_agents_selected_tags', '[]'), true),
    'prevIconPath' => RCH_PLUGIN_ASSETS_URL_IMG . 'left-arrow.svg',
    'nextIconPath' => RCH_PLUGIN_ASSETS_URL_IMG . 'right-arrow.svg',
    'displayMode' => get_option('rch_listing_display_mode', 'combined'),
]);
?>

<?php get_footer(); ?>