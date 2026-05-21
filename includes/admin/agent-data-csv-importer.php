<?php
/**
 * CSV import: agent bios (post content) and testimonials.
 *
 * @package RechatPlugin
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('RCH_AGENT_TESTIMONIALS_META_KEY')) {
    define('RCH_AGENT_TESTIMONIALS_META_KEY', 'agent_testimonials');
}

/** AJAX / form nonce action. */
const RCH_AGENT_IMPORT_NONCE_ACTION = 'rch_agent_import';

/**
 * @return array<string, string>
 */
function rch_agent_import_header_aliases(): array
{
    return [
        'agent_match'              => 'agent_match',
        'agent'                    => 'agent_match',
        'agent_id'                 => 'agent_match',
        'post_id'                  => 'agent_match',
        'rechat_id'                => 'agent_match',
        'api_id'                   => 'agent_match',
        'match_by'                 => 'match_by',
        'bio'                      => 'bio',
        'biography'                => 'bio',
        'content'                  => 'bio',
        'post_content'             => 'bio',
        'testimonial_name'         => 'testimonial_name',
        'name'                     => 'testimonial_name',
        'client_name'              => 'testimonial_name',
        'author'                   => 'testimonial_name',
        'testimonial_description'  => 'testimonial_description',
        'description'              => 'testimonial_description',
        'testimonial'              => 'testimonial_description',
        'quote'                    => 'testimonial_description',
        'text'                     => 'testimonial_description',
        'title'                    => 'agent_title_meta',
        'agent_title'              => 'agent_title_meta',
        'testimonial_rank'         => 'testimonial_stars',
        'testimonial_stars'        => 'testimonial_stars',
        'stars'                    => 'testimonial_stars',
        'rank'                     => 'testimonial_stars',
        'rating'                   => 'testimonial_stars',
        'testimonial_link'         => 'testimonial_link',
        'link'                     => 'testimonial_link',
        'url'                      => 'testimonial_link',
    ];
}

/**
 * Normalize a CSV cell (trim; treat "-" as empty).
 */
function rch_agent_import_normalize_cell(string $value): string
{
    $value = trim($value);
    if ($value === '-' || $value === '—') {
        return '';
    }

    return $value;
}

/**
 * @return array<string, string>
 */
function rch_agent_import_allowed_match_by(): array
{
    return [
        'auto'    => __('Auto-detect', 'rechat-plugin'),
        'post_id' => __('Post ID', 'rechat-plugin'),
        'api_id'  => __('Rechat ID (api_id)', 'rechat-plugin'),
        'title'   => __('Agent post title (exact)', 'rechat-plugin'),
    ];
}

/**
 * Valid match_by keys for CSV rows and the import form default.
 *
 * @return list<string>
 */
function rch_agent_import_valid_match_by_keys(): array
{
    return ['auto', 'post_id', 'api_id', 'title'];
}

/**
 * Normalize per-row match_by (handles common CSV mistake: Rechat ID pasted into match_by).
 */
function rch_agent_import_resolve_match_by(string $row_match_by, string $default_match_by, string $agent_match = ''): string
{
    $valid   = rch_agent_import_valid_match_by_keys();
    $default = sanitize_key($default_match_by);
    if (! in_array($default, $valid, true)) {
        $default = 'auto';
    }

    $raw = trim($row_match_by);
    if ($raw === '') {
        return $default;
    }

    $key = sanitize_key($raw);
    if (in_array($key, $valid, true)) {
        return $key;
    }

    $match_trim = trim($agent_match);
    if ($match_trim !== '' && strcasecmp($raw, $match_trim) === 0) {
        return 'api_id';
    }

    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $match_trim)) {
        return 'api_id';
    }

    return $default;
}

/**
 * Run a callback while switched to the blog where agent CPT posts live.
 *
 * @template T
 * @param callable(): T $callback
 * @return T
 */
