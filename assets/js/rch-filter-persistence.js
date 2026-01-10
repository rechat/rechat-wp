/**
 * Filter Persistence Module
 * Handles saving and restoring filter state across page navigation
 * Uses sessionStorage to preserve filters when navigating to single listings and returning via Back button
 */

(function() {
    'use strict';

    const STORAGE_KEY = 'rch_listing_filters_state';
    const SCROLL_KEY = 'rch_listing_scroll_position';
    const PAGE_KEY = 'rch_listing_current_page';

    /**
     * Filter Persistence Manager
     */
    window.RCH_FilterPersistence = {
        /**
         * Save current filter state to sessionStorage
         * @param {Object} filters - Current filter object
         * @param {number} currentPage - Current pagination page
         */
        saveState: function(filters, currentPage) {
            try {
                const state = {
                    filters: filters,
                    currentPage: currentPage || 1,
                    scrollPosition: window.pageYOffset || document.documentElement.scrollTop,
                    timestamp: Date.now()
                };
                
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
                
                console.log('Filter state saved:', state);
            } catch (e) {
                console.error('Error saving filter state:', e);
            }
        },

        /**
         * Retrieve saved filter state from sessionStorage
         * @returns {Object|null} Saved state object or null if not found
         */
        getState: function() {
            try {
                const savedState = sessionStorage.getItem(STORAGE_KEY);
                
                if (savedState) {
                    const state = JSON.parse(savedState);
                    
                    // Check if state is still fresh (within 1 hour)
                    const ONE_HOUR = 60 * 60 * 1000;
                    if (Date.now() - state.timestamp < ONE_HOUR) {
                        console.log('Filter state retrieved:', state);
                        return state;
                    } else {
                        // State is too old, clear it
                        this.clearState();
                    }
                }
            } catch (e) {
                console.error('Error retrieving filter state:', e);
            }
            
            return null;
        },

        /**
         * Clear saved filter state
         */
        clearState: function() {
            try {
                sessionStorage.removeItem(STORAGE_KEY);
                sessionStorage.removeItem(SCROLL_KEY);
                sessionStorage.removeItem(PAGE_KEY);
                console.log('Filter state cleared');
            } catch (e) {
                console.error('Error clearing filter state:', e);
            }
        },

        /**
         * Check if we came from a single listing (via Back button)
         * @returns {boolean}
         */
        isReturningFromSingleListing: function() {
            // Check if we have navigation type indicating back button
            const perfData = window.performance && window.performance.getEntriesByType('navigation')[0];
            const isBackNavigation = perfData && perfData.type === 'back_forward';
            
            // Also check if we have saved state
            const hasSavedState = sessionStorage.getItem(STORAGE_KEY) !== null;
            
            return isBackNavigation && hasSavedState;
        },

        /**
         * Restore scroll position from saved state
         * @param {number} scrollPosition - Y scroll position to restore
         */
        restoreScrollPosition: function(scrollPosition) {
            if (scrollPosition && scrollPosition > 0) {
                // Use a slight delay to ensure content is rendered
                setTimeout(function() {
                    window.scrollTo(0, scrollPosition);
                    console.log('Scroll position restored:', scrollPosition);
                }, 100);
            }
        },

        /**
         * Check if this is initial page load (not from Back button)
         * @returns {boolean}
         */
        isInitialPageLoad: function() {
            const perfData = window.performance && window.performance.getEntriesByType('navigation')[0];
            return !perfData || (perfData.type !== 'back_forward' && perfData.type !== 'reload');
        },

        /**
         * Initialize persistence on listing archive page
         * Call this after filters object and functions are defined
         */
        initializeArchivePage: function() {
            // Clear state if this is a fresh page load (not from Back button)
            if (this.isInitialPageLoad() && !window.location.search.includes('content=')) {
                // Only clear if there's no search query in URL
                this.clearState();
                console.log('Fresh page load - cleared old filter state');
            }
        },

        /**
         * Attach click handlers to listing links to save state before navigation
         * @param {string} selector - CSS selector for listing links
         */
        attachListingLinkHandlers: function(selector) {
            const self = this;
            
            // Use event delegation for dynamically loaded content
            document.addEventListener('click', function(e) {
                // Check if the clicked element is a link inside a listing item
                const link = e.target.closest('.house-item a, .rch-listing-item a');
                
                if (link && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
                    // Save state before navigating
                    if (typeof filters !== 'undefined' && typeof currentPage !== 'undefined') {
                        self.saveState(filters, currentPage);
                        console.log('Saving state before navigating to listing');
                    }
                }
            });
        },

        /**
         * Mark that we should restore state on next load
         * Useful for when user explicitly clicks a listing
         */
        markForRestore: function() {
            try {
                sessionStorage.setItem('rch_should_restore', 'true');
            } catch (e) {
                console.error('Error marking for restore:', e);
            }
        },

        /**
         * Check if we should restore state
         * @returns {boolean}
         */
        shouldRestore: function() {
            try {
                const shouldRestore = sessionStorage.getItem('rch_should_restore') === 'true';
                sessionStorage.removeItem('rch_should_restore'); // Clear after checking
                return shouldRestore || this.isReturningFromSingleListing();
            } catch (e) {
                return false;
            }
        }
    };

    console.log('RCH Filter Persistence module loaded');
})();
