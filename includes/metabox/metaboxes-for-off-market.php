<?php

/**
 * Off Market — admin meta boxes.
 *
 * Renders + saves the fields declared in the registry
 * (includes/off-market/off-market-fields.php). One meta box per group.
 * Follows the same conventions as metaboxes-for-agents.php: nonce, autosave
 * bail, capability check, post-type check, per-type sanitization.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit();
}

/**
 * Register one meta box per registry group.
 */
function rch_off_market_add_meta_boxes()
{
    foreach (rch_off_market_get_groups() as $group => $title) {
        add_meta_box(
            'off_market_' . $group . '_meta_box',
            $title,
            'rch_off_market_render_meta_box',
            RCH_OFF_MARKET_CPT,
            'normal',
            'default',
            array('group' => $group)
        );
    }
}
add_action('add_meta_boxes', 'rch_off_market_add_meta_boxes');

/**
 * Render the fields for one group.
 *
 * @param WP_Post $post
 * @param array   $box   Contains ['args' => ['group' => ...]].
 */
function rch_off_market_render_meta_box($post, $box)
{
    $group = isset($box['args']['group']) ? $box['args']['group'] : '';

    // One nonce is enough for the whole screen; print it on the first box.
    static $nonce_done = false;
    if (! $nonce_done) {
        wp_nonce_field('off_market_meta_box', 'off_market_meta_box_nonce');
        $nonce_done = true;
    }

    echo '<table class="form-table" role="presentation"><tbody>';

    foreach (rch_off_market_get_fields() as $field) {
        if ($field['group'] !== $group) {
            continue;
        }

        $key   = $field['key'];
        $type  = isset($field['type']) ? $field['type'] : 'text';
        $value = get_post_meta($post->ID, $key, true);
        $name  = 'off_market_' . $key;
        $desc  = isset($field['desc']) ? $field['desc'] : '';

        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($field['label']) . '</label></th>';
        echo '<td>';

        switch ($type) {
            case 'textarea':
                echo '<textarea id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" rows="3" class="widefat">' . esc_textarea($value) . '</textarea>';
                break;

            case 'gallery':
                $tokens = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $value)));
                echo '<div class="rch-om-gallery">';
                echo '<ul class="rch-om-gallery__list">';
                foreach ($tokens as $tok) {
                    if (ctype_digit($tok)) {
                        $src = wp_get_attachment_image_url((int) $tok, 'thumbnail');
                    } elseif (filter_var($tok, FILTER_VALIDATE_URL)) {
                        $src = $tok;
                    } else {
                        $src = '';
                    }
                    if (! $src) {
                        continue;
                    }
                    echo '<li class="rch-om-gallery__item" data-id="' . esc_attr($tok) . '">';
                    echo '<img src="' . esc_url($src) . '" alt="" />';
                    echo '<button type="button" class="rch-om-gallery__remove" aria-label="' . esc_attr__('Remove image', 'rechat-plugin') . '">&times;</button>';
                    echo '</li>';
                }
                echo '</ul>';
                echo '<input type="hidden" class="rch-om-gallery__input" name="' . esc_attr($name) . '" value="' . esc_attr(implode(',', $tokens)) . '" />';
                echo '<button type="button" class="button rch-om-gallery__add">' . esc_html__('Add / Upload Images', 'rechat-plugin') . '</button>';
                echo '</div>';
                break;

            case 'select':
                echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" class="widefat">';
                echo '<option value="">' . esc_html__('— Select —', 'rechat-plugin') . '</option>';
                foreach ((array) $field['options'] as $opt_val => $opt_label) {
                    echo '<option value="' . esc_attr($opt_val) . '" ' . selected($value, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
                }
                echo '</select>';
                break;

            case 'number':
                echo '<input type="number" step="any" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;

            case 'date':
                echo '<input type="date" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;

            case 'text':
            default:
                echo '<input type="text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="widefat" />';
                break;
        }

        if ($desc !== '') {
            echo '<p class="description">' . esc_html($desc) . '</p>';
        }

        echo '</td></tr>';
    }

    echo '</tbody></table>';
}

/**
 * Save all registry fields.
 *
 * @param int $post_id
 * @return void
 */
function rch_off_market_save_meta_box($post_id)
{
    if (! isset($_POST['off_market_meta_box_nonce']) || ! wp_verify_nonce($_POST['off_market_meta_box_nonce'], 'off_market_meta_box')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (get_post_type($post_id) !== RCH_OFF_MARKET_CPT) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    foreach (rch_off_market_get_fields() as $field) {
        $key  = $field['key'];
        $name = 'off_market_' . $key;
        if (! isset($_POST[$name])) {
            continue;
        }

        $raw  = wp_unslash($_POST[$name]);
        $type = isset($field['type']) ? $field['type'] : 'text';

        switch ($type) {
            case 'gallery':
                // Tokens: attachment IDs (numeric) or raw URLs (legacy). Stored comma-separated.
                $tokens = array();
                foreach (preg_split('/[\r\n,]+/', (string) $raw) as $tok) {
                    $tok = trim($tok);
                    if ($tok === '') {
                        continue;
                    }
                    if (ctype_digit($tok)) {
                        $tokens[] = (string) absint($tok);
                    } elseif (filter_var($tok, FILTER_VALIDATE_URL)) {
                        $tokens[] = esc_url_raw($tok);
                    }
                }
                $clean = implode(',', $tokens);
                break;

            case 'textarea':
                $clean = sanitize_textarea_field($raw);
                break;

            case 'select':
                $allowed = array_keys((array) $field['options']);
                $clean = in_array($raw, $allowed, true) ? $raw : '';
                break;

            default:
                $clean = sanitize_text_field($raw);
                break;
        }

        if ($clean === '' || $clean === array()) {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $clean);
        }
    }
}
add_action('save_post_' . RCH_OFF_MARKET_CPT, 'rch_off_market_save_meta_box');

/**
 * Media Library uploader assets for the gallery field — only on the
 * Off Market add/edit screen.
 *
 * @param string $hook
 */
function rch_off_market_admin_assets($hook)
{
    if (! in_array($hook, array('post.php', 'post-new.php'), true)) {
        return;
    }
    $screen = get_current_screen();
    if (! $screen || $screen->post_type !== RCH_OFF_MARKET_CPT) {
        return;
    }
    if (! defined('RCH_PLUGIN_ASSETS') || ! defined('RCH_VERSION')) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'rch-off-market-admin',
        RCH_PLUGIN_ASSETS . 'js/rch-off-market-admin.js',
        array('jquery', 'jquery-ui-sortable'),
        RCH_VERSION,
        true
    );

    $css = '.rch-om-gallery__list{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 10px;padding:0;list-style:none}'
        . '.rch-om-gallery__item{position:relative;width:100px;height:100px;cursor:move}'
        . '.rch-om-gallery__item img{width:100%;height:100%;object-fit:cover;border-radius:4px;display:block;border:1px solid #dcdcde}'
        . '.rch-om-gallery__item:first-child::after{content:"Cover";position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.6);color:#fff;font-size:10px;text-align:center;padding:1px 0}'
        . '.rch-om-gallery__remove{position:absolute;top:-8px;right:-8px;width:22px;height:22px;border-radius:50%;border:none;background:#d63638;color:#fff;cursor:pointer;line-height:20px;padding:0;font-size:15px}';
    wp_register_style('rch-off-market-admin', false, array(), RCH_VERSION);
    wp_enqueue_style('rch-off-market-admin');
    wp_add_inline_style('rch-off-market-admin', $css);
}
add_action('admin_enqueue_scripts', 'rch_off_market_admin_assets');
