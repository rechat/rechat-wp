<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Testimonials Gutenberg block.
 * Editor renders via ServerSideRender; front-end output is the
 * [rch_testimonials] shortcode (Rechat SDK <rechat-testimonials> web component).
 ******************************/

function rch_register_block_assets_testimonials()
{
    if (! wp_script_is('rch-gutenberg-js', 'registered')) {
        wp_register_script(
            'rch-gutenberg-js',
            RCH_PLUGIN_URL . 'build/index.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-api-fetch'),
            RCH_VERSION,
            true
        );
    }

    // Expose the preview endpoint + brand id to the editor so the testimonials
    // block can render a real live preview (see src/blocks/testimonials-block.js).
    // The preview loads from admin-ajax (same host) NOT a srcDoc iframe, because
    // the Rechat SDK resolves the portal by window.location.hostname — a srcDoc
    // iframe has no hostname and the SDK request fails ("Too small: expected
    // string to have >=1 characters").
    wp_localize_script('rch-gutenberg-js', 'rchTestimonialsPreview', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('rch_testimonials_preview'),
        'brandId' => (string) get_option('rch_rechat_brand_id', ''),
    ));

    register_block_type('rch-rechat-plugin/testimonials-block', array(
        'editor_script' => 'rch-gutenberg-js',
        'attributes'    => array(
            'limit'     => array('type' => 'number', 'default' => 0),
            'title'     => array('type' => 'string', 'default' => ''),
            'colorMode' => array('type' => 'string', 'default' => ''),
        ),
        'render_callback' => 'rch_render_testimonials_block',
    ));
}
add_action('init', 'rch_register_block_assets_testimonials');

/**
 * Render callback → emits the [rch_testimonials] shortcode.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function rch_render_testimonials_block($attributes)
{
    $limit      = isset($attributes['limit']) ? (int) $attributes['limit'] : 0;
    $title      = isset($attributes['title']) ? sanitize_text_field((string) $attributes['title']) : '';
    $color_mode = isset($attributes['colorMode']) ? strtolower((string) $attributes['colorMode']) : '';

    $shortcode = '[rch_testimonials';

    // Only pass limit when set (>0); otherwise the SDK shows all testimonials.
    if ($limit > 0) {
        $shortcode .= ' limit="' . esc_attr($limit) . '"';
    }
    if ($title !== '') {
        $shortcode .= ' title="' . esc_attr($title) . '"';
    }
    if ($color_mode === 'light' || $color_mode === 'dark') {
        $shortcode .= ' color_mode="' . esc_attr($color_mode) . '"';
    }

    $shortcode .= ']';

    return do_shortcode($shortcode);
}

/**
 * Admin-ajax: full-page HTML for the editor preview iframe.
 *
 * Rendered on the site host (so the Rechat SDK's hostname-based portal lookup
 * gets a real, non-empty hostname) with the <rechat-root> markup present at load
 * (so the SDK mounts it — it scans the DOM once on script load). Mirrors the
 * front-end [rch_testimonials] output.
 */
function rch_render_testimonials_preview_iframe()
{
    if (! current_user_can('edit_posts')) {
        status_header(403);
        exit;
    }
    // Nonce passed as ?nonce= (see the localized rchTestimonialsPreview.nonce).
    if (! isset($_GET['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'rch_testimonials_preview')) {
        status_header(403);
        exit;
    }

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;
    $color = isset($_GET['color_mode']) ? strtolower(sanitize_text_field(wp_unslash($_GET['color_mode']))) : '';

    if (function_exists('rch_register_rechat_sdk_assets')) {
        rch_register_rechat_sdk_assets();
    }
    $css = defined('RCH_RECHAT_SDK_CSS_URL') ? RCH_RECHAT_SDK_CSS_URL : '';
    $js  = defined('RCH_RECHAT_SDK_JS_URL') ? RCH_RECHAT_SDK_JS_URL : '';

    $brand = get_option('rch_rechat_brand_id');
    if (function_exists('rch_get_rechat_root_attributes')) {
        $root_attrs = rch_get_rechat_root_attributes(
            array('brand' => $brand, 'color_mode' => $color),
            '',
            ''
        );
    } else {
        $root_attrs = 'brand_id="' . esc_attr((string) $brand) . '"';
    }
    $limit_attr = $limit > 0 ? ' limit="' . (int) $limit . '"' : '';

    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');

    echo '<!doctype html><html><head><meta charset="utf-8">';
    if ($css !== '') {
        echo '<link rel="stylesheet" href="' . esc_url($css) . '">';
    }
    echo '<style>html,body{margin:0;padding:8px;background:transparent;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}</style>';
    echo '</head><body>';
    echo '<rechat-root ' . $root_attrs . '>';
    echo '<rechat-testimonials' . $limit_attr . '></rechat-testimonials>';
    echo '</rechat-root>';
    if ($js !== '') {
        echo '<script src="' . esc_url($js) . '"></script>';
    }
    echo '</body></html>';
    exit;
}
add_action('wp_ajax_rch_testimonials_preview', 'rch_render_testimonials_preview_iframe');
