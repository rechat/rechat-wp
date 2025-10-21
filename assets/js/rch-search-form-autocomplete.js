/**
 * Google Places Autocomplete for Rechat Search Form
 * This script handles place search in the search form and passes coordinates as query params
 */

let searchFormAutocomplete;

/**
 * Initialize Google Places Autocomplete for search form
 */
function initSearchFormAutocomplete() {
    // Get reference to the search input field
    const searchInput = document.getElementById('content');

    // Only initialize if the element exists and we're on the search form page
    if (searchInput && document.getElementById('rch-search-form')) {
        searchFormAutocomplete = new google.maps.places.Autocomplete(searchInput, {
            types: ['(cities)'],
            fields: ['geometry', 'name', 'formatted_address', 'address_components'],
            componentRestrictions: { country: 'us' }
        });

        // Add listener for place selection
        searchFormAutocomplete.addListener('place_changed', () => {
            handleSearchFormPlaceSelection(searchFormAutocomplete);
        });

        // Modify the form submission to include lat/lng in the query
        const searchForm = document.getElementById('rch-search-form');
        if (searchForm) {
            searchForm.addEventListener('submit', handleSearchFormSubmit);
        }
    }
}

/**
 * Handle place selection from autocomplete in the search form
 * @param {Object} autocomplete - The autocomplete object that triggered the event
 */
function handleSearchFormPlaceSelection(autocomplete) {
    const place = autocomplete.getPlace();

    if (!place.geometry || !place.geometry.location) {
        console.error('No details available for place selection');
        return;
    }

    // Get the latitude and longitude
    const lat = place.geometry.location.lat();
    const lng = place.geometry.location.lng();

    // Store the place data in hidden inputs that will be submitted with the form
    addOrUpdateHiddenInput('place_lat', lat);
    addOrUpdateHiddenInput('place_lng', lng);

    // Use the formatted address if available, otherwise fall back to place name
    const displayName = place.formatted_address || place.name;
    addOrUpdateHiddenInput('place_name', displayName);

    // Update the input field with the formatted address (showing the full selection)
    document.getElementById('content').value = displayName;
}

/**
 * Create or update a hidden input field in the search form
 * @param {string} name - The name attribute for the input
 * @param {string|number} value - The value to set
 */
function addOrUpdateHiddenInput(name, value) {
    const form = document.getElementById('rch-search-form');
    let input = form.querySelector(`input[name="${name}"]`);

    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        form.appendChild(input);
    }

    input.value = value;
}

/**
 * Handle the search form submission
 * @param {Event} event - The form submission event
 */
function handleSearchFormSubmit(event) {
    const contentInput = document.getElementById('content');
    const placeLat = document.querySelector('input[name="place_lat"]');
    const placeLng = document.querySelector('input[name="place_lng"]');
    const placeName = document.querySelector('input[name="place_name"]');

    // If we have place coordinates, add them as separate hidden fields
    if (placeLat && placeLng && placeLat.value && placeLng.value) {
        // Create separate hidden fields for the coordinates
        const coordsInput = document.createElement('input');
        coordsInput.type = 'hidden';
        coordsInput.name = 'place_coords';
        coordsInput.value = `${placeLat.value},${placeLng.value}`;

        // Add to the form
        document.getElementById('rch-search-form').appendChild(coordsInput);

        // If we have a place_name, make sure content field has the same value
        if (placeName && placeName.value) {
            contentInput.value = placeName.value;
        }
    }
}

/**
 * Parse the URL parameters to extract coordinates
 * This runs on the listings page after search form submission
 */
function parseContentParam() {
    // Only run this on the listings page
    if (!document.querySelector('.rch-container-listing-list')) {
        return;
    }

    // Try to get coordinates from the URL query parameters
    const urlParams = new URLSearchParams(window.location.search);
    const placeCoords = urlParams.get('place_coords');

    if (placeCoords && placeCoords.includes(',')) {
        const [lat, lng] = placeCoords.split(',');

        // Convert to numbers
        const latitude = parseFloat(lat);
        const longitude = parseFloat(lng);

        // Get place name from content parameter
        const placeName = urlParams.get('content') || '';

        // Set the values in the map and hidden inputs
        updateListingsPageWithCoordinates(latitude, longitude, placeName);
    }
}

/**
 * Update the listings page with the coordinates from the search
 * @param {number} lat - Latitude
 * @param {number} lng - Longitude 
 * @param {string} placeName - Name of the place
 */
function updateListingsPageWithCoordinates(lat, lng, placeName) {
    // Set zoom level 12 for cities
    const zoom = 12;

    // Create hidden inputs for the filter form
    const filterForm = document.querySelector('.rch-filters, .rch-filters-mobile');
    if (filterForm) {
        // Add hidden inputs with the coordinates
        addOrUpdateHiddenInput('place_lat', lat, filterForm);
        addOrUpdateHiddenInput('place_lng', lng, filterForm);
        addOrUpdateHiddenInput('place_name', placeName, filterForm);
        addOrUpdateHiddenInput('place_zoom', zoom, filterForm);
    }

    // If the map exists, update it
    if (typeof map !== 'undefined') {
        // Update map center and zoom
        map.setCenter({ lat: lat, lng: lng });
        map.setZoom(zoom);

        // Call the API to calculate polygon
        calculatePolygonFromPlace(lat, lng, zoom);
    }
}

/**
 * Helper function to add or update hidden inputs in any form
 * @param {string} name - Input name
 * @param {string|number} value - Input value
 * @param {HTMLElement} container - The container to add the input to
 */
function addOrUpdateHiddenInput(name, value, container = document.getElementById('rch-search-form')) {
    if (!container) return;

    let input = container.querySelector(`input[name="${name}"]`);

    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        container.appendChild(input);
    }

    input.value = value;
}

// Add initialization to the window load event
document.addEventListener('DOMContentLoaded', function () {
    // Initialize the search form autocomplete
    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' &&
        typeof google.maps.places !== 'undefined') {
        initSearchFormAutocomplete();

        // If on the listings page, parse content parameter
        parseContentParam();
    } else {
        // If Google Maps API is loaded with callback=initMap, we need to wait for that
        const originalInitMap = window.initMap;

        window.initMap = function () {
            // Call the original initMap function first if it exists
            if (typeof originalInitMap === 'function') {
                originalInitMap();
            }

            // Then initialize Places Autocomplete
            initSearchFormAutocomplete();

            // If on the listings page, parse content parameter
            parseContentParam();
        };
    }
});
