<?php
/**
 * Multisite: Office sub-site theme options deploy wizard (main site admin).
 *
 * Mirrors the agent deploy wizard (includes/multisite/agent-site-deploy-wizard.php) but targets
 * office sub-sites (hub `offices` CPT linked via `_rch_office_site_id`). It intentionally reuses the
 * agent wizard's entity-agnostic building blocks so behaviour stays identical:
 *
 *  - Theme option discovery/sanitize: rch_agent_wizard_get_theme_profile(),
 *    rch_agent_wizard_sanitize_theme_options_row(), rch_agent_wizard_canonicalize_hero_title_keys(),
 *    rch_agent_wizard_flush_theme_option_cache(), rch_agent_wizard_get_import_source_value().
 *  - Broadcast + Menus/Widgets steps: the office wizard UI/JS calls the SAME `rch_agent_wizard_*`
 *    AJAX endpoints with target_mode = "office_only" (those endpoints are already multisite-scope
 *    aware). No office duplicates of that logic exist here.
 *  - Shared CSRF nonce: RCH_AGENT_WIZARD_NONCE_ACTION, so a single localized nonce authorises both
 *    the office-only endpoints below and the reused agent broadcast/menu endpoints.
 *
 * Office-specific here: office CPT field defs, office theme profile (network office default theme),
 * deploy to office blogs, and the office load/deploy/draft AJAX endpoints.
 *
 * Only active on WordPress Multisite. Loaded from index.php inside an is_multisite() guard.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! is_multisite()) {
    return;
}

/**
 * User meta key for the office wizard personal draft (JSON).
 */
const RCH_OFFICE_WIZARD_DRAFT_META = 'rch_office_site_wizard_draft';

/**
 * Network (site) option holding the last DEPLOYED office theme-option configuration (JSON).
 */
const RCH_OFFICE_WIZARD_SHARED_DRAFT_OPTION = 'rch_office_wizard_shared_draft';

/**
 * Stylesheet slug used to build the office wizard rows (network “Default theme for office sub-sites”).
 */
function rch_office_wizard_ui_stylesheet(): string
{
    if (function_exists('rch_multisite_resolve_theme_network_default_for_offices')) {
        return (string) rch_multisite_resolve_theme_network_default_for_offices()['stylesheet'];
    }

    return (string) wp_get_theme()->get_stylesheet();
}

/**
 * Theme profile (field metadata + storage) for the office wizard UI theme.
 *
 * Delegates to the shared, entity-agnostic discovery in the agent wizard.
 *
 * @return array<string, mixed>
 */
function rch_office_wizard_get_ui_profile(): array
{
    if (! function_exists('rch_agent_wizard_get_theme_profile')) {
        return ['keys' => [], 'labels' => [], 'storage_primary' => '', 'storage_mirror' => null];
    }

    return rch_agent_wizard_get_theme_profile(rch_office_wizard_ui_stylesheet());
}

/**
 * Whether a discovered theme option key is an agent-only wizard helper key.
 *
 * The shared profile builder appends agent wizard-only keys (rch-wizard-agent-*). They are
 * meaningless in the office wizard, so we hide them from the office field list.
 */
function rch_office_wizard_is_agent_only_wizard_key(string $key): bool
{
    return stripos($key, 'rch-wizard-agent-') === 0;
}

/**
 * Allowed office theme option keys (follows the network office default theme), minus agent-only keys.
 *
 * @return list<string>
 */
function rch_office_wizard_allowed_theme_option_keys(): array
{
    $profile = rch_office_wizard_get_ui_profile();
    $keys    = isset($profile['keys']) && is_array($profile['keys']) ? $profile['keys'] : [];

    $keys = array_values(array_filter($keys, static function ($key) {
        return is_string($key) && $key !== '' && ! rch_office_wizard_is_agent_only_wizard_key($key);
    }));

    /** @var list<string> $keys */
    return apply_filters('rch_office_wizard_allowed_theme_option_keys', $keys);
}

/**
 * Union of office wizard UI keys + a destination office sub-site's own theme keys (deploy accepts both).
 *
 * @return list<string>
 */
function rch_office_wizard_resolve_deploy_allowed_keys(int $office_id): array
{
    $keys = rch_office_wizard_allowed_theme_option_keys();

    if ($office_id <= 0 || ! function_exists('rch_multisite_get_office_blog_id')) {
        return $keys;
    }

    $blog_id = (int) rch_multisite_get_office_blog_id($office_id);
    if ($blog_id <= 0) {
        return $keys;
    }

    switch_to_blog($blog_id);
    $stylesheet = (string) get_option('stylesheet');
    restore_current_blog();

    if ($stylesheet === '' || ! function_exists('rch_agent_wizard_get_theme_profile')) {
        return $keys;
    }

    $dest_keys = rch_agent_wizard_get_theme_profile($stylesheet)['keys'] ?? [];
    if (! is_array($dest_keys)) {
        $dest_keys = [];
    }
    $dest_keys = array_filter($dest_keys, static function ($k) {
        return is_string($k) && $k !== '' && ! rch_office_wizard_is_agent_only_wizard_key($k);
    });

    return array_values(array_unique(array_merge($keys, $dest_keys)));
}

/**
 * Human labels for the office wizard theme keys.
 *
 * @return array<string, string>
 */
function rch_office_wizard_theme_key_labels(): array
{
    $profile = rch_office_wizard_get_ui_profile();
    $labels  = isset($profile['labels']) && is_array($profile['labels']) ? $profile['labels'] : [];

    $out = [];
    foreach ($labels as $key => $label) {
        if (! is_string($key) || rch_office_wizard_is_agent_only_wizard_key($key)) {
            continue;
        }
        $out[$key] = $label;
    }

    return apply_filters('rch_office_wizard_theme_key_labels', $out);
}

/**
 * Importable office CPT sources: post field / meta key => label + default theme option key.
 *
 * Offices store little meta today (office name/content + office_id + address + phone). Manual
 * values + placeholders cover everything else.
 *
 * @return array<string, array{label:string, default_theme_key:string}>
 */
