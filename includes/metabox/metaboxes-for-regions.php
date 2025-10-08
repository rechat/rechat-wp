<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Add meta box for the 'regions' post type
 ******************************/
function add_region_meta_box()
{
    add_meta_box(
        'region_meta_box', // Meta box ID
        'Region Details', // Meta box title
        'display_region_meta_box', // Callback function to display the meta box
        'regions', // Custom post type
        'normal', // Context (placement)
        'high'    // Priority
    );
}
add_action('add_meta_boxes', 'add_region_meta_box');

/*******************************
 * Function to display the meta box for 'regions' post type
 ******************************/
function display_region_meta_box($post)
{
    // Retrieve the 'region_id' meta value
    $region_id = get_post_meta($post->ID, 'region_id', true);
?>
    <label for="region_id">Region ID (not available for locally added regions):</label>
    <input type="text" id="region_id" name="region_id" value="<?php echo esc_attr($region_id); ?>" class="widefat" readonly />
    <br>
<?php
}

/*******************************
 * Add a custom column to the 'regions' post type list table
 ******************************/
function add_region_id_column($columns)
{
    // Add a new column for Region ID
    $columns['region_id'] = 'Region ID';
    return $columns;
}
add_filter('manage_regions_posts_columns', 'add_region_id_column');

/*******************************
 * Display the content of the custom column
 ******************************/
function show_region_id_column_content($column, $post_id)
{
    if ($column === 'region_id') {
        // Retrieve the 'region_id' meta value
        $region_id = get_post_meta($post_id, 'region_id', true);
        echo esc_html($region_id);
    }
}
add_action('manage_regions_posts_custom_column', 'show_region_id_column_content', 10, 2);

/*******************************
 * Make the 'Region ID' column sortable
 ******************************/
function make_region_id_column_sortable($columns)
{
    $columns['region_id'] = 'region_id';
    return $columns;
}
add_filter('manage_edit-regions_sortable_columns', 'make_region_id_column_sortable');

/*******************************
 * Handle sorting by 'Region ID' column
 ******************************/
function sort_region_id_column($query)
{
    if (!is_admin()) {
        return;
    }

    $orderby = $query->get('orderby');

    if ($orderby === 'region_id') {
        $query->set('meta_key', 'region_id');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'sort_region_id_column');
/*******************************
 * Register the 'region_id' meta field for the 'regions' post type
 ******************************/
function register_region_id_meta()
{
    register_meta('post', 'region_id', array(
        'type'         => 'string', // Specify the data type of the meta value
        'description'  => 'Region ID', // Description of the meta field
        'single'       => true, // Whether the meta value is a single entry or an array
        'show_in_rest' => true, // Make it accessible via the REST API
        'auth_callback' => function () {
            return current_user_can('edit_posts'); // Authentication callback
        }
    ));
}
add_action('init', 'register_region_id_meta');
