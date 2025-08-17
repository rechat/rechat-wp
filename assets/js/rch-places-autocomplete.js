/**
 * Google Places Autocomplete integration for Rechat search form
 * Attaches autocomplete functionality to the location search input field
 */
(function($) {
    'use strict';
    
    // Add our initialization to the global initMap function
    var originalInitMap = window.initMap || function() {};
    
    window.initMap = function() {
        // Call the original initMap function first
        originalInitMap();
        
        // Then initialize our places autocomplete
        initPlacesAutocomplete();
    };
    
    // If the document is already loaded and Google Maps is available, initialize immediately
    $(document).ready(function() {
        if (window.google && window.google.maps && window.google.maps.places) {
            initPlacesAutocomplete();
        }
    });
    
    /**
     * Initialize Google Places Autocomplete on the search input field
     */
    function initPlacesAutocomplete() {
        // Find the search input field
        const searchInput = document.getElementById('content');
        
        // If no search input is found, exit early
        if (!searchInput) {
            return;
        }
        
        // Create the autocomplete object, restricting the search to geographical
        // location types only (cities, addresses, etc.)
        const autocomplete = new google.maps.places.Autocomplete(
            searchInput,
            {
                types: ['geocode'], // Only use 'geocode' type which includes cities, addresses, regions
                // Default to a global search, no specific country restriction
                fields: ['address_components', 'formatted_address', 'geometry', 'name'],
            }
        );
        
        // When the user selects a place, populate the address fields in the form
        autocomplete.addListener('place_changed', function() {
            const place = autocomplete.getPlace();
            
            if (!place.geometry) {
                // User entered the name of a place that was not suggested
                // Just use the text they entered as is
                return;
            }
            
            // Fill in the search input with the selected place's name/address
            const address = place.formatted_address || place.name;
            searchInput.value = address;
            
            // Optionally: You could also store lat/lng coordinates in hidden fields
            // if you want to use them in your backend processing
            /*
            const latitude = place.geometry.location.lat();
            const longitude = place.geometry.location.lng();
            
            // If you have hidden fields for lat/lng
            if (document.getElementById('search-lat')) {
                document.getElementById('search-lat').value = latitude;
            }
            if (document.getElementById('search-lng')) {
                document.getElementById('search-lng').value = longitude;
            }
            */
        });
        
        // Prevent form submission when Enter is pressed in the autocomplete field
        // (this allows the user to select from the dropdown with Enter)
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && $('.pac-container:visible').length) {
                e.preventDefault();
            }
        });
    }
    
})(jQuery);
