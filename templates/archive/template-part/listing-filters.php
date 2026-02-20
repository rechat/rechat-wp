<?php if (wp_is_mobile()) { ?>
    <div class="rch-under-filter-in-mobile">
        <div class="box-filter-listing-text">
            <input type="search" class="rch-text-filter" id="content" placeholder="Search by Address, City, Zip code" />

        </div>
        <button class="filter-toggle-btn button-filter-for-mobile" onclick="toggleFilters()">Show Filters</button>
    </div>
    <div class="rch-filters-mobile" id="filters-container">
        <div class="rch-head-mobile-filter">
            <button type="button" class="reset-btn-all" onclick="resetFilter('all')">Reset</button>
            <span>
                Filters
            </span>
            <button class="close-btn" onclick="closeFilters()">
                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>close.svg" alt="">
            </button>
        </div>
        <div class="box-filter-listing">
            <span class="toggleMain">
                Property Type
                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>down-arrow.svg" alt="">
            </span>
            <div class="rch-inside-filters rch-for-lease" style="display:flex;">
                <span>
                    <input type="radio" id="mobile_all" name="property_types" data-name="All Listings" value="Residential,Residential Lease,Lots & Acreage,Commercial,Multi-Family" onchange="applyFilters()">
                    <label for="mobile_all" class="ios-radio">All Listings</label>
                </span>
                <span>
                    <input type="radio" id="mobile_residential" name="property_types" data-name="Residential" value="Residential" onchange="applyFilters()">
                    <label for="mobile_residential" class="ios-radio">Residential</label>
                </span>
                <span>
                    <input type="radio" id="mobile_sale" name="property_types" data-name="Sale" value="Residential,Lots & Acreage,Commercial,Multi-Family" onchange="applyFilters()">
                    <label for="mobile_sale" class="ios-radio">Sale</label>
                </span>
                <span>
                    <input type="radio" id="mobile_lease" name="property_types" data-name="Lease" value="Residential Lease" onchange="applyFilters()">
                    <label for="mobile_lease" class="ios-radio">Lease</label>
                </span>
                <span>
                    <input type="radio" id="mobile_lots" name="property_types" data-name="Lots & Acreage" value="Lots & Acreage" onchange="applyFilters()">
                    <label for="mobile_lots" class="ios-radio">Lots & Acreage</label>
                </span>
                <span>
                    <input type="radio" id="mobile_commercial" name="property_types" data-name="Commercial" value="Commercial" onchange="applyFilters()">
                    <label for="mobile_commercial" class="ios-radio">Commercial</label>
                </span>
                <span>
                    <input type="radio" id="mobile_multifamily" name="property_types" data-name="Multi-Family" value="Multi-Family" onchange="applyFilters()">
                    <label for="mobile_multifamily" class="ios-radio">Multi-Family</label>
                </span>
                <button type="button" class="reset-btn" onclick="resetFilter('property_types')">Reset</button>
            </div>
        </div>
        <div class="box-filter-listing rch-price-filter-listing">
            <span class="toggleMain">
                Price
                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>down-arrow.svg" alt="">
            </span>
            <div class="rch-inside-filters rch-main-price" style="display: none;">
                <div class="rch-under-main-price">
                    <div>
                        <label for="minimum_price">Min:</label>
                        <select id="minimum_price" class="rch-price">
                            <!-- Options populated dynamically -->
                        </select>
                    </div>
                    <span>To</span>
                    <div>
                        <label for="maximum_price">Max:</label>
                        <select id="maximum_price" class="rch-price" onchange="applyFilters()">
                            <!-- Options populated dynamically -->
                        </select>
                    </div>
                </div>
                <button type="button" class="reset-btn" onclick="resetFilter('price')">Reset</button>

            </div>
        </div>
        <div class="box-filter-listing rch-price-filter-listing rch-beds-filter-listing">
            <span class="toggleMain">
                Beds
                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>down-arrow.svg" alt="">
            </span>
            <div class="rch-inside-filters rch-main-price" style="display: none;">
                <div class="rch-under-main-price">
                    <div>
                        <label for="minimum_bedrooms">Min:</label>
                        <select id="minimum_bedrooms" class="rch-beds" onchange="handleBedsChange('min')">
                            <option value="">Min</option>
                            <option value="1" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 1) ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 2) ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 3) ? 'selected' : ''; ?>>3</option>
                            <option value="4" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 4) ? 'selected' : ''; ?>>4</option>
                            <option value="5" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 5) ? 'selected' : ''; ?>>5</option>
                            <option value="6" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 6) ? 'selected' : ''; ?>>6</option>
                        </select>
                    </div>
                    <span>To</span>
                    <div>
                        <label for="maximum_bedrooms">Max:</label>
                        <select id="maximum_bedrooms" class="rch-beds" onchange="handleBedsChange()">
                            <option value="">Max</option>
                            <option value="1" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 1) ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 2) ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 3) ? 'selected' : ''; ?>>3</option>
                            <option value="4" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 4) ? 'selected' : ''; ?>>4</option>
                            <option value="5" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 5) ? 'selected' : ''; ?>>5</option>
                            <option value="6" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 6) ? 'selected' : ''; ?>>6</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="reset-btn" onclick="resetFilter('beds')">Reset</button>

            </div>
        </div>

        <div class="box-filter-listing rch-status-filter-listing">
            <span class="toggleMain">
                Status
                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>down-arrow.svg" alt="">
            </span>
            <div class="rch-other-inside-filters rch-inside-filters" style="display: none;">
                <label>
                    <input type="checkbox" name="listing_statuses" value="Active,Incoming,Coming Soon,Pending" onchange="applyFilters()"> Active
                </label>
                <label>
                    <input type="checkbox" name="listing_statuses" value="Sold,Leased" onchange="applyFilters()"> Closed
                </label>
                <label>
                    <input type="checkbox" name="listing_statuses" value="Withdrawn,Expired" onchange="applyFilters()"> Archived
                </label>
            </div>
        </div>
        <div class="box-filter-listing rch-open-house-filter-listing">
            <span class="toggleMain">
                Open Houses Only
                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>down-arrow.svg" alt="">
            </span>
            <div class="rch-other-inside-filters rch-inside-filters" style="display: none;">
                <label>
                    <input type="checkbox" id="mobile_open_house" name="open_house" value="true" <?php echo (isset($atts['open_houses_only']) && $atts['open_houses_only']) ? 'checked' : ''; ?> onchange="applyFilters()"> Open Houses Only
                </label>
            </div>
        </div>
        <div class="box-filter-listing rch-price-filter-listing rch-bath-filter-listing">
            <span class="toggleMain">
                Bath
                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>down-arrow.svg" alt="">
            </span>
            <div class="rch-inside-filters rch-main-price rch-main-beds" style="display: none;">
                <div class="rch-button-select">
                    <!-- Buttons to select the number of bathrooms -->
                    <button type="button" class="filter-btn default-btn" data-value="">Any</button>
                    <button type="button" class="filter-btn <?php echo (isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] == 1) ? 'active' : ''; ?>" data-value="1">+1</button>
                    <button type="button" class="filter-btn <?php echo (isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] == 2) ? 'active' : ''; ?>" data-value="2">+2</button>
                    <button type="button" class="filter-btn <?php echo (isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] == 3) ? 'active' : ''; ?>" data-value="3">+3</button>
                    <button type="button" class="filter-btn <?php echo (isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] == 4) ? 'active' : ''; ?>" data-value="4">+4</button>
                    <button type="button" class="filter-btn <?php echo (isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] == 5) ? 'active' : ''; ?>" data-value="5">+5</button>
                </div>
                <button type="button" class="reset-btn" onclick="resetFilter('baths')">Reset</button> <!-- Reset Button -->
            </div>


        </div>
        <div class="box-filter-listing rch-parking-filter-listing">
            <span class="toggleMain">
                Parking Spaces
                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>down-arrow.svg" alt="">
            </span>
            <div class="rch-other-inside-filters rch-inside-filters rch-main-parking" style="display: none;">
                <div class="rch-button-select">
                    <button type="button" class="filter-btn default-btn" data-value="">Any</button>
                    <button type="button" class="filter-btn" data-value="1">+1</button>
                    <button type="button" class="filter-btn" data-value="2">+2</button>
                    <button type="button" class="filter-btn" data-value="3">+3</button>
                    <button type="button" class="filter-btn" data-value="4">+4</button>
                    <button type="button" class="filter-btn" data-value="5">+5</button>
                </div>
                <button type="button" class="reset-btn" onclick="resetFilter('parking')">Reset</button>

            </div>
        </div>
        <div class="box-filter-listing rch-square-footage-filter-listing">
            <span class="toggleMain">
                Square Footage
                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>down-arrow.svg" alt="">
            </span>
            <div class="rch-other-inside-filters  rch-inside-filters" style="display: none;">
                <div class="rch-under-main-price">
                    <div>
                        <label for="minimum_square_meters">Min</label>
                        <input type="text" id="minimum_square_meters" class="rch-square" placeholder="Min">
                    </div>
                    <span>To</span>
                    <div>
                        <label for="maximum_square_meters">Max</label>
                        <input type="text" id="maximum_square_meters" class="rch-square" placeholder="Max">
                    </div>
                </div>
                <button type="button" class="reset-btn" onclick="resetFilter('square')">Reset</button>
            </div>



        </div>
        <!-- Year Built -->
        <div class=" box-filter-listing rch-year-built-filter-listing">
            <span class="toggleMain">
                Year Built
                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>down-arrow.svg" alt="">
            </span>
            <div class="rch-other-inside-filters rch-inside-filters" style="display: none;">
                <div class="rch-under-main-price">
                    <div>
                        <label for="minimum_year_built">Min</label>
                        <select id="minimum_year_built" class="rch-year" placeholder="Min">
                            <!-- Options YearBuilt dynamically -->
                        </select>
                    </div>
                    <span>To</span>
                    <div>
                        <label for="maximum_year_built">Max</label>
                        <select type="text" id="maximum_year_built" class="rch-year" placeholder="Max" onchange="applyFilters()">
                            <!-- Options YearBuilt dynamically -->
                        </select>
                    </div>
                </div>
                <button type="button" class="reset-btn" onclick="resetFilter('yearBuilt')">Reset</button>

            </div>
        </div>
        <!-- ZIP Code Filter -->
        <div class="box-filter-listing rch-zip-code-filter-listing">
            <span class="toggleMain">

                ZIP Code
                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>down-arrow.svg" alt="">
            </span>
            <div class="rch-other-inside-filters rch-inside-postal-code rch-inside-filters" style="display: none;">
                <input type="text" id="postal_codes" name="postal_codes" placeholder="Enter ZIP Code">
                <div id="tags-container" class="rch-tag-postal-codes"></div>
            </div>
        </div>
        <div class="rch-footer-filter-mobile">
            <button class="close-btn" onclick="closeFilters()">
                See Properties
            </button>
        </div>
    </div>
