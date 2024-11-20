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
    $template = esc_js($atts['template']); // Use the template attribute in JavaScript

    ob_start();
?>
    <div class="swiper trendingSwiper <?php echo $template ?>">
        <div class="swiper-wrapper" id="rch-listing-list-latest-<?php echo $template ?>"></div>
        <div id="rch-loading-listing" style="display: none;" class="rch-loader"></div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const listingPerPage = <?php echo intval($atts['limit']); ?>;
            const template = "<?php echo esc_js($atts['template']); ?>"; // Template parameter from shortcode

            function updateListingList() {
                const listingList = document.getElementById('rch-listing-list-latest-<?php echo $template ?>');
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
                    new Swiper(".top-listing", {
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
                            0: {
                                slidesPerView: 1.5
                            },
                            576: {
                                slidesPerView: 2.5
                            },
                            768: {
                                slidesPerView: 3.5
                            },
                            1200: {
                                slidesPerView: 4.5
                            },
                        },
                    });
                    new Swiper(".main-listing-index", {
                        slidesPerView: 3.5,
                        spaceBetween: 16,
                        centeredSlides: true,
                        loop: true,
                        navigation: {
                            nextEl: ".swiper-button-next",
                            prevEl: ".swiper-button-prev",
                        },

                        breakpoints: {
                            0: {
                                slidesPerView: 1.15,
                            },
                            375: {
                                slidesPerView: 1.25,
                            },
                            425: {
                                slidesPerView: 1.75,
                            },
                            576: {
                                slidesPerView: 2.15,
                            },
                            768: {
                                slidesPerView: 2.25,
                            },
                            992: {
                                slidesPerView: 2.75,
                            },
                            1200: {
                                slidesPerView: 3.15,
                            },
                            1320: {
                                slidesPerView: 3.5,
                            },
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
