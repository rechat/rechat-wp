<?php
/**
 * Agent Metabox Management
 * 
 * Handles custom fields for the 'agents' post type including
 * social media, contact info, and Rechat API integration
 *
 * @package RechatPlugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/*******************************
 * Add meta box to the 'agents' post type
 ******************************/
function rch_add_agents_meta_box()
{
    add_meta_box(
        'rch_agents_meta_box',
        __('Agent Details', 'rechat-plugin'),
        'rch_agents_meta_box_html',
        'agents',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'rch_add_agents_meta_box');


/*******************************
 * Display the meta box HTML
 ******************************/
function rch_agents_meta_box_html($post)
{
    // Add nonce for security
    wp_nonce_field('rch_agents_meta_box', 'rch_agents_meta_box_nonce');

    // Get all agent meta fields
    $meta_fields = rch_get_agent_meta_fields($post->ID);
    
    // Add inline styles to override theme styles
    rch_add_metabox_inline_styles();
    
    // Display metabox sections
    rch_render_agent_api_field($meta_fields['api_id']);
    rch_render_agent_profile_fields($meta_fields);
    rch_render_agent_contact_fields($meta_fields);
    rch_render_agent_social_fields($meta_fields);
}

/*******************************
 * Add inline styles to ensure WordPress default input styling
 ******************************/
function rch_add_metabox_inline_styles()
{
    ?>
    <style>
        #rch_agents_meta_box .rch-agent-field {
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
        
        #rch_agents_meta_box .rch-agent-field:focus {
            border-color: #2271b1 !important;
            box-shadow: 0 0 0 1px #2271b1 !important;
            outline: 2px solid transparent !important;
        }
        
        #rch_agents_meta_box .rch-agent-field[readonly] {
            background-color: #f6f7f7 !important;
            cursor: default !important;
        }
        
        #rch_agents_meta_box .rch-field-wrapper {
            margin-bottom: 15px !important;
        }
        
        #rch_agents_meta_box .rch-field-label {
            display: block !important;
            margin-bottom: 5px !important;
            font-weight: 600 !important;
            color: #1d2327 !important;
            font-size: 14px !important;
        }
        
        #rch_agents_meta_box .rch-field-label em {
            font-weight: normal !important;
            color: #646970 !important;
        }
        
        #rch_agents_meta_box h4 {
            margin-top: 20px !important;
            margin-bottom: 10px !important;
            border-bottom: 1px solid #dcdcde !important;
            padding-bottom: 5px !important;
        }
    </style>
    <?php
}

/*******************************
 * Get all agent meta field values
 ******************************/
function rch_get_agent_meta_fields($post_id)
{
    $fields = [
        'api_id',
        'profile_image_url',
        'timezone',
        'website',
        'phone_number',
        'email',
        'license_number',
        'designation',
        'instagram',
        'twitter',
        'linkedin',
        'youtube',
        'facebook',
    ];

    $meta_data = [];
    foreach ($fields as $field) {
        $meta_data[$field] = get_post_meta($post_id, $field, true);
    }

    return $meta_data;
}

/*******************************
 * Render API ID field (readonly)
 ******************************/
function rch_render_agent_api_field($api_id)
{
    ?>
    <div class="rch-field-wrapper">
        <label for="rch_api_id_field" class="rch-field-label">
            <strong><?php esc_html_e('Rechat ID', 'rechat-plugin'); ?></strong>
            <em><?php esc_html_e('(not available for locally added agents)', 'rechat-plugin'); ?></em>
        </label>
        <input 
            type="text" 
            id="rch_api_id_field" 
            name="rch_api_id_field" 
            value="<?php echo esc_attr($api_id); ?>" 
            class="rch-agent-field" 
            readonly 
        />
    </div>
    <?php
}

/*******************************
 * Render profile-related fields
 ******************************/
function rch_render_agent_profile_fields($meta_fields)
{
    $profile_fields = [
        'profile_image_url' => __('Profile Image URL', 'rechat-plugin'),
        'timezone' => __('Timezone', 'rechat-plugin'),
        'designation' => __('Designation', 'rechat-plugin'),
        'license_number' => __('License Number', 'rechat-plugin'),
    ];

    foreach ($profile_fields as $field => $label) {
        $field_name = 'rch_agents_' . $field;
        $value = isset($meta_fields[$field]) ? $meta_fields[$field] : '';
        $input_type = ($field === 'profile_image_url') ? 'url' : 'text';
        
        rch_render_text_field($field_name, $label, $value, $input_type);
    }
}

/*******************************
 * Render contact-related fields
 ******************************/
function rch_render_agent_contact_fields($meta_fields)
{
    $contact_fields = [
        'website' => [
            'label' => __('Website', 'rechat-plugin'),
            'type' => 'url',
        ],
        'phone_number' => [
            'label' => __('Phone Number', 'rechat-plugin'),
            'type' => 'tel',
        ],
        'email' => [
            'label' => __('Email', 'rechat-plugin'),
            'type' => 'email',
        ],
    ];

    foreach ($contact_fields as $field => $config) {
        $field_name = 'rch_agents_' . $field;
        $value = isset($meta_fields[$field]) ? $meta_fields[$field] : '';
        
        rch_render_text_field($field_name, $config['label'], $value, $config['type']);
    }
}

/*******************************
 * Render social media fields
 ******************************/
