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
function rch_render_agent_listings_section($post_id)
{
    // Get admin settings for listing display mode
    $display_mode = get_option('rch_listing_display_mode', 'combined'); // Default: combined

    $default_agents_string = rch_normalize_agent_listings_filter_agents_meta(
        get_post_meta($post_id, 'agents', true)
    );

    $active_agents_string = $default_agents_string;

    $property_types = 'Residential, Residential Lease, Lots & Acreage, Multi-Family, Commercial';
    $active_statuses = function_exists('rch_get_agent_single_active_listing_statuses_string')
        ? rch_get_agent_single_active_listing_statuses_string()
        : 'Active, Incoming, Coming Soon';
    $sold_statuses = function_exists('rch_get_agent_single_sold_listing_statuses')
        ? implode(', ', rch_get_agent_single_sold_listing_statuses())
        : 'Sold, Leased';

    // Prepare attributes for helper functions (NEW SDK)
    $brand_id = get_option('rch_rechat_brand_id');

    // For rechat-root (only brand_id)
    $root_attrs = !empty($brand_id) ? 'brand_id="' . esc_attr($brand_id) . '"' : '';

    // Generate attributes strings for combined/active/sold sections
    $combined_attrs = rch_get_agent_listings_attrs($active_agents_string, $property_types, $active_statuses);
    $active_attrs = rch_get_agent_listings_attrs($active_agents_string, $property_types, $active_statuses);
    $sold_attrs = rch_get_agent_listings_attrs($default_agents_string, $property_types, $sold_statuses);

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
 * Normalize agents post meta to a comma-separated filter_agents value.
 *
 * @param mixed $raw Value from get_post_meta( $post_id, 'agents', true ).
 * @return string
 */
function rch_normalize_agent_listings_filter_agents_meta($raw): string
{
    if (is_array($raw)) {
        $parts = array_filter(array_map('trim', $raw), static function ($v) {
            return $v !== '' && $v !== null;
        });

        return implode(',', $parts);
    }

    if (is_string($raw)) {
        return trim($raw);
    }

    if (is_scalar($raw) && (string) $raw !== '') {
        return trim((string) $raw);
    }

    return '';
}

/**
 * Generate rechat-listings attributes for agent pages
 * 
 * @param string $agents_string Comma-separated Rechat agent IDs for filter_agents
 * @param string $property_types Property types string
 * @param string $listing_statuses Listing statuses string
 * @return string Formatted attributes string
 */
function rch_get_agent_listings_attrs($agents_string, $property_types, $listing_statuses)
{
    $attrs = array();

    $attrs[] = 'filter_pagination_limit="10"';
    $attrs[] = 'filter_agents="' . esc_attr($agents_string) . '"';
    if ('' === trim((string) $agents_string)) {
        $attrs[] = 'disabled="true"';
    }
    $attrs[] = 'filter_property_types="' . esc_attr($property_types) . '"';
    $attrs[] = 'filter_listing_statuses="' . esc_attr($listing_statuses) . '"';
    $attrs[] = 'listing_hyperlink_href="' . home_url() . '/listing-detail/{city}/{street_address}/{id}/"';

    return implode("\n                        ", $attrs);
}

/**
 * Render combined listings section (Active listings only)
 */
function rch_render_combined_listings($agent_title, $root_attrs, $combined_attrs)
{
?>
    <!-- Combined Listings Section -->
    <div class="rch-agents-list rch-agents-combined-section" data-rechat-listings-section="active">
        <h2><?php echo esc_html($agent_title); ?>'s Properties</h2>
        <rechat-root <?php echo $root_attrs; ?> data-rechat-listings-mount>
            <rechat-listings <?php echo $combined_attrs; ?>>
                <div class="rechat-shell">
                    <rechat-map-listings-grid></rechat-map-listings-grid>
                    <rechat-listings-pagination></rechat-listings-pagination>
                </div>
            </rechat-listings>
        </rechat-root>
    </div>
<?php
}

/**
 * Render separate listings sections (Active and Sold)
 */
function rch_render_separate_listings($agent_title, $root_attrs, $active_attrs, $sold_attrs)
{
?>
    <!-- Active Listings Section -->
    <div class="rch-agents-list rch-agents-active-section" data-rechat-listings-section="active">
        <h2><?php echo esc_html($agent_title); ?>'s Active Listings</h2>
        <rechat-root <?php echo $root_attrs; ?> data-rechat-listings-mount>
            <rechat-listings <?php echo $active_attrs; ?>>
                <div class="rechat-shell">
                    <rechat-map-listings-grid></rechat-map-listings-grid>
                    <rechat-listings-pagination></rechat-listings-pagination>
                </div>
            </rechat-listings>
        </rechat-root>
    </div>

    <!-- Sold Listings Section -->
    <div class="rch-agents-list rch-agents-sold-section" data-rechat-listings-section="sold">
        <h2><?php echo esc_html($agent_title); ?>'s Sold Listings</h2>
        <rechat-root <?php echo $root_attrs; ?> data-rechat-listings-mount>
            <rechat-listings <?php echo $sold_attrs; ?>>
            <div class="rechat-shell">
                    <rechat-map-listings-grid></rechat-map-listings-grid>
                    <rechat-listings-pagination></rechat-listings-pagination>
                </div>
            </rechat-listings>
        </rechat-root>
    </div>
<?php
}

/**
 * Render active listings only section
 */
function rch_render_active_only_listings($agent_title, $root_attrs, $active_attrs)
{
?>
    <!-- Active Listings Only Section -->
    <div class="rch-agents-list rch-agents-active-section" data-rechat-listings-section="active">
        <h2><?php echo esc_html($agent_title); ?>'s Active Listings</h2>
        <rechat-root <?php echo $root_attrs; ?> data-rechat-listings-mount>
            <rechat-listings <?php echo $active_attrs; ?>>
            <div class="rechat-shell">
                    <rechat-map-listings-grid></rechat-map-listings-grid>
                    <rechat-listings-pagination></rechat-listings-pagination>
                </div>
            </rechat-listings>
        </rechat-root>
    </div>
<?php
}

/**
 * Render sold listings only section
 */
function rch_render_sold_only_listings($agent_title, $root_attrs, $sold_attrs)
{
?>
    <!-- Sold Listings Only Section -->
    <div class="rch-agents-list rch-agents-sold-section" data-rechat-listings-section="sold">
        <h2><?php echo esc_html($agent_title); ?>'s Sold Listings</h2>
        <rechat-root <?php echo $root_attrs; ?> data-rechat-listings-mount>
            <rechat-listings <?php echo $sold_attrs; ?>>
            <div class="rechat-shell">
                    <rechat-map-listings-grid></rechat-map-listings-grid>
                    <rechat-listings-pagination></rechat-listings-pagination>
                </div>
            </rechat-listings>
        </rechat-root>
    </div>
<?php
}
