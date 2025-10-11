<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Add meta box for the 'offices' post type
 ******************************/
function add_office_meta_box()
{
    add_meta_box(
        'office_meta_box', // Meta box ID
        'Office Details', // Meta box title
        'display_office_meta_box', // Callback function to display the meta box
        'offices', // Custom post type
        'normal', // Context (placement)
        'high'    // Priority
    );
}
add_action('add_meta_boxes', 'add_office_meta_box');

/*******************************
 * Function to display the meta box for 'offices' post type
 ******************************/
function display_office_meta_box($post)
{
    // Retrieve the 'office_id' meta value
    $office_id = get_post_meta($post->ID, 'office_id', true);
?>
    <p>
        <label for="office_id">Office ID (not available for locally added offices):</label>
        <input type="text" id="office_id" name="office_id" value="<?php echo esc_attr($office_id); ?>" readonly>
    </p>
    <?php
    // Single address field
    $office_address = get_post_meta($post->ID, 'office_address', true);

    // Nonce for security
    wp_nonce_field('office_address_meta_nonce', 'office_address_meta_nonce_field');
    ?>

    <h4><?php esc_html_e('Address', 'rechat-plugin'); ?></h4>
    <p>
        <label for="office_address"><?php esc_html_e('Full Address', 'rechat-plugin'); ?>:</label><br>
        <input type="text" id="office_address" name="office_address" value="<?php echo esc_attr($office_address); ?>" style="width:100%;">
    </p>
<?php
}

/*******************************
 * Add a custom column to the 'offices' post type list table
 ******************************/
function add_office_id_column($columns)
{
    // Add a new column for Office ID
    $columns['office_id'] = 'Office ID';
    return $columns;
}
add_filter('manage_offices_posts_columns', 'add_office_id_column');

/*******************************
 * Display the content of the custom column
 ******************************/
function show_office_id_column_content($column, $post_id)
{
    if ($column === 'office_id') {
        // Retrieve the 'office_id' meta value
        $office_id = get_post_meta($post_id, 'office_id', true);
        echo esc_html($office_id);
    }
}
add_action('manage_offices_posts_custom_column', 'show_office_id_column_content', 10, 2);

/*******************************
 * Make the 'Office ID' column sortable
 ******************************/
function make_office_id_column_sortable($columns)
{
    $columns['office_id'] = 'office_id';
    return $columns;
}
add_filter('manage_edit-offices_sortable_columns', 'make_office_id_column_sortable');

/*******************************
 * Handle sorting by 'Office ID' column
 ******************************/
function sort_office_id_column($query)
{
    if (!is_admin()) {
        return;
    }

    $orderby = $query->get('orderby');

    if ($orderby === 'office_id') {
        $query->set('meta_key', 'office_id');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'sort_office_id_column');
/**
 * Save office address meta when post is saved
 */
function save_office_address_meta($post_id)
{
    // Verify nonce
    if (!isset($_POST['office_address_meta_nonce_field']) || !wp_verify_nonce(sanitize_text_field($_POST['office_address_meta_nonce_field']), 'office_address_meta_nonce')) {
        return;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Sanitize and save fields
    if (isset($_POST['office_address'])) {
        update_post_meta($post_id, 'office_address', sanitize_text_field($_POST['office_address']));
    }
}
add_action('save_post_offices', 'save_office_address_meta');
/*******************************
 * Register the 'region_id' meta field for the 'regions' post type
 ******************************/
function register_office_id_meta()
{
    register_meta('post', 'office_id', array(
        'type'         => 'string', // Specify the data type of the meta value
        'description'  => 'Office ID', // Description of the meta field
        'single'       => true, // Whether the meta value is a single entry or an array
        'show_in_rest' => true, // Make it accessible via the REST API
        'auth_callback' => function () {
            return current_user_can('edit_posts'); // Authentication callback
        }
    ));

    // Register single office address meta field
    register_meta('post', 'office_address', array(
        'type' => 'string',
        'description' => 'Office full address',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('init', 'register_office_id_meta');
