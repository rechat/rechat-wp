<?php
function rch_agent_search()
{
    // Verify the nonce
    if (!isset($_GET['nonce']) || !wp_verify_nonce(wp_unslash($_GET['nonce']), 'rch_ajax_front_nonce')) {
        wp_send_json_error('Invalid nonce', 403);
        exit;
    }
    // Get the search query from AJAX
    $search_query = isset($_GET['query']) ? sanitize_text_field(wp_unslash($_GET['query'])) : '';

    // Arguments for WP_Query
    $args = array(
        'post_type' => 'agents',  // Replace with your actual custom post type
        'posts_per_page' => -1,
        's' => $search_query,
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) :
        echo '<ul>';
        while ($query->have_posts()) : $query->the_post();
            $post_id = get_the_ID();
            $profile_image_url = get_post_meta($post_id, 'profile_image_url', true);
            $timezone = get_post_meta($post_id, 'timezone', true);
?>
            <li>
                <a href="<?php the_permalink(); ?>" class="rch-search-result-item">
                    <img src="<?php echo esc_url($profile_image_url); ?>" alt="<?php the_title(); ?>">
                    <span><?php the_title(); ?></span>
                    <span><?php echo esc_html($timezone); ?></span>
                </a>
            </li>
<?php
        endwhile;
        echo '</ul>';
    else :
        echo '<div class="notfound">' . esc_html__('No agents found.', 'rechat-plugin') . '</div>';
    endif;

    wp_reset_postdata();

    wp_die();
}
add_action('wp_ajax_rch_agent_search', 'rch_agent_search');
add_action('wp_ajax_nopriv_rch_agent_search', 'rch_agent_search');
