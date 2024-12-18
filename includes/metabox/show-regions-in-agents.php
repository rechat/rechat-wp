<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Create Custom MetaBox For Show Regions In Agents
 ******************************/
function custom_add_regions_meta_box()
{
    add_meta_box(
        'regions_meta_box', // ID of the meta box
        __('Rechat-Regions', 'rechat-plugin'), // Title of the meta box
        'rch_custom_regions_meta_box_callback', // Callback function
        'agents', // Post type where the meta box appears
        'side', // Context (normal, side, advanced)
        'high' // Priority
    );
}
add_action('add_meta_boxes', 'custom_add_regions_meta_box');

function rch_custom_regions_meta_box_callback($post)
{
    // Nonce field for security
    wp_nonce_field('custom_save_regions_meta_box_data', 'custom_regions_meta_box_nonce');

    // Get the current regions associated with the agent
    $selected_regions = get_post_meta($post->ID, '_rch_agent_regions', true);
    // Search input
    echo '<input type="text" id="region-search" class="rch-input-search" placeholder="Search for Rechat-Regions...">';

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
            $checked = in_array($region->ID, (array) $selected_regions) ? 'checked="checked"' : '';
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
function rch_custom_regions_admin_scripts()
{
    global $post_type;
    if ($post_type == 'agents') {
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
                    var selectedRegions = $('#selected-regions').val().split(',');

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

                // Handle adding selected regions to the hidden field
                $(document).on('change', '#region-results input[type="checkbox"]', function() {
                    var selectedRegions = $('#selected-regions').val().split(',');
                    if ($(this).is(':checked')) {
                        selectedRegions.push($(this).val());
                    } else {
                        selectedRegions = selectedRegions.filter(function(value) {
                            return value !== $(this).val();
                        }.bind(this));
                    }
                    $('#selected-regions').val(selectedRegions.join(','));
                });
            });
        </script>
<?php
    }
}
add_action('admin_footer', 'rch_custom_regions_admin_scripts');


/*******************************
 * Handle the AJAX Request in PHP
 ******************************/
function rch_custom_ajax_search_regions()
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
add_action('wp_ajax_search_regions', 'rch_custom_ajax_search_regions');



/*******************************
 * Save the Selected Offices
 ******************************/
function custom_save_regions_meta_box_data($post_id)
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
        update_post_meta($post_id, '_rch_agent_regions', $regions);
    } else {
        delete_post_meta($post_id, '_rch_agent_regions'); // If no regions selected, delete the meta
    }
}
add_action('save_post', 'custom_save_regions_meta_box_data');


/*******************************
 * Display Offices on the Front-End
 ******************************/
// function custom_display_offices($post_id) {
//     $offices = get_post_meta($post_id, '_rch_agent_offices', true);

//     if ($offices && is_array($offices)) {
//         echo '<h2>Associated Offices</h2>';
//         echo '<ul>';
//         foreach ($offices as $office_id) {
//             echo '<li><a href="' . get_permalink($office_id) . '">' . get_the_title($office_id) . '</a></li>';
//         }
//         echo '</ul>';
//     }
// }

// // Example usage in a template file (e.g., single-agents.php)
// if (is_singular('agents')) {
//     custom_display_offices(get_the_ID());
// }
