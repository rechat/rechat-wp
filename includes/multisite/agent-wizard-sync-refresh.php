<?php
/**
 * Keep agent sub-site theme options in sync with the hub agent post.
 *
 * The deploy wizard resolves dynamic tokens ({$post_title}, {$post_content}, {$phone_number}, …)
 * and meta-bound fields into literal values, then writes them into the sub-site's theme option
 * storage. That is a point-in-time snapshot: when a later Rechat API sync (or a manual admin edit)
 * changes the hub agent — e.g. the agent's bio — the sub-site keeps showing the stale value.
 *
 * This module re-runs the deployment *recipe* the wizard already persisted per sub-site
 * (`rch_agent_wizard_last_deployment.theme_rows`) against the fresh hub data, so every sub-site
 * repaints with current values. Only `meta` fields and `manual` templates containing `{$...}`
 * tokens change; `skip` fields and static manual text are left untouched, preserving any
 * editor customizations.
 *
 * @package rechat-plugin
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Fingerprint of every hub value that can flow into a sub-site through the wizard.
 *
 * Covers the full import source surface (post_title, post_content, and all importable meta),
 * which is exactly the data the deploy recipe reads. Used to skip re-deploying agents whose
 * bound data did not actually change on a sync.
 *
 * @param int $agent_id Hub agent post ID.
 * @return string md5 fingerprint (empty string when the agent id is invalid).
 */
function rch_agent_wizard_source_fields_hash(int $agent_id): string
{
    if ($agent_id <= 0 || ! function_exists('rch_agent_wizard_importable_field_defs')) {
        return '';
    }

    $keys = array_keys(rch_agent_wizard_importable_field_defs());
    sort($keys);

    $parts = [];
    foreach ($keys as $key) {
        $parts[ $key ] = rch_agent_wizard_get_import_source_value($agent_id, (string) $key);
    }

    return md5((string) wp_json_encode($parts));
}

/**
 * Re-run the last wizard deployment recipe for one agent against current hub data.
 *
 * Reuses the exact deploy path (token + meta resolution, sanitize, storage_primary + mirror
 * write, cache flush), so a redeploy is identical to the original deploy but with fresh values.
 * Runs with the capability check disabled because it is system-triggered (cron sync / save_post),
 * not a network-admin UI request.
 *
 * @param int $agent_id Hub agent post ID.
 * @return array|WP_Error Deploy result on success, or WP_Error (`rch_wizard_no_recipe` when the
 *                        agent's sub-site has never been deployed through the wizard).
 */
function rch_agent_wizard_redeploy_from_recipe(int $agent_id)
{
    if (! is_multisite()) {
        return new WP_Error('rch_wizard_not_multisite', __('Multisite is not enabled.', 'rechat-plugin'));
    }

    if (
        ! function_exists('rch_multisite_get_agent_blog_id')
        || ! function_exists('rch_agent_wizard_read_destination_last_deployment')
        || ! function_exists('rch_agent_wizard_deploy_to_agent_blog')
    ) {
        return new WP_Error('rch_wizard_missing', __('Wizard helpers are not loaded.', 'rechat-plugin'));
    }

    $blog_id = rch_multisite_get_agent_blog_id($agent_id);
    if (! $blog_id) {
        return new WP_Error('rch_wizard_no_blog', __('This agent has no linked sub-site.', 'rechat-plugin'));
    }

    $last = rch_agent_wizard_read_destination_last_deployment($blog_id);
    if (! is_array($last) || empty($last['theme_rows']) || ! is_array($last['theme_rows'])) {
        return new WP_Error('rch_wizard_no_recipe', __('This sub-site has never been deployed through the wizard.', 'rechat-plugin'));
    }

    return rch_agent_wizard_deploy_to_agent_blog($agent_id, $last['theme_rows'], false);
}

