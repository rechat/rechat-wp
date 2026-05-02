<?php
if (! defined('ABSPATH')) {
    exit();
}

/**
 * Whether a major SEO plugin is already expected to output BreadcrumbList JSON-LD.
 *
 * @return bool
 */
function rch_seo_plugin_handles_breadcrumb_schema()
{
    if (apply_filters('rch_suppress_breadcrumb_schema', false)) {
        return true;
    }

    // Yoast SEO — breadcrumbs enabled in settings.
    if (class_exists('WPSEO_Options')) {
        $enabled = WPSEO_Options::get('breadcrumbs-enable', false);
        if ($enabled === true || $enabled === 'on' || $enabled === '1' || $enabled === 'enabled') {
            return true;
        }
    }

    // Rank Math — breadcrumbs module / setting.
    if (class_exists('\RankMath\Helper') && method_exists('\RankMath\Helper', 'get_settings')) {
        $bc = \RankMath\Helper::get_settings('breadcrumbs');
        if ($bc === true || $bc === 'on' || $bc === '1') {
            return true;
        }
        if (is_array($bc)) {
            foreach (['enable', 'breadcrumbs_enable', 'show_home'] as $k) {
                if (isset($bc[$k])) {
                    $v = $bc[$k];
                    if ($v === true || $v === 'on' || $v === '1') {
                        return true;
                    }
                }
            }
        }
    }

    // SEOPress — breadcrumbs toggle.
    if (function_exists('seopress_get_toggle_option')) {
        $t = seopress_get_toggle_option('toggle-breadcrumbs');
        if ($t === '1' || $t === true || $t === 'on') {
            return true;
        }
    }

    // All in One SEO — breadcrumbs enabled.
    if (defined('AIOSEO_VERSION') && function_exists('aioseo')) {
        try {
            $opts = aioseo()->options;
            if ($opts && isset($opts->breadcrumbs->enable) && ! empty($opts->breadcrumbs->enable)) {
                return true;
            }
        } catch (\Throwable $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        }
    }

    return (bool) apply_filters('rch_seo_plugin_handles_breadcrumb_schema', false);
}

/**
 * Resolve primary category term ID for a post when Yoast / Rank Math set it.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy slug.
 * @return int Term ID or 0.
 */
function rch_breadcrumb_primary_term_id($post_id, $taxonomy)
{
    if (class_exists('WPSEO_Primary_Term')) {
        $primary = new WPSEO_Primary_Term($taxonomy, $post_id);
        $tid     = (int) $primary->get_primary_term();
        if ($tid > 0) {
            return $tid;
        }
    }
    $rm = (int) get_post_meta($post_id, 'rank_math_primary_' . $taxonomy, true);
    if ($rm > 0) {
        return $rm;
    }

    return 0;
}

/**
 * Build flat breadcrumb trail: each item has name + url (absolute).
 *
 * @return array<int,array{name:string,url:string}>
 */
