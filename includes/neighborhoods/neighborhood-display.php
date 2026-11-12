<?php
/**
 * Neighborhood display controls: order, visibility and "similar neighborhoods".
 *
 * Every theme overrides the neighborhood templates and runs its own WP_Query, so the rules
 * are applied centrally in pre_get_posts instead of in templates. Three front-end places
 * ("contexts") are recognised:
 *
 *   home    - neighborhood queries on the front page / blog home
 *   archive - the /neighborhoods/ archive (main query and the theme's own query)
 *   related - neighborhood queries on a single neighborhood page ("similar neighborhoods")
 *
 * Per neighborhood (post meta):
 *   _rch_nh_hide_home / _rch_nh_hide_archive / _rch_nh_hide_related = '1'
 *   _rch_nh_related_mode  ''|'manual'|'none'   similar section ON this neighborhood's page
 *   _rch_nh_related_ids   int[]                 manual picks for that section
 *
 * Per site (options):
 *   rch_nh_order_active      '1' once an order was saved on the Order & Visibility screen
 *   rch_nh_archive_per_page  int, 0 = keep the theme / plugin default
 *   rch_nh_hidden_ids        cached hidden-ID lists per context
 *
 * Hidden neighborhoods stay published: own URL, sitemap, search, admin, REST and every query
 * outside the three contexts are untouched. With nothing configured, sites render exactly as
 * before.
 *
 * Themes can force or skip the rules on a query:
 *   new WP_Query(['post_type' => 'neighborhoods', 'rch_nh_context' => 'home'|'archive'|'related'|'none'])
 *
 * @package Rechat_Plugin
 */

if (! defined('ABSPATH')) {
    exit;
}

const RCH_NH_POST_TYPE        = 'neighborhoods';
const RCH_NH_META_RELATED_MODE = '_rch_nh_related_mode';
const RCH_NH_META_RELATED_IDS  = '_rch_nh_related_ids';
const RCH_NH_OPT_ORDER_ACTIVE  = 'rch_nh_order_active';
const RCH_NH_OPT_ARCHIVE_COUNT = 'rch_nh_archive_per_page';
const RCH_NH_OPT_HIDDEN_CACHE  = 'rch_nh_hidden_ids';

/**
 * Contexts that can hide a neighborhood => their meta key.
 *
 * @return array<string, string>
 */
function rch_nh_hide_meta_keys(): array
{
    return [
        'home'    => '_rch_nh_hide_home',
        'archive' => '_rch_nh_hide_archive',
        'related' => '_rch_nh_hide_related',
    ];
}

/**
 * Human labels for the three places.
 *
 * @return array<string, string>
 */
function rch_nh_context_labels(): array
{
    return [
        'home'    => __('Home page', 'rechat-plugin'),
        'archive' => __('Neighborhoods page', 'rechat-plugin'),
        'related' => __('Similar neighborhoods', 'rechat-plugin'),
    ];
}

/* -------------------------------------------------------------------------
 * Data helpers
 * ---------------------------------------------------------------------- */

/**
 * IDs hidden from a context. Cached in one option, rebuilt when a hide flag changes.
 *
 * @param string $context home|archive|related
 * @return int[]
 */
function rch_nh_get_hidden_ids(string $context): array
{
    $keys = rch_nh_hide_meta_keys();
    if (! isset($keys[$context])) {
        return [];
    }

    $cache = get_option(RCH_NH_OPT_HIDDEN_CACHE, null);

    if (! is_array($cache)) {
        global $wpdb;

        $cache = [];
        foreach ($keys as $ctx => $meta_key) {
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE pm.meta_key = %s AND pm.meta_value = '1' AND p.post_type = %s",
                    $meta_key,
                    RCH_NH_POST_TYPE
                )
            );
            $cache[$ctx] = array_values(array_unique(array_map('intval', (array) $ids)));
        }

        update_option(RCH_NH_OPT_HIDDEN_CACHE, $cache, true);
    }

    return isset($cache[$context]) && is_array($cache[$context])
        ? array_map('intval', $cache[$context])
        : [];
}

/**
 * Drop the hidden-ID cache whenever a hide flag is added, changed or removed
 * (editor, Order & Visibility screen, WP-CLI, Broadcast copies, ...).
 *
 * @param mixed  $meta_id  Unused.
 * @param int    $post_id  Unused.
 * @param string $meta_key Meta key.
 */
