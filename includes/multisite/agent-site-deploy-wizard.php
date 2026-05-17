<?php
/**
 * Multisite: Agent sub-site theme options deploy wizard (main site admin).
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once RCH_PLUGIN_INCLUDES . 'multisite/agent-wizard-menus-widgets-sync.php';
require_once RCH_PLUGIN_INCLUDES . 'multisite/agent-wizard-testimonials-sync.php';

/**
 * User meta key for draft wizard state (JSON).
 */
const RCH_AGENT_WIZARD_DRAFT_META = 'rch_agent_site_wizard_draft';

/**
 * AJAX nonce action.
 */
const RCH_AGENT_WIZARD_NONCE_ACTION = 'rch_agent_wizard';

/**
 * Stylesheet slug used to build wizard rows (network “Default theme for agent sub-sites”).
 */
function rch_agent_wizard_wizard_ui_stylesheet(): string
{
    if (function_exists('rch_multisite_resolve_theme_network_default_for_agents')) {
        return (string) rch_multisite_resolve_theme_network_default_for_agents()['stylesheet'];
    }

    return (string) wp_get_theme()->get_stylesheet();
}

/**
 * Labels shared by all profiles (wizard-only option keys).
 *
 * @return array<string, string>
 */
function rch_agent_wizard_wizard_only_labels(): array
{
    return [
        'rch-wizard-agent-email'       => __('Agent email (stored in theme options)', 'rechat-plugin'),
        'rch-wizard-agent-post-id'     => __('Agent post ID (stored in theme options)', 'rechat-plugin'),
        'rch-wizard-agent-api-id'      => __('Rechat / API ID (stored in theme options)', 'rechat-plugin'),
        'rch-wizard-agent-designation' => __('Designation (stored in theme options)', 'rechat-plugin'),
    ];
}

/**
 * Merge theme keys with wizard-only keys.
 *
 * @param list<string> $theme_keys
 * @return list<string>
 */
function rch_agent_wizard_merge_wizard_keys(array $theme_keys): array
{
    $wizard = ['rch-wizard-agent-email', 'rch-wizard-agent-post-id', 'rch-wizard-agent-api-id', 'rch-wizard-agent-designation'];

    return array_values(array_unique(array_merge($theme_keys, $wizard)));
}

require_once __DIR__ . '/agent-wizard-dynamic-options.php';

/**
 * Field metadata + storage from the active agent theme only (manifest, themeoption.php, option snapshot). No hardcoded per-theme profiles.
 *
 * @return array<string, mixed>
 */
function rch_agent_wizard_get_theme_profile(string $stylesheet): array
{
    $dynamic = rch_agent_wizard_try_build_dynamic_theme_profile($stylesheet);
    if (is_array($dynamic)) {
        return apply_filters('rch_agent_wizard_theme_profile', $dynamic, $stylesheet);
    }

    $stylesheet = is_string($stylesheet) ? $stylesheet : '';
    $empty       = [
        'keys'            => [],
        'labels'          => rch_agent_wizard_wizard_only_labels(),
        'urls'            => [],
        'textareas'       => [],
        'textarea_json'   => [],
        'numbers'         => [],
        'wp_kses'         => [],
        'color'           => [],
        'image_media'     => [],
        'video_media'     => [],
        'select_keys'     => [],
        'select_options'  => [],
        'import_defaults' => [],
        'storage_primary' => '',
        'storage_mirror'  => null,
        'dynamic'         => false,
        'stylesheet'      => $stylesheet,
    ];

    return apply_filters('rch_agent_wizard_theme_profile', $empty, $stylesheet);
}

/**
 * Allowed theme option keys for the current wizard (follows network agent default theme).
 *
 * @return list<string>
 */
function rch_agent_wizard_allowed_theme_option_keys(): array
{
    $keys = rch_agent_wizard_get_theme_profile(rch_agent_wizard_wizard_ui_stylesheet())['keys'];

    /** @var list<string> $keys */
    return apply_filters('rch_agent_wizard_allowed_theme_option_keys', $keys);
}

/**
 * Human labels for current wizard profile.
 *
 * @return array<string, string>
 */
function rch_agent_wizard_theme_key_labels(): array
{
    $labels = rch_agent_wizard_get_theme_profile(rch_agent_wizard_wizard_ui_stylesheet())['labels'];

    return apply_filters('rch_agent_wizard_theme_key_labels', $labels);
}

/**
 * Whether a theme option key should use the tag-picker UI (JSON string[] like theme option panel).
 */
function rch_agent_wizard_key_uses_tags_multiselect_ui(string $key): bool
{
    $k = strtolower($key);

    return $k === 'rch_selected_tags'
        || rch_agent_wizard_str_contains_ci($k, 'selected-tags')
        || rch_agent_wizard_str_contains_ci($k, 'selected_tags');
}

/**
 * Lead channel choices for wizard select (same API as Rechat settings).
 *
 * @return list<array{value:string,label:string}>
 */
function rch_agent_wizard_fetch_lead_channel_select_options(): array
{
    if (! function_exists('rch_fetch_lead_channels')) {
        return [];
    }

    $response = rch_fetch_lead_channels();
    if (empty($response['success']) || empty($response['data']['data']) || ! is_array($response['data']['data'])) {
        return [];
    }

    $out = [];
    foreach ($response['data']['data'] as $channel) {
        if (! is_array($channel) || ! isset($channel['id'])) {
            continue;
        }
        $id    = (string) $channel['id'];
        $label = isset($channel['title']) && is_string($channel['title']) && $channel['title'] !== ''
            ? (string) $channel['title']
            : $id;
        $out[] = ['value' => $id, 'label' => $label];
    }

    /**
     * @param list<array{value:string,label:string}> $out
     */
    return apply_filters('rch_agent_wizard_lead_channel_select_options', $out);
}

/**
 * Tag labels from Rechat API (theme option panel uses same pool).
 *
 * @return list<string>
 */
function rch_agent_wizard_fetch_tag_choice_strings(): array
{
    if (! function_exists('rch_fetch_tags')) {
        return [];
    }

    $response = rch_fetch_tags();
    if (empty($response['success']) || empty($response['data']['data']) || ! is_array($response['data']['data'])) {
        return [];
    }

    $out = [];
    foreach ($response['data']['data'] as $tag) {
        if (! is_array($tag)) {
            continue;
        }
        $name = $tag['tag'] ?? $tag['text'] ?? '';
        $name = is_string($name) ? trim($name) : '';
        if ($name !== '' && ! in_array($name, $out, true)) {
            $out[] = $name;
        }
    }
    sort($out, SORT_STRING);

    /** @param list<string> $out */
    return apply_filters('rch_agent_wizard_tag_choice_strings', $out);
}

/**
 * Manual step: all theme keys with UI type (matches theme option panel).
 *
 * @return list<array{key:string,label:string,type:string,media:string,options?:list<array{value:string,label:string}>}>
 */
function rch_agent_wizard_manual_field_defs(): array
{
    $profile         = rch_agent_wizard_get_theme_profile(rch_agent_wizard_wizard_ui_stylesheet());
    $labels          = $profile['labels'];
    $select_keys     = isset($profile['select_keys']) && is_array($profile['select_keys']) ? $profile['select_keys'] : [];
    $select_opts_map = isset($profile['select_options']) && is_array($profile['select_options']) ? $profile['select_options'] : [];
    $rows            = [];

    foreach ($profile['keys'] as $key) {
        $type  = 'text';
        $media = '';
        if (in_array($key, $profile['textareas'], true)) {
            $type = 'textarea';
        } elseif (in_array($key, $profile['textarea_json'], true) || $key === 'rch_selected_tags') {
            $type = 'textarea_json';
        } elseif (in_array($key, $profile['urls'], true)) {
            $type = 'url';
        } elseif (in_array($key, $profile['numbers'], true) || preg_match('/^rch-counter-\d-value$/', $key)) {
            $type = 'number';
        }
        if (in_array($key, $profile['image_media'], true)) {
            $media = 'image';
        } elseif (in_array($key, $profile['video_media'], true)) {
            $media = 'video';
        }

        $options = [];
        if (in_array($key, $select_keys, true)) {
            $type = 'select';
            $opts = isset($select_opts_map[ $key ]) && is_array($select_opts_map[ $key ]) ? $select_opts_map[ $key ] : [];
            if ($opts === [] && function_exists('rch_fetch_lead_channels')) {
                $opts = rch_agent_wizard_fetch_lead_channel_select_options();
            }
            foreach ($opts as $opt) {
                if (is_array($opt) && isset($opt['value'], $opt['label'])) {
                    $options[] = ['value' => (string) $opt['value'], 'label' => (string) $opt['label']];
                }
            }
        } elseif (($type === 'textarea_json' || $key === 'rch_selected_tags') && rch_agent_wizard_key_uses_tags_multiselect_ui($key)) {
            $type = 'tags';
            foreach (rch_agent_wizard_fetch_tag_choice_strings() as $t) {
                $options[] = ['value' => $t, 'label' => $t];
            }
        }

        $row = [
            'key'   => $key,
            'label' => $labels[ $key ] ?? $key,
            'type'  => $type,
            'media' => $media,
        ];
        if ($options !== []) {
            $row['options'] = $options;
        }
        $rows[] = $row;
    }
    usort(
        $rows,
        static function (array $a, array $b): int {
            return strcasecmp($a['label'], $b['label']);
        }
    );

    return $rows;
}

/**
 * Importable field defs with default_theme_key adjusted for the wizard UI theme.
 *
 * @return array<string, array{label:string, default_theme_key:string}>
 */
function rch_agent_wizard_importable_field_defs_resolved(): array
{
    $defs = rch_agent_wizard_importable_field_defs();
    $map  = rch_agent_wizard_get_theme_profile(rch_agent_wizard_wizard_ui_stylesheet())['import_defaults'] ?? [];
    if (! is_array($map)) {
        return $defs;
    }
    foreach ($map as $meta_key => $theme_key) {
        if (isset($defs[ $meta_key ]) && is_string($theme_key) && $theme_key !== '') {
            $defs[ $meta_key ]['default_theme_key'] = $theme_key;
        }
    }

    return $defs;
}

