<?php
/**
 * Multisite: agent subsite listing scope + Rechat credential fallback.
 *
 * - Subsites mapped to an agent post use main-site `agents` post meta as global filter_agents.
 * - When this blog has no Rechat OAuth options, brand/access/refresh fall back to the main site.
 *
 * Hub (main site) WordPress options used for fallback and for reading hub values:
 * - `rch_rechat_brand_id`
 * - `rch_rechat_google_map_api_key`
 * (also: `rch_rechat_access_token`, `rch_rechat_refresh_token`)
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('is_multisite') || ! is_multisite()) {
    return;
}

/**
 * Whether this request should apply agent-only listing scoping (not main hub, not office-only).
 */
function rch_multisite_is_agent_listing_scope_active(): bool
{
    if (! is_multisite() || get_current_blog_id() === (int) get_main_site_id()) {
        return false;
    }

    return function_exists('rch_is_rechat_agent_only_subsite') && rch_is_rechat_agent_only_subsite();
}

/**
 * Resolve the main-site agent CPT post ID linked to the current blog.
 */
function rch_multisite_resolve_agent_post_id_for_current_blog(): int
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $cached = 0;

    if (! is_multisite()) {
        return $cached;
    }

    $blog_id = get_current_blog_id();
    $main_id  = (int) get_main_site_id();

    if ($blog_id <= 0 || $blog_id === $main_id) {
        return $cached;
    }

    switch_to_blog($main_id);

    $agent_match = new WP_Query([
        'post_type'              => 'agents',
        'post_status'            => 'any',
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => [
            [
                'key'   => '_rch_agent_site_id',
                'value' => (string) $blog_id,
            ],
        ],
    ]);

    if ($agent_match->have_posts()) {
        $cached = (int) $agent_match->posts[0];
    }

    restore_current_blog();

    return $cached;
}

/**
 * Normalize main-site `agents` post meta to a comma-separated string for SDK / API.
 *
 * @param mixed $raw Value from get_post_meta(…, 'agents', true).
 */
function rch_multisite_normalize_agents_meta_to_csv($raw): string
{
    if (is_array($raw)) {
        $parts = array_filter(array_map('trim', $raw), static function ($v) {
            return $v !== '' && $v !== null;
        });

        return implode(',', $parts);
    }

    if (is_string($raw)) {
        $raw = trim($raw);
        if ($raw !== '' && $raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return rch_multisite_normalize_agents_meta_to_csv($decoded);
            }
        }

        return $raw;
    }

    if (is_scalar($raw) && (string) $raw !== '') {
        return trim((string) $raw);
    }

    return '';
}

/**
 * Comma-separated Rechat agent IDs for the current agent subsite (from main site meta `agents`).
 */
function rch_multisite_get_main_site_agent_ids_csv(): string
{
    static $csv = null;

    if ($csv !== null) {
        return $csv;
    }

    $csv = '';

    if (! rch_multisite_is_agent_listing_scope_active()) {
        return $csv;
    }

    $agent_post_id = rch_multisite_resolve_agent_post_id_for_current_blog();

    if ($agent_post_id <= 0) {
        return $csv;
    }

    $main_id = (int) get_main_site_id();
    switch_to_blog($main_id);
    $raw = get_post_meta($agent_post_id, 'agents', true);
    restore_current_blog();

    $csv = rch_multisite_normalize_agents_meta_to_csv($raw);

    return $csv;
}

/**
 * True when this blog is not the main site and the given option value is empty.
 */
function rch_multisite_rechat_option_is_empty($value): bool
{
    return $value === false || $value === null || $value === '';
}

/**
 * Fallback main-site Rechat option for subsites with missing credentials.
 *
 * @param mixed  $value Local option value.
 * @param string $option Option name.
 * @return mixed
 */
function rch_multisite_fallback_rechat_option($value, string $option)
{
    if (! is_multisite()) {
        return $value;
    }

    $main_id = (int) get_main_site_id();
    if (get_current_blog_id() === $main_id) {
        return $value;
    }

    if (! rch_multisite_rechat_option_is_empty($value)) {
        return $value;
    }

    switch_to_blog($main_id);
    $main_value = get_option($option, false);
    restore_current_blog();

    if (! rch_multisite_rechat_option_is_empty($main_value)) {
        return $main_value;
    }

    return $value;
}

/**
 * Read an option from the network main site (after switch_to_blog), cached per request.
 *
 * @param string $option Option name.
 * @return mixed
 */
function rch_multisite_get_hub_site_option(string $option)
{
    static $cache = [];

    if (array_key_exists($option, $cache)) {
        return $cache[$option];
    }

    if (! is_multisite()) {
        $cache[$option] = get_option($option, false);

        return $cache[$option];
    }

    $main_id = (int) get_main_site_id();
    switch_to_blog($main_id);
    $cache[$option] = get_option($option, false);
    restore_current_blog();

    return $cache[$option];
}

add_filter('option_rch_rechat_brand_id', static function ($value) {
    return rch_multisite_fallback_rechat_option($value, 'rch_rechat_brand_id');
}, 5);

add_filter('option_rch_rechat_access_token', static function ($value) {
    return rch_multisite_fallback_rechat_option($value, 'rch_rechat_access_token');
}, 5);

add_filter('option_rch_rechat_refresh_token', static function ($value) {
    return rch_multisite_fallback_rechat_option($value, 'rch_rechat_refresh_token');
}, 5);

