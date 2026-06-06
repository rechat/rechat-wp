/**
 * Latest listings shortcode — Swiper init (Rechat listings + Swiper).
 *
 * Do not set Swiper observer/observeParents on Rechat-managed slides: it breaks React
 * inside the SDK (FilterContext / store errors). Init only after listings are fetched.
 */
(function (window) {
  'use strict';

  var SLIDE_SELECTOR = '.rechat-listings-list__item';
  var REAL_LISTING_SELECTOR = '.listing-card, a.listing-card__hyperlink, a[href*="listing-detail"]';
  var FEW_SLIDES_CENTER_THRESHOLD = 4;

  function slideHasRealListing(slide) {
    if (!slide) {
      return false;
    }
    if (slide.querySelector(REAL_LISTING_SELECTOR)) {
      return true;
    }
    var nodes = slide.querySelectorAll('*');
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].shadowRoot && nodes[i].shadowRoot.querySelector(REAL_LISTING_SELECTOR)) {
        return true;
      }
    }
    return false;
  }

  /** Count only slides with real listing cards — ignore SDK skeleton rows. */
  function countSlides(swiperEl) {
    var slides = swiperEl.querySelectorAll(SLIDE_SELECTOR);
    var realCount = 0;
    slides.forEach(function (slide) {
      if (slideHasRealListing(slide)) {
        realCount += 1;
      }
    });
    return realCount;
  }

  function getFewSlidesThreshold(swiperConfig) {
    if (typeof swiperConfig.autoCenterFewSlidesThreshold === 'number' && !isNaN(swiperConfig.autoCenterFewSlidesThreshold)) {
      return swiperConfig.autoCenterFewSlidesThreshold;
    }
    return FEW_SLIDES_CENTER_THRESHOLD;
  }

  function shouldCenterFewSlides(swiperConfig, slideCount) {
    if (swiperConfig.autoCenterFewSlides === false) {
      return false;
    }
    return slideCount > 0 && slideCount < getFewSlidesThreshold(swiperConfig);
  }

  /**
   * Largest numeric slidesPerView from root config and breakpoints (for loop math).
   *
   * @param {Record<string, unknown>} swiperConfig
   * @returns {number}
   */
  function getMaxNumericSlidesPerView(swiperConfig) {
    var maxSpv = 1;
    var root = swiperConfig.slidesPerView;
    if (typeof root === 'number' && !isNaN(root)) {
      maxSpv = Math.max(maxSpv, root);
    }
    var bps = swiperConfig.breakpoints;
    if (bps && typeof bps === 'object') {
      Object.keys(bps).forEach(function (key) {
        var bp = bps[key];
        if (bp && typeof bp.slidesPerView === 'number' && !isNaN(bp.slidesPerView)) {
          maxSpv = Math.max(maxSpv, bp.slidesPerView);
        }
      });
    }
    return maxSpv;
  }

  function capSlidesPerViewForCount(swiperConfig, slideCount) {
    if (slideCount <= 1) {
      return;
    }
    if (typeof swiperConfig.slidesPerView === 'number' && !isNaN(swiperConfig.slidesPerView)) {
      swiperConfig.slidesPerView = Math.min(swiperConfig.slidesPerView, slideCount);
    }
    if (swiperConfig.breakpoints && typeof swiperConfig.breakpoints === 'object') {
      Object.keys(swiperConfig.breakpoints).forEach(function (key) {
        var bp = swiperConfig.breakpoints[key];
        if (bp && typeof bp.slidesPerView === 'number' && !isNaN(bp.slidesPerView)) {
          bp.slidesPerView = Math.min(bp.slidesPerView, slideCount);
        }
      });
    }
  }

  /**
   * Swiper 11: loop + centeredSlides + fractional slidesPerView needs extra duplicated slides
   * and stable lengths; see migration guide (loopAdditionalSlides replaces loopedSlides).
   *
   * @param {Record<string, unknown>} swiperConfig
   * @param {number} slideCount
   */
  function applyLoopStabilityFixes(swiperConfig, slideCount) {
    if (!swiperConfig.loop || slideCount < 1) {
      return;
    }
    var maxSpv = getMaxNumericSlidesPerView(swiperConfig);
    var ceilSpv = Math.ceil(maxSpv);
    var extra = Math.max(ceilSpv * 2, 4);
    swiperConfig.loopAdditionalSlides = Math.min(slideCount, extra);

    swiperConfig.watchSlidesProgress = true;
  }

  function maybeDisableLoop(swiperConfig, slideCount) {
    if (!swiperConfig.loop || slideCount < 1) {
      return;
    }
    var maxSpv = getMaxNumericSlidesPerView(swiperConfig);
    if (typeof maxSpv !== 'number' || isNaN(maxSpv)) {
      return;
    }
    var minSlides = Math.max(2, Math.ceil(maxSpv) * 2);
    if (slideCount < minSlides) {
      swiperConfig.loop = false;
    }
  }

  function markFewSlidesContainer(container, slideCount) {
    container.classList.add('rch-latest-listings-swiper--few-slides');
    container.setAttribute('data-rch-slide-count', String(slideCount));
  }

  function applyFewSlidesCentering(swiperConfig, slideCount, container) {
    if (!shouldCenterFewSlides(swiperConfig, slideCount)) {
      return false;
    }

    markFewSlidesContainer(container, slideCount);
    swiperConfig.centeredSlides = true;
    swiperConfig.centerInsufficientSlides = true;
    swiperConfig.loop = false;
    delete swiperConfig.loopAdditionalSlides;
    capSlidesPerViewForCount(swiperConfig, slideCount);
    return true;
  }

  function centerFewSlidesTrack(swiper, slideCount) {
    if (!swiper || swiper.destroyed || slideCount < 1) {
      return;
    }

    var slides = swiper.slides;
    if (!slides || !slides.length) {
      return;
    }

    var count = Math.min(slideCount, slides.length);
    var space = Number(swiper.params.spaceBetween) || 0;
    var totalWidth = 0;

    for (var i = 0; i < count; i++) {
      totalWidth += slides[i].offsetWidth;
    }
    if (count > 1) {
      totalWidth += space * (count - 1);
    }

    var offset = Math.max(0, (swiper.width - totalWidth) / 2);
    swiper.setTranslate(offset);
    swiper.updateProgress();
    swiper.updateSlidesClasses();
  }

  function scheduleFewSlidesCenter(swiper, slideCount) {
    [0, 50, 150, 400].forEach(function (delay) {
      window.setTimeout(function () {
        if (swiper && !swiper.destroyed) {
          swiper.update();
          centerFewSlidesTrack(swiper, slideCount);
        }
      }, delay);
    });
  }

  function refreshFewSlidesLayout(swiper, rawConfig, container, swiperEl) {
    var slideCount = countSlides(swiperEl);
    if (!shouldCenterFewSlides(rawConfig, slideCount)) {
      return;
    }

    markFewSlidesContainer(container, slideCount);
    swiper.params.centeredSlides = true;
    swiper.params.centerInsufficientSlides = true;
    swiper.params.loop = false;
    capSlidesPerViewForCount(swiper.params, slideCount);
    swiper.update();
    scheduleFewSlidesCenter(swiper, slideCount);
  }

  function stripCustomConfigKeys(swiperConfig) {
    delete swiperConfig.autoCenterFewSlides;
    delete swiperConfig.autoCenterFewSlidesThreshold;
  }

  function buildSwiperConfig(rawConfig, slideCount, container) {
    var swiperConfig = JSON.parse(JSON.stringify(rawConfig));
    swiperConfig.slideClass = 'rechat-listings-list__item';

    var fewSlides = applyFewSlidesCentering(swiperConfig, slideCount, container);
    maybeDisableLoop(swiperConfig, slideCount);
    if (swiperConfig.loop) {
      applyLoopStabilityFixes(swiperConfig, slideCount);
    }

    stripCustomConfigKeys(swiperConfig);

    var sb = swiperConfig.spaceBetween;
    if (typeof sb === 'number' && !isNaN(sb) && swiperConfig.breakpoints && typeof swiperConfig.breakpoints === 'object') {
      Object.keys(swiperConfig.breakpoints).forEach(function (key) {
        var bp = swiperConfig.breakpoints[key];
        if (bp && typeof bp === 'object' && typeof bp.spaceBetween === 'undefined') {
          bp.spaceBetween = sb;
        }
      });
    }

    return { config: swiperConfig, fewSlides: fewSlides };
  }

  function bindFewSlidesEvents(swiper, slideCount) {
    swiper.on('resize', function () {
      centerFewSlidesTrack(swiper, slideCount);
    });
    scheduleFewSlidesCenter(swiper, slideCount);
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

    var slideCount = countSlides(swiperEl);

    if (swiperEl.swiper) {
      if (slideCount > 0) {
        refreshFewSlidesLayout(swiperEl.swiper, rawConfig, container, swiperEl);
      }
      return true;
    }

    if (slideCount < 1) {
      return false;
    }

    var built = buildSwiperConfig(rawConfig, slideCount, container);
    var swiperConfig = built.config;

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

    if (typeof swiperConfig.touchEventsTarget === 'undefined') {
      swiperConfig.touchEventsTarget = 'container';
    }

    var swiper = new window.Swiper(swiperEl, swiperConfig);
    if (built.fewSlides) {
      bindFewSlidesEvents(swiper, slideCount);
    } else {
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(function () {
          if (swiper && typeof swiper.update === 'function' && !swiper.destroyed) {
            swiper.update();
          }
        });
      });
    }

    return true;
  }

  function scheduleInit(uniqueId, swiperConfig, attempt) {
    attempt = attempt || 0;
    var maxAttempts = 80;

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

  window.rchLatestListingsSwiperRegister = function (uniqueId, swiperConfig) {
    window.addEventListener('rechat-listings:fetched', function () {
      scheduleInit(uniqueId, swiperConfig, 0);
    });
  };
})(window);
