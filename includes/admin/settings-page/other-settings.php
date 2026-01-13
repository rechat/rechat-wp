<?php

/*******************************
 * Define the setting fields
 ******************************/
function rch_appearance_setting()
{
    // Existing primary color setting
    $args = array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => '#2271b1',
    );
    register_setting('appearance_settings', '_rch_primary_color', $args);

    // New posts per page setting
    $posts_per_page_args = array(
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 10,
    );
    register_setting('appearance_settings', '_rch_posts_per_page', $posts_per_page_args);

    // Register new settings for Lead Channel and Tags
    function sanitize_lead_channel($input)
    {
        return sanitize_text_field($input); // Ensure the input is an integer (the ID of the lead channel)
    }

    function sanitize_tags($input)
    {
        return array_map('sanitize_text_field', $input); // Sanitizes each tag
    }

    // Sanitize function for JSON array (used for tags)
    function rch_sanitize_json_array($input)
    {
        // Decode the JSON string
        $decoded = json_decode($input, true);
        
        // If it's not valid JSON or not an array, return empty JSON array
        if (!is_array($decoded)) {
            return '[]';
        }
        
        // Sanitize each item and re-encode
        $sanitized = array_map('sanitize_text_field', $decoded);
        return wp_json_encode($sanitized);
    }

    // Register the 'rch_lead_channel' setting
    register_setting('appearance_settings', 'rch_lead_channels', array(
        'sanitize_callback' => 'sanitize_lead_channel',
    ));

    // Register for Agent's Lead Channels
    register_setting('appearance_settings', 'rch_agents_lead_channels', array(
        'sanitize_callback' => 'sanitize_lead_channel',
    ));

    // Register the 'rch_selected_tags' setting with proper sanitization
    register_setting('appearance_settings', 'rch_selected_tags', array(
        'sanitize_callback' => 'rch_sanitize_json_array',
    ));

    // Register for Agent's Tags
    register_setting('appearance_settings', 'rch_agents_selected_tags', array(
        'sanitize_callback' => 'rch_sanitize_json_array',
    ));

    add_settings_section(
        'rch_theme_appearance_setting',
        __('Listing Page Lead Capture', 'rechat-plugin'),
        null,
        'appearance_setting'
    );
    // Add the field for posts per page
    //     add_settings_field(
    //         'rch_posts_per_page',
    //         __('Posts Per Page', 'rechat-plugin'),
    //         'rch_render_posts_per_page_field',
    //         'appearance_setting',
    //         'rch_theme_appearance_setting'
    //     );
    // Add a section heading for 'Lead Channels and Tags' for the first set
    add_settings_field(
        'rch_lead_channels',
        __('Lead Source', 'rechat-plugin'),
        'rch_render_lead_channel',
        'appearance_setting',
        'rch_theme_appearance_setting'
    );

    // Add Tags for first lead
    add_settings_field(
        'rch_select_tag',
        __('Tags', 'rechat-plugin'),
        'rch_render_select_tag',
        'appearance_setting',
        'rch_theme_appearance_setting'
    );

    // Add a new section heading for Agent's Lead Channels and Tags
    add_settings_section(
        'rch_agents_section',
        __('Agent Page Lead Capture', 'rechat-plugin'),
        null,
        'appearance_setting'
    );

    // Add Lead Channels for agents
    add_settings_field(
        'rch_agents_lead_channels',
        __('Lead Source', 'rechat-plugin'),
        'rch_render_agents_lead_channel',
        'appearance_setting',
        'rch_agents_section'
    );

    // Add Tags for agents
    add_settings_field(
        'rch_agents_select_tag',
        __('Tags', 'rechat-plugin'),
        'rch_render_agents_select_tag',
        'appearance_setting',
        'rch_agents_section'
    );
}

add_action('admin_init', 'rch_appearance_setting');

/*******************************
 * Register General Settings
 ******************************/
function rch_general_setting()
{
    // Register setting for listing display mode (combined, separate, active-only, sold-only)
    register_setting('general_settings', 'rch_listing_display_mode', array(
        'type' => 'string',
        'default' => 'combined',
        'sanitize_callback' => 'sanitize_text_field'
    ));

    // Register setting for sort order (date or price)
    register_setting('general_settings', 'rch_listing_sort_order', array(
        'type' => 'string',
        'default' => 'date',
        'sanitize_callback' => 'sanitize_text_field'
    ));

    // Add section for Agent Listing Display Options
    add_settings_section(
        'rch_agents_listing_display_section',
        __('Agent Page Listing Display', 'rechat-plugin'),
        'rch_render_agents_listing_display_description',
        'general_settings'
    );

    // Add radio buttons for listing display mode
    add_settings_field(
        'rch_listing_display_mode',
        __('Listing Display Mode', 'rechat-plugin'),
        'rch_render_listing_display_mode_field',
        'general_settings',
        'rch_agents_listing_display_section'
    );

    // Add radio buttons for sort order
    add_settings_field(
        'rch_listing_sort_order',
        __('Sort Listings By', 'rechat-plugin'),
        'rch_render_listing_sort_order_field',
        'general_settings',
        'rch_agents_listing_display_section'
    );
}

