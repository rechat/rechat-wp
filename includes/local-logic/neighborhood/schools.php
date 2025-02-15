<!--NOTE: If you are implementing multiple SDKs, make sure you create unique IDs for each-->
<div id="neighborhood-schools-widget" class="rch-neighborhood-local-logic"></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        function initializeSDK() {
            if (window.ll) {
                const sdkContainer = document.getElementById("neighborhood-schools-widget");
                const lat = <?php echo get_post_meta(get_the_ID(), '_neighborhood_lat', true); ?>;
                const lng = <?php echo get_post_meta(get_the_ID(), '_neighborhood_lng', true); ?>;

                const sdkOptions = {
                    lat: lat,
                    lng: lng
                };

                window.ll.create("neighborhood-schools", sdkContainer, sdkOptions);
            } else {
                console.error("Local Logic SDK is not loaded.");
                setTimeout(initializeSDK, 1000); // Retry after 1 second
            }
        }

        initializeSDK(); // Initialize on page load
    });
</script>
