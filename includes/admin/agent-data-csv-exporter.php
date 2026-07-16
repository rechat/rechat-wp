<?php
/**
 * Agent CSV export.
 *
 * Streams a CSV of the `agents` custom post type, with the admin choosing which
 * fields (core post fields + agent custom meta) to include. Pairs with the CSV
 * importer; both live under the "Import / Export Agents" settings tab.
 *
 * @package RechatPlugin
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Export nonce action. */
const RCH_AGENT_EXPORT_NONCE_ACTION = 'rch_agent_export';

/**
 * Exportable agent fields: key => [label, post].
 *
 * `post` is the WP_Post property to read for core fields; when absent the key is
 * treated as a post-meta key. Filterable so themes/add-ons can expose extra meta.
 *
 * @return array<string, array{label:string, post?:string}>
 */
function rch_agent_export_fields(): array
{
    $fields = [
        'post_id'           => ['label' => __('Post ID', 'rechat-plugin'), 'post' => 'ID'],
        'post_title'        => ['label' => __('Agent name (post title)', 'rechat-plugin'), 'post' => 'post_title'],
        'bio'               => ['label' => __('Bio (post content)', 'rechat-plugin'), 'post' => 'post_content'],
        'api_id'            => ['label' => __('Rechat ID (api_id)', 'rechat-plugin')],
        'first_name'        => ['label' => __('First name', 'rechat-plugin')],
        'last_name'         => ['label' => __('Last name', 'rechat-plugin')],
        'email'             => ['label' => __('Email', 'rechat-plugin')],
        'phone_number'      => ['label' => __('Phone number', 'rechat-plugin')],
        'agent_title'       => ['label' => __('Agent title', 'rechat-plugin')],
        'designation'       => ['label' => __('Designation', 'rechat-plugin')],
        'license_number'    => ['label' => __('License number', 'rechat-plugin')],
        'website'           => ['label' => __('Website', 'rechat-plugin')],
        'facebook'          => ['label' => __('Facebook', 'rechat-plugin')],
        'instagram'         => ['label' => __('Instagram', 'rechat-plugin')],
        'twitter'           => ['label' => __('Twitter', 'rechat-plugin')],
        'linkedin'          => ['label' => __('LinkedIn', 'rechat-plugin')],
        'youtube'           => ['label' => __('YouTube', 'rechat-plugin')],
        'profile_image_url' => ['label' => __('Profile image URL', 'rechat-plugin')],
        'timezone'          => ['label' => __('Timezone', 'rechat-plugin')],
        'agent_address'     => ['label' => __('Address', 'rechat-plugin')],
        'office_address'    => ['label' => __('Office address', 'rechat-plugin')],
        'agent_visibility'  => ['label' => __('Visibility', 'rechat-plugin')],
        '_rch_agent_offices' => ['label' => __('Offices', 'rechat-plugin')],
        '_rch_agent_regions' => ['label' => __('Regions', 'rechat-plugin')],
        'agent_testimonials' => ['label' => __('Testimonials (JSON)', 'rechat-plugin')],
    ];

    /**
     * Filter the exportable agent fields.
     *
     * @param array $fields key => [label, post?].
     */
    return (array) apply_filters('rch_agent_export_fields', $fields);
}

/**
 * Fields checked by default in the export UI.
 *
 * @return list<string>
 */
function rch_agent_export_default_fields(): array
{
    return [
        'post_id',
        'post_title',
        'api_id',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'agent_title',
    ];
}

/**
 * Value of one export field for an agent post (scalars as-is; arrays as JSON).
 *
 * @param WP_Post $post  Agent post.
 * @param string  $key   Field key.
 * @param array   $field Field definition.
 * @return string
 */
function rch_agent_export_field_value(WP_Post $post, string $key, array $field): string
{
    if (! empty($field['post'])) {
        $prop = $field['post'];
        $val  = isset($post->$prop) ? $post->$prop : '';
        return (string) $val;
    }

    $val = get_post_meta($post->ID, $key, true);

    if (is_array($val) || is_object($val)) {
        return (string) wp_json_encode($val);
    }

    return (string) $val;
}

/**
 * Handle the export request: validate, gather agents, stream a CSV download.
 *
 * @return void
 */
function rch_agent_export_csv(): void
{
    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'rechat-plugin'));
    }
    check_admin_referer(RCH_AGENT_EXPORT_NONCE_ACTION, 'rch_agent_export_nonce');

    $all_fields = rch_agent_export_fields();

    // Selected fields in the order submitted (drag-to-reorder in the UI controls
    // the CSV column order). Keep valid, unique keys; fall back to defaults.
    $submitted = isset($_POST['fields']) && is_array($_POST['fields'])
        ? array_map('sanitize_key', wp_unslash($_POST['fields']))
        : [];
    $requested = [];
    foreach ($submitted as $key) {
        if (isset($all_fields[$key]) && ! in_array($key, $requested, true)) {
            $requested[] = $key;
        }
    }
    if (empty($requested)) {
        $requested = array_values(array_intersect(array_keys($all_fields), rch_agent_export_default_fields()));
    }

    // Query agents on the blog that owns the agents CPT (multisite-safe).
    $runner = function () {
        $q = new WP_Query([
            'post_type'      => 'agents',
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            'suppress_filters' => true,
        ]);

        return $q->posts;
    };

    $posts = function_exists('rch_agent_import_with_agents_blog')
        ? rch_agent_import_with_agents_blog($runner)
        : $runner();

    $filename = 'agents-export-' . gmdate('Y-m-d') . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel opens accented characters correctly.
    fwrite($out, "\xEF\xBB\xBF");

    // Header row = field keys (machine-friendly, import-compatible).
    fputcsv($out, $requested);

    if (is_array($posts)) {
        foreach ($posts as $post) {
            if (! $post instanceof WP_Post) {
                continue;
            }
            $row = [];
            foreach ($requested as $key) {
                $row[] = rch_agent_export_field_value($post, $key, $all_fields[$key]);
            }
            fputcsv($out, $row);
        }
    }

    fclose($out);
    exit;
}
add_action('admin_post_rch_agent_export_csv', 'rch_agent_export_csv');
