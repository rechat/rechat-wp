<?php
// Register Custom Post Type
function rch_agents()
{

	$labels = array(
		'name'                  => _x('Agents', 'Post Type General Name', 'rch_agents'),
		'singular_name'         => _x('Agent', 'Post Type Singular Name', 'rch_agents'),
		'menu_name'             => __('Agents', 'rch_agents'),
		'name_admin_bar'        => __('Agents', 'rch_agents'),
		'archives'              => __('Item Archives', 'rch_agents'),
		'attributes'            => __('Item Attributes', 'rch_agents'),
		'parent_item_colon'     => __('Parent Item:', 'rch_agents'),
		'all_items'             => __('All Items', 'rch_agents'),
		'add_new_item'          => __('Add New Item', 'rch_agents'),
		'add_new'               => __('Add New Agent', 'rch_agents'),
		'new_item'              => __('New Agent', 'rch_agents'),
		'edit_item'             => __('Edit Agent', 'rch_agents'),
		'update_item'           => __('UpdateAgent', 'rch_agents'),
		'view_item'             => __('View Agent', 'rch_agents'),
		'view_items'            => __('View Agents', 'rch_agents'),
		'search_items'          => __('Search Agent', 'rch_agents'),
		'not_found'             => __('Not found', 'rch_agents'),
		'not_found_in_trash'    => __('Not found in Trash', 'rch_agents'),
		'featured_image'        => __('Featured Image of Agent', 'rch_agents'),
		'set_featured_image'    => __('Set featured image', 'rch_agents'),
		'remove_featured_image' => __('Remove featured image of Agent', 'rch_agents'),
		'use_featured_image'    => __('Use as featured image', 'rch_agents'),
		'insert_into_item'      => __('Insert into Agent', 'rch_agents'),
		'uploaded_to_this_item' => __('Uploaded to this item', 'rch_agents'),
		'items_list'            => __('Items list', 'rch_agents'),
		'items_list_navigation' => __('Items list navigation', 'rch_agents'),
		'filter_items_list'     => __('Filter items list', 'rch_agents'),
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
		'rewrite'               => array('slug' => 'agents'), // Customize slug if needed

	);
	register_post_type('agents', $args);
}
add_action('init', 'rch_agents', 0);
