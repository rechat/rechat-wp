<?php
/**
 * Agent-Office Association Metabox
 * 
 * Handles linking agents to offices with searchable checkboxes
 * and displays office associations in admin columns
 *
 * @package RechatPlugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*******************************
 * Add offices meta box to agents post type
 ******************************/
function rch_add_agent_offices_meta_box()
{
    add_meta_box(
        'rch_agents_offices_meta_box',
        __('Rechat Offices', 'rechat-plugin'),
        'rch_agent_offices_meta_box_callback',
        'agents',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'rch_add_agent_offices_meta_box');


/*******************************
 * Display offices meta box with searchable checkboxes
 ******************************/
function rch_agent_offices_meta_box_callback($post)
{
    // Add nonce for security
    wp_nonce_field('rch_save_agent_offices', 'rch_agent_offices_nonce');

    // Get selected offices
    $selected_offices = get_post_meta($post->ID, '_rch_agent_offices', true);
    $selected_offices = is_array($selected_offices) ? $selected_offices : [];

    // Add inline styles
    rch_add_agent_offices_metabox_styles();

    // Render search input
    rch_render_office_search_input();

    // Render office checkboxes
    rch_render_office_checkboxes($selected_offices);
}

/*******************************
 * Add inline styles for office metabox
 ******************************/
function rch_add_agent_offices_metabox_styles()
{
    ?>
    <style>
        #rch_agents_offices_meta_box .rch-office-search {
            box-sizing: border-box !important;
            width: 100% !important;
            padding: 5px 8px !important;
            margin-bottom: 10px !important;
            border: 1px solid #8c8f94 !important;
            border-radius: 4px !important;
            font-size: 13px !important;
            background-color: #fff !important;
        }
        
        #rch_agents_offices_meta_box .rch-office-search:focus {
            border-color: #2271b1 !important;
            box-shadow: 0 0 0 1px #2271b1 !important;
            outline: 2px solid transparent !important;
        }
        
        #rch_agents_offices_meta_box .rch-office-results {
            max-height: 200px !important;
            overflow-y: auto !important;
            border: 1px solid #dcdcde !important;
            padding: 8px !important;
            background-color: #fff !important;
        }
        
        #rch_agents_offices_meta_box .rch-office-results label {
            display: block !important;
            padding: 4px 0 !important;
            margin: 0 !important;
            cursor: pointer !important;
            font-size: 13px !important;
        }
        
        #rch_agents_offices_meta_box .rch-office-results label:hover {
            background-color: #f6f7f7 !important;
        }
        
        #rch_agents_offices_meta_box .rch-office-results input[type="checkbox"] {
            margin-right: 5px !important;
        }
        
        #rch_agents_offices_meta_box .rch-no-offices {
            color: #646970 !important;
            font-style: italic !important;
            padding: 10px !important;
        }
    </style>
    <?php
}

/*******************************
 * Render office search input
 ******************************/
function rch_render_office_search_input()
{
    ?>
    <input 
        type="text" 
        id="rch-office-search" 
        class="rch-office-search" 
        placeholder="<?php esc_attr_e('Search for offices...', 'rechat-plugin'); ?>"
    />
    <?php
}

/*******************************
 * Render office checkboxes
 ******************************/
function rch_render_office_checkboxes($selected_offices)
{
    echo '<div id="rch-office-results" class="rch-office-results">';

    $offices = rch_get_all_offices();

    if (!empty($offices)) {
        foreach ($offices as $office) {
            $checked = in_array($office->ID, $selected_offices, true) ? 'checked="checked"' : '';
            ?>
            <label>
                <input 
                    type="checkbox" 
                    name="rch_agent_offices[]" 
                    value="<?php echo esc_attr($office->ID); ?>" 
                    <?php echo $checked; ?>
                />
                <?php echo esc_html($office->post_title); ?>
            </label>
            <?php
        }
    } else {
        echo '<p class="rch-no-offices">' . esc_html__('No offices available.', 'rechat-plugin') . '</p>';
    }

    echo '</div>';
}

/*******************************
 * Get all published offices
 ******************************/
function rch_get_all_offices()
{
    $args = [
        'post_type' => 'offices',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
    ];

    return get_posts($args);
}



/*******************************
 * Enqueue AJAX search script for offices
 ******************************/
function rch_agent_offices_ajax_script()
{
    global $post_type;
    
    if ($post_type !== 'agents') {
        return;
    }
    ?>
    <script type="text/javascript">
    (function($) {
        'use strict';
        
        $(document).ready(function() {
            var $searchInput = $('#rch-office-search');
            var $resultsContainer = $('#rch-office-results');
            
            // Handle search input
            $searchInput.on('input', function() {
                var searchQuery = $(this).val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rch_search_offices',
                        search: searchQuery,
                        nonce: '<?php echo wp_create_nonce('rch_search_offices_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $resultsContainer.html(response.data);
                        }
                    },
                    error: function() {
                        $resultsContainer.html('<p class="rch-no-offices"><?php esc_html_e('Error loading offices.', 'rechat-plugin'); ?></p>');
                    }
                });
            });
        });
    })(jQuery);
    </script>
    <?php
}
add_action('admin_footer', 'rch_agent_offices_ajax_script');


