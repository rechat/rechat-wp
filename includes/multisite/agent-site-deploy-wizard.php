<?php
/**
 * Multisite: Agent sub-site theme options deploy wizard (main site admin).
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * User meta key for draft wizard state (JSON).
 */
const RCH_AGENT_WIZARD_DRAFT_META = 'rch_agent_site_wizard_draft';

/**
 * AJAX nonce action.
 */
const RCH_AGENT_WIZARD_NONCE_ACTION = 'rch_agent_wizard';

/**
 * Stylesheet slug used to build wizard rows (network “Default theme for agent sub-sites”).
 */
function rch_agent_wizard_wizard_ui_stylesheet(): string
{
    if (function_exists('rch_multisite_resolve_theme_network_default_for_agents')) {
        return (string) rch_multisite_resolve_theme_network_default_for_agents()['stylesheet'];
    }

    return (string) wp_get_theme()->get_stylesheet();
}

/**
 * Labels shared by all profiles (wizard-only option keys).
 *
 * @return array<string, string>
 */
function rch_agent_wizard_wizard_only_labels(): array
{
    return [
        'rch-wizard-agent-email'       => __('Agent email (stored in theme options)', 'rechat-plugin'),
        'rch-wizard-agent-post-id'     => __('Agent post ID (stored in theme options)', 'rechat-plugin'),
        'rch-wizard-agent-api-id'      => __('Rechat / API ID (stored in theme options)', 'rechat-plugin'),
        'rch-wizard-agent-designation' => __('Designation (stored in theme options)', 'rechat-plugin'),
    ];
}

/**
 * Merge theme keys with wizard-only keys.
 *
 * @param list<string> $theme_keys
 * @return list<string>
 */
function rch_agent_wizard_merge_wizard_keys(array $theme_keys): array
{
    $wizard = ['rch-wizard-agent-email', 'rch-wizard-agent-post-id', 'rch-wizard-agent-api-id', 'rch-wizard-agent-designation'];

    return array_values(array_unique(array_merge($theme_keys, $wizard)));
}

/**
 * Acropolis-agent profile (pentama_options_v2 + pentama_options_agent_website).
 *
 * @return array<string, mixed>
 */
function rch_agent_wizard_profile_acropolis(): array
{
    $theme_keys = [
        'rch-theme-phone',
        'rch-theme-logo-one',
        'rch-theme-menu-image',
        'rch-theme-title-main-hero',
        'rch-theme-hero-description',
        'rch-theme-hero-video',
        'rch-counter-1-value',
        'rch-counter-1-text',
        'rch-counter-2-value',
        'rch-counter-2-text',
        'rch-counter-3-value',
        'rch-counter-3-text',
        'rch-counter-4-value',
        'rch-counter-4-text',
        'rch-theme-results-description',
        'rch-theme-title-listing',
        'rch-theme-description-listing',
        'rch-theme-title-listing-button',
        'rch-theme-url-listing-button',
        'rch-theme-title-agents',
        'rch-theme-title-blog',
        'rch-theme-title-testimonial',
        'rch-theme-talk-title',
        'rch-theme-talk-description',
        'rch-theme-address-line-1',
        'rch-theme-address-line-2',
        'rch-theme-talk-shortcode',
        'rch-theme-talk-image',
        'rch-theme-telegram-url',
        'rch-theme-whatsapp-url',
        'rch-theme-instagram-url',
        'rch-village-theme-lead-channel',
        'rch_selected_tags',
    ];

    $labels = [
        'rch-theme-phone'               => __('Phone Number', 'rechat-plugin'),
        'rch-theme-logo-one'            => __('Logo', 'rechat-plugin'),
        'rch-theme-menu-image'          => __('Image For Section 2', 'rechat-plugin'),
        'rch-theme-hero-video'          => __('Hero Section Video', 'rechat-plugin'),
        'rch-theme-title-main-hero'     => __('Main Title For Hero Section', 'rechat-plugin'),
        'rch-theme-hero-description'    => __('Hero Section Description', 'rechat-plugin'),
        'rch-counter-1-value'           => __('Counter 1 — value', 'rechat-plugin'),
        'rch-counter-1-text'            => __('Counter 1 — label text', 'rechat-plugin'),
        'rch-counter-2-value'           => __('Counter 2 — value', 'rechat-plugin'),
        'rch-counter-2-text'            => __('Counter 2 — label text', 'rechat-plugin'),
        'rch-counter-3-value'           => __('Counter 3 — value', 'rechat-plugin'),
        'rch-counter-3-text'            => __('Counter 3 — label text', 'rechat-plugin'),
        'rch-counter-4-value'           => __('Counter 4 — value', 'rechat-plugin'),
        'rch-counter-4-text'            => __('Counter 4 — label text', 'rechat-plugin'),
        'rch-theme-results-description' => __('Description For Results', 'rechat-plugin'),
        'rch-theme-title-listing'       => __('Title For Listing', 'rechat-plugin'),
        'rch-theme-description-listing' => __('Description For Listing', 'rechat-plugin'),
        'rch-theme-title-listing-button'=> __('Title For Listing Button', 'rechat-plugin'),
        'rch-theme-url-listing-button'  => __('Url For Listing Button', 'rechat-plugin'),
        'rch-theme-title-agents'        => __('Title For Agents', 'rechat-plugin'),
        'rch-theme-title-blog'          => __('Title For Blog', 'rechat-plugin'),
        'rch-theme-title-testimonial'   => __('Title For Testimonial', 'rechat-plugin'),
        'rch-theme-talk-title'          => __('Title For Talk Section', 'rechat-plugin'),
        'rch-theme-talk-description'    => __('Description For Talk Section', 'rechat-plugin'),
        'rch-theme-address-line-1'      => __('First Line Address', 'rechat-plugin'),
        'rch-theme-address-line-2'      => __('Second Line Address', 'rechat-plugin'),
        'rch-theme-talk-shortcode'      => __('Shortcode For Talk Section', 'rechat-plugin'),
        'rch-village-theme-lead-channel'=> __('Lead Channels For index Form', 'rechat-plugin'),
        'rch_selected_tags'             => __('Tags For Index Form Lead capture', 'rechat-plugin'),
        'rch-theme-talk-image'          => __('Image For Talk Section', 'rechat-plugin'),
        'rch-theme-telegram-url'        => __('Telegram URL', 'rechat-plugin'),
        'rch-theme-whatsapp-url'        => __('whatsapp URL', 'rechat-plugin'),
        'rch-theme-instagram-url'       => __('Instagram URL', 'rechat-plugin'),
    ];

    return [
        'keys'            => rch_agent_wizard_merge_wizard_keys($theme_keys),
        'labels'          => array_merge($labels, rch_agent_wizard_wizard_only_labels()),
        'urls'            => [
            'rch-theme-logo-one',
            'rch-theme-menu-image',
            'rch-theme-hero-video',
            'rch-theme-talk-image',
            'rch-theme-url-listing-button',
        ],
        'textareas'       => [
            'rch-theme-hero-description',
            'rch-theme-results-description',
            'rch-theme-description-listing',
            'rch-theme-talk-description',
        ],
        'textarea_json'   => ['rch_selected_tags'],
        'numbers'         => ['rch-counter-1-value', 'rch-counter-2-value', 'rch-counter-3-value', 'rch-counter-4-value'],
        'wp_kses'         => [
            'rch-theme-title-agents',
            'rch-theme-title-blog',
            'rch-theme-title-testimonial',
            'rch-theme-address-line-1',
            'rch-theme-address-line-2',
        ],
        'color'           => [],
        'image_media'     => ['rch-theme-logo-one', 'rch-theme-menu-image', 'rch-theme-talk-image'],
        'video_media'     => ['rch-theme-hero-video'],
        'import_defaults' => [],
        'storage_primary' => 'pentama_options_v2',
        'storage_mirror'  => 'pentama_options_agent_website',
    ];
}

