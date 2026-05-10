<?php
/**
 * Template part for displaying the listing contact/lead capture form
 * 
 * This template displays:
 * - Lead capture form with 5 fields (first_name, last_name, phone_number, email, note)
 * - Hidden fields for action and listing data
 * - Submit button with loading spinner
 * - Success and error message containers
 * 
 * @package Rechat_Plugin
 * @var array $listing_detail The listing detail array containing all property information
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Ensure $listing_detail is available
if (!isset($listing_detail) || !is_array($listing_detail)) {
    return;
}
?>

<div class="rch-listing-form-lead" id="leadCaptureForm">

    <form action="" method="post">
        <h2>Inquire About This Property</h2>
        <!-- First Name -->
        <div class="form-group">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" required>
        </div>

        <!-- Last Name -->
        <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" placeholder="Enter your last name" required>
        </div>

        <!-- Phone Number -->
        <div class="form-group">
            <label for="phone_number">Phone Number</label>
            <input type="tel" id="phone_number" name="phone_number" placeholder="Enter your phone number" required>
        </div>

        <!-- Email Address -->
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="Enter your email address" required>
        </div>

        <!-- Note -->
        <div class="form-group">
            <label for="note">Note</label>
            <textarea id="note" name="note" placeholder="Write your note here" required></textarea>
        </div>

        <!-- Submit Button -->
        <button type="submit">Submit Request</button>
        <div id="loading-spinner" class="rch-loading-spinner-form" style="display: none;"></div>
        <div id="rch-listing-success-sdk" class="rch-success-box-listing">
            Thank you! Your data has been successfully sent.
        </div>
        <div id="rch-listing-cancel-sdk" class="rch-error-box-listing">
            Something went wrong. Please try again.
        </div>
    </form>
</div>
