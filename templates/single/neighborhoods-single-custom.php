<?php get_header() ?>
<div id="primary" class="content-area rch-primary-content">
    <main id="main" class="site-main content-container site-container">
        <div class="container">
            <div>
                <article class="singlePost--content text">
                    <div class="neighborhoodSinglePost">
                        <?php the_content() ?>
                    </div>
                </article>
            </div>
        </div>
        <section class="related-neighbour">
            <div>
                <h2 class="lp-h2">Explore Similar Neighborhoods</h2>
                <ul class="rch-neighborhoods-archive">
                    <?php echo get_related_neighborhoods(); ?>
                </ul>
            </div>
        </section>
    </main><!-- #main -->
</div><!-- #primary -->
<?php get_footer() ?>