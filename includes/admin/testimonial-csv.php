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
        'brand_id'    => __('Brand ID', 'rechat-plugin'),
        'domain'      => __('Site domain', 'rechat-plugin'),
        'url'         => __('Site URL', 'rechat-plugin'),
    ];
}

/**
 * Blog IDs to export from: every network site on multisite, else just the current site.
 *
 * @return array<int,int>
 */
function rch_testimonial_export_blog_ids(): array
{
    if (! is_multisite()) {
        return [(int) get_current_blog_id()];
    }
    $ids = get_sites(['number' => 0, 'fields' => 'ids', 'orderby' => 'id']);
    return array_map('intval', (array) $ids);
}

/**
 * Per-site context columns (brand id, domain, URL) for the current blog.
 *
 * @return array{brand_id:string,domain:string,url:string}
 */
function rch_testimonial_site_context(): array
{
    // On an agent subsite the brand_id column carries the owning agent's Rechat ID
    // (api_id) instead of the site brand; main site + office subsites keep brand_id.
    $brand_id = (string) get_option('rch_rechat_brand_id', '');
    $agent_id = rch_testimonial_agent_rechat_id_for_current_blog();
    if ($agent_id !== '') {
        $brand_id = $agent_id;
    }

    return [
        'brand_id' => $brand_id,
        'domain'   => (string) wp_parse_url(home_url('/'), PHP_URL_HOST),
        'url'      => (string) home_url('/'),
    ];
}

/**
 * Owning agent's Rechat ID (api_id) for the current blog, or '' when this is not
 * an agent subsite. Cache-free (safe inside a switch_to_blog export loop; the
 * shared scope helpers are statically cached and would return a stale value).
 */
function rch_testimonial_agent_rechat_id_for_current_blog(): string
{
    if (! is_multisite()) {
        return '';
    }
    $blog_id = (int) get_current_blog_id();
    $main_id = (int) get_main_site_id();
    if ($blog_id <= 0 || $blog_id === $main_id) {
        return '';
    }

    // The hub `agents` post (on the main site) is linked to this subsite via _rch_agent_site_id.
    switch_to_blog($main_id);
    $ids = get_posts([
        'post_type'              => 'agents',
        'post_status'            => 'any',
        'numberposts'            => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_key'               => '_rch_agent_site_id',
        'meta_value'             => (string) $blog_id,
        'suppress_filters'       => true,
    ]);
    $api_id = ! empty($ids) ? (string) get_post_meta((int) $ids[0], 'api_id', true) : '';
    restore_current_blog();

    return trim($api_id);
}

/**
 * Clean a testimonial name: drop a leading dash (hyphen / en / em dash) so
 * "– Caroline Wood" becomes "Caroline Wood".
 */
function rch_testimonial_clean_name($title): string
{
    $t = preg_replace('/^\s*[-\x{2012}\x{2013}\x{2014}\x{2015}]\s*/u', '', (string) $title);
    return trim((string) $t);
}

/**
 * Flatten testimonial content to plain text (strip HTML, decode entities,
 * collapse whitespace).
 */
function rch_testimonial_plain_text($html): string
{
    $text = wp_strip_all_tags((string) $html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string) $text);
}

/**
 * Build one CSV row for a testimonial post + site context.
 *
 * @param WP_Post                                          $post
 * @param array{brand_id:string,domain:string,url:string} $ctx
 * @return array<int,string|int>
 */
function rch_testimonial_export_row(WP_Post $post, array $ctx): array
{
    $stars = function_exists('rch_get_testimonial_stars') ? rch_get_testimonial_stars($post->ID) : (string) get_post_meta($post->ID, 'testimonial_stars', true);
    $link  = function_exists('rch_get_testimonial_link') ? rch_get_testimonial_link($post->ID) : (string) get_post_meta($post->ID, 'testimonial_link', true);

    return [
        $post->ID,
        rch_testimonial_clean_name($post->post_title),
        rch_testimonial_plain_text($post->post_content),
        $stars,
        $link,
        $post->post_status,
        $post->post_date,
        $ctx['brand_id'],
        $ctx['domain'],
        $ctx['url'],
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

    if (! is_multisite() && ! post_type_exists('testimonial')) {
        wp_die(esc_html__('The testimonial post type is not registered on this site.', 'rechat-plugin'));
    }

    $columns  = rch_testimonial_csv_columns();
    $filename = 'testimonials-export-' . gmdate('Y-m-d') . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel renders accented characters.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_keys($columns));

    // Export from every network site (multisite) or just the current site.
    foreach (rch_testimonial_export_blog_ids() as $blog_id) {
        $switched = false;
        if (is_multisite() && $blog_id !== (int) get_current_blog_id()) {
            switch_to_blog($blog_id);
            $switched = true;
        }

        if (post_type_exists('testimonial')) {
            $ctx   = rch_testimonial_site_context();
            $posts = get_posts([
                'post_type'        => 'testimonial',
                'post_status'      => ['publish', 'draft', 'pending', 'private'],
                'numberposts'      => -1,
                'orderby'          => 'date',
                'order'            => 'DESC',
                'suppress_filters' => true,
            ]);
            foreach ($posts as $post) {
                if ($post instanceof WP_Post) {
                    fputcsv($out, rch_testimonial_export_row($post, $ctx));
                }
            }
        }

        if ($switched) {
            restore_current_blog();
        }
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
            if (is_multisite()) {
                esc_html_e('Export testimonials from every site in the network (name, text, stars, link, plus each site\'s brand ID, domain, and URL), or import a CSV back into this site.', 'rechat-plugin');
            } else {
                printf(
                    /* translators: %d: number of testimonial posts */
                    esc_html__('Export all %d testimonial posts (name, text, stars, link, brand ID, domain, URL), or import a CSV of the same columns.', 'rechat-plugin'),
                    (int) $total
                );
            }
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
