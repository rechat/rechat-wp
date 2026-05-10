<?php
/**
 * Single Listing Template
 * 
 * Template for displaying individual listing details
 * Theme overrides: Copy this file to your theme's root directory
 * 
 * ⚠️ IMPORTANT: Do NOT modify the template-parts includes below!
 * Template parts are loaded from the plugin directory so updates always apply.
 * Theme customization: Override template-parts individually in your theme.
 * 
 * @package Rechat
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if agent exists in the agents custom post type
$agent_api_id = isset($listing_detail['list_agent']['id']) ? $listing_detail['list_agent']['id'] : '';
$seller_agent_api_id = isset($listing_detail['selling_agent']['id']) ? $listing_detail['selling_agent']['id'] : '';

$agent_posts = rch_check_agent_exists($agent_api_id);
$seller_agent_posts = rch_check_agent_exists($seller_agent_api_id);

get_header();
?>

<div class="container">
    <div id="primary" class="content-area rch-primary-content">
        <main id="main" class="site-main content-container site-container">
            <div id="rch-house-detail" class="rch-house-main-details">
                
                <?php
                /**
                 * Gallery Section
                 * Displays property images with status badge
                 */
                include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-gallery.php';
                ?>
                
                <?php
                /**
                 * Header Section
                 * Displays price, address, and MLS number
                 */
                include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-header.php';
                ?>

                <div class="rch-single-house-main-layout">
                    <div class="rch-single-left-main-layout">
                        
                        <?php
                        /**
                         * Summary Section
                         * Displays key property metrics (beds, baths, sqft, etc.)
                         */
                        include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-summary.php';
                        ?>
                        
                        <?php
                        /**
                         * Description Section
                         * Displays property description with show more/less
                         */
                        include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-description.php';
                        ?>
                        
                        <?php
                        /**
                         * Open Houses Section
                         * Displays scheduled open house information
                         */
                        include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-open-houses.php';
                        ?>
                        
                        <?php
                        /**
                         * Features Section
                         * Displays all property features organized by category
                         * - Facilities & Features
                         * - Amenities & Utilities
                         * - Interior Features
                         * - Exterior Features
                         * - Parking
                         */
                        include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-features.php';
                        ?>
                        
                        <?php
                        /**
                         * Agents Section
                         * Displays listing agent, seller agent, local logic widgets, and disclaimer
                         */
                        include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-agents.php';
                        ?>
                        
                    </div>
                    
                    <div class="rch-single-right-main-layout">
                        <?php
                        /**
                         * Contact Form Section
                         * Lead capture form for property inquiries
                         */
                        include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-contact-form.php';
                        ?>
                    </div>
                </div>
                
            </div>
        </main><!-- #main -->
    </div><!-- #primary -->
</div>

<?php
/**
 * Modal Section
 * Image gallery lightbox with Swiper slider
 */
include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-modal.php';
?>

<?php get_footer(); ?>

<?php
/**
 * Scripts Section
 * Handles all JavaScript functionality:
 * - Rechat SDK initialization
 * - Lead capture form
 * - Swiper sliders
 * - Modal controls
 * - Show more/less toggle
 */
include RCH_PLUGIN_DIR . 'templates/single/template-parts/listing/listing-scripts.php';
?>
