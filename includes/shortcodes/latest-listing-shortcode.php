<?php
function rch_display_latest_listings_shortcode($atts)
{
    // Set default attributes and override with user-provided attributes
    $atts = shortcode_atts(
        array(
            'limit' => 7,
            'template' => '',
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
    <div class="swiper thumbsSwiper trendingSwiper <?php echo esc_attr($template); ?>" thumbsSlider="true">
        <div class="swiper-wrapper" id="rch-listing-list-latest-<?php echo esc_attr($template); ?>"></div>
        <div id="rch-loading-listing" style="display: block;" class="rch-loader"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const listingPerPage = <?php echo intval($atts['limit']); ?>;
            const template = "<?php echo esc_js($atts['template']); ?>";
            const adminAjaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";

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

                listingList.innerHTML = '';
                loading.style.display = 'block';

                fetch(adminAjaxUrl, {
                        method: 'POST', // Ensure method is POST
                        body: new URLSearchParams({
                            action: 'rch_fetch_listing',
                            listing_per_page: listingPerPage,
                            template: template,
                            brand: '<?php echo esc_js(get_option('rch_rechat_brand_id')); ?>'
                            // add any other parameters here
                        }),
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        loading.style.display = 'none';
                        const listings = data.data.listings;
                        listingList.innerHTML = '';

                        listings.forEach(listing => {
                            listingList.innerHTML += listing.content;
                        });
                        initializeSwiper(`.${template}`, swiperSettings);

                    })
                    .catch(error => {
                        console.error('Error fetching listing:', error);
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