function rch_agent_import_with_agents_blog(callable $callback)
{
    if (! is_multisite()) {
        return $callback();
    }

    $main_id = (int) get_main_site_id();
    if (get_current_blog_id() === $main_id) {
        return $callback();
    }

    switch_to_blog($main_id);

    try {
        return $callback();
    } finally {
        restore_current_blog();
    }
}

/**
 * Parse uploaded CSV into normalized rows.
 *
 * @return array{rows: array<int, array<string, string>>, errors: string[]}
 */
function rch_agent_import_parse_csv_file(string $tmp_path): array
{
    $errors = [];
    if (! is_readable($tmp_path)) {
        return ['rows' => [], 'errors' => [__('Could not read the uploaded file.', 'rechat-plugin')]];
    }

    $handle = fopen($tmp_path, 'rb');
    if ($handle === false) {
        return ['rows' => [], 'errors' => [__('Could not open the CSV file.', 'rechat-plugin')]];
    }

    $header = fgetcsv($handle);
    if ($header === false || $header === [null]) {
        fclose($handle);
        return ['rows' => [], 'errors' => [__('CSV file is empty or has no header row.', 'rechat-plugin')]];
    }

    $aliases  = rch_agent_import_header_aliases();
    $map      = [];
    $required = false;

    foreach ($header as $i => $col) {
        $key = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $col)));
        if ($key === '') {
            continue;
        }
        if (isset($aliases[ $key ])) {
            $map[ (int) $i ] = $aliases[ $key ];
            if ($aliases[ $key ] === 'agent_match') {
                $required = true;
            }
        }
    }

    if (! $required) {
        fclose($handle);
        return [
            'rows'   => [],
            'errors' => [__('Missing required column: agent_match (or alias: agent, post_id, api_id, rechat_id).', 'rechat-plugin')],
        ];
    }

    $rows = [];
    $line = 1;
    while (($data = fgetcsv($handle)) !== false) {
        $line++;
        if ($data === [null] || $data === false) {
            continue;
        }
        $all_empty = true;
        foreach ($data as $cell) {
            if (trim((string) $cell) !== '') {
                $all_empty = false;
                break;
            }
        }
        if ($all_empty) {
            continue;
        }

        $first_cell = trim((string) ($data[0] ?? ''));
        if ($first_cell !== '' && strpos($first_cell, '#') === 0) {
            continue;
        }

        $row = [
            'agent_match'             => '',
            'match_by'                => '',
            'bio'                     => '',
            'agent_title_meta'        => '',
            'testimonial_name'        => '',
            'testimonial_description' => '',
            'testimonial_stars'       => '',
            'testimonial_link'        => '',
            '_line'                   => (string) $line,
        ];

        foreach ($map as $idx => $field) {
            if (isset($data[ $idx ])) {
                $row[ $field ] = rch_agent_import_normalize_cell((string) $data[ $idx ]);
            }
        }

        if ($row['agent_match'] !== '' && strpos($row['agent_match'], '#') === 0) {
            continue;
        }

        $has_continuation = $row['agent_match'] === ''
            && (
                $row['bio'] !== ''
                || $row['agent_title_meta'] !== ''
                || $row['testimonial_name'] !== ''
                || $row['testimonial_description'] !== ''
                || $row['testimonial_stars'] !== ''
                || $row['testimonial_link'] !== ''
            );

        if ($row['agent_match'] === '' && ! $has_continuation) {
            continue;
        }

        if ($row['agent_match'] === '' && $has_continuation) {
            $rows[] = $row;
            continue;
        }

        $rows[] = $row;
    }

    fclose($handle);

    if ($rows === [] && $errors === []) {
        $errors[] = __('No data rows found in CSV.', 'rechat-plugin');
    }

    return ['rows' => $rows, 'errors' => $errors];
}

/**
 * Resolve agent post ID from match value.
 */
function rch_agent_import_resolve_agent_id(string $match, string $match_by = 'auto'): int
{
    return rch_agent_import_with_agents_blog(
        static function () use ($match, $match_by): int {
            return rch_agent_import_resolve_agent_id_on_current_blog($match, $match_by);
        }
    );
}