/**
 * rechat-theme-one profile (single get_option('pentama_options')).
 *
 * @return array<string, mixed>
 */
function rch_agent_wizard_profile_rechat_theme_one(): array
{
    $theme_keys = [
        'rch-theme-phone',
        'rch-theme-logo-one',
        'rch-theme-linkedin',
        'rch-theme-instagram',
        'rch-theme-x',
        'rch-theme-title-main-hero',
        'rch-theme-hero-description',
        'rch-theme-title-listing',
        'rch-theme-listing-page-link',
        'rch-theme-title-on-big-img',
        'rch-theme-big-title-image',
        'rch-theme-title-for-start-conversation',
        'rch-theme-description-for-start-conversation',
        'rch-theme-title-for-our-agents',
        'rch-theme-title-for-meet-agents',
        'rch-theme-description-for-meet-agents',
        'rch-theme-title-for-local-office',
        'rch-theme-description-for-local-office',
        'rch-theme-local-office-image',
        'rch-theme-local-office-video',
        'rch-theme-local-office-bg-color',
        'rch-theme-brand-name-footer',
        'rch-theme-description-in-footer',
        'rch-theme-menu-image-1',
        'rch-theme-menu-image-2',
        'rch-theme-menu-image-3',
        'rch-theme-menu-image-4',
        'rch-theme-hero-video',
    ];

    $labels = [
        'rch-theme-phone'                            => __('Phone Number', 'rechat-plugin'),
        'rch-theme-logo-one'                         => __('Logo', 'rechat-plugin'),
        'rch-theme-linkedin'                         => __('LinkedIn URL', 'rechat-plugin'),
        'rch-theme-instagram'                        => __('Instagram URL', 'rechat-plugin'),
        'rch-theme-x'                                => __('X URL', 'rechat-plugin'),
        'rch-theme-title-main-hero'                  => __('Main Title For Hero Section', 'rechat-plugin'),
        'rch-theme-hero-description'               => __('Hero Section Description', 'rechat-plugin'),
        'rch-theme-title-listing'                  => __('Title For Latest Listing', 'rechat-plugin'),
        'rch-theme-listing-page-link'              => __('Link of Listing Page', 'rechat-plugin'),
        'rch-theme-title-on-big-img'               => __('Title on Big image in Main Page', 'rechat-plugin'),
        'rch-theme-big-title-image'                => __('Image for Big Title', 'rechat-plugin'),
        'rch-theme-title-for-start-conversation'   => __('Title For Start the Conversation', 'rechat-plugin'),
        'rch-theme-description-for-start-conversation' => __('Description For Start the Conversation', 'rechat-plugin'),
        'rch-theme-title-for-our-agents'           => __('Title For Our Agents', 'rechat-plugin'),
        'rch-theme-title-for-meet-agents'          => __('Title For Meet Our Expert Agents', 'rechat-plugin'),
        'rch-theme-description-for-meet-agents'    => __('Description For Meet Our Expert Agents', 'rechat-plugin'),
        'rch-theme-title-for-local-office'         => __('Title For Our Local Office', 'rechat-plugin'),
        'rch-theme-description-for-local-office'   => __('Description For Our Local Office', 'rechat-plugin'),
        'rch-theme-local-office-image'             => __('Image for Local Office', 'rechat-plugin'),
        'rch-theme-local-office-video'             => __('Video URL for Local Office', 'rechat-plugin'),
        'rch-theme-local-office-bg-color'          => __('Background Color for Local Office Box', 'rechat-plugin'),
        'rch-theme-brand-name-footer'              => __('Brand Name in Footer', 'rechat-plugin'),
        'rch-theme-description-in-footer'          => __('Description in Footer', 'rechat-plugin'),
        'rch-theme-menu-image-1'                   => __('Menu Image 1', 'rechat-plugin'),
        'rch-theme-menu-image-2'                   => __('Menu Image 2', 'rechat-plugin'),
        'rch-theme-menu-image-3'                   => __('Menu Image 3', 'rechat-plugin'),
        'rch-theme-menu-image-4'                   => __('Menu Image 4', 'rechat-plugin'),
        'rch-theme-hero-video'                     => __('Hero Section Video', 'rechat-plugin'),
    ];

    $menu_images = ['rch-theme-menu-image-1', 'rch-theme-menu-image-2', 'rch-theme-menu-image-3', 'rch-theme-menu-image-4'];

    return [
        'keys'            => rch_agent_wizard_merge_wizard_keys($theme_keys),
        'labels'          => array_merge($labels, rch_agent_wizard_wizard_only_labels()),
        'urls'            => array_merge(
            ['rch-theme-logo-one', 'rch-theme-linkedin', 'rch-theme-instagram', 'rch-theme-x', 'rch-theme-listing-page-link', 'rch-theme-big-title-image', 'rch-theme-local-office-image', 'rch-theme-local-office-video', 'rch-theme-hero-video'],
            $menu_images
        ),
        'textareas'       => [
            'rch-theme-hero-description',
            'rch-theme-description-for-meet-agents',
            'rch-theme-description-for-local-office',
            'rch-theme-description-in-footer',
        ],
        'textarea_json'   => [],
        'numbers'         => [],
        'wp_kses'         => [
            'rch-theme-title-main-hero',
            'rch-theme-description-for-start-conversation',
        ],
        'color'           => ['rch-theme-local-office-bg-color'],
        'image_media'     => array_merge(['rch-theme-logo-one', 'rch-theme-big-title-image', 'rch-theme-local-office-image'], $menu_images),
        'video_media'     => ['rch-theme-hero-video', 'rch-theme-local-office-video'],
        'import_defaults' => [
            'instagram'         => 'rch-theme-instagram',
            'website'           => 'rch-theme-listing-page-link',
            'linkedin'          => 'rch-theme-linkedin',
            'twitter'           => 'rch-theme-x',
            'profile_image_url' => 'rch-theme-menu-image-1',
        ],
        'storage_primary' => 'pentama_options',
        'storage_mirror'  => null,
    ];
}

