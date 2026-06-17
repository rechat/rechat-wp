<?php
/**
 * Rechat Plugin – WordPress Multisite: Agent Sites
 *
 * Automatically creates and maintains a dedicated network sub-site for every
 * agent post (custom post type "agents"), both when the post is saved manually
 * in the admin and when agents are synced from the Rechat API.
 *
 * Features:
 *  - Subdomain mode  : john.example.com
 *  - Subdirectory mode: example.com/john
 *  - Per-agent and per-office sub-sites (offices use an o- URL prefix to avoid slug clashes)
 *  - Per-post theme override (optional; falls back to network default)
 *  - Per-agent / per-office site enable / disable (archived, not deleted)
 *  - Bulk provision from settings page
 *  - Status tables with inline enable/disable toggles
 *
 * Only active on WordPress Multisite installations.
 *
 * Admin UI for the Multisite settings tab lives in views/admin-tab.php (loaded at
 * the bottom of this file) to keep this module easier to navigate.
 */

if (! defined('ABSPATH')) {
    exit;
}

// Bail silently if not a multisite install – this file may be included
// unconditionally from index.php, the guard makes the functions no-ops.
if (! is_multisite()) {
    return;
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1 – HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Sanitise an agent's display name into a valid DNS / URL path slug.
 *
 * Rules (RFC 1123 §2.1):
 *  – lowercase letters, digits and hyphens only
 *  – no leading/trailing hyphens
 *  – max 63 characters
 *
 * @param  string $name  Agent's display name.
 * @return string        Sanitised slug, or empty string if nothing valid remains.
 */
function rch_multisite_sanitize_slug(string $name): string
{
    $slug = remove_accents($name);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 63);
    $slug = rtrim($slug, '-');

    return (string) $slug;
}

/**
 * Configured agent sub-site slug format.
 *
 * @return string `initial_lastname` (default) or `firstname_lastname`.
 */
function rch_multisite_get_agent_slug_format(): string
{
    $saved = (string) get_site_option('rch_multisite_agent_slug_format', 'initial_lastname');

    if (! in_array($saved, ['initial_lastname', 'firstname_lastname'], true)) {
        return 'initial_lastname';
    }

    return $saved;
}

/**
 * Build the agent sub-site slug base from post meta using the configured format.
 *
 * Formats:
 *  - initial_lastname:  first initial + last name, no separators (e.g. "afreeman")
 *  - firstname_lastname: first name + last name with hyphens (e.g. "amy-freeman")
 *
 * Falls back to the agent display name when name meta is missing.
 *
 * @param int         $agent_post_id Agent post ID.
 * @param string      $agent_name    Agent display name (usually post title).
 * @param string|null $format        Optional format override.
 * @return string Sanitized slug base (may be empty if nothing valid remains).
 */
function rch_multisite_agent_site_slug_base(int $agent_post_id, string $agent_name, ?string $format = null): string
{
    $format = $format ?? rch_multisite_get_agent_slug_format();
    $first  = trim((string) get_post_meta($agent_post_id, 'first_name', true));
    $last   = trim((string) get_post_meta($agent_post_id, 'last_name', true));

    if ($format === 'firstname_lastname') {
        if ($first !== '' && $last !== '') {
            $slug = rch_multisite_sanitize_slug($first . ' ' . $last);
            if ($slug !== '') {
                return $slug;
            }
        }

        if ($first !== '' || $last !== '') {
            $slug = rch_multisite_sanitize_slug(trim($first . ' ' . $last));
            if ($slug !== '') {
                return $slug;
            }
        }

        return rch_multisite_sanitize_slug($agent_name);
    }

    if ($last !== '') {
        $initial = $first !== '' ? mb_substr($first, 0, 1) : '';
        $raw     = remove_accents($initial . $last);
        $raw     = strtolower($raw);
        $raw     = preg_replace('/[^a-z0-9]+/', '', $raw);
        $raw     = substr((string) $raw, 0, 63);
        $raw     = (string) $raw;
        if ($raw !== '') {
            return $raw;
        }
    }

    $fallback = remove_accents($agent_name);
    $fallback = strtolower($fallback);
    $fallback = preg_replace('/[^a-z0-9]+/', '', $fallback);
    $fallback = substr((string) $fallback, 0, 63);

    return (string) $fallback;
}

/**
 * Normalize a slug base for the selected agent URL format.
 *
 * @param string      $base_slug Slug base.
 * @param string|null $format    Optional format override.
 * @return string
 */
function rch_multisite_normalize_agent_site_slug(string $base_slug, ?string $format = null): string
{
    $format    = $format ?? rch_multisite_get_agent_slug_format();
    $base_slug = trim((string) $base_slug);

    if ($format === 'firstname_lastname') {
        $base_slug = remove_accents($base_slug);
        $base_slug = strtolower($base_slug);
        $base_slug = preg_replace('/[^a-z0-9]+/', '-', $base_slug);
        $base_slug = trim($base_slug, '-');
        $base_slug = substr((string) $base_slug, 0, 63);
        $base_slug = rtrim((string) $base_slug, '-');
    } else {
        $base_slug = strtolower($base_slug);
        $base_slug = preg_replace('/[^a-z0-9]+/', '', $base_slug);
        $base_slug = substr((string) $base_slug, 0, 63);
    }

    return (string) $base_slug;
}

/**
 * Resolve the final unique agent sub-site slug (base + collision suffix when needed).
 *
 * @param int         $agent_post_id   Agent post ID.
 * @param string      $agent_name      Agent display name.
 * @param int|null    $exclude_blog_id Blog ID allowed to already own the target domain/path.
 * @param string|null $format          Optional format override.
 * @return string
 */
function rch_multisite_resolve_agent_site_slug(
    int $agent_post_id,
    string $agent_name,
    ?int $exclude_blog_id = null,
    ?string $format = null
): string {
    $base = rch_multisite_agent_site_slug_base($agent_post_id, $agent_name, $format);

    return rch_multisite_unique_agent_site_slug($base, $exclude_blog_id, $format, $agent_post_id);
}

/**
 * Agent hub post that owns a subsite (via `_rch_agent_site_id`), if any.
 *
 * @param int $blog_id Subsite blog ID.
 * @return int Agent post ID, or 0 if unlinked / not an agent post.
 */
function rch_multisite_find_agent_post_id_by_site_id(int $blog_id): int
{
    if ($blog_id <= 0) {
        return 0;
    }

    $posts = get_posts([
        'post_type'      => 'agents',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => '_rch_agent_site_id',
                'value'   => $blog_id,
                'compare' => '=',
            ],
        ],
    ]);

    return ! empty($posts) ? (int) $posts[0] : 0;
}

/**
 * Whether this agent post may link to the given subsite (unclaimed or already theirs).
 *
 * @param int $agent_post_id Agent post ID on the hub.
 * @param int $blog_id       Network site ID.
 * @return bool
 */
function rch_multisite_can_agent_claim_blog_id(int $agent_post_id, int $blog_id): bool
{
    if ($blog_id <= 0 || $agent_post_id <= 0) {
        return false;
    }

    if (! get_site($blog_id)) {
        return false;
    }

    $owner = rch_multisite_find_agent_post_id_by_site_id($blog_id);

    return $owner === 0 || $owner === $agent_post_id;
}

/**
 * Blog ID for an existing network site at the agent slug location, or 0.
 *
 * @param string $slug Agent subsite slug (subdomain label or path segment).
 * @return int
 */
function rch_multisite_blog_id_for_agent_site_slug(string $slug): int
{
    $slug = trim($slug);

    if ($slug === '') {
        return 0;
    }

    $loc = rch_multisite_build_site_location($slug);

    $existing = get_sites([
        'domain' => $loc['domain'],
        'path'   => $loc['path'],
        'number' => 1,
        'fields' => 'ids',
    ]);

    return ! empty($existing) ? (int) $existing[0] : 0;
}

/**
 * Link an agent post to an existing subsite (adopt orphan / repair missing meta).
 *
 * @param int    $post_id Agent post ID.
 * @param int    $blog_id Subsite blog ID.
 * @param string $slug    Slug used for the site location.
 * @return int Blog ID on success, 0 if claim not allowed.
 */
function rch_multisite_link_agent_to_existing_site(int $post_id, int $blog_id, string $slug): int
{
    if (! rch_multisite_can_agent_claim_blog_id($post_id, $blog_id)) {
        return 0;
    }

    update_post_meta($post_id, '_rch_agent_site_id', $blog_id);
    update_post_meta($post_id, '_rch_agent_slug', $slug);

    if (function_exists('rch_multisite_set_subsite_role_option')) {
        rch_multisite_set_subsite_role_option($blog_id, 'agent');
    }

    $site = get_site($blog_id);

    // Reactivate the site if a previous agent record (changed Rechat id) left it
    // archived. Adopting brings the same site back online instead of duplicating.
    if ($site && ('1' === (string) $site->archived || '1' === (string) $site->deleted)) {
        update_blog_status($blog_id, 'archived', '0');
        update_blog_status($blog_id, 'deleted', '0');
    }

    if ($site) {
        error_log(
            'Rechat Plugin Multisite: Linked agent post ' . $post_id .
            ' to existing site ' . $site->domain . $site->path .
            ' (blog_id=' . $blog_id . ')'
        );
    }

    return $blog_id;
}

/**
 * If the agent post lost `_rch_agent_site_id`, try to relink to an existing subsite at the base or stored slug.
 *
 * @param int    $post_id     Agent post ID.
 * @param string $agent_name  Agent display name.
 * @return int Linked blog ID, or 0 if none found.
 */
function rch_multisite_relink_agent_site_if_orphaned(int $post_id, string $agent_name): int
{
    if (rch_multisite_get_agent_blog_id($post_id) > 0) {
        return rch_multisite_get_agent_blog_id($post_id);
    }

    $candidates = [];
    $base       = rch_multisite_normalize_agent_site_slug(
        rch_multisite_agent_site_slug_base($post_id, $agent_name)
    );

    if ($base !== '') {
        $candidates[] = $base;
    }

    $stored = trim((string) get_post_meta($post_id, '_rch_agent_slug', true));

    if ($stored !== '' && ! in_array($stored, $candidates, true)) {
        $candidates[] = $stored;
    }

    foreach ($candidates as $slug) {
        $blog_id = rch_multisite_blog_id_for_agent_site_slug($slug);

        if ($blog_id <= 0) {
            continue;
        }

        $linked = rch_multisite_link_agent_to_existing_site($post_id, $blog_id, $slug);

        if ($linked > 0) {
            if (function_exists('rch_multisite_schedule_broadcast_to_new_blog')) {
                rch_multisite_schedule_broadcast_to_new_blog($linked);
            }

            do_action('rch_multisite_agent_site_created', $post_id, $linked);

            return $linked;
        }
    }

    return 0;
}

/**
 * Ensure an agent slug is unique across the network for the current URL mode.
 *
 * In subdomain mode this guarantees unique subdomains.
 *
 * @param string      $base_slug         Sanitized slug base.
 * @param int|null    $exclude_blog_id   Blog ID allowed to already own the target domain/path (used for renames).
 * @param string|null $format            Optional format override.
 * @param int|null    $for_agent_post_id When set, an unclaimed existing site at the slug may be reused (no numeric suffix).
 * @return string Unique slug (base or base + numeric suffix).
 */
function rch_multisite_unique_agent_site_slug(
    string $base_slug,
    ?int $exclude_blog_id = null,
    ?string $format = null,
    ?int $for_agent_post_id = null
): string
{
    $format    = $format ?? rch_multisite_get_agent_slug_format();
    $base_slug = rch_multisite_normalize_agent_site_slug($base_slug, $format);

    if ($base_slug === '') {
        $base_slug = 'agent';
    }

    $candidate = $base_slug;
    $n         = 2;

    while (true) {
        $loc = rch_multisite_build_site_location($candidate);

        $existing = get_sites([
            'domain' => $loc['domain'],
            'path'   => $loc['path'],
            'number' => 1,
            'fields' => 'ids',
        ]);

        if (empty($existing)) {
            return $candidate;
        }

        $existing_id = (int) $existing[0];
        if ($exclude_blog_id && $existing_id === (int) $exclude_blog_id) {
            return $candidate;
        }

        if ($for_agent_post_id > 0 && rch_multisite_can_agent_claim_blog_id($for_agent_post_id, $existing_id)) {
            return $candidate;
        }

        if ($format === 'firstname_lastname') {
            $suffix    = '-' . $n;
            $candidate = substr($base_slug, 0, max(1, 63 - strlen($suffix))) . $suffix;
        } else {
            $suffix    = (string) $n;
            $candidate = substr($base_slug, 0, max(1, 63 - strlen($suffix))) . $suffix;
        }
        $n++;

        if ($n > 5000) {
            return 'agent' . wp_rand(100000, 999999);
        }
    }
}

/**
 * Build a URL slug for office sub-sites (prefixed so agent + office names never collide).
 *
 * @param  string $name Office display name.
 * @return string       e.g. o-main-office, or empty if invalid.
 */
function rch_multisite_office_public_slug(string $name): string
{
    $base = rch_multisite_sanitize_slug($name);

    if ($base === '') {
        return '';
    }

    if (strlen($base) > 60) {
        $base = substr($base, 0, 60);
        $base = rtrim($base, '-');
    }

    return 'o-' . $base;
}

/**
 * Return the configured URL type for agent sites.
 *
 * When the admin has not explicitly chosen a type yet, we auto-detect from
 * the WordPress SUBDOMAIN_INSTALL constant so the default always matches the
 * network's actual setup.
 *
 * @return string  'subdomain' or 'subdirectory'.
 */
function rch_multisite_get_url_type(): string
{
    $saved = (string) get_site_option('rch_multisite_url_type', '');

    if (in_array($saved, ['subdomain', 'subdirectory'], true)) {
        return $saved;
    }

    // Auto-detect: respect the WordPress network configuration.
    return (defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) ? 'subdomain' : 'subdirectory';
}

/**
 * Whether the network has opted in to creating one sub-site per agent.
 *
 * When false, sync and manual saves only update agent posts — no sites are
 * created or updated. Existing agent sub-sites are left as-is unless managed
 * while this option is on again.
 *
 * @return bool
 */
function rch_multisite_is_create_agent_sites_enabled(): bool
{
    return (bool) get_site_option('rch_multisite_create_agent_sites', '1');
}

/**
 * Whether agent sub-site login/credential emails are sent to agents.
 *
 * Disabled by default — turn on from the Multisite settings tab only when you
 * actually want WordPress credentials emailed out to agents.
 *
 * @return bool
 */
function rch_multisite_is_agent_credentials_email_enabled(): bool
{
    return (bool) get_site_option('rch_multisite_send_agent_credentials_email', '0');
}

/**
 * Whether the network creates one sub-site per office post.
 *
 * @return bool
 */
function rch_multisite_is_create_office_sites_enabled(): bool
{
    return (bool) get_site_option('rch_multisite_create_office_sites', '1');
}

/**
 * Subsite options inherited from the main site when empty on the subsite.
 *
 * @return string[]
 */
function rch_multisite_hub_inherited_option_names(): array
{
    return (array) apply_filters(
        'rch_multisite_hub_inherited_option_names',
        [
            'rch_rechat_local_logic_api_key',
            'rch_rechat_google_map_api_key',
        ]
    );
}

/**
 * Whether an option value is considered empty for hub inheritance.
 *
 * @param mixed $value Option value.
 */
function rch_multisite_option_value_is_empty($value): bool
{
    if (is_array($value)) {
        return $value === [];
    }

    return $value === false || $value === null || $value === '';
}

/**
 * Option name for listing-page Local Logic feature checkboxes.
 */
function rch_multisite_local_logic_features_option_name(): string
{
    return 'rch_rechat_local_logic_features';
}

