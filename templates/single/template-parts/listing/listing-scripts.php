<?php
/**
 * Template part for listing single page JavaScript functionality
 * 
 * This template includes:
 * - Rechat SDK initialization and lead tracking
 * - Lead capture form submission handler with AJAX
 * - Photo lightbox (#myModal): Swiper main + thumbs, open/close, keyboard, scroll lock
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

// Off Market single: route the lead to the Agent selected in the listing's meta
// box (agent_id → that Agent post's `email`), mirroring agents-single-custom.
// Wins over the multisite default so the chosen agent gets the inquiry.
if (is_singular('off_market')) {
    $rch_om_agent_id = (int) get_post_meta(get_the_ID(), 'agent_id', true);
    if ($rch_om_agent_id) {
        $rch_om_agent_email = sanitize_email((string) get_post_meta($rch_om_agent_id, 'email', true));
        if ($rch_om_agent_email !== '') {
            $rch_listing_assignee = $rch_om_agent_email;
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
    // Photo lightbox (#myModal): full-screen Swiper gallery with thumbnails.
    // Opened by every [data-slider] element in the top gallery (cover, grid tiles,
    // "View all photos"). Esc closes, arrow keys navigate, page scroll is locked
    // while open, focus returns to the element that opened it.
    (function () {
        var modal = document.getElementById('myModal');
        if (!modal || typeof Swiper === 'undefined') { return; }

        var mainEl   = modal.querySelector('.rch-lightbox__main');
        var thumbsEl = modal.querySelector('.rch-lightbox__thumbs');
        var closeBtn = modal.querySelector('.rch-lightbox__close');
        var currentEl = modal.querySelector('.rch-lightbox__current');
        var slides = mainEl ? mainEl.querySelectorAll('.swiper-slide') : [];
        if (!mainEl || !thumbsEl || !closeBtn || !slides.length) { return; }

        var opener = null;

        var thumbs = new Swiper(thumbsEl, {
            spaceBetween: 8,
            slidesPerView: 'auto',
            freeMode: true,
            watchSlidesProgress: true,
            slideToClickedSlide: true
        });

        var main = new Swiper(mainEl, {
            spaceBetween: 16,
            speed: 320,
            zoom: { maxRatio: 3 },
            navigation: {
                nextEl: modal.querySelector('.rch-lightbox__nav--next'),
                prevEl: modal.querySelector('.rch-lightbox__nav--prev')
            },
            thumbs: { swiper: thumbs }
        });

        // Lazy images only load when near the viewport; force the neighbours of the
        // current slide so the next swipe never shows an empty frame.
        function preloadAround(index) {
            for (var i = index - 2; i <= index + 2; i++) {
                var slide = slides[i];
                if (!slide) { continue; }
                var img = slide.querySelector('img');
                if (img && img.getAttribute('loading') === 'lazy') { img.setAttribute('loading', 'eager'); }
            }
        }

        function sync() {
            var index = main.activeIndex || 0;
            if (currentEl) { currentEl.textContent = index + 1; }
            preloadAround(index);
        }
        main.on('slideChange', sync);

        function isOpen() { return modal.classList.contains('is-open'); }

        function open(index, source) {
            opener = source || document.activeElement;
            modal.classList.add('is-open');
            document.documentElement.classList.add('rch-lightbox-open');
            // Swiper measured 0 while display:none — re-measure now that it is visible.
            thumbs.update();
            main.update();
            main.slideTo(index, 0);
            sync();
            closeBtn.focus();
        }

        function close() {
            if (!isOpen()) { return; }
            if (main.zoom && typeof main.zoom.out === 'function') { try { main.zoom.out(); } catch (e) {} }
            modal.classList.remove('is-open');
            document.documentElement.classList.remove('rch-lightbox-open');
            if (opener && typeof opener.focus === 'function') { opener.focus(); }
            opener = null;
        }

        var openers = document.querySelectorAll('.rch-top-img-slider [data-slider]');
        Array.prototype.forEach.call(openers, function (el) {
            var index = parseInt(el.getAttribute('data-slider'), 10) || 0;
            el.addEventListener('click', function (event) {
                event.preventDefault();
                open(index, el);
            });
            el.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    open(index, el);
                }
            });
        });

        closeBtn.addEventListener('click', close);

        // Click on the dark backdrop (anywhere that is not the photo or a control) closes.
        modal.addEventListener('click', function (event) {
            var t = event.target;
            if (!(t instanceof Element)) { return; }
            if (t === modal
                || t.classList.contains('rch-lightbox__stage')
                || t.classList.contains('rch-lightbox__bar')
                || (t.classList.contains('swiper-slide') && t.closest('.rch-lightbox__main'))
                || t.classList.contains('swiper-zoom-container')) {
                close();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (!isOpen()) { return; }
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                main.slideNext();
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                main.slidePrev();
            } else if (event.key === 'Tab') {
                // Keep focus inside the dialog.
                var focusables = modal.querySelectorAll('button:not([disabled])');
                if (!focusables.length) { return; }
                var first = focusables[0];
                var last = focusables[focusables.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
    })();

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
