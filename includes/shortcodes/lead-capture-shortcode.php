<?php

/**
 * Lead Capture Form Shortcode
 * 
 * Renders a customizable lead capture form with Rechat SDK integration
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output of the form
 */
function rch_render_leads_form_shortcode($atts)
{
    // Enqueue scripts
    rch_enqueue_lead_form_assets();

    // Parse and sanitize shortcode attributes
    $atts = rch_parse_shortcode_attributes($atts);

    // Get lead channel and tags
    $lead_channel = rch_get_lead_channel($atts);
    $selected_tags = rch_get_selected_tags($atts);

    // Generate unique form ID for this instance
    $form_id = 'leadCaptureForm_' . uniqid();
    $nonce = wp_create_nonce('rch_lead_capture_nonce');

    // Start output buffering
    ob_start();

    // Render form HTML
    rch_render_form_html($atts, $form_id, $nonce);

    // Render inline script for this form instance
    rch_render_form_script($form_id, $lead_channel, $selected_tags);

    return ob_get_clean();
}

/**
 * Parse and sanitize shortcode attributes
 * 
 * @param array $atts Raw shortcode attributes
 * @return array Sanitized attributes
 */
function rch_parse_shortcode_attributes($atts)
{
    $defaults = [
        'form_title' => __('Contact Us', 'rechat-plugin'),
        'show_first_name' => 'true',
        'show_last_name' => 'true',
        'show_phone_number' => 'true',
        'show_email' => 'true',
        'show_note' => 'true',
        'lead_channel' => '',
        'tags' => '',
    ];

    $atts = shortcode_atts($defaults, $atts, 'rch_leads_form');

    // Convert string booleans to actual booleans
    $boolean_fields = ['show_first_name', 'show_last_name', 'show_phone_number', 'show_email', 'show_note'];
    foreach ($boolean_fields as $field) {
        $atts[$field] = filter_var($atts[$field], FILTER_VALIDATE_BOOLEAN);
    }

    // Sanitize text fields
    $atts['form_title'] = sanitize_text_field($atts['form_title']);
    $atts['lead_channel'] = sanitize_text_field($atts['lead_channel']);
    $atts['tags'] = sanitize_text_field($atts['tags']);

    return $atts;
}

/**
 * Get lead channel from attributes or settings
 * 
 * @param array $atts Shortcode attributes
 * @return string Sanitized lead channel
 */
function rch_get_lead_channel($atts)
{
    if (!empty($atts['lead_channel'])) {
        return sanitize_text_field($atts['lead_channel']);
    }

    return sanitize_text_field(get_option('rch_lead_channels', ''));
}

/**
 * Get selected tags from attributes or settings
 * 
 * @param array $atts Shortcode attributes
 * @return array Sanitized tags array
 */
function rch_get_selected_tags($atts)
{
    $tags = [];

    if (!empty($atts['tags'])) {
        $tags = array_map('trim', explode(',', $atts['tags']));
    } else {
        $saved_tags = get_option('rch_selected_tags', []);
        $tags = is_array($saved_tags) ? $saved_tags : [];
    }

    // Sanitize each tag
    return array_map('sanitize_text_field', array_filter($tags));
}

/**
 * Enqueue form assets
 */
function rch_enqueue_lead_form_assets()
{
    static $assets_enqueued = false;

    if ($assets_enqueued) {
        return;
    }

    // Enqueue Rechat SDK
    wp_enqueue_script(
        'rechat-sdk',
        'https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.js',
        [],
        null,
        true
    );

    $assets_enqueued = true;
}

/**
 * Render form HTML
 * 
 * @param array $atts Form attributes
 * @param string $form_id Unique form ID
 * @param string $nonce WordPress nonce
 */
