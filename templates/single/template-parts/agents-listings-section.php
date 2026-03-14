<?php
/**
 * Agent Listings Section
 * 
 * This file contains the core logic for rendering agent listings on single agent pages.
 * It is loaded directly from the plugin to ensure updates are applied even when 
 * the template file is overridden in the theme.
 * 
 * @package Rechat
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Render agent listings section based on display mode
 * 
 * @param int $post_id The agent post ID
 */
function rch_render_agent_listings_section($post_id) {
    // Get the agents meta value
    $agents = get_post_meta($post_id, 'agents', true);
    
    // Get admin settings for listing display mode
    $display_mode = get_option('rch_listing_display_mode', 'combined'); // Default: combined

    // Convert agents array to comma-separated string for web component
    if (is_array($agents)) {
        $agents_string = implode(',', $agents);
    } else {
        $agents_string = $agents;
    }

    // Define common property types and subtypes
    $property_subtypes = 'RES-Single Family, RES-Half Duplex, RES-Farm/Ranch, RES-Condo, RES-Townhouse, LSE-Apartment, LSE-Condo/Townhome, LSE-Duplex, LSE-Fourplex, LSE-House, LSE-Mobile, LSE-Triplex, LND-Commercial, LND-Farm/Ranch, LND-Residential, MUL-Full Duplex, MUL-Apartment/5Plex+, MUL-Fourplex, MUL-Multiple Single Units, MUL-Triplex, COM-Lease, COM-Sale, Lot/Land';
    $property_types = 'Residential, Residential Lease, Lots & Acreage, Multi-Family, Commercial';
    $active_statuses = 'Active, Active Contingent, Active Kick Out, Active Option Contract, Active Under Contract, Pending';
    $sold_statuses = 'Sold, Leased';
    
    // Prepare attributes for helper functions (NEW SDK)
    $brand_id = get_option('rch_rechat_brand_id');
    
    // For rechat-root (only brand_id)
    $root_attrs = !empty($brand_id) ? 'brand_id="' . esc_attr($brand_id) . '"' : '';
    
    // Generate attributes strings for combined/active/sold sections
    $combined_attrs = rch_get_agent_listings_attrs($brand_id, $agents_string, $property_subtypes, $property_types, $active_statuses);
    $active_attrs = rch_get_agent_listings_attrs($brand_id, $agents_string, $property_subtypes, $property_types, $active_statuses);
    $sold_attrs = rch_get_agent_listings_attrs($brand_id, $agents_string, $property_subtypes, $property_types, $sold_statuses);
    
    // Get agent title for display
    $agent_title = get_the_title($post_id);
    
    // Render based on display mode
    if ($display_mode === 'combined') {
        rch_render_combined_listings($agent_title, $root_attrs, $combined_attrs);
    } elseif ($display_mode === 'separate') {
        rch_render_separate_listings($agent_title, $root_attrs, $active_attrs, $sold_attrs);
    } elseif ($display_mode === 'active-only') {
        rch_render_active_only_listings($agent_title, $root_attrs, $active_attrs);
    } elseif ($display_mode === 'sold-only') {
        rch_render_sold_only_listings($agent_title, $root_attrs, $sold_attrs);
    }
}

/**
 * Generate rechat-listings attributes for agent pages
 * 
 * @param string $brand_id Brand ID
 * @param string $agents_string Comma-separated agent IDs
 * @param string $property_subtypes Property subtypes string
 * @param string $property_types Property types string
 * @param string $listing_statuses Listing statuses string
 * @return string Formatted attributes string
 */
function rch_get_agent_listings_attrs($brand_id, $agents_string, $property_subtypes, $property_types, $listing_statuses) {
    $attrs = array();
    
    // Add brand_id (always show own brand listings on agent pages)
    if (!empty($brand_id)) {
        $attrs[] = 'brand_id="' . esc_attr($brand_id) . '"';
    }
    
    $attrs[] = 'filter_pagination_limit="9"';
    $attrs[] = 'filter_agents="' . esc_attr($agents_string) . '"';
    $attrs[] = 'filter_property_subtypes="' . esc_attr($property_subtypes) . '"';
    $attrs[] = 'filter_property_types="' . esc_attr($property_types) . '"';
    $attrs[] = 'filter_listing_statuses="' . esc_attr($listing_statuses) . '"';
    $attrs[] = 'listing_hyperlink_href="' . home_url() . '/listing-detail/{street_address}/{id}/"';
    
    return implode("\n                        ", $attrs);
}

/**
 * Render combined listings section (Active listings only)
 */
function rch_render_combined_listings($agent_title, $root_attrs, $combined_attrs) {
    ?>
    <!-- Combined Listings Section -->
    <div class="rch-agents-list rch-agents-combined-section">
        <h2><?php echo esc_html($agent_title); ?>'s Properties</h2>
        <rechat-root <?php echo $root_attrs; ?>>
            <rechat-listings <?php echo $combined_attrs; ?>>
                <div>
                    <rechat-listings-list />
                </div>

                <div class="pagination">
                    <rechat-listings-pagination />
                </div>
            </rechat-listings>
        </rechat-root>
    </div>
    <?php
}

/**
 * Render separate listings sections (Active and Sold)
 */
function rch_render_separate_listings($agent_title, $root_attrs, $active_attrs, $sold_attrs) {
    ?>
    <!-- Active Listings Section -->
    <div class="rch-agents-list rch-agents-active-section">
        <h2><?php echo esc_html($agent_title); ?>'s Active Listings</h2>
        <rechat-root <?php echo $root_attrs; ?>>
            <rechat-listings <?php echo $active_attrs; ?>>
                <div>
                    <rechat-listings-list />
                </div>

                <div class="pagination">
                    <rechat-listings-pagination />
                </div>
            </rechat-listings>
        </rechat-root>
    </div>

    <!-- Sold Listings Section -->
    <div class="rch-agents-list rch-agents-sold-section">
        <h2><?php echo esc_html($agent_title); ?>'s Sold Listings</h2>
        <rechat-root <?php echo $root_attrs; ?>>
            <rechat-listings <?php echo $sold_attrs; ?>>
                <div>
                    <rechat-listings-list />
                </div>

                <div class="pagination">
                    <rechat-listings-pagination />
                </div>
            </rechat-listings>
        </rechat-root>
    </div>
    <?php
}

/**
 * Render active listings only section
 */
function rch_render_active_only_listings($agent_title, $root_attrs, $active_attrs) {
    ?>
    <!-- Active Listings Only Section -->
    <div class="rch-agents-list rch-agents-active-section">
        <h2><?php echo esc_html($agent_title); ?>'s Active Listings</h2>
        <rechat-root <?php echo $root_attrs; ?>>
            <rechat-listings <?php echo $active_attrs; ?>>
                <div>
                    <rechat-listings-list />
                </div>

                <div class="pagination">
                    <rechat-listings-pagination />
                </div>
            </rechat-listings>
        </rechat-root>
    </div>
    <?php
}

/**
 * Render sold listings only section
 */
function rch_render_sold_only_listings($agent_title, $root_attrs, $sold_attrs) {
    ?>
    <!-- Sold Listings Only Section -->
    <div class="rch-agents-list rch-agents-sold-section">
        <h2><?php echo esc_html($agent_title); ?>'s Sold Listings</h2>
        <rechat-root <?php echo $root_attrs; ?>>
            <rechat-listings <?php echo $sold_attrs; ?>>
                <div>
                    <rechat-listings-list />
                </div>

                <div class="pagination">
                    <rechat-listings-pagination />
                </div>
            </rechat-listings>
        </rechat-root>
    </div>
    <?php
}
