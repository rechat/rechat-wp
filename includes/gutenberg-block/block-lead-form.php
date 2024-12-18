<?php
if (! defined('ABSPATH')) {
    exit();
}
/*******************************
 * this code for Lead Form gutenbberg block
 ******************************/
/*******************************
 * Register Leads form block in php
 ******************************/
function rch_register_block_assets_leads_form()
{
    register_block_type('rch-rechat-plugin/leads-form-block', array(
        'editor_script' => 'rch-gutenberg-js', // JavaScript file for the block editor
        'attributes' => array(
            'formTitle' => array(
                'type' => 'string',
                'default' => '',
            ),
            'leadChannel' => array(
                'type' => 'string',
                'default' => '',
            ),
            'emailForGetLead' => array(
                'type' => 'string',
                'default' => '',
            ),
            'showFirstName' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showLastName' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showPhoneNumber' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showEmail' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'showNote' => array(
                'type' => 'boolean',
                'default' => true,
            ),
            'selectedTagsFrom' => array(
                'type' => 'array',
                'default' => array(),
            ),
        ),
        'render_callback' => 'rch_render_leads_form_block', // Callback function for rendering the block
    ));
}
add_action('init', 'rch_register_block_assets_leads_form');

/*******************************
 * Callback function for leads form block
 ******************************/
function rch_render_leads_form_block($attributes)
{
    // Extract attributes
    $form_title = isset($attributes['formTitle']) ? $attributes['formTitle'] : '';
    $lead_channel = isset($attributes['leadChannel']) ? $attributes['leadChannel'] : '';
    $email_get_lead = isset($attributes['emailForGetLead']) ? $attributes['emailForGetLead'] : '';
    $show_first_name = isset($attributes['showFirstName']) ? $attributes['showFirstName'] : true;
    $show_last_name = isset($attributes['showLastName']) ? $attributes['showLastName'] : true;
    $show_phone_number = isset($attributes['showPhoneNumber']) ? $attributes['showPhoneNumber'] : true;
    $show_email = isset($attributes['showEmail']) ? $attributes['showEmail'] : true;
    $show_note = isset($attributes['showNote']) ? $attributes['showNote'] : true;
    $selected_tags = isset($attributes['selectedTagsFrom']) ? $attributes['selectedTagsFrom'] : array();
    $is_editor = defined('REST_REQUEST') && REST_REQUEST && isset($_GET['context']) && $_GET['context'] === 'edit';

    // Start output buffering
    ob_start();

    // HTML for the form
?>
    <div class="rch-leads-form-block">
        <form id="leadCaptureForm" method="post">
            <?php if ($form_title): ?>
                <h2><?php echo esc_html($form_title); ?></h2>
            <?php endif; ?>
            <?php if ($show_first_name): ?>
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" required>
                </div>
            <?php endif; ?>
            <?php if ($show_last_name): ?>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" placeholder="Enter your last name" required>
                </div>
            <?php endif; ?>
            <?php if ($show_phone_number): ?>
                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="tel" id="phone_number" name="phone_number" placeholder="Enter your phone number" required>
                </div>
            <?php endif; ?>
            <?php if ($show_email): ?>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                </div>
            <?php endif; ?>
            <?php if ($show_note): ?>
                <div class="form-group">
                    <label for="note">Note</label>
                    <textarea id="note" name="note" placeholder="Write your note here" required></textarea>
                </div>
            <?php endif; ?>
            <button type="submit" <?php echo $is_editor ? 'disabled' : ''; ?>>Submit Request</button>
        </form>
        <div id="loading-spinner" class="rch-loading-spinner-form" style="display: none;"></div>
        <div id="rch-listing-success-sdk" class="rch-success-box-listing">
            Thank you! Your data has been successfully sent.
        </div>
        <div id="rch-listing-cancel-sdk" class="rch-error-box-listing">
            Something went wrong. Please try again.
        </div>
    </div>

    <?php if (!$is_editor): ?>
        <script src="https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.js"></script>
        <script>
            const sdk = new Rechat.Sdk();

            const channel = {
                lead_channel: '<?php echo esc_js(sanitize_text_field($lead_channel)); ?>'
            };

            document.getElementById('leadCaptureForm').addEventListener('submit', function(event) {
                event.preventDefault();

                const input = {
                    first_name: document.getElementById('first_name')?.value.trim(),
                    last_name: document.getElementById('last_name')?.value.trim(),
                    phone_number: document.getElementById('phone_number')?.value.trim(),
                    email: document.getElementById('email')?.value.trim(),
                    note: document.getElementById('note')?.value.trim(),
                    tag: <?php echo wp_json_encode($selected_tags); ?>,
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
    <?php endif; ?>

<?php
    return ob_get_clean();
}
function handle_lead_submission()
{
    // Get the form data
    $first_name = '';
    $last_name = '';
    $phone_number = '';
    $email = '';
    $note = '';
    $tags = '';
    $lead_channel = '';
    if (isset($_POST['first_name'])) {
        $first_name = sanitize_text_field(wp_unslash($_POST['first_name']));
    }
    // Validate and sanitize last_name
    if (isset($_POST['last_name'])) {
        $last_name = sanitize_text_field(wp_unslash($_POST['last_name']));
    }

    // Validate and sanitize phone_number
    if (isset($_POST['phone_number'])) {
        $phone_number = sanitize_text_field(wp_unslash($_POST['phone_number']));
    }

    // Validate and sanitize email
    if (isset($_POST['email'])) {
        $email = sanitize_email(wp_unslash($_POST['email']));
    }

    // Validate and sanitize note
    if (isset($_POST['note'])) {
        $note = sanitize_textarea_field(wp_unslash($_POST['note']));
    }

    // Validate and sanitize tags (array)
    if (isset($_POST['tag']) && is_array($_POST['tag'])) {
        $tags = implode(', ', array_map('sanitize_text_field', wp_unslash($_POST['tag'])));
    }

    // Validate and sanitize lead_channel
    if (isset($_POST['lead_channel'])) {
        $lead_channel = sanitize_text_field(wp_unslash($_POST['lead_channel']));
    }

    // Email data
    $to = 'mseiedmiri@gmail.com'; // The email address to send the lead data to
    $subject = 'New Lead Submission';
    $message = "First Name: $first_name\n";
    $message .= "Last Name: $last_name\n";
    $message .= "Phone Number: $phone_number\n";
    $message .= "Email: $email\n";
    $message .= "Note: $note\n";
    $message .= "Tags: $tags\n";
    $message .= "Lead Channel: $lead_channel\n";

    // Email headers
    $headers = array(
        'Content-Type' => 'text/plain; charset=UTF-8',
        'From' => 'no-reply@example.com',
    );

    // Send the email
    $mail_sent = wp_mail($to, $subject, $message, $headers);

    // Prepare the response data
    $response_data = array(
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone_number' => $phone_number,
        'email' => $email,
        'note' => $note,
        'tags' => $tags,
        'lead_channel' => $lead_channel,
        'mail_sent' => $mail_sent ? 'Yes' : 'No' // Ensure it is a string
    );

    // Capture debug output
    ob_start();
    $debug_output = ob_get_clean();

    // Prepare the debug output in the response data
    $response_data['debug_output'] = $debug_output;

    // Return the response as JSON
    header('Content-Type: application/json');
    echo wp_json_encode(array('success' => true, 'data' => $response_data));

    // Always terminate the request after sending the response
    exit();
}

add_action('wp_ajax_handle_lead_submission', 'handle_lead_submission');
add_action('wp_ajax_nopriv_handle_lead_submission', 'handle_lead_submission');
