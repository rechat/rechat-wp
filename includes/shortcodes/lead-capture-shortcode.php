<?php
function rch_render_leads_form_shortcode($atts)
{
    // Parse shortcode attributes
    $atts = shortcode_atts(
        [
            'form_title' => 'Contact Us', // Default form title
            'show_first_name' => 'true', // Defaults as string
            'show_last_name' => 'true',
            'show_phone_number' => 'true',
            'show_email' => 'true',
            'show_note' => 'true',
            'lead_channel' => '', // Optional lead_channel from attributes
            'tags' => '', // Optional tags (comma-separated list)
        ],
        $atts,
        'rch_leads_form'
    );

    // Convert attributes to boolean
    $atts['show_first_name'] = filter_var($atts['show_first_name'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_last_name'] = filter_var($atts['show_last_name'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_phone_number'] = filter_var($atts['show_phone_number'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_email'] = filter_var($atts['show_email'], FILTER_VALIDATE_BOOLEAN);
    $atts['show_note'] = filter_var($atts['show_note'], FILTER_VALIDATE_BOOLEAN);

    // Use shortcode attributes for lead_channel and tags if provided
    $lead_channel = !empty($atts['lead_channel']) ? $atts['lead_channel'] : get_option('rch_lead_channels', '');
    $selected_tags = !empty($atts['tags'])
        ? explode(',', $atts['tags']) // Convert comma-separated string to an array
        : get_option('rch_selected_tags', []);

    // Start output buffering
    ob_start();

    // HTML for the form
?>
    <div class="rch-leads-form-shortcode">
        <form id="leadCaptureForm" method="post">
            <?php if (!empty($atts['form_title'])): ?>
                <h2><?php echo esc_html($atts['form_title']); ?></h2>
            <?php endif; ?>
            <?php if ($atts['show_first_name']): ?>
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" required>
                </div>
            <?php endif; ?>
            <?php if ($atts['show_last_name']): ?>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" placeholder="Enter your last name" required>
                </div>
            <?php endif; ?>
            <?php if ($atts['show_phone_number']): ?>
                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="tel" id="phone_number" name="phone_number" placeholder="Enter your phone number" required>
                </div>
            <?php endif; ?>
            <?php if ($atts['show_email']): ?>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                </div>
            <?php endif; ?>
            <?php if ($atts['show_note']): ?>
                <div class="form-group">
                    <label for="note">Note</label>
                    <textarea id="note" name="note" placeholder="Write your note here" required></textarea>
                </div>
            <?php endif; ?>
            <button type="submit">Submit Request</button>
        </form>
        <div id="loading-spinner" class="rch-loading-spinner-form" style="display: none;"></div>
        <div id="rch-listing-success-sdk" class="rch-success-box-listing">
            Thank you! Your data has been successfully sent.
        </div>
        <div id="rch-listing-cancel-sdk" class="rch-error-box-listing">
            Something went wrong. Please try again.
        </div>
    </div>
    <script src="https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.js"></script>
    <script>
        const sdk = new Rechat.Sdk();

        const channel = {
            lead_channel: "<?php echo esc_js(sanitize_text_field($lead_channel)); ?>"
        };

        document.getElementById('leadCaptureForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const input = {
                first_name: document.getElementById('first_name')?.value.trim(),
                last_name: document.getElementById('last_name')?.value.trim(),
                phone_number: document.getElementById('phone_number')?.value.trim(),
                email: document.getElementById('email')?.value.trim(),
                note: document.getElementById('note')?.value.trim(),
                tag: <?php echo wp_json_encode($selected_tags); ?>, // Convert PHP array to JS array
                source_type: 'Website',
            };

            // Show loading spinner and hide success/error alerts
            document.getElementById('loading-spinner').style.display = 'block';
            document.getElementById('rch-listing-success-sdk').style.display = 'none';
            document.getElementById('rch-listing-cancel-sdk').style.display = 'none';

            sdk.Leads.capture(channel, input)
                .then(() => {
                    document.getElementById('loading-spinner').style.display = 'none';
                    document.getElementById('rch-listing-success-sdk').style.display = 'block';
                })
                .catch((e) => {
                    document.getElementById('loading-spinner').style.display = 'none';
                    document.getElementById('rch-listing-cancel-sdk').style.display = 'block';
                    console.error('Error:', e);
                });
        });
    </script>

<?php
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('rch_leads_form', 'rch_render_leads_form_shortcode');
?>