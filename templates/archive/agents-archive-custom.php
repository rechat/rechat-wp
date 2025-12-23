<?php
get_header();

// Get the number of posts per page from the settings
$posts_per_page = get_option('_rch_posts_per_page', 12);

$paged = (get_query_var('paged')) ? absint(get_query_var('paged')) : 1;

$args = array(
    'post_type'      => 'agents',
    'posts_per_page' => $posts_per_page,
    'orderby'        => 'title',   // Sort by name (post title)
    'order'          => 'ASC',     // ASC = A → Z
    'paged'          => $paged,
    'meta_query'     => array(
        'relation' => 'OR',
        array(
            'key'     => 'agent_visibility',
            'value'   => 'show',
            'compare' => '='
        ),
        array(
            'key'     => 'agent_visibility',
            'compare' => 'NOT EXISTS' // Include agents where visibility is not set (defaults to show)
        )
    )
);

$query = new WP_Query($args);
?>

<div id="primary" class="content-area rch-primary-content">
    <main id="main" class="site-main rch-agents-rechat content-container site-container">
        <div class="rch-top-filter">
            <!-- AJAX Search Form -->
            <form id="rch-agent-search-form" method="get" action="">
                <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'search.svg'); ?>" alt="Search Icon">
                <input type="text" id="rch-agent-search" name="rch_agent_search" placeholder="Search by Name">
                <div id="rch-agent-search-results" class="rch-dropdown-menu" style="display: none;">
                    <!-- AJAX results will appear here -->
                </div>
            </form>
        </div>
        <ul class="rch-archive-agents">
            <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post();
                    // Get the current post ID
                    $post_id = get_the_ID();
                    $profile_image_url = get_post_meta($post_id, 'profile_image_url', true);
                    $timezone = get_post_meta($post_id, 'timezone', true);
                    $phone_number = get_post_meta($post_id, 'phone_number', true);
                    $designation = get_post_meta($post_id, 'designation', true);
            ?>
                    <li>
                        <div class="rch-image-container">
                            <picture>
                                <a href="<?php the_permalink() ?>">
                                    <div class="rch-loader"></div>
                                    <img src="<?php echo esc_url($profile_image_url); ?>" alt="<?php the_title() ?>" class="rch-profile-image">
                                </a>
                            </picture>
                        </div>
                        <div class="rch-archive-name">
                            <h3>
                                <a href="<?php the_permalink() ?>">
                                    <?php the_title() ?>
                                </a>
                            </h3>
                            <span>
                                <?php echo esc_html($designation); ?>
                            </span>
                        </div>
                        <div class="rch-archive-end-line">
                            <a href="<?php the_permalink() ?>">View Profile</a>
                            <?php if ($phone_number) : ?>
                                <a href="tel:<?php echo esc_attr($phone_number); ?>" class="rch-agent-phone-archive">Contact</a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endwhile;
            else : ?>
                <div class='notfound'><?php esc_html_e('Sorry. There Is Nothing.', 'rechat-plugin'); ?></div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </ul>

        <!-- Add pagination if needed -->
        <div class="rch-pagination-agent">
            <div class="rch-pagination-container">
                <?php
                echo wp_kses_post(paginate_links(array(
                    'total' => $query->max_num_pages,
                    'current' => $paged,
                    'prev_text' => esc_html__('<', 'rechat-plugin'),
                    'next_text' => esc_html__('>', 'rechat-plugin'),
                    'end_size'  => 2,
                    'mid_size'  => 2,
                )));
                ?>
            </div>
        </div>

    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