/**
 * rechat-theme-two profile (get_option('pentama_options'), Acropolis-like subset).
 *
 * @return array<string, mixed>
 */
function rch_agent_wizard_profile_rechat_theme_two(): array
{
    $theme_keys = [
        'rch-theme-phone',
        'rch-theme-logo-one',
        'rch-theme-menu-image',
        'rch-theme-title-main-hero',
        'rch-theme-hero-description',
        'rch-theme-hero-video',
        'rch-counter-1-value',
        'rch-counter-1-text',
        'rch-counter-2-value',
        'rch-counter-2-text',
        'rch-counter-3-value',
        'rch-counter-3-text',
        'rch-theme-results-description',
        'rch-theme-title-listing',
        'rch-theme-description-listing',
        'rch-theme-title-listing-button',
        'rch-theme-url-listing-button',
        'rch-theme-title-agents',
        'rch-theme-title-blog',
        'rch-theme-title-testimonial',
        'rch-theme-talk-title',
        'rch-theme-talk-description',
        'rch-theme-talk-shortcode',
        'rch-theme-talk-image',
        'rch-theme-telegram-url',
        'rch-theme-whatsapp-url',
        'rch-theme-instagram-url',
    ];

    $p = rch_agent_wizard_profile_acropolis();
    $labels = array_intersect_key($p['labels'], array_flip($theme_keys));

    return [
        'keys'            => rch_agent_wizard_merge_wizard_keys($theme_keys),
        'labels'          => array_merge($labels, rch_agent_wizard_wizard_only_labels()),
        'urls'            => [
            'rch-theme-logo-one',
            'rch-theme-menu-image',
            'rch-theme-hero-video',
            'rch-theme-talk-image',
            'rch-theme-url-listing-button',
        ],
        'textareas'       => [
            'rch-theme-hero-description',
            'rch-theme-results-description',
            'rch-theme-description-listing',
            'rch-theme-talk-description',
        ],
        'textarea_json'   => [],
        'numbers'         => ['rch-counter-1-value', 'rch-counter-2-value', 'rch-counter-3-value'],
        'wp_kses'         => [
            'rch-theme-title-agents',
            'rch-theme-title-blog',
            'rch-theme-title-testimonial',
        ],
        'color'           => [],
        'image_media'     => ['rch-theme-logo-one', 'rch-theme-menu-image', 'rch-theme-talk-image'],
        'video_media'     => ['rch-theme-hero-video'],
        'import_defaults' => [],
        'storage_primary' => 'pentama_options',
        'storage_mirror'  => null,
    ];
}

/**
 * Legacy field metadata (hardcoded per known theme) without filters.
 *
 * @return array<string, mixed>
 */
function rch_agent_wizard_get_legacy_theme_profile_raw(string $stylesheet): array
{
    $stylesheet = is_string($stylesheet) ? $stylesheet : '';

    if ($stylesheet === 'rechat-theme-one') {
        return rch_agent_wizard_profile_rechat_theme_one();
    }

    if ($stylesheet === 'rechat-theme-two') {
        return rch_agent_wizard_profile_rechat_theme_two();
    }

    return rch_agent_wizard_profile_acropolis();
}

require_once __DIR__ . '/agent-wizard-dynamic-options.php';

/**
 * Field metadata + storage: dynamic (option snapshot / manifest) when available, else legacy.
 *
 * @return array<string, mixed>
 */
