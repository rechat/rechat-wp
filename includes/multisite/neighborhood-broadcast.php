<?php
/**
 * Multisite: broadcast hub neighborhoods to agent subsites by office overlap.
 *
 * Agent has offices (_rch_agent_offices). Neighborhood has offices (_rch_neighborhood_office).
 * When they share an office, the neighborhood is broadcast to that agent's subsite via ThreeWP Broadcast.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! is_multisite()) {
    return;
}

const RCH_AGENT_NEIGHBORHOOD_BROADCAST_HOOK = 'rch_sync_agent_neighborhood_broadcasts';

/**
 * Ensure Broadcast can handle the neighborhoods CPT.
 *
 * @param string[] $types Post type slugs.
 * @return string[]
 */
function rch_neighborhood_broadcast_register_post_type(array $types): array
{
    if (post_type_exists('neighborhoods') && ! in_array('neighborhoods', $types, true)) {
        $types[] = 'neighborhoods';
    }

    return $types;
}

add_filter('rch_multisite_broadcast_scan_post_types', 'rch_neighborhood_broadcast_register_post_type');

/**
 * @param string[] $types Post types from Broadcast settings.
 * @return string[]
 */
function rch_neighborhood_broadcast_threewp_post_types(array $types): array
{
    if (post_type_exists('neighborhoods') && ! in_array('neighborhoods', $types, true)) {
        $types[] = 'neighborhoods';
    }

    return $types;
}

add_filter('threewp_broadcast_post_types', 'rch_neighborhood_broadcast_threewp_post_types');

/**
 * Normalize agent office meta to post IDs.
 *
 * @param mixed $raw Meta value.
 * @return int[]
 */
function rch_normalize_agent_office_ids($raw): array
{
    if (! is_array($raw)) {
        return [];
    }

    $ids = array_map('absint', $raw);
    $ids = array_values(array_unique(array_filter($ids)));

    return $ids;
}

/**
 * Agent post IDs on the hub linked to any of the given offices.
 *
 * @param int[] $office_ids Office post IDs on the source blog.
 * @return int[]
 */
function rch_get_agent_post_ids_for_offices(array $office_ids): array
{
    $office_ids = array_values(array_unique(array_filter(array_map('absint', $office_ids))));

    if ($office_ids === []) {
        return [];
    }

    $source = rch_multisite_broadcast_source_blog_id();
    $prev   = get_current_blog_id();

    if ($prev !== $source) {
        switch_to_blog($source);
    }

    $meta_query = ['relation' => 'OR'];

    foreach ($office_ids as $office_id) {
        $meta_query[] = [
            'key'     => '_rch_agent_offices',
            'value'   => sprintf('i:%d;', $office_id),
            'compare' => 'LIKE',
        ];
    }

    $query = new WP_Query([
        'post_type'              => 'agents',
        'post_status'            => 'any',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => $meta_query,
    ]);

    if ($prev !== $source) {
        restore_current_blog();
    }

    $ids = array_map('intval', $query->posts);

    return array_values(array_unique(array_filter($ids)));
}

/**
 * Hub neighborhood post IDs assigned to any of the given offices.
 *
 * @param int[] $office_ids Office post IDs.
 * @return int[]
 */
function rch_get_neighborhood_ids_for_offices(array $office_ids): array
{
    $office_ids = array_values(array_unique(array_filter(array_map('absint', $office_ids))));

    if ($office_ids === []) {
        return [];
    }

    $source = rch_multisite_broadcast_source_blog_id();
    $prev   = get_current_blog_id();

    if ($prev !== $source) {
        switch_to_blog($source);
    }

    $meta_query = ['relation' => 'OR'];

    foreach ($office_ids as $office_id) {
        $meta_query[] = [
            'key'     => RCH_NEIGHBORHOOD_OFFICE_META_KEY,
            'value'   => sprintf('i:%d;', $office_id),
            'compare' => 'LIKE',
        ];
        $meta_query[] = [
            'key'     => RCH_NEIGHBORHOOD_OFFICE_META_KEY,
            'value'   => $office_id,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ];
    }

    $query = new WP_Query([
        'post_type'              => 'neighborhoods',
        'post_status'            => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => $meta_query,
    ]);

    if ($prev !== $source) {
        restore_current_blog();
    }

    $ids = array_map('intval', $query->posts);

    return array_values(array_unique(array_filter($ids)));
}

