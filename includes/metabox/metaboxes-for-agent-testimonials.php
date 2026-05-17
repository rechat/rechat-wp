<?php
/**
 * Agent testimonials repeater metabox.
 *
 * @package RechatPlugin
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Post meta key: array of { name, description }. */
const RCH_AGENT_TESTIMONIALS_META_KEY = 'agent_testimonials';

/**
 * Blog ID where agent posts (and agent_testimonials meta) are stored.
 */
function rch_agent_testimonials_storage_blog_id(): int
{
    if (! is_multisite()) {
        return (int) get_current_blog_id();
    }

    return (int) get_main_site_id();
}

/**
 * Normalize DB value (array, JSON string, or serialized PHP) to a list.
 *
 * @param mixed $raw
 * @return array<int, mixed>
 */
function rch_agent_testimonials_normalize_stored_meta($raw): array
{
    if (is_array($raw)) {
        return $raw;
    }

    if (! is_string($raw) || $raw === '') {
        return [];
    }

    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }

    $un = maybe_unserialize($raw);

    return is_array($un) ? $un : [];
}

/**
 * @return array<int, array{name:string, description:string}>
 */
function rch_get_agent_testimonials(int $agent_id): array
{
    if ($agent_id <= 0) {
        return [];
    }

    $storage_blog = rch_agent_testimonials_storage_blog_id();
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

        return [];
    }

    $raw = get_post_meta($agent_id, RCH_AGENT_TESTIMONIALS_META_KEY, true);
    if ($raw === '' || $raw === false) {
        $raw = get_post_meta($agent_id, 'rch_agent_testimonials', true);
    }

    $rows = rch_sanitize_agent_testimonials(rch_agent_testimonials_normalize_stored_meta($raw));

    if ($switched) {
        restore_current_blog();
    }

    return $rows;
}

/**
 * @param mixed $input
 * @return array<int, array{name:string, description:string}>
 */
function rch_sanitize_agent_testimonials($input): array
{
    $input = rch_agent_testimonials_normalize_stored_meta($input);
    if ($input === []) {
        return [];
    }

    $out = [];
    foreach ($input as $row) {
        if (! is_array($row)) {
            continue;
        }

        $name = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
        if ($name === '' && isset($row['testimonial_name'])) {
            $name = sanitize_text_field((string) $row['testimonial_name']);
        }

        $description = isset($row['description']) ? sanitize_textarea_field((string) $row['description']) : '';
        if ($description === '' && isset($row['testimonial_description'])) {
            $description = sanitize_textarea_field((string) $row['testimonial_description']);
        }

        if ($name === '' && $description === '') {
            continue;
        }

        $out[] = [
            'name'        => $name,
            'description' => $description,
        ];

        if (count($out) >= 50) {
            break;
        }
    }

    return array_values($out);
}

function rch_add_agent_testimonials_meta_box(): void
{
    add_meta_box(
        'rch_agent_testimonials_meta_box',
        __('Testimonials', 'rechat-plugin'),
        'rch_agent_testimonials_meta_box_html',
        'agents',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'rch_add_agent_testimonials_meta_box');

function rch_agent_testimonials_meta_box_html(WP_Post $post): void
{
    wp_nonce_field('rch_save_agent_testimonials', 'rch_agent_testimonials_nonce');

    $testimonials = rch_get_agent_testimonials((int) $post->ID);

    rch_agent_testimonials_metabox_styles();
    ?>
    <div id="rch-agent-testimonials-root">
        <p class="description">
            <?php esc_html_e('Add one or more testimonials for this agent. Each row needs a name and testimonial text.', 'rechat-plugin'); ?>
        </p>
        <div id="rch-agent-testimonials-list">
            <?php
            if ($testimonials === []) {
                rch_render_agent_testimonial_row(0, ['name' => '', 'description' => '']);
            } else {
                foreach ($testimonials as $i => $item) {
                    rch_render_agent_testimonial_row((int) $i, $item);
                }
            }
            ?>
        </div>
        <p>
            <button type="button" class="button" id="rch-add-agent-testimonial">
                <?php esc_html_e('Add testimonial', 'rechat-plugin'); ?>
            </button>
        </p>
    </div>

    <script type="text/html" id="rch-agent-testimonial-row-template">
        <?php rch_render_agent_testimonial_row('__INDEX__', ['name' => '', 'description' => '']); ?>
    </script>
    <?php
}

/**
 * @param int|string $index
 * @param array{name?:string, description?:string} $item
 */
function rch_render_agent_testimonial_row($index, array $item): void
{
    $name        = isset($item['name']) ? (string) $item['name'] : '';
    $description = isset($item['description']) ? (string) $item['description'] : '';
    $idx         = is_numeric($index) ? (int) $index : (string) $index;
    ?>
    <div class="rch-testimonial-row" data-index="<?php echo esc_attr((string) $idx); ?>">
        <div class="rch-testimonial-row__head">
            <strong class="rch-testimonial-row__title"><?php esc_html_e('Testimonial', 'rechat-plugin'); ?></strong>
            <button type="button" class="button-link-delete rch-remove-agent-testimonial" aria-label="<?php esc_attr_e('Remove testimonial', 'rechat-plugin'); ?>">
                <?php esc_html_e('Remove', 'rechat-plugin'); ?>
            </button>
        </div>
        <p>
            <label>
                <?php esc_html_e('Name', 'rechat-plugin'); ?>
                <input
                    type="text"
                    class="widefat"
                    name="rch_agent_testimonials[<?php echo esc_attr((string) $idx); ?>][name]"
                    value="<?php echo esc_attr($name); ?>"
                    placeholder="<?php esc_attr_e('Client or author name', 'rechat-plugin'); ?>"
                />
            </label>
        </p>
        <p>
            <label>
                <?php esc_html_e('Description', 'rechat-plugin'); ?>
                <textarea
                    class="widefat"
                    rows="4"
                    name="rch_agent_testimonials[<?php echo esc_attr((string) $idx); ?>][description]"
                    placeholder="<?php esc_attr_e('Testimonial text', 'rechat-plugin'); ?>"
                ><?php echo esc_textarea($description); ?></textarea>
            </label>
        </p>
    </div>
    <?php
}

function rch_agent_testimonials_metabox_styles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <style>
        #rch_agent_testimonials_meta_box #rch-agent-testimonials-list {
            margin: 0;
        }
        #rch_agent_testimonials_meta_box .rch-testimonial-row {
            margin: 0 0 12px;
            padding: 12px 14px;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            background: #fff;
        }
        #rch_agent_testimonials_meta_box .rch-testimonial-row__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        #rch_agent_testimonials_meta_box .rch-testimonial-row__title {
            font-size: 13px;
        }
        #rch_agent_testimonials_meta_box .rch-testimonial-row label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
        }
        #rch_agent_testimonials_meta_box .rch-testimonial-row p {
            margin: 0 0 10px;
        }
        #rch_agent_testimonials_meta_box .rch-testimonial-row p:last-child {
            margin-bottom: 0;
        }
    </style>
    <?php
}

