<?php
/**
 * Neighborhood–Office association metabox.
 *
 * @package RechatPlugin
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Post meta key: assigned office post IDs (int[]), or empty if none. */
if (! defined('RCH_NEIGHBORHOOD_OFFICE_META_KEY')) {
    define('RCH_NEIGHBORHOOD_OFFICE_META_KEY', '_rch_neighborhood_office');
}

/**
 * Normalize stored neighborhood office meta (legacy single int or array of IDs).
 *
 * @param mixed $raw Meta value from get_post_meta.
 * @return int[]
 */
function rch_normalize_neighborhood_office_ids($raw): array
{
    if ($raw === '' || $raw === false || $raw === null) {
        return [];
    }

    if (is_numeric($raw)) {
        $id = absint($raw);

        return $id > 0 ? [$id] : [];
    }

    if (is_string($raw) && is_numeric(trim($raw))) {
        $id = absint($raw);

        return $id > 0 ? [$id] : [];
    }

    if (! is_array($raw)) {
        return [];
    }

    $ids = array_map('absint', $raw);
    $ids = array_values(array_unique(array_filter($ids)));

    return $ids;
}

/**
 * @param int $neighborhood_id Neighborhood post ID.
 * @return int[] Office post IDs.
 */
function rch_get_neighborhood_office_ids(int $neighborhood_id): array
{
    $raw = get_post_meta($neighborhood_id, RCH_NEIGHBORHOOD_OFFICE_META_KEY, true);

    return rch_normalize_neighborhood_office_ids($raw);
}

/**
 * First assigned office (backward compatibility).
 *
 * @param int $neighborhood_id Neighborhood post ID.
 * @return int Office post ID, or 0 if none.
 */
function rch_get_neighborhood_office_id(int $neighborhood_id): int
{
    $ids = rch_get_neighborhood_office_ids($neighborhood_id);

    return $ids !== [] ? (int) $ids[0] : 0;
}

/**
 * Whether neighborhood office assignment UI runs on this blog (main site only).
 */
function rch_neighborhood_office_metabox_is_main_site(): bool
{
    if (! is_multisite()) {
        return true;
    }

    return get_current_blog_id() === (int) get_main_site_id();
}

/**
 * Query neighborhoods assigned to an office.
 *
 * @param int                  $office_id Office post ID.
 * @param array<string, mixed> $args      Optional WP_Query overrides.
 * @return \WP_Query
 */
function rch_query_neighborhoods_by_office(int $office_id, array $args = []): WP_Query
{
    $office_id = absint($office_id);

    $defaults = [
        'post_type'      => 'neighborhoods',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ];

    if ($office_id > 0) {
        $defaults['meta_query'] = [
            'relation' => 'OR',
            [
                'key'     => RCH_NEIGHBORHOOD_OFFICE_META_KEY,
                'value'   => sprintf('i:%d;', $office_id),
                'compare' => 'LIKE',
            ],
            [
                'key'     => RCH_NEIGHBORHOOD_OFFICE_META_KEY,
                'value'   => $office_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ];
    } else {
        $defaults['post__in'] = [0];
    }

    return new WP_Query(array_merge($defaults, $args));
}

/**
 * Main-site office post ID linked to the current multisite blog (_rch_office_site_id).
 *
 * @return int Office post ID on the main site, or 0.
 */
function rch_multisite_resolve_office_post_id_for_current_blog(): int
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
    $main_id = (int) get_main_site_id();

    if ($blog_id <= 0 || $blog_id === $main_id) {
        return $cached;
    }

    switch_to_blog($main_id);

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

    if ($office_match->have_posts()) {
        $cached = (int) $office_match->posts[0];
    }

    restore_current_blog();

    return $cached;
}

/**
 * Permalink for a post on a specific network site.
 *
 * @param int $blog_id  Target blog ID.
 * @param int $post_id  Post ID on that blog.
 * @return string
 */
function rch_multisite_get_post_permalink(int $blog_id, int $post_id): string
{
    $blog_id  = absint($blog_id);
    $post_id  = absint($post_id);

    if ($blog_id <= 0 || $post_id <= 0) {
        return '';
    }

    switch_to_blog($blog_id);
    $url = get_permalink($post_id);
    restore_current_blog();

    return is_string($url) ? $url : '';
}

/**
 * Archive URL for a post type on the main network site.
 *
 * @param string $post_type Post type slug.
 * @return string
 */
function rch_get_main_site_post_type_archive_link(string $post_type): string
{
    if (! is_multisite()) {
        $link = get_post_type_archive_link($post_type);

        return is_string($link) ? $link : '';
    }

    $main_id = (int) get_main_site_id();

    switch_to_blog($main_id);
    $link = get_post_type_archive_link($post_type);
    restore_current_blog();

    return is_string($link) ? $link : '';
}

/**
 * Neighborhood list items from the main site for one office (permalink/thumbnail safe across blogs).
 *
 * @param int                  $office_id Main-site office post ID.
 * @param array<string, mixed> $args      WP_Query overrides.
 * @return array<int, array{id:int, title:string, permalink:string, thumbnail:string}>
 */
