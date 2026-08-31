<?php

/**
 * Off Market — field registry + listing-detail adapter.
 *
 * ONE source of truth for the Off Market feature. The registry drives:
 *   - the admin meta boxes (render + save)     — metaboxes-for-off-market.php
 *   - the single-page data shape               — rch_off_market_build_listing_detail()
 *
 * Off Market listings are entered manually in wp-admin and carry NO Rechat API
 * dependency. The adapter reshapes the flat post meta into the SAME nested
 * `$listing_detail` array the API-backed listing detail template parts expect,
 * so the single template can reuse those parts verbatim.
 *
 * Add / change a field in ONE place — the $registry array below — and it flows
 * to the admin UI, the save handler, and (via its `map`) the frontend.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit();
}

/**
 * Off Market post type slug — single source.
 */
if (! defined('RCH_OFF_MARKET_CPT')) {
    define('RCH_OFF_MARKET_CPT', 'off_market');
}

/**
 * Field registry.
 *
 * Each entry:
 *   key     => meta key (stored in post meta, unprefixed like the other CPTs)
 *   label   => admin label
 *   type    => text | number | textarea | select | date | images
 *   group   => meta box section this field renders under
 *   options => (select only) value => label
 *   desc    => (optional) admin help text
 *
 * Frontend mapping is handled in rch_off_market_build_listing_detail() so the
 * nested `$listing_detail` shape stays explicit and readable.
 *
 * @return array[]
 */
function rch_off_market_get_fields()
{
    static $registry = null;
    if ($registry !== null) {
        return $registry;
    }

    $registry = array(

        /* ---------------- Identity / status ---------------- */
        array('key' => 'status',        'label' => 'Status',        'type' => 'select', 'group' => 'identity',
            'options' => array('Active' => 'Active', 'Coming Soon' => 'Coming Soon', 'Pending' => 'Pending', 'Sold' => 'Sold', 'Off Market' => 'Off Market'),
            'desc' => 'Shown as the badge on the gallery and archive card.'),
        array('key' => 'mls_number',    'label' => 'MLS Number',    'type' => 'text',   'group' => 'identity'),
        array('key' => 'rechat_id',     'label' => 'Rechat ID (UUID, optional)', 'type' => 'text', 'group' => 'identity',
            'desc' => 'Optional. Off Market works with no API — leave blank if unknown.'),
        array('key' => 'list_date',     'label' => 'List Date',     'type' => 'date',   'group' => 'identity'),
        array('key' => 'sold_date',     'label' => 'Sold Date',     'type' => 'date',   'group' => 'identity'),

        /* ---------------- Price ---------------- */
        array('key' => 'price',         'label' => 'Price',         'type' => 'number', 'group' => 'price',
            'desc' => 'Numbers only, e.g. 214900. Formatting is added automatically.'),
        array('key' => 'currency',      'label' => 'Currency',      'type' => 'text',   'group' => 'price',
            'desc' => 'Currency symbol/code (default $).'),

        /* ---------------- Address ---------------- */
        array('key' => 'address_line',  'label' => 'Street Address', 'type' => 'text',  'group' => 'address'),
        array('key' => 'city',          'label' => 'City',          'type' => 'text',   'group' => 'address'),
        array('key' => 'state',         'label' => 'State',         'type' => 'text',   'group' => 'address'),
        array('key' => 'postal_code',   'label' => 'Postal Code',   'type' => 'text',   'group' => 'address'),
        array('key' => 'latitude',      'label' => 'Latitude',      'type' => 'text',   'group' => 'address'),
        array('key' => 'longitude',     'label' => 'Longitude',     'type' => 'text',   'group' => 'address'),

        /* ---------------- Key facts ---------------- */
        array('key' => 'bedrooms',      'label' => 'Bedrooms',      'type' => 'number', 'group' => 'facts'),
        array('key' => 'bathrooms',     'label' => 'Bathrooms',     'type' => 'text',   'group' => 'facts', 'desc' => 'Whole or decimal, e.g. 2 or 2.5.'),
        array('key' => 'square_feet',   'label' => 'Living Space (SqFt)', 'type' => 'number', 'group' => 'facts'),
        array('key' => 'lot_size_sqft', 'label' => 'Lot Size (SqFt)', 'type' => 'number', 'group' => 'facts'),
        array('key' => 'year_built',    'label' => 'Year Built',    'type' => 'number', 'group' => 'facts'),
        array('key' => 'property_type', 'label' => 'Property Type', 'type' => 'text',   'group' => 'facts'),
        array('key' => 'property_subtype', 'label' => 'Property Subtype', 'type' => 'text', 'group' => 'facts'),

        /* ---------------- Gallery ---------------- */
        array('key' => 'gallery_image_urls', 'label' => 'Gallery Image URLs', 'type' => 'images', 'group' => 'gallery',
            'desc' => 'One image URL per line. The first image is used as the cover. If left blank, the Featured Image is used.'),

        /* ---------------- Description ---------------- */
        // Description comes from the main post editor (post_content) — see adapter.

        /* ---------------- Features (all optional, hidden when empty) ---------------- */
        array('key' => 'heating',            'label' => 'Heating',            'type' => 'text', 'group' => 'features', 'desc' => 'Comma-separated.'),
        array('key' => 'cooling',            'label' => 'Cooling',            'type' => 'text', 'group' => 'features', 'desc' => 'Comma-separated.'),
        array('key' => 'flooring',           'label' => 'Flooring',           'type' => 'text', 'group' => 'features', 'desc' => 'Comma-separated.'),
        array('key' => 'appliances',         'label' => 'Appliances',         'type' => 'text', 'group' => 'features', 'desc' => 'Comma-separated.'),
        array('key' => 'interior_features',  'label' => 'Interior Features',  'type' => 'text', 'group' => 'features', 'desc' => 'Comma-separated.'),
        array('key' => 'exterior_features',  'label' => 'Exterior Features',  'type' => 'text', 'group' => 'features', 'desc' => 'Comma-separated.'),
        array('key' => 'pool_features',      'label' => 'Pool Features',      'type' => 'text', 'group' => 'features', 'desc' => 'Comma-separated.'),
        array('key' => 'roof',               'label' => 'Roof',               'type' => 'text', 'group' => 'features', 'desc' => 'Comma-separated.'),
        array('key' => 'construction_materials', 'label' => 'Construction Materials', 'type' => 'text', 'group' => 'features', 'desc' => 'Comma-separated.'),
        array('key' => 'architectural_style', 'label' => 'Architectural Style', 'type' => 'text', 'group' => 'features'),
        array('key' => 'parking_spaces',     'label' => 'Parking Spaces',     'type' => 'text', 'group' => 'features'),
        array('key' => 'parking_features',   'label' => 'Parking Features',   'type' => 'text', 'group' => 'features', 'desc' => 'Comma-separated.'),
        array('key' => 'subdivision_name',   'label' => 'Subdivision',        'type' => 'text', 'group' => 'features'),
        array('key' => 'school_district',    'label' => 'School District',    'type' => 'text', 'group' => 'features'),
        array('key' => 'elementary_school_name', 'label' => 'Elementary School', 'type' => 'text', 'group' => 'features'),
        array('key' => 'middle_school_name', 'label' => 'Middle School',      'type' => 'text', 'group' => 'features'),
        array('key' => 'high_school_name',   'label' => 'High School',        'type' => 'text', 'group' => 'features'),

        /* ---------------- Agent / disclaimer ---------------- */
        array('key' => 'agent_name',    'label' => 'Listing Agent Name', 'type' => 'text', 'group' => 'agent'),
        array('key' => 'agent_id',      'label' => 'Listing Agent Rechat ID (optional)', 'type' => 'text', 'group' => 'agent'),
        array('key' => 'disclaimer',    'label' => 'MLS Disclaimer',    'type' => 'textarea', 'group' => 'agent'),
    );

    return apply_filters('rch_off_market_fields', $registry);
}