/**
 * Whether the Local Logic features option has no enabled features.
 *
 * @param mixed $value Option value.
 */
function rch_multisite_local_logic_features_is_empty($value): bool
{
    return ! is_array($value) || $value === [];
}

/**
 * Read an option from a site's database (bypasses filters / object cache).
 *
 * @param int    $blog_id      Blog ID.
 * @param string $option_name Option name.
 * @return mixed Unserialized value, or false when missing.
 */
function rch_multisite_fetch_blog_option_mixed(int $blog_id, string $option_name)
{
    if ($blog_id <= 0 || $option_name === '') {
        return false;
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

    if ($row === null) {
        return false;
    }

    return maybe_unserialize($row);
}

/**
 * Listing-page Local Logic features enabled on the network main site.
 *
 * @return string[]
 */
function rch_multisite_fetch_hub_local_logic_features(): array
{
    $raw = rch_multisite_fetch_blog_option_mixed((int) get_main_site_id(), rch_multisite_local_logic_features_option_name());

    if (! is_array($raw)) {
        return [];
    }

    $allowed = array_keys(RCH_LOCAL_LOGIC_FEATURES);

    return array_values(array_filter($raw, static function ($feature) use ($allowed) {
        return is_string($feature) && in_array($feature, $allowed, true);
    }));
}

/**
 * Ensure Local Content is enabled in a features list.
 *
 * @param string[] $features Feature keys.
 * @return string[]
 */
function rch_multisite_features_with_local_content(array $features): array
{
    if (! in_array('LocalContent', $features, true)) {
        $features[] = 'LocalContent';
    }

    return array_values(array_unique($features));
}

/**
 * Sync listing-page Local Logic features onto a subsite.
 *
 * @param int  $blog_id               Target blog ID.
 * @param bool $only_if_subsite_empty When true, copy hub features only when subsite has none selected.
 * @param bool $ensure_local_content  When true, always enable the Local Content checkbox.
 * @return int 1 when the option was updated, 0 otherwise.
 */
function rch_multisite_sync_local_logic_features_to_blog(
    int $blog_id,
    bool $only_if_subsite_empty = true,
    bool $ensure_local_content = false
): int {
    if (! is_multisite() || $blog_id <= 0 || $blog_id === (int) get_main_site_id()) {
        return 0;
    }

    $option_name = rch_multisite_local_logic_features_option_name();
    $local       = rch_multisite_fetch_blog_option_mixed($blog_id, $option_name);
    $local       = is_array($local) ? $local : [];
    $next        = $local;

    if (rch_multisite_local_logic_features_is_empty($local)) {
        $hub = rch_multisite_fetch_hub_local_logic_features();

        if (! rch_multisite_local_logic_features_is_empty($hub)) {
            $next = $hub;
        }
    } elseif (! $only_if_subsite_empty) {
        $next = $local;
    }

    if ($ensure_local_content) {
        $next = rch_multisite_features_with_local_content($next);
    }

    if ($next === $local) {
        return 0;
    }

    switch_to_blog($blog_id);
    update_option($option_name, $next, false);
    restore_current_blog();

    return 1;
}

/**
 * Copy hub Local Logic / Google Map API keys onto a subsite when that subsite's value is empty.
 *
 * @param int  $blog_id               Target blog ID.
 * @param bool $only_if_subsite_empty When true, never overwrite a non-empty subsite value.
 * @return int Number of options written on the subsite.
 */
function rch_multisite_sync_hub_api_keys_to_blog(int $blog_id, bool $only_if_subsite_empty = true): int
{
    if (! is_multisite() || $blog_id <= 0) {
        return 0;
    }

    $main_id = (int) get_main_site_id();

    if ($blog_id === $main_id) {
        return 0;
    }

    $hub_values = [];

    foreach (rch_multisite_hub_inherited_option_names() as $option_name) {
        if ($option_name === '') {
            continue;
        }

        $hub_val = rch_multisite_fetch_hub_option_value($option_name);

        if (! rch_multisite_option_value_is_empty($hub_val)) {
            $hub_values[$option_name] = $hub_val;
        }
    }

    if ($hub_values === []) {
        return 0;
    }

    $written = 0;

    switch_to_blog($blog_id);

    foreach ($hub_values as $option_name => $hub_val) {
        $local = get_option($option_name, '');

        if ($only_if_subsite_empty && ! rch_multisite_option_value_is_empty($local)) {
            continue;
        }

        update_option($option_name, $hub_val, false);
        $written++;
    }

    restore_current_blog();

    return $written;
}

/**
 * Sync hub Local Logic API keys and listing features onto an agent subsite.
 *
 * @param int  $blog_id               Target blog ID.
 * @param bool $only_if_subsite_empty When true, only fill empty subsite values (except Local Content when forced).
 * @param bool $ensure_local_content  When true, enable the Local Content feature checkbox.
 * @return int Number of options updated on the subsite.
 */
function rch_multisite_sync_hub_local_logic_settings_to_blog(
    int $blog_id,
    bool $only_if_subsite_empty = true,
    bool $ensure_local_content = false
): int {
    $written  = rch_multisite_sync_hub_api_keys_to_blog($blog_id, $only_if_subsite_empty);
    $written += rch_multisite_sync_local_logic_features_to_blog($blog_id, $only_if_subsite_empty, $ensure_local_content);

    return $written;
}

/**
 * Read a scalar option from the network main site.
 *
 * @param string $option_name Option name.
 * @return string
 */
function rch_multisite_fetch_hub_option_value(string $option_name): string
{
    if (! is_multisite() || $option_name === '') {
        return '';
    }

    if (function_exists('rch_multisite_fetch_raw_option_value_for_blog')) {
        return rch_multisite_fetch_raw_option_value_for_blog((int) get_main_site_id(), $option_name);
    }

    $main_id = (int) get_main_site_id();
    switch_to_blog($main_id);
    $value = get_option($option_name, '');
    restore_current_blog();

    if (is_string($value)) {
        return $value;
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    return '';
}

/**
 * Network default theme for agent sub-sites (no per-post override).
 *
 * @return array{template:string,stylesheet:string}
 */
function rch_multisite_resolve_theme_network_default_for_agents(): array
{
    $main_id = get_main_site_id();
    $saved   = (string) get_site_option('rch_multisite_agent_theme_stylesheet', '');

    if ($saved !== '') {
        $theme = wp_get_theme($saved);
        if ($theme->exists()) {
            return [
                'template'   => $theme->get_template(),
                'stylesheet' => $theme->get_stylesheet(),
            ];
        }
    }

    return [
        'template'   => (string) get_blog_option($main_id, 'template'),
        'stylesheet' => (string) get_blog_option($main_id, 'stylesheet'),
    ];
}

/**
 * Network default theme for office sub-sites (no per-post override).
 *
 * @return array{template:string,stylesheet:string}
 */
function rch_multisite_resolve_theme_network_default_for_offices(): array
{
    $main_id = get_main_site_id();
    $saved   = (string) get_site_option('rch_multisite_office_theme_stylesheet', '');

    if ($saved !== '') {
        $theme = wp_get_theme($saved);
        if ($theme->exists()) {
            return [
                'template'   => $theme->get_template(),
                'stylesheet' => $theme->get_stylesheet(),
            ];
        }
    }

    return [
        'template'   => (string) get_blog_option($main_id, 'template'),
        'stylesheet' => (string) get_blog_option($main_id, 'stylesheet'),
    ];
}

/**
 * Alias: agent network default (backward compatibility for callers).
 *
 * @return array{template:string,stylesheet:string}
 */
function rch_multisite_resolve_theme_network_default(): array
{
    return rch_multisite_resolve_theme_network_default_for_agents();
}

/**
 * Resolve template + stylesheet for agent sub-sites bulk apply (network agent default).
 *
 * @return array{template:string,stylesheet:string}
 */
function rch_multisite_resolve_theme_for_agent_sites(): array
{
    return rch_multisite_resolve_theme_network_default_for_agents();
}

/**
 * Resolve theme for a specific agent or office post (per-post override or network default).
 *
 * Post meta `_rch_subsite_theme_stylesheet` may hold a theme stylesheet slug; empty = inherit.
 *
 * @param  int $post_id Agent or office post ID.
 * @return array{template:string,stylesheet:string}
 */
function rch_multisite_resolve_theme_for_post(int $post_id): array
{
    $saved = (string) get_post_meta($post_id, '_rch_subsite_theme_stylesheet', true);

    if ($saved !== '') {
        $theme = wp_get_theme($saved);
        if ($theme->exists()) {
            return [
                'template'   => $theme->get_template(),
                'stylesheet' => $theme->get_stylesheet(),
            ];
        }
    }

    $pt = get_post_type($post_id);
    if ($pt === 'offices') {
        return rch_multisite_resolve_theme_network_default_for_offices();
    }

    return rch_multisite_resolve_theme_network_default_for_agents();
}

/**
 * Installed themes suitable for the Multisite settings dropdown (slug => label).
 *
 * @return array<string, string>
 */
function rch_multisite_get_theme_choices(): array
{
    $choices = [];
    foreach (wp_get_themes() as $slug => $theme) {
        if ($theme->exists()) {
            $choices[$slug] = $theme->get('Name') . ' — ' . $slug;
        }
    }
    natcasesort($choices);

    return $choices;
}

/**
 * Activate a theme on one blog (agent sub-site).
 *
 * @param  int    $blog_id     Target blog ID.
 * @param  string $stylesheet  Theme stylesheet slug.
 * @return true|WP_Error
 */
function rch_multisite_activate_theme_on_blog(int $blog_id, string $stylesheet)
{
    $theme = wp_get_theme($stylesheet);
    if (! $theme->exists()) {
        return new WP_Error(
            'rch_theme_missing',
            sprintf(
                /* translators: %s: theme slug */
                __('Theme "%s" is not installed.', 'rechat-plugin'),
                $stylesheet
            )
        );
    }

    switch_to_blog($blog_id);

    switch_theme($stylesheet);

    $allowed = (array) get_option('allowedthemes', []);
    $allowed[$stylesheet] = true;
    update_option('allowedthemes', $allowed);

    flush_rewrite_rules(false);

    restore_current_blog();

    return true;
}

/**
 * Build the domain + path pair used when creating a network site for an agent.
 *
 * Subdomain mode:
 *   domain = john.example.com   path = /
 *
 * Subdirectory mode (respects the network's base path, e.g. /rechat-plugin/):
 *   domain = example.com        path = /rechat-plugin/john/
 *
 * This is critical for installs where WordPress lives in a subdirectory –
 * new sites must be nested under the same base path, otherwise WordPress
 * will not list them in Network Admin.
 *
 * @param  string $slug  Sanitised agent slug.
 * @return array{domain:string,path:string}
 */
function rch_multisite_build_site_location(string $slug): array
{
    $network      = get_network();
    $base_domain  = preg_replace('/^www\./i', '', $network->domain);
    // Network base path, e.g. '/rechat-plugin/' or just '/'.
    $network_path = trailingslashit($network->path);

    if (rch_multisite_get_url_type() === 'subdirectory') {
        return [
            'domain' => $base_domain,
            'path'   => $network_path . $slug . '/',
        ];
    }

    // Subdomain: the sub-site lives at its own domain, path is always '/'.
    return [
        'domain' => $slug . '.' . $base_domain,
        'path'   => '/',
    ];
}

/**
 * Return the blog_id of the network site linked to an agent post,
 * but only when the site actually exists in the network.
 *
 * If the stored blog_id points to a non-existent or deleted site the stale
 * meta is cleared so that the next provisioning run recreates it correctly.
 *
 * @param  int $post_id  Agent post ID.
 * @return int           Valid blog ID, or 0 if none / site gone.
 */
function rch_multisite_get_agent_blog_id(int $post_id): int
{
    $blog_id = (int) get_post_meta($post_id, '_rch_agent_site_id', true);

    if (! $blog_id) {
        return 0;
    }

    // Validate that the site still exists in the network.
    $site = get_site($blog_id);

    if (! $site) {
        // Stale meta – clear it so the next call creates a fresh site.
        delete_post_meta($post_id, '_rch_agent_site_id');
        delete_post_meta($post_id, '_rch_agent_slug');
        error_log(
            'Rechat Plugin Multisite: Cleared stale blog_id=' . $blog_id .
            ' from agent post ' . $post_id . ' (site no longer exists).'
        );
        return 0;
    }

    return $blog_id;
}

/**
 * Check whether the agent site is enabled for a given agent post.
 *
 * Defaults to true (enabled) when no meta has been saved yet so that
 * existing agents are not silently skipped after upgrade.
 *
 * @param  int $post_id  Agent post ID.
 * @return bool
 */
function rch_multisite_is_agent_site_enabled(int $post_id): bool
{
    $meta = get_post_meta($post_id, '_rch_agent_site_enabled', true);

    // Empty string means meta was never saved → default enabled.
    if ($meta === '') {
        return true;
    }

    return (bool) $meta;
}

/**
 * Linked multisite sub-site URL for an agent (does not read or override `website` meta).
 *
 * @param  int $post_id Agent post ID (main-site `agents` CPT).
 * @return string       Empty when not multisite, site disabled, or no linked blog.
 */
function rch_get_agent_subsite_url(int $post_id): string
{
    if (
        ! is_multisite()
        || ! rch_multisite_is_agent_site_enabled($post_id)
    ) {
        return '';
    }

    $blog_id = rch_multisite_get_agent_blog_id($post_id);

    return $blog_id > 0 ? get_site_url($blog_id) : '';
}

/**
 * Website URL shown on agent single templates (display only; does not change `website` meta).
 *
 * Priority: linked multisite sub-site → current agent sub-site home → Rechat `website` meta.
 *
 * @param  int $post_id Agent post ID (main-site `agents` CPT).
 * @return string
 */
function rch_get_agent_public_website_url(int $post_id): string
{
    $subsite_url = rch_get_agent_subsite_url($post_id);

    if ($subsite_url !== '') {
        return $subsite_url;
    }

    if (function_exists('rch_is_rechat_agent_only_subsite') && rch_is_rechat_agent_only_subsite()) {
        return home_url('/');
    }

    return (string) get_post_meta($post_id, 'website', true);
}

/**
 * Return the blog_id linked to an office post, clearing stale meta if the site is gone.
 *
 * @param  int $post_id Office post ID.
 * @return int          Valid blog ID, or 0.
 */
function rch_multisite_get_office_blog_id(int $post_id): int
{
    $blog_id = (int) get_post_meta($post_id, '_rch_office_site_id', true);

    if (! $blog_id) {
        return 0;
    }

    $site = get_site($blog_id);

    if (! $site) {
        delete_post_meta($post_id, '_rch_office_site_id');
        delete_post_meta($post_id, '_rch_office_slug');
        error_log(
            'Rechat Plugin Multisite: Cleared stale blog_id=' . $blog_id .
            ' from office post ' . $post_id . ' (site no longer exists).'
        );

        return 0;
    }

    return $blog_id;
}

/**
 * Whether the office sub-site is enabled for this office post.
 *
 * @param  int $post_id Office post ID.
 * @return bool
 */
function rch_multisite_is_office_site_enabled(int $post_id): bool
{
    $meta = get_post_meta($post_id, '_rch_office_site_enabled', true);

    if ($meta === '') {
        return true;
    }

    return (bool) $meta;
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2 – SITE CREATION, UPDATE & ENABLE/DISABLE
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create a new network sub-site for an agent, store the blog_id in post meta.
 *
 * If a site at the derived domain+path already exists it is adopted
 * (blog_id stored) rather than a duplicate being created.
 *
 * @param  int    $post_id     Agent post ID.
 * @param  string $agent_name  Agent's display name.
 * @return int|WP_Error        New (or existing) blog_id, or WP_Error on failure.
 */
function rch_multisite_create_site_for_agent(int $post_id, string $agent_name)
{
    $broadcast_err = function_exists('rch_multisite_broadcast_dependency_error')
        ? rch_multisite_broadcast_dependency_error()
        : null;

    if ($broadcast_err instanceof WP_Error) {
        return $broadcast_err;
    }

    $relinked = rch_multisite_relink_agent_site_if_orphaned($post_id, $agent_name);

    if ($relinked > 0) {
        return $relinked;
    }

    $slug = rch_multisite_resolve_agent_site_slug($post_id, $agent_name, null);

    if (empty($slug)) {
        return new WP_Error(
            'rch_invalid_slug',
            sprintf(
                /* translators: %s: agent name */
                __('Could not generate a valid slug from agent name "%s".', 'rechat-plugin'),
                $agent_name
            )
        );
    }

    $location = rch_multisite_build_site_location($slug);
    $domain   = $location['domain'];
    $path     = $location['path'];

    // ── Guard: adopt if site already registered on the network ────────────────
    $existing_sites = get_sites([
        'domain'  => $domain,
        'path'    => $path,
        'number'  => 1,
        'fields'  => 'ids',
    ]);

    if (! empty($existing_sites)) {
        $existing_blog_id = (int) $existing_sites[0];
        $linked           = rch_multisite_link_agent_to_existing_site($post_id, $existing_blog_id, $slug);

        if ($linked <= 0) {
            return new WP_Error(
                'rch_site_slug_taken',
                sprintf(
                    /* translators: %s: site slug */
                    __('Sub-site slug "%s" is already linked to another agent.', 'rechat-plugin'),
                    $slug
                )
            );
        }

        if (function_exists('rch_multisite_schedule_broadcast_to_new_blog')) {
            rch_multisite_schedule_broadcast_to_new_blog($linked);
        }

        do_action('rch_multisite_agent_site_created', $post_id, $linked);

        return $linked;
    }

    // ── Resolve the admin user that will own the new site ──────────────────────
    $admin_user_id = absint(get_site_option('rch_multisite_admin_user_id', 0));

    if (! $admin_user_id) {
        $admin_email   = get_site_option('admin_email');
        $admin_user    = get_user_by('email', $admin_email);
        $admin_user_id = $admin_user ? (int) $admin_user->ID : 1;
    }

    // ── Create the site ────────────────────────────────────────────────────────
    $site_args = [
        'domain'     => $domain,
        'path'       => $path,
        'title'      => $agent_name,
        'network_id' => get_current_network_id(),
        'user_id'    => $admin_user_id,
        'options'    => [
            'blogdescription' => sprintf(
                /* translators: %s: agent name */
                __('%s – Agent Site', 'rechat-plugin'),
                $agent_name
            ),
        ],
    ];

    // wp_insert_site() was introduced in WP 5.1; fall back to wpmu_create_blog().
    if (function_exists('wp_insert_site')) {
        $result = wp_insert_site($site_args);
    } else {
        /** @noinspection PhpDeprecationInspection */
        $result = wpmu_create_blog(
            $domain,
            $path,
            $agent_name,
            $admin_user_id,
            [],
            get_current_network_id()
        );
    }

    if (is_wp_error($result)) {
        error_log(
            'Rechat Plugin Multisite: Failed to create site for agent "' .
            $agent_name . '" – ' . $result->get_error_message()
        );
        return $result;
    }

    // wp_insert_site() returns a WP_Site object; wpmu_create_blog() returns the int.
    $blog_id = is_object($result) ? (int) $result->id : (int) $result;

    update_post_meta($post_id, '_rch_agent_site_id', $blog_id);
    update_post_meta($post_id, '_rch_agent_slug', $slug);

    // Apply theme (per-post override or network default) and essential settings.
    rch_multisite_configure_new_site($blog_id, $agent_name, $post_id, false);

    rch_multisite_maybe_provision_agent_site_editor($post_id, $blog_id);

    if (function_exists('rch_multisite_schedule_broadcast_to_new_blog')) {
        rch_multisite_schedule_broadcast_to_new_blog($blog_id);
    }

    /**
     * Fires after Rechat provisions an agent subsite (neighborhood broadcast, etc.).
     *
     * @param int $post_id Agent post ID on the hub.
     * @param int $blog_id New subsite blog ID.
     */
    do_action('rch_multisite_agent_site_created', $post_id, $blog_id);

    error_log(
        'Rechat Plugin Multisite: Created site ' . $domain . $path .
        ' (blog_id=' . $blog_id . ') for agent post ' . $post_id
    );

    return $blog_id;
}

/**
 * Ensure a network user is on the given blog with the Agent role (add or promote).
 *
 * @param  int $user_id WordPress user ID.
 * @param  int $blog_id Blog ID.
 * @return true|WP_Error
 */
function rch_multisite_ensure_user_editor_on_blog(int $user_id, int $blog_id)
{
    $role = function_exists('rch_agent_site_user_role') ? rch_agent_site_user_role() : 'agent';

    switch_to_blog($blog_id);

    if (is_user_member_of_blog($user_id, $blog_id)) {
        $user = new WP_User($user_id);
        $user->set_role($role);
        restore_current_blog();

        return true;
    }

    $added = add_user_to_blog($blog_id, $user_id, $role);
    restore_current_blog();

    if (is_wp_error($added)) {
        return $added;
    }

    return true;
}

/**
 * For every published agent with a linked sub-site and valid profile email, ensure the
 * matching network user is on that blog with {@see rch_agent_site_user_role()} (no welcome emails).
 *
 * @return array{updated:int,skipped:int,api_keys_synced:int,errors:string[]}
 */
function rch_multisite_bulk_reassign_agent_site_user_roles(): array
{
    $updated         = 0;
    $skipped         = 0;
    $api_keys_synced = 0;
    $errors          = [];

    $agents = get_posts([
        'post_type'   => 'agents',
        'numberposts' => -1,
        'post_status' => 'publish',
        'orderby'     => 'title',
        'order'       => 'ASC',
        'fields'      => 'all',
    ]);

    foreach ($agents as $agent) {
        $blog_id = rch_multisite_get_agent_blog_id((int) $agent->ID);
        $email   = sanitize_email((string) get_post_meta($agent->ID, 'email', true));
        $label   = trim((string) $agent->post_title);

        if ($label === '') {
            /* translators: %d: agent post ID */
            $label = sprintf(__('Agent #%d', 'rechat-plugin'), (int) $agent->ID);
        }

        if ($blog_id && rch_multisite_sync_hub_local_logic_settings_to_blog($blog_id, true, true) > 0) {
            $api_keys_synced++;
        }

        if (! $blog_id || ! $email || ! is_email($email)) {
            $skipped++;
            continue;
        }

        $user_id = email_exists($email);

        if (! $user_id) {
            $skipped++;
            continue;
        }

        $result = rch_multisite_ensure_user_editor_on_blog((int) $user_id, $blog_id);

        if (is_wp_error($result)) {
            $errors[] = sprintf(
                '%1$s: %2$s',
                esc_html($label),
                esc_html($result->get_error_message())
            );
            continue;
        }

        $updated++;
    }

    return compact('updated', 'skipped', 'api_keys_synced', 'errors');
}

/**
 * Build a WordPress username base from the agent post title (same source as subsite / agent name).
 *
 * Stored `_rch_agent_slug` is only used when the post title cannot produce a valid login (e.g. empty),
 * because API slugs are often short IDs (e.g. "v1") and must not override the human-readable agent name.
 *
 * @param  int $agent_post_id Agent post ID.
 * @return string              Sanitized login fragment (may still collide until uniqued).
 */
function rch_multisite_agent_editor_login_base(int $agent_post_id): string
{
    $raw_title = (string) get_post_field('post_title', $agent_post_id, 'raw');
    $candidate = sanitize_user(rch_multisite_sanitize_slug($raw_title), true);

    if ($candidate === '' || ! validate_username($candidate)) {
        $slug = (string) get_post_meta($agent_post_id, '_rch_agent_slug', true);
        $candidate = $slug !== '' ? sanitize_user($slug, true) : '';

        if ($candidate === '' || ! validate_username($candidate)) {
            $candidate = 'agent-' . $agent_post_id;
        }
    }

    $candidate = substr($candidate, 0, 60);
    $candidate = rtrim($candidate, '-');

    if ($candidate === '' || ! validate_username($candidate)) {
        $candidate = 'agent-' . $agent_post_id;
    }

    /**
     * Filter the base WordPress username derived from an agent before collision handling.
     *
     * @param string $candidate     Sanitized username base.
     * @param int    $agent_post_id Agent post ID.
     */
    return (string) apply_filters('rch_multisite_agent_editor_login_base', $candidate, $agent_post_id);
}

/**
 * Pick a network-unique username for this email: reuse if the same user already owns the login.
 *
 * @param  string $base  Base login from {@see rch_multisite_agent_editor_login_base()}.
 * @param  string $email Agent email (must match the user being created or linked).
 * @return string
 */
function rch_multisite_unique_agent_editor_login(string $base, string $email): string
{
    $base = sanitize_user($base, true);

    if ($base === '' || ! validate_username($base)) {
        $base = 'agent';
    }

    $base    = substr($base, 0, 60);
    $candidate = $base;
    $suffix_n  = 2;

    while (true) {
        if (! username_exists($candidate)) {
            return $candidate;
        }

        $owner = get_user_by('login', $candidate);

        if ($owner && strcasecmp((string) $owner->user_email, (string) $email) === 0) {
            return $candidate;
        }

        $suffix    = '-' . $suffix_n;
        $candidate = substr($base, 0, max(1, 60 - strlen($suffix))) . $suffix;
        $suffix_n++;

        if ($suffix_n > 2000) {
            return sanitize_user('agent-' . wp_generate_password(10, false, false), true);
        }
    }
}

/**
 * Change a user's login to the preferred agent-based username when possible.
 *
 * @param  int    $user_id      User ID.
 * @param  string $new_login    Desired login (already uniqued for this email).
 * @return string               Login to use (new or unchanged on failure).
 */
function rch_multisite_try_set_agent_editor_user_login(int $user_id, string $new_login): string
{
    $user = get_userdata($user_id);

    if (! $user) {
        return $new_login;
    }

    if ($user->user_login === $new_login) {
        return $new_login;
    }

    if (! validate_username($new_login)) {
        return $user->user_login;
    }

    $conflict = get_user_by('login', $new_login);

    if ($conflict && (int) $conflict->ID !== $user_id) {
        return $user->user_login;
    }

    $result = wp_update_user([
        'ID'         => $user_id,
        'user_login' => $new_login,
    ]);

    if (is_wp_error($result)) {
        error_log('Rechat Multisite: could not update user_login — ' . $result->get_error_message());

        return $user->user_login;
    }

    $refreshed = get_userdata($user_id);

    return $refreshed ? (string) $refreshed->user_login : $new_login;
}

/**
 * Email addresses to notify when an agent subsite editor is provisioned or updated.
 *
 * @return string[] Non-empty unique emails.
 */
function rch_multisite_get_agent_editor_admin_notice_recipients(): array
{
    $out = [];

    $network_admin = (string) get_site_option('admin_email', '');

    if ($network_admin && is_email($network_admin)) {
        $out[] = $network_admin;
    }

    if (is_multisite()) {
        $main_id = get_main_site_id();
        switch_to_blog($main_id);
        $main_admin = (string) get_option('admin_email', '');
        restore_current_blog();

        if ($main_admin && is_email($main_admin)) {
            $out[] = $main_admin;
        }
    }

    $owner_id = absint(get_site_option('rch_multisite_admin_user_id', 0));

    if ($owner_id) {
        $owner = get_userdata($owner_id);

        if ($owner && is_email((string) $owner->user_email)) {
            $out[] = (string) $owner->user_email;
        }
    }

    $out = array_filter(array_map('sanitize_email', $out));

    $deduped = [];

    foreach ($out as $addr) {
        if (! $addr || ! is_email($addr)) {
            continue;
        }

        $key = strtolower($addr);

        if (! isset($deduped[$key])) {
            $deduped[$key] = $addr;
        }
    }

    $out = array_values($deduped);

    /**
     * Filter who receives the admin copy when an agent editor account is synced.
     *
     * @param string[] $out Email addresses.
     */
    return array_values(array_filter(
        (array) apply_filters('rch_multisite_agent_editor_admin_notice_emails', $out)
    ));
}

/**
 * Notify network/main-site admins that an agent subsite editor was synced (separate email from the agent).
 *
 * @param  int    $agent_post_id Agent post ID.
 * @param  string $agent_name    Agent display name.
 * @param  string $agent_email   Agent profile email.
 * @param  string $wp_username   Final WordPress username.
 * @param  string $login_url     Subsite login URL.
 * @param  string $site_url      Subsite front URL.
 * @param  bool   $existing_user Whether they already had a network account.
 * @return void
 */
function rch_multisite_send_agent_site_editor_admin_notice(
    int $agent_post_id,
    string $agent_name,
    string $agent_email,
    string $wp_username,
    string $login_url,
    string $site_url,
    bool $existing_user
): void {
    // Gated by the same Multisite setting as the agent credential email. While credential
    // emailing is off (default), no provisioning emails go out at all — agents’ sites are
    // often not ready yet, so neither they nor admins should receive login details.
    if (! rch_multisite_is_agent_credentials_email_enabled()) {
        return;
    }

    $recipients = rch_multisite_get_agent_editor_admin_notice_recipients();

    if ($recipients === []) {
        return;
    }

    $main_id   = get_main_site_id();
    $site_name = wp_specialchars_decode((string) get_blog_option($main_id, 'blogname'), ENT_QUOTES);

    $subject = sprintf(
        /* translators: %s: agent display name */
        __('[%s] Agent subsite editor synced', 'rechat-plugin'),
        $site_name
    );

    $mode = $existing_user
        /* translators: network user existed */
        ? __('Linked existing network user', 'rechat-plugin')
        : __('Created new network user', 'rechat-plugin');

    $body = sprintf(
        /* translators: 1: mode, 2: agent name, 3: post ID, 4: profile email, 5: WP username, 6: site URL, 7: login URL, 8: network name */
        __(
            "An agent subsite editor was updated.\n\n%1\$s\n\nAgent: %2\$s (post ID %3\$d)\nProfile email: %4\$s\nWordPress username: %5\$s\n\nSub-site: %6\$s\nLogin URL: %7\$s\n\n— %8\$s\n",
            'rechat-plugin'
        ),
        $mode,
        $agent_name,
        $agent_post_id,
        $agent_email,
        $wp_username,
        $site_url,
        $login_url,
        $site_name
    );

    foreach ($recipients as $to) {
        wp_mail($to, $subject, $body);
    }
}

/**
 * Sync the agent subsite Editor account from agent `email` meta and send login instructions.
 *
 * @param  int  $agent_post_id     Agent post ID.
 * @param  int  $blog_id           Agent sub-site blog ID.
 * @param  bool $bypass_idempotency When false, no-op if `_rch_agent_site_editor_provisioned` is already `1`.
 * @return array{ok:bool,skipped?:bool,message?:string} `skipped` only when ok is true and nothing was done.
 */
function rch_multisite_sync_agent_site_editor(int $agent_post_id, int $blog_id, bool $bypass_idempotency): array
{
    if (! $bypass_idempotency && get_post_meta($agent_post_id, '_rch_agent_site_editor_provisioned', true) === '1') {
        return [
            'ok'      => true,
            'skipped' => true,
            'message' => '',
        ];
    }

    $email = sanitize_email((string) get_post_meta($agent_post_id, 'email', true));

    if (! $email || ! is_email($email)) {
        update_post_meta($agent_post_id, '_rch_agent_site_editor_provisioned', 'skipped_no_email');

        return [
            'ok'      => false,
            'message' => __('This agent has no valid email address. Add one on the agent profile first.', 'rechat-plugin'),
        ];
    }

    $agent_name    = get_the_title($agent_post_id);
    $login_base    = rch_multisite_agent_editor_login_base($agent_post_id);
    $desired_login = rch_multisite_unique_agent_editor_login($login_base, $email);

    switch_to_blog($blog_id);
    $login_url = wp_login_url();
    $site_url  = home_url('/');
    restore_current_blog();

    $existing_id = email_exists($email);

    if ($existing_id) {
        $uid = (int) $existing_id;

        $ensured = rch_multisite_ensure_user_editor_on_blog($uid, $blog_id);
        if (is_wp_error($ensured)) {
            error_log('Rechat Multisite: ensure editor on blog failed — ' . $ensured->get_error_message());

            return [
                'ok'      => false,
                'message' => $ensured->get_error_message(),
            ];
        }

        $final_login = rch_multisite_try_set_agent_editor_user_login($uid, $desired_login);

        if ($agent_name !== '') {
            wp_update_user([
                'ID'           => $uid,
                'display_name' => $agent_name,
            ]);
        }

        update_post_meta($agent_post_id, '_rch_agent_site_editor_provisioned', '1');

        // When the admin clicks "Update editor" (bypass idempotency), reset the password and email credentials.
        // This matches the requested behavior for provisioning and avoids "use your existing password" confusion.
        $plain_pass     = '';
        $treat_as_new   = false;
        $existing_user  = true;

        // Only reset the password when we will actually email it — otherwise the
        // agent would be locked out of a freshly-randomized password they never receive.
        if ($bypass_idempotency && rch_multisite_is_agent_credentials_email_enabled()) {
            $plain_pass = wp_generate_password(24, true, true);
            wp_set_password($plain_pass, $uid);
            $treat_as_new  = true;
            $existing_user = false;
        }

        rch_multisite_send_agent_site_editor_email(
            $email,
            $agent_name,
            $login_url,
            $plain_pass,
            $existing_user,
            $final_login
        );
        rch_multisite_send_agent_site_editor_admin_notice(
            $agent_post_id,
            $agent_name,
            $email,
            $final_login,
            $login_url,
            $site_url,
            ! $treat_as_new
        );

        return [
            'ok'      => true,
            'message' => $bypass_idempotency
                ? __('Editor access is set and login credentials were emailed to the agent.', 'rechat-plugin')
                : __('Editor access is set for this site and instructions were emailed to the agent.', 'rechat-plugin'),
        ];
    }

    $password = wp_generate_password(24, true, true);

    $agent_role = function_exists('rch_agent_site_user_role') ? rch_agent_site_user_role() : 'agent';

    // Create the network user with role on the agent sub-site (not main-site default_role/subscriber).
    switch_to_blog($blog_id);
    if (function_exists('rch_register_agent_user_roles')) {
        rch_register_agent_user_roles();
    }

    $user_id = wp_insert_user([
        'user_login'   => $desired_login,
        'user_email'   => $email,
        'user_pass'    => $password,
        'display_name' => $agent_name,
        'role'         => $agent_role,
    ]);
    restore_current_blog();

    if (is_wp_error($user_id)) {
        error_log('Rechat Multisite: wp_insert_user failed — ' . $user_id->get_error_message());

        return [
            'ok'      => false,
            'message' => $user_id->get_error_message(),
        ];
    }

    $ensured = rch_multisite_ensure_user_editor_on_blog((int) $user_id, $blog_id);
    if (is_wp_error($ensured)) {
        error_log('Rechat Multisite: add user to blog failed — ' . $ensured->get_error_message());

        return [
            'ok'      => false,
            'message' => $ensured->get_error_message(),
        ];
    }

    $final_login = rch_multisite_try_set_agent_editor_user_login((int) $user_id, $desired_login);

    update_post_meta($agent_post_id, '_rch_agent_site_editor_provisioned', '1');

    rch_multisite_send_agent_site_editor_email(
        $email,
        $agent_name,
        $login_url,
        $password,
        false,
        $final_login
    );
    rch_multisite_send_agent_site_editor_admin_notice(
        $agent_post_id,
        $agent_name,
        $email,
        $final_login,
        $login_url,
        $site_url,
        false
    );

    return [
        'ok'      => true,
        'message' => __('A new editor account was created and login details were emailed to the agent.', 'rechat-plugin'),
    ];
}

/**
 * After a new agent sub-site is created, add an Editor user from the agent email and notify them.
 *
 * Only runs for agent subsites (not offices). Skips if meta `email` is missing/invalid or already provisioned.
 *
 * @param  int $agent_post_id Agent post ID.
 * @param  int $blog_id       New agent sub-site blog ID.
 * @return void
 */
function rch_multisite_maybe_provision_agent_site_editor(int $agent_post_id, int $blog_id): void
{
    $result = rch_multisite_sync_agent_site_editor($agent_post_id, $blog_id, false);

    if (! empty($result['skipped'])) {
        return;
    }

    if (empty($result['ok'])) {
        error_log(
            'Rechat Plugin Multisite: Editor sync failed for agent post ' . $agent_post_id .
            ' — ' . ($result['message'] ?? '')
        );
    }
}

/**
 * Email the agent their subsite login details (new account or existing user added to site).
 *
 * @param  string $to_email       Recipient.
 * @param  string $agent_name     Display name.
 * @param  string $login_url      Subsite wp-login URL.
 * @param  string $plain_pass     Empty if existing account.
 * @param  bool   $existing_user  Whether they already had a network account.
 * @param  string $username_label Username shown in the email body.
 * @return void
 */
function rch_multisite_send_agent_site_editor_email(
    string $to_email,
    string $agent_name,
    string $login_url,
    string $plain_pass,
    bool $existing_user,
    string $username_label
): void {
    // Disabled by default. Admins enable this from the Multisite settings tab
    // only when they want WordPress credentials emailed to agents.
    if (! rch_multisite_is_agent_credentials_email_enabled()) {
        return;
    }

    $main_id   = get_main_site_id();
    $site_name = wp_specialchars_decode((string) get_blog_option($main_id, 'blogname'), ENT_QUOTES);

    if ($existing_user) {
        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] You have been added to your agent website', 'rechat-plugin'),
            $site_name
        );
        $body = sprintf(
            /* translators: 1: agent name, 2: login URL, 3: site name, 4: WordPress username */
            __(
                "Hello %1\$s,\n\nA WordPress account has been linked to your agent website so you can manage your site content.\n\nYou already have a login on this network — use your existing password.\n\nUsername: %4\$s\n\nLog in here:\n%2\$s\n\n— %3\$s\n",
                'rechat-plugin'
            ),
            $agent_name,
            $login_url,
            $site_name,
            $username_label
        );
    } else {
        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] Your agent website access details', 'rechat-plugin'),
            $site_name
        );
        $body = sprintf(
            /* translators: 1: agent name, 2: username, 3: password, 4: login URL, 5: site name */
            __(
                "Hello %1\$s,\n\nA WordPress account has been created for you to manage your agent website.\n\nUsername: %2\$s\nPassword: %3\$s\n\nPlease log in and change your password after your first login:\n%4\$s\n\nKeep this message secure. If you did not expect this email, contact your broker or site administrator.\n\n— %5\$s\n",
                'rechat-plugin'
            ),
            $agent_name,
            $username_label,
            $plain_pass,
            $login_url,
            $site_name
        );
    }

    wp_mail($to_email, $subject, $body);
}