<?php
} else { ?>
    <div class="rch-filters">
        <div class="box-filter-listing-text">
            <input type="search" class="rch-text-filter" id="content" placeholder="Search by Address, City, Zip code" />

        </div>
        <div class="box-filter-listing">
            <span class="toggleMain" id="rch-property-type-text">Property Type</span>
            <div class="rch-inside-filters rch-for-lease" style="display: none;">
                <span>
                    <input type="radio" id="desktop_all" name="property_types" data-name="All Listings" value="Residential,Residential Lease,Lots & Acreage,Commercial,Multi-Family" onchange="applyFilters()">
                    <label for="desktop_all" class="ios-radio">All Listings</label>
                </span>
                <span>
                    <input type="radio" id="desktop_residential" name="property_types" data-name="Residential" value="Residential" onchange="applyFilters()">
                    <label for="desktop_residential" class="ios-radio">Residential</label>
                </span>
                <span>
                    <input type="radio" id="desktop_sale" name="property_types" data-name="Sale" value="Residential,Lots & Acreage,Commercial,Multi-Family" onchange="applyFilters()">
                    <label for="desktop_sale" class="ios-radio">Sale</label>
                </span>
                <span>
                    <input type="radio" id="desktop_lease" name="property_types" data-name="Lease" value="Residential Lease" onchange="applyFilters()">
                    <label for="desktop_lease" class="ios-radio">Lease</label>
                </span>
                <span>
                    <input type="radio" id="desktop_lots" name="property_types" data-name="Lots & Acreage" value="Lots & Acreage" onchange="applyFilters()">
                    <label for="desktop_lots" class="ios-radio">Lots & Acreage</label>
                </span>
                <span>
                    <input type="radio" id="desktop_commercial" name="property_types" data-name="Commercial" value="Commercial" onchange="applyFilters()">
                    <label for="desktop_commercial" class="ios-radio">Commercial</label>
                </span>
                <span>
                    <input type="radio" id="desktop_multifamily" name="property_types" data-name="Multi-Family" value="Multi-Family" onchange="applyFilters()">
                    <label for="desktop_multifamily" class="ios-radio">Multi-Family</label>
                </span>
                <button type="button" class="reset-btn" onclick="resetFilter('property_types')">Reset</button>

            </div>

        </div>
        <div class="box-filter-listing rch-price-filter-listing">
            <span class="toggleMain" id="rch-price-text-filter">Price</span>
            <div class="rch-inside-filters rch-main-price" style="display: none;">
                <span>
                    $ Price
                </span>
                <div class="rch-under-main-price">
                    <div>
                        <label for="minimum_price">Min:</label>
                        <select id="minimum_price" class="rch-price" onchange="handlePriceChange()">
                            <!-- Options populated dynamically -->
                        </select>
                    </div>
                    <span>To</span>
                    <div>
                        <label for="maximum_price">Max:</label>
                        <select id="maximum_price" class="rch-price" onchange="handlePriceChange()">
                            <!-- Options populated dynamically -->
                        </select>
                    </div>
                </div>
                <button type="button" class="reset-btn" onclick="resetFilter('price')">Reset</button>
            </div>
        </div>
        <div class="box-filter-listing rch-price-filter-listing rch-beds-filter-listing">
            <span id="rch-beds-text-filter" class="toggleMain">Beds</span>
            <div class="rch-inside-filters rch-main-price" style="display: none;">
                <span>
                    Beds
                </span>
                <div class="rch-under-main-price">
                    <div>
                        <label for="minimum_bedrooms">Min:</label>
                        <select id="minimum_bedrooms" class="rch-beds" onchange="handleBedsChange('min')">
                            <option value="">Min</option>
                            <option value="1" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 1) ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 2) ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 3) ? 'selected' : ''; ?>>3</option>
                            <option value="4" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 4) ? 'selected' : ''; ?>>4</option>
                            <option value="5" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 5) ? 'selected' : ''; ?>>5</option>
                            <option value="6" <?php echo (isset($atts['minimum_bedrooms']) && $atts['minimum_bedrooms'] == 6) ? 'selected' : ''; ?>>6</option>
                        </select>
                    </div>
                    <span>To</span>
                    <div>
                        <label for="maximum_bedrooms">Max:</label>
                        <select id="maximum_bedrooms" class="rch-beds" onchange="handleBedsChange()">
                            <option value="">Max</option>
                            <option value="1" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 1) ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 2) ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 3) ? 'selected' : ''; ?>>3</option>
                            <option value="4" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 4) ? 'selected' : ''; ?>>4</option>
                            <option value="5" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 5) ? 'selected' : ''; ?>>5</option>
                            <option value="6" <?php echo (isset($atts['maximum_bedrooms']) && $atts['maximum_bedrooms'] == 6) ? 'selected' : ''; ?>>6</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="reset-btn" id="reset-btn-beds" onclick="resetFilter('beds')">Reset</button>
            </div>
        </div>


        <div class="box-filter-listing rch-price-filter-listing rch-bath-filter-listing">
            <span id="rch-baths-text-filter" class="toggleMain">Bath</span>
            <div class="rch-inside-filters rch-main-price rch-main-beds" style="display: none;">
                <span>
                    Baths
                </span>
                <div class="rch-button-select">
                    <!-- Buttons to select the number of bathrooms -->
                    <button type="button" class="filter-btn " data-value="">Any</button>
                    <button type="button" class="filter-btn <?php echo (isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] == 1) ? 'active' : ''; ?>" data-value="1">+1</button>
                    <button type="button" class="filter-btn <?php echo (isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] == 2) ? 'active' : ''; ?>" data-value="2">+2</button>
                    <button type="button" class="filter-btn <?php echo (isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] == 3) ? 'active' : ''; ?>" data-value="3">+3</button>
                    <button type="button" class="filter-btn <?php echo (isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] == 4) ? 'active' : ''; ?>" data-value="4">+4</button>
                    <button type="button" class="filter-btn <?php echo (isset($atts['minimum_bathrooms']) && $atts['minimum_bathrooms'] == 5) ? 'active' : ''; ?>" data-value="5">+5</button>
                </div>
                <button type="button" class="reset-btn" onclick="resetFilter('baths')">Reset</button> <!-- Reset Button -->
            </div>
        </div>

        <div class="box-filter-listing">
            <span class="toggleMain more-filter-text">
                More Filters
                <span id="filter-badge" class="rch-filter-badge" style="display: none;">0</span>

            </span>
            <div class="rch-inside-filters rch-other-filter-listing" style="display: none;">
                <div class="rch-status-filter-listing">
                    <span class="title-each-filter-more">Status</span>
                    <div class="rch-other-inside-filters">
                        <label>
                            <input type="checkbox" name="listing_statuses" value="Active,Incoming,Coming Soon,Pending" onchange="applyFilters()"> Active
                        </label>
                        <label>
                            <input type="checkbox" name="listing_statuses" value="Sold,Leased" onchange="applyFilters()"> Closed
                        </label>
                        <label>
                            <input type="checkbox" name="listing_statuses" value="Withdrawn,Expired" onchange="applyFilters()"> Archived
                        </label>
                    </div>
                </div>

                <!-- Open Houses Only Filter -->
                <div class="rch-open-house-filter-listing">
                    <div class="rch-other-inside-filters">
                        <label>
                            <input type="checkbox" id="desktop_open_house" name="open_house" value="true" <?php echo (isset($atts['open_houses_only']) && $atts['open_houses_only']) ? 'checked' : ''; ?> onchange="applyFilters()"> Open Houses Only
                        </label>
                    </div>
                </div>

                <!-- Parking Spaces Filter -->
                <div class="rch-parking-filter-listing">
                    <span id="rch-parking-text-filter" class="title-each-filter-more">Parking Spaces</span>
                    <div class="rch-other-inside-filters rch-button-select rch-main-parking">
                        <button type="button" class="filter-btn default-btn" data-value="">Any</button>
                        <button type="button" class="filter-btn" data-value="1">+1</button>
                        <button type="button" class="filter-btn" data-value="2">+2</button>
                        <button type="button" class="filter-btn" data-value="3">+3</button>
                        <button type="button" class="filter-btn" data-value="4">+4</button>
                        <button type="button" class="filter-btn" data-value="5">+5</button>
                    </div>
                    <button type="button" class="reset-btn" onclick="resetFilter('parking')">Reset</button>
                </div>

                <!-- Square Footage Filter -->
                <div class="rch-square-footage-filter-listing">
                    <span id="rch-footage-text-filter" class="title-each-filter-more">Square Footage</span>
                    <div class="rch-other-inside-filters rch-under-main-price">
                        <div>
                            <label for="minimum_square_meters">Min</label>
                            <input type="text" id="minimum_square_meters" class="rch-square" value="<?php echo $atts['minimum_square_meters'] ?>" placeholder="Min" onkeyup="handleSquareChange()">
                        </div>
                        <span>To</span>
                        <div>
                            <label for="maximum_square_meters">Max</label>
                            <input type="text" id="maximum_square_meters" class="rch-square" value="<?php echo $atts['maximum_square_meters'] ?>" placeholder="Max" onkeyup="handleSquareChange()">
                        </div>
                    </div>
                    <button type="button" class="reset-btn" onclick="resetFilter('square')">Reset</button>
                </div>

                <!-- Lot Size Filter -->
                <div class="rch-lot-size-filter-listing">
                    <span id="rch-lot-text-filter" class="title-each-filter-more">Lot Size</span>
                    <div class="rch-other-inside-filters rch-under-main-price">
                        <div>
                            <label for="minimum_square_meters">Min</label>
                            <input type="text" id="minimum_lot_square_meters" class="rch-lot" value="<?php echo $atts['minimum_lot_square_meters'] ?>" placeholder="Min" onkeyup="handleLotChange()">
                        </div>
                        <span>To</span>
                        <div>
                            <label for="maximum_lot_square_meters">Max</label>
                            <input type="text" id="maximum_lot_square_meters" class="rch-lot" value="<?php echo $atts['maximum_lot_square_meters'] ?>" placeholder="Max" onkeyup="handleLotChange()">
                        </div>
                    </div>
                    <button type="button" class="reset-btn" onclick="resetFilter('lot')">Reset</button>
                </div>
                <!-- Year Built -->
                <div class="rch-year-built-filter-listing">
                    <span id="rch-year-text-filter" class="title-each-filter-more">Year Built</span>
                    <div class="rch-other-inside-filters rch-under-main-price">
                        <div>
                            <label for="minimum_year_built">Min</label>
                            <select id="minimum_year_built" class="rch-year" placeholder="Min" onchange="handleYearChange()">
                                <!-- Options YearBuilt dynamically -->
                            </select>
                        </div>
                        <span>To</span>
                        <div>
                            <label for="maximum_year_built">Max</label>
                            <select type="text" id="maximum_year_built" class="rch-year" placeholder="Max" onchange="handleYearChange()">
                                <!-- Options YearBuilt dynamically -->
                            </select>
                        </div>
                    </div>
                    <button type="button" class="reset-btn" onclick="resetFilter('yearBuilt')">Reset</button>
                </div>
                <!-- ZIP Code Filter -->
                <div class="rch-zip-code-filter-listing">
                    <span class="title-other-each-filter-more">ZIP Code</span>
                    <div class="rch-other-inside-filters rch-inside-postal-code">
                        <input type="text" id="postal_codes" name="postal_codes" placeholder="Enter ZIP Code">
                        <div id="tags-container" class="rch-tag-postal-codes"></div>
                    </div>
                </div>
            </div>
        </div>
        <button type="button" class="reset-btn-all" onclick="resetFilter('all')">Reset</button>
        <div class="rch-map-view">
            <label>
                <span>
                    Show Map
                </span>
                <input type="checkbox" id="toggle-map" checked>

            </label>
        </div>
    </div>
<?php } ?>