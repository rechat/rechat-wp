document.addEventListener('DOMContentLoaded', function() {
    var mapElement = document.getElementById('map');
    var mapButton = document.getElementById('rch-map-toggle-mobile');
    var mapButtonSpan = mapButton.querySelector('span');

    // Initially hide the map on mobile
    if (window.innerWidth < 768) {
        mapElement.style.zIndex = -1;
        mapButtonSpan.textContent = 'View Map';
    }

    // Add click event to the button
    mapButton.addEventListener('click', function() {
        if (mapElement.style.zIndex == -1) {
            // If map is hidden, slide it down and change button text
            mapElement.style.zIndex = 1;
            mapButtonSpan.textContent = 'Close Map';
        } else {
            // If map is visible, slide it up and change button text
            mapElement.style.zIndex = -1;
            mapButtonSpan.textContent = 'View Map';
        }
    });

    // Handle window resize to reset map visibility on desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            mapElement.style.zIndex = 1;
            mapButtonSpan.textContent = 'Close Map';
        } else if (mapElement.style.zIndex == -1) {
            mapButtonSpan.textContent = 'View Map';
        }
    });
});