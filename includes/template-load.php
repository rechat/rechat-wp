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
        $plugin_template = RCH_PLUGIN_INCLUDES . 'load-listing/' . $house_detail_template_name;
        return $plugin_template;
    }

    return $template;
}
add_filter('template_include', 'load_custom_templates');



// function load_custom_templates($template)
// {
//     // Define custom templates for post types and listing details
//     $agents_single_template_name = 'agents-single-custom.php';
//     $agents_archive_template_name = 'agents-archive-custom.php';

//     $offices_single_template_name = 'offices-single-custom.php';
//     $offices_archive_template_name = 'offices-archive-custom.php';

//     $regions_single_template_name = 'regions-single-custom.php';
//     $regions_archive_template_name = 'regions-archive-custom.php';

//     $house_detail_template_name = 'fetch-single-listing.php';
//     // Redirect logic for 'agents' post type archive
//     if (is_post_type_archive('agents')) {
//         $theme_template = locate_template('rechat/' . $agents_archive_template_name);
//         if ($theme_template) {
//             return $theme_template;
//         }
//         $plugin_template = RCH_PLUGIN_DIR . 'templates/archive/' . $agents_archive_template_name;
//         if (file_exists($plugin_template)) {
//             return $plugin_template;
//         }
//     }

//     // Redirect logic for 'offices' post type archive
//     if (is_post_type_archive('offices')) {
//         $theme_template = locate_template('rechat/' . $offices_archive_template_name);
//         if ($theme_template) {
//             return $theme_template;
//         }
//         $plugin_template = RCH_PLUGIN_DIR . 'templates/archive/' . $offices_archive_template_name;
//         if (file_exists($plugin_template)) {
//             return $plugin_template;
//         }
//     }

//     // Redirect logic for 'regions' post type archive
//     if (is_post_type_archive('regions')) {
//         $theme_template = locate_template('rechat/' . $regions_archive_template_name);
//         if ($theme_template) {
//             return $theme_template;
//         }
//         $plugin_template = RCH_PLUGIN_DIR . 'templates/archive/' . $regions_archive_template_name;
//         if (file_exists($plugin_template)) {
//             return $plugin_template;
//         }
//     }

//     // Redirect logic for single 'agents' posts
//     if (is_singular('agents')) {
//         $theme_template = locate_template('rechat/' . $agents_single_template_name);
//         if ($theme_template) {
//             return $theme_template;
//         }
//         $plugin_template = RCH_PLUGIN_DIR . 'templates/single/' . $agents_single_template_name;
//         if (file_exists($plugin_template)) {
//             return $plugin_template;
//         }
//     }

//     // Redirect logic for single 'offices' posts
//     if (is_singular('offices')) {
//         $theme_template = locate_template('rechat/' . $offices_single_template_name);
//         if ($theme_template) {
//             return $theme_template;
//         }
//         $plugin_template = RCH_PLUGIN_DIR . 'templates/single/' . $offices_single_template_name;
//         if (file_exists($plugin_template)) {
//             return $plugin_template;
//         }
//     }

//     // Redirect logic for single 'regions' posts
//     if (is_singular('regions')) {
//         $theme_template = locate_template('rechat/' . $regions_single_template_name);
//         if ($theme_template) {
//             return $theme_template;
//         }
//         $plugin_template = RCH_PLUGIN_DIR . 'templates/single/' . $regions_single_template_name;
//         if (file_exists($plugin_template)) {
//             return $plugin_template;
//         }
//     }

//     // listing detail logic
//     if (isset($_GET['listing_id'])) {
//         $plugin_template = RCH_PLUGIN_INCLUDES . 'load-listing/' . $house_detail_template_name;
//         return $plugin_template;
//         /*******************************
//          * Note: In this section i logic and in load-listing/fetch-single-listing.php define the template
//          ******************************/
//     }
//     return $template;
// }
// add_filter('template_include', 'load_custom_templates');
