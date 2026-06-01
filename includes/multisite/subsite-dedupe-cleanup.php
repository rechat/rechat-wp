<?php
/**
 * Multisite: remove duplicate agent/office subsites and duplicate hub agent posts.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! is_multisite()) {
    return;
}

/**
 * Public URL slug segment for a network site (subdomain label or subdirectory segment).
 *
 * @param \WP_Site|object $site Site object.
 * @return string Empty when not a Rechat-style subsite location.
 */
function rch_multisite_public_slug_from_site($site): string
{
    if (! $site || ! isset($site->domain, $site->path)) {
        return '';
    }

    $network      = get_network();
    $base_domain  = preg_replace('/^www\./i', '', (string) $network->domain);
    $network_path = trailingslashit((string) $network->path);
    $domain       = strtolower((string) $site->domain);
    $path         = (string) $site->path;

    if (rch_multisite_get_url_type() === 'subdirectory') {
        if (strpos($path, $network_path) !== 0) {
            return '';
        }

        $rest = substr($path, strlen($network_path));
        $rest = trim($rest, '/');

        return $rest;
    }

    $suffix = '.' . $base_domain;

    if (strlen($domain) < strlen($suffix) || substr($domain, -strlen($suffix)) !== $suffix) {
        return '';
    }

    return substr($domain, 0, -strlen($suffix));
}

/**
 * Whether a subsite slug belongs to the same agent as a base slug (incl. numeric suffix dupes).
 *
 * @param string      $candidate_slug Site slug.
 * @param string      $base_slug      Agent base slug.
 * @param string|null $format         Slug format.
 * @return bool
 */
function rch_multisite_slug_matches_agent_family(string $candidate_slug, string $base_slug, ?string $format = null): bool
{
    $format = $format ?? rch_multisite_get_agent_slug_format();
    $base   = rch_multisite_normalize_agent_site_slug($base_slug, $format);
    $slug   = rch_multisite_normalize_agent_site_slug($candidate_slug, $format);

    if ($base === '' || $slug === '') {
        return false;
    }

    if ($slug === $base) {
        return true;
    }

    if ($format === 'firstname_lastname') {
        return (bool) preg_match('/^' . preg_quote($base, '/') . '-\d+$/', $slug);
    }

    return (bool) preg_match('/^' . preg_quote($base, '/') . '\d+$/', $slug);
}

/**
 * Whether a subsite slug belongs to the same office slug family (o-name, o-name2, …).
 *
 * @param string $candidate_slug Site slug.
 * @param string $base_slug      Office public slug (o-…).
 * @return bool
 */
function rch_multisite_slug_matches_office_family(string $candidate_slug, string $base_slug): bool
{
    $base = rch_multisite_sanitize_slug($base_slug);
    $slug = rch_multisite_sanitize_slug($candidate_slug);

    if ($base === '' || $slug === '') {
        return false;
    }

    if ($slug === $base) {
        return true;
    }

    return (bool) preg_match('/^' . preg_quote($base, '/') . '\d+$/', $slug);
}

/**
 * All network blog IDs whose public slug matches an agent slug family.
 *
 * @param string $base_slug Agent base slug.
 * @return int[]
 */
