<?php
/**
 * Region Metabox Management
 * 
 * Handles custom fields for the 'regions' post type including
 * region ID from Rechat API
 *
 * @package RechatPlugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*******************************
 * Add meta box for the 'regions' post type
 ******************************/
function rch_add_region_meta_box()
{
    add_meta_box(
        'rch_region_meta_box',
        __('Region Details', 'rechat-plugin'),
        'rch_display_region_meta_box',
        'regions',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'rch_add_region_meta_box');

/*******************************
 * Display the meta box for 'regions' post type
 ******************************/
function rch_display_region_meta_box($post)
{
    // Add nonce for security
    wp_nonce_field('rch_region_meta_box', 'rch_region_meta_box_nonce');

    // Get region meta fields
    $region_id = get_post_meta($post->ID, 'region_id', true);

    // Add inline styles to override theme styles
    rch_add_region_metabox_inline_styles();

    // Render field
    rch_render_region_id_field($region_id);
}

/*******************************
 * Add inline styles to ensure WordPress default input styling
 ******************************/
function rch_add_region_metabox_inline_styles()
{
    ?>
    <style>
        #rch_region_meta_box .rch-region-field {
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 3px 5px !important;
            line-height: 2 !important;
            min-height: 30px !important;
            max-width: 100% !important;
            width: 100% !important;
            box-shadow: 0 0 0 transparent !important;
            border-radius: 4px !important;
            border: 1px solid #8c8f94 !important;
            background-color: #fff !important;
            color: #2c3338 !important;
            font-size: 14px !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif !important;
        }
        
        #rch_region_meta_box .rch-region-field:focus {
            border-color: #2271b1 !important;
            box-shadow: 0 0 0 1px #2271b1 !important;
            outline: 2px solid transparent !important;
        }
        
        #rch_region_meta_box .rch-region-field[readonly] {
            background-color: #f6f7f7 !important;
            cursor: default !important;
        }
        
        #rch_region_meta_box .rch-field-wrapper {
            margin-bottom: 15px !important;
        }
        
        #rch_region_meta_box .rch-field-label {
            display: block !important;
            margin-bottom: 5px !important;
            font-weight: 600 !important;
            color: #1d2327 !important;
            font-size: 14px !important;
        }
        
        #rch_region_meta_box .rch-field-label em {
            font-weight: normal !important;
            color: #646970 !important;
        }
    </style>
    <?php
}

/*******************************
 * Render region ID field (readonly)
 ******************************/
function rch_render_region_id_field($region_id)
{
    ?>
    <div class="rch-field-wrapper">
        <label for="rch_region_id" class="rch-field-label">
            <strong><?php esc_html_e('Region ID', 'rechat-plugin'); ?></strong>
            <em><?php esc_html_e('(not available for locally added regions)', 'rechat-plugin'); ?></em>
        </label>
        <input 
            type="text" 
            id="rch_region_id" 
            name="rch_region_id" 
            value="<?php echo esc_attr($region_id); ?>" 
            class="rch-region-field" 
            readonly 
        />
    </div>
    <?php
}

/*******************************
 * Save region meta box data
 ******************************/
function rch_save_region_meta_box($post_id)
{
    // Verify nonce
    if (!isset($_POST['rch_region_meta_box_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['rch_region_meta_box_nonce'], 'rch_region_meta_box')) {
        return;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Region ID is readonly and managed by API sync
    // No manual saving required
}
add_action('save_post_regions', 'rch_save_region_meta_box');


/*******************************
 * Add Region ID column to regions post list
 ******************************/
function rch_add_region_id_column($columns)
{
    // Insert Region ID column after title
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'title') {
            $new_columns['rch_region_id'] = __('Region ID', 'rechat-plugin');
        }
    }
    
    return $new_columns;
}
add_filter('manage_regions_posts_columns', 'rch_add_region_id_column');

/*******************************
 * Display Region ID column data
 ******************************/
function rch_show_region_id_column($column, $post_id)
{
    if ($column === 'rch_region_id') {
        $region_id = get_post_meta($post_id, 'region_id', true);
        
        if (!empty($region_id)) {
            echo esc_html($region_id);
        } else {
            echo '<em>' . esc_html__('Local Region', 'rechat-plugin') . '</em>';
        }
    }
}
add_action('manage_regions_posts_custom_column', 'rch_show_region_id_column', 10, 2);

/*******************************
 * Make Region ID column sortable
 ******************************/
function rch_make_region_id_sortable($columns)
{
    $columns['rch_region_id'] = 'region_id';
    return $columns;
}
add_filter('manage_edit-regions_sortable_columns', 'rch_make_region_id_sortable');

/*******************************
 * Handle sorting by Region ID
 ******************************/
function rch_regions_orderby($query)
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');
    
    if ($orderby === 'region_id') {
        $query->set('meta_key', 'region_id');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'rch_regions_orderby');

/*******************************
 * Register region meta fields
 ******************************/
function rch_register_region_meta()
{
    register_meta('post', 'region_id', [
        'type' => 'string',
        'description' => __('Region ID from Rechat API', 'rechat-plugin'),
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);
}
add_action('init', 'rch_register_region_meta');

