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
 * Whether the network creates one sub-site per office post.
 *
 * @return bool
 */
function rch_multisite_is_create_office_sites_enabled(): bool
{
    return (bool) get_site_option('rch_multisite_create_office_sites', '1');
}

/**
 * Network default theme for agent/office sub-sites (no per-post override).
 *
 * Uses `rch_multisite_agent_theme_stylesheet` when set; otherwise main site theme.
 *
 * @return array{template:string,stylesheet:string}
 */
function rch_multisite_resolve_theme_network_default(): array
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
 * Resolve template + stylesheet for agent/office sub-sites (bulk apply, legacy name).
 *
 * @return array{template:string,stylesheet:string}
 */
function rch_multisite_resolve_theme_for_agent_sites(): array
{
    return rch_multisite_resolve_theme_network_default();
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

    return rch_multisite_resolve_theme_network_default();
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
    $slug = rch_multisite_sanitize_slug($agent_name);

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
        update_post_meta($post_id, '_rch_agent_site_id', $existing_blog_id);
        update_post_meta($post_id, '_rch_agent_slug', $slug);
        error_log(
            'Rechat Plugin Multisite: Adopted existing site ' . $domain . $path .
            ' (blog_id=' . $existing_blog_id . ') for agent post ' . $post_id
        );
        return $existing_blog_id;
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

    error_log(
        'Rechat Plugin Multisite: Created site ' . $domain . $path .
        ' (blog_id=' . $blog_id . ') for agent post ' . $post_id
    );

    return $blog_id;
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
        : rch_multisite_resolve_theme_network_default();

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

    restore_current_blog();

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
 * Apply a theme to every agent sub-site that has a linked blog_id.
 *
 * @param  string|null $stylesheet  Theme stylesheet slug, or null to use
 *                                   rch_multisite_resolve_theme_for_agent_sites().
 * @return array{updated:int,errors:string[]}
 */
function rch_multisite_bulk_apply_theme_to_agent_sites(?string $stylesheet = null): array
{
    if ($stylesheet !== null && $stylesheet !== '') {
        $theme = wp_get_theme($stylesheet);
        if (! $theme->exists()) {
            return [
                'updated' => 0,
                'errors'  => [
                    sprintf(
                        /* translators: %s: theme slug */
                        __('Theme "%s" is not installed.', 'rechat-plugin'),
                        $stylesheet
                    ),
                ],
            ];
        }
        $stylesheet = $theme->get_stylesheet();
    } else {
        $pair       = rch_multisite_resolve_theme_for_agent_sites();
        $stylesheet = $pair['stylesheet'];
    }

    if ($stylesheet === '') {
        return [
            'updated' => 0,
            'errors'  => [__('Could not resolve a theme to apply.', 'rechat-plugin')],
        ];
    }

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

    $offices = get_posts([
        'post_type'   => 'offices',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

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

    // Empty string = use saved network default / main site (see resolve).
    $result = rch_multisite_bulk_apply_theme_to_agent_sites($raw === '' ? null : $raw);

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

    wp_send_json_success([
        'message' => sprintf(
            /* translators: %d: number of sites */
            __('Theme applied successfully to %d agent/office sub-site(s).', 'rechat-plugin'),
            $result['updated']
        ),
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
        $slug_preview = rch_multisite_sanitize_slug($post->post_title);
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

    add_settings_error(
        'rch_multisite',
        'rch_multisite_saved',
        __('Multisite settings saved.', 'rechat-plugin'),
        'updated'
    );
}
add_action('admin_init', 'rch_multisite_save_settings');

require_once __DIR__ . '/views/admin-tab.php';
