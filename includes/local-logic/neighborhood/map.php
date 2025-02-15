<!-- Neighborhood Map Widget -->
<div id="neighborhood-map-widget" class="rch-neighborhood-local-logic"></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        function initializeSDK() {
            if (window.ll) {
                const sdkContainer = document.getElementById("neighborhood-map-widget");
                const lat = 45.5282164; // Replace with dynamic latitude
                const lng = -73.5978527; // Replace with dynamic longitude

                const sdkOptions = {
                    lat: lat,
                    lng: lng
                };

                window.ll.create("neighborhood-map", sdkContainer, sdkOptions);
            } else {
                console.error("Local Logic SDK is not loaded.");
                setTimeout(initializeSDK, 1000); // Retry after 1 second
            }
        }

        initializeSDK(); // Initialize on page load
    });
</script>