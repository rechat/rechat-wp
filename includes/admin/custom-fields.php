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
    <div id="map" style="width: 100%; height: 400px;"></div>
    <input type="hidden" id="neighborhood_lat" name="neighborhood_lat" value="<?php echo esc_attr($lat); ?>">
    <input type="hidden" id="neighborhood_lng" name="neighborhood_lng" value="<?php echo esc_attr($lng); ?>">
    <p>Click on the map to select a location.</p>

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

            map.addListener('click', function(event) {
                marker.setPosition(event.latLng);
                document.getElementById("neighborhood_lat").value = event.latLng.lat();
                document.getElementById("neighborhood_lng").value = event.latLng.lng();
            });

            marker.addListener('dragend', function(event) {
                document.getElementById("neighborhood_lat").value = event.latLng.lat();
                document.getElementById("neighborhood_lng").value = event.latLng.lng();
            });
        }
    </script>

    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo get_option('rch_rechat_google_map_api_key') ?>&callback=initMap"></script>

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
