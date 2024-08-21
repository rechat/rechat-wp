<?php
/*
Plugin Name: Rechat Agents Plugin
Description: Fetches and manages agent data from Rechat.
Version: 1.0.0
Author URI: https://rechat.com/
*/

// Exit if accessed directly
if (! defined('ABSPATH')) {
    http_response_code(404);
    exit();
}
// define required constants.
define('RCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RCH_PLUGIN_URL', plugin_dir_url(__FILE__));
const RCH_PLUGIN_INCLUDES = RCH_PLUGIN_DIR . 'includes/';
const RCH_PLUGIN_ASSETS = RCH_PLUGIN_DIR . 'assets/';
const RCH_PLUGIN_ASSETS_URL = RCH_PLUGIN_URL . 'assets/images/';

// register_activation_hook();
// Add a "Settings" link to the plugin actions
function rch_plugin_action_links($links)
{
    $settings_link = '<a href="admin.php?page=rechat-setting">' . __('Settings') . '</a>';
    array_push($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'rch_plugin_action_links');

// Add logic seprate in admin or Frontend
include RCH_PLUGIN_INCLUDES . 'front/enqueue-front.php';
include RCH_PLUGIN_INCLUDES . 'admin/custom-post.php';
include RCH_PLUGIN_INCLUDES . 'admin/template-load.php';
include RCH_PLUGIN_DIR . 'templates/archive/search-result.php';
include RCH_PLUGIN_INCLUDES . 'api-load.php';
include RCH_PLUGIN_INCLUDES . 'admin/schedule.php';
include RCH_PLUGIN_INCLUDES . 'admin/menu-setting.php';
include RCH_PLUGIN_INCLUDES . 'front/add-css-in-setting.php';
if (is_admin()) {
    include RCH_PLUGIN_INCLUDES . 'admin/enqueue-admin.php';
    
    include RCH_PLUGIN_INCLUDES . 'admin/custom-meta-boxes.php';
}


