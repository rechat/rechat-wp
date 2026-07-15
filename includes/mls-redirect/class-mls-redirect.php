<?php
/**
 * MLS redirect: redirect service.
 *
 * Single responsibility: send the HTTP redirect (or force a 404) — nothing else.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Performs the redirect / 404 for the MLS resolver.
 */
class RCH_MLS_Redirect
{
    /**
     * 301-redirect to a resolved listing URL and stop execution.
     *
     * The target is always a same-site listing URL built from home_url(), so
     * wp_safe_redirect() is used (it whitelists the site host).
     *
     * @param string $url Destination URL.
     * @return void
     */
    public function to_listing($url)
    {
        $url = esc_url_raw($url);
        if ($url === '') {
            return;
        }

        /**
         * Filter the redirect status code (default 301 permanent).
         *
         * @param int    $status HTTP status.
         * @param string $url    Destination URL.
         */
        $status = (int) apply_filters('rch_mls_redirect_status', 301, $url);

        wp_safe_redirect($url, $status);
        exit;
    }

    /**
     * Force a hard 404 for an MLS route that resolved to nothing.
     *
     * Ensures an explicit /mls/{id} or /id/{id} with no match returns a proper
     * 404 page instead of silently falling through to the front page.
     *
     * @return void
     */
    public function not_found()
    {
        global $wp_query;

        if ($wp_query instanceof WP_Query) {
            $wp_query->set_404();
        }
        status_header(404);
        nocache_headers();
    }
}
