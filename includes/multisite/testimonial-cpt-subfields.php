<?php
/**
 * Sub-site testimonial CPT: stars + link meta (synced from main-site agent testimonials).
 *
 * @package RechatPlugin
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('RCH_TESTIMONIAL_STARS_META')) {
    define('RCH_TESTIMONIAL_STARS_META', 'testimonial_stars');
}
if (! defined('RCH_TESTIMONIAL_LINK_META')) {
    define('RCH_TESTIMONIAL_LINK_META', 'testimonial_link');
}

/**
 * @return string Star rating for a testimonial post (empty if not set).
 */
function rch_get_testimonial_stars(int $post_id): string
{
    if ($post_id <= 0) {
        return '';
    }

    $stars = get_post_meta($post_id, RCH_TESTIMONIAL_STARS_META, true);
    if ($stars === '' || $stars === false) {
        $stars = get_post_meta($post_id, 'stars', true);
    }

    return is_scalar($stars) ? trim((string) $stars) : '';
}

/**
 * @return string Testimonial link URL (empty if not set).
 */
function rch_get_testimonial_link(int $post_id): string
{
    if ($post_id <= 0) {
        return '';
    }

    $link = get_post_meta($post_id, RCH_TESTIMONIAL_LINK_META, true);
    if ($link === '' || $link === false) {
        $link = get_post_meta($post_id, 'link', true);
    }

    return is_scalar($link) ? esc_url_raw(trim((string) $link)) : '';
}

function rch_register_testimonial_cpt_meta(): void
{
    if (! post_type_exists('testimonial')) {
        return;
    }

    register_post_meta(
        'testimonial',
        RCH_TESTIMONIAL_STARS_META,
        [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => static function ($value) {
                return function_exists('rch_sanitize_agent_testimonial_stars')
                    ? rch_sanitize_agent_testimonial_stars($value)
                    : trim((string) $value);
            },
            'auth_callback'     => static function (): bool {
                return current_user_can('edit_posts');
            },
        ]
    );

    register_post_meta(
        'testimonial',
        RCH_TESTIMONIAL_LINK_META,
        [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => static function ($value) {
                return function_exists('rch_sanitize_agent_testimonial_link')
                    ? rch_sanitize_agent_testimonial_link($value)
                    : esc_url_raw(trim((string) $value));
            },
            'auth_callback'     => static function (): bool {
                return current_user_can('edit_posts');
            },
        ]
    );
}
add_action('init', 'rch_register_testimonial_cpt_meta', 20);

function rch_add_testimonial_cpt_meta_box(): void
{
    if (! post_type_exists('testimonial')) {
        return;
    }

    add_meta_box(
        'rch_testimonial_subfields',
        __('Rating & link', 'rechat-plugin'),
        'rch_render_testimonial_cpt_meta_box',
        'testimonial',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'rch_add_testimonial_cpt_meta_box');

function rch_render_testimonial_cpt_meta_box(WP_Post $post): void
{
    wp_nonce_field('rch_save_testimonial_subfields', 'rch_testimonial_subfields_nonce');

    $stars = rch_get_testimonial_stars((int) $post->ID);
    $link  = rch_get_testimonial_link((int) $post->ID);
    ?>
    <p>
        <label for="rch_testimonial_stars_field"><strong><?php esc_html_e('Stars', 'rechat-plugin'); ?></strong></label>
        <input
            type="text"
            class="widefat"
            id="rch_testimonial_stars_field"
            name="rch_testimonial_stars_field"
            value="<?php echo esc_attr($stars); ?>"
            placeholder="<?php esc_attr_e('e.g. 4.8', 'rechat-plugin'); ?>"
        />
    </p>
    <p>
        <label for="rch_testimonial_link_field"><strong><?php esc_html_e('Link', 'rechat-plugin'); ?></strong></label>
        <input
            type="url"
            class="widefat"
            id="rch_testimonial_link_field"
            name="rch_testimonial_link_field"
            value="<?php echo esc_attr($link); ?>"
            placeholder="<?php esc_attr_e('https://…', 'rechat-plugin'); ?>"
        />
    </p>
    <p class="description">
        <?php esc_html_e('Usually filled by “Import testimonials to sub-site(s)” from the main-site agent. You can edit here after sync.', 'rechat-plugin'); ?>
    </p>
    <?php
}

function rch_save_testimonial_cpt_meta_box(int $post_id): void
{
    if (! isset($_POST['rch_testimonial_subfields_nonce'])) {
        return;
    }

    if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rch_testimonial_subfields_nonce'])), 'rch_save_testimonial_subfields')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'testimonial') {
        return;
    }

    $stars = isset($_POST['rch_testimonial_stars_field'])
        ? (function_exists('rch_sanitize_agent_testimonial_stars')
            ? rch_sanitize_agent_testimonial_stars(wp_unslash($_POST['rch_testimonial_stars_field']))
            : trim(sanitize_text_field(wp_unslash($_POST['rch_testimonial_stars_field']))))
        : '';

    $link = isset($_POST['rch_testimonial_link_field'])
        ? (function_exists('rch_sanitize_agent_testimonial_link')
            ? rch_sanitize_agent_testimonial_link(wp_unslash($_POST['rch_testimonial_link_field']))
            : esc_url_raw(trim(sanitize_text_field(wp_unslash($_POST['rch_testimonial_link_field'])))))
        : '';

    if (function_exists('rch_agent_testimonial_sync_save_row_meta')) {
        rch_agent_testimonial_sync_save_row_meta($post_id, $stars, $link);
        return;
    }

    if ($stars !== '') {
        update_post_meta($post_id, RCH_TESTIMONIAL_STARS_META, $stars);
    } else {
        delete_post_meta($post_id, RCH_TESTIMONIAL_STARS_META);
    }

    if ($link !== '') {
        update_post_meta($post_id, RCH_TESTIMONIAL_LINK_META, $link);
    } else {
        delete_post_meta($post_id, RCH_TESTIMONIAL_LINK_META);
    }
}
add_action('save_post_testimonial', 'rch_save_testimonial_cpt_meta_box');
