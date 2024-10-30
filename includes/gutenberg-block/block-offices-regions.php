<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * this code for offices and regions gutenberg block
 ******************************/
/*******************************
 * Register block assets for offices or any other block type
 ******************************/
// 
function rch_register_block_assets($block_name, $render_callback)
{
    wp_register_script('rch-gutenberg-js', RCH_PLUGIN_URL . 'build/index.js', array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'));

    register_block_type($block_name, array(
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
        ),
        'render_callback' => $render_callback,
    ));
}
add_action('init', function () {
    rch_register_block_assets('rch-rechat-plugin/regions-block', 'rch_render_regions_block');
    rch_register_block_assets('rch-rechat-plugin/offices-block', 'rch_render_offices_block');

    // Add more blocks as needed
});

/*******************************
 * callback function for rechat region and office block
 ******************************/
function rch_render_block($attributes, $post_type, $meta_key = '', $meta_value = '')
{
    $posts_per_page = isset($attributes['postsPerPage']) ? $attributes['postsPerPage'] : 5;
    $regionBgColor = isset($attributes['regionBgColor']) ? $attributes['regionBgColor'] : '#edf1f5';
    $textColor = isset($attributes['textColor']) ? $attributes['textColor'] : '#000';
    $filterByRegions = isset($attributes['filterByRegions']) ? $attributes['filterByRegions'] : '';
    // Ensure $filterByRegions is treated correctly based on whether it's an array or string
    if (is_array($filterByRegions)) {
        // No need to add quotes, just search for each value directly
        $serialized_values = array_map(function ($value) {
            return $value; // Return the value directly for LIKE comparison
        }, $filterByRegions);
    } else {
        // For a single value, just use it as is
        $serialized_values = $filterByRegions;
    }
    // Meta query setup (for filtering by region or any other key)
    $meta_query = !empty($meta_key) ? array(
        array(
            'key'     => $meta_key,
            'value'   => $serialized_values,
            'compare' => 'LIKE',
        )
    ) : '';

    // Build WP Query
    $args = array(
        'post_type'      => $post_type,
        'posts_per_page' => $posts_per_page,
        'paged'          => 1,
        'meta_query'     => $meta_query,
    );
    $query = new WP_Query($args);
    $total_pages = $query->max_num_pages;

    // Output the block
    ob_start();
?>
    <div id="rch-block-<?php echo esc_attr($post_type); ?>"
        data-nonce="<?php echo wp_create_nonce('rch_load_more_' . $post_type . '_nonce'); ?>"
        data-posts-per-page="<?php echo esc_attr($posts_per_page); ?>"
        data-region-bg-color="<?php echo esc_attr($regionBgColor); ?>"
        data-text-color="<?php echo esc_attr($textColor); ?>"
        data-total-pages="<?php echo esc_attr($total_pages); ?>"
        class="rch-rechat-clock-group"
        data-meta-key="<?php echo esc_attr($meta_key); ?>"
        data-meta-value="<?php echo esc_attr($serialized_values); ?>"
        data-nonce="<?php echo wp_create_nonce('rch_load_more_offices_nonce'); ?>">
        <ul class="rch-rechat-block">
            <?php if ($query->have_posts()) : ?>
                <?php while ($query->have_posts()) : $query->the_post();
                    // Load different templates based on post type
                    if ($post_type === 'regions') {
                        $theme_template = locate_template('rechat/rch-regions-block-template.php');
                        if ($theme_template) {
                            include $theme_template; // Theme/child theme template
                        } else {
                            include RCH_PLUGIN_DIR . 'templates/template-block/rch-regions-block-template.php'; // Plugin fallback
                        }
                    } elseif ($post_type === 'offices') {
                        $theme_template = locate_template('rechat/rch-offices-block-template.php');
                        if ($theme_template) {
                            include $theme_template; // Theme/child theme template
                        } else {
                            include RCH_PLUGIN_DIR . 'templates/template-block/rch-offices-block-template.php'; // Plugin fallback
                        }
                    }
                endwhile;
                wp_reset_postdata();
            else : ?>
                <p>No posts found.</p>
            <?php endif; ?>
        </ul>

        <div id="rch-loading-<?php echo esc_attr($post_type); ?>" style="display: none;" class="rch-loader"></div>

        <?php if ($total_pages > 1) : ?>
            <div class="rch-pagination">
                <button id="prev-page" class="rch-pagination-button" data-post-type="<?php echo esc_attr($post_type); ?>" data-page="1" disabled>Previous</button>
                <span id="pagination-info">Page 1 of <?php echo esc_attr($total_pages); ?></span>
                <button id="next-page" class="rch-pagination-button" data-post-type="<?php echo esc_attr($post_type); ?>" data-page="1" <?php echo $total_pages <= 1 ? 'disabled' : ''; ?>>Next</button>
            </div>

        <?php endif; ?>
    </div>
<?php
    return ob_get_clean();
}

// Render callback for regions
function rch_render_regions_block($attributes)
{
    return rch_render_block($attributes, 'regions');
}
// Render callback for offices
function rch_render_offices_block($attributes)
{
    return rch_render_block($attributes, 'offices', 'rch_associated_regions_to_office',);
}

/*******************************
 *  function for pagination ajax for rechat region and office block
 ******************************/
function rch_load_more_posts()
{
    $post_type = sanitize_text_field($_POST['postType']);
    check_ajax_referer('rch_load_more_' . $post_type . '_nonce', 'nonce');
    $post_type = sanitize_text_field($_POST['postType']);
    $posts_per_page = isset($_POST['postsPerPage']) ? intval($_POST['postsPerPage']) : 5;
    $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $regionBgColor = sanitize_text_field($_POST['regionBgColor']);
    $textColor = sanitize_text_field($_POST['textColor']);
    // Get the meta key and value from the POST data if provided
    $meta_key = isset($_POST['meta_key']) ? sanitize_text_field($_POST['meta_key']) : '';
    $meta_value = isset($_POST['meta_value']) ? sanitize_text_field($_POST['meta_value']) : '';

    // Prepare the meta query if a meta key is provided
    $meta_query = [];
    if (!empty($meta_key) && !empty($meta_value)) {
        $meta_query[] = array(
            'key'     => $meta_key,
            'value'   => $meta_value,
            'compare' => 'LIKE', // Use 'LIKE' for partial matching; change as needed
        );
    }

    $args = array(
        'post_type' => $post_type,
        'posts_per_page' => $posts_per_page,
        'paged' => $paged,
        'meta_query' => !empty($meta_query) ? $meta_query : null, // Include the meta query if it exists
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        ob_start();

        while ($query->have_posts()) : $query->the_post();
            $theme_template = locate_template('rechat/rch-regions-block-template.php');

            if ($theme_template) {
                // If the template is found in the theme/child theme, load it
                include $theme_template;
            } else {
                // Fall back to the plugin's template
                include RCH_PLUGIN_DIR . 'templates/template-block/rch-regions-block-template.php';
            }
        endwhile;

        wp_reset_postdata();
        $output = ob_get_clean();

        wp_send_json_success(array(
            'html' => $output,
            'current_page' => $paged,
            'total_pages' => $query->max_num_pages,
        ));
    } else {
        wp_send_json_error('No posts found.');
    }

    wp_die();
}
add_action('wp_ajax_rch_load_more_posts', 'rch_load_more_posts');
add_action('wp_ajax_nopriv_rch_load_more_posts', 'rch_load_more_posts'); // This is for non-logged-in users
