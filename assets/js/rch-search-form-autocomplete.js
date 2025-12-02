/**
 * Google Places Autocomplete for Rechat Search Form
 * This script handles place search in the search form and passes coordinates as query params
 */

let searchFormAutocomplete;
let searchFormOriginalInput = ''; // Store the original input before autocomplete modifies it

/**
 * Initialize Google Places Autocomplete for search form
 */
function initSearchFormAutocomplete() {
    // Get reference to the search input field
    const searchInput = document.getElementById('content');

    // Only initialize if the element exists and we're on the search form page
    if (searchInput && document.getElementById('rch-search-form')) {
        // Capture the original input before autocomplete modifies it
        searchInput.addEventListener('input', (e) => {
            searchFormOriginalInput = e.target.value;
        });
        
        searchFormAutocomplete = new google.maps.places.Autocomplete(searchInput, {
            types: ['geocode'],
            fields: ['geometry', 'name', 'formatted_address', 'address_components', 'types'],
            componentRestrictions: { country: 'us' }
        });

        // Filter out neighborhoods - only allow cities, states, and postal codes, streets, and addresses
        searchFormAutocomplete.setTypes(['locality', 'administrative_area_level_1', 'postal_code', 'street_address', 'route']);

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

    // Extract unit number from original input if present
    const unitMatch = searchFormOriginalInput.match(/(?:unit|apt|apartment|suite|#)\s*([a-z0-9-]+)/i);
    const hasUnit = unitMatch !== null;
    
    // Check if this is a specific address (street_address or premise)
    const isSpecificAddress = place.types && (
        place.types.includes('street_address') || 
        place.types.includes('premise') ||
        place.types.includes('subpremise')
    );

    // Check if the place has viewport or bounds
    let polygonString = '';
    
    // If it's a specific address or has a unit, create a small polygon
    if (isSpecificAddress || hasUnit) {
        // Create a very small polygon around the exact point (approximately 20 meters)
        const offset = 0.0002;
        const smallPolygon = [
            [lat + offset, lng - offset], // Northwest
            [lat + offset, lng + offset], // Northeast
            [lat - offset, lng + offset], // Southeast
            [lat - offset, lng - offset], // Southwest
            [lat + offset, lng - offset]  // Close polygon
        ];
        
        polygonString = smallPolygon.map(point => `${point[0]},${point[1]}`).join('|');
    } else if (place.geometry.viewport) {
        const viewport = place.geometry.viewport;
        const ne = viewport.getNorthEast();
        const sw = viewport.getSouthWest();
        
        // Create polygon from viewport (5 points to close the polygon)
        polygonString = `${ne.lat()},${sw.lng()}|${ne.lat()},${ne.lng()}|${sw.lat()},${ne.lng()}|${sw.lat()},${sw.lng()}|${ne.lat()},${sw.lng()}`;
    } else if (place.geometry.bounds) {
        const bounds = place.geometry.bounds;
        const ne = bounds.getNorthEast();
        const sw = bounds.getSouthWest();
        
        // Create polygon from bounds (5 points to close the polygon)
        polygonString = `${ne.lat()},${sw.lng()}|${ne.lat()},${ne.lng()}|${sw.lat()},${ne.lng()}|${sw.lat()},${sw.lng()}|${ne.lat()},${sw.lng()}`;
    }

    // Store the place data in hidden inputs that will be submitted with the form
    addOrUpdateHiddenInput('place_lat', lat);
    addOrUpdateHiddenInput('place_lng', lng);
    
    // Store the polygon string if we have it
    if (polygonString) {
        addOrUpdateHiddenInput('place_polygon', polygonString);
    }

    // Use the formatted address if available, otherwise fall back to place name
    const displayName = place.formatted_address || place.name;
    addOrUpdateHiddenInput('place_name', displayName);
    
    // If this is a specific address with a unit, store the original input as the address parameter
    // Always check the original input to preserve user-entered unit numbers that Google might strip
    if (hasUnit && isSpecificAddress) {
        addOrUpdateHiddenInput('place_address', searchFormOriginalInput);
    } else {
        // Remove address parameter if no unit
        const form = document.getElementById('rch-search-form');
        const addressInput = form.querySelector('input[name="place_address"]');
        if (addressInput) {
            addressInput.remove();
        }
    }

    // Update the input field with the original input if it has a unit, otherwise use formatted address
    // This preserves the unit number the user typed
    if (hasUnit && searchFormOriginalInput) {
        document.getElementById('content').value = searchFormOriginalInput;
    } else {
        document.getElementById('content').value = displayName;
    }
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
    const placePolygon = document.querySelector('input[name="place_polygon"]');
    const placeAddress = document.querySelector('input[name="place_address"]');

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
        
        // If we have a polygon string, add it to the form
        if (placePolygon && placePolygon.value) {
            const polygonInput = document.createElement('input');
            polygonInput.type = 'hidden';
            polygonInput.name = 'place_polygon_string';
            polygonInput.value = placePolygon.value;
            document.getElementById('rch-search-form').appendChild(polygonInput);
        }
        
        // If we have an address (with unit number), add it to the form
        if (placeAddress && placeAddress.value) {
            const addressInput = document.createElement('input');
            addressInput.type = 'hidden';
            addressInput.name = 'address';
            addressInput.value = placeAddress.value;
            document.getElementById('rch-search-form').appendChild(addressInput);
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
