<?php
if (! defined('ABSPATH')) {
    exit();
}

/*******************************
 * Set default agent_visibility to 'show' for new agents
 ******************************/
function set_default_agent_visibility($post_id)
{
    // Check if this is an agent post type
    if (get_post_type($post_id) !== 'agents') {
        return;
    }

    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Get current visibility value
    $visibility = get_post_meta($post_id, 'agent_visibility', true);

    // If visibility is not set, set it to 'show' as default
    if (empty($visibility)) {
        update_post_meta($post_id, 'agent_visibility', 'show');
    }
}

// Run when a new post is created
add_action('wp_insert_post', 'set_default_agent_visibility', 10, 1);
