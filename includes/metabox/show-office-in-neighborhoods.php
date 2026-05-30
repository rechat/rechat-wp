<?php
/**
 * Neighborhood–Office association metabox.
 *
 * @package RechatPlugin
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Post meta key: assigned office post ID (int), or empty if none. */
if (! defined('RCH_NEIGHBORHOOD_OFFICE_META_KEY')) {
    define('RCH_NEIGHBORHOOD_OFFICE_META_KEY', '_rch_neighborhood_office');
}

/**
 * @param int $neighborhood_id Neighborhood post ID.
 * @return int Office post ID, or 0 if none.
 */
function rch_get_neighborhood_office_id(int $neighborhood_id): int
{
    $office_id = get_post_meta($neighborhood_id, RCH_NEIGHBORHOOD_OFFICE_META_KEY, true);

    return $office_id !== '' && $office_id !== false ? absint($office_id) : 0;
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
    add_meta_box(
        'rch_neighborhood_office_meta_box',
        __('Assigned Office', 'rechat-plugin'),
        'rch_neighborhood_office_meta_box_callback',
        'neighborhoods',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'rch_add_neighborhood_office_meta_box');

function rch_neighborhood_office_meta_box_callback(WP_Post $post): void
{
    wp_nonce_field('rch_save_neighborhood_office', 'rch_neighborhood_office_nonce');

    $selected_office = rch_get_neighborhood_office_id((int) $post->ID);
    $offices         = function_exists('rch_get_all_offices') ? rch_get_all_offices() : get_posts([
        'post_type'      => 'offices',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
    ?>
    <p>
        <label for="rch_neighborhood_office" class="screen-reader-text">
            <?php esc_html_e('Assigned Office', 'rechat-plugin'); ?>
        </label>
        <select name="rch_neighborhood_office" id="rch_neighborhood_office" class="widefat">
            <option value=""><?php esc_html_e('— None —', 'rechat-plugin'); ?></option>
            <?php foreach ($offices as $office) : ?>
                <option value="<?php echo esc_attr((string) $office->ID); ?>" <?php selected($selected_office, (int) $office->ID); ?>>
                    <?php echo esc_html($office->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p class="description">
        <?php esc_html_e('Link this neighborhood to an office for use in office pages and theme templates.', 'rechat-plugin'); ?>
    </p>
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

    if (! isset($_POST['rch_neighborhood_office']) || $_POST['rch_neighborhood_office'] === '') {
        delete_post_meta($post_id, RCH_NEIGHBORHOOD_OFFICE_META_KEY);
        return;
    }

    $office_id = absint($_POST['rch_neighborhood_office']);

    if ($office_id <= 0 || get_post_type($office_id) !== 'offices') {
        delete_post_meta($post_id, RCH_NEIGHBORHOOD_OFFICE_META_KEY);
        return;
    }

    update_post_meta($post_id, RCH_NEIGHBORHOOD_OFFICE_META_KEY, $office_id);
}
add_action('save_post_neighborhoods', 'rch_save_neighborhood_office_meta');

function rch_add_neighborhood_office_admin_column(array $columns): array
{
    $new_columns = [];

    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;

        if ($key === 'title') {
            $new_columns['rch_neighborhood_office'] = __('Office', 'rechat-plugin');
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

    $office_id = rch_get_neighborhood_office_id($post_id);

    if ($office_id <= 0) {
        echo '<em>' . esc_html__('None', 'rechat-plugin') . '</em>';
        return;
    }

    $title = get_the_title($office_id);

    if ($title === '') {
        echo '<em>' . esc_html__('None', 'rechat-plugin') . '</em>';
        return;
    }

    echo esc_html($title);
}
add_action('manage_neighborhoods_posts_custom_column', 'rch_show_neighborhood_office_admin_column', 10, 2);