/**
 * Resolve agent post ID on the current blog (call inside rch_agent_import_with_agents_blog).
 */
function rch_agent_import_resolve_agent_id_on_current_blog(string $match, string $match_by = 'auto'): int
{
    $match = trim($match);
    if ($match === '') {
        return 0;
    }

    $match_by = rch_agent_import_resolve_match_by($match_by, 'auto', $match);
    if ($match_by === 'auto') {
        if (ctype_digit($match)) {
            $post = get_post((int) $match);
            if ($post && $post->post_type === 'agents') {
                return (int) $post->ID;
            }
        }

        $by_api = rch_agent_import_find_agent_by_api_id($match);
        if ($by_api > 0) {
            return $by_api;
        }

        return rch_agent_import_find_agent_by_title($match);
    }

    if ($match_by === 'post_id') {
        $post = get_post(absint($match));
        return ($post && $post->post_type === 'agents') ? (int) $post->ID : 0;
    }

    if ($match_by === 'api_id') {
        return rch_agent_import_find_agent_by_api_id($match);
    }

    if ($match_by === 'title') {
        return rch_agent_import_find_agent_by_title($match);
    }

    return 0;
}

function rch_agent_import_find_agent_by_api_id(string $api_id): int
{
    return rch_agent_import_with_agents_blog(
        static function () use ($api_id): int {
            return rch_agent_import_find_agent_by_api_id_on_current_blog($api_id);
        }
    );
}

function rch_agent_import_find_agent_by_api_id_on_current_blog(string $api_id): int
{
    $api_id = trim($api_id);
    if ($api_id === '') {
        return 0;
    }

    $posts = get_posts([
        'post_type'      => 'agents',
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'api_id',
                'value'   => $api_id,
                'compare' => '=',
            ],
        ],
    ]);

    return ! empty($posts) ? (int) $posts[0] : 0;
}

function rch_agent_import_find_agent_by_title(string $title): int
{
    return rch_agent_import_with_agents_blog(
        static function () use ($title): int {
            return rch_agent_import_find_agent_by_title_on_current_blog($title);
        }
    );
}

function rch_agent_import_find_agent_by_title_on_current_blog(string $title): int
{
    $posts = get_posts([
        'post_type'              => 'agents',
        'posts_per_page'         => 1,
        'post_status'            => 'any',
        'fields'                 => 'ids',
        'title'                  => $title,
        'suppress_filters'       => false,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
    ]);

    if (! empty($posts)) {
        return (int) $posts[0];
    }

    global $wpdb;
    $id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'agents' AND post_title = %s AND post_status != 'trash' LIMIT 1",
            $title
        )
    );

    return $id > 0 ? $id : 0;
}

/**
 * Group CSV rows by resolved agent.
 *
 * @param array<int, array<string, string>> $rows
 * @return array<int, array{
 *   agent_id:int,
 *   agent_title:string,
 *   agent_match:string,
 *   bio:string,
 *   agent_title_meta:string,
 *   testimonials: array<int, array{name:string, description:string, stars:string, link:string}>,
 *   lines: int[],
 *   errors: string[]
 * }>
 */