function rch_nh_flush_hidden_cache_on_meta($meta_id, $post_id, $meta_key): void
{
    if (in_array($meta_key, rch_nh_hide_meta_keys(), true)) {
        delete_option(RCH_NH_OPT_HIDDEN_CACHE);
    }
}
add_action('added_post_meta', 'rch_nh_flush_hidden_cache_on_meta', 10, 3);
add_action('updated_post_meta', 'rch_nh_flush_hidden_cache_on_meta', 10, 3);
add_action('deleted_post_meta', 'rch_nh_flush_hidden_cache_on_meta', 10, 3);

/**
 * Whether a neighborhood is hidden from a context.
 */
function rch_nh_is_hidden(int $post_id, string $context): bool
{
    $keys = rch_nh_hide_meta_keys();

    return isset($keys[$context]) && get_post_meta($post_id, $keys[$context], true) === '1';
}

/**
 * Set / clear one hide flag.
 */
function rch_nh_set_hidden(int $post_id, string $context, bool $hidden): void
{
    $keys = rch_nh_hide_meta_keys();
    if (! isset($keys[$context])) {
        return;
    }

    if ($hidden) {
        update_post_meta($post_id, $keys[$context], '1');
    } else {
        delete_post_meta($post_id, $keys[$context]);
    }
}

/**
 * Similar-section mode of one neighborhood page: 'auto' (default), 'manual' or 'none'.
 * 'manual' without any published pick falls back to 'auto'.
 */
function rch_nh_get_related_mode(int $post_id): string
{
    $mode = (string) get_post_meta($post_id, RCH_NH_META_RELATED_MODE, true);

    if ($mode === 'none') {
        return 'none';
    }
    if ($mode === 'manual' && rch_nh_get_related_ids($post_id)) {
        return 'manual';
    }

    return 'auto';
}

/**
 * Manually picked similar neighborhoods (published only, never the page itself).
 *
 * @return int[]
 */
function rch_nh_get_related_ids(int $post_id): array
{
    $ids = get_post_meta($post_id, RCH_NH_META_RELATED_IDS, true);
    $ids = is_array($ids) ? array_map('intval', $ids) : [];
    $out = [];

    foreach ($ids as $id) {
        if (
            $id > 0
            && $id !== $post_id
            && get_post_type($id) === RCH_NH_POST_TYPE
            && get_post_status($id) === 'publish'
        ) {
            $out[] = $id;
        }
    }

    return array_values(array_unique($out));
}

/**
 * For themes: should the "similar neighborhoods" section be printed on this page?
 *
 *   <?php if (rch_neighborhood_show_related()) : ?> <section>…</section> <?php endif; ?>
 *
 * @param int $post_id Neighborhood ID, default: the current page.
 */
function rch_neighborhood_show_related(int $post_id = 0): bool
{
    $post_id = $post_id > 0 ? $post_id : (int) get_queried_object_id();

    if ($post_id <= 0 || get_post_type($post_id) !== RCH_NH_POST_TYPE) {
        return true;
    }

    return rch_nh_get_related_mode($post_id) !== 'none';
}

/* -------------------------------------------------------------------------
 * Front end: apply the rules to neighborhood queries
 * ---------------------------------------------------------------------- */

/**
 * Which context a neighborhoods query belongs to ('' = leave the query alone).
 */
function rch_nh_detect_query_context(WP_Query $query): string
{
    $forced = $query->get('rch_nh_context');
    if (is_string($forced) && $forced !== '') {
        return in_array($forced, ['home', 'archive', 'related'], true) ? $forced : '';
    }

    if (
        is_admin()
        || wp_doing_cron()
        || (defined('REST_REQUEST') && REST_REQUEST)
        || (defined('WP_CLI') && WP_CLI)
        || $query->is_feed()
    ) {
        return '';
    }

    // A query for specific posts is an explicit choice of the theme: never touch it.
    if ($query->get('p') || $query->get('name') || $query->get('post__in')) {
        return '';
    }

    if ($query->is_main_query()) {
        return $query->is_post_type_archive(RCH_NH_POST_TYPE) ? 'archive' : '';
    }

    // Secondary query: the page being rendered decides.
    global $wp_query;
    if (! $wp_query instanceof WP_Query || ! did_action('wp')) {
        return '';
    }

    if ($wp_query->is_post_type_archive(RCH_NH_POST_TYPE)) {
        return 'archive';
    }
    if ($wp_query->is_singular(RCH_NH_POST_TYPE)) {
        return 'related';
    }
    if ($wp_query->is_front_page() || $wp_query->is_home()) {
        return 'home';
    }

    return '';
}

