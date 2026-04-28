/**
 * [rch_leads_form] — Rechat Leads.capture() with per-instance config from wp_add_inline_script.
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

  function waitForRechat(callback, attempt) {
    attempt = attempt || 0;
    if (typeof window.Rechat !== 'undefined') {
      callback();
      return;
    }
    if (attempt < 120) {
      window.setTimeout(function () {
        waitForRechat(callback, attempt + 1);
      }, 50);
      return;
    }
    console.error('Rechat SDK not loaded');
  }

  function sanitizeInput(value) {
    if (!value) {
      return '';
    }
    return String(value).trim().replace(/[<>]/g, '');
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
    var leadChannel = config.leadChannel || '';
    var tags = config.tags || [];

    var form = document.getElementById(formId);
    if (!form) {
      console.error('Lead capture form not found:', formId);
      return;
    }

    var sdk = new window.Rechat.Sdk();
    var channel = { lead_channel: leadChannel };
    var loadingEl = document.getElementById(formId + '_loading');
    var successEl = document.getElementById(formId + '_success');
    var errorEl = document.getElementById(formId + '_error');
    var l10n = getL10n();

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var firstNameEl = document.getElementById(formId + '_first_name');
      var lastNameEl = document.getElementById(formId + '_last_name');
      var phoneEl = document.getElementById(formId + '_phone_number');
      var emailEl = document.getElementById(formId + '_email');
      var noteEl = document.getElementById(formId + '_note');

      var input = {
        source_type: 'Website',
        referer_url: window.location.href,
      };

      if (firstNameEl) {
        input.first_name = sanitizeInput(firstNameEl.value);
      }
      if (lastNameEl) {
        input.last_name = sanitizeInput(lastNameEl.value);
      }
      if (phoneEl) {
        var phone = sanitizeInput(phoneEl.value);
        if (!isValidPhone(phone)) {
          window.alert(l10n.invalidPhone);
          return;
        }
        input.phone_number = phone;
      }
      if (emailEl) {
        var email = sanitizeInput(emailEl.value);
        if (!isValidEmail(email)) {
          window.alert(l10n.invalidEmail);
          return;
        }
        input.email = email;
      }
      if (noteEl) {
        input.note = sanitizeInput(noteEl.value);
      }

      if (tags && tags.length > 0) {
        input.tag = tags;
      }

      setVisible(loadingEl, true);
      setVisible(successEl, false);
      setVisible(errorEl, false);

      sdk.Leads.capture(channel, input)
        .then(function () {
          setVisible(loadingEl, false);
          setVisible(successEl, true);
          form.reset();
        })
        .catch(function (err) {
          setVisible(loadingEl, false);
          setVisible(errorEl, true);
          console.error('Lead capture error:', err);
        });
    });
  }

  /**
   * @param {{ formId: string, leadChannel: string, tags: string[] }} config
   */
  window.rchLeadCaptureInitInstance = function (config) {
    waitForRechat(function () {
      bindForm(config);
    });
  };
})(window);