function rch_render_agent_social_fields($meta_fields)
{
    echo '<h4>' . esc_html__('Social Media Links', 'rechat-plugin') . '</h4>';
    
    $social_fields = [
        'instagram' => __('Instagram', 'rechat-plugin'),
        'twitter' => __('Twitter', 'rechat-plugin'),
        'linkedin' => __('LinkedIn', 'rechat-plugin'),
        'youtube' => __('YouTube', 'rechat-plugin'),
        'facebook' => __('Facebook', 'rechat-plugin'),
    ];

    foreach ($social_fields as $field => $label) {
        $field_name = 'rch_agents_' . $field;
        $value = isset($meta_fields[$field]) ? $meta_fields[$field] : '';
        
        rch_render_text_field($field_name, $label, $value, 'url');
    }
}

/*******************************
 * Helper to render individual text field
 ******************************/
function rch_render_text_field($name, $label, $value, $type = 'text')
{
    ?>
    <div class="rch-field-wrapper">
        <label for="<?php echo esc_attr($name); ?>" class="rch-field-label">
            <strong><?php echo esc_html($label); ?></strong>
        </label>
        <input 
            type="<?php echo esc_attr($type); ?>" 
            id="<?php echo esc_attr($name); ?>" 
            name="<?php echo esc_attr($name); ?>" 
            value="<?php echo esc_attr($value); ?>" 
            class="rch-agent-field" 
        />
    </div>
    <?php
}


/*******************************
 * Save agent meta box data
 ******************************/
function rch_save_agents_meta_box($post_id)
{
    // Verify nonce
    if (!isset($_POST['rch_agents_meta_box_nonce'])) {
        return $post_id;
    }

    if (!wp_verify_nonce($_POST['rch_agents_meta_box_nonce'], 'rch_agents_meta_box')) {
        return $post_id;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    // Define field mapping with sanitization type
    $field_config = rch_get_agent_field_config();

    // Save each field with appropriate sanitization
    foreach ($field_config as $input_name => $config) {
        if (isset($_POST[$input_name])) {
            $value = wp_unslash($_POST[$input_name]);
            $sanitized_value = rch_sanitize_agent_field($value, $config['type']);
            
            update_post_meta($post_id, $config['meta_key'], $sanitized_value);
        }
    }
}
add_action('save_post', 'rch_save_agents_meta_box');

/*******************************
 * Get agent field configuration
 ******************************/
function rch_get_agent_field_config()
{
    return [
        'rch_agents_profile_image_url' => [
            'meta_key' => 'profile_image_url',
            'type' => 'url',
        ],
        'rch_agents_website' => [
            'meta_key' => 'website',
            'type' => 'url',
        ],
        'rch_agents_instagram' => [
            'meta_key' => 'instagram',
            'type' => 'url',
        ],
        'rch_agents_twitter' => [
            'meta_key' => 'twitter',
            'type' => 'url',
        ],
        'rch_agents_linkedin' => [
            'meta_key' => 'linkedin',
            'type' => 'url',
        ],
        'rch_agents_youtube' => [
            'meta_key' => 'youtube',
            'type' => 'url',
        ],
        'rch_agents_facebook' => [
            'meta_key' => 'facebook',
            'type' => 'url',
        ],
        'rch_agents_phone_number' => [
            'meta_key' => 'phone_number',
            'type' => 'text',
        ],
        'rch_agents_email' => [
            'meta_key' => 'email',
            'type' => 'email',
        ],
        'rch_agents_timezone' => [
            'meta_key' => 'timezone',
            'type' => 'text',
        ],
        'rch_agents_designation' => [
            'meta_key' => 'designation',
            'type' => 'text',
        ],
        'rch_agents_license_number' => [
            'meta_key' => 'license_number',
            'type' => 'text',
        ],
    ];
}

/*******************************
 * Sanitize field based on type
 ******************************/
function rch_sanitize_agent_field($value, $type)
{
    switch ($type) {
        case 'url':
            return esc_url_raw($value);
        
        case 'email':
            return sanitize_email($value);
        
        case 'text':
        default:
            return sanitize_text_field($value);
    }
}


/*******************************
 * Add API ID column to agents post list
 ******************************/
function rch_add_agents_api_id_column($columns)
{
    // Insert API ID column after title
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'title') {
            $new_columns['rch_api_id'] = __('Rechat ID', 'rechat-plugin');
        }
    }
    
    return $new_columns;
}
add_filter('manage_agents_posts_columns', 'rch_add_agents_api_id_column');

/*******************************
 * Display API ID column data
 ******************************/
function rch_show_agents_api_id_column($column, $post_id)
{
    if ($column === 'rch_api_id') {
        $api_id = get_post_meta($post_id, 'api_id', true);
        
        if (!empty($api_id)) {
            echo esc_html($api_id);
        } else {
            echo '<em>' . esc_html__('Local Agent', 'rechat-plugin') . '</em>';
        }
    }
}
add_action('manage_agents_posts_custom_column', 'rch_show_agents_api_id_column', 10, 2);

/*******************************
 * Make API ID column sortable
 ******************************/
function rch_make_agents_api_id_sortable($columns)
{
    $columns['rch_api_id'] = 'api_id';
    return $columns;
}
add_filter('manage_edit-agents_sortable_columns', 'rch_make_agents_api_id_sortable');

/*******************************
 * Handle sorting by API ID
 ******************************/
function rch_agents_api_id_orderby($query)
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');
    
    if ($orderby === 'api_id') {
        $query->set('meta_key', 'api_id');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'rch_agents_api_id_orderby');

