<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * Function to add meta box to the 'agents' post type
 ******************************/
function add_agents_meta_box()
{
    add_meta_box(
        'agents_meta_box',       // Meta box ID
        'Agent Details',         // Title
        'agents_meta_box_html',  // Callback function
        'agents',                // Post type
        'normal',                // Context (where to show the meta box)
        'high'                   // Priority
    );
    
    add_meta_box(
        'agents_visibility_meta_box',       // Meta box ID
        'Agent Visibility',                 // Title
        'agents_visibility_meta_box_html',  // Callback function
        'agents',                           // Post type
        'side',                             // Context (sidebar)
        'default'                           // Priority
    );

    add_meta_box(
        'agents_address_meta_box',
        __('Address', 'rechat-plugin'),
        'agents_address_meta_box_html',
        'agents',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'add_agents_meta_box');

/*******************************
 * Callback function to display the meta box HTML
 ******************************/
function agents_meta_box_html($post)
{
    // Add a nonce field for security
    wp_nonce_field('agents_meta_box', 'agents_meta_box_nonce');

    // Get current values of custom fields
    $api_id = get_post_meta($post->ID, 'api_id', true);
    $website = get_post_meta($post->ID, 'website', true);
    $instagram = get_post_meta($post->ID, 'instagram', true);
    $twitter = get_post_meta($post->ID, 'twitter', true);
    $linkedin = get_post_meta($post->ID, 'linkedin', true);
    $youtube = get_post_meta($post->ID, 'youtube', true);
    $facebook = get_post_meta($post->ID, 'facebook', true);
    $phone_number = get_post_meta($post->ID, 'phone_number', true);
    $email = get_post_meta($post->ID, 'email', true);
    $timezone = get_post_meta($post->ID, 'timezone', true);
    $profile_image_url = get_post_meta($post->ID, 'profile_image_url', true);
    $license_number = get_post_meta($post->ID, 'license_number', true);
    $agent_title = get_post_meta($post->ID, 'agent_title', true);
    $first_name = get_post_meta($post->ID, 'first_name', true);
    $last_name = get_post_meta($post->ID, 'last_name', true);
    $display_order_raw = get_post_meta($post->ID, RCH_AGENT_DISPLAY_ORDER_META_KEY, true);
    $display_order_show = '';
    if (! rch_agent_display_order_meta_is_empty($display_order_raw)) {
        $display_order_show = (string) (int) $display_order_raw;
    }
    // Fields below are filled from the Rechat API on every sync. Lock them for synced
    // agents (those with a Rechat ID); locally-added agents keep them editable.
    $rch_is_synced = ($api_id !== '' && $api_id !== null);
    $rch_api_ro = $rch_is_synced
        ? ' readonly title="' . esc_attr__('Synced from Rechat — edit it in the Rechat app.', 'rechat-plugin') . '" style="background:#f0f0f1;color:#50575e;cursor:not-allowed;"'
        : '';
?>
    <?php if ($rch_is_synced) : ?>
    <div class="notice notice-info inline" style="margin:0 0 14px;padding:10px 12px;">
        <p style="margin:0;">
            <strong><?php esc_html_e('These fields are synced from Rechat.', 'rechat-plugin'); ?></strong>
            <?php esc_html_e('Edit them in the Rechat app — any change here is read-only and will be overwritten on the next sync. Only Display order and Agent title are editable in WordPress.', 'rechat-plugin'); ?>
        </p>
    </div>
    <?php endif; ?>

    <label for="api_id_field">Rechat ID (not available for locally added agents): </label>
    <input type="text" id="api_id_field" name="api_id_field" value="<?php echo esc_attr($api_id); ?>" class="widefat" readonly />
    <br>
    <label for="agents_display_order">Display order</label>
    <input type="number" id="agents_display_order" name="agents_display_order" value="<?php echo esc_attr($display_order_show); ?>" class="small-text" min="0" step="1" />
    <p class="description">Set 0, 1, 2… to pin order at the top of lists. Leave empty for no number — those agents appear after all numbered ones. Meta key: <code><?php echo esc_html(RCH_AGENT_DISPLAY_ORDER_META_KEY); ?></code></p>
    <br>
    <label for="agents_profile_image_url">Profile Image URL <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_profile_image_url" name="agents_profile_image_url" value="<?php echo esc_attr($profile_image_url); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>
    <label for="agents_timezone">timezone <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_timezone" name="agents_timezone" value="<?php echo esc_attr($timezone); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>
    <label for="agents_first_name">First Name <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_first_name" name="agents_first_name" value="<?php echo esc_attr($first_name); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>
    <label for="agents_last_name">Last Name <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_last_name" name="agents_last_name" value="<?php echo esc_attr($last_name); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>
    <label for="agents_website">Website <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_website" name="agents_website" value="<?php echo esc_attr($website); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>

    <label for="agents_instagram">Instagram <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_instagram" name="agents_instagram" value="<?php echo esc_attr($instagram); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>

    <label for="agents_twitter">Twitter <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_twitter" name="agents_twitter" value="<?php echo esc_attr($twitter); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>

    <label for="agents_linkedin">LinkedIn <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_linkedin" name="agents_linkedin" value="<?php echo esc_attr($linkedin); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>

    <label for="agents_youtube">YouTube <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_youtube" name="agents_youtube" value="<?php echo esc_attr($youtube); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>

    <label for="agents_facebook">Facebook <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_facebook" name="agents_facebook" value="<?php echo esc_attr($facebook); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>

    <label for="agents_phone_number">Phone Number <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_phone_number" name="agents_phone_number" value="<?php echo esc_attr($phone_number); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>
    <label for="agents_license_number">License Number <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_license_number" name="agents_license_number" value="<?php echo esc_attr($license_number); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>
    <label for="agents_email">Email <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_email" name="agents_email" value="<?php echo esc_attr($email); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <label for="agents_designation">Designation <em>(<?php esc_html_e('from Rechat', 'rechat-plugin'); ?>)</em></label>
    <input type="text" id="agents_designation" name="agents_designation" value="<?php echo esc_attr(get_post_meta($post->ID, 'designation', true)); ?>" class="widefat"<?php echo $rch_api_ro; ?> />
    <br>
    <label for="agents_agent_title"><?php esc_html_e('Agent title', 'rechat-plugin'); ?></label>
    <input type="text" id="agents_agent_title" name="agents_agent_title" value="<?php echo esc_attr($agent_title); ?>" class="widefat" />
    <p class="description"><?php esc_html_e('Optional display title (e.g. Senior Realtor). Stored as agent_title. Editable in WordPress.', 'rechat-plugin'); ?></p>
    <br>
<?php
}

/*******************************
 * Callback function for agent visibility meta box
 ******************************/
function agents_visibility_meta_box_html($post)
{
    // Add a nonce field for security
    wp_nonce_field('agents_visibility_meta_box', 'agents_visibility_meta_box_nonce');

    // Get current value, default to 'show' if not set
    $visibility = get_post_meta($post->ID, 'agent_visibility', true);
    if (empty($visibility)) {
        $visibility = 'show';
    }
?>
    <p>
        <label for="agent_visibility_show">
            <input type="radio" id="agent_visibility_show" name="agent_visibility" value="show" <?php checked($visibility, 'show'); ?> />
            Show
        </label>
    </p>
    <p>
        <label for="agent_visibility_hide">
            <input type="radio" id="agent_visibility_hide" name="agent_visibility" value="hide" <?php checked($visibility, 'hide'); ?> />
            Hide
        </label>
    </p>
    <p class="description">Choose whether to display this agent on the website.</p>
<?php
}

/*******************************
 * Address meta box (from assigned offices; read-only)
 ******************************/
function agents_address_meta_box_html($post)
{
    $entries = function_exists('rch_get_agent_office_addresses')
        ? rch_get_agent_office_addresses((int) $post->ID)
        : [];

    ?>
    <style>
        #agents_address_meta_box .rch-agent-address-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #agents_address_meta_box .rch-agent-address-item {
            margin: 0 0 12px;
            padding: 10px 12px;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            background: #f6f7f7;
        }
        #agents_address_meta_box .rch-agent-address-item:last-child {
            margin-bottom: 0;
        }
        #agents_address_meta_box .rch-agent-address-office {
            margin: 0 0 6px;
            font-weight: 600;
        }
        #agents_address_meta_box .rch-agent-address-text {
            margin: 0;
            white-space: pre-wrap;
        }
        #agents_address_meta_box .rch-agent-address-empty {
            color: #646970;
            font-style: italic;
        }
    </style>
    <?php

    if ($entries === []) {
        echo '<p class="rch-agent-address-empty">' . esc_html__(
            'No offices assigned. Assign offices in the Rechat Offices box to show address here.',
            'rechat-plugin'
        ) . '</p>';
        return;
    }

    echo '<ul class="rch-agent-address-list">';
    foreach ($entries as $entry) {
        $office_name = isset($entry['office_name']) ? (string) $entry['office_name'] : '';
        $address     = isset($entry['address']) ? trim((string) $entry['address']) : '';
        ?>
        <li class="rch-agent-address-item">
            <p class="rch-agent-address-office"><?php echo esc_html($office_name); ?></p>
            <?php if ($address !== '') : ?>
                <p class="rch-agent-address-text"><?php echo esc_html($address); ?></p>
            <?php else : ?>
                <p class="rch-agent-address-empty"><?php esc_html_e('No address on file for this office.', 'rechat-plugin'); ?></p>
            <?php endif; ?>
        </li>
        <?php
    }
    echo '</ul>';

    if (count($entries) > 1) {
        echo '<p class="description">' . esc_html__(
            'Multiple offices: each address is listed under its office name. The combined text is stored as agent_address for subsite theme import.',
            'rechat-plugin'
        ) . '</p>';
    } else {
        echo '<p class="description">' . esc_html__(
            'Address comes from the assigned office. It is stored as agent_address for subsite theme import.',
            'rechat-plugin'
        ) . '</p>';
    }
}