add_action('admin_init', 'rch_general_setting');

/*******************************
 * Render the Posts Per Page input field.
 ******************************/
// function rch_render_posts_per_page_field()
// {
//     $posts_per_page = get_option('_rch_posts_per_page', 12); // Default to 10
//     echo '<input type="number" id="rch_posts_per_page" name="_rch_posts_per_page" value="' . esc_attr($posts_per_page) . '" min="1" />';
// }
/*******************************
 * Render the lead channel input field for the first lead
 ******************************/
function rch_render_lead_channel()
{
    $brand_id = get_option('rch_rechat_brand_id');
    $api_url = "https://api.rechat.com/brands/{$brand_id}/leads/channels";
    $access_token = get_option('rch_rechat_access_token');
    $response = rch_api_request($api_url, $access_token);

    if (!$response['success']) {
        echo esc_html($response['message']);
        return;
    }

    $data = $response['data'];
    if (empty($data['data'])) {
        echo 'No channels available';
        return;
    }

    $selected_channel = get_option('rch_lead_channels', '');
    $options = '<option value="">Select Lead Channel</option>';
    foreach ($data['data'] as $channel) {
        $id = esc_attr($channel['id']);
        $title = !empty($channel['title']) ? esc_html($channel['title']) : 'Unnamed';
        $selected = selected($id, $selected_channel, false);
        $options .= "<option value='{$id}' {$selected}>{$title}</option>";
    }

    // Output the <select> element with the options
    echo '<select id="' . esc_attr('rch_lead_channels') . '" name="' . esc_attr('rch_lead_channels') . '">' . $options . '</select>';
}

/*******************************
 * Render the tags input field for the first lead
 ******************************/
function rch_render_select_tag()
{
    $api_url = "https://api.rechat.com/contacts/tags";
    $access_token = get_option('rch_rechat_access_token');
    $brand_id = get_option('rch_rechat_brand_id');
    $response = rch_api_request($api_url, $access_token, $brand_id);

    if (!$response['success']) {
        echo esc_html($response['message']);
        return;
    }

    $data = $response['data'];
    if (empty($data['data'])) {
        echo 'No tags available';
        return;
    }

    $selected_tags = get_option('rch_selected_tags', '[]');
    $selected_tags = json_decode($selected_tags, true);

    if (!is_array($selected_tags)) {
        $selected_tags = [];
    }

    echo "<select id='tag-select' style='width:100%; margin-bottom:10px;'>";
    echo "<option value='' disabled selected>Please select a tag</option>";
    foreach ($data['data'] as $tag) {
        $name = !empty($tag['tag']) ? esc_html($tag['tag']) : 'Unnamed';
        $selected = in_array($name, $selected_tags) ? 'selected' : '';
        echo "<option value='" . esc_attr($name) . "' " . esc_html($selected) . ">" . esc_html($name) . "</option>";
    }
    echo "</select>";

    echo "<div id='selected-tags-container' style='margin-bottom:10px;'></div>";
    echo "<input type='hidden' name='rch_selected_tags' id='rch_selected_tags_input' value='" . esc_attr(wp_json_encode($selected_tags)) . "'>";

    echo "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectBox = document.getElementById('tag-select');
                const selectedTagsContainer = document.getElementById('selected-tags-container');
                const hiddenInput = document.getElementById('rch_selected_tags_input');
                let selectedTagNames = hiddenInput.value ? JSON.parse(hiddenInput.value) : [];

                function renderChips() {
                    selectedTagsContainer.innerHTML = '';
                    selectedTagNames.forEach(function(tagName) {
                        const chip = document.createElement('span');
                        chip.textContent = tagName;
                        chip.className = 'tag-chip';
                        chip.style.cssText = 'display: inline-block; margin: 0 5px 5px 0; padding: 5px; background-color: #ddd; border-radius: 3px;';
                        const closeBtn = document.createElement('span');
                        closeBtn.textContent = ' ×';
                        closeBtn.style.cssText = 'margin-left: 5px; cursor: pointer;';
                        closeBtn.onclick = function() {
                            selectedTagNames = selectedTagNames.filter(tag => tag !== tagName);
                            updateHiddenInput();
                            renderChips();
                        };
                        chip.appendChild(closeBtn);
                        selectedTagsContainer.appendChild(chip);
                    });
                }

                function updateHiddenInput() {
                    hiddenInput.value = JSON.stringify(selectedTagNames);
                }

                selectBox.addEventListener('change', function() {
                    const selectedTagName = selectBox.value;
                    if (selectedTagName && !selectedTagNames.includes(selectedTagName)) {
                        selectedTagNames.push(selectedTagName);
                        updateHiddenInput();
                        renderChips();
                    }
                    selectBox.value = '';
                });

                renderChips();
            });
        </script>
    ";
}

