/**
 * Formats the price to a shortened format (e.g., 1M, 340K).
 * @param {number} price - The price to format.
 * @returns {string} - The formatted price.
 */
function formatPrice(price) {
    if (price >= 1000000) {
        return (price / 1000000).toFixed(1) + 'M';
    } else if (price >= 1000) {
        return (price / 1000).toFixed(1) + 'K';
    }
    return price.toString();
}

/**
 * Processes and displays map markers for all listings data.
 * @param {Array} allListingsData - Array of listing objects containing lat, lng, image, price, and address.
 */
function processMapMarkers(allListingsData) {
    // Clear existing markers from the map
    clearMarkers();

    // Iterate through all listings data
    allListingsData.forEach(listing => {
        if (listing.lat && listing.lng) {
            addMapMarker(listing, map);
        }
    });
}

/**
 * Function for initialize the map
 */
function initMap() {
    
    // First initialize the map with a default view
    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 39.8283, lng: -98.5795 }, // Centered on the United States
        zoom: 4, // Adjusted zoom level for a better view of the US
        mapId: 'a1b2c3d4e5f6g7h8' // Add your Map ID here
    });
    
    // Initialize drawing tools and buttons (these don't trigger AJAX requests)
    initDrawingManager();
    addDrawAreaButton();
    
    // Now that the map exists, fetch listings data, but DON'T attach event listeners yet
    fetchListingsDataAndUpdateMap();
}

// New function to fetch data and update the map
function fetchListingsDataAndUpdateMap() {
    
    // Use the existing updateListingList function
    updateListingList()
        .then(allListingsData => {
            if (allListingsData && allListingsData.length > 0) {
                const bounds = createBoundsFromListings(allListingsData);
                
                // Update the existing map
                map.fitBounds(bounds);
                map.setCenter(bounds.getCenter());
                
                // Update filter points for the current view
                updateFilterPoints();
                
                // NOW attach the map event listeners AFTER initial data is loaded
                attachMapEventListeners();
            } else {
                // Still attach listeners even if no listings found
                attachMapEventListeners();
            }
        })
        .catch(error => {
            console.error('Error fetching listings data:', error);
            attachMapEventListeners(); // Ensure listeners are attached even on error
        });
}

function addDrawAreaButton() {
    const controlDiv = document.createElement('div');
    const controlUI = document.createElement('button');
    controlUI.style.backgroundColor = '#fff';
    controlUI.style.border = '2px solid #fff';
    controlUI.style.borderRadius = '3px';
    controlUI.style.boxShadow = '0 2px 6px rgba(0,0,0,.3)';
    controlUI.style.cursor = 'pointer';
    controlUI.style.margin = '10px';
    controlUI.style.textAlign = 'center';
    controlUI.title = 'Click to draw an area';
    controlUI.innerText = 'Draw Area';
    controlDiv.appendChild(controlUI);

    controlDiv.index = 1;
    map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(controlDiv);

    controlUI.addEventListener('click', function () {
        if (isDrawing) {
            toggleDrawing();
        } else if (drawnPolygon) {
            removePolygon();
            controlUI.innerText = 'Draw Area';
        } else {
            toggleDrawing();
            controlUI.innerText = 'Remove Polygon';
        }
    });
}
/**
 * Initializes the Drawing Manager for the map
 */
let drawingManager;
let isDrawing = false;
let drawnPolygon = null;

function initDrawingManager() {
    drawingManager = new google.maps.drawing.DrawingManager({
        drawingMode: null,
        drawingControl: false,
        polygonOptions: {
            editable: true,
            draggable: true
        }
    });

    drawingManager.setMap(map);

    google.maps.event.addListener(drawingManager, 'overlaycomplete', function (event) {
        if (event.type === google.maps.drawing.OverlayType.POLYGON) {
            if (drawnPolygon) {
                drawnPolygon.setMap(null); // Remove the previous polygon
            }
            drawnPolygon = event.overlay;
            const path = drawnPolygon.getPath();
            const points = [];

            for (let i = 0; i < path.getLength(); i++) {
                const point = path.getAt(i);
                points.push({ lat: point.lat(), lng: point.lng() });
            }

            // Add the first point again at the end to close the polygon
            if (points.length > 0) {
                points.push({ ...points[0] });
            }

            sendPointsToAPI(points);

            // Change button text to "Remove Polygon"
            document.querySelector('button[title="Click to draw an area"]').innerText = 'Remove Polygon';
        }
    });
}

function toggleDrawing() {
    if (isDrawing) {
        drawingManager.setDrawingMode(null);
        isDrawing = false;
    } else {
        drawingManager.setDrawingMode(google.maps.drawing.OverlayType.POLYGON);
        isDrawing = true;
    }
}

function removePolygon() {
    if (drawnPolygon) {
        drawnPolygon.setMap(null);
        drawnPolygon = null;
        // Optionally, clear the points sent to the API
        filters.points = '';
        document.getElementById('query-string').value = '';
        updateListingList();
    }
    isDrawing = false;
    document.querySelector('button[title="Click to draw an area"]').innerText = 'Draw Area';
}
/**
 * Sends the points of the drawn shape to the API
 * @param {Array} points - Array of points with lat/lng properties
 */
function sendPointsToAPI(points) {
    const queryString = points.map(point => `${point.lat},${point.lng}`).join('|');
    document.getElementById('query-string').value = queryString;
    filters.points = queryString;
    // Ensure the value is updated before calling updateListingList
    setTimeout(() => {
        updateListingList();
    }, 0);
}
/**
 * Creates a LatLngBounds object from an array of listings
 * @param {Array} listings - Array of listing objects with lat/lng properties
 * @returns {google.maps.LatLngBounds} - Bounds object containing all valid listing coordinates
 */
