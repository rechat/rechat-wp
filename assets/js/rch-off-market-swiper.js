/**
 * Off Market — swiper init for [rch_off_market display_type="swiper"].
 *
 * Uses the global `Swiper` provided by the active theme / SDK bundle (same
 * approach as rch-latest-listings-swiper.js). These are STATIC cards (not SDK
 * slides), so a plain init on load is safe — no observer, no fetch events.
 */
(function () {
    'use strict';

    function initAll() {
        if (typeof Swiper === 'undefined') {
            return; // theme hasn't provided Swiper (yet)
        }

        var wrappers = document.querySelectorAll('.rch-off-market-swiper');
        for (var i = 0; i < wrappers.length; i++) {
            var wrap = wrappers[i];
            var el = wrap.querySelector('.swiper');
            if (!el || el.dataset.omInit) {
                continue;
            }
            el.dataset.omInit = '1';

            var spv = parseFloat(wrap.dataset.spv) || 3;
            var space = parseInt(wrap.dataset.space, 10);
            if (isNaN(space)) { space = 24; }
            var loop = wrap.dataset.loop === 'true';
            var autoplay = wrap.dataset.autoplay === 'true';

            var opts = {
                slidesPerView: 1.2,
                spaceBetween: space,
                loop: loop,
                watchOverflow: true,
                navigation: {
                    nextEl: el.querySelector('.swiper-button-next'),
                    prevEl: el.querySelector('.swiper-button-prev')
                },
                pagination: {
                    el: el.querySelector('.swiper-pagination'),
                    clickable: true
                },
                breakpoints: {
                    576: { slidesPerView: Math.min(2, spv) },
                    768: { slidesPerView: Math.min(3, spv) },
                    1200: { slidesPerView: spv }
                }
            };

            if (autoplay) {
                var delay = parseInt(wrap.dataset.autoplayDelay, 10);
                opts.autoplay = {
                    delay: isNaN(delay) ? 3500 : delay,
                    disableOnInteraction: false
                };
            }

            /* global Swiper */
            new Swiper(el, opts);
        }
    }

    if (document.readyState !== 'loading') {
        initAll();
    } else {
        document.addEventListener('DOMContentLoaded', initAll);
    }
    // Late safety net if Swiper loads after DOMContentLoaded.
    window.addEventListener('load', initAll);
})();
