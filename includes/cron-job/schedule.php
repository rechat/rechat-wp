<?php
if (! defined('ABSPATH')) {
    exit();
}

/*******************************
 * Define custom intervals with 12 hours load agents regions and offices
 ******************************/
function add_custom_cron_intervals($schedules)
{
    $schedules['every_12_hours'] = array(
        'interval' => 12 * HOUR_IN_SECONDS, // 12 hours
        'display'  => __('Every 12 Hours')
    );
    return $schedules;
}
add_filter('cron_schedules', 'add_custom_cron_intervals');

// Schedule the cron job
function schedule_agents_data_cron_job()
{
    if (!wp_next_scheduled('agents_data_cron_hook')) {
        wp_schedule_event(time(), 'every_12_hours', 'agents_data_cron_hook');
    }
}
add_action('wp', 'schedule_agents_data_cron_job');

// Define the function to run
function cron_job_function()
{
    rch_update_agents_offices_regions_data(); // Your function to fetch and process data
}
add_action('agents_data_cron_hook', 'cron_job_function');

// Clear the cron job
function clear_agents_data_cron_job()
{
    $timestamp = wp_next_scheduled('agents_data_cron_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'agents_data_cron_hook');
    }
}
register_deactivation_hook(__FILE__, 'clear_agents_data_cron_job');
