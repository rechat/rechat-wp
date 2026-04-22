<?php
/**
 * Detect Rechat “agent-only” network subsites and expose helpers to slim the admin UI there.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Persist how this blog was provisioned by Rechat (for multisite agent/office subsites).
 *
 * @param int    $blog_id Blog ID.
 * @param string $role    `agent` or `office`.
 * @return void
 */
function rch_multisite_set_subsite_role_option(int $blog_id, string $role): void
{
    if (! is_multisite() || $blog_id <= 0) {
        return;
    }

    if (! in_array($role, ['agent', 'office'], true)) {
        return;
    }

    switch_to_blog($blog_id);
    update_option('rch_rechat_subsite_role', $role, true);
    restore_current_blog();
}

/**
 * Whether the current blog is an agent subsite (not the network hub, not an office subsite).
 *
 * When true, Rechat hides its CPT menus, settings page, and related admin UI so editors only see
 * normal WordPress content tools on the agent’s public site.
 *
 * @return bool
 */
function rch_is_rechat_agent_only_subsite(): bool
{
    static $cache = null;

    if ($cache !== null) {
        return (bool) apply_filters('rch_is_rechat_agent_only_subsite', $cache);
    }

    $cache = false;

    if (! is_multisite()) {
        return (bool) apply_filters('rch_is_rechat_agent_only_subsite', $cache);
    }

    $blog_id = get_current_blog_id();
    $main_id  = (int) get_main_site_id();

    if ($blog_id <= 0 || $blog_id === $main_id) {
        return (bool) apply_filters('rch_is_rechat_agent_only_subsite', $cache);
    }

    $role = (string) get_option('rch_rechat_subsite_role', '');

    if ($role === 'agent') {
        $cache = true;

        return (bool) apply_filters('rch_is_rechat_agent_only_subsite', $cache);
    }

    if ($role === 'office') {
        return (bool) apply_filters('rch_is_rechat_agent_only_subsite', $cache);
    }

    // Legacy subsites created before we stored rch_rechat_subsite_role — detect once from main site meta.
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
        restore_current_blog();
        rch_multisite_set_subsite_role_option($blog_id, 'agent');
        $cache = true;

        return (bool) apply_filters('rch_is_rechat_agent_only_subsite', $cache);
    }

    $office_match = new WP_Query([
        'post_type'              => 'offices',
        'post_status'            => 'any',
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => [
            [
                'key'   => '_rch_office_site_id',
                'value' => (string) $blog_id,
            ],
        ],
    ]);

    restore_current_blog();

    if ($office_match->have_posts()) {
        rch_multisite_set_subsite_role_option($blog_id, 'office');
    }

    return (bool) apply_filters('rch_is_rechat_agent_only_subsite', $cache);
}

/**
 * Block direct access to Rechat settings when the admin UI is hidden on an agent subsite.
 *
 * @return void
 */
function rch_rechat_agent_subsite_block_settings_page(): void
{
    if (! is_admin() || wp_doing_ajax()) {
        return;
    }

    if (! function_exists('rch_is_rechat_agent_only_subsite') || ! rch_is_rechat_agent_only_subsite()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

    if ($page !== 'rechat-setting') {
        return;
    }

    if (! current_user_can('read')) {
        return;
    }

    wp_safe_redirect(admin_url());
    exit;
}

add_action('admin_init', 'rch_rechat_agent_subsite_block_settings_page', 0);
