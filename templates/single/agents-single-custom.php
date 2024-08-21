<?php
get_header();

// Get the current post ID
$post_id = get_the_ID();

// Retrieve the meta values
$website = get_post_meta($post_id, 'website', true);
$instagram = get_post_meta($post_id, 'instagram', true);
$twitter = get_post_meta($post_id, 'twitter', true);
$linkedin = get_post_meta($post_id, 'linkedin', true);
$youtube = get_post_meta($post_id, 'youtube', true);
$facebook = get_post_meta($post_id, 'facebook', true);
$phone_number = get_post_meta($post_id, 'phone_number', true);
$email = get_post_meta($post_id, 'email', true);
$profile_image_url = get_post_meta($post_id, 'profile_image_url', true);
$timezone = get_post_meta($post_id, 'timezone', true);
?>

<div id="primary" class="content-area rch-primary-content">
    <main id="main" class="site-main content-container site-container">

        <?php
        while (have_posts()) : the_post();

        ?>
            <div class="rch-top-single-agent">
                <div class="rch-left-top-single-agent">
                    <?php if ($timezone) : ?>
                        <div class="rch-image-container">
                            <picture>
                                <a href="<?php the_permalink() ?>">
                                    <div class="rch-loader"></div>
                                    <img src="<?php echo esc_url($profile_image_url); ?>" alt="<?php the_title() ?>" class="rch-profile-image">
                                </a>
                            </picture>
                        </div>
                    <?php endif; ?>
                    <div class="rch-data-agent">
                        <?php the_title('<h1>', '</h1>') ?>

                        <?php if ($timezone) : ?>
                            <span>
                                Location:
                                <?php echo esc_html($timezone); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($phone_number) : ?>
                            <span>
                                Phone:
                                <?php echo esc_html($phone_number); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($email) : ?>
                            <span>
                                Email:
                                <?php echo esc_html($email); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($website) : ?>
                            <span>
                                Website:
                                <a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rch-right-top-single-agent">
                    <?php if ($instagram || $twitter || $linkedin || $youtube || $facebook) : ?>
                        <span>
                            Social Media:
                        </span>
                        <ul>
                            <?php if ($instagram) : ?>
                                <li>
                                    <a href="<?php echo esc_url($instagram); ?>" target="_blank">
                                        <img src="<?php echo RCH_PLUGIN_ASSETS_URL ?>instagram.svg" alt="">
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if ($twitter) : ?>
                                <li>
                                    <a href="<?php echo esc_url($twitter); ?>" target="_blank">
                                        <img src="<?php echo RCH_PLUGIN_ASSETS_URL ?>x.svg" alt="">
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if ($linkedin) : ?>
                                <li>
                                    <a href="<?php echo esc_url($linkedin); ?>" target="_blank">
                                        <img src="<?php echo RCH_PLUGIN_ASSETS_URL ?>linkedin.svg" alt="">
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if ($youtube) : ?>
                                <li>
                                    <a href="<?php echo esc_url($youtube); ?>" target="_blank">
                                        <img src="<?php echo RCH_PLUGIN_ASSETS_URL ?>youtube.svg" alt="">
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if ($facebook) : ?>
                                <li>
                                    <a href="<?php echo esc_url($facebook); ?>" target="_blank">
                                        <img src="<?php echo RCH_PLUGIN_ASSETS_URL ?>facebook.svg" alt="">
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <div class="rch-main-content">
                <?php the_content() ?>
            </div>
            <?php if ($phone_number) : ?>
                <div class="rch-single-call">
                    <a href="tel:<?php echo esc_attr($phone_number); ?>">
                        Contact <?php the_title() ?>
                    </a>
                </div>
            <?php endif; ?>
        <?php
        endwhile;
        ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
?>