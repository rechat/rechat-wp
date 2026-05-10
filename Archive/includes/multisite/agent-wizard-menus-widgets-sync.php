<?php
/**
 * Agent wizard: copy nav menus + widget options from a source blog to many subsites.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! is_multisite()) {
    return;
}

/**
 * Source blog for menu/widget templates (default: same as Broadcast source / main site).
 */
function rch_agent_wizard_menus_widgets_source_blog_id(): int
{
    if (function_exists('rch_multisite_broadcast_source_blog_id')) {
        return (int) apply_filters(
            'rch_agent_wizard_menus_widgets_source_blog_id',
            rch_multisite_broadcast_source_blog_id()
        );
    }

    return (int) apply_filters('rch_agent_wizard_menus_widgets_source_blog_id', get_main_site_id());
}

/**
 * Export widget-related options from a blog (sidebars_widgets + widget_*).
 *
 * @return array<string, mixed>
 */
function rch_agent_wizard_export_widget_options(int $blog_id): array
{
    global $wpdb;

    switch_to_blog($blog_id);

    $rows = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name = 'sidebars_widgets' OR option_name LIKE 'widget\\_%'",
        ARRAY_A
    );

    restore_current_blog();

    $out = [];
    if (! is_array($rows)) {
        return $out;
    }

    foreach ($rows as $row) {
        if (! isset($row['option_name'], $row['option_value'])) {
            continue;
        }
        $name = (string) $row['option_name'];
        if ($name === '' || ! is_string($row['option_value'])) {
            continue;
        }
        $out[ $name ] = maybe_unserialize($row['option_value']);
    }

    return (array) apply_filters('rch_agent_wizard_export_widget_options', $out, $blog_id);
}

/**
 * Replace widget options on a blog.
 *
 * @param array<string, mixed> $options
 */
function rch_agent_wizard_import_widget_options(int $blog_id, array $options): void
{
    $options = apply_filters('rch_agent_wizard_import_widget_options', $options, $blog_id);
    if (! is_array($options) || $options === []) {
        return;
    }

    switch_to_blog($blog_id);

    foreach ($options as $name => $value) {
        if (! is_string($name) || $name === '') {
            continue;
        }
        if (strpos($name, 'widget_') !== 0 && $name !== 'sidebars_widgets') {
            continue;
        }
        update_option($name, $value, true);
    }

    restore_current_blog();
}

/**
 * Collect nav menu item data on source blog (flat list).
 *
 * @return WP_Post[]|false
 */
function rch_agent_wizard_get_menu_items_on_blog(int $blog_id, int $menu_term_id)
{
    switch_to_blog($blog_id);
    $items = wp_get_nav_menu_items(
        $menu_term_id,
        [
            'post_status' => 'any',
        ]
    );
    restore_current_blog();

    return is_array($items) ? $items : [];
}

/**
 * Resolve a menu item link on the source blog (used when cloning as custom links).
 */
function rch_agent_wizard_nav_item_url_on_source(WP_Post $item): string
{
    if ($item->type === 'custom') {
        return (string) $item->url;
    }

    $url = (string) $item->url;
    if ($url !== '') {
        return $url;
    }

    switch_to_blog(rch_agent_wizard_menus_widgets_source_blog_id());

    if ($item->type === 'post_type' && (int) $item->object_id > 0) {
        $url = (string) get_permalink((int) $item->object_id);
    } elseif ($item->type === 'taxonomy' && (int) $item->object_id > 0 && $item->object) {
        $link = get_term_link((int) $item->object_id, (string) $item->object);
        $url  = ! is_wp_error($link) ? (string) $link : '';
    }

    restore_current_blog();

    return $url !== '' ? $url : '#';
}

/**
 * Delete a nav menu on a blog by term_id if it exists.
 */
function rch_agent_wizard_delete_nav_menu_on_blog(int $blog_id, int $term_id): void
{
    if ($term_id <= 0) {
        return;
    }
    switch_to_blog($blog_id);
    wp_delete_nav_menu($term_id);
    restore_current_blog();
}

/**
 * Remove existing menu with same slug or name on target, then create empty menu.
 *
 * @return int|WP_Error New menu term_id.
 */
