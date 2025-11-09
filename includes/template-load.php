<?php
if (! defined('ABSPATH')) {
    exit();
}

// Template redirect logic for custom post types and listing details
function load_custom_templates($template)
{
    // Define custom templates for post types and listing details
    $agents_single_template_name = 'agents-single-custom.php';
    $agents_archive_template_name = 'agents-archive-custom.php';

    $offices_single_template_name = 'offices-single-custom.php';
    $offices_archive_template_name = 'offices-archive-custom.php';

    $regions_single_template_name = 'regions-single-custom.php';
    $regions_archive_template_name = 'regions-archive-custom.php';

    $neighborhoods_single_template_name = 'neighborhoods-single-custom.php';
    $neighborhoods_archive_template_name = 'neighborhoods-archive-custom.php';

    $house_detail_template_name = 'fetch-single-listing.php';



    // Redirect logic for archive pages
    $archives = [
        'agents' => $agents_archive_template_name,
        'offices' => $offices_archive_template_name,
        'regions' => $regions_archive_template_name,
        'neighborhoods' => $neighborhoods_archive_template_name
    ];

    foreach ($archives as $post_type => $file_name) {
        if (is_post_type_archive($post_type)) {
            $custom_template = get_custom_template('rechat/' . $file_name, RCH_PLUGIN_DIR . 'templates/archive/' . $file_name);
            if ($custom_template) return $custom_template;
        }
    }

    // Redirect logic for single post types
    $singles = [
        'agents' => $agents_single_template_name,
        'offices' => $offices_single_template_name,
        'regions' => $regions_single_template_name,
        'neighborhoods' => $neighborhoods_single_template_name
    ];

    foreach ($singles as $post_type => $file_name) {
        if (is_singular($post_type)) {
            $custom_template = get_custom_template('rechat/' . $file_name, RCH_PLUGIN_DIR . 'templates/single/' . $file_name);
            if ($custom_template) return $custom_template;
        }
    }

    // Listing detail logic
    if (isset($_GET['listing_id'])) {
        add_filter('body_class', function ($classes) {
            $classes[] = 'listing-single-page';
            return $classes;
        });
        $plugin_template = RCH_PLUGIN_INCLUDES . 'load-listing/' . $house_detail_template_name;
        return $plugin_template;
    }

    return $template;
}
add_filter('template_include', 'load_custom_templates');
