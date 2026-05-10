<?php
/**
 * Agent Single Page Scripts and Enqueues
 * 
 * This file handles all script enqueuing and localization for agent single pages.
 * It is loaded directly from the plugin to ensure updates are applied even when 
 * the template file is overridden in the theme.
 * 
 * @package Rechat
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Enqueue scripts for agent single page
 * 
 * @param string $email Agent email for lead capture
 */
function rch_enqueue_agent_single_scripts($email = '') {
    // Enqueue Rechat SDK
    wp_enqueue_script(
        'rechat-sdk',
        'https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.js',
        [],
        null,
        true
    );

    // Enqueue agent single page JavaScript for lead capture form
    wp_enqueue_script(
        'rch-agent-single',
        RCH_PLUGIN_ASSETS . 'js/rch-agent-single.js',
        ['jquery', 'rechat-sdk'],
        RCH_VERSION,
        true
    );

    // Pass PHP data to JavaScript for lead capture form functionality
    wp_localize_script('rch-agent-single', 'rchAgentData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'brandId' => get_option('rch_rechat_brand_id'),
        'agentEmail' => $email,
        'leadChannel' => get_option('rch_agents_lead_channels'),
        'tags' => json_decode(get_option('rch_agents_selected_tags', '[]'), true),
    ]);
}
