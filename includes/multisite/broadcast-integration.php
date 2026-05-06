<?php
/**
 * Rechat Multisite ↔ ThreeWP Broadcast integration.
 *
 * - Treats Broadcast as required when agent or office subsites are enabled.
 * - After a Rechat-provisioned subsite exists, pushes already-broadcast parent posts
 *   to the new blog via Broadcast’s API (update_children with new blog IDs).
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
 * Possible plugin.php paths for ThreeWP Broadcast (folder name varies by install source).
 *
 * @return string[]
 */
function rch_multisite_broadcast_plugin_basenames(): array
{
    return (array) apply_filters(
        'rch_multisite_broadcast_plugin_basenames',
        [
            'ThreeWP_Broadcast/ThreeWP_Broadcast.php',
            'threewp-broadcast/ThreeWP_Broadcast.php',
        ]
    );
}

/**
 * Whether ThreeWP Broadcast is loaded and network-active.
 */
function rch_multisite_broadcast_plugin_active(): bool
{
    if (! function_exists('ThreeWP_Broadcast')) {
        return false;
    }

    if (! function_exists('is_plugin_active_for_network')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    foreach (rch_multisite_broadcast_plugin_basenames() as $basename) {
        if ($basename && is_plugin_active_for_network($basename)) {
            return true;
        }
    }

    return false;
}

/**
 * True when Rechat is configured to create agent and/or office subsites (Broadcast required).
 */
function rch_multisite_subsites_require_broadcast(): bool
{
    if (! function_exists('rch_multisite_is_create_agent_sites_enabled')) {
        return false;
    }

    return rch_multisite_is_create_agent_sites_enabled()
        || rch_multisite_is_create_office_sites_enabled();
}

/**
 * Return WP_Error when subsites are enabled but Broadcast is missing.
 *
 * @return WP_Error|null
 */
function rch_multisite_broadcast_dependency_error(): ?WP_Error
{
    if (! rch_multisite_subsites_require_broadcast()) {
        return null;
    }

    if (rch_multisite_broadcast_plugin_active()) {
        return null;
    }

    return new WP_Error(
        'rch_broadcast_required',
        __(
            'Rechat multisite requires the Broadcast (ThreeWP Broadcast) plugin to be installed and network-activated. Install it from the Plugins screen or your network admin, then try again.',
            'rechat-plugin'
        )
    );
}

/**
 * Blog ID where parent posts live (Broadcast source). Default: main site of the network.
 */
function rch_multisite_broadcast_source_blog_id(): int
{
    return (int) apply_filters('rch_multisite_broadcast_source_blog_id', get_main_site_id());
}

/**
 * Child post ID on a target blog for a parent post (ThreeWP Broadcast), or the parent ID when target is the parent blog.
 *
 * @param int $parent_blog_id Blog where $parent_post_id exists.
 * @param int $parent_post_id Post ID on that blog.
 * @param int $target_blog_id  Sub-site to resolve onto.
 * @return int 0 if Broadcast is unavailable or there is no linked child on the target.
 */
function rch_multisite_broadcast_child_post_id_on_blog(int $parent_blog_id, int $parent_post_id, int $target_blog_id): int
{
    if ($parent_post_id <= 0 || $target_blog_id <= 0 || $parent_blog_id <= 0) {
        return 0;
    }

    if ($target_blog_id === $parent_blog_id) {
        switch_to_blog($parent_blog_id);
        $p = get_post($parent_post_id);
        restore_current_blog();

        return $p instanceof WP_Post ? $parent_post_id : 0;
    }

    if (! function_exists('ThreeWP_Broadcast')) {
        return 0;
    }

    /** @var mixed $bcd */
    $bcd = null;

    switch_to_blog($parent_blog_id);

    try {
        $broadcast = ThreeWP_Broadcast();
        $bcd       = $broadcast->get_post_broadcast_data($parent_blog_id, $parent_post_id);
    } catch (Throwable $e) {
        $bcd = null;
    }

    restore_current_blog();

    if (! is_object($bcd) || ! method_exists($bcd, 'get_linked_children')) {
        return 0;
    }

    $children = $bcd->get_linked_children();
    if (! is_array($children) || $children === []) {
        return 0;
    }

    $child_id = 0;
    if (isset($children[ $target_blog_id ])) {
        $child_id = (int) $children[ $target_blog_id ];
    } elseif (isset($children[ (string) $target_blog_id ])) {
        $child_id = (int) $children[ (string) $target_blog_id ];
    }

    if ($child_id <= 0) {
        return 0;
    }

    switch_to_blog($target_blog_id);
    $child = get_post($child_id);
    restore_current_blog();

    return $child instanceof WP_Post ? $child_id : 0;
}

/**
 * User ID used while running Broadcast API (capabilities). Default: first super admin, else multisite owner option, else 1.
 */
function rch_multisite_broadcast_runner_user_id(): int
{
    $filtered = (int) apply_filters('rch_multisite_broadcast_runner_user_id', 0);

    if ($filtered > 0 && get_userdata($filtered)) {
        return $filtered;
    }

    $super = (array) get_super_admins();

    if ($super !== []) {
        $u = get_user_by('login', (string) $super[0]);

        if ($u) {
            return (int) $u->ID;
        }
    }

    $owner = absint(get_site_option('rch_multisite_admin_user_id', 0));

    if ($owner && get_userdata($owner)) {
        return $owner;
    }

    return 1;
}

/**
 * Post types to scan on the source blog (Broadcast’s configured list, plus common CPTs).
 *
 * @return string[]
 */
function rch_multisite_broadcast_scan_post_types(): array
{
    $types = ['post', 'page'];

    if (rch_multisite_broadcast_plugin_active()) {
        /** @var \threewp_broadcast\ThreeWP_Broadcast $broadcast */
        $broadcast = ThreeWP_Broadcast();
        $raw       = $broadcast->get_site_option('post_types', 'post page');

        if (is_string($raw) && $raw !== '') {
            $types = array_filter(array_map('trim', preg_split('/\s+/', $raw)));
        }
    }

    foreach (['agents', 'offices', 'listings'] as $rch) {
        if (post_type_exists($rch)) {
            $types[] = $rch;
        }
    }

    $types = array_values(array_unique(array_filter($types)));

    return (array) apply_filters('rch_multisite_broadcast_scan_post_types', $types);
}

/**
 * Queue a deferred Broadcast pass so HTTP requests are not blocked for large networks.
 */
function rch_multisite_schedule_broadcast_to_new_blog(int $new_blog_id): void
{
    if ($new_blog_id <= 0) {
        return;
    }

    if (! rch_multisite_broadcast_plugin_active()) {
        return;
    }

    $source = rch_multisite_broadcast_source_blog_id();

    if ($new_blog_id === $source) {
        return;
    }

    $hook = 'rch_multisite_run_broadcast_to_new_blog';
    $args = [$new_blog_id];

    if (wp_next_scheduled($hook, $args)) {
        return;
    }

    $transient_key = 'rch_bc_sched_' . $new_blog_id;

    if (get_site_transient($transient_key)) {
        return;
    }

    set_site_transient($transient_key, 1, 2 * MINUTE_IN_SECONDS);
    wp_schedule_single_event(time() + 15, $hook, $args);
}

/**
 * Cron / scheduled handler: broadcast all parent posts that already have children to one new blog.
 *
 * @param int $new_blog_id Target subsite blog ID.
 * @return void
 */
function rch_multisite_run_broadcast_to_new_blog(int $new_blog_id): void
{
    delete_site_transient('rch_bc_sched_' . $new_blog_id);

    if (! rch_multisite_broadcast_plugin_active()) {
        return;
    }

    $source = rch_multisite_broadcast_source_blog_id();

    if ($new_blog_id <= 0 || $new_blog_id === $source) {
        return;
    }

    $runner = rch_multisite_broadcast_runner_user_id();
    $prev   = get_current_user_id();

    wp_set_current_user($runner);

    switch_to_blog($source);

    /** @var \threewp_broadcast\ThreeWP_Broadcast $broadcast */
    $broadcast = ThreeWP_Broadcast();
    $types     = rch_multisite_broadcast_scan_post_types();
    $api       = $broadcast->api();

    if (apply_filters('rch_multisite_broadcast_use_low_priority', false)) {
        $api->low_priority();
    }
    $updated = 0;
    $failed  = 0;
    $paged   = 1;
    $per     = (int) apply_filters('rch_multisite_broadcast_batch_size', 40);

    if ($per < 1) {
        $per = 40;
    }

    try {
        for ($paged = 1; $paged <= 5000; $paged++) {
            $query = new WP_Query([
                'post_type'              => $types,
                'post_status'            => ['publish', 'future'],
                'posts_per_page'         => $per,
                'paged'                  => $paged,
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'fields'                 => 'ids',
                'no_found_rows'          => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);

            if (empty($query->posts)) {
                break;
            }

            foreach ($query->posts as $post_id) {
                $post_id = (int) $post_id;
                $bcd     = $broadcast->get_post_broadcast_data($source, $post_id);

                if ($bcd->get_linked_parent() !== false) {
                    continue;
                }

                if (! $bcd->has_linked_children()) {
                    continue;
                }

                $children = $bcd->get_linked_children();

                if (is_array($children) && array_key_exists($new_blog_id, $children)) {
                    continue;
                }

                try {
                    // Prefer broadcast_children() so we only push to the new blog (update_children() would
                    // rebroadcast to every existing child site as well, which is costly on large networks).
                    $api->broadcast_children($post_id, [$new_blog_id]);
                    $updated++;
                } catch (Throwable $e) {
                    $failed++;
                    error_log(
                        'Rechat Multisite + Broadcast: broadcast_children failed for post ' . $post_id .
                        ' → blog ' . $new_blog_id . ' — ' . $e->getMessage()
                    );
                }
            }

            if ($paged >= (int) $query->max_num_pages) {
                break;
            }
        }
    } catch (Throwable $e) {
        error_log(
            'Rechat Multisite + Broadcast: fatal during rebroadcast to blog ' . $new_blog_id .
            ' — ' . $e->getMessage()
        );
    }

    restore_current_blog();
    wp_set_current_user($prev);

    error_log(
        sprintf(
            'Rechat Multisite + Broadcast: finished push to blog_id=%d from source blog_id=%d — updated: %d, failures: %d',
            $new_blog_id,
            $source,
            $updated,
            $failed
        )
    );
}

add_action('rch_multisite_run_broadcast_to_new_blog', 'rch_multisite_run_broadcast_to_new_blog', 10, 1);

/**
 * Optional: run the same Broadcast push for any new network site (not only Rechat-created).
 * Enable with: add_filter( 'rch_multisite_broadcast_on_any_new_site', '__return_true' );
 *
 * @param \WP_Site $new_site New site object.
 * @return void
 */
function rch_multisite_on_wp_initialize_site_broadcast(\WP_Site $new_site): void
{
    if (! apply_filters('rch_multisite_broadcast_on_any_new_site', false)) {
        return;
    }

    $blog_id = (int) $new_site->blog_id;

    if ($blog_id <= 0 || $blog_id === rch_multisite_broadcast_source_blog_id()) {
        return;
    }

    if (! rch_multisite_broadcast_plugin_active()) {
        return;
    }

    rch_multisite_schedule_broadcast_to_new_blog($blog_id);
}

add_action('wp_initialize_site', 'rch_multisite_on_wp_initialize_site_broadcast', 100, 1);

/**
 * Admin / network admin notice when Broadcast is required but inactive.
 */
function rch_multisite_broadcast_dependency_admin_notice(): void
{
    if (! current_user_can('manage_network_options') && ! current_user_can('manage_options')) {
        return;
    }

    if (! rch_multisite_subsites_require_broadcast() || rch_multisite_broadcast_plugin_active()) {
        return;
    }

    $install = network_admin_url('plugin-install.php?tab=plugin-information&plugin=threewp-broadcast');

    echo '<div class="notice notice-error"><p>';
    echo esc_html__(
        'Rechat multisite is set to create agent or office sub-sites, but the Broadcast (ThreeWP Broadcast) plugin is not network-active. Install and network-activate Broadcast so content can be synced to new sites.',
        'rechat-plugin'
    );
    echo ' ';
    printf(
        '<a href="%1$s">%2$s</a>',
        esc_url($install),
        esc_html__('View plugin on WordPress.org', 'rechat-plugin')
    );
    echo '</p></div>';
}

add_action('admin_notices', 'rch_multisite_broadcast_dependency_admin_notice');
add_action('network_admin_notices', 'rch_multisite_broadcast_dependency_admin_notice');
