<?php
get_header();

// Get the number of posts per page from the settings
$posts_per_page = get_option('_rch_posts_per_page', 12);

$paged = (get_query_var('paged')) ? absint(get_query_var('paged')) : 1;

$args = array(
    'post_type'      => 'offices', // Ensure this is your custom post type
    'posts_per_page' => $posts_per_page,
    'orderby'        => 'menu_order', // Order by the custom order
    'order'          => 'ASC', // Or 'DESC' depending on your needs
    'paged'          => $paged
);

$query = new WP_Query($args);
?>

<div id="primary" class="content-area rch-primary-content">
    <main id="main" class="site-main rch-agents-rechat content-container site-container">
        <?php the_archive_title('<h1>', '</h1>') ?>
        <ul class="rch-archive-regions-offices">
            <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post();
            ?>
                    <li>
                        <a href="<?php the_permalink() ?>">
                            <?php the_title('<h2>', '</h2>') ?>
                        </a>

                    </li>
                <?php endwhile;
            else : ?>
                <div class='notfound'><?php _e('Sorry. There Is Nothing.'); ?></div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </ul>

        <!-- Add pagination if needed -->
        <div class="rch-pagination">
            <div class="rch-pagination-container">
                <?php
                echo paginate_links(array(
                    'total' => $query->max_num_pages,
                    'current' => $paged,
                    'prev_text' => __('<'),
                    'next_text' => __('>'),
                    'end_size'  => 2,
                    'mid_size'  => 2,
                ));
                ?>
            </div>
        </div>

    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