function rch_agent_import_group_rows(array $rows, string $default_match_by): array
{
    $grouped        = [];
    $last_agent_id  = 0;

    foreach ($rows as $row) {
        $line      = isset($row['_line']) ? (int) $row['_line'] : 0;
        $match     = isset($row['agent_match']) ? trim((string) $row['agent_match']) : '';
        $row_match = rch_agent_import_resolve_match_by(
            isset($row['match_by']) ? (string) $row['match_by'] : '',
            $default_match_by,
            $match
        );

        if ($match === '' && $last_agent_id > 0) {
            $agent_id = $last_agent_id;
        } else {
            $agent_id = rch_agent_import_resolve_agent_id($match, $row_match);
            if ($agent_id > 0) {
                $last_agent_id = $agent_id;
            }
        }

        if ($agent_id <= 0) {
            $key = 'unresolved:' . md5($match . '|' . $row_match);
            if (! isset($grouped[ $key ])) {
                $grouped[ $key ] = [
                    'agent_id'      => 0,
                    'agent_title'   => '',
                    'agent_match'   => $match,
                    'bio'           => '',
                    'testimonials'  => [],
                    'lines'         => [],
                    'errors'        => [],
                ];
            }
            $grouped[ $key ]['lines'][] = $line;
            if ($match === '') {
                $grouped[ $key ]['errors'][] = sprintf(
                    /* translators: %d: CSV line number */
                    __('Line %d: testimonial row has no agent_match and no previous agent on file — skipped.', 'rechat-plugin'),
                    $line
                );
            } else {
                $grouped[ $key ]['errors'][] = sprintf(
                    /* translators: 1: match value, 2: line number */
                    __('No agent found for “%1$s” (line %2$d).', 'rechat-plugin'),
                    $match,
                    $line
                );
            }
            continue;
        }

        if (! isset($grouped[ $agent_id ])) {
            $post = rch_agent_import_with_agents_blog(
                static function () use ($agent_id) {
                    return get_post($agent_id);
                }
            );
            $grouped[ $agent_id ] = [
                'agent_id'          => $agent_id,
                'agent_title'       => $post ? (string) $post->post_title : '',
                'agent_match'       => $match,
                'bio'               => '',
                'agent_title_meta'  => '',
                'testimonials'      => [],
                'lines'             => [],
                'errors'            => [],
            ];
        }

        $grouped[ $agent_id ]['lines'][] = $line;

        $bio = isset($row['bio']) ? trim((string) $row['bio']) : '';
        if ($bio !== '') {
            $grouped[ $agent_id ]['bio'] = $bio;
        }

        $title_meta = isset($row['agent_title_meta']) ? trim((string) $row['agent_title_meta']) : '';
        if ($title_meta !== '') {
            $grouped[ $agent_id ]['agent_title_meta'] = $title_meta;
        }

        $t_name = isset($row['testimonial_name']) ? trim((string) $row['testimonial_name']) : '';
        $t_desc = isset($row['testimonial_description']) ? trim((string) $row['testimonial_description']) : '';
        $t_stars = isset($row['testimonial_stars']) ? trim((string) $row['testimonial_stars']) : '';
        $t_link  = isset($row['testimonial_link']) ? trim((string) $row['testimonial_link']) : '';

        if ($t_name !== '' || $t_desc !== '' || $t_stars !== '' || $t_link !== '') {
            $grouped[ $agent_id ]['testimonials'][] = [
                'name'        => $t_name,
                'description' => $t_desc,
                'stars'       => $t_stars,
                'link'        => $t_link,
            ];
        }
    }

    return $grouped;
}

/**
 * @param array<int, mixed> $grouped
 * @param array{
 *   match_by?:string,
 *   import_bio?:bool,
 *   import_agent_title?:bool,
 *   import_testimonials?:bool,
 *   testimonial_mode?:string
 * } $options
 */
