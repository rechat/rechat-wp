<?php
/**
 * Sync main-site agent testimonials (agent_testimonials meta) to sub-site testimonial CPT posts.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('rch_sanitize_agent_testimonial_stars') && defined('RCH_PLUGIN_INCLUDES')) {
    require_once RCH_PLUGIN_INCLUDES . 'metabox/metaboxes-for-agent-testimonials.php';
}

require_once RCH_PLUGIN_INCLUDES . 'multisite/testimonial-cpt-subfields.php';

/** Post meta on sub-site testimonial posts — source agent ID (main site). */
const RCH_TESTIMONIAL_SYNC_AGENT_META = '_rch_sync_agent_id';

if (! defined('RCH_TESTIMONIAL_SYNC_KEY_META')) {
    define('RCH_TESTIMONIAL_SYNC_KEY_META', '_rch_sync_key');
}
if (! defined('RCH_TESTIMONIAL_STARS_META')) {
    define('RCH_TESTIMONIAL_STARS_META', 'testimonial_stars');
}
if (! defined('RCH_TESTIMONIAL_LINK_META')) {
    define('RCH_TESTIMONIAL_LINK_META', 'testimonial_link');
}

/**
 * Stable key for one testimonial row (index + content).
 *
 * @param array{name?:string, description?:string, stars?:string, link?:string} $item
 */
function rch_agent_testimonial_sync_row_key(int $index, array $item): string
{
    $name = isset($item['name']) ? strtolower(trim((string) $item['name'])) : '';
    $desc = isset($item['description']) ? strtolower(trim((string) $item['description'])) : '';

    return substr(md5($index . '|' . $name . '|' . $desc), 0, 20);
}

/**
 * @param array<string, mixed> $item
 */
function rch_agent_testimonial_sync_item_stars(array $item): string
{
    $raw = $item['stars'] ?? $item['testimonial_stars'] ?? $item['rank'] ?? $item['testimonial_rank'] ?? '';

    if (function_exists('rch_sanitize_agent_testimonial_stars')) {
        return rch_sanitize_agent_testimonial_stars($raw);
    }

    return is_scalar($raw) ? trim((string) $raw) : '';
}

/**
 * @param array<string, mixed> $item
 */
function rch_agent_testimonial_sync_item_link(array $item): string
{
    $raw = $item['link'] ?? $item['testimonial_link'] ?? $item['url'] ?? '';

    if (function_exists('rch_sanitize_agent_testimonial_link')) {
        return rch_sanitize_agent_testimonial_link($raw);
    }

    $url = is_scalar($raw) ? trim((string) $raw) : '';

    return $url !== '' ? esc_url_raw($url) : '';
}

/**
 * @return array<int, array{name:string, description:string, stars:string, link:string}>
 */
function rch_agent_testimonial_sync_get_source_rows(int $agent_id): array
{
    if ($agent_id <= 0) {
        return [];
    }

    if (function_exists('rch_get_agent_testimonials')) {
        return rch_get_agent_testimonials($agent_id);
    }

    if (! defined('RCH_AGENT_TESTIMONIALS_META_KEY')) {
        return [];
    }

    $storage_blog = function_exists('rch_agent_testimonials_storage_blog_id')
        ? rch_agent_testimonials_storage_blog_id()
        : (int) get_current_blog_id();
    $switched     = false;

    if (is_multisite() && get_current_blog_id() !== $storage_blog) {
        switch_to_blog($storage_blog);
        $switched = true;
    }

    $raw = get_post_meta($agent_id, RCH_AGENT_TESTIMONIALS_META_KEY, true);
    if ($raw === '' || $raw === false) {
        $raw = get_post_meta($agent_id, 'rch_agent_testimonials', true);
    }

    if ($switched) {
        restore_current_blog();
    }

    if (function_exists('rch_agent_testimonials_normalize_stored_meta')) {
        $raw = rch_agent_testimonials_normalize_stored_meta($raw);
    }

    return function_exists('rch_sanitize_agent_testimonials')
        ? rch_sanitize_agent_testimonials($raw)
        : [];
}

/**
 * Sync one agent's testimonials into a sub-site blog (must call inside switch_to_blog target).
 *
 * @return array{created:int, updated:int, deleted:int, skipped:int, total:int}|WP_Error
 */
