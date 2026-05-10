<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
/*******************************
 * Define custom cron intervals
 ******************************/
function rch_add_custom_cron_intervals($schedules)
{
    // Validate input
    if (!is_array($schedules)) {
        $schedules = [];
    }

    // Add 12-hour interval if not already defined
    if (!isset($schedules[RCH_CRON_INTERVAL])) {
        $schedules[RCH_CRON_INTERVAL] = [
            'interval' => RCH_CRON_INTERVAL_SECONDS,
            'display'  => __('Every 12 Hours', 'rechat-plugin'),
        ];
    }

    return $schedules;
}
add_filter('cron_schedules', 'rch_add_custom_cron_intervals');

/*******************************
 * Schedule the cron job for syncing all Rechat data
 * Syncs: Agents, Offices, Regions, Branding, and more
 ******************************/
function rch_schedule_data_sync_cron_job()
{
    // Only schedule if not already scheduled
    if (!wp_next_scheduled(RCH_CRON_HOOK)) {
        $scheduled = wp_schedule_event(time(), RCH_CRON_INTERVAL, RCH_CRON_HOOK);
        
        // Log if scheduling fails
        if ($scheduled === false) {
            error_log('Rechat Plugin: Failed to schedule data sync cron job - ' . RCH_CRON_HOOK);
        }
    }
}
add_action('wp', 'rch_schedule_data_sync_cron_job');

/*******************************
 * Execute the cron job function
 * Syncs all data from Rechat API: Agents, Offices, Regions, Branding
 ******************************/
function rch_execute_data_sync_cron_job()
{
    // Verify the function exists before calling
    if (!function_exists('rch_update_agents_offices_regions_data')) {
        error_log('Rechat Plugin: rch_update_agents_offices_regions_data function not found');
        return;
    }

    // Log cron execution
    error_log('Rechat Plugin: Starting scheduled data sync (Agents, Offices, Regions, Branding)');

    // Execute the data sync
    try {
        $result = rch_update_agents_offices_regions_data();
        
        // Log the result
        if (is_array($result) && isset($result['success'])) {
            if ($result['success']) {
                error_log('Rechat Plugin: Scheduled data sync completed successfully');
            } else {
                $message = isset($result['message']) ? $result['message'] : 'Unknown error';
                error_log('Rechat Plugin: Scheduled data sync failed - ' . $message);
            }
        }
    } catch (Exception $e) {
        error_log('Rechat Plugin: Cron job exception - ' . $e->getMessage());
    }
}
add_action(RCH_CRON_HOOK, 'rch_execute_data_sync_cron_job');

/*******************************
 * Clear the cron job on plugin deactivation
 ******************************/
function rch_clear_data_sync_cron_job()
{
    // Get the next scheduled event
    $timestamp = wp_next_scheduled(RCH_CRON_HOOK);
    
    if ($timestamp) {
        $unscheduled = wp_unschedule_event($timestamp, RCH_CRON_HOOK);
        
        // Log if unscheduling fails
        if ($unscheduled === false) {
            error_log('Rechat Plugin: Failed to unschedule data sync cron job - ' . RCH_CRON_HOOK);
        } else {
            error_log('Rechat Plugin: Data sync cron job unscheduled successfully');
        }
    }

    // Clear all scheduled events for this hook (cleanup)
    wp_clear_scheduled_hook(RCH_CRON_HOOK);
}

/*******************************
 * Register deactivation hook
 * Note: This should ideally be called from the main plugin file
 * with the main plugin file path, not __FILE__
 ******************************/
if (defined('RCH_PLUGIN_DIR')) {
    register_deactivation_hook(RCH_PLUGIN_DIR . 'index.php', 'rch_clear_data_sync_cron_job');
}

/*******************************
 * Check cron job status (for debugging)
 * Returns status of data sync cron job
 ******************************/
function rch_get_data_sync_cron_status()
{
    $next_run = wp_next_scheduled(RCH_CRON_HOOK);
    
    return [
        'hook' => RCH_CRON_HOOK,
        'interval' => RCH_CRON_INTERVAL,
        'is_scheduled' => (bool) $next_run,
        'next_run' => $next_run ? date('Y-m-d H:i:s', $next_run) : null,
        'next_run_timestamp' => $next_run,
        'time_until_next_run' => $next_run ? human_time_diff(time(), $next_run) : null,
        'syncs' => ['Agents', 'Offices', 'Regions', 'Branding'],
    ];
}

/*******************************
 * Manual trigger for data sync cron job (for testing)
 * Only available for administrators
 * Syncs all Rechat data: Agents, Offices, Regions, Branding
 ******************************/
function rch_manual_trigger_data_sync_cron()
{
    // Verify nonce
    if (!isset($_POST['rch_manual_cron_nonce']) || 
        !wp_verify_nonce($_POST['rch_manual_cron_nonce'], 'rch_manual_cron_trigger')) {
        wp_send_json_error(__('Security check failed.', 'rechat-plugin'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Insufficient permissions.', 'rechat-plugin'));
        return;
    }

    // Execute the cron job
    rch_execute_data_sync_cron_job();

    wp_send_json_success([
        'message' => __('Data sync cron job executed successfully.', 'rechat-plugin'),
        'status' => rch_get_data_sync_cron_status(),
    ]);
}
add_action('wp_ajax_rch_manual_trigger_cron', 'rch_manual_trigger_data_sync_cron');