/**
 * Meta box sections (group key => title). Order = display order.
 *
 * @return array
 */
function rch_off_market_get_groups()
{
    return apply_filters('rch_off_market_groups', array(
        'identity' => __('Status & Identity', 'rechat-plugin'),
        'price'    => __('Price', 'rechat-plugin'),
        'address'  => __('Address', 'rechat-plugin'),
        'facts'    => __('Key Facts', 'rechat-plugin'),
        'gallery'  => __('Gallery', 'rechat-plugin'),
        'features' => __('Features', 'rechat-plugin'),
        'agent'    => __('Agent & Disclaimer', 'rechat-plugin'),
    ));
}

/**
 * Read a single off-market meta value (trimmed string).
 *
 * @param int    $post_id
 * @param string $key
 * @return string
 */
function rch_off_market_meta($post_id, $key)
{
    return trim((string) get_post_meta($post_id, $key, true));
}

/**
 * Parse the gallery textarea (one URL per line) into a clean URL list.
 *
 * @param int $post_id
 * @return string[]
 */
function rch_off_market_gallery_urls($post_id)
{
    $raw = (string) get_post_meta($post_id, 'gallery_image_urls', true);
    $urls = array();
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ($line !== '' && filter_var($line, FILTER_VALIDATE_URL)) {
            $urls[] = $line;
        }
    }
    // Fall back to the Featured Image when no gallery URLs are provided.
    if (empty($urls) && has_post_thumbnail($post_id)) {
        $thumb = get_the_post_thumbnail_url($post_id, 'full');
        if ($thumb) {
            $urls[] = $thumb;
        }
    }
    return $urls;
}

/**
 * Build the API-shaped `$listing_detail` array from an Off Market post's meta.
 *
 * Only keys that actually have values are populated, so the reused template
 * parts (which guard on isset/!empty) naturally render nothing for blanks —
 * no empty labels, values, icons, or containers.
 *
 * @param int $post_id
 * @return array
 */