function rch_get_neighborhood_cards_for_office(int $office_id, array $args = []): array
{
    $office_id = absint($office_id);

    if ($office_id <= 0) {
        return [];
    }

    $main_id = is_multisite() ? (int) get_main_site_id() : get_current_blog_id();

    switch_to_blog($main_id);

    $query = rch_query_neighborhoods_by_office($office_id, $args);
    $cards = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $cards[] = [
                'id'         => $post_id,
                'title'      => get_the_title(),
                'permalink'  => get_permalink($post_id),
                'thumbnail'  => get_the_post_thumbnail($post_id, 'full'),
            ];
        }
        wp_reset_postdata();
    }

    restore_current_blog();

    return $cards;
}

/**
 * Neighborhood cards for the office linked to the current subsite.
 *
 * @param array<string, mixed> $args WP_Query overrides.
 * @return array<int, array{id:int, title:string, permalink:string, thumbnail:string}>
 */
function rch_get_neighborhood_cards_for_current_office_subsite(array $args = []): array
{
    $office_id = rch_multisite_resolve_office_post_id_for_current_blog();

    return rch_get_neighborhood_cards_for_office($office_id, $args);
}

/**
 * WP_Query for agents on the main site assigned to one office.
 *
 * @param int                  $office_id Main-site office post ID.
 * @param array<string, mixed> $args      WP_Query overrides.
 * @return \WP_Query
 */
function rch_query_agents_by_office(int $office_id, array $args = []): WP_Query
{
    $office_id = absint($office_id);

    if ($office_id <= 0) {
        return new WP_Query(['post__in' => [0]]);
    }

    $query_args = array_merge(
        [
            'post_type'      => 'agents',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
        ],
        $args
    );

    $query_args['meta_query'] = [
        'relation' => 'AND',
        [
            'key'     => '_rch_agent_offices',
            'value'   => sprintf('i:%d;', $office_id),
            'compare' => 'LIKE',
        ],
        [
            'relation' => 'OR',
            [
                'key'     => 'agent_visibility',
                'value'   => 'show',
                'compare' => '=',
            ],
            [
                'key'     => 'agent_visibility',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ];

    $use_display_order = ! isset($args['orderby']) || $args['orderby'] === 'display_order';

    if ($use_display_order) {
        unset($query_args['orderby'], $query_args['order']);

        if (function_exists('rch_wp_query_agents_display_order')) {
            return rch_wp_query_agents_display_order($query_args, 'ASC');
        }
    }

    return new WP_Query($query_args);
}

/**
 * Agent list items from the main site for one office (permalink/meta safe across blogs).
 *
 * @param int                  $office_id Main-site office post ID.
 * @param array<string, mixed> $args      WP_Query overrides.
 * @return array<int, array{id:int, title:string, permalink:string, profile_image_url:string}>
 */
function rch_get_agent_cards_for_office(int $office_id, array $args = []): array
{
    $office_id = absint($office_id);

    if ($office_id <= 0) {
        return [];
    }

    $main_id = is_multisite() ? (int) get_main_site_id() : get_current_blog_id();

    switch_to_blog($main_id);

    $query = rch_query_agents_by_office($office_id, $args);
    $cards = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id           = get_the_ID();
            $profile_image_url = (string) get_post_meta($post_id, 'profile_image_url', true);

            if ($profile_image_url === '') {
                continue;
            }

            $cards[] = [
                'id'                => $post_id,
                'title'             => get_the_title(),
                'permalink'         => get_permalink($post_id),
                'profile_image_url' => $profile_image_url,
            ];
        }
        wp_reset_postdata();
    }

    restore_current_blog();

    return $cards;
}

function rch_add_neighborhood_office_meta_box(): void
{
    if (! rch_neighborhood_office_metabox_is_main_site()) {
        return;
    }

    add_meta_box(
        'rch_neighborhood_office_meta_box',
        __('Assigned Offices', 'rechat-plugin'),
        'rch_neighborhood_office_meta_box_callback',
        'neighborhoods',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'rch_add_neighborhood_office_meta_box');

/**
 * Inline styles for neighborhood office checkboxes (main site).
 */
function rch_neighborhood_office_metabox_styles(): void
{
    ?>
    <style>
        #rch_neighborhood_office_meta_box .rch-neighborhood-office-search {
            box-sizing: border-box;
            width: 100%;
            padding: 5px 8px;
            margin-bottom: 10px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-size: 13px;
        }
        #rch_neighborhood_office_meta_box .rch-neighborhood-office-results {
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid #dcdcde;
            padding: 8px;
            background: #fff;
        }
        #rch_neighborhood_office_meta_box .rch-neighborhood-office-results label {
            display: block;
            padding: 4px 0;
            margin: 0;
            cursor: pointer;
            font-size: 13px;
        }
        #rch_neighborhood_office_meta_box .rch-neighborhood-office-results input[type="checkbox"] {
            margin-right: 5px;
        }
    </style>
    <?php
}