/**
 * Theme option key => agent profile field key (for wizard meta binding restore).
 *
 * @return array<string, string>
 */
function rch_agent_wizard_theme_to_meta_import_map(): array
{
    $out  = [];
    $defs = rch_agent_wizard_importable_field_defs_resolved();
    foreach ($defs as $meta_key => $def) {
        if (! is_string($meta_key) || ! is_array($def)) {
            continue;
        }
        $theme_key = isset($def['default_theme_key']) ? (string) $def['default_theme_key'] : '';
        if ($theme_key !== '') {
            $out[ $theme_key ] = $meta_key;
        }
    }

    $import_defaults = rch_agent_wizard_get_theme_profile(rch_agent_wizard_wizard_ui_stylesheet())['import_defaults'] ?? [];
    if (is_array($import_defaults)) {
        foreach ($import_defaults as $meta_key => $theme_key) {
            if (is_string($meta_key) && is_string($theme_key) && $theme_key !== '') {
                $out[ $theme_key ] = $meta_key;
            }
        }
    }

    /**
     * @param array<string, string> $out
     */
    return apply_filters('rch_agent_wizard_theme_to_meta_import_map', $out);
}

/**
 * Restore meta bindings in draft theme rows when manual values match last deployment / agent meta.
 *
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 * @return array<string, array{mode:string, value?:mixed, meta_key?:string}>
 */
function rch_agent_wizard_reconcile_draft_theme_rows(int $agent_id, array $theme_rows): array
{
    if ($agent_id <= 0 || $theme_rows === []) {
        return $theme_rows;
    }

    if (! function_exists('rch_multisite_get_agent_blog_id')) {
        return $theme_rows;
    }

    $blog_id = (int) rch_multisite_get_agent_blog_id($agent_id);
    if ($blog_id <= 0) {
        return $theme_rows;
    }

    $last = rch_agent_wizard_read_destination_last_deployment($blog_id);
    if ($last === null || empty($last['theme_rows']) || ! is_array($last['theme_rows'])) {
        return $theme_rows;
    }

    foreach ($theme_rows as $theme_key => $cfg) {
        if (! is_string($theme_key) || ! is_array($cfg)) {
            continue;
        }
        if (($cfg['mode'] ?? '') !== 'manual') {
            continue;
        }
        if (! isset($last['theme_rows'][ $theme_key ]) || ! is_array($last['theme_rows'][ $theme_key ])) {
            continue;
        }
        $deploy = $last['theme_rows'][ $theme_key ];
        if (($deploy['mode'] ?? '') !== 'meta' || empty($deploy['meta_key'])) {
            continue;
        }
        $meta_key = sanitize_key((string) $deploy['meta_key']);
        $manual   = isset($cfg['value']) ? (string) $cfg['value'] : '';
        $from_meta = rch_agent_wizard_get_import_source_value($agent_id, $meta_key);
        if ($manual !== '' && trim($manual) === trim((string) $from_meta)) {
            $theme_rows[ $theme_key ] = [
                'mode'      => 'meta',
                'meta_key'  => $meta_key,
            ];
        }
    }

    return $theme_rows;
}

/**
 * Count published agents that already have a linked sub-site.
 */
