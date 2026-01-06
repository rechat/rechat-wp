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
                            <?php if ($profile_image_url) : ?>
                                <div class="rch-image-container">
                                    <picture>
                                        <a href="<?php the_permalink() ?>">
                                            <div class="rch-loader"></div>
                                            <img src="<?php echo esc_url($profile_image_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="rch-profile-image">
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
                                Thank you! Your data has been successfully sent.
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
            
            // Convert agents array to comma-separated string for web component
            if (is_array($agents)) {
                $agents_string = implode(',', $agents);
            } else {
                $agents_string = $agents;
            }
            
            // Define common property types and subtypes
            $property_subtypes = 'RES-Single Family, RES-Half Duplex, RES-Farm/Ranch, RES-Condo, RES-Townhouse, LSE-Apartment, LSE-Condo/Townhome, LSE-Duplex, LSE-Fourplex, LSE-House, LSE-Mobile, LSE-Triplex, LND-Commercial, LND-Farm/Ranch, LND-Residential, MUL-Full Duplex, MUL-Apartment/5Plex+, MUL-Fourplex, MUL-Multiple Single Units, MUL-Triplex, COM-Lease, COM-Sale, Lot/Land';
            $property_types = 'Residential, Residential Lease, Lots & Acreage, Multi-Family, Commercial';
            $active_statuses = 'Active, Active Contingent, Active Kick Out, Active Option Contract, Active Under Contract, Pending';
            $sold_statuses = 'Sold';
            ?>

            <?php if ($display_mode === 'combined') : ?>
            <!-- Combined Listings Section -->
            <div class="rch-agents-list rch-agents-combined-section">
                <h2><?php the_title(); ?>'s Properties</h2>
                <rechat-root
                    filter_agents="<?php echo esc_attr($agents_string); ?>"
                    filter_property_subtypes="<?php echo esc_attr($property_subtypes); ?>"
                    filter_property_types="<?php echo esc_attr($property_types); ?>"
                    filter_listing_statuses="<?php echo esc_attr($active_statuses); ?>"
                >
                    <rechat-listings-list></rechat-listings-list>
                </rechat-root>
            </div>

            <?php elseif ($display_mode === 'separate') : ?>
            <!-- Active Listings Section -->
            <div class="rch-agents-list rch-agents-active-section">
                <h2><?php the_title(); ?>'s Active Listings</h2>
                <rechat-root
                    filter_search_limit="5"
                    filter_agents="<?php echo esc_attr($agents_string); ?>"
                    filter_property_subtypes="<?php echo esc_attr($property_subtypes); ?>"
                    filter_property_types="<?php echo esc_attr($property_types); ?>"
                    filter_listing_statuses="<?php echo esc_attr($active_statuses); ?>"
                >
                    <rechat-listings-list></rechat-listings-list>
                </rechat-root>
            </div>

            <!-- Sold Listings Section -->
            <div class="rch-agents-list rch-agents-sold-section">
                <h2><?php the_title(); ?>'s Sold Listings</h2>
                <rechat-root
                    filter_agents="<?php echo esc_attr($agents_string); ?>"
                    filter_property_subtypes="<?php echo esc_attr($property_subtypes); ?>"
                    filter_property_types="<?php echo esc_attr($property_types); ?>"
                    filter_listing_statuses="<?php echo esc_attr($sold_statuses); ?>"
                >
                    <rechat-listings-list></rechat-listings-list>
                </rechat-root>
            </div>

            <?php elseif ($display_mode === 'active-only') : ?>
            <!-- Active Listings Only Section -->
            <div class="rch-agents-list rch-agents-active-section">
                <h2><?php the_title(); ?>'s Active Listings</h2>
                <rechat-root
                    filter_agents="<?php echo esc_attr($agents_string); ?>"
                    filter_property_subtypes="<?php echo esc_attr($property_subtypes); ?>"
                    filter_property_types="<?php echo esc_attr($property_types); ?>"
                    filter_listing_statuses="<?php echo esc_attr($active_statuses); ?>"
                >
                    <rechat-listings-list></rechat-listings-list>
                </rechat-root>
            </div>

            <?php elseif ($display_mode === 'sold-only') : ?>
            <!-- Sold Listings Only Section -->
            <div class="rch-agents-list rch-agents-sold-section">
                <h2><?php the_title(); ?>'s Sold Listings</h2>
                <rechat-root
                    filter_search_limit="5"
                    filter_agents="<?php echo esc_attr($agents_string); ?>"
                    filter_property_subtypes="<?php echo esc_attr($property_subtypes); ?>"
                    filter_property_types="<?php echo esc_attr($property_types); ?>"
                    filter_listing_statuses="<?php echo esc_attr($sold_statuses); ?>"
                >
                    <rechat-listings-list></rechat-listings-list>
                </rechat-root>
            </div>

            <?php elseif ($display_mode === 'slider') : ?>
            <!-- Slider Mode Section -->
            <div class="rch-agents-list rch-agents-slider-section">
                <h2><?php the_title(); ?>'s Active Listings</h2>
                <rechat-root 
                    filter_agents="<?php echo esc_attr($agents_string); ?>"
                    filter_property_subtypes="<?php echo esc_attr($property_subtypes); ?>"
                    filter_property_types="<?php echo esc_attr($property_types); ?>"
                    filter_listing_statuses="<?php echo esc_attr($active_statuses); ?>"
                >
                    <div class="rch-slider-container">
                        <button class="rch-nav-btn rch-nav-btn-prev" id="prevBtn" aria-label="Previous">‹</button>
                        <rechat-listings-list></rechat-listings-list>
                        <button class="rch-nav-btn rch-nav-btn-next" id="nextBtn" aria-label="Next">›</button>
                    </div>
                </rechat-root>
                
                <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const scrollAmount = 320;
                    document.getElementById('prevBtn')?.addEventListener('click', () => {
                        document.querySelector('rechat-listings-list')?.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
                    });
                    document.getElementById('nextBtn')?.addEventListener('click', () => {
                        document.querySelector('rechat-listings-list')?.scrollBy({ left: scrollAmount, behavior: 'smooth' });
                    });
                });
                </script>
            </div>
            <?php endif; ?>
        <?php endwhile; ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php
// Enqueue Rechat SDK
wp_enqueue_script('rechat-sdk', 'https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.js', [], null, true);

// Enqueue agent single page JavaScript for lead capture form
wp_enqueue_script('rch-agent-single', RCH_PLUGIN_ASSETS . 'js/rch-agent-single.js', ['jquery', 'rechat-sdk'], RCH_VERSION, true);

// Pass PHP data to JavaScript for lead capture form functionality
wp_localize_script('rch-agent-single', 'rchAgentData', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'brandId' => get_option('rch_rechat_brand_id'),
    'agentEmail' => $email,
    'leadChannel' => get_option('rch_agents_lead_channels'),
    'tags' => json_decode(get_option('rch_agents_selected_tags', '[]'), true),
]);
?>

<?php get_footer(); ?>