function rch_agent_wizard_recreate_empty_menu_on_blog(int $target_blog, string $name, string $slug)
{
    switch_to_blog($target_blog);

    foreach (wp_get_nav_menus() as $m) {
        if ($m->slug === $slug || $m->name === $name) {
            wp_delete_nav_menu((int) $m->term_id);
        }
    }

    $new_id = wp_create_nav_menu($name);
    restore_current_blog();

    if (is_wp_error($new_id)) {
        return $new_id;
    }

    return (int) $new_id;
}

/**
 * Clone menu items from source payload onto target menu_id; returns old_item_id => new_item_id.
 *
 * @param WP_Post[] $items
 * @return array<int, int>|WP_Error
 */
function rch_agent_wizard_clone_menu_items_on_blog(int $target_blog, int $target_menu_id, array $items)
{
    if ($target_menu_id <= 0 || $items === []) {
        return [];
    }

    switch_to_blog($target_blog);

    $by_id = [];
    foreach ($items as $it) {
        if ($it instanceof WP_Post) {
            $by_id[ (int) $it->ID ] = $it;
        }
    }

    $ids       = array_keys($by_id);
    $id_map    = [];
    $remaining = array_flip($ids);
    $max_pass  = count($ids) + 5;
    $pass      = 0;

    while ($remaining !== [] && $pass < $max_pass) {
        $pass++;
        $progress = false;

        foreach (array_keys($remaining) as $old_id) {
            $item = $by_id[ $old_id ];
            $pold = (int) $item->menu_item_parent;

            if ($pold !== 0 && ! isset($id_map[ $pold ])) {
                continue;
            }

            $pnew = $pold === 0 ? 0 : (int) $id_map[ $pold ];
            $url  = rch_agent_wizard_nav_item_url_on_source($item);

            $classes = is_array($item->classes) ? implode(' ', array_filter($item->classes)) : '';

            $args = [
                'menu-item-title'       => $item->title,
                'menu-item-description' => $item->post_content,
                'menu-item-attr-title'  => $item->post_excerpt,
                'menu-item-target'      => $item->target,
                'menu-item-classes'     => $classes,
                'menu-item-xfn'         => $item->xfn,
                'menu-item-url'         => $url !== '' ? $url : '#',
                'menu-item-status'      => $item->post_status === 'publish' ? 'publish' : 'draft',
                'menu-item-type'        => 'custom',
                'menu-item-object'      => '',
                'menu-item-object-id'   => 0,
                'menu-item-parent-id'   => $pnew,
                'menu-item-position'    => (int) $item->menu_order,
            ];

            $new_item_id = wp_update_nav_menu_item($target_menu_id, 0, $args);

            if (is_wp_error($new_item_id)) {
                restore_current_blog();

                return $new_item_id;
            }

            $id_map[ $old_id ] = (int) $new_item_id;
            unset($remaining[ $old_id ]);
            $progress = true;
        }

        if (! $progress) {
            break;
        }
    }

    restore_current_blog();

    if ($remaining !== []) {
        return new WP_Error(
            'rch_mw_menu_items',
            sprintf(
                /* translators: %d: number of menu items not cloned */
                __('Could not clone %d menu item(s) (parent order).', 'rechat-plugin'),
                count($remaining)
            )
        );
    }

    return $id_map;
}

/**
 * Merge nav_menu_locations on target using source assignments for cloned menus only.
 *
 * @param array<int, int> $source_term_to_target_term Source menu term_id => target menu term_id.
 */
function rch_agent_wizard_apply_menu_locations_on_blog(
    int $source_blog,
    int $target_blog,
    array $source_term_to_target_term
): void {
    if ($source_term_to_target_term === []) {
        return;
    }

    switch_to_blog($source_blog);
    $source_locs = get_theme_mod('nav_menu_locations', []);
    if (! is_array($source_locs)) {
        $source_locs = [];
    }
    restore_current_blog();

    switch_to_blog($target_blog);
    $target_locs = get_theme_mod('nav_menu_locations', []);
    if (! is_array($target_locs)) {
        $target_locs = [];
    }

    foreach ($source_locs as $location => $src_menu_id) {
        $src_menu_id = (int) $src_menu_id;
        if ($src_menu_id && isset($source_term_to_target_term[ $src_menu_id ])) {
            $target_locs[ (string) $location ] = (int) $source_term_to_target_term[ $src_menu_id ];
        }
    }

    set_theme_mod('nav_menu_locations', $target_locs);
    restore_current_blog();
}