function rch_agent_wizard_count_agents_with_subsites(): int
{
    if (! function_exists('rch_multisite_get_agent_blog_id')) {
        return 0;
    }
    $ids = get_posts([
        'post_type'      => 'agents',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $n = 0;
    foreach ($ids as $aid) {
        if (rch_multisite_get_agent_blog_id((int) $aid) > 0) {
            $n++;
        }
    }

    return $n;
}

/**
 * Deploy same theme row configuration to every agent sub-site that exists.
 *
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 * @return array{updated:int, skipped:int, errors:list<string>}
 */
function rch_agent_wizard_deploy_all_agent_subsites(array $theme_rows): array
{
    $updated = 0;
    $skipped = 0;
    $errors  = [];

    $agents = get_posts([
        'post_type'      => 'agents',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($agents as $agent_id) {
        $agent_id = (int) $agent_id;
        if (! function_exists('rch_multisite_get_agent_blog_id') || ! rch_multisite_get_agent_blog_id($agent_id)) {
            $skipped++;
            continue;
        }
        $result = rch_agent_wizard_deploy_to_agent_blog($agent_id, $theme_rows);
        if (is_wp_error($result)) {
            $errors[] = sprintf(
                '%s (ID %d): %s',
                get_the_title($agent_id),
                $agent_id,
                $result->get_error_message()
            );
        } else {
            $updated++;
        }
    }

    return compact('updated', 'skipped', 'errors');
}

/**
 * Importable agent sources: meta key or synthetic key => label + default theme key (empty = choose in wizard).
 *
 * @return array<string, array{label:string, default_theme_key:string}>
 */
function rch_agent_wizard_importable_field_defs(): array
{
    $defs = [
        'post_title' => [
            'label'               => __('Agent name (post title)', 'rechat-plugin'),
            'default_theme_key'   => 'rch-theme-title-main-hero',
        ],
        'post_content' => [
            'label'               => __('Agent content (post editor)', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'first_name' => [
            'label'               => __('First name', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'api_id' => [
            'label'               => __('Rechat / API ID', 'rechat-plugin'),
            'default_theme_key'   => 'rch-wizard-agent-api-id',
        ],
        'profile_image_url' => [
            'label'               => __('Profile image URL', 'rechat-plugin'),
            'default_theme_key'   => 'rch-theme-menu-image',
        ],
        'phone_number' => [
            'label'               => __('Phone number', 'rechat-plugin'),
            'default_theme_key'   => 'rch-theme-phone',
        ],
        'email' => [
            'label'               => __('Email', 'rechat-plugin'),
            'default_theme_key'   => 'rch-wizard-agent-email',
        ],
        'designation' => [
            'label'               => __('Designation', 'rechat-plugin'),
            'default_theme_key'   => 'rch-wizard-agent-designation',
        ],
        'last_name' => [
            'label'               => __('Last name', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'website' => [
            'label'               => __('Website URL', 'rechat-plugin'),
            'default_theme_key'   => 'rch-theme-url-listing-button',
        ],
        'instagram' => [
            'label'               => __('Instagram handle or URL', 'rechat-plugin'),
            'default_theme_key'   => 'rch-theme-instagram-url',
        ],
        'twitter' => [
            'label'               => __('Twitter / X', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'linkedin' => [
            'label'               => __('LinkedIn', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'youtube' => [
            'label'               => __('YouTube', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'facebook' => [
            'label'               => __('Facebook', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'timezone' => [
            'label'               => __('Timezone', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'license_number' => [
            'label'               => __('License number', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'agent_address' => [
            'label'               => __('Address (from assigned offices)', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'agent_visibility' => [
            'label'               => __('Visibility (show/hide)', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
    ];

    /**
     * Filter importable agent meta / synthetic fields for the wizard.
     *
     * @param array<string, array{label:string, default_theme_key:string}> $defs
     */
    return apply_filters('rch_agent_wizard_importable_field_defs', $defs);
}

/**
 * @param mixed $value
 */
function rch_agent_wizard_normalize_social_url($value): string
{
    $s = is_string($value) ? trim($value) : '';
    if ($s === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $s)) {
        return esc_url_raw($s);
    }
    // Plain handle → Instagram-style URL guess only when path looks like handle
    if (preg_match('/^[a-z0-9._]+$/i', $s)) {
        return esc_url_raw('https://instagram.com/' . $s);
    }

    return esc_url_raw($s);
}

/**
 * Sanitize values using a theme profile (option panel field kinds).
 *
 * @param  array<string, mixed> $row
 * @param  array<string, mixed> $profile From rch_agent_wizard_get_theme_profile().
 * @return array<string, mixed>
 */
function rch_agent_wizard_sanitize_theme_options_row(array $row, ?array $profile = null): array
{
    if ($profile === null) {
        $profile = rch_agent_wizard_get_theme_profile(rch_agent_wizard_wizard_ui_stylesheet());
    }

    $allowed = array_flip($profile['keys']);
    $out     = [];

    foreach ($row as $key => $value) {
        if (! is_string($key) || ! isset($allowed[ $key ])) {
            continue;
        }

        if ($key === 'rch_selected_tags' || in_array($key, $profile['textarea_json'] ?? [], true)) {
            if (is_array($value)) {
                $out[ $key ] = array_map('sanitize_text_field', $value);
            } elseif (is_string($value)) {
                $decoded     = json_decode(wp_unslash($value), true);
                $out[ $key ] = is_array($decoded) ? array_map('sanitize_text_field', $decoded) : [];
            } else {
                $out[ $key ] = [];
            }
            continue;
        }

        if ($key === 'rch-wizard-agent-post-id') {
            $out[ $key ] = (string) absint($value);
            continue;
        }

        if ($key === 'rch-theme-talk-shortcode') {
            $out[ $key ] = wp_kses_post(wp_unslash(is_string($value) ? $value : ''));
            continue;
        }

        if (
            $key === 'rch-theme-instagram-url'
            || $key === 'rch-theme-instagram'
            || strpos(strtolower($key), 'instagram') !== false
        ) {
            $out[ $key ] = rch_agent_wizard_normalize_social_url($value);
            continue;
        }

        if (in_array($key, $profile['urls'] ?? [], true)) {
            $out[ $key ] = esc_url_raw(is_string($value) ? $value : '');
            continue;
        }

        if (in_array($key, $profile['textareas'] ?? [], true)) {
            $out[ $key ] = sanitize_textarea_field(is_string($value) ? $value : '');
            continue;
        }

        if (in_array($key, $profile['wp_kses'] ?? [], true)) {
            $out[ $key ] = wp_kses_post(is_string($value) ? $value : '');
            continue;
        }

        if (in_array($key, $profile['color'] ?? [], true)) {
            $c = sanitize_hex_color(is_string($value) ? $value : '');
            $out[ $key ] = $c ? $c : '';
            continue;
        }

        if (in_array($key, $profile['numbers'] ?? [], true) || preg_match('/^rch-counter-\d-value$/', $key)) {
            $out[ $key ] = sanitize_text_field(is_string($value) || is_numeric($value) ? (string) $value : '');
            continue;
        }

        if (in_array($key, ['rch-theme-telegram-url', 'rch-theme-whatsapp-url'], true)) {
            $out[ $key ] = sanitize_text_field(is_string($value) ? $value : '');
            continue;
        }

        $out[ $key ] = sanitize_text_field(is_string($value) ? $value : (is_scalar($value) ? (string) $value : ''));
    }

    return $out;
}

/**
 * Read agent field value for import (post title, post body, or post meta).
 *
 * @return string
 */
function rch_agent_wizard_get_import_source_value(int $agent_id, string $field): string
{
    if ($field === 'post_title') {
        $post = get_post($agent_id);
        return $post ? (string) $post->post_title : '';
    }

    if ($field === 'post_content') {
        $post = get_post($agent_id);
        if (! $post || ! is_string($post->post_content)) {
            return '';
        }
        $raw = $post->post_content;
        /**
         * Text pushed into theme options (often textarea). Default: strip block markup, keep breaks.
         *
         * @param string $text    Processed post_content.
         * @param int    $agent_id Agent post ID.
         * @param WP_Post $post   Agent post object.
         */
        return (string) apply_filters('rch_agent_wizard_import_post_content_value', wp_strip_all_tags($raw, false), $agent_id, $post);
    }

    $raw = get_post_meta($agent_id, $field, true);

    return is_scalar($raw) ? (string) $raw : '';
}

/**
 * Manual text templates: replace {$meta_key} with agent meta values.
 *
 * Supported tokens: any key from rch_agent_wizard_importable_field_defs(), plus {$post_title} and {$post_content}.
 *
 * Example: "Buy & Sell Home in {$timezone} With Confidence"
 */
function rch_agent_wizard_apply_placeholders(int $agent_id, string $text): string
{
    if ($text === '' || strpos($text, '{$') === false) {
        return $text;
    }

    $allowed = array_flip(array_keys(rch_agent_wizard_importable_field_defs()));
    $allowed['post_title'] = true;

    $out = preg_replace_callback(
        '/\{\$([a-zA-Z0-9_]+)\}/',
        static function (array $m) use ($agent_id, $allowed): string {
            $key = isset($m[1]) ? sanitize_key((string) $m[1]) : '';
            if ($key === '' || ! isset($allowed[ $key ])) {
                return $m[0];
            }
            return rch_agent_wizard_get_import_source_value($agent_id, $key);
        },
        $text
    );

    return is_string($out) ? $out : $text;
}

/**
 * Build theme option patch from per-field mode (skip / manual / meta).
 *
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 * @return array<string, mixed>
 */
function rch_agent_wizard_build_row_from_theme_rows(int $agent_id, array $theme_rows): array
{
    $allowed      = array_flip(rch_agent_wizard_allowed_theme_option_keys());
    $meta_allowed = array_flip(array_keys(rch_agent_wizard_importable_field_defs()));
    $row          = [];

    $instagram_keys = ['rch-theme-instagram-url', 'rch-theme-instagram'];
    $website_keys   = ['rch-theme-url-listing-button', 'rch-theme-listing-page-link'];

    foreach ($theme_rows as $theme_key => $cfg) {
        if (! is_string($theme_key) || ! isset($allowed[ $theme_key ])) {
            continue;
        }
        if (! is_array($cfg)) {
            continue;
        }

        $mode = isset($cfg['mode']) ? sanitize_key((string) $cfg['mode']) : 'skip';
        if ($mode === 'skip' || $mode === '') {
            continue;
        }

        if ($mode === 'manual') {
            $v = $cfg['value'] ?? '';
            if (is_string($v)) {
                $v = rch_agent_wizard_apply_placeholders($agent_id, $v);
            }
            $row[ $theme_key ] = $v;
            continue;
        }

        if ($mode !== 'meta') {
            continue;
        }

        $meta_key = isset($cfg['meta_key']) ? sanitize_key((string) $cfg['meta_key']) : '';
        if ($meta_key === '' || ! isset($meta_allowed[ $meta_key ])) {
            continue;
        }

        $value = rch_agent_wizard_get_import_source_value($agent_id, $meta_key);

        if ($meta_key === 'instagram' && (in_array($theme_key, $instagram_keys, true) || strpos(strtolower($theme_key), 'instagram') !== false)) {
            $value = rch_agent_wizard_normalize_social_url($value);
        } elseif (
            $meta_key === 'website'
            && (
                in_array($theme_key, $website_keys, true)
                || (strpos(strtolower($theme_key), 'listing') !== false && (strpos(strtolower($theme_key), 'url') !== false || strpos(strtolower($theme_key), 'link') !== false))
            )
        ) {
            $value = esc_url_raw($value);
        }

        $row[ $theme_key ] = $value;
    }

    $row['rch-wizard-agent-post-id'] = (string) $agent_id;

    return $row;
}

/**
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 * @return true|WP_Error
 */
function rch_agent_wizard_deploy_to_agent_blog(int $agent_id, array $theme_rows)
{
    if (! is_multisite()) {
        return new WP_Error('rch_wizard_not_multisite', __('Multisite is not enabled.', 'rechat-plugin'));
    }

    if (! current_user_can('manage_network_options')) {
        return new WP_Error('rch_wizard_cap', __('Insufficient permissions.', 'rechat-plugin'));
    }

    if (! function_exists('rch_multisite_get_agent_blog_id')) {
        return new WP_Error('rch_wizard_missing', __('Multisite helpers are not loaded.', 'rechat-plugin'));
    }

    $blog_id = rch_multisite_get_agent_blog_id($agent_id);
    if (! $blog_id) {
        return new WP_Error('rch_wizard_no_blog', __('This agent has no linked sub-site. Provision the site first.', 'rechat-plugin'));
    }

    $post = get_post($agent_id);
    if (! $post || $post->post_type !== 'agents') {
        return new WP_Error('rch_wizard_bad_agent', __('Invalid agent post.', 'rechat-plugin'));
    }

    $merged_raw = rch_agent_wizard_build_row_from_theme_rows($agent_id, $theme_rows);

    switch_to_blog($blog_id);

    $dest_stylesheet = (string) get_option('stylesheet');
    $profile         = rch_agent_wizard_get_theme_profile($dest_stylesheet);
    if (empty($profile['storage_primary']) || ! is_string($profile['storage_primary'])) {
        restore_current_blog();
        return new WP_Error(
            'rch_wizard_storage_missing',
            __('Could not detect theme option storage for this sub-site theme. Add a rechat-agent-wizard.json manifest, or configure rch_agent_wizard_storage_config / rch_agent_wizard_theme_storage_map.', 'rechat-plugin')
        );
    }
    $sanitized       = rch_agent_wizard_sanitize_theme_options_row($merged_raw, $profile);
    $dest_allowed    = array_flip($profile['keys']);
    $sanitized       = array_intersect_key($sanitized, $dest_allowed);

    $primary = isset($profile['storage_primary']) ? (string) $profile['storage_primary'] : 'pentama_options_v2';
    $mirror  = array_key_exists('storage_mirror', $profile) ? $profile['storage_mirror'] : 'pentama_options_agent_website';

    $existing = get_option($primary, []);
    if (! is_array($existing)) {
        $existing = [];
    }

    $merged = array_merge($existing, $sanitized);

    update_option($primary, $merged, false);
    if (is_string($mirror) && $mirror !== '') {
        update_option($mirror, $merged, false);
    }

    rch_agent_wizard_record_last_deployment_in_blog($agent_id, $theme_rows);

    restore_current_blog();

    return true;
}

/**
 * Record the last wizard deployment row config + raw deployed values on the agent sub-site so the
 * wizard can repaint with the exact modes (skip/manual/meta) and values the user picked.
 *
 * Must be called inside `switch_to_blog($blog_id)`.
 *
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 */
function rch_agent_wizard_record_last_deployment_in_blog(int $agent_id, array $theme_rows): void
{
    $allowed_meta = array_flip(array_keys(rch_agent_wizard_importable_field_defs_resolved()));
    $clean_rows   = [];

    foreach ($theme_rows as $key => $cfg) {
        if (! is_string($key) || ! is_array($cfg)) {
            continue;
        }

        $mode = isset($cfg['mode']) ? sanitize_key((string) $cfg['mode']) : 'skip';
        if (! in_array($mode, ['skip', 'manual', 'meta'], true)) {
            continue;
        }

        $entry = ['mode' => $mode];

        if ($mode === 'manual') {
            $entry['value'] = $cfg['value'] ?? '';
        }

        if ($mode === 'meta') {
            $mk = isset($cfg['meta_key']) ? sanitize_key((string) $cfg['meta_key']) : '';
            if ($mk === '' || ! isset($allowed_meta[$mk])) {
                continue;
            }
            $entry['meta_key'] = $mk;
        }

        $clean_rows[$key] = $entry;
    }

    $payload = [
        'agent_id'   => $agent_id,
        'theme_rows' => $clean_rows,
        'updated_at' => time(),
    ];

    update_option('rch_agent_wizard_last_deployment', wp_json_encode($payload), false);
}

/**
 * @return bool|WP_Error
 */
function rch_agent_wizard_user_can_run()
{
    if (! is_multisite()) {
        return new WP_Error('rch_wizard_ms', __('Multisite only.', 'rechat-plugin'));
    }

    if (! current_user_can('manage_network_options')) {
        return new WP_Error('rch_wizard_cap', __('Insufficient permissions.', 'rechat-plugin'));
    }

    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        return new WP_Error('rch_wizard_rechat', __('You do not have access to Rechat settings.', 'rechat-plugin'));
    }

    return true;
}

/**
 * AJAX: agent meta + blog id + defs for wizard bootstrap.
 */
function rch_agent_wizard_ajax_load_agent(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    $agent_id = isset($_POST['agent_id']) ? absint($_POST['agent_id']) : 0;
    $post     = $agent_id ? get_post($agent_id) : null;

    if (! $post || $post->post_type !== 'agents') {
        wp_send_json_error(['message' => __('Invalid agent.', 'rechat-plugin')]);
    }

    $defs = rch_agent_wizard_importable_field_defs_resolved();
    $meta = [];

    foreach (array_keys($defs) as $key) {
        $meta[ $key ] = rch_agent_wizard_get_import_source_value($agent_id, $key);
    }

    $blog_id = function_exists('rch_multisite_get_agent_blog_id') ? rch_multisite_get_agent_blog_id($agent_id) : 0;

    $current_theme   = rch_agent_wizard_read_destination_theme_options((int) $blog_id);
    $last_deployment = rch_agent_wizard_read_destination_last_deployment((int) $blog_id);

    update_user_meta(get_current_user_id(), 'rch_agent_wizard_last_agent_id', $agent_id);

    $testimonial_rows = function_exists('rch_agent_testimonial_sync_get_source_rows')
        ? rch_agent_testimonial_sync_get_source_rows($agent_id)
        : [];

    wp_send_json_success([
        'agent_id'        => $agent_id,
        'title'           => get_the_title($post),
        'blog_id'         => $blog_id,
        'meta'            => $meta,
        'defs'            => $defs,
        'theme_keys'      => rch_agent_wizard_theme_key_labels(),
        'current_theme'   => $current_theme,
        'last_deployment' => $last_deployment,
        'testimonials'    => [
            'count' => count($testimonial_rows),
            'rows'  => $testimonial_rows,
        ],
    ]);
}

/**
 * Read the last wizard deployment row config from the agent sub-site (for re-edit in the wizard).
 *
 * Returns the row config exactly as the user picked it (mode = skip/manual/meta + value/meta_key).
 *
 * @return array{theme_rows: array<string, array{mode:string, value?:mixed, meta_key?:string}>, updated_at: int}|null
 */
function rch_agent_wizard_read_destination_last_deployment(int $blog_id): ?array
{
    if ($blog_id <= 0 || ! is_multisite()) {
        return null;
    }

    switch_to_blog($blog_id);
    $raw = get_option('rch_agent_wizard_last_deployment', '');
    restore_current_blog();

    if (! is_string($raw) || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (! is_array($decoded) || empty($decoded['theme_rows']) || ! is_array($decoded['theme_rows'])) {
        return null;
    }

    $allowed_themes = array_flip(rch_agent_wizard_allowed_theme_option_keys());
    $allowed_meta   = array_flip(array_keys(rch_agent_wizard_importable_field_defs_resolved()));
    $clean          = [];

    foreach ($decoded['theme_rows'] as $key => $cfg) {
        if (! is_string($key) || ! isset($allowed_themes[$key]) || ! is_array($cfg)) {
            continue;
        }

        $mode = isset($cfg['mode']) ? sanitize_key((string) $cfg['mode']) : 'skip';
        if (! in_array($mode, ['skip', 'manual', 'meta'], true)) {
            continue;
        }

        $entry = ['mode' => $mode];

        if ($mode === 'manual') {
            $entry['value'] = $cfg['value'] ?? '';
        }

        if ($mode === 'meta') {
            $mk = isset($cfg['meta_key']) ? sanitize_key((string) $cfg['meta_key']) : '';
            if ($mk === '' || ! isset($allowed_meta[$mk])) {
                continue;
            }
            $entry['meta_key'] = $mk;
        }

        $clean[$key] = $entry;
    }

    return [
        'theme_rows' => $clean,
        'updated_at' => isset($decoded['updated_at']) ? (int) $decoded['updated_at'] : 0,
    ];
}

/**
 * Read the agent sub-site's currently saved theme options (for re-edit in the wizard).
 *
 * Reads `storage_primary` (e.g. `pentama_options_v2`), then merges from `storage_mirror`
 * (e.g. `pentama_options_agent_website`). Only keys allowed by the wizard profile are returned.
 *
 * Tag arrays are returned as PHP arrays — wp_send_json encodes them as JSON arrays.
 *
 * @return array<string, mixed>
 */
function rch_agent_wizard_read_destination_theme_options(int $blog_id): array
{
    if ($blog_id <= 0 || ! is_multisite()) {
        return [];
    }

    $allowed = array_flip(rch_agent_wizard_allowed_theme_option_keys());

    switch_to_blog($blog_id);

    $dest_stylesheet = (string) get_option('stylesheet');
    $profile         = rch_agent_wizard_get_theme_profile($dest_stylesheet);

    $primary = isset($profile['storage_primary']) && is_string($profile['storage_primary'])
        ? $profile['storage_primary']
        : 'pentama_options_v2';
    $mirror  = array_key_exists('storage_mirror', $profile) ? $profile['storage_mirror'] : 'pentama_options_agent_website';

    $primary_data = get_option($primary, []);
    if (! is_array($primary_data)) {
        $primary_data = [];
    }

    $mirror_data = [];
    if (is_string($mirror) && $mirror !== '' && $mirror !== $primary) {
        $maybe = get_option($mirror, []);
        if (is_array($maybe)) {
            $mirror_data = $maybe;
        }
    }

    restore_current_blog();

    // Primary wins on key conflict; mirror fills missing keys (legacy data).
    $merged = array_merge($mirror_data, $primary_data);

    $out = [];
    foreach ($merged as $key => $value) {
        if (! is_string($key) || ! isset($allowed[$key])) {
            continue;
        }
        $out[$key] = $value;
    }

    return $out;
}

/**
 * AJAX: save draft to user meta.
 */
function rch_agent_wizard_ajax_save_draft(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    $raw = isset($_POST['draft']) ? wp_unslash($_POST['draft']) : '';
    if (! is_string($raw) || strlen($raw) > 500000) {
        wp_send_json_error(['message' => __('Draft too large or invalid.', 'rechat-plugin')]);
    }

    $decoded = json_decode($raw, true);
    if (! is_array($decoded)) {
        wp_send_json_error(['message' => __('Draft must be JSON object.', 'rechat-plugin')]);
    }

    $save_src = isset($decoded['_draftSaveSrc']) && (string) $decoded['_draftSaveSrc'] === 'auto' ? 'auto' : 'user';
    unset($decoded['_draftSaveSrc']);

    $draft_agent_id = isset($decoded['agentId']) ? absint($decoded['agentId']) : 0;
    if (
        $draft_agent_id > 0
        && isset($decoded['themeRows'])
        && is_array($decoded['themeRows'])
        && $decoded['themeRows'] !== []
    ) {
        $decoded['themeRows'] = rch_agent_wizard_reconcile_draft_theme_rows($draft_agent_id, $decoded['themeRows']);
    }

    $uid      = get_current_user_id();
    $existing = get_user_meta($uid, RCH_AGENT_WIZARD_DRAFT_META, true);
    $prev     = is_string($existing) && $existing !== '' ? json_decode($existing, true) : null;

    // Autosave must not wipe stored row config (empty collectThemeRows from a second tab, race before hydrate, etc.).
    if (
        $save_src === 'auto'
        && isset($decoded['themeRows'])
        && is_array($decoded['themeRows'])
        && $decoded['themeRows'] === []
        && is_array($prev)
        && isset($prev['themeRows'])
        && is_array($prev['themeRows'])
        && $prev['themeRows'] !== []
    ) {
        $decoded['themeRows'] = $prev['themeRows'];
    }

    if (
        $save_src === 'auto'
        && is_array($prev)
        && ! empty($prev['meta'])
        && is_array($prev['meta'])
    ) {
        $incoming_meta = isset($decoded['meta']) && is_array($decoded['meta']) ? $decoded['meta'] : [];
        if ($incoming_meta === []) {
            $decoded['meta'] = $prev['meta'];
        }
    }

    if (
        $save_src === 'auto'
        && is_array($prev)
        && isset($prev['broadcastPostIds'])
        && is_array($prev['broadcastPostIds'])
        && $prev['broadcastPostIds'] !== []
    ) {
        $inc_bc = isset($decoded['broadcastPostIds']) && is_array($decoded['broadcastPostIds'])
            ? $decoded['broadcastPostIds']
            : [];
        if ($inc_bc === []) {
            $decoded['broadcastPostIds'] = $prev['broadcastPostIds'];
        }
    }

    if (
        $save_src === 'auto'
        && is_array($prev)
        && isset($prev['mwMenuTermIds'])
        && is_array($prev['mwMenuTermIds'])
        && $prev['mwMenuTermIds'] !== []
    ) {
        $inc_mw = isset($decoded['mwMenuTermIds']) && is_array($decoded['mwMenuTermIds'])
            ? $decoded['mwMenuTermIds']
            : [];
        if ($inc_mw === []) {
            $decoded['mwMenuTermIds'] = $prev['mwMenuTermIds'];
        }
    }

    if (
        $save_src === 'auto'
        && is_array($prev)
        && isset($prev['menuBuilderItems'])
        && is_array($prev['menuBuilderItems'])
        && $prev['menuBuilderItems'] !== []
    ) {
        $inc_mb = isset($decoded['menuBuilderItems']) && is_array($decoded['menuBuilderItems'])
            ? $decoded['menuBuilderItems']
            : [];
        if ($inc_mb === []) {
            $decoded['menuBuilderItems'] = $prev['menuBuilderItems'];
        }
    }

    /**
     * @param array<string, mixed> $decoded Draft about to be stored.
     * @param array<string, mixed>|null $prev  Previous decoded draft or null.
     * @param string                 $save_src `auto` or `user`.
     */
    $decoded = apply_filters('rch_agent_wizard_save_draft_data', $decoded, $prev, $save_src);

    if (! is_array($decoded)) {
        wp_send_json_error(['message' => __('Draft save was rejected.', 'rechat-plugin')]);
    }

    update_user_meta($uid, RCH_AGENT_WIZARD_DRAFT_META, wp_json_encode($decoded));

    wp_send_json_success(['message' => __('Draft saved.', 'rechat-plugin')]);
}

/**
 * AJAX: load draft from user meta.
 */
function rch_agent_wizard_ajax_load_draft(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    $json = get_user_meta(get_current_user_id(), RCH_AGENT_WIZARD_DRAFT_META, true);
    if (! is_string($json) || $json === '') {
        wp_send_json_success(['draft' => null]);
    }

    $decoded = json_decode($json, true);
    wp_send_json_success(['draft' => is_array($decoded) ? $decoded : null]);
}

/**
 * AJAX: deploy merged options to agent sub-site.
 */
function rch_agent_wizard_ajax_deploy(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error($err = rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => $err->get_error_message()], 403);
    }

    $agent_id = isset($_POST['agent_id']) ? absint($_POST['agent_id']) : 0;

    $theme_rows_raw = isset($_POST['theme_rows_json']) ? wp_unslash($_POST['theme_rows_json']) : '';
    if (! is_string($theme_rows_raw) || $theme_rows_raw === '') {
        wp_send_json_error(['message' => __('Missing theme configuration.', 'rechat-plugin')]);
    }
    $theme_rows = json_decode($theme_rows_raw, true);
    if (! is_array($theme_rows)) {
        wp_send_json_error(['message' => __('Invalid theme configuration JSON.', 'rechat-plugin')]);
    }

    $allowed_themes = array_flip(rch_agent_wizard_allowed_theme_option_keys());
    $allowed_meta   = array_flip(array_keys(rch_agent_wizard_importable_field_defs_resolved()));
    $clean_rows     = [];
    foreach ($theme_rows as $tk => $cfg) {
        if (! is_string($tk) || ! isset($allowed_themes[ $tk ]) || ! is_array($cfg)) {
            continue;
        }
        $mode = isset($cfg['mode']) ? sanitize_key((string) $cfg['mode']) : 'skip';
        if (! in_array($mode, ['skip', 'manual', 'meta'], true)) {
            continue;
        }
        $entry = ['mode' => $mode];
        if ($mode === 'manual') {
            $entry['value'] = $cfg['value'] ?? '';
        }
        if ($mode === 'meta') {
            $mk = isset($cfg['meta_key']) ? sanitize_key((string) $cfg['meta_key']) : '';
            if ($mk === '' || ! isset($allowed_meta[ $mk ])) {
                continue;
            }
            $entry['meta_key'] = $mk;
        }
        $clean_rows[ $tk ] = $entry;
    }

    $scope = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : 'single';
    if ($scope === 'all') {
        $bulk = rch_agent_wizard_deploy_all_agent_subsites($clean_rows);
        $msg  = sprintf(
            /* translators: 1: updated count, 2: skipped count */
            __('Updated %1$d agent sub-site(s). Skipped %2$d (no linked site).', 'rechat-plugin'),
            $bulk['updated'],
            $bulk['skipped']
        );
        wp_send_json_success([
            'message' => $msg,
            'updated' => $bulk['updated'],
            'skipped' => $bulk['skipped'],
            'errors'  => $bulk['errors'],
        ]);
        return;
    }

    if (! $agent_id) {
        wp_send_json_error(['message' => __('Select an agent or choose “All agent sub-sites”.', 'rechat-plugin')]);
    }

    $result = rch_agent_wizard_deploy_to_agent_blog($agent_id, $clean_rows);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    update_user_meta(get_current_user_id(), 'rch_agent_wizard_last_agent_id', $agent_id);

    $testimonial_summary = null;
    if (function_exists('rch_agent_wizard_sync_testimonials_for_agent')) {
        $ts = rch_agent_wizard_sync_testimonials_for_agent($agent_id);
        if (! is_wp_error($ts)) {
            $testimonial_summary = $ts;
        }
    }

    $message = __('Theme options were saved on the agent sub-site.', 'rechat-plugin');
    if (is_array($testimonial_summary)) {
        $message .= ' ' . sprintf(
            /* translators: 1: created, 2: updated, 3: removed */
            __('Testimonials: %1$d created, %2$d updated, %3$d removed.', 'rechat-plugin'),
            (int) $testimonial_summary['created'],
            (int) $testimonial_summary['updated'],
            (int) $testimonial_summary['deleted']
        );
    }

    wp_send_json_success([
        'message'              => $message,
        'blog_id'              => function_exists('rch_multisite_get_agent_blog_id') ? rch_multisite_get_agent_blog_id($agent_id) : 0,
        'testimonial_summary'  => $testimonial_summary,
    ]);
}

/**
 * Whether the wizard should show the Broadcast content step (ThreeWP Broadcast network-active).
 */
function rch_agent_wizard_broadcast_step_enabled(): bool
{
    if (! is_multisite()) {
        return false;
    }

    return function_exists('rch_multisite_broadcast_plugin_active') && rch_multisite_broadcast_plugin_active();
}

/**
 * Blog IDs linked from published agent posts (excludes template $source).
 *
 * @return int[]
 */
function rch_agent_wizard_collect_agent_site_blog_ids(int $source): array
{
    $ids = [];
    if (! function_exists('rch_multisite_get_agent_blog_id')) {
        return $ids;
    }
    $agent_ids = get_posts(
        [
            'post_type'      => 'agents',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]
    );
    foreach ($agent_ids as $aid) {
        $bid = rch_multisite_get_agent_blog_id((int) $aid);
        if ($bid > 0 && $bid !== $source) {
            $ids[] = $bid;
        }
    }

    return $ids;
}

/**
 * Blog IDs linked from published office posts (excludes template $source).
 *
 * @return int[]
 */
function rch_agent_wizard_collect_office_site_blog_ids(int $source): array
{
    $ids = [];
    if (! post_type_exists('offices') || ! function_exists('rch_multisite_get_office_blog_id')) {
        return $ids;
    }
    $office_ids = get_posts(
        [
            'post_type'      => 'offices',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]
    );
    foreach ($office_ids as $oid) {
        $bid = rch_multisite_get_office_blog_id((int) $oid);
        if ($bid > 0 && $bid !== $source) {
            $ids[] = $bid;
        }
    }

    return $ids;
}

/**
 * Target blog IDs for wizard Broadcast / menus-widgets actions.
 *
 * @param string $target_mode `agent_only` | `office_only` | `all_subsites` | `agent_office` (legacy: agents + offices).
 * @return int[]
 */
function rch_agent_wizard_broadcast_target_blog_ids(string $target_mode): array
{
    $source = function_exists('rch_multisite_broadcast_source_blog_id')
        ? rch_multisite_broadcast_source_blog_id()
        : (int) get_main_site_id();
    $ids = [];

    switch ($target_mode) {
        case 'all_subsites':
            $sites = get_sites(
                [
                    'number'   => 5000,
                    'spam'     => 0,
                    'deleted'  => 0,
                    'archived' => 0,
                ]
            );
            foreach ($sites as $site) {
                $bid = (int) $site->blog_id;
                if ($bid > 0 && $bid !== $source) {
                    $ids[] = $bid;
                }
            }
            break;
        case 'office_only':
            $ids = rch_agent_wizard_collect_office_site_blog_ids($source);
            break;
        case 'agent_office':
            $ids = array_merge(
                rch_agent_wizard_collect_agent_site_blog_ids($source),
                rch_agent_wizard_collect_office_site_blog_ids($source)
            );
            break;
        case 'agent_only':
        default:
            $ids = rch_agent_wizard_collect_agent_site_blog_ids($source);
            break;
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

    /**
     * @param int[]  $ids
     * @param string $target_mode
     * @param int    $source
     */
    return (array) apply_filters('rch_agent_wizard_broadcast_target_blog_ids', $ids, $target_mode, $source);
}

/**
 * Run Broadcast API for posts/pages on the source blog toward many targets.
 *
 * @param int[]  $post_ids    Parent post IDs on the source blog.
 * @param string $target_mode See rch_agent_wizard_broadcast_target_blog_ids().
 * @return array{ok:int,fail:int,errors:string[],target_count:int}
 */
function rch_agent_wizard_broadcast_posts_to_targets(array $post_ids, string $target_mode): array
{
    $out = [
        'ok'           => 0,
        'fail'         => 0,
        'errors'       => [],
        'target_count' => 0,
    ];

    if (! rch_agent_wizard_broadcast_step_enabled() || ! function_exists('ThreeWP_Broadcast')) {
        $out['errors'][] = __('Broadcast is not available.', 'rechat-plugin');

        return $out;
    }

    $post_ids = array_values(
        array_unique(
            array_filter(
                array_map('absint', $post_ids),
                static function (int $id): bool {
                    return $id > 0;
                }
            )
        )
    );

    if ($post_ids === []) {
        $out['errors'][] = __('No posts selected.', 'rechat-plugin');

        return $out;
    }

    $targets = rch_agent_wizard_broadcast_target_blog_ids($target_mode);
    $out['target_count'] = count($targets);

    if ($targets === []) {
        $out['errors'][] = __('No target blogs found for this mode.', 'rechat-plugin');

        return $out;
    }

    $max_targets = (int) apply_filters('rch_agent_wizard_broadcast_max_targets', 500);
    if ($max_targets > 0 && count($targets) > $max_targets) {
        $out['errors'][] = sprintf(
            /* translators: 1: current target count, 2: max allowed */
            __('Too many target sites (%1$d). Maximum is %2$d. Use the agent/office mode, raise the limit via filter, or split the network.', 'rechat-plugin'),
            count($targets),
            $max_targets
        );

        return $out;
    }

    $source = rch_multisite_broadcast_source_blog_id();
    $runner  = rch_multisite_broadcast_runner_user_id();
    $prev    = get_current_user_id();

    wp_set_current_user($runner);

    switch_to_blog($source);

    /** @var \threewp_broadcast\ThreeWP_Broadcast $broadcast */
    $broadcast = ThreeWP_Broadcast();
    $api       = $broadcast->api();

    if (apply_filters('rch_multisite_broadcast_use_low_priority', false)) {
        $api->low_priority();
    }

    foreach ($post_ids as $pid) {
        $post = get_post($pid);
        if (! $post || ! in_array($post->post_type, ['post', 'page'], true)) {
            $out['fail']++;
            $out['errors'][] = sprintf(
                /* translators: %d: post ID */
                __('Skipped invalid post or page (ID %d).', 'rechat-plugin'),
                $pid
            );
            continue;
        }

        try {
            $api->broadcast_children($pid, $targets);
            $out['ok']++;
        } catch (Throwable $e) {
            $out['fail']++;
            $out['errors'][] = sprintf(
                /* translators: 1: post ID, 2: error message */
                __('Post %1$d: %2$s', 'rechat-plugin'),
                $pid,
                $e->getMessage()
            );
        }
    }

    restore_current_blog();
    wp_set_current_user($prev);

    return $out;
}

/**
 * AJAX: paginated posts/pages on Broadcast source blog for picker.
 */
function rch_agent_wizard_ajax_list_broadcast_posts(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    if (! rch_agent_wizard_broadcast_step_enabled()) {
        wp_send_json_error(['message' => __('Broadcast step is not available.', 'rechat-plugin')]);
    }

    $source = rch_multisite_broadcast_source_blog_id();
    $paged  = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
    $per    = isset($_POST['per_page']) ? min(50, max(5, absint($_POST['per_page']))) : 20;
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    switch_to_blog($source);

    $q = new WP_Query(
        [
            'post_type'              => ['post', 'page'],
            'post_status'            => ['publish', 'future', 'draft', 'private'],
            'posts_per_page'         => $per,
            'paged'                  => $paged,
            's'                      => $search,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_term_meta_cache' => false,
        ]
    );

    $items = [];
    foreach ($q->posts as $p) {
        if (! $p instanceof WP_Post) {
            continue;
        }
        $items[] = [
            'id'         => (int) $p->ID,
            'title'      => get_the_title($p),
            'type'       => $p->post_type,
            'type_label' => $p->post_type === 'page' ? __('Page', 'rechat-plugin') : __('Post', 'rechat-plugin'),
            'status'     => $p->post_status,
            'modified'   => mysql2date('Y-m-d H:i', $p->post_modified, false),
        ];
    }

    $max_pages = (int) $q->max_num_pages;
    $found     = (int) $q->found_posts;

    restore_current_blog();

    wp_send_json_success(
        [
            'items'      => $items,
            'paged'      => $paged,
            'max_pages'  => $max_pages,
            'found'      => $found,
            'source_id'  => $source,
            'source_url' => get_site_url($source, '/'),
        ]
    );
}

/**
 * AJAX: broadcast selected post/page IDs from source blog to targets.
 */
function rch_agent_wizard_ajax_broadcast_posts(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    if (! rch_agent_wizard_broadcast_step_enabled()) {
        wp_send_json_error(['message' => __('Broadcast step is not available.', 'rechat-plugin')]);
    }

    $raw = isset($_POST['post_ids']) ? wp_unslash($_POST['post_ids']) : '';
    if (! is_string($raw) || $raw === '') {
        wp_send_json_error(['message' => __('No posts selected.', 'rechat-plugin')]);
    }

    $post_ids = json_decode($raw, true);
    if (! is_array($post_ids)) {
        wp_send_json_error(['message' => __('Invalid post list.', 'rechat-plugin')]);
    }

    $max_batch = (int) apply_filters('rch_agent_wizard_broadcast_max_posts_per_request', 15);
    if ($max_batch < 1) {
        $max_batch = 15;
    }
    if (count($post_ids) > $max_batch) {
        wp_send_json_error(
            [
                'message' => sprintf(
                    /* translators: %d: maximum posts per request */
                    __('Too many items in one request (max %d). Run again for the rest.', 'rechat-plugin'),
                    $max_batch
                ),
            ]
        );
    }

    $mode = isset($_POST['target_mode']) ? sanitize_key(wp_unslash($_POST['target_mode'])) : 'agent_only';
    $allowed_modes = ['agent_only', 'office_only', 'all_subsites', 'agent_office'];
    if (! in_array($mode, $allowed_modes, true)) {
        $mode = 'agent_only';
    }

    $result = rch_agent_wizard_broadcast_posts_to_targets($post_ids, $mode);

    $msg = sprintf(
        /* translators: 1: success count, 2: failure count, 3: target blog count */
        __('Finished: %1$d succeeded, %2$d failed, across %3$d target site(s).', 'rechat-plugin'),
        $result['ok'],
        $result['fail'],
        $result['target_count']
    );

    wp_send_json_success(
        [
            'message'      => $msg,
            'ok'           => $result['ok'],
            'fail'         => $result['fail'],
            'target_count' => $result['target_count'],
            'errors'       => $result['errors'],
        ]
    );
}

/**
 * AJAX: list nav menus + sidebar summary on source blog for menus/widgets step.
 */
function rch_agent_wizard_ajax_list_menus_widgets(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    if (! function_exists('rch_agent_wizard_menus_widgets_source_blog_id')) {
        wp_send_json_error(['message' => __('Menus/widgets sync is unavailable.', 'rechat-plugin')]);
    }

    $source = rch_agent_wizard_menus_widgets_source_blog_id();

    switch_to_blog($source);

    $menus = [];
    foreach (wp_get_nav_menus() as $m) {
        $menus[] = [
            'term_id' => (int) $m->term_id,
            'name'    => $m->name,
            'slug'    => $m->slug,
            'count'   => (int) $m->count,
        ];
    }

    global $wp_registered_sidebars;
    $sidebars_widgets = get_option('sidebars_widgets', []);
    if (! is_array($sidebars_widgets)) {
        $sidebars_widgets = [];
    }

    $sidebars = [];
    foreach ($sidebars_widgets as $sid => $widgets) {
        if ($sid === 'wp_inactive_widgets') {
            continue;
        }
        $label = isset($wp_registered_sidebars[ $sid ]['name'])
            ? (string) $wp_registered_sidebars[ $sid ]['name']
            : (string) $sid;
        $n = is_array($widgets) ? count($widgets) : 0;
        $sidebars[] = [
            'id'    => (string) $sid,
            'label' => $label,
            'count' => $n,
        ];
    }

    restore_current_blog();

    $nav_locations = function_exists('rch_agent_wizard_get_nav_menu_locations_catalog')
        ? rch_agent_wizard_get_nav_menu_locations_catalog($source)
        : [];

    wp_send_json_success(
        [
            'source_id'     => $source,
            'source_url'    => get_site_url($source, '/'),
            'menus'         => $menus,
            'sidebars'      => $sidebars,
            'nav_locations' => $nav_locations,
        ]
    );
}

/**
 * AJAX: search template-site posts/pages for menu builder (title + permalink).
 */
function rch_agent_wizard_ajax_menu_builder_search_posts(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    if (! function_exists('rch_agent_wizard_menus_widgets_source_blog_id')) {
        wp_send_json_error(['message' => __('Menu builder is unavailable.', 'rechat-plugin')]);
    }

    $source = rch_agent_wizard_menus_widgets_source_blog_id();
    $paged  = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
    $per    = isset($_POST['per_page']) ? min(30, max(5, absint($_POST['per_page']))) : 15;
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    $types = (array) apply_filters('rch_agent_wizard_menu_builder_post_types', ['post', 'page']);

    switch_to_blog($source);

    $q = new WP_Query(
        [
            'post_type'              => $types,
            'post_status'            => 'publish',
            'posts_per_page'         => $per,
            'paged'                  => $paged,
            's'                      => $search,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_term_meta_cache' => false,
        ]
    );

    $items = [];
    foreach ($q->posts as $p) {
        if (! $p instanceof WP_Post) {
            continue;
        }
        $items[] = [
            'id'    => (int) $p->ID,
            'title' => get_the_title($p),
            'type'  => $p->post_type,
            'url'   => (string) get_permalink($p),
        ];
    }

    restore_current_blog();

    wp_send_json_success(
        [
            'items'     => $items,
            'paged'     => $paged,
            'max_pages' => (int) $q->max_num_pages,
            'found'     => (int) $q->found_posts,
        ]
    );
}

/**
 * AJAX: resolve post IDs on the menus/widgets source blog for menu builder (same shape as search).
 */
function rch_agent_wizard_ajax_menu_builder_posts_by_ids(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    if (! function_exists('rch_agent_wizard_menus_widgets_source_blog_id')) {
        wp_send_json_error(['message' => __('Menu builder is unavailable.', 'rechat-plugin')]);
    }

    $raw = isset($_POST['post_ids']) ? wp_unslash($_POST['post_ids']) : '';
    $arr = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
    if (! is_array($arr)) {
        wp_send_json_error(['message' => __('Invalid request.', 'rechat-plugin')]);
    }

    $ids = [];
    foreach ($arr as $id) {
        $n = absint($id);
        if ($n > 0) {
            $ids[] = $n;
        }
    }
    $ids = array_values(array_unique($ids));
    $max  = (int) apply_filters('rch_agent_wizard_menu_builder_max_items', 80);
    $ids  = array_slice($ids, 0, max(1, $max));

    if ($ids === []) {
        wp_send_json_success(['items' => []]);
    }

    $source = rch_agent_wizard_menus_widgets_source_blog_id();
    $types  = (array) apply_filters('rch_agent_wizard_menu_builder_post_types', ['post', 'page']);

    switch_to_blog($source);

    $q = new WP_Query(
        [
            'post_type'              => $types,
            'post_status'            => 'publish',
            'post__in'               => $ids,
            'posts_per_page'         => count($ids),
            'orderby'                => 'post__in',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_term_meta_cache' => false,
        ]
    );

    $items = [];
    foreach ($q->posts as $p) {
        if (! $p instanceof WP_Post) {
            continue;
        }
        $items[] = [
            'id'    => (int) $p->ID,
            'title' => get_the_title($p),
            'type'  => $p->post_type,
            'url'   => (string) get_permalink($p),
        ];
    }

    restore_current_blog();

    wp_send_json_success(['items' => $items]);
}

/**
 * AJAX: create flat menu from builder items + assign locations on all targets.
 */
function rch_agent_wizard_ajax_create_builder_menu(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    if (! function_exists('rch_agent_wizard_push_builder_menu_to_targets')) {
        wp_send_json_error(['message' => __('Menu builder is unavailable.', 'rechat-plugin')]);
    }

    $menu_name = isset($_POST['menu_name']) ? sanitize_text_field(wp_unslash($_POST['menu_name'])) : '';
    $raw_items = isset($_POST['items']) ? wp_unslash($_POST['items']) : '';
    $raw_locs  = isset($_POST['location_slugs']) ? wp_unslash($_POST['location_slugs']) : '';

    $items = is_string($raw_items) && $raw_items !== '' ? json_decode($raw_items, true) : [];
    if (! is_array($items)) {
        wp_send_json_error(['message' => __('Invalid menu items JSON.', 'rechat-plugin')]);
    }

    $location_slugs = is_string($raw_locs) && $raw_locs !== '' ? json_decode($raw_locs, true) : [];
    if (! is_array($location_slugs)) {
        $location_slugs = [];
    }
    $location_slugs = array_values(
        array_filter(
            array_map('sanitize_key', $location_slugs),
            static function (string $s): bool {
                return $s !== '';
            }
        )
    );

    $mode = isset($_POST['target_mode']) ? sanitize_key(wp_unslash($_POST['target_mode'])) : 'agent_only';
    $allowed_modes = ['agent_only', 'office_only', 'all_subsites', 'agent_office'];
    if (! in_array($mode, $allowed_modes, true)) {
        $mode = 'agent_only';
    }

    $result = rch_agent_wizard_push_builder_menu_to_targets($mode, $menu_name, $items, $location_slugs);

    if ($result['ok'] === 0 && $result['fail'] === 0 && $result['errors'] !== []) {
        wp_send_json_error(['message' => implode(' ', $result['errors'])]);
    }

    wp_send_json_success(
        [
            'message' => sprintf(
                /* translators: 1: success count, 2: fail count */
                __('Created menu on %1$d site(s). Failed: %2$d.', 'rechat-plugin'),
                $result['ok'],
                $result['fail']
            ),
            'ok'     => $result['ok'],
            'failed' => $result['fail'],
            'errors' => $result['errors'],
        ]
    );
}

/**
 * AJAX: clone selected menus + optionally all widget options to target blogs.
 */
function rch_agent_wizard_ajax_apply_menus_widgets(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    if (! function_exists('rch_agent_wizard_menus_widgets_source_blog_id')) {
        wp_send_json_error(['message' => __('Menus/widgets sync is unavailable.', 'rechat-plugin')]);
    }

    $raw = isset($_POST['menu_term_ids']) ? wp_unslash($_POST['menu_term_ids']) : '';
    $menu_ids = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            wp_send_json_error(['message' => __('Invalid menu list.', 'rechat-plugin')]);
        }
        $menu_ids = $decoded;
    }

    $menu_ids = array_values(
        array_unique(
            array_filter(
                array_map('absint', $menu_ids),
                static function (int $id): bool {
                    return $id > 0;
                }
            )
        )
    );

    $copy_widgets = ! empty($_POST['copy_widgets']);

    if ($menu_ids === [] && ! $copy_widgets) {
        wp_send_json_error(['message' => __('Select at least one menu, or enable widget copy.', 'rechat-plugin')]);
    }

    $mode = isset($_POST['target_mode']) ? sanitize_key(wp_unslash($_POST['target_mode'])) : 'agent_only';
    $allowed_modes = ['agent_only', 'office_only', 'all_subsites', 'agent_office'];
    if (! in_array($mode, $allowed_modes, true)) {
        $mode = 'agent_only';
    }

    $targets = rch_agent_wizard_broadcast_target_blog_ids($mode);
    $source  = rch_agent_wizard_menus_widgets_source_blog_id();

    $targets = array_values(
        array_filter(
            $targets,
            static function (int $bid) use ($source): bool {
                return $bid > 0 && $bid !== $source;
            }
        )
    );

    if ($targets === []) {
        wp_send_json_error(['message' => __('No target blogs found for this mode.', 'rechat-plugin')]);
    }

    $max_targets = (int) apply_filters('rch_agent_wizard_menus_widgets_max_targets', 500);
    if ($max_targets > 0 && count($targets) > $max_targets) {
        wp_send_json_error(
            [
                'message' => sprintf(
                    /* translators: 1: target count, 2: max */
                    __('Too many target sites (%1$d). Maximum is %2$d.', 'rechat-plugin'),
                    count($targets),
                    $max_targets
                ),
            ]
        );
    }

    $widget_export = $copy_widgets ? rch_agent_wizard_export_widget_options($source) : [];

    $ok     = 0;
    $failed = 0;
    $errors = [];

    foreach ($targets as $blog_id) {
        $r = rch_agent_wizard_sync_menus_widgets_to_blog($source, $blog_id, $menu_ids, $copy_widgets, $widget_export);
        if (is_wp_error($r)) {
            $failed++;
            $errors[] = sprintf(
                /* translators: 1: blog ID, 2: error message */
                __('Blog %1$d: %2$s', 'rechat-plugin'),
                $blog_id,
                $r->get_error_message()
            );
        } else {
            $ok++;
        }
    }

    wp_send_json_success(
        [
            'message' => sprintf(
                /* translators: 1: success count, 2: fail count */
                __('Updated %1$d site(s). Failed: %2$d.', 'rechat-plugin'),
                $ok,
                $failed
            ),
            'ok'      => $ok,
            'failed'  => $failed,
            'errors'  => $errors,
            'targets' => count($targets),
        ]
    );
}

add_action('wp_ajax_rch_agent_wizard_load_agent', 'rch_agent_wizard_ajax_load_agent');
add_action('wp_ajax_rch_agent_wizard_save_draft', 'rch_agent_wizard_ajax_save_draft');
add_action('wp_ajax_rch_agent_wizard_load_draft', 'rch_agent_wizard_ajax_load_draft');
add_action('wp_ajax_rch_agent_wizard_deploy', 'rch_agent_wizard_ajax_deploy');
add_action('wp_ajax_rch_agent_wizard_list_broadcast_posts', 'rch_agent_wizard_ajax_list_broadcast_posts');
add_action('wp_ajax_rch_agent_wizard_broadcast_posts', 'rch_agent_wizard_ajax_broadcast_posts');
add_action('wp_ajax_rch_agent_wizard_list_menus_widgets', 'rch_agent_wizard_ajax_list_menus_widgets');
add_action('wp_ajax_rch_agent_wizard_apply_menus_widgets', 'rch_agent_wizard_ajax_apply_menus_widgets');
add_action('wp_ajax_rch_agent_wizard_menu_builder_search', 'rch_agent_wizard_ajax_menu_builder_search_posts');
add_action('wp_ajax_rch_agent_wizard_menu_builder_posts_by_ids', 'rch_agent_wizard_ajax_menu_builder_posts_by_ids');
add_action('wp_ajax_rch_agent_wizard_create_builder_menu', 'rch_agent_wizard_ajax_create_builder_menu');

/**
 * Enqueue wizard assets on Rechat settings tab.
 *
 * @param string $hook Page hook.
 */
function rch_agent_wizard_enqueue_assets(string $hook): void
{
    if ($hook !== 'toplevel_page_rechat-setting') {
        return;
    }

    if (! is_multisite()) {
        return;
    }

    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    if ($tab !== 'agent-site-wizard') {
        return;
    }

    if (! defined('RCH_PLUGIN_URL') || ! defined('RCH_VERSION')) {
        return;
    }

    wp_enqueue_media();

    wp_enqueue_style(
        'rch-agent-site-wizard',
        RCH_PLUGIN_URL . 'assets/css/rch-agent-site-wizard.css',
        [],
        RCH_VERSION
    );

    wp_enqueue_script(
        'rch-agent-site-wizard',
        RCH_PLUGIN_URL . 'assets/js/rch-agent-site-deploy-wizard.js',
        ['jquery', 'media-upload', 'media-views'],
        RCH_VERSION,
        true
    );

    $labels = rch_agent_wizard_theme_key_labels();
    foreach (rch_agent_wizard_allowed_theme_option_keys() as $slug) {
        if (! isset($labels[ $slug ])) {
            $labels[ $slug ] = $slug;
        }
    }
    $theme_keys = [];
    foreach ($labels as $slug => $label) {
        if (! in_array($slug, rch_agent_wizard_allowed_theme_option_keys(), true)) {
            continue;
        }
        $theme_keys[] = ['slug' => $slug, 'label' => $label];
    }
    usort(
        $theme_keys,
        static function (array $a, array $b): int {
            return strcasecmp($a['label'], $b['label']);
        }
    );

    $metabox_labels = [];
    foreach (rch_agent_wizard_importable_field_defs_resolved() as $mk => $def) {
        $metabox_labels[ $mk ] = $def['label'];
    }

    $ui_ss   = rch_agent_wizard_wizard_ui_stylesheet();
    $ui_prof = rch_agent_wizard_get_theme_profile($ui_ss);
    $ui_name = wp_get_theme($ui_ss)->exists() ? wp_get_theme($ui_ss)->get('Name') : $ui_ss;
    $stor    = $ui_prof['storage_primary'] ?? 'pentama_options_v2';
    $stor_m  = isset($ui_prof['storage_mirror']) && is_string($ui_prof['storage_mirror']) ? $ui_prof['storage_mirror'] : '';

    $bc_step   = rch_agent_wizard_broadcast_step_enabled();
    $bc_source = $bc_step && function_exists('rch_multisite_broadcast_source_blog_id')
        ? rch_multisite_broadcast_source_blog_id()
        : 0;
    $bc_name   = $bc_source ? (string) get_blog_option($bc_source, 'blogname', '') : '';
    $bc_counts = [
        'agent_only'   => $bc_step ? count(rch_agent_wizard_broadcast_target_blog_ids('agent_only')) : 0,
        'office_only'  => $bc_step ? count(rch_agent_wizard_broadcast_target_blog_ids('office_only')) : 0,
        'all_subsites' => $bc_step ? count(rch_agent_wizard_broadcast_target_blog_ids('all_subsites')) : 0,
    ];

    $mw_source = function_exists('rch_agent_wizard_menus_widgets_source_blog_id')
        ? rch_agent_wizard_menus_widgets_source_blog_id()
        : (int) get_main_site_id();
    $mw_name   = $mw_source ? (string) get_blog_option($mw_source, 'blogname', '') : '';
    $mw_counts = [
        'agent_only'   => count(rch_agent_wizard_broadcast_target_blog_ids('agent_only')),
        'office_only'  => count(rch_agent_wizard_broadcast_target_blog_ids('office_only')),
        'all_subsites' => count(rch_agent_wizard_broadcast_target_blog_ids('all_subsites')),
    ];

    $last_agent_id = (int) get_user_meta(get_current_user_id(), 'rch_agent_wizard_last_agent_id', true);
    if ($last_agent_id > 0) {
        $last_post = get_post($last_agent_id);
        if (! $last_post || $last_post->post_type !== 'agents') {
            $last_agent_id = 0;
        }
    }

    wp_localize_script(
        'rch-agent-site-wizard',
        'rchAgentWizard',
        [
            'ajaxurl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce(RCH_AGENT_WIZARD_NONCE_ACTION),
            'themeKeys'      => $theme_keys,
            'themeImportMap' => rch_agent_wizard_theme_to_meta_import_map(),
            'metaboxLabels'  => $metabox_labels,
            'bulkCount'      => rch_agent_wizard_count_agents_with_subsites(),
            'lastAgentId'    => $last_agent_id,
            'broadcastStep'  => $bc_step,
            'broadcastSource' => [
                'blog_id' => $bc_source,
                'label'   => $bc_name !== '' ? $bc_name : sprintf(/* translators: %d: blog ID */ __('Blog %d', 'rechat-plugin'), $bc_source),
            ],
            'broadcastTargetCounts' => $bc_counts,
            'menusWidgetsTargetCounts' => $mw_counts,
            'menusWidgetsSource' => [
                'blog_id' => $mw_source,
                'label'   => $mw_name !== '' ? $mw_name : sprintf(/* translators: %d: blog ID */ __('Blog %d', 'rechat-plugin'), $mw_source),
            ],
            'targetTheme'    => [
                'stylesheet'      => $ui_ss,
                'name'            => $ui_name,
                'optionPrimary'   => $stor,
                'optionMirror'    => $stor_m,
            ],
            'strings'        => [
                'pickAgent'       => __('Select an agent and load data, or switch to “All agent sub-sites”.', 'rechat-plugin'),
                'noBlog'          => __('This agent has no sub-site yet. Provision it under the Multisite tab.', 'rechat-plugin'),
                'bulkNoSites'     => __('No agent sub-sites exist yet. Provision sites under Multisite first.', 'rechat-plugin'),
                'deployOk'        => __('Deployment finished.', 'rechat-plugin'),
                'deployFail'      => __('Deployment failed.', 'rechat-plugin'),
                'bulkPreview'     => __('Metabox-driven values differ per agent; preview uses the loaded agent only.', 'rechat-plugin'),
                'previewHeading'  => __('What will change', 'rechat-plugin'),
                'previewEmpty'    => __('Nothing to deploy yet — every theme row is set to “Do not change”. Go back and choose manual values or agent profile fields.', 'rechat-plugin'),
                'badgeManual'     => __('Manual value', 'rechat-plugin'),
                'badgeMeta'       => __('From agent profile', 'rechat-plugin'),
                'valuePreview'    => __('Preview', 'rechat-plugin'),
                'techToggle'      => __('Show technical JSON (advanced)', 'rechat-plugin'),
                'scopeSingle'     => __('Single agent', 'rechat-plugin'),
                'scopeAll'        => __('All agent sub-sites', 'rechat-plugin'),
                'sitesCount'      => /* translators: %d: count */ __('%d sites will be updated.', 'rechat-plugin'),
                'bcStepTitle'     => __('Broadcast content', 'rechat-plugin'),
                'bcStepLead'      => __('Choose posts and pages on the Broadcast source site, then push copies to the selected target blogs using ThreeWP Broadcast.', 'rechat-plugin'),
                'bcSourceLine'     => __('Source: %s (blog ID %d)', 'rechat-plugin'),
                'bcTargetAgents'   => __('Agent sub-sites only (%d blogs)', 'rechat-plugin'),
                'bcTargetOffices'  => __('Office sub-sites only (%d blogs)', 'rechat-plugin'),
                'bcTargetAll'      => __('All network sub-sites except source (%d blogs)', 'rechat-plugin'),
                'bcSearch'        => __('Search titles…', 'rechat-plugin'),
                'bcLoad'          => __('Load list', 'rechat-plugin'),
                'bcPrev'          => __('Previous page', 'rechat-plugin'),
                'bcNext'          => __('Next page', 'rechat-plugin'),
                'bcSelectAll'     => __('Select all on this page', 'rechat-plugin'),
                'bcClearPage'     => __('Clear page selection', 'rechat-plugin'),
                'bcRun'           => __('Broadcast selected', 'rechat-plugin'),
                'bcNoneSelected'  => __('Select at least one post or page.', 'rechat-plugin'),
                'bcLoading'       => __('Loading…', 'rechat-plugin'),
                'bcEmpty'         => __('No posts or pages found.', 'rechat-plugin'),
                'bcColTitle'      => __('Title', 'rechat-plugin'),
                'bcColType'       => __('Type', 'rechat-plugin'),
                'bcColStatus'     => __('Status', 'rechat-plugin'),
                'bcColModified'   => __('Modified', 'rechat-plugin'),
                'mwSourceLine'     => __('Template site: %s (blog ID %d)', 'rechat-plugin'),
                'mwTargetAgents'   => __('Agent sub-sites only (%d blogs)', 'rechat-plugin'),
                'mwTargetOffices'  => __('Office sub-sites only (%d blogs)', 'rechat-plugin'),
                'mwTargetAll'      => __('All network sub-sites except template (%d blogs)', 'rechat-plugin'),
                'mwLoad'          => __('Load menus & sidebars', 'rechat-plugin'),
                'mwApplyNone'     => __('Select at least one menu, or tick “Copy all widget settings”.', 'rechat-plugin'),
                'mwMenusLegend'   => __('Menus', 'rechat-plugin'),
                'mwSidebarLine'   => __('Sidebars on template: %s', 'rechat-plugin'),
                'mbHeading'       => __('Build new menu for targets', 'rechat-plugin'),
                'mbLead'          => __('Add links from template posts/pages, custom URLs, then pick theme display locations. Uses the same target scope as above. Links use the template permalink (works best with Broadcast-matched content).', 'rechat-plugin'),
                'mbNameLabel'     => __('New menu name', 'rechat-plugin'),
                'mbNamePh'        => __('e.g. Main navigation', 'rechat-plugin'),
                'mbSearchLabel'   => __('Add from template content', 'rechat-plugin'),
                'mbSearchBtn'     => __('Search', 'rechat-plugin'),
                'mbCustomLabel'   => __('Custom link', 'rechat-plugin'),
                'mbUrlPh'         => __('https://…', 'rechat-plugin'),
                'mbLinkTextPh'    => __('Link text', 'rechat-plugin'),
                'mbAddCustom'     => __('Add custom link', 'rechat-plugin'),
                'mbItemsLabel'    => __('Menu structure', 'rechat-plugin'),
                'mbItemsEmpty'    => __('No links yet. Search or add a custom link.', 'rechat-plugin'),
                'mbLocLabel'      => __('Theme display locations (template)', 'rechat-plugin'),
                'mbLocEmpty'      => __('Load menus & sidebars above to load locations, or your theme may not register menu locations.', 'rechat-plugin'),
                'mbCreate'        => __('Create menu on all targets', 'rechat-plugin'),
                'mbNeedName'      => __('Enter a menu name.', 'rechat-plugin'),
                'mbNeedItem'      => __('Add at least one link (title and URL, or title and a template post from Add / broadcast list so each site can use its own page link).', 'rechat-plugin'),
                'mbAdd'           => __('Add', 'rechat-plugin'),
                'mbBroadcastPicksEmpty' => __('No posts or pages are checked on the Broadcast step yet.', 'rechat-plugin'),
                'mbRemove'        => __('Remove', 'rechat-plugin'),
                'mbUp'            => __('Move up', 'rechat-plugin'),
                'mbDown'          => __('Move down', 'rechat-plugin'),
                'mbPrev'          => __('Previous', 'rechat-plugin'),
                'mbNext'          => __('Next', 'rechat-plugin'),
                'testimonialsCountSingle' => /* translators: %d: count */ __('%d testimonial row(s) on this agent.', 'rechat-plugin'),
                'testimonialsCountNone'   => __('No testimonials on this agent yet. Add them in the agent editor on the main site.', 'rechat-plugin'),
                'testimonialsBulkHint'    => __('Imports testimonials for every agent that has a sub-site (uses each agent’s testimonial list).', 'rechat-plugin'),
                'testimonialsPickAgent'   => __('Select an agent and load profile first, or switch to “All agent sub-sites”.', 'rechat-plugin'),
                'testimonialsNoBlog'      => __('This agent has no sub-site yet.', 'rechat-plugin'),
            ],
        ]
    );
}

add_action('admin_enqueue_scripts', 'rch_agent_wizard_enqueue_assets', 20);

/**
 * Render wizard tab (included from menu-setting).
 */
function rch_agent_wizard_render_tab(): void
{
    if (! is_multisite()) {
        return;
    }

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        echo '<p>' . esc_html__('You do not have access to this tool.', 'rechat-plugin') . '</p>';
        return;
    }

    require RCH_PLUGIN_INCLUDES . 'multisite/views/agent-site-deploy-wizard-tab.php';
}
