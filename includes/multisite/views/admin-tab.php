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
    $agent_slug_format = rch_multisite_get_agent_slug_format();
    $network         = get_network();
    $base_domain     = preg_replace('/^www\./i', '', $network->domain);
    $network_path    = trailingslashit($network->path); // e.g. '/rechat-plugin/'
    $provision_nonce    = wp_create_nonce('rch_multisite_provision_all');
    $toggle_nonce       = wp_create_nonce('rch_multisite_toggle_agent');
    $reprovision_nonce  = wp_create_nonce('rch_multisite_reprovision_editor');
    $bulk_theme_nonce   = wp_create_nonce('rch_multisite_bulk_theme');
    $row_theme_nonce   = wp_create_nonce('rch_multisite_row_theme');
    $reassign_roles_nonce = wp_create_nonce('rch_multisite_reassign_agent_roles');
    $resync_themes_nonce   = wp_create_nonce('rch_multisite_resync_themes');
    $fix_themes_nonce      = wp_create_nonce('rch_multisite_fix_themes');
    $migrate_urls_nonce    = wp_create_nonce('rch_multisite_migrate_agent_urls');
    $dedupe_cleanup_nonce  = wp_create_nonce('rch_multisite_subsite_dedupe');
    $saved_agent_theme  = (string) get_site_option('rch_multisite_agent_theme_stylesheet', '');
    $saved_office_theme = (string) get_site_option('rch_multisite_office_theme_stylesheet', '');
    $theme_choices      = rch_multisite_get_theme_choices();

    // Detect the WordPress network install type.
    $wp_subdomain_install = defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL;

    // URL pattern examples for the helper text.
    $example_subdomain    = 'john.' . $base_domain;
    $example_subdirectory = $base_domain . rtrim($network_path, '/') . '/john';
    $example_agent_initial_lastname = 'afreeman.' . $base_domain;
    $example_agent_firstname_lastname = 'amy-freeman.' . $base_domain;

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

    $tools_enabled = ($create_sites || $create_offices);

    ?>
    <div class="tab-content rch-ms">

        <div class="rch-tab-intro">
            <h2>
                <span class="dashicons dashicons-networking" aria-hidden="true"></span>
                <?php esc_html_e('Multisite – Agent & Office Sites', 'rechat-plugin'); ?>
            </h2>
            <p>
                <?php esc_html_e('Each agent and office can have its own WordPress sub-site. Configure how those sites are named and themed below, then use the action cards to create, theme, and maintain them. Per-site overrides live in the status tables at the bottom.', 'rechat-plugin'); ?>
            </p>
        </div>

        <?php settings_errors('rch_multisite'); ?>

        <?php if (function_exists('rch_multisite_subsites_require_broadcast') && rch_multisite_subsites_require_broadcast() && function_exists('rch_multisite_broadcast_plugin_active') && ! rch_multisite_broadcast_plugin_active()) : ?>
            <div class="notice notice-error inline" style="max-width:1100px;">
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

        <?php /* ── Settings card ─────────────────────────────────────────── */ ?>
        <div class="rch-card">
            <div class="rch-card__head">
                <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                <h3><?php esc_html_e('Network settings', 'rechat-plugin'); ?></h3>
            </div>
            <div class="rch-card__body">
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
                                    <?php esc_html_e('Used for new agent sites, for “Sync sub-site themes” (each row: network default or override), and for “Apply theme to all agent sub-sites” when that action uses the dropdown value. Save Multisite Settings before syncing so the saved default is applied. Enable themes in Network Admin → Themes if a theme does not activate.', 'rechat-plugin'); ?>
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

                        <tr valign="top">
                            <th scope="row">
                                <?php esc_html_e('Agent sub-site URL slug', 'rechat-plugin'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label style="display:block;margin-bottom:6px;">
                                        <input
                                            type="radio"
                                            name="rch_multisite_agent_slug_format"
                                            value="initial_lastname"
                                            <?php checked($agent_slug_format, 'initial_lastname'); ?>
                                        >
                                        <?php esc_html_e('First initial + last name', 'rechat-plugin'); ?>
                                        &nbsp;<code><?php echo esc_html($example_agent_initial_lastname); ?></code>
                                    </label>
                                    <label style="display:block;">
                                        <input
                                            type="radio"
                                            name="rch_multisite_agent_slug_format"
                                            value="firstname_lastname"
                                            <?php checked($agent_slug_format, 'firstname_lastname'); ?>
                                        >
                                        <?php esc_html_e('First name + last name', 'rechat-plugin'); ?>
                                        &nbsp;<code><?php echo esc_html($example_agent_firstname_lastname); ?></code>
                                    </label>
                                </fieldset>
                                <p class="description" style="margin-top:6px;">
                                    <?php esc_html_e('Controls how agent sub-site URLs are generated for new sites, sync, and the “Migrate agent sub-site URLs” action. Default is first initial + last name. Changing this does not rename existing sites until you run the migration action.', 'rechat-plugin'); ?>
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
            </div>
        </div>

        <?php if (! $tools_enabled) : ?>
            <div class="notice notice-warning inline" style="max-width:1100px;margin:16px 0;">
                <p>
                    <?php esc_html_e('Agent and office sub-site creation are both turned off above. Enable at least one and save to use the maintenance tools below.', 'rechat-plugin'); ?>
                </p>
            </div>
        <?php endif; ?>

        <?php /* ── Provisioning actions ─────────────────────────────────── */ ?>
        <h3 class="rch-section-title">
            <span class="dashicons dashicons-superhero" aria-hidden="true"></span>
            <?php esc_html_e('Create & maintain sites', 'rechat-plugin'); ?>
        </h3>
        <p class="rch-section-sub">
            <?php esc_html_e('Run these after adding agents/offices or changing the settings above. All are safe to run more than once.', 'rechat-plugin'); ?>
        </p>

        <div class="rch-action-grid">

            <div class="rch-action rch-action--primary">
                <div class="rch-action__icon"><span class="dashicons dashicons-plus-alt" aria-hidden="true"></span></div>
                <div class="rch-action__main">
                    <h4 class="rch-action__title"><?php esc_html_e('Provision all sites', 'rechat-plugin'); ?></h4>
                    <p class="rch-action__desc">
                        <?php esc_html_e('Creates any missing sub-sites for enabled agents and offices and updates their titles. Disabled entries are skipped. Start here.', 'rechat-plugin'); ?>
                    </p>
                    <div class="rch-action__foot">
                        <button
                            id="rch-multisite-provision-btn"
                            type="button"
                            class="button button-primary"
                            data-nonce="<?php echo esc_attr($provision_nonce); ?>"
                            <?php disabled(! $tools_enabled); ?>
                        >
                            <?php esc_html_e('Provision all agent & office sites', 'rechat-plugin'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>
                    <div class="rch-action__result"></div>
                </div>
            </div>

            <div class="rch-action">
                <div class="rch-action__icon"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span></div>
                <div class="rch-action__main">
                    <h4 class="rch-action__title"><?php esc_html_e('Migrate agent URLs', 'rechat-plugin'); ?></h4>
                    <p class="rch-action__desc">
                        <?php esc_html_e('Renames existing agent sub-sites to match the “Agent sub-site URL slug” format selected above. Save settings first. Changes live subdomain URLs.', 'rechat-plugin'); ?>
                    </p>
                    <div class="rch-action__foot">
                        <button
                            id="rch-multisite-migrate-agent-urls-btn"
                            type="button"
                            class="button"
                            data-nonce="<?php echo esc_attr($migrate_urls_nonce); ?>"
                            data-confirm="<?php esc_attr_e('Rename all existing agent sub-sites to match the selected slug format? This changes subdomain URLs across the network.', 'rechat-plugin'); ?>"
                        >
                            <?php esc_html_e('Migrate agent sub-site URLs', 'rechat-plugin'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>
                    <div class="rch-action__result"></div>
                </div>
            </div>

        </div>

        <?php /* ── Theme actions ────────────────────────────────────────── */ ?>
        <h3 class="rch-section-title">
            <span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
            <?php esc_html_e('Themes', 'rechat-plugin'); ?>
        </h3>
        <p class="rch-section-sub">
            <?php esc_html_e('Push the default themes (set above) onto existing sub-sites. Set per-site exceptions in the status tables, then use “Sync” to apply everyone at once.', 'rechat-plugin'); ?>
        </p>

        <div class="rch-action-grid">

            <div class="rch-action">
                <div class="rch-action__icon"><span class="dashicons dashicons-update" aria-hidden="true"></span></div>
                <div class="rch-action__main">
                    <h4 class="rch-action__title"><?php esc_html_e('Sync sub-site themes', 'rechat-plugin'); ?></h4>
                    <p class="rch-action__desc">
                        <?php esc_html_e('Re-applies the resolved theme to every linked agent and office site: the per-row override if set, otherwise the network default. Use after changing defaults or row overrides.', 'rechat-plugin'); ?>
                    </p>
                    <div class="rch-action__foot">
                        <button
                            id="rch-multisite-resync-themes-btn"
                            type="button"
                            class="button button-secondary"
                            data-nonce="<?php echo esc_attr($resync_themes_nonce); ?>"
                        >
                            <?php esc_html_e('Sync themes (defaults + overrides)', 'rechat-plugin'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>
                    <div class="rch-action__result"></div>
                </div>
            </div>

            <div class="rch-action">
                <div class="rch-action__icon"><span class="dashicons dashicons-hammer" aria-hidden="true"></span></div>
                <div class="rch-action__main">
                    <h4 class="rch-action__title"><?php esc_html_e('Fix missing themes', 'rechat-plugin'); ?></h4>
                    <p class="rch-action__desc">
                        <?php esc_html_e('Only fills sub-sites that have no theme yet (empty template). Sites that already activated a theme are left untouched.', 'rechat-plugin'); ?>
                    </p>
                    <div class="rch-action__foot">
                        <button
                            id="rch-multisite-fix-themes-btn"
                            type="button"
                            class="button"
                            data-nonce="<?php echo esc_attr($fix_themes_nonce); ?>"
                        >
                            <?php esc_html_e('Fix theme on existing sites', 'rechat-plugin'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>
                    <div class="rch-action__result"></div>
                </div>
            </div>

            <div class="rch-action">
                <div class="rch-action__icon"><span class="dashicons dashicons-groups" aria-hidden="true"></span></div>
                <div class="rch-action__main">
                    <h4 class="rch-action__title"><?php esc_html_e('Force theme on all agents', 'rechat-plugin'); ?></h4>
                    <p class="rch-action__desc">
                        <?php esc_html_e('Forces the “Default theme for agent sub-sites” onto every agent site, ignoring per-row overrides. No need to save the form first.', 'rechat-plugin'); ?>
                    </p>
                    <div class="rch-action__foot">
                        <button
                            type="button"
                            id="rch-multisite-bulk-theme-agents-btn"
                            class="button"
                            data-entity="agents"
                            data-theme-select="#rch-multisite-agent-theme"
                            data-nonce="<?php echo esc_attr($bulk_theme_nonce); ?>"
                        >
                            <?php esc_html_e('Apply to all agent sub-sites', 'rechat-plugin'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>
                    <div class="rch-action__result"></div>
                </div>
            </div>

            <div class="rch-action">
                <div class="rch-action__icon"><span class="dashicons dashicons-building" aria-hidden="true"></span></div>
                <div class="rch-action__main">
                    <h4 class="rch-action__title"><?php esc_html_e('Force theme on all offices', 'rechat-plugin'); ?></h4>
                    <p class="rch-action__desc">
                        <?php esc_html_e('Forces the “Default theme for office sub-sites” onto every office site, ignoring per-row overrides. No need to save the form first.', 'rechat-plugin'); ?>
                    </p>
                    <div class="rch-action__foot">
                        <button
                            type="button"
                            id="rch-multisite-bulk-theme-offices-btn"
                            class="button"
                            data-entity="offices"
                            data-theme-select="#rch-multisite-office-theme"
                            data-nonce="<?php echo esc_attr($bulk_theme_nonce); ?>"
                        >
                            <?php esc_html_e('Apply to all office sub-sites', 'rechat-plugin'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>
                    <div class="rch-action__result"></div>
                </div>
            </div>

        </div>

        <?php /* ── Users & roles ────────────────────────────────────────── */ ?>
        <h3 class="rch-section-title">
            <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
            <?php esc_html_e('Users & roles', 'rechat-plugin'); ?>
        </h3>

        <div class="rch-action-grid">

            <div class="rch-action">
                <div class="rch-action__icon"><span class="dashicons dashicons-id" aria-hidden="true"></span></div>
                <div class="rch-action__main">
                    <h4 class="rch-action__title"><?php esc_html_e('Reassign agent roles', 'rechat-plugin'); ?></h4>
                    <p class="rch-action__desc">
                        <?php esc_html_e('For every agent with a sub-site and valid email, sets the matching WordPress user to the current agent role, copies main-site Local Logic & Google Map API keys when empty, and enables Local Content. No login emails are sent. Use after changing role names or capabilities.', 'rechat-plugin'); ?>
                    </p>
                    <div class="rch-action__foot">
                        <button
                            id="rch-multisite-reassign-agent-roles-btn"
                            type="button"
                            class="button"
                            data-nonce="<?php echo esc_attr($reassign_roles_nonce); ?>"
                        >
                            <?php esc_html_e('Reassign role for all agents', 'rechat-plugin'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>
                    <div class="rch-action__result"></div>
                </div>
            </div>

        </div>

        <?php /* ── Danger zone ──────────────────────────────────────────── */ ?>
        <div class="rch-danger-zone">
            <h3>
                <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                <?php esc_html_e('Danger zone — remove duplicate sub-sites', 'rechat-plugin'); ?>
            </h3>
            <p class="rch-dz__desc">
                <?php esc_html_e('Keeps one sub-site per published agent and office on the hub (prefers the base URL, e.g. cmoreland not cmoreland2). Trashes duplicate hub agent posts that share the same Rechat API ID and permanently deletes extra network sites. Back up the network first. Preview is read-only — run “Preview” before the permanent cleanup.', 'rechat-plugin'); ?>
            </p>
            <div class="rch-dz__actions">
                <button
                    type="button"
                    id="rch-multisite-dedupe-preview-btn"
                    class="button button-secondary"
                    data-nonce="<?php echo esc_attr($dedupe_cleanup_nonce); ?>"
                >
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                    <?php esc_html_e('Preview duplicate cleanup', 'rechat-plugin'); ?>
                </button>
                <button
                    type="button"
                    id="rch-multisite-dedupe-run-btn"
                    class="button rch-btn-danger"
                    data-nonce="<?php echo esc_attr($dedupe_cleanup_nonce); ?>"
                    data-confirm="<?php esc_attr_e('Permanently delete duplicate agent/office sub-sites and trash duplicate hub agent posts? This cannot be undone. Continue?', 'rechat-plugin'); ?>"
                >
                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                    <?php esc_html_e('Run cleanup (permanent)', 'rechat-plugin'); ?>
                </button>
                <span class="spinner"></span>
            </div>
            <div class="rch-dz__result rch-action__result"></div>
        </div>

        <?php /* ── Agent status table ───────────────────────────────────── */ ?>
        <div class="rch-card">
            <div class="rch-card__head">
                <span class="dashicons dashicons-businessperson" aria-hidden="true"></span>
                <h3><?php esc_html_e('Agent site status', 'rechat-plugin'); ?></h3>
            </div>
            <div class="rch-card__body">
                <p class="description" style="margin-top:0;max-width:900px;">
                    <?php esc_html_e('Override the network default theme per agent in the Theme column — changes apply immediately if the site exists, otherwise on creation. “Update editor” creates or re-adds the WordPress user from the agent email, ensures the correct role, and re-sends the login email (no duplicate users). To change roles without emailing everyone, use “Reassign agent roles” above.', 'rechat-plugin'); ?>
                </p>

                <?php if (empty($agents)) : ?>
                    <p><?php esc_html_e('No agent posts found.', 'rechat-plugin'); ?></p>
                <?php else : ?>
                    <div class="rch-table-scroll">
                    <table class="widefat striped">
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
                                    $preview_slug = rch_multisite_agent_site_slug_base($agent->ID, $agent->post_title);
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
                                            <span class="rch-status rch-status--off">&#9632; <?php esc_html_e('Disabled', 'rechat-plugin'); ?></span>
                                        <?php elseif ($has_site) : ?>
                                            <span class="rch-status rch-status--on">&#10003; <?php esc_html_e('Active', 'rechat-plugin'); ?></span>
                                        <?php else : ?>
                                            <span class="rch-status rch-status--pending">&#9679; <?php esc_html_e('Pending', 'rechat-plugin'); ?></span>
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
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php /* ── Office status table ──────────────────────────────────── */ ?>
        <div class="rch-card">
            <div class="rch-card__head">
                <span class="dashicons dashicons-building" aria-hidden="true"></span>
                <h3><?php esc_html_e('Office site status', 'rechat-plugin'); ?></h3>
            </div>
            <div class="rch-card__body">
                <p class="description" style="margin-top:0;max-width:900px;">
                    <?php esc_html_e('Same as agents: pick a theme per office or leave Network default.', 'rechat-plugin'); ?>
                </p>

                <?php if (empty($offices)) : ?>
                    <p><?php esc_html_e('No office posts found.', 'rechat-plugin'); ?></p>
                <?php else : ?>
                    <div class="rch-table-scroll">
                    <table class="widefat striped">
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
                                            <span class="rch-status rch-status--off">&#9632; <?php esc_html_e('Disabled', 'rechat-plugin'); ?></span>
                                        <?php elseif ($has_site_o) : ?>
                                            <span class="rch-status rch-status--on">&#10003; <?php esc_html_e('Active', 'rechat-plugin'); ?></span>
                                        <?php else : ?>
                                            <span class="rch-status rch-status--pending">&#9679; <?php esc_html_e('Pending', 'rechat-plugin'); ?></span>
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
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- .tab-content -->

    <script>
    (function ($) {
        'use strict';

        var STR = {
            done:    <?php echo wp_json_encode(__('Done.', 'rechat-plugin')); ?>,
            error:   <?php echo wp_json_encode(__('An error occurred.', 'rechat-plugin')); ?>,
            failed:  <?php echo wp_json_encode(__('Request failed. Please try again.', 'rechat-plugin')); ?>,
            notes:   <?php echo wp_json_encode(__('Notes:', 'rechat-plugin')); ?>,
            saved:   <?php echo wp_json_encode(__('Saved.', 'rechat-plugin')); ?>,
            updating:<?php echo wp_json_encode(__('Updating…', 'rechat-plugin')); ?>,
            enable:  <?php echo wp_json_encode(__('Enable Site', 'rechat-plugin')); ?>,
            disable: <?php echo wp_json_encode(__('Disable Site', 'rechat-plugin')); ?>,
            active:  <?php echo wp_json_encode(__('Active', 'rechat-plugin')); ?>,
            disabled:<?php echo wp_json_encode(__('Disabled', 'rechat-plugin')); ?>,
            sending: <?php echo wp_json_encode(__('Sending…', 'rechat-plugin')); ?>
        };

        function renderResult($result, response) {
            if (response.success) {
                var d   = response.data;
                var msg = (d && typeof d === 'object' && d.message) ? d.message : (d || STR.done);
                var html = '<div class="notice notice-success inline"><p>' + msg + '</p>';
                if (d && d.detail) {
                    html += '<p>' + d.detail + '</p>';
                }
                if (d && d.errors && d.errors.length) {
                    html += '<p><strong>' + STR.notes + '</strong></p><ul>';
                    $.each(d.errors, function (i, err) { html += '<li>' + err + '</li>'; });
                    html += '</ul>';
                }
                html += '</div>';
                $result.html(html);
                return true;
            }
            $result.html(
                '<div class="notice notice-error inline"><p>' +
                (response.data || STR.error) + '</p></div>'
            );
            return false;
        }

        /**
         * Generic AJAX action runner. Reads nonce + optional confirm from the
         * button, writes feedback into the card's own result area, and toggles
         * the card spinner. opts: { data, reloadOnSuccess }.
         */
        function rchRunAction($btn, action, opts) {
            opts = opts || {};

            var confirmMsg = $btn.data('confirm');
            if (confirmMsg && ! window.confirm(confirmMsg)) {
                return;
            }

            var $card    = $btn.closest('.rch-action, .rch-danger-zone');
            var $result  = $card.find('.rch-action__result').first();
            var $spinner = $card.find('.spinner').first();

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.empty();

            var data = $.extend({ action: action, _nonce: $btn.data('nonce') }, opts.data || {});

            $.post(ajaxurl, data, function (response) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                var ok = renderResult($result, response);
                if (ok && opts.reloadOnSuccess) {
                    setTimeout(function () { window.location.reload(); }, 2500);
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                $result.html('<div class="notice notice-error inline"><p>' + STR.failed + '</p></div>');
            });
        }

        // ── Provisioning / maintenance actions ─────────────────────────────────
        $('#rch-multisite-provision-btn').on('click', function () {
            rchRunAction($(this), 'rch_multisite_provision_all', { reloadOnSuccess: true });
        });

        $('#rch-multisite-migrate-agent-urls-btn').on('click', function () {
            rchRunAction($(this), 'rch_multisite_migrate_agent_subsite_urls');
        });

        // ── Theme actions ──────────────────────────────────────────────────────
        $('#rch-multisite-fix-themes-btn').on('click', function () {
            rchRunAction($(this), 'rch_multisite_fix_themes');
        });

        $('#rch-multisite-resync-themes-btn').on('click', function () {
            rchRunAction($(this), 'rch_multisite_resync_themes');
        });

        $('#rch-multisite-bulk-theme-agents-btn, #rch-multisite-bulk-theme-offices-btn').on('click', function () {
            var $btn = $(this);
            rchRunAction($btn, 'rch_multisite_bulk_apply_theme', {
                data: {
                    entity: $btn.data('entity'),
                    theme:  $($btn.data('theme-select')).val()
                }
            });
        });

        // ── Users & roles ────────────────────────────────────────────────────
        $('#rch-multisite-reassign-agent-roles-btn').on('click', function () {
            rchRunAction($(this), 'rch_multisite_reassign_agent_site_user_roles');
        });

        // ── Danger zone: duplicate cleanup ─────────────────────────────────────
        $('#rch-multisite-dedupe-preview-btn').on('click', function () {
            rchRunAction($(this), 'rch_multisite_subsite_dedupe_cleanup', {
                data: { execute: '0', confirm: '' }
            });
        });

        $('#rch-multisite-dedupe-run-btn').on('click', function () {
            rchRunAction($(this), 'rch_multisite_subsite_dedupe_cleanup', {
                data: { execute: '1', confirm: 'yes' }
            });
        });

        // ── Per-row theme override (status tables) ─────────────────────────────
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
                    $fb.text(STR.saved);
                    setTimeout(function () { $fb.text(''); }, 2500);
                } else {
                    $fb.text(response.data || STR.error).css('color', '#d63638');
                }
            }).fail(function () {
                $sel.prop('disabled', false);
                $fb.text(STR.failed).css('color', '#d63638');
            });
        });

        // ── Per-agent enable / disable toggle ──────────────────────────────────
        $(document).on('click', '.rch-toggle-agent-site', function () {
            var $btn    = $(this);
            var postId  = $btn.data('post-id');
            var enable  = parseInt($btn.data('enable'), 10);
            var nonce   = $btn.data('nonce');

            $btn.prop('disabled', true).text(STR.updating);

            $.post(ajaxurl, {
                action:  'rch_multisite_toggle_agent_site',
                _nonce:  nonce,
                post_id: postId,
                enable:  enable
            }, function (response) {
                if (response.success) {
                    var d    = response.data;
                    var $row = $('#rch-agent-row-' + postId);
                    if (! $row.length) {
                        $row = $('#rch-office-row-' + postId);
                    }
                    var $status = $row.find('.rch-site-status');
                    var $blogId = $row.find('.rch-blog-id');

                    if (d.enabled) {
                        $status.html('<span class="rch-status rch-status--on">&#10003; ' + STR.active + '</span>');
                        $btn
                            .removeClass('button-primary')
                            .css({ color: '#dc3232', 'border-color': '#dc3232' })
                            .text(STR.disable)
                            .data('enable', 0);
                    } else {
                        $status.html('<span class="rch-status rch-status--off">&#9632; ' + STR.disabled + '</span>');
                        $btn
                            .addClass('button-primary')
                            .css({ color: '', 'border-color': '' })
                            .text(STR.enable)
                            .data('enable', 1);
                    }

                    if (d.blog_id) {
                        $blogId.text(d.blog_id);
                    }

                    $btn.prop('disabled', false);
                } else {
                    $btn.prop('disabled', false).text(enable ? STR.enable : STR.disable);
                    alert(response.data || STR.error);
                }
            }).fail(function () {
                $btn.prop('disabled', false).text(enable ? STR.enable : STR.disable);
                alert(STR.failed);
            });
        });

        // ── Update editor user (provision / re-email) ─────────────────────────
        $(document).on('click', '.rch-reprovision-agent-editor', function () {
            var $btn   = $(this);
            var postId = $btn.data('post-id');
            var nonce  = $btn.data('nonce');
            var $fb    = $btn.closest('td').find('.rch-reprovision-editor-feedback');

            $btn.prop('disabled', true);
            $fb.text(STR.sending);

            $.post(ajaxurl, {
                action:  'rch_multisite_reprovision_agent_editor',
                _nonce:  nonce,
                post_id: postId
            }, function (response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $fb.css('color', '#00a32a').text(response.data.message || STR.done);
                } else {
                    $fb.css('color', '#d63638').text(response.data || STR.error);
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $fb.css('color', '#d63638').text(STR.failed);
            });
        });

    }(jQuery));
    </script>
    <?php
}