function rch_agent_wizard_get_theme_profile(string $stylesheet): array
{
    $dynamic = rch_agent_wizard_try_build_dynamic_theme_profile($stylesheet);
    if (is_array($dynamic)) {
        return apply_filters('rch_agent_wizard_theme_profile', $dynamic, $stylesheet);
    }

    // If theme has no option storage and no manifest, show empty (do not fall back to Acropolis).
    $storage = rch_agent_wizard_resolve_storage_config($stylesheet);
    $disc    = function_exists('rch_agent_wizard_discover_from_themeoption_php')
        ? rch_agent_wizard_discover_from_themeoption_php($stylesheet)
        : null;
    $has_themeoption = is_array($disc) && ! empty($disc['fields']);

    $known = in_array($stylesheet, ['Acropolis-agent', 'rechat-theme-one', 'rechat-theme-two'], true);
    if (! $known && ! $has_themeoption && ($storage['primary'] ?? '') === '') {
        $empty = [
            'keys'            => [],
            'labels'          => rch_agent_wizard_wizard_only_labels(),
            'urls'            => [],
            'textareas'       => [],
            'textarea_json'   => [],
            'numbers'         => [],
            'wp_kses'         => [],
            'color'           => [],
            'image_media'     => [],
            'video_media'     => [],
            'import_defaults' => [],
            'storage_primary' => '',
            'storage_mirror'  => null,
            'dynamic'         => false,
            'stylesheet'      => $stylesheet,
        ];

        return apply_filters('rch_agent_wizard_theme_profile', $empty, $stylesheet);
    }

    $legacy = rch_agent_wizard_get_legacy_theme_profile_raw($stylesheet);
    $legacy['storage_primary'] = is_string($storage['primary'] ?? null) ? $storage['primary'] : '';
    $legacy['storage_mirror']  = $storage['mirror'] ?? null;

    return apply_filters('rch_agent_wizard_theme_profile', $legacy, $stylesheet);
}

/**
 * Allowed theme option keys for the current wizard (follows network agent default theme).
 *
 * @return list<string>
 */
function rch_agent_wizard_allowed_theme_option_keys(): array
{
    $keys = rch_agent_wizard_get_theme_profile(rch_agent_wizard_wizard_ui_stylesheet())['keys'];

    /** @var list<string> $keys */
    return apply_filters('rch_agent_wizard_allowed_theme_option_keys', $keys);
}

/**
 * Human labels for current wizard profile.
 *
 * @return array<string, string>
 */
function rch_agent_wizard_theme_key_labels(): array
{
    $labels = rch_agent_wizard_get_theme_profile(rch_agent_wizard_wizard_ui_stylesheet())['labels'];

    return apply_filters('rch_agent_wizard_theme_key_labels', $labels);
}

/**
 * Manual step: all theme keys with UI type (matches theme option panel).
 *
 * @return list<array{key:string,label:string,type:string,media:string}>
 */
function rch_agent_wizard_manual_field_defs(): array
{
    $profile = rch_agent_wizard_get_theme_profile(rch_agent_wizard_wizard_ui_stylesheet());
    $labels  = $profile['labels'];
    $rows    = [];

    foreach ($profile['keys'] as $key) {
        $type  = 'text';
        $media = '';
        if (in_array($key, $profile['textareas'], true)) {
            $type = 'textarea';
        } elseif (in_array($key, $profile['textarea_json'], true) || $key === 'rch_selected_tags') {
            $type = 'textarea_json';
        } elseif (in_array($key, $profile['urls'], true)) {
            $type = 'url';
        } elseif (in_array($key, $profile['numbers'], true) || preg_match('/^rch-counter-\d-value$/', $key)) {
            $type = 'number';
        }
        if (in_array($key, $profile['image_media'], true)) {
            $media = 'image';
        } elseif (in_array($key, $profile['video_media'], true)) {
            $media = 'video';
        }
        $rows[] = [
            'key'   => $key,
            'label' => $labels[ $key ] ?? $key,
            'type'  => $type,
            'media' => $media,
        ];
    }
    usort(
        $rows,
        static function (array $a, array $b): int {
            return strcasecmp($a['label'], $b['label']);
        }
    );

    return $rows;
}

/**
 * Importable field defs with default_theme_key adjusted for the wizard UI theme.
 *
 * @return array<string, array{label:string, default_theme_key:string}>
 */
function rch_agent_wizard_importable_field_defs_resolved(): array
{
    $defs = rch_agent_wizard_importable_field_defs();
    $map  = rch_agent_wizard_get_theme_profile(rch_agent_wizard_wizard_ui_stylesheet())['import_defaults'] ?? [];
    if (! is_array($map)) {
        return $defs;
    }
    foreach ($map as $meta_key => $theme_key) {
        if (isset($defs[ $meta_key ]) && is_string($theme_key) && $theme_key !== '') {
            $defs[ $meta_key ]['default_theme_key'] = $theme_key;
        }
    }

    return $defs;
}

/**
 * Count published agents that already have a linked sub-site.
 */