/*******************************
 * Render the lead channel input field for Agent's Lead Channel
 ******************************/
function rch_render_agents_lead_channel()
{
    $brand_id = get_option('rch_rechat_brand_id');
    $api_url = "https://api.rechat.com/brands/{$brand_id}/leads/channels";
    $access_token = get_option('rch_rechat_access_token');
    $response = rch_api_request($api_url, $access_token);

    if (!$response['success']) {
        echo esc_html($response['message']);
        return;
    }

    $data = $response['data'];
    if (empty($data['data'])) {
        echo 'No channels available';
        return;
    }

    $selected_channel = get_option('rch_agents_lead_channels', '');
    $options = '<option value="">Select Lead Channel</option>';
    foreach ($data['data'] as $channel) {
        $id = esc_attr($channel['id']);
        $title = !empty($channel['title']) ? esc_html($channel['title']) : 'Unnamed';
        $selected = selected($id, $selected_channel, false);
        $options .= "<option value='{$id}' {$selected}>{$title}</option>";
    }

    // Output the <select> element with the options
    echo "<select id='" . esc_attr('rch_agents_lead_channels') . "' name='" . esc_attr('rch_agents_lead_channels') . "'>{$options}</select>";
}


/*******************************
 * Render the tags input field for Agent's Tags
 ******************************/
function rch_render_agents_select_tag()
{
    $api_url = "https://api.rechat.com/contacts/tags";
    $access_token = get_option('rch_rechat_access_token');
    $brand_id = get_option('rch_rechat_brand_id');
    $response = rch_api_request($api_url, $access_token, $brand_id);

    if (!$response['success']) {
        echo esc_html($response['message']);
        return;
    }

    $data = $response['data'];
    if (empty($data['data'])) {
        echo 'No tags available';
        return;
    }

    $selected_tags = get_option('rch_agents_selected_tags', '[]');
    $selected_tags = json_decode($selected_tags, true);

    if (!is_array($selected_tags)) {
        $selected_tags = [];
    }

    echo "<select id='agent-tag-select' style='width:100%; margin-bottom:10px;'>";
    echo "<option value='' disabled selected>Please select a tag</option>";
    foreach ($data['data'] as $tag) {
        $name = !empty($tag['tag']) ? esc_html($tag['tag']) : 'Unnamed';
        $selected = in_array($name, $selected_tags) ? 'selected' : '';
        echo "<option value='" . esc_attr($name) . "' " . esc_attr($selected) . ">" . esc_html($name) . "</option>";
    }
    echo "</select>";

    echo "<div id='agent-selected-tags-container' style='margin-bottom:10px;'></div>";
    echo "<input type='hidden' name='rch_agents_selected_tags' id='rch_agents_selected_tags_input' value='" . esc_attr(wp_json_encode($selected_tags)) . "'>";

    echo "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectBox = document.getElementById('agent-tag-select');
                const selectedTagsContainer = document.getElementById('agent-selected-tags-container');
                const hiddenInput = document.getElementById('rch_agents_selected_tags_input');
                let selectedTagNames = hiddenInput.value ? JSON.parse(hiddenInput.value) : [];

                function renderChips() {
                    selectedTagsContainer.innerHTML = '';
                    selectedTagNames.forEach(function(tagName) {
                        const chip = document.createElement('span');
                        chip.textContent = tagName;
                        chip.className = 'tag-chip';
                        chip.style.cssText = 'display: inline-block; margin: 0 5px 5px 0; padding: 5px; background-color: #ddd; border-radius: 3px;';
                        const closeBtn = document.createElement('span');
                        closeBtn.textContent = ' ×';
                        closeBtn.style.cssText = 'margin-left: 5px; cursor: pointer;';
                        closeBtn.onclick = function() {
                            selectedTagNames = selectedTagNames.filter(tag => tag !== tagName);
                            updateHiddenInput();
                            renderChips();
                        };
                        chip.appendChild(closeBtn);
                        selectedTagsContainer.appendChild(chip);
                    });
                }

                function updateHiddenInput() {
                    hiddenInput.value = JSON.stringify(selectedTagNames);
                }

                selectBox.addEventListener('change', function() {
                    const selectedTagName = selectBox.value;
                    if (selectedTagName && !selectedTagNames.includes(selectedTagName)) {
                        selectedTagNames.push(selectedTagName);
                        updateHiddenInput();
                        renderChips();
                    }
                    selectBox.value = '';
                });

                renderChips();
            });
        </script>
    ";
}

