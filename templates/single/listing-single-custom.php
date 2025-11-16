<?php
// Check if agent exists in the agents custom post type
$agent_api_id = isset($listing_detail['list_agent']['id']) ? $listing_detail['list_agent']['id'] : '';
$seller_agent_api_id = isset($listing_detail['selling_agent']['id']) ? $listing_detail['selling_agent']['id'] : '';

$agent_posts = rch_check_agent_exists($agent_api_id);
$seller_agent_posts = rch_check_agent_exists($seller_agent_api_id);
get_header() ?>
<div class="container">
    <div id="primary" class="content-area rch-primary-content">
        <main id="main" class="site-main content-container site-container">
            <div id="rch-house-detail" class="rch-house-main-details">
                <div class="rch-top-img-slider">
                    <div class="rch-left-top-slider">
                        <?php if (is_array($listing_detail['gallery_image_urls']) && !empty($listing_detail['gallery_image_urls'])) { ?>
                            <picture data-slider="0" id="myBtn">
                                <img src="<?php echo esc_url($listing_detail['cover_image_url']); ?>" alt="Image of House">
                            </picture>
                            <button id="myBtn" data-slider="0" class="rch-load-images">
                                <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'gallery.svg'); ?>" alt=" gallery icon">

                                View all Photos
                            </button>
                            <span class="<?php echo $listing_detail['status']; ?>"><?php echo $listing_detail['status']; ?></span>
                        <?php
                        }
                        ?>
                    </div>
                    <div class="rch-right-top-slider">
                        <?php
                        // Ensure the gallery_image_urls is an array and has values
                        if (is_array($listing_detail['gallery_image_urls']) && !empty($listing_detail['gallery_image_urls'])) {
                            // Loop through the first 4 images
                            $i = 1;
                            foreach (array_slice($listing_detail['gallery_image_urls'], 1, 4) as $image_url) {
                        ?>
                                <picture data-slider="<?php echo esc_attr($i); ?>" id="myBtn">
                                    <img src="<?php echo esc_url($image_url); ?>" alt="Gallery of House">
                                </picture>
                        <?php
                                $i++;
                            }
                        } else {
                            // Fallback message if no images are available
                            echo '';
                        }
                        ?>
                    </div>
                </div>
                <div class="rch-single-price-house">
                    <?php echo sanitize_text_field($listing_detail['formatted']['price']['text']); ?>
                </div>
                <h1 class="rch-single-address">

                    <?php
                    // Check if address property exists
                    if (isset($listing_detail['formatted']['full_address']['text'])) {
                        $address = $listing_detail['formatted']['full_address']['text'];
                        $full_address = '';
                        echo esc_html($address);
                    } else {
                        // Fallback message if address is not set
                        echo '<p>Address information not available.</p>';
                    }
                    ?>
                </h1>

                <div class="rch-single-house-main-layout">
                    <div class="rch-single-left-main-layout">
                        <div class="rch-formatted-data-summary">
                            <ul>
                                <?php // Bedroom count
                                if (isset($listing_detail['formatted']['bedroom_count']['text_no_label']) && strlen(trim((string) $listing_detail['formatted']['bedroom_count']['text_no_label'])) > 0) : ?>
                                    <li>
                                        <b>
                                            <?php echo esc_html($listing_detail['formatted']['bedroom_count']['text_no_label']); ?>
                                        </b>
                                        <span>Total Bedrooms</span>
                                    </li>
                                <?php endif; ?>

                                <?php // Bathroom count
                                if (isset($listing_detail['formatted']['total_bathroom_count']['text_no_label']) && strlen(trim((string) $listing_detail['formatted']['total_bathroom_count']['text_no_label'])) > 0) : ?>
                                    <li>
                                        <b>
                                            <?php echo esc_html($listing_detail['formatted']['total_bathroom_count']['text_no_label']); ?>
                                        </b>
                                        <span>Total Bathrooms</span>
                                    </li>
                                <?php endif; ?>

                                <?php // Year built (allow 0 but treat empty/null as missing)
                                if (isset($listing_detail['property']['year_built']) && strlen(trim((string) $listing_detail['property']['year_built'])) > 0) : ?>
                                    <li>
                                        <b>
                                            <?php echo esc_html($listing_detail['property']['year_built']); ?>
                                        </b>
                                        <span>Year Built</span>
                                    </li>
                                <?php endif; ?>

                                <?php // Lot size
                                if (isset($listing_detail['formatted']['lot_size_square_feet']['text_no_label']) && strlen(trim((string) $listing_detail['formatted']['lot_size_square_feet']['text_no_label'])) > 0) : ?>
                                    <li>
                                        <b>
                                            <?php echo esc_html($listing_detail['formatted']['lot_size_square_feet']['text_no_label']); ?>
                                        </b>
                                        <span>Lot size/SQ.FT</span>
                                    </li>
                                <?php endif; ?>

                                <?php // Parking spaces
                                if (isset($listing_detail['formatted']['parking_spaces']['text_no_label']) && strlen(trim((string) $listing_detail['formatted']['parking_spaces']['text_no_label'])) > 0) : ?>
                                    <li>
                                        <b>
                                            <?php echo esc_html($listing_detail['formatted']['parking_spaces']['text_no_label']); ?>
                                        </b>
                                        <span>Parking Spaces</span>
                                    </li>
                                <?php endif; ?>

                            </ul>
                        </div>
                        <div class="rch-main-description-single-house">

                            <?php if (!empty($listing_detail['property']['description'])): ?>
                                <div class="main-des-single-house" id="rch-overview">
                                    <h2>
                                        About the Property
                                    </h2>
                                    <div class="rch-main-description-listing" id="rch-description-text">
                                        <?php echo wp_kses_post($listing_detail['property']['description']); ?>
                                    </div>
                                    <button class="rch-show-more-btn" id="rch-show-more-btn" style="display: none;">Show More</button>
                                </div>
                            <?php endif; ?>
                        </div>

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
                        <?php
                        // Output result
                        if (!empty($agent_posts)):
                            // Agents found - display list of agents
                        ?>
                            <div class="rch-agent-exists rch-agent-info">
                                <h2><?php echo count($agent_posts) > 1 ? 'Listing Agents' : 'Listing Agent'; ?></h2>
                                <ul class="rch-agent-list">
                                    <?php foreach ($agent_posts as $agent_post) :
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
                        // Output result
                        if (!empty($seller_agent_posts)):
                            // Agents found - display list of agents

                        ?>
                            <div class="rch-agent-exists rch-agent-info">
                                <h2><?php echo count($agent_posts) > 1 ? 'Listing Agents' : 'Listing Agent'; ?></h2>
                                <ul class="rch-agent-list">
                                    <?php foreach ($agent_posts as $agent_post) :
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
                                    $template_part_path = $plugin_dir . 'local-logic/' . $feature_templates[$feature] . '.php';                            // Check if the template part file exists, then include it
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
                    </div>
                    <div class="rch-single-right-main-layout">
                        <div class="rch-listing-form-lead" id="leadCaptureForm">

                            <form action="" method="post">
                                <h2>Inquire About This Property</h2>
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
            </div>
        </main><!-- #main -->
    </div><!-- #primary -->
