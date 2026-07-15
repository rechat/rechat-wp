<?php
/**
 * MLS redirect: controller / orchestrator.
 *
 * Single responsibility: wire request parsing, lookup and redirect together on the
 * `template_redirect` hook. Collaborators are injected (dependency injection) so
 * each piece stays independently testable.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Orchestrates the MLS resolve → redirect flow.
 */
class RCH_MLS_Controller
{
    /**
     * @var RCH_MLS_Request
     */
    protected $request;

    /**
     * @var RCH_MLS_Lookup
     */
    protected $lookup;

    /**
     * @var RCH_MLS_Redirect
     */
    protected $redirect;

    /**
     * @param RCH_MLS_Request  $request  Request parser/validator.
     * @param RCH_MLS_Lookup   $lookup   Listing lookup service.
     * @param RCH_MLS_Redirect $redirect Redirect service.
     */
    public function __construct(RCH_MLS_Request $request, RCH_MLS_Lookup $lookup, RCH_MLS_Redirect $redirect)
    {
        $this->request  = $request;
        $this->lookup   = $lookup;
        $this->redirect = $redirect;
    }

    /**
     * Register the front-end hook.
     *
     * @return void
     */
    public function register()
    {
        add_action('template_redirect', array($this, 'handle'));
    }

    /**
     * Resolve the current request and redirect (or 404) when it is an MLS lookup.
     *
     * @return void
     */
    public function handle()
    {
        if (is_admin() || is_robots() || is_favicon()) {
            return;
        }

        // 1) Explicit intent: /mls/{id} or /id/{id}. A miss here must be a 404,
        //    never a silent fall-through to the front page.
        $explicit = $this->request->from_query_var();
        if ($explicit !== '') {
            $url = $this->lookup->resolve($explicit);
            if ($url !== '') {
                $this->redirect->to_listing($url);
            }
            $this->redirect->not_found();
            return;
        }

        // 2) Bare `/#{mls}` — only when WordPress found no matching route (404),
        //    so pages/posts/terms are never intercepted. A miss leaves the 404 as-is.
        if (is_404()) {
            $bare = $this->request->from_unresolved_path();
            if ($bare !== '') {
                $url = $this->lookup->resolve($bare);
                if ($url !== '') {
                    $this->redirect->to_listing($url);
                }
            }
        }
    }
}
