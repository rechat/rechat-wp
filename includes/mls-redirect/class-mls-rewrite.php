<?php
/**
 * MLS redirect: rewrite rules + query var.
 *
 * Single responsibility: register the URL routes for the explicit /mls/ and /id/
 * prefixes and expose the query var they feed.
 *
 * The bare `/#{mls}` route is intentionally NOT registered as a rewrite rule.
 * A greedy `^([^/]+)/?$` rule would shadow every page, post and taxonomy term.
 * Instead the bare route is handled on `template_redirect` only when the request
 * is otherwise a 404 (see RCH_MLS_Controller) — so existing WordPress routes always
 * win and only genuinely unmatched URLs are considered for an MLS lookup.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers rewrite rules and the query var for MLS routes.
 */
class RCH_MLS_Rewrite
{
    /**
     * Bump when the rewrite rules below change to trigger a one-time flush.
     */
    const RULES_VERSION = '1';

    /**
     * Option storing the last-flushed rules version.
     */
    const VERSION_OPTION = 'rch_mls_rewrite_version';

    /**
     * Hook rules + query var registration.
     *
     * @return void
     */
    public function register()
    {
        add_action('init', array($this, 'add_rules'));
        add_filter('query_vars', array($this, 'add_query_var'));
    }

    /**
     * Register the /mls/{id} and /id/{id} rewrite rules and flush once if needed.
     *
     * @return void
     */
    public function add_rules()
    {
        add_rewrite_rule('^mls/([^/]+)/?$', 'index.php?' . RCH_MLS_Request::QUERY_VAR . '=$matches[1]', 'top');
        add_rewrite_rule('^id/([^/]+)/?$', 'index.php?' . RCH_MLS_Request::QUERY_VAR . '=$matches[1]', 'top');

        // Self-healing flush: activation flushes too, but plugin updates applied in
        // place (e.g. the GitHub updater) never re-run the activation hook.
        if (get_option(self::VERSION_OPTION) !== self::RULES_VERSION) {
            flush_rewrite_rules(false);
            update_option(self::VERSION_OPTION, self::RULES_VERSION);
        }
    }

    /**
     * Whitelist the MLS query var.
     *
     * @param string[] $vars Registered query vars.
     * @return string[]
     */
    public function add_query_var($vars)
    {
        $vars[] = RCH_MLS_Request::QUERY_VAR;
        return $vars;
    }
}