</div>
<div id="myModal" class="rch-imgs-modal">
    <!-- Modal content -->
    <div class="rch-modal-content">
        <span class="rch-img-modal-close">&times;</span>
        <div class="swiper rch-houses-mySwiper2">
            <div class="swiper-wrapper">
                <?php foreach ($listing_detail['gallery_image_urls'] as $attachment_url) { ?>
                    <div class="swiper-slide">
                        <picture>
                            <img src="<?php echo esc_url($attachment_url); ?>" alt="Image Of House">
                        </picture>
                    </div>
                <?php } ?>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
        <div thumbsSlider="" class="swiper rch-houses-mySwiper">
            <div class="swiper-wrapper">
                <?php foreach ($listing_detail['gallery_image_urls'] as $attachment_url) { ?>
                    <div class="swiper-slide">
                        <picture>
                            <img src="<?php echo esc_url($attachment_url); ?>" alt="Images of House">
                        </picture>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

</div>
<?php get_footer() ?>
<script src="https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.js"></script>
<script>
    const sdk = new Rechat.Sdk({
        tracker: {
            cookie: {
                name: 'rechat-sdk-tracker' // default: rechat-sdk-tracker
            }
        }
    })

    const channel = {
        lead_channel: '<?php echo esc_js(get_option("rch_lead_channels")); ?>'
    };
    sdk.Leads.Tracker.capture({
        listing_id: '<?php echo esc_js($listing_detail['id']) ?>'
    })

    document.getElementById('leadCaptureForm').addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent form from submitting normally

        const input = {
            first_name: document.getElementById('first_name').value,
            last_name: document.getElementById('last_name').value,
            phone_number: document.getElementById('phone_number').value,
            email: document.getElementById('email').value,
            note: document.getElementById('note').value,
            tag: <?php echo wp_json_encode(explode(',', get_option("rch_selected_tags"))); ?>, // Convert comma-separated string to array
            source_type: 'Website',
            mlsid: '<?php echo esc_js($listing_detail['mls_number']) ?>',
            listing_id: '<?php echo esc_js($listing_detail['id']) ?>',
            referer_url: window.location.href
        };

        // Hide success, error alerts, and show loading spinner
        document.getElementById('rch-listing-success-sdk').style.display = 'none';
        document.getElementById('rch-listing-cancel-sdk').style.display = 'none';
        document.getElementById('loading-spinner').style.display = 'block';

        sdk.Leads.capture(channel, input)
            .then(() => {
                // Hide loading spinner and show success message
                document.getElementById('loading-spinner').style.display = 'none';
                document.getElementById('rch-listing-success-sdk').style.display = 'block';
            })
            .catch((e) => {
                // Hide loading spinner and show error message
                document.getElementById('loading-spinner').style.display = 'none';
                document.getElementById('rch-listing-cancel-sdk').style.display = 'block';
                console.log('Error:', e);
            });
    });
