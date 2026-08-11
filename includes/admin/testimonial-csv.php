<?php
/**
 * Testimonial CPT CSV import / export.
 *
 * Exports every `testimonial` post (name, text, stars, link) to CSV and imports
 * the same shape back (create or update). Stars live in the `testimonial_stars`
 * meta registered by testimonial-cpt-subfields.php; the link in `testimonial_link`.
 * Rendered on the "Import / Export" settings tab.
 *
 * @package RechatPlugin
 */

if (! defined('ABSPATH')) {
    exit;
}

const RCH_TESTIMONIAL_EXPORT_NONCE_ACTION = 'rch_testimonial_export';
const RCH_TESTIMONIAL_IMPORT_NONCE_ACTION = 'rch_testimonial_import';

/**
 * Machine column keys for the testimonial CSV (header row, import-compatible).
 *
 * @return array<string,string> key => human label
 */
function rch_testimonial_csv_columns(): array
{
    return [
        'post_id'     => __('Post ID', 'rechat-plugin'),
        'name'        => __('Name (post title)', 'rechat-plugin'),
        'testimonial' => __('Testimonial (post content)', 'rechat-plugin'),
        'stars'       => __('Stars', 'rechat-plugin'),
        'link'        => __('Link', 'rechat-plugin'),
        'status'      => __('Status', 'rechat-plugin'),
        'date'        => __('Date', 'rechat-plugin'),
    ];
}

/**
 * Sanitize a stars value to a 0–5 number string ('' when empty/invalid).
 */
function rch_testimonial_csv_sanitize_stars($value): string
{
    if (function_exists('rch_sanitize_agent_testimonial_stars')) {
        return rch_sanitize_agent_testimonial_stars($value);
    }
    $raw = trim(str_replace(',', '.', (string) $value));
    if ($raw === '' || ! is_numeric($raw)) {
        return '';
    }
    $n = (float) $raw;
    if ($n < 0 || $n > 5) {
        return '';
    }
    return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
}

/**
 * Export every testimonial post as a CSV download.
 *
 * @return void
 */
function rch_testimonial_export_csv(): void
{
    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'rechat-plugin'));
    }
    check_admin_referer(RCH_TESTIMONIAL_EXPORT_NONCE_ACTION, 'rch_testimonial_export_nonce');

    if (! post_type_exists('testimonial')) {
        wp_die(esc_html__('The testimonial post type is not registered on this site.', 'rechat-plugin'));
    }

    $query = new WP_Query([
        'post_type'        => 'testimonial',
        'post_status'      => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page'   => -1,
        'orderby'          => 'date',
        'order'            => 'DESC',
        'no_found_rows'    => true,
        'suppress_filters' => true,
    ]);

    $columns  = rch_testimonial_csv_columns();
    $filename = 'testimonials-export-' . gmdate('Y-m-d') . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel renders accented characters.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_keys($columns));

    foreach ($query->posts as $post) {
        if (! $post instanceof WP_Post) {
            continue;
        }
        $stars = function_exists('rch_get_testimonial_stars') ? rch_get_testimonial_stars($post->ID) : (string) get_post_meta($post->ID, 'testimonial_stars', true);
        $link  = function_exists('rch_get_testimonial_link') ? rch_get_testimonial_link($post->ID) : (string) get_post_meta($post->ID, 'testimonial_link', true);

        fputcsv($out, [
            $post->ID,
            $post->post_title,
            $post->post_content,
            $stars,
            $link,
            $post->post_status,
            $post->post_date,
        ]);
    }

    fclose($out);
    exit;
}
add_action('admin_post_rch_testimonial_export_csv', 'rch_testimonial_export_csv');

/**
 * Import testimonials from an uploaded CSV (create or update by post_id).
 *
 * @return void
 */
function rch_testimonial_import_csv(): void
{
    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'rechat-plugin'));
    }
    check_admin_referer(RCH_TESTIMONIAL_IMPORT_NONCE_ACTION, 'rch_testimonial_import_nonce');

    $redirect = admin_url('admin.php?page=rechat-setting&tab=agent-import');

    if (! post_type_exists('testimonial')) {
        wp_safe_redirect(add_query_arg('rch_ti_import', 'no_cpt', $redirect));
        exit;
    }

    if (empty($_FILES['rch_testimonial_csv']['tmp_name']) || ! is_uploaded_file($_FILES['rch_testimonial_csv']['tmp_name'])) {
        wp_safe_redirect(add_query_arg('rch_ti_import', 'no_file', $redirect));
        exit;
    }

    $handle = fopen($_FILES['rch_testimonial_csv']['tmp_name'], 'r');
    if (! $handle) {
        wp_safe_redirect(add_query_arg('rch_ti_import', 'no_file', $redirect));
        exit;
    }

    // Header row → column index map (accept name/testimonial aliases).
    $header = fgetcsv($handle);
    if (! is_array($header)) {
        fclose($handle);
        wp_safe_redirect(add_query_arg('rch_ti_import', 'empty', $redirect));
        exit;
    }
    $header = array_map(static function ($h) {
        return strtolower(trim((string) $h));
    }, $header);
    // Strip a UTF-8 BOM off the first header cell.
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }
    $idx = array_flip($header);

    $get = static function (array $row, array $idx, array $keys) {
        foreach ($keys as $k) {
            if (isset($idx[$k]) && isset($row[$idx[$k]])) {
                return trim((string) $row[$idx[$k]]);
            }
        }
        return '';
    };

    $created = 0;
    $updated = 0;

    while (($row = fgetcsv($handle)) !== false) {
        if (! is_array($row)) {
            continue;
        }
        $name  = $get($row, $idx, ['name', 'title', 'post_title']);
        $text  = $get($row, $idx, ['testimonial', 'content', 'post_content', 'text', 'description']);
        $stars = rch_testimonial_csv_sanitize_stars($get($row, $idx, ['stars', 'testimonial_stars', 'rating', 'rank']));
        $link  = esc_url_raw($get($row, $idx, ['link', 'testimonial_link', 'url']));
        $pid   = (int) $get($row, $idx, ['post_id', 'id']);

        // Skip a fully empty row.
        if ($name === '' && $text === '' && $stars === '' && $link === '') {
            continue;
        }

        $existing = ($pid > 0 && get_post($pid) && get_post_type($pid) === 'testimonial') ? $pid : 0;

        if ($existing > 0) {
            wp_update_post([
                'ID'           => $existing,
                'post_title'   => $name,
                'post_content' => $text,
            ]);
            $post_id = $existing;
            $updated++;
        } else {
            $post_id = wp_insert_post([
                'post_type'    => 'testimonial',
                'post_status'  => 'publish',
                'post_title'   => $name !== '' ? $name : __('Testimonial', 'rechat-plugin'),
                'post_content' => $text,
            ]);
            if (is_wp_error($post_id) || ! $post_id) {
                continue;
            }
            $created++;
        }

        if ($stars !== '') {
            update_post_meta($post_id, defined('RCH_TESTIMONIAL_STARS_META') ? RCH_TESTIMONIAL_STARS_META : 'testimonial_stars', $stars);
        }
        if ($link !== '') {
            update_post_meta($post_id, defined('RCH_TESTIMONIAL_LINK_META') ? RCH_TESTIMONIAL_LINK_META : 'testimonial_link', $link);
        }
    }

    fclose($handle);

    $redirect = add_query_arg([
        'rch_ti_import'  => 'done',
        'rch_ti_created' => $created,
        'rch_ti_updated' => $updated,
    ], $redirect);
    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_rch_testimonial_import_csv', 'rch_testimonial_import_csv');

