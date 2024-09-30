<?php
// Template Redirect
function load_custom_templates($template)
{
    // Define the templates to use
    $agents_single_template_name = 'agents-single-custom.php'; // Template for single 'agents' posts
    $agents_archive_template_name = 'agents-archive-custom.php'; // Template for 'agents' archives

    $offices_single_template_name = 'offices-single-custom.php'; // Template for single 'offices' posts
    $offices_archive_template_name = 'offices-archive-custom.php'; // Template for 'offices' archives

    $regions_single_template_name = 'regions-single-custom.php'; // Template for single 'regions' posts
    $regions_archive_template_name = 'regions-archive-custom.php'; // Template for 'regions' archives

    // Check if the current page is a post type archive for 'agents'
    if (is_post_type_archive('agents')) {
        // Check if the archive template exists in the theme
        $theme_template = locate_template($agents_archive_template_name);
        if ($theme_template) {
            return $theme_template;
        }

        // Fall back to the plugin's template if not found in theme
        $plugin_template = RCH_PLUGIN_DIR . 'templates/archive/' . $agents_archive_template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    // Check if the current page is a post type archive for 'offices'
    if (is_post_type_archive('offices')) {
        // Check if the archive template exists in the theme
        $theme_template = locate_template($offices_archive_template_name);
        if ($theme_template) {
            return $theme_template;
        }

        // Fall back to the plugin's template if not found in theme
        $plugin_template = RCH_PLUGIN_DIR . 'templates/archive/' . $offices_archive_template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    // Check if the current page is a post type archive for 'regions'
    if (is_post_type_archive('regions')) {
        // Check if the archive template exists in the theme
        $theme_template = locate_template($regions_archive_template_name);
        if ($theme_template) {
            return $theme_template;
        }

        // Fall back to the plugin's template if not found in theme
        $plugin_template = RCH_PLUGIN_DIR . 'templates/archive/' . $regions_archive_template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    // Check if the current page is a single 'agents' post
    if (is_singular('agents')) {
        // Check if the single template exists in the theme
        $theme_template = locate_template($agents_single_template_name);
        if ($theme_template) {
            return $theme_template;
        }

        // Fall back to the plugin's template if not found in theme
        $plugin_template = RCH_PLUGIN_DIR . 'templates/single/' . $agents_single_template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    // Check if the current page is a single 'offices' post
    if (is_singular('offices')) {
        // Check if the single template exists in the theme
        $theme_template = locate_template($offices_single_template_name);
        if ($theme_template) {
            return $theme_template;
        }

        // Fall back to the plugin's template if not found in theme
        $plugin_template = RCH_PLUGIN_DIR . 'templates/single/' . $offices_single_template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    // Check if the current page is a single 'regions' post
    if (is_singular('regions')) {
        // Check if the single template exists in the theme
        $theme_template = locate_template($regions_single_template_name);
        if ($theme_template) {
            return $theme_template;
        }

        // Fall back to the plugin's template if not found in theme
        $plugin_template = RCH_PLUGIN_DIR . 'templates/single/' . $regions_single_template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    // Return the original template if no custom template is found
    return $template;
}
add_filter('template_include', 'load_custom_templates');