</script>
<script>
    var swiper = new Swiper(".rch-houses-mySwiper", {
        spaceBetween: 10,
        slidesPerView: 8, // Default for desktop
        freeMode: true,
        watchSlidesProgress: true,

        // Add responsive breakpoints
        breakpoints: {
            // When the window width is >= 320px (small mobile)
            320: {
                slidesPerView: 3, // 1 slide per view on small screens
            },
            // When the window width is >= 480px (mobile)
            480: {
                slidesPerView: 4, // 2 slides per view on mobile screens
            },
            // When the window width is >= 768px (tablets)
            768: {
                slidesPerView: 5, // 4 slides per view on tablet screens
            },
            // When the window width is >= 1024px (desktops)
            1024: {
                slidesPerView: 6, // 6 slides per view on large tablets or small desktops
            },
            // When the window width is >= 1280px (desktops)
            1280: {
                slidesPerView: 8, // Full slides per view on desktop
            }
        }
    });

    var swiper2 = new Swiper(".rch-houses-mySwiper2", {
        spaceBetween: 10,
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
        thumbs: {
            swiper: swiper,
        },
    });
    // Get the modal
    var modal = document.getElementById("myModal");

    // Get the button that opens the modal
    var btns = document.querySelectorAll("[id='myBtn']");

    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("rch-img-modal-close")[0];

    // When the user clicks the button, open the modal
    for (const btn of btns) {
        btn.addEventListener('click', function(event) {
            const data = this.getAttribute('data-slider');
            modal.style.display = "flex";
            swiper2.update();
            swiper2.slideTo(data);
            swiper2.update();
        })
    }

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() {
        modal.style.display = "none";
    }

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // Show More / Show Less functionality
    document.addEventListener('DOMContentLoaded', function() {
        const descriptionText = document.getElementById('rch-description-text');
        const showMoreBtn = document.getElementById('rch-show-more-btn');

        if (descriptionText && showMoreBtn) {
            // Check if the description height is greater than 200px
            if (descriptionText.scrollHeight > 200) {
                descriptionText.classList.add('collapsed');
                showMoreBtn.style.display = 'inline-block';

                // Toggle show more/less
                showMoreBtn.addEventListener('click', function() {
                    if (descriptionText.classList.contains('collapsed')) {
                        descriptionText.classList.remove('collapsed');
                        showMoreBtn.textContent = 'Show Less';
                    } else {
                        descriptionText.classList.add('collapsed');
                        showMoreBtn.textContent = 'Show More';
                        // Scroll back to the description section
                        descriptionText.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            }
        }
    });
</script>