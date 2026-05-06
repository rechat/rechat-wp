<?php

/**
 * Multisite: hub brand/map fallback + markup patching on subsites.
 *
 * - OAuth tokens (`rch_rechat_access_token`, `rch_rechat_refresh_token`) are per-blog only; subsites
 *   do not inherit the main site connection — each site uses Connect To Rechat on that blog.
 * - When local brand or map API key is empty, those options still fall back to the main site DB value.
 * - Output buffer on subsites with empty local brand or map patches `<rechat-root>` / `<rechat-listings>`.
 *
 * Hub options used for non-OAuth fallback / raw markup patch:
 * - `rch_rechat_brand_id`
 * - `rch_rechat_google_map_api_key`
 *
 * Requires a real WordPress multisite network (`is_multisite()`). Separate single-site installs
 * are not supported here. You do not need to recreate agent subsites when hub options exist;
 * use Network → Rechat → Multisite tools if the agent site link meta is missing.
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
 * True when this blog is not the main site and the given option value is empty.
 */
function rch_multisite_rechat_option_is_empty($value): bool
{
    return $value === false || $value === null || $value === '';
}

/**
 * Read option_value from a site's options table (bypasses get_option / object cache).
 * Use for hub fallback when subsite shows empty brand/map in markup.
 *
 * @param int    $blog_id      Blog ID.
 * @param string $option_name Option name (e.g. rch_rechat_brand_id).
 * @return string Unserialized scalar string, or empty string when missing / non-scalar.
 */
function rch_multisite_fetch_raw_option_value_for_blog(int $blog_id, string $option_name): string
{
    if ($blog_id <= 0 || $option_name === '') {
        return '';
    }

    global $wpdb;

    $row = null;

    try {
        switch_to_blog($blog_id);
        $row = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $option_name
            )
        );
    } finally {
        restore_current_blog();
    }

    if ($row === null || $row === '') {
        return '';
    }

    $unpacked = maybe_unserialize($row);

    if (is_string($unpacked)) {
        return $unpacked;
    }

    if (is_scalar($unpacked)) {
        return (string) $unpacked;
    }

    return '';
}

/**
 * Short-circuit empty subsite options with hub DB values (runs before alloptions cache).
 */
function rch_multisite_pre_option_hub_fallback($pre, string $option_name)
{
    unset($pre);

    if (! is_multisite()) {
        return false;
    }

    $main_id = (int) get_main_site_id();
    $here    = get_current_blog_id();

    if ($here <= 0 || $here === $main_id) {
        return false;
    }

    $local = rch_multisite_fetch_raw_option_value_for_blog($here, $option_name);
    if ($local !== '') {
        return false;
    }

    $hub = rch_multisite_fetch_raw_option_value_for_blog($main_id, $option_name);

    return $hub !== '' ? $hub : false;
}

add_filter('pre_option_rch_rechat_brand_id', static function ($pre, $option, $default) {
    unset($option, $default);

    return rch_multisite_pre_option_hub_fallback($pre, 'rch_rechat_brand_id');
}, 5, 3);

add_filter('pre_option_rch_rechat_google_map_api_key', static function ($pre, $option, $default) {
    unset($option, $default);

    return rch_multisite_pre_option_hub_fallback($pre, 'rch_rechat_google_map_api_key');
}, 5, 3);

/**
 * Fallback main-site Rechat option for subsites when local value empty (brand / map only; not OAuth).
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

/**
 * Safe preg_replace_callback: never return null (avoids wiping the whole page on PCRE / UTF-8 errors).
 *
 * @param string   $pattern  Regex pattern.
 * @param callable $callback Replacement callback.
 * @param string   $subject  HTML / text.
 * @return string
 */
function rch_multisite_preg_replace_callback_safe(string $pattern, callable $callback, string $subject): string
{
    if ($subject === '') {
        return $subject;
    }

    $result = @preg_replace_callback($pattern, $callback, $subject);

    return is_string($result) ? $result : $subject;
}

/**
 * Safe preg_replace wrapper.
 *
 * @param string|string[] $pattern     Regex or array.
 * @param string|string[] $replacement Replacement.
 * @param string|string[] $subject     Subject string.
 * @param int             $limit       Optional limit (default -1 = no limit).
 * @return string
 */
function rch_multisite_preg_replace_safe($pattern, $replacement, $subject, int $limit = -1): string
{
    if (! is_string($subject) || $subject === '') {
        return is_string($subject) ? $subject : '';
    }

    $result = $limit >= 0
        ? @preg_replace($pattern, $replacement, $subject, $limit)
        : @preg_replace($pattern, $replacement, $subject);

    return is_string($result) ? $result : $subject;
}

/**
 * Fix empty brand_id on rechat-root / rechat-listings using hub option (raw DB).
 *
 * @param string $html     Full page HTML.
 * @param string $tag      Tag name without brackets (rechat-root or rechat-listings).
 * @param string $brand_id Brand UUID from main site.
 * @return string
 */
function rch_multisite_ob_patch_open_tag_brand_id(string $html, string $tag, string $brand_id): string
{
    if ($brand_id === '') {
        return $html;
    }

    $safe = esc_attr($brand_id);

    return rch_multisite_preg_replace_callback_safe(
        '/<' . preg_quote($tag, '/') . '\b([^>]*)>/i',
        static function ($m) use ($safe, $tag) {
            $inner = $m[1];
            if (preg_match('/\bbrand_id\s*=\s*(["\'])([^"\']*)\1/i', $inner, $mm)) {
                if (trim($mm[2], " \t\n\r\0\x0B") !== '') {
                    return '<' . $tag . $inner . '>';
                }

                $inner = rch_multisite_preg_replace_safe(
                    '/\bbrand_id\s*=\s*["\'][^"\']*["\']/i',
                    'brand_id="' . $safe . '"',
                    $inner,
                    1
                );

                return '<' . $tag . $inner . '>';
            }

            $prefix = ($inner !== '' && $inner[0] !== ' ') ? ' ' : '';

            return '<' . $tag . $inner . $prefix . 'brand_id="' . $safe . '">';
        },
        $html
    );
}

