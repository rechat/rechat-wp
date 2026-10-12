<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RealScout admin page.
 *
 * Hidden page (no menu entry) reachable only at wp-admin/admin.php?page=realscout.
 * Administrators only (manage_options). Stores a numeric value per agent as protected
 * post meta (leading underscore → never shown in the Custom Fields box or REST) plus
 * the main website ID as a site option. Storage keys + getters: includes/realscout/realscout-helpers.php.
 */

if (!defined('RCH_REALSCOUT_PAGE_SLUG')) {
    define('RCH_REALSCOUT_PAGE_SLUG', 'realscout');
}
if (!defined('RCH_REALSCOUT_CAPABILITY')) {
    define('RCH_REALSCOUT_CAPABILITY', 'manage_options');
}
/*******************************
 * Access check: logged-in administrators only
 ******************************/
function rch_realscout_current_user_can(): bool
{
    return is_user_logged_in() && current_user_can(RCH_REALSCOUT_CAPABILITY);
}

/*******************************
 * Register hidden page (no menu item; URL access only)
 ******************************/
function rch_realscout_register_page()
{
    // Empty parent slug registers the page without adding it to any menu.
    $hook = add_submenu_page(
        '',
        __('RealScout', 'rechat-plugin'),
        __('RealScout', 'rechat-plugin'),
        RCH_REALSCOUT_CAPABILITY,
        RCH_REALSCOUT_PAGE_SLUG,
        'rch_realscout_render_page'
    );

    if ($hook) {
        add_action('load-' . $hook, 'rch_realscout_set_admin_title');
    }
}
add_action('admin_menu', 'rch_realscout_register_page');

/*******************************
 * Hidden pages have no menu entry to derive a title from; set it explicitly.
 ******************************/
function rch_realscout_set_admin_title()
{
    global $title;
    $title = __('RealScout', 'rechat-plugin');
}

/*******************************
 * Query every agent on the current site
 ******************************/
function rch_realscout_get_agents(): array
{
    $query = new WP_Query([
        'post_type'              => 'agents',
        'post_status'            => ['publish', 'draft', 'pending', 'private', 'future'],
        'posts_per_page'         => -1,
        'orderby'                => 'title',
        'order'                  => 'ASC',
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
        'suppress_filters'       => true,
    ]);

    return $query->posts;
}

/*******************************
 * Save handler (admin-post.php?action=rch_realscout_save)
 ******************************/
function rch_realscout_handle_save()
{
    if (!rch_realscout_current_user_can()) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rechat-plugin'), 403);
    }

    check_admin_referer('rch_realscout_save', 'rch_realscout_nonce');

    // Main website ID (number, or a pasted agent-encoded-id which is decoded to its number).
    $main_invalid = false;
    if (isset($_POST['rch_realscout_main_site_id'])) {
        $raw_main     = sanitize_text_field(wp_unslash($_POST['rch_realscout_main_site_id']));
        $main_site_id = rch_realscout_normalize_id($raw_main);

        if ($main_site_id === '' && trim($raw_main) !== '') {
            $main_invalid = true;
        } else {
            update_option(RCH_REALSCOUT_MAIN_SITE_ID_OPTION, $main_site_id, false);
        }
    }

    // Agent values. Only posted keys are touched (unchanged inputs are disabled client-side
    // to stay under max_input_vars), so a missing key never wipes a stored value.
    $saved   = 0;
    $invalid = [];
    $posted  = isset($_POST['rch_realscout_agent']) && is_array($_POST['rch_realscout_agent'])
        ? wp_unslash($_POST['rch_realscout_agent'])
        : [];

    foreach ($posted as $agent_id => $raw_value) {
        $agent_id = absint($agent_id);
        if (!$agent_id || get_post_type($agent_id) !== 'agents' || is_array($raw_value)) {
            continue;
        }

        $raw_value = trim((string) $raw_value);

        if ($raw_value === '') {
            delete_post_meta($agent_id, RCH_REALSCOUT_AGENT_META_KEY);
            $saved++;
            continue;
        }

        // Number, or a pasted agent-encoded-id decoded back to its number.
        $value = rch_realscout_normalize_id($raw_value);
        if ($value === '') {
            $invalid[] = $agent_id;
            continue;
        }

        update_post_meta($agent_id, RCH_REALSCOUT_AGENT_META_KEY, $value);
        $saved++;
    }

    $redirect = add_query_arg(
        [
            'page'                => RCH_REALSCOUT_PAGE_SLUG,
            'rch_rs_saved'        => 1,
            'rch_rs_count'        => $saved,
            'rch_rs_invalid'      => $invalid ? implode(',', $invalid) : false,
            'rch_rs_main_invalid' => $main_invalid ? 1 : false,
        ],
        admin_url('admin.php')
    );

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_rch_realscout_save', 'rch_realscout_handle_save');

/*******************************
 * Render page
 ******************************/