/*******************************
 * Handle AJAX office search
 ******************************/
function rch_ajax_search_offices()
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rch_search_offices_nonce')) {
        wp_send_json_error(__('Security check failed.', 'rechat-plugin'));
    }

    $search_query = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    $args = [
        'post_type' => 'offices',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        's' => $search_query,
        'orderby' => 'title',
        'order' => 'ASC',
    ];

    $offices = new WP_Query($args);

    ob_start();

    if ($offices->have_posts()) {
        while ($offices->have_posts()) {
            $offices->the_post();
            ?>
            <label>
                <input 
                    type="checkbox" 
                    name="rch_agent_offices[]" 
                    value="<?php echo esc_attr(get_the_ID()); ?>"
                />
                <?php echo esc_html(get_the_title()); ?>
            </label>
            <?php
        }
        wp_reset_postdata();
    } else {
        echo '<p class="rch-no-offices">' . esc_html__('No offices found.', 'rechat-plugin') . '</p>';
    }

    $html = ob_get_clean();
    wp_send_json_success($html);
}
add_action('wp_ajax_rch_search_offices', 'rch_ajax_search_offices');



/*******************************
 * Save selected offices for agent
 ******************************/
function rch_save_agent_offices_meta($post_id)
{
    // Verify nonce
    if (!isset($_POST['rch_agent_offices_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['rch_agent_offices_nonce'], 'rch_save_agent_offices')) {
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

    // Save or delete offices
    if (isset($_POST['rch_agent_offices']) && is_array($_POST['rch_agent_offices'])) {
        $offices = array_map('intval', $_POST['rch_agent_offices']);
        update_post_meta($post_id, '_rch_agent_offices', $offices);
    } else {
        delete_post_meta($post_id, '_rch_agent_offices');
    }
}
add_action('save_post_agents', 'rch_save_agent_offices_meta');



/*******************************
 * Add offices and regions columns to agents list
 ******************************/
function rch_add_agent_association_columns($columns)
{
    // Insert after title
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'title') {
            $new_columns['rch_offices'] = __('Offices', 'rechat-plugin');
            $new_columns['rch_regions'] = __('Regions', 'rechat-plugin');
        }
    }
    
    return $new_columns;
}
add_filter('manage_agents_posts_columns', 'rch_add_agent_association_columns');

/*******************************
 * Display offices and regions column data
 ******************************/
function rch_show_agent_association_columns($column, $post_id)
{
    if ($column === 'rch_offices') {
        rch_display_agent_offices_column($post_id);
    } elseif ($column === 'rch_regions') {
        rch_display_agent_regions_column($post_id);
    }
}
add_action('manage_agents_posts_custom_column', 'rch_show_agent_association_columns', 10, 2);

/*******************************
 * Display offices column data
 ******************************/
function rch_display_agent_offices_column($post_id)
{
    $offices = get_post_meta($post_id, '_rch_agent_offices', true);
    
    if (!empty($offices) && is_array($offices)) {
        $office_titles = [];
        foreach ($offices as $office_id) {
            $title = get_the_title($office_id);
            if ($title) {
                $office_titles[] = $title;
            }
        }
        
        if (!empty($office_titles)) {
            echo esc_html(implode(', ', $office_titles));
        } else {
            echo '<em>' . esc_html__('No Offices Assigned', 'rechat-plugin') . '</em>';
        }
    } else {
        echo '<em>' . esc_html__('No Offices Assigned', 'rechat-plugin') . '</em>';
    }
}

/*******************************
 * Display regions column data
 ******************************/
function rch_display_agent_regions_column($post_id)
{
    $regions = get_post_meta($post_id, '_rch_agent_regions', true);
    
    if (!empty($regions) && is_array($regions)) {
        $region_titles = [];
        foreach ($regions as $region_id) {
            $title = get_the_title($region_id);
            if ($title) {
                $region_titles[] = $title;
            }
        }
        
        if (!empty($region_titles)) {
            echo esc_html(implode(', ', $region_titles));
        } else {
            echo '<em>' . esc_html__('No Regions Assigned', 'rechat-plugin') . '</em>';
        }
    } else {
        echo '<em>' . esc_html__('No Regions Assigned', 'rechat-plugin') . '</em>';
    }
}


/*******************************
 * Example: Display offices on front-end (helper function)
 * Uncomment to use in template files (e.g., single-agents.php)
 ******************************/
/*
function rch_display_agent_offices($post_id) {
    $offices = get_post_meta($post_id, '_rch_agent_offices', true);

    if (!empty($offices) && is_array($offices)) {
        echo '<h2>' . esc_html__('Associated Offices', 'rechat-plugin') . '</h2>';
        echo '<ul>';
        foreach ($offices as $office_id) {
            $title = get_the_title($office_id);
            $permalink = get_permalink($office_id);
            if ($title && $permalink) {
                echo '<li><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></li>';
            }
        }
        echo '</ul>';
    }
}

// Usage in template file:
if (is_singular('agents')) {
    rch_display_agent_offices(get_the_ID());
}
*/