/**
 * Admin notice after a testimonial import (shown on the settings page).
 *
 * @return void
 */
function rch_testimonial_import_admin_notice(): void
{
    if (! isset($_GET['page'], $_GET['rch_ti_import']) || $_GET['page'] !== 'rechat-setting') {
        return;
    }
    $status = sanitize_key(wp_unslash($_GET['rch_ti_import']));

    if ($status === 'done') {
        $created = isset($_GET['rch_ti_created']) ? (int) $_GET['rch_ti_created'] : 0;
        $updated = isset($_GET['rch_ti_updated']) ? (int) $_GET['rch_ti_updated'] : 0;
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: 1: created count, 2: updated count */
                __('Testimonials imported: %1$d created, %2$d updated.', 'rechat-plugin'),
                $created,
                $updated
            ))
        );
        return;
    }

    $messages = [
        'no_file' => __('No CSV file was uploaded.', 'rechat-plugin'),
        'no_cpt'  => __('The testimonial post type is not registered on this site.', 'rechat-plugin'),
        'empty'   => __('The CSV file was empty.', 'rechat-plugin'),
    ];
    if (isset($messages[$status])) {
        printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html($messages[$status]));
    }
}
add_action('admin_notices', 'rch_testimonial_import_admin_notice');

/**
 * Render the testimonial export + import tools (called from the Import/Export tab).
 *
 * @return void
 */
function rch_testimonial_tools_render(): void
{
    if (! post_type_exists('testimonial')) {
        return;
    }
    $count = wp_count_posts('testimonial');
    $total = 0;
    if (is_object($count)) {
        foreach (['publish', 'draft', 'pending', 'private'] as $st) {
            $total += isset($count->$st) ? (int) $count->$st : 0;
        }
    }
    $action = esc_url(admin_url('admin-post.php'));
    ?>
    <div class="rch-testimonial-tools" style="margin-top:32px;padding-top:24px;border-top:1px solid #dcdcde;">
        <h2><?php esc_html_e('Testimonials (CPT)', 'rechat-plugin'); ?></h2>
        <p class="description">
            <?php
            printf(
                /* translators: %d: number of testimonial posts */
                esc_html__('Export all %d testimonial posts (name, text, stars, link), or import a CSV of the same columns.', 'rechat-plugin'),
                (int) $total
            );
            ?>
        </p>

        <div style="display:flex;gap:32px;flex-wrap:wrap;margin-top:16px;">
            <form method="post" action="<?php echo $action; ?>">
                <input type="hidden" name="action" value="rch_testimonial_export_csv" />
                <?php wp_nonce_field(RCH_TESTIMONIAL_EXPORT_NONCE_ACTION, 'rch_testimonial_export_nonce'); ?>
                <h3 style="margin:0 0 8px;"><?php esc_html_e('Export', 'rechat-plugin'); ?></h3>
                <button type="submit" class="button button-primary"><?php esc_html_e('Export testimonials CSV', 'rechat-plugin'); ?></button>
            </form>

            <form method="post" action="<?php echo $action; ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="rch_testimonial_import_csv" />
                <?php wp_nonce_field(RCH_TESTIMONIAL_IMPORT_NONCE_ACTION, 'rch_testimonial_import_nonce'); ?>
                <h3 style="margin:0 0 8px;"><?php esc_html_e('Import', 'rechat-plugin'); ?></h3>
                <p class="description" style="margin:0 0 8px;">
                    <?php esc_html_e('Columns: post_id (optional), name, testimonial, stars, link. Rows with a matching post_id update; others create.', 'rechat-plugin'); ?>
                </p>
                <input type="file" name="rch_testimonial_csv" accept=".csv,text/csv" required />
                <button type="submit" class="button"><?php esc_html_e('Import testimonials CSV', 'rechat-plugin'); ?></button>
            </form>
        </div>
    </div>
    <?php
}
