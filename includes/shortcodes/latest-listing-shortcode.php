<?php
function rch_display_latest_listings_shortcode($atts)
{
    // Set default attributes and override with user-provided attributes
    $atts = shortcode_atts(
        array(
            'limit' => 7, // Default limit to 7 listings if not provided
            'template' => '', // Default template is empty (uses the default template)
        ),
        $atts
    );

    ob_start();
?>
    <div class="swiper trendingSwiper">
        <div class="swiper-wrapper" id="rch-listing-list-latest"></div>
        <div id="rch-loading-listing" style="display: none;" class="rch-loader"></div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const listingPerPage = <?php echo intval($atts['limit']); ?>;
        const template = "<?php echo esc_js($atts['template']); ?>"; // Template parameter from shortcode

        function updateListingList() {
            const listingList = document.getElementById('rch-listing-list-latest');
            const loading = document.getElementById('rch-loading-listing');

            listingList.innerHTML = '';
            loading.style.display = 'block';

            // Construct the query string with the template parameter
            let queryString = `?action=rch_fetch_listing&listing_per_page=${listingPerPage}&shortcode_template=true&template=${template}`;

            fetch(`<?php echo admin_url('admin-ajax.php'); ?>${queryString}`)
                .then(response => response.text())
                .then(html => {
                    loading.style.display = 'none';
                    listingList.innerHTML = html;

                    initializeSwiper();
                })
                .catch(error => {
                    loading.style.display = 'none';
                    console.error('Error fetching listing:', error);
                    listingList.innerHTML = '<p>Error loading listing.</p>';
                });
        }

        function initializeSwiper() {
            const swiperWrapper = document.querySelector('.swiper-wrapper');
            if (swiperWrapper && swiperWrapper.children.length > 0) {
                new Swiper(".trendingSwiper", {
                    spaceBetween: 30,
                    slidesPerView: 4.5,
                    loop: true,
                    centeredSlides: true,
                    speed: 20000,
                    effect: "slide",
                    grabCursor: true,
                    simulateTouch: false,
                    autoplay: {
                        delay: 0,
                        disableOnInteraction: false,
                    },
                    breakpoints: {
                        0: { slidesPerView: 1.5 },
                        576: { slidesPerView: 2.5 },
                        768: { slidesPerView: 3.5 },
                        1200: { slidesPerView: 4.5 },
                    },
                });
            }
        }

        updateListingList();
    });
    </script>
<?php
    return ob_get_clean();
}
add_shortcode('rch_latest_listings', 'rch_display_latest_listings_shortcode');
