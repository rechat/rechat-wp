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
    ];
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
            'testimonial_name'        => '',
            'testimonial_description' => '',
            '_line'                   => (string) $line,
        ];

        foreach ($map as $idx => $field) {
            if (isset($data[ $idx ])) {
                $row[ $field ] = trim((string) $data[ $idx ]);
            }
        }

        if ($row['agent_match'] === '' || strpos($row['agent_match'], '#') === 0) {
            if ($row['agent_match'] !== '') {
                continue;
            }
            $errors[] = sprintf(
                /* translators: %d: CSV line number */
                __('Line %d: agent_match is empty — row skipped.', 'rechat-plugin'),
                $line
            );
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
    $match = trim($match);
    if ($match === '') {
        return 0;
    }

    $match_by = sanitize_key($match_by);
    if ($match_by === '' || $match_by === 'auto') {
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
 *   testimonials: array<int, array{name:string, description:string}>,
 *   lines: int[],
 *   errors: string[]
 * }>
 */
function rch_agent_import_group_rows(array $rows, string $default_match_by): array
{
    $grouped = [];

    foreach ($rows as $row) {
        $line      = isset($row['_line']) ? (int) $row['_line'] : 0;
        $match     = $row['agent_match'] ?? '';
        $row_match = isset($row['match_by']) && $row['match_by'] !== ''
            ? sanitize_key($row['match_by'])
            : $default_match_by;

        $agent_id = rch_agent_import_resolve_agent_id($match, $row_match);

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
            $grouped[ $key ]['errors'][] = sprintf(
                /* translators: 1: match value, 2: line number */
                __('No agent found for “%1$s” (line %2$d).', 'rechat-plugin'),
                $match,
                $line
            );
            continue;
        }

        if (! isset($grouped[ $agent_id ])) {
            $post = get_post($agent_id);
            $grouped[ $agent_id ] = [
                'agent_id'     => $agent_id,
                'agent_title'  => $post ? (string) $post->post_title : '',
                'agent_match'  => $match,
                'bio'          => '',
                'testimonials' => [],
                'lines'        => [],
                'errors'       => [],
            ];
        }

        $grouped[ $agent_id ]['lines'][] = $line;

        $bio = isset($row['bio']) ? trim((string) $row['bio']) : '';
        if ($bio !== '') {
            $grouped[ $agent_id ]['bio'] = $bio;
        }

        $t_name = isset($row['testimonial_name']) ? trim((string) $row['testimonial_name']) : '';
        $t_desc = isset($row['testimonial_description']) ? trim((string) $row['testimonial_description']) : '';
        if ($t_name !== '' || $t_desc !== '') {
            $grouped[ $agent_id ]['testimonials'][] = [
                'name'        => $t_name,
                'description' => $t_desc,
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
 *   import_testimonials?:bool,
 *   testimonial_mode?:string
 * } $options
 */
function rch_agent_import_build_plan(array $grouped, array $options): array
{
    $import_bio           = ! empty($options['import_bio']);
    $import_testimonials  = ! empty($options['import_testimonials']);
    $testimonial_mode     = isset($options['testimonial_mode']) && $options['testimonial_mode'] === 'merge' ? 'merge' : 'replace';

    $plan = [
        'agents'   => [],
        'summary'  => [
            'total_rows'    => 0,
            'agents_ok'     => 0,
            'agents_skip'   => 0,
            'bios'          => 0,
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
        $new_testimonials = [];
        if ($import_testimonials && ! empty($item['testimonials']) && is_array($item['testimonials'])) {
            $new_testimonials = function_exists('rch_sanitize_agent_testimonials')
                ? rch_sanitize_agent_testimonials($item['testimonials'])
                : $item['testimonials'];
        }

        $will_bio = $bio !== '';
        $will_t   = $new_testimonials !== [];

        if (! $will_bio && ! $will_t) {
            $plan['summary']['agents_skip']++;
            $plan['agents'][] = [
                'agent_id'    => $agent_id,
                'agent_title' => (string) ($item['agent_title'] ?? ''),
                'status'      => 'skip',
                'message'     => __('No bio or testimonial data for this agent in the CSV.', 'rechat-plugin'),
                'bio'         => false,
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
                    continue;
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
            } elseif ($testimonial_mode === 'replace') {
                delete_post_meta($agent_id, RCH_AGENT_TESTIMONIALS_META_KEY);
            }
        }

        if ($did) {
            $updated++;
        }
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
    if (empty($options['import_bio']) && empty($options['import_testimonials'])) {
        wp_send_json_error(['message' => __('Select at least one: Import bio or Import testimonials.', 'rechat-plugin')]);
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
        $csv  = "# One agent, one row — bio and one testimonial together\n";
        $csv .= "agent_match,match_by,bio,testimonial_name,testimonial_description\n";
        $csv .= "42,post_id,\"Your bio paragraph here.\",Jane Client,\"They were wonderful to work with.\"\n";
        $filename = 'rechat-agent-import-simple.csv';
    } else {
        $csv  = "# Lines starting with # are ignored. Empty cells = leave that field unchanged on this row.\n";
        $csv .= "agent_match,match_by,bio,testimonial_name,testimonial_description\n";
        $csv .= "# Agent A (WP post ID 42): row 1 = bio only | rows 2-3 = two testimonials (same agent_match)\n";
        $csv .= "42,post_id,\"Jane has 10 years of experience helping buyers and sellers.\",,\n";
        $csv .= "42,post_id,,Sarah M.,\"Our home purchase was smooth and stress-free.\"\n";
        $csv .= "42,post_id,,Tom R.,\"Highly professional and always available.\"\n";
        $csv .= "# Agent B (Rechat api_id): bio + one testimonial on a single row\n";
        $csv .= "YOUR-RECHAT-API-ID,api_id,\"Short bio from CSV.\",Alex P.,\"Would recommend to anyone.\"\n";
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
                'selectOne'     => __('Enable “Import bio” and/or “Import testimonials”.', 'rechat-plugin'),
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
