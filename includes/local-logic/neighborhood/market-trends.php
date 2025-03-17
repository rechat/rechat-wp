<!--NOTE: If you are implementing multiple SDKs, make sure you create unique IDs for each-->
<div id="neighborhood-market-trends-widget" class="rch-neighborhood-local-logic"></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        function initializeSDK() {
            if (window.ll) {
                const sdkContainer = document.getElementById("neighborhood-market-trends-widget");
                const lat = <?php echo get_post_meta(get_the_ID(), '_neighborhood_lat', true); ?>;
                const lng = <?php echo get_post_meta(get_the_ID(), '_neighborhood_lng', true); ?>;

                const sdkOptions = {
                    lat: lat,
                    lng: lng
                };

                window.ll.create("neighborhood-market-trends", sdkContainer, sdkOptions);
            } else {
                console.error("Local Logic SDK is not loaded.");
                setTimeout(initializeSDK, 1000); // Retry after 1 second
            }
        }

        initializeSDK(); // Initialize on page load
    });
</script>
