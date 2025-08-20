let currentPage = 1; // Initialize current page
let mapMarkers = [];
let map;
let isMapInitialized = false; // Flag to check if the map has already been initialized
let listingPerPage = rchListingData.listingPerPage; // Set number of listings per page
let totalListing = rchListingData.totalListing; // Set total number of listings
let totalPages = Math.ceil(totalListing / listingPerPage); // Calculate total pages
// Extract filters passed through the shortcode attributes
let defaultFilters = {
    brand: rchListingData.filters.brand,
    minimum_price: rchListingData.filters.minimum_price,
    maximum_price: rchListingData.filters.maximum_price,
    minimum_lot_square_meters: rchListingData.filters.minimum_lot_square_meters,
    maximum_lot_square_meters: rchListingData.filters.maximum_lot_square_meters,
    minimum_bathrooms: rchListingData.filters.minimum_bathrooms,
    maximum_bathrooms: rchListingData.filters.maximum_bathrooms,
    minimum_square_meters: rchListingData.filters.minimum_square_meters,
    maximum_square_meters: rchListingData.filters.maximum_square_meters,
    minimum_year_built: rchListingData.filters.minimum_year_built,
    maximum_year_built: rchListingData.filters.maximum_year_built,
    minimum_bedrooms: rchListingData.filters.minimum_bedrooms,
    maximum_bedrooms: rchListingData.filters.maximum_bedrooms,
    listing_statuses: rchListingData.filters.listing_statuses,
    property_types: rchListingData.propertyTypes, // Convert PHP array to JavaScript array
};

let filters = {
    ...defaultFilters
};

// Add points to filters only if we have valid coordinates
if (rchListingData.mapCoordinates && rchListingData.mapCoordinates.hasValidCoordinates) {
    const queryString = document.getElementById('query-string');
    if (queryString && queryString.value) {
        filters.points = queryString.value;
    }
}

function fetchListingsData() {
    const loading = document.getElementById('rch-loading-spinner');
    loading.style.display = 'grid'; // Show loading spinner

    return fetch(rchListingData.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'rch_fetch_listing',
            page: currentPage,
            listing_per_page: listingPerPage,
            ...filters,
        }),
    })
        .then(response => response.json())
        .then(data => {
            loading.style.display = 'none'; // Hide loading spinner
            if (data.data.allListingsData && Array.isArray(data.data.allListingsData)) {
                return data.data.allListingsData;
            } else {
                return [];
            }
        })
        .catch(error => {
            loading.style.display = 'none'; // Hide loading spinner on error
            console.error('Error fetching listings data:', error);
            return [];
        });
}

let abortController = new AbortController(); // Global abort controller

function updateListingList() {
    const listingList = document.getElementById('listing-list');
    const loading = document.getElementById('rch-loading-listing');
    const pagination = document.getElementById('pagination');

    listingList.style.display = 'none';
    listingList.innerHTML = ''; // Clear the listing content
    loading.style.display = 'grid'; // Show loading spinner
    pagination.style.display = 'none'; // Hide pagination while loading

    // Abort the previous request if it's still pending
    abortController.abort();
    abortController = new AbortController(); // Create a new controller for the new request

    // Fetch the latest query-string value dynamically
    let queryString = `?action=rch_fetch_listing&page=${currentPage}&listing_per_page=${listingPerPage}`;

    // Add points (query-string) value to the queryString
    const pointsValue = document.getElementById('query-string').value; // Get the updated query-string
    if (pointsValue) {
        queryString += `&points=${encodeURIComponent(pointsValue)}`;
    }
    // Apply other filters
    Object.keys(filters).forEach(key => {
        const filterValue = filters[key];
        if (filterValue || filterValue === 0) {
            if (Array.isArray(filterValue) && filterValue.length > 0) {
                filterValue.forEach(value => {
                    queryString += `&${key}[]=${encodeURIComponent(value)}`;
                });
            } else {
                queryString += `&${key}=${encodeURIComponent(filterValue)}`;
            }
        }
    });

    return fetch(rchListingData.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'rch_fetch_listing',
            page: currentPage,
            listing_per_page: listingPerPage,
            ...filters,
        }),
        signal: abortController.signal, // Attach the signal to the request
    })
        .then(response => response.json())
        .then(data => {
            loading.style.display = 'none'; // Hide loading spinner
            listingList.style.display = 'grid'; // Show the listing container
            pagination.style.display = 'flex'; // Show pagination

            if (data.data.listings && Array.isArray(data.data.listings) && data.data.listings.length > 0) {
                const listings = data.data.listings;
                const allListingsData = data.data.allListingsData;
                listingPerPage = data.data.listingPerPage;

                listingList.innerHTML = ''; // Clear previous content
                clearMarkers(); // Remove existing markers before adding new ones

                listings.forEach(listing => {
                    listingList.innerHTML += listing.content; // Append new listings
                });

                processMapMarkers(allListingsData);
                
                // Show loading message in pagination area
                pagination.innerHTML = '<div class="rch-pagination-loading">Loading pagination...</div>';
                pagination.style.display = 'flex';
                
                // Fetch total count in a separate request
                fetchTotalListingCount();

                return allListingsData;
            } else {
                clearMarkers();
                listingList.innerHTML = `<p class="rch-no-listing">${data.data.message || 'No listings available.'}</p>`;
                pagination.style.display = 'none'; // Hide pagination
                return [];
            }
        })
        .catch(error => {
            if (error.name === 'AbortError') {
                console.log('Fetch aborted due to a new request'); // Request was aborted intentionally
                return;
            }
            loading.style.display = 'none'; // Hide loading spinner on error
            console.error('Error fetching listing:', error);
            listingList.innerHTML = '<p>Error loading listing.</p>';
            return [];
        });
}

// Function to fetch the total listing count separately
function fetchTotalListingCount() {
    const pagination = document.getElementById('pagination');
    
    return fetch(rchListingData.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'rch_fetch_total_listing_count',
            ...filters,
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            totalListing = data.data.total;
            totalPages = Math.ceil(totalListing / listingPerPage);
            updatePagination(); // Now update the pagination with the actual count
        } else {
            pagination.innerHTML = '<p>Error loading pagination.</p>';
        }
    })
    .catch(error => {
        console.error('Error fetching total count:', error);
        pagination.innerHTML = '<p>Error loading pagination.</p>';
    });
}
