/**
 * Google Places Autocomplete for Rechat Plugin
 * This script handles place search and map updates.
 */

let mobileAutocomplete;
let desktopAutocomplete;

/**
 * Initialize Google Places Autocomplete
 * This function gets called after Google Maps API is loaded
 */
function initPlacesAutocomplete() {
    // Get references to both search input fields
    const mobileSearchInput = document.getElementById('content');
    const desktopSearchInput = document.querySelector('.rch-filters .rch-text-filter#content');

    // Only initialize if the elements exist
    if (mobileSearchInput) {
        mobileAutocomplete = new google.maps.places.Autocomplete(mobileSearchInput, {
             types: ['geocode'],
            fields: ['geometry', 'name', 'formatted_address', 'address_components', 'types'],
            componentRestrictions: { country: 'us' }
        });

        // Filter out neighborhoods - only allow cities, states, and postal codes
        mobileAutocomplete.setTypes(['locality', 'administrative_area_level_1', 'postal_code', 'street_address', 'route']);

        // Add listener for place selection
        mobileAutocomplete.addListener('place_changed', () => {
            handlePlaceSelection(mobileAutocomplete);
        });
    }

    if (desktopSearchInput) {
        desktopAutocomplete = new google.maps.places.Autocomplete(desktopSearchInput, {
             types: ['geocode'],
            fields: ['geometry', 'name', 'formatted_address', 'address_components', 'types'],
            componentRestrictions: { country: 'us' }
        });

        // Filter out neighborhoods - only allow cities, states, and postal codes
        desktopAutocomplete.setTypes(['locality', 'administrative_area_level_1', 'postal_code', 'street_address', 'route']);

        // Add listener for place selection
        desktopAutocomplete.addListener('place_changed', () => {
            handlePlaceSelection(desktopAutocomplete);
        });
    }
}

/**
 * Handle place selection from autocomplete
 * @param {Object} autocomplete - The autocomplete object that triggered the event
 */
function handlePlaceSelection(autocomplete) {
    const place = autocomplete.getPlace();
    if (!place.geometry || !place.geometry.location) {
        console.error('No details available for place selection');
        return;
    }

    // Get the latitude and longitude
    const lat = place.geometry.location.lat();
    const lng = place.geometry.location.lng();
    // Check if the place has viewport (bounding box)
    let boundingBox = null;
    if (place.geometry.viewport) {
        const viewport = place.geometry.viewport;
        boundingBox = {
            northeast: [viewport.getNorthEast().lat(), viewport.getNorthEast().lng()],
            southeast: [viewport.getSouthWest().lat(), viewport.getNorthEast().lng()],
            southwest: [viewport.getSouthWest().lat(), viewport.getSouthWest().lng()],
            northwest: [viewport.getNorthEast().lat(), viewport.getSouthWest().lng()]
        };
    }

    // Use bounds if available, otherwise use viewport or default zoom
    let zoom = 12;
    if (place.geometry.bounds) {
        // Has precise bounds - use them
        map.fitBounds(place.geometry.bounds);
        zoom = map.getZoom();
    } else if (place.geometry.viewport) {
        // Has viewport - use it
        map.fitBounds(place.geometry.viewport);
        zoom = map.getZoom();
    } else {
        // No bounds - just center and zoom
        map.setCenter(place.geometry.location);
        map.setZoom(zoom);
    }

    // Calculate polygon from the place
    calculatePolygonFromPlace(lat, lng, zoom, boundingBox, place);
}

/**
 * Calculate polygon from place coordinates
 * @param {number} lat - The latitude of the selected place
 * @param {number} lng - The longitude of the selected place
 * @param {number} zoom - The current zoom level
 * @param {Object|null} boundingBox - Pre-calculated bounding box from place viewport
 * @param {Object} place - The full place object from Google Places API
 */
