<?php
/**
 * MLS redirect: bootstrap / composition root.
 *
 * Loads the classes and wires them together (dependency injection happens here).
 * Included from the plugin bootstrap (index.php).
 *
 * URL routes handled:
 *   /{mls_number}                 (bare, only on an otherwise-404 request)
 *   /{mls_source}/{mls_number}    (e.g. /NTREIS/21191513, only on an otherwise-404 request)
 *   /mls/{mls_number}
 *   /id/{mls_number}
 * Each 301-redirects to the listing's /listing-detail/... permalink, or 404s.
 *
 * @package Rechat
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-mls-request.php';
require_once __DIR__ . '/class-mls-lookup.php';
require_once __DIR__ . '/class-mls-redirect.php';
require_once __DIR__ . '/class-mls-rewrite.php';
require_once __DIR__ . '/class-mls-controller.php';

/**
 * Instantiate + register the MLS redirect feature once.
 *
 * @return void
 */
function rch_mls_redirect_boot()
{
    static $booted = false;
    if ($booted) {
        return;
    }
    $booted = true;

    $rewrite = new RCH_MLS_Rewrite();
    $rewrite->register();

    $controller = new RCH_MLS_Controller(
        new RCH_MLS_Request(),
        new RCH_MLS_Lookup(),
        new RCH_MLS_Redirect()
    );
    $controller->register();
}

rch_mls_redirect_boot();