/**
 * Put the saved manual order first and keep the query's own sort as the tiebreaker.
 */
function rch_nh_apply_manual_order(WP_Query $query): void
{
    if (get_option(RCH_NH_OPT_ORDER_ACTIVE) !== '1') {
        return;
    }

    $orderby = $query->get('orderby');
    $order   = strtoupper((string) $query->get('order')) === 'ASC' ? 'ASC' : 'DESC';
    $current = [];

    if (is_array($orderby)) {
        $current = $orderby;
    } elseif (is_string($orderby) && trim($orderby) !== '') {
        foreach (preg_split('/\s+/', trim($orderby)) as $key) {
            $current[$key] = $order;
        }
    } else {
        $current = ['date' => $order];
    }

    if (isset($current['menu_order'])) {
        return; // Theme already sorts by the manual order.
    }

    $query->set('orderby', array_merge(['menu_order' => 'ASC'], $current));
}

/**
 * Apply hide / order / count / similar rules.
 */
function rch_nh_filter_queries(WP_Query $query): void
{
    if ($query->get('post_type') !== RCH_NH_POST_TYPE) {
        return;
    }

    $context = rch_nh_detect_query_context($query);
    if ($context === '') {
        return;
    }

    $exclude = array_map('intval', (array) $query->get('post__not_in'));

    if ($context === 'related') {
        $current_id = (int) get_queried_object_id();
        if ($current_id <= 0 || get_post_type($current_id) !== RCH_NH_POST_TYPE) {
            $current_id = 0;
        }

        $mode = $current_id ? rch_nh_get_related_mode($current_id) : 'auto';

        if ($mode === 'none') {
            $query->set('post__in', [0]); // Nothing: the section is switched off for this page.
            return;
        }

        if ($mode === 'manual') {
            $ids = rch_nh_get_related_ids($current_id);
            $query->set('post__in', $ids);
            $query->set('post__not_in', []);
            $query->set('orderby', 'post__in');
            $query->set('posts_per_page', count($ids));
            $query->set('ignore_sticky_posts', true);
            return;
        }

        if ($current_id) {
            $exclude[] = $current_id;
        }
        $exclude = array_merge($exclude, rch_nh_get_hidden_ids('related'));
        $query->set('post__not_in', array_values(array_unique(array_filter($exclude))));
        return;
    }

    $exclude = array_merge($exclude, rch_nh_get_hidden_ids($context));
    $exclude = array_values(array_unique(array_filter($exclude)));
    if ($exclude) {
        $query->set('post__not_in', $exclude);
    }

    rch_nh_apply_manual_order($query);

    if ($context === 'archive') {
        $per_page = (int) get_option(RCH_NH_OPT_ARCHIVE_COUNT, 0);
        if ($per_page > 0) {
            $query->set('posts_per_page', $per_page);
        }
    }
}
add_action('pre_get_posts', 'rch_nh_filter_queries', 20);

/**
 * Similar section switched off for this page: mark the body and hide the standard wrapper,
 * so themes that print the section unconditionally need no edit.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function rch_nh_body_class(array $classes): array
{
    if (is_singular(RCH_NH_POST_TYPE) && ! rch_neighborhood_show_related()) {
        $classes[] = 'rch-nh-related-off';
    }

    return $classes;
}
add_filter('body_class', 'rch_nh_body_class');

function rch_nh_related_off_css(): void
{
    if (is_singular(RCH_NH_POST_TYPE) && ! rch_neighborhood_show_related()) {
        echo '<style id="rch-nh-related-off">'
            . 'body.rch-nh-related-off .related-neighbour,'
            . 'body.rch-nh-related-off .rch-related-neighborhoods{display:none !important}'
            . '</style>' . "\n";
    }
}
add_action('wp_head', 'rch_nh_related_off_css', 99);

/**
 * With a manual order in place, a new neighborhood goes to the end instead of the top
 * (new posts start at menu_order 0, which sorts before every ordered one).
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 * @param bool    $update  False on creation.
 */
