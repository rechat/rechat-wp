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

    // Fields are limited to the agreed Off Market data table. Nothing else.
    $registry = array(

        /* ---------------- Identity ---------------- */
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

        /* ---------------- Gallery ---------------- */
        array('key' => 'gallery_image_urls', 'label' => 'Gallery Images', 'type' => 'gallery', 'group' => 'gallery',
            'desc' => 'Upload from the Media Library. The first image is the cover. Drag to reorder. If left empty, the Featured Image is used.'),

        /* ---------------- Agent ----------------
         * Pick an existing site Agent. We store the selected Agent's WP post ID
         * in `agent_id`; the Agent's name is mirrored into `agent_name` on save.
         * The single page renders this Agent with the same card as the listing
         * agent / seller agent on the API listing detail page.
         */
        array('key' => 'agent_id',      'label' => 'Agent',         'type' => 'agent_select', 'group' => 'agent',
            'desc' => 'Select an agent from this site. Their profile card (photo, license, phone, email) shows on the listing.'),
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
        'identity' => __('Identity', 'rechat-plugin'),
        'price'    => __('Price', 'rechat-plugin'),
        'address'  => __('Address', 'rechat-plugin'),
        'facts'    => __('Key Facts', 'rechat-plugin'),
        'gallery'  => __('Gallery', 'rechat-plugin'),
        'agent'    => __('Agent', 'rechat-plugin'),
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
 * Resolve the gallery into a clean URL list.
 *
 * The stored meta is a comma- or newline-separated list of tokens. Each token
 * is either a Media Library attachment ID (numeric — the normal case from the
 * media picker) or a raw image URL (legacy / pasted). Falls back to the
 * Featured Image when nothing is set.
 *
 * @param int    $post_id
 * @param string $size    Image size for attachment IDs (default 'full').
 * @return string[]
 */
function rch_off_market_gallery_urls($post_id, $size = 'full')
{
    $raw = (string) get_post_meta($post_id, 'gallery_image_urls', true);
    $urls = array();

    foreach (preg_split('/[\r\n,]+/', $raw) as $token) {
        $token = trim($token);
        if ($token === '') {
            continue;
        }
        if (ctype_digit($token)) {
            $src = wp_get_attachment_image_url((int) $token, $size);
            if ($src) {
                $urls[] = $src;
            }
        } elseif (filter_var($token, FILTER_VALIDATE_URL)) {
            $urls[] = $token;
        }
    }

    // Fall back to the Featured Image when no gallery images are provided.
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

    // --- Listing id (rechat_id if provided, else synthetic) ---
    $rechat_id = rch_off_market_meta($post_id, 'rechat_id');
    $detail['id'] = $rechat_id !== '' ? $rechat_id : 'off-market-' . $post_id;

    // --- Beds / baths / sqft ---
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

    // --- Coordinates (feeds the LocalLogic LocalContent widget) ---
    $lat = rch_off_market_meta($post_id, 'latitude');
    $lng = rch_off_market_meta($post_id, 'longitude');
    if ($lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng)) {
        $property['address']['location'] = array(
            'latitude'  => $lat,
            'longitude' => $lng,
        );
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

    // Agent card is rendered by the single template from the selected Agent
    // post (see off-market-single-custom.php) — not from $listing_detail.

    // Gallery badge (reused part reads status) — no status field, keep it blank.
    $detail['status'] = '';

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
