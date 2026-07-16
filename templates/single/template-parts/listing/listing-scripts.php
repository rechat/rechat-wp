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

// Listing lead-capture defaults: on subsites, inherit from network main site when empty.
$rch_listing_lead_channel = (string) get_option('rch_lead_channels', '');
$rch_listing_tags         = get_option('rch_selected_tags', []);
$rch_listing_assignee     = '';

if (is_multisite()) {
    $rch_main_site_id = (int) get_main_site_id();
    $rch_here_id      = (int) get_current_blog_id();

    if ($rch_here_id > 0 && $rch_main_site_id > 0 && $rch_here_id !== $rch_main_site_id) {
        if ($rch_listing_lead_channel === '') {
            switch_to_blog($rch_main_site_id);
            $rch_listing_lead_channel = (string) get_option('rch_lead_channels', '');
            restore_current_blog();
        }

        $rch_tags_empty = !is_array($rch_listing_tags) || $rch_listing_tags === [];
        if ($rch_tags_empty) {
            switch_to_blog($rch_main_site_id);
            $rch_listing_tags = get_option('rch_selected_tags', []);
            restore_current_blog();
        }

        if (function_exists('rch_multisite_get_linked_agent_profile_email')) {
            $rch_listing_assignee = rch_multisite_get_linked_agent_profile_email();
        }
    }
}

$rch_listing_tags = is_array($rch_listing_tags) ? array_values($rch_listing_tags) : [];
$rch_listing_tags = array_map('sanitize_text_field', $rch_listing_tags);
$rch_listing_tags_json = wp_json_encode($rch_listing_tags);

// Anti-spam CAPTCHA assets (no-op unless a provider is configured).
if (function_exists('rch_lead_antispam_enqueue_captcha')) {
    rch_lead_antispam_enqueue_captcha();
}
$rch_listing_ajax_url = admin_url('admin-ajax.php');
?>

<script src="https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.js"></script>
<script>
    // Listing view tracking (analytics only). Wrapped so any SDK failure can never
    // break the lead form submit handler below.
    try {
        if (typeof Rechat !== 'undefined' && Rechat.Sdk) {
            var sdk = new Rechat.Sdk({
                tracker: { cookie: { name: 'rechat-sdk-tracker' } }
            });
            sdk.Leads.Tracker.capture({
                listing_id: '<?php echo esc_js($listing_detail['id']); ?>'
            });
        }
    } catch (e) {
        if (window.console) { console.warn('Rechat tracker unavailable:', e); }
    }

    // Lead capture posts to the WordPress server (anti-spam) which forwards to Rechat.
    (function () {
        var leadForm = document.querySelector('#leadCaptureForm form') || document.getElementById('leadCaptureForm');
        if (!leadForm || leadForm.tagName !== 'FORM') { return; }

        var rchListingAjaxUrl = '<?php echo esc_js($rch_listing_ajax_url); ?>';
        var rchListingChannel = '<?php echo esc_js($rch_listing_lead_channel); ?>';
        var rchListingTags = '<?php echo esc_js($rch_listing_tags_json); ?>';
        var rchListingAssignee = '<?php echo esc_js($rch_listing_assignee); ?>';
        var rchListingId = '<?php echo esc_js($listing_detail['id']); ?>';
        var rchListingMls = '<?php echo esc_js($listing_detail['mls_number']); ?>';

        leadForm.addEventListener('submit', function (event) {
            event.preventDefault();

            document.getElementById('rch-listing-success-sdk').style.display = 'none';
            document.getElementById('rch-listing-cancel-sdk').style.display = 'none';
            document.getElementById('loading-spinner').style.display = 'block';

            var tokenPromise = window.rchLeadToken ? window.rchLeadToken(leadForm) : Promise.resolve('');

            tokenPromise.then(function (token) {
                var fd = new FormData(leadForm);
                fd.set('action', 'rch_submit_lead_rechat_api');
                // Security fields from PHP so submission works even when the listing
                // template is overridden by the theme (no hidden fields in markup).
                fd.set('rch_lead_nonce_field', '<?php echo esc_js(wp_create_nonce('rch_lead_form')); ?>');
                fd.set('<?php echo esc_js(RCH_LEAD_TS_FIELD); ?>', '<?php echo esc_js(rch_lead_antispam_timestamp_value()); ?>');
                fd.set('lead_channel', rchListingChannel);
                fd.set('tags_json', rchListingTags);
                fd.set('listing_id', rchListingId);
                fd.set('mlsid', rchListingMls);
                fd.set('referer_url', window.location.href);
                if (rchListingAssignee) { fd.set('assignee_email', rchListingAssignee); }
                if (token) { fd.append('rch_captcha_token', token); }

                return fetch(rchListingAjaxUrl, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                });
            })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    document.getElementById('loading-spinner').style.display = 'none';
                    if (json && json.success) {
                        document.getElementById('rch-listing-success-sdk').style.display = 'block';
                        if (typeof leadForm.reset === 'function') { leadForm.reset(); }
                    } else {
                        document.getElementById('rch-listing-cancel-sdk').style.display = 'block';
                    }
                })
                .catch(function (e) {
                    document.getElementById('loading-spinner').style.display = 'none';
                    document.getElementById('rch-listing-cancel-sdk').style.display = 'block';
                    console.log('Error:', e);
                });
        });
    })();
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
