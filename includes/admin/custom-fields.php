<?php

function add_neighborhood_map_metabox()
{
    add_meta_box(
        'neighborhood_map',
        'Select Location on Map',
        'neighborhood_map_callback',
        'neighborhoods',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_neighborhood_map_metabox');

function neighborhood_map_callback($post)
{
    $lat = get_post_meta($post->ID, '_neighborhood_lat', true) ?: '37.7749';
    $lng = get_post_meta($post->ID, '_neighborhood_lng', true) ?: '-122.4194';

?>
    <div style="margin-bottom: 15px;">
        <label for="neighborhood_search_box" style="display: block; margin-bottom: 5px; font-weight: 600;">Search Location:</label>
        <input type="text" id="neighborhood_search_box" placeholder="Enter a location..." style="width: 100%; padding: 8px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    <div id="map" style="width: 100%; height: 400px;"></div>
    <input type="hidden" id="neighborhood_lat" name="neighborhood_lat" value="<?php echo esc_attr($lat); ?>">
    <input type="hidden" id="neighborhood_lng" name="neighborhood_lng" value="<?php echo esc_attr($lng); ?>">
    <p>Search for a location above or click on the map to select a location.</p>

    <script>
        function initMap() {
            var defaultLocation = {
                lat: parseFloat(<?php echo $lat; ?>),
                lng: parseFloat(<?php echo $lng; ?>)
            };

            var map = new google.maps.Map(document.getElementById('map'), {
                center: defaultLocation,
                zoom: 10
            });

            var marker = new google.maps.Marker({
                position: defaultLocation,
                map: map,
                draggable: true
            });

            // Initialize the autocomplete
            var searchBox = document.getElementById('neighborhood_search_box');
            var autocomplete = new google.maps.places.Autocomplete(searchBox, {
                fields: ['geometry', 'name', 'formatted_address']
            });

            // Bias the autocomplete results to the map's viewport
            autocomplete.bindTo('bounds', map);

            // Listen for place selection from autocomplete
            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();

                if (!place.geometry || !place.geometry.location) {
                    window.alert("No details available for: '" + place.name + "'");
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
                document.getElementById("neighborhood_lat").value = place.geometry.location.lat();
                document.getElementById("neighborhood_lng").value = place.geometry.location.lng();
            });

            // Listen for map clicks
            map.addListener('click', function(event) {
                marker.setPosition(event.latLng);
                document.getElementById("neighborhood_lat").value = event.latLng.lat();
                document.getElementById("neighborhood_lng").value = event.latLng.lng();
            });

            // Listen for marker drag
            marker.addListener('dragend', function(event) {
                document.getElementById("neighborhood_lat").value = event.latLng.lat();
                document.getElementById("neighborhood_lng").value = event.latLng.lng();
            });
        }
    </script>

    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo get_option('rch_rechat_google_map_api_key') ?>&libraries=places&callback=initMap"></script>

<?php
}
function save_neighborhood_map_meta($post_id)
{
    if (isset($_POST['neighborhood_lat']) && isset($_POST['neighborhood_lng'])) {
        update_post_meta($post_id, '_neighborhood_lat', sanitize_text_field($_POST['neighborhood_lat']));
        update_post_meta($post_id, '_neighborhood_lng', sanitize_text_field($_POST['neighborhood_lng']));
    }
}
add_action('save_post', 'save_neighborhood_map_meta');