function rch_agent_import_build_plan(array $grouped, array $options): array
{
    $import_bio           = ! empty($options['import_bio']);
    $import_agent_title   = ! empty($options['import_agent_title']);
    $import_testimonials  = ! empty($options['import_testimonials']);
    $testimonial_mode     = isset($options['testimonial_mode']) && $options['testimonial_mode'] === 'merge' ? 'merge' : 'replace';

    $plan = [
        'agents'   => [],
        'summary'  => [
            'total_rows'    => 0,
            'agents_ok'     => 0,
            'agents_skip'   => 0,
            'bios'          => 0,
            'agent_titles'  => 0,
            'testimonials'  => 0,
            'errors'        => 0,
        ],
        'errors'   => [],
    ];

    foreach ($grouped as $item) {
        if (! is_array($item)) {
            continue;
        }

        $agent_id = (int) ($item['agent_id'] ?? 0);
        $errors   = isset($item['errors']) && is_array($item['errors']) ? $item['errors'] : [];

        if ($agent_id <= 0) {
            foreach ($errors as $err) {
                $plan['errors'][] = $err;
            }
            $plan['summary']['errors'] += count($errors);
            $plan['summary']['agents_skip']++;
            continue;
        }

        $bio = $import_bio ? trim((string) ($item['bio'] ?? '')) : '';
        $title_meta = $import_agent_title ? trim((string) ($item['agent_title_meta'] ?? '')) : '';
        $new_testimonials = [];
        if ($import_testimonials && ! empty($item['testimonials']) && is_array($item['testimonials'])) {
            $new_testimonials = function_exists('rch_sanitize_agent_testimonials')
                ? rch_sanitize_agent_testimonials($item['testimonials'])
                : $item['testimonials'];
        }

        $will_bio   = $bio !== '';
        $will_title = $title_meta !== '';
        $will_t     = $new_testimonials !== [];

        if (! $will_bio && ! $will_title && ! $will_t) {
            $plan['summary']['agents_skip']++;
            $plan['agents'][] = [
                'agent_id'    => $agent_id,
                'agent_title' => (string) ($item['agent_title'] ?? ''),
                'status'      => 'skip',
                'message'     => __('No bio, agent title, or testimonial data for this agent in the CSV.', 'rechat-plugin'),
                'bio'         => false,
                'agent_title_meta' => false,
                'testimonials_count' => 0,
            ];
            continue;
        }

        $final_testimonial_count = count($new_testimonials);
        if ($will_t && $testimonial_mode === 'merge' && function_exists('rch_get_agent_testimonials')) {
            $existing = rch_get_agent_testimonials($agent_id);
            $merged   = function_exists('rch_sanitize_agent_testimonials')
                ? rch_sanitize_agent_testimonials(array_merge($existing, $new_testimonials))
                : array_merge($existing, $new_testimonials);
            $final_testimonial_count = count($merged);
        }

        $plan['summary']['agents_ok']++;
        if ($will_bio) {
            $plan['summary']['bios']++;
        }
        if ($will_title) {
            $plan['summary']['agent_titles']++;
        }
        if ($will_t) {
            $plan['summary']['testimonials'] += count($new_testimonials);
        }

        $plan['agents'][] = [
            'agent_id'             => $agent_id,
            'agent_title'          => (string) ($item['agent_title'] ?? ''),
            'status'               => 'ready',
            'message'              => '',
            'bio'                  => $will_bio,
            'bio_preview'          => $will_bio ? wp_html_excerpt($bio, 120, '…') : '',
            'agent_title_meta'     => $will_title,
            'agent_title_preview'  => $will_title ? wp_html_excerpt($title_meta, 80, '…') : '',
            'testimonials_count'   => $final_testimonial_count,
            'testimonials_new'     => count($new_testimonials),
            'testimonial_mode'     => $testimonial_mode,
        ];
    }

    return $plan;
}

/**
 * Execute import from grouped rows.
 *
 * @param array<int, mixed> $grouped
 * @param array<string, mixed> $options
 */
