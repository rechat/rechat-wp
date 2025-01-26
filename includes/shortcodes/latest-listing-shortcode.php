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
            'centered_slides' => false,
            'breakpoints' => '', // Default breakpoints are empty
        ),
        $atts
    );

    $template = esc_js($atts['template']); // Template attribute
    $slides_per_view = floatval($atts['slides_per_view']);
    $space_between = intval($atts['space_between']);
    $loop = filter_var($atts['loop'], FILTER_VALIDATE_BOOLEAN);
    $centered_slides = filter_var($atts['centered_slides'], FILTER_VALIDATE_BOOLEAN);
    $breakpoints = $atts['breakpoints'] ? esc_js($atts['breakpoints']) : '{}'; // Escape breakpoints or use empty object
    ob_start();
?>
    <div class="swiper thumbsSwiper trendingSwiper <?php echo esc_attr($template); ?>" thumbsSlider="true">
        <div class="swiper-wrapper" id="rch-listing-list-latest-<?php echo esc_attr($template); ?>"></div>
        <div id="rch-loading-listing" style="display: none;" class="rch-loader"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const listingPerPage = <?php echo intval($atts['limit']); ?>;
            const template = "<?php echo esc_js($atts['template']); ?>";
            const adminAjaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";

            // Swiper settings from shortcode attributes
            const swiperSettings = {
                slidesPerView: <?php echo floatval($slides_per_view); ?>,
                spaceBetween: <?php echo intval($space_between); ?>,
                loop: <?php echo $loop ? 'true' : 'false'; ?>,
                centeredSlides: <?php echo $centered_slides ? 'true' : 'false'; ?>,
                breakpoints: <?php echo $breakpoints; ?>, // Add breakpoints directly here
                navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev",
                },
            };
console.log(swiperSettings)
            function updateListingList() {
                const listingList = document.getElementById('rch-listing-list-latest-<?php echo esc_attr($template); ?>');
                const loading = document.getElementById('rch-loading-listing');

                listingList.innerHTML = '';
                loading.style.display = 'block';

                let queryString = `?action=rch_fetch_listing&listing_per_page=${listingPerPage}&shortcode_template=true&template=${template}`;
                fetch(adminAjaxUrl, {
                        method: 'POST', // Ensure method is POST
                        body: new URLSearchParams({
                            action: 'rch_fetch_listing',
                            listing_per_page: listingPerPage,
                            template: template,
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
                        initializeSwiper(swiperSettings);

                    })
                    .catch(error => {
                        console.error('Error fetching listing:', error);
                    });
            }

            function initializeSwiper(settings) {
                const swiperWrapper = document.querySelector('.swiper-wrapper');
                if (swiperWrapper && swiperWrapper.children.length > 0) {
                    new Swiper(".top-listing", settings); // Initialize Swiper with dynamic settings
                    new Swiper(".main-listing-index", settings); // Initialize another Swiper with same settings
                }
            }

            updateListingList();
        });
    </script>

<?php
    return ob_get_clean();
}

add_shortcode('rch_latest_listings', 'rch_display_latest_listings_shortcode');
