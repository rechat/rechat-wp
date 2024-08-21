<?php
// Template Redirect
function load_custom_templates($template)
{
    // Define the templates to use
    $single_template_name = 'agents-single-custom.php'; // Template for single posts
    $archive_template_name = 'agents-archive-custom.php'; // Template for archives

    // Check if the current page is a post type archive for 'agents'
    if (is_post_type_archive('agents')) {
        // Check if the archive template exists in the theme
        $theme_template = locate_template($archive_template_name);
        if ($theme_template) {
            return $theme_template;
        }

        // Fall back to the plugin's template if not found in theme
        $plugin_template = RCH_PLUGIN_DIR . 'templates/archive/' . $archive_template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    // Check if the current page is a single 'agents' post
    if (is_singular('agents')) {
        // Check if the single template exists in the theme
        $theme_template = locate_template($single_template_name);
        if ($theme_template) {
            return $theme_template;
        }

        // Fall back to the plugin's template if not found in theme
        $plugin_template = RCH_PLUGIN_DIR . 'templates/single/' . $single_template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    // Return the original template if no custom template is found
    return $template;
}
add_filter('template_include', 'load_custom_templates');