function rch_nh_new_post_goes_last(int $post_id, WP_Post $post, bool $update): void
{
    if (
        $update
        || (int) $post->menu_order !== 0
        || $post->post_status === 'auto-draft'
        || get_option(RCH_NH_OPT_ORDER_ACTIVE) !== '1'
        || wp_is_post_revision($post_id)
    ) {
        return;
    }

    global $wpdb;

    $max = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = %s", RCH_NH_POST_TYPE)
    );

    $wpdb->update($wpdb->posts, ['menu_order' => $max + 1], ['ID' => $post_id], ['%d'], ['%d']);
    clean_post_cache($post_id);
}
add_action('save_post_' . RCH_NH_POST_TYPE, 'rch_nh_new_post_goes_last', 20, 3);

/* -------------------------------------------------------------------------
 * Editor: "Display" box on each neighborhood
 * ---------------------------------------------------------------------- */

function rch_nh_add_display_metabox(): void
{
    add_meta_box(
        'rch-nh-display',
        __('Display', 'rechat-plugin'),
        'rch_nh_render_display_metabox',
        RCH_NH_POST_TYPE,
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'rch_nh_add_display_metabox');

function rch_nh_render_display_metabox(WP_Post $post): void
{
    wp_nonce_field('rch_nh_display_save', 'rch_nh_display_nonce');

    $mode = (string) get_post_meta($post->ID, RCH_NH_META_RELATED_MODE, true);
    $mode = in_array($mode, ['manual', 'none'], true) ? $mode : 'auto';

    $picked = get_post_meta($post->ID, RCH_NH_META_RELATED_IDS, true);
    $picked = is_array($picked) ? array_map('intval', $picked) : [];

    $others = get_posts([
        'post_type'        => RCH_NH_POST_TYPE,
        'post_status'      => 'publish',
        'posts_per_page'   => -1,
        'orderby'          => ['menu_order' => 'ASC', 'title' => 'ASC'],
        'exclude'          => [$post->ID],
        'suppress_filters' => true,
        'rch_nh_context'   => 'none',
    ]);
    ?>
    <p style="margin-bottom:6px"><strong><?php esc_html_e('Hide this neighborhood from', 'rechat-plugin'); ?></strong></p>
    <?php foreach (rch_nh_context_labels() as $context => $label) : ?>
        <label style="display:block;margin:4px 0">
            <input type="checkbox" name="rch_nh_hide[<?php echo esc_attr($context); ?>]" value="1" <?php checked(rch_nh_is_hidden($post->ID, $context)); ?>>
            <?php
            echo esc_html(
                $context === 'related'
                    ? __('"Similar neighborhoods" on other pages', 'rechat-plugin')
                    : $label
            );
            ?>
        </label>
    <?php endforeach; ?>
    <p class="description"><?php esc_html_e('It stays published, keeps its own link and stays in the sitemap.', 'rechat-plugin'); ?></p>

    <hr style="margin:14px 0">

    <p style="margin-bottom:6px"><strong><?php esc_html_e('Similar neighborhoods on this page', 'rechat-plugin'); ?></strong></p>
    <select name="rch_nh_related_mode" id="rch-nh-related-mode" style="width:100%">
        <option value="auto" <?php selected($mode, 'auto'); ?>><?php esc_html_e('Automatic (default)', 'rechat-plugin'); ?></option>
        <option value="manual" <?php selected($mode, 'manual'); ?>><?php esc_html_e('Choose neighborhoods', 'rechat-plugin'); ?></option>
        <option value="none" <?php selected($mode, 'none'); ?>><?php esc_html_e('Hide the section', 'rechat-plugin'); ?></option>
    </select>

    <div id="rch-nh-related-picker" style="margin-top:10px;<?php echo $mode === 'manual' ? '' : 'display:none'; ?>">
        <?php if ($others) : ?>
            <input type="search" id="rch-nh-related-search" placeholder="<?php esc_attr_e('Search neighborhoods', 'rechat-plugin'); ?>" style="width:100%;margin-bottom:6px">
            <div id="rch-nh-related-list" style="max-height:220px;overflow:auto;border:1px solid #dcdcde;padding:6px 8px;background:#fff">
                <?php foreach ($others as $other) : ?>
                    <label style="display:block;margin:3px 0">
                        <input type="checkbox" name="rch_nh_related_ids[]" value="<?php echo esc_attr((string) $other->ID); ?>" <?php checked(in_array((int) $other->ID, $picked, true)); ?>>
                        <?php echo esc_html(get_the_title($other)); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="description"><?php esc_html_e('Shown in the order set under Neighborhoods → Order & Visibility. Nothing ticked = automatic.', 'rechat-plugin'); ?></p>
        <?php else : ?>
            <p class="description"><?php esc_html_e('No other published neighborhoods yet.', 'rechat-plugin'); ?></p>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        var mode = document.getElementById('rch-nh-related-mode');
        var picker = document.getElementById('rch-nh-related-picker');
        var search = document.getElementById('rch-nh-related-search');
        if (mode && picker) {
            mode.addEventListener('change', function () {
                picker.style.display = mode.value === 'manual' ? '' : 'none';
            });
        }
        if (search) {
            search.addEventListener('input', function () {
                var term = search.value.toLowerCase();
                document.querySelectorAll('#rch-nh-related-list label').forEach(function (label) {
                    label.style.display = label.textContent.toLowerCase().indexOf(term) === -1 ? 'none' : 'block';
                });
            });
            search.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); }
            });
        }
    })();
    </script>
    <?php
}

