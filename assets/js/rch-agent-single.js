/**
 * Agent Single Page JavaScript - Simplified for Lead Capture Only
 * Handles lead capture form submission using Rechat SDK
 * 
 * @package RechatPlugin
 * @version 2.1.0
 * 
 * Note: Listing display is now handled by Rechat Web Components
 */

(function($) {
    'use strict';

    // ============================================================================
    // Utility Classes
    // ============================================================================

    /**
     * Handles DOM element manipulation
     */
    class DOMService {
        static show(element) {
            if (element) element.style.display = 'block';
        }

        static hide(element) {
            if (element) element.style.display = 'none';
        }

        static getElement(id) {
            return document.getElementById(id);
        }

        static getValue(id) {
            const element = this.getElement(id);
            return element ? element.value : '';
        }
    }

    /**
     * Validates configuration and SDK availability
     */
    class Validator {
        static isConfigValid() {
            return typeof rchAgentData !== 'undefined';
        }

        static isRechatSDKAvailable() {
            return typeof Rechat !== 'undefined' && Rechat.Sdk;
        }
    }

    // ============================================================================
    // Lead Capture Form Handler
    // ============================================================================

    /**
     * Handles lead capture form submission
     */
    class LeadCaptureForm {
        constructor(formRoot, sdk, config) {
            // Template uses id="leadCaptureForm" on wrapper div; bind real <form> for submit/reset.
            this.formRoot = formRoot;
            this.form =
                formRoot && formRoot.tagName === 'FORM'
                    ? formRoot
                    : formRoot
                      ? formRoot.querySelector('form')
                      : null;
            this.sdk = sdk;
            this.config = config;
            this.elements = {
                success: DOMService.getElement('rch-listing-success-sdk'),
                error: DOMService.getElement('rch-listing-cancel-sdk'),
                loading: DOMService.getElement('loading-spinner')
            };
        }

        initialize() {
            if (!this.form) return;

            DOMService.hide(this.elements.success);
            DOMService.hide(this.elements.error);
            DOMService.hide(this.elements.loading);

            this.form.addEventListener('submit', (event) => {
                event.preventDefault();
                this.handleSubmit();
            });
        }

        async handleSubmit() {
            try {
                this._showLoading();

                const ajaxUrl = (this.config && this.config.ajaxUrl) || (window.ajaxurl || '');
                const token = window.rchLeadToken ? await window.rchLeadToken(this.form) : '';

                const cfg = this.config || {};
                const fd = new FormData(this.form);
                fd.set('action', 'rch_submit_lead_rechat_api');
                fd.set('referer_url', window.location.href);

                // Supply security + config from localized data so the form works even
                // when the agent template is overridden by the theme (no hidden fields).
                if (cfg.nonce) { fd.set('rch_lead_nonce_field', cfg.nonce); }
                if (cfg.ts) { fd.set(cfg.tsField || 'rch_form_ts', cfg.ts); }
                fd.set('lead_channel', cfg.leadChannel || '');
                if (cfg.agentEmail) { fd.set('assignee_email', cfg.agentEmail); }
                fd.set('tags_json', JSON.stringify(cfg.tags || []));
                if (token) {
                    fd.append('rch_captcha_token', token);
                }

                const res = await fetch(ajaxUrl, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                });
                const json = await res.json();

                if (!json || !json.success) {
                    throw new Error('Lead capture failed');
                }

                this._showSuccess();
                if (typeof this.form.reset === 'function') {
                    this.form.reset();
                }
            } catch (error) {
                console.error('Lead capture error:', error);
                this._showError();
            }
        }

        _collectFormData() {
            return {
                first_name: DOMService.getValue('first_name'),
                last_name: DOMService.getValue('last_name'),
                phone_number: DOMService.getValue('phone_number'),
                email: DOMService.getValue('email'),
                note: DOMService.getValue('note'),
                tag: this.config.tags,
                source_type: 'Website',
                agent_emails: this.config.agentEmail,
                referer_url: window.location.href
            };
        }

        _showLoading() {
            DOMService.hide(this.elements.success);
            DOMService.hide(this.elements.error);
            DOMService.show(this.elements.loading);
        }

        _showSuccess() {
            DOMService.hide(this.elements.loading);
            DOMService.hide(this.elements.error);
            DOMService.show(this.elements.success);

            // Hide success message after 5 seconds
            setTimeout(() => {
                DOMService.hide(this.elements.success);
            }, 5000);
        }

        _showError() {
            DOMService.hide(this.elements.loading);
            DOMService.hide(this.elements.success);
            DOMService.show(this.elements.error);

            // Hide error message after 5 seconds
            setTimeout(() => {
                DOMService.hide(this.elements.error);
            }, 5000);
        }

        /**
         * SDK may resolve with { code: 'OK', data: {...} } or with no body on success.
         *
         * @param {unknown} result
         * @returns {boolean}
         */
        _isCaptureSuccess(result) {
            if (result === undefined || result === null) {
                return true;
            }
            if (typeof result !== 'object') {
                return true;
            }
            const code = /** @type {{ code?: string }} */ (result).code;
            if (code === undefined || code === null || code === '') {
                return true;
            }
            return String(code).toUpperCase() === 'OK';
        }
    }

    // ============================================================================
    // Application Initialization
    // ============================================================================

    /**
     * Initialize lead capture form when DOM is ready
     */
    class AgentSinglePageApp {
        constructor() {
            this.config = rchAgentData;
        }

        initialize() {
            if (!Validator.isConfigValid()) {
                console.warn('Rechat lead configuration not available');
                return;
            }

            const formRoot = DOMService.getElement('leadCaptureForm');

            if (formRoot) {
                // Submission goes through the WordPress server (anti-spam), not the SDK.
                const leadForm = new LeadCaptureForm(formRoot, null, this.config);
                leadForm.initialize();
            }
        }
    }

    // ============================================================================
    // Bootstrap Application
    // ============================================================================

    document.addEventListener('DOMContentLoaded', function() {
        const app = new AgentSinglePageApp();
        app.initialize();
    });

})(jQuery);
