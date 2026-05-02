<?php
if (! defined('ABSPATH')) {
    exit();
}

/**
 * Template labels (Template Name: …) that identify a “contact” page for schema.
 *
 * @return array<int,string>
 */
function rch_contact_page_template_labels()
{
    $labels = ['contact'];

    return array_values(
        array_unique(
            array_map(
                static function ($label) {
                    return strtolower(trim((string) $label));
                },
                (array) apply_filters('rch_contact_page_template_labels', $labels)
            )
        )
    );
}

/**
 * Whether this page uses a page template whose display name matches a contact label.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function rch_is_contact_template_page($post_id)
{
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return false;
    }

    $post = get_post($post_id);
    if (! $post instanceof WP_Post || $post->post_type !== 'page') {
        return false;
    }

    $slug = get_page_template_slug($post_id);
    if (! is_string($slug) || $slug === '' || $slug === 'default') {
        return false;
    }

    $labels = rch_contact_page_template_labels();

    $templates = wp_get_theme()->get_page_templates($post);
    if (is_array($templates) && isset($templates[ $slug ])) {
        $page_name = strtolower(trim(wp_strip_all_tags((string) $templates[ $slug ])));
        if (in_array($page_name, $labels, true)) {
            return true;
        }
    }

    $basename = strtolower(pathinfo($slug, PATHINFO_FILENAME));
    if ($basename === 'contact') {
        return (bool) apply_filters('rch_contact_page_match_by_filename', true, $slug, $post_id);
    }

    return (bool) apply_filters('rch_is_contact_template_page', false, $post_id, $slug);
}

/**
 * Resolve latitude/longitude for a contact page (ACF map-style fields, post meta, or filter).
 *
 * @param int $post_id Page ID.
 * @return array{lat:float,lng:float}|null
 */
function rch_contact_page_resolve_geo($post_id)
{
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return null;
    }

    $filtered = apply_filters('rch_contact_page_geo', null, $post_id);
    if (is_array($filtered) && isset($filtered['lat'], $filtered['lng']) && is_numeric($filtered['lat']) && is_numeric($filtered['lng'])) {
        return [
            'lat' => (float) $filtered['lat'],
            'lng' => (float) $filtered['lng'],
        ];
    }

    $lat = get_post_meta($post_id, 'rch_geo_latitude', true);
    $lng = get_post_meta($post_id, 'rch_geo_longitude', true);
    if (is_numeric($lat) && is_numeric($lng)) {
        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    if (function_exists('get_field')) {
        $map_keys = ['map', 'location', 'google_map', 'address_map', 'office_location', 'contact_map', 'geo'];
        foreach ($map_keys as $key) {
            $m = get_field($key, $post_id);
            if (is_array($m) && isset($m['lat'], $m['lng']) && is_numeric($m['lat']) && is_numeric($m['lng'])) {
                return ['lat' => (float) $m['lat'], 'lng' => (float) $m['lng']];
            }
        }
    }

    return null;
}

/**
 * Plain-text address line for PostalAddress when available.
 *
 * @param int $post_id Page ID.
 * @return string
 */
function rch_contact_page_resolve_address_text($post_id)
{
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return '';
    }

    $text = apply_filters('rch_contact_page_address_text', '', $post_id);
    if (is_string($text) && trim($text) !== '') {
        return sanitize_text_field($text);
    }

    if (function_exists('get_field')) {
        $v = get_field('address', $post_id);
        if (is_string($v) && trim($v) !== '') {
            return sanitize_text_field($v);
        }
        if (is_array($v) && isset($v['address']) && is_string($v['address'])) {
            return sanitize_text_field($v['address']);
        }
    }

    $meta = get_post_meta($post_id, 'rch_contact_address', true);
    if (is_string($meta) && trim($meta) !== '') {
        return sanitize_text_field($meta);
    }

    return '';
}

/**
 * Build ContactPage (+ optional geo) JSON-LD for a contact template page.
 *
 * @param int $post_id Page ID.
 * @return array<string,mixed>
 */
function rch_build_contact_page_geo_schema($post_id)
{
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return [];
    }

    $url = get_permalink($post_id);
    if (! is_string($url) || $url === '') {
        return [];
    }

    $title = get_the_title($post_id);
    $title = is_string($title) ? wp_strip_all_tags($title) : '';

    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'ContactPage',
        'name'     => $title !== '' ? $title : __('Contact', 'rechat-plugin'),
        'url'      => esc_url_raw($url),
    ];

    $excerpt = get_post_field('post_excerpt', $post_id);
    if (is_string($excerpt) && trim($excerpt) !== '') {
        $schema['description'] = wp_strip_all_tags($excerpt);
    }

    $geo = rch_contact_page_resolve_geo($post_id);
    $address_text = rch_contact_page_resolve_address_text($post_id);

    $telephone = '';
    $email     = '';
    if (function_exists('get_field')) {
        $p = get_field('phone', $post_id);
        if (is_string($p) && trim($p) !== '') {
            $telephone = sanitize_text_field($p);
        }
        $e = get_field('email', $post_id);
        if (is_string($e) && is_email($e)) {
            $email = sanitize_email($e);
        }
    }
    if ($telephone === '') {
        $telephone = sanitize_text_field((string) get_post_meta($post_id, 'rch_contact_phone', true));
    }
    if ($email === '') {
        $em = get_post_meta($post_id, 'rch_contact_email', true);
        if (is_string($em) && is_email($em)) {
            $email = sanitize_email($em);
        }
    }

    $main_entity = [
        '@type' => 'LocalBusiness',
        'name'  => get_bloginfo('name'),
        'url'   => esc_url_raw(home_url('/')),
    ];
    if ($telephone !== '') {
        $main_entity['telephone'] = $telephone;
    }
    if ($email !== '') {
        $main_entity['email'] = $email;
    }
    if ($address_text !== '') {
        $main_entity['address'] = [
            '@type'          => 'PostalAddress',
            'streetAddress'  => $address_text,
            'addressCountry' => apply_filters('rch_contact_page_address_country', 'US', $post_id),
        ];
    }
    if ($geo !== null) {
        $main_entity['geo'] = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => $geo['lat'],
            'longitude' => $geo['lng'],
        ];
    }

    $schema['mainEntity'] = $main_entity;

    return $schema;
}

/**
 * Output ContactPage JSON-LD on pages using the contact template.
 *
 * @return void
 */
function rch_output_contact_page_geo_jsonld()
{
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return;
    }
    if (! is_singular('page')) {
        return;
    }

    $post_id = (int) get_queried_object_id();
    if ($post_id < 1 || ! rch_is_contact_template_page($post_id)) {
        return;
    }

    $schema = rch_build_contact_page_geo_schema($post_id);

    /**
     * Filter ContactPage / geo JSON-LD before output.
     *
     * @param array $schema  Schema graph node.
     * @param int   $post_id Page ID.
     */
    $schema = apply_filters('rch_contact_page_geo_schema', $schema, $post_id);

    if (empty($schema) || ! is_array($schema)) {
        return;
    }

    $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (! is_string($json) || $json === '') {
        return;
    }

    echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
}
add_action('wp_head', 'rch_output_contact_page_geo_jsonld', 22);