/**
 * Clone one nav menu from source to target blog; returns target menu term_id or WP_Error.
 *
 * @return int|WP_Error
 */
function rch_agent_wizard_clone_nav_menu_between_blogs(int $source_blog, int $source_menu_term_id, int $target_blog)
{
    switch_to_blog($source_blog);
    $menu = wp_get_nav_menu_object($source_menu_term_id);
    restore_current_blog();

    if (! is_object($menu) || empty($menu->term_id)) {
        return new WP_Error('rch_mw_no_menu', __('Menu not found on source site.', 'rechat-plugin'));
    }

    $items = rch_agent_wizard_get_menu_items_on_blog($source_blog, $source_menu_term_id);

    $new_menu = rch_agent_wizard_recreate_empty_menu_on_blog($target_blog, (string) $menu->name, (string) $menu->slug);
    if (is_wp_error($new_menu)) {
        return $new_menu;
    }

    if ($items !== []) {
        $map = rch_agent_wizard_clone_menu_items_on_blog($target_blog, (int) $new_menu, $items);
        if (is_wp_error($map)) {
            rch_agent_wizard_delete_nav_menu_on_blog($target_blog, (int) $new_menu);

            return $map;
        }
    }

    return (int) $new_menu;
}

/**
 * Run menu + optional widget sync for one target blog.
 *
 * @param int[] $source_menu_term_ids
 * @return true|WP_Error
 */
function rch_agent_wizard_sync_menus_widgets_to_blog(
    int $source_blog,
    int $target_blog,
    array $source_menu_term_ids,
    bool $copy_widgets,
    array $widget_export
) {
    if ($target_blog <= 0 || $target_blog === $source_blog) {
        return new WP_Error('rch_mw_bad_blog', __('Invalid target blog.', 'rechat-plugin'));
    }

    $term_map = [];

    foreach ($source_menu_term_ids as $mid) {
        $mid = (int) $mid;
        if ($mid <= 0) {
            continue;
        }
        $new_id = rch_agent_wizard_clone_nav_menu_between_blogs($source_blog, $mid, $target_blog);
        if (is_wp_error($new_id)) {
            return $new_id;
        }
        $term_map[ $mid ] = (int) $new_id;
    }

    if ($term_map !== []) {
        rch_agent_wizard_apply_menu_locations_on_blog($source_blog, $target_blog, $term_map);
    }

    if ($copy_widgets && $widget_export !== []) {
        rch_agent_wizard_import_widget_options($target_blog, $widget_export);
    }

    return true;
}

/**
 * Theme-registered nav menu locations for a blog (active theme).
 *
 * @return array<int, array{slug:string,label:string}>
 */
function rch_agent_wizard_get_nav_menu_locations_catalog(int $blog_id): array
{
    switch_to_blog($blog_id);
    $regs = get_registered_nav_menus();
    restore_current_blog();

    if (! is_array($regs)) {
        $regs = [];
    }

    $out = [];
    foreach ($regs as $slug => $label) {
        $out[] = [
            'slug'  => (string) $slug,
            'label' => (string) $label,
        ];
    }

    /**
     * @param array<int, array{slug:string,label:string}> $out
     */
    return (array) apply_filters('rch_agent_wizard_nav_menu_locations_catalog', $out, $blog_id);
}

/**
 * Create a flat menu on one blog: post-type items when Broadcast child exists, else custom links.
 *
 * @param array<int, array{title:string,url:string,source_post_id?:int}> $items `source_post_id` is the post ID on the parent (template) blog used for Broadcast lookup.
 * @return int|WP_Error Nav menu term ID.
 */
