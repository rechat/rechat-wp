<?php
/**
 * Custom “Agent” role for WordPress users provisioned on agent sub-sites, plus Rechat settings capability.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('RCH_CAP_MANAGE_RECHAT')) {
    define('RCH_CAP_MANAGE_RECHAT', 'rch_manage_rechat_settings');
}

/**
 * Capability required to access the Rechat settings UI and related AJAX/OAuth actions.
 */
function rch_rechat_settings_capability(): string
{
    return (string) apply_filters('rch_rechat_settings_capability', RCH_CAP_MANAGE_RECHAT);
}

/**
 * Role slug assigned to users linked to an agent sub-site (replaces the former `editor` assignment).
 */
function rch_agent_site_user_role(): string
{
    return (string) apply_filters('rch_agent_site_user_role', 'agent');
}

/**
 * Whether the current user may use Rechat settings (agents with custom cap, site admins, super admins).
 */
function rch_current_user_can_manage_rechat(): bool
{
    if (is_multisite() && is_super_admin()) {
        return true;
    }

    $cap = rch_rechat_settings_capability();

    return current_user_can($cap) || current_user_can('manage_options');
}

/**
 * Register / upgrade the Agent role and grant administrators the Rechat settings capability.
 *
 * @return void
 */
function rch_register_agent_user_roles(): void
{
    if (! function_exists('add_role') || ! function_exists('get_role')) {
        return;
    }

    $role_slug = rch_agent_site_user_role();
    $display   = __('Agent', 'rechat-plugin');

    $editor = get_role('editor');
    $caps   = [];

    if ($editor && is_array($editor->capabilities)) {
        foreach ($editor->capabilities as $cap => $granted) {
            if ($granted) {
                $caps[ $cap ] = true;
            }
        }
    }

    if ($caps === []) {
        $caps = [
            'read'                   => true,
            'upload_files'           => true,
            'edit_posts'             => true,
            'edit_pages'             => true,
            'edit_others_posts'      => true,
            'edit_others_pages'      => true,
            'edit_published_posts'   => true,
            'edit_published_pages'   => true,
            'publish_posts'          => true,
            'publish_pages'          => true,
            'delete_posts'           => true,
            'delete_pages'           => true,
            'delete_others_posts'    => true,
            'delete_others_pages'    => true,
            'delete_published_posts' => true,
            'delete_published_pages' => true,
            'delete_private_posts'   => true,
            'delete_private_pages'   => true,
            'edit_private_posts'     => true,
            'edit_private_pages'     => true,
            'read_private_posts'     => true,
            'read_private_pages'     => true,
        ];
    }

    $caps[ RCH_CAP_MANAGE_RECHAT ] = true;
    $caps['list_users']            = true;

    $role_obj = get_role($role_slug);

    if (! $role_obj) {
        add_role($role_slug, $display, $caps);
        $role_obj = get_role($role_slug);
    }

    if ($role_obj) {
        foreach ($caps as $cap => $on) {
            if ($on) {
                $role_obj->add_cap((string) $cap);
            }
        }
    }

    $admin = get_role('administrator');

    if ($admin && ! $admin->has_cap(RCH_CAP_MANAGE_RECHAT)) {
        $admin->add_cap(RCH_CAP_MANAGE_RECHAT);
    }
}

add_action('init', static function (): void {
    rch_register_agent_user_roles();
}, 5);

/**
 * Let multisite super admins pass Rechat capability checks on any blog.
 *
 * @param array<string,bool> $allcaps All capabilities for the user.
 * @param string[]           $caps    Primitive capabilities being checked.
 * @param array<int,mixed>   $args    Addition arguments for the check.
 * @param \WP_User           $user    User object.
 * @return array<string,bool>
 */
function rch_grant_rechat_cap_to_super_admin($allcaps, $caps = null, $args = null, $user = null)
{
    if (! is_multisite()) {
        return $allcaps;
    }

    $uid = 0;

    if ($user instanceof WP_User) {
        $uid = (int) $user->ID;
    } elseif (is_numeric($user)) {
        $uid = (int) $user;
    }

    if (! $uid) {
        $uid = (int) get_current_user_id();
    }

    if ($uid && is_super_admin($uid)) {
        $allcaps[ RCH_CAP_MANAGE_RECHAT ] = true;
    }

    return $allcaps;
}

add_filter('user_has_cap', 'rch_grant_rechat_cap_to_super_admin', 10, 4);

/**
 * Allow saving Rechat option groups with the custom capability (defaults to manage_options).
 *
 * @param string $capability Default capability for the options group.
 * @return string
 */
function rch_filter_option_page_capability(string $capability): string
{
    return rch_rechat_settings_capability();
}

add_filter('option_page_capability_appearance_settings', 'rch_filter_option_page_capability');
add_filter('option_page_capability_general_settings', 'rch_filter_option_page_capability');
add_filter('option_page_capability_local_logic_settings', 'rch_filter_option_page_capability');
