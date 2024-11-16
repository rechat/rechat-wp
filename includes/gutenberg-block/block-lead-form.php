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
            'leadChannel' => array(
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
    $lead_channel = isset($attributes['leadChannel']) ? $attributes['leadChannel'] : '';
    $show_first_name = isset($attributes['showFirstName']) ? $attributes['showFirstName'] : true;
    $show_last_name = isset($attributes['showLastName']) ? $attributes['showLastName'] : true;
    $show_phone_number = isset($attributes['showPhoneNumber']) ? $attributes['showPhoneNumber'] : true;
    $show_email = isset($attributes['showEmail']) ? $attributes['showEmail'] : true;
    $show_note = isset($attributes['showNote']) ? $attributes['showNote'] : true;
    $selected_tags = isset($attributes['selectedTagsFrom']) ? $attributes['selectedTagsFrom'] : array();
    ob_start();
?>
    <div class="rch-leads-form-block">
        <form id="leadCaptureForm" method="post">
            <h2>Submit Your Form</h2>
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
            lead_channel: '<?php echo sanitize_text_field($lead_channel); ?>'
        };

        document.getElementById('leadCaptureForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const input = {
                first_name: document.getElementById('first_name').value,
                last_name: document.getElementById('last_name').value,
                phone_number: document.getElementById('phone_number').value,
                email: document.getElementById('email').value,
                note: document.getElementById('note').value,
                tag: <?php echo json_encode($selected_tags); ?>, // This will convert the PHP array to a JS array
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