/*******************************
 * Render description for Agent Listing Display section
 ******************************/
function rch_render_agents_listing_display_description()
{
    echo '<p>' . __('Choose how listings should be displayed on agent pages.', 'rechat-plugin') . '</p>';
}

/*******************************
 * Render radio buttons for Listing Display Mode
 ******************************/
function rch_render_listing_display_mode_field()
{
    $display_mode = get_option('rch_listing_display_mode', 'combined');
    ?>
    <fieldset>
        <label>
            <input type="radio" name="rch_listing_display_mode" value="combined" <?php checked('combined', $display_mode); ?> />
            <strong><?php _e('Combined (Default)', 'rechat-plugin'); ?></strong>
            <p class="description" style="margin-left: 25px;"><?php _e('Show all listings (Active and Sold) together in one section', 'rechat-plugin'); ?></p>
        </label>
        <br><br>
        <label>
            <input type="radio" name="rch_listing_display_mode" value="separate" <?php checked('separate', $display_mode); ?> />
            <strong><?php _e('Separate Sections', 'rechat-plugin'); ?></strong>
            <p class="description" style="margin-left: 25px;"><?php _e('Show Active and Sold listings in two separate sections with independent pagination', 'rechat-plugin'); ?></p>
        </label>
        <br><br>
        <label>
            <input type="radio" name="rch_listing_display_mode" value="active-only" <?php checked('active-only', $display_mode); ?> />
            <strong><?php _e('Active Only', 'rechat-plugin'); ?></strong>
            <p class="description" style="margin-left: 25px;"><?php _e('Show only Active listings (Active, Incoming, Coming Soon, Active Under Contract, Active Option Contract, Active Contingent, Active Kick Out, Pending)', 'rechat-plugin'); ?></p>
        </label>
        <br><br>
        <label>
            <input type="radio" name="rch_listing_display_mode" value="sold-only" <?php checked('sold-only', $display_mode); ?> />
            <strong><?php _e('Sold Only', 'rechat-plugin'); ?></strong>
            <p class="description" style="margin-left: 25px;"><?php _e('Show only Sold listings (Sold, Leased)', 'rechat-plugin'); ?></p>
        </label>
    </fieldset>
    <?php
}

/*******************************
 * Render radio buttons for Listing Sort Order
 ******************************/
function rch_render_listing_sort_order_field()
{
    $sort_order = get_option('rch_listing_sort_order', 'date');
    ?>
    <fieldset>
        <label>
            <input type="radio" name="rch_listing_sort_order" value="date" <?php checked('date', $sort_order); ?> />
            <strong><?php _e('Date (Default)', 'rechat-plugin'); ?></strong>
            <p class="description" style="margin-left: 25px;"><?php _e('Sort listings by listing date (newest first)', 'rechat-plugin'); ?></p>
        </label>
        <br><br>
        <label>
            <input type="radio" name="rch_listing_sort_order" value="price" <?php checked('price', $sort_order); ?> />
            <strong><?php _e('Price', 'rechat-plugin'); ?></strong>
            <p class="description" style="margin-left: 25px;"><?php _e('Sort listings by price (highest first)', 'rechat-plugin'); ?></p>
        </label>
    </fieldset>
    <?php
}

/*******************************
 * AJAX handler for updating all data
 ******************************/
function rch_update_all_data()
{
    // Verify nonce for security
    check_ajax_referer('rch_ajax_nonce', 'nonce');

    // Get primary color and logo
    rch_get_primary_color_and_logo();

    // Call the function to fetch and update data
    $result = rch_update_agents_offices_regions_data();
    // Return the result
    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_rch_update_all_data', 'rch_update_all_data');
