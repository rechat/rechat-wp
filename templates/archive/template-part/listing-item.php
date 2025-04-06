<div class="house-item">
    <a href="<?php echo esc_url(get_home_url() . '/listing-detail/?listing_id=' . esc_attr($listing['id'])); ?>">
        <picture class="<?php echo empty($listing['cover_image_url']) ? 'rch-fallback-logo-listing' : ''; ?>"
        >
        <img 
    src="<?php 
        echo !empty($listing['cover_image_url']) 
            ? esc_url($listing['cover_image_url']) 
            : esc_url(get_option('rch_brand_logo_url', PMY_THEME_URL . '/assets/img/placeholder.webp')); 
    ?>" 
    alt="House Image">    
    </picture>
        <?php if (!empty($listing['price'])): ?>
            <h3>$ <?php echo esc_html(number_format($listing['price'])); ?></h3>
        <?php endif; ?>

        <?php if (!empty($listing['address']['street_number']) || !empty($listing['address']['street_name']) || !empty($listing['address']['city']) || !empty($listing['address']['state'])): ?>
            <p>
                <?php 
                echo esc_html($listing['address']['street_number'] . ' ' . $listing['address']['street_name'] . ', ' . $listing['address']['city'] . ', ' . $listing['address']['state']); 
                ?>
            </p>
        <?php endif; ?>

        <ul>
            <?php if (!empty($listing['compact_property']['bedroom_count'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'bed.svg'); ?>" alt="Beds"><b><?php echo esc_html($listing['compact_property']['bedroom_count']); ?></b> Beds</li>
            <?php endif; ?>
            <?php if (!empty($listing['compact_property']['full_bathroom_count'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'shower-full.svg'); ?>" alt="Full shower"><b><?php echo esc_html($listing['compact_property']['full_bathroom_count']); ?></b> Full Baths</li>
            <?php endif; ?>
            <?php if (!empty($listing['compact_property']['half_bathroom_count'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'shower-half.svg'); ?>" alt="Half shower"><b><?php echo esc_html($listing['compact_property']['half_bathroom_count']); ?></b> Half Baths</li>
            <?php endif; ?>
            <?php if (!empty($listing['compact_property']['square_meters'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'sq.svg'); ?>" alt="sq ft"><b><?php echo esc_html(number_format(floatval($listing['compact_property']['square_meters']), 2)); ?></b> SQ.FT</li>
            <?php endif; ?>
        </ul>
    </a>
</div>
