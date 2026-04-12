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
 *  - Per-agent site enable / disable (archived, not deleted)
 *  - Bulk provision from settings page
 *  - Status table with inline enable/disable toggles
 *
 * Only active on WordPress Multisite installations.
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
 * Resolve template + stylesheet for agent sub-sites (new + bulk apply).
 *
 * Uses the network option `rch_multisite_agent_theme_stylesheet` when set to a
 * valid installed theme; otherwise mirrors the main site active theme.
 *
 * @return array{template:string,stylesheet:string}
 */
function rch_multisite_resolve_theme_for_agent_sites(): array
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

    // Apply the main site's theme and essential settings to the new site.
    rch_multisite_configure_new_site($blog_id, $agent_name);

    error_log(
        'Rechat Plugin Multisite: Created site ' . $domain . $path .
        ' (blog_id=' . $blog_id . ') for agent post ' . $post_id
    );

    return $blog_id;
}

/**
 * Configure a freshly-created agent sub-site so it looks correct immediately.
 *
 * Copies the main site's active theme, permalink structure, timezone, and
 * date format so the sub-site is fully styled from the first visit.
 *
 * @param  int    $blog_id     The newly created blog ID.
 * @param  string $agent_name  Agent display name (used for the site title).
 * @return void
 */
function rch_multisite_configure_new_site(int $blog_id, string $agent_name): void
{
    $main_id = get_main_site_id();

    $resolved   = rch_multisite_resolve_theme_for_agent_sites();
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
        ', permalink: ' . ($permalink ?: '(default)')
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
            rch_multisite_configure_new_site($blog_id, $agent->post_title);
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
            __('Theme applied successfully to %d agent sub-site(s).', 'rechat-plugin'),
            $result['updated']
        ),
        'updated' => $result['updated'],
        'errors'  => [],
    ]);
}
add_action('wp_ajax_rch_multisite_bulk_apply_theme', 'rch_multisite_ajax_bulk_apply_theme');

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
    if (get_post_type($post_id) !== 'agents') {
        return;
    }

    $blog_id = rch_multisite_get_agent_blog_id($post_id);

    if (! $blog_id) {
        return;
    }

    if (get_site_option('rch_multisite_delete_site_on_agent_delete', 0)) {
        wpmu_delete_blog($blog_id, true);
        error_log(
            'Rechat Plugin Multisite: Deleted site blog_id=' . $blog_id .
            ' because agent post ' . $post_id . ' was deleted.'
        );
    } else {
        update_blog_status($blog_id, 'archived', 1);
        error_log(
            'Rechat Plugin Multisite: Archived site blog_id=' . $blog_id .
            ' because agent post ' . $post_id . ' was deleted.'
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
    if (! rch_multisite_is_create_agent_sites_enabled()) {
        $data['multisite'] = '<b>' . esc_html__('Agent Sites (Multisite)', 'rechat-plugin') . '</b><br> '
            . esc_html__('Off — enable “Create a sub-site for each agent” in Rechat → Multisite.', 'rechat-plugin');

        return $data;
    }

    $counts = rch_multisite_sync_counter();

    $data['multisite'] = sprintf(
        '<b>%s</b><br> created: %d, updated: %d, skipped (disabled): %d, errors: %d',
        esc_html__('Agent Sites (Multisite)', 'rechat-plugin'),
        $counts['created'],
        $counts['updated'],
        $counts['skipped'],
        $counts['errors']
    );

    return $data;
}
add_filter('rch_sync_response_data', 'rch_multisite_append_sync_result');

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

    $enabled = rch_multisite_is_agent_site_enabled($post->ID);
    $blog_id = rch_multisite_get_agent_blog_id($post->ID);
    $slug    = (string) get_post_meta($post->ID, '_rch_agent_slug', true);

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
}
// Priority 5 → runs before save_post_agents (priority 10).
add_action('save_post', 'rch_multisite_save_agent_metabox', 5);

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

    if (! rch_multisite_is_create_agent_sites_enabled()) {
        wp_send_json_error(
            __('Agent sub-sites are disabled. Turn on “Create a sub-site for each agent” in the settings above.', 'rechat-plugin')
        );
        return;
    }

    $agents = get_posts([
        'post_type'   => 'agents',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

    $created  = 0;
    $updated  = 0;
    $skipped  = 0;
    $errors   = [];

    foreach ($agents as $agent) {
        $name = trim($agent->post_title);

        if (empty($name)) {
            continue;
        }

        // Honour per-agent disabled flag.
        if (! rch_multisite_is_agent_site_enabled($agent->ID)) {
            $skipped++;
            continue;
        }

        $blog_id = rch_multisite_get_agent_blog_id($agent->ID);

        if ($blog_id) {
            rch_multisite_update_site_for_agent($blog_id, $name);
            $updated++;
        } else {
            $result = rch_multisite_create_site_for_agent($agent->ID, $name);

            if (is_wp_error($result)) {
                $errors[] = esc_html($name) . ': ' . esc_html($result->get_error_message());
            } else {
                $created++;
            }
        }
    }

    wp_send_json_success([
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors'  => $errors,
        'message' => sprintf(
            /* translators: 1: created, 2: updated, 3: skipped */
            __('Done. Created: %1$d, updated: %2$d, skipped (disabled): %3$d.', 'rechat-plugin'),
            $created,
            $updated,
            $skipped
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

    if (! $post_id || get_post_type($post_id) !== 'agents') {
        wp_send_json_error(__('Invalid agent.', 'rechat-plugin'));
        return;
    }

    $enable  = isset($_POST['enable']) && (bool) $_POST['enable'];

    if ($enable && ! rch_multisite_is_create_agent_sites_enabled()) {
        wp_send_json_error(
            __('Creating agent sub-sites is turned off in Multisite settings.', 'rechat-plugin')
        );
        return;
    }

    rch_multisite_set_agent_site_enabled($post_id, $enable);

    // If enabling and no site exists yet, create it now.
    if ($enable && ! rch_multisite_get_agent_blog_id($post_id)) {
        $name   = get_the_title($post_id);
        $result = rch_multisite_create_site_for_agent($post_id, $name);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
    }

    $blog_id = rch_multisite_get_agent_blog_id($post_id);

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

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 7 – ADMIN TAB RENDERER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Render the Multisite tab in the Rechat Settings page.
 *
 * @return void
 */
function rch_multisite_render_admin_tab(): void
{
    $create_sites    = rch_multisite_is_create_agent_sites_enabled();
    $admin_user_id   = absint(get_site_option('rch_multisite_admin_user_id', 0));
    $delete_on_del   = (bool) get_site_option('rch_multisite_delete_site_on_agent_delete', 0);
    $url_type        = rch_multisite_get_url_type();
    $network         = get_network();
    $base_domain     = preg_replace('/^www\./i', '', $network->domain);
    $network_path    = trailingslashit($network->path); // e.g. '/rechat-plugin/'
    $provision_nonce   = wp_create_nonce('rch_multisite_provision_all');
    $toggle_nonce      = wp_create_nonce('rch_multisite_toggle_agent');
    $bulk_theme_nonce  = wp_create_nonce('rch_multisite_bulk_theme');
    $saved_agent_theme = (string) get_site_option('rch_multisite_agent_theme_stylesheet', '');
    $theme_choices     = rch_multisite_get_theme_choices();

    // Detect the WordPress network install type.
    $wp_subdomain_install = defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL;

    // URL pattern examples for the helper text.
    $example_subdomain    = 'john.' . $base_domain;
    $example_subdirectory = $base_domain . rtrim($network_path, '/') . '/john';

    // All published agent posts for the status table.
    $agents = get_posts([
        'post_type'   => 'agents',
        'numberposts' => -1,
        'post_status' => 'publish',
        'orderby'     => 'title',
        'order'       => 'ASC',
        'fields'      => 'all',
    ]);

    ?>
    <div class="tab-content">

        <h2><?php esc_html_e('Multisite – Agent Sites', 'rechat-plugin'); ?></h2>

        <?php settings_errors('rch_multisite'); ?>

        <?php /* ── Settings form ──────────────────────────────────────────── */ ?>
        <form method="POST" action="">
            <?php wp_nonce_field('rch_multisite_save_settings', 'rch_multisite_save_nonce'); ?>

            <table class="form-table">

                <?php /* Master: create sub-sites for agents */ ?>
                <tr valign="top">
                    <th scope="row">
                        <?php esc_html_e('Agent sub-sites', 'rechat-plugin'); ?>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="rch_multisite_create_agent_sites"
                                value="1"
                                <?php checked($create_sites); ?>
                            >
                            <?php esc_html_e('Create a WordPress sub-site for each agent', 'rechat-plugin'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When checked, syncing agents from the API, manual saves, and “Provision” can create and update individual network sites. Turn this off if you only want agent profiles (the Agents post type) without separate websites.', 'rechat-plugin'); ?>
                        </p>
                    </td>
                </tr>

                <?php /* Theme for agent sub-sites */ ?>
                <tr valign="top">
                    <th scope="row">
                        <label for="rch-multisite-agent-theme">
                            <?php esc_html_e('Theme for agent sub-sites', 'rechat-plugin'); ?>
                        </label>
                    </th>
                    <td>
                        <select
                            name="rch_multisite_agent_theme_stylesheet"
                            id="rch-multisite-agent-theme"
                            class="regular-text"
                        >
                            <option value="" <?php selected($saved_agent_theme, ''); ?>>
                                <?php esc_html_e('Same as main site (default)', 'rechat-plugin'); ?>
                            </option>
                            <?php foreach ($theme_choices as $slug => $label) : ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($saved_agent_theme, $slug); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Used when new agent sites are created and as the target for “Apply theme to all agent sub-sites” below. Enable themes in Network Admin → Themes if a theme does not activate.', 'rechat-plugin'); ?>
                        </p>
                    </td>
                </tr>

                <?php /* URL type */ ?>
                <tr valign="top">
                    <th scope="row">
                        <?php esc_html_e('Site URL format', 'rechat-plugin'); ?>
                    </th>
                    <td>
                        <?php
                        // Show a notice if the selected type mismatches SUBDOMAIN_INSTALL.
                        $saved_type   = (string) get_site_option('rch_multisite_url_type', '');
                        $type_is_auto = ! in_array($saved_type, ['subdomain', 'subdirectory'], true);
                        ?>
                        <?php if ($wp_subdomain_install) : ?>
                            <div class="notice notice-info inline" style="margin:0 0 8px;padding:6px 12px;">
                                <p>
                                    <strong><?php esc_html_e('Detected: Subdomain install', 'rechat-plugin'); ?></strong>
                                    — <code>SUBDOMAIN_INSTALL = true</code> in wp-config.php.
                                    <?php if ($type_is_auto) esc_html_e('Defaulting to Subdomain mode.', 'rechat-plugin'); ?>
                                </p>
                            </div>
                        <?php else : ?>
                            <div class="notice notice-info inline" style="margin:0 0 8px;padding:6px 12px;">
                                <p>
                                    <strong><?php esc_html_e('Detected: Subdirectory install', 'rechat-plugin'); ?></strong>
                                    — <code>SUBDOMAIN_INSTALL = false</code> in wp-config.php.
                                    Network base path: <code><?php echo esc_html($network_path); ?></code>.
                                    <?php if ($type_is_auto) esc_html_e('Defaulting to Subdirectory mode.', 'rechat-plugin'); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <fieldset>
                            <label style="display:block;margin-bottom:6px;">
                                <input
                                    type="radio"
                                    name="rch_multisite_url_type"
                                    value="subdomain"
                                    <?php checked($url_type, 'subdomain'); ?>
                                    <?php if (! $wp_subdomain_install) echo 'style="opacity:.6"'; ?>
                                >
                                <?php esc_html_e('Subdomain', 'rechat-plugin'); ?>
                                &nbsp;<code><?php echo esc_html($example_subdomain); ?></code>
                                <?php if (! $wp_subdomain_install) : ?>
                                    &nbsp;<em style="color:#d63638;"><?php esc_html_e('(requires SUBDOMAIN_INSTALL = true)', 'rechat-plugin'); ?></em>
                                <?php endif; ?>
                            </label>
                            <label style="display:block;">
                                <input
                                    type="radio"
                                    name="rch_multisite_url_type"
                                    value="subdirectory"
                                    <?php checked($url_type, 'subdirectory'); ?>
                                    <?php if ($wp_subdomain_install) echo 'style="opacity:.6"'; ?>
                                >
                                <?php esc_html_e('Subdirectory', 'rechat-plugin'); ?>
                                &nbsp;<code><?php echo esc_html($example_subdirectory); ?></code>
                                <?php if ($wp_subdomain_install) : ?>
                                    &nbsp;<em style="color:#d63638;"><?php esc_html_e('(requires SUBDOMAIN_INSTALL = false)', 'rechat-plugin'); ?></em>
                                <?php endif; ?>
                            </label>
                        </fieldset>
                        <p class="description" style="margin-top:6px;">
                            <?php esc_html_e('Must match the network type set in wp-config.php. Changing this after sites have already been created will not rename existing sites.', 'rechat-plugin'); ?>
                        </p>
                    </td>
                </tr>

                <?php /* Owner user ID */ ?>
                <tr valign="top">
                    <th scope="row">
                        <label for="rch_multisite_admin_user_id">
                            <?php esc_html_e('Owner User ID for new sites', 'rechat-plugin'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="rch_multisite_admin_user_id"
                            name="rch_multisite_admin_user_id"
                            value="<?php echo esc_attr($admin_user_id ?: ''); ?>"
                            placeholder="<?php esc_attr_e('Leave blank to use network admin', 'rechat-plugin'); ?>"
                            class="small-text"
                            min="1"
                        >
                        <p class="description">
                            <?php esc_html_e('WordPress user ID set as admin of each new agent site. Leave blank to auto-use the network admin.', 'rechat-plugin'); ?>
                        </p>
                    </td>
                </tr>

                <?php /* Delete on agent delete */ ?>
                <tr valign="top">
                    <th scope="row">
                        <?php esc_html_e('When agent post is deleted', 'rechat-plugin'); ?>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="rch_multisite_delete_on_delete"
                                value="1"
                                <?php checked($delete_on_del); ?>
                            >
                            <?php esc_html_e('Permanently delete the sub-site (and all its content). When unchecked the site is archived instead.', 'rechat-plugin'); ?>
                        </label>
                    </td>
                </tr>

            </table>

            <?php submit_button(__('Save Multisite Settings', 'rechat-plugin')); ?>
        </form>

        <hr>

        <?php /* ── Bulk provision ────────────────────────────────────────── */ ?>
        <h3><?php esc_html_e('Provision / Reconcile All Agent Sites', 'rechat-plugin'); ?></h3>

        <p>
            <?php esc_html_e('Creates missing sub-sites for all enabled agents and updates titles. Disabled agents are skipped. Safe to run multiple times.', 'rechat-plugin'); ?>
        </p>

        <?php if (! $create_sites) : ?>
            <p class="notice notice-warning inline" style="margin:0 0 12px;padding:8px 12px;">
                <?php esc_html_e('Agent sub-site creation is turned off above. Enable “Create a WordPress sub-site for each agent” to use these tools.', 'rechat-plugin'); ?>
            </p>
        <?php endif; ?>

        <button
            id="rch-multisite-provision-btn"
            type="button"
            class="button button-primary"
            data-nonce="<?php echo esc_attr($provision_nonce); ?>"
            <?php disabled(! $create_sites); ?>
        >
            <?php esc_html_e('Provision All Agent Sites', 'rechat-plugin'); ?>
        </button>

        <button
            id="rch-multisite-fix-themes-btn"
            type="button"
            class="button"
            style="margin-left:8px;"
            data-nonce="<?php echo esc_attr(wp_create_nonce('rch_multisite_fix_themes')); ?>"
        >
            <?php esc_html_e('Fix Theme on Existing Sites', 'rechat-plugin'); ?>
        </button>

        <span id="rch-multisite-provision-spinner" class="spinner" style="float:none;margin-top:4px;"></span>

        <div id="rch-multisite-provision-result" style="margin-top:12px;"></div>

        <p style="margin-top:16px;margin-bottom:6px;">
            <strong><?php esc_html_e('Bulk apply theme to all agent sub-sites', 'rechat-plugin'); ?></strong>
        </p>
        <p class="description" style="max-width:720px;">
            <?php esc_html_e('Uses the theme selected in “Theme for agent sub-sites” above (change the dropdown and click here — you do not need to save the form first). Applies to every agent that already has a sub-site. Useful for first-time setup or switching designs network-wide.', 'rechat-plugin'); ?>
        </p>
        <p>
            <button
                type="button"
                id="rch-multisite-bulk-theme-btn"
                class="button button-secondary"
                data-nonce="<?php echo esc_attr($bulk_theme_nonce); ?>"
            >
                <?php esc_html_e('Apply theme to all agent sub-sites', 'rechat-plugin'); ?>
            </button>
            <span id="rch-multisite-bulk-theme-spinner" class="spinner" style="float:none;margin-top:4px;"></span>
        </p>
        <div id="rch-multisite-bulk-theme-result" style="margin-top:8px;"></div>

        <hr>

        <?php /* ── Status table ─────────────────────────────────────────── */ ?>
        <h3><?php esc_html_e('Agent Site Status', 'rechat-plugin'); ?></h3>

        <?php if (empty($agents)) : ?>
            <p><?php esc_html_e('No agent posts found.', 'rechat-plugin'); ?></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Agent', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Site URL', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Blog ID', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Site Status', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Enable / Disable', 'rechat-plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agents as $agent) :
                        $blog_id  = rch_multisite_get_agent_blog_id($agent->ID);
                        $slug     = (string) get_post_meta($agent->ID, '_rch_agent_slug', true);
                        $enabled  = rch_multisite_is_agent_site_enabled($agent->ID);

                        if ($slug) {
                            $loc      = rch_multisite_build_site_location($slug);
                            $site_url = $blog_id
                                ? get_site_url($blog_id)
                                : 'https://' . $loc['domain'] . rtrim($loc['path'], '/');
                        } else {
                            $preview_slug = rch_multisite_sanitize_slug($agent->post_title);
                            if ($preview_slug) {
                                $loc      = rch_multisite_build_site_location($preview_slug);
                                $site_url = 'https://' . $loc['domain'] . rtrim($loc['path'], '/');
                            } else {
                                $site_url = '';
                            }
                        }

                        $has_site = (bool) $blog_id;
                    ?>
                        <tr id="rch-agent-row-<?php echo esc_attr((string) $agent->ID); ?>">
                            <td>
                                <a href="<?php echo esc_url((string) get_edit_post_link($agent->ID)); ?>">
                                    <?php echo esc_html($agent->post_title); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($has_site) : ?>
                                    <a href="<?php echo esc_url($site_url); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html($site_url); ?>
                                    </a>
                                <?php elseif ($site_url) : ?>
                                    <code><?php echo esc_html($site_url); ?></code>
                                    <em style="color:#888;"> (<?php esc_html_e('not yet created', 'rechat-plugin'); ?>)</em>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="rch-blog-id">
                                <?php echo $has_site ? esc_html((string) $blog_id) : '—'; ?>
                            </td>
                            <td class="rch-site-status">
                                <?php if (! $enabled) : ?>
                                    <span style="color:#888;">&#9632; <?php esc_html_e('Disabled', 'rechat-plugin'); ?></span>
                                <?php elseif ($has_site) : ?>
                                    <span style="color:#46b450;">&#10003; <?php esc_html_e('Active', 'rechat-plugin'); ?></span>
                                <?php else : ?>
                                    <span style="color:#f0a500;">&#9679; <?php esc_html_e('Pending', 'rechat-plugin'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($enabled) : ?>
                                    <button
                                        type="button"
                                        class="button rch-toggle-agent-site"
                                        data-post-id="<?php echo esc_attr((string) $agent->ID); ?>"
                                        data-enable="0"
                                        data-nonce="<?php echo esc_attr($toggle_nonce); ?>"
                                        style="color:#dc3232;border-color:#dc3232;"
                                    >
                                        <?php esc_html_e('Disable Site', 'rechat-plugin'); ?>
                                    </button>
                                <?php else : ?>
                                    <button
                                        type="button"
                                        class="button button-primary rch-toggle-agent-site"
                                        data-post-id="<?php echo esc_attr((string) $agent->ID); ?>"
                                        data-enable="1"
                                        data-nonce="<?php echo esc_attr($toggle_nonce); ?>"
                                        <?php disabled(! $create_sites); ?>
                                    >
                                        <?php esc_html_e('Enable Site', 'rechat-plugin'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div><!-- .tab-content -->

    <script>
    (function ($) {
        'use strict';

        // ── Bulk provision ─────────────────────────────────────────────────────
        $('#rch-multisite-provision-btn').on('click', function () {
            var $btn     = $(this);
            var $spinner = $('#rch-multisite-provision-spinner');
            var $result  = $('#rch-multisite-provision-result');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.html('');

            $.post(ajaxurl, {
                action: 'rch_multisite_provision_all',
                _nonce: $btn.data('nonce'),
            }, function (response) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (response.success) {
                    var d    = response.data;
                    var html = '<div class="notice notice-success inline"><p>' + d.message + '</p>';

                    if (d.errors && d.errors.length) {
                        html += '<p><strong><?php echo esc_js(__('Errors:', 'rechat-plugin')); ?></strong></p><ul>';
                        $.each(d.errors, function (i, err) { html += '<li>' + err + '</li>'; });
                        html += '</ul>';
                    }

                    html += '</div>';
                    $result.html(html);

                    // Reload so the status table reflects the new sites.
                    setTimeout(function () { window.location.reload(); }, 2500);
                } else {
                    $result.html(
                        '<div class="notice notice-error inline"><p>' +
                        (response.data || '<?php echo esc_js(__('An error occurred.', 'rechat-plugin')); ?>') +
                        '</p></div>'
                    );
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                $result.html(
                    '<div class="notice notice-error inline"><p><?php echo esc_js(__('Request failed. Please try again.', 'rechat-plugin')); ?></p></div>'
                );
            });
        });

        // ── Fix themes on existing sites ───────────────────────────────────────
        $('#rch-multisite-fix-themes-btn').on('click', function () {
            var $btn     = $(this);
            var $spinner = $('#rch-multisite-provision-spinner');
            var $result  = $('#rch-multisite-provision-result');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.html('');

            $.post(ajaxurl, {
                action: 'rch_multisite_fix_themes',
                _nonce: $btn.data('nonce'),
            }, function (response) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (response.success) {
                    $result.html(
                        '<div class="notice notice-success inline"><p>' +
                        response.data.message + '</p></div>'
                    );
                } else {
                    $result.html(
                        '<div class="notice notice-error inline"><p>' +
                        (response.data || '<?php echo esc_js(__('An error occurred.', 'rechat-plugin')); ?>') +
                        '</p></div>'
                    );
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                $result.html(
                    '<div class="notice notice-error inline"><p><?php echo esc_js(__('Request failed. Please try again.', 'rechat-plugin')); ?></p></div>'
                );
            });
        });

        // ── Bulk apply theme to all agent sub-sites ────────────────────────────
        $('#rch-multisite-bulk-theme-btn').on('click', function () {
            var $btn     = $(this);
            var $spin    = $('#rch-multisite-bulk-theme-spinner');
            var $out     = $('#rch-multisite-bulk-theme-result');
            var theme    = $('#rch-multisite-agent-theme').val();

            $btn.prop('disabled', true);
            $spin.addClass('is-active');
            $out.html('');

            $.post(ajaxurl, {
                action: 'rch_multisite_bulk_apply_theme',
                _nonce: $btn.data('nonce'),
                theme:  theme
            }, function (response) {
                $btn.prop('disabled', false);
                $spin.removeClass('is-active');

                if (response.success) {
                    var d    = response.data;
                    var html = '<div class="notice notice-success inline"><p>' + d.message + '</p>';
                    if (d.errors && d.errors.length) {
                        html += '<p><strong><?php echo esc_js(__('Warnings:', 'rechat-plugin')); ?></strong></p><ul>';
                        $.each(d.errors, function (i, err) { html += '<li>' + err + '</li>'; });
                        html += '</ul>';
                    }
                    html += '</div>';
                    $out.html(html);
                } else {
                    $out.html(
                        '<div class="notice notice-error inline"><p>' +
                        (response.data || '<?php echo esc_js(__('An error occurred.', 'rechat-plugin')); ?>') +
                        '</p></div>'
                    );
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $spin.removeClass('is-active');
                $out.html(
                    '<div class="notice notice-error inline"><p><?php echo esc_js(__('Request failed. Please try again.', 'rechat-plugin')); ?></p></div>'
                );
            });
        });

        // ── Per-agent enable / disable toggle ──────────────────────────────────
        $(document).on('click', '.rch-toggle-agent-site', function () {
            var $btn    = $(this);
            var postId  = $btn.data('post-id');
            var enable  = parseInt($btn.data('enable'), 10);
            var nonce   = $btn.data('nonce');

            $btn.prop('disabled', true).text('<?php echo esc_js(__('Updating…', 'rechat-plugin')); ?>');

            $.post(ajaxurl, {
                action:  'rch_multisite_toggle_agent_site',
                _nonce:  nonce,
                post_id: postId,
                enable:  enable,
            }, function (response) {
                if (response.success) {
                    var d       = response.data;
                    var $row    = $('#rch-agent-row-' + postId);
                    var $status = $row.find('.rch-site-status');
                    var $blogId = $row.find('.rch-blog-id');

                    if (d.enabled) {
                        $status.html('<span style="color:#46b450;">&#10003; <?php echo esc_js(__('Active', 'rechat-plugin')); ?></span>');
                        $btn
                            .removeClass('button-primary')
                            .css({ color: '#dc3232', 'border-color': '#dc3232' })
                            .text('<?php echo esc_js(__('Disable Site', 'rechat-plugin')); ?>')
                            .data('enable', 0);
                    } else {
                        $status.html('<span style="color:#888;">&#9632; <?php echo esc_js(__('Disabled', 'rechat-plugin')); ?></span>');
                        $btn
                            .addClass('button-primary')
                            .css({ color: '', 'border-color': '' })
                            .text('<?php echo esc_js(__('Enable Site', 'rechat-plugin')); ?>')
                            .data('enable', 1);
                    }

                    if (d.blog_id) {
                        $blogId.text(d.blog_id);
                    }

                    $btn.prop('disabled', false);
                } else {
                    $btn.prop('disabled', false).text(
                        enable ? '<?php echo esc_js(__('Enable Site', 'rechat-plugin')); ?>'
                               : '<?php echo esc_js(__('Disable Site', 'rechat-plugin')); ?>'
                    );
                    alert(response.data || '<?php echo esc_js(__('An error occurred.', 'rechat-plugin')); ?>');
                }
            }).fail(function () {
                $btn.prop('disabled', false).text(
                    enable ? '<?php echo esc_js(__('Enable Site', 'rechat-plugin')); ?>'
                           : '<?php echo esc_js(__('Disable Site', 'rechat-plugin')); ?>'
                );
                alert('<?php echo esc_js(__('Request failed. Please try again.', 'rechat-plugin')); ?>');
            });
        });

    }(jQuery));
    </script>
    <?php
}