/**
 * Create a network sub-site for an office post.
 *
 * @param  int    $post_id      Office post ID.
 * @param  string $office_name  Office display name.
 * @return int|WP_Error
 */
function rch_multisite_create_site_for_office(int $post_id, string $office_name)
{
    $broadcast_err = function_exists('rch_multisite_broadcast_dependency_error')
        ? rch_multisite_broadcast_dependency_error()
        : null;

    if ($broadcast_err instanceof WP_Error) {
        return $broadcast_err;
    }

    $slug = rch_multisite_office_public_slug($office_name);

    if (empty($slug)) {
        return new WP_Error(
            'rch_invalid_slug',
            sprintf(
                /* translators: %s: office name */
                __('Could not generate a valid slug from office name "%s".', 'rechat-plugin'),
                $office_name
            )
        );
    }

    $location = rch_multisite_build_site_location($slug);
    $domain   = $location['domain'];
    $path     = $location['path'];

    $existing_sites = get_sites([
        'domain' => $domain,
        'path'   => $path,
        'number' => 1,
        'fields' => 'ids',
    ]);

    if (! empty($existing_sites)) {
        $existing_blog_id = (int) $existing_sites[0];
        update_post_meta($post_id, '_rch_office_site_id', $existing_blog_id);
        update_post_meta($post_id, '_rch_office_slug', $slug);
        error_log(
            'Rechat Plugin Multisite: Adopted existing site ' . $domain . $path .
            ' (blog_id=' . $existing_blog_id . ') for office post ' . $post_id
        );

        // Reactivate if a previous office record left the site archived.
        $existing_site = get_site($existing_blog_id);
        if ($existing_site && ('1' === (string) $existing_site->archived || '1' === (string) $existing_site->deleted)) {
            update_blog_status($existing_blog_id, 'archived', '0');
            update_blog_status($existing_blog_id, 'deleted', '0');
        }

        if (function_exists('rch_multisite_set_subsite_role_option')) {
            rch_multisite_set_subsite_role_option($existing_blog_id, 'office');
        }

        if (function_exists('rch_multisite_schedule_broadcast_to_new_blog')) {
            rch_multisite_schedule_broadcast_to_new_blog($existing_blog_id);
        }

        return $existing_blog_id;
    }

    $admin_user_id = absint(get_site_option('rch_multisite_admin_user_id', 0));

    if (! $admin_user_id) {
        $admin_email   = get_site_option('admin_email');
        $admin_user    = get_user_by('email', $admin_email);
        $admin_user_id = $admin_user ? (int) $admin_user->ID : 1;
    }

    $site_args = [
        'domain'     => $domain,
        'path'       => $path,
        'title'      => $office_name,
        'network_id' => get_current_network_id(),
        'user_id'    => $admin_user_id,
        'options'    => [
            'blogdescription' => sprintf(
                /* translators: %s: office name */
                __('%s – Office Site', 'rechat-plugin'),
                $office_name
            ),
        ],
    ];

    if (function_exists('wp_insert_site')) {
        $result = wp_insert_site($site_args);
    } else {
        /** @noinspection PhpDeprecationInspection */
        $result = wpmu_create_blog(
            $domain,
            $path,
            $office_name,
            $admin_user_id,
            [],
            get_current_network_id()
        );
    }

    if (is_wp_error($result)) {
        error_log(
            'Rechat Plugin Multisite: Failed to create site for office "' .
            $office_name . '" – ' . $result->get_error_message()
        );

        return $result;
    }

    $blog_id = is_object($result) ? (int) $result->id : (int) $result;

    update_post_meta($post_id, '_rch_office_site_id', $blog_id);
    update_post_meta($post_id, '_rch_office_slug', $slug);

    rch_multisite_configure_new_site($blog_id, $office_name, $post_id, true);

    if (function_exists('rch_multisite_schedule_broadcast_to_new_blog')) {
        rch_multisite_schedule_broadcast_to_new_blog($blog_id);
    }

    error_log(
        'Rechat Plugin Multisite: Created site ' . $domain . $path .
        ' (blog_id=' . $blog_id . ') for office post ' . $post_id
    );

    return $blog_id;
}

