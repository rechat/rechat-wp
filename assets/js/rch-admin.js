jQuery(document).ready(function ($) {
    // Handle tab navigation
    $('.nav-tab').on('click', function (e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').hide();
        var selected_tab = $(this).attr('href');
        $(selected_tab).show();
    });
    $('#update_agents_data').on('click', function () {
        var statusDiv = $('#agents_update_status');
        var button = $(this);

        // Clear previous status message
        statusDiv.html('');

        // Show loading indicator and disable the button
        button.prop('disabled', true);
        var originalText = button.text();
        button.text('Updating...'); // You can also use a spinner here

        // Send the AJAX request
        $.ajax({
            url: rch_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'rch_update_agents_data',
                nonce: rch_ajax_object.nonce
            },
            success: function (response) {
                if (response.success) {
                    statusDiv.html('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>');
                } else {
                    statusDiv.html('<div class="notice notice-error is-dismissible"><p>' + response.data + '</p></div>');
                }
            },
            error: function () {
                statusDiv.html('<div class="notice notice-error is-dismissible"><p>An error occurred while updating agents data.</p></div>');
            },
            complete: function () {
                // Re-enable the button and reset its text
                button.prop('disabled', false);
                button.text(originalText);
            }
        });
    });
});
