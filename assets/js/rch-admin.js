jQuery(document).ready(function ($) {
    /*******************************
     * show loading when click on update data in setting
     ******************************/

    $('#update_agents_data').on('click', function () {
        var statusDiv = $('#agents_update_status');
        var button = $(this);
        var progressBar = $('#progress-bar');
        var progressContainer = $('#progress-container');

        // Clear previous status message
        statusDiv.html('');

        // Show progress bar and reset it
        progressBar.css('width', '0%');
        progressContainer.show();

        // Disable the button
        button.prop('disabled', true);

        // Simulate smooth progress update
        var simulatedProgress = 0;
        var progressInterval = setInterval(function () {
            if (simulatedProgress < 90) {
                simulatedProgress += 5; // Increment progress
                progressBar.css('width', simulatedProgress + '%');
            }
        }, 2000); // Update every 500ms

        // Send the AJAX request
        $.ajax({
            url: rch_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'rch_update_all_data',
                nonce: rch_ajax_object.nonce
            },
            success: function (response) {
                if (response.success) {
                    var messages = response.data;
                    var messageHtml = '<div class="notice notice-success is-dismissible"><p>' +
                        messages.agents + '<br>' +
                        messages.offices + '<br>' +
                        messages.regions + '</p></div>';
                    statusDiv.html(messageHtml);
                } else {
                    statusDiv.html('<div class="notice notice-error is-dismissible"><p>' + response.data + '</p></div>');
                }
            },
            error: function () {
                statusDiv.html('<div class="notice notice-error is-dismissible"><p>An error occurred while updating agents data.</p></div>');
            },
            complete: function () {
                // Simulate completion of progress bar
                clearInterval(progressInterval);
                progressBar.css('width', '100%'); // Fill the bar

                // Hide progress bar after a slight delay
                setTimeout(function () {
                    progressContainer.hide();
                    progressBar.css('width', '0%'); // Reset for next time
                }, 1000); // Show full bar for a moment

                // Re-enable the button
                button.prop('disabled', false);
            }
        });
    });
    /*******************************
     * show modal when click on disconnect and ask are you sure?
     ******************************/
    var $modal = $('#disconnect-modal');
    var $btn = $('#show-disconnect-modal');
    var $close = $('.disconnect-close');
    var $confirmButton = $('#confirm-disconnect');
    var $cancelButton = $('#cancel-disconnect');
    var $form = $('#disconnect-form');

    $btn.on('click', function () {
        $modal.show();
    });

    $close.on('click', function () {
        $modal.hide();
    });

    $cancelButton.on('click', function () {
        $modal.hide();
    });

    $confirmButton.on('click', function () {
        $form.submit();
    });

    $(window).on('click', function (event) {
        if ($(event.target).is($modal)) {
            $modal.hide();
        }
    });

});
