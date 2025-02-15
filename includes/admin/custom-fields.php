<?php
// Add a meta box for Latitude & Longitude
function neighborhoods_add_meta_box() {
    add_meta_box(
        'neighborhoods_location', 
        __('Neighborhood Location', 'textdomain'), 
        'neighborhoods_meta_box_callback', 
        'neighborhoods', 
        'normal', 
        'high'
    );
}
add_action('add_meta_boxes', 'neighborhoods_add_meta_box');

// Display the Meta Box Fields
function neighborhoods_meta_box_callback($post) {
    $lat = get_post_meta($post->ID, '_neighborhood_lat', true);
    $lng = get_post_meta($post->ID, '_neighborhood_lng', true);

    wp_nonce_field('neighborhoods_save_location', 'neighborhoods_nonce');
    ?>

    <label for="neighborhood_lat"><?php _e('Latitude:', 'textdomain'); ?></label>
    <input type="text" id="neighborhood_lat" name="neighborhood_lat" value="<?php echo esc_attr($lat); ?>" style="width:100%;">
    <br><br>

    <label for="neighborhood_lng"><?php _e('Longitude:', 'textdomain'); ?></label>
    <input type="text" id="neighborhood_lng" name="neighborhood_lng" value="<?php echo esc_attr($lng); ?>" style="width:100%;">

    <?php
}
function neighborhoods_save_location($post_id) {
    if (!isset($_POST['neighborhoods_nonce']) || !wp_verify_nonce($_POST['neighborhoods_nonce'], 'neighborhoods_save_location')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['neighborhood_lat'])) {
        update_post_meta($post_id, '_neighborhood_lat', sanitize_text_field($_POST['neighborhood_lat']));
    }
    if (isset($_POST['neighborhood_lng'])) {
        update_post_meta($post_id, '_neighborhood_lng', sanitize_text_field($_POST['neighborhood_lng']));
    }
}
add_action('save_post', 'neighborhoods_save_location');