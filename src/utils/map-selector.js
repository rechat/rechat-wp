import { useEffect, useRef } from '@wordpress/element';

const MapSelector = ({ apiKey, latitude, longitude, zoom, onLocationChange, onZoomChange }) => {
    const mapRef = useRef(null);
    const markerRef = useRef(null);
    const mapInstanceRef = useRef(null);
    const searchBoxRef = useRef(null);

    // Initialize map when component mounts
    useEffect(() => {
        if (!apiKey || !window.google || !window.google.maps) {
            // Load Google Maps API
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places,drawing`;
            script.async = true;
            script.onload = initMap;
            document.head.appendChild(script);
            
            return () => {
                // Clean up script when component unmounts
                document.head.removeChild(script);
            };
        } else {
            // Google Maps API already loaded
            initMap();
        }
    }, [apiKey]);

    // Re-center map when lat/lng changes from external source
    useEffect(() => {
        if (mapInstanceRef.current && markerRef.current && latitude && longitude) {
            const position = new window.google.maps.LatLng(parseFloat(latitude), parseFloat(longitude));
            mapInstanceRef.current.setCenter(position);
            markerRef.current.setPosition(position);
        }
    }, [latitude, longitude]);

    // Update zoom when it changes from external source
    useEffect(() => {
        if (mapInstanceRef.current && zoom) {
            mapInstanceRef.current.setZoom(parseInt(zoom));
        }
    }, [zoom]);

    const initMap = () => {
        if (!window.google || !window.google.maps) return;
        
        // Default position if no coordinates provided
        const defaultLat = latitude ? parseFloat(latitude) : 37.7749;
        const defaultLng = longitude ? parseFloat(longitude) : -122.4194;
        const defaultZoom = zoom ? parseInt(zoom) : 12;
        
        const mapOptions = {
            center: { lat: defaultLat, lng: defaultLng },
            zoom: defaultZoom,
            mapTypeId: window.google.maps.MapTypeId.ROADMAP,
            zoomControl: true,
            mapTypeControl: true,
            scaleControl: true,
            streetViewControl: false,
            rotateControl: false,
            fullscreenControl: true
        };
        
        // Create map instance
        const mapInstance = new window.google.maps.Map(mapRef.current, mapOptions);
        mapInstanceRef.current = mapInstance;
        
        // Create marker at center
        const marker = new window.google.maps.Marker({
            position: { lat: defaultLat, lng: defaultLng },
            map: mapInstance,
            draggable: true
        });
        markerRef.current = marker;
        
        // Add event listener for marker drag
        marker.addListener('dragend', function() {
            const position = marker.getPosition();
            if (onLocationChange) {
                onLocationChange({
                    lat: position.lat(),
                    lng: position.lng()
                });
            }
        });
        
        // Add event listener for map click
        mapInstance.addListener('click', function(event) {
            marker.setPosition(event.latLng);
            if (onLocationChange) {
                onLocationChange({
                    lat: event.latLng.lat(),
                    lng: event.latLng.lng()
                });
            }
        });
        
        // Add event listener for zoom changed
        mapInstance.addListener('zoom_changed', function() {
            if (onZoomChange) {
                onZoomChange(mapInstance.getZoom());
            }
        });
        
        // Create search box if Places library is available
        if (window.google.maps.places) {
            const input = document.createElement('input');
            input.setAttribute('type', 'text');
            input.setAttribute('placeholder', 'Search for a location...');
            input.style.width = '70%';
            input.style.padding = '12px';
            input.style.borderRadius = '4px';
            input.style.marginTop = '10px';
            input.style.boxSizing = 'border-box';
            
            const searchBox = new window.google.maps.places.SearchBox(input);
            searchBoxRef.current = searchBox;
            mapInstance.controls[window.google.maps.ControlPosition.TOP_CENTER].push(input);
            
            // Bias search results to current map viewport
            mapInstance.addListener('bounds_changed', function() {
                searchBox.setBounds(mapInstance.getBounds());
            });
            
            // Listen for search box selections
            searchBox.addListener('places_changed', function() {
                const places = searchBox.getPlaces();
                
                if (places.length === 0) return;
                
                const place = places[0];
                
                if (!place.geometry || !place.geometry.location) return;
                
                // Update marker and map position
                marker.setPosition(place.geometry.location);
                mapInstance.setCenter(place.geometry.location);
                
                // Update stored location
                if (onLocationChange) {
                    onLocationChange({
                        lat: place.geometry.location.lat(),
                        lng: place.geometry.location.lng()
                    });
                }
            });
        }
    };

    return (
        <div style={{ height: '300px', marginBottom: '20px', position: 'relative' }}>
            <div 
                ref={mapRef}
                style={{ 
                    height: '100%', 
                    width: '100%',
                }}
            />
        </div>
    );
};

export default MapSelector;
