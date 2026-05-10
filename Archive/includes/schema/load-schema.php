<?php
if (! defined('ABSPATH')) {
    exit();
}

/**
 * Bootstrap structured data (JSON-LD) for the plugin.
 */
require_once RCH_PLUGIN_INCLUDES . 'schema/agent-person-schema.php';
require_once RCH_PLUGIN_INCLUDES . 'schema/listing-realestate-listing-schema.php';
require_once RCH_PLUGIN_INCLUDES . 'schema/brokerage-local-business-schema.php';
require_once RCH_PLUGIN_INCLUDES . 'schema/breadcrumb-list-schema.php';
require_once RCH_PLUGIN_INCLUDES . 'schema/contact-page-geo-schema.php';