function rch_agent_import_run_grouped(array $grouped, array $options): array
{
    $plan = rch_agent_import_build_plan($grouped, $options);

    $import_bio          = ! empty($options['import_bio']);
    $import_agent_title  = ! empty($options['import_agent_title']);
    $import_testimonials = ! empty($options['import_testimonials']);
    $testimonial_mode    = isset($options['testimonial_mode']) && $options['testimonial_mode'] === 'merge' ? 'merge' : 'replace';

    $updated = 0;
    $failed  = 0;

    foreach ($grouped as $item) {
        if (! is_array($item) || (int) ($item['agent_id'] ?? 0) <= 0) {
            continue;
        }

        $agent_id = (int) $item['agent_id'];
        $did      = false;

        rch_agent_import_with_agents_blog(
            static function () use (
                $import_agent_title,
                $import_bio,
                $import_testimonials,
                $testimonial_mode,
                &$did,
                &$plan,
                &$failed,
                &$updated,
                $item,
                $agent_id
            ): void {
        if ($import_agent_title) {
            $title_meta = trim((string) ($item['agent_title_meta'] ?? ''));
            if ($title_meta !== '') {
                update_post_meta($agent_id, 'agent_title', sanitize_text_field($title_meta));
                $did = true;
            }
        }

        if ($import_bio) {
            $bio = trim((string) ($item['bio'] ?? ''));
            if ($bio !== '') {
                $result = wp_update_post(
                    [
                        'ID'           => $agent_id,
                        'post_content' => wp_kses_post($bio),
                    ],
                    true
                );
                if (is_wp_error($result)) {
                    $plan['errors'][] = sprintf(
                        /* translators: 1: agent title, 2: error message */
                        __('Bio update failed for “%1$s”: %2$s', 'rechat-plugin'),
                        (string) ($item['agent_title'] ?? $agent_id),
                        $result->get_error_message()
                    );
                    $failed++;

                    return;
                }
                $did = true;
            }
        }

        if ($import_testimonials && ! empty($item['testimonials']) && is_array($item['testimonials'])) {
            $new = function_exists('rch_sanitize_agent_testimonials')
                ? rch_sanitize_agent_testimonials($item['testimonials'])
                : $item['testimonials'];

            if ($new !== []) {
                if ($testimonial_mode === 'merge' && function_exists('rch_get_agent_testimonials')) {
                    $existing = rch_get_agent_testimonials($agent_id);
                    $new      = function_exists('rch_sanitize_agent_testimonials')
                        ? rch_sanitize_agent_testimonials(array_merge($existing, $new))
                        : array_merge($existing, $new);
                }

                update_post_meta($agent_id, RCH_AGENT_TESTIMONIALS_META_KEY, $new);
                $did = true;

                if (function_exists('rch_agent_wizard_sync_testimonials_for_agent') && is_multisite()) {
                    $sync = rch_agent_wizard_sync_testimonials_for_agent($agent_id);
                    if (is_wp_error($sync) && $sync->get_error_code() !== 'rch_testimonial_no_blog') {
                        $plan['errors'][] = sprintf(
                            /* translators: 1: agent title, 2: error message */
                            __('Testimonial sub-site sync failed for “%1$s”: %2$s', 'rechat-plugin'),
                            (string) ($item['agent_title'] ?? $agent_id),
                            $sync->get_error_message()
                        );
                    }
                }
            } elseif ($testimonial_mode === 'replace') {
                delete_post_meta($agent_id, RCH_AGENT_TESTIMONIALS_META_KEY);
            }
        }

        if ($did) {
            $updated++;
        }
            }
        );
    }

    $plan['summary']['updated'] = $updated;
    $plan['summary']['failed']  = $failed;

    return $plan;
}

function rch_agent_import_verify_request(): bool
{
    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        return false;
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    return wp_verify_nonce($nonce, RCH_AGENT_IMPORT_NONCE_ACTION);
}

function rch_agent_import_get_options_from_request(): array
{
    $match_by = isset($_POST['match_by']) ? sanitize_key(wp_unslash($_POST['match_by'])) : 'auto';
    if (! isset(rch_agent_import_allowed_match_by()[ $match_by ])) {
        $match_by = 'auto';
    }

    $mode = isset($_POST['testimonial_mode']) ? sanitize_key(wp_unslash($_POST['testimonial_mode'])) : 'replace';
    if ($mode !== 'merge') {
        $mode = 'replace';
    }

    return [
        'match_by'             => $match_by,
        'import_bio'           => ! empty($_POST['import_bio']),
        'import_agent_title'   => ! empty($_POST['import_agent_title']),
        'import_testimonials'  => ! empty($_POST['import_testimonials']),
        'testimonial_mode'     => $mode,
    ];
}