function rch_agent_wizard_count_agents_with_subsites(): int
{
    if (! function_exists('rch_multisite_get_agent_blog_id')) {
        return 0;
    }
    $ids = get_posts([
        'post_type'      => 'agents',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $n = 0;
    foreach ($ids as $aid) {
        if (rch_multisite_get_agent_blog_id((int) $aid) > 0) {
            $n++;
        }
    }

    return $n;
}

/**
 * Deploy same theme row configuration to every agent sub-site that exists.
 *
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 * @return array{updated:int, skipped:int, errors:list<string>}
 */
function rch_agent_wizard_deploy_all_agent_subsites(array $theme_rows): array
{
    $updated = 0;
    $skipped = 0;
    $errors  = [];

    $agents = get_posts([
        'post_type'      => 'agents',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($agents as $agent_id) {
        $agent_id = (int) $agent_id;
        if (! function_exists('rch_multisite_get_agent_blog_id') || ! rch_multisite_get_agent_blog_id($agent_id)) {
            $skipped++;
            continue;
        }
        $result = rch_agent_wizard_deploy_to_agent_blog($agent_id, $theme_rows);
        if (is_wp_error($result)) {
            $errors[] = sprintf(
                '%s (ID %d): %s',
                get_the_title($agent_id),
                $agent_id,
                $result->get_error_message()
            );
        } else {
            $updated++;
        }
    }

    return compact('updated', 'skipped', 'errors');
}

/**
 * Importable agent sources: meta key or synthetic key => label + default theme key (empty = choose in wizard).
 *
 * @return array<string, array{label:string, default_theme_key:string}>
 */
function rch_agent_wizard_importable_field_defs(): array
{
    $defs = [
        'post_title' => [
            'label'               => __('Agent name (post title)', 'rechat-plugin'),
            'default_theme_key'   => 'rch-theme-title-main-hero',
        ],
        'api_id' => [
            'label'               => __('Rechat / API ID', 'rechat-plugin'),
            'default_theme_key'   => 'rch-wizard-agent-api-id',
        ],
        'profile_image_url' => [
            'label'               => __('Profile image URL', 'rechat-plugin'),
            'default_theme_key'   => 'rch-theme-menu-image',
        ],
        'phone_number' => [
            'label'               => __('Phone number', 'rechat-plugin'),
            'default_theme_key'   => 'rch-theme-phone',
        ],
        'email' => [
            'label'               => __('Email', 'rechat-plugin'),
            'default_theme_key'   => 'rch-wizard-agent-email',
        ],
        'designation' => [
            'label'               => __('Designation', 'rechat-plugin'),
            'default_theme_key'   => 'rch-wizard-agent-designation',
        ],
        'last_name' => [
            'label'               => __('Last name', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'website' => [
            'label'               => __('Website URL', 'rechat-plugin'),
            'default_theme_key'   => 'rch-theme-url-listing-button',
        ],
        'instagram' => [
            'label'               => __('Instagram handle or URL', 'rechat-plugin'),
            'default_theme_key'   => 'rch-theme-instagram-url',
        ],
        'twitter' => [
            'label'               => __('Twitter / X', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'linkedin' => [
            'label'               => __('LinkedIn', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'youtube' => [
            'label'               => __('YouTube', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'facebook' => [
            'label'               => __('Facebook', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'timezone' => [
            'label'               => __('Timezone', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'license_number' => [
            'label'               => __('License number', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
        'agent_visibility' => [
            'label'               => __('Visibility (show/hide)', 'rechat-plugin'),
            'default_theme_key'   => '',
        ],
    ];

    /**
     * Filter importable agent meta / synthetic fields for the wizard.
     *
     * @param array<string, array{label:string, default_theme_key:string}> $defs
     */
    return apply_filters('rch_agent_wizard_importable_field_defs', $defs);
}

/**
 * @param mixed $value
 */
function rch_agent_wizard_normalize_social_url($value): string
{
    $s = is_string($value) ? trim($value) : '';
    if ($s === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $s)) {
        return esc_url_raw($s);
    }
    // Plain handle → Instagram-style URL guess only when path looks like handle
    if (preg_match('/^[a-z0-9._]+$/i', $s)) {
        return esc_url_raw('https://instagram.com/' . $s);
    }

    return esc_url_raw($s);
}

/**
 * Sanitize values using a theme profile (option panel field kinds).
 *
 * @param  array<string, mixed> $row
 * @param  array<string, mixed> $profile From rch_agent_wizard_get_theme_profile().
 * @return array<string, mixed>
 */
function rch_agent_wizard_sanitize_theme_options_row(array $row, ?array $profile = null): array
{
    if ($profile === null) {
        $profile = rch_agent_wizard_get_theme_profile(rch_agent_wizard_wizard_ui_stylesheet());
    }

    $allowed = array_flip($profile['keys']);
    $out     = [];

    foreach ($row as $key => $value) {
        if (! is_string($key) || ! isset($allowed[ $key ])) {
            continue;
        }

        if ($key === 'rch_selected_tags' || in_array($key, $profile['textarea_json'] ?? [], true)) {
            if (is_array($value)) {
                $out[ $key ] = array_map('sanitize_text_field', $value);
            } elseif (is_string($value)) {
                $decoded     = json_decode(wp_unslash($value), true);
                $out[ $key ] = is_array($decoded) ? array_map('sanitize_text_field', $decoded) : [];
            } else {
                $out[ $key ] = [];
            }
            continue;
        }

        if ($key === 'rch-wizard-agent-post-id') {
            $out[ $key ] = (string) absint($value);
            continue;
        }

        if ($key === 'rch-theme-talk-shortcode') {
            $out[ $key ] = wp_kses_post(wp_unslash(is_string($value) ? $value : ''));
            continue;
        }

        if (
            $key === 'rch-theme-instagram-url'
            || $key === 'rch-theme-instagram'
            || strpos(strtolower($key), 'instagram') !== false
        ) {
            $out[ $key ] = rch_agent_wizard_normalize_social_url($value);
            continue;
        }

        if (in_array($key, $profile['urls'] ?? [], true)) {
            $out[ $key ] = esc_url_raw(is_string($value) ? $value : '');
            continue;
        }

        if (in_array($key, $profile['textareas'] ?? [], true)) {
            $out[ $key ] = sanitize_textarea_field(is_string($value) ? $value : '');
            continue;
        }

        if (in_array($key, $profile['wp_kses'] ?? [], true)) {
            $out[ $key ] = wp_kses_post(is_string($value) ? $value : '');
            continue;
        }

        if (in_array($key, $profile['color'] ?? [], true)) {
            $c = sanitize_hex_color(is_string($value) ? $value : '');
            $out[ $key ] = $c ? $c : '';
            continue;
        }

        if (in_array($key, $profile['numbers'] ?? [], true) || preg_match('/^rch-counter-\d-value$/', $key)) {
            $out[ $key ] = sanitize_text_field(is_string($value) || is_numeric($value) ? (string) $value : '');
            continue;
        }

        if (in_array($key, ['rch-theme-telegram-url', 'rch-theme-whatsapp-url'], true)) {
            $out[ $key ] = sanitize_text_field(is_string($value) ? $value : '');
            continue;
        }

        $out[ $key ] = sanitize_text_field(is_string($value) ? $value : (is_scalar($value) ? (string) $value : ''));
    }

    return $out;
}

/**
 * Read agent field value for import (post title or meta).
 *
 * @return string
 */
function rch_agent_wizard_get_import_source_value(int $agent_id, string $field): string
{
    if ($field === 'post_title') {
        $post = get_post($agent_id);
        return $post ? (string) $post->post_title : '';
    }

    $raw = get_post_meta($agent_id, $field, true);

    return is_scalar($raw) ? (string) $raw : '';
}

/**
 * Build theme option patch from per-field mode (skip / manual / meta).
 *
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 * @return array<string, mixed>
 */
function rch_agent_wizard_build_row_from_theme_rows(int $agent_id, array $theme_rows): array
{
    $allowed      = array_flip(rch_agent_wizard_allowed_theme_option_keys());
    $meta_allowed = array_flip(array_keys(rch_agent_wizard_importable_field_defs()));
    $row          = [];

    $instagram_keys = ['rch-theme-instagram-url', 'rch-theme-instagram'];
    $website_keys   = ['rch-theme-url-listing-button', 'rch-theme-listing-page-link'];

    foreach ($theme_rows as $theme_key => $cfg) {
        if (! is_string($theme_key) || ! isset($allowed[ $theme_key ])) {
            continue;
        }
        if (! is_array($cfg)) {
            continue;
        }

        $mode = isset($cfg['mode']) ? sanitize_key((string) $cfg['mode']) : 'skip';
        if ($mode === 'skip' || $mode === '') {
            continue;
        }

        if ($mode === 'manual') {
            $row[ $theme_key ] = $cfg['value'] ?? '';
            continue;
        }

        if ($mode !== 'meta') {
            continue;
        }

        $meta_key = isset($cfg['meta_key']) ? sanitize_key((string) $cfg['meta_key']) : '';
        if ($meta_key === '' || ! isset($meta_allowed[ $meta_key ])) {
            continue;
        }

        $value = rch_agent_wizard_get_import_source_value($agent_id, $meta_key);

        if ($meta_key === 'instagram' && (in_array($theme_key, $instagram_keys, true) || strpos(strtolower($theme_key), 'instagram') !== false)) {
            $value = rch_agent_wizard_normalize_social_url($value);
        } elseif (
            $meta_key === 'website'
            && (
                in_array($theme_key, $website_keys, true)
                || (strpos(strtolower($theme_key), 'listing') !== false && (strpos(strtolower($theme_key), 'url') !== false || strpos(strtolower($theme_key), 'link') !== false))
            )
        ) {
            $value = esc_url_raw($value);
        }

        $row[ $theme_key ] = $value;
    }

    $row['rch-wizard-agent-post-id'] = (string) $agent_id;

    return $row;
}

/**
 * @param array<string, array{mode:string, value?:mixed, meta_key?:string}> $theme_rows
 * @return true|WP_Error
 */
function rch_agent_wizard_deploy_to_agent_blog(int $agent_id, array $theme_rows)
{
    if (! is_multisite()) {
        return new WP_Error('rch_wizard_not_multisite', __('Multisite is not enabled.', 'rechat-plugin'));
    }

    if (! current_user_can('manage_network_options')) {
        return new WP_Error('rch_wizard_cap', __('Insufficient permissions.', 'rechat-plugin'));
    }

    if (! function_exists('rch_multisite_get_agent_blog_id')) {
        return new WP_Error('rch_wizard_missing', __('Multisite helpers are not loaded.', 'rechat-plugin'));
    }

    $blog_id = rch_multisite_get_agent_blog_id($agent_id);
    if (! $blog_id) {
        return new WP_Error('rch_wizard_no_blog', __('This agent has no linked sub-site. Provision the site first.', 'rechat-plugin'));
    }

    $post = get_post($agent_id);
    if (! $post || $post->post_type !== 'agents') {
        return new WP_Error('rch_wizard_bad_agent', __('Invalid agent post.', 'rechat-plugin'));
    }

    $merged_raw = rch_agent_wizard_build_row_from_theme_rows($agent_id, $theme_rows);

    switch_to_blog($blog_id);

    $dest_stylesheet = (string) get_option('stylesheet');
    $profile         = rch_agent_wizard_get_theme_profile($dest_stylesheet);
    if (empty($profile['storage_primary']) || ! is_string($profile['storage_primary'])) {
        restore_current_blog();
        return new WP_Error(
            'rch_wizard_storage_missing',
            __('Could not detect theme option storage for this sub-site theme. Add a rechat-agent-wizard.json manifest, or configure rch_agent_wizard_storage_config / rch_agent_wizard_theme_storage_map.', 'rechat-plugin')
        );
    }
    $sanitized       = rch_agent_wizard_sanitize_theme_options_row($merged_raw, $profile);
    $dest_allowed    = array_flip($profile['keys']);
    $sanitized       = array_intersect_key($sanitized, $dest_allowed);

    $primary = isset($profile['storage_primary']) ? (string) $profile['storage_primary'] : 'pentama_options_v2';
    $mirror  = array_key_exists('storage_mirror', $profile) ? $profile['storage_mirror'] : 'pentama_options_agent_website';

    $existing = get_option($primary, []);
    if (! is_array($existing)) {
        $existing = [];
    }

    $merged = array_merge($existing, $sanitized);

    update_option($primary, $merged, false);
    if (is_string($mirror) && $mirror !== '') {
        update_option($mirror, $merged, false);
    }

    restore_current_blog();

    return true;
}

/**
 * @return bool|WP_Error
 */
function rch_agent_wizard_user_can_run()
{
    if (! is_multisite()) {
        return new WP_Error('rch_wizard_ms', __('Multisite only.', 'rechat-plugin'));
    }

    if (! current_user_can('manage_network_options')) {
        return new WP_Error('rch_wizard_cap', __('Insufficient permissions.', 'rechat-plugin'));
    }

    if (! function_exists('rch_current_user_can_manage_rechat') || ! rch_current_user_can_manage_rechat()) {
        return new WP_Error('rch_wizard_rechat', __('You do not have access to Rechat settings.', 'rechat-plugin'));
    }

    return true;
}

/**
 * AJAX: agent meta + blog id + defs for wizard bootstrap.
 */
function rch_agent_wizard_ajax_load_agent(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    $agent_id = isset($_POST['agent_id']) ? absint($_POST['agent_id']) : 0;
    $post     = $agent_id ? get_post($agent_id) : null;

    if (! $post || $post->post_type !== 'agents') {
        wp_send_json_error(['message' => __('Invalid agent.', 'rechat-plugin')]);
    }

    $defs = rch_agent_wizard_importable_field_defs_resolved();
    $meta = [];

    foreach (array_keys($defs) as $key) {
        $meta[ $key ] = rch_agent_wizard_get_import_source_value($agent_id, $key);
    }

    $blog_id = function_exists('rch_multisite_get_agent_blog_id') ? rch_multisite_get_agent_blog_id($agent_id) : 0;

    wp_send_json_success([
        'agent_id'   => $agent_id,
        'title'      => get_the_title($post),
        'blog_id'    => $blog_id,
        'meta'       => $meta,
        'defs'       => $defs,
        'theme_keys' => rch_agent_wizard_theme_key_labels(),
    ]);
}

/**
 * AJAX: save draft to user meta.
 */
function rch_agent_wizard_ajax_save_draft(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    $raw = isset($_POST['draft']) ? wp_unslash($_POST['draft']) : '';
    if (! is_string($raw) || strlen($raw) > 200000) {
        wp_send_json_error(['message' => __('Draft too large or invalid.', 'rechat-plugin')]);
    }

    $decoded = json_decode($raw, true);
    if (! is_array($decoded)) {
        wp_send_json_error(['message' => __('Draft must be JSON object.', 'rechat-plugin')]);
    }

    update_user_meta(get_current_user_id(), RCH_AGENT_WIZARD_DRAFT_META, wp_json_encode($decoded));

    wp_send_json_success(['message' => __('Draft saved.', 'rechat-plugin')]);
}

/**
 * AJAX: load draft from user meta.
 */
function rch_agent_wizard_ajax_load_draft(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => __('Permission denied.', 'rechat-plugin')], 403);
    }

    $json = get_user_meta(get_current_user_id(), RCH_AGENT_WIZARD_DRAFT_META, true);
    if (! is_string($json) || $json === '') {
        wp_send_json_success(['draft' => null]);
    }

    $decoded = json_decode($json, true);
    wp_send_json_success(['draft' => is_array($decoded) ? $decoded : null]);
}

/**
 * AJAX: deploy merged options to agent sub-site.
 */
function rch_agent_wizard_ajax_deploy(): void
{
    check_ajax_referer(RCH_AGENT_WIZARD_NONCE_ACTION, 'nonce');

    if (is_wp_error($err = rch_agent_wizard_user_can_run())) {
        wp_send_json_error(['message' => $err->get_error_message()], 403);
    }

    $agent_id = isset($_POST['agent_id']) ? absint($_POST['agent_id']) : 0;

    $theme_rows_raw = isset($_POST['theme_rows_json']) ? wp_unslash($_POST['theme_rows_json']) : '';
    if (! is_string($theme_rows_raw) || $theme_rows_raw === '') {
        wp_send_json_error(['message' => __('Missing theme configuration.', 'rechat-plugin')]);
    }
    $theme_rows = json_decode($theme_rows_raw, true);
    if (! is_array($theme_rows)) {
        wp_send_json_error(['message' => __('Invalid theme configuration JSON.', 'rechat-plugin')]);
    }

    $allowed_themes = array_flip(rch_agent_wizard_allowed_theme_option_keys());
    $allowed_meta   = array_flip(array_keys(rch_agent_wizard_importable_field_defs_resolved()));
    $clean_rows     = [];
    foreach ($theme_rows as $tk => $cfg) {
        if (! is_string($tk) || ! isset($allowed_themes[ $tk ]) || ! is_array($cfg)) {
            continue;
        }
        $mode = isset($cfg['mode']) ? sanitize_key((string) $cfg['mode']) : 'skip';
        if (! in_array($mode, ['skip', 'manual', 'meta'], true)) {
            continue;
        }
        $entry = ['mode' => $mode];
        if ($mode === 'manual') {
            $entry['value'] = $cfg['value'] ?? '';
        }
        if ($mode === 'meta') {
            $mk = isset($cfg['meta_key']) ? sanitize_key((string) $cfg['meta_key']) : '';
            if ($mk === '' || ! isset($allowed_meta[ $mk ])) {
                continue;
            }
            $entry['meta_key'] = $mk;
        }
        $clean_rows[ $tk ] = $entry;
    }

    $scope = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : 'single';
    if ($scope === 'all') {
        $bulk = rch_agent_wizard_deploy_all_agent_subsites($clean_rows);
        $msg  = sprintf(
            /* translators: 1: updated count, 2: skipped count */
            __('Updated %1$d agent sub-site(s). Skipped %2$d (no linked site).', 'rechat-plugin'),
            $bulk['updated'],
            $bulk['skipped']
        );
        wp_send_json_success([
            'message' => $msg,
            'updated' => $bulk['updated'],
            'skipped' => $bulk['skipped'],
            'errors'  => $bulk['errors'],
        ]);
        return;
    }

    if (! $agent_id) {
        wp_send_json_error(['message' => __('Select an agent or choose “All agent sub-sites”.', 'rechat-plugin')]);
    }

    $result = rch_agent_wizard_deploy_to_agent_blog($agent_id, $clean_rows);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success([
        'message' => __('Theme options were saved on the agent sub-site.', 'rechat-plugin'),
        'blog_id' => function_exists('rch_multisite_get_agent_blog_id') ? rch_multisite_get_agent_blog_id($agent_id) : 0,
    ]);
}

add_action('wp_ajax_rch_agent_wizard_load_agent', 'rch_agent_wizard_ajax_load_agent');
add_action('wp_ajax_rch_agent_wizard_save_draft', 'rch_agent_wizard_ajax_save_draft');
add_action('wp_ajax_rch_agent_wizard_load_draft', 'rch_agent_wizard_ajax_load_draft');
add_action('wp_ajax_rch_agent_wizard_deploy', 'rch_agent_wizard_ajax_deploy');

/**
 * Enqueue wizard assets on Rechat settings tab.
 *
 * @param string $hook Page hook.
 */
function rch_agent_wizard_enqueue_assets(string $hook): void
{
    if ($hook !== 'toplevel_page_rechat-setting') {
        return;
    }

    if (! is_multisite()) {
        return;
    }

    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    if ($tab !== 'agent-site-wizard') {
        return;
    }

    if (! defined('RCH_PLUGIN_URL') || ! defined('RCH_VERSION')) {
        return;
    }

    wp_enqueue_media();

    wp_enqueue_style(
        'rch-agent-site-wizard',
        RCH_PLUGIN_URL . 'assets/css/rch-agent-site-wizard.css',
        [],
        RCH_VERSION
    );

    wp_enqueue_script(
        'rch-agent-site-wizard',
        RCH_PLUGIN_URL . 'assets/js/rch-agent-site-deploy-wizard.js',
        ['jquery', 'media-upload', 'media-views'],
        RCH_VERSION,
        true
    );

    $labels = rch_agent_wizard_theme_key_labels();
    foreach (rch_agent_wizard_allowed_theme_option_keys() as $slug) {
        if (! isset($labels[ $slug ])) {
            $labels[ $slug ] = $slug;
        }
    }
    $theme_keys = [];
    foreach ($labels as $slug => $label) {
        if (! in_array($slug, rch_agent_wizard_allowed_theme_option_keys(), true)) {
            continue;
        }
        $theme_keys[] = ['slug' => $slug, 'label' => $label];
    }
    usort(
        $theme_keys,
        static function (array $a, array $b): int {
            return strcasecmp($a['label'], $b['label']);
        }
    );

    $metabox_labels = [];
    foreach (rch_agent_wizard_importable_field_defs_resolved() as $mk => $def) {
        $metabox_labels[ $mk ] = $def['label'];
    }

    $ui_ss   = rch_agent_wizard_wizard_ui_stylesheet();
    $ui_prof = rch_agent_wizard_get_theme_profile($ui_ss);
    $ui_name = wp_get_theme($ui_ss)->exists() ? wp_get_theme($ui_ss)->get('Name') : $ui_ss;
    $stor    = $ui_prof['storage_primary'] ?? 'pentama_options_v2';
    $stor_m  = isset($ui_prof['storage_mirror']) && is_string($ui_prof['storage_mirror']) ? $ui_prof['storage_mirror'] : '';

    wp_localize_script(
        'rch-agent-site-wizard',
        'rchAgentWizard',
        [
            'ajaxurl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce(RCH_AGENT_WIZARD_NONCE_ACTION),
            'themeKeys'      => $theme_keys,
            'metaboxLabels'  => $metabox_labels,
            'bulkCount'      => rch_agent_wizard_count_agents_with_subsites(),
            'targetTheme'    => [
                'stylesheet'      => $ui_ss,
                'name'            => $ui_name,
                'optionPrimary'   => $stor,
                'optionMirror'    => $stor_m,
            ],
            'strings'        => [
                'pickAgent'       => __('Select an agent and load data, or switch to “All agent sub-sites”.', 'rechat-plugin'),
                'noBlog'          => __('This agent has no sub-site yet. Provision it under the Multisite tab.', 'rechat-plugin'),
                'bulkNoSites'     => __('No agent sub-sites exist yet. Provision sites under Multisite first.', 'rechat-plugin'),
                'deployOk'        => __('Deployment finished.', 'rechat-plugin'),
                'deployFail'      => __('Deployment failed.', 'rechat-plugin'),
                'bulkPreview'     => __('Metabox-driven values differ per agent; preview uses the loaded agent only.', 'rechat-plugin'),
                'previewHeading'  => __('What will change', 'rechat-plugin'),
                'previewEmpty'    => __('Nothing to deploy yet — every theme row is set to “Do not change”. Go back and choose manual values or agent profile fields.', 'rechat-plugin'),
                'badgeManual'     => __('Manual value', 'rechat-plugin'),
                'badgeMeta'       => __('From agent profile', 'rechat-plugin'),
                'valuePreview'    => __('Preview', 'rechat-plugin'),
                'techToggle'      => __('Show technical JSON (advanced)', 'rechat-plugin'),
                'scopeSingle'     => __('Single agent', 'rechat-plugin'),
                'scopeAll'        => __('All agent sub-sites', 'rechat-plugin'),
                'sitesCount'      => /* translators: %d: count */ __('%d sites will be updated.', 'rechat-plugin'),
            ],
        ]
    );
}

add_action('admin_enqueue_scripts', 'rch_agent_wizard_enqueue_assets', 20);

/**
 * Render wizard tab (included from menu-setting).
 */
function rch_agent_wizard_render_tab(): void
{
    if (! is_multisite()) {
        return;
    }

    if (is_wp_error(rch_agent_wizard_user_can_run())) {
        echo '<p>' . esc_html__('You do not have access to this tool.', 'rechat-plugin') . '</p>';
        return;
    }

    require RCH_PLUGIN_INCLUDES . 'multisite/views/agent-site-deploy-wizard-tab.php';
}
