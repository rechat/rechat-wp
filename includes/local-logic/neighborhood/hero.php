<?php $api_key = get_option('rch_rechat_local_logic_api_key') ?>
<script
    async
    src="https://sdk.locallogic.co/sdks-js/1.22.21/index.umd.js"
    onload="loadNeighborhoodHeroSDK()"></script>
<style>
    #neighborhood-hero-widget {
        width: 100%;
    }
</style>
<div id="neighborhood-hero-widget"></div>
<script>
    const globalOptions = {
        locale: "en", // Change to either english or french
        appearance: {
            theme: "day",
            // Add any other appearance changes here
            variables: {
                "--ll-color-primary": "#fd3958",
                "--ll-color-primary-variant1": "#d5405b",
                "--ll-border-radius-small": "8px",
                "--ll-border-radius-medium": "16px",
                "--ll-font-family": "Avenir, sans-serif"
            }
        }
    };

    function loadNeighborhoodHeroSDK() {
        // Your API key or token
        const ll = LLSDKsJS("<?php echo esc_js($api_key) ?>", globalOptions);

        // This is the div that will contain the widget
        const sdkContainer = document.getElementById("neighborhood-hero-widget");

        // Check if lat and lng exist, if not set them to empty strings
        const lat = <?php echo isset($listing_detail['property']['address']['location']['latitude']) ? esc_js($listing_detail['property']['address']['location']['latitude']) : "''"; ?>;
        const lng = <?php echo isset($listing_detail['property']['address']['location']['longitude']) ? esc_js($listing_detail['property']['address']['location']['longitude']) : "''"; ?>;

        const sdkOptions = {
            lat: lat,
            lng: lng
            // ...Other sdk specific options
        };

        const sdkInstance = ll.create("neighborhood-hero", sdkContainer, sdkOptions);
    }
</script>