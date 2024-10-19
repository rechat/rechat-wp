<div class="house-item">
    <a href="<?php echo esc_url(get_home_url() . '/listing-detail/?listing_id=' . $house['id']); ?>">
        <picture>
            <img src="<?php echo !empty($house['cover_image_url']) ? $house['cover_image_url'] : RCH_PLUGIN_ASSETS_URL . 'rechat_logo.jpg'; ?>" alt="House Image">
        </picture>
        <?php if (!empty($house['price'])): ?>
            <h3>$ <?php echo number_format($house['price']); ?></h3>
        <?php endif; ?>

        <?php if (!empty($house['address']['street_number']) || !empty($house['address']['street_name']) || !empty($house['address']['city']) || !empty($house['address']['state'])): ?>
            <p><?php echo $house['address']['street_number'] . ' ' . $house['address']['street_name'] . ', ' . $house['address']['city'] . ', ' . $house['address']['state']; ?></p>
        <?php endif; ?>

        <ul>
            <?php if (!empty($house['compact_property']['bedroom_count'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL . 'bed.svg'); ?>" alt="Beds"><b><?php echo $house['compact_property']['bedroom_count']; ?></b> Beds</li>
            <?php endif; ?>
            <?php if (!empty($house['compact_property']['full_bathroom_count'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL . 'shower-full.svg'); ?>" alt="Full shower"><b><?php echo $house['compact_property']['full_bathroom_count']; ?></b> Full Baths</li>
            <?php endif; ?>
            <?php if (!empty($house['compact_property']['half_bathroom_count'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL . 'shower-half.svg'); ?>" alt="Half shower"><b><?php echo $house['compact_property']['half_bathroom_count']; ?></b> Half Baths</li>
            <?php endif; ?>
            <?php if (!empty($house['compact_property']['square_meters'])): ?>
                <li><img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL . 'sq.svg'); ?>" alt="sq ft"><b><?php echo number_format(floatval($house['compact_property']['square_meters']), 2); ?></b> SQ.FT</li>
            <?php endif; ?>
        </ul>
    </a>
</div>