function rch_realscout_render_page()
{
    if (!rch_realscout_current_user_can()) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'rechat-plugin'), 403);
    }

    $agents       = rch_realscout_get_agents();
    $main_site_id = rch_realscout_get_main_site_id();

    $invalid_ids = [];
    if (!empty($_GET['rch_rs_invalid'])) {
        $invalid_ids = array_filter(array_map('absint', explode(',', sanitize_text_field(wp_unslash($_GET['rch_rs_invalid'])))));
    }
    ?>
    <div class="wrap rch-realscout">
        <h1><?php esc_html_e('RealScout', 'rechat-plugin'); ?></h1>

        <?php if (!empty($_GET['rch_rs_saved'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('RealScout settings saved.', 'rechat-plugin'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['rch_rs_main_invalid'])) : ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Main Website ID was not saved because the value is not a RealScout agent number or encoded ID.', 'rechat-plugin'); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($invalid_ids) : ?>
            <div class="notice notice-error">
                <p>
                    <?php esc_html_e('These agents were not saved because the value is not a RealScout agent number or encoded ID:', 'rechat-plugin'); ?>
                    <?php echo esc_html(implode(', ', array_map('get_the_title', $invalid_ids))); ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="rch-realscout-form">
            <input type="hidden" name="action" value="rch_realscout_save">
            <?php wp_nonce_field('rch_realscout_save', 'rch_realscout_nonce'); ?>

            <h2><?php esc_html_e('Main Website', 'rechat-plugin'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="rch_realscout_main_site_id"><?php esc_html_e('Main Website ID', 'rechat-plugin'); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="rch_realscout_main_site_id"
                            name="rch_realscout_main_site_id"
                            value="<?php echo esc_attr($main_site_id); ?>"
                            class="regular-text">
                        <p class="description"><?php esc_html_e('Used by RealScout widgets on the main site, office sites, and agent sites whose agent has no value below.', 'rechat-plugin'); ?></p>
                    </td>
                </tr>
            </table>

            <h2>
                <?php esc_html_e('Agents', 'rechat-plugin'); ?>
                <span class="count">(<?php echo esc_html(number_format_i18n(count($agents))); ?>)</span>
            </h2>

            <p class="description"><?php esc_html_e('Enter the RealScout agent number (e.g. 322973) or paste the agent-encoded-id from RealScout (e.g. QWdlbnQtMzIyOTcz).', 'rechat-plugin'); ?></p>

            <?php if (!$agents) : ?>
                <p><?php esc_html_e('No agents found on this site.', 'rechat-plugin'); ?></p>
            <?php else : ?>
                <p>
                    <label for="rch-realscout-filter" class="screen-reader-text"><?php esc_html_e('Filter agents', 'rechat-plugin'); ?></label>
                    <input type="search" id="rch-realscout-filter" class="regular-text" placeholder="<?php esc_attr_e('Filter agents by name…', 'rechat-plugin'); ?>">
                </p>

                <table class="widefat striped" style="max-width: 720px;">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Agent', 'rechat-plugin'); ?></th>
                            <th scope="col" style="width: 220px;"><?php esc_html_e('Value', 'rechat-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agents as $agent) :
                            $field_id = 'rch-realscout-agent-' . $agent->ID;
                            $name     = $agent->post_title !== '' ? $agent->post_title : __('(no name)', 'rechat-plugin');
                            ?>
                            <tr data-name="<?php echo esc_attr(strtolower($name)); ?>">
                                <td>
                                    <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($name); ?></label>
                                    <?php if ($agent->post_status !== 'publish') : ?>
                                        <span class="description">— <?php echo esc_html(get_post_status_object($agent->post_status)->label ?? $agent->post_status); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        id="<?php echo esc_attr($field_id); ?>"
                                        name="rch_realscout_agent[<?php echo esc_attr($agent->ID); ?>]"
                                        value="<?php echo esc_attr(rch_realscout_get_agent_value((int) $agent->ID)); ?>"
                                        class="regular-text"
                                        style="width: 100%;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php submit_button(__('Save Changes', 'rechat-plugin')); ?>
        </form>
    </div>

    <script>
    (function () {
        var form = document.getElementById('rch-realscout-form');
        var filter = document.getElementById('rch-realscout-filter');

        if (filter) {
            filter.addEventListener('input', function () {
                var term = filter.value.trim().toLowerCase();
                document.querySelectorAll('.rch-realscout tbody tr').forEach(function (row) {
                    row.style.display = !term || row.dataset.name.indexOf(term) !== -1 ? '' : 'none';
                });
            });
        }

        // Submit only changed agent fields so large agent lists stay under max_input_vars.
        if (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll('input[name^="rch_realscout_agent["]').forEach(function (input) {
                    if (input.value === input.defaultValue) {
                        input.disabled = true;
                    }
                });
            });
        }
    })();
    </script>
    <?php
}