/**
 * Save the Display box.
 */
function rch_nh_save_display_metabox(int $post_id): void
{
    if (
        ! isset($_POST['rch_nh_display_nonce'])
        || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rch_nh_display_nonce'])), 'rch_nh_display_save')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || wp_is_post_revision($post_id)
        || ! current_user_can('edit_post', $post_id)
    ) {
        return;
    }

    $hide = isset($_POST['rch_nh_hide']) && is_array($_POST['rch_nh_hide']) ? wp_unslash($_POST['rch_nh_hide']) : [];
    foreach (array_keys(rch_nh_hide_meta_keys()) as $context) {
        rch_nh_set_hidden($post_id, $context, ! empty($hide[$context]));
    }

    $mode = isset($_POST['rch_nh_related_mode']) ? sanitize_key(wp_unslash($_POST['rch_nh_related_mode'])) : 'auto';
    if (in_array($mode, ['manual', 'none'], true)) {
        update_post_meta($post_id, RCH_NH_META_RELATED_MODE, $mode);
    } else {
        delete_post_meta($post_id, RCH_NH_META_RELATED_MODE);
    }

    $ids = isset($_POST['rch_nh_related_ids']) && is_array($_POST['rch_nh_related_ids'])
        ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['rch_nh_related_ids'])))))
        : [];
    $ids = array_values(array_diff($ids, [$post_id]));

    if ($ids) {
        // Keep the picks in the site's neighborhood order.
        $ordered = get_posts([
            'post_type'        => RCH_NH_POST_TYPE,
            'post_status'      => 'any',
            'posts_per_page'   => -1,
            'post__in'         => $ids,
            'orderby'          => ['menu_order' => 'ASC', 'title' => 'ASC'],
            'fields'           => 'ids',
            'suppress_filters' => true,
        'rch_nh_context'   => 'none',
        ]);
        update_post_meta($post_id, RCH_NH_META_RELATED_IDS, array_map('intval', $ordered));
    } else {
        delete_post_meta($post_id, RCH_NH_META_RELATED_IDS);
    }
}
add_action('save_post_' . RCH_NH_POST_TYPE, 'rch_nh_save_display_metabox');

/* -------------------------------------------------------------------------
 * Admin list: "Display" column
 * ---------------------------------------------------------------------- */

/**
 * @param array<string, string> $columns Columns.
 * @return array<string, string>
 */
function rch_nh_admin_columns(array $columns): array
{
    $columns['rch_nh_display'] = __('Display', 'rechat-plugin');

    return $columns;
}
add_filter('manage_' . RCH_NH_POST_TYPE . '_posts_columns', 'rch_nh_admin_columns');

