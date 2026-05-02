<?php
if (! defined('ABSPATH')) {
    exit();
}

/**
 * Whether a major SEO plugin is already configured to emit Organization / LocalBusiness-style site schema.
 *
 * @return bool
 */
function rch_seo_plugin_handles_site_organization_schema()
{
    $suppressed = apply_filters('rch_suppress_brokerage_local_business_schema', false);
    if ($suppressed) {
        return true;
    }

    // Yoast SEO — Knowledge Graph / company.
    if (class_exists('WPSEO_Options')) {
        $company_or_person = WPSEO_Options::get('company_or_person');
        $company_name      = WPSEO_Options::get('company_name');
        if ($company_or_person === 'company' && is_string($company_name) && $company_name !== '') {
            return true;
        }
    }

    // Rank Math — Knowledge Graph name set.
    if (class_exists('\RankMath\Helper') && method_exists('\RankMath\Helper', 'get_settings')) {
        $kg_name = \RankMath\Helper::get_settings('knowledgegraph_name');
        if (is_string($kg_name) && trim($kg_name) !== '') {
            return true;
        }
    }

    // All in One SEO — site represents organization with a name.
    if (defined('AIOSEO_VERSION') && function_exists('aioseo')) {
        try {
            $opts = aioseo()->options;
            if ($opts && isset($opts->searchAppearance->global->schema)) {
                $schema   = $opts->searchAppearance->global->schema;
                $represents = $schema->siteRepresents ?? '';
                $org_name   = $schema->organizationName ?? '';
                if ($represents === 'organization' && is_string($org_name) && $org_name !== '') {
                    return true;
                }
            }
        } catch (\Throwable $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
            // Ignore if AIOSEO API shape differs.
        }
    }

    return (bool) apply_filters('rch_seo_plugin_handles_site_organization_schema', false);
}

/**
 * Build default brokerage schema for the site homepage (real estate brokerage).
 *
 * @return array<string,mixed>
 */
function rch_build_brokerage_local_business_schema()
{
    $name = get_bloginfo('name');
    if (! is_string($name) || $name === '') {
        return [];
    }

    $url = home_url('/');
    if (! is_string($url) || $url === '') {
        return [];
    }

    $description = get_bloginfo('description');
    $description   = is_string($description) ? wp_strip_all_tags($description) : '';

    $image = '';
    $logo_id = (int) get_theme_mod('custom_logo');
    if ($logo_id > 0) {
        $logo_url = wp_get_attachment_image_url($logo_id, 'full');
        if (is_string($logo_url) && filter_var($logo_url, FILTER_VALIDATE_URL)) {
            $image = esc_url_raw($logo_url);
        }
    }
    if ($image === '') {
        $icon = get_site_icon_url(512);
        if (is_string($icon) && filter_var($icon, FILTER_VALIDATE_URL)) {
            $image = esc_url_raw($icon);
        }
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'LocalBusiness',
        'name'     => wp_strip_all_tags($name),
        'url'      => esc_url_raw($url),
    ];

    if ($description !== '') {
        $schema['description'] = $description;
    }
    if ($image !== '') {
        $schema['image'] = $image;
    }

    return $schema;
}

/**
 * Output brokerage LocalBusiness-style JSON-LD on the front page only when SEO plugins do not already do it.
 *
 * @return void
 */
function rch_output_brokerage_local_business_jsonld()
{
    if (is_admin() || ! is_front_page()) {
        return;
    }
    if (rch_seo_plugin_handles_site_organization_schema()) {
        return;
    }

    $schema = rch_build_brokerage_local_business_schema();
    /**
     * Filter the brokerage / LocalBusiness JSON-LD before output (homepage only).
     *
     * @param array $schema Schema.org graph node.
     */
    $schema = apply_filters('rch_brokerage_local_business_schema', $schema);

    if (empty($schema) || ! is_array($schema)) {
        return;
    }

    $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (! is_string($json) || $json === '') {
        return;
    }

    echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
}
add_action('wp_head', 'rch_output_brokerage_local_business_jsonld', 20);