function rch_multisite_blog_ids_in_agent_slug_family(string $base_slug): array
{
    $format = rch_multisite_get_agent_slug_format();
    $base   = rch_multisite_normalize_agent_site_slug($base_slug, $format);
    $ids    = [];
    $main   = (int) get_main_site_id();

    foreach (get_sites(['number' => 0, 'fields' => 'all']) as $site) {
        $blog_id = (int) $site->blog_id;

        if ($blog_id === $main) {
            continue;
        }

        $slug = rch_multisite_public_slug_from_site($site);

        if ($slug !== '' && rch_multisite_slug_matches_agent_family($slug, $base, $format)) {
            $ids[] = $blog_id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * All network blog IDs in an office slug family.
 *
 * @param string $base_slug Office slug (o-…).
 * @return int[]
 */
function rch_multisite_blog_ids_in_office_slug_family(string $base_slug): array
{
    $ids  = [];
    $main = (int) get_main_site_id();

    foreach (get_sites(['number' => 0, 'fields' => 'all']) as $site) {
        $blog_id = (int) $site->blog_id;

        if ($blog_id === $main) {
            continue;
        }

        $slug = rch_multisite_public_slug_from_site($site);

        if ($slug !== '' && rch_multisite_slug_matches_office_family($slug, $base_slug)) {
            $ids[] = $blog_id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Pick one subsite to keep for an agent (prefer base slug, then lowest blog ID).
 *
 * @param int[]  $blog_ids  Candidate blog IDs.
 * @param string $base_slug Agent base slug.
 * @return int 0 if none.
 */
function rch_multisite_pick_canonical_agent_blog_id(array $blog_ids, string $base_slug): int
{
    $blog_ids = array_values(array_unique(array_filter(array_map('intval', $blog_ids))));

    if ($blog_ids === []) {
        return 0;
    }

    $format = rch_multisite_get_agent_slug_format();
    $base   = rch_multisite_normalize_agent_site_slug($base_slug, $format);
    $exact  = [];

    foreach ($blog_ids as $blog_id) {
        $site = get_site($blog_id);

        if (! $site) {
            continue;
        }

        $slug = rch_multisite_public_slug_from_site($site);

        if ($slug === $base) {
            $exact[] = $blog_id;
        }
    }

    if ($exact !== []) {
        return (int) min($exact);
    }

    return (int) min($blog_ids);
}

/**
 * Pick canonical office subsite (prefer exact o-slug match).
 *
 * @param int[]  $blog_ids  Candidate IDs.
 * @param string $base_slug Office slug.
 * @return int
 */
function rch_multisite_pick_canonical_office_blog_id(array $blog_ids, string $base_slug): int
{
    $blog_ids = array_values(array_unique(array_filter(array_map('intval', $blog_ids))));
    $base = (strpos($base_slug, 'o-') === 0)
        ? rch_multisite_sanitize_slug($base_slug)
        : rch_multisite_office_public_slug($base_slug);

    if ($base === '') {
        return 0;
    }

    if ($blog_ids === []) {
        return 0;
    }

    $exact = [];

    foreach ($blog_ids as $blog_id) {
        $site = get_site($blog_id);

        if (! $site) {
            continue;
        }

        if (rch_multisite_public_slug_from_site($site) === $base) {
            $exact[] = $blog_id;
        }
    }

    if ($exact !== []) {
        return (int) min($exact);
    }

    return (int) min($blog_ids);
}

/**
 * Permanently delete a subsite (never the main site).
 *
 * @param int  $blog_id Blog ID.
 * @param bool $dry_run Preview only.
 * @return bool True when deleted or would delete.
 */
function rch_multisite_remove_duplicate_blog(int $blog_id, bool $dry_run): bool
{
    $blog_id = (int) $blog_id;
    $main    = (int) get_main_site_id();

    if ($blog_id <= 0 || $blog_id === $main) {
        return false;
    }

    if (! get_site($blog_id)) {
        return false;
    }

    if ($dry_run) {
        return true;
    }

    wpmu_delete_blog($blog_id, true);

    return true;
}

/**
 * Trash duplicate hub `agents` posts that share the same Rechat api_id.
 *
 * @param bool $dry_run Preview only.
 * @return array{kept:int,trashed:int,skipped:int}
 */
function rch_multisite_deduplicate_hub_agent_posts(bool $dry_run = true): array
{
    $by_api   = [];
    $kept     = 0;
    $trashed  = 0;
    $skipped  = 0;

    $agents = get_posts([
        'post_type'   => 'agents',
        'numberposts' => -1,
        'post_status' => 'any',
        'fields'      => 'all',
    ]);

    foreach ($agents as $agent) {
        $api_id = trim((string) get_post_meta($agent->ID, 'api_id', true));

        if ($api_id === '') {
            $skipped++;
            continue;
        }

        if (! isset($by_api[$api_id])) {
            $by_api[$api_id] = [];
        }

        $by_api[$api_id][] = (int) $agent->ID;
    }

    foreach ($by_api as $post_ids) {
        if (count($post_ids) < 2) {
            continue;
        }

        $canonical = 0;
        $best_blog = PHP_INT_MAX;

        foreach ($post_ids as $pid) {
            $bid = (int) get_post_meta($pid, '_rch_agent_site_id', true);

            if ($bid > 0 && $bid < $best_blog) {
                $best_blog = $bid;
                $canonical = $pid;
            }
        }

        if ($canonical <= 0) {
            $canonical = (int) min($post_ids);
        }

        $kept++;

        foreach ($post_ids as $pid) {
            if ($pid === $canonical) {
                continue;
            }

            delete_post_meta($pid, '_rch_agent_site_id');
            delete_post_meta($pid, '_rch_agent_slug');

            if ($dry_run) {
                $trashed++;
                continue;
            }

            wp_trash_post($pid);
            $trashed++;
        }
    }

    return compact('kept', 'trashed', 'skipped');
}

/**
 * For each agent post: keep one subsite, delete numbered duplicates, fix meta.
 *
 * @param bool $dry_run Preview only.
 * @return array<string, mixed>
 */
function rch_multisite_cleanup_duplicate_agent_subsites(bool $dry_run = true): array
{
    $agents_processed = 0;
    $sites_kept       = 0;
    $sites_removed    = 0;
    $meta_linked      = 0;
    $errors           = [];
    $kept_blog_ids    = [];
    $main             = (int) get_main_site_id();
    $kept_blog_ids[]  = $main;

    $agents = get_posts([
        'post_type'   => 'agents',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

    foreach ($agents as $agent) {
        $agent_id = (int) $agent->ID;
        $name     = trim((string) $agent->post_title);

        if ($name === '') {
            continue;
        }

        $agents_processed++;
        $base_slug = rch_multisite_agent_site_slug_base($agent_id, $name);

        if ($base_slug === '') {
            continue;
        }

        $candidates = rch_multisite_blog_ids_in_agent_slug_family($base_slug);

        $linked = (int) get_post_meta($agent_id, '_rch_agent_site_id', true);

        if ($linked > 0 && get_site($linked)) {
            $candidates[] = $linked;
        }

        $candidates = array_values(array_unique(array_filter(array_map('intval', $candidates))));

        if ($candidates === []) {
            continue;
        }

        $canonical = rch_multisite_pick_canonical_agent_blog_id($candidates, $base_slug);

        if ($canonical <= 0) {
            continue;
        }

        $slug = rch_multisite_public_slug_from_site(get_site($canonical));

        if ($slug === '') {
            $slug = rch_multisite_resolve_agent_site_slug($agent_id, $name, $canonical);
        }

        if (! in_array($canonical, $kept_blog_ids, true)) {
            $kept_blog_ids[] = $canonical;
            $sites_kept++;
        }

        if ((int) get_post_meta($agent_id, '_rch_agent_site_id', true) !== $canonical) {
            if (! $dry_run) {
                if (function_exists('rch_multisite_link_agent_to_existing_site')) {
                    rch_multisite_link_agent_to_existing_site($agent_id, $canonical, $slug);
                } else {
                    update_post_meta($agent_id, '_rch_agent_site_id', $canonical);
                    update_post_meta($agent_id, '_rch_agent_slug', $slug);
                }
            }

            $meta_linked++;
        }

        foreach ($candidates as $blog_id) {
            if ($blog_id === $canonical) {
                continue;
            }

            if (rch_multisite_remove_duplicate_blog($blog_id, $dry_run)) {
                $sites_removed++;
            } else {
                $errors[] = sprintf('Could not remove agent duplicate blog_id=%d', $blog_id);
            }
        }
    }

    return compact(
        'agents_processed',
        'sites_kept',
        'sites_removed',
        'meta_linked',
        'errors',
        'kept_blog_ids'
    );
}

/**
 * For each office post: keep one subsite, delete duplicates.
 *
 * @param bool              $dry_run       Preview only.
 * @param int[]|null        $kept_blog_ids Blog IDs already kept (agents + main).
 * @return array<string, mixed>
 */
function rch_multisite_cleanup_duplicate_office_subsites(bool $dry_run = true, ?array $kept_blog_ids = null): array
{
    $offices_processed = 0;
    $sites_kept        = 0;
    $sites_removed     = 0;
    $meta_linked       = 0;
    $errors            = [];

    if ($kept_blog_ids === null) {
        $kept_blog_ids = [(int) get_main_site_id()];
    }

    $offices = get_posts([
        'post_type'   => 'offices',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

    foreach ($offices as $office) {
        $office_id = (int) $office->ID;
        $name      = trim((string) $office->post_title);

        if ($name === '') {
            continue;
        }

        $offices_processed++;
        $base_slug = rch_multisite_office_public_slug($name);

        if ($base_slug === '') {
            continue;
        }

        $candidates = rch_multisite_blog_ids_in_office_slug_family($base_slug);
        $linked     = (int) get_post_meta($office_id, '_rch_office_site_id', true);

        if ($linked > 0 && get_site($linked)) {
            $candidates[] = $linked;
        }

        $candidates = array_values(array_unique(array_filter(array_map('intval', $candidates))));

        if ($candidates === []) {
            continue;
        }

        $canonical = rch_multisite_pick_canonical_office_blog_id($candidates, $base_slug);

        if ($canonical <= 0) {
            continue;
        }

        if (! in_array($canonical, $kept_blog_ids, true)) {
            $kept_blog_ids[] = $canonical;
            $sites_kept++;
        }

        if ((int) get_post_meta($office_id, '_rch_office_site_id', true) !== $canonical) {
            if (! $dry_run) {
                update_post_meta($office_id, '_rch_office_site_id', $canonical);
                update_post_meta($office_id, '_rch_office_slug', $base_slug);

                if (function_exists('rch_multisite_set_subsite_role_option')) {
                    rch_multisite_set_subsite_role_option($canonical, 'office');
                }
            }

            $meta_linked++;
        }

        foreach ($candidates as $blog_id) {
            if ($blog_id === $canonical) {
                continue;
            }

            if (rch_multisite_remove_duplicate_blog($blog_id, $dry_run)) {
                $sites_removed++;
            } else {
                $errors[] = sprintf('Could not remove office duplicate blog_id=%d', $blog_id);
            }
        }
    }

    return compact(
        'offices_processed',
        'sites_kept',
        'sites_removed',
        'meta_linked',
        'errors',
        'kept_blog_ids'
    );
}

/**
 * Remove agent subsites that match a known slug family but were not linked to any agent post.
 *
 * @param bool       $dry_run       Preview only.
 * @param int[]|null $kept_blog_ids IDs to preserve.
 * @return array{sites_removed:int,errors:string[]}
 */
function rch_multisite_cleanup_orphan_agent_subsites(bool $dry_run = true, ?array $kept_blog_ids = null): array
{
    $sites_removed = 0;
    $errors        = [];
    $main          = (int) get_main_site_id();

    if ($kept_blog_ids === null) {
        $kept_blog_ids = [$main];
    }

    $known_families = [];

    $agents = get_posts([
        'post_type'   => 'agents',
        'numberposts' => -1,
        'post_status' => 'publish',
        'fields'      => 'all',
    ]);

    foreach ($agents as $agent) {
        $name = trim((string) $agent->post_title);

        if ($name === '') {
            continue;
        }

        $base = rch_multisite_agent_site_slug_base((int) $agent->ID, $name);

        if ($base !== '') {
            $known_families[$base] = true;
        }
    }

    foreach (get_sites(['number' => 0, 'fields' => 'all']) as $site) {
        $blog_id = (int) $site->blog_id;

        if ($blog_id === $main || in_array($blog_id, $kept_blog_ids, true)) {
            continue;
        }

        $slug = rch_multisite_public_slug_from_site($site);

        if ($slug === '') {
            continue;
        }

        $matched = false;

        foreach (array_keys($known_families) as $base) {
            if (rch_multisite_slug_matches_agent_family($slug, $base)) {
                $matched = true;
                break;
            }
        }

        if (! $matched) {
            continue;
        }

        if (rch_multisite_remove_duplicate_blog($blog_id, $dry_run)) {
            $sites_removed++;
        } else {
            $errors[] = sprintf('Could not remove orphan blog_id=%d (%s)', $blog_id, $slug);
        }
    }

    return compact('sites_removed', 'errors');
}

/**
 * Full network cleanup: duplicate agent posts, agent subsites, office subsites, orphans.
 *
 * @param bool $dry_run When true, only reports actions (no deletes).
 * @return array<string, mixed>
 */
function rch_multisite_run_subsite_dedupe_cleanup(bool $dry_run = true): array
{
    $main_id     = (int) get_main_site_id();
    $total_sites = count(get_sites(['number' => 0, 'fields' => 'ids']));

    $agent_posts = rch_multisite_deduplicate_hub_agent_posts($dry_run);
    $agent_sites = rch_multisite_cleanup_duplicate_agent_subsites($dry_run);
    $kept        = $agent_sites['kept_blog_ids'] ?? [$main_id];

    $office_sites = rch_multisite_cleanup_duplicate_office_subsites($dry_run, $kept);

    $removed_total =
        (int) ($agent_sites['sites_removed'] ?? 0) +
        (int) ($office_sites['sites_removed'] ?? 0);

    $errors = array_merge(
        $agent_sites['errors'] ?? [],
        $office_sites['errors'] ?? []
    );

    return [
        'dry_run'              => $dry_run,
        'network_sites_before' => $total_sites,
        'network_sites_after'  => $dry_run ? $total_sites : max(1, $total_sites - $removed_total),
        'sites_removed_total'  => $removed_total,
        'agent_posts'          => $agent_posts,
        'agent_sites'          => $agent_sites,
        'office_sites'         => $office_sites,
        'errors'               => $errors,
    ];
}

/**
 * AJAX: preview or run duplicate subsite cleanup.
 *
 * @return void
 */
function rch_multisite_ajax_subsite_dedupe_cleanup(): void
{
    check_ajax_referer('rch_multisite_subsite_dedupe', '_nonce');

    if (! current_user_can('manage_network_options')) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    $dry_run = ! isset($_POST['execute']) || '1' !== (string) wp_unslash($_POST['execute']);

    if (! $dry_run && (! isset($_POST['confirm']) || 'yes' !== (string) wp_unslash($_POST['confirm']))) {
        wp_send_json_error(__('Missing confirmation.', 'rechat-plugin'));
        return;
    }

    $result = rch_multisite_run_subsite_dedupe_cleanup($dry_run);

    $ap = $result['agent_posts'];
    $as = $result['agent_sites'];
    $os = $result['office_sites'];
    $message = $dry_run
        ? sprintf(
            /* translators: 1: sites to remove, 2: current network site count */
            __('Preview: would remove %1$d duplicate sub-site(s). Network currently has %2$d site(s). No changes were made.', 'rechat-plugin'),
            (int) $result['sites_removed_total'],
            (int) $result['network_sites_before']
        )
        : sprintf(
            /* translators: 1: removed count, 2: site count after */
            __('Removed %1$d duplicate sub-site(s). Network now has about %2$d site(s).', 'rechat-plugin'),
            (int) $result['sites_removed_total'],
            (int) $result['network_sites_after']
        );

    $detail = sprintf(
        'Agent posts: %d duplicate hub post(s) %s. Agent subsites: %d agent(s) scanned, %d kept, %d removed, %d meta link(s). Offices: %d office(s), %d removed.',
        (int) ($ap['trashed'] ?? 0),
        $dry_run ? 'would trash' : 'trashed',
        (int) ($as['agents_processed'] ?? 0),
        (int) ($as['sites_kept'] ?? 0),
        (int) ($as['sites_removed'] ?? 0),
        (int) ($as['meta_linked'] ?? 0),
        (int) ($os['offices_processed'] ?? 0),
        (int) ($os['sites_removed'] ?? 0)
    );

    wp_send_json_success([
        'message' => $message,
        'detail'  => $detail,
        'result'  => $result,
        'errors'  => $result['errors'],
    ]);
}

add_action('wp_ajax_rch_multisite_subsite_dedupe_cleanup', 'rch_multisite_ajax_subsite_dedupe_cleanup');
