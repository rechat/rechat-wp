jQuery(document).ready(function ($) {
    'use strict';

    /*******************************
     * Real-time progress tracking for data sync
     ******************************/
    $('#update_agents_data').on('click', function () {
        var statusDiv = $('#agents_update_status');
        var button = $(this);
        var progressBar = $('#progress-bar');
        var progressContainer = $('#progress-container');

        // Clear previous status message
        statusDiv.html('');

        // Show progress bar and reset it
        progressBar.css('width', '0%').text('');
        progressContainer.show();

        // Disable the button
        button.prop('disabled', true);

        // Define sync steps
        var steps = [
            { name: 'Initializing...', weight: 10 },
            { name: 'Syncing data...', weight: 80 },
            { name: 'Finalizing...', weight: 10 }
        ];

        var currentStep = 0;
        var totalProgress = 0;

        // Function to update progress bar
        function updateProgress(stepIndex, stepProgress) {
            var completedWeight = 0;
            
            // Calculate completed weight from previous steps
            for (var i = 0; i < stepIndex; i++) {
                completedWeight += steps[i].weight;
            }
            
            // Add current step progress
            var currentWeight = (steps[stepIndex].weight * stepProgress) / 100;
            totalProgress = completedWeight + currentWeight;
            
            // Update progress bar
            progressBar.css('width', totalProgress + '%');
            progressBar.text(Math.round(totalProgress) + '%');
        }

        // Function to show step message
        function showStepMessage(message, type) {
            type = type || 'info';
            var className = 'notice notice-' + type;
            statusDiv.append('<div class="' + className + '"><p>' + message + '</p></div>');
        }

        // Start sync process
        function startSync() {
            currentStep = 0;
            updateProgress(0, 0);
            showStepMessage(steps[0].name, 'info');
            
            // Simulate initialization
            setTimeout(function() {
                updateProgress(0, 100);
                syncData();
            }, 500);
        }

        // Main data sync
        function syncData() {
            currentStep = 1;
            updateProgress(1, 0);
            showStepMessage(steps[1].name, 'info');

            // Track sub-progress during AJAX call
            var subProgress = 0;
            var progressSimulator = setInterval(function() {
                if (subProgress < 90) {
                    subProgress += 5;
                    updateProgress(1, subProgress);
                }
            }, 300);

            // Send the AJAX request
            $.ajax({
                url: rch_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'rch_update_all_data',
                    nonce: rch_ajax_object.nonce
                },
                success: function (response) {
                    clearInterval(progressSimulator);
                    updateProgress(1, 100);

                    if (response.success) {
                        var messages = response.data;
                        
                        // Clear info messages
                        statusDiv.html('');
                        
                        // Show detailed results
                        var successHtml = '<div class="notice notice-success is-dismissible">' +
                            '<p><strong>Sync completed successfully!</strong></p>' +
                            '<ul style="margin-left: 20px;">' +
                            '<li>' + messages.agents + '</li>' +
                            '<li>' + messages.offices + '</li>' +
                            '<li>' + messages.regions + '</li>' +
                            '<li>' + messages.branding + '</li>' +
                            '</ul>' +
                            '</div>';
                        statusDiv.html(successHtml);
                        
                        finalize(true);
                    } else {
                        clearInterval(progressSimulator);
                        statusDiv.html('<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> ' + response.data + '</p></div>');
                        finalize(false);
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    clearInterval(progressSimulator);
                    var errorMessage = 'An error occurred while syncing data.';
                    
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data) {
                        errorMessage = jqXHR.responseJSON.data;
                    } else if (textStatus) {
                        errorMessage += ' (' + textStatus + ')';
                    }
                    
                    statusDiv.html('<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> ' + errorMessage + '</p></div>');
                    finalize(false);
                }
            });
        }

        // Finalize sync
        function finalize(success) {
            currentStep = 2;
            updateProgress(2, 0);
            
            setTimeout(function() {
                updateProgress(2, 100);
                
                // Keep progress bar at 100% briefly
                setTimeout(function() {
                    progressContainer.fadeOut(400, function() {
                        progressBar.css('width', '0%').text('');
                    });
                    
                    // Re-enable the button
                    button.prop('disabled', false);
                }, 1000);
            }, 300);
        }

        // Start the sync process
        startSync();
    });

    /*******************************
     * Disconnect modal functionality
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
