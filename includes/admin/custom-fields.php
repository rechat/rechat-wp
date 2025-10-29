<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*******************************
 * Add neighborhood map metabox
 ******************************/
function add_neighborhood_map_metabox()
{
    add_meta_box(
        'neighborhood_map',
        __('Select Location on Map', 'rechat-plugin'),
        'neighborhood_map_callback',
        'neighborhoods',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_neighborhood_map_metabox');

/*******************************
 * Render neighborhood map metabox
 ******************************/
function neighborhood_map_callback($post)
{
    // Verify post object
    if (!$post || !isset($post->ID)) {
        return;
    }
    
    // Add nonce for security
    wp_nonce_field('rch_save_neighborhood_map', 'rch_neighborhood_map_nonce');
    
    // Get stored coordinates or use default (San Francisco)
    $lat = get_post_meta($post->ID, '_neighborhood_lat', true);
    $lng = get_post_meta($post->ID, '_neighborhood_lng', true);
    
    // Set defaults if empty
    $lat = !empty($lat) ? $lat : '37.7749';
    $lng = !empty($lng) ? $lng : '-122.4194';
    
    // Validate coordinates
    $lat = is_numeric($lat) ? floatval($lat) : 37.7749;
    $lng = is_numeric($lng) ? floatval($lng) : -122.4194;
    
    // Get Google Maps API key
    $google_map_api_key = get_option('rch_rechat_google_map_api_key', '');
    
    if (empty($google_map_api_key)) {
        echo '<div class="notice notice-warning inline"><p>';
        printf(
            /* translators: %s: settings page URL */
            esc_html__('Please configure your Google Maps API key in the %s to use this feature.', 'rechat-plugin'),
            '<a href="' . esc_url(admin_url('admin.php?page=rechat-setting&tab=local-logic')) . '">' . esc_html__('plugin settings', 'rechat-plugin') . '</a>'
        );
        echo '</p></div>';
        return;
    }

    ?>
    <div style="margin-bottom: 15px;">
        <label for="neighborhood_search_box" style="display: block; margin-bottom: 5px; font-weight: 600;">
            <?php esc_html_e('Search Location:', 'rechat-plugin'); ?>
        </label>
        <input 
            type="text" 
            id="neighborhood_search_box" 
            placeholder="<?php esc_attr_e('Enter a location...', 'rechat-plugin'); ?>" 
            style="width: 100%; padding: 8px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px;"
        >
    </div>
    
    <div id="rch-neighborhood-map" style="width: 100%; height: 400px;"></div>
    
    <input 
        type="hidden" 
        id="neighborhood_lat" 
        name="neighborhood_lat" 
        value="<?php echo esc_attr($lat); ?>"
    >
    <input 
        type="hidden" 
        id="neighborhood_lng" 
        name="neighborhood_lng" 
        value="<?php echo esc_attr($lng); ?>"
    >
    
    <p class="description">
        <?php esc_html_e('Search for a location above or click on the map to select a location.', 'rechat-plugin'); ?>
    </p>

    <script>
    (function() {
        'use strict';
        
        window.rchInitNeighborhoodMap = function() {
            var defaultLocation = {
                lat: <?php echo esc_js($lat); ?>,
                lng: <?php echo esc_js($lng); ?>
            };

            var mapElement = document.getElementById('rch-neighborhood-map');
            if (!mapElement || typeof google === 'undefined') {
                return;
            }

            var map = new google.maps.Map(mapElement, {
                center: defaultLocation,
                zoom: 10,
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: true
            });

            var marker = new google.maps.Marker({
                position: defaultLocation,
                map: map,
                draggable: true,
                title: <?php echo wp_json_encode(__('Drag to change location', 'rechat-plugin')); ?>
            });

            // Initialize the autocomplete
            var searchBox = document.getElementById('neighborhood_search_box');
            if (searchBox && google.maps.places) {
                var autocomplete = new google.maps.places.Autocomplete(searchBox, {
                    fields: ['geometry', 'name', 'formatted_address']
                });

                // Bias the autocomplete results to the map's viewport
                autocomplete.bindTo('bounds', map);

                // Listen for place selection from autocomplete
                autocomplete.addListener('place_changed', function() {
                    var place = autocomplete.getPlace();

                    if (!place.geometry || !place.geometry.location) {
                        console.warn('No details available for: ' + place.name);
                        return;
                    }

                    // Update map center and marker position
                    map.setCenter(place.geometry.location);
                    marker.setPosition(place.geometry.location);

                    // Zoom to the selected place
                    if (place.geometry.viewport) {
                        map.fitBounds(place.geometry.viewport);
                    } else {
                        map.setZoom(15);
                    }

                    // Update hidden fields with new coordinates
                    updateCoordinates(place.geometry.location.lat(), place.geometry.location.lng());
                });
            }

            // Helper function to update coordinates
            function updateCoordinates(lat, lng) {
                var latField = document.getElementById('neighborhood_lat');
                var lngField = document.getElementById('neighborhood_lng');
                
                if (latField && lngField) {
                    latField.value = lat;
                    lngField.value = lng;
                }
            }

            // Listen for map clicks
            map.addListener('click', function(event) {
                if (event.latLng) {
                    marker.setPosition(event.latLng);
                    updateCoordinates(event.latLng.lat(), event.latLng.lng());
                }
            });

            // Listen for marker drag
            marker.addListener('dragend', function(event) {
                if (event.latLng) {
                    updateCoordinates(event.latLng.lat(), event.latLng.lng());
                }
            });
        };
    })();
    </script>

    <script 
        async 
        defer 
        src="<?php echo esc_url('https://maps.googleapis.com/maps/api/js?key=' . urlencode($google_map_api_key) . '&libraries=places&callback=rchInitNeighborhoodMap'); ?>"
    ></script>

    <?php
}

/*******************************
 * Save neighborhood map metadata
 ******************************/
function save_neighborhood_map_meta($post_id)
{
    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['rch_neighborhood_map_nonce']) || 
        !wp_verify_nonce($_POST['rch_neighborhood_map_nonce'], 'rch_save_neighborhood_map')) {
        return;
    }
    
    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Check if this is the correct post type
    if (get_post_type($post_id) !== 'neighborhoods') {
        return;
    }
    
    // Save latitude
    if (isset($_POST['neighborhood_lat'])) {
        $lat = sanitize_text_field(wp_unslash($_POST['neighborhood_lat']));
        
        // Validate latitude (-90 to 90)
        if (is_numeric($lat)) {
            $lat = floatval($lat);
            if ($lat >= -90 && $lat <= 90) {
                update_post_meta($post_id, '_neighborhood_lat', $lat);
            }
        }
    }
    
    // Save longitude
    if (isset($_POST['neighborhood_lng'])) {
        $lng = sanitize_text_field(wp_unslash($_POST['neighborhood_lng']));
        
        // Validate longitude (-180 to 180)
        if (is_numeric($lng)) {
            $lng = floatval($lng);
            if ($lng >= -180 && $lng <= 180) {
                update_post_meta($post_id, '_neighborhood_lng', $lng);
            }
        }
    }
}
add_action('save_post', 'save_neighborhood_map_meta');
