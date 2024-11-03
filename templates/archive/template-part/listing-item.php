<div class="house-item">
    <a href="<?php echo esc_url(get_home_url() . '/listing-detail/?listing_id=' . $listing['id']); ?>">
        <picture>
            <img src="<?php echo !empty($listing['cover_image_url']) ? $listing['cover_image_url'] : RCH_PLUGIN_ASSETS_URL_IMG . 'rechat_logo.jpg'; ?>" alt="House Image">
        </picture>
        <?php if (!empty($listing['price'])): ?>
            <h3>$ <?php echo number_format($listing['price']); ?></h3>
        <?php endif; ?>

        <?php if (!empty($listing['address']['street_number']) || !empty($listing['address']['street_name']) || !empty($listing['address']['city']) || !empty($listing['address']['state'])): ?>
            <p><?php echo $listing['address']['street_number'] . ' ' . $listing['address']['street_name'] . ', ' . $listing['address']['city'] . ', ' . $listing['address']['state']; ?></p>
        <?php endif; ?>

        <ul>
            <?php if (!empty($listing['compact_property']['bedroom_count'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'bed.svg'); ?>" alt="Beds"><b><?php echo $listing['compact_property']['bedroom_count']; ?></b> Beds</li>
            <?php endif; ?>
            <?php if (!empty($listing['compact_property']['full_bathroom_count'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'shower-full.svg'); ?>" alt="Full shower"><b><?php echo $listing['compact_property']['full_bathroom_count']; ?></b> Full Baths</li>
            <?php endif; ?>
            <?php if (!empty($listing['compact_property']['half_bathroom_count'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'shower-half.svg'); ?>" alt="Half shower"><b><?php echo $listing['compact_property']['half_bathroom_count']; ?></b> Half Baths</li>
            <?php endif; ?>
            <?php if (!empty($listing['compact_property']['square_meters'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'sq.svg'); ?>" alt="sq ft"><b><?php echo number_format(floatval($listing['compact_property']['square_meters']), 2); ?></b> SQ.FT</li>
            <?php endif; ?>
        </ul>
    </a>
</div>