function rch_render_form_html($atts, $form_id, $nonce)
{

?>
    <div class="rch-leads-form-shortcode">
        <form id="<?php echo esc_attr($form_id); ?>" class="rch-lead-capture-form" method="post">
            <?php wp_nonce_field('rch_lead_capture_nonce', 'rch_nonce_field'); ?>

            <?php if (!empty($atts['form_title'])): ?>
                <h2><?php echo esc_html($atts['form_title']); ?></h2>
            <?php endif; ?>

            <?php if ($atts['show_first_name']): ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_id); ?>_first_name">
                        <?php esc_html_e('First Name', 'rechat-plugin'); ?>
                    </label>
                    <input
                        type="text"
                        id="<?php echo esc_attr($form_id); ?>_first_name"
                        name="first_name"
                        placeholder="<?php esc_attr_e('Enter your first name', 'rechat-plugin'); ?>"
                        required
                        maxlength="100"
                        pattern="[A-Za-z\s\-']+">
                </div>
            <?php endif; ?>

            <?php if ($atts['show_last_name']): ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_id); ?>_last_name">
                        <?php esc_html_e('Last Name', 'rechat-plugin'); ?>
                    </label>
                    <input
                        type="text"
                        id="<?php echo esc_attr($form_id); ?>_last_name"
                        name="last_name"
                        placeholder="<?php esc_attr_e('Enter your last name', 'rechat-plugin'); ?>"
                        required
                        maxlength="100"
                        pattern="[A-Za-z\s\-']+">
                </div>
            <?php endif; ?>

            <?php if ($atts['show_phone_number']): ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_id); ?>_phone_number">
                        <?php esc_html_e('Phone Number', 'rechat-plugin'); ?>
                    </label>
                    <input
                        type="tel"
                        id="<?php echo esc_attr($form_id); ?>_phone_number"
                        name="phone_number"
                        placeholder="<?php esc_attr_e('Enter your phone number', 'rechat-plugin'); ?>"
                        required
                        maxlength="20">
                </div>
            <?php endif; ?>

            <?php if ($atts['show_email']): ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_id); ?>_email">
                        <?php esc_html_e('Email Address', 'rechat-plugin'); ?>
                    </label>
                    <input
                        type="email"
                        id="<?php echo esc_attr($form_id); ?>_email"
                        name="email"
                        placeholder="<?php esc_attr_e('Enter your email address', 'rechat-plugin'); ?>"
                        required
                        maxlength="255">
                </div>
            <?php endif; ?>

            <?php if ($atts['show_note']): ?>
                <div class="form-group">
                    <label for="<?php echo esc_attr($form_id); ?>_note">
                        <?php esc_html_e('Note', 'rechat-plugin'); ?>
                    </label>
                    <textarea
                        id="<?php echo esc_attr($form_id); ?>_note"
                        name="note"
                        placeholder="<?php esc_attr_e('Write your note here', 'rechat-plugin'); ?>"
                        required
                        maxlength="1000"></textarea>
                </div>
            <?php endif; ?>

            <button type="submit" class="rch-submit-btn">
                <?php esc_html_e('Submit Request', 'rechat-plugin'); ?>
            </button>
        </form>

        <div id="<?php echo esc_attr($form_id); ?>_loading" class="rch-loading-spinner-form" style="display: none;"></div>
        <div id="<?php echo esc_attr($form_id); ?>_success" class="rch-success-box-listing">
            <?php esc_html_e('Thank you! Your data has been successfully sent.', 'rechat-plugin'); ?>
        </div>
        <div id="<?php echo esc_attr($form_id); ?>_error" class="rch-error-box-listing">
            <?php esc_html_e('Something went wrong. Please try again.', 'rechat-plugin'); ?>
        </div>
    </div>
<?php
}

/**
 * Render form JavaScript
 * 
 * @param string $form_id Unique form ID
 * @param string $lead_channel Lead channel
 * @param array $selected_tags Selected tags
 */
function rch_render_form_script($form_id, $lead_channel, $selected_tags)
{
?>
    <script>
        (function() {
            'use strict';

            // Wait for Rechat SDK to load
            if (typeof Rechat === 'undefined') {
                console.error('Rechat SDK not loaded');
                return;
            }

            const sdk = new Rechat.Sdk();
            const formId = <?php echo wp_json_encode($form_id); ?>;
            const form = document.getElementById(formId);

            if (!form) {
                console.error('Form not found:', formId);
                return;
            }

            const channel = {
                lead_channel: <?php echo wp_json_encode($lead_channel); ?>
            };

            const loadingEl = document.getElementById(formId + '_loading');
            const successEl = document.getElementById(formId + '_success');
            const errorEl = document.getElementById(formId + '_error');

            /**
             * Sanitize input value
             */
            function sanitizeInput(value) {
                if (!value) return '';
                return value.trim().replace(/[<>]/g, '');
            }

            /**
             * Validate email format
             */
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }

            /**
             * Validate phone number
             */
            function isValidPhone(phone) {
                const phoneRegex = /^[\d\s\-\+\(\)]+$/;
                return phoneRegex.test(phone) && phone.replace(/\D/g, '').length >= 10;
            }

            form.addEventListener('submit', function(event) {
                event.preventDefault();

                // Get form elements
                const firstNameEl = document.getElementById(formId + '_first_name');
                const lastNameEl = document.getElementById(formId + '_last_name');
                const phoneEl = document.getElementById(formId + '_phone_number');
                const emailEl = document.getElementById(formId + '_email');
                const noteEl = document.getElementById(formId + '_note');

                // Build input object with sanitization
                const input = {
                    source_type: 'Website'
                };

                if (firstNameEl) {
                    input.first_name = sanitizeInput(firstNameEl.value);
                }

                if (lastNameEl) {
                    input.last_name = sanitizeInput(lastNameEl.value);
                }

                if (phoneEl) {
                    const phone = sanitizeInput(phoneEl.value);
                    if (!isValidPhone(phone)) {
                        alert('Please enter a valid phone number');
                        return;
                    }
                    input.phone_number = phone;
                }

                if (emailEl) {
                    const email = sanitizeInput(emailEl.value);
                    if (!isValidEmail(email)) {
                        alert('Please enter a valid email address');
                        return;
                    }
                    input.email = email;
                }

                if (noteEl) {
                    input.note = sanitizeInput(noteEl.value);
                }

                // Add tags
                const tags = <?php echo wp_json_encode($selected_tags); ?>;
                if (tags && tags.length > 0) {
                    input.tag = tags;
                }

                // Show loading, hide messages
                if (loadingEl) loadingEl.style.display = 'block';
                if (successEl) successEl.style.display = 'none';
                if (errorEl) errorEl.style.display = 'none';

                // Submit to SDK
                sdk.Leads.capture(channel, input)
                    .then(function() {
                        if (loadingEl) loadingEl.style.display = 'none';
                        if (successEl) successEl.style.display = 'block';
                        form.reset();
                    })
                    .catch(function(error) {
                        if (loadingEl) loadingEl.style.display = 'none';
                        if (errorEl) errorEl.style.display = 'block';
                        console.error('Lead capture error:', error);
                    });
            });
        })();
    </script>
<?php
}

// Register the shortcode
add_shortcode('rch_leads_form', 'rch_render_leads_form_shortcode');
