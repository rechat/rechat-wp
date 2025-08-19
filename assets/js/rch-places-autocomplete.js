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
            types: ['(cities)'],
            fields: ['geometry', 'name', 'formatted_address']
        });
        
        // Add listener for place selection
        mobileAutocomplete.addListener('place_changed', () => {
            handlePlaceSelection(mobileAutocomplete);
        });
    }
    
    if (desktopSearchInput) {
        desktopAutocomplete = new google.maps.places.Autocomplete(desktopSearchInput, {
            types: ['(cities)'],
            fields: ['geometry', 'name', 'formatted_address']
        });
        
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
    
    // Use a closer zoom level for cities/places (zoom level 12 is good for cities)
    const zoom = 10;
    
    // Update the map center and zoom immediately
    map.setCenter(place.geometry.location);
    map.setZoom(zoom);
    
    // Call PHP functions through AJAX to calculate bounding box and polygon
    calculatePolygonFromPlace(lat, lng, zoom);
}

/**
 * Calculate polygon from place coordinates
 * @param {number} lat - The latitude of the selected place
 * @param {number} lng - The longitude of the selected place
 * @param {number} zoom - The current zoom level
 */
function calculatePolygonFromPlace(lat, lng, zoom) {
    jQuery.ajax({
        url: rchListingData.ajaxUrl,
        type: 'POST',
        data: {
            action: 'rch_calculate_polygon_from_place',
            lat: lat,
            lng: lng,
            zoom: zoom
        },
        success: function(response) {
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
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
        }
    });
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
    google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
        // If the new zoom is too far out, enforce a closer zoom
        if (map.getZoom() < 12) {
            map.setZoom(12);
        }
    });
}

// Add initialization to the window load event
document.addEventListener('DOMContentLoaded', function() {
    // Check if Google Maps API is already loaded
    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' && 
        typeof google.maps.places !== 'undefined') {
        initPlacesAutocomplete();
    } else {
        // If Google Maps API is loaded with callback=initMap, we need to wait for that
        // Original initMap function from rch-rechat-listings-map.js will be called first
        const originalInitMap = window.initMap;
        
        window.initMap = function() {
            // Call the original initMap function first
            if (typeof originalInitMap === 'function') {
                originalInitMap();
            }
            
            // Then initialize Places Autocomplete
            initPlacesAutocomplete();
        };
    }
});
