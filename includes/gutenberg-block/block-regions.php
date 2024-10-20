<?php
function register_regions_block() {
    // Register block script
    wp_register_script(
        'regions-block-js',
        RCH_PLUGIN_URL  . 'assets/js/regions-block.js', // Path to your block JS file
        array( 'wp-blocks', 'wp-editor', 'wp-components', 'wp-element' ),
        true
    );

    // Register block type
    register_block_type('rch-plugin/regions-block', array(
        'editor_script' => 'regions-block-js',
        'render_callback' => 'render_regions_block',
        'attributes' => array(
            'postsToShow' => array(
                'type' => 'number',
                'default' => 5,
            ),
            'postsPerPage' => array(
                'type' => 'number',
                'default' => 5,
            ),
        ),
    ));
}
add_action('init', 'register_regions_block');

function render_regions_block($attributes)
{
    $posts_per_page = isset($attributes['postsPerPage']) ? $attributes['postsPerPage'] : 5;

    $args = array(
        'post_type' => 'regions',
        'posts_per_page' => $posts_per_page,
    );

    $query = new WP_Query($args);
    $output = '<div class="regions-block">';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $output .= '<div class="region-post">';
            $output .= '<h2>' . get_the_title() . '</h2>';
            $output .= '<div>' . get_the_content() . '</div>';
            $output .= '</div>';
        }
        wp_reset_postdata();
    } else {
        $output .= '<p>No regions found.</p>';
    }

    $output .= '</div>';
    return $output;
}


function enqueue_block_assets()
{
    wp_enqueue_script(
        'regions-block',
        RCH_PLUGIN_URL  . 'assets/js/regions-block.js',
        array('wp-blocks', 'wp-editor', 'wp-components', 'wp-element'),
        true
    );
}
add_action('enqueue_block_editor_assets', 'enqueue_block_assets');
