<?php
/**
 * General tab: Rechat brand color.
 *
 * A single color the admin can pick. When set, it is emitted as the `brand-color` attribute on
 * every `<rechat-root>` the plugin renders (listing shortcode/block + the agent single listings
 * section), so the Rechat SDK themes its components from one color. Empty = no attribute (SDK default).
 *
 * Value is hex, 8-digit (#rrggbbaa) supported so alpha can be picked.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Settings group + option name for the Rechat brand color.
 */
const RCH_BRAND_COLOR_GROUP  = 'rch_brand_color_group';
const RCH_BRAND_COLOR_OPTION = 'rch_rechat_brand_color';

/**
 * Option name for the site-wide Rechat color mode (light | dark). Saved in the same form/group
 * as the brand color, so one "Save" persists both.
 */
const RCH_COLOR_MODE_OPTION = 'rch_rechat_color_mode';

/**
 * Sanitize the color mode to 'light' or 'dark' (default 'light').
 */
function rch_sanitize_rechat_color_mode($value): string
{
    return (is_string($value) && strtolower(trim($value)) === 'dark') ? 'dark' : 'light';
}

/**
 * The saved site-wide Rechat color mode ('light' | 'dark').
 */
function rch_get_rechat_color_mode(): string
{
    return rch_sanitize_rechat_color_mode(get_option(RCH_COLOR_MODE_OPTION, 'light'));
}

/**
 * Option name for the site-wide Rechat UI theme (default | compact). Saved in the same form/group.
 */
const RCH_UI_THEME_OPTION = 'rch_rechat_ui_theme';

/**
 * Option name for the site-wide Rechat corner radius (default | sharp). Saved in the same form/group.
 */
const RCH_UI_RADII_OPTION = 'rch_rechat_ui_radii';

/**
 * Sanitize the UI theme to 'default' or 'compact'.
 */
function rch_sanitize_rechat_ui_theme($value): string
{
    return (is_string($value) && strtolower(trim($value)) === 'compact') ? 'compact' : 'default';
}

/**
 * Sanitize the corner radius to 'default' or 'sharp'.
 */
function rch_sanitize_rechat_ui_radii($value): string
{
    return (is_string($value) && strtolower(trim($value)) === 'sharp') ? 'sharp' : 'default';
}

/**
 * The saved site-wide Rechat UI theme ('default' | 'compact').
 */
function rch_get_rechat_ui_theme(): string
{
    return rch_sanitize_rechat_ui_theme(get_option(RCH_UI_THEME_OPTION, 'default'));
}

/**
 * The saved site-wide Rechat corner radius ('default' | 'sharp').
 */
function rch_get_rechat_ui_radii(): string
{
    return rch_sanitize_rechat_ui_radii(get_option(RCH_UI_RADII_OPTION, 'default'));
}

/**
 * Option name for the site-wide default map style/preset (default | liberty | bright | positron | dark).
 */
const RCH_MAP_STYLE_OPTION = 'rch_rechat_map_style';

/**
 * Allowed map-style presets (mirrors rch_get_rechat_map_preset_allowlist()).
 *
 * @return string[]
 */
function rch_rechat_map_style_choices(): array
{
    return function_exists('rch_get_rechat_map_preset_allowlist')
        ? rch_get_rechat_map_preset_allowlist()
        : ['liberty', 'bright', 'positron', 'dark'];
}

/**
 * Sanitize the map style to a known preset, or 'default' (no site-wide override).
 */
function rch_sanitize_rechat_map_style($value): string
{
    $value = is_string($value) ? strtolower(trim($value)) : '';

    return in_array($value, rch_rechat_map_style_choices(), true) ? $value : 'default';
}

/**
 * The saved site-wide map-style preset, or '' when set to 'default' (no override).
 */
function rch_get_rechat_map_style(): string
{
    $value = rch_sanitize_rechat_map_style(get_option(RCH_MAP_STYLE_OPTION, 'default'));

    return $value === 'default' ? '' : $value;
}

/**
 * Normalize a color string to a lowercase 6-digit RGB hex (#rrggbb), or '' when invalid/empty.
 *
 * Brand color is RGB only — no alpha. Accepts #rgb / #rrggbb / #rrggbbaa (with or without leading
 * #); 3-digit is expanded, any alpha byte is dropped.
 */
function rch_rechat_brand_color_normalize($value): string
{
    if (! is_string($value)) {
        return '';
    }

    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    if ($value[0] !== '#') {
        $value = '#' . $value;
    }

    if (preg_match('/^#[0-9a-f]{3}$/', $value)) {
        $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
    }

    // Drop alpha from 8-digit input → keep RGB only.
    if (preg_match('/^#[0-9a-f]{8}$/', $value)) {
        $value = substr($value, 0, 7);
    }

    if (preg_match('/^#[0-9a-f]{6}$/', $value)) {
        return $value;
    }

    return '';
}