function rch_agent_testimonial_sync_apply_on_current_blog(int $agent_id, array $rows)
{
    if (! post_type_exists('testimonial')) {
        return new WP_Error(
            'rch_testimonial_cpt_missing',
            __('The testimonial post type is not registered on this sub-site. Activate the agent theme (or register the testimonial CPT) and try again.', 'rechat-plugin')
        );
    }

    $existing_posts = get_posts([
        'post_type'      => 'testimonial',
        'post_status'    => ['publish', 'draft', 'private', 'pending'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => RCH_TESTIMONIAL_SYNC_AGENT_META,
                'value' => (string) $agent_id,
            ],
        ],
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ]);

    $by_key = [];
    foreach ($existing_posts as $post_id) {
        $post_id = (int) $post_id;
        $key     = (string) get_post_meta($post_id, RCH_TESTIMONIAL_SYNC_KEY_META, true);
        if ($key === '') {
            continue;
        }
        if (! isset($by_key[ $key ])) {
            $by_key[ $key ] = $post_id;
        }
    }

    $created  = 0;
    $updated  = 0;
    $skipped  = 0;
    $seen     = [];
    $index    = 0;

    foreach ($rows as $item) {
        if (! is_array($item)) {
            ++$skipped;
            continue;
        }

        $name = isset($item['name']) ? sanitize_text_field((string) $item['name']) : '';
        $desc = isset($item['description']) ? wp_kses_post((string) $item['description']) : '';
        $stars = rch_agent_testimonial_sync_item_stars($item);
        $link  = rch_agent_testimonial_sync_item_link($item);

        if ($name === '' && trim(wp_strip_all_tags($desc)) === '') {
            ++$skipped;
            continue;
        }

        $sync_key = rch_agent_testimonial_sync_row_key($index, [
            'name'        => $name,
            'description' => $desc,
            'stars'       => $stars,
            'link'        => $link,
        ]);
        $seen[ $sync_key ] = true;
        ++$index;

        $title = $name !== '' ? $name : __('Testimonial', 'rechat-plugin');

        $postarr = [
            'post_type'    => 'testimonial',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $desc,
            'post_name'    => sanitize_title($title . '-' . $sync_key),
            'menu_order'   => $index - 1,
        ];

        if (isset($by_key[ $sync_key ])) {
            $post_id = (int) $by_key[ $sync_key ];
            $postarr['ID'] = $post_id;
            $result      = wp_update_post($postarr, true);
            if (is_wp_error($result)) {
                ++$skipped;
                continue;
            }
            rch_agent_testimonial_sync_save_row_meta($post_id, $stars, $link);
            ++$updated;
            continue;
        }

        $post_id = wp_insert_post($postarr, true);
        if (is_wp_error($post_id) || ! $post_id) {
            ++$skipped;
            continue;
        }

        $post_id = (int) $post_id;
        update_post_meta($post_id, RCH_TESTIMONIAL_SYNC_AGENT_META, $agent_id);
        update_post_meta($post_id, RCH_TESTIMONIAL_SYNC_KEY_META, $sync_key);
        rch_agent_testimonial_sync_save_row_meta($post_id, $stars, $link);
        ++$created;
    }

    rch_agent_testimonial_sync_repair_row_meta_by_order($agent_id, $rows);

    $deleted = 0;
    foreach ($by_key as $key => $post_id) {
        if (isset($seen[ $key ])) {
            continue;
        }
        if (wp_delete_post((int) $post_id, true)) {
            ++$deleted;
        }
    }

    return [
        'created' => $created,
        'updated' => $updated,
        'deleted' => $deleted,
        'skipped' => $skipped,
        'total'   => count($rows),
    ];
}

/**
 * Persist stars + link on a sub-site testimonial post.
 */
function rch_agent_testimonial_sync_save_row_meta(int $post_id, string $stars, string $link): void
{
    if ($post_id <= 0) {
        return;
    }

    if ($stars !== '') {
        update_post_meta($post_id, RCH_TESTIMONIAL_STARS_META, $stars);
        update_post_meta($post_id, 'stars', $stars);
    } else {
        delete_post_meta($post_id, RCH_TESTIMONIAL_STARS_META);
        delete_post_meta($post_id, 'stars');
    }

    if ($link !== '') {
        update_post_meta($post_id, RCH_TESTIMONIAL_LINK_META, $link);
        update_post_meta($post_id, 'link', $link);
    } else {
        delete_post_meta($post_id, RCH_TESTIMONIAL_LINK_META);
        delete_post_meta($post_id, 'link');
    }
}

