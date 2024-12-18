<?php
if (!defined('ABSPATH')) {
    exit();
}

/*******************************
 * Create Custom MetaBox For Show Regions In Offices
 ******************************/
function custom_add_regions_meta_box_in_Offices()
{
    add_meta_box(
        'regions_meta_box_in_Offices', // ID of the meta box
        __('Rechat Regions', 'rechat-plugin'), // Title of the meta box
        'rch_custom_regions_meta_box_callback_in_Offices', // Callback function
        'offices', // Post type where the meta box appears
        'side', // Context (normal, side, advanced)
        'high' // Priority
    );
}
add_action('add_meta_boxes', 'custom_add_regions_meta_box_in_Offices');

function rch_custom_regions_meta_box_callback_in_Offices($post)
{
    // Nonce field for security
    wp_nonce_field('custom_save_regions_meta_box_data', 'custom_regions_meta_box_nonce');

    // Get the associated regions' meta values from the office's meta field (these are values from the API)
    $associated_region_metas = get_post_meta($post->ID, 'rch_associated_regions_to_office', true);

    // Search input for regions
    echo '<input type="text" id="region-search" class="rch-input-search" placeholder="Search for Rechat Regions...">';

    // Container for displaying search results
    echo '<div id="region-results" class="rch-rechat-meta-scroll">';

    // Query all regions
    $args = array(
        'post_type' => 'regions',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );
    $regions = get_posts($args);

    // Display all regions with checkboxes
    if ($regions) {
        foreach ($regions as $region) {
            $checked = in_array($region->ID, (array) $associated_region_metas) ? 'checked="checked"' : '';

            // Display the checkbox for each region
            echo '<label><input type="checkbox" name="agent_regions[]" value="' . esc_attr($region->ID) . '" ' . esc_attr($checked) . '> ' . esc_html($region->post_title) . '</label><br>';
        }
    } else {
        echo '<p>No regions available.</p>';
    }

    echo '</div>';
}




/*******************************
 * Add AJAX Script for Search
 ******************************/
function rch_custom_regions_admin_scripts_in_Offices()
{
    global $post_type;
    if ($post_type == 'offices') {
?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Trigger search on input
                $('#region-search').on('input', function() {
                    searchRegions();
                });

                // Trigger initial search on page load to display all regions
                searchRegions();

                function searchRegions() {
                    var searchQuery = $('#region-search').val();
                    var selectedRegions = $('input[name="agent_regions[]"]:checked').map(function() {
                        return $(this).val();
                    }).get();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'search_regions',
                            search: searchQuery,
                            selected_regions: selectedRegions
                        },
                        success: function(response) {
                            $('#region-results').html(response);
                        }
                    });
                }
            });
        </script>
<?php
    }
}
add_action('admin_footer', 'rch_custom_regions_admin_scripts_in_Offices');

/*******************************
 * Handle the AJAX Request in PHP
 ******************************/
function rch_custom_ajax_search_regions_in_Offices()
{
    $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $selected_regions = isset($_POST['selected_regions']) ? array_map('intval', $_POST['selected_regions']) : array();

    $args = array(
        'post_type' => 'regions',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        's' => $search_query, // Search by query if provided
    );

    $regions = new WP_Query($args);

    if ($regions->have_posts()) {
        while ($regions->have_posts()) : $regions->the_post();
            $checked = in_array(get_the_ID(), $selected_regions) ? 'checked="checked"' : '';
            echo '<label><input type="checkbox" name="agent_regions[]" value="' . esc_attr(get_the_ID()) . '" ' . esc_attr($checked) . '> ' . esc_html(get_the_title()) . '</label><br>';
        endwhile;
    } else {
        echo '<p>No regions found.</p>';
    }

    wp_die();
}
add_action('wp_ajax_search_regions', 'rch_custom_ajax_search_regions_in_Offices');

/*******************************
 * Save the Selected Regions
 ******************************/
function custom_save_regions_meta_box_data_in_Offices($post_id)
{
    // Check if nonce is set and valid
    if (!isset($_POST['custom_regions_meta_box_nonce']) || !wp_verify_nonce($_POST['custom_regions_meta_box_nonce'], 'custom_save_regions_meta_box_data')) {
        return;
    }

    // Check if the user has permission to save the data
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save the selected regions
    if (isset($_POST['agent_regions'])) {
        $regions = array_map('intval', $_POST['agent_regions']); // Map to ensure IDs are integers
        update_post_meta($post_id, 'rch_associated_regions_to_office', $regions);
    } else {
        delete_post_meta($post_id, 'rch_associated_regions_to_office'); // If no regions selected, delete the meta
    }
}
add_action('save_post', 'custom_save_regions_meta_box_data_in_Offices');
/*******************************
 * Show Custom Column in Admin In Offices (Associated Regions)
 *******************************/
function custom_regions_columns_in_Offices($columns)
{
    $columns['regions'] = __('Associated Regions', 'rechat-plugin');
    return $columns;
}
add_filter('manage_offices_posts_columns', 'custom_regions_columns_in_Offices');

/*******************************
 * Display Custom Column in Admin (Associated Regions)
 *******************************/
function custom_regions_custom_column_in_Offices($column, $post_id)
{
    if ($column === 'regions') {
        // Retrieve the associated regions from the custom meta field
        $regions = get_post_meta($post_id, 'rch_associated_regions_to_office', true);

        // Ensure $regions is an array
        if (!is_array($regions)) {
            $regions = array($regions); // Wrap in an array if it's not already one
        }

        if ($regions && !empty($regions)) {
            $output = array();

            // Loop through the regions and get their titles
            foreach ($regions as $region_id) {
                $output[] = get_the_title($region_id); // Get the title of each associated region
            }

            // Display the region titles separated by commas
            echo implode(', ', array_map('esc_html', $output));
        } else {
            echo esc_html(__('No Regions Assigned', 'rechat-plugin'));
        }
    }
}
add_action('manage_offices_posts_custom_column', 'custom_regions_custom_column_in_Offices', 10, 2);