function rch_off_market_build_listing_detail($post_id)
{
    $detail = array();
    $formatted = array();
    $property = array();

    // --- Price ---
    $price = rch_off_market_meta($post_id, 'price');
    if ($price !== '' && is_numeric(str_replace(',', '', $price))) {
        $price_num = floatval(str_replace(',', '', $price));
        $symbol = rch_off_market_meta($post_id, 'currency');
        $symbol = $symbol !== '' ? $symbol : '$';
        $formatted['price'] = array(
            'text'  => $symbol . number_format($price_num),
            'value' => $price_num,
        );
    }

    // --- Address ---
    $address_bits = array_filter(array(
        rch_off_market_meta($post_id, 'address_line'),
        trim(implode(', ', array_filter(array(
            rch_off_market_meta($post_id, 'city'),
            trim(rch_off_market_meta($post_id, 'state') . ' ' . rch_off_market_meta($post_id, 'postal_code')),
        )))),
    ));
    if (! empty($address_bits)) {
        $formatted['full_address'] = array('text' => implode(', ', $address_bits));
    }

    // --- MLS + status + id ---
    $mls = rch_off_market_meta($post_id, 'mls_number');
    if ($mls !== '') {
        $detail['mls_number'] = $mls;
    }
    $status = rch_off_market_meta($post_id, 'status');
    if ($status !== '') {
        $detail['status'] = $status;
    }
    $rechat_id = rch_off_market_meta($post_id, 'rechat_id');
    $detail['id'] = $rechat_id !== '' ? $rechat_id : 'off-market-' . $post_id;

    // --- Beds / baths / sqft / lot ---
    $bedrooms = rch_off_market_meta($post_id, 'bedrooms');
    if ($bedrooms !== '') {
        $formatted['bedroom_count'] = array('text' => $bedrooms . ' Beds', 'text_no_label' => $bedrooms);
    }
    $bathrooms = rch_off_market_meta($post_id, 'bathrooms');
    if ($bathrooms !== '') {
        $formatted['total_bathroom_count'] = array('text' => $bathrooms . ' Baths', 'text_no_label' => $bathrooms);
        $formatted['bathrooms'] = array('text' => $bathrooms . ' Baths', 'text_no_label' => $bathrooms);
    }
    $sqft = rch_off_market_meta($post_id, 'square_feet');
    if ($sqft !== '' && is_numeric(str_replace(',', '', $sqft))) {
        $sqft_num = floatval(str_replace(',', '', $sqft));
        $formatted['square_feet'] = array(
            'text'          => number_format($sqft_num) . ' Sqft',
            'text_no_label' => number_format($sqft_num),
            'value'         => $sqft_num,
        );
    }
    $lot = rch_off_market_meta($post_id, 'lot_size_sqft');
    if ($lot !== '' && is_numeric(str_replace(',', '', $lot))) {
        $lot_num = floatval(str_replace(',', '', $lot));
        $formatted['lot_size_square_feet'] = array(
            'text_no_label' => number_format($lot_num),
            'value'         => $lot_num,
        );
        if ($lot_num > 43560) {
            $formatted['lot_size_acres'] = array('text_no_label' => number_format($lot_num / 43560, 2));
        }
    }
    $parking = rch_off_market_meta($post_id, 'parking_spaces');
    if ($parking !== '') {
        $formatted['parking_spaces'] = array('text' => $parking, 'text_no_label' => $parking);
    }

    // --- Property scalar fields ---
    $property_map = array(
        'year_built', 'property_type', 'property_subtype', 'heating', 'cooling',
        'flooring', 'appliances', 'interior_features', 'exterior_features',
        'pool_features', 'roof', 'construction_materials', 'architectural_style',
        'parking_features', 'subdivision_name', 'school_district',
        'elementary_school_name', 'middle_school_name', 'high_school_name',
    );
    foreach ($property_map as $key) {
        $val = rch_off_market_meta($post_id, $key);
        if ($val !== '') {
            $property[$key] = $val;
        }
    }

    // --- Description from the post editor ---
    $post = get_post($post_id);
    if ($post && trim((string) $post->post_content) !== '') {
        $property['description'] = apply_filters('the_content', $post->post_content);
    }

    // --- Gallery ---
    $gallery = rch_off_market_gallery_urls($post_id);
    if (! empty($gallery)) {
        $detail['gallery_image_urls'] = $gallery;
        $detail['cover_image_url']    = $gallery[0];
    }

    // --- Agent (reuses the listing-agents "courtesy" fallback line) ---
    $agent_name = rch_off_market_meta($post_id, 'agent_name');
    if ($agent_name !== '') {
        $formatted['courtesy'] = array('text' => $agent_name);
    }
    $agent_id = rch_off_market_meta($post_id, 'agent_id');
    if ($agent_id !== '') {
        $detail['list_agent'] = array('id' => $agent_id);
    }

    // --- Disclaimer ---
    $disclaimer = rch_off_market_meta($post_id, 'disclaimer');
    if ($disclaimer !== '') {
        $detail['mls_info'] = array('disclaimer' => $disclaimer);
    }

    // No open houses for manual listings.
    $detail['open_houses'] = array();

    if (! empty($formatted)) {
        $detail['formatted'] = $formatted;
    }
    if (! empty($property)) {
        $detail['property'] = $property;
    }

    return apply_filters('rch_off_market_listing_detail', $detail, $post_id);
}
