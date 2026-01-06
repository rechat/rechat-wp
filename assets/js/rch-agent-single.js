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
        constructor(formElement, sdk, config) {
            this.form = formElement;
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

            this.form.addEventListener('submit', (event) => {
                event.preventDefault();
                this.handleSubmit();
            });
        }

        async handleSubmit() {
            try {
                this._showLoading();

                const leadData = this._collectFormData();
                await this.sdk.Leads.capture(
                    { lead_channel: this.config.leadChannel },
                    leadData
                );

                this._showSuccess();
                this.form.reset();
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
            if (!Validator.isRechatSDKAvailable() || !Validator.isConfigValid()) {
                console.warn('Rechat SDK or configuration not available');
                return;
            }

            const sdk = new Rechat.Sdk();
            const formElement = DOMService.getElement('leadCaptureForm');
            
            if (formElement) {
                const leadForm = new LeadCaptureForm(formElement, sdk, this.config);
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
