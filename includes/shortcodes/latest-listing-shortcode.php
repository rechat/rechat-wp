<?php
function rch_display_latest_listings_shortcode($atts)
{
    // Set default attributes and override with user-provided attributes
    $atts = shortcode_atts(
        array(
            'limit' => 7, // Default limit to 7 listings if not provided
            'template' => '', // Default template is empty (uses the default template)
            'slides_per_view' => 3.5, // Default for slidesPerView
            'space_between' => 16, // Default for spaceBetween
            'loop' => true, // Default for loop
            'breakpoints' => '', // Default breakpoints as empty
            'centered_slides' => false, // Default for centeredSlides (false)
        ),
        $atts
    );

    $template = esc_js($atts['template']); // Use the template attribute in JavaScript
    $slides_per_view = floatval($atts['slides_per_view']); // Convert to float
    $space_between = intval($atts['space_between']); // Convert to integer
    $loop = filter_var($atts['loop'], FILTER_VALIDATE_BOOLEAN); // Validate loop as boolean
    $centered_slides = filter_var($atts['centered_slides'], FILTER_VALIDATE_BOOLEAN); // Validate centeredSlides as boolean
    $breakpoints = $atts['breakpoints'] ? esc_js($atts['breakpoints']) : '{}'; // Escape breakpoints or use empty object

    ob_start();
?>
<div class="swiper thumbsSwiper trendingSwiper <?php echo esc_attr($template); ?>" thumbsSlider="">
<div class="swiper-wrapper" id="rch-listing-list-latest-<?php echo esc_attr($template); ?>"></div>
        <div id="rch-loading-listing" style="display: none;" class="rch-loader"></div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const listingPerPage = <?php echo intval($atts['limit']); ?>; // Escaped as an integer
        const template = "<?php echo esc_js($atts['template']); ?>"; // Escaped for use in JS
        const adminAjaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>"; // Escaped for use in URLs

        // Swiper settings from shortcode attributes
        const swiperSettings = {
            slidesPerView: <?php echo intval($slides_per_view); ?>, // Escaped as an integer
            spaceBetween: <?php echo intval($space_between); ?>, // Escaped as an integer
            loop: <?php echo $loop ? 'true' : 'false'; ?>, // Escaped as boolean
            centeredSlides: <?php echo $centered_slides ? 'true' : 'false'; ?>, // Escaped as boolean
            breakpoints: <?php echo wp_json_encode($breakpoints); ?>, // Escaped for JSON format
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
        };

        function updateListingList() {
            const listingList = document.getElementById('rch-listing-list-latest-<?php echo esc_attr($template); ?>'); // Escaped as attribute
            const loading = document.getElementById('rch-loading-listing');

            listingList.innerHTML = '';
            loading.style.display = 'block';

            // Construct the query string with the template parameter
            let queryString = `?action=rch_fetch_listing&listing_per_page=${listingPerPage}&shortcode_template=true&template=${template}`;

            fetch(`${adminAjaxUrl}${queryString}`) // Safe URL output
                .then(response => response.text())
                .then(html => {
                    loading.style.display = 'none';
                    listingList.innerHTML = html;

                    initializeSwiper(swiperSettings);
                })
                .catch(error => {
                    loading.style.display = 'none';
                    console.error('Error fetching listing:', error);
                    listingList.innerHTML = '<p>Error loading listing.</p>';
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
