/**
 * Google Places Autocomplete for Rechat Plugin
 * This script handles place search and map updates.
 */

let mobileAutocomplete;
let desktopAutocomplete;
let originalSearchInput = ''; // Store the original input before autocomplete modifies it

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
        // Capture the original input before autocomplete modifies it
        mobileSearchInput.addEventListener('input', (e) => {
            originalSearchInput = e.target.value;
            
            // Clear address filter when user modifies the input
            if (typeof filters !== 'undefined' && filters.address) {
                delete filters.address;
                console.log('Cleared address filter on input change');
            }
            
            // If input is cleared, reset to default map viewport
            if (e.target.value.trim() === '') {
                console.log('Input cleared - resetting to default map viewport');
                
                // Reset the preset polygon flag
                if (typeof hasPresetPolygonFromSearch !== 'undefined') {
                    hasPresetPolygonFromSearch = false;
                }
                
                // Clear the query-string input
                const queryStringInput = document.getElementById('query-string');
                if (queryStringInput) {
                    queryStringInput.value = '';
                }
                
                // Remove points from filters to go back to default
                if (typeof filters !== 'undefined') {
                    delete filters.points;
                }
                
                // Get default coordinates from shortcode or use US center
                let defaultLat = 39.8283;
                let defaultLng = -98.5795;
                let defaultZoom = 4;
                let hasShortcodeCoords = false;
                
                if (typeof rchListingData !== 'undefined' && rchListingData.mapCoordinates) {
                    const coords = rchListingData.mapCoordinates;
                    if (coords.latitude && coords.longitude) {
                        defaultLat = parseFloat(coords.latitude);
                        defaultLng = parseFloat(coords.longitude);
                        hasShortcodeCoords = true;
                    }
                    if (coords.zoom) {
                        defaultZoom = parseInt(coords.zoom);
                    }
                }
                
                console.log('Resetting to:', { lat: defaultLat, lng: defaultLng, zoom: defaultZoom, fromShortcode: hasShortcodeCoords });
                
                // Update listings and fit map to show them
                if (typeof updateListingList === 'function') {
                    updateListingList().then(allListingsData => {
                        if (allListingsData && allListingsData.length > 0 && typeof map !== 'undefined') {
                            // If we have shortcode coordinates, use them; otherwise fit to listings
                            if (hasShortcodeCoords) {
                                map.setCenter({ lat: defaultLat, lng: defaultLng });
                                map.setZoom(defaultZoom);
                            } else {
                                // Fit map bounds to show all listings
                                if (typeof createBoundsFromListings === 'function') {
                                    const bounds = createBoundsFromListings(allListingsData);
                                    map.fitBounds(bounds);
                                    map.setCenter(bounds.getCenter());
                                }
                            }
                        } else if (typeof map !== 'undefined') {
                            // No listings, use default or shortcode center
                            map.setCenter({ lat: defaultLat, lng: defaultLng });
                            map.setZoom(defaultZoom);
                        }
                    }).catch(error => {
                        console.error('Error updating listings:', error);
                    });
                }
            }
        });
        
        mobileAutocomplete = new google.maps.places.Autocomplete(mobileSearchInput, {
             types: ['geocode'],
            fields: ['geometry', 'name', 'formatted_address', 'address_components', 'types'],
            componentRestrictions: { country: 'us' }
        });

        // Filter out neighborhoods - only allow cities, states, and postal codes
        mobileAutocomplete.setTypes(['locality', 'administrative_area_level_1', 'postal_code', 'street_address', 'route']);

        // Add listener for place selection
        mobileAutocomplete.addListener('place_changed', () => {
            handlePlaceSelection(mobileAutocomplete, originalSearchInput);
        });
    }

    if (desktopSearchInput) {
        // Capture the original input before autocomplete modifies it
        desktopSearchInput.addEventListener('input', (e) => {
            originalSearchInput = e.target.value;
            
            // Clear address filter when user modifies the input
            if (typeof filters !== 'undefined' && filters.address) {
                delete filters.address;
                console.log('Cleared address filter on input change');
            }
            
            // If input is cleared, reset to default map viewport
            if (e.target.value.trim() === '') {
                console.log('Input cleared - resetting to default map viewport');
                
                // Reset the preset polygon flag
                if (typeof hasPresetPolygonFromSearch !== 'undefined') {
                    hasPresetPolygonFromSearch = false;
                }
                
                // Clear the query-string input
                const queryStringInput = document.getElementById('query-string');
                if (queryStringInput) {
                    queryStringInput.value = '';
                }
                
                // Remove points from filters to go back to default
                if (typeof filters !== 'undefined') {
                    delete filters.points;
                }
                
                // Get default coordinates from shortcode or use US center
                let defaultLat = 39.8283;
                let defaultLng = -98.5795;
                let defaultZoom = 4;
                let hasShortcodeCoords = false;
                
                if (typeof rchListingData !== 'undefined' && rchListingData.mapCoordinates) {
                    const coords = rchListingData.mapCoordinates;
                    if (coords.latitude && coords.longitude) {
                        defaultLat = parseFloat(coords.latitude);
                        defaultLng = parseFloat(coords.longitude);
                        hasShortcodeCoords = true;
                    }
                    if (coords.zoom) {
                        defaultZoom = parseInt(coords.zoom);
                    }
                }
                
                console.log('Resetting to:', { lat: defaultLat, lng: defaultLng, zoom: defaultZoom, fromShortcode: hasShortcodeCoords });
                
                // Update listings and fit map to show them
                if (typeof updateListingList === 'function') {
                    updateListingList().then(allListingsData => {
                        if (allListingsData && allListingsData.length > 0 && typeof map !== 'undefined') {
                            // If we have shortcode coordinates, use them; otherwise fit to listings
                            if (hasShortcodeCoords) {
                                map.setCenter({ lat: defaultLat, lng: defaultLng });
                                map.setZoom(defaultZoom);
                            } else {
                                // Fit map bounds to show all listings
                                if (typeof createBoundsFromListings === 'function') {
                                    const bounds = createBoundsFromListings(allListingsData);
                                    map.fitBounds(bounds);
                                    map.setCenter(bounds.getCenter());
                                }
                            }
                        } else if (typeof map !== 'undefined') {
                            // No listings, use default or shortcode center
                            map.setCenter({ lat: defaultLat, lng: defaultLng });
                            map.setZoom(defaultZoom);
                        }
                    }).catch(error => {
                        console.error('Error updating listings:', error);
                    });
                }
            }
        });
        
        desktopAutocomplete = new google.maps.places.Autocomplete(desktopSearchInput, {
             types: ['geocode'],
            fields: ['geometry', 'name', 'formatted_address', 'address_components', 'types'],
            componentRestrictions: { country: 'us' }
        });

        // Filter out neighborhoods - only allow cities, states, and postal codes
        desktopAutocomplete.setTypes(['locality', 'administrative_area_level_1', 'postal_code', 'street_address', 'route']);

        // Add listener for place selection
        desktopAutocomplete.addListener('place_changed', () => {
            handlePlaceSelection(desktopAutocomplete, originalSearchInput);
        });
    }
}

