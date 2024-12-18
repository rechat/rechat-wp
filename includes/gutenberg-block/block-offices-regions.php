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
    wp_register_script(
        'rch-gutenberg-js',
        RCH_PLUGIN_URL . 'build/index.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        '1.0.0',
        true // 
    );
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
        $serialized_values = array_map(function ($value) {
            return $value;
        }, $filterByRegions);
    } else {
        $serialized_values = $filterByRegions;
    }

    // Initialize the meta query array
    $meta_query = [];

    // Add a meta query condition only if filterByRegions is not empty
    if (!empty($filterByRegions) && !empty($meta_key)) {
        $meta_query[] = array(
            'key'     => $meta_key,
            'value'   => $serialized_values,
            'compare' => 'LIKE',
        );
    }

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
        data-nonce="<?php echo esc_attr(wp_create_nonce('rch_load_more_' . $post_type . '_nonce')); ?>"
        data-posts-per-page="<?php echo esc_attr($posts_per_page); ?>"
        data-region-bg-color="<?php echo esc_attr($regionBgColor); ?>"
        data-text-color="<?php echo esc_attr($textColor); ?>"
        data-total-pages="<?php echo esc_attr($total_pages); ?>"
        class="rch-rechat-clock-group"
        data-meta-key="<?php echo esc_attr($meta_key); ?>"
        data-meta-value="<?php echo esc_attr($serialized_values); ?>"
        data-nonce="<?php echo esc_attr(wp_create_nonce('rch_load_more_offices_nonce')); ?>">
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
    // Validate and sanitize postType
    $post_type = '';
    if (isset($_POST['postType'])) {
        $post_type = sanitize_text_field(wp_unslash($_POST['postType']));
    }

    // Validate nonce
    check_ajax_referer('rch_load_more_' . $post_type . '_nonce', 'nonce');

    // Validate and sanitize postsPerPage
    $posts_per_page = isset($_POST['postsPerPage']) ? intval(wp_unslash($_POST['postsPerPage'])) : 5;

    // Validate and sanitize page
    $paged = isset($_POST['page']) ? intval(wp_unslash($_POST['page'])) : 1;

    // Validate and sanitize regionBgColor
    $regionBgColor = '';
    if (isset($_POST['regionBgColor'])) {
        $regionBgColor = sanitize_text_field(wp_unslash($_POST['regionBgColor']));
    }

    // Validate and sanitize textColor
    $textColor = '';
    if (isset($_POST['textColor'])) {
        $textColor = sanitize_text_field(wp_unslash($_POST['textColor']));
    }

    // Validate and sanitize meta_key
    $meta_key = '';
    if (isset($_POST['meta_key'])) {
        $meta_key = sanitize_text_field(wp_unslash($_POST['meta_key']));
    }

    // Validate and sanitize meta_value
    $meta_value = '';
    if (isset($_POST['meta_value'])) {
        $meta_value = sanitize_text_field(wp_unslash($_POST['meta_value']));
    }

    // Prepare the meta query if a meta key is provided
    $meta_query = [];
    if (!empty($meta_key) && !empty($meta_value)) {
        $meta_query[] = array(
            'key'     => $meta_key,
            'value'   => $meta_value,
            'compare' => 'LIKE',
        );
    }

    $args = array(
        'post_type'      => $post_type,
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'meta_query'     => !empty($meta_query) ? $meta_query : null,
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        ob_start();

        while ($query->have_posts()) : $query->the_post();
            $theme_template = locate_template('rechat/rch-regions-block-template.php');

            if ($theme_template) {
                include $theme_template;
            } else {
                include RCH_PLUGIN_DIR . 'templates/template-block/rch-regions-block-template.php';
            }
        endwhile;

        wp_reset_postdata();
        $output = ob_get_clean();

        wp_send_json_success(array(
            'html'         => $output,
            'current_page' => $paged,
            'total_pages'  => $query->max_num_pages,
        ));
    } else {
        wp_send_json_error('No posts found.');
    }

    wp_die();
}

add_action('wp_ajax_rch_load_more_posts', 'rch_load_more_posts');
add_action('wp_ajax_nopriv_rch_load_more_posts', 'rch_load_more_posts'); // This is for non-logged-in users
