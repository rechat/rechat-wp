<?php
get_header();

// Get the number of posts per page from the settings
$posts_per_page = get_option('_rch_posts_per_page', 12);

$paged = (get_query_var('paged')) ? absint(get_query_var('paged')) : 1;

$args = array(
    'post_type'      => 'neighborhoods', // Ensure this is your custom post type
    'posts_per_page' => $posts_per_page,
    'orderby'        => 'menu_order', // Order by the custom order
    'order'          => 'ASC', // Or 'DESC' depending on your needs
    'paged'          => $paged
);

$query = new WP_Query($args);
?>

<div id="primary" class="content-area rch-primary-content">
    <main id="main" class="site-main rch-agents-rechat content-container site-container">
        <ul class="rch-neighborhoods-archive">
            <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post();
            ?>
                    <li class="rch-items-neighbor item">
                        <a href="<?php the_permalink(); ?>" class="item-wrapper">
                            <div class="image-holder">
                                <?php the_post_thumbnail('full'); // Display the featured image 
                                ?>
                            </div>
                            <div class="overlay"></div>
                            <div class="content-container">
                                <h3 class="lp-h3 neighborhood-name"><?php the_title(); ?></h3>
                                <div class="button-wrapper">
                                    <span class="btn">Learn More</span>
                                </div>
                            </div>
                        </a>
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
