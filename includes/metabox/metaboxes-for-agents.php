<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
} 
/*******************************
 * Function to add meta box to the 'agents' post type
 ******************************/
function add_agents_meta_box()
{
    add_meta_box(
        'agents_meta_box',       // Meta box ID
        'Agent Details',         // Title
        'agents_meta_box_html',  // Callback function
        'agents',                // Post type
        'normal',                // Context (where to show the meta box)
        'high'                   // Priority
    );
}
add_action('add_meta_boxes', 'add_agents_meta_box');

/*******************************
 * Callback function to display the meta box HTML
 ******************************/
function agents_meta_box_html($post)
{
    // Add a nonce field for security
    wp_nonce_field('agents_meta_box', 'agents_meta_box_nonce');

    // Get current values of custom fields
    $api_id = get_post_meta($post->ID, 'api_id', true);
    $website = get_post_meta($post->ID, 'website', true);
    $instagram = get_post_meta($post->ID, 'instagram', true);
    $twitter = get_post_meta($post->ID, 'twitter', true);
    $linkedin = get_post_meta($post->ID, 'linkedin', true);
    $youtube = get_post_meta($post->ID, 'youtube', true);
    $facebook = get_post_meta($post->ID, 'facebook', true);
    $phone_number = get_post_meta($post->ID, 'phone_number', true);
    $email = get_post_meta($post->ID, 'email', true);
    $timezone = get_post_meta($post->ID, 'timezone', true);
    $profile_image_url = get_post_meta($post->ID, 'profile_image_url', true);
?>
    <label for="api_id_field">Rechat ID (not available for locally added agents): </label>
    <input type="text" id="api_id_field" name="api_id_field" value="<?php echo esc_attr($api_id); ?>" class="widefat" readonly />
    <br>
    <label for="agents_profile_image_url">Profile Image URL</label>
    <input type="text" id="agents_profile_image_url" name="agents_profile_image_url" value="<?php echo esc_attr($profile_image_url); ?>" class="widefat" />
    <br>
    <label for="agents_timezone">timezone</label>
    <input type="text" id="agents_timezone" name="agents_timezone" value="<?php echo esc_attr($timezone); ?>" class="widefat" />
    <br>
    <label for="agents_website">Website</label>
    <input type="text" id="agents_website" name="agents_website" value="<?php echo esc_attr($website); ?>" class="widefat" />
    <br>

    <label for="agents_instagram">Instagram</label>
    <input type="text" id="agents_instagram" name="agents_instagram" value="<?php echo esc_attr($instagram); ?>" class="widefat" />
    <br>

    <label for="agents_twitter">Twitter</label>
    <input type="text" id="agents_twitter" name="agents_twitter" value="<?php echo esc_attr($twitter); ?>" class="widefat" />
    <br>

    <label for="agents_linkedin">LinkedIn</label>
    <input type="text" id="agents_linkedin" name="agents_linkedin" value="<?php echo esc_attr($linkedin); ?>" class="widefat" />
    <br>

    <label for="agents_youtube">YouTube</label>
    <input type="text" id="agents_youtube" name="agents_youtube" value="<?php echo esc_attr($youtube); ?>" class="widefat" />
    <br>

    <label for="agents_facebook">Facebook</label>
    <input type="text" id="agents_facebook" name="agents_facebook" value="<?php echo esc_attr($facebook); ?>" class="widefat" />
    <br>

    <label for="agents_phone_number">Phone Number</label>
    <input type="text" id="agents_phone_number" name="agents_phone_number" value="<?php echo esc_attr($phone_number); ?>" class="widefat" />
    <br>

    <label for="agents_email">Email</label>
    <input type="text" id="agents_email" name="agents_email" value="<?php echo esc_attr($email); ?>" class="widefat" />
    <label for="agents_designation">Designation</label>
<input type="text" id="agents_designation" name="agents_designation" value="<?php echo esc_attr(get_post_meta($post->ID, 'designation', true)); ?>" class="widefat" />
<br>
<?php
}

/*******************************
 * Function to save custom field data
 ******************************/
function save_agents_meta_box($post_id)
{
    // Check if our nonce is set.
    if (!isset($_POST['agents_meta_box_nonce'])) {
        return $post_id;
    }
    $nonce = $_POST['agents_meta_box_nonce'];

    // Verify that the nonce is valid.
    if (!wp_verify_nonce($nonce, 'agents_meta_box')) {
        return $post_id;
    }

    // Check if this is an autosave.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // Check the user's permissions.
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    // Sanitize and save each field
    $fields = array(
        'agents_profile_image_url' => 'profile_image_url',
        'agents_website' => 'website',
        'agents_instagram' => 'instagram',
        'agents_twitter' => 'twitter',
        'agents_linkedin' => 'linkedin',
        'agents_youtube' => 'youtube',
        'agents_facebook' => 'facebook',
        'agents_phone_number' => 'phone_number',
        'agents_email' => 'email',
        'agents_timezone' => 'timezone',
        'agents_designation'       => 'designation', // Add designation
    );

    foreach ($fields as $input_name => $meta_key) {
        if (isset($_POST[$input_name])) {
            $value = sanitize_text_field($_POST[$input_name]);
            update_post_meta($post_id, $meta_key, $value);
        }
    }
}


add_action('save_post', 'save_agents_meta_box');

/*******************************
 * Function to show api id column in custom post type
 ******************************/
function add_api_id_columns($columns) {
    // Add a new column for API ID
    $columns['api_id'] = 'Rechat ID (not available for locally added agents)';
    return $columns;
}
add_filter('manage_agents_posts_columns', 'add_api_id_columns');


function show_api_id_column_data($column, $post_id) {
    if ($column === 'api_id') {
        $api_id = get_post_meta($post_id, 'api_id', true);
        echo esc_html($api_id);
    }
}
add_action('manage_agents_posts_custom_column', 'show_api_id_column_data', 10, 2);