/**
 * Handle place selection from autocomplete
 * @param {Object} autocomplete - The autocomplete object that triggered the event
 * @param {string} originalInput - The original input text before autocomplete modified it
 */
function handlePlaceSelection(autocomplete, originalInput = '') {
    const place = autocomplete.getPlace();
    if (!place.geometry || !place.geometry.location) {
        console.error('No details available for place selection');
        return;
    }

    // Get the latitude and longitude
    const lat = place.geometry.location.lat();
    const lng = place.geometry.location.lng();
    
    // Extract unit number from original input if present
    const unitMatch = originalInput.match(/(?:unit|apt|apartment|suite|#)\s*([a-z0-9-]+)/i);
    const hasUnit = unitMatch !== null;
    
    // Check if this is a specific address (street_address or premise)
    const isSpecificAddress = place.types && (
        place.types.includes('street_address') || 
        place.types.includes('premise') ||
        place.types.includes('subpremise')
    );
    
    console.log('Place selected:', place);
    console.log('Original input:', originalInput);
    console.log('Has unit:', hasUnit, 'Unit:', unitMatch ? unitMatch[1] : 'N/A');
    console.log('Is specific address:', isSpecificAddress, 'Types:', place.types);
    
    // If user specified a unit or it's a specific address, use very small search area
    if (isSpecificAddress || hasUnit) {
        // For specific addresses or units, create a very small polygon around the exact point
        // This creates a box of approximately 20 meters on each side
        const offset = 0.0002; // About 20-22 meters in degrees
        
        const smallPolygon = [
            [lat + offset, lng - offset], // Northwest
            [lat + offset, lng + offset], // Northeast
            [lat - offset, lng + offset], // Southeast
            [lat - offset, lng - offset], // Southwest
            [lat + offset, lng - offset]  // Close polygon
        ];
        
        const polygonString = smallPolygon.map(point => `${point[0]},${point[1]}`).join('|');
        
        console.log('Created small polygon for specific address/unit:', polygonString);
        
        // Update the hidden input with the small polygon
        document.getElementById('query-string').value = polygonString;
        
        // Update filters to use the small polygon
        if (typeof filters !== 'undefined') {
            filters.points = polygonString;
            
            // Store the unit info in the address filter if present, otherwise clear it
            if (hasUnit) {
                filters.address = originalInput;
                console.log('Setting address filter to:', originalInput);
            } else {
                // Clear address filter if no unit specified
                delete filters.address;
            }
        }
        
        // Center map on the exact location with high zoom
        map.setCenter({ lat: lat, lng: lng });
        map.setZoom(19); // Very close zoom for specific address/unit
        
        // Fetch listings with the small polygon
        updateListingList();
        return;
    }
    
    // For non-specific addresses (streets, neighborhoods, cities), use polygon search
    // Clear any address filter since we're searching an area, not a specific unit
    if (typeof filters !== 'undefined') {
        delete filters.address;
    }
    
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