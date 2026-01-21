<?php

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Enqueue Rechat SDK assets globally
 */
function rch_latest_listings_enqueue_assets()
{
    wp_enqueue_style('rechat-sdk-css');
    wp_enqueue_script('rechat-sdk-js');
}
add_action('wp_enqueue_scripts', 'rch_latest_listings_enqueue_assets');

/**
 * Latest Listings Web Component Shortcode
 * Usage: [rch_latest_listings property_types="Residential" listing_statuses="Active"]
 */
function rch_display_latest_listings_shortcode($atts)
{
    // Parse shortcode attributes with defaults
    $atts = shortcode_atts([
        'minimum_price' => '',
        'maximum_price' => '',
        'minimum_lot_square_meters' => '',
        'maximum_lot_square_meters' => '',
        'minimum_bathrooms' => '',
        'maximum_bathrooms' => '',
        'minimum_square_meters' => '',
        'maximum_square_meters' => '',
        'minimum_year_built' => '',
        'maximum_year_built' => '',
        'minimum_bedrooms' => '',
        'maximum_bedrooms' => '',
        'listing_per_page' => '10',
        'brand' => '',
        'listing_statuses' => '',
        'own_listing' => false,
        'property_types' => '',
        'map_latitude' => '',
        'map_longitude' => '',
        'sort_by' => '-list_date',
        'filter_address' => '',
    ], $atts);

    // Convert boolean attributes from strings
    $atts['own_listing'] = filter_var($atts['own_listing'], FILTER_VALIDATE_BOOLEAN);

    // Set brand only if own_listing is true
    if ($atts['own_listing']) {
        $atts['brand'] = get_option('rch_rechat_brand_id');
    } else {
        $atts['brand'] = '';
    }

    // Map property_types based on user input
    $property_types_raw = trim($atts['property_types']);
    if (!empty($property_types_raw)) {
        switch ($property_types_raw) {
            case 'All Listings':
                $atts['property_types'] = 'Residential,Residential Lease,Lots & Acreage,Commercial,Multi-Family';
                break;
            case 'Sale':
                $atts['property_types'] = 'Residential,Lots & Acreage,Commercial,Multi-Family';
                break;
            case 'Lease':
                $atts['property_types'] = 'Residential Lease';
                break;
            case 'Lots & Acreage':
                $atts['property_types'] = 'Lots & Acreage';
                break;
            case 'Commercial':
                $atts['property_types'] = 'Commercial';
                break;
            case 'Residential':
                $atts['property_types'] = 'Residential';
                break;
            default:
                // If it doesn't match any predefined values, use it as-is
                $atts['property_types'] = $property_types_raw;
                break;
        }
    }

    // Convert listing_statuses string to array if needed
    if (!empty($atts['listing_statuses']) && is_string($atts['listing_statuses'])) {
        $atts['listing_statuses'] = array_map('trim', explode(',', $atts['listing_statuses']));
    }

    // Sanitize and prepare data
    $listing_statuses_str = rch_sanitize_listing_statuses($atts['listing_statuses'] ?? []);
    $map_default_center = rch_get_map_default_center(
        $atts['map_latitude'] ?? '',
        $atts['map_longitude'] ?? ''
    );

    // Get rechat root attributes using helper function
    $rechat_attrs = rch_get_rechat_root_attributes($atts, $map_default_center, $listing_statuses_str);

    // Start output buffering
    ob_start();
?>
    <style>
.main-listing-sdk {
    position: relative;
}
        rechat-root {
            display: block;
        }

        rechat-listings-list {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 16px;
            padding: 16px;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            scroll-behavior: smooth;
        }

        rechat-listings-list::-webkit-scrollbar {
            display: none;
        }

        rechat-listings-list > div {
            flex: 0 0 auto;
            scroll-snap-align: start;
        }

        .nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: 1px solid #ddd;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: all 0.2s ease;
        }

        .nav-btn:hover {
            background: #f5f5f5;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .nav-btn:active {
            transform: translateY(-50%) scale(0.95);
        }

        .nav-btn-prev {
            left: 8px;
        }

        .nav-btn-next {
            right: 8px;
        }

        .nav-btn svg {
            width: 20px;
            height: 20px;
            fill: #333;
        }
    </style>

<div class="main-listing-sdk">
        <rechat-root <?php echo $rechat_attrs; ?>>
        <div class="container-listing-sdk">
            <button class="nav-btn nav-btn-prev" id="prevBtn">
                <svg viewBox="0 0 24 24">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                </svg>
            </button>

            <rechat-listings-list></rechat-listings-list>

            <button class="nav-btn nav-btn-next" id="nextBtn">
                <svg viewBox="0 0 24 24">
                    <path d="M8.59 16.59L10 18l6-6-6-6-1.41 1.41L13.17 12z"/>
                </svg>
            </button>
        </div>
    </rechat-root>
</div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const prevBtn = document.getElementById('prevBtn')
            const nextBtn = document.getElementById('nextBtn')

            const scrollAmount = 320

            prevBtn.addEventListener('click', () => {
                const list = document.querySelector('rechat-listings-list')
                if (list) {
                    list.scrollBy({ left: -scrollAmount, behavior: 'smooth' })
                }
            })

            nextBtn.addEventListener('click', () => {
                const list = document.querySelector('rechat-listings-list')
                if (list) {
                    list.scrollBy({ left: scrollAmount, behavior: 'smooth' })
                }
            })
        })
    </script>
<?php

    return ob_get_clean();
}
add_shortcode('rch_latest_listings', 'rch_display_latest_listings_shortcode');
