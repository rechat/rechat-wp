<?php
if (! defined('ABSPATH')) {
    exit();
}

/**
 * Agent post meta written during Rechat API sync (rch_update_agents_offices_regions_data).
 *
 * Keys match agents metabox meta (metaboxes-for-agents.php).
 *
 * @param array  $user                      Rechat API user object ($item['user']).
 * @param string $api_id                    Brand-user association id ($item['id']).
 * @param string $default_profile_image_url Fallback when profile_image_url empty.
 * @param int[]  $regions_for_agent         Region post IDs.
 * @param int[]  $offices_for_agent         Office post IDs.
 * @return array<string, mixed>
 */
function rch_agent_meta_from_rechat_api_user(
    array $user,
    string $api_id,
    string $default_profile_image_url,
    array $regions_for_agent,
    array $offices_for_agent
): array {
    return array(
        'website' => $user['website'] ?? '',
        'instagram' => $user['instagram'] ?? '',
        'twitter' => $user['twitter'] ?? '',
        'linkedin' => $user['linkedin'] ?? '',
        'youtube' => $user['youtube'] ?? '',
        'facebook' => $user['facebook'] ?? '',
        'profile_image_url' => ! empty($user['profile_image_url']) ? $user['profile_image_url'] : $default_profile_image_url,
        'phone_number' => $user['phone_number'] ?? '',
        'email' => $user['email'] ?? '',
        'timezone' => $user['timezone'] ?? '',
        'designation' => $user['designation'] ?? '',
        'license_number' => $user['agents'][0]['license_number'] ?? '',
        'agents' => isset($user['agents']) && is_array($user['agents']) ?
            array_map(function ($agent) {
                return $agent['id'] ?? null;
            }, array_filter($user['agents'], function ($agent) {
                return isset($agent['id']);
            })) : array(),
        'api_id' => $api_id,
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? '',
        '_rch_agent_regions' => $regions_for_agent,
        '_rch_agent_offices' => $offices_for_agent,
    );
}

/**
 * @param array $user Rechat API user object.
 */
function rch_agent_display_name_from_rechat_api_user(array $user): string
{
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}