function rch_nh_admin_column_content(string $column, int $post_id): void
{
    if ($column !== 'rch_nh_display') {
        return;
    }

    $hidden = [];
    foreach (rch_nh_context_labels() as $context => $label) {
        if (rch_nh_is_hidden($post_id, $context)) {
            $hidden[] = $label;
        }
    }

    echo $hidden
        ? esc_html(sprintf(/* translators: %s: list of places */ __('Hidden from: %s', 'rechat-plugin'), implode(', ', $hidden)))
        : esc_html__('Shown everywhere', 'rechat-plugin');

    $mode = (string) get_post_meta($post_id, RCH_NH_META_RELATED_MODE, true);
    if ($mode === 'none') {
        echo '<br><span class="description">' . esc_html__('Similar section: off', 'rechat-plugin') . '</span>';
    } elseif ($mode === 'manual') {
        echo '<br><span class="description">' . esc_html__('Similar section: chosen', 'rechat-plugin') . '</span>';
    }
}
add_action('manage_' . RCH_NH_POST_TYPE . '_posts_custom_column', 'rch_nh_admin_column_content', 10, 2);

/* -------------------------------------------------------------------------
 * Admin screen: Neighborhoods → Order & Visibility
 * ---------------------------------------------------------------------- */

function rch_nh_register_order_page(): void
{
    $type = get_post_type_object(RCH_NH_POST_TYPE);
    if (! $type || ! $type->show_ui) {
        return;
    }

    $hook = add_submenu_page(
        'edit.php?post_type=' . RCH_NH_POST_TYPE,
        __('Neighborhoods: Order & Visibility', 'rechat-plugin'),
        __('Order & Visibility', 'rechat-plugin'),
        $type->cap->edit_others_posts,
        'rch-nh-order',
        'rch_nh_render_order_page'
    );

    if ($hook) {
        add_action('admin_print_scripts-' . $hook, static function (): void {
            wp_enqueue_script('jquery-ui-sortable');
        });
    }
}
add_action('admin_menu', 'rch_nh_register_order_page');