function rch_agent_import_ajax_preview(): void
{
    if (! rch_agent_import_verify_request()) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    if (empty($_FILES['csv_file']['tmp_name'])) {
        wp_send_json_error(['message' => __('Please choose a CSV file.', 'rechat-plugin')]);
    }

    $parsed = rch_agent_import_parse_csv_file((string) $_FILES['csv_file']['tmp_name']);
    if ($parsed['rows'] === [] && $parsed['errors'] !== []) {
        wp_send_json_error(['message' => implode(' ', $parsed['errors'])]);
    }

    $options = rch_agent_import_get_options_from_request();
    $grouped = rch_agent_import_group_rows($parsed['rows'], $options['match_by']);
    $plan    = rch_agent_import_build_plan($grouped, $options);

    $plan['parse_errors'] = $parsed['errors'];
    $plan['summary']['total_rows'] = count($parsed['rows']);

    wp_send_json_success($plan);
}
add_action('wp_ajax_rch_agent_import_preview', 'rch_agent_import_ajax_preview');

function rch_agent_import_ajax_run(): void
{
    if (! rch_agent_import_verify_request()) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    if (empty($_FILES['csv_file']['tmp_name'])) {
        wp_send_json_error(['message' => __('Please choose a CSV file.', 'rechat-plugin')]);
    }

    $parsed = rch_agent_import_parse_csv_file((string) $_FILES['csv_file']['tmp_name']);
    if ($parsed['rows'] === []) {
        wp_send_json_error(['message' => implode(' ', $parsed['errors']) ?: __('No rows to import.', 'rechat-plugin')]);
    }

    $options = rch_agent_import_get_options_from_request();
    if (empty($options['import_bio']) && empty($options['import_agent_title']) && empty($options['import_testimonials'])) {
        wp_send_json_error(['message' => __('Select at least one: Import bio, agent title, or testimonials.', 'rechat-plugin')]);
    }

    $grouped = rch_agent_import_group_rows($parsed['rows'], $options['match_by']);
    $result  = rch_agent_import_run_grouped($grouped, $options);

    $result['parse_errors'] = $parsed['errors'];
    $result['summary']['total_rows'] = count($parsed['rows']);
    $result['message'] = sprintf(
        /* translators: %d: number of agents updated */
        _n(
            'Import finished. %d agent updated.',
            'Import finished. %d agents updated.',
            (int) ($result['summary']['updated'] ?? 0),
            'rechat-plugin'
        ),
        (int) ($result['summary']['updated'] ?? 0)
    );

    wp_send_json_success($result);
}
add_action('wp_ajax_rch_agent_import_run', 'rch_agent_import_ajax_run');

function rch_agent_import_download_sample(): void
{
    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        wp_die(esc_html__('Permission denied.', 'rechat-plugin'), '', ['response' => 403]);
    }

    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if (! wp_verify_nonce($nonce, RCH_AGENT_IMPORT_NONCE_ACTION)) {
        wp_die(esc_html__('Invalid request.', 'rechat-plugin'), '', ['response' => 403]);
    }

    $variant = isset($_GET['variant']) ? sanitize_key(wp_unslash($_GET['variant'])) : 'full';

    if ($variant === 'simple') {
        $csv  = "# One agent, one row — bio, title, and one testimonial\n";
        $csv .= "agent_match,match_by,bio,title,testimonial_name,testimonial_description,testimonial_rank,testimonial_link\n";
        $csv .= "42,post_id,\"Your bio paragraph here.\",Licensed Real Estate Agent,Jane Client,\"They were wonderful to work with.\",4.8,https://example.com/review\n";
        $filename = 'rechat-agent-import-simple.csv';
    } else {
        $csv  = "# Lines starting with # are ignored. Empty agent_match = same agent as the row above (extra testimonials).\n";
        $csv .= "agent_match,match_by,bio,title,testimonial_name,testimonial_description,testimonial_rank,testimonial_link\n";
        $csv .= "# Agent A (WP post ID 42): row 1 = bio + title | rows 2-3 = more testimonials (agent_match blank)\n";
        $csv .= "42,post_id,\"Jane has 10 years of experience.\",Licensed Real Estate Agent,,\n";
        $csv .= ",,,Sarah M.,\"Our home purchase was smooth.\",5,https://example.com/review/1\n";
        $csv .= ",,,Tom R.,\"Highly professional.\",4.7,https://example.com/review/2\n";
        $csv .= "# Agent B (Rechat api_id): everything on one row\n";
        $csv .= "YOUR-RECHAT-API-ID,api_id,\"Short bio.\",Principal Broker,Alex P.,\"Would recommend.\",5,https://example.com/review/3\n";
        $filename = 'rechat-agent-import-sample.csv';
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $csv;
    exit;
}
add_action('admin_post_rch_agent_import_sample_csv', 'rch_agent_import_download_sample');

