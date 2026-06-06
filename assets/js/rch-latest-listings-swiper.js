/**
 * Latest listings shortcode — Swiper init (Rechat listings + Swiper).
 *
 * Do not set Swiper observer/observeParents on Rechat-managed slides: it breaks React
 * inside the SDK (FilterContext / store errors). Init only after listings are fetched.
 *
 * When listing count is below the threshold (default 4), skip Swiper and use flex centering
 * so a single card is not pushed off-screen by transform math.
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

  function shouldUseStaticCenter(swiperConfig, slideCount) {
    if (swiperConfig.autoCenterFewSlides === false) {
      return false;
    }
    return slideCount > 0 && slideCount < getFewSlidesThreshold(swiperConfig);
  }

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

  function stripCustomConfigKeys(swiperConfig) {
    delete swiperConfig.autoCenterFewSlides;
    delete swiperConfig.autoCenterFewSlidesThreshold;
  }

  function hideSkeletonSlides(swiperEl) {
    swiperEl.querySelectorAll(SLIDE_SELECTOR).forEach(function (slide) {
      if (slideHasRealListing(slide)) {
        slide.style.removeProperty('display');
        slide.removeAttribute('aria-hidden');
        return;
      }
      slide.style.display = 'none';
      slide.setAttribute('aria-hidden', 'true');
    });
  }

  function destroySwiperIfAny(swiperEl) {
    if (swiperEl.swiper && !swiperEl.swiper.destroyed) {
      swiperEl.swiper.destroy(true, true);
    }
  }

  function resetWrapperTransform(wrapper) {
    if (!wrapper) {
      return;
    }
    wrapper.style.transform = 'none';
    wrapper.style.transitionDuration = '0ms';
  }

  function applyStaticCenterLayout(container, swiperEl, slideCount) {
    container.classList.add('rch-latest-listings-swiper--few-slides');
    container.classList.add('rch-latest-listings-swiper-static-active');
    container.setAttribute('data-rch-slide-count', String(slideCount));
    container.setAttribute('data-rch-listings-swiper-mode', 'static-centered');

    swiperEl.classList.add('rch-latest-listings-swiper-static');
    hideSkeletonSlides(swiperEl);
    resetWrapperTransform(swiperEl.querySelector('rechat-listings-list.swiper-wrapper, .swiper-wrapper'));
  }

  function clearStaticCenterLayout(container, swiperEl) {
    container.classList.remove('rch-latest-listings-swiper--few-slides');
    container.classList.remove('rch-latest-listings-swiper-static-active');
    container.removeAttribute('data-rch-slide-count');
    container.removeAttribute('data-rch-listings-swiper-mode');
    swiperEl.classList.remove('rch-latest-listings-swiper-static');
  }

  function buildSwiperConfig(rawConfig, slideCount) {
    var swiperConfig = JSON.parse(JSON.stringify(rawConfig));
    swiperConfig.slideClass = 'rechat-listings-list__item';

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

    return swiperConfig;
  }

  function schedulePostInitLayoutFix(swiper) {
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        if (swiper && typeof swiper.update === 'function' && !swiper.destroyed) {
          swiper.update();
        }
      });
    });
  }

  /**
   * @param {string} uniqueId
   * @param {Record<string, unknown>} rawConfig
   * @returns {boolean} true if layout is ready or Swiper was constructed
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

    var slideCount = countSlides(swiperEl);
    if (slideCount < 1) {
      return false;
    }

    if (shouldUseStaticCenter(rawConfig, slideCount)) {
      destroySwiperIfAny(swiperEl);
      applyStaticCenterLayout(container, swiperEl, slideCount);
      return true;
    }

    clearStaticCenterLayout(container, swiperEl);

    if (typeof window.Swiper === 'undefined') {
      return false;
    }

    if (swiperEl.swiper) {
      return true;
    }

    var swiperConfig = buildSwiperConfig(rawConfig, slideCount);

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
    schedulePostInitLayoutFix(swiper);
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
