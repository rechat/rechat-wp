<?php

/**
 * Search form filters template
 * This is a modified version of listing-filters.php for the standalone search form shortcode
 * Converted to direct dropdowns (no toggle required)
 */

// If we're using this in the search form, we don't need to check for mobile
$is_search_form = isset($is_search_form) && $is_search_form;

// Define compact mode for the search form
$is_compact = isset($compact) && $compact;
$container_class = $is_compact ? 'rch-search-filters-compact' : 'rch-search-filters';
?>

<div class="<?php echo esc_attr($container_class); ?>">
        <div class="rch-search-form-col">
            <input type="search" class="rch-text-filter" name="content" id="search-content" placeholder="Search by City, ZIP, Address..." />
        </div>
    <div class="rch-search-form-col">
        <select name="property_types" id="search-property-types" class="rch-dropdown">
            <option value="">Property Type</option>
            <option value="Residential,Residential Lease,Lots & Acreage,Commercial,Multi-Family">All Listings</option>
            <option value="Residential">For Sale</option>
            <option value="Residential Lease">For Lease</option>
            <option value="Lots & Acreage">Lots & Acreage</option>
            <option value="Commercial">Commercial</option>
        </select>
    </div>

    <div class="rch-search-form-col">
        <select name="minimum_bathrooms" id="search-minimum_bathrooms" class="rch-dropdown">
            <option value="">Min Bathrooms</option>
            <option value="1">1+ Bath</option>
            <option value="2">2+ Bath</option>
            <option value="3">3+ Bath</option>
            <option value="4">4+ Bath</option>
            <option value="5">5+ Bath</option>
        </select>
    </div>

    <div class="rch-search-form-col">
        <select id="search-minimum_bedrooms" name="minimum_bedrooms" class="rch-dropdown">
            <option value="">Min Beds</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
            <option value="5">5</option>
            <option value="6">6+</option>
        </select>
    </div>

    <div class="rch-search-form-col">
        <select id="search-maximum_bedrooms" name="maximum_bedrooms" class="rch-dropdown">
            <option value="">Max Beds</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
            <option value="5">5</option>
            <option value="6">6+</option>
        </select>
    </div>
    <div class="rch-search-form-col">
        <select id="search-minimum_price" name="minimum_price" class="rch-dropdown">
            <option value="">Min Price</option>
            <option value="50000">$50,000</option>
            <option value="100000">$100,000</option>
            <option value="150000">$150,000</option>
            <option value="200000">$200,000</option>
            <option value="250000">$250,000</option>
            <option value="300000">$300,000</option>
            <option value="350000">$350,000</option>
            <option value="400000">$400,000</option>
            <option value="450000">$450,000</option>
            <option value="500000">$500,000</option>
            <option value="600000">$600,000</option>
            <option value="700000">$700,000</option>
            <option value="800000">$800,000</option>
            <option value="900000">$900,000</option>
            <option value="1000000">$1M</option>
            <option value="1500000">$1.5M</option>
            <option value="2000000">$2M</option>
            <option value="3000000">$3M</option>
            <option value="5000000">$5M</option>
            <option value="10000000">$10M</option>
        </select>
    </div>
    <select id="search-maximum_price" name="maximum_price" class="rch-dropdown">
        <option value="">Max Price</option>
        <option value="50000">$50,000</option>
        <option value="100000">$100,000</option>
        <option value="150000">$150,000</option>
        <option value="200000">$200,000</option>
        <option value="250000">$250,000</option>
        <option value="300000">$300,000</option>
        <option value="350000">$350,000</option>
        <option value="400000">$400,000</option>
        <option value="450000">$450,000</option>
        <option value="500000">$500,000</option>
        <option value="600000">$600,000</option>
        <option value="700000">$700,000</option>
        <option value="800000">$800,000</option>
        <option value="900000">$900,000</option>
        <option value="1000000">$1M</option>
        <option value="1500000">$1.5M</option>
        <option value="2000000">$2M</option>
        <option value="3000000">$3M</option>
        <option value="5000000">$5M</option>
        <option value="10000000">$10M</option>
    </select>
</div>