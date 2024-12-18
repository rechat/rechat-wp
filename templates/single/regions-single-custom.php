<?php
get_header();
?>

<div id="primary" class="content-area rch-primary-content">
    <main id="main" class="site-main content-container site-container">

        <?php
        while (have_posts()) : the_post();
            // Escape the title
            the_title('<h1>', '</h1>');
            // Escape the content
            echo '<div class="rch-region-main-content">' . wp_kses_post(get_the_content()) . '</div>';

            // Get the current region ID
            $region_id = get_the_ID();

            // Query for agents assigned to this region
            $args = array(
                'post_type' => 'agents',
                'meta_query' => array(
                    array(
                        'key' => '_rch_agent_regions',
                        'value' => sprintf('i:%d;', $region_id), // Searching for the specific region ID in the serialized array
                        'compare' => 'LIKE',
                    ),
                ),
            );
            $agents = get_posts($args);

            if ($agents) {
                echo '<h2>Assigned Agents:</h2>';
                echo '<div class="rch-agents-rechat"><ul class="rch-archive-agents">';
                foreach ($agents as $agent) {
                    // Get the agent's meta data
                    $profile_image_url = get_post_meta($agent->ID, 'profile_image_url', true);
                    $timezone = get_post_meta($agent->ID, 'timezone', true);
                    $phone_number = get_post_meta($agent->ID, 'phone_number', true);
        ?>
                    <li>
                        <div class="rch-image-container">
                            <picture>
                                <a href="<?php echo esc_url(get_permalink($agent->ID)); ?>">
                                    <div class="rch-loader"></div>
                                    <img src="<?php echo esc_url($profile_image_url); ?>" alt="<?php echo esc_attr($agent->post_title); ?>" class="rch-profile-image">
                                </a>
                            </picture>
                        </div>
                        <div class="rch-archive-name">
                            <h3>
                                <a href="<?php echo esc_url(get_permalink($agent->ID)); ?>">
                                    <?php echo esc_html($agent->post_title); ?>
                                </a>
                            </h3>
                            <span>
                                <?php echo esc_html($timezone); ?>
                            </span>
                        </div>
                        <div class="rch-archive-end-line">
                            <a href="<?php echo esc_url(get_permalink($agent->ID)); ?>">View Profile</a>
                            <?php if ($phone_number) : ?>
                                <a href="tel:<?php echo esc_attr($phone_number); ?>">Contact</a>
                            <?php endif; ?>
                        </div>
                    </li>
        <?php
                }
                echo '</ul></div>';
            } else {
                echo '<p>No agents assigned to this region.</p>';
            }

        endwhile;
        ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
?>