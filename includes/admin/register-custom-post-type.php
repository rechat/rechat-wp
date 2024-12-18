<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Register Custom Post Type for Agents
 ******************************/
function rch_agents()
{
    $labels = array(
        'name'                  => _x('Agents', 'Post Type General Name', 'rechat-plugin'),
        'singular_name'         => _x('Agent', 'Post Type Singular Name', 'rechat-plugin'),
        'menu_name'             => __('Agents', 'rechat-plugin'),
        'name_admin_bar'        => __('Agents', 'rechat-plugin'),
        'archives'              => __('Agent Archives', 'rechat-plugin'),
        'attributes'            => __('Agent Attributes', 'rechat-plugin'),
        'parent_item_colon'     => __('Parent Agent:', 'rechat-plugin'),
        'all_items'             => __('All Agents', 'rechat-plugin'),
        'add_new_item'          => __('Add New Agent', 'rechat-plugin'),
        'add_new'               => __('Add New', 'rechat-plugin'),
        'new_item'              => __('New Agent', 'rechat-plugin'),
        'edit_item'             => __('Edit Agent', 'rechat-plugin'),
        'update_item'           => __('Update Agent', 'rechat-plugin'),
        'view_item'             => __('View Agent', 'rechat-plugin'),
        'view_items'            => __('View Agents', 'rechat-plugin'),
        'search_items'          => __('Search Agents', 'rechat-plugin'),
        'not_found'             => __('Not found', 'rechat-plugin'),
        'not_found_in_trash'    => __('Not found in Trash', 'rechat-plugin'),
        'featured_image'        => __('Featured Image of Agent', 'rechat-plugin'),
        'set_featured_image'    => __('Set featured image', 'rechat-plugin'),
        'remove_featured_image' => __('Remove featured image', 'rechat-plugin'),
        'use_featured_image'    => __('Use as featured image', 'rechat-plugin'),
        'insert_into_item'      => __('Insert into Agent', 'rechat-plugin'),
        'uploaded_to_this_item' => __('Uploaded to this Agent', 'rechat-plugin'),
        'items_list'            => __('Agents list', 'rechat-plugin'),
        'items_list_navigation' => __('Agents list navigation', 'rechat-plugin'),
        'filter_items_list'     => __('Filter Agents list', 'rechat-plugin'),
    );
    $args = array(
        'label'                 => __('Agent', 'rechat-plugin'),
        'description'           => __('Add Your Agents Here', 'rechat-plugin'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor', 'thumbnail', 'page-attributes'),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-admin-users',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'show_in_rest'          => true,
        'capability_type'       => 'page',
        'rewrite'               => array('slug' => 'agents'),
    );
    register_post_type('agents', $args);
}

/*******************************
 * Register Custom Post Type for offices
 ******************************/
function rch_offices()
{
    $labels = array(
        'name'                  => _x('Offices', 'Post Type General Name', 'rechat-plugin'),
        'singular_name'         => _x('Office', 'Post Type Singular Name', 'rechat-plugin'),
        'menu_name'             => __('Offices', 'rechat-plugin'),
        'name_admin_bar'        => __('Offices', 'rechat-plugin'),
        'archives'              => __('Office Archives', 'rechat-plugin'),
        'attributes'            => __('Office Attributes', 'rechat-plugin'),
        'parent_item_colon'     => __('Parent Office:', 'rechat-plugin'),
        'all_items'             => __('All Offices', 'rechat-plugin'),
        'add_new_item'          => __('Add New Office', 'rechat-plugin'),
        'add_new'               => __('Add New', 'rechat-plugin'),
        'new_item'              => __('New Office', 'rechat-plugin'),
        'edit_item'             => __('Edit Office', 'rechat-plugin'),
        'update_item'           => __('Update Office', 'rechat-plugin'),
        'view_item'             => __('View Office', 'rechat-plugin'),
        'view_items'            => __('View Offices', 'rechat-plugin'),
        'search_items'          => __('Search Offices', 'rechat-plugin'),
        'not_found'             => __('Not found', 'rechat-plugin'),
        'not_found_in_trash'    => __('Not found in Trash', 'rechat-plugin'),
        'featured_image'        => __('Featured Image of Office', 'rechat-plugin'),
        'set_featured_image'    => __('Set featured image', 'rechat-plugin'),
        'remove_featured_image' => __('Remove featured image', 'rechat-plugin'),
        'use_featured_image'    => __('Use as featured image', 'rechat-plugin'),
        'insert_into_item'      => __('Insert into Office', 'rechat-plugin'),
        'uploaded_to_this_item' => __('Uploaded to this Office', 'rechat-plugin'),
        'items_list'            => __('Offices list', 'rechat-plugin'),
        'items_list_navigation' => __('Offices list navigation', 'rechat-plugin'),
        'filter_items_list'     => __('Filter Offices list', 'rechat-plugin'),
    );
    $args = array(
        'label'                 => __('Office', 'rechat-plugin'),
        'description'           => __('Add Your Offices Here', 'rechat-plugin'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor', 'thumbnail', 'page-attributes'),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true, // Changed to true to appear in the top-level menu
        'menu_position'         => 6,    // Position in the admin menu
        'menu_icon'             => 'dashicons-building', // Custom icon for Offices
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'show_in_rest'          => true,
        'capability_type'       => 'page',
        'rewrite'               => array('slug' => 'offices'),
    );
    register_post_type('offices', $args);
}

/*******************************
 * Register Custom Post Type for Regions
 ******************************/
function rch_regions()
{
    $labels = array(
        'name'                  => _x('Regions', 'Post Type General Name', 'rechat-plugin'),
        'singular_name'         => _x('Region', 'Post Type Singular Name', 'rechat-plugin'),
        'menu_name'             => __('Regions', 'rechat-plugin'),
        'name_admin_bar'        => __('Regions', 'rechat-plugin'),
        'archives'              => __('Region Archives', 'rechat-plugin'),
        'attributes'            => __('Region Attributes', 'rechat-plugin'),
        'parent_item_colon'     => __('Parent Region:', 'rechat-plugin'),
        'all_items'             => __('All Regions', 'rechat-plugin'),
        'add_new_item'          => __('Add New Region', 'rechat-plugin'),
        'add_new'               => __('Add New', 'rechat-plugin'),
        'new_item'              => __('New Region', 'rechat-plugin'),
        'edit_item'             => __('Edit Region', 'rechat-plugin'),
        'update_item'           => __('Update Region', 'rechat-plugin'),
        'view_item'             => __('View Region', 'rechat-plugin'),
        'view_items'            => __('View Regions', 'rechat-plugin'),
        'search_items'          => __('Search Regions', 'rechat-plugin'),
        'not_found'             => __('Not found', 'rechat-plugin'),
        'not_found_in_trash'    => __('Not found in Trash', 'rechat-plugin'),
        'featured_image'        => __('Featured Image of Region', 'rechat-plugin'),
        'set_featured_image'    => __('Set featured image', 'rechat-plugin'),
        'remove_featured_image' => __('Remove featured image', 'rechat-plugin'),
        'use_featured_image'    => __('Use as featured image', 'rechat-plugin'),
        'insert_into_item'      => __('Insert into Region', 'rechat-plugin'),
        'uploaded_to_this_item' => __('Uploaded to this Region', 'rechat-plugin'),
        'items_list'            => __('Regions list', 'rechat-plugin'),
        'items_list_navigation' => __('Regions list navigation', 'rechat-plugin'),
        'filter_items_list'     => __('Filter Regions list', 'rechat-plugin'),
    );
    $args = array(
        'label'                 => __('Region', 'rechat-plugin'),
        'description'           => __('Add Your Regions Here', 'rechat-plugin'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor', 'thumbnail', 'page-attributes'),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true, // Changed to true to appear in the top-level menu
        'menu_position'         => 7,    // Position in the admin menu
        'menu_icon'             => 'dashicons-location-alt', // Custom icon for Regions
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'show_in_rest'          => true,
        'capability_type'       => 'page',
        'rewrite'               => array('slug' => 'regions'),
    );
    register_post_type('regions', $args);
}

// Hook into the 'init' action to register the custom post types
add_action('init', 'rch_agents', 0);
add_action('init', 'rch_offices', 0);
add_action('init', 'rch_regions', 0);

