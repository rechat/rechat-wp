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
    // Enough buffer for centered + fractional SPV loop translation bugs (Swiper #6024 class issues).
    var extra = Math.max(ceilSpv * 2, 4);
    swiperConfig.loopAdditionalSlides = Math.min(slideCount, extra);

    swiperConfig.watchSlidesProgress = true;
    if (swiperConfig.centeredSlides) {
      swiperConfig.roundLengths = true;
    }
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
    var maxSpv = getMaxNumericSlidesPerView(swiperConfig);
    if (typeof maxSpv !== 'number' || isNaN(maxSpv)) {
      return;
    }
    var minSlides = Math.max(2, Math.ceil(maxSpv) * 2);
    if (slideCount < minSlides) {
      swiperConfig.loop = false;
    }
  }

  /**
   * @param {Event} ev
   * @param {HTMLElement} container
   * @returns {boolean}
   */
  function eventBelongsToContainer(ev, container) {
    var host = container.querySelector('rechat-listings');
    if (!host) {
      return false;
    }
    if (ev.composedPath) {
      var path = ev.composedPath();
      if (path.indexOf(host) !== -1) {
        return true;
      }
    }
    var t = ev.target;
    if (t && (t === host || (typeof host.contains === 'function' && host.contains(t)))) {
      return true;
    }
    if (t && typeof container.contains === 'function' && container.contains(t)) {
      return true;
    }
    // SDK sometimes fires a global custom event; single widget on page cannot be disambiguated.
    var widgets = document.querySelectorAll('.rch-latest-listings-shortcode-swiper[id^="rch-latest-listings-"]');
    return widgets.length === 1 && widgets[0] === container;
  }

  /**
   * @param {{ update: Function, destroyed?: boolean }} swiper
   */
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
    if (swiperConfig.loop) {
      applyLoopStabilityFixes(swiperConfig, slideCount);
    }

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

    var swiper = new window.Swiper(swiperEl, swiperConfig);
    schedulePostInitLayoutFix(swiper);
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
    window.addEventListener('rechat-listings:fetched', function (ev) {
      var container = document.getElementById(uniqueId);
      if (!container || !eventBelongsToContainer(ev, container)) {
        return;
      }
      scheduleInit(uniqueId, swiperConfig, 0);
    });
  };
})(window);
