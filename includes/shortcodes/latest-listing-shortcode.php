<?php
function rch_display_latest_listings_shortcode($atts)
{
    // Set default attributes and override with user-provided attributes
    $atts = shortcode_atts(
        array(
            'display_type' => 'swiper', // New attribute to select between 'swiper' or 'grid'
            'limit' => 7,
            'template' => '',
            'content' => '',
            'map_points' => '', // New attribute for the 4 points of the map
            'listing_statuses' => '', // New attribute for listing statuses
            'slides_per_view' => 3.5,
            'space_between' => 16,
            'loop' => true,
            'breakpoints' => '',
            'pagination' => false,
            'pagination_clickable' => false,
            'pagination_type' => 'bullets',
            'pagination_render_bullet' => '',
            'navigation' => false,
            'centered_slides' => false, // New attribute
            'speed' => '', // New attribute
            'effect' => '', // New attribute
            'grab_cursor' => false, // New attribute
            'simulate_touch' => true, // New attribute
            'autoplay' => '', // New attribute (JSON string for autoplay settings)
        ),
        $atts
    );

    $template = esc_js($atts['template']);
    $display_type = esc_attr($atts['display_type']);
    $map_points = esc_js($atts['map_points']);
    
    // Map listing_statuses based on user input
    $listing_statuses_raw = trim($atts['listing_statuses']);
    $listing_statuses = '';
    
    if (!empty($listing_statuses_raw)) {
        switch ($listing_statuses_raw) {
            case 'All Listings':
                $listing_statuses = 'Residential,Residential Lease,Lots & Acreage,Commercial,Multi-Family';
                break;
            case 'Sale':
                $listing_statuses = 'Residential,Lots & Acreage,Commercial,Multi-Family';
                break;
            case 'Lease':
                $listing_statuses = 'Residential Lease';
                break;
            case 'Lots & Acreage':
                $listing_statuses = 'Lots & Acreage';
                break;
            case 'Commercial':
                $listing_statuses = 'Commercial';
                break;
            default:
                // If it doesn't match any predefined values, use it as-is
                $listing_statuses = $listing_statuses_raw;
                break;
        }
    }
    // Use sanitize_text_field instead of esc_js to preserve special characters
    $listing_statuses = sanitize_text_field($listing_statuses);
    
    $slides_per_view = floatval($atts['slides_per_view']);
    $space_between = intval($atts['space_between']);
    $loop = filter_var($atts['loop'], FILTER_VALIDATE_BOOLEAN);
    $breakpoints = !empty($atts['breakpoints']) ? $atts['breakpoints'] : '{}';
    $pagination = filter_var($atts['pagination'], FILTER_VALIDATE_BOOLEAN);
    $pagination_clickable = filter_var($atts['pagination_clickable'], FILTER_VALIDATE_BOOLEAN);
    $pagination_type = esc_js($atts['pagination_type']);
    $pagination_render_bullet = !empty($atts['pagination_render_bullet']) ? $atts['pagination_render_bullet'] : 'null';
    $navigation = filter_var($atts['navigation'], FILTER_VALIDATE_BOOLEAN);
    $centered_slides = filter_var($atts['centered_slides'], FILTER_VALIDATE_BOOLEAN);
    $speed = intval($atts['speed']);
    $effect = esc_js($atts['effect']);
    $grab_cursor = filter_var($atts['grab_cursor'], FILTER_VALIDATE_BOOLEAN);
    $simulate_touch = filter_var($atts['simulate_touch'], FILTER_VALIDATE_BOOLEAN);
    $autoplay = !empty($atts['autoplay']) ? $atts['autoplay'] : null;


    ob_start();
?>
    <style>

    </style>
    <?php if ($display_type === 'swiper'): ?>
        <div class="swiper thumbsSwiper trendingSwiper <?php echo esc_attr($template); ?>" thumbsSlider="true">
            <div class="swiper-wrapper" id="rch-listing-list-latest-<?php echo esc_attr($template); ?>"></div>
            <div id="rch-loading-listing" class="rch-loading-container">
                <div class="rch-loader"></div>
                <div class="rch-loader-text">Loading listings...</div>
            </div>
        </div>
    <?php else: ?>
        <div class="rch-grid-container <?php echo esc_attr($template); ?>-grid">
            <div id="rch-listing-list-latest-<?php echo esc_attr($template); ?>"></div>
            <div id="rch-loading-listing" class="rch-loading-container">
                <div class="rch-loader"></div>
                <div class="rch-loader-text">Loading listings...</div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const listingPerPage = <?php echo intval($atts['limit']); ?>;
            const template = "<?php echo esc_js($atts['template']); ?>";
            const adminAjaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
            const mapPoints = "<?php echo $map_points; ?>";
            const listingStatuses = <?php echo json_encode($listing_statuses); ?>;

            // Build Swiper settings object conditionally
            const swiperSettings = {
                slidesPerView: <?php echo floatval($slides_per_view); ?>,
                spaceBetween: <?php echo intval($space_between); ?>,
                loop: <?php echo $loop ? 'true' : 'false'; ?>,
                breakpoints: JSON.parse('<?php echo str_replace('\"', '"', $breakpoints); ?>'),
            };

            <?php if ($pagination): ?>
                swiperSettings.pagination = {
                    el: '.swiper-pagination',
                    clickable: <?php echo $pagination_clickable ? 'true' : 'false'; ?>,
                    type: '<?php echo $pagination_type; ?>'
                };

                <?php if (!empty($atts['pagination_render_bullet'])): ?>
                    swiperSettings.pagination.renderBullet = <?php echo stripslashes($pagination_render_bullet); ?>;
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($navigation): ?>
                swiperSettings.navigation = {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev",
                };
            <?php endif; ?>

            <?php if ($centered_slides): ?>
                swiperSettings.centeredSlides = true;
            <?php endif; ?>

            <?php if ($speed): ?>
                swiperSettings.speed = <?php echo intval($speed); ?>;
            <?php endif; ?>

            <?php if (!empty($effect)): ?>
                swiperSettings.effect = "<?php echo esc_js($effect); ?>";
            <?php endif; ?>

            <?php if ($grab_cursor): ?>
                swiperSettings.grabCursor = true;
            <?php endif; ?>

            <?php if (!$simulate_touch): ?>
                swiperSettings.simulateTouch = false;
            <?php endif; ?>

            <?php if (!empty($autoplay)): ?>
                swiperSettings.autoplay = JSON.parse('<?php echo str_replace('\"', '"', $autoplay); ?>');
            <?php endif; ?>

            function updateListingList() {
                const listingList = document.getElementById('rch-listing-list-latest-<?php echo esc_attr($template); ?>');
                const loading = document.getElementById('rch-loading-listing');

                // Clear the listing container and show loading
                listingList.innerHTML = '';
                loading.style.display = 'flex';
                const token = '<?php echo get_option('rch_rechat_access_token'); ?>';
                fetch(adminAjaxUrl, {
                        method: 'POST', // Ensure method is POST
                        body: new URLSearchParams({
                            action: 'rch_fetch_listing',
                            listing_per_page: listingPerPage,
                            template: template,
                            content: '<?php echo esc_js($atts['content']); ?>', // Pass the content filter
                            brand: '<?php echo esc_js(get_option('rch_rechat_brand_id')); ?>',
                            points: mapPoints, // Pass the map points
                            listing_statuses: listingStatuses // Pass the listing statuses
                            // add any other parameters here
                        }),
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'authorization': 'Bearer ' + token
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Hide loading indicator
                        loading.style.display = 'none';

                        const listings = data.data.listings;
                        listingList.innerHTML = '';

                        if (listings && listings.length > 0) {
                            listings.forEach(listing => {
                                listingList.innerHTML += listing.content;
                            });

                            // Only initialize Swiper if display_type is swiper
                            <?php if ($display_type === 'swiper'): ?>
                                initializeSwiper(`.${template}`, swiperSettings);
                            <?php endif; ?>
                        } else {
                            // Display a message when no listings are returned
                            listingList.innerHTML = '<div class="rch-no-listings-message">Nothing to show</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching listing:', error);

                        // Hide loading indicator even on error
                        loading.style.display = 'none';

                        // Display a message when there's an error
                        listingList.innerHTML = '<div class="rch-no-listings-message">No listings available.</div>';
                    });
            }

            function initializeSwiper(selector, settings) {
                const swiperWrapper = document.querySelector(selector);
                if (swiperWrapper && swiperWrapper.children.length > 0) {
                    new Swiper(selector, settings);
                }
            }

            updateListingList();
        });
    </script>

<?php
    return ob_get_clean();
}

add_shortcode('rch_latest_listings', 'rch_display_latest_listings_shortcode');
