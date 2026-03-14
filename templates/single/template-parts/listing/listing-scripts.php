<?php
/**
 * Template part for listing single page JavaScript functionality
 * 
 * This template includes:
 * - Rechat SDK initialization and lead tracking
 * - Lead capture form submission handler with AJAX
 * - Swiper slider initialization (main and thumbnail sliders with responsive breakpoints)
 * - Modal open/close handlers for image gallery
 * - Show more/less description toggle functionality
 * 
 * @package Rechat_Plugin
 * @var array $listing_detail The listing detail array containing all property information
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Ensure $listing_detail is available
if (!isset($listing_detail) || !is_array($listing_detail)) {
    return;
}
?>

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
            tag: <?php echo get_option("rch_selected_tags", '[]'); ?>,
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
