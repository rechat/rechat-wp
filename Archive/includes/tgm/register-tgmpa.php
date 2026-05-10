<?php
/**
 * Register required plugins for Multisite (TGMPA).
 *
 * Broadcast (ThreeWP) — https://wordpress.org/plugins/threewp-broadcast/
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('tgmpa_register', 'rch_tgmpa_register_multisite_dependencies');

/**
 * On Multisite only: require Broadcast so content can be synced across agent/office sub-sites.
 *
 * @return void
 */
function rch_tgmpa_register_multisite_dependencies(): void
{
    if (! is_multisite()) {
        return;
    }

    $plugins = [
        [
            'name'     => 'Broadcast',
            'slug'     => 'threewp-broadcast',
            'required' => true,
            'version'  => '',
        ],
    ];

    $config = [
        'id'           => 'rch_rechat_tgmpa',
        'default_path' => '',
        'menu'         => 'rch-install-plugins',
        'parent_slug'  => 'plugins.php',
        'capability'   => 'install_plugins',
        'has_notices'  => true,
        'dismissable'  => false,
        'dismiss_msg'  => '',
        'is_automatic' => true,
        'message'      => sprintf(
            /* translators: %s: plugin name (Broadcast) */
            __(
                'When Rechat creates agent or office sub-sites, the %s plugin must be installed and network-activated so shared content can be pushed to each new site automatically.',
                'rechat-plugin'
            ),
            'Broadcast (ThreeWP Broadcast)'
        ),
    ];

    tgmpa($plugins, $config);
}
