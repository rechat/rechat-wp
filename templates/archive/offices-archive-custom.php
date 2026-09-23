<?php
get_header();

// Get the number of posts per page from the settings
$posts_per_page = get_option('_rch_posts_per_page', 12);

$paged = (get_query_var('paged')) ? absint(get_query_var('paged')) : 1;

$args = array(
    'post_type'      => 'offices', // Ensure this is your custom post type
    'posts_per_page' => $posts_per_page,
    // Order by menu_order, then a UNIQUE tiebreaker (title, then ID). Synced
    // records share menu_order = 0, so menu_order alone is non-deterministic
    // across LIMIT/OFFSET pages (rows repeat on some pages, others never
    // appear). The tiebreaker makes pagination return every record once.
    'orderby'        => array('menu_order' => 'ASC', 'title' => 'ASC', 'ID' => 'ASC'),
    'paged'          => $paged
);

$query = new WP_Query($args);
?>

<div id="primary" class="content-area rch-primary-content">
    <main id="main" class="site-main rch-agents-rechat content-container site-container">
        <?php the_archive_title('<h1>', '</h1>'); ?>
        <ul class="rch-archive-regions-offices">
            <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post(); ?>
                    <li>
                        <a href="<?php the_permalink(); ?>">
                            <?php the_title('<h2>', '</h2>'); ?>
                        </a>
                    </li>
                <?php endwhile; ?>
            <?php else : ?>
                <div class='notfound'><?php esc_html_e('Sorry. There Is Nothing.', 'rechat-plugin'); ?></div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </ul>

        <!-- Add pagination if needed -->
        <div class="rch-pagination">
            <div class="rch-pagination-container">
                <?php
                // Escape pagination output
                echo wp_kses_post(paginate_links(array(
                    'total' => $query->max_num_pages,
                    'current' => $paged,
                    'prev_text' => __('&lt;', 'rechat-plugin'),
                    'next_text' => __('&gt;', 'rechat-plugin'),
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
