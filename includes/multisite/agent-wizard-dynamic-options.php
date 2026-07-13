<?php
/**
 * Dynamic theme option resolution + wizard field discovery for agent deploy wizard.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @param string $haystack ASCII-safe substring search for option keys.
 */
function rch_agent_wizard_str_contains_ci(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    return strpos(strtolower($haystack), strtolower($needle)) !== false;
}

/**
 * Sanitize a theme option key WITHOUT lowercasing.
 *
 * Theme option keys are array keys in wp_options and may legitimately contain uppercase
 * letters (e.g. `rch-rodeo-agent-theme-workUs-title`). `sanitize_key()` lowercases, which
 * makes the wizard write the deployed value under a different key than the theme reads —
 * so those fields silently fall back to the theme default. Strip only unsafe characters.
 */
function rch_agent_wizard_sanitize_option_key(string $key): string
{
    $key = preg_replace('/[^A-Za-z0-9_\-]/', '', trim($key));

    return is_string($key) ? $key : '';
}

/**
 * @param string $haystack
 */
function rch_agent_wizard_str_ends_with(string $haystack, string $suffix): bool
{
    $len = strlen($suffix);

    return $len === 0 || substr($haystack, -$len) === $suffix;
}

/**
 * Optional JSON in theme root: rechat-agent-wizard.json
 *
 * @return array<string, mixed>|null
 */
