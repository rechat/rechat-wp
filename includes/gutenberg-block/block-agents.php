<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * this code for agent gutenbberg block
 ******************************/
/*******************************
 * Register agent block in php
 ******************************/
function rch_register_block_assets_agents()
{

    register_block_type('rch-rechat-plugin/agents-block', array(
        'editor_script' => 'rch-gutenberg-js',
        'attributes' => array(
            'postsPerPage' => array(
                'type' => 'number',
                'default' => 5,
            ),
            'regionBgColor' => array(
                'type' => 'string',
                'default' => '#edf1f5',
            ),
            'textColor' => array(
                'type' => 'string',
                'default' => '#000',
            ),
            'filterByRegions' => array(
                'type' => 'string',
                'default' => '',
            ),
            'filterByOffices' => array(
                'type' => 'string',
                'default' => '',
            ),
            'sortBy' => array(
                'type' => 'string',
                'default' => '',
            ),
            'sortOrder' => array(
                'type' => 'string',
                'default' => '',
            ),
        ),
        'render_callback' => 'rch_render_agents_block',
    ));
}
add_action('init', 'rch_register_block_assets_agents');

/*******************************
 * callback function for agents block
 ******************************/
function rch_render_agents_block($attributes)
{
    $posts_per_page = isset($attributes['postsPerPage']) ? $attributes['postsPerPage'] : 5;
    $region_bg_color = isset($attributes['regionBgColor']) ? $attributes['regionBgColor'] : '#edf1f5';
    $text_color = isset($attributes['textColor']) ? $attributes['textColor'] : '#000';
    $filter_by_Regions = isset($attributes['filterByRegions']) ? $attributes['filterByRegions'] : '';
    $filter_by_offices = isset($attributes['filterByOffices']) ? $attributes['filterByOffices'] : '';
    $sort_by = isset($attributes['sortBy']) ? $attributes['sortBy'] : 'date'; // Default to sorting by date
    $sort_order = isset($attributes['sortOrder']) ? $attributes['sortOrder'] : 'desc'; // Default to descending

    // Set up the meta query based on the provided filters
    $meta_query = array('relation' => 'AND');

    if (!empty($filter_by_Regions)) {
        $meta_query[] = array(
            'key'     => '_rch_agent_regions', // Replace with your actual meta key for regions
            'value'   => $filter_by_Regions,
            'compare' => 'LIKE',
        );
    }

    if (!empty($filter_by_offices)) {
        $meta_query[] = array(
            'key'     => '_rch_agent_offices', // Replace with your actual meta key for offices
            'value'   => $filter_by_offices,
            'compare' => 'LIKE',
        );
    }

    $orderby = ($sort_by === 'name') ? 'title' : 'date'; // Determine the order by field
    $order = ($sort_order === 'asc') ? 'ASC' : 'DESC'; // Determine the order direction

    $args = array(
        'post_type'      => 'agents',
        'posts_per_page' => $posts_per_page,
        'paged'          => 1,
        'meta_query'     => $meta_query,
        'orderby'        => $orderby,
        'order'          => $order,
    );

    $query = new WP_Query($args);
    $total_pages = $query->max_num_pages; // Get total number of pages

    ob_start();
?>
    <div id="rch-agents-block"
        data-nonce="<?php echo wp_create_nonce('rch_load_more_agents_nonce'); ?>"
        data-posts-per-page="<?php echo esc_attr($posts_per_page); ?>"
        data-region-bg-color="<?php echo esc_attr($region_bg_color); ?>"
        data-text-color="<?php echo esc_attr($text_color); ?>"
        data-total-pages="<?php echo esc_attr($total_pages); ?>"
        data-filter-region="<?php echo esc_attr($filter_by_Regions); ?>"
        data-filter-office="<?php echo esc_attr($filter_by_offices); ?>"
        data-sort_by="<?php echo esc_attr($sort_by); ?>"
        data-sort_order="<?php echo esc_attr($sort_order); ?>"
        >
        <ul class="rch-archive-agents">

            <?php if ($query->have_posts()) : ?>
                <?php while ($query->have_posts()) : $query->the_post();
                    $post_id = get_the_ID();
                    $profile_image_url = get_post_meta($post_id, 'profile_image_url', true);
                    $timezone = get_post_meta($post_id, 'timezone', true);
                    $phone_number = get_post_meta($post_id, 'phone_number', true);
                    $designation = get_post_meta($post_id, 'designation', true);

                    $theme_template = locate_template('rechat/rch-agents-block-template.php');

                    if ($theme_template) {
                        include $theme_template;
                    } else {
                        include RCH_PLUGIN_DIR . 'templates/template-block/rch-agents-block-template.php';
                    }
                endwhile; ?>
                <?php wp_reset_postdata(); ?>
            <?php else : ?>
                <p>No agents found.</p>
            <?php endif; ?>
        </ul>
        <div id="rch-loading-houses" style="display: none;" class="rch-loader"></div>
        <!-- Pagination controls -->
        <?php if ($total_pages > 1) : ?>
            <div class="rch-pagination">
                <button id="prev-page" data-page="1" disabled>Previous</button>
                <span id="pagination-info">Page 1 of <?php echo esc_attr($total_pages); ?></span>
                <button id="next-page" data-page="1" <?php echo $total_pages <= 1 ? 'disabled' : ''; ?>>Next</button>
            </div>
        <?php endif; ?>
    </div>
<?php
    return ob_get_clean();
}