/**
 * Re-apply stars/link on synced testimonial posts by menu_order (fixes posts synced before meta existed).
 *
 * @param array<int, array<string, mixed>> $rows
 */
function rch_agent_testimonial_sync_repair_row_meta_by_order(int $agent_id, array $rows): void
{
    if ($rows === []) {
        return;
    }

    $posts = get_posts([
        'post_type'      => 'testimonial',
        'post_status'    => ['publish', 'draft', 'private', 'pending'],
        'posts_per_page' => 50,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'   => RCH_TESTIMONIAL_SYNC_AGENT_META,
                'value' => (string) $agent_id,
            ],
        ],
    ]);

    if ($posts === []) {
        return;
    }

    $index = 0;
    foreach ($rows as $item) {
        if (! is_array($item)) {
            continue;
        }

        $name = isset($item['name']) ? sanitize_text_field((string) $item['name']) : '';
        $desc = isset($item['description']) ? (string) $item['description'] : '';
        if ($name === '' && trim(wp_strip_all_tags($desc)) === '') {
            continue;
        }

        if (! isset($posts[ $index ])) {
            break;
        }

        $post_id = (int) $posts[ $index ]->ID;
        rch_agent_testimonial_sync_save_row_meta(
            $post_id,
            rch_agent_testimonial_sync_item_stars($item),
            rch_agent_testimonial_sync_item_link($item)
        );
        ++$index;
    }
}

/**
 * Sync agent testimonials from main site to linked sub-site.
 *
 * @return array{created:int, updated:int, deleted:int, skipped:int, total:int, blog_id:int}|WP_Error
 */
function rch_agent_wizard_sync_testimonials_for_agent(int $agent_id)
{
    if (! is_multisite()) {
        return new WP_Error('rch_testimonial_ms', __('Multisite only.', 'rechat-plugin'));
    }

    if ($agent_id <= 0) {
        return new WP_Error('rch_testimonial_agent', __('Invalid agent.', 'rechat-plugin'));
    }

    $storage_blog = function_exists('rch_agent_testimonials_storage_blog_id')
        ? rch_agent_testimonials_storage_blog_id()
        : (int) get_current_blog_id();
    $switched     = false;

    if (is_multisite() && get_current_blog_id() !== $storage_blog) {
        switch_to_blog($storage_blog);
        $switched = true;
    }

    $post = get_post($agent_id);
    if (! $post || $post->post_type !== 'agents') {
        if ($switched) {
            restore_current_blog();
        }

        return new WP_Error('rch_testimonial_agent', __('Invalid agent post.', 'rechat-plugin'));
    }

    if ($switched) {
        restore_current_blog();
    }

    if (! function_exists('rch_multisite_get_agent_blog_id')) {
        return new WP_Error('rch_testimonial_missing', __('Multisite helpers are not loaded.', 'rechat-plugin'));
    }

    $blog_id = (int) rch_multisite_get_agent_blog_id($agent_id);
    if ($blog_id <= 0) {
        return new WP_Error(
            'rch_testimonial_no_blog',
            __('This agent has no linked sub-site. Provision the site first.', 'rechat-plugin')
        );
    }

    $rows = rch_agent_testimonial_sync_get_source_rows($agent_id);

    if ($rows === []) {
        return new WP_Error(
            'rch_testimonial_empty',
            __('No testimonials found on this agent on the main site. Open the agent, add testimonials in the Testimonials box, click Update/Save, then import again.', 'rechat-plugin')
        );
    }

    switch_to_blog($blog_id);
    $result = rch_agent_testimonial_sync_apply_on_current_blog($agent_id, $rows);
    restore_current_blog();

    if (is_wp_error($result)) {
        return $result;
    }

    $result['blog_id'] = $blog_id;

    /**
     * @param array{created:int, updated:int, deleted:int, skipped:int, total:int, blog_id:int} $result
     */
    return apply_filters('rch_agent_wizard_sync_testimonials_result', $result, $agent_id, $blog_id, $rows);
}

/**
 * Sync testimonials for every published agent that has a sub-site.
 *
 * @return array{agents:int, created:int, updated:int, deleted:int, skipped:int, errors:list<string>}
 */
