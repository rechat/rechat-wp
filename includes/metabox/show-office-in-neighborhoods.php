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