function rch_office_wizard_importable_field_defs(): array
{
    $defs = [
        'post_title' => [
            'label'             => __('Office name (post title)', 'rechat-plugin'),
            'default_theme_key' => '',
        ],
        'post_content' => [
            'label'             => __('Office content (post editor)', 'rechat-plugin'),
            'default_theme_key' => '',
        ],
        'office_id' => [
            'label'             => __('Rechat Office brand ID', 'rechat-plugin'),
            'default_theme_key' => '',
        ],
        'office_address' => [
            'label'             => __('Office address', 'rechat-plugin'),
            'default_theme_key' => '',
        ],
        'office_phone' => [
            'label'             => __('Office phone', 'rechat-plugin'),
            'default_theme_key' => '',
        ],
    ];

    /**
     * Filter importable office CPT fields for the office wizard.
     *
     * @param array<string, array{label:string, default_theme_key:string}> $defs
     */
    return apply_filters('rch_office_wizard_importable_field_defs', $defs);
}

/**
 * Importable office defs with default_theme_key adjusted from the office theme profile import map.
 *
 * @return array<string, array{label:string, default_theme_key:string}>
 */
function rch_office_wizard_importable_field_defs_resolved(): array
{
    $defs = rch_office_wizard_importable_field_defs();
    $map  = rch_office_wizard_get_ui_profile()['import_defaults'] ?? [];

    if (is_array($map)) {
        foreach ($map as $meta_key => $theme_key) {
            if (isset($defs[$meta_key]) && is_string($theme_key) && $theme_key !== '') {
                $defs[$meta_key]['default_theme_key'] = $theme_key;
            }
        }
    }

    return $defs;
}

/**
 * Theme option key => office field key (for wizard meta-binding restore in the localized import map).
 *
 * @return array<string, string>
 */
function rch_office_wizard_theme_to_meta_import_map(): array
{
    $out  = [];
    $defs = rch_office_wizard_importable_field_defs_resolved();

    foreach ($defs as $meta_key => $def) {
        if (! is_string($meta_key) || ! is_array($def)) {
            continue;
        }
        $theme_key = isset($def['default_theme_key']) ? (string) $def['default_theme_key'] : '';
        if ($theme_key !== '') {
            $out[$theme_key] = $meta_key;
        }
    }

    return apply_filters('rch_office_wizard_theme_to_meta_import_map', $out);
}

/**
 * Manual step: every office theme key with its UI type (mirrors the agent wizard row builder,
 * bound to the office theme profile).
 *
 * @return list<array{key:string,label:string,help?:string,type:string,media:string,options?:list<array{value:string,label:string}>}>
 */
function rch_office_wizard_manual_field_defs(): array
{
    $profile = rch_office_wizard_get_ui_profile();

    $labels          = isset($profile['labels']) && is_array($profile['labels']) ? $profile['labels'] : [];
    $field_help      = isset($profile['field_help']) && is_array($profile['field_help']) ? $profile['field_help'] : [];
    $field_order     = isset($profile['field_order']) && is_array($profile['field_order']) ? $profile['field_order'] : [];
    $select_keys     = isset($profile['select_keys']) && is_array($profile['select_keys']) ? $profile['select_keys'] : [];
    $select_opts_map = isset($profile['select_options']) && is_array($profile['select_options']) ? $profile['select_options'] : [];
    $keys            = isset($profile['keys']) && is_array($profile['keys']) ? $profile['keys'] : [];
    $rows            = [];

    $has = static function (string $bucket) use ($profile): array {
        return isset($profile[$bucket]) && is_array($profile[$bucket]) ? $profile[$bucket] : [];
    };

    foreach ($keys as $key) {
        if (! is_string($key) || rch_office_wizard_is_agent_only_wizard_key($key)) {
            continue;
        }

        $type  = 'text';
        $media = '';
        if (in_array($key, $has('textareas'), true)) {
            $type = 'textarea';
        } elseif (in_array($key, $has('textarea_json'), true) || $key === 'rch_selected_tags') {
            $type = 'textarea_json';
        } elseif (in_array($key, $has('urls'), true)) {
            $type = 'url';
        } elseif (in_array($key, $has('numbers'), true) || preg_match('/^rch-counter-\d-value$/', $key)) {
            $type = 'number';
        }
        if (in_array($key, $has('image_media'), true)) {
            $media = 'image';
        } elseif (in_array($key, $has('video_media'), true)) {
            $media = 'video';
        }

        $options = [];
        if (function_exists('rch_agent_wizard_key_uses_tags_multiselect_ui') && rch_agent_wizard_key_uses_tags_multiselect_ui($key)) {
            $type = 'tags';
            if (function_exists('rch_agent_wizard_fetch_tag_choice_strings')) {
                foreach (rch_agent_wizard_fetch_tag_choice_strings() as $t) {
                    $options[] = ['value' => $t, 'label' => $t];
                }
            }
        } elseif (in_array($key, $select_keys, true)) {
            $type = 'select';
            $opts = isset($select_opts_map[$key]) && is_array($select_opts_map[$key]) ? $select_opts_map[$key] : [];
            $is_lead_channel_key = function_exists('rch_agent_wizard_str_contains_ci')
                && (rch_agent_wizard_str_contains_ci($key, 'lead-channel') || rch_agent_wizard_str_contains_ci($key, 'lead_channel'));
            if ($opts === [] && $is_lead_channel_key && function_exists('rch_agent_wizard_fetch_lead_channel_select_options')) {
                $opts = rch_agent_wizard_fetch_lead_channel_select_options();
            }
            foreach ($opts as $opt) {
                if (is_array($opt) && isset($opt['value'], $opt['label'])) {
                    $options[] = ['value' => (string) $opt['value'], 'label' => (string) $opt['label']];
                }
            }
        }

        $row = [
            'key'   => $key,
            'label' => $labels[$key] ?? $key,
            'type'  => $type,
            'media' => $media,
        ];
        if (isset($field_help[$key]) && is_string($field_help[$key]) && $field_help[$key] !== '') {
            $row['help'] = $field_help[$key];
        }
        if ($options !== []) {
            $row['options'] = $options;
        }
        $rows[] = $row;
    }

    if ($field_order !== []) {
        $rank = [];
        foreach ($field_order as $i => $fk) {
            if (is_string($fk) && $fk !== '' && ! isset($rank[$fk])) {
                $rank[$fk] = $i;
            }
        }
        usort($rows, static function (array $a, array $b) use ($rank): int {
            $ra = $rank[$a['key']] ?? PHP_INT_MAX;
            $rb = $rank[$b['key']] ?? PHP_INT_MAX;
            if ($ra === $rb) {
                return strcasecmp($a['label'], $b['label']);
            }

            return $ra <=> $rb;
        });
    } else {
        usort($rows, static function (array $a, array $b): int {
            return strcasecmp($a['label'], $b['label']);
        });
    }

    return $rows;
}