function rch_agent_testimonials_admin_script(): void
{
    global $post_type;
    if ($post_type !== 'agents') {
        return;
    }
    ?>
    <script>
    (function ($) {
        'use strict';

        function reindexTestimonialRows() {
            $('#rch-agent-testimonials-list .rch-testimonial-row').each(function (i) {
                var $row = $(this);
                $row.attr('data-index', i);
                $row.find('input[name^="rch_agent_testimonials"]').each(function () {
                    var $el = $(this);
                    var field = $el.attr('name').replace(/\[(\d+|__INDEX__)\]/, '[' + i + ']');
                    $el.attr('name', field);
                });
                $row.find('textarea[name^="rch_agent_testimonials"]').each(function () {
                    var $el = $(this);
                    var field = $el.attr('name').replace(/\[(\d+|__INDEX__)\]/, '[' + i + ']');
                    $el.attr('name', field);
                });
            });
        }

        $(document).on('click', '#rch-add-agent-testimonial', function (e) {
            e.preventDefault();
            var template = $('#rch-agent-testimonial-row-template').html();
            if (!template) {
                return;
            }
            var $list = $('#rch-agent-testimonials-list');
            var nextIndex = $list.find('.rch-testimonial-row').length;
            $list.append(template.replace(/__INDEX__/g, String(nextIndex)));
            reindexTestimonialRows();
        });

        $(document).on('click', '.rch-remove-agent-testimonial', function (e) {
            e.preventDefault();
            var $list = $('#rch-agent-testimonials-list');
            var $rows = $list.find('.rch-testimonial-row');
            if ($rows.length <= 1) {
                $rows.find('input, textarea').val('');
                return;
            }
            $(this).closest('.rch-testimonial-row').remove();
            reindexTestimonialRows();
        });
    })(jQuery);
    </script>
    <?php
}
add_action('admin_footer', 'rch_agent_testimonials_admin_script');

function rch_save_agent_testimonials_meta(int $post_id): void
{
    if (! isset($_POST['rch_agent_testimonials_nonce'])) {
        return;
    }

    if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rch_agent_testimonials_nonce'])), 'rch_save_agent_testimonials')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'agents') {
        return;
    }

    $raw = isset($_POST['rch_agent_testimonials']) && is_array($_POST['rch_agent_testimonials'])
        ? wp_unslash($_POST['rch_agent_testimonials'])
        : [];

    $testimonials = rch_sanitize_agent_testimonials($raw);

    if ($testimonials === []) {
        delete_post_meta($post_id, RCH_AGENT_TESTIMONIALS_META_KEY);
        return;
    }

    update_post_meta($post_id, RCH_AGENT_TESTIMONIALS_META_KEY, $testimonials);
}
add_action('save_post_agents', 'rch_save_agent_testimonials_meta');

/**
 * Block editor / hybrid save: persist testimonials when fields are posted without the classic nonce.
 */
function rch_save_agent_testimonials_meta_from_posted_fields(int $post_id): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (isset($_POST['rch_agent_testimonials_nonce'])) {
        return;
    }

    if (! isset($_POST['rch_agent_testimonials']) || ! is_array($_POST['rch_agent_testimonials'])) {
        return;
    }

    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'agents') {
        return;
    }

    $testimonials = rch_sanitize_agent_testimonials(wp_unslash($_POST['rch_agent_testimonials']));

    if ($testimonials === []) {
        delete_post_meta($post_id, RCH_AGENT_TESTIMONIALS_META_KEY);
        return;
    }

    update_post_meta($post_id, RCH_AGENT_TESTIMONIALS_META_KEY, $testimonials);
}
add_action('save_post_agents', 'rch_save_agent_testimonials_meta_from_posted_fields', 25);

function rch_register_agent_testimonials_meta(): void
{
    register_post_meta(
        'agents',
        RCH_AGENT_TESTIMONIALS_META_KEY,
        [
            'type'              => 'array',
            'description'       => __('Agent testimonials (name + description).', 'rechat-plugin'),
            'single'            => true,
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'name'        => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'auth_callback'     => static function (): bool {
                return current_user_can('edit_posts');
            },
            'sanitize_callback' => 'rch_sanitize_agent_testimonials',
        ]
    );
}
add_action('init', 'rch_register_agent_testimonials_meta');