/**
 * Fix empty map_api_key on <rechat-listings> from hub option.
 *
 * @param string $html   Full page HTML.
 * @param string $api_key Maps API key from main site.
 * @return string
 */
function rch_multisite_ob_patch_rechat_listings_map_api_key(string $html, string $api_key): string
{
    if ($api_key === '') {
        return $html;
    }

    $safe = esc_attr($api_key);

    return rch_multisite_preg_replace_callback_safe(
        '/<rechat-listings\b([^>]*)>/i',
        static function ($m) use ($safe) {
            $inner = $m[1];
            if (preg_match('/\bmap_api_key\s*=\s*(["\'])([^"\']*)\1/i', $inner, $mm)) {
                if (trim($mm[2], " \t\n\r\0\x0B") !== '') {
                    return '<rechat-listings' . $inner . '>';
                }

                $inner = rch_multisite_preg_replace_safe(
                    '/\bmap_api_key\s*=\s*["\'][^"\']*["\']/i',
                    'map_api_key="' . $safe . '"',
                    $inner,
                    1
                );

                return '<rechat-listings' . $inner . '>';
            }

            $prefix = ($inner !== '' && $inner[0] !== ' ') ? ' ' : '';

            return '<rechat-listings' . $inner . $prefix . 'map_api_key="' . $safe . '">';
        },
        $html
    );
}

/**
 * Strip mistaken filter_agents on <rechat-listings-list> (parent should own filters).
 *
 * @param string $html Full page HTML.
 * @return string
 */
function rch_multisite_ob_strip_filter_agents_on_listings_list(string $html): string
{
    return rch_multisite_preg_replace_callback_safe(
        '/<rechat-listings-list\b([^>]*)>/i',
        static function ($m) {
            $inner = rch_multisite_preg_replace_safe('/\sfilter_agents\s*=\s*(["\'])[^"\']*\1/i', '', $m[1]);

            return '<rechat-listings-list' . $inner . '>';
        },
        $html
    );
}

/**
 * Full-page output filter: hub brand/map on live markup; strip stray filter_agents on list child.
 *
 * @param string $html Full page HTML.
 * @return string
 */
function rch_multisite_ob_patch_rechat_markup(string $html)
{
    if (! is_string($html) || $html === '') {
        return $html;
    }

    if (! is_multisite() || get_current_blog_id() === (int) get_main_site_id()) {
        return $html;
    }

    $original = $html;
    $orig_len = strlen($html);

    try {
        $main_id = (int) get_main_site_id();
        $hub_brand = rch_multisite_fetch_raw_option_value_for_blog($main_id, 'rch_rechat_brand_id');
        $hub_map  = rch_multisite_fetch_raw_option_value_for_blog($main_id, 'rch_rechat_google_map_api_key');

        if ($hub_brand !== '') {
            $html = rch_multisite_ob_patch_open_tag_brand_id($html, 'rechat-root', $hub_brand);
            $html = rch_multisite_ob_patch_open_tag_brand_id($html, 'rechat-listings', $hub_brand);
        }

        if ($hub_map !== '') {
            $html = rch_multisite_ob_patch_rechat_listings_map_api_key($html, $hub_map);
        }

        $html = rch_multisite_ob_strip_filter_agents_on_listings_list($html);
    } catch (Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('rch_multisite_ob_patch_rechat_markup: ' . $e->getMessage());
        }

        return $original;
    }

    if (! is_string($html)) {
        return $original;
    }

    // If output collapsed (e.g. PREG error), keep the original document.
    if ($orig_len > 200 && strlen($html) < (int) ($orig_len * 0.5)) {
        return $original;
    }

    return $html;
}

/**
 * Whether to buffer the front-end HTML for post-processing.
 */
function rch_multisite_should_buffer_listing_markup(): bool
{
    if (! is_multisite() || get_current_blog_id() === (int) get_main_site_id()) {
        return false;
    }

    $main_id = (int) get_main_site_id();
    $hub_brand = rch_multisite_fetch_raw_option_value_for_blog($main_id, 'rch_rechat_brand_id');
    $hub_map  = rch_multisite_fetch_raw_option_value_for_blog($main_id, 'rch_rechat_google_map_api_key');

    $here = get_current_blog_id();
    $local_brand = rch_multisite_fetch_raw_option_value_for_blog($here, 'rch_rechat_brand_id');
    $local_map  = rch_multisite_fetch_raw_option_value_for_blog($here, 'rch_rechat_google_map_api_key');

    if ($hub_brand === '' && $hub_map === '') {
        return false;
    }

    return $local_brand === '' || $local_map === '';
}

/**
 * Start whole-page buffering so SDK markup can be corrected for hub-less subsites.
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

    if (! rch_multisite_should_buffer_listing_markup()) {
        return;
    }

    if (! empty($GLOBALS['rch_multisite_listing_scope_buffer_started'])) {
        return;
    }

    ob_start('rch_multisite_ob_patch_rechat_markup');
    $GLOBALS['rch_multisite_listing_scope_buffer_started'] = true;
}

add_action('template_redirect', 'rch_multisite_start_agent_listing_scope_buffer', 5);