function rch_agent_wizard_create_flat_custom_menu_on_blog(int $blog_id, string $menu_name, array $items)
{
    $menu_name = sanitize_text_field($menu_name);
    if ($menu_name === '') {
        return new WP_Error('rch_mb_name', __('Menu name is required.', 'rechat-plugin'));
    }

    $max_items = (int) apply_filters('rch_agent_wizard_menu_builder_max_items', 80);
    if ($max_items < 1) {
        $max_items = 80;
    }

    $parent_blog = rch_agent_wizard_menus_widgets_source_blog_id();

    $clean = [];
    foreach (array_slice($items, 0, $max_items) as $row) {
        if (! is_array($row)) {
            continue;
        }
        $title = isset($row['title']) ? sanitize_text_field((string) $row['title']) : '';
        $url   = isset($row['url']) ? esc_url_raw((string) $row['url']) : '';
        $src   = isset($row['source_post_id']) ? absint($row['source_post_id']) : 0;
        if ($title === '') {
            continue;
        }
        if ($url === '' && $src <= 0) {
            continue;
        }
        $clean[] = [
            'title'            => $title,
            'url'              => $url,
            'source_post_id'   => $src,
        ];
    }

    if ($clean === []) {
        return new WP_Error('rch_mb_items', __('Add at least one valid menu link (title and URL, or title and a broadcast-linked post).', 'rechat-plugin'));
    }

    switch_to_blog($blog_id);

    $slug = sanitize_title($menu_name);
    foreach (wp_get_nav_menus() as $m) {
        if ($m->slug === $slug || $m->name === $menu_name) {
            wp_delete_nav_menu((int) $m->term_id);
        }
    }

    $menu_id = wp_create_nav_menu($menu_name);
    if (is_wp_error($menu_id)) {
        restore_current_blog();

        return $menu_id;
    }

    $menu_id = (int) $menu_id;
    $pos     = 1;

    foreach ($clean as $row) {
        $source_pid = (int) ($row['source_post_id'] ?? 0);
        $local_id   = 0;
        if ($source_pid > 0 && function_exists('rch_multisite_broadcast_child_post_id_on_blog')) {
            $local_id = rch_multisite_broadcast_child_post_id_on_blog($parent_blog, $source_pid, $blog_id);
        }

        if ($local_id > 0) {
            $post = get_post($local_id);
            if (! $post instanceof WP_Post) {
                wp_delete_nav_menu($menu_id);
                restore_current_blog();

                return new WP_Error(
                    'rch_mb_post',
                    sprintf(
                        /* translators: %d: post ID */
                        __('Could not load post %d on this site for the menu.', 'rechat-plugin'),
                        $local_id
                    )
                );
            }

            $item_title = $row['title'] !== '' ? $row['title'] : get_the_title($post);
            $args       = [
                'menu-item-title'       => $item_title,
                'menu-item-url'         => '',
                'menu-item-status'      => 'publish',
                'menu-item-type'        => 'post_type',
                'menu-item-object'       => (string) $post->post_type,
                'menu-item-object-id'    => $local_id,
                'menu-item-parent-id'    => 0,
                'menu-item-position'     => $pos,
                'menu-item-description'  => '',
                'menu-item-attr-title'   => '',
                'menu-item-target'       => '',
                'menu-item-classes'      => '',
                'menu-item-xfn'          => '',
            ];

            $nid = wp_update_nav_menu_item($menu_id, 0, $args);
            if (is_wp_error($nid)) {
                wp_delete_nav_menu($menu_id);
                restore_current_blog();

                return $nid;
            }
            $pos++;

            continue;
        }

        if ($row['url'] === '') {
            continue;
        }

        $args = [
            'menu-item-title'       => $row['title'],
            'menu-item-url'         => $row['url'],
            'menu-item-status'      => 'publish',
            'menu-item-type'        => 'custom',
            'menu-item-object'      => '',
            'menu-item-object-id'   => 0,
            'menu-item-parent-id'   => 0,
            'menu-item-position'    => $pos,
            'menu-item-description' => '',
            'menu-item-attr-title'  => '',
            'menu-item-target'      => '',
            'menu-item-classes'     => '',
            'menu-item-xfn'         => '',
        ];

        $nid = wp_update_nav_menu_item($menu_id, 0, $args);
        if (is_wp_error($nid)) {
            wp_delete_nav_menu($menu_id);
            restore_current_blog();

            return $nid;
        }
        $pos++;
    }

    if ($pos === 1) {
        wp_delete_nav_menu($menu_id);
        restore_current_blog();

        return new WP_Error(
            'rch_mb_items',
            __('No menu items were created. Broadcast the selected posts first so each site has a matching page, or add custom URLs.', 'rechat-plugin')
        );
    }

    restore_current_blog();

    return $menu_id;
}