/**
 * The saved Rechat brand color (normalized), or '' when unset.
 */
function rch_get_rechat_brand_color(): string
{
    return rch_rechat_brand_color_normalize(get_option(RCH_BRAND_COLOR_OPTION, ''));
}

/**
 * Register the brand-color option + sanitize callback.
 */
function rch_register_brand_color_setting(): void
{
    register_setting(
        RCH_BRAND_COLOR_GROUP,
        RCH_BRAND_COLOR_OPTION,
        [
            'type'              => 'string',
            'sanitize_callback' => 'rch_rechat_brand_color_normalize',
            'default'           => '',
        ]
    );

    register_setting(
        RCH_BRAND_COLOR_GROUP,
        RCH_COLOR_MODE_OPTION,
        [
            'type'              => 'string',
            'sanitize_callback' => 'rch_sanitize_rechat_color_mode',
            'default'           => 'light',
        ]
    );

    register_setting(
        RCH_BRAND_COLOR_GROUP,
        RCH_UI_THEME_OPTION,
        [
            'type'              => 'string',
            'sanitize_callback' => 'rch_sanitize_rechat_ui_theme',
            'default'           => 'default',
        ]
    );

    register_setting(
        RCH_BRAND_COLOR_GROUP,
        RCH_UI_RADII_OPTION,
        [
            'type'              => 'string',
            'sanitize_callback' => 'rch_sanitize_rechat_ui_radii',
            'default'           => 'default',
        ]
    );

    register_setting(
        RCH_BRAND_COLOR_GROUP,
        RCH_MAP_STYLE_OPTION,
        [
            'type'              => 'string',
            'sanitize_callback' => 'rch_sanitize_rechat_map_style',
            'default'           => 'default',
        ]
    );
}
add_action('admin_init', 'rch_register_brand_color_setting');

/**
 * Enqueue the color-picker control assets on the General Settings tab only.
 *
 * @param string $hook Admin page hook.
 */
