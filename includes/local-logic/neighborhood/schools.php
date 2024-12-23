<?php $api_key = get_option('rch_rechat_local_logic_api_key') ?>

<script
    async
    src="https://sdk.locallogic.co/sdks-js/1.22.21/index.umd.js"
    onload="loadNeighborhoodSchoolsSDK()"></script>


<style>
    #neighborhood-schools-widget {
        height: 700px;
        width: 100%;
    }
</style>

<!--NOTE: If you are implementing multiple SDKs, make sure you create unique IDs for each-->
<div id="neighborhood-schools-widget"></div>

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

    function loadNeighborhoodSchoolsSDK() {
        // Your API key or token
        const ll = LLSDKsJS("<?php echo esc_js($api_key) ?>", globalOptions);

        // This is the div that will contain the widget
        const sdkContainer = document.getElementById("neighborhood-schools-widget");
        const lat = <?php echo isset($listing_detail['property']['address']['location']['latitude']) ? esc_js($listing_detail['property']['address']['location']['latitude']) : "''"; ?>;
        const lng = <?php echo isset($listing_detail['property']['address']['location']['longitude']) ? esc_js($listing_detail['property']['address']['location']['longitude']) : "''"; ?>;

        const sdkOptions = {
            lat: lat,
            lng: lng
            // ...Other sdk specific options
        };

        const sdkInstance = ll.create("neighborhood-schools", sdkContainer, sdkOptions);
    }
</script>