/**
 * Neighborhood IDs on the hub that match an agent's assigned offices.
 *
 * @param int $agent_post_id Agent post ID on the source blog.
 * @return int[]
 */
function rch_get_neighborhood_ids_for_agent(int $agent_post_id): array
{
    $agent_post_id = absint($agent_post_id);

    if ($agent_post_id <= 0) {
        return [];
    }

    $source = rch_multisite_broadcast_source_blog_id();
    $prev   = get_current_blog_id();

    if ($prev !== $source) {
        switch_to_blog($source);
    }

    $office_ids = rch_normalize_agent_office_ids(get_post_meta($agent_post_id, '_rch_agent_offices', true));

    if ($prev !== $source) {
        restore_current_blog();
    }

    return rch_get_neighborhood_ids_for_offices($office_ids);
}

/**
 * Resolve hub agent post ID from an agent subsite blog ID.
 *
 * @param int $blog_id Agent subsite blog ID.
 * @return int
 */
function rch_resolve_agent_post_id_for_blog(int $blog_id): int
{
    $blog_id = absint($blog_id);
    $main_id = (int) get_main_site_id();

    if ($blog_id <= 0 || $blog_id === $main_id) {
        return 0;
    }

    switch_to_blog($main_id);

    $match = new WP_Query([
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

    $agent_id = $match->have_posts() ? (int) $match->posts[0] : 0;

    restore_current_blog();

    return $agent_id;
}

/**
 * Debounced cron: sync neighborhoods for one agent.
 *
 * @param int $agent_post_id Hub agent post ID.
 * @return void
 */
function rch_schedule_agent_neighborhood_broadcast_sync(int $agent_post_id): void
{
    $agent_post_id = absint($agent_post_id);

    if ($agent_post_id <= 0 || ! rch_multisite_broadcast_plugin_active()) {
        return;
    }

    $args = [$agent_post_id];

    if (wp_next_scheduled(RCH_AGENT_NEIGHBORHOOD_BROADCAST_HOOK, $args)) {
        return;
    }

    $key = 'rch_nb_sync_' . $agent_post_id;

    if (get_site_transient($key)) {
        return;
    }

    set_site_transient($key, 1, 2 * MINUTE_IN_SECONDS);
    wp_schedule_single_event(time() + 12, RCH_AGENT_NEIGHBORHOOD_BROADCAST_HOOK, $args);
}

/**
 * Broadcast one hub post to a target blog (ThreeWP Broadcast).
 *
 * @param int $parent_post_id Post ID on source blog.
 * @param int $target_blog_id Target subsite blog ID.
 * @return bool
 */
function rch_broadcast_post_to_blog(int $parent_post_id, int $target_blog_id): bool
{
    $parent_post_id = absint($parent_post_id);
    $target_blog_id = absint($target_blog_id);

    if ($parent_post_id <= 0 || $target_blog_id <= 0 || ! function_exists('ThreeWP_Broadcast')) {
        return false;
    }

    $source = rch_multisite_broadcast_source_blog_id();

    if ($target_blog_id === $source) {
        return false;
    }

    $runner = rch_multisite_broadcast_runner_user_id();
    $prev   = get_current_user_id();

    wp_set_current_user($runner);

    switch_to_blog($source);

    try {
        $post = get_post($parent_post_id);

        if (! $post instanceof WP_Post || $post->post_type !== 'neighborhoods') {
            restore_current_blog();
            wp_set_current_user($prev);

            return false;
        }

        /** @var \threewp_broadcast\ThreeWP_Broadcast $broadcast */
        $broadcast = ThreeWP_Broadcast();
        $api       = $broadcast->api();

        if (apply_filters('rch_multisite_broadcast_use_low_priority', false)) {
            $api->low_priority();
        }

        $api->broadcast_children($parent_post_id, [$target_blog_id]);
    } catch (Throwable $e) {
        error_log(
            'Rechat neighborhood broadcast: failed post ' . $parent_post_id .
            ' → blog ' . $target_blog_id . ' — ' . $e->getMessage()
        );
        restore_current_blog();
        wp_set_current_user($prev);

        return false;
    }

    restore_current_blog();
    wp_set_current_user($prev);

    return true;
}

/**
 * Remove broadcast neighborhood children on an agent blog that no longer match office overlap.
 *
 * @param int   $agent_blog_id        Agent subsite blog ID.
 * @param int[] $allowed_parent_ids   Hub neighborhood post IDs that should remain.
 * @return int Number of posts trashed.
 */
function rch_prune_stale_neighborhood_broadcasts_on_blog(int $agent_blog_id, array $allowed_parent_ids): int
{
    $agent_blog_id = absint($agent_blog_id);

    if ($agent_blog_id <= 0 || ! function_exists('ThreeWP_Broadcast')) {
        return 0;
    }

    $allowed_parent_ids = array_flip(array_map('absint', $allowed_parent_ids));
    $source             = rch_multisite_broadcast_source_blog_id();
    $trashed            = 0;

    switch_to_blog($agent_blog_id);

    $children = get_posts([
        'post_type'      => 'neighborhoods',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($children as $child_id) {
        $child_id = (int) $child_id;
        $bcd      = rch_multisite_get_post_broadcast_data($agent_blog_id, $child_id);

        if (! is_object($bcd) || ! method_exists($bcd, 'get_linked_parent')) {
            continue;
        }

        $parent = $bcd->get_linked_parent();

        if (! is_array($parent) || empty($parent['blog_id']) || empty($parent['post_id'])) {
            continue;
        }

        if ((int) $parent['blog_id'] !== $source) {
            continue;
        }

        $parent_id = (int) $parent['post_id'];

        if (isset($allowed_parent_ids[ $parent_id ])) {
            continue;
        }

        if (wp_trash_post($child_id)) {
            $trashed++;
        }
    }

    restore_current_blog();

    return $trashed;
}

/**
 * Push matching hub neighborhoods to one agent subsite; prune stale copies.
 *
 * @param int $agent_post_id Hub agent post ID.
 * @return void
 */
function rch_run_sync_agent_neighborhood_broadcasts(int $agent_post_id): void
{
    delete_site_transient('rch_nb_sync_' . $agent_post_id);

    if (! rch_multisite_broadcast_plugin_active()) {
        return;
    }

    $agent_post_id = absint($agent_post_id);

    if ($agent_post_id <= 0) {
        return;
    }

    $source = rch_multisite_broadcast_source_blog_id();
    $prev   = get_current_blog_id();

    if ($prev !== $source) {
        switch_to_blog($source);
    }

    $agent_blog_id = function_exists('rch_multisite_get_agent_blog_id')
        ? rch_multisite_get_agent_blog_id($agent_post_id)
        : (int) get_post_meta($agent_post_id, '_rch_agent_site_id', true);

    if ($prev !== $source) {
        restore_current_blog();
    }

    if ($agent_blog_id <= 0 || $agent_blog_id === $source) {
        return;
    }

    $neighborhood_ids = rch_get_neighborhood_ids_for_agent($agent_post_id);
    $neighborhood_ids = (array) apply_filters(
        'rch_agent_neighborhood_broadcast_post_ids',
        $neighborhood_ids,
        $agent_post_id,
        $agent_blog_id
    );

    $broadcasted = 0;
    $failed      = 0;

    foreach ($neighborhood_ids as $neighborhood_id) {
        if (rch_broadcast_post_to_blog((int) $neighborhood_id, $agent_blog_id)) {
            $broadcasted++;
        } else {
            $failed++;
        }
    }

    $pruned = rch_prune_stale_neighborhood_broadcasts_on_blog($agent_blog_id, $neighborhood_ids);

    error_log(
        sprintf(
            'Rechat neighborhood broadcast: agent post %d → blog %d — sent: %d, failed: %d, pruned: %d',
            $agent_post_id,
            $agent_blog_id,
            $broadcasted,
            $failed,
            $pruned
        )
    );
}

add_action(RCH_AGENT_NEIGHBORHOOD_BROADCAST_HOOK, 'rch_run_sync_agent_neighborhood_broadcasts', 10, 1);

/**
 * Schedule neighborhood broadcast sync for agents tied to any office in either list.
 *
 * @param int[] $previous_office_ids Offices before change.
 * @param int[] $new_office_ids      Offices after change.
 * @return void
 */
function rch_schedule_agents_for_neighborhood_office_change(array $previous_office_ids, array $new_office_ids): void
{
    $merged = array_values(
        array_unique(
            array_merge(
                array_map('absint', $previous_office_ids),
                array_map('absint', $new_office_ids)
            )
        )
    );

    foreach (rch_get_agent_post_ids_for_offices($merged) as $agent_id) {
        rch_schedule_agent_neighborhood_broadcast_sync($agent_id);
    }
}

/**
 * After hub neighborhood office assignment changes, sync affected agents.
 *
 * @param int $neighborhood_id Neighborhood post ID.
 * @return void
 */
function rch_neighborhood_offices_changed_sync_agents(int $neighborhood_id): void
{
    if (! function_exists('rch_neighborhood_office_metabox_is_main_site')
        || ! rch_neighborhood_office_metabox_is_main_site()) {
        return;
    }

    $office_ids = rch_get_neighborhood_office_ids($neighborhood_id);

    foreach (rch_get_agent_post_ids_for_offices($office_ids) as $agent_id) {
        rch_schedule_agent_neighborhood_broadcast_sync($agent_id);
    }
}

add_action('save_post_neighborhoods', 'rch_neighborhood_offices_changed_sync_agents', 40);

/**
 * When neighborhood office meta updates (including API), sync agents for those offices.
 *
 * @param int    $meta_id    Meta ID.
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value.
 * @return void
 */
function rch_on_neighborhood_office_meta_updated($meta_id, $post_id, $meta_key, $meta_value): void
{
    unset($meta_id, $meta_value);

    if ($meta_key !== RCH_NEIGHBORHOOD_OFFICE_META_KEY || get_post_type($post_id) !== 'neighborhoods') {
        return;
    }

    rch_neighborhood_offices_changed_sync_agents((int) $post_id);
}

add_action('updated_post_meta', 'rch_on_neighborhood_office_meta_updated', 10, 4);
add_action('added_post_meta', 'rch_on_neighborhood_office_meta_updated', 10, 4);
add_action('deleted_post_meta', 'rch_on_neighborhood_office_meta_updated', 10, 4);

/**
 * When agent office assignment changes, sync that agent's neighborhoods.
 *
 * @param int $agent_post_id Agent post ID.
 * @return void
 */
function rch_agent_offices_changed_sync_neighborhoods(int $agent_post_id): void
{
    if (get_post_type($agent_post_id) !== 'agents') {
        return;
    }

    rch_schedule_agent_neighborhood_broadcast_sync($agent_post_id);
}

add_action('save_post_agents', 'rch_agent_offices_changed_sync_neighborhoods', 40);

/**
 * @param int    $meta_id    Meta ID.
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value.
 * @return void
 */
function rch_on_agent_offices_meta_updated($meta_id, $post_id, $meta_key, $meta_value): void
{
    unset($meta_id, $meta_value);

    if ($meta_key !== '_rch_agent_offices' || get_post_type($post_id) !== 'agents') {
        return;
    }

    rch_schedule_agent_neighborhood_broadcast_sync((int) $post_id);
}

add_action('updated_post_meta', 'rch_on_agent_offices_meta_updated', 10, 4);
add_action('added_post_meta', 'rch_on_agent_offices_meta_updated', 10, 4);
add_action('deleted_post_meta', 'rch_on_agent_offices_meta_updated', 10, 4);

/**
 * New agent subsite: schedule neighborhood broadcast after generic Broadcast pass.
 *
 * @param int $agent_post_id Agent post ID.
 * @param int $blog_id       New subsite blog ID.
 * @return void
 */
function rch_on_agent_site_created_schedule_neighborhood_broadcast(int $agent_post_id, int $blog_id): void
{
    unset($blog_id);

    rch_schedule_agent_neighborhood_broadcast_sync($agent_post_id);
}

add_action('rch_multisite_agent_site_created', 'rch_on_agent_site_created_schedule_neighborhood_broadcast', 10, 2);

/**
 * Generic “broadcast to new blog” cron finished — also sync neighborhoods for agent subsites.
 *
 * @param int $new_blog_id New blog ID.
 * @return void
 */
function rch_after_broadcast_to_new_blog_sync_neighborhoods(int $new_blog_id): void
{
    $agent_id = rch_resolve_agent_post_id_for_blog($new_blog_id);

    if ($agent_id > 0) {
        rch_schedule_agent_neighborhood_broadcast_sync($agent_id);
    }
}

add_action('rch_multisite_run_broadcast_to_new_blog', 'rch_after_broadcast_to_new_blog_sync_neighborhoods', 20, 1);