function rch_nh_render_order_page(): void
{
    $type = get_post_type_object(RCH_NH_POST_TYPE);
    if (! $type || ! current_user_can($type->cap->edit_others_posts)) {
        wp_die(esc_html__('You are not allowed to manage neighborhoods.', 'rechat-plugin'));
    }

    $posts = get_posts([
        'post_type'        => RCH_NH_POST_TYPE,
        'post_status'      => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page'   => -1,
        'orderby'          => ['menu_order' => 'ASC', 'title' => 'ASC', 'ID' => 'ASC'],
        'suppress_filters' => true,
        'rch_nh_context'   => 'none',
    ]);

    $labels        = rch_nh_context_labels();
    $order_active  = get_option(RCH_NH_OPT_ORDER_ACTIVE) === '1';
    $archive_count = (int) get_option(RCH_NH_OPT_ARCHIVE_COUNT, 0);
    $default_count = (int) get_option('_rch_posts_per_page', 12);
    $notice        = isset($_GET['rch_nh_saved']) ? sanitize_key(wp_unslash($_GET['rch_nh_saved'])) : '';
    ?>
    <div class="wrap rch-nh-order">
        <h1><?php esc_html_e('Neighborhoods: Order & Visibility', 'rechat-plugin'); ?></h1>

        <?php if ($notice === '1') : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Saved.', 'rechat-plugin'); ?></p></div>
        <?php elseif ($notice === 'reset') : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Order reset. Neighborhoods use the theme\'s default order again.', 'rechat-plugin'); ?></p></div>
        <?php endif; ?>

        <p class="description" style="max-width:820px">
            <?php esc_html_e('Drag the rows to set the order used on the home page and on the Neighborhoods page. Tick a box to hide a neighborhood from that place. Hidden neighborhoods stay published, keep their own link and stay in the sitemap.', 'rechat-plugin'); ?>
        </p>
        <p class="description">
            <?php
            echo $order_active
                ? esc_html__('Order: custom (saved on this screen).', 'rechat-plugin')
                : esc_html__('Order: theme default. It becomes custom the first time you save this screen.', 'rechat-plugin');
            ?>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="rch-nh-order-form">
            <input type="hidden" name="action" value="rch_nh_save_order">
            <?php wp_nonce_field('rch_nh_save_order', 'rch_nh_order_nonce'); ?>
            <input type="hidden" name="rch_nh_order" id="rch-nh-field-order" value="">
            <?php foreach (array_keys($labels) as $context) : ?>
                <input type="hidden" name="rch_nh_hidden_<?php echo esc_attr($context); ?>" id="rch-nh-field-<?php echo esc_attr($context); ?>" value="">
            <?php endforeach; ?>

            <table class="form-table" role="presentation" style="max-width:820px">
                <tr>
                    <th scope="row"><label for="rch-nh-archive-count"><?php esc_html_e('Neighborhoods per page', 'rechat-plugin'); ?></label></th>
                    <td>
                        <input type="number" min="0" step="1" class="small-text" id="rch-nh-archive-count" name="rch_nh_archive_per_page" value="<?php echo esc_attr($archive_count > 0 ? (string) $archive_count : ''); ?>" placeholder="<?php echo esc_attr((string) $default_count); ?>">
                        <p class="description">
                            <?php esc_html_e('How many neighborhoods the Neighborhoods page shows per page. Leave empty to keep the default.', 'rechat-plugin'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php if (! $posts) : ?>
                <p><?php esc_html_e('No neighborhoods yet.', 'rechat-plugin'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat striped" style="max-width:980px">
                    <thead>
                        <tr>
                            <th style="width:34px"></th>
                            <th style="width:44px">#</th>
                            <th><?php esc_html_e('Neighborhood', 'rechat-plugin'); ?></th>
                            <?php foreach ($labels as $context => $label) : ?>
                                <th style="width:150px">
                                    <?php
                                    /* translators: %s: place name */
                                    echo esc_html(sprintf(__('Hide: %s', 'rechat-plugin'), $label));
                                    ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody id="rch-nh-order-rows">
                        <?php foreach ($posts as $index => $item) : ?>
                            <tr data-id="<?php echo esc_attr((string) $item->ID); ?>">
                                <td class="rch-nh-handle" title="<?php esc_attr_e('Drag to reorder', 'rechat-plugin'); ?>" style="cursor:move"><span class="dashicons dashicons-menu"></span></td>
                                <td class="rch-nh-position"><?php echo esc_html((string) ($index + 1)); ?></td>
                                <td>
                                    <strong><a href="<?php echo esc_url((string) get_edit_post_link($item->ID)); ?>"><?php echo esc_html(get_the_title($item) !== '' ? get_the_title($item) : __('(no title)', 'rechat-plugin')); ?></a></strong>
                                    <?php if ($item->post_status !== 'publish') : ?>
                                        <span class="description">&mdash; <?php echo esc_html((string) get_post_status_object($item->post_status)->label); ?></span>
                                    <?php endif; ?>
                                    <span class="rch-nh-move" style="float:right">
                                        <button type="button" class="button-link rch-nh-up" aria-label="<?php esc_attr_e('Move up', 'rechat-plugin'); ?>"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
                                        <button type="button" class="button-link rch-nh-down" aria-label="<?php esc_attr_e('Move down', 'rechat-plugin'); ?>"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
                                    </span>
                                </td>
                                <?php foreach (array_keys($labels) as $context) : ?>
                                    <td>
                                        <input type="checkbox" class="rch-nh-hide" data-context="<?php echo esc_attr($context); ?>" <?php checked(rch_nh_is_hidden((int) $item->ID, $context)); ?> aria-label="<?php echo esc_attr($labels[$context]); ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p class="submit">
                <?php submit_button(__('Save order & visibility', 'rechat-plugin'), 'primary', 'submit', false); ?>
                <?php if ($order_active) : ?>
                    <button type="submit" name="rch_nh_reset_order" value="1" class="button" style="margin-left:8px" onclick="return confirm('<?php echo esc_js(__('Reset the custom order? Neighborhoods go back to the theme\'s default order. Visibility settings are kept.', 'rechat-plugin')); ?>');">
                        <?php esc_html_e('Reset order', 'rechat-plugin'); ?>
                    </button>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <script>
    jQuery(function ($) {
        var $rows = $('#rch-nh-order-rows');

        function renumber() {
            $rows.children('tr').each(function (i) {
                $(this).find('.rch-nh-position').text(i + 1);
            });
        }

        if ($rows.length && $.fn.sortable) {
            $rows.sortable({
                handle: '.rch-nh-handle',
                axis: 'y',
                helper: function (e, tr) {
                    var $cells = tr.children();
                    var $clone = tr.clone();
                    $clone.children().each(function (i) { $(this).width($cells.eq(i).width()); });
                    return $clone;
                },
                update: renumber
            });
        }

        $rows.on('click', '.rch-nh-up', function () {
            var $tr = $(this).closest('tr');
            $tr.prev().before($tr);
            renumber();
        });
        $rows.on('click', '.rch-nh-down', function () {
            var $tr = $(this).closest('tr');
            $tr.next().after($tr);
            renumber();
        });

        $('#rch-nh-order-form').on('submit', function () {
            var order = [], hidden = { home: [], archive: [], related: [] };
            $rows.children('tr').each(function () {
                var id = $(this).data('id');
                order.push(id);
                $(this).find('.rch-nh-hide:checked').each(function () {
                    hidden[$(this).data('context')].push(id);
                });
            });
            $('#rch-nh-field-order').val(order.join(','));
            $.each(hidden, function (context, ids) {
                $('#rch-nh-field-' + context).val(ids.join(','));
            });
        });
    });
    </script>
    <?php
}

/**
 * Save the Order & Visibility screen.
 */
function rch_nh_handle_save_order(): void
{
    $type = get_post_type_object(RCH_NH_POST_TYPE);
    if (! $type || ! current_user_can($type->cap->edit_others_posts)) {
        wp_die(esc_html__('You are not allowed to manage neighborhoods.', 'rechat-plugin'));
    }
    check_admin_referer('rch_nh_save_order', 'rch_nh_order_nonce');

    global $wpdb;

    $redirect = admin_url('edit.php?post_type=' . RCH_NH_POST_TYPE . '&page=rch-nh-order');

    $parse_ids = static function (string $field): array {
        $raw = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '';

        return array_values(array_unique(array_filter(array_map('absint', explode(',', $raw)))));
    };

    $valid_ids = array_map(
        'intval',
        $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", RCH_NH_POST_TYPE))
    );

    // Archive count + visibility are saved on both buttons.
    $per_page = isset($_POST['rch_nh_archive_per_page']) ? absint(wp_unslash($_POST['rch_nh_archive_per_page'])) : 0;
    if ($per_page > 0) {
        update_option(RCH_NH_OPT_ARCHIVE_COUNT, min($per_page, 500));
    } else {
        delete_option(RCH_NH_OPT_ARCHIVE_COUNT);
    }

    $order = array_values(array_intersect($parse_ids('rch_nh_order'), $valid_ids));

    if ($order) { // Empty = the page was submitted without its script; change nothing.
        foreach (array_keys(rch_nh_hide_meta_keys()) as $context) {
            $hide = array_intersect($parse_ids('rch_nh_hidden_' . $context), $valid_ids);
            foreach ($order as $id) {
                $want = in_array($id, $hide, true);
                if ($want !== rch_nh_is_hidden($id, $context)) {
                    rch_nh_set_hidden($id, $context, $want);
                }
            }
        }
    }
    delete_option(RCH_NH_OPT_HIDDEN_CACHE);

    if (! empty($_POST['rch_nh_reset_order'])) {
        $wpdb->update($wpdb->posts, ['menu_order' => 0], ['post_type' => RCH_NH_POST_TYPE], ['%d'], ['%s']);
        delete_option(RCH_NH_OPT_ORDER_ACTIVE);
        foreach ($valid_ids as $id) {
            clean_post_cache($id);
        }
        wp_safe_redirect(add_query_arg('rch_nh_saved', 'reset', $redirect));
        exit;
    }

    if ($order) {
        $position = 0;
        foreach ($order as $id) {
            $position++;
            $wpdb->update($wpdb->posts, ['menu_order' => $position], ['ID' => $id], ['%d'], ['%d']);
            clean_post_cache($id);
        }
        update_option(RCH_NH_OPT_ORDER_ACTIVE, '1', true);
    }

    wp_safe_redirect(add_query_arg('rch_nh_saved', '1', $redirect));
    exit;
}
add_action('admin_post_rch_nh_save_order', 'rch_nh_handle_save_order');