function calculatePolygonFromPlace(lat, lng, zoom, boundingBox = null, place = null) {
    // If we already have a bounding box from the place viewport, use it directly
    if (boundingBox) {
        const polygonString = formatPolygonString(boundingBox);
        
        // Update the hidden input with the new polygon string
        document.getElementById('query-string').value = polygonString;

        // Update filters for API requests
        if (typeof filters !== 'undefined') {
            filters.points = polygonString;
        }

        // Fetch new listings with the updated polygon
        updateListingList();
        return;
    }

    // Otherwise, calculate it via AJAX
    const ajaxData = {
        action: 'rch_calculate_polygon_from_place',
        lat: lat,
        lng: lng,
        zoom: zoom
    };

    // If we have place geometry bounds or viewport, send them
    if (place) {
        if (place.geometry.bounds) {
            const bounds = place.geometry.bounds;
            ajaxData.bounds = {
                northeast: [bounds.getNorthEast().lat(), bounds.getNorthEast().lng()],
                southwest: [bounds.getSouthWest().lat(), bounds.getSouthWest().lng()]
            };
        } else if (place.geometry.viewport) {
            const viewport = place.geometry.viewport;
            ajaxData.bounds = {
                northeast: [viewport.getNorthEast().lat(), viewport.getNorthEast().lng()],
                southwest: [viewport.getSouthWest().lat(), viewport.getSouthWest().lng()]
            };
        }
    }

    jQuery.ajax({
        url: rchListingData.ajaxUrl,
        type: 'POST',
        data: ajaxData,
        success: function (response) {
            if (response.success) {
                // Update the hidden input with the new polygon string
                document.getElementById('query-string').value = response.data.polygonString;

                // Update the map viewport using the bounding box
                updateMapFromBoundingBox(response.data.boundingBox);

                // Update filters for API requests
                if (typeof filters !== 'undefined') {
                    filters.points = response.data.polygonString;
                }

                // Fetch new listings with the updated polygon
                updateListingList();
            } else {
                console.error('Error calculating polygon:', response.data.message);
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX error:', error);
        }
    });
}

/**
 * Format bounding box into polygon string for API
 * @param {Object} boundingBox - The bounding box with northeast, southeast, southwest, northwest coordinates
 * @returns {string} - Formatted polygon string
 */
function formatPolygonString(boundingBox) {
    const points = [
        boundingBox.northeast,
        boundingBox.southeast,
        boundingBox.southwest,
        boundingBox.northwest,
        boundingBox.northeast // Close the polygon
    ];
    
    return points.map(point => `${point[0]},${point[1]}`).join('|');
}

/**
 * Update the map viewport based on a bounding box
 * @param {Object} boundingBox - The bounding box object with northeast, southeast, southwest, and northwest coordinates
 */
function updateMapFromBoundingBox(boundingBox) {
    const bounds = new google.maps.LatLngBounds(
        new google.maps.LatLng(boundingBox.southwest[0], boundingBox.southwest[1]),
        new google.maps.LatLng(boundingBox.northeast[0], boundingBox.northeast[1])
    );

    // Store the current zoom level before fitting bounds
    const currentZoom = map.getZoom();

    // Fit bounds to update the viewport
    map.fitBounds(bounds);

    // After fitting bounds, set a minimum zoom level to ensure we're close enough
    google.maps.event.addListenerOnce(map, 'bounds_changed', function () {
        // If the new zoom is too far out, enforce a closer zoom
        if (map.getZoom() < 12) {
            map.setZoom(12);
        }
    });
}

// Add initialization to the window load event
document.addEventListener('DOMContentLoaded', function () {
    // Check if Google Maps API is already loaded
    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' &&
        typeof google.maps.places !== 'undefined') {
        initPlacesAutocomplete();
    } else {
        // If Google Maps API is loaded with callback=initMap, we need to wait for that
        // Original initMap function from rch-rechat-listings-map.js will be called first
        const originalInitMap = window.initMap;

        window.initMap = function () {
            // Call the original initMap function first
            if (typeof originalInitMap === 'function') {
                originalInitMap();
            }

            // Then initialize Places Autocomplete
            initPlacesAutocomplete();
        };
    }
});