function rch_breadcrumb_collect_trail()
{
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return [];
    }

    // Single-item trails are not useful; front page has no trail beyond "Home".
    if (is_front_page()) {
        return [];
    }

    $trail = [];

    $home_label = get_bloginfo('name');
    $home_label = is_string($home_label) && $home_label !== '' ? wp_strip_all_tags($home_label) : __('Home', 'rechat-plugin');
    $trail[]    = [
        'name' => $home_label,
        'url'  => esc_url_raw(home_url('/')),
    ];

    // Blog index when a static page is set as the front.
    if (is_home() && ! is_front_page()) {
        $posts_page_id = (int) get_option('page_for_posts');
        if ($posts_page_id > 0) {
            $trail[] = [
                'name' => wp_strip_all_tags(get_the_title($posts_page_id)),
                'url'  => esc_url_raw(get_permalink($posts_page_id)),
            ];
        }

        return $trail;
    }

    if (is_singular()) {
        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return [];
        }

        $pto = get_post_type_object($post->post_type);
        if ($pto && ! empty($pto->has_archive) && ! in_array($post->post_type, ['post', 'page'], true)) {
            $archive = get_post_type_archive_link($post->post_type);
            if (is_string($archive) && $archive !== '') {
                $trail[] = [
                    'name' => wp_strip_all_tags($pto->labels->name),
                    'url'  => esc_url_raw($archive),
                ];
            }
        }

        if ($post->post_type === 'post') {
            $primary_id = rch_breadcrumb_primary_term_id((int) $post->ID, 'category');
            $terms      = [];
            if ($primary_id > 0) {
                $t = get_term($primary_id, 'category');
                if ($t instanceof WP_Term && ! is_wp_error($t)) {
                    $terms[] = $t;
                }
            }
            if (empty($terms)) {
                $cats = get_the_category($post->ID);
                if (! empty($cats[0]) && $cats[0] instanceof WP_Term) {
                    $terms[] = $cats[0];
                }
            }
            if (! empty($terms[0])) {
                $term = $terms[0];
                $anc  = array_reverse(get_ancestors((int) $term->term_id, 'category'));
                foreach ($anc as $tid) {
                    $t = get_term((int) $tid, 'category');
                    if ($t instanceof WP_Term && ! is_wp_error($t)) {
                        $link = get_term_link($t);
                        if (! is_wp_error($link)) {
                            $trail[] = [
                                'name' => wp_strip_all_tags($t->name),
                                'url'  => esc_url_raw($link),
                            ];
                        }
                    }
                }
                $link = get_term_link($term);
                if (! is_wp_error($link)) {
                    $trail[] = [
                        'name' => wp_strip_all_tags($term->name),
                        'url'  => esc_url_raw($link),
                    ];
                }
            }
        }

        if (is_post_type_hierarchical($post->post_type)) {
            $ancestors = array_reverse(get_post_ancestors($post, $post->post_type));
            foreach ($ancestors as $aid) {
                $aid = (int) $aid;
                if ($aid < 1) {
                    continue;
                }
                $trail[] = [
                    'name' => wp_strip_all_tags(get_the_title($aid)),
                    'url'  => esc_url_raw(get_permalink($aid)),
                ];
            }
        }

        $trail[] = [
            'name' => wp_strip_all_tags(get_the_title($post)),
            'url'  => esc_url_raw(get_permalink($post)),
        ];

        return $trail;
    }

    if (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        if (! $term instanceof WP_Term) {
            return [];
        }
        $anc = array_reverse(get_ancestors((int) $term->term_id, $term->taxonomy));
        foreach ($anc as $tid) {
            $t = get_term((int) $tid, $term->taxonomy);
            if ($t instanceof WP_Term && ! is_wp_error($t)) {
                $link = get_term_link($t);
                if (! is_wp_error($link)) {
                    $trail[] = [
                        'name' => wp_strip_all_tags($t->name),
                        'url'  => esc_url_raw($link),
                    ];
                }
            }
        }
        $link = get_term_link($term);
        if (! is_wp_error($link)) {
            $trail[] = [
                'name' => wp_strip_all_tags($term->name),
                'url'  => esc_url_raw($link),
            ];
        }

        return $trail;
    }

    if (is_post_type_archive()) {
        $pto = get_queried_object();
        if ($pto instanceof WP_Post_Type) {
            $link = get_post_type_archive_link($pto->name);
            if (is_string($link) && $link !== '') {
                $trail[] = [
                    'name' => wp_strip_all_tags($pto->label),
                    'url'  => esc_url_raw($link),
                ];
            }
        }

        return $trail;
    }

    if (is_author()) {
        $author = get_queried_object();
        if ($author instanceof WP_User) {
            $trail[] = [
                'name' => wp_strip_all_tags($author->display_name),
                'url'  => esc_url_raw(get_author_posts_url((int) $author->ID)),
            ];
        }

        return $trail;
    }

    if (is_search()) {
        $trail[] = [
            'name' => sprintf(
                /* translators: %s: search query */
                __('Search results for "%s"', 'rechat-plugin'),
                wp_strip_all_tags(get_search_query())
            ),
            'url' => esc_url_raw(get_search_link()),
        ];

        return $trail;
    }

    if (is_date()) {
        $y = (int) get_query_var('year');
        $m = (int) get_query_var('monthnum');
        $d = (int) get_query_var('day');

        if ($y > 0) {
            $trail[] = [
                'name' => (string) $y,
                'url'  => esc_url_raw(get_year_link($y)),
            ];
        }
        if ($m > 0 && $y > 0) {
            $month_ts = mktime(0, 0, 0, $m, 1, $y);
            $trail[]  = [
                'name' => date_i18n(_x('F Y', 'breadcrumb month archive format', 'rechat-plugin'), $month_ts),
                'url'  => esc_url_raw(get_month_link($y, $m)),
            ];
        }
        if (is_day() && $d > 0 && $m > 0 && $y > 0) {
            $day_ts  = mktime(0, 0, 0, $m, $d, $y);
            $trail[] = [
                'name' => date_i18n(_x('F j, Y', 'breadcrumb day archive format', 'rechat-plugin'), $day_ts),
                'url'  => esc_url_raw(get_day_link($y, $m, $d)),
            ];
        }

        return $trail;
    }

    return [];
}

/**
 * Build BreadcrumbList JSON-LD from the current trail.
 *
 * @param array<int,array{name:string,url:string}> $trail Breadcrumb steps.
 * @return array<string,mixed>
 */
function rch_build_breadcrumb_list_schema(array $trail)
{
    if (count($trail) < 2) {
        return [];
    }

    $elements = [];
    $pos      = 1;
    foreach ($trail as $step) {
        if (empty($step['name']) || empty($step['url'])) {
            continue;
        }
        $elements[] = [
            '@type'    => 'ListItem',
            'position' => $pos,
            'name'     => $step['name'],
            'item'     => $step['url'],
        ];
        $pos++;
    }

    if (count($elements) < 2) {
        return [];
    }

    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $elements,
    ];
}

/**
 * Output BreadcrumbList JSON-LD when no major SEO plugin is handling it.
 *
 * @return void
 */
function rch_output_breadcrumb_list_jsonld()
{
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return;
    }
    if (rch_seo_plugin_handles_breadcrumb_schema()) {
        return;
    }

    $trail  = rch_breadcrumb_collect_trail();
    $schema = rch_build_breadcrumb_list_schema($trail);

    /**
     * Filter the BreadcrumbList JSON-LD before output.
     *
     * @param array $schema BreadcrumbList node (empty array to skip).
     * @param array $trail  Raw trail used to build the schema.
     */
    $schema = apply_filters('rch_breadcrumb_list_schema', $schema, $trail);

    if (empty($schema) || ! is_array($schema)) {
        return;
    }

    $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (! is_string($json) || $json === '') {
        return;
    }

    echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
}
add_action('wp_head', 'rch_output_breadcrumb_list_jsonld', 25);
