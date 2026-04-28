/**
 * Latest listings shortcode — Swiper init (Rechat listings + Swiper).
 *
 * Do not set Swiper observer/observeParents on Rechat-managed slides: it breaks React
 * inside the SDK (FilterContext / store errors). Init only after listings are fetched.
 */
(function (window) {
  'use strict';

  var SLIDE_SELECTOR = '.rechat-listings-list__item';

  function countSlides(swiperEl) {
    return swiperEl.querySelectorAll(SLIDE_SELECTOR).length;
  }

  /**
   * Loop + fractional slidesPerView with too few slides causes bad indices in some Swiper builds.
   *
   * @param {Record<string, unknown>} swiperConfig
   * @param {number} slideCount
   */
  function maybeDisableLoop(swiperConfig, slideCount) {
    if (!swiperConfig.loop || slideCount < 1) {
      return;
    }
    var spv = swiperConfig.slidesPerView;
    if (typeof spv !== 'number' || isNaN(spv)) {
      return;
    }
    var minSlides = Math.max(2, Math.ceil(spv) * 2);
    if (slideCount < minSlides) {
      swiperConfig.loop = false;
    }
  }

  /**
   * @param {string} uniqueId
   * @param {Record<string, unknown>} rawConfig
   * @returns {boolean} true if Swiper was constructed or already exists
   */
  function initSwiperInstance(uniqueId, rawConfig) {
    var container = document.getElementById(uniqueId);
    if (!container) {
      return false;
    }

    var swiperEl = container.querySelector('.swiper');
    if (!swiperEl) {
      return false;
    }

    if (typeof window.Swiper === 'undefined') {
      return false;
    }

    if (swiperEl.swiper) {
      return true;
    }

    var slideCount = countSlides(swiperEl);
    if (slideCount < 1) {
      return false;
    }

    var swiperConfig = JSON.parse(JSON.stringify(rawConfig));
    swiperConfig.slideClass = 'rechat-listings-list__item';

    maybeDisableLoop(swiperConfig, slideCount);

    var paginationEl = container.querySelector('.swiper-pagination');
    if (paginationEl && swiperConfig.pagination) {
      swiperConfig.pagination = Object.assign({}, swiperConfig.pagination, {
        el: paginationEl,
      });
    }

    var nextEl = container.querySelector('.swiper-button-next');
    var prevEl = container.querySelector('.swiper-button-prev');
    if (nextEl && prevEl && swiperConfig.navigation) {
      swiperConfig.navigation = Object.assign({}, swiperConfig.navigation, {
        nextEl: nextEl,
        prevEl: prevEl,
      });
    }

    new window.Swiper(swiperEl, swiperConfig);
    return true;
  }

  /**
   * @param {string} uniqueId
   * @param {Record<string, unknown>} swiperConfig
   * @param {number} attempt
   */
  function scheduleInit(uniqueId, swiperConfig, attempt) {
    attempt = attempt || 0;
    var maxAttempts = 60;

    function run() {
      if (initSwiperInstance(uniqueId, swiperConfig)) {
        return;
      }
      if (typeof window.Swiper === 'undefined') {
        window.setTimeout(run, 100);
        return;
      }
      if (attempt < maxAttempts) {
        window.setTimeout(function () {
          scheduleInit(uniqueId, swiperConfig, attempt + 1);
        }, 50);
      }
    }

    window.requestAnimationFrame(run);
  }

  /**
   * Called once per shortcode instance from wp_add_inline_script.
   * Match original behaviour: init after Rechat has fetched listing slides.
   *
   * @param {string} uniqueId
   * @param {Record<string, unknown>} swiperConfig
   */
  window.rchLatestListingsSwiperRegister = function (uniqueId, swiperConfig) {
    window.addEventListener('rechat-listings:fetched', function () {
      scheduleInit(uniqueId, swiperConfig, 0);
    });
  };
})(window);