/**
 * Assign a menu to registered display locations on one blog (only known slugs).
 *
 * @param string[] $location_slugs
 */
function rch_agent_wizard_assign_menu_to_locations_on_blog(int $blog_id, int $menu_term_id, array $location_slugs): void
{
    if ($menu_term_id <= 0 || $location_slugs === []) {
        return;
    }

    switch_to_blog($blog_id);

    $registered = get_registered_nav_menus();
    if (! is_array($registered)) {
        $registered = [];
    }

    $locs = get_theme_mod('nav_menu_locations', []);
    if (! is_array($locs)) {
        $locs = [];
    }

    foreach ($location_slugs as $slug) {
        $slug = sanitize_key((string) $slug);
        if ($slug !== '' && isset($registered[ $slug ])) {
            $locs[ $slug ] = $menu_term_id;
        }
    }

    set_theme_mod('nav_menu_locations', $locs);
    restore_current_blog();
}

/**
 * Create the same flat menu on many blogs and assign display locations.
 *
 * @param array<int, array{title:string,url:string,source_post_id?:int}> $items
 * @param string[]                                                         $location_slugs Valid slugs on template (also checked per target).
 * @return array{ok:int,fail:int,errors:string[]}
 */
function rch_agent_wizard_push_builder_menu_to_targets(
    string $target_mode,
    string $menu_name,
    array $items,
    array $location_slugs
): array {
    $out = [
        'ok'     => 0,
        'fail'   => 0,
        'errors' => [],
    ];

    $source = rch_agent_wizard_menus_widgets_source_blog_id();
    $targets = rch_agent_wizard_broadcast_target_blog_ids($target_mode);

    $targets = array_values(
        array_filter(
            $targets,
            static function (int $bid) use ($source): bool {
                return $bid > 0 && $bid !== $source;
            }
        )
    );

    if ($targets === []) {
        $out['errors'][] = __('No target blogs found for this mode.', 'rechat-plugin');

        return $out;
    }

    $max_targets = (int) apply_filters('rch_agent_wizard_menus_widgets_max_targets', 500);
    if ($max_targets > 0 && count($targets) > $max_targets) {
        $out['errors'][] = sprintf(
            /* translators: 1: count, 2: max */
            __('Too many target sites (%1$d). Maximum is %2$d.', 'rechat-plugin'),
            count($targets),
            $max_targets
        );

        return $out;
    }

    $template_slugs = array_flip(
        array_column(rch_agent_wizard_get_nav_menu_locations_catalog($source), 'slug')
    );

    $location_slugs = array_values(
        array_unique(
            array_filter(
                array_map('sanitize_key', $location_slugs),
                static function (string $s) use ($template_slugs): bool {
                    return $s !== '' && isset($template_slugs[ $s ]);
                }
            )
        )
    );

    foreach ($targets as $blog_id) {
        switch_to_blog((int) $blog_id);
        $reg = get_registered_nav_menus();
        if (! is_array($reg)) {
            $reg = [];
        }
        restore_current_blog();

        $for_blog = [];
        foreach ($location_slugs as $slug) {
            if (isset($reg[ $slug ])) {
                $for_blog[] = $slug;
            }
        }

        $mid = rch_agent_wizard_create_flat_custom_menu_on_blog((int) $blog_id, $menu_name, $items);
        if (is_wp_error($mid)) {
            $out['fail']++;
            $out['errors'][] = sprintf(
                /* translators: 1: blog ID, 2: message */
                __('Blog %1$d: %2$s', 'rechat-plugin'),
                $blog_id,
                $mid->get_error_message()
            );
            continue;
        }

        rch_agent_wizard_assign_menu_to_locations_on_blog((int) $blog_id, (int) $mid, $for_blog);
        $out['ok']++;
    }

    return $out;
}
