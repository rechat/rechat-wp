/**
 * [rch_leads_form] — submits to the WordPress server (admin-ajax) which runs the
 * anti-spam checks and forwards to Rechat. Per-instance config from wp_add_inline_script.
 */
(function (window) {
  'use strict';

  var VISIBLE = 'rch-lead-capture--visible';

  function getL10n() {
    return window.rchLeadCaptureL10n || {
      invalidPhone: 'Please enter a valid phone number',
      invalidEmail: 'Please enter a valid email address',
    };
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function isValidPhone(phone) {
    return /^[\d\s\-+()]+$/.test(phone) && phone.replace(/\D/g, '').length >= 10;
  }

  function setVisible(el, show) {
    if (!el) {
      return;
    }
    if (show) {
      el.classList.add(VISIBLE);
    } else {
      el.classList.remove(VISIBLE);
    }
  }

  function bindForm(config) {
    var formId = config.formId;
    var ajaxUrl = config.ajaxUrl || (window.ajaxurl || '');

    var form = document.getElementById(formId);
    if (!form) {
      console.error('Lead capture form not found:', formId);
      return;
    }

    var loadingEl = document.getElementById(formId + '_loading');
    var successEl = document.getElementById(formId + '_success');
    var errorEl = document.getElementById(formId + '_error');
    var l10n = getL10n();

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var phoneEl = document.getElementById(formId + '_phone_number');
      var emailEl = document.getElementById(formId + '_email');

      if (phoneEl && !isValidPhone(phoneEl.value.trim())) {
        window.alert(l10n.invalidPhone);
        return;
      }
      if (emailEl && !isValidEmail(emailEl.value.trim())) {
        window.alert(l10n.invalidEmail);
        return;
      }

      setVisible(loadingEl, true);
      setVisible(successEl, false);
      setVisible(errorEl, false);

      var tokenPromise = window.rchLeadToken ? window.rchLeadToken(form) : Promise.resolve('');

      tokenPromise.then(function (token) {
        var fd = new FormData(form);
        fd.set('action', 'rch_submit_lead_rechat_api');
        fd.set('referer_url', window.location.href);
        if (token) {
          fd.append('rch_captcha_token', token);
        }

        return fetch(ajaxUrl, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
        });
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          setVisible(loadingEl, false);
          if (json && json.success) {
            setVisible(successEl, true);
            form.reset();
          } else {
            setVisible(errorEl, true);
          }
        })
        .catch(function (err) {
          setVisible(loadingEl, false);
          setVisible(errorEl, true);
          console.error('Lead capture error:', err);
        });
    });
  }

  window.rchLeadCaptureInitInstance = function (config) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { bindForm(config); });
    } else {
      bindForm(config);
    }
  };
})(window);
