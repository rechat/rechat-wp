<?php
/*
Plugin Name: Rechat Plugin
Description: Fetches and manages agent, offices, regions, and Listing data from Rechat.
Version: 7.0.6
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
define('RCH_VERSION', '7.0.6');
define('RCH_VERSION_SWIPER', '11.2.5');
if (! function_exists('rch_is_localhost_environment')) {
    /**
     * True when site runs on local dev (localhost, 127.0.0.1, .local, .test, or WP_ENVIRONMENT_TYPE=local).
     */
    function rch_is_localhost_environment(): bool
    {
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
            return true;
        }

        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        $host = strtolower(preg_replace('/:\d+$/', '', $host));

        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return true;
        }

        foreach (['.local', '.test', '.localhost'] as $tld) {
            $len = strlen($tld);
            if ($len > 0 && strlen($host) >= $len && substr($host, -$len) === $tld) {
                return true;
            }
        }

        return false;
    }
}

/** Pinned @rechat/sdk version (must match unpkg URL path when not on localhost builder). */
if (! defined('RCH_RECHAT_SDK_VERSION')) {
    define('RCH_RECHAT_SDK_VERSION', '2.0.3');
}

if (! defined('RCH_RECHAT_SDK_CSS_URL')) {
    define(
        'RCH_RECHAT_SDK_CSS_URL',
        rch_is_localhost_environment()
            ? 'https://sdk.rechat.com/builder/dist/rechat.min.css'
            : 'https://unpkg.com/@rechat/sdk@' . RCH_RECHAT_SDK_VERSION . '/dist/rechat.min.css'
    );
}
if (! defined('RCH_RECHAT_SDK_JS_URL')) {
    define(
        'RCH_RECHAT_SDK_JS_URL',
        rch_is_localhost_environment()
            ? 'https://sdk.rechat.com/builder/dist/rechat.min.js'
            : 'https://unpkg.com/@rechat/sdk@' . RCH_RECHAT_SDK_VERSION . '/dist/rechat.min.js'
    );
}
const RCH_PLUGIN_INCLUDES = RCH_PLUGIN_DIR . 'includes/';
/** Post meta: manual sort position for agents (lower first). Legacy DB rows may still equal RCH_AGENT_DISPLAY_ORDER_EMPTY_SORT (treated like empty). */
if (! defined('RCH_AGENT_DISPLAY_ORDER_META_KEY')) {
    define('RCH_AGENT_DISPLAY_ORDER_META_KEY', 'agent_display_order');
}
if (! defined('RCH_AGENT_DISPLAY_ORDER_EMPTY_SORT')) {
    define('RCH_AGENT_DISPLAY_ORDER_EMPTY_SORT', 999999);
}
const RCH_PLUGIN_ASSETS = RCH_PLUGIN_URL . 'assets/';
const RCH_PLUGIN_ASSETS_URL_IMG = RCH_PLUGIN_URL . 'assets/images/';
// Rechat REST API host (shared across plugin HTTP calls)
if (! defined('RECHAT_API_BASE_URL')) {
    define('RECHAT_API_BASE_URL', 'https://api.rechat.com');
}
// OAuth2 Configuration Constants
const RCH_OAUTH_CLIENT_ID = '65230631-97a6-4fb5-bf32-54aafb1e1b54';
const RCH_OAUTH_CLIENT_SECRET = 'secret';
const RCH_OAUTH_AUTH_URL = 'https://app.rechat.com/oauth2/auth';
if (! defined('RCH_OAUTH_TOKEN_URL')) {
    define('RCH_OAUTH_TOKEN_URL', rtrim(RECHAT_API_BASE_URL, '/') . '/oauth2/token');
}
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

require_once RCH_PLUGIN_INCLUDES . 'roles/agent-user-role.php';

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
    // New: /listing-detail/{city}/{street_address}/{id}/
    add_rewrite_rule(
        '^listing-detail/([^/]+)/([^/]+)/([a-f0-9\\-]+)/?$',
        'index.php?listing_detail=1&listing_city=$matches[1]&listing_street=$matches[2]&listing_id=$matches[3]',
        'top'
    );
    // Legacy: /listing-detail/{street_address}/{id}/ (kept for redirects)
    add_rewrite_rule(
        '^listing-detail/([^/]+)/([a-f0-9\\-]+)/?$',
        'index.php?listing_detail=1&listing_street=$matches[1]&listing_id=$matches[2]',
        'top'
    );
    flush_rewrite_rules();

    if (function_exists('rch_register_agent_user_roles')) {
        rch_register_agent_user_roles();
    }
}
register_activation_hook(__FILE__, 'rch_plugin_activate');

// Add rewrite rules on init
add_action('init', 'rch_add_rewrite_rules');
function rch_add_rewrite_rules()
{
    // New: /listing-detail/{city}/{street_address}/{id}/
    add_rewrite_rule(
        '^listing-detail/([^/]+)/([^/]+)/([a-f0-9\\-]+)/?$',
        'index.php?listing_detail=1&listing_city=$matches[1]&listing_street=$matches[2]&listing_id=$matches[3]',
        'top'
    );
    // Legacy: /listing-detail/{street_address}/{id}/ (kept for redirects)
    add_rewrite_rule(
        '^listing-detail/([^/]+)/([a-f0-9\\-]+)/?$',
        'index.php?listing_detail=1&listing_street=$matches[1]&listing_id=$matches[2]',
        'top'
    );
}
// Register the query variable
add_filter('query_vars', 'rch_plugin_query_vars');
function rch_plugin_query_vars($vars)
{
    $vars[] = 'house_detail';
    $vars[] = 'listing_detail';
    $vars[] = 'listing_id';
    $vars[] = 'listing_city';
    $vars[] = 'listing_street';
    return $vars;
}
// Add logic seprate in admin or Frontend
include RCH_PLUGIN_INCLUDES . 'front/enqueue-front.php';
include RCH_PLUGIN_INCLUDES . 'schema/load-schema.php';
include RCH_PLUGIN_INCLUDES . 'seo/auto-meta-tags.php';
include RCH_PLUGIN_INCLUDES . 'front/add-css-in-setting.php';
if (is_multisite()) {
    require_once RCH_PLUGIN_INCLUDES . 'multisite/subsite-admin-context.php';
}
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
// Multisite: Broadcast integration (no-op on single-site installs)
include RCH_PLUGIN_INCLUDES . 'multisite/broadcast-integration.php';
include RCH_PLUGIN_INCLUDES . 'multisite/neighborhood-broadcast.php';
// Multisite: agent sub-site management (no-op on single-site installs)
include RCH_PLUGIN_INCLUDES . 'multisite/agent-sites.php';
// Multisite: agent → sub-site theme options wizard (AJAX + tab UI)
if (is_multisite()) {
    require_once RCH_PLUGIN_INCLUDES . 'multisite/agent-site-deploy-wizard.php';
}
// Multisite: prompt to install & activate Broadcast (ThreeWP) from wordpress.org — admin only
if (is_multisite() && is_admin()) {
    require_once RCH_PLUGIN_INCLUDES . 'tgm/class-tgm-plugin-activation.php';
    require_once RCH_PLUGIN_INCLUDES . 'tgm/register-tgmpa.php';
}
if (is_admin()) {
    include RCH_PLUGIN_INCLUDES . 'admin/enqueue-admin.php';
    require_once RCH_PLUGIN_INCLUDES . 'admin/agent-data-csv-importer.php';
}