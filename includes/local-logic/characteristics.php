<?php $api_key = get_option('rch_rechat_local_logic_api_key') ?>
<script
    async
    src="https://sdk.locallogic.co/sdks-js/1.22.21/index.umd.js"
    onload="loadNeighborhoodCharacteristicsSDK()"></script>


<style>
    #neighborhood-characteristics-widget {
        width: 100%;
    }
</style>

<!--NOTE: If you are implementing multiple SDKs, make sure you create unique IDs for each-->
<div id="neighborhood-characteristics-widget"></div>

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

    function loadNeighborhoodCharacteristicsSDK() {
        // Your API key or token
        const ll = LLSDKsJS("<?php echo $api_key ?>", globalOptions);

        // This is the div that will contain the widget
        const sdkContainer = document.getElementById("neighborhood-characteristics-widget");

        const lat = <?php echo isset($listing_detail['property']['address']['location']['latitude']) ? $listing_detail['property']['address']['location']['latitude'] : "''"; ?>;
        const lng = <?php echo isset($listing_detail['property']['address']['location']['longitude']) ? $listing_detail['property']['address']['location']['longitude'] : "''"; ?>;

        const sdkOptions = {
            lat: lat,
            lng: lng
            // ...Other sdk specific options
        };

        const sdkInstance = ll.create("neighborhood-characteristics", sdkContainer, sdkOptions);
    }
</script>