/*******************************
 * Function to save custom field data
 ******************************/
function save_agents_meta_box($post_id)
{
    // Check if our nonce is set.
    if (!isset($_POST['agents_meta_box_nonce'])) {
        return $post_id;
    }
    $nonce = $_POST['agents_meta_box_nonce'];

    // Verify that the nonce is valid.
    if (!wp_verify_nonce($nonce, 'agents_meta_box')) {
        return $post_id;
    }

    // Check if this is an autosave.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // Check the user's permissions.
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    if (get_post_type($post_id) !== 'agents') {
        return $post_id;
    }

    // Sanitize and save each field
    $fields = array(
        'agents_profile_image_url' => 'profile_image_url',
        'agents_website' => 'website',
        'agents_first_name' => 'first_name',
        'agents_last_name' => 'last_name',
        'agents_instagram' => 'instagram',
        'agents_twitter' => 'twitter',
        'agents_linkedin' => 'linkedin',
        'agents_youtube' => 'youtube',
        'agents_facebook' => 'facebook',
        'agents_phone_number' => 'phone_number',
        'agents_email' => 'email',
        'agents_timezone' => 'timezone',
        'agents_designation' => 'designation',
        'agents_agent_title' => 'agent_title',
        'agents_license_number' => 'license_number', // Save License Number
    );

    foreach ($fields as $input_name => $meta_key) {
        if (isset($_POST[$input_name])) {
            $value = sanitize_text_field($_POST[$input_name]);
            update_post_meta($post_id, $meta_key, $value);
        }
    }

    $order_input = isset($_POST['agents_display_order']) ? sanitize_text_field(wp_unslash($_POST['agents_display_order'])) : '';
    $order_input = trim((string) $order_input);
    if ($order_input === '') {
        delete_post_meta($post_id, RCH_AGENT_DISPLAY_ORDER_META_KEY);
    } else {
        $n = absint($order_input);
        if ($n >= RCH_AGENT_DISPLAY_ORDER_EMPTY_SORT) {
            $n = RCH_AGENT_DISPLAY_ORDER_EMPTY_SORT - 1;
        }
        update_post_meta($post_id, RCH_AGENT_DISPLAY_ORDER_META_KEY, (string) $n);
    }
    
    // Save agent visibility
    if (isset($_POST['agents_visibility_meta_box_nonce']) && wp_verify_nonce($_POST['agents_visibility_meta_box_nonce'], 'agents_visibility_meta_box')) {
        if (isset($_POST['agent_visibility'])) {
            $visibility = sanitize_text_field($_POST['agent_visibility']);
            // Only allow 'show' or 'hide' values
            if (in_array($visibility, array('show', 'hide'))) {
                update_post_meta($post_id, 'agent_visibility', $visibility);
            }
        } else {
            // If not set, default to 'show'
            $current_visibility = get_post_meta($post_id, 'agent_visibility', true);
            if (empty($current_visibility)) {
                update_post_meta($post_id, 'agent_visibility', 'show');
            }
        }
    }

    if (function_exists('rch_sync_agent_address_meta')) {
        rch_sync_agent_address_meta($post_id);
    }
}


