<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RealScout shared helpers (front + admin).
 *
 * Storage (managed only on the hidden admin page, wp-admin/admin.php?page=realscout):
 * - Agent value: protected post meta on the `agents` CPT (main site on multisite).
 * - Main website ID: option on the main site.
 *
 * Values are RealScout agent numbers (e.g. 322973). RealScout widgets expect
 * agent-encoded-id = base64("Agent-322973") = "QWdlbnQtMzIyOTcz".
 */

if (!defined('RCH_REALSCOUT_AGENT_META_KEY')) {
    define('RCH_REALSCOUT_AGENT_META_KEY', '_rch_realscout_agent_value');
}
if (!defined('RCH_REALSCOUT_MAIN_SITE_ID_OPTION')) {
    define('RCH_REALSCOUT_MAIN_SITE_ID_OPTION', 'rch_realscout_main_site_id');
}
if (!defined('RCH_REALSCOUT_SCRIPT_URL')) {
    define('RCH_REALSCOUT_SCRIPT_URL', 'https://em.realscout.com/widgets/realscout-web-components.umd.js');
}

/*******************************
 * Getters (current blog)
 ******************************/
function rch_realscout_get_agent_value(int $agent_id): string
{
    return (string) get_post_meta($agent_id, RCH_REALSCOUT_AGENT_META_KEY, true);
}

function rch_realscout_get_main_site_id(): string
{
    return (string) get_option(RCH_REALSCOUT_MAIN_SITE_ID_OPTION, '');
}

/*******************************
 * Normalize admin input: digits stay as-is; a pasted encoded ID
 * ("QWdlbnQtMzIyOTcz") is decoded back to its number. Returns '' if invalid.
 ******************************/
function rch_realscout_normalize_id(string $value): string
{
    $value = trim($value);

    if ($value === '' || ctype_digit($value)) {
        return $value;
    }

    $decoded = base64_decode($value, true);
    if (is_string($decoded) && preg_match('/^Agent-(\d+)$/', $decoded, $m)) {
        return $m[1];
    }

    return '';
}

/*******************************
 * Stored number → RealScout agent-encoded-id.
 ******************************/
function rch_realscout_encode_id(string $value): string
{
    $value = rch_realscout_normalize_id($value);

    return $value === '' ? '' : base64_encode('Agent-' . $value);
}

/*******************************
 * Read values from the main site (where the admin page stores them).
 * Single-site installs read the current blog.
 ******************************/
function rch_realscout_hub_agent_value(int $hub_agent_id): string
{
    if ($hub_agent_id <= 0) {
        return '';
    }

    if (!is_multisite() || (int) get_current_blog_id() === (int) get_main_site_id()) {
        return rch_realscout_get_agent_value($hub_agent_id);
    }

    switch_to_blog((int) get_main_site_id());
    $value = rch_realscout_get_agent_value($hub_agent_id);
    restore_current_blog();

    return $value;
}

function rch_realscout_hub_main_site_id(): string
{
    if (function_exists('rch_multisite_get_hub_site_option')) {
        return (string) rch_multisite_get_hub_site_option(RCH_REALSCOUT_MAIN_SITE_ID_OPTION);
    }

    return rch_realscout_get_main_site_id();
}

/*******************************
 * Resolve the agent-encoded-id for the current blog.
 *
 * - Agent subsite: value of the linked main-site agent.
 * - Otherwise (hub, office subsite, single-site) or agent without a value:
 *   main website ID.
 ******************************/
function rch_realscout_resolve_encoded_id(): string
{
    static $cache = [];

    $blog_id = (int) get_current_blog_id();
    if (array_key_exists($blog_id, $cache)) {
        return $cache[$blog_id];
    }

    $encoded = '';

    if (is_multisite() && function_exists('rch_multisite_resolve_agent_post_id_for_current_blog')) {
        $hub_agent_id = rch_multisite_resolve_agent_post_id_for_current_blog();
        $encoded      = rch_realscout_encode_id(rch_realscout_hub_agent_value($hub_agent_id));
    }

    if ($encoded === '') {
        $encoded = rch_realscout_encode_id(rch_realscout_hub_main_site_id());
    }

    $cache[$blog_id] = (string) apply_filters('rch_realscout_encoded_id', $encoded, $blog_id);

    return $cache[$blog_id];
}