function rch_enqueue_brand_color_assets(string $hook): void
{
    if ($hook !== 'toplevel_page_rechat-setting' || ! defined('RCH_PLUGIN_URL') || ! defined('RCH_VERSION')) {
        return;
    }

    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'sync-data';
    if ($tab !== 'general-settings') {
        return;
    }

    wp_enqueue_style(
        'rch-theme-colors',
        RCH_PLUGIN_URL . 'assets/css/rch-theme-colors.css',
        [],
        RCH_VERSION
    );
    wp_enqueue_script(
        'rch-theme-colors',
        RCH_PLUGIN_URL . 'assets/js/rch-theme-colors.js',
        [],
        RCH_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'rch_enqueue_brand_color_assets', 20);

/**
 * Render the "Rechat brand color" card (called from the General Settings tab).
 *
 * Function name kept as rch_render_theme_colors_card() so the General tab include stays unchanged.
 */
function rch_render_theme_colors_card(): void
{
    $value = rch_get_rechat_brand_color(); // '' when unset (6-digit RGB when set).
    $rgb   = $value !== '' ? $value : '#000000';
    ?>
    <div class="rch-card">
        <div class="rch-card__head">
            <span class="dashicons dashicons-art" aria-hidden="true"></span>
            <h3><?php esc_html_e('Rechat brand color', 'rechat-plugin'); ?></h3>
        </div>
        <div class="rch-card__body">
            <p class="description" style="margin-top:0;">
                <?php esc_html_e('Pick one brand color. When set, it is applied to the Rechat SDK components as brand-color on every rechat-root the plugin renders (listings + agent listings). Leave it empty (Reset) to use the SDK default. Transparency is supported (8-digit hex).', 'rechat-plugin'); ?>
            </p>
            <form method="POST" action="options.php" class="rch-tc-form">
                <?php settings_fields(RCH_BRAND_COLOR_GROUP); ?>

                <?php
                $color_mode    = rch_get_rechat_color_mode();
                $ui_theme      = rch_get_rechat_ui_theme();
                $ui_radii      = rch_get_rechat_ui_radii();
                $map_style_val = rch_sanitize_rechat_map_style(get_option(RCH_MAP_STYLE_OPTION, 'default'));
                $map_style_choices = [
                    'default'  => __('Default (SDK)', 'rechat-plugin'),
                    'liberty'  => __('Liberty', 'rechat-plugin'),
                    'bright'   => __('Bright', 'rechat-plugin'),
                    'positron' => __('Positron', 'rechat-plugin'),
                    'dark'     => __('Dark', 'rechat-plugin'),
                ];
                ?>
                <div class="rch-tc-selects">
                    <p class="rch-tc-mode">
                        <label for="rch-tc-color-mode" style="font-weight:600;display:block;margin-bottom:4px;">
                            <?php esc_html_e('Color mode', 'rechat-plugin'); ?>
                        </label>
                        <select id="rch-tc-color-mode" name="<?php echo esc_attr(RCH_COLOR_MODE_OPTION); ?>">
                            <option value="light" <?php selected($color_mode, 'light'); ?>><?php esc_html_e('Light', 'rechat-plugin'); ?></option>
                            <option value="dark" <?php selected($color_mode, 'dark'); ?>><?php esc_html_e('Dark', 'rechat-plugin'); ?></option>
                        </select>
                        <span class="description" style="display:block;margin-top:4px;">
                            <?php esc_html_e('Adds color-mode to every rechat-root the plugin renders. Default is light.', 'rechat-plugin'); ?>
                        </span>
                    </p>

                    <p class="rch-tc-mode">
                        <label for="rch-tc-ui-theme" style="font-weight:600;display:block;margin-bottom:4px;">
                            <?php esc_html_e('Theme', 'rechat-plugin'); ?>
                        </label>
                        <select id="rch-tc-ui-theme" name="<?php echo esc_attr(RCH_UI_THEME_OPTION); ?>">
                            <option value="default" <?php selected($ui_theme, 'default'); ?>><?php esc_html_e('Rechat (default)', 'rechat-plugin'); ?></option>
                            <option value="compact" <?php selected($ui_theme, 'compact'); ?>><?php esc_html_e('Compact', 'rechat-plugin'); ?></option>
                        </select>
                        <span class="description" style="display:block;margin-top:4px;">
                            <?php esc_html_e('Compact adds data-theme="compact" to rechat-root. Default adds nothing.', 'rechat-plugin'); ?>
                        </span>
                    </p>

                    <p class="rch-tc-mode">
                        <label for="rch-tc-ui-radii" style="font-weight:600;display:block;margin-bottom:4px;">
                            <?php esc_html_e('Corner radius', 'rechat-plugin'); ?>
                        </label>
                        <select id="rch-tc-ui-radii" name="<?php echo esc_attr(RCH_UI_RADII_OPTION); ?>">
                            <option value="default" <?php selected($ui_radii, 'default'); ?>><?php esc_html_e('Default', 'rechat-plugin'); ?></option>
                            <option value="sharp" <?php selected($ui_radii, 'sharp'); ?>><?php esc_html_e('Sharp', 'rechat-plugin'); ?></option>
                        </select>
                        <span class="description" style="display:block;margin-top:4px;">
                            <?php esc_html_e('Sharp adds data-radii="sharp" to rechat-root. Default adds nothing.', 'rechat-plugin'); ?>
                        </span>
                    </p>

                    <p class="rch-tc-mode">
                        <label for="rch-tc-map-style" style="font-weight:600;display:block;margin-bottom:4px;">
                            <?php esc_html_e('Map style', 'rechat-plugin'); ?>
                        </label>
                        <select id="rch-tc-map-style" name="<?php echo esc_attr(RCH_MAP_STYLE_OPTION); ?>">
                            <?php foreach ($map_style_choices as $ms_val => $ms_label) : ?>
                                <option value="<?php echo esc_attr($ms_val); ?>" <?php selected($map_style_val, $ms_val); ?>><?php echo esc_html($ms_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="description" style="display:block;margin-top:4px;">
                            <?php esc_html_e('Default map preset for every listings map (rechat-map). Each listing block can override it. Default = SDK default.', 'rechat-plugin'); ?>
                        </span>
                    </p>
                </div>

                <div class="rch-tc-grid">
                    <div class="rch-tc-row" data-key="brand_color" data-default="">
                        <div class="rch-tc-row__label">
                            <span class="rch-tc-label"><?php esc_html_e('Brand color', 'rechat-plugin'); ?></span>
                            <code class="rch-tc-var">brand-color</code>
                        </div>
                        <div class="rch-tc-row__controls">
                            <span class="rch-tc-swatch" aria-hidden="true"><span class="rch-tc-swatch__fill" style="background: <?php echo esc_attr($value !== '' ? $value : 'transparent'); ?>;"></span></span>
                            <input type="color" class="rch-tc-color" value="<?php echo esc_attr($rgb); ?>" aria-label="<?php esc_attr_e('Brand color', 'rechat-plugin'); ?>" />
                            <input type="text" class="rch-tc-hex" id="rch-tc-brand-color" name="<?php echo esc_attr(RCH_BRAND_COLOR_OPTION); ?>" value="<?php echo esc_attr($value); ?>" maxlength="7" spellcheck="false" autocomplete="off" placeholder="#rrggbb" />
                            <button type="button" class="button-link rch-tc-reset"><?php esc_html_e('Reset', 'rechat-plugin'); ?></button>
                        </div>
                    </div>
                </div>

                <?php submit_button(__('Save brand color', 'rechat-plugin')); ?>
            </form>
        </div>
    </div>
    <?php
}
