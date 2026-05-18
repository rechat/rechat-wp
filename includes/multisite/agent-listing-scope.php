<?php

/**
 * Multisite: agent subsite listing scope + hub brand/map fallback + markup patching.
 *
 * - Agent-only subsites (linked to a hub `agents` post via `_rch_agent_site_id`) auto-scope every
 *   `<rechat-listings>` / `[listings]` / Gutenberg listing block / `[rch_latest_listings]` /
 *   legacy archive AJAX with `filter_agents` from the hub agent's `agents` post meta.
 * - OAuth tokens (`rch_rechat_access_token`, `rch_rechat_refresh_token`) are per-blog only; subsites
 *   do not inherit the main site connection — each site uses Connect To Rechat on that blog.
 * - When local brand or map API key is empty, those options still fall back to the main site DB value.
 * - Output buffer on subsites with empty local brand or map (or active agent scope) patches
 *   `<rechat-root>` / `<rechat-listings>`.
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
 * Gutenberg ServerSideRender (block editor preview) and render_block(): merge filter_agents
 * into listing block attrs before PHP render_callback runs. Otherwise empty filter_agents is
 * omitted from the [listings …] string and the editor preview misses agent scoping.
 *
 * @param array         $parsed_block Parsed block (see WP_Block_Parser_Block).
 * @param array         $source_block Unmodified copy (unused).
 * @param \WP_Block|null $parent_block Parent block (unused).
 * @return array
 */
function rch_multisite_render_block_data_inject_listing_filter_agents($parsed_block, $source_block = null, $parent_block = null)
{
    unset($source_block, $parent_block);

    if (! is_array($parsed_block)) {
        return $parsed_block;
    }

    if (($parsed_block['blockName'] ?? '') !== 'rch-rechat-plugin/listing-block') {
        return $parsed_block;
    }

    if (! rch_multisite_is_agent_listing_scope_active()) {
        return $parsed_block;
    }

    $csv = rch_multisite_get_main_site_agent_ids_csv();
    if ($csv === '') {
        return $parsed_block;
    }

    if (! isset($parsed_block['attrs']) || ! is_array($parsed_block['attrs'])) {
        $parsed_block['attrs'] = [];
    }

    $parsed_block['attrs']['filter_agents'] = $csv;

    return $parsed_block;
}

add_filter('render_block_data', 'rch_multisite_render_block_data_inject_listing_filter_agents', 10, 3);

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
 * Legacy archive AJAX: ensure listing fetch body includes agents[] for this subsite.
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
 * Space before appending a new HTML attribute (check trailing whitespace, not leading).
 *
 * @param string $inner Existing attributes inside the tag.
 * @return string '' or single space
 */
function rch_multisite_ob_attr_append_prefix(string $inner): string
{
    if ($inner === '') {
        return '';
    }

    return preg_match('/\s$/u', $inner) ? '' : ' ';
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

            $prefix = rch_multisite_ob_attr_append_prefix($inner);

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

            $prefix = rch_multisite_ob_attr_append_prefix($inner);

            return '<rechat-listings' . $inner . $prefix . 'map_api_key="' . $safe . '">';
        },
        $html
    );
}

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

    return rch_multisite_preg_replace_callback_safe(
        '/<rechat-listings\b([^>]*)>/i',
        static function ($m) use ($attr) {
            $inner = $m[1];
            if (preg_match('/\bfilter_agents\s*=/i', $inner)) {
                $inner = rch_multisite_preg_replace_safe(
                    '/\sfilter_agents\s*=\s*(["\'])[^"\']*\1/i',
                    ' ' . $attr,
                    $inner,
                    1
                );

                return '<rechat-listings' . $inner . '>';
            }

            $prefix = rch_multisite_ob_attr_append_prefix($inner);

            return '<rechat-listings' . $inner . $prefix . $attr . '>';
        },
        $html
    );
}

/**
 * When the agent subsite has no main-site `agents` CSV, set disabled="true" on <rechat-listings>
 * (parity with rch_get_agent_listings_attrs in agents-listings-section.php).
 *
 * @param string $html Full page HTML.
 * @return string
 */
function rch_multisite_ob_disable_rechat_listings_when_no_hub_agents(string $html): string
{
    if (! is_string($html) || $html === '') {
        return $html;
    }

    if (! rch_multisite_is_agent_listing_scope_active()) {
        return $html;
    }

    if (rch_multisite_get_main_site_agent_ids_csv() !== '') {
        return $html;
    }

    return rch_multisite_preg_replace_callback_safe(
        '/<rechat-listings\b([^>]*)>/i',
        static function ($m) {
            $inner = $m[1];
            if (preg_match('/\bdisabled\s*=/i', $inner)) {
                return '<rechat-listings' . $inner . '>';
            }

            $prefix = rch_multisite_ob_attr_append_prefix($inner);

            return '<rechat-listings' . $inner . $prefix . 'disabled="true">';
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
            $inner = $m[1];
            $inner = rch_multisite_preg_replace_safe('/\sfilter_agents\s*=\s*(["\'])[^"\']*\1/i', '', $inner);
            $inner = rch_multisite_preg_replace_safe('/\sbrand_id\s*=\s*(["\'])[^"\']*\1/i', '', $inner);
            $inner = rch_multisite_preg_replace_safe('/\smap_api_key\s*=\s*(["\'])[^"\']*\1/i', '', $inner);

            return '<rechat-listings-list' . $inner . '>';
        },
        $html
    );
}

/**
 * Full-page output filter: agent scope filter_agents + hub brand/map on live markup.
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
        $html = rch_multisite_ob_inject_filter_agents($html);
        $html = rch_multisite_ob_disable_rechat_listings_when_no_hub_agents($html);

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

    if (rch_multisite_is_agent_listing_scope_active()) {
        // Always buffer agent subsites: inject filter_agents, hub brand/map fallback, and/or
        // disabled="true" on <rechat-listings> when hub agents meta is empty.
        return true;
    }

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