add_action('save_post', 'save_agents_meta_box');

/*******************************
 * Function to show api id column in custom post type
 ******************************/
function add_api_id_columns($columns)
{
    // Add a new column for API ID
    $columns['api_id'] = 'Rechat ID (not available for locally added agents)';
    // Add visibility column
    $columns['agent_visibility'] = 'Visibility';
    $columns['agent_display_order'] = 'Order';
    return $columns;
}
add_filter('manage_agents_posts_columns', 'add_api_id_columns');


function show_api_id_column_data($column, $post_id)
{
    if ($column === 'api_id') {
        $api_id = get_post_meta($post_id, 'api_id', true);
        echo esc_html($api_id);
    }
    
    if ($column === 'agent_visibility') {
        $visibility = get_post_meta($post_id, 'agent_visibility', true);
        // Default to 'show' if not set
        if (empty($visibility)) {
            $visibility = 'show';
        }
        
        // Display with color coding
        if ($visibility === 'show') {
            echo '<span style="color: green; font-weight: bold;">● Show</span>';
        } else {
            echo '<span style="color: red; font-weight: bold;">● Hide</span>';
        }
    }

    if ($column === 'agent_display_order') {
        $ord = get_post_meta($post_id, RCH_AGENT_DISPLAY_ORDER_META_KEY, true);
        if (rch_agent_display_order_meta_is_empty($ord)) {
            echo '&mdash;';
        } else {
            echo esc_html((string) (int) $ord);
        }
    }
}
add_action('manage_agents_posts_custom_column', 'show_api_id_column_data', 10, 2);
