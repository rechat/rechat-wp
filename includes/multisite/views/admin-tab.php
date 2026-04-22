<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Render the Multisite tab in the Rechat Settings page.
 *
 * @return void
 */
function rch_multisite_render_admin_tab(): void
{
    $create_sites    = rch_multisite_is_create_agent_sites_enabled();
    $create_offices  = rch_multisite_is_create_office_sites_enabled();
    $admin_user_id   = absint(get_site_option('rch_multisite_admin_user_id', 0));
    $delete_on_del   = (bool) get_site_option('rch_multisite_delete_site_on_agent_delete', 0);
    $url_type        = rch_multisite_get_url_type();
    $network         = get_network();
    $base_domain     = preg_replace('/^www\./i', '', $network->domain);
    $network_path    = trailingslashit($network->path); // e.g. '/rechat-plugin/'
    $provision_nonce    = wp_create_nonce('rch_multisite_provision_all');
    $toggle_nonce       = wp_create_nonce('rch_multisite_toggle_agent');
    $reprovision_nonce  = wp_create_nonce('rch_multisite_reprovision_editor');
    $bulk_theme_nonce   = wp_create_nonce('rch_multisite_bulk_theme');
    $row_theme_nonce   = wp_create_nonce('rch_multisite_row_theme');
    $reassign_roles_nonce = wp_create_nonce('rch_multisite_reassign_agent_roles');
    $saved_agent_theme  = (string) get_site_option('rch_multisite_agent_theme_stylesheet', '');
    $saved_office_theme = (string) get_site_option('rch_multisite_office_theme_stylesheet', '');
    $theme_choices      = rch_multisite_get_theme_choices();

    // Detect the WordPress network install type.
    $wp_subdomain_install = defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL;

    // URL pattern examples for the helper text.
    $example_subdomain    = 'john.' . $base_domain;
    $example_subdirectory = $base_domain . rtrim($network_path, '/') . '/john';

    // All published agent posts for the status table.
    $agents = get_posts([
        'post_type'   => 'agents',
        'numberposts' => -1,
        'post_status' => 'publish',
        'orderby'     => 'title',
        'order'       => 'ASC',
        'fields'      => 'all',
    ]);

    $offices = get_posts([
        'post_type'   => 'offices',
        'numberposts' => -1,
        'post_status' => 'publish',
        'orderby'     => 'title',
        'order'       => 'ASC',
        'fields'      => 'all',
    ]);

    ?>
    <div class="tab-content">

        <h2><?php esc_html_e('Multisite – Agent & Office Sites', 'rechat-plugin'); ?></h2>

        <?php settings_errors('rch_multisite'); ?>

        <?php if (function_exists('rch_multisite_subsites_require_broadcast') && rch_multisite_subsites_require_broadcast() && function_exists('rch_multisite_broadcast_plugin_active') && ! rch_multisite_broadcast_plugin_active()) : ?>
            <div class="notice notice-error inline" style="max-width:900px;">
                <p>
                    <?php esc_html_e('Rechat is set to create agent or office sub-sites, but Broadcast (ThreeWP Broadcast) is not network-active. Sub-site creation is blocked until Broadcast is installed and network-activated.', 'rechat-plugin'); ?>
                    <?php
                    printf(
                        ' <a href="%1$s">%2$s</a>',
                        esc_url(network_admin_url('plugins.php')),
                        esc_html__('Network Plugins', 'rechat-plugin')
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <?php /* ── Settings form ──────────────────────────────────────────── */ ?>
        <form method="POST" action="">
            <?php wp_nonce_field('rch_multisite_save_settings', 'rch_multisite_save_nonce'); ?>

            <table class="form-table">

                <?php /* Master: create sub-sites for agents */ ?>
                <tr valign="top">
                    <th scope="row">
                        <?php esc_html_e('Agent sub-sites', 'rechat-plugin'); ?>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="rch_multisite_create_agent_sites"
                                value="1"
                                <?php checked($create_sites); ?>
                            >
                            <?php esc_html_e('Create a WordPress sub-site for each agent', 'rechat-plugin'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When checked, syncing agents from the API, manual saves, and “Provision” can create and update individual network sites. Turn this off if you only want agent profiles (the Agents post type) without separate websites.', 'rechat-plugin'); ?>
                        </p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        <?php esc_html_e('Office sub-sites', 'rechat-plugin'); ?>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="rch_multisite_create_office_sites"
                                value="1"
                                <?php checked($create_offices); ?>
                            >
                            <?php esc_html_e('Create a WordPress sub-site for each office', 'rechat-plugin'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When checked, syncing offices from the API, manual saves, and “Provision” can create and update a network site per office. Office URLs use an o- prefix (e.g. o-main-office) so they never clash with agent slugs.', 'rechat-plugin'); ?>
                        </p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        <label for="rch-multisite-agent-theme">
                            <?php esc_html_e('Default theme for agent sub-sites', 'rechat-plugin'); ?>
                        </label>
                    </th>
                    <td>
                        <select
                            name="rch_multisite_agent_theme_stylesheet"
                            id="rch-multisite-agent-theme"
                            class="regular-text"
                        >
                            <option value="" <?php selected($saved_agent_theme, ''); ?>>
                                <?php esc_html_e('Same as main site (default)', 'rechat-plugin'); ?>
                            </option>
                            <?php foreach ($theme_choices as $slug => $label) : ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($saved_agent_theme, $slug); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Used for new agent sites and for “Apply theme to all agent sub-sites” below when no per-agent override is set. Enable themes in Network Admin → Themes if a theme does not activate.', 'rechat-plugin'); ?>
                        </p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        <label for="rch-multisite-office-theme">
                            <?php esc_html_e('Default theme for office sub-sites', 'rechat-plugin'); ?>
                        </label>
                    </th>
                    <td>
                        <select
                            name="rch_multisite_office_theme_stylesheet"
                            id="rch-multisite-office-theme"
                            class="regular-text"
                        >
                            <option value="" <?php selected($saved_office_theme, ''); ?>>
                                <?php esc_html_e('Same as main site (default)', 'rechat-plugin'); ?>
                            </option>
                            <?php foreach ($theme_choices as $slug => $label) : ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($saved_office_theme, $slug); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Used for new office sites and for “Apply theme to all office sub-sites” below when no per-office override is set.', 'rechat-plugin'); ?>
                        </p>
                    </td>
                </tr>

                <?php /* URL type */ ?>
                <tr valign="top">
                    <th scope="row">
                        <?php esc_html_e('Site URL format', 'rechat-plugin'); ?>
                    </th>
                    <td>
                        <?php
                        // Show a notice if the selected type mismatches SUBDOMAIN_INSTALL.
                        $saved_type   = (string) get_site_option('rch_multisite_url_type', '');
                        $type_is_auto = ! in_array($saved_type, ['subdomain', 'subdirectory'], true);
                        ?>
                        <?php if ($wp_subdomain_install) : ?>
                            <div class="notice notice-info inline" style="margin:0 0 8px;padding:6px 12px;">
                                <p>
                                    <strong><?php esc_html_e('Detected: Subdomain install', 'rechat-plugin'); ?></strong>
                                    — <code>SUBDOMAIN_INSTALL = true</code> in wp-config.php.
                                    <?php if ($type_is_auto) esc_html_e('Defaulting to Subdomain mode.', 'rechat-plugin'); ?>
                                </p>
                            </div>
                        <?php else : ?>
                            <div class="notice notice-info inline" style="margin:0 0 8px;padding:6px 12px;">
                                <p>
                                    <strong><?php esc_html_e('Detected: Subdirectory install', 'rechat-plugin'); ?></strong>
                                    — <code>SUBDOMAIN_INSTALL = false</code> in wp-config.php.
                                    Network base path: <code><?php echo esc_html($network_path); ?></code>.
                                    <?php if ($type_is_auto) esc_html_e('Defaulting to Subdirectory mode.', 'rechat-plugin'); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <fieldset>
                            <label style="display:block;margin-bottom:6px;">
                                <input
                                    type="radio"
                                    name="rch_multisite_url_type"
                                    value="subdomain"
                                    <?php checked($url_type, 'subdomain'); ?>
                                    <?php if (! $wp_subdomain_install) echo 'style="opacity:.6"'; ?>
                                >
                                <?php esc_html_e('Subdomain', 'rechat-plugin'); ?>
                                &nbsp;<code><?php echo esc_html($example_subdomain); ?></code>
                                <?php if (! $wp_subdomain_install) : ?>
                                    &nbsp;<em style="color:#d63638;"><?php esc_html_e('(requires SUBDOMAIN_INSTALL = true)', 'rechat-plugin'); ?></em>
                                <?php endif; ?>
                            </label>
                            <label style="display:block;">
                                <input
                                    type="radio"
                                    name="rch_multisite_url_type"
                                    value="subdirectory"
                                    <?php checked($url_type, 'subdirectory'); ?>
                                    <?php if ($wp_subdomain_install) echo 'style="opacity:.6"'; ?>
                                >
                                <?php esc_html_e('Subdirectory', 'rechat-plugin'); ?>
                                &nbsp;<code><?php echo esc_html($example_subdirectory); ?></code>
                                <?php if ($wp_subdomain_install) : ?>
                                    &nbsp;<em style="color:#d63638;"><?php esc_html_e('(requires SUBDOMAIN_INSTALL = false)', 'rechat-plugin'); ?></em>
                                <?php endif; ?>
                            </label>
                        </fieldset>
                        <p class="description" style="margin-top:6px;">
                            <?php esc_html_e('Must match the network type set in wp-config.php. Changing this after sites have already been created will not rename existing sites.', 'rechat-plugin'); ?>
                        </p>
                    </td>
                </tr>

                <?php /* Owner user ID */ ?>
                <tr valign="top">
                    <th scope="row">
                        <label for="rch_multisite_admin_user_id">
                            <?php esc_html_e('Owner User ID for new sites', 'rechat-plugin'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="rch_multisite_admin_user_id"
                            name="rch_multisite_admin_user_id"
                            value="<?php echo esc_attr($admin_user_id ?: ''); ?>"
                            placeholder="<?php esc_attr_e('Leave blank to use network admin', 'rechat-plugin'); ?>"
                            class="small-text"
                            min="1"
                        >
                        <p class="description">
                            <?php esc_html_e('WordPress user ID set as admin of each new agent site. Leave blank to auto-use the network admin.', 'rechat-plugin'); ?>
                        </p>
                    </td>
                </tr>

                <?php /* Delete on agent delete */ ?>
                <tr valign="top">
                    <th scope="row">
                            <?php esc_html_e('When agent or office post is deleted', 'rechat-plugin'); ?>
                    </th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="rch_multisite_delete_on_delete"
                                value="1"
                                <?php checked($delete_on_del); ?>
                            >
                            <?php esc_html_e('Permanently delete the linked sub-site (and all its content). When unchecked the site is archived instead.', 'rechat-plugin'); ?>
                        </label>
                    </td>
                </tr>

            </table>

            <?php submit_button(__('Save Multisite Settings', 'rechat-plugin')); ?>
        </form>

        <hr>

        <?php /* ── Bulk provision ────────────────────────────────────────── */ ?>
        <h3><?php esc_html_e('Provision / Reconcile All Agent & Office Sites', 'rechat-plugin'); ?></h3>

        <p>
            <?php esc_html_e('Creates missing sub-sites for all enabled agents and offices and updates titles. Disabled entries are skipped. Safe to run multiple times.', 'rechat-plugin'); ?>
        </p>

        <?php if (! $create_sites && ! $create_offices) : ?>
            <p class="notice notice-warning inline" style="margin:0 0 12px;padding:8px 12px;">
                <?php esc_html_e('Agent and office sub-site creation are turned off above. Enable at least one to use these tools.', 'rechat-plugin'); ?>
            </p>
        <?php endif; ?>

        <button
            id="rch-multisite-provision-btn"
            type="button"
            class="button button-primary"
            data-nonce="<?php echo esc_attr($provision_nonce); ?>"
            <?php disabled(! $create_sites && ! $create_offices); ?>
        >
            <?php esc_html_e('Provision All Agent & Office Sites', 'rechat-plugin'); ?>
        </button>

        <button
            id="rch-multisite-fix-themes-btn"
            type="button"
            class="button"
            style="margin-left:8px;"
            data-nonce="<?php echo esc_attr(wp_create_nonce('rch_multisite_fix_themes')); ?>"
        >
            <?php esc_html_e('Fix Theme on Existing Sites', 'rechat-plugin'); ?>
        </button>

        <button
            id="rch-multisite-reassign-agent-roles-btn"
            type="button"
            class="button"
            style="margin-left:8px;"
            data-nonce="<?php echo esc_attr($reassign_roles_nonce); ?>"
        >
            <?php esc_html_e('Reassign agent sub-site role for all agents', 'rechat-plugin'); ?>
        </button>

        <span id="rch-multisite-provision-spinner" class="spinner" style="float:none;margin-top:4px;"></span>

        <div id="rch-multisite-provision-result" style="margin-top:12px;"></div>

        <p class="description" style="max-width:900px;margin-top:10px;">
            <?php esc_html_e('“Reassign agent sub-site role for all agents” walks every agent profile that has a sub-site and a valid email, finds the WordPress user with that email, and sets their role on that sub-site to the current agent role (e.g. Agent). No login emails are sent. Use this after changing role names or capabilities.', 'rechat-plugin'); ?>
        </p>

        <p style="margin-top:16px;margin-bottom:6px;">
            <strong><?php esc_html_e('Bulk apply theme (agents vs offices)', 'rechat-plugin'); ?></strong>
        </p>
        <p class="description" style="max-width:720px;">
            <?php esc_html_e('Each row uses its own default theme dropdown above. You do not need to save the form first. Only sites that already exist are updated.', 'rechat-plugin'); ?>
        </p>
        <p style="margin-top:10px;">
            <button
                type="button"
                id="rch-multisite-bulk-theme-agents-btn"
                class="button button-secondary"
                data-nonce="<?php echo esc_attr($bulk_theme_nonce); ?>"
            >
                <?php esc_html_e('Apply theme to all agent sub-sites', 'rechat-plugin'); ?>
            </button>
            <span id="rch-multisite-bulk-theme-agents-spinner" class="spinner" style="float:none;margin-top:4px;"></span>
        </p>
        <div id="rch-multisite-bulk-theme-agents-result" style="margin-top:8px;margin-bottom:16px;"></div>
        <p>
            <button
                type="button"
                id="rch-multisite-bulk-theme-offices-btn"
                class="button button-secondary"
                data-nonce="<?php echo esc_attr($bulk_theme_nonce); ?>"
            >
                <?php esc_html_e('Apply theme to all office sub-sites', 'rechat-plugin'); ?>
            </button>
            <span id="rch-multisite-bulk-theme-offices-spinner" class="spinner" style="float:none;margin-top:4px;"></span>
        </p>
        <div id="rch-multisite-bulk-theme-offices-result" style="margin-top:8px;"></div>

        <hr>

        <?php /* ── Status table ─────────────────────────────────────────── */ ?>
        <h3><?php esc_html_e('Agent Site Status', 'rechat-plugin'); ?></h3>
        <p class="description" style="max-width:900px;margin-bottom:10px;">
            <?php esc_html_e('Use the Theme column to override the network default for one agent. Changes apply immediately when a sub-site already exists; otherwise they apply when the site is created. “Update editor” creates or re-adds the WordPress user from the agent email, ensures the correct sub-site role, and sends the login email again (no duplicate user if they already exist). To change roles without emailing everyone, use “Reassign agent sub-site role for all agents” above.', 'rechat-plugin'); ?>
        </p>

        <?php if (empty($agents)) : ?>
            <p><?php esc_html_e('No agent posts found.', 'rechat-plugin'); ?></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:1100px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Agent', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Site URL', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Blog ID', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Site Status', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Theme for this sub-site', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Enable / Disable', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Editor account', 'rechat-plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agents as $agent) :
                        $blog_id      = rch_multisite_get_agent_blog_id($agent->ID);
                        $slug         = (string) get_post_meta($agent->ID, '_rch_agent_slug', true);
                        $enabled      = rch_multisite_is_agent_site_enabled($agent->ID);
                        $row_subtheme = (string) get_post_meta($agent->ID, '_rch_subsite_theme_stylesheet', true);

                        if ($slug) {
                            $loc      = rch_multisite_build_site_location($slug);
                            $site_url = $blog_id
                                ? get_site_url($blog_id)
                                : 'https://' . $loc['domain'] . rtrim($loc['path'], '/');
                        } else {
                            $preview_slug = rch_multisite_sanitize_slug($agent->post_title);
                            if ($preview_slug) {
                                $loc      = rch_multisite_build_site_location($preview_slug);
                                $site_url = 'https://' . $loc['domain'] . rtrim($loc['path'], '/');
                            } else {
                                $site_url = '';
                            }
                        }

                        $has_site = (bool) $blog_id;
                    ?>
                        <tr id="rch-agent-row-<?php echo esc_attr((string) $agent->ID); ?>">
                            <td>
                                <a href="<?php echo esc_url((string) get_edit_post_link($agent->ID)); ?>">
                                    <?php echo esc_html($agent->post_title); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($has_site) : ?>
                                    <a href="<?php echo esc_url($site_url); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html($site_url); ?>
                                    </a>
                                <?php elseif ($site_url) : ?>
                                    <code><?php echo esc_html($site_url); ?></code>
                                    <em style="color:#888;"> (<?php esc_html_e('not yet created', 'rechat-plugin'); ?>)</em>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="rch-blog-id">
                                <?php echo $has_site ? esc_html((string) $blog_id) : '—'; ?>
                            </td>
                            <td class="rch-site-status">
                                <?php if (! $enabled) : ?>
                                    <span style="color:#888;">&#9632; <?php esc_html_e('Disabled', 'rechat-plugin'); ?></span>
                                <?php elseif ($has_site) : ?>
                                    <span style="color:#46b450;">&#10003; <?php esc_html_e('Active', 'rechat-plugin'); ?></span>
                                <?php else : ?>
                                    <span style="color:#f0a500;">&#9679; <?php esc_html_e('Pending', 'rechat-plugin'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select
                                    class="rch-row-theme-select"
                                    data-post-id="<?php echo esc_attr((string) $agent->ID); ?>"
                                    data-post-type="agents"
                                    data-nonce="<?php echo esc_attr($row_theme_nonce); ?>"
                                    style="max-width:min(100%,260px);"
                                >
                                    <option value="" <?php selected($row_subtheme, ''); ?>>
                                        <?php esc_html_e('Network default', 'rechat-plugin'); ?>
                                    </option>
                                    <?php foreach ($theme_choices as $slug_opt => $label) : ?>
                                        <option value="<?php echo esc_attr($slug_opt); ?>" <?php selected($row_subtheme, $slug_opt); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="rch-row-theme-feedback" style="display:inline-block;margin-left:6px;min-width:52px;font-size:12px;color:#46b450;" aria-live="polite"></span>
                            </td>
                            <td>
                                <?php if ($enabled) : ?>
                                    <button
                                        type="button"
                                        class="button rch-toggle-agent-site"
                                        data-post-id="<?php echo esc_attr((string) $agent->ID); ?>"
                                        data-enable="0"
                                        data-nonce="<?php echo esc_attr($toggle_nonce); ?>"
                                        style="color:#dc3232;border-color:#dc3232;"
                                    >
                                        <?php esc_html_e('Disable Site', 'rechat-plugin'); ?>
                                    </button>
                                <?php else : ?>
                                    <button
                                        type="button"
                                        class="button button-primary rch-toggle-agent-site"
                                        data-post-id="<?php echo esc_attr((string) $agent->ID); ?>"
                                        data-enable="1"
                                        data-nonce="<?php echo esc_attr($toggle_nonce); ?>"
                                        <?php disabled(! $create_sites); ?>
                                    >
                                        <?php esc_html_e('Enable Site', 'rechat-plugin'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($has_site) : ?>
                                    <button
                                        type="button"
                                        class="button button-secondary rch-reprovision-agent-editor"
                                        data-post-id="<?php echo esc_attr((string) $agent->ID); ?>"
                                        data-nonce="<?php echo esc_attr($reprovision_nonce); ?>"
                                    >
                                        <?php esc_html_e('Update editor', 'rechat-plugin'); ?>
                                    </button>
                                    <span class="rch-reprovision-editor-feedback" style="display:block;margin-top:4px;font-size:12px;color:#646970;" aria-live="polite"></span>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <hr>

        <h3><?php esc_html_e('Office Site Status', 'rechat-plugin'); ?></h3>
        <p class="description" style="max-width:900px;margin-bottom:10px;">
            <?php esc_html_e('Same as agents: pick a theme per office or leave Network default.', 'rechat-plugin'); ?>
        </p>

        <?php if (empty($offices)) : ?>
            <p><?php esc_html_e('No office posts found.', 'rechat-plugin'); ?></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:1100px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Office', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Site URL', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Blog ID', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Site Status', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Theme for this sub-site', 'rechat-plugin'); ?></th>
                        <th><?php esc_html_e('Enable / Disable', 'rechat-plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offices as $office) :
                        $blog_id_o      = rch_multisite_get_office_blog_id($office->ID);
                        $slug_o         = (string) get_post_meta($office->ID, '_rch_office_slug', true);
                        $enabled_o      = rch_multisite_is_office_site_enabled($office->ID);
                        $row_subtheme_o = (string) get_post_meta($office->ID, '_rch_subsite_theme_stylesheet', true);

                        if ($slug_o) {
                            $loc_o      = rch_multisite_build_site_location($slug_o);
                            $site_url_o = $blog_id_o
                                ? get_site_url($blog_id_o)
                                : 'https://' . $loc_o['domain'] . rtrim($loc_o['path'], '/');
                        } else {
                            $preview_o = rch_multisite_office_public_slug($office->post_title);
                            if ($preview_o) {
                                $loc_o      = rch_multisite_build_site_location($preview_o);
                                $site_url_o = 'https://' . $loc_o['domain'] . rtrim($loc_o['path'], '/');
                            } else {
                                $site_url_o = '';
                            }
                        }

                        $has_site_o = (bool) $blog_id_o;
                    ?>
                        <tr id="rch-office-row-<?php echo esc_attr((string) $office->ID); ?>">
                            <td>
                                <a href="<?php echo esc_url((string) get_edit_post_link($office->ID)); ?>">
                                    <?php echo esc_html($office->post_title); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($has_site_o) : ?>
                                    <a href="<?php echo esc_url($site_url_o); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html($site_url_o); ?>
                                    </a>
                                <?php elseif ($site_url_o) : ?>
                                    <code><?php echo esc_html($site_url_o); ?></code>
                                    <em style="color:#888;"> (<?php esc_html_e('not yet created', 'rechat-plugin'); ?>)</em>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="rch-blog-id">
                                <?php echo $has_site_o ? esc_html((string) $blog_id_o) : '—'; ?>
                            </td>
                            <td class="rch-site-status">
                                <?php if (! $enabled_o) : ?>
                                    <span style="color:#888;">&#9632; <?php esc_html_e('Disabled', 'rechat-plugin'); ?></span>
                                <?php elseif ($has_site_o) : ?>
                                    <span style="color:#46b450;">&#10003; <?php esc_html_e('Active', 'rechat-plugin'); ?></span>
                                <?php else : ?>
                                    <span style="color:#f0a500;">&#9679; <?php esc_html_e('Pending', 'rechat-plugin'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select
                                    class="rch-row-theme-select"
                                    data-post-id="<?php echo esc_attr((string) $office->ID); ?>"
                                    data-post-type="offices"
                                    data-nonce="<?php echo esc_attr($row_theme_nonce); ?>"
                                    style="max-width:min(100%,260px);"
                                >
                                    <option value="" <?php selected($row_subtheme_o, ''); ?>>
                                        <?php esc_html_e('Network default', 'rechat-plugin'); ?>
                                    </option>
                                    <?php foreach ($theme_choices as $slug_opt => $label) : ?>
                                        <option value="<?php echo esc_attr($slug_opt); ?>" <?php selected($row_subtheme_o, $slug_opt); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="rch-row-theme-feedback" style="display:inline-block;margin-left:6px;min-width:52px;font-size:12px;color:#46b450;" aria-live="polite"></span>
                            </td>
                            <td>
                                <?php if ($enabled_o) : ?>
                                    <button
                                        type="button"
                                        class="button rch-toggle-agent-site"
                                        data-post-id="<?php echo esc_attr((string) $office->ID); ?>"
                                        data-enable="0"
                                        data-nonce="<?php echo esc_attr($toggle_nonce); ?>"
                                        style="color:#dc3232;border-color:#dc3232;"
                                    >
                                        <?php esc_html_e('Disable Site', 'rechat-plugin'); ?>
                                    </button>
                                <?php else : ?>
                                    <button
                                        type="button"
                                        class="button button-primary rch-toggle-agent-site"
                                        data-post-id="<?php echo esc_attr((string) $office->ID); ?>"
                                        data-enable="1"
                                        data-nonce="<?php echo esc_attr($toggle_nonce); ?>"
                                        <?php disabled(! $create_offices); ?>
                                    >
                                        <?php esc_html_e('Enable Site', 'rechat-plugin'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div><!-- .tab-content -->

    <script>
    (function ($) {
        'use strict';

        // ── Per-row theme (status tables) ───────────────────────────────────────
        $(document).on('change', '.rch-row-theme-select', function () {
            var $sel = $(this);
            var $fb  = $sel.next('.rch-row-theme-feedback');

            $fb.text('').css('color', '#46b450');
            $sel.prop('disabled', true);

            $.post(ajaxurl, {
                action:    'rch_multisite_save_row_theme',
                _nonce:    $sel.attr('data-nonce'),
                post_id:   $sel.attr('data-post-id'),
                post_type: $sel.attr('data-post-type'),
                theme:     $sel.val()
            }, function (response) {
                $sel.prop('disabled', false);
                if (response.success) {
                    $fb.text('<?php echo esc_js(__('Saved.', 'rechat-plugin')); ?>');
                    setTimeout(function () { $fb.text(''); }, 2500);
                } else {
                    $fb.text(response.data || '<?php echo esc_js(__('Error', 'rechat-plugin')); ?>').css('color', '#d63638');
                }
            }).fail(function () {
                $sel.prop('disabled', false);
                $fb.text('<?php echo esc_js(__('Request failed.', 'rechat-plugin')); ?>').css('color', '#d63638');
            });
        });

        // ── Bulk provision ─────────────────────────────────────────────────────
        $('#rch-multisite-provision-btn').on('click', function () {
            var $btn     = $(this);
            var $spinner = $('#rch-multisite-provision-spinner');
            var $result  = $('#rch-multisite-provision-result');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.html('');

            $.post(ajaxurl, {
                action: 'rch_multisite_provision_all',
                _nonce: $btn.data('nonce'),
            }, function (response) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (response.success) {
                    var d    = response.data;
                    var html = '<div class="notice notice-success inline"><p>' + d.message + '</p>';

                    if (d.errors && d.errors.length) {
                        html += '<p><strong><?php echo esc_js(__('Errors:', 'rechat-plugin')); ?></strong></p><ul>';
                        $.each(d.errors, function (i, err) { html += '<li>' + err + '</li>'; });
                        html += '</ul>';
                    }

                    html += '</div>';
                    $result.html(html);

                    // Reload so the status table reflects the new sites.
                    setTimeout(function () { window.location.reload(); }, 2500);
                } else {
                    $result.html(
                        '<div class="notice notice-error inline"><p>' +
                        (response.data || '<?php echo esc_js(__('An error occurred.', 'rechat-plugin')); ?>') +
                        '</p></div>'
                    );
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                $result.html(
                    '<div class="notice notice-error inline"><p><?php echo esc_js(__('Request failed. Please try again.', 'rechat-plugin')); ?></p></div>'
                );
            });
        });

        // ── Fix themes on existing sites ───────────────────────────────────────
        $('#rch-multisite-fix-themes-btn').on('click', function () {
            var $btn     = $(this);
            var $spinner = $('#rch-multisite-provision-spinner');
            var $result  = $('#rch-multisite-provision-result');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.html('');

            $.post(ajaxurl, {
                action: 'rch_multisite_fix_themes',
                _nonce: $btn.data('nonce'),
            }, function (response) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (response.success) {
                    $result.html(
                        '<div class="notice notice-success inline"><p>' +
                        response.data.message + '</p></div>'
                    );
                } else {
                    $result.html(
                        '<div class="notice notice-error inline"><p>' +
                        (response.data || '<?php echo esc_js(__('An error occurred.', 'rechat-plugin')); ?>') +
                        '</p></div>'
                    );
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                $result.html(
                    '<div class="notice notice-error inline"><p><?php echo esc_js(__('Request failed. Please try again.', 'rechat-plugin')); ?></p></div>'
                );
            });
        });

        // ── Bulk reassign agent sub-site role (no emails) ─────────────────────
        $('#rch-multisite-reassign-agent-roles-btn').on('click', function () {
            var $btn     = $(this);
            var $spinner = $('#rch-multisite-provision-spinner');
            var $result  = $('#rch-multisite-provision-result');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.html('');

            $.post(ajaxurl, {
                action: 'rch_multisite_reassign_agent_site_user_roles',
                _nonce: $btn.data('nonce'),
            }, function (response) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (response.success) {
                    var d    = response.data;
                    var html = '<div class="notice notice-success inline"><p>' + d.message + '</p>';

                    if (d.errors && d.errors.length) {
                        html += '<p><strong><?php echo esc_js(__('Errors:', 'rechat-plugin')); ?></strong></p><ul>';
                        $.each(d.errors, function (i, err) {
                            html += '<li>' + err + '</li>';
                        });
                        html += '</ul>';
                    }

                    html += '</div>';
                    $result.html(html);
                } else {
                    $result.html(
                        '<div class="notice notice-error inline"><p>' +
                        (response.data || '<?php echo esc_js(__('An error occurred.', 'rechat-plugin')); ?>') +
                        '</p></div>'
                    );
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                $result.html(
                    '<div class="notice notice-error inline"><p><?php echo esc_js(__('Request failed. Please try again.', 'rechat-plugin')); ?></p></div>'
                );
            });
        });

        function rchBulkApplyTheme($btn, entity, themeSelector, $spin, $out) {
            $btn.prop('disabled', true);
            $spin.addClass('is-active');
            $out.html('');

            $.post(ajaxurl, {
                action: 'rch_multisite_bulk_apply_theme',
                _nonce:  $btn.data('nonce'),
                theme:   $(themeSelector).val(),
                entity:  entity
            }, function (response) {
                $btn.prop('disabled', false);
                $spin.removeClass('is-active');

                if (response.success) {
                    var d    = response.data;
                    var html = '<div class="notice notice-success inline"><p>' + d.message + '</p>';
                    if (d.errors && d.errors.length) {
                        html += '<p><strong><?php echo esc_js(__('Warnings:', 'rechat-plugin')); ?></strong></p><ul>';
                        $.each(d.errors, function (i, err) { html += '<li>' + err + '</li>'; });
                        html += '</ul>';
                    }
                    html += '</div>';
                    $out.html(html);
                } else {
                    $out.html(
                        '<div class="notice notice-error inline"><p>' +
                        (response.data || '<?php echo esc_js(__('An error occurred.', 'rechat-plugin')); ?>') +
                        '</p></div>'
                    );
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $spin.removeClass('is-active');
                $out.html(
                    '<div class="notice notice-error inline"><p><?php echo esc_js(__('Request failed. Please try again.', 'rechat-plugin')); ?></p></div>'
                );
            });
        }

        $('#rch-multisite-bulk-theme-agents-btn').on('click', function () {
            rchBulkApplyTheme(
                $(this),
                'agents',
                '#rch-multisite-agent-theme',
                $('#rch-multisite-bulk-theme-agents-spinner'),
                $('#rch-multisite-bulk-theme-agents-result')
            );
        });

        $('#rch-multisite-bulk-theme-offices-btn').on('click', function () {
            rchBulkApplyTheme(
                $(this),
                'offices',
                '#rch-multisite-office-theme',
                $('#rch-multisite-bulk-theme-offices-spinner'),
                $('#rch-multisite-bulk-theme-offices-result')
            );
        });

        // ── Per-agent enable / disable toggle ──────────────────────────────────
        $(document).on('click', '.rch-toggle-agent-site', function () {
            var $btn    = $(this);
            var postId  = $btn.data('post-id');
            var enable  = parseInt($btn.data('enable'), 10);
            var nonce   = $btn.data('nonce');

            $btn.prop('disabled', true).text('<?php echo esc_js(__('Updating…', 'rechat-plugin')); ?>');

            $.post(ajaxurl, {
                action:  'rch_multisite_toggle_agent_site',
                _nonce:  nonce,
                post_id: postId,
                enable:  enable,
            }, function (response) {
                if (response.success) {
                    var d       = response.data;
                    var $row    = $('#rch-agent-row-' + postId);
                    if (!$row.length) {
                        $row = $('#rch-office-row-' + postId);
                    }
                    var $status = $row.find('.rch-site-status');
                    var $blogId = $row.find('.rch-blog-id');

                    if (d.enabled) {
                        $status.html('<span style="color:#46b450;">&#10003; <?php echo esc_js(__('Active', 'rechat-plugin')); ?></span>');
                        $btn
                            .removeClass('button-primary')
                            .css({ color: '#dc3232', 'border-color': '#dc3232' })
                            .text('<?php echo esc_js(__('Disable Site', 'rechat-plugin')); ?>')
                            .data('enable', 0);
                    } else {
                        $status.html('<span style="color:#888;">&#9632; <?php echo esc_js(__('Disabled', 'rechat-plugin')); ?></span>');
                        $btn
                            .addClass('button-primary')
                            .css({ color: '', 'border-color': '' })
                            .text('<?php echo esc_js(__('Enable Site', 'rechat-plugin')); ?>')
                            .data('enable', 1);
                    }

                    if (d.blog_id) {
                        $blogId.text(d.blog_id);
                    }

                    $btn.prop('disabled', false);
                } else {
                    $btn.prop('disabled', false).text(
                        enable ? '<?php echo esc_js(__('Enable Site', 'rechat-plugin')); ?>'
                               : '<?php echo esc_js(__('Disable Site', 'rechat-plugin')); ?>'
                    );
                    alert(response.data || '<?php echo esc_js(__('An error occurred.', 'rechat-plugin')); ?>');
                }
            }).fail(function () {
                $btn.prop('disabled', false).text(
                    enable ? '<?php echo esc_js(__('Enable Site', 'rechat-plugin')); ?>'
                           : '<?php echo esc_js(__('Disable Site', 'rechat-plugin')); ?>'
                );
                alert('<?php echo esc_js(__('Request failed. Please try again.', 'rechat-plugin')); ?>');
            });
        });

        // ── Update editor user (provision / re-email) ─────────────────────────
        $(document).on('click', '.rch-reprovision-agent-editor', function () {
            var $btn   = $(this);
            var postId = $btn.data('post-id');
            var nonce  = $btn.data('nonce');
            var $fb    = $btn.closest('td').find('.rch-reprovision-editor-feedback');

            $btn.prop('disabled', true);
            $fb.text('<?php echo esc_js(__('Sending…', 'rechat-plugin')); ?>');

            $.post(ajaxurl, {
                action:  'rch_multisite_reprovision_agent_editor',
                _nonce:  nonce,
                post_id: postId,
            }, function (response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $fb.css('color', '#00a32a').text(response.data.message || '<?php echo esc_js(__('Done.', 'rechat-plugin')); ?>');
                } else {
                    $fb.css('color', '#d63638').text(response.data || '<?php echo esc_js(__('An error occurred.', 'rechat-plugin')); ?>');
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $fb.css('color', '#d63638').text('<?php echo esc_js(__('Request failed. Please try again.', 'rechat-plugin')); ?>');
            });
        });

    }(jQuery));
    </script>
    <?php
}