/**
 * Manual text templates: replace {$field} tokens with office field values.
 */
function rch_office_wizard_apply_placeholders(int $office_id, string $text): string
{
    if ($text === '' || strpos($text, '{$') === false || ! function_exists('rch_agent_wizard_get_import_source_value')) {
        return $text;
    }

    $allowed = array_flip(array_keys(rch_office_wizard_importable_field_defs()));
    $allowed['post_title'] = true;

    $out = preg_replace_callback(
        '/\{\$([a-zA-Z0-9_]+)\}/',
        static function (array $m) use ($office_id, $allowed): string {
            $key = isset($m[1]) ? sanitize_key((string) $m[1]) : '';
            if ($key === '' || ! isset($allowed[$key])) {
                return $m[0];
            }

            return rch_agent_wizard_get_import_source_value($office_id, $key);
        },
        $text
    );

    return is_string($out) ? $out : $text;
}

/**
 * Build a theme option patch from per-field mode config (skip / manual / meta) for an office.
 *
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 * @param list<string>|null                                                  $allowed_keys
 * @return array<string, mixed>
 */
function rch_office_wizard_build_row_from_theme_rows(int $office_id, array $theme_rows, ?array $allowed_keys = null): array
{
    $allowed_list = is_array($allowed_keys) && $allowed_keys !== []
        ? $allowed_keys
        : rch_office_wizard_allowed_theme_option_keys();
    $allowed      = array_flip($allowed_list);
    $meta_allowed = array_flip(array_keys(rch_office_wizard_importable_field_defs()));
    $row          = [];

    foreach ($theme_rows as $theme_key => $cfg) {
        if (! is_string($theme_key) || ! isset($allowed[$theme_key]) || ! is_array($cfg)) {
            continue;
        }

        $mode = isset($cfg['mode']) ? sanitize_key((string) $cfg['mode']) : 'skip';
        if ($mode === 'skip' || $mode === '') {
            continue;
        }

        if ($mode === 'manual') {
            $v = $cfg['value'] ?? '';
            if (is_string($v)) {
                $v = rch_office_wizard_apply_placeholders($office_id, $v);
            }
            $row[$theme_key] = $v;
            continue;
        }

        if ($mode !== 'meta') {
            continue;
        }

        $meta_key = isset($cfg['meta_key']) ? sanitize_key((string) $cfg['meta_key']) : '';
        if ($meta_key === '' || ! isset($meta_allowed[$meta_key])) {
            continue;
        }

        $row[$theme_key] = function_exists('rch_agent_wizard_get_import_source_value')
            ? rch_agent_wizard_get_import_source_value($office_id, $meta_key)
            : '';
    }

    $row['rch-wizard-office-post-id'] = (string) $office_id;

    return $row;
}

/**
 * Record the last office wizard deployment on the office sub-site (must run inside switch_to_blog).
 *
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 * @param array<string, mixed>                                              $deployed_options
 */
function rch_office_wizard_record_last_deployment_in_blog(int $office_id, array $theme_rows, array $deployed_options = []): void
{
    $allowed_meta = array_flip(array_keys(rch_office_wizard_importable_field_defs_resolved()));
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
            $raw_val        = $cfg['value'] ?? '';
            $entry['value'] = is_string($raw_val) ? $raw_val : (is_scalar($raw_val) ? (string) $raw_val : '');
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

    $options_snapshot = [];
    foreach ($deployed_options as $opt_key => $opt_val) {
        if (is_string($opt_key) && $opt_key !== '') {
            $options_snapshot[$opt_key] = $opt_val;
        }
    }

    update_option('rch_office_wizard_last_deployment', wp_json_encode([
        'office_id'        => $office_id,
        'theme_rows'       => $clean_rows,
        'deployed_options' => $options_snapshot,
        'updated_at'       => time(),
    ]), false);
}

/**
 * Read the last office wizard deployment row config from the office sub-site (for re-edit).
 *
 * @return array{theme_rows: array<string, array{mode:string, value?:mixed, meta_key?:string}>, deployed_options: array<string,mixed>, updated_at: int}|null
 */