/*******************************
 * ajax pagination function for agents block
 ******************************/
function rch_load_more_agents()
{
    check_ajax_referer('rch_load_more_agents_nonce', 'nonce');

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $posts_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 5;
    $region_bg_color = isset($_POST['region_bg_color']) ? sanitize_hex_color($_POST['region_bg_color']) : '#edf1f5';
    $text_color = isset($_POST['text_color']) ? sanitize_hex_color($_POST['text_color']) : '#000';
    $filter_by_Regions = isset($_POST['filter_by_Regions']) ? sanitize_text_field($_POST['filter_by_Regions']) : '';
    $filter_by_offices = isset($_POST['filter_by_offices']) ? sanitize_text_field($_POST['filter_by_offices']) : '';
    $sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : '';
    $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : '';

    // Set up the meta query based on the provided filters
    $meta_query = array('relation' => 'AND');

    if (!empty($filter_by_Regions)) {
        $meta_query[] = array(
            'key'     => '_rch_agent_regions', // Your actual meta key for regions
            'value'   => $filter_by_Regions,
            'compare' => 'LIKE', // Use LIKE if storing multiple regions, or '=' if a single value
        );
    }

    if (!empty($filter_by_offices)) {
        $meta_query[] = array(
            'key'     => '_rch_agent_offices', // Your actual meta key for offices
            'value'   => $filter_by_offices,
            'compare' => 'LIKE', // Use LIKE if storing multiple offices, or '=' if a single value
        );
    }
    $orderby = ($sort_by === 'name') ? 'title' : 'date'; // Determine the order by field
    $order = ($sort_order === 'asc') ? 'ASC' : 'DESC'; // Determine the order direction
    $args = array(
        'post_type'      => 'agents',
        'posts_per_page' => $posts_per_page,
        'paged'          => $page,
        'meta_query'     => $meta_query, // Apply the meta query filters
        'orderby'        => $orderby,
        'order'          => $order,
    );

    $query = new WP_Query($args);

    ob_start();
    if ($query->have_posts()) :
        while ($query->have_posts()) : $query->the_post();
            $post_id = get_the_ID();
            $profile_image_url = get_post_meta($post_id, 'profile_image_url', true);
            $timezone = get_post_meta($post_id, 'timezone', true);
            $phone_number = get_post_meta($post_id, 'phone_number', true);
            $theme_template = locate_template('rechat/rch-agents-block-template.php');

            if ($theme_template) {
                // If the template is found in the theme/child theme, load it
                include $theme_template;
            } else {
                // Fall back to the plugin's template
                include RCH_PLUGIN_DIR . 'templates/template-block/rch-agents-block-template.php';
            }
        endwhile;
        wp_reset_postdata();
    else :
        echo '<p>No agents found.</p>';
    endif;

    $html = ob_get_clean();
    wp_send_json_success(array('html' => $html));
}

add_action('wp_ajax_rch_load_more_agents', 'rch_load_more_agents');
add_action('wp_ajax_nopriv_rch_load_more_agents', 'rch_load_more_agents');
