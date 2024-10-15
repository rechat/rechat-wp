<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Create Custom MetaBox For Show Offices In Agents
 ******************************/
function custom_add_offices_meta_box()
{
    add_meta_box(
        'agents_offices_meta_box', // ID
        __('Rechat-Offices'), // Title
        'rch_custom_offices_meta_box_callback', // Callback
        'agents', // Screen (post type)
        'side', // Context
        'default' // Priority
    );
}
add_action('add_meta_boxes', 'custom_add_offices_meta_box');

function rch_custom_offices_meta_box_callback($post)
{
    // Nonce field for security
    wp_nonce_field('custom_save_offices_meta_box_data', 'custom_offices_meta_box_nonce');

    // Get the current offices associated with the agent
    $selected_offices = get_post_meta($post->ID, '_rch_agent_offices', true);

    // Search input
    echo '<input type="text" id="office-search" class="rch-input-search" placeholder="Search for offices...">';

    // Container for displaying search results
    echo '<div id="office-results" class="rch-rechat-meta-scroll">';

    // Query all offices
    $args = array(
        'post_type' => 'offices',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );
    $offices = get_posts($args);

    // Display all offices with checkboxes
    if ($offices) {
        foreach ($offices as $office) {
            $checked = in_array($office->ID, (array) $selected_offices) ? 'checked="checked"' : '';
            echo '<label><input type="checkbox" name="agent_offices[]" value="' . esc_attr($office->ID) . '" ' . $checked . '> ' . esc_html($office->post_title) . '</label><br>';
        }
    } else {
        echo '<p>No offices available.</p>';
    }
    echo '</div>';
}


/*******************************
 * Add AJAX Script for Search
 ******************************/
function rch_ajax_offices_admin_scripts()
{
    global $post_type;
    if ($post_type == 'agents') {
?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#office-search').on('input', function() {
                    var searchQuery = $(this).val();
                    var selectedOffices = $('#selected-offices').val().split(',');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'search_offices',
                            search: searchQuery,
                            selected_offices: selectedOffices
                        },
                        success: function(response) {
                            $('#office-results').html(response);
                        }
                    });
                });

                // Handle adding selected offices to the hidden field
                $(document).on('change', '#office-results input[type="checkbox"]', function() {
                    var selectedOffices = $('#selected-offices').val().split(',');
                    if ($(this).is(':checked')) {
                        selectedOffices.push($(this).val());
                    } else {
                        selectedOffices = selectedOffices.filter(function(value) {
                            return value !== $(this).val();
                        }.bind(this));
                    }
                    $('#selected-offices').val(selectedOffices.join(','));
                });
            });
        </script>
<?php
    }
}
add_action('admin_footer', 'rch_ajax_offices_admin_scripts');

/*******************************
 * Handle the AJAX Request in PHP
 ******************************/
function rch_ajax_search_offices_function()
{
    $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $selected_offices = isset($_POST['selected_offices']) ? array_map('intval', $_POST['selected_offices']) : array();

    $args = array(
        'post_type' => 'offices',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        's' => $search_query,
    );

    $offices = new WP_Query($args);

    if ($offices->have_posts()) {
        while ($offices->have_posts()) : $offices->the_post();
            $checked = in_array(get_the_ID(), $selected_offices) ? 'checked="checked"' : '';
            echo '<label><input type="checkbox" name="agent_offices[]" value="' . esc_attr(get_the_ID()) . '" ' . $checked . '> ' . esc_html(get_the_title()) . '</label><br>';
        endwhile;
    } else {
        echo '<p>No offices found.</p>';
    }

    wp_die();
}
add_action('wp_ajax_search_offices', 'rch_ajax_search_offices_function');


/*******************************
 * Save the Selected Offices
 ******************************/
function rch_save_offices_meta_box_data($post_id)
{
    // Check if nonce is set and valid
    if (!isset($_POST['custom_offices_meta_box_nonce']) || !wp_verify_nonce($_POST['custom_offices_meta_box_nonce'], 'custom_save_offices_meta_box_data')) {
        return;
    }

    // Check if the user has permission to save the data
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save the selected offices
    if (isset($_POST['agent_offices'])) {
        $offices = array_map('intval', $_POST['agent_offices']); // Map to ensure IDs are integers
        update_post_meta($post_id, '_rch_agent_offices', $offices);
    } else {
        delete_post_meta($post_id, '_rch_agent_offices'); // If no offices selected, delete the meta
    }
}

add_action('save_post', 'rch_save_offices_meta_box_data');


/*******************************
 * Show Custom Column in Admin In Agents (regions and offices)
 ******************************/
function custom_agents_columns($columns)
{
    $columns['offices'] = __('Offices');
    $columns['regions'] = __('Regions');
    return $columns;
}
add_filter('manage_agents_posts_columns', 'custom_agents_columns');

function custom_agents_custom_column($column, $post_id)
{
    if ($column === 'offices') {
        $offices = get_post_meta($post_id, '_rch_agent_offices', true);
        if ($offices && is_array($offices)) {
            $output = array();
            foreach ($offices as $office_id) {
                $output[] = get_the_title($office_id);
            }
            echo implode(', ', $output);
        } else {
            echo __('No Offices Assigned');
        }
    } elseif ($column === 'regions') {
        $regions = get_post_meta($post_id, '_rch_agent_regions', true);
        if ($regions && is_array($regions)) {
            $output = array();

            foreach ($regions as $region_id) {
                $output[] = get_the_title($region_id); // This gets the title of each associated region.
            }
            echo implode(', ', $output); // Display the regions separated by commas.
        } else {
            echo __('No Regions Assigned');
        }
    }
}
add_action('manage_agents_posts_custom_column', 'custom_agents_custom_column', 10, 2);

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
