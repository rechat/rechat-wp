<?php
/**
 * Agent-Region Association Metabox
 * 
 * Handles linking agents to regions with searchable checkboxes
 * Note: Region columns are displayed via show-offices-in-agents.php
 *
 * @package RechatPlugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*******************************
 * Add regions meta box to agents post type
 ******************************/
function rch_add_agent_regions_meta_box()
{
    add_meta_box(
        'rch_agents_regions_meta_box',
        __('Rechat Regions', 'rechat-plugin'),
        'rch_agent_regions_meta_box_callback',
        'agents',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'rch_add_agent_regions_meta_box');


/*******************************
 * Display regions meta box with searchable checkboxes
 ******************************/
function rch_agent_regions_meta_box_callback($post)
{
    // Add nonce for security
    wp_nonce_field('rch_save_agent_regions', 'rch_agent_regions_nonce');

    // Get selected regions
    $selected_regions = get_post_meta($post->ID, '_rch_agent_regions', true);
    $selected_regions = is_array($selected_regions) ? $selected_regions : [];

    // Add inline styles
    rch_add_agent_regions_metabox_styles();

    // Render search input
    rch_render_region_search_input();

    // Render region checkboxes
    rch_render_region_checkboxes($selected_regions);
}

/*******************************
 * Add inline styles for region metabox
 ******************************/
function rch_add_agent_regions_metabox_styles()
{
    ?>
    <style>
        #rch_agents_regions_meta_box .rch-region-search {
            box-sizing: border-box !important;
            width: 100% !important;
            padding: 5px 8px !important;
            margin-bottom: 10px !important;
            border: 1px solid #8c8f94 !important;
            border-radius: 4px !important;
            font-size: 13px !important;
            background-color: #fff !important;
        }
        
        #rch_agents_regions_meta_box .rch-region-search:focus {
            border-color: #2271b1 !important;
            box-shadow: 0 0 0 1px #2271b1 !important;
            outline: 2px solid transparent !important;
        }
        
        #rch_agents_regions_meta_box .rch-region-results {
            max-height: 200px !important;
            overflow-y: auto !important;
            border: 1px solid #dcdcde !important;
            padding: 8px !important;
            background-color: #fff !important;
        }
        
        #rch_agents_regions_meta_box .rch-region-results label {
            display: block !important;
            padding: 4px 0 !important;
            margin: 0 !important;
            cursor: pointer !important;
            font-size: 13px !important;
        }
        
        #rch_agents_regions_meta_box .rch-region-results label:hover {
            background-color: #f6f7f7 !important;
        }
        
        #rch_agents_regions_meta_box .rch-region-results input[type="checkbox"] {
            margin-right: 5px !important;
        }
        
        #rch_agents_regions_meta_box .rch-no-regions {
            color: #646970 !important;
            font-style: italic !important;
            padding: 10px !important;
        }
    </style>
    <?php
}

/*******************************
 * Render region search input
 ******************************/
function rch_render_region_search_input()
{
    ?>
    <input 
        type="text" 
        id="rch-region-search" 
        class="rch-region-search" 
        placeholder="<?php esc_attr_e('Search for regions...', 'rechat-plugin'); ?>"
    />
    <?php
}

/*******************************
 * Render region checkboxes
 ******************************/
function rch_render_region_checkboxes($selected_regions)
{
    echo '<div id="rch-region-results" class="rch-region-results">';

    $regions = rch_get_all_regions();

    if (!empty($regions)) {
        foreach ($regions as $region) {
            $checked = in_array($region->ID, $selected_regions, true) ? 'checked="checked"' : '';
            ?>
            <label>
                <input 
                    type="checkbox" 
                    name="rch_agent_regions[]" 
                    value="<?php echo esc_attr($region->ID); ?>" 
                    <?php echo $checked; ?>
                />
                <?php echo esc_html($region->post_title); ?>
            </label>
            <?php
        }
    } else {
        echo '<p class="rch-no-regions">' . esc_html__('No regions available.', 'rechat-plugin') . '</p>';
    }

    echo '</div>';
}

/*******************************
 * Get all published regions
 ******************************/
function rch_get_all_regions()
{
    $args = [
        'post_type' => 'regions',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
    ];

    return get_posts($args);
}


/*******************************
 * Enqueue AJAX search script for regions
 ******************************/
function rch_agent_regions_ajax_script()
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
            var $searchInput = $('#rch-region-search');
            var $resultsContainer = $('#rch-region-results');
            
            // Handle search input
            $searchInput.on('input', function() {
                var searchQuery = $(this).val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rch_search_regions',
                        search: searchQuery,
                        nonce: '<?php echo wp_create_nonce('rch_search_regions_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $resultsContainer.html(response.data);
                        }
                    },
                    error: function() {
                        $resultsContainer.html('<p class="rch-no-regions"><?php esc_html_e('Error loading regions.', 'rechat-plugin'); ?></p>');
                    }
                });
            });
        });
    })(jQuery);
    </script>
    <?php
}
add_action('admin_footer', 'rch_agent_regions_ajax_script');

/*******************************
 * Handle AJAX region search
 ******************************/
function rch_ajax_search_regions()
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rch_search_regions_nonce')) {
        wp_send_json_error(__('Security check failed.', 'rechat-plugin'));
    }

    $search_query = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    $args = [
        'post_type' => 'regions',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        's' => $search_query,
        'orderby' => 'title',
        'order' => 'ASC',
    ];

    $regions = new WP_Query($args);

    ob_start();

    if ($regions->have_posts()) {
        while ($regions->have_posts()) {
            $regions->the_post();
            ?>
            <label>
                <input 
                    type="checkbox" 
                    name="rch_agent_regions[]" 
                    value="<?php echo esc_attr(get_the_ID()); ?>"
                />
                <?php echo esc_html(get_the_title()); ?>
            </label>
            <?php
        }
        wp_reset_postdata();
    } else {
        echo '<p class="rch-no-regions">' . esc_html__('No regions found.', 'rechat-plugin') . '</p>';
    }

    $html = ob_get_clean();
    wp_send_json_success($html);
}
add_action('wp_ajax_rch_search_regions', 'rch_ajax_search_regions');


/*******************************
 * Save selected regions for agent
 ******************************/
function rch_save_agent_regions_meta($post_id)
{
    // Verify nonce
    if (!isset($_POST['rch_agent_regions_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['rch_agent_regions_nonce'], 'rch_save_agent_regions')) {
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

    // Save or delete regions
    if (isset($_POST['rch_agent_regions']) && is_array($_POST['rch_agent_regions'])) {
        $regions = array_map('intval', $_POST['rch_agent_regions']);
        update_post_meta($post_id, '_rch_agent_regions', $regions);
    } else {
        delete_post_meta($post_id, '_rch_agent_regions');
    }
}
add_action('save_post_agents', 'rch_save_agent_regions_meta');

/*******************************
 * Example: Display regions on front-end (helper function)
 * Uncomment to use in template files (e.g., single-agents.php)
 ******************************/
/*
function rch_display_agent_regions($post_id) {
    $regions = get_post_meta($post_id, '_rch_agent_regions', true);

    if (!empty($regions) && is_array($regions)) {
        echo '<h2>' . esc_html__('Associated Regions', 'rechat-plugin') . '</h2>';
        echo '<ul>';
        foreach ($regions as $region_id) {
            $title = get_the_title($region_id);
            $permalink = get_permalink($region_id);
            if ($title && $permalink) {
                echo '<li><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></li>';
            }
        }
        echo '</ul>';
    }
}

// Usage in template file:
if (is_singular('agents')) {
    rch_display_agent_regions(get_the_ID());
}
*/