function createBoundsFromListings(listings) {
    const bounds = new google.maps.LatLngBounds();

    listings.forEach(listing => {
        if (listing.lat && listing.lng) {
            bounds.extend(new google.maps.LatLng(listing.lat, listing.lng));
        }
    });

    return bounds;
}

/**
 * Attaches all necessary event listeners to the map
 */
function attachMapEventListeners() {
    // Events that should trigger listings/filter updates
    const updateEvents = ['dragend', 'zoom_changed'];

    // Attach all update events
    updateEvents.forEach(eventName => {
        google.maps.event.addListener(map, eventName, function () {
            updateFilterPoints();
            updateListingList();
        });
    });
}
/**
 * Updates the filter points based on the current map bounds.
 */
function updateFilterPoints() {
    const bounds = map.getBounds();
    if (!bounds) return;

    const ne = bounds.getNorthEast();
    const sw = bounds.getSouthWest();

    const latLngs = [
        { lat: ne.lat(), lng: sw.lng() }, // North-West
        { lat: ne.lat(), lng: ne.lng() }, // North-East
        { lat: sw.lat(), lng: ne.lng() }, // South-East
        { lat: sw.lat(), lng: sw.lng() }, // South-West
        { lat: ne.lat(), lng: sw.lng() }  // Closing the polygon
    ];

    const queryString = latLngs
        .map(point => `${point.lat},${point.lng}`)
        .join('|');

    filters.points = queryString;
    document.getElementById('query-string').value = queryString;

    // Call the function to update listings (assumed to be defined elsewhere)
}
/**
 * Adds a marker and info window to the map for a given listing.
 * @param {Object} listing - Listing object containing lat, lng, image, price, and address.
 * @param {Object} map - Google Maps map instance.
 */
function addMapMarker(listing, map) {
    const position = {
        lat: listing.lat,
        lng: listing.lng
    };
    
    const formattedPrice = formatPrice(listing.price);
    let marker;
    
    // Check if Advanced Marker API is available
    if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
        // Create price pin element for advanced marker
        const pinElement = document.createElement('div');
        pinElement.className = 'price-pin';
        pinElement.style.backgroundColor = rchData.primaryColor;
        pinElement.style.color = '#fff';
        pinElement.style.padding = '4px 8px';
        pinElement.style.borderRadius = '6px';
        pinElement.style.fontSize = '9px';
        pinElement.style.fontWeight = 'bold';
        pinElement.style.border = '2px solid #fff';
        pinElement.style.boxShadow = '0 2px 4px rgba(0,0,0,0.3)';
        pinElement.style.textAlign = 'center';
        pinElement.innerText = formattedPrice;
        
        // Create advanced marker
        marker = new google.maps.marker.AdvancedMarkerElement({
            map,
            position,
            content: pinElement,
            title: listing.price.toLocaleString()
        });
    } else {
        // Fallback to traditional marker
        marker = new google.maps.Marker({
            map,
            position,
            label: {
                text: formattedPrice,
                color: '#fff',
                fontSize: '9px',
                fontWeight: 'bold'
            },
            title: listing.price.toLocaleString()
        });
    }
    
    // Info window content
    const infoWindowContent = `
        <div class="custom-info-window">
            <a href="${rchData.homeUrl}/listing-detail/?listing_id=${listing.id}" target="_blank">
                <img src="${listing.image || "https://fakeimg.pl/171x90"}" alt="Listing Image" class="info-window-img"/>
                <div class="info-window-content">
                    <p><strong>Price:</strong> ${listing.price.toLocaleString()}</p>
                    <p><strong>Location:</strong> ${listing.address}</p>
                </div>
            </a>
        </div>
    `;
    
    // Create info window
    const infoWindow = new google.maps.InfoWindow({
        content: infoWindowContent,
        disableAutoPan: true
    });
    
    let isClicked = false;
    
    // Add event listeners - compatible with both marker types
    marker.addListener('click', () => {
        isClicked = true;
        infoWindow.open({
            anchor: marker,
            map
        });
    });
    
    // Handle mouseover/mouseout for advanced markers
    if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
        marker.content.addEventListener('mouseover', () => {
            if (!isClicked) {
                infoWindow.open({
                    anchor: marker,
                    map
                });
            }
        });
        
        marker.content.addEventListener('mouseout', () => {
            if (!isClicked) {
                infoWindow.close();
            }
        });
    } else {
        // Handle mouseover/mouseout for traditional markers
        marker.addListener('mouseover', () => {
            if (!isClicked) {
                infoWindow.open({
                    anchor: marker,
                    map
                });
            }
        });
        
        marker.addListener('mouseout', () => {
            if (!isClicked) {
                infoWindow.close();
            }
        });
    }
    
    // Close info window when clicking elsewhere on the map
    google.maps.event.addListener(map, 'click', () => {
        isClicked = false;
        infoWindow.close();
    });
    
    // Add marker to the array for future management
    mapMarkers.push(marker);
}

/**
 * Clears all markers from the map.
 */
function clearMarkers() {
    mapMarkers.forEach(marker => {
        if (marker.map) {
            if (google.maps.marker && google.maps.marker.AdvancedMarkerElement && 
                marker instanceof google.maps.marker.AdvancedMarkerElement) {
                marker.map = null;
            } else {
                marker.setMap(null);
            }
        }
    });
    mapMarkers.length = 0;
}