/**
 * Refresh the linked sub-site's dynamic theme options when the hub agent changes.
 *
 * Gated on a fingerprint of the import source values so unchanged agents cost only one hash
 * compute (no switch_to_blog / option writes) on every 12-hour sync. The fingerprint is stored
 * after a successful redeploy, or when there is simply nothing to refresh (no sub-site, no
 * recipe), so those cases are not re-attempted each sync. Transient failures are NOT recorded,
 * so they retry on the next run.
 *
 * @param int $agent_id Hub agent post ID.
 * @return void
 */
function rch_agent_wizard_refresh_subsite_after_agent_change(int $agent_id): void
{
    if ($agent_id <= 0 || ! is_multisite()) {
        return;
    }

    // Hub-only. Agent posts and the wizard recipe live on the network main site. If this ever
    // runs in a sub-site context (e.g. a stray sub-site cron), a numeric post ID means a
    // different post on the main site (an attachment, an acf-field, …), so resolving it would
    // write garbage into theme options. Never resolve/write off the main site.
    if (! is_main_site()) {
        return;
    }

    if (get_post_type($agent_id) !== 'agents') {
        return;
    }

    if (! function_exists('rch_multisite_get_agent_blog_id') || ! rch_multisite_get_agent_blog_id($agent_id)) {
        // No sub-site to keep in sync — record the fingerprint so we skip cheaply next time.
        update_post_meta($agent_id, '_rch_agent_wizard_source_hash', rch_agent_wizard_source_fields_hash($agent_id));
        return;
    }

    $new_hash = rch_agent_wizard_source_fields_hash($agent_id);
    $old_hash = get_post_meta($agent_id, '_rch_agent_wizard_source_hash', true);
    if (is_string($old_hash) && $old_hash !== '' && $old_hash === $new_hash) {
        return; // Bound hub data unchanged since the last refresh.
    }

    $result = rch_agent_wizard_redeploy_from_recipe($agent_id);

    // Record the fingerprint on success or when there is nothing to deploy; leave it unset on a
    // real error so the redeploy is retried on the next sync.
    $nothing_to_do = is_wp_error($result)
        && in_array($result->get_error_code(), ['rch_wizard_no_recipe', 'rch_wizard_no_blog'], true);

    if (! is_wp_error($result) || $nothing_to_do) {
        update_post_meta($agent_id, '_rch_agent_wizard_source_hash', $new_hash);
    }
}

/**
 * After an API sync updates/creates an agent, refresh its sub-site.
 *
 * Runs at priority 40 so `rch_multisite_on_agent_synced` (priority 10) has already created the
 * sub-site for brand-new agents. Fires inside the agent-sync window, which is why the save_post
 * handler below bails during sync (avoids a double refresh on partial mid-save data).
 *
 * @param int $post_id Agent post ID.
 * @return void
 */
function rch_agent_wizard_refresh_subsite_on_sync(int $post_id): void
{
    rch_agent_wizard_refresh_subsite_after_agent_change((int) $post_id);
}
add_action('rch_after_agent_synced', 'rch_agent_wizard_refresh_subsite_on_sync', 40, 1);

/**
 * Refresh the sub-site after a manual hub-side agent edit in wp-admin.
 *
 * Skipped during an API sync — the `rch_after_agent_synced` hook handles sync-time refreshes with
 * fully-written data, whereas save_post fires mid-sync before all meta is set.
 *
 * @param int     $post_id Agent post ID.
 * @param WP_Post $post    Agent post object.
 * @param bool    $update  Whether this is an update (vs. initial insert).
 * @return void
 */
function rch_agent_wizard_refresh_subsite_on_save(int $post_id, WP_Post $post, bool $update): void
{
    if (function_exists('rch_is_doing_agent_sync') && rch_is_doing_agent_sync()) {
        return;
    }
    if (! empty($GLOBALS['rch_doing_agent_sync'])) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if ($post->post_type !== 'agents' || $post->post_status === 'auto-draft' || wp_is_post_revision($post_id)) {
        return;
    }

    rch_agent_wizard_refresh_subsite_after_agent_change((int) $post_id);
}
add_action('save_post_agents', 'rch_agent_wizard_refresh_subsite_on_save', 40, 3);