function rch_neighborhood_office_meta_box_callback(WP_Post $post): void
{
    wp_nonce_field('rch_save_neighborhood_office', 'rch_neighborhood_office_nonce');

    $selected_offices = rch_get_neighborhood_office_ids((int) $post->ID);
    $offices          = function_exists('rch_get_all_offices') ? rch_get_all_offices() : get_posts([
        'post_type'      => 'offices',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    rch_neighborhood_office_metabox_styles();
    ?>
    <p>
        <label for="rch-neighborhood-office-search" class="screen-reader-text">
            <?php esc_html_e('Search offices', 'rechat-plugin'); ?>
        </label>
        <input
            type="text"
            id="rch-neighborhood-office-search"
            class="rch-neighborhood-office-search"
            placeholder="<?php esc_attr_e('Search offices…', 'rechat-plugin'); ?>"
        />
    </p>
    <div id="rch-neighborhood-office-results" class="rch-neighborhood-office-results">
        <?php if ($offices !== []) : ?>
            <?php foreach ($offices as $office) : ?>
                <label class="rch-neighborhood-office-option">
                    <input
                        type="checkbox"
                        name="rch_neighborhood_offices[]"
                        value="<?php echo esc_attr((string) $office->ID); ?>"
                        <?php checked(in_array((int) $office->ID, $selected_offices, true)); ?>
                    />
                    <?php echo esc_html($office->post_title); ?>
                </label>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="description"><?php esc_html_e('No offices available.', 'rechat-plugin'); ?></p>
        <?php endif; ?>
    </div>
    <p class="description">
        <?php esc_html_e('Select one or more offices. This neighborhood appears on each assigned office site and templates.', 'rechat-plugin'); ?>
    </p>
    <script>
    (function () {
        var search = document.getElementById('rch-neighborhood-office-search');
        var box = document.getElementById('rch-neighborhood-office-results');
        if (!search || !box) {
            return;
        }
        search.addEventListener('input', function () {
            var q = search.value.toLowerCase();
            box.querySelectorAll('.rch-neighborhood-office-option').forEach(function (label) {
                var text = (label.textContent || '').toLowerCase();
                label.style.display = text.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    })();
    </script>
    <?php
}

function rch_save_neighborhood_office_meta(int $post_id): void
{
    if (! isset($_POST['rch_neighborhood_office_nonce'])) {
        return;
    }

    if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rch_neighborhood_office_nonce'])), 'rch_save_neighborhood_office')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'neighborhoods') {
        return;
    }

    if (! rch_neighborhood_office_metabox_is_main_site()) {
        return;
    }

    $previous_office_ids = rch_get_neighborhood_office_ids($post_id);

    $raw_ids = isset($_POST['rch_neighborhood_offices']) ? wp_unslash($_POST['rch_neighborhood_offices']) : [];
    if (! is_array($raw_ids)) {
        $raw_ids = [];
    }

    $office_ids = [];

    foreach ($raw_ids as $raw_id) {
        $office_id = absint($raw_id);
        if ($office_id <= 0 || get_post_type($office_id) !== 'offices') {
            continue;
        }
        $office_ids[] = $office_id;
    }

    $office_ids = array_values(array_unique($office_ids));

    if ($office_ids === []) {
        delete_post_meta($post_id, RCH_NEIGHBORHOOD_OFFICE_META_KEY);
    } else {
        update_post_meta($post_id, RCH_NEIGHBORHOOD_OFFICE_META_KEY, $office_ids);
    }

    if (function_exists('rch_schedule_agents_for_neighborhood_office_change')) {
        rch_schedule_agents_for_neighborhood_office_change($previous_office_ids, $office_ids);
    }
}
add_action('save_post_neighborhoods', 'rch_save_neighborhood_office_meta');

function rch_add_neighborhood_office_admin_column(array $columns): array
{
    if (! rch_neighborhood_office_metabox_is_main_site()) {
        return $columns;
    }

    $new_columns = [];

    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;

        if ($key === 'title') {
            $new_columns['rch_neighborhood_office'] = __('Offices', 'rechat-plugin');
        }
    }

    return $new_columns;
}
add_filter('manage_neighborhoods_posts_columns', 'rch_add_neighborhood_office_admin_column');

function rch_show_neighborhood_office_admin_column(string $column, int $post_id): void
{
    if ($column !== 'rch_neighborhood_office') {
        return;
    }

    $office_ids = rch_get_neighborhood_office_ids($post_id);

    if ($office_ids === []) {
        echo '<em>' . esc_html__('None', 'rechat-plugin') . '</em>';
        return;
    }

    $titles = [];

    foreach ($office_ids as $office_id) {
        $title = get_the_title($office_id);
        if ($title !== '') {
            $titles[] = $title;
        }
    }

    if ($titles === []) {
        echo '<em>' . esc_html__('None', 'rechat-plugin') . '</em>';
        return;
    }

    echo esc_html(implode(', ', $titles));
}
add_action('manage_neighborhoods_posts_custom_column', 'rch_show_neighborhood_office_admin_column', 10, 2);
