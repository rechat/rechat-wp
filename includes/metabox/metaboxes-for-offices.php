<?php
if ( ! defined( 'ABSPATH' ) ) {
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
}

/*******************************
 * Add a custom column to the 'offices' post type list table
 ******************************/
function add_office_id_column($columns) {
    // Add a new column for Office ID
    $columns['office_id'] = 'Office ID';
    return $columns;
}
add_filter('manage_offices_posts_columns', 'add_office_id_column');

/*******************************
 * Display the content of the custom column
 ******************************/
function show_office_id_column_content($column, $post_id) {
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
function make_office_id_column_sortable($columns) {
    $columns['office_id'] = 'office_id';
    return $columns;
}
add_filter('manage_edit-offices_sortable_columns', 'make_office_id_column_sortable');

/*******************************
 * Handle sorting by 'Office ID' column
 ******************************/
function sort_office_id_column($query) {
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