function rch_agent_wizard_sync_testimonials_for_all_agents(): array
{
    $summary = [
        'agents'  => 0,
        'created' => 0,
        'updated' => 0,
        'deleted' => 0,
        'skipped' => 0,
        'errors'  => [],
    ];

    $storage_blog = function_exists('rch_agent_testimonials_storage_blog_id')
        ? rch_agent_testimonials_storage_blog_id()
        : (int) get_current_blog_id();
    $switched     = false;

    if (is_multisite() && get_current_blog_id() !== $storage_blog) {
        switch_to_blog($storage_blog);
        $switched = true;
    }

    $agent_ids = get_posts([
        'post_type'      => 'agents',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    if ($switched) {
        restore_current_blog();
    }

    foreach ($agent_ids as $agent_id) {
        $agent_id = (int) $agent_id;
        $result   = rch_agent_wizard_sync_testimonials_for_agent($agent_id);

        if (is_wp_error($result)) {
            if ($result->get_error_code() === 'rch_testimonial_no_blog') {
                continue;
            }
            $summary['errors'][] = sprintf(
                '%s (ID %d): %s',
                get_the_title($agent_id),
                $agent_id,
                $result->get_error_message()
            );
            continue;
        }

        ++$summary['agents'];
        $summary['created'] += (int) $result['created'];
        $summary['updated'] += (int) $result['updated'];
        $summary['deleted'] += (int) $result['deleted'];
        $summary['skipped'] += (int) $result['skipped'];
    }

    return $summary;
}

/**
 * Auto-sync when an agent is saved on the main site (if it has a sub-site).
 */
function rch_agent_maybe_auto_sync_testimonials_on_save(int $post_id, WP_Post $post, bool $update): void
{
    if (! is_multisite() || ! $update) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if ($post->post_type !== 'agents' || $post->post_status === 'auto-draft') {
        return;
    }

    /**
     * @param bool $enabled
     * @param int  $post_id
     */
    $enabled = apply_filters('rch_agent_auto_sync_testimonials', true, $post_id);
    if (! $enabled) {
        return;
    }

    if (! function_exists('rch_multisite_get_agent_blog_id')) {
        return;
    }

    if ((int) rch_multisite_get_agent_blog_id($post_id) <= 0) {
        return;
    }

    rch_agent_wizard_sync_testimonials_for_agent($post_id);
}
add_action('save_post_agents', 'rch_agent_maybe_auto_sync_testimonials_on_save', 30, 3);

/**
 * AJAX: sync testimonials to sub-site(s).
 */
function rch_agent_wizard_ajax_sync_testimonials(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    $scope    = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : 'single';
    $agent_id = isset($_POST['agent_id']) ? absint($_POST['agent_id']) : 0;

    if ($scope === 'single' && $agent_id <= 0) {
        wp_send_json_error(['message' => __('Select an agent and load profile first, or choose “All agent sub-sites”.', 'rechat-plugin')]);
    }

    if ($scope === 'all') {
        $bulk = rch_agent_wizard_sync_testimonials_for_all_agents();
        $msg  = sprintf(
            /* translators: 1: agents synced, 2: created, 3: updated, 4: removed */
            __('Synced testimonials for %1$d agent sub-site(s): %2$d created, %3$d updated, %4$d removed from sub-sites.', 'rechat-plugin'),
            $bulk['agents'],
            $bulk['created'],
            $bulk['updated'],
            $bulk['deleted']
        );
        wp_send_json_success([
            'message' => $msg,
            'summary' => $bulk,
        ]);
        return;
    }

    if ($agent_id <= 0) {
        wp_send_json_error(['message' => __('Select an agent or choose “All agent sub-sites”.', 'rechat-plugin')]);
    }

    $result = rch_agent_wizard_sync_testimonials_for_agent($agent_id);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    $msg = sprintf(
        /* translators: 1: created, 2: updated, 3: removed, 4: source row count */
        __('Testimonials synced on sub-site: %1$d created, %2$d updated, %3$d removed (%4$d on main site).', 'rechat-plugin'),
        (int) $result['created'],
        (int) $result['updated'],
        (int) $result['deleted'],
        (int) $result['total']
    );

    wp_send_json_success([
        'message' => $msg,
        'summary' => $result,
    ]);
}

add_action('wp_ajax_rch_agent_wizard_sync_testimonials', 'rch_agent_wizard_ajax_sync_testimonials');
