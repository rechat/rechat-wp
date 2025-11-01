<?php
/*
Plugin Name: Rechat Plugin
Description: Fetches and manages agent, offices, regions, and Listing data from Rechat.
Version: 6.0.1
Author URI: https://rechat.com/
Text Domain: rechat-plugin
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: https://github.com/rechat/rechat-wp
GitHub Branch: master
*/
// Exit if accessed directly
if (! defined('ABSPATH')) {
    http_response_code(404);
    exit();
}
// define required constants.
define('RCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RCH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RCH_VERSION', '1.0.0');
define('RCH_VERSION_SWIPER', '11.2.5');
const RCH_PLUGIN_INCLUDES = RCH_PLUGIN_DIR . 'includes/';
const RCH_PLUGIN_ASSETS = RCH_PLUGIN_URL . 'assets/';
const RCH_PLUGIN_ASSETS_URL_IMG = RCH_PLUGIN_URL . 'assets/images/';
// OAuth2 Configuration Constants
const RCH_OAUTH_CLIENT_ID = '65230631-97a6-4fb5-bf32-54aafb1e1b54';
const RCH_OAUTH_CLIENT_SECRET = 'secret';
const RCH_OAUTH_AUTH_URL = 'https://app.rechat.com/oauth2/auth';
const RCH_OAUTH_TOKEN_URL = 'https://api.rechat.com/oauth2/token';
const RCH_TOKEN_REFRESH_HOOK = 'rch_refresh_token_event';
// Constants for cron configuration
const RCH_CRON_HOOK = 'rch_data_sync_hook';
const RCH_CRON_INTERVAL = 'rch_every_12_hours';
const RCH_CRON_INTERVAL_SECONDS = 12 * HOUR_IN_SECONDS;
// Constants for settings group
const RCH_APPEARANCE_SETTINGS_GROUP = 'appearance_settings';
// Constants for settings group
const RCH_LOCAL_LOGIC_SETTINGS_GROUP = 'local_logic_settings';

// Available features for listing page
const RCH_LOCAL_LOGIC_FEATURES = [
    'LocalContent' => 'Local Content',
];

// Available features for neighborhood page
const RCH_NEIGHBORHOOD_FEATURES = [
    'Hero' => 'Neighborhood Hero',
    'Map' => 'Neighborhood Map',
    'Highlights' => 'Neighborhood Highlights',
    'Characteristics' => 'Neighborhood Characteristics',
    'Schools' => 'Neighborhood Schools',
    'Demographics' => 'Neighborhood Demographics',
    'PropertyValueDrivers' => 'Neighborhood Property Value Drivers',
    'MarketTrends' => 'Neighborhood Market Trends',
    'Match' => 'Neighborhood Match',
];

// Add a "Settings" link to the plugin actions
function rch_plugin_action_links($links)
{
    $settings_link = '<a href="admin.php?page=rechat-setting">' . __('Settings', 'rechat-plugin') . '</a>';
    array_push($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'rch_plugin_action_links');

// Register activation hook to flush rewrite rules

function rch_plugin_activate()
{
    add_rewrite_rule('^listing-detail/?$', 'index.php?listing_detail=1', 'top');
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'rch_plugin_activate');

// Register the query variable
add_filter('query_vars', 'rch_plugin_query_vars');
function rch_plugin_query_vars($vars)
{
    $vars[] = 'house_detail';
    return $vars;
}
// Add logic seprate in admin or Frontend
include RCH_PLUGIN_INCLUDES . 'front/enqueue-front.php';
include RCH_PLUGIN_INCLUDES . 'front/add-css-in-setting.php';
include RCH_PLUGIN_INCLUDES . 'admin/register-custom-post-type.php';
include RCH_PLUGIN_INCLUDES . 'admin/settings-page/other-settings.php';
include RCH_PLUGIN_INCLUDES . 'admin/settings-page/local-logic-setting.php';
include RCH_PLUGIN_INCLUDES . 'admin/menu-setting.php';
include RCH_PLUGIN_INCLUDES . 'admin/custom-fields.php';
include RCH_PLUGIN_INCLUDES . 'template-load.php';
include RCH_PLUGIN_INCLUDES . 'helper.php';
include RCH_PLUGIN_DIR . 'templates/archive/search-result.php';
include RCH_PLUGIN_INCLUDES . 'load-agents-regions-offices/api-load-agents-regions-offices.php';
include RCH_PLUGIN_INCLUDES . 'cron-job/schedule.php';
include RCH_PLUGIN_INCLUDES . 'oauth2/oauth-handler.php';
include RCH_PLUGIN_INCLUDES . 'load-listing/fetch-archive-listings.php';
include RCH_PLUGIN_INCLUDES . 'shortcodes/listing-shortcodes.php';
include RCH_PLUGIN_INCLUDES . 'shortcodes/lead-capture-shortcode.php';
include RCH_PLUGIN_INCLUDES . 'shortcodes/latest-listing-shortcode.php';
include RCH_PLUGIN_INCLUDES . 'shortcodes/search_listing_shortcode.php';
include RCH_PLUGIN_INCLUDES . 'gutenberg-block/block-offices-regions.php';
include RCH_PLUGIN_INCLUDES . 'gutenberg-block/block-agents.php';
include RCH_PLUGIN_INCLUDES . 'gutenberg-block/block-listing.php';
include RCH_PLUGIN_INCLUDES . 'gutenberg-block/block-lead-form.php';
include RCH_PLUGIN_INCLUDES . 'metabox/load-all-meta-boxes.php';
if (is_admin()) {
    include RCH_PLUGIN_INCLUDES . 'admin/enqueue-admin.php';


}