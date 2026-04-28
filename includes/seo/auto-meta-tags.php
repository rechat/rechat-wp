<?php
if (! defined('ABSPATH')) {
    exit();
}

/**
 * Whether a major SEO plugin is active (Yoast, Rank Math, SEOPress, AIOSEO, The SEO Framework, etc.).
 *
 * @return bool
 */
function rch_major_seo_plugin_active()
{
    if (apply_filters('rch_force_disable_auto_meta', false)) {
        return true;
    }

    $constants = [
        'WPSEO_VERSION',
        'RANK_MATH_VERSION',
        'SEOPRESS_VERSION',
        'AIOSEO_VERSION',
        'THE_SEO_FRAMEWORK_VERSION',
        'SLIM_SEO_VERSION',
        'SQSEO_VERSION',
    ];
    foreach ($constants as $const) {
        if (defined($const)) {
            return true;
        }
    }

    if (! function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins = [
        'wordpress-seo/wp-seo.php',
        'wordpress-seo-premium/wp-seo-premium.php',
        'seo-by-rank-math/rank-math.php',
        'seo-by-rank-math-pro/rank-math-pro.php',
        'all-in-one-seo-pack/all_in_one_seo_pack.php',
        'all-in-one-seo-pack-pro/all_in_one_seo_pack.php',
        'seopress/seopress.php',
        'seopress-pro/seopress-pro.php',
        'autodescription/autodescription.php',
        'wp-meta-seo/wp-meta-seo.php',
        'squirrly-seo/squirrly.php',
    ];
    foreach ($plugins as $rel) {
        if (is_plugin_active($rel)) {
            return true;
        }
    }

    return (bool) apply_filters('rch_major_seo_plugin_active', false);
}

/**
 * Trim and normalize text for meta description (recommended ~155–160 chars).
 *
 * @param string $text Raw text.
 * @param int    $max  Max length.
 * @return string
 */
function rch_trim_meta_description($text, $max = 160)
{
    $text = wp_strip_all_tags((string) $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($text) > $max) {
        return mb_substr($text, 0, max(0, $max - 3)) . '...';
    }
    if (strlen($text) > $max) {
        return substr($text, 0, max(0, $max - 3)) . '...';
    }

    return $text;
}

/**
 * Auto-generated meta description for the current main query.
 *
 * @return string
 */
function rch_auto_generate_meta_description()
{
    if (get_query_var('listing_detail')) {
        $fallback = get_bloginfo('description', 'display');

        return rch_trim_meta_description(is_string($fallback) ? $fallback : '');
    }

    if (is_front_page() && ! is_home()) {
        $d = get_bloginfo('description', 'display');

        return rch_trim_meta_description(is_string($d) ? $d : '');
    }

    if (is_home() && is_front_page()) {
        $d = get_bloginfo('description', 'display');

        return rch_trim_meta_description(is_string($d) ? $d : '');
    }

    if (is_home() && ! is_front_page()) {
        $posts_page = (int) get_option('page_for_posts');
        if ($posts_page > 0) {
            $post = get_post($posts_page);
            if ($post instanceof WP_Post) {
                if (is_string($post->post_excerpt) && trim($post->post_excerpt) !== '') {
                    return rch_trim_meta_description($post->post_excerpt);
                }

                return rch_trim_meta_description($post->post_content);
            }
        }

        $d = get_bloginfo('description', 'display');

        return rch_trim_meta_description(is_string($d) ? $d : '');
    }

    if (is_singular()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            if (is_string($post->post_excerpt) && trim($post->post_excerpt) !== '') {
                return rch_trim_meta_description($post->post_excerpt);
            }

            return rch_trim_meta_description($post->post_content);
        }
    }

    if (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term && is_string($term->description) && trim($term->description) !== '') {
            return rch_trim_meta_description($term->description);
        }
        if ($term instanceof WP_Term) {
            return rch_trim_meta_description(
                sprintf(
                    /* translators: %s: taxonomy term name */
                    __('Content filed under %s.', 'rechat-plugin'),
                    $term->name
                )
            );
        }
    }

    if (is_author()) {
        $author = get_queried_object();
        if ($author instanceof WP_User) {
            $bio = get_the_author_meta('description', $author->ID);

            return rch_trim_meta_description(
                is_string($bio) && trim($bio) !== ''
                    ? $bio
                    : sprintf(
                        /* translators: %s: author display name */
                        __('Articles and content by %s.', 'rechat-plugin'),
                        $author->display_name
                    )
            );
        }
    }

    if (is_post_type_archive()) {
        $pto = get_queried_object();
        if ($pto instanceof WP_Post_Type) {
            $desc = is_string($pto->description) ? $pto->description : '';

            return rch_trim_meta_description(
                $desc !== ''
                    ? $desc
                    : sprintf(
                        /* translators: %s: post type plural label */
                        __('Archive of %s.', 'rechat-plugin'),
                        $pto->labels->name
                    )
            );
        }
    }

    if (is_search()) {
        return rch_trim_meta_description(
            sprintf(
                /* translators: %s: search query */
                __('Search results for "%s".', 'rechat-plugin'),
                get_search_query()
            )
        );
    }

    if (is_date()) {
        return rch_trim_meta_description(get_the_archive_title());
    }

    $d = get_bloginfo('description', 'display');

    return rch_trim_meta_description(is_string($d) ? $d : '');
}

/**
 * Canonical URL for the current view.
 *
 * @return string
 */
