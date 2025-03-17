<?php
$api_key = get_option('rch_rechat_neighborhood_api_key');
$primary_color = get_option('_rch_primary_color');
 get_header() ?>
<div id="primary" class="content-area rch-primary-content">
    <main id="main" class="site-main content-container site-container">
    <div class="container">
    <div>
        <article class="singlePost--content text">
            <div class="neighborhoodSinglePost">
                <?php the_content() ?>
            </div>

            <script
                async
                src="https://sdk.locallogic.co/sdks-js/1.22.21/index.umd.js"
                onload="initializeLocalLogicSDK()"></script>
            <script>
                const globalOptions = {
                    locale: "en", // Change to either english or french
                    appearance: {
                        theme: "day",
                        // Add any other appearance changes here
                        variables: {
                            "--ll-color-primary": "<?php echo esc_js($primary_color); ?>",
                            "--ll-color-primary-variant1": "<?php echo esc_js($primary_color); ?>",
                            "--ll-border-radius-small": "8px",
                            "--ll-border-radius-medium": "16px",
                            "--ll-font-family": "Avenir, sans-serif"
                        }
                    }
                };

                function initializeLocalLogicSDK() {
                    const apiKey = "<?php echo esc_js($api_key) ?>";
                    if (apiKey) { // Check if apiKey is not empty
                        window.ll = LLSDKsJS(apiKey, globalOptions);
                    } else {
                        console.error("API Key is missing.");
                    }
                }
            </script>

            <?php
            // Retrieve the selected features from the settings
            $selected_features = get_option('rch_rechat_neighborhood_features', []);
            // Define the available template parts corresponding to each feature
            $feature_templates = [
                'Hero' => 'hero', // Template part for Hero feature
                'Map' => 'map', // Template part for Map feature
                'Highlights' => 'highlights', // Template part for Highlights feature
                'Characteristics' => 'characteristics', // Template part for Characteristics feature
                'Schools' => 'schools', // Template part for Schools feature
                'Demographics' => 'demographics', // Template part for Demographics feature
                'PropertyValueDrivers' => 'property-value-drivers', // Template part for PropertyValueDrivers feature
                'MarketTrends' => 'market-trends', // Template part for MarketTrends feature
                'Match' => 'match', // Template part for Match feature
            ];

            // Only run the loop if the API key is not empty
            if (!empty($api_key)) {
                // Loop through the selected features and include the corresponding template part from the plugin
                foreach ($selected_features as $feature) {
                    if (array_key_exists($feature, $feature_templates)) {
                        // Get the plugin directory path
                        $plugin_dir = RCH_PLUGIN_INCLUDES; // Adjust if your plugin files are in a subfolder

                        // Construct the template part file path
                        $template_part_path = $plugin_dir . 'local-logic/neighborhood/' . $feature_templates[$feature] . '.php';                            // Check if the template part file exists, then include it
                        if (file_exists($template_part_path)) {
                            include $template_part_path;
                        }
                    }
                }
            }
            ?>
        </article>
    </div>
</div>

</div>
<section class="related-neighbour">
    <div>
        <h2 class="lp-h2">Explore Similar Neighborhoods</h2>
        <ul class="rch-neighborhoods-archive">
            <?php echo get_related_neighborhoods(); ?>
        </ul>
    </div>
</section>
</main><!-- #main -->
</div><!-- #primary -->
<?php get_footer() ?>