/**
 * Configure a freshly-created agent or office sub-site so it looks correct immediately.
 *
 * @param  int    $blog_id       The newly created blog ID.
 * @param  string $site_title    Site title / display name.
 * @param  int|null $post_id     Agent or office post ID for per-post theme; null = network default only.
 * @param  bool   $is_office     Unused for theme; reserved for logging.
 * @return void
 */
function rch_multisite_configure_new_site(int $blog_id, string $site_title, ?int $post_id = null, bool $is_office = false): void
{
    $main_id = get_main_site_id();

    $resolved = ($post_id && $post_id > 0)
        ? rch_multisite_resolve_theme_for_post($post_id)
        : ($is_office
            ? rch_multisite_resolve_theme_network_default_for_offices()
            : rch_multisite_resolve_theme_network_default_for_agents());

    $template   = $resolved['template'];
    $stylesheet = $resolved['stylesheet'];

    // Read remaining settings from the main site.
    $permalink  = (string) get_blog_option($main_id, 'permalink_structure');
    $timezone   = (string) get_blog_option($main_id, 'timezone_string');
    $date_fmt   = (string) get_blog_option($main_id, 'date_format');
    $time_fmt   = (string) get_blog_option($main_id, 'time_format');

    switch_to_blog($blog_id);

    // ── Theme ─────────────────────────────────────────────────────────────────
    if ($template) {
        update_option('template',   $template);
        update_option('stylesheet', $stylesheet ?: $template);

        // Mark the theme as explicitly allowed on this sub-site so WordPress
        // does not fall back to a default theme.
        $allowed = (array) get_option('allowedthemes', []);
        $allowed[$stylesheet ?: $template] = true;
        update_option('allowedthemes', $allowed);
    }

    // ── Permalinks ────────────────────────────────────────────────────────────
    if ($permalink) {
        update_option('permalink_structure', $permalink);
        // Flush rewrite rules so the new permalink structure takes effect.
        flush_rewrite_rules(false);
    }

    // ── Locale / date / time ─────────────────────────────────────────────────
    if ($timezone)  update_option('timezone_string', $timezone);
    if ($date_fmt)  update_option('date_format',     $date_fmt);
    if ($time_fmt)  update_option('time_format',     $time_fmt);

    update_option('rch_rechat_subsite_role', $is_office ? 'office' : 'agent', true);

    // Remove the default "Hello World!" post and "Sample Page" that WordPress
    // creates automatically for every new subsite.
    $default_post = get_posts(array('name' => 'hello-world', 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1));
    if (!empty($default_post)) {
        wp_delete_post($default_post[0]->ID, true);
    }
    $default_page = get_posts(array('name' => 'sample-page', 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 1));
    if (!empty($default_page)) {
        wp_delete_post($default_page[0]->ID, true);
    }

    foreach (rch_multisite_hub_inherited_option_names() as $option_name) {
        if ($option_name === '') {
            continue;
        }

        $local = get_option($option_name, '');

        if (! rch_multisite_option_value_is_empty($local)) {
            continue;
        }

        $hub_val = rch_multisite_fetch_hub_option_value($option_name);

        if (! rch_multisite_option_value_is_empty($hub_val)) {
            update_option($option_name, $hub_val, false);
        }
    }

    restore_current_blog();

    rch_multisite_sync_local_logic_features_to_blog($blog_id, true, false);

    error_log(
        'Rechat Plugin Multisite: Configured site blog_id=' . $blog_id .
        ' – theme: ' . ($stylesheet ?: $template) .
        ', permalink: ' . ($permalink ?: '(default)') .
        ($post_id ? ' post_id=' . $post_id : '')
    );
}

/**
 * Apply theme + settings to all already-created agent sites that are missing
 * their theme (i.e. sites where template option is empty).
 *
 * Called from the AJAX "Fix theme on existing sites" action.
 *
 * @return array{fixed:int,skipped:int}
 */