function rch_office_wizard_read_destination_last_deployment(int $blog_id): ?array
{
    if ($blog_id <= 0 || ! is_multisite()) {
        return null;
    }

    switch_to_blog($blog_id);
    $raw = get_option('rch_office_wizard_last_deployment', '');
    restore_current_blog();

    if (! is_string($raw) || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (! is_array($decoded) || empty($decoded['theme_rows']) || ! is_array($decoded['theme_rows'])) {
        return null;
    }

    $allowed_themes = array_flip(rch_office_wizard_allowed_theme_option_keys());
    $allowed_meta   = array_flip(array_keys(rch_office_wizard_importable_field_defs_resolved()));
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
            $raw_val        = $cfg['value'] ?? '';
            $entry['value'] = is_string($raw_val) ? $raw_val : (is_scalar($raw_val) ? (string) $raw_val : '');
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

    $deployed_options = [];
    if (! empty($decoded['deployed_options']) && is_array($decoded['deployed_options'])) {
        foreach ($decoded['deployed_options'] as $opt_key => $opt_val) {
            if (is_string($opt_key) && $opt_key !== '' && isset($allowed_themes[$opt_key])) {
                $deployed_options[$opt_key] = $opt_val;
            }
        }
    }

    return [
        'theme_rows'       => $clean,
        'deployed_options' => $deployed_options,
        'updated_at'       => isset($decoded['updated_at']) ? (int) $decoded['updated_at'] : 0,
    ];
}

/**
 * Read the office sub-site's currently saved theme options (for re-edit).
 *
 * @return array<string, mixed>
 */
function rch_office_wizard_read_destination_theme_options(int $blog_id): array
{
    if ($blog_id <= 0 || ! is_multisite() || ! function_exists('rch_agent_wizard_get_theme_profile')) {
        return [];
    }

    $allowed = array_flip(rch_office_wizard_allowed_theme_option_keys());

    switch_to_blog($blog_id);
    $dest_stylesheet = (string) get_option('stylesheet');
    $profile         = rch_agent_wizard_get_theme_profile($dest_stylesheet);
    $primary         = isset($profile['storage_primary']) && is_string($profile['storage_primary']) ? $profile['storage_primary'] : 'pentama_options_v2';
    $mirror          = array_key_exists('storage_mirror', $profile) ? $profile['storage_mirror'] : 'pentama_options_agent_website';

    $primary_data = get_option($primary, []);
    $primary_data = is_array($primary_data) ? $primary_data : [];

    $mirror_data = [];
    if (is_string($mirror) && $mirror !== '' && $mirror !== $primary) {
        $maybe = get_option($mirror, []);
        $mirror_data = is_array($maybe) ? $maybe : [];
    }
    restore_current_blog();

    $merged = array_merge($mirror_data, $primary_data);
    $out    = [];
    foreach ($merged as $key => $value) {
        if (is_string($key) && isset($allowed[$key])) {
            $out[$key] = $value;
        }
    }

    return $out;
}

/**
 * Count published offices that already have a linked sub-site.
 */
function rch_office_wizard_count_offices_with_subsites(): int
{
    if (! function_exists('rch_multisite_get_office_blog_id')) {
        return 0;
    }
    $ids = get_posts([
        'post_type'      => 'offices',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $n = 0;
    foreach ($ids as $oid) {
        if (rch_multisite_get_office_blog_id((int) $oid) > 0) {
            $n++;
        }
    }

    return $n;
}

/**
 * Capability gate for the office wizard (same rules as the agent wizard).
 *
 * @return true|WP_Error
 */
function rch_office_wizard_user_can_run()
{
    if (! is_multisite()) {
        return new WP_Error('rch_office_wizard_ms', __('Multisite only.', 'rechat-plugin'));
    }
    if (! current_user_can('manage_network_options')) {
        return new WP_Error('rch_office_wizard_cap', __('Insufficient permissions.', 'rechat-plugin'));
    }
    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        return new WP_Error('rch_office_wizard_rechat', __('You do not have access to Rechat settings.', 'rechat-plugin'));
    }

    return true;
}

/**
 * Deploy per-field theme rows to one office sub-site.
 *
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 * @param bool                                                              $require_cap
 * @return array|WP_Error
 */
function rch_office_wizard_deploy_to_office_blog(int $office_id, array $theme_rows, bool $require_cap = true)
{
    if (! is_multisite()) {
        return new WP_Error('rch_office_wizard_not_multisite', __('Multisite is not enabled.', 'rechat-plugin'));
    }
    if ($require_cap && ! current_user_can('manage_network_options')) {
        return new WP_Error('rch_office_wizard_cap', __('Insufficient permissions.', 'rechat-plugin'));
    }
    if (! function_exists('rch_multisite_get_office_blog_id') || ! function_exists('rch_agent_wizard_get_theme_profile')) {
        return new WP_Error('rch_office_wizard_missing', __('Multisite helpers are not loaded.', 'rechat-plugin'));
    }

    $blog_id = rch_multisite_get_office_blog_id($office_id);
    if (! $blog_id) {
        return new WP_Error('rch_office_wizard_no_blog', __('This office has no linked sub-site. Provision the site first.', 'rechat-plugin'));
    }

    $post = get_post($office_id);
    if (! $post || $post->post_type !== 'offices') {
        return new WP_Error('rch_office_wizard_bad_office', __('Invalid office post.', 'rechat-plugin'));
    }

    switch_to_blog($blog_id);

    $dest_stylesheet = (string) get_option('stylesheet');
    $profile         = rch_agent_wizard_get_theme_profile($dest_stylesheet);

    if (empty($profile['storage_primary']) || ! is_string($profile['storage_primary'])) {
        restore_current_blog();
        return new WP_Error(
            'rch_office_wizard_storage_missing',
            __('Could not detect theme option storage for this office sub-site theme. Add a rechat-agent-wizard.json manifest, or configure rch_agent_wizard_storage_config / rch_agent_wizard_theme_storage_map.', 'rechat-plugin')
        );
    }

    $allowed_deploy = rch_office_wizard_resolve_deploy_allowed_keys($office_id);
    $merged_raw     = rch_office_wizard_build_row_from_theme_rows($office_id, $theme_rows, $allowed_deploy);

    $sanitized = function_exists('rch_agent_wizard_sanitize_theme_options_row')
        ? rch_agent_wizard_sanitize_theme_options_row($merged_raw, $profile, $allowed_deploy)
        : $merged_raw;

    $key_allow = array_flip($allowed_deploy);
    if ($key_allow !== []) {
        $sanitized = array_intersect_key($sanitized, $key_allow);
    }

    $primary = (string) $profile['storage_primary'];
    $mirror  = array_key_exists('storage_mirror', $profile) ? $profile['storage_mirror'] : 'pentama_options_agent_website';

    $existing = get_option($primary, []);
    if (! is_array($existing)) {
        $existing = [];
    }

    $merged = array_merge($existing, $sanitized);
    if (function_exists('rch_agent_wizard_canonicalize_hero_title_keys')) {
        $merged = rch_agent_wizard_canonicalize_hero_title_keys($merged, array_keys($key_allow !== [] ? $key_allow : array_flip($allowed_deploy)));
    }
    if (function_exists('rch_leads_form_sync_talk_options_row')) {
        $merged = rch_leads_form_sync_talk_options_row($merged);
    }

    update_option($primary, $merged, false);
    if (is_string($mirror) && $mirror !== '' && $mirror !== $primary) {
        update_option($mirror, $merged, false);
    }

    if (function_exists('rch_agent_wizard_flush_theme_option_cache')) {
        rch_agent_wizard_flush_theme_option_cache($primary, is_string($mirror) ? $mirror : null);
    }

    $deployed_options = [];
    foreach ($sanitized as $opt_key => $opt_val) {
        $deployed_options[$opt_key] = $merged[$opt_key] ?? $opt_val;
    }

    rch_office_wizard_record_last_deployment_in_blog($office_id, $theme_rows, $deployed_options);

    restore_current_blog();

    return [
        'blog_id'          => $blog_id,
        'deployed_options' => $deployed_options,
        'deployed_keys'    => array_keys($sanitized),
        'theme_rows'       => $theme_rows,
        'storage_primary'  => $primary,
    ];
}

/**
 * Deploy the same theme row configuration to every office sub-site that exists.
 *
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 * @return array{updated:int, skipped:int, errors:list<string>}
 */
function rch_office_wizard_deploy_all_office_subsites(array $theme_rows): array
{
    $updated = 0;
    $skipped = 0;
    $errors  = [];

    $offices = get_posts([
        'post_type'      => 'offices',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($offices as $office_id) {
        $office_id = (int) $office_id;
        if (! function_exists('rch_multisite_get_office_blog_id') || ! rch_multisite_get_office_blog_id($office_id)) {
            $skipped++;
            continue;
        }
        $result = rch_office_wizard_deploy_to_office_blog($office_id, $theme_rows);
        if (is_wp_error($result)) {
            $errors[] = sprintf('%s (ID %d): %s', get_the_title($office_id), $office_id, $result->get_error_message());
        } else {
            $updated++;
        }
    }

    return compact('updated', 'skipped', 'errors');
}

// ─────────────────────────────────────────────────────────────────────────────
// SHARED DEPLOYED PROFILE (network-wide default shown on form open)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @return array<string, mixed>|null
 */
function rch_office_wizard_get_deployed_default(): ?array
{
    $json = get_site_option(RCH_OFFICE_WIZARD_SHARED_DRAFT_OPTION, '');
    if (! is_string($json) || $json === '') {
        return null;
    }
    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * @param array<string, mixed> $patch
 */
function rch_office_wizard_update_shared_deploy_profile(array $patch): void
{
    $existing = rch_office_wizard_get_deployed_default();
    $existing = is_array($existing) ? $existing : [];

    $payload                 = array_merge($existing, $patch);
    $payload['draftVersion'] = 4;
    $payload['_deployed']    = true;
    $payload['savedAt']      = time();

    update_site_option(RCH_OFFICE_WIZARD_SHARED_DRAFT_OPTION, wp_json_encode($payload));
}

/**
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 */
function rch_office_wizard_store_deployed_default(array $theme_rows, string $scope, int $office_id): void
{
    $scope = in_array($scope, ['single', 'all'], true) ? $scope : 'single';

    rch_office_wizard_update_shared_deploy_profile([
        'scope'     => $scope,
        'officeId'  => $scope === 'all' ? 0 : (int) $office_id,
        'themeRows' => $theme_rows,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX ENDPOINTS (office-only). Shared broadcast/menu endpoints stay on the agent wizard.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * AJAX: office meta + blog id + defs for wizard bootstrap.
 */
function rch_office_wizard_ajax_load_office(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_office_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    $office_id = isset($_POST['agent_id']) ? absint($_POST['agent_id']) : 0; // JS reuses the shared "agent_id" field.
    $post      = $office_id ? get_post($office_id) : null;

    if (! $post || $post->post_type !== 'offices') {
        wp_send_json_error(['message' => __('Invalid office.', 'rechat-plugin')]);
    }

    $defs = rch_office_wizard_importable_field_defs_resolved();
    $meta = [];
    foreach (array_keys($defs) as $key) {
        $meta[$key] = function_exists('rch_agent_wizard_get_import_source_value')
            ? rch_agent_wizard_get_import_source_value($office_id, $key)
            : '';
    }

    $blog_id = function_exists('rch_multisite_get_office_blog_id') ? rch_multisite_get_office_blog_id($office_id) : 0;

    update_user_meta(get_current_user_id(), 'rch_office_wizard_last_office_id', $office_id);

    wp_send_json_success([
        'agent_id'        => $office_id,
        'title'           => get_the_title($post),
        'blog_id'         => $blog_id,
        'meta'            => $meta,
        'defs'            => $defs,
        'theme_keys'      => rch_office_wizard_theme_key_labels(),
        'current_theme'   => rch_office_wizard_read_destination_theme_options((int) $blog_id),
        'last_deployment' => rch_office_wizard_read_destination_last_deployment((int) $blog_id),
        // Offices have no testimonial CPT; return an empty shape so the shared JS never errors.
        'testimonials'    => ['count' => 0, 'rows' => []],
    ]);
}
add_action('wp_ajax_rch_office_wizard_load_office', 'rch_office_wizard_ajax_load_office');

/**
 * AJAX: save office wizard draft (user meta). On explicit save of a single office, also push.
 */
function rch_office_wizard_ajax_save_draft(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_office_wizard_user_can_run())) {
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

    $uid      = get_current_user_id();
    $existing = get_user_meta($uid, RCH_OFFICE_WIZARD_DRAFT_META, true);
    $prev     = is_string($existing) && $existing !== '' ? json_decode($existing, true) : null;

    // Autosave must never wipe stored row config with an empty payload (second tab / pre-hydrate race).
    if (
        $save_src === 'auto'
        && isset($decoded['themeRows']) && is_array($decoded['themeRows']) && $decoded['themeRows'] === []
        && is_array($prev) && isset($prev['themeRows']) && is_array($prev['themeRows']) && $prev['themeRows'] !== []
    ) {
        $decoded['themeRows'] = $prev['themeRows'];
    }

    update_user_meta($uid, RCH_OFFICE_WIZARD_DRAFT_META, wp_json_encode($decoded));

    $push_message  = '';
    $scope         = isset($decoded['scope']) ? sanitize_key((string) $decoded['scope']) : 'single';
    $office_id     = isset($decoded['agentId']) ? absint($decoded['agentId']) : 0; // JS shares "agentId" for the selected post.
    $theme_rows    = isset($decoded['themeRows']) && is_array($decoded['themeRows']) ? $decoded['themeRows'] : [];

    if ($save_src === 'user' && $scope === 'single' && $office_id > 0 && $theme_rows !== []) {
        $push_result = rch_office_wizard_deploy_to_office_blog($office_id, $theme_rows);
        if (is_wp_error($push_result)) {
            wp_send_json_error(['message' => $push_result->get_error_message()]);
        }
        $push_message = ' ' . __('Office sub-site theme options were updated.', 'rechat-plugin');
    }

    wp_send_json_success(['message' => __('Draft saved.', 'rechat-plugin') . $push_message]);
}
add_action('wp_ajax_rch_office_wizard_save_draft', 'rch_office_wizard_ajax_save_draft');

/**
 * AJAX: load office wizard form data (personal draft or network deployed default).
 */
function rch_office_wizard_ajax_load_draft(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_office_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    $prefer = isset($_POST['prefer']) ? sanitize_key(wp_unslash($_POST['prefer'])) : 'deployed';

    if ($prefer === 'personal') {
        $json  = get_user_meta(get_current_user_id(), RCH_OFFICE_WIZARD_DRAFT_META, true);
        $draft = is_string($json) && $json !== '' ? json_decode($json, true) : null;
        wp_send_json_success(['draft' => is_array($draft) ? $draft : null, 'source' => 'personal']);
    }

    $deployed = rch_office_wizard_get_deployed_default();
    wp_send_json_success(['draft' => is_array($deployed) ? $deployed : null, 'source' => 'deployed']);
}
add_action('wp_ajax_rch_office_wizard_load_draft', 'rch_office_wizard_ajax_load_draft');

/**
 * AJAX: deploy merged options to office sub-site(s).
 */
function rch_office_wizard_ajax_deploy(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error($err = rch_office_wizard_user_can_run())) {
        wp_send_json_error(['message' => $err->get_error_message()], 403);
    }

    $office_id = isset($_POST['agent_id']) ? absint($_POST['agent_id']) : 0; // shared JS field.

    $theme_rows_raw = isset($_POST['theme_rows_json']) ? wp_unslash($_POST['theme_rows_json']) : '';
    if (! is_string($theme_rows_raw) || $theme_rows_raw === '') {
        wp_send_json_error(['message' => __('Missing theme configuration.', 'rechat-plugin')]);
    }
    $theme_rows = json_decode($theme_rows_raw, true);
    if (! is_array($theme_rows)) {
        wp_send_json_error(['message' => __('Invalid theme configuration JSON.', 'rechat-plugin')]);
    }

    $scope = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : 'single';

    $allowed_list = ($scope === 'all' || $office_id <= 0)
        ? rch_office_wizard_allowed_theme_option_keys()
        : rch_office_wizard_resolve_deploy_allowed_keys($office_id);
    $allowed_themes = array_flip($allowed_list);
    $allowed_meta   = array_flip(array_keys(rch_office_wizard_importable_field_defs_resolved()));
    $clean_rows     = [];

    foreach ($theme_rows as $tk => $cfg) {
        if (! is_string($tk) || ! isset($allowed_themes[$tk]) || ! is_array($cfg)) {
            continue;
        }
        $mode = isset($cfg['mode']) ? sanitize_key((string) $cfg['mode']) : 'skip';
        if (! in_array($mode, ['skip', 'manual', 'meta'], true)) {
            continue;
        }
        $entry = ['mode' => $mode];
        if ($mode === 'manual') {
            $raw_val        = $cfg['value'] ?? '';
            $entry['value'] = is_string($raw_val) ? $raw_val : (is_scalar($raw_val) ? (string) $raw_val : '');
        }
        if ($mode === 'meta') {
            $mk = isset($cfg['meta_key']) ? sanitize_key((string) $cfg['meta_key']) : '';
            if ($mk === '' || ! isset($allowed_meta[$mk])) {
                continue;
            }
            $entry['meta_key'] = $mk;
        }
        $clean_rows[$tk] = $entry;
    }

    if ($clean_rows !== []) {
        rch_office_wizard_store_deployed_default($clean_rows, $scope, $office_id);
    }

    if ($scope === 'all') {
        $bulk = rch_office_wizard_deploy_all_office_subsites($clean_rows);
        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: updated count, 2: skipped count */
                __('Updated %1$d office sub-site(s). Skipped %2$d (no linked site).', 'rechat-plugin'),
                $bulk['updated'],
                $bulk['skipped']
            ),
            'updated' => $bulk['updated'],
            'skipped' => $bulk['skipped'],
            'errors'  => $bulk['errors'],
        ]);
        return;
    }

    if (! $office_id) {
        wp_send_json_error(['message' => __('Select an office or choose “All office sub-sites”.', 'rechat-plugin')]);
    }

    $result = rch_office_wizard_deploy_to_office_blog($office_id, $clean_rows);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    $last_deployment = null;
    if (is_array($result) && ! empty($result['blog_id'])) {
        $last_deployment = rch_office_wizard_read_destination_last_deployment((int) $result['blog_id']);
    }

    update_user_meta(get_current_user_id(), 'rch_office_wizard_last_office_id', $office_id);

    wp_send_json_success([
        'message'          => __('Theme options were saved on the office sub-site.', 'rechat-plugin'),
        'blog_id'          => function_exists('rch_multisite_get_office_blog_id') ? rch_multisite_get_office_blog_id($office_id) : 0,
        'last_deployment'  => $last_deployment,
        'deployed_keys'    => is_array($result) && isset($result['deployed_keys']) ? $result['deployed_keys'] : [],
        'deployed_preview' => [],
        'storage_primary'  => is_array($result) && isset($result['storage_primary']) ? $result['storage_primary'] : '',
    ]);
}
add_action('wp_ajax_rch_office_wizard_deploy', 'rch_office_wizard_ajax_deploy');

// ─────────────────────────────────────────────────────────────────────────────
// ASSETS + TAB
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Enqueue office wizard assets on the Rechat settings "office-site-wizard" tab.
 *
 * @param string $hook Page hook.
 */
function rch_office_wizard_enqueue_assets(string $hook): void
{
    if ($hook !== 'toplevel_page_rechat-setting' || ! is_multisite()) {
        return;
    }

    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    if ($tab !== 'office-site-wizard') {
        return;
    }

    if (! defined('RCH_PLUGIN_URL') || ! defined('RCH_VERSION')) {
        return;
    }

    wp_enqueue_media();

    // Reuse the agent wizard stylesheet — the office view uses the same rch-wz-* markup.
    wp_enqueue_style(
        'rch-office-site-wizard',
        RCH_PLUGIN_URL . 'assets/css/rch-agent-site-wizard.css',
        [],
        RCH_VERSION
    );

    wp_enqueue_script(
        'rch-office-site-wizard',
        RCH_PLUGIN_URL . 'assets/js/rch-office-site-deploy-wizard.js',
        ['jquery', 'media-upload', 'media-views'],
        RCH_VERSION,
        true
    );

    $allowed_keys = rch_office_wizard_allowed_theme_option_keys();
    $labels       = rch_office_wizard_theme_key_labels();
    foreach ($allowed_keys as $slug) {
        if (! isset($labels[$slug])) {
            $labels[$slug] = $slug;
        }
    }
    $theme_keys = [];
    foreach ($labels as $slug => $label) {
        if (! in_array($slug, $allowed_keys, true)) {
            continue;
        }
        $theme_keys[] = ['slug' => $slug, 'label' => $label];
    }
    usort($theme_keys, static function (array $a, array $b): int {
        return strcasecmp($a['label'], $b['label']);
    });

    $metabox_labels = [];
    foreach (rch_office_wizard_importable_field_defs_resolved() as $mk => $def) {
        $metabox_labels[$mk] = $def['label'];
    }

    $ui_ss   = rch_office_wizard_ui_stylesheet();
    $ui_prof = rch_office_wizard_get_ui_profile();
    $ui_name = wp_get_theme($ui_ss)->exists() ? wp_get_theme($ui_ss)->get('Name') : $ui_ss;
    $stor    = $ui_prof['storage_primary'] ?? 'pentama_options_v2';
    $stor_m  = isset($ui_prof['storage_mirror']) && is_string($ui_prof['storage_mirror']) ? $ui_prof['storage_mirror'] : '';

    $bc_step   = function_exists('rch_agent_wizard_broadcast_step_enabled') && rch_agent_wizard_broadcast_step_enabled();
    $bc_source = $bc_step && function_exists('rch_multisite_broadcast_source_blog_id') ? rch_multisite_broadcast_source_blog_id() : 0;
    $bc_name   = $bc_source ? (string) get_blog_option($bc_source, 'blogname', '') : '';

    $count_targets = static function (string $mode): int {
        return function_exists('rch_agent_wizard_broadcast_target_blog_ids')
            ? count(rch_agent_wizard_broadcast_target_blog_ids($mode))
            : 0;
    };
    $target_counts = [
        'agent_only'   => $count_targets('agent_only'),
        'office_only'  => $count_targets('office_only'),
        'all_subsites' => $count_targets('all_subsites'),
    ];

    $mw_source = function_exists('rch_agent_wizard_menus_widgets_source_blog_id')
        ? rch_agent_wizard_menus_widgets_source_blog_id()
        : (int) get_main_site_id();
    $mw_name = $mw_source ? (string) get_blog_option($mw_source, 'blogname', '') : '';

    $last_office_id = (int) get_user_meta(get_current_user_id(), 'rch_office_wizard_last_office_id', true);
    if ($last_office_id > 0) {
        $last_post = get_post($last_office_id);
        if (! $last_post || $last_post->post_type !== 'offices') {
            $last_office_id = 0;
        }
    }

    wp_localize_script(
        'rch-office-site-wizard',
        'rchOfficeWizard',
        [
            'ajaxurl'         => admin_url('admin-ajax.php'),
            // Shared nonce: authorises both office endpoints and the reused agent broadcast/menu endpoints.
            'nonce'           => wp_create_nonce(RCH_AGENT_WIZARD_NONCE_ACTION),
            'themeKeys'       => $theme_keys,
            'themeImportMap'  => rch_office_wizard_theme_to_meta_import_map(),
            'metaboxLabels'   => $metabox_labels,
            'bulkCount'       => rch_office_wizard_count_offices_with_subsites(),
            'lastAgentId'     => $last_office_id,
            'broadcastStep'   => $bc_step,
            'broadcastSource' => [
                'blog_id' => $bc_source,
                'label'   => $bc_name !== '' ? $bc_name : sprintf(/* translators: %d: blog ID */ __('Blog %d', 'rechat-plugin'), $bc_source),
            ],
            'broadcastTargetCounts'    => $target_counts,
            'menusWidgetsTargetCounts' => $target_counts,
            'menusWidgetsSource'       => [
                'blog_id' => $mw_source,
                'label'   => $mw_name !== '' ? $mw_name : sprintf(/* translators: %d: blog ID */ __('Blog %d', 'rechat-plugin'), $mw_source),
            ],
            'targetTheme'     => [
                'stylesheet'    => $ui_ss,
                'name'          => $ui_name,
                'optionPrimary' => $stor,
                'optionMirror'  => $stor_m,
            ],
            // Default broadcast + menus/widgets scope to offices for this wizard.
            'defaultTargetMode' => 'office_only',
            'strings'         => rch_office_wizard_localized_strings(),
        ]
    );
}
add_action('admin_enqueue_scripts', 'rch_office_wizard_enqueue_assets', 20);

/**
 * i18n strings for the office wizard JS (office wording; broadcast/menu strings mirror the agent set).
 *
 * @return array<string, string>
 */
function rch_office_wizard_localized_strings(): array
{
    return [
        'pickAgent'      => __('Select an office and load data, or switch to “All office sub-sites”.', 'rechat-plugin'),
        'noBlog'         => __('This office has no sub-site yet. Provision it under the Multisite tab.', 'rechat-plugin'),
        'bulkNoSites'    => __('No office sub-sites exist yet. Provision sites under Multisite first.', 'rechat-plugin'),
        'deployOk'       => __('Deployment finished.', 'rechat-plugin'),
        'deployFail'     => __('Deployment failed.', 'rechat-plugin'),
        'bulkPreview'    => __('Metabox-driven values differ per office; preview uses the loaded office only.', 'rechat-plugin'),
        'previewHeading' => __('What will change', 'rechat-plugin'),
        'previewEmpty'   => __('Nothing to deploy yet — every theme row is set to “Do not change”. Go back and choose manual values or office profile fields.', 'rechat-plugin'),
        'badgeManual'    => __('Manual value', 'rechat-plugin'),
        'badgeMeta'      => __('From office profile', 'rechat-plugin'),
        'valuePreview'   => __('Preview', 'rechat-plugin'),
        'techToggle'     => __('Show technical JSON (advanced)', 'rechat-plugin'),
        'scopeSingle'    => __('Single office', 'rechat-plugin'),
        'scopeAll'       => __('All office sub-sites', 'rechat-plugin'),
        'sitesCount'     => /* translators: %d: count */ __('%d sites will be updated.', 'rechat-plugin'),
        'bcStepTitle'    => __('Broadcast content', 'rechat-plugin'),
        'bcStepLead'     => __('Choose posts and pages on the Broadcast source site, then push copies to the selected target blogs using ThreeWP Broadcast.', 'rechat-plugin'),
        'bcSourceLine'   => __('Source: %s (blog ID %d)', 'rechat-plugin'),
        'bcTargetAgents'  => __('Agent sub-sites only (%d blogs)', 'rechat-plugin'),
        'bcTargetOffices' => __('Office sub-sites only (%d blogs)', 'rechat-plugin'),
        'bcTargetAll'     => __('All network sub-sites except source (%d blogs)', 'rechat-plugin'),
        'bcSearch'       => __('Search titles…', 'rechat-plugin'),
        'bcLoad'         => __('Load list', 'rechat-plugin'),
        'bcPrev'         => __('Previous page', 'rechat-plugin'),
        'bcNext'         => __('Next page', 'rechat-plugin'),
        'bcSelectAll'    => __('Select all on this page', 'rechat-plugin'),
        'bcClearPage'    => __('Clear page selection', 'rechat-plugin'),
        'bcRun'          => __('Broadcast selected', 'rechat-plugin'),
        'bcNoneSelected' => __('Select at least one post or page.', 'rechat-plugin'),
        'bcLoading'      => __('Loading…', 'rechat-plugin'),
        'bcEmpty'        => __('No posts or pages found.', 'rechat-plugin'),
        'bcBroadcastedCount' => /* translators: %d: sub-site count */ __('Already broadcast to %d sub-site(s)', 'rechat-plugin'),
        'draftNonePersonal'  => __('No saved draft for your account yet.', 'rechat-plugin'),
        'bcColTitle'     => __('Title', 'rechat-plugin'),
        'bcColType'      => __('Type', 'rechat-plugin'),
        'bcColStatus'    => __('Status', 'rechat-plugin'),
        'bcColModified'  => __('Modified', 'rechat-plugin'),
        'mwSourceLine'    => __('Template site: %s (blog ID %d)', 'rechat-plugin'),
        'mwTargetAgents'  => __('Agent sub-sites only (%d blogs)', 'rechat-plugin'),
        'mwTargetOffices' => __('Office sub-sites only (%d blogs)', 'rechat-plugin'),
        'mwTargetAll'     => __('All network sub-sites except template (%d blogs)', 'rechat-plugin'),
        'mwLoad'         => __('Load menus & sidebars', 'rechat-plugin'),
        'mwApplyNone'    => __('Select at least one menu, or tick “Copy all widget settings”.', 'rechat-plugin'),
        'mwMenusLegend'  => __('Menus', 'rechat-plugin'),
        'mwSidebarLine'  => __('Sidebars on template: %s', 'rechat-plugin'),
        'mbHeading'      => __('Build new menu for targets', 'rechat-plugin'),
        'mbLead'         => __('Add links from template posts/pages, custom URLs, then pick theme display locations. Uses the same target scope as above.', 'rechat-plugin'),
        'mbNameLabel'    => __('New menu name', 'rechat-plugin'),
        'mbNamePh'       => __('e.g. Main navigation', 'rechat-plugin'),
        'mbSearchLabel'  => __('Add from template content', 'rechat-plugin'),
        'mbSearchBtn'    => __('Search', 'rechat-plugin'),
        'mbCustomLabel'  => __('Custom link', 'rechat-plugin'),
        'mbUrlPh'        => __('https://…', 'rechat-plugin'),
        'mbLinkTextPh'   => __('Link text', 'rechat-plugin'),
        'mbAddCustom'    => __('Add custom link', 'rechat-plugin'),
        'mbItemsLabel'   => __('Menu structure', 'rechat-plugin'),
        'mbItemsEmpty'   => __('No links yet. Search or add a custom link.', 'rechat-plugin'),
        'mbLocLabel'     => __('Theme display locations (template)', 'rechat-plugin'),
        'mbLocEmpty'     => __('Load menus & sidebars above to load locations, or your theme may not register menu locations.', 'rechat-plugin'),
        'mbCreate'       => __('Create menu on all targets', 'rechat-plugin'),
        'mbNeedName'     => __('Enter a menu name.', 'rechat-plugin'),
        'mbNeedItem'     => __('Add at least one link (title and URL, or title and a template post so each site can use its own page link).', 'rechat-plugin'),
        'mbAdd'          => __('Add', 'rechat-plugin'),
        'mbBroadcastedLabel'  => __('Broadcasted posts & pages', 'rechat-plugin'),
        'mbBroadcastedLead'   => __('Content already pushed to sub-sites. Add to the menu structure; each target uses its own copy.', 'rechat-plugin'),
        'mbBroadcastedLoad'   => __('Load broadcasted list', 'rechat-plugin'),
        'mbBroadcastedEmpty'  => __('No broadcasted posts or pages found. Run Broadcast on the previous step first.', 'rechat-plugin'),
        'mbBroadcastedPlaceholder' => __('Click “Load broadcasted list” to show posts and pages that have already been broadcast.', 'rechat-plugin'),
        'mbBroadcastedTotal'  => /* translators: %d: count */ __('%d broadcasted item(s) total.', 'rechat-plugin'),
        'mbBroadcastedColSites' => __('Sub-sites', 'rechat-plugin'),
        'mbBroadcastedAddSelected' => __('Add selected to menu', 'rechat-plugin'),
        'mbBroadcastedNoneSelected' => __('Select at least one broadcasted post or page.', 'rechat-plugin'),
        'mbBroadcastedAdded'  => /* translators: %d: count */ __('Added %d link(s) to the menu.', 'rechat-plugin'),
        'mbBroadcastedAllDup' => __('Selected items are already in the menu.', 'rechat-plugin'),
        'mbRemove'       => __('Remove', 'rechat-plugin'),
        'mbUp'           => __('Move up', 'rechat-plugin'),
        'mbDown'         => __('Move down', 'rechat-plugin'),
        'mbPrev'         => __('Previous', 'rechat-plugin'),
        'mbNext'         => __('Next', 'rechat-plugin'),
    ];
}

/**
 * Render the office wizard tab (included from menu-setting).
 */
function rch_office_wizard_render_tab(): void
{
    if (! is_multisite()) {
        return;
    }

    if (is_wp_error(rch_office_wizard_user_can_run())) {
        echo '<p>' . esc_html__('You do not have access to this tool.', 'rechat-plugin') . '</p>';
        return;
    }

    require RCH_PLUGIN_INCLUDES . 'multisite/views/office-site-deploy-wizard-tab.php';
}