function rch_agent_import_enqueue_assets(string $hook): void
{
    if ($hook !== 'toplevel_page_rechat-setting') {
        return;
    }

    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    if ($tab !== 'agent-import') {
        return;
    }

    if (! defined('RCH_PLUGIN_URL') || ! defined('RCH_VERSION')) {
        return;
    }

    wp_enqueue_style(
        'rch-agent-import',
        RCH_PLUGIN_URL . 'assets/css/rch-agent-import.css',
        ['rch-admin-styles'],
        RCH_VERSION
    );

    wp_enqueue_script(
        'rch-agent-import',
        RCH_PLUGIN_URL . 'assets/js/rch-agent-import.js',
        ['jquery'],
        RCH_VERSION,
        true
    );

    wp_localize_script(
        'rch-agent-import',
        'rchAgentImport',
        [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce(RCH_AGENT_IMPORT_NONCE_ACTION),
            'sampleUrl' => wp_nonce_url(
                admin_url('admin-post.php?action=rch_agent_import_sample_csv'),
                RCH_AGENT_IMPORT_NONCE_ACTION,
                'nonce'
            ),
            'i18n'      => [
                'chooseFile'    => __('Choose a CSV file first.', 'rechat-plugin'),
                'previewing'    => __('Analyzing CSV…', 'rechat-plugin'),
                'importing'     => __('Importing…', 'rechat-plugin'),
                'previewReady'  => __('Preview ready. Review the table, then run import.', 'rechat-plugin'),
                'previewFailed' => __('Preview failed.', 'rechat-plugin'),
                'importFailed'  => __('Import failed.', 'rechat-plugin'),
                'importDone'    => __('Import completed.', 'rechat-plugin'),
                'confirmImport' => __('Run import now? This updates agent bios and/or testimonials on the live site.', 'rechat-plugin'),
                'noAgents'      => __('No agents ready to import. Fix errors in your CSV and preview again.', 'rechat-plugin'),
                'selectOne'     => __('Enable “Import bio”, “Agent title”, and/or “Import testimonials”.', 'rechat-plugin'),
                'agentTitles'   => __('agent titles', 'rechat-plugin'),
            ],
        ]
    );
}
add_action('admin_enqueue_scripts', 'rch_agent_import_enqueue_assets');

function rch_agent_import_render_tab(): void
{
    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        return;
    }

    if (! post_type_exists('agents')) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('Agents post type is not available on this site.', 'rechat-plugin') . '</p></div>';
        return;
    }

    include RCH_PLUGIN_INCLUDES . 'admin/views/agent-data-import-tab.php';
}

/**
 * Sample CSV download URLs for the import tab.
 *
 * @return array{full:string, simple:string}
 */
function rch_agent_import_get_sample_urls(): array
{
    return [
        'full'   => wp_nonce_url(
            admin_url('admin-post.php?action=rch_agent_import_sample_csv'),
            RCH_AGENT_IMPORT_NONCE_ACTION,
            'nonce'
        ),
        'simple' => wp_nonce_url(
            admin_url('admin-post.php?action=rch_agent_import_sample_csv&variant=simple'),
            RCH_AGENT_IMPORT_NONCE_ACTION,
            'nonce'
        ),
    ];
}