add_filter('option_rch_rechat_google_map_api_key', static function ($value) {
    return rch_multisite_fallback_rechat_option($value, 'rch_rechat_google_map_api_key');
}, 5);

/**
 * Late pass: other code can empty options after priority 5; re-apply hub fallback.
 */
add_filter('option_rch_rechat_brand_id', static function ($value) {
    return rch_multisite_fallback_rechat_option($value, 'rch_rechat_brand_id');
}, 999);

add_filter('option_rch_rechat_google_map_api_key', static function ($value) {
    return rch_multisite_fallback_rechat_option($value, 'rch_rechat_google_map_api_key');
}, 999);

add_filter('option_rch_rechat_access_token', static function ($value) {
    return rch_multisite_fallback_rechat_option($value, 'rch_rechat_access_token');
}, 999);

add_filter('option_rch_rechat_refresh_token', static function ($value) {
    return rch_multisite_fallback_rechat_option($value, 'rch_rechat_refresh_token');
}, 999);

/**
 * [listings] and Gutenberg block (via [listings]): inject filter_agents after shortcode_atts merge.
 */
add_filter('shortcode_atts_listings', static function ($out, $pairs, $atts) {
    unset($pairs, $atts);

    if (! rch_multisite_is_agent_listing_scope_active()) {
        return $out;
    }

    $csv = rch_multisite_get_main_site_agent_ids_csv();
    if ($csv === '') {
        return $out;
    }

    $out['filter_agents'] = $csv;

    return $out;
}, 10, 3);

/**
 * [rch_latest_listings] does not pass a shortcode name into shortcode_atts(), so we proxy the handler.
 */
function rch_multisite_proxy_latest_listings_shortcode($atts)
{
    $atts = is_array($atts) ? $atts : [];

    if (rch_multisite_is_agent_listing_scope_active()) {
        $csv = rch_multisite_get_main_site_agent_ids_csv();
        if ($csv !== '') {
            $atts['filter_agents'] = $csv;
        }
    }

    if (! function_exists('rch_display_latest_listings_shortcode')) {
        return '';
    }

    return rch_display_latest_listings_shortcode($atts);
}

add_action('init', static function () {
    if (! is_multisite()) {
        return;
    }

    if (! function_exists('rch_display_latest_listings_shortcode')) {
        return;
    }

    remove_shortcode('rch_latest_listings');
    add_shortcode('rch_latest_listings', 'rch_multisite_proxy_latest_listings_shortcode');
}, 20);

/**
 * Legacy archive AJAX: ensure valerts body includes agents[] for this subsite.
 */
function rch_multisite_prime_agents_for_listing_ajax(): void
{
    if (! rch_multisite_is_agent_listing_scope_active()) {
        return;
    }

    $csv = rch_multisite_get_main_site_agent_ids_csv();
    if ($csv === '') {
        return;
    }

    $_POST['agents'] = $csv;
}

add_action('wp_ajax_rch_fetch_listing', 'rch_multisite_prime_agents_for_listing_ajax', 0);
add_action('wp_ajax_nopriv_rch_fetch_listing', 'rch_multisite_prime_agents_for_listing_ajax', 0);
add_action('wp_ajax_rch_fetch_total_listing_count', 'rch_multisite_prime_agents_for_listing_ajax', 0);
add_action('wp_ajax_nopriv_rch_fetch_total_listing_count', 'rch_multisite_prime_agents_for_listing_ajax', 0);

/**
 * Inject or replace filter_agents on all <rechat-listings> tags (search form, widgets, FSE, etc.).
 *
 * @param string $html Full page HTML.
 * @return string
 */
function rch_multisite_ob_inject_filter_agents($html)
{
    if (! is_string($html) || $html === '') {
        return $html;
    }

    if (! rch_multisite_is_agent_listing_scope_active()) {
        return $html;
    }

    $csv = rch_multisite_get_main_site_agent_ids_csv();
    if ($csv === '') {
        return $html;
    }

    $attr = 'filter_agents="' . esc_attr($csv) . '"';

    return (string) preg_replace_callback(
        '/<rechat-listings\b([^>]*)>/i',
        static function ($m) use ($attr) {
            $inner = $m[1];
            if (preg_match('/\bfilter_agents\s*=/i', $inner)) {
                $inner = (string) preg_replace(
                    '/\sfilter_agents\s*=\s*(["\'])[^"\']*\1/i',
                    ' ' . $attr,
                    $inner,
                    1
                );

                return '<rechat-listings' . $inner . '>';
            }

            $prefix = ($inner !== '' && $inner[0] !== ' ') ? ' ' : '';

            return '<rechat-listings' . $inner . $prefix . $attr . '>';
        },
        $html
    );
}

/**
 * Start whole-page buffering so search shortcode and stray SDK markup get filter_agents.
 */
function rch_multisite_start_agent_listing_scope_buffer(): void
{
    if (is_admin() && ! is_customize_preview()) {
        return;
    }

    if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || wp_is_json_request()) {
        return;
    }

    if (is_feed()) {
        return;
    }

    if (! rch_multisite_is_agent_listing_scope_active()) {
        return;
    }

    if (rch_multisite_get_main_site_agent_ids_csv() === '') {
        return;
    }

    if (! empty($GLOBALS['rch_multisite_listing_scope_buffer_started'])) {
        return;
    }

    ob_start('rch_multisite_ob_inject_filter_agents');
    $GLOBALS['rch_multisite_listing_scope_buffer_started'] = true;
}

add_action('template_redirect', 'rch_multisite_start_agent_listing_scope_buffer', 0);
