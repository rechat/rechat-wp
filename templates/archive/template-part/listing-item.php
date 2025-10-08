<div class="house-item">
    <a href="<?php echo esc_url(get_home_url() . '/listing-detail/?listing_id=' . esc_attr($listing['id'])); ?>">
        <picture class="<?php echo empty($listing['cover_image_url']) ? 'rch-fallback-logo-listing' : ''; ?>">
            <img
                src="<?php
                        echo !empty($listing['cover_image_url'])
                            ? esc_url($listing['cover_image_url'])
                            : esc_url(get_option('rch_inverted_container_logo_wide', get_home_url() . '/assets/img/placeholder.webp'));
                        ?>"
                alt="House Image">
        </picture>
        <?php if (!empty($listing['formatted']['price'])): ?>
            <h3><?php echo sanitize_textarea_field($listing['formatted']['price']['text']); ?></h3>
        <?php endif; ?>

        <?php if (!empty($listing['formatted']['street_address']['text'])): ?>
            <p>
                <?php
                echo sanitize_textarea_field($listing['formatted']['street_address']['text']) . ' ' . sanitize_textarea_field($listing['formatted']['address_line_2']['text']);
                ?>
            </p>
        <?php endif; ?>

        <ul>
            <?php if (!empty($listing['formatted']['bedroom_count']['text'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'bed.svg'); ?>" alt="Beds"><b><?php echo esc_html($listing['formatted']['bedroom_count']['text']); ?></b></li>
            <?php endif; ?>
            <?php if (!empty($listing['formatted']['total_bathroom_count']['text'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'shower-full.svg'); ?>" alt="Full shower"><b><?php echo esc_html($listing['formatted']['total_bathroom_count']['text']); ?></b></li>
            <?php endif; ?>
            <?php if (!empty($listing['formatted']['square_feet']['text'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'sq.svg'); ?>" alt="sq ft"><b><?php echo sanitize_text_field($listing['formatted']['square_feet']['text']); ?></b></li>
            <?php endif; ?>
        </ul>
    </a>
</div>