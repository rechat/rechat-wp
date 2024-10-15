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
        'name'                  => _x('Agents', 'Post Type General Name', 'rch_agents'),
        'singular_name'         => _x('Agent', 'Post Type Singular Name', 'rch_agents'),
        'menu_name'             => __('Agents', 'rch_agents'),
        'name_admin_bar'        => __('Agents', 'rch_agents'),
        'archives'              => __('Agent Archives', 'rch_agents'),
        'attributes'            => __('Agent Attributes', 'rch_agents'),
        'parent_item_colon'     => __('Parent Agent:', 'rch_agents'),
        'all_items'             => __('All Agents', 'rch_agents'),
        'add_new_item'          => __('Add New Agent', 'rch_agents'),
        'add_new'               => __('Add New', 'rch_agents'),
        'new_item'              => __('New Agent', 'rch_agents'),
        'edit_item'             => __('Edit Agent', 'rch_agents'),
        'update_item'           => __('Update Agent', 'rch_agents'),
        'view_item'             => __('View Agent', 'rch_agents'),
        'view_items'            => __('View Agents', 'rch_agents'),
        'search_items'          => __('Search Agents', 'rch_agents'),
        'not_found'             => __('Not found', 'rch_agents'),
        'not_found_in_trash'    => __('Not found in Trash', 'rch_agents'),
        'featured_image'        => __('Featured Image of Agent', 'rch_agents'),
        'set_featured_image'    => __('Set featured image', 'rch_agents'),
        'remove_featured_image' => __('Remove featured image', 'rch_agents'),
        'use_featured_image'    => __('Use as featured image', 'rch_agents'),
        'insert_into_item'      => __('Insert into Agent', 'rch_agents'),
        'uploaded_to_this_item' => __('Uploaded to this Agent', 'rch_agents'),
        'items_list'            => __('Agents list', 'rch_agents'),
        'items_list_navigation' => __('Agents list navigation', 'rch_agents'),
        'filter_items_list'     => __('Filter Agents list', 'rch_agents'),
    );
    $args = array(
        'label'                 => __('Agent', 'rch_agents'),
        'description'           => __('Add Your Agents Here', 'rch_agents'),
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
        'name'                  => _x('Offices', 'Post Type General Name', 'rch_offices'),
        'singular_name'         => _x('Office', 'Post Type Singular Name', 'rch_offices'),
        'menu_name'             => __('Offices', 'rch_offices'),
        'name_admin_bar'        => __('Offices', 'rch_offices'),
        'archives'              => __('Office Archives', 'rch_offices'),
        'attributes'            => __('Office Attributes', 'rch_offices'),
        'parent_item_colon'     => __('Parent Office:', 'rch_offices'),
        'all_items'             => __('All Offices', 'rch_offices'),
        'add_new_item'          => __('Add New Office', 'rch_offices'),
        'add_new'               => __('Add New', 'rch_offices'),
        'new_item'              => __('New Office', 'rch_offices'),
        'edit_item'             => __('Edit Office', 'rch_offices'),
        'update_item'           => __('Update Office', 'rch_offices'),
        'view_item'             => __('View Office', 'rch_offices'),
        'view_items'            => __('View Offices', 'rch_offices'),
        'search_items'          => __('Search Offices', 'rch_offices'),
        'not_found'             => __('Not found', 'rch_offices'),
        'not_found_in_trash'    => __('Not found in Trash', 'rch_offices'),
        'featured_image'        => __('Featured Image of Office', 'rch_offices'),
        'set_featured_image'    => __('Set featured image', 'rch_offices'),
        'remove_featured_image' => __('Remove featured image', 'rch_offices'),
        'use_featured_image'    => __('Use as featured image', 'rch_offices'),
        'insert_into_item'      => __('Insert into Office', 'rch_offices'),
        'uploaded_to_this_item' => __('Uploaded to this Office', 'rch_offices'),
        'items_list'            => __('Offices list', 'rch_offices'),
        'items_list_navigation' => __('Offices list navigation', 'rch_offices'),
        'filter_items_list'     => __('Filter Offices list', 'rch_offices'),
    );
    $args = array(
        'label'                 => __('Office', 'rch_offices'),
        'description'           => __('Add Your Offices Here', 'rch_offices'),
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
        'name'                  => _x('Regions', 'Post Type General Name', 'rch_regions'),
        'singular_name'         => _x('Region', 'Post Type Singular Name', 'rch_regions'),
        'menu_name'             => __('Regions', 'rch_regions'),
        'name_admin_bar'        => __('Regions', 'rch_regions'),
        'archives'              => __('Region Archives', 'rch_regions'),
        'attributes'            => __('Region Attributes', 'rch_regions'),
        'parent_item_colon'     => __('Parent Region:', 'rch_regions'),
        'all_items'             => __('All Regions', 'rch_regions'),
        'add_new_item'          => __('Add New Region', 'rch_regions'),
        'add_new'               => __('Add New', 'rch_regions'),
        'new_item'              => __('New Region', 'rch_regions'),
        'edit_item'             => __('Edit Region', 'rch_regions'),
        'update_item'           => __('Update Region', 'rch_regions'),
        'view_item'             => __('View Region', 'rch_regions'),
        'view_items'            => __('View Regions', 'rch_regions'),
        'search_items'          => __('Search Regions', 'rch_regions'),
        'not_found'             => __('Not found', 'rch_regions'),
        'not_found_in_trash'    => __('Not found in Trash', 'rch_regions'),
        'featured_image'        => __('Featured Image of Region', 'rch_regions'),
        'set_featured_image'    => __('Set featured image', 'rch_regions'),
        'remove_featured_image' => __('Remove featured image', 'rch_regions'),
        'use_featured_image'    => __('Use as featured image', 'rch_regions'),
        'insert_into_item'      => __('Insert into Region', 'rch_regions'),
        'uploaded_to_this_item' => __('Uploaded to this Region', 'rch_regions'),
        'items_list'            => __('Regions list', 'rch_regions'),
        'items_list_navigation' => __('Regions list navigation', 'rch_regions'),
        'filter_items_list'     => __('Filter Regions list', 'rch_regions'),
    );
    $args = array(
        'label'                 => __('Region', 'rch_regions'),
        'description'           => __('Add Your Regions Here', 'rch_regions'),
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
        'capability_type'       => 'page',
        'rewrite'               => array('slug' => 'regions'),
    );
    register_post_type('regions', $args);
}

// Hook into the 'init' action to register the custom post types
add_action('init', 'rch_agents', 0);
add_action('init', 'rch_offices', 0);
add_action('init', 'rch_regions', 0);
