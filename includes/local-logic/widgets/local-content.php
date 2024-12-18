<?php $api_key = get_option('rch_rechat_local_logic_api_key');
$primary_color = get_option('_rch_primary_color');

?>


<script
    async
    src="https://sdk.locallogic.co/sdks-js/1.22.21/index.umd.js"
    onload="loadLocalContentSDK()"></script>
<style>
    #local-content-widget {
        height: 700px;
        width: 100%;
    }
</style>

<!--NOTE: If you are implementing multiple SDKs, make sure you create unique IDs for each-->
<div id="local-content-widget"></div>

<script>
    const globalOptions = {
        locale: "en", // Change to either english or french
        appearance: {
            theme: "day",
            // Add any other appearance changes here
            variables: {
                "--ll-color-primary": "<?php echo esc_js($primary_color); ?>",
                "--ll-color-primary-variant1": "<?php echo esc_js($primary_color); ?>",
                "--ll-font-family": "Avenir, sans-serif"
            }
        }
    };

    function loadLocalContentSDK() {
        // Your API key or token
        const ll = LLSDKsJS("<?php echo esc_js($api_key) ?>", globalOptions);

        // This is the div that will contain the widget
        const sdkContainer = document.getElementById("local-content-widget");

        const sdkOptions = {
            lat: <?php echo isset($listing_detail['property']['address']['location']['latitude']) ? esc_js($listing_detail['property']['address']['location']['latitude']) : "''"; ?>,
            lng: <?php echo isset($listing_detail['property']['address']['location']['longitude']) ? esc_js($listing_detail['property']['address']['location']['longitude']) : "''"; ?>,

            // ...Other sdk specific options
            mapProvider: {
                name: "google",
                key: "<?php echo esc_js($google_map_api) ?>",
            },

        }

        const sdkInstance = ll.create("local-content", sdkContainer, sdkOptions);
    }
</script>