function rch_agent_wizard_load_theme_manifest(string $stylesheet): ?array
{
    $stylesheet = is_string($stylesheet) ? preg_replace('/[^a-zA-Z0-9._\-]/', '', $stylesheet) : '';
    if ($stylesheet === '') {
        return null;
    }

    $path = trailingslashit(get_theme_root()) . $stylesheet . '/rechat-agent-wizard.json';
    if (! is_readable($path)) {
        return null;
    }

    $json = file_get_contents($path);
    if (! is_string($json) || $json === '') {
        return null;
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : null;
}

/**
 * Heuristic: discover option key + fields from a theme's includes/themeoption.php.
 *
 * Looks for update_option('option_name', ...) and array keys in $pentama_options = [ ... ].
 *
 * @return array{storage:array{primary:string,mirror:?string},fields:list<array{key:string,label:string,type:string,media:string}>}|null
 */
function rch_agent_wizard_discover_from_themeoption_php(string $stylesheet): ?array
{
    $stylesheet = is_string($stylesheet) ? preg_replace('/[^a-zA-Z0-9._\-]/', '', $stylesheet) : '';
    if ($stylesheet === '') {
        return null;
    }

    $path = trailingslashit(get_theme_root()) . $stylesheet . '/includes/themeoption.php';
    if (! is_readable($path)) {
        return null;
    }

    $src = file_get_contents($path);
    if (! is_string($src) || $src === '') {
        return null;
    }

    $primary = '';
    if (preg_match("/update_option\\(\\s*['\\\"]([^'\\\"]+)['\\\"]\\s*,/i", $src, $m)) {
        $primary = (string) $m[1];
    } elseif (preg_match("/get_option\\(\\s*['\\\"]([^'\\\"]+)['\\\"]\\s*\\)/i", $src, $m2)) {
        $primary = (string) $m2[1];
    }

    if ($primary === '') {
        return null;
    }

    $keys = [];
    if (preg_match_all("/['\\\"]([a-zA-Z0-9_\\-]+)['\\\"]\\s*=>/m", $src, $mm)) {
        $keys = array_values(array_unique(array_filter($mm[1], static function ($v) {
            if (! is_string($v) || $v === '') {
                return false;
            }
            // Ignore wp_kses allowlists like ['br' => []] — not theme option keys.
            $vl = strtolower($v);

            // Accept rch-/rch_ keys and any hyphenated key (theme option keys use hyphens; HTML tag/attribute names don't).
            return strpos($vl, 'rch-') === 0 || strpos($vl, 'rch_') === 0
                || (strpos($vl, '-') !== false && strlen($vl) > 3);
        })));
    }

    if (count($keys) === 0) {
        return null;
    }

    $fields = [];
    foreach ($keys as $k) {
        $infer = rch_agent_wizard_infer_field_shape($k, '');
        $fields[] = [
            'key'   => $k,
            'label' => rch_agent_wizard_humanize_option_key($k),
            'type'  => $infer['type'],
            'media' => $infer['media'],
        ];
    }

    return [
        'storage' => ['primary' => $primary, 'mirror' => null],
        'fields'  => $fields,
    ];
}

/**
 * Whether a string is a Rechat/theme option key (not wp_kses noise).
 */
function rch_agent_wizard_is_theme_option_key(string $key): bool
{
    if ($key === '') {
        return false;
    }
    $kl = strtolower($key);

    return strpos($kl, 'rch-') === 0 || strpos($kl, 'rch_') === 0
        || (strpos($kl, '-') !== false && strlen($kl) > 3);
}

/**
 * Resolve path to theme option panel view (labels/help for wizard).
 */
function rch_agent_wizard_resolve_option_panel_path(string $stylesheet): ?string
{
    $stylesheet = is_string($stylesheet) ? preg_replace('/[^a-zA-Z0-9._\-]/', '', $stylesheet) : '';
    if ($stylesheet === '') {
        return null;
    }

    $filtered = apply_filters('rch_agent_wizard_option_panel_path', null, $stylesheet);
    if (is_string($filtered) && $filtered !== '' && is_readable($filtered)) {
        return $filtered;
    }

    $root = trailingslashit(get_theme_root()) . $stylesheet . '/';
    $candidates = [
        $root . 'includes/views/option-panel.php',
        $root . 'views/option-panel.php',
    ];

    foreach ($candidates as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Short section name from an option-panel <h3> heading.
 */
function rch_agent_wizard_normalize_panel_section_label(string $raw_heading): string
{
    $raw = trim(html_entity_decode(wp_strip_all_tags($raw_heading), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($raw === '') {
        return '';
    }

    $before_paren = trim((string) preg_replace('/\s*\(.*/u', '', $raw));
    $checks         = [
        '/^Footer\s*—/iu'                    => 'Footer',
        '/^Stored only/iu'                   => 'Admin only',
        '/^Homepage\s*—\s*Top hero/iu'       => 'Hero',
        '/^Homepage\s*—\s*Agent profile/iu'  => 'Profile',
        '/^Homepage\s*—\s*Stats/iu'          => 'Stats',
        '/^Homepage\s*—\s*Active listings/iu' => 'Active listings',
        '/^Homepage\s*—\s*Sold listings/iu'  => 'Sold listings',
        '/^Homepage bottom\s*—\s*Talk/iu'    => 'Talk',
    ];
    foreach ($checks as $pattern => $short) {
        if (preg_match($pattern, $before_paren)) {
            return $short;
        }
    }

    if (strpos($before_paren, '—') !== false) {
        $parts = array_map('trim', preg_split('/\s*—\s*/u', $before_paren, 2));

        return $parts[0] !== '' ? $parts[0] : $before_paren;
    }

    return $before_paren;
}

/**
 * Template/file hint from option-panel section heading, e.g. footer.php, index.php.
 */
function rch_agent_wizard_extract_panel_placement_hint(string $raw_heading): string
{
    $raw = wp_strip_all_tags($raw_heading);
    if (preg_match('/\b([a-z0-9_\-\/]+\.php)\b/i', $raw, $m)) {
        return (string) $m[1];
    }
    if (preg_match('/\.([a-z][a-z0-9_-]+)/i', $raw, $m2)) {
        return 'index.php · .' . $m2[1];
    }

    return '';
}

/**
 * Whether a field label already starts with the section short name.
 */
function rch_agent_wizard_panel_label_has_section(string $label, string $section): bool
{
    if ($section === '' || $label === '') {
        return false;
    }

    return (bool) preg_match('/^' . preg_quote($section, '/') . '\s*—/iu', $label);
}

/**
 * Discover labels, help text, control type, and order from theme option-panel.php.
 *
 * @return array{fields:list<array{key:string,label:string,help?:string,type:string,media:string}>}|null
 */
function rch_agent_wizard_discover_from_option_panel_php(string $stylesheet): ?array
{
    $path = rch_agent_wizard_resolve_option_panel_path($stylesheet);
    if ($path === null) {
        return null;
    }

    $src = file_get_contents($path);
    if (! is_string($src) || $src === '') {
        return null;
    }

    if (! preg_match_all('/<tr\b[^>]*>.*?<\/tr>/is', $src, $row_matches)) {
        return null;
    }

    $fields              = [];
    $current_section     = '';
    $current_placement   = '';
    foreach ($row_matches[0] as $row) {
        if (preg_match('/<h3\b[^>]*>(.*?)<\/h3>/is', $row, $h3_match)) {
            $heading_raw         = (string) $h3_match[1];
            $current_section     = rch_agent_wizard_normalize_panel_section_label($heading_raw);
            $current_placement   = rch_agent_wizard_extract_panel_placement_hint($heading_raw);

            continue;
        }

        if (! preg_match('/\bname\s*=\s*["\']([^"\']+)["\']/i', $row, $name_match)) {
            continue;
        }
        $key = trim((string) $name_match[1]);
        if (! rch_agent_wizard_is_theme_option_key($key)) {
            continue;
        }

        $label = '';
        if (preg_match('/<th[^>]*\bscope\s*=\s*["\']row["\'][^>]*>(.*?)<\/th>/is', $row, $th_match)) {
            $label = trim(html_entity_decode(wp_strip_all_tags($th_match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if ($label !== '' && $current_section !== '' && ! rch_agent_wizard_panel_label_has_section($label, $current_section)) {
            $label = $current_section . ' — ' . $label;
        }

        $help = '';
        if (preg_match('/<p\s+class\s*=\s*["\']description["\'][^>]*>(.*?)<\/p>/is', $row, $help_match)) {
            $help = trim(html_entity_decode(wp_strip_all_tags($help_match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($current_placement !== '') {
            $placement_line = sprintf(
                /* translators: %s: theme template file such as index.php or footer.php */
                __('Placed in: %s', 'rechat-plugin'),
                $current_placement
            );
            $help = $help !== '' ? $placement_line . ' · ' . $help : $placement_line;
        } elseif ($current_section !== '') {
            $placement_line = sprintf(
                /* translators: %s: homepage section name such as Hero or Profile */
                __('Section: %s', 'rechat-plugin'),
                $current_section
            );
            $help = $help !== '' ? $placement_line . ' · ' . $help : $placement_line;
        }

        $type  = 'text';
        $media = '';
        if (function_exists('rch_agent_wizard_key_uses_tags_multiselect_ui') && rch_agent_wizard_key_uses_tags_multiselect_ui($key)) {
            $type = 'textarea_json';
        } elseif (preg_match('/<textarea\b/i', $row)) {
            $type = 'textarea';
        } elseif (preg_match('/<select\b/i', $row)) {
            $type = 'select';
        } elseif (preg_match('/<input[^>]*\btype\s*=\s*["\']([^"\']+)["\']/i', $row, $type_match)) {
            $input_type = strtolower((string) $type_match[1]);
            if (
                $input_type === 'hidden'
                && (
                    (function_exists('rch_agent_wizard_key_uses_tags_multiselect_ui') && rch_agent_wizard_key_uses_tags_multiselect_ui($key))
                    || rch_agent_wizard_str_contains_ci($key, 'selected-tags')
                    || rch_agent_wizard_str_contains_ci($key, 'selected_tags')
                )
            ) {
                $type = 'textarea_json';
            } elseif ($input_type === 'email') {
                $type = 'text';
            } elseif (in_array($input_type, ['url', 'number', 'text'], true)) {
                $type = $input_type === 'text' ? 'text' : $input_type;
            }
        }

        if (preg_match('/upload_image_button/i', $row)) {
            $media = 'image';
            if ($type === 'text') {
                $type = 'url';
            }
        } elseif (preg_match('/upload_video_button/i', $row)) {
            $media = 'video';
            if ($type === 'text') {
                $type = 'url';
            }
        }

        $field = [
            'key'   => $key,
            'label' => $label !== '' ? $label : rch_agent_wizard_humanize_option_key($key),
            'type'  => $type,
            'media' => $media,
        ];
        if ($help !== '') {
            $field['help'] = $help;
        }
        $fields[] = $field;
    }

    if ($fields === []) {
        return null;
    }

    /**
     * @param array{fields:list<array<string,mixed>>} $result
     */
    $result = ['fields' => $fields];

    return apply_filters('rch_agent_wizard_discovered_option_panel_fields', $result, $stylesheet, $path);
}

/**
 * Build wizard field list: keys from themeoption.php, labels/order from option-panel.php, optional manifest overrides.
 *
 * @param array<string, mixed>|null $manifest Optional rechat-agent-wizard.json (storage, exclude_fields, field overrides).
 * @param array{storage:array{primary:string,mirror:?string},fields:list<array<string,mixed>>} $disc From themeoption.php.
 * @param array{fields:list<array<string,mixed>>}|null $panel From option-panel.php.
 * @return array{storage:array{primary:string,mirror:?string},fields:list<array<string,mixed>>}
 */
function rch_agent_wizard_merge_theme_field_definitions(?array $manifest, array $disc, ?array $panel): array
{
    $storage = isset($disc['storage']) && is_array($disc['storage']) ? $disc['storage'] : ['primary' => '', 'mirror' => null];

    if (is_array($manifest) && ! empty($manifest['storage']['primary']) && is_string($manifest['storage']['primary'])) {
        $storage['primary'] = $manifest['storage']['primary'];
        if (isset($manifest['storage']['mirror']) && is_string($manifest['storage']['mirror']) && $manifest['storage']['mirror'] !== '') {
            $storage['mirror'] = $manifest['storage']['mirror'];
        }
    }

    $disc_fields = isset($disc['fields']) && is_array($disc['fields']) ? $disc['fields'] : [];

    $exclude = [];
    if (is_array($manifest) && isset($manifest['exclude_fields']) && is_array($manifest['exclude_fields'])) {
        foreach ($manifest['exclude_fields'] as $ex) {
            if (is_string($ex) && $ex !== '') {
                $exclude[ $ex ] = true;
            }
        }
    }

    $disc_by_key = [];
    $disc_order  = [];
    foreach ($disc_fields as $field) {
        if (! is_array($field) || empty($field['key']) || ! is_string($field['key'])) {
            continue;
        }
        if (! empty($exclude[ $field['key'] ])) {
            continue;
        }
        $disc_by_key[ $field['key'] ] = $field;
        $disc_order[]                  = $field['key'];
    }

    $panel_by_key = [];
    $panel_order  = [];
    if (is_array($panel) && isset($panel['fields']) && is_array($panel['fields'])) {
        foreach ($panel['fields'] as $field) {
            if (! is_array($field) || empty($field['key']) || ! is_string($field['key'])) {
                continue;
            }
            $k = $field['key'];
            if (! isset($disc_by_key[ $k ])) {
                continue;
            }
            $panel_by_key[ $k ] = $field;
            $panel_order[]      = $k;
        }
    }

    $manifest_overrides = [];
    if (is_array($manifest) && isset($manifest['fields']) && is_array($manifest['fields'])) {
        foreach ($manifest['fields'] as $field) {
            if (! is_array($field) || empty($field['key']) || ! is_string($field['key'])) {
                continue;
            }
            if (! isset($disc_by_key[ $field['key'] ])) {
                continue;
            }
            $manifest_overrides[ $field['key'] ] = $field;
        }
    }

    $ordered_keys = [];
    $seen         = [];
    foreach ($panel_order as $k) {
        if (empty($seen[ $k ])) {
            $ordered_keys[] = $k;
            $seen[ $k ]     = true;
        }
    }
    foreach ($disc_order as $k) {
        if (empty($seen[ $k ])) {
            $ordered_keys[] = $k;
            $seen[ $k ]     = true;
        }
    }

    $merged = [];
    foreach ($ordered_keys as $k) {
        $field = $disc_by_key[ $k ];
        if (isset($panel_by_key[ $k ]) && is_array($panel_by_key[ $k ])) {
            $field = array_merge($field, array_filter(
                $panel_by_key[ $k ],
                static function ($v, $prop) {
                    if ($v === '' || $v === null) {
                        return false;
                    }

                    return in_array($prop, ['label', 'help', 'type', 'media'], true);
                },
                ARRAY_FILTER_USE_BOTH
            ));
        }
        if (isset($manifest_overrides[ $k ]) && is_array($manifest_overrides[ $k ])) {
            $field = array_merge($field, array_filter(
                $manifest_overrides[ $k ],
                static function ($v, $prop) {
                    if ($v === '' || $v === null) {
                        return false;
                    }

                    return $prop !== 'key';
                },
                ARRAY_FILTER_USE_BOTH
            ));
        }
        $merged[] = $field;
    }

    return [
        'storage' => $storage,
        'fields'  => $merged,
    ];
}

/**
 * @deprecated Use {@see rch_agent_wizard_merge_theme_field_definitions()}.
 */
function rch_agent_wizard_merge_manifest_discovery_fields(?array $manifest, array $disc): array
{
    return rch_agent_wizard_merge_theme_field_definitions($manifest, $disc, null);
}

/**
 * Read a WordPress option as an associative array on the current blog.
 *
 * @return array<string, mixed>
 */
function rch_agent_wizard_get_option_array_current_blog(string $option_name): array
{
    $option_name = is_string($option_name) ? $option_name : '';
    if ($option_name === '') {
        return [];
    }

    $raw = get_option($option_name, []);

    return is_array($raw) ? $raw : [];
}

/**
 * Resolve primary + optional mirror option names for a theme stylesheet.
 *
 * @return array{primary:string, mirror:?string}
 */
function rch_agent_wizard_resolve_storage_config(string $stylesheet): array
{
    $stylesheet = is_string($stylesheet) ? $stylesheet : '';

    $filtered = apply_filters('rch_agent_wizard_storage_config', null, $stylesheet);
    if (is_array($filtered) && ! empty($filtered['primary']) && is_string($filtered['primary'])) {
        $mirror = isset($filtered['mirror']) && is_string($filtered['mirror']) && $filtered['mirror'] !== ''
            ? $filtered['mirror']
            : null;

        return [
            'primary' => $filtered['primary'],
            'mirror'  => $mirror,
        ];
    }

    $map = get_site_option('rch_agent_wizard_theme_storage_map', []);
    if (is_string($map) && $map !== '') {
        $decoded = json_decode($map, true);
        $map     = is_array($decoded) ? $decoded : [];
    }
    if (! is_array($map)) {
        $map = [];
    }

    if (isset($map[ $stylesheet ]) && is_array($map[ $stylesheet ]) && ! empty($map[ $stylesheet ]['primary'])) {
        $p = (string) $map[ $stylesheet ]['primary'];
        $m = isset($map[ $stylesheet ]['mirror']) && is_string($map[ $stylesheet ]['mirror']) && $map[ $stylesheet ]['mirror'] !== ''
            ? (string) $map[ $stylesheet ]['mirror']
            : null;

        return ['primary' => $p, 'mirror' => $m];
    }

    $manifest = rch_agent_wizard_load_theme_manifest($stylesheet);
    if (is_array($manifest) && isset($manifest['storage']) && is_array($manifest['storage']) && ! empty($manifest['storage']['primary'])) {
        $p = (string) $manifest['storage']['primary'];
        $m = isset($manifest['storage']['mirror']) && is_string($manifest['storage']['mirror']) && $manifest['storage']['mirror'] !== ''
            ? (string) $manifest['storage']['mirror']
            : null;

        return ['primary' => $p, 'mirror' => $m];
    }

    // Best-effort: most bundled themes declare update_option() in includes/themeoption.php.
    $disc = rch_agent_wizard_discover_from_themeoption_php($stylesheet);
    if (is_array($disc) && ! empty($disc['storage']['primary']) && is_string($disc['storage']['primary'])) {
        return [
            'primary' => (string) $disc['storage']['primary'],
            'mirror'  => null,
        ];
    }

    // Unknown theme: no safe default. Caller should show empty state or configure mapping.
    return ['primary' => '', 'mirror' => null];
}

/**
 * Human-readable label from option key slug.
 */
function rch_agent_wizard_humanize_option_key(string $key): string
{
    $key = str_replace(['rch-', 'rch_'], '', $key);
    $key = str_replace(['-', '_'], ' ', $key);

    return ucwords(trim($key));
}

/**
 * Infer field UI + sanitize buckets from key name and sample value.
 *
 * @return array{type:string, media:string, buckets:array<string, list<string>>}
 */
function rch_agent_wizard_infer_field_shape(string $key, $sample_value): array
{
    $kl = strtolower($key);
    $b  = [
        'urls'          => [],
        'textareas'     => [],
        'textarea_json' => [],
        'shortcodes'    => [],
        'numbers'       => [],
        'wp_kses'       => [],
        'color'         => [],
        'image_media'   => [],
        'video_media'   => [],
        'select'        => [],
    ];

    $type  = 'text';
    $media = '';

    if (rch_agent_wizard_str_contains_ci($kl, 'lead-channel') || rch_agent_wizard_str_contains_ci($kl, 'lead_channel')) {
        $b['select'][] = $key;

        return ['type' => 'select', 'media' => '', 'buckets' => $b];
    }

    if (
        rch_agent_wizard_str_contains_ci($kl, 'selected-tags')
        || rch_agent_wizard_str_contains_ci($kl, 'selected_tags')
        || strtolower((string) $key) === 'rch_selected_tags'
    ) {
        $b['textarea_json'][] = $key;

        return ['type' => 'textarea_json', 'media' => '', 'buckets' => $b];
    }

    if (is_array($sample_value)) {
        $b['textarea_json'][] = $key;

        return ['type' => 'textarea_json', 'media' => '', 'buckets' => $b];
    }

    if (rch_agent_wizard_str_contains_ci($kl, 'shortcode')) {
        $b['shortcodes'][] = $key;

        return ['type' => 'textarea', 'media' => '', 'buckets' => $b];
    }

    if (rch_agent_wizard_str_contains_ci($kl, 'color')) {
        $b['color'][] = $key;

        return ['type' => 'text', 'media' => '', 'buckets' => $b];
    }

    if (preg_match('/(^|-)video($|-)/', $kl) || rch_agent_wizard_str_contains_ci($kl, '-video')) {
        $b['urls'][]         = $key;
        $b['video_media'][]  = $key;

        return ['type' => 'url', 'media' => 'video', 'buckets' => $b];
    }

    if (
        rch_agent_wizard_str_contains_ci($kl, 'image')
        || rch_agent_wizard_str_contains_ci($kl, 'logo')
        || rch_agent_wizard_str_contains_ci($kl, 'thumb')
        || rch_agent_wizard_str_contains_ci($kl, 'avatar')
    ) {
        $b['urls'][]        = $key;
        $b['image_media'][] = $key;

        return ['type' => 'url', 'media' => 'image', 'buckets' => $b];
    }

    // Email keys must never be classified as URLs — even when named like `*-email-url` — otherwise
    // esc_url_raw() would prepend http:// to a bare address. Keep them out of the `urls` bucket.
    if (
        rch_agent_wizard_str_contains_ci($kl, 'email')
        || rch_agent_wizard_str_contains_ci($kl, 'mail')
    ) {
        return ['type' => 'email', 'media' => '', 'buckets' => $b];
    }

    // Address keys must never be classified as URLs — even when named like `*-address-url` —
    // otherwise esc_url_raw() prepends http:// to a bare address and %20-encodes the spaces.
    if (rch_agent_wizard_str_contains_ci($kl, 'address')) {
        $b['textareas'][] = $key;

        return ['type' => 'textarea', 'media' => '', 'buckets' => $b];
    }

    if (
        rch_agent_wizard_str_contains_ci($kl, 'url')
        || rch_agent_wizard_str_contains_ci($kl, 'link')
        || rch_agent_wizard_str_contains_ci($kl, 'href')
        || rch_agent_wizard_str_ends_with($kl, '-uri')
    ) {
        $b['urls'][] = $key;

        return ['type' => 'url', 'media' => '', 'buckets' => $b];
    }

    if (rch_agent_wizard_str_contains_ci($kl, 'description') || rch_agent_wizard_str_contains_ci($kl, 'content') || rch_agent_wizard_str_contains_ci($kl, 'bio')) {
        $b['textareas'][] = $key;

        return ['type' => 'textarea', 'media' => '', 'buckets' => $b];
    }

    if (is_string($sample_value) && strlen($sample_value) > 200) {
        $b['textareas'][] = $key;

        return ['type' => 'textarea', 'media' => '', 'buckets' => $b];
    }

    if (is_string($sample_value) && $sample_value !== '' && is_numeric($sample_value)) {
        $b['numbers'][] = $key;

        return ['type' => 'number', 'media' => '', 'buckets' => $b];
    }

    if (preg_match('/(^|-)value$/', $kl) && preg_match('/counter|count|number/i', $kl)) {
        $b['numbers'][] = $key;

        return ['type' => 'number', 'media' => '', 'buckets' => $b];
    }

    if (is_string($sample_value) && preg_match('/<[a-z][\s\S]*>/i', $sample_value)) {
        $b['wp_kses'][] = $key;

        return ['type' => 'text', 'media' => '', 'buckets' => $b];
    }

    return ['type' => 'text', 'media' => '', 'buckets' => $b];
}

/**
 * Merge bucket lists from infer into profile arrays.
 *
 * @param array<string, list<string>> $acc
 * @param array<string, list<string>> $buckets
 */
function rch_agent_wizard_merge_buckets(array &$acc, array $buckets): void
{
    foreach ($buckets as $name => $list) {
        if (! isset($acc[ $name ])) {
            $acc[ $name ] = [];
        }
        foreach ($list as $k) {
            if (is_string($k) && $k !== '' && ! in_array($k, $acc[ $name ], true)) {
                $acc[ $name ][] = $k;
            }
        }
    }
}

/**
 * Heuristic import_defaults (meta => theme key) from discovered keys.
 *
 * @param list<string> $keys
 * @return array<string, string>
 */
function rch_agent_wizard_dynamic_heuristic_import_defaults(array $keys): array
{
    $out = [];
    foreach ($keys as $k) {
        if (! is_string($k)) {
            continue;
        }
        $kl = strtolower($k);
        if (rch_agent_wizard_str_contains_ci($kl, 'instagram') && ! isset($out['instagram'])) {
            $out['instagram'] = $k;
        }
        if ((rch_agent_wizard_str_contains_ci($kl, 'phone') || rch_agent_wizard_str_contains_ci($kl, 'tel')) && ! isset($out['phone_number'])) {
            $out['phone_number'] = $k;
        }
        if (rch_agent_wizard_str_contains_ci($kl, 'address') && ! isset($out['agent_address'])) {
            $out['agent_address'] = $k;
        }
        if (rch_agent_wizard_str_contains_ci($kl, 'listing') && (rch_agent_wizard_str_contains_ci($kl, 'url') || rch_agent_wizard_str_contains_ci($kl, 'link')) && ! isset($out['website'])) {
            $out['website'] = $k;
        }
        if (
            (rch_agent_wizard_str_contains_ci($kl, 'menu-image') || (rch_agent_wizard_str_contains_ci($kl, 'profile') && rch_agent_wizard_str_contains_ci($kl, 'image')))
            && ! isset($out['profile_image_url'])
        ) {
            $out['profile_image_url'] = $k;
        }
        if ((rch_agent_wizard_str_contains_ci($kl, 'linkedin') || rch_agent_wizard_str_contains_ci($kl, 'linked-in')) && ! isset($out['linkedin'])) {
            $out['linkedin'] = $k;
        }
        if ((rch_agent_wizard_str_contains_ci($kl, 'twitter') || $kl === 'rch-theme-x' || rch_agent_wizard_str_contains_ci($kl, '-x')) && ! isset($out['twitter'])) {
            $out['twitter'] = $k;
        }
        if (rch_agent_wizard_str_contains_ci($kl, 'title-main-hero') && ! isset($out['post_title'])) {
            $out['post_title'] = $k;
        }
    }

    /**
     * @param array<string, string> $out
     */
    return apply_filters('rch_agent_wizard_dynamic_import_defaults', $out, $keys);
}

/**
 * Build profile array (same shape as legacy) from manifest "fields" or option snapshot.
 *
 * @param array<string, mixed> $snapshot Merged key => sample value
 * @return array<string, mixed>
 */
function rch_agent_wizard_build_dynamic_profile_from_data(
    string $stylesheet,
    array $storage,
    array $snapshot,
    ?array $manifest
): array {
    $keys        = [];
    $labels      = [];
    $field_help  = [];
    $field_order = [];

    $buckets = [
        'urls'            => [],
        'textareas'       => [],
        'textarea_json'   => [],
        'shortcodes'      => [],
        'numbers'         => [],
        'wp_kses'         => [],
        'color'           => [],
        'image_media'     => [],
        'video_media'     => [],
        'select'          => [],
    ];

    $select_options_by_key = [];

    $from_manifest = is_array($manifest) && isset($manifest['fields']) && is_array($manifest['fields']) && $manifest['fields'] !== [];

    if ($from_manifest) {
        foreach ($manifest['fields'] as $field) {
            if (! is_array($field) || empty($field['key']) || ! is_string($field['key'])) {
                continue;
            }
            $k = rch_agent_wizard_sanitize_option_key($field['key']);
            if ($k === '') {
                continue;
            }
            $keys[]       = $k;
            $labels[ $k ] = isset($field['label']) && is_string($field['label']) && $field['label'] !== ''
                ? $field['label']
                : rch_agent_wizard_humanize_option_key($k);
            if (isset($field['help']) && is_string($field['help']) && $field['help'] !== '') {
                $field_help[ $k ] = $field['help'];
            }
            $field_order[] = $k;
            $type  = isset($field['type']) ? sanitize_key((string) $field['type']) : 'text';
            $media = isset($field['media']) ? sanitize_key((string) $field['media']) : '';
            $sample = $snapshot[ $k ] ?? '';

            if ($type === 'textarea') {
                $buckets['textareas'][] = $k;
            } elseif (
                $type === 'tags'
                || (
                    $type === 'textarea_json'
                    && function_exists('rch_agent_wizard_key_uses_tags_multiselect_ui')
                    && rch_agent_wizard_key_uses_tags_multiselect_ui($k)
                )
            ) {
                $buckets['textarea_json'][] = $k;
            } elseif ($type === 'textarea_json') {
                $buckets['textarea_json'][] = $k;
            } elseif ($type === 'select') {
                $buckets['select'][] = $k;
                if (! empty($field['options']) && is_array($field['options'])) {
                    $opts = [];
                    foreach ($field['options'] as $opt) {
                        if (! is_array($opt)) {
                            continue;
                        }
                        $ov = isset($opt['value']) ? (string) $opt['value'] : '';
                        $ol = isset($opt['label']) && is_string($opt['label']) && $opt['label'] !== '' ? (string) $opt['label'] : $ov;
                        if ($ov !== '') {
                            $opts[] = ['value' => $ov, 'label' => $ol];
                        }
                    }
                    if ($opts !== []) {
                        $select_options_by_key[ $k ] = $opts;
                    }
                }
            } elseif ($type === 'url') {
                $buckets['urls'][] = $k;
            } elseif ($type === 'number') {
                $buckets['numbers'][] = $k;
            } elseif ($type === 'html' || $type === 'kses') {
                $buckets['wp_kses'][] = $k;
            } elseif ($type === 'color') {
                $buckets['color'][] = $k;
            } else {
                $infer = rch_agent_wizard_infer_field_shape($k, $sample);
                rch_agent_wizard_merge_buckets($buckets, $infer['buckets']);
            }

            if ($media === 'image') {
                $buckets['image_media'][] = $k;
                if (! in_array($k, $buckets['urls'], true)) {
                    $buckets['urls'][] = $k;
                }
            } elseif ($media === 'video') {
                $buckets['video_media'][] = $k;
                if (! in_array($k, $buckets['urls'], true)) {
                    $buckets['urls'][] = $k;
                }
            }
        }
        $keys = array_values(array_unique($keys));
    } else {
        foreach ($snapshot as $k => $v) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            $sk = rch_agent_wizard_sanitize_option_key($k);
            if ($sk === '' || $sk !== $k) {
                continue;
            }
            $keys[]       = $k;
            $labels[ $k ] = rch_agent_wizard_humanize_option_key($k);
            $infer        = rch_agent_wizard_infer_field_shape($k, $v);
            rch_agent_wizard_merge_buckets($buckets, $infer['buckets']);
        }
        $keys = array_values(array_unique($keys));
        sort($keys);
    }

    $import_defaults = rch_agent_wizard_dynamic_heuristic_import_defaults($keys);

    $wizard_labels = function_exists('rch_agent_wizard_wizard_only_labels')
        ? rch_agent_wizard_wizard_only_labels()
        : [];

    // If the theme has zero discoverable theme keys, show zero fields (per requirement).
    $merged_keys = [];
    if (count($keys) > 0) {
        $merged_keys = function_exists('rch_agent_wizard_merge_wizard_keys')
            ? rch_agent_wizard_merge_wizard_keys($keys)
            : array_merge($keys, ['rch-wizard-agent-email', 'rch-wizard-agent-post-id', 'rch-wizard-agent-api-id', 'rch-wizard-agent-designation']);
    }

    $select_keys = isset($buckets['select']) && is_array($buckets['select'])
        ? array_values(array_unique(array_filter($buckets['select'], 'is_string')))
        : [];

    $theme_field_order = $field_order !== [] ? $field_order : $keys;
    $ordered_keys      = [];
    foreach ($theme_field_order as $ok) {
        if (in_array($ok, $merged_keys, true) && ! in_array($ok, $ordered_keys, true)) {
            $ordered_keys[] = $ok;
        }
    }
    foreach ($merged_keys as $mk) {
        if (! in_array($mk, $ordered_keys, true)) {
            $ordered_keys[] = $mk;
        }
    }

    return [
        'keys'            => $merged_keys,
        'field_order'     => $ordered_keys,
        'field_help'      => $field_help,
        'labels'          => array_merge($labels, $wizard_labels),
        'urls'            => $buckets['urls'],
        'textareas'       => $buckets['textareas'],
        'textarea_json'   => $buckets['textarea_json'],
        'shortcodes'      => $buckets['shortcodes'],
        'numbers'         => $buckets['numbers'],
        'wp_kses'         => $buckets['wp_kses'],
        'color'           => $buckets['color'],
        'image_media'     => $buckets['image_media'],
        'video_media'     => $buckets['video_media'],
        'select_keys'     => $select_keys,
        'select_options'  => $select_options_by_key,
        'import_defaults' => $import_defaults,
        'storage_primary' => $storage['primary'],
        'storage_mirror'  => $storage['mirror'],
        'dynamic'         => true,
        'stylesheet'      => $stylesheet,
    ];
}

/**
 * Merge snapshots from primary + mirror options for discovery.
 *
 * @return array<string, mixed>
 */
function rch_agent_wizard_merge_option_snapshots(array $storage): array
{
    $a = rch_agent_wizard_get_option_array_current_blog($storage['primary']);
    $b = [];
    if (! empty($storage['mirror']) && is_string($storage['mirror'])) {
        $b = rch_agent_wizard_get_option_array_current_blog($storage['mirror']);
    }

    return array_merge($b, $a);
}

/**
 * @return array<string, mixed>|null Full profile or null (wizard shows empty until manifest/options exist)
 */
function rch_agent_wizard_try_build_dynamic_theme_profile(string $stylesheet): ?array
{
    $stylesheet = is_string($stylesheet) ? $stylesheet : '';

    $enabled = apply_filters('rch_agent_wizard_use_dynamic_options', true, $stylesheet);
    if (! $enabled) {
        return null;
    }

    $storage   = rch_agent_wizard_resolve_storage_config($stylesheet);
    if (($storage['primary'] ?? '') === '') {
        return null;
    }

    $manifest  = rch_agent_wizard_load_theme_manifest($stylesheet);
    $snapshot  = rch_agent_wizard_merge_option_snapshots($storage);

    // Keys: includes/themeoption.php. Labels/help/order: includes/views/option-panel.php. Manifest: optional overrides only.
    $disc = rch_agent_wizard_discover_from_themeoption_php($stylesheet);
    if (is_array($disc) && ! empty($disc['fields']) && is_array($disc['fields'])) {
        $panel    = rch_agent_wizard_discover_from_option_panel_php($stylesheet);
        $merged   = rch_agent_wizard_merge_theme_field_definitions(is_array($manifest) ? $manifest : null, $disc, $panel);
        $storage  = $merged['storage'];
        $manifest = array_merge(is_array($manifest) ? $manifest : [], $merged);
        // refresh snapshot using discovered storage (different themes share pentama_options; we must not show leftover keys)
        $snapshot = rch_agent_wizard_merge_option_snapshots($storage);
    }

    $has_discovered_fields = is_array($manifest)
        && isset($manifest['fields'])
        && is_array($manifest['fields'])
        && $manifest['fields'] !== [];

    if (! $has_discovered_fields && count($snapshot) === 0) {
        return null;
    }

    $profile = rch_agent_wizard_build_dynamic_profile_from_data($stylesheet, $storage, $snapshot, $manifest);

    /**
     * Adjust auto-built profile before the global {@see 'rch_agent_wizard_theme_profile'} filter runs.
     *
     * @param array<string, mixed> $profile
     */
    return apply_filters('rch_agent_wizard_dynamic_theme_profile', $profile, $stylesheet, $snapshot, $manifest);
}
