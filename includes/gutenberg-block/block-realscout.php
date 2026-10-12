<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * RealScout Home Value Gutenberg block.
 *
 * Dynamic block (save → null): post content holds only style attributes, never IDs,
 * so a page broadcast to agent subsites resolves its own agent-encoded-id at render:
 * linked main-site agent's RealScout value → fallback main website ID
 * (see rch_realscout_resolve_encoded_id()).
 ******************************/

if (! defined('RCH_REALSCOUT_BLOCK_NAME')) {
    define('RCH_REALSCOUT_BLOCK_NAME', 'rch-rechat-plugin/realscout-home-value-block');
}

/**
 * Widget style defaults (RealScout embed snippet) → CSS custom properties.
 *
 * @return array<string, array{var: string, default: string}>
 */
function rch_realscout_block_style_map(): array
{
    return array(
        'backgroundColor'          => array('var' => '--rs-hvw-background-color',            'default' => '#ffffff'),
        'titleColor'               => array('var' => '--rs-hvw-title-color',                 'default' => '#000000'),
        'subtitleColor'            => array('var' => '--rs-hvw-subtitle-color',              'default' => 'rgba(28, 30, 38, 0.5)'),
        'inputTextColor'           => array('var' => '--rs-hvw-input-text-color',            'default' => 'inherit'),
        'primaryButtonTextColor'   => array('var' => '--rs-hvw-primary-button-text-color',   'default' => '#ffffff'),
        'primaryButtonColor'       => array('var' => '--rs-hvw-primary-button-color',        'default' => 'rgb(35, 93, 137)'),
        'secondaryButtonTextColor' => array('var' => '--rs-hvw-secondary-button-text-color', 'default' => 'rgb(35, 93, 137)'),
        'secondaryButtonColor'     => array('var' => '--rs-hvw-secondary-button-color',      'default' => '#ffffff'),
        'widgetWidth'              => array('var' => '--rs-hvw-widget-width',                'default' => 'auto'),
    );
}

function rch_register_block_assets_realscout()
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

    // RealScout web components (ES module, loaded once per page). null version → no ?ver= on third-party URL.
    wp_register_script('rch-realscout', RCH_REALSCOUT_SCRIPT_URL, array(), null, false);

    $attributes = array();
    foreach (rch_realscout_block_style_map() as $key => $style) {
        $attributes[$key] = array('type' => 'string', 'default' => $style['default']);
    }

    register_block_type(RCH_REALSCOUT_BLOCK_NAME, array(
        'editor_script'   => 'rch-gutenberg-js',
        'attributes'      => $attributes,
        'supports'        => array('align' => array('wide', 'full')),
        'render_callback' => 'rch_render_realscout_block',
    ));
}
add_action('init', 'rch_register_block_assets_realscout');

/**
 * RealScout script must load as type="module".
 */
function rch_realscout_script_module_tag($tag, $handle)
{
    if ($handle !== 'rch-realscout' || strpos($tag, 'type="module"') !== false) {
        return $tag;
    }

    return preg_replace('/<script\b(?![^>]*\btype=)/', '<script type="module"', $tag, 1);
}
add_filter('script_loader_tag', 'rch_realscout_script_module_tag', 10, 2);

/**
 * Enqueue in <head> when the current singular post contains the block.
 * The render callback also enqueues (late → footer) for blocks rendered elsewhere.
 */
function rch_realscout_enqueue_front_assets()
{
    if (is_singular() && has_block(RCH_REALSCOUT_BLOCK_NAME, get_queried_object_id())) {
        wp_enqueue_script('rch-realscout');
    }
}
add_action('wp_enqueue_scripts', 'rch_realscout_enqueue_front_assets');

/**
 * Editor canvas is an iframe that only receives enqueue_block_assets; load the RealScout
 * module there unconditionally so a freshly inserted (unsaved) block previews immediately.
 */
function rch_realscout_enqueue_editor_canvas_assets()
{
    if (is_admin()) {
        wp_enqueue_script('rch-realscout');
    }
}
add_action('enqueue_block_assets', 'rch_realscout_enqueue_editor_canvas_assets');

/**
 * Editor config: the ID resolved for this site drives the live preview (attribute only,
 * never shown or editable in the UI).
 */
function rch_realscout_enqueue_editor_assets()
{
    $has_main = rch_realscout_encode_id(rch_realscout_hub_main_site_id()) !== '';

    wp_add_inline_script(
        'rch-gutenberg-js',
        'window.rchRealScoutBlock = ' . wp_json_encode(array(
            'previewEncodedId' => rch_realscout_resolve_encoded_id(),
            'hasMainSiteId'    => $has_main,
            'canManage'        => current_user_can('manage_options'),
            'settingsUrl'      => is_multisite() ? get_admin_url((int) get_main_site_id(), 'admin.php?page=realscout') : admin_url('admin.php?page=realscout'),
        )) . ';',
        'before'
    );
}
add_action('enqueue_block_editor_assets', 'rch_realscout_enqueue_editor_assets');

/**
 * Allow only plain color/length tokens in CSS custom property values.
 */
function rch_realscout_sanitize_css_value(string $value): string
{
    $value = trim($value);

    return preg_match('/^[#a-zA-Z0-9(),.%\s-]{1,64}$/', $value) ? $value : '';
}

/**
 * Render callback → <realscout-home-value agent-encoded-id="…">.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function rch_render_realscout_block($attributes)
{
    $encoded_id = rch_realscout_resolve_encoded_id();

    // Nothing configured for this site: render nothing rather than a broken widget.
    if ($encoded_id === '') {
        return '';
    }

    wp_enqueue_script('rch-realscout');

    $declarations = array();
    foreach (rch_realscout_block_style_map() as $key => $style) {
        $value = isset($attributes[$key]) ? rch_realscout_sanitize_css_value((string) $attributes[$key]) : '';
        if ($value === '') {
            $value = $style['default'];
        }
        $declarations[] = $style['var'] . ': ' . $value;
    }

    $wrapper = get_block_wrapper_attributes(array('class' => 'rch-realscout-home-value'));

    return sprintf(
        '<div %1$s><realscout-home-value agent-encoded-id="%2$s" style="%3$s"></realscout-home-value></div>',
        $wrapper,
        esc_attr($encoded_id),
        esc_attr(implode('; ', $declarations) . ';')
    );
}
