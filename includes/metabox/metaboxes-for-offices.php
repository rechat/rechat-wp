<?php
/**
 * Office Metabox Management
 * 
 * Handles custom fields for the 'offices' post type including
 * office ID and address information
 *
 * @package RechatPlugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*******************************
 * Add meta box for the 'offices' post type
 ******************************/
function rch_add_office_meta_box()
{
    add_meta_box(
        'rch_office_meta_box',
        __('Office Details', 'rechat-plugin'),
        'rch_display_office_meta_box',
        'offices',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'rch_add_office_meta_box');


/*******************************
 * Display the meta box for 'offices' post type
 ******************************/
function rch_display_office_meta_box($post)
{
    // Add nonce for security
    wp_nonce_field('rch_office_meta_box', 'rch_office_meta_box_nonce');

    // Get office meta fields
    $office_id = get_post_meta($post->ID, 'office_id', true);
    $office_address = get_post_meta($post->ID, 'office_address', true);

    // Add inline styles to override theme styles
    rch_add_office_metabox_inline_styles();

    // Render fields
    rch_render_office_id_field($office_id);
    rch_render_office_address_field($office_address);
}

/*******************************
 * Add inline styles to ensure WordPress default input styling
 ******************************/
function rch_add_office_metabox_inline_styles()
{
    ?>
    <style>
        #rch_office_meta_box .rch-office-field {
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
        
        #rch_office_meta_box .rch-office-field:focus {
            border-color: #2271b1 !important;
            box-shadow: 0 0 0 1px #2271b1 !important;
            outline: 2px solid transparent !important;
        }
        
        #rch_office_meta_box .rch-office-field[readonly] {
            background-color: #f6f7f7 !important;
            cursor: default !important;
        }
        
        #rch_office_meta_box .rch-field-wrapper {
            margin-bottom: 15px !important;
        }
        
        #rch_office_meta_box .rch-field-label {
            display: block !important;
            margin-bottom: 5px !important;
            font-weight: 600 !important;
            color: #1d2327 !important;
            font-size: 14px !important;
        }
        
        #rch_office_meta_box .rch-field-label em {
            font-weight: normal !important;
            color: #646970 !important;
        }
        
        #rch_office_meta_box h4 {
            margin-top: 20px !important;
            margin-bottom: 10px !important;
            border-bottom: 1px solid #dcdcde !important;
            padding-bottom: 5px !important;
        }
    </style>
    <?php
}

/*******************************
 * Render office ID field (readonly)
 ******************************/
function rch_render_office_id_field($office_id)
{
    ?>
    <div class="rch-field-wrapper">
        <label for="rch_office_id" class="rch-field-label">
            <strong><?php esc_html_e('Office ID', 'rechat-plugin'); ?></strong>
            <em><?php esc_html_e('(not available for locally added offices)', 'rechat-plugin'); ?></em>
        </label>
        <input 
            type="text" 
            id="rch_office_id" 
            name="rch_office_id" 
            value="<?php echo esc_attr($office_id); ?>" 
            class="rch-office-field" 
            readonly 
        />
    </div>
    <?php
}

/*******************************
 * Render office address field
 ******************************/
function rch_render_office_address_field($office_address)
{
    ?>
    <h4><?php esc_html_e('Address', 'rechat-plugin'); ?></h4>
    <div class="rch-field-wrapper">
        <label for="rch_office_address" class="rch-field-label">
            <strong><?php esc_html_e('Full Address', 'rechat-plugin'); ?></strong>
        </label>
        <input 
            type="text" 
            id="rch_office_address" 
            name="rch_office_address" 
            value="<?php echo esc_attr($office_address); ?>" 
            class="rch-office-field" 
        />
    </div>
    <?php
}


/*******************************
 * Add Office ID column to offices post list
 ******************************/
function rch_add_office_id_column($columns)
{
    // Insert Office ID column after title
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'title') {
            $new_columns['rch_office_id'] = __('Office ID', 'rechat-plugin');
        }
    }
    
    return $new_columns;
}
add_filter('manage_offices_posts_columns', 'rch_add_office_id_column');

/*******************************
 * Display Office ID column data
 ******************************/
function rch_show_office_id_column($column, $post_id)
{
    if ($column === 'rch_office_id') {
        $office_id = get_post_meta($post_id, 'office_id', true);
        
        if (!empty($office_id)) {
            echo esc_html($office_id);
        } else {
            echo '<em>' . esc_html__('Local Office', 'rechat-plugin') . '</em>';
        }
    }
}
add_action('manage_offices_posts_custom_column', 'rch_show_office_id_column', 10, 2);

/*******************************
 * Make Office ID column sortable
 ******************************/
function rch_make_office_id_sortable($columns)
{
    $columns['rch_office_id'] = 'office_id';
    return $columns;
}
add_filter('manage_edit-offices_sortable_columns', 'rch_make_office_id_sortable');

/*******************************
 * Handle sorting by Office ID
 ******************************/
function rch_offices_orderby($query)
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');
    
    if ($orderby === 'office_id') {
        $query->set('meta_key', 'office_id');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'rch_offices_orderby');

/*******************************
 * Save office meta box data
 ******************************/
function rch_save_office_meta_box($post_id)
{
    // Verify nonce
    if (!isset($_POST['rch_office_meta_box_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['rch_office_meta_box_nonce'], 'rch_office_meta_box')) {
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

    // Save office address
    if (isset($_POST['rch_office_address'])) {
        $office_address = sanitize_text_field(wp_unslash($_POST['rch_office_address']));
        update_post_meta($post_id, 'office_address', $office_address);
    }
}
add_action('save_post_offices', 'rch_save_office_meta_box');

/*******************************
 * Register office meta fields
 ******************************/
function rch_register_office_meta()
{
    register_meta('post', 'office_id', [
        'type' => 'string',
        'description' => __('Office ID from Rechat API', 'rechat-plugin'),
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);

    register_meta('post', 'office_address', [
        'type' => 'string',
        'description' => __('Office full address', 'rechat-plugin'),
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);
}
add_action('init', 'rch_register_office_meta');

