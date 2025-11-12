/**
 * Agent Single Page JavaScript
 * Handles lead capture form submission and agent properties listing with pagination
 */

(function($) {
    'use strict';

    // Initialize SDK when ready
    let sdk;
    if (typeof Rechat !== 'undefined' && Rechat.Sdk) {
        sdk = new Rechat.Sdk();
    }

    // Lead Capture Form Submission
    const leadCaptureForm = document.getElementById('leadCaptureForm');
    if (leadCaptureForm && typeof rchAgentData !== 'undefined') {
        leadCaptureForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const channel = {
                lead_channel: rchAgentData.leadChannel
            };

            const input = {
                first_name: document.getElementById('first_name').value,
                last_name: document.getElementById('last_name').value,
                phone_number: document.getElementById('phone_number').value,
                email: document.getElementById('email').value,
                note: document.getElementById('note').value,
                tag: rchAgentData.tags,
                source_type: 'Website',
                agent_emails: rchAgentData.agentEmail,
                referer_url: window.location.href
            };

            // Hide success, error alerts, and show loading spinner
            document.getElementById('rch-listing-success-sdk').style.display = 'none';
            document.getElementById('rch-listing-cancel-sdk').style.display = 'none';
            document.getElementById('loading-spinner').style.display = 'block';

            sdk.Leads.capture(channel, input)
                .then(() => {
                    document.getElementById('loading-spinner').style.display = 'none';
                    document.getElementById('rch-listing-success-sdk').style.display = 'block';
                })
                .catch((e) => {
                    document.getElementById('loading-spinner').style.display = 'none';
                    document.getElementById('rch-listing-cancel-sdk').style.display = 'block';
                    console.log('Error:', e);
                });
        });
    }

    // Agent Properties Listing
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof rchAgentData === 'undefined' || !rchAgentData.agentMatrixIds) {
            return;
        }

        // Initialize pagination variables
        let agentCurrentPage = 1; 
        let agentTotalPages = 1;
        let agentListingPerPage = 12; // Number of listings per page
        
        const agentMatrixIds = rchAgentData.agentMatrixIds;
        const propertiesList = document.getElementById('agent-properties-list');
        const loadingElement = document.getElementById('loading-properties');
        const paginationElement = document.getElementById('agent-pagination');
        const adminAjaxUrl = rchAgentData.ajaxUrl;
        const brandId = rchAgentData.brandId;
        
        if (!agentMatrixIds || agentMatrixIds.length === 0) {
            propertiesList.innerHTML = '<li class="no-properties">No properties found for this agent.</li>';
            loadingElement.style.display = 'none';
            paginationElement.style.display = 'none';
            return;
        }
        
        window.agentChangePage = function(direction) {
            const newPage = agentCurrentPage + direction;
            // Validate page is within bounds
            if (newPage >= 1 && newPage <= agentTotalPages) {
                agentCurrentPage = newPage;
                updateActivePage();
                updateAgentPropertiesList();
            }
        };
        
        window.agentGoToPage = function(page) {
            // Validate page is within bounds
            if (page >= 1 && page <= agentTotalPages) {
                agentCurrentPage = page;
                updateActivePage();
                updateAgentPropertiesList();
            }
        };
        
        // Function to update just the active page in the pagination without redrawing everything
        function updateActivePage() {
            const pageButtons = paginationElement.querySelectorAll('button');
            pageButtons.forEach(button => {
                if (button.textContent && !isNaN(parseInt(button.textContent))) {
                    if (parseInt(button.textContent) === agentCurrentPage) {
                        button.classList.add('active');
                    } else {
                        button.classList.remove('active');
                    }
                }
            });
        }
        
        window.updateAgentPagination = function() {
            const currentPaginationVisible = paginationElement.style.display === 'flex';
            paginationElement.innerHTML = '';
            
            const prevIconPath = rchAgentData.prevIconPath;
            const nextIconPath = rchAgentData.nextIconPath;
            
            if (agentTotalPages > 1) {
                // Show "Previous" button
                if (agentCurrentPage > 1) {
                    const prevButton = document.createElement('button');
                    const prevIcon = document.createElement('img');
                    prevIcon.src = prevIconPath;
                    prevIcon.alt = 'Previous';
                    prevIcon.className = 'pagination-icon';
                    prevButton.appendChild(prevIcon);
                    prevButton.onclick = () => window.agentChangePage(-1);
                    paginationElement.appendChild(prevButton);
                }
                
                // Generate page numbers dynamically
                let lastShownPage = 0; // Track the last page we showed to know when to add dots
                
                for (let page = 1; page <= agentTotalPages; page++) {
                    const shouldShow = 
                        page === 1 || // Always show first page
                        page === agentTotalPages || // Always show last page
                        Math.abs(page - agentCurrentPage) <= 2; // Show 2 pages before and after current
                    
                    if (shouldShow) {
                        // Add dots if there's a gap between this page and the last shown page
                        if (lastShownPage > 0 && page > lastShownPage + 1) {
                            const dots = document.createElement('span');
                            dots.textContent = '...';
                            paginationElement.appendChild(dots);
                        }
                        
                        const pageButton = document.createElement('button');
                        pageButton.textContent = page;
                        pageButton.className = page === agentCurrentPage ? 'active' : '';
                        pageButton.onclick = () => window.agentGoToPage(page);
                        paginationElement.appendChild(pageButton);
                        
                        lastShownPage = page;
                    }
                }
                
                // Show "Next" button
                if (agentCurrentPage < agentTotalPages) {
                    const nextButton = document.createElement('button');
                    const nextIcon = document.createElement('img');
                    nextIcon.src = nextIconPath;
                    nextIcon.alt = 'Next';
                    nextIcon.className = 'pagination-icon';
                    nextButton.appendChild(nextIcon);
                    nextButton.onclick = () => window.agentChangePage(1);
                    paginationElement.appendChild(nextButton);
                }
                
                if (!currentPaginationVisible) {
                    paginationElement.style.display = 'flex';
                }
            } else {
                paginationElement.style.display = 'none';
            }
        };
        
        // Function to fetch the total count for pagination
        function fetchAgentTotalCount() {
            paginationElement.innerHTML = '<div class="rch-pagination-loading">Loading pagination...</div>';
            paginationElement.style.display = 'flex';
            
            return fetch(adminAjaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'rch_fetch_total_listing_count',
                    brand: brandId,
                    agents: agentMatrixIds,
                    listing_statuses:'Active, Sold, Pending,Temp Off Market, Leased,Active Option Contract, Active Contingent, Active Kick Out, Incoming,Coming Soon,Active Under Contract'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const totalProperties = data.data.total;
                    agentTotalPages = Math.ceil(totalProperties / agentListingPerPage);
                    window.updateAgentPagination();
                } else {
                    paginationElement.innerHTML = '<p>Error loading pagination.</p>';
                    paginationElement.style.display = 'flex';
                }
            })
            .catch(error => {
                console.error('Error fetching total count:', error);
                paginationElement.innerHTML = '<p>Error loading pagination.</p>';
                paginationElement.style.display = 'flex';
            });
        }
        
        // Function to update agent properties list
        function updateAgentPropertiesList() {
            propertiesList.innerHTML = '';
            loadingElement.style.display = 'block';
            
            fetch(adminAjaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'rch_fetch_listing',
                    page: agentCurrentPage,
                    listing_per_page: agentListingPerPage,
                    brand: brandId,
                    agents: agentMatrixIds,
                    listing_statuses:'Active, Sold, Pending,Temp Off Market, Leased,Active Option Contract, Active Contingent, Active Kick Out, Incoming,Coming Soon,Active Under Contract'
                })
            })
            .then(response => response.json())
            .then(data => {
                loadingElement.style.display = 'none';
                
                if (data.success && data.data.listings && data.data.listings.length > 0) {
                    const listings = data.data.listings;
                    
                    listings.forEach(listing => {
                        propertiesList.innerHTML += listing.content;
                    });
                    
                    if (!paginationElement.hasChildNodes() || agentCurrentPage === 1) {
                        fetchAgentTotalCount();
                    }
                } else {
                    propertiesList.innerHTML = '<li class="no-properties">No properties found for this agent.</li>';
                    paginationElement.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching agent properties:', error);
                loadingElement.style.display = 'none';
                propertiesList.innerHTML = '<li class="no-properties">Error loading properties. Please try again later.</li>';
                paginationElement.style.display = 'none';
            });
        }
        
        // Initial load of agent properties
        updateAgentPropertiesList();
    });

})(jQuery);
