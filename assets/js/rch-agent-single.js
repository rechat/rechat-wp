/**
 * Agent Single Page JavaScript
 * Handles lead capture form submission and agent properties listing with pagination
 * 
 * @package RechatPlugin
 * @version 2.0.0
 * 
 * Architecture follows SOLID principles:
 * - Single Responsibility: Each class has one clear purpose
 * - Open/Closed: Extensible without modification
 * - Liskov Substitution: Interface-based design
 * - Interface Segregation: Focused interfaces
 * - Dependency Inversion: Depends on abstractions
 */

(function($) {
    'use strict';

    // ============================================================================
    // Configuration & Constants
    // ============================================================================

    const CONFIG = {
        LISTING_STATUSES: 'Active, Sold, Pending,Temp Off Market, Leased,Active Option Contract, Active Contingent, Active Kick Out, Incoming,Coming Soon,Active Under Contract',
        LISTINGS_PER_PAGE: 12,
        PAGINATION_RANGE: 2,
        MESSAGES: {
            NO_PROPERTIES: 'No properties found for this agent.',
            ERROR_LOADING: 'Error loading properties. Please try again later.',
            ERROR_PAGINATION: 'Error loading pagination.',
            LOADING_PAGINATION: 'Loading pagination...'
        }
    };

    // ============================================================================
    // Utility Classes
    // ============================================================================

    /**
     * Handles DOM element manipulation and visibility
     * Single Responsibility: DOM operations only
     */
    class DOMService {
        static show(element) {
            if (element) element.style.display = 'block';
        }

        static hide(element) {
            if (element) element.style.display = 'none';
        }

        static setDisplay(element, display) {
            if (element) element.style.display = display;
        }

        static setHTML(element, html) {
            if (element) element.innerHTML = html;
        }

        static getElement(id) {
            return document.getElementById(id);
        }

        static getValue(id) {
            const element = this.getElement(id);
            return element ? element.value : '';
        }

        static addClass(element, className) {
            if (element) element.classList.add(className);
        }

        static removeClass(element, className) {
            if (element) element.classList.remove(className);
        }

        static createButton(text, onClick, className = '') {
            const button = document.createElement('button');
            button.textContent = text;
            if (className) button.className = className;
            button.onclick = onClick;
            return button;
        }

        static createImageButton(src, alt, onClick, className = '') {
            const button = document.createElement('button');
            const img = document.createElement('img');
            img.src = src;
            img.alt = alt;
            img.className = className;
            button.appendChild(img);
            button.onclick = onClick;
            return button;
        }
    }

    /**
     * Validates data and configuration
     * Single Responsibility: Validation logic only
     */
    class Validator {
        static isConfigValid() {
            return typeof rchAgentData !== 'undefined';
        }

        static hasAgentIds() {
            return this.isConfigValid() && 
                   rchAgentData.agentMatrixIds && 
                   rchAgentData.agentMatrixIds.length > 0;
        }

        static isPageValid(page, totalPages) {
            return page >= 1 && page <= totalPages;
        }

        static isRechatSDKAvailable() {
            return typeof Rechat !== 'undefined' && Rechat.Sdk;
        }
    }

    /**
     * Handles HTTP requests to WordPress AJAX endpoints
     * Single Responsibility: API communication only
     */
    class AjaxService {
        constructor(ajaxUrl) {
            this.ajaxUrl = ajaxUrl;
        }

        async post(params) {
            try {
                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(params)
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                return await response.json();
            } catch (error) {
                console.error('AJAX request failed:', error);
                throw error;
            }
        }

        async fetchListings(params) {
            const data = await this.post({
                action: 'rch_fetch_listing',
                ...params
            });

            if (!data.success) {
                throw new Error('Failed to fetch listings');
            }

            return data.data.listings || [];
        }

        async fetchTotalCount(params) {
            const data = await this.post({
                action: 'rch_fetch_total_listing_count',
                ...params
            });

            if (!data.success) {
                throw new Error('Failed to fetch total count');
            }

            return data.data.total;
        }
    }

    // ============================================================================
    // Business Logic Classes
    // ============================================================================

    /**
     * Manages pagination state and logic
     * Single Responsibility: Pagination state management
     */
    class PaginationState {
        constructor(listingsPerPage = CONFIG.LISTINGS_PER_PAGE) {
            this.currentPage = 1;
            this.totalPages = 1;
            this.listingsPerPage = listingsPerPage;
        }

        setTotalPages(total) {
            this.totalPages = Math.ceil(total / this.listingsPerPage);
        }

        canGoToPage(page) {
            return Validator.isPageValid(page, this.totalPages);
        }

        goToPage(page) {
            if (this.canGoToPage(page)) {
                this.currentPage = page;
                return true;
            }
            return false;
        }

        nextPage() {
            return this.goToPage(this.currentPage + 1);
        }

        previousPage() {
            return this.goToPage(this.currentPage - 1);
        }

        reset() {
            this.currentPage = 1;
        }

        shouldShowPage(page) {
            return page === 1 || 
                   page === this.totalPages || 
                   Math.abs(page - this.currentPage) <= CONFIG.PAGINATION_RANGE;
        }
    }

    /**
     * Renders pagination UI
     * Single Responsibility: Pagination UI rendering
     */
    class PaginationRenderer {
        constructor(container, state, iconPaths) {
            this.container = container;
            this.state = state;
            this.iconPaths = iconPaths;
        }

        render(onPageChange) {
            DOMService.setHTML(this.container, '');

            if (this.state.totalPages <= 1) {
                DOMService.hide(this.container);
                return;
            }

            this._renderPreviousButton(onPageChange);
            this._renderPageNumbers(onPageChange);
            this._renderNextButton(onPageChange);

            DOMService.setDisplay(this.container, 'flex');
        }

        _renderPreviousButton(onPageChange) {
            if (this.state.currentPage > 1) {
                const prevButton = DOMService.createImageButton(
                    this.iconPaths.prev,
                    'Previous',
                    () => onPageChange(-1),
                    'pagination-icon'
                );
                this.container.appendChild(prevButton);
            }
        }

        _renderNextButton(onPageChange) {
            if (this.state.currentPage < this.state.totalPages) {
                const nextButton = DOMService.createImageButton(
                    this.iconPaths.next,
                    'Next',
                    () => onPageChange(1),
                    'pagination-icon'
                );
                this.container.appendChild(nextButton);
            }
        }

        _renderPageNumbers(onPageChange) {
            let lastShownPage = 0;

            for (let page = 1; page <= this.state.totalPages; page++) {
                if (this.state.shouldShowPage(page)) {
                    if (lastShownPage > 0 && page > lastShownPage + 1) {
                        this._renderEllipsis();
                    }

                    const isActive = page === this.state.currentPage;
                    const pageButton = DOMService.createButton(
                        page,
                        () => onPageChange(0, page),
                        isActive ? 'active' : ''
                    );
                    this.container.appendChild(pageButton);

                    lastShownPage = page;
                }
            }
        }

        _renderEllipsis() {
            const dots = document.createElement('span');
            dots.textContent = '...';
            this.container.appendChild(dots);
        }

        updateActiveState() {
            const buttons = this.container.querySelectorAll('button');
            buttons.forEach(button => {
                const pageNum = parseInt(button.textContent);
                if (!isNaN(pageNum)) {
                    const isActive = pageNum === this.state.currentPage;
                    isActive ? 
                        DOMService.addClass(button, 'active') : 
                        DOMService.removeClass(button, 'active');
                }
            });
        }

        showLoading() {
            DOMService.setHTML(
                this.container, 
                `<div class="rch-pagination-loading">${CONFIG.MESSAGES.LOADING_PAGINATION}</div>`
            );
            DOMService.setDisplay(this.container, 'flex');
        }

        showError() {
            DOMService.setHTML(
                this.container, 
                `<p>${CONFIG.MESSAGES.ERROR_PAGINATION}</p>`
            );
            DOMService.setDisplay(this.container, 'flex');
        }
    }

    /**
     * Renders property listings
     * Single Responsibility: Listings UI rendering
     */
    class ListingsRenderer {
        constructor(container, loadingElement) {
            this.container = container;
            this.loadingElement = loadingElement;
        }

        showLoading() {
            DOMService.setHTML(this.container, '');
            DOMService.show(this.loadingElement);
        }

        hideLoading() {
            DOMService.hide(this.loadingElement);
        }

        renderListings(listings) {
            this.hideLoading();
            
            if (!listings || listings.length === 0) {
                this.showNoResults();
                return;
            }

            const html = listings.map(listing => listing.content).join('');
            DOMService.setHTML(this.container, html);
        }

        showNoResults() {
            this.hideLoading();
            // Hide pagination if present
            const pagination = DOMService.getElement('agent-pagination');
            if (pagination) DOMService.hide(pagination);

            DOMService.setHTML(
                this.container, 
                `<li class="no-properties">${CONFIG.MESSAGES.NO_PROPERTIES}</li>`
            );
        }

        showError() {
            this.hideLoading();
            DOMService.setHTML(
                this.container, 
                `<li class="no-properties">${CONFIG.MESSAGES.ERROR_LOADING}</li>`
            );
        }
    }

    /**
     * Orchestrates agent properties display with pagination
     * Single Responsibility: Coordinate listings and pagination
     */
    class AgentPropertiesManager {
        constructor(config, ajaxService, listingsRenderer, paginationRenderer, paginationState) {
            this.config = config;
            this.ajaxService = ajaxService;
            this.listingsRenderer = listingsRenderer;
            this.paginationRenderer = paginationRenderer;
            this.paginationState = paginationState;
        }

        async initialize() {
            if (!Validator.hasAgentIds()) {
                this.listingsRenderer.hideLoading();
                this.listingsRenderer.showNoResults();
                return;
            }

            await this.loadListings();
        }

        async loadListings() {
            try {
                this.listingsRenderer.showLoading();

                const params = this._buildListingsParams();
                const listings = await this.ajaxService.fetchListings(params);

                this.listingsRenderer.renderListings(listings);

                if (this.paginationState.currentPage === 1) {
                    await this.loadPaginationData();
                }
            } catch (error) {
                console.error('Error loading listings:', error);
                this.listingsRenderer.showError();
            }
        }

        async loadPaginationData() {
            try {
                this.paginationRenderer.showLoading();

                const params = this._buildCountParams();
                const total = await this.ajaxService.fetchTotalCount(params);

                this.paginationState.setTotalPages(total);
                this.paginationRenderer.render(
                    (direction, page) => this.handlePageChange(direction, page)
                );
            } catch (error) {
                console.error('Error loading pagination:', error);
                this.paginationRenderer.showError();
            }
        }

        async handlePageChange(direction = 0, page = null) {
            const targetPage = page || (this.paginationState.currentPage + direction);
            
            if (this.paginationState.goToPage(targetPage)) {
                this.paginationRenderer.updateActiveState();
                await this.loadListings();
            }
        }

        _buildListingsParams() {
            return {
                page: this.paginationState.currentPage,
                listing_per_page: this.paginationState.listingsPerPage,
                brand: this.config.brandId,
                agents: this.config.agentMatrixIds,
                order_by: this.config.sortBy,
                listing_statuses: CONFIG.LISTING_STATUSES
            };
        }

        _buildCountParams() {
            return {
                brand: this.config.brandId,
                agents: this.config.agentMatrixIds,
                sortBy: this.config.sortBy,
                listing_statuses: CONFIG.LISTING_STATUSES
            };
        }
    }

    /**
     * Handles lead capture form submission
     * Single Responsibility: Lead form processing
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
            DOMService.show(this.elements.success);
        }

        _showError() {
            DOMService.hide(this.elements.loading);
            DOMService.show(this.elements.error);
        }
    }

    // ============================================================================
    // Application Initialization
    // ============================================================================

    /**
     * Main application class - coordinates all components
     * Single Responsibility: Application initialization and coordination
     */
    class AgentSinglePageApp {
        constructor() {
            this.config = rchAgentData;
        }

        initialize() {
            this._initializeLeadForm();
            this._initializePropertiesListing();
        }

        _initializeLeadForm() {
            if (!Validator.isRechatSDKAvailable() || !Validator.isConfigValid()) {
                return;
            }

            const sdk = new Rechat.Sdk();
            const formElement = DOMService.getElement('leadCaptureForm');
            
            if (formElement) {
                const leadForm = new LeadCaptureForm(formElement, sdk, this.config);
                leadForm.initialize();
            }
        }

        _initializePropertiesListing() {
            if (!Validator.isConfigValid()) {
                return;
            }

            const ajaxService = new AjaxService(this.config.ajaxUrl);
            const paginationState = new PaginationState();

            const listingsRenderer = new ListingsRenderer(
                DOMService.getElement('agent-properties-list'),
                DOMService.getElement('loading-properties')
            );

            const paginationRenderer = new PaginationRenderer(
                DOMService.getElement('agent-pagination'),
                paginationState,
                {
                    prev: this.config.prevIconPath,
                    next: this.config.nextIconPath
                }
            );

            const propertiesManager = new AgentPropertiesManager(
                this.config,
                ajaxService,
                listingsRenderer,
                paginationRenderer,
                paginationState
            );

            propertiesManager.initialize();
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