function rch_auto_meta_canonical_url()
{
    if (get_query_var('listing_detail')) {
        $path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';

        return is_string($path) && $path !== '' ? home_url(user_trailingslashit($path)) : home_url('/');
    }

    if (is_singular()) {
        $url = get_permalink();
        if (is_string($url)) {
            return $url;
        }
    }

    if (is_home() && ! is_front_page()) {
        $p = get_permalink((int) get_option('page_for_posts'));
        if (is_string($p)) {
            return $p;
        }
    }

    global $wp;
    if (isset($wp->request)) {
        return home_url(user_trailingslashit($wp->request));
    }

    return home_url('/');
}

/**
 * OG / Twitter image URL.
 *
 * @return string
 */
function rch_auto_meta_image_url()
{
    if (is_singular() && has_post_thumbnail()) {
        $u = get_the_post_thumbnail_url(get_queried_object_id(), 'large');
        if (is_string($u) && $u !== '') {
            return $u;
        }
    }

    $icon = get_site_icon_url(512);
    if (is_string($icon) && $icon !== '') {
        return $icon;
    }

    return '';
}

/**
 * OG type string.
 *
 * @return string
 */
function rch_auto_meta_og_type()
{
    if (is_singular('post')) {
        return 'article';
    }
    if (is_singular()) {
        return 'article';
    }

    return 'website';
}

/**
 * Print default meta description, Open Graph, and Twitter tags when no SEO plugin handles them.
 *
 * @return void
 */
function rch_output_auto_meta_tags()
{
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return;
    }
    if (rch_major_seo_plugin_active()) {
        return;
    }

    $description = rch_auto_generate_meta_description();
    $description = apply_filters('rch_auto_meta_description', $description);
    $description = is_string($description) ? $description : '';

    $canonical = rch_auto_meta_canonical_url();
    $canonical = apply_filters('rch_auto_meta_canonical_url', $canonical);
    $canonical = is_string($canonical) ? esc_url($canonical) : '';

    $og_title = wp_get_document_title();
    $og_title = apply_filters('rch_auto_meta_og_title', $og_title);
    $og_title = is_string($og_title) ? wp_strip_all_tags($og_title) : '';

    $og_image = rch_auto_meta_image_url();
    $og_image = apply_filters('rch_auto_meta_og_image', $og_image);
    $og_image = is_string($og_image) ? esc_url($og_image) : '';

    $og_type = rch_auto_meta_og_type();
    $og_type = apply_filters('rch_auto_meta_og_type', $og_type);

    if ($description !== '') {
        echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
    }

    if ($canonical !== '') {
        echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
    }

    $og_locale = str_replace('-', '_', get_locale());
    echo '<meta property="og:locale" content="' . esc_attr($og_locale) . '" />' . "\n";
    echo '<meta property="og:type" content="' . esc_attr($og_type) . '" />' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($og_title) . '" />' . "\n";
    if ($description !== '') {
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
    }
    if ($canonical !== '') {
        echo '<meta property="og:url" content="' . esc_url($canonical) . '" />' . "\n";
    }
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
    if ($og_image !== '') {
        echo '<meta property="og:image" content="' . esc_url($og_image) . '" />' . "\n";
    }

    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '" />' . "\n";
    if ($description !== '') {
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
    }
    if ($og_image !== '') {
        echo '<meta name="twitter:image" content="' . esc_url($og_image) . '" />' . "\n";
    }

    if (is_singular('post')) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            $pub = get_the_date('c', $post);
            $mod = get_the_modified_date('c', $post);
            if (is_string($pub) && $pub !== '') {
                echo '<meta property="article:published_time" content="' . esc_attr($pub) . '" />' . "\n";
            }
            if (is_string($mod) && $mod !== '') {
                echo '<meta property="article:modified_time" content="' . esc_attr($mod) . '" />' . "\n";
            }
        }
    }
}
add_action('wp_head', 'rch_output_auto_meta_tags', 2);

/**
 * Improve document title parts when no SEO plugin is present (archives, etc.).
 *
 * @param array<string,string> $title Document title parts.
 * @return array<string,string>
 */
function rch_auto_document_title_parts($title)
{
    if (is_admin() || ! is_array($title) || rch_major_seo_plugin_active()) {
        return $title;
    }

    if (get_query_var('listing_detail')) {
        $title['title'] = __('Property listing', 'rechat-plugin');
        $title['site']   = get_bloginfo('name', 'display');

        return apply_filters('rch_auto_document_title_parts', $title);
    }

    if (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $title['title'] = single_term_title('', false);
        }
    } elseif (is_post_type_archive()) {
        $pto = get_queried_object();
        if ($pto instanceof WP_Post_Type) {
            $title['title'] = $pto->labels->name;
        }
    } elseif (is_author()) {
        $author = get_queried_object();
        if ($author instanceof WP_User) {
            $title['title'] = sprintf(
                /* translators: %s: author display name */
                __('Author: %s', 'rechat-plugin'),
                $author->display_name
            );
        }
    } elseif (is_search()) {
        $title['title'] = sprintf(
            /* translators: %s: search query */
            __('Search: %s', 'rechat-plugin'),
            get_search_query()
        );
    } elseif (is_date()) {
        $title['title'] = get_the_archive_title();
    }

    return apply_filters('rch_auto_document_title_parts', $title);
}
add_filter('document_title_parts', 'rch_auto_document_title_parts', 20);
