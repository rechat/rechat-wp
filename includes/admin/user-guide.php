<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * User Guide tab: Rechat → User Guide.
 *
 * Content is built from docs/user-guide.md by `npm run build:guide`:
 *   docs/user-guide-body.html  shown inside this tab
 *   docs/user-guide.html       standalone copy users download or print to PDF
 */

/*******************************
 * Enqueue guide styles on the User Guide tab only
 ******************************/
function rch_user_guide_enqueue_styles($hook)
{
    if (!defined('RCH_PLUGIN_URL') || !defined('RCH_VERSION')) {
        return;
    }

    if ($hook !== 'toplevel_page_rechat-setting') {
        return;
    }

    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
    if ($tab !== 'user-guide') {
        return;
    }

    wp_enqueue_style(
        'rch-user-guide',
        RCH_PLUGIN_URL . 'assets/css/rch-user-guide.css',
        [],
        RCH_VERSION
    );
}
add_action('admin_enqueue_scripts', 'rch_user_guide_enqueue_styles');

/*******************************
 * Render User Guide tab
 ******************************/
function rch_user_guide_render_tab()
{
    if (!defined('RCH_PLUGIN_DIR') || !defined('RCH_PLUGIN_URL')) {
        return;
    }

    $body_file = RCH_PLUGIN_DIR . 'docs/user-guide-body.html';
    $html_url  = RCH_PLUGIN_URL . 'docs/user-guide.html';
    $md_url    = RCH_PLUGIN_URL . 'docs/user-guide.md';
    $version   = defined('RCH_VERSION') ? RCH_VERSION : '';

    ?>
    <div class="tab-content">
        <div class="rch-tab-intro">
            <h2>
                <span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
                <?php esc_html_e('User guide', 'rechat-plugin'); ?>
            </h2>
            <p>
                <?php
                printf(
                    /* translators: %s: plugin version */
                    esc_html__('How to connect, sync, build listing pages and run agent websites with the Rechat plugin (version %s). Download a copy to read offline or share with your team.', 'rechat-plugin'),
                    esc_html($version)
                );
                ?>
            </p>
        </div>

        <div class="rch-guide">
            <div class="rch-guide__actions">
                <a class="button button-primary" href="<?php echo esc_url($html_url); ?>" download="rechat-plugin-user-guide.html">
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    <?php esc_html_e('Download guide (HTML)', 'rechat-plugin'); ?>
                </a>
                <a class="button" href="<?php echo esc_url($md_url); ?>" download="rechat-plugin-user-guide.md">
                    <span class="dashicons dashicons-media-text" aria-hidden="true"></span>
                    <?php esc_html_e('Download Markdown', 'rechat-plugin'); ?>
                </a>
                <a class="button" href="<?php echo esc_url($html_url . '#print'); ?>" target="_blank" rel="noopener">
                    <span class="dashicons dashicons-printer" aria-hidden="true"></span>
                    <?php esc_html_e('Print / Save as PDF', 'rechat-plugin'); ?>
                </a>
            </div>

            <?php
            if (is_readable($body_file)) {
                echo wp_kses_post(file_get_contents($body_file));
            } else {
                echo '<div class="notice notice-warning inline"><p>' . esc_html__('The user guide file is missing. Reinstall the plugin or run "npm run build:guide".', 'rechat-plugin') . '</p></div>';
            }
            ?>
        </div>
    </div>
    <?php
}
