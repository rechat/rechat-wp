<li style="background-color:<?php echo esc_attr($region_bg_color); ?>">
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
            <a href="<?php the_permalink() ?>" style="color:<?php echo esc_attr($text_color); ?>">
                <?php the_title() ?>
            </a>
        </h3>
        <span>
            <?php echo esc_html($designation) ?>
        </span>
    </div>
    <div class="rch-archive-end-line">
        <a href="<?php the_permalink() ?>">View Profile</a>
        <?php if ($phone_number) : ?>
            <a href="tel:<?php echo esc_attr($phone_number); ?>" class="rch-agent-phone-archive">Contact</a>
        <?php endif; ?>
    </div>
</li>