function rch_multisite_fix_themes_on_existing_sites(): array
{
    $agents = get_posts([
        'post_type'   => 'agents',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

    $fixed   = 0;
    $skipped = 0;

    foreach ($agents as $agent) {
        $blog_id = rch_multisite_get_agent_blog_id($agent->ID);

        if (! $blog_id) {
            $skipped++;
            continue;
        }

        // Check whether the site already has a theme set.
        $current_template = get_blog_option($blog_id, 'template');

        if (empty($current_template)) {
            rch_multisite_configure_new_site($blog_id, $agent->post_title, $agent->ID, false);
            $fixed++;
        } else {
            $skipped++;
        }
    }

    $offices = get_posts([
        'post_type'   => 'offices',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

    foreach ($offices as $office) {
        $blog_id = rch_multisite_get_office_blog_id($office->ID);

        if (! $blog_id) {
            $skipped++;
            continue;
        }

        $current_template = get_blog_option($blog_id, 'template');

        if (empty($current_template)) {
            rch_multisite_configure_new_site($blog_id, $office->post_title, $office->ID, true);
            $fixed++;
        } else {
            $skipped++;
        }
    }

    return ['fixed' => $fixed, 'skipped' => $skipped];
}

/**
 * For every agent/office with a linked blog, activate the theme from
 * rch_multisite_resolve_theme_for_post() (network default + per-row override).
 *
 * Use after saving a new network default so agents without a row override get the new theme.
 * Rows with a per-site theme override keep that theme.
 *
 * @return array{updated:int,unchanged:int,skipped:int,errors:string[]}
 */
function rch_multisite_resync_subsite_themes_from_resolved(): array
{
    $updated   = 0;
    $unchanged = 0;
    $skipped   = 0;
    $errors    = [];

    $sync_posts = static function (array $posts, callable $blog_id_cb) use (&$updated, &$unchanged, &$skipped, &$errors): void {
        foreach ($posts as $post) {
            $blog_id = (int) $blog_id_cb($post->ID);
            if (! $blog_id) {
                $skipped++;
                continue;
            }

            $resolved = rch_multisite_resolve_theme_for_post((int) $post->ID);
            $target   = $resolved['stylesheet'] !== '' ? $resolved['stylesheet'] : $resolved['template'];

            if ($target === '') {
                $skipped++;
                continue;
            }

            $current = (string) get_blog_option($blog_id, 'stylesheet');

            if ($current === $target) {
                $unchanged++;
                continue;
            }

            $result = rch_multisite_activate_theme_on_blog($blog_id, $target);
            if (is_wp_error($result)) {
                $errors[] = $post->post_title . ': ' . $result->get_error_message();
            } else {
                $updated++;
            }
        }
    };

    $agents = get_posts([
        'post_type'   => 'agents',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

    $offices = get_posts([
        'post_type'   => 'offices',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

    $sync_posts($agents, 'rch_multisite_get_agent_blog_id');
    $sync_posts($offices, 'rch_multisite_get_office_blog_id');

    return compact('updated', 'unchanged', 'skipped', 'errors');
}

/**
 * Resolve stylesheet for bulk apply (explicit slug or network default for entity).
 *
 * @param  string|null $stylesheet Raw stylesheet or null/empty to use network default.
 * @param  string      $entity      'agents' or 'offices'.
 * @return array{stylesheet:string,errors:string[]}
 */
function rch_multisite_bulk_resolve_stylesheet(?string $stylesheet, string $entity): array
{
    if ($stylesheet !== null && $stylesheet !== '') {
        $theme = wp_get_theme($stylesheet);
        if (! $theme->exists()) {
            return [
                'stylesheet' => '',
                'errors'     => [
                    sprintf(
                        /* translators: %s: theme slug */
                        __('Theme "%s" is not installed.', 'rechat-plugin'),
                        $stylesheet
                    ),
                ],
            ];
        }

        return ['stylesheet' => $theme->get_stylesheet(), 'errors' => []];
    }

    $pair = $entity === 'offices'
        ? rch_multisite_resolve_theme_network_default_for_offices()
        : rch_multisite_resolve_theme_network_default_for_agents();

    $stylesheet = $pair['stylesheet'];

    if ($stylesheet === '') {
        return [
            'stylesheet' => '',
            'errors'     => [__('Could not resolve a theme to apply.', 'rechat-plugin')],
        ];
    }

    return ['stylesheet' => $stylesheet, 'errors' => []];
}

/**
 * Apply a theme to every agent sub-site that has a linked blog_id.
 *
 * @param  string|null $stylesheet Theme stylesheet slug, or null for network agent default.
 * @return array{updated:int,errors:string[]}
 */
function rch_multisite_bulk_apply_theme_to_agent_sites(?string $stylesheet = null): array
{
    $resolved = rch_multisite_bulk_resolve_stylesheet($stylesheet, 'agents');
    if (! empty($resolved['errors'])) {
        return ['updated' => 0, 'errors' => $resolved['errors']];
    }
    $stylesheet = $resolved['stylesheet'];

    $agents = get_posts([
        'post_type'   => 'agents',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

    $updated = 0;
    $errors  = [];

    foreach ($agents as $agent) {
        $blog_id = rch_multisite_get_agent_blog_id($agent->ID);
        if (! $blog_id) {
            continue;
        }

        $result = rch_multisite_activate_theme_on_blog($blog_id, $stylesheet);
        if (is_wp_error($result)) {
            $errors[] = $agent->post_title . ': ' . $result->get_error_message();
        } else {
            $updated++;
        }
    }

    return compact('updated', 'errors');
}

/**
 * Apply a theme to every office sub-site that has a linked blog_id.
 *
 * @param  string|null $stylesheet Theme stylesheet slug, or null for network office default.
 * @return array{updated:int,errors:string[]}
 */
function rch_multisite_bulk_apply_theme_to_office_sites(?string $stylesheet = null): array
{
    $resolved = rch_multisite_bulk_resolve_stylesheet($stylesheet, 'offices');
    if (! empty($resolved['errors'])) {
        return ['updated' => 0, 'errors' => $resolved['errors']];
    }
    $stylesheet = $resolved['stylesheet'];

    $offices = get_posts([
        'post_type'   => 'offices',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

    $updated = 0;
    $errors  = [];

    foreach ($offices as $office) {
        $blog_id = rch_multisite_get_office_blog_id($office->ID);
        if (! $blog_id) {
            continue;
        }

        $result = rch_multisite_activate_theme_on_blog($blog_id, $stylesheet);
        if (is_wp_error($result)) {
            $errors[] = $office->post_title . ': ' . $result->get_error_message();
        } else {
            $updated++;
        }
    }

    return compact('updated', 'errors');
}

/**
 * AJAX handler: fix missing themes on all existing agent sites.
 *
 * @return void
 */
function rch_multisite_ajax_fix_themes(): void
{
    check_ajax_referer('rch_multisite_fix_themes', '_nonce');

    if (! current_user_can('manage_network_options')) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    $result = rch_multisite_fix_themes_on_existing_sites();

    wp_send_json_success([
        'message' => sprintf(
            /* translators: 1: fixed count, 2: skipped count */
            __('Done. Theme applied to %1$d site(s). %2$d already had a theme.', 'rechat-plugin'),
            $result['fixed'],
            $result['skipped']
        ),
    ]);
}
add_action('wp_ajax_rch_multisite_fix_themes', 'rch_multisite_ajax_fix_themes');

/**
 * AJAX: push resolved theme (network default + per-row overrides) to all linked sub-sites.
 *
 * @return void
 */
function rch_multisite_ajax_resync_themes(): void
{
    check_ajax_referer('rch_multisite_resync_themes', '_nonce');

    if (! current_user_can('manage_network_options')) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    $result = rch_multisite_resync_subsite_themes_from_resolved();

    $message = sprintf(
        /* translators: 1: updated count, 2: unchanged count, 3: skipped count */
        __('Done. Updated %1$d sub-site(s). %2$d already matched. %3$d skipped (no linked site or no theme resolved).', 'rechat-plugin'),
        $result['updated'],
        $result['unchanged'],
        $result['skipped']
    );

    wp_send_json_success([
        'message' => $message,
        'errors'  => $result['errors'],
    ]);
}
add_action('wp_ajax_rch_multisite_resync_themes', 'rch_multisite_ajax_resync_themes');

/**
 * AJAX: bulk-reassign the agent sub-site role for every provisioned agent user (no emails).
 *
 * @return void
 */
function rch_multisite_ajax_reassign_agent_site_user_roles(): void
{
    check_ajax_referer('rch_multisite_reassign_agent_roles', '_nonce');

    if (! is_multisite()) {
        wp_send_json_error(__('Multisite is not enabled.', 'rechat-plugin'));
        return;
    }

    if (
        ! function_exists('rch_current_user_can_manage_rechat')
        || ! rch_current_user_can_manage_rechat()
        || ! current_user_can('manage_options')
    ) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    $result = rch_multisite_bulk_reassign_agent_site_user_roles();

    $message = sprintf(
        /* translators: 1: number of users updated, 2: number of agent rows skipped, 3: subsites that received hub API keys */
        __('Done. Role updated for %1$d account(s). %2$d agent row(s) skipped (no sub-site, invalid email, or no WordPress user with that email). Local Logic / Google Map API keys and the Local Content feature were applied on %3$d sub-site(s) (empty keys copied from the main site; Local Content enabled on each agent sub-site).', 'rechat-plugin'),
        $result['updated'],
        $result['skipped'],
        isset($result['api_keys_synced']) ? (int) $result['api_keys_synced'] : 0
    );

    wp_send_json_success([
        'message' => $message,
        'errors'  => $result['errors'],
    ]);
}
add_action('wp_ajax_rch_multisite_reassign_agent_site_user_roles', 'rch_multisite_ajax_reassign_agent_site_user_roles');

/**
 * AJAX: apply selected theme to all agent sub-sites (bulk).
 *
 * @return void
 */
function rch_multisite_ajax_bulk_apply_theme(): void
{
    check_ajax_referer('rch_multisite_bulk_theme', '_nonce');

    if (! current_user_can('manage_network_options')) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    $raw = isset($_POST['theme']) ? sanitize_text_field(wp_unslash($_POST['theme'])) : '';
    $entity = isset($_POST['entity']) ? sanitize_key(wp_unslash($_POST['entity'])) : 'agents';
    if (! in_array($entity, ['agents', 'offices'], true)) {
        $entity = 'agents';
    }

    $stylesheet = $raw === '' ? null : $raw;
    $result     = $entity === 'offices'
        ? rch_multisite_bulk_apply_theme_to_office_sites($stylesheet)
        : rch_multisite_bulk_apply_theme_to_agent_sites($stylesheet);

    if (! empty($result['errors'])) {
        wp_send_json_success([
            'message' => sprintf(
                /* translators: %d: number of sites updated */
                __('Applied theme to %d site(s). Some sites reported errors — see list.', 'rechat-plugin'),
                $result['updated']
            ),
            'updated' => $result['updated'],
            'errors'  => $result['errors'],
        ]);
        return;
    }

    $msg = $entity === 'offices'
        ? sprintf(
            /* translators: %d: number of office sites */
            __('Theme applied successfully to %d office sub-site(s).', 'rechat-plugin'),
            $result['updated']
        )
        : sprintf(
            /* translators: %d: number of agent sites */
            __('Theme applied successfully to %d agent sub-site(s).', 'rechat-plugin'),
            $result['updated']
        );

    wp_send_json_success([
        'message' => $msg,
        'updated' => $result['updated'],
        'errors'  => [],
    ]);
}
add_action('wp_ajax_rch_multisite_bulk_apply_theme', 'rch_multisite_ajax_bulk_apply_theme');

/**
 * AJAX: save per-agent or per-office theme from the Multisite status table row.
 *
 * @return void
 */
function rch_multisite_ajax_save_row_theme(): void
{
    check_ajax_referer('rch_multisite_row_theme', '_nonce');

    if (! current_user_can('manage_network_options')) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    $post_id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
    $theme_raw = isset($_POST['theme']) ? sanitize_text_field(wp_unslash($_POST['theme'])) : '';

    if (! $post_id || ! in_array($post_type, ['agents', 'offices'], true)) {
        wp_send_json_error(__('Invalid request.', 'rechat-plugin'));
        return;
    }

    if (get_post_type($post_id) !== $post_type) {
        wp_send_json_error(__('Invalid post.', 'rechat-plugin'));
        return;
    }

    if ($theme_raw !== '' && ! wp_get_theme($theme_raw)->exists()) {
        wp_send_json_error(__('That theme is not installed.', 'rechat-plugin'));
        return;
    }

    if ($theme_raw === '') {
        delete_post_meta($post_id, '_rch_subsite_theme_stylesheet');
    } else {
        update_post_meta($post_id, '_rch_subsite_theme_stylesheet', $theme_raw);
    }

    $blog_id = $post_type === 'agents'
        ? rch_multisite_get_agent_blog_id($post_id)
        : rch_multisite_get_office_blog_id($post_id);

    if ($blog_id) {
        $pair = rch_multisite_resolve_theme_for_post($post_id);
        if (! empty($pair['stylesheet'])) {
            $applied = rch_multisite_activate_theme_on_blog($blog_id, $pair['stylesheet']);
            if (is_wp_error($applied)) {
                wp_send_json_error($applied->get_error_message());
                return;
            }
        }
    }

    wp_send_json_success([
        'message' => __('Theme saved.', 'rechat-plugin'),
    ]);
}
add_action('wp_ajax_rch_multisite_save_row_theme', 'rch_multisite_ajax_save_row_theme');

/**
 * Update the site's display name when an agent's name changes.
 *
 * The subdomain / path is intentionally kept stable after creation –
 * changing a live subdomain or path would break all existing permalinks.
 *
 * @param  int    $blog_id   Network blog ID to update.
 * @param  string $new_name  New agent display name.
 * @return void
 */
function rch_multisite_update_site_for_agent(int $blog_id, string $new_name): void
{
    switch_to_blog($blog_id);
    update_option('blogname', $new_name);
    update_option(
        'blogdescription',
        sprintf(
            /* translators: %s: agent name */
            __('%s – Agent Site', 'rechat-plugin'),
            $new_name
        )
    );
    restore_current_blog();

    error_log(
        'Rechat Plugin Multisite: Updated blogname for blog_id=' . $blog_id .
        ' to "' . $new_name . '"'
    );
}

/**
 * Update the site's display name when an office's title changes.
 *
 * @param  int    $blog_id   Network blog ID.
 * @param  string $new_name  New office display name.
 * @return void
 */
function rch_multisite_update_site_for_office(int $blog_id, string $new_name): void
{
    switch_to_blog($blog_id);
    update_option('blogname', $new_name);
    update_option(
        'blogdescription',
        sprintf(
            /* translators: %s: office name */
            __('%s – Office Site', 'rechat-plugin'),
            $new_name
        )
    );
    restore_current_blog();

    error_log(
        'Rechat Plugin Multisite: Updated office blogname for blog_id=' . $blog_id .
        ' to "' . $new_name . '"'
    );
}

/**
 * Enable or disable the network site linked to an agent post.
 *
 * Disabled  → site is archived (content preserved, URL returns 404).
 * Re-enabled → site is unarchived.
 *
 * @param  int  $post_id  Agent post ID.
 * @param  bool $enabled  True to enable, false to disable.
 * @return void
 */
function rch_multisite_set_agent_site_enabled(int $post_id, bool $enabled): void
{
    update_post_meta($post_id, '_rch_agent_site_enabled', $enabled ? '1' : '0');

    $blog_id = rch_multisite_get_agent_blog_id($post_id);

    if (! $blog_id) {
        return;
    }

    if ($enabled) {
        // Un-archive: set archived = 0, deleted = 0, spam = 0.
        update_blog_status($blog_id, 'archived', '0');
        update_blog_status($blog_id, 'deleted', '0');
        clean_blog_cache($blog_id);
        error_log(
            'Rechat Plugin Multisite: Enabled site blog_id=' . $blog_id .
            ' for agent post ' . $post_id
        );
    } else {
        // Archive: front-end blocked for everyone (see rch_multisite_block_archived_agent_frontend).
        update_blog_status($blog_id, 'archived', '1');
        clean_blog_cache($blog_id);
        error_log(
            'Rechat Plugin Multisite: Disabled (archived) site blog_id=' . $blog_id .
            ' for agent post ' . $post_id
        );
    }
}

/**
 * Enable or disable the network site linked to an office post.
 *
 * @param  int  $post_id  Office post ID.
 * @param  bool $enabled  True to enable, false to archive.
 * @return void
 */
function rch_multisite_set_office_site_enabled(int $post_id, bool $enabled): void
{
    update_post_meta($post_id, '_rch_office_site_enabled', $enabled ? '1' : '0');

    $blog_id = rch_multisite_get_office_blog_id($post_id);

    if (! $blog_id) {
        return;
    }

    if ($enabled) {
        update_blog_status($blog_id, 'archived', '0');
        update_blog_status($blog_id, 'deleted', '0');
        clean_blog_cache($blog_id);
        error_log(
            'Rechat Plugin Multisite: Enabled office site blog_id=' . $blog_id .
            ' for office post ' . $post_id
        );
    } else {
        update_blog_status($blog_id, 'archived', '1');
        clean_blog_cache($blog_id);
        error_log(
            'Rechat Plugin Multisite: Disabled (archived) office site blog_id=' . $blog_id .
            ' for office post ' . $post_id
        );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2c – ARCHIVED SITE: BLOCK PUBLIC + ADMIN BAR STYLES ON SUB-SITES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Block the public front-end for archived agent sub-sites for all users,
 * including network Super Admins.
 *
 * WordPress core intentionally lets Super Admins bypass ms_site_check() for
 * archived blogs so they can still open the site. That made “Disable site”
 * appear to do nothing while logged in as Super Admin. This callback runs on
 * template_redirect and stops the front-end only; wp-admin remains available.
 *
 * @return void
 */
function rch_multisite_block_archived_agent_frontend(): void
{
    if (! is_multisite() || get_current_blog_id() <= 1) {
        return;
    }

    if (is_admin()) {
        return;
    }

    if (wp_doing_ajax() || wp_doing_cron()) {
        return;
    }

    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    $site = get_site();
    if (! $site || '1' !== $site->archived) {
        return;
    }

    wp_die(
        '<p>' . esc_html__('This site has been archived or suspended.', 'rechat-plugin') . '</p>',
        esc_html__('Site unavailable', 'rechat-plugin'),
        ['response' => 410]
    );
}
add_action('template_redirect', 'rch_multisite_block_archived_agent_frontend', 0);

/**
 * Ensure admin-bar (and dashicons) styles load on agent sub-sites when the toolbar
 * is shown. Fixes unstyled admin bar when the theme omits dependencies.
 *
 * @return void
 */
function rch_multisite_enqueue_admin_bar_assets(): void
{
    if (! is_user_logged_in() || ! is_admin_bar_showing()) {
        return;
    }

    if (! is_multisite() || get_current_blog_id() <= 1) {
        return;
    }

    if (wp_style_is('admin-bar', 'enqueued') || wp_style_is('admin-bar', 'done')) {
        return;
    }

    wp_enqueue_style('admin-bar');
    if (! wp_style_is('dashicons', 'enqueued') && ! wp_style_is('dashicons', 'done')) {
        wp_enqueue_style('dashicons');
    }
}
add_action('wp_enqueue_scripts', 'rch_multisite_enqueue_admin_bar_assets', 99);

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3 – HOOKS (post save / sync / delete)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Fired on save_post_agents – handles manual saves from the WP admin.
 *
 * Skipped during autosave, revisions, and API-sync (which uses its own action).
 * Respects the per-agent enabled flag.
 *
 * @param  int $post_id  Agent post ID.
 * @return void
 */
function rch_multisite_on_save_agent_post(int $post_id): void
{
    if (! rch_multisite_is_create_agent_sites_enabled()) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    // During API sync, helper.php fires rch_after_agent_synced instead.
    if (function_exists('rch_is_doing_agent_sync') && rch_is_doing_agent_sync()) {
        return;
    }

    // Respect the per-agent enabled flag.
    if (! rch_multisite_is_agent_site_enabled($post_id)) {
        return;
    }

    $agent_name = get_the_title($post_id);

    if (empty(trim($agent_name))) {
        return;
    }

    $existing_blog_id = rch_multisite_get_agent_blog_id($post_id);

    if ($existing_blog_id) {
        rch_multisite_update_site_for_agent($existing_blog_id, $agent_name);
    } else {
        rch_multisite_create_site_for_agent($post_id, $agent_name);
    }
}
add_action('save_post_agents', 'rch_multisite_on_save_agent_post');

/**
 * Manual save of an office post — create or update its sub-site when enabled.
 *
 * @param  int $post_id Office post ID.
 * @return void
 */
function rch_multisite_on_save_office_post(int $post_id): void
{
    if (! rch_multisite_is_create_office_sites_enabled()) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    if (function_exists('rch_is_doing_rechat_sync') && rch_is_doing_rechat_sync()) {
        return;
    }

    if (! rch_multisite_is_office_site_enabled($post_id)) {
        return;
    }

    $title = get_the_title($post_id);

    if (empty(trim((string) $title))) {
        return;
    }

    $existing_blog_id = rch_multisite_get_office_blog_id($post_id);

    if ($existing_blog_id) {
        rch_multisite_update_site_for_office($existing_blog_id, $title);
    } else {
        rch_multisite_create_site_for_office($post_id, $title);
    }
}
add_action('save_post_offices', 'rch_multisite_on_save_office_post');

/**
 * Fired by rch_process_agents_data() via do_action('rch_after_agent_synced') –
 * handles both new and updated agents during an API sync.
 * Respects the per-agent enabled flag.
 *
 * @param  int    $post_id     Agent post ID.
 * @param  string $agent_name  Agent display name (full name from API).
 * @return void
 */
function rch_multisite_on_agent_synced(int $post_id, string $agent_name): void
{
    if (empty(trim($agent_name))) {
        return;
    }

    // Skip if this agent has been manually disabled.
    if (! rch_multisite_is_agent_site_enabled($post_id)) {
        return;
    }

    $existing_blog_id = rch_multisite_get_agent_blog_id($post_id);

    if ($existing_blog_id) {
        rch_multisite_update_site_for_agent($existing_blog_id, $agent_name);
    } else {
        rch_multisite_create_site_for_agent($post_id, $agent_name);
    }
}
add_action('rch_after_agent_synced', 'rch_multisite_on_agent_synced', 10, 2);

/**
 * When an agent post is deleted, archive or permanently delete the linked site.
 *
 * Default: archive (safe).
 * Set site option `rch_multisite_delete_site_on_agent_delete` = 1 to hard-delete.
 *
 * @param  int $post_id  Agent post ID being deleted.
 * @return void
 */
function rch_multisite_on_agent_deleted(int $post_id): void
{
    $pt = get_post_type($post_id);

    if ($pt === 'agents') {
        $blog_id = rch_multisite_get_agent_blog_id($post_id);
    } elseif ($pt === 'offices') {
        $blog_id = rch_multisite_get_office_blog_id($post_id);
    } else {
        return;
    }

    if (! $blog_id) {
        return;
    }

    if (get_site_option('rch_multisite_delete_site_on_agent_delete', 0)) {
        wpmu_delete_blog($blog_id, true);
        error_log(
            'Rechat Plugin Multisite: Deleted site blog_id=' . $blog_id .
            ' because ' . $pt . ' post ' . $post_id . ' was deleted.'
        );
    } else {
        update_blog_status($blog_id, 'archived', 1);
        error_log(
            'Rechat Plugin Multisite: Archived site blog_id=' . $blog_id .
            ' because ' . $pt . ' post ' . $post_id . ' was deleted.'
        );
    }
}
add_action('before_delete_post', 'rch_multisite_on_agent_deleted');

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3b – SYNC ACCUMULATOR (count multisite results during agent sync)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Persistent counters for the current sync run.
 * Uses a static variable so every call to rch_multisite_on_agent_synced()
 * within the same request accumulates into one shared set of numbers.
 *
 * @param  string|null $key    Counter key ('created'|'updated'|'skipped'|'errors').
 *                             Pass null to read the full array.
 * @param  int         $delta  Amount to add (default 1).
 * @return array               Current counters array.
 */
function rch_multisite_sync_counter(?string $key = null, int $delta = 1): array
{
    static $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

    if ($key !== null && array_key_exists($key, $counts)) {
        $counts[$key] += $delta;
    }

    return $counts;
}

/**
 * Upgrade rch_multisite_on_agent_synced to track results in the accumulator
 * so the sync-response filter can report them.
 *
 * We remove the original callback and replace it with this wrapper that
 * also increments the counters.
 *
 * @param  int    $post_id     Agent post ID.
 * @param  string $agent_name  Agent display name.
 * @return void
 */
function rch_multisite_on_agent_synced_counted(int $post_id, string $agent_name): void
{
    if (! rch_multisite_is_create_agent_sites_enabled()) {
        return;
    }

    if (empty(trim($agent_name))) {
        return;
    }

    // During a full API sync, defer provisioning until the sync finishes (after
    // rch_delete_outdated_posts removes stale agents). Otherwise a re-created
    // agent (new Rechat api_id) provisions a "-2" duplicate while the old post
    // still owns the base slug, then the old site gets archived when the old
    // post is deleted. Flushed in rch_multisite_append_sync_result().
    if (function_exists('rch_is_doing_agent_sync') && rch_is_doing_agent_sync()) {
        $GLOBALS['rch_multisite_deferred_agent_provision'][$post_id] = $agent_name;
        return;
    }

    rch_multisite_provision_agent_site_now($post_id, $agent_name);
}

/**
 * Create/update one agent sub-site now and update the sync counters.
 *
 * @param  int    $post_id     Agent post ID.
 * @param  string $agent_name  Agent display name.
 * @return void
 */
function rch_multisite_provision_agent_site_now(int $post_id, string $agent_name): void
{
    if (! rch_multisite_is_agent_site_enabled($post_id)) {
        rch_multisite_sync_counter('skipped');
        return;
    }

    $existing_blog_id = rch_multisite_get_agent_blog_id($post_id);

    if ($existing_blog_id) {
        rch_multisite_update_site_for_agent($existing_blog_id, $agent_name);
        rch_multisite_sync_counter('updated');
    } else {
        $result = rch_multisite_create_site_for_agent($post_id, $agent_name);
        if (is_wp_error($result)) {
            rch_multisite_sync_counter('errors');
        } else {
            rch_multisite_sync_counter('created');
        }
    }
}

// Replace the original rch_after_agent_synced callback with the counted version.
remove_action('rch_after_agent_synced', 'rch_multisite_on_agent_synced', 10);
add_action('rch_after_agent_synced', 'rch_multisite_on_agent_synced_counted', 10, 2);

/**
 * Append multisite provisioning results to the admin sync-response data.
 *
 * Hooked into 'rch_sync_response_data' which is applied right before
 * wp_send_json() in rch_update_agents_offices_regions_data().
 *
 * @param  array $data  Existing sync result strings keyed by label.
 * @return array
 */
function rch_multisite_append_sync_result(array $data): array
{
    // Flush provisioning deferred during the sync. This runs after both
    // rch_delete_outdated_posts() calls, so a re-created agent/office (changed
    // Rechat id) adopts and reactivates its existing sub-site instead of getting
    // a "-2" duplicate or leaving the original archived.
    if (! empty($GLOBALS['rch_multisite_deferred_agent_provision'])) {
        $queue = $GLOBALS['rch_multisite_deferred_agent_provision'];
        $GLOBALS['rch_multisite_deferred_agent_provision'] = [];
        foreach ($queue as $queued_post_id => $queued_name) {
            if (get_post_type((int) $queued_post_id) === 'agents') {
                rch_multisite_provision_agent_site_now((int) $queued_post_id, (string) $queued_name);
            }
        }
    }

    if (! empty($GLOBALS['rch_multisite_deferred_office_provision'])) {
        $queue = $GLOBALS['rch_multisite_deferred_office_provision'];
        $GLOBALS['rch_multisite_deferred_office_provision'] = [];
        foreach ($queue as $queued_post_id => $queued_name) {
            if (get_post_type((int) $queued_post_id) === 'offices') {
                rch_multisite_provision_office_site_now((int) $queued_post_id, (string) $queued_name);
            }
        }
    }

    $counts        = rch_multisite_sync_counter();
    $office_counts = rch_multisite_office_sync_counter();

    if (! rch_multisite_is_create_agent_sites_enabled()) {
        $data['multisite'] = '<b>' . esc_html__('Agent Sites (Multisite)', 'rechat-plugin') . '</b><br> '
            . esc_html__('Off — enable “Create a sub-site for each agent” in Rechat → Multisite.', 'rechat-plugin');
    } else {
        $data['multisite'] = sprintf(
            '<b>%s</b><br> created: %d, updated: %d, skipped (disabled): %d, errors: %d',
            esc_html__('Agent Sites (Multisite)', 'rechat-plugin'),
            $counts['created'],
            $counts['updated'],
            $counts['skipped'],
            $counts['errors']
        );
    }

    if (! rch_multisite_is_create_office_sites_enabled()) {
        $data['multisite'] .= '<br><br><b>' . esc_html__('Office Sites (Multisite)', 'rechat-plugin') . '</b><br> '
            . esc_html__('Off — enable “Create a sub-site for each office” in Rechat → Multisite.', 'rechat-plugin');
    } else {
        $data['multisite'] .= sprintf(
            '<br><br><b>%s</b><br> created: %d, updated: %d, skipped (disabled): %d, errors: %d',
            esc_html__('Office Sites (Multisite)', 'rechat-plugin'),
            $office_counts['created'],
            $office_counts['updated'],
            $office_counts['skipped'],
            $office_counts['errors']
        );
    }

    return $data;
}
add_filter('rch_sync_response_data', 'rch_multisite_append_sync_result');

/**
 * Counters for office multisite operations during the same sync request.
 *
 * @param  string|null $key   created|updated|skipped|errors, or null to read all.
 * @param  int         $delta Increment.
 * @return array
 */
function rch_multisite_office_sync_counter(?string $key = null, int $delta = 1): array
{
    static $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

    if ($key !== null && array_key_exists($key, $counts)) {
        $counts[$key] += $delta;
    }

    return $counts;
}

/**
 * @param  int    $post_id     Office post ID.
 * @param  string $office_name Office name.
 * @return void
 */
function rch_multisite_on_office_synced_counted(int $post_id, string $office_name): void
{
    if (! rch_multisite_is_create_office_sites_enabled()) {
        return;
    }

    if (empty(trim($office_name))) {
        return;
    }

    // Defer during sync (see rch_multisite_on_agent_synced_counted): provisioning
    // before the stale office post is deleted lets the old post's deletion archive
    // the shared site. Offices sync under the rch_doing_rechat_sync flag. Flushed
    // in rch_multisite_append_sync_result().
    if (function_exists('rch_is_doing_rechat_sync') && rch_is_doing_rechat_sync()) {
        $GLOBALS['rch_multisite_deferred_office_provision'][$post_id] = $office_name;
        return;
    }

    rch_multisite_provision_office_site_now($post_id, $office_name);
}

/**
 * Create/update one office sub-site now and update the office sync counters.
 *
 * @param  int    $post_id      Office post ID.
 * @param  string $office_name  Office display name.
 * @return void
 */
function rch_multisite_provision_office_site_now(int $post_id, string $office_name): void
{
    if (! rch_multisite_is_office_site_enabled($post_id)) {
        rch_multisite_office_sync_counter('skipped');

        return;
    }

    $existing_blog_id = rch_multisite_get_office_blog_id($post_id);

    if ($existing_blog_id) {
        rch_multisite_update_site_for_office($existing_blog_id, $office_name);
        rch_multisite_office_sync_counter('updated');
    } else {
        $result = rch_multisite_create_site_for_office($post_id, $office_name);
        if (is_wp_error($result)) {
            rch_multisite_office_sync_counter('errors');
        } else {
            rch_multisite_office_sync_counter('created');
        }
    }
}

add_action('rch_after_office_synced', 'rch_multisite_on_office_synced_counted', 10, 2);

// ─────────────────────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Register the "Agent Site" metabox on the agent post edit screen.
 *
 * @return void
 */
function rch_multisite_register_agent_metabox(): void
{
    add_meta_box(
        'rch_agent_site_control',
        __('Agent Site', 'rechat-plugin'),
        'rch_multisite_render_agent_metabox',
        'agents',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'rch_multisite_register_agent_metabox');

/**
 * Render the Agent Site metabox.
 *
 * @param  WP_Post $post  Current agent post.
 * @return void
 */
function rch_multisite_render_agent_metabox(WP_Post $post): void
{
    wp_nonce_field('rch_agent_site_metabox_' . $post->ID, 'rch_agent_site_metabox_nonce');

    $reprovision_nonce = wp_create_nonce('rch_multisite_reprovision_editor');

    $enabled         = rch_multisite_is_agent_site_enabled($post->ID);
    $blog_id         = rch_multisite_get_agent_blog_id($post->ID);
    $slug            = (string) get_post_meta($post->ID, '_rch_agent_slug', true);
    $theme_choices   = rch_multisite_get_theme_choices();
    $subsite_theme   = (string) get_post_meta($post->ID, '_rch_subsite_theme_stylesheet', true);

    // Build site URL preview.
    if ($slug) {
        $location = rch_multisite_build_site_location($slug);
        $site_url = 'https://' . $location['domain'] . rtrim($location['path'], '/');
    } else {
        $slug_preview = rch_multisite_agent_site_slug_base($post->ID, $post->post_title);
        if ($slug_preview) {
            $location = rch_multisite_build_site_location($slug_preview);
            $site_url = 'https://' . $location['domain'] . rtrim($location['path'], '/');
        } else {
            $site_url = '';
        }
    }

    ?>
    <?php if (! rch_multisite_is_create_agent_sites_enabled()) : ?>
        <p class="notice notice-warning inline" style="margin:0 0 10px;padding:8px 10px;">
            <?php
            esc_html_e(
                'Network-wide sub-site creation is turned off. Enable “Create a sub-site for each agent” under Rechat → Multisite to create or sync sites.',
                'rechat-plugin'
            );
            ?>
        </p>
    <?php endif; ?>

    <div id="rch-agent-site-metabox-root">

    <p>
        <label style="display:flex;align-items:center;gap:8px;cursor:<?php echo rch_multisite_is_create_agent_sites_enabled() ? 'pointer' : 'default'; ?>;">
            <input
                type="checkbox"
                name="rch_agent_site_enabled"
                value="1"
                <?php checked($enabled); ?>
                <?php disabled(! rch_multisite_is_create_agent_sites_enabled() && ! $blog_id); ?>
                style="margin:0;"
            >
            <strong><?php esc_html_e('Enable site for this agent', 'rechat-plugin'); ?></strong>
        </label>
    </p>

    <p style="margin-top:12px;">
        <label for="rch_subsite_theme_stylesheet_agent">
            <strong><?php esc_html_e('Theme for this sub-site', 'rechat-plugin'); ?></strong>
        </label>
    </p>
    <select name="rch_subsite_theme_stylesheet" id="rch_subsite_theme_stylesheet_agent" class="widefat">
        <option value="" <?php selected($subsite_theme, ''); ?>>
            <?php esc_html_e('Use network default', 'rechat-plugin'); ?>
        </option>
        <?php foreach ($theme_choices as $slug_opt => $label) : ?>
            <option value="<?php echo esc_attr($slug_opt); ?>" <?php selected($subsite_theme, $slug_opt); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">
        <?php esc_html_e('Optional. Overrides the global default in Rechat → Multisite for this agent only.', 'rechat-plugin'); ?>
    </p>

    <?php if ($blog_id) : ?>
        <p style="margin-top:8px;">
            <span style="color:<?php echo $enabled ? '#46b450' : '#999'; ?>;">
                <?php echo $enabled
                    ? '&#10003; ' . esc_html__('Site is active', 'rechat-plugin')
                    : '&#9679; ' . esc_html__('Site is disabled (archived)', 'rechat-plugin');
                ?>
            </span>
        </p>
        <p>
            <a href="<?php echo esc_url(get_site_url($blog_id)); ?>" target="_blank" rel="noopener" class="button button-small">
                <?php esc_html_e('View Site', 'rechat-plugin'); ?>
            </a>
            <a href="<?php echo esc_url(get_admin_url($blog_id)); ?>" target="_blank" rel="noopener" class="button button-small">
                <?php esc_html_e('Site Admin', 'rechat-plugin'); ?>
            </a>
        </p>
        <?php if (is_multisite()) : ?>
            <p style="margin-top:10px;">
                <button
                    type="button"
                    class="button button-secondary rch-reprovision-agent-editor"
                    data-post-id="<?php echo esc_attr((string) $post->ID); ?>"
                    data-nonce="<?php echo esc_attr($reprovision_nonce); ?>"
                >
                    <?php esc_html_e('Update editor user & email', 'rechat-plugin'); ?>
                </button>
            </p>
            <p class="description rch-reprovision-editor-feedback" style="margin-top:4px;min-height:1.2em;" aria-live="polite"></p>
        <?php endif; ?>
        <p class="description">
            <?php esc_html_e('Blog ID:', 'rechat-plugin'); ?> <code><?php echo esc_html((string) $blog_id); ?></code>
        </p>
    <?php elseif ($site_url) : ?>
        <p class="description" style="margin-top:8px;">
            <?php esc_html_e('Site URL will be:', 'rechat-plugin'); ?><br>
            <code><?php echo esc_html($site_url); ?></code>
        </p>
    <?php endif; ?>

    </div>

    <?php if (is_multisite() && $blog_id) : ?>
        <script>
        (function ($) {
            $(document).on('click', '#rch-agent-site-metabox-root .rch-reprovision-agent-editor', function () {
                var $btn   = $(this);
                var postId = $btn.data('post-id');
                var nonce  = $btn.data('nonce');
                var $fb    = $('#rch-agent-site-metabox-root .rch-reprovision-editor-feedback');

                $btn.prop('disabled', true);
                $fb.css({ color: '' }).text(<?php echo wp_json_encode(__('Sending…', 'rechat-plugin')); ?>);

                $.post(ajaxurl, {
                    action:  'rch_multisite_reprovision_agent_editor',
                    _nonce:  nonce,
                    post_id: postId,
                }, function (response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $fb.css('color', '#00a32a').text(response.data.message || <?php echo wp_json_encode(__('Done.', 'rechat-plugin')); ?>);
                    } else {
                        $fb.css('color', '#d63638').text(response.data || <?php echo wp_json_encode(__('An error occurred.', 'rechat-plugin')); ?>);
                    }
                }).fail(function () {
                    $btn.prop('disabled', false);
                    $fb.css('color', '#d63638').text(<?php echo wp_json_encode(__('Request failed. Please try again.', 'rechat-plugin')); ?>);
                });
            });
        })(jQuery);
        </script>
    <?php endif; ?>
    <?php
}

/**
 * Save the per-agent site enabled flag from the metabox.
 *
 * Runs on save_post with priority 5 so that the meta is written before
 * save_post_agents (priority 10) reads it to decide whether to create a site.
 *
 * @param  int $post_id  Post ID being saved.
 * @return void
 */
function rch_multisite_save_agent_metabox(int $post_id): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'agents') {
        return;
    }

    if (
        ! isset($_POST['rch_agent_site_metabox_nonce']) ||
        ! wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['rch_agent_site_metabox_nonce'])),
            'rch_agent_site_metabox_' . $post_id
        )
    ) {
        return;
    }

    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    // When sub-site creation is off and this agent has no site yet, the checkbox is
    // disabled — it is not posted, so do not treat a missing value as "disabled".
    if (! rch_multisite_is_create_agent_sites_enabled() && ! rch_multisite_get_agent_blog_id($post_id)) {
        return;
    }

    $new_enabled = isset($_POST['rch_agent_site_enabled']) && $_POST['rch_agent_site_enabled'] === '1';

    rch_multisite_set_agent_site_enabled($post_id, $new_enabled);

    if (isset($_POST['rch_subsite_theme_stylesheet'])) {
        $raw = sanitize_text_field(wp_unslash($_POST['rch_subsite_theme_stylesheet']));
        if ($raw === '' || wp_get_theme($raw)->exists()) {
            if ($raw === '') {
                delete_post_meta($post_id, '_rch_subsite_theme_stylesheet');
            } else {
                update_post_meta($post_id, '_rch_subsite_theme_stylesheet', $raw);
            }

            $bid = rch_multisite_get_agent_blog_id($post_id);
            if ($bid) {
                $pair = rch_multisite_resolve_theme_for_post($post_id);
                if (! empty($pair['stylesheet'])) {
                    rch_multisite_activate_theme_on_blog($bid, $pair['stylesheet']);
                }
            }
        }
    }
}
// Priority 5 → runs before save_post_agents (priority 10).
add_action('save_post', 'rch_multisite_save_agent_metabox', 5);

/**
 * Register the Office Site metabox.
 *
 * @return void
 */
function rch_multisite_register_office_metabox(): void
{
    add_meta_box(
        'rch_office_site_control',
        __('Office Site', 'rechat-plugin'),
        'rch_multisite_render_office_metabox',
        'offices',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'rch_multisite_register_office_metabox');

/**
 * Render the Office Site metabox.
 *
 * @param  WP_Post $post Office post.
 * @return void
 */
function rch_multisite_render_office_metabox(WP_Post $post): void
{
    wp_nonce_field('rch_office_site_metabox_' . $post->ID, 'rch_office_site_metabox_nonce');

    $enabled       = rch_multisite_is_office_site_enabled($post->ID);
    $blog_id       = rch_multisite_get_office_blog_id($post->ID);
    $slug          = (string) get_post_meta($post->ID, '_rch_office_slug', true);
    $theme_choices = rch_multisite_get_theme_choices();
    $subsite_theme = (string) get_post_meta($post->ID, '_rch_subsite_theme_stylesheet', true);

    if ($slug) {
        $location = rch_multisite_build_site_location($slug);
        $site_url = 'https://' . $location['domain'] . rtrim($location['path'], '/');
    } else {
        $slug_preview = rch_multisite_office_public_slug($post->post_title);
        if ($slug_preview) {
            $location = rch_multisite_build_site_location($slug_preview);
            $site_url = 'https://' . $location['domain'] . rtrim($location['path'], '/');
        } else {
            $site_url = '';
        }
    }

    ?>
    <?php if (! rch_multisite_is_create_office_sites_enabled()) : ?>
        <p class="notice notice-warning inline" style="margin:0 0 10px;padding:8px 10px;">
            <?php
            esc_html_e(
                'Network-wide office sub-site creation is turned off. Enable it under Rechat → Multisite.',
                'rechat-plugin'
            );
            ?>
        </p>
    <?php endif; ?>

    <p>
        <label style="display:flex;align-items:center;gap:8px;cursor:<?php echo rch_multisite_is_create_office_sites_enabled() ? 'pointer' : 'default'; ?>;">
            <input
                type="checkbox"
                name="rch_office_site_enabled"
                value="1"
                <?php checked($enabled); ?>
                <?php disabled(! rch_multisite_is_create_office_sites_enabled() && ! $blog_id); ?>
                style="margin:0;"
            >
            <strong><?php esc_html_e('Enable site for this office', 'rechat-plugin'); ?></strong>
        </label>
    </p>

    <p style="margin-top:12px;">
        <label for="rch_subsite_theme_stylesheet_office">
            <strong><?php esc_html_e('Theme for this sub-site', 'rechat-plugin'); ?></strong>
        </label>
    </p>
    <select name="rch_subsite_theme_stylesheet_office" id="rch_subsite_theme_stylesheet_office" class="widefat">
        <option value="" <?php selected($subsite_theme, ''); ?>>
            <?php esc_html_e('Use network default', 'rechat-plugin'); ?>
        </option>
        <?php foreach ($theme_choices as $slug_opt => $label) : ?>
            <option value="<?php echo esc_attr($slug_opt); ?>" <?php selected($subsite_theme, $slug_opt); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">
        <?php esc_html_e('Optional. Overrides the global default in Rechat → Multisite for this office only.', 'rechat-plugin'); ?>
    </p>

    <?php if ($blog_id) : ?>
        <p style="margin-top:8px;">
            <span style="color:<?php echo $enabled ? '#46b450' : '#999'; ?>;">
                <?php echo $enabled
                    ? '&#10003; ' . esc_html__('Site is active', 'rechat-plugin')
                    : '&#9679; ' . esc_html__('Site is disabled (archived)', 'rechat-plugin');
                ?>
            </span>
        </p>
        <p>
            <a href="<?php echo esc_url(get_site_url($blog_id)); ?>" target="_blank" rel="noopener" class="button button-small">
                <?php esc_html_e('View Site', 'rechat-plugin'); ?>
            </a>
            <a href="<?php echo esc_url(get_admin_url($blog_id)); ?>" target="_blank" rel="noopener" class="button button-small">
                <?php esc_html_e('Site Admin', 'rechat-plugin'); ?>
            </a>
        </p>
        <p class="description">
            <?php esc_html_e('Blog ID:', 'rechat-plugin'); ?> <code><?php echo esc_html((string) $blog_id); ?></code>
        </p>
    <?php elseif ($site_url) : ?>
        <p class="description" style="margin-top:8px;">
            <?php esc_html_e('Site URL will be:', 'rechat-plugin'); ?><br>
            <code><?php echo esc_html($site_url); ?></code>
        </p>
    <?php endif; ?>
    <?php
}

/**
 * Save office site metabox (enable + per-site theme).
 *
 * @param  int $post_id Post ID.
 * @return void
 */
function rch_multisite_save_office_metabox(int $post_id): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'offices') {
        return;
    }

    if (
        ! isset($_POST['rch_office_site_metabox_nonce']) ||
        ! wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['rch_office_site_metabox_nonce'])),
            'rch_office_site_metabox_' . $post_id
        )
    ) {
        return;
    }

    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    if (! rch_multisite_is_create_office_sites_enabled() && ! rch_multisite_get_office_blog_id($post_id)) {
        return;
    }

    $new_enabled = isset($_POST['rch_office_site_enabled']) && $_POST['rch_office_site_enabled'] === '1';

    rch_multisite_set_office_site_enabled($post_id, $new_enabled);

    if (isset($_POST['rch_subsite_theme_stylesheet_office'])) {
        $raw = sanitize_text_field(wp_unslash($_POST['rch_subsite_theme_stylesheet_office']));
        if ($raw === '' || wp_get_theme($raw)->exists()) {
            if ($raw === '') {
                delete_post_meta($post_id, '_rch_subsite_theme_stylesheet');
            } else {
                update_post_meta($post_id, '_rch_subsite_theme_stylesheet', $raw);
            }

            $bid = rch_multisite_get_office_blog_id($post_id);
            if ($bid) {
                $pair = rch_multisite_resolve_theme_for_post($post_id);
                if (! empty($pair['stylesheet'])) {
                    rch_multisite_activate_theme_on_blog($bid, $pair['stylesheet']);
                }
            }
        }
    }
}
add_action('save_post', 'rch_multisite_save_office_metabox', 5);

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 5 – AJAX HANDLERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * AJAX: Bulk-provision sites for every enabled agent post.
 * Network admin capability required.
 *
 * @return void
 */
function rch_multisite_ajax_provision_all(): void
{
    check_ajax_referer('rch_multisite_provision_all', '_nonce');

    if (! current_user_can('manage_network_options')) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    if (! rch_multisite_is_create_agent_sites_enabled() && ! rch_multisite_is_create_office_sites_enabled()) {
        wp_send_json_error(
            __('Sub-site creation is disabled. Enable agent and/or office sub-sites in the settings above.', 'rechat-plugin')
        );
        return;
    }

    $broadcast_err = function_exists('rch_multisite_broadcast_dependency_error')
        ? rch_multisite_broadcast_dependency_error()
        : null;

    if ($broadcast_err instanceof WP_Error) {
        wp_send_json_error($broadcast_err->get_error_message());
        return;
    }

    $created_a = 0;
    $updated_a = 0;
    $skipped_a = 0;
    $created_o = 0;
    $updated_o = 0;
    $skipped_o = 0;
    $errors    = [];

    if (rch_multisite_is_create_agent_sites_enabled()) {
        $agents = get_posts([
            'post_type'   => 'agents',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'all',
        ]);

        foreach ($agents as $agent) {
            $name = trim($agent->post_title);

            if (empty($name)) {
                continue;
            }

            if (! rch_multisite_is_agent_site_enabled($agent->ID)) {
                $skipped_a++;
                continue;
            }

            $blog_id = rch_multisite_get_agent_blog_id($agent->ID);

            if ($blog_id) {
                rch_multisite_update_site_for_agent($blog_id, $name);
                $updated_a++;
            } else {
                $result = rch_multisite_create_site_for_agent($agent->ID, $name);

                if (is_wp_error($result)) {
                    $errors[] = esc_html($name) . ': ' . esc_html($result->get_error_message());
                } else {
                    $created_a++;
                }
            }
        }
    }

    if (rch_multisite_is_create_office_sites_enabled()) {
        $offices = get_posts([
            'post_type'   => 'offices',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'all',
        ]);

        foreach ($offices as $office) {
            $name = trim($office->post_title);

            if (empty($name)) {
                continue;
            }

            if (! rch_multisite_is_office_site_enabled($office->ID)) {
                $skipped_o++;
                continue;
            }

            $blog_id = rch_multisite_get_office_blog_id($office->ID);

            if ($blog_id) {
                rch_multisite_update_site_for_office($blog_id, $name);
                $updated_o++;
            } else {
                $result = rch_multisite_create_site_for_office($office->ID, $name);

                if (is_wp_error($result)) {
                    $errors[] = esc_html($name) . ': ' . esc_html($result->get_error_message());
                } else {
                    $created_o++;
                }
            }
        }
    }

    wp_send_json_success([
        'created' => $created_a + $created_o,
        'updated' => $updated_a + $updated_o,
        'skipped' => $skipped_a + $skipped_o,
        'errors'  => $errors,
        'message' => sprintf(
            /* translators: 1–6: counts for agents and offices */
            __('Done. Agents — created: %1$d, updated: %2$d, skipped: %3$d. Offices — created: %4$d, updated: %5$d, skipped: %6$d.', 'rechat-plugin'),
            $created_a,
            $updated_a,
            $skipped_a,
            $created_o,
            $updated_o,
            $skipped_o
        ),
    ]);
}
add_action('wp_ajax_rch_multisite_provision_all', 'rch_multisite_ajax_provision_all');

/**
 * Rename (migrate) existing agent sub-sites to the deterministic slug format.
 *
 * Updates wp_blogs domain/path via wp_update_site (or update_blog_details fallback) and
 * then updates `home` and `siteurl` options on the destination blog.
 *
 * @param  int    $blog_id    Target blog ID.
 * @param  string $domain    New domain.
 * @param  string $path      New path.
 * @return true|\WP_Error
 */
function rch_multisite_rename_blog_location(int $blog_id, string $domain, string $path)
{
    if (! is_multisite()) {
        return new WP_Error('rch_not_multisite', __('Multisite is not enabled.', 'rechat-plugin'));
    }

    $site = get_site($blog_id);
    if (! $site) {
        return new WP_Error('rch_site_missing', __('Sub-site does not exist.', 'rechat-plugin'));
    }

    $domain = strtolower(trim($domain));
    $path   = trailingslashit('/' . ltrim($path, '/'));

    if ($domain === '' || $path === '') {
        return new WP_Error('rch_invalid_location', __('Invalid target domain/path.', 'rechat-plugin'));
    }

    // Avoid collisions (should be prevented by slug uniqueness resolver).
    $existing = get_sites([
        'domain' => $domain,
        'path'   => $path,
        'number' => 1,
        'fields' => 'ids',
    ]);
    if (! empty($existing) && (int) $existing[0] !== $blog_id) {
        return new WP_Error('rch_location_taken', __('Target domain/path already exists on the network.', 'rechat-plugin'));
    }

    if (function_exists('wp_update_site')) {
        $res = wp_update_site($blog_id, [
            'domain' => $domain,
            'path'   => $path,
        ]);
        if (is_wp_error($res)) {
            return $res;
        }
    } else {
        // Legacy fallback.
        update_blog_details($blog_id, [
            'domain' => $domain,
            'path'   => $path,
        ]);
    }

    // Update home/siteurl to match new location.
    $scheme = parse_url(get_site_url($blog_id), PHP_URL_SCHEME);
    $scheme = $scheme ?: 'https';
    $base   = set_url_scheme('http://' . $domain . $path, $scheme);
    $base   = rtrim($base, '/');

    switch_to_blog($blog_id);
    update_option('home', $base, true);
    update_option('siteurl', $base, true);
    flush_rewrite_rules(false);
    restore_current_blog();

    clean_blog_cache($blog_id);

    return true;
}

/**
 * Migrate all existing agent subsites to the new slug format: [first initial][lastname].
 *
 * @return array{renamed:int,unchanged:int,skipped:int,errors:string[]}
 */
function rch_multisite_migrate_agent_subsite_urls(): array
{
    $renamed   = 0;
    $unchanged = 0;
    $skipped   = 0;
    $errors    = [];

    if (! is_multisite()) {
        return compact('renamed', 'unchanged', 'skipped', 'errors');
    }

    // Subdomain install required for this migration tool.
    if (rch_multisite_get_url_type() !== 'subdomain') {
        $errors[] = __('This migration is only available for subdomain installs.', 'rechat-plugin');
        return compact('renamed', 'unchanged', 'skipped', 'errors');
    }

    $agents = get_posts([
        'post_type'   => 'agents',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

    foreach ($agents as $agent) {
        $agent_id = (int) $agent->ID;
        $blog_id  = rch_multisite_get_agent_blog_id($agent_id);
        if (! $blog_id) {
            $skipped++;
            continue;
        }

        $name = trim((string) $agent->post_title);
        $slug = rch_multisite_resolve_agent_site_slug($agent_id, $name, $blog_id);
        $loc  = rch_multisite_build_site_location($slug);

        $site = get_site($blog_id);
        if (! $site) {
            $skipped++;
            continue;
        }

        $current_domain = strtolower((string) $site->domain);
        $current_path   = (string) $site->path;

        if ($current_domain === strtolower($loc['domain']) && $current_path === $loc['path']) {
            // Keep meta in sync anyway.
            update_post_meta($agent_id, '_rch_agent_slug', $slug);
            $unchanged++;
            continue;
        }

        $res = rch_multisite_rename_blog_location($blog_id, $loc['domain'], $loc['path']);
        if (is_wp_error($res)) {
            $errors[] = esc_html($name ?: ('Agent #' . $agent_id)) . ': ' . esc_html($res->get_error_message());
            continue;
        }

        update_post_meta($agent_id, '_rch_agent_slug', $slug);
        $renamed++;
    }

    return compact('renamed', 'unchanged', 'skipped', 'errors');
}

/**
 * AJAX: migrate existing agent sub-site URLs to firstInitial+lastName format.
 *
 * @return void
 */
function rch_multisite_ajax_migrate_agent_subsite_urls(): void
{
    check_ajax_referer('rch_multisite_migrate_agent_urls', '_nonce');

    if (! current_user_can('manage_network_options')) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    if (! is_multisite()) {
        wp_send_json_error(__('Multisite is not enabled.', 'rechat-plugin'));
        return;
    }

    $result = rch_multisite_migrate_agent_subsite_urls();

    wp_send_json_success([
        'message' => sprintf(
            /* translators: 1: renamed, 2: unchanged, 3: skipped */
            __('Done. Renamed %1$d agent sub-site(s). %2$d already matched. %3$d skipped (no linked site).', 'rechat-plugin'),
            (int) $result['renamed'],
            (int) $result['unchanged'],
            (int) $result['skipped']
        ),
        'errors'  => $result['errors'],
    ]);
}
add_action('wp_ajax_rch_multisite_migrate_agent_subsite_urls', 'rch_multisite_ajax_migrate_agent_subsite_urls');

/**
 * AJAX: Toggle the enabled/disabled state for a single agent site.
 * Called from the status table's inline toggle buttons.
 *
 * @return void
 */
function rch_multisite_ajax_toggle_agent_site(): void
{
    check_ajax_referer('rch_multisite_toggle_agent', '_nonce');

    if (! current_user_can('manage_options')) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $pt      = $post_id ? get_post_type($post_id) : '';

    if (! $post_id || ! in_array($pt, ['agents', 'offices'], true)) {
        wp_send_json_error(__('Invalid post.', 'rechat-plugin'));
        return;
    }

    $enable = isset($_POST['enable']) && (bool) $_POST['enable'];

    if ($pt === 'agents') {
        if ($enable && ! rch_multisite_is_create_agent_sites_enabled()) {
            wp_send_json_error(
                __('Creating agent sub-sites is turned off in Multisite settings.', 'rechat-plugin')
            );
            return;
        }

        rch_multisite_set_agent_site_enabled($post_id, $enable);

        if ($enable && ! rch_multisite_get_agent_blog_id($post_id)) {
            $name   = get_the_title($post_id);
            $result = rch_multisite_create_site_for_agent($post_id, $name);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }
        }

        $blog_id = rch_multisite_get_agent_blog_id($post_id);
    } else {
        if ($enable && ! rch_multisite_is_create_office_sites_enabled()) {
            wp_send_json_error(
                __('Creating office sub-sites is turned off in Multisite settings.', 'rechat-plugin')
            );
            return;
        }

        rch_multisite_set_office_site_enabled($post_id, $enable);

        if ($enable && ! rch_multisite_get_office_blog_id($post_id)) {
            $name   = get_the_title($post_id);
            $result = rch_multisite_create_site_for_office($post_id, $name);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }
        }

        $blog_id = rch_multisite_get_office_blog_id($post_id);
    }

    wp_send_json_success([
        'enabled'  => $enable,
        'blog_id'  => $blog_id,
        'site_url' => $blog_id ? get_site_url($blog_id) : '',
    ]);
}
add_action('wp_ajax_rch_multisite_toggle_agent_site', 'rch_multisite_ajax_toggle_agent_site');

/**
 * AJAX: Re-run editor provisioning for an agent sub-site (create/add user, ensure Editor role, email).
 *
 * @return void
 */
function rch_multisite_ajax_reprovision_agent_editor(): void
{
    check_ajax_referer('rch_multisite_reprovision_editor', '_nonce');

    if (! current_user_can('manage_options')) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    if (! is_multisite()) {
        wp_send_json_error(__('Multisite is not enabled.', 'rechat-plugin'));
        return;
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

    if (! $post_id || get_post_type($post_id) !== 'agents') {
        wp_send_json_error(__('Invalid agent.', 'rechat-plugin'));
        return;
    }

    $blog_id = rch_multisite_get_agent_blog_id($post_id);

    if (! $blog_id) {
        wp_send_json_error(__('This agent does not have a sub-site yet.', 'rechat-plugin'));
        return;
    }

    $result = rch_multisite_sync_agent_site_editor($post_id, $blog_id, true);

    if (empty($result['ok'])) {
        wp_send_json_error($result['message'] ?? __('Could not sync the editor account.', 'rechat-plugin'));
        return;
    }

    wp_send_json_success([
        'message' => $result['message'] ?? __('Done.', 'rechat-plugin'),
    ]);
}
add_action('wp_ajax_rch_multisite_reprovision_agent_editor', 'rch_multisite_ajax_reprovision_agent_editor');

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 6 – SETTINGS SAVE (handles POST from the Multisite tab form)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Persist settings submitted from the Multisite admin tab.
 *
 * @return void
 */
function rch_multisite_save_settings(): void
{
    if (
        ! isset($_POST['rch_multisite_save_nonce']) ||
        ! wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['rch_multisite_save_nonce'])),
            'rch_multisite_save_settings'
        )
    ) {
        return;
    }

    if (! current_user_can('manage_network_options')) {
        return;
    }

    // Create one sub-site per agent (master switch).
    $create_sites = isset($_POST['rch_multisite_create_agent_sites']) && '1' === $_POST['rch_multisite_create_agent_sites'];
    update_site_option('rch_multisite_create_agent_sites', $create_sites ? '1' : '0');

    $create_office_sites = isset($_POST['rch_multisite_create_office_sites']) && '1' === $_POST['rch_multisite_create_office_sites'];
    update_site_option('rch_multisite_create_office_sites', $create_office_sites ? '1' : '0');

    // Send WordPress login/credential emails to agents (off by default).
    $send_agent_credentials = isset($_POST['rch_multisite_send_agent_credentials_email']) && '1' === $_POST['rch_multisite_send_agent_credentials_email'];
    update_site_option('rch_multisite_send_agent_credentials_email', $send_agent_credentials ? '1' : '0');

    // Owner user ID.
    if (isset($_POST['rch_multisite_admin_user_id'])) {
        update_site_option('rch_multisite_admin_user_id', absint($_POST['rch_multisite_admin_user_id']));
    }

    // URL type: subdomain or subdirectory.
    $url_type = isset($_POST['rch_multisite_url_type'])
        ? sanitize_key($_POST['rch_multisite_url_type'])
        : 'subdomain';
    if (! in_array($url_type, ['subdomain', 'subdirectory'], true)) {
        $url_type = 'subdomain';
    }
    update_site_option('rch_multisite_url_type', $url_type);

    $slug_format = isset($_POST['rch_multisite_agent_slug_format'])
        ? sanitize_key(wp_unslash($_POST['rch_multisite_agent_slug_format']))
        : 'initial_lastname';
    if (! in_array($slug_format, ['initial_lastname', 'firstname_lastname'], true)) {
        $slug_format = 'initial_lastname';
    }
    update_site_option('rch_multisite_agent_slug_format', $slug_format);

    // Delete on agent delete flag.
    $delete_flag = isset($_POST['rch_multisite_delete_on_delete']) ? 1 : 0;
    update_site_option('rch_multisite_delete_site_on_agent_delete', $delete_flag);

    // Default theme for new agent sub-sites + bulk apply target (empty = mirror main site).
    $theme_choice = isset($_POST['rch_multisite_agent_theme_stylesheet'])
        ? sanitize_text_field(wp_unslash($_POST['rch_multisite_agent_theme_stylesheet']))
        : '';
    if ($theme_choice !== '' && ! wp_get_theme($theme_choice)->exists()) {
        $theme_choice = '';
    }
    update_site_option('rch_multisite_agent_theme_stylesheet', $theme_choice);

    $office_theme = isset($_POST['rch_multisite_office_theme_stylesheet'])
        ? sanitize_text_field(wp_unslash($_POST['rch_multisite_office_theme_stylesheet']))
        : '';
    if ($office_theme !== '' && ! wp_get_theme($office_theme)->exists()) {
        $office_theme = '';
    }
    update_site_option('rch_multisite_office_theme_stylesheet', $office_theme);

    add_settings_error(
        'rch_multisite',
        'rch_multisite_saved',
        __('Multisite settings saved.', 'rechat-plugin'),
        'updated'
    );
}
add_action('admin_init', 'rch_multisite_save_settings');

require_once __DIR__ . '/agent-listing-scope.php';
require_once __DIR__ . '/subsite-dedupe-cleanup.php';
require_once __DIR__ . '/views/admin-tab.php';
