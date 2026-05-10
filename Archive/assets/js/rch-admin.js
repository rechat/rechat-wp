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
                        var resultItems = '';
                        var coreKeys = ['agents', 'offices', 'regions', 'branding'];
                        $.each(coreKeys, function (i, key) {
                            if (messages[key]) {
                                resultItems += '<li>' + messages[key] + '</li>';
                            }
                        });

                        // Append any extra module results (e.g. multisite) returned
                        // by the rch_sync_response_data filter.
                        $.each(messages, function (key, value) {
                            if ($.inArray(key, coreKeys) === -1) {
                                resultItems += '<li>' + value + '</li>';
                            }
                        });

                        var successHtml = '<div class="notice notice-success is-dismissible">' +
                            '<p><strong>Sync completed successfully!</strong></p>' +
                            '<ul style="margin-left: 20px;">' +
                            resultItems +
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

    /*******************************
     * General settings: country + state (hidden inputs submit; display selects + AJAX + transient cache on server)
     ******************************/
    var $hCountry = $('#rch_selected_country');
    var $dCountry = $('#rch_selected_country_display');
    var $hState = $('#rch_selected_state');
    var $dState = $('#rch_selected_state_display');

    if (
        $hCountry.length &&
        $dCountry.length &&
        $hState.length &&
        $dState.length &&
        typeof rch_ajax_object !== 'undefined'
    ) {
        var $countryStatus = $('.rch-boundary-country-status');
        var $countryLoad = $dCountry.next('.rch-boundary-country-loading');
        var $stateLoad = $dState.next('.rch-boundary-state-loading');
        var $stateErr = $stateLoad.next('.rch-boundary-state-error');
        var rchB = rch_ajax_object.boundary_states || {};
        var statePh = rchB.state_placeholder || 'Select a state / province';

        function rchHasOption($sel, val) {
            if (val === '' || val === null || typeof val === 'undefined') {
                return true;
            }
            return (
                $sel.find('option').filter(function () {
                    return String(this.value) === String(val);
                }).length > 0
            );
        }

        function rchEnsureOption($sel, val, label) {
            if (val === '' || val === null || typeof val === 'undefined') {
                return;
            }
            if (!rchHasOption($sel, val)) {
                $sel.append(
                    $('<option></option>')
                        .attr('value', val)
                        .text(label || val)
                );
            }
        }

        function rchCountryLoading(on) {
            if (on) {
                $countryLoad.show();
                $countryLoad.find('.rch-boundary-country-loading-text').text(rchB.loading_countries || '');
                $dCountry.attr('aria-busy', 'true');
            } else {
                $countryLoad.hide();
                $dCountry.attr('aria-busy', 'false');
            }
        }

        function rchStateLoading(on) {
            if (on) {
                $stateLoad.show();
                $stateLoad.find('.rch-boundary-state-loading-text').text(rchB.loading || '');
            } else {
                $stateLoad.hide();
            }
        }

        function rchStateShowError(msg) {
            if (msg) {
                $stateErr.text(msg).show();
            } else {
                $stateErr.text('').hide();
            }
        }

        function rchResetStateDisplay() {
            $dState.empty();
            $dState.append($('<option></option>').attr('value', '').text(statePh));
        }

        function rchFillStateOptions(rows) {
            rchResetStateDisplay();
            $.each(rows || [], function (_, row) {
                if (row && row.value) {
                    $dState.append(
                        $('<option></option>')
                            .attr('value', row.value)
                            .text(row.label || row.value)
                    );
                }
            });
            rchApplySavedStateSelection();
        }

        function rchApplySavedStateSelection() {
            var saved = ($hState.val() || '').toString();
            if (!saved) {
                $dState.val('');
                return;
            }
            var matchVal = '';
            $dState.find('option').each(function () {
                if (
                    String(this.value) === saved ||
                    String(this.value).toLowerCase() === saved.toLowerCase()
                ) {
                    matchVal = this.value;
                    return false;
                }
            });
            if (matchVal !== '') {
                $dState.val(matchVal);
                $hState.val(matchVal);
                return;
            }
            rchEnsureOption($dState, saved, saved);
            $dState.val(saved);
        }

        function rchApplySavedCountrySelection() {
            var savedC = ($hCountry.val() || '').toString();
            if (!savedC) {
                $dCountry.val('');
                return;
            }
            var matchVal = '';
            $dCountry.find('option').each(function () {
                if (String(this.value).toLowerCase() === savedC.toLowerCase()) {
                    matchVal = this.value;
                    return false;
                }
            });
            if (matchVal !== '') {
                $dCountry.val(matchVal);
                $hCountry.val(matchVal);
                return;
            }
            rchEnsureOption($dCountry, savedC, savedC + ' (saved)');
            $dCountry.val(savedC);
        }

        function rchFetchStates(countryCode, isRetry) {
            rchStateShowError('');
            if (!countryCode) {
                $dState.prop('disabled', true);
                rchResetStateDisplay();
                rchStateLoading(false);
                return $.Deferred().resolve().promise();
            }
            var iso = String(countryCode).toUpperCase();
            $dState.prop('disabled', true);
            rchResetStateDisplay();
            rchStateLoading(true);
            return $.ajax({
                url: rch_ajax_object.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'rch_fetch_boundary_states',
                    nonce: rch_ajax_object.nonce,
                    country: iso,
                    force_refresh: isRetry ? 1 : 0
                }
            })
                .done(function (response) {
                    rchStateLoading(false);
                    if (!response || !response.success) {
                        var errMsg =
                            typeof response.data === 'string'
                                ? response.data
                                : (response.data && response.data.message) || rchB.failed || '';
                        rchStateShowError(errMsg);
                        $dState.prop('disabled', true);
                        return;
                    }
                    var opts =
                        response.data && Object.prototype.hasOwnProperty.call(response.data, 'options')
                            ? response.data.options
                            : null;
                    if (!$.isArray(opts)) {
                        rchStateShowError(rchB.failed || '');
                        $dState.prop('disabled', true);
                        return;
                    }
                    if (opts.length === 0 && !isRetry) {
                        rchFetchStates(countryCode, true);
                        return;
                    }
                    if (opts.length === 0 && isRetry) {
                        rchStateShowError(rchB.failed || '');
                        $dState.prop('disabled', true);
                        return;
                    }
                    rchFillStateOptions(opts);
                    $dState.prop('disabled', false);
                })
                .fail(function () {
                    rchStateLoading(false);
                    $dState.prop('disabled', true);
                    rchStateShowError(rchB.failed || '');
                });
        }

        $dCountry.on('change', function () {
            var code = $dCountry.val();
            $hCountry.val(code);
            $hState.val('');
            if (!code) {
                $dState.prop('disabled', true);
                rchResetStateDisplay();
                rchStateLoading(false);
                rchStateShowError('');
                return;
            }
            rchFetchStates(code, false);
        });

        $dState.on('change', function () {
            $hState.val($dState.val());
        });

        function rchLoadCountries(isRetry) {
            rchCountryLoading(true);
            $.ajax({
                url: rch_ajax_object.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'rch_fetch_boundary_countries',
                    nonce: rch_ajax_object.nonce,
                    force_refresh: isRetry ? 1 : 0
                }
            })
                .done(function (response) {
                    rchCountryLoading(false);
                    var opts =
                        response &&
                        response.success &&
                        response.data &&
                        Object.prototype.hasOwnProperty.call(response.data, 'options')
                            ? response.data.options
                            : null;
                    if (!response || !response.success || !$.isArray(opts)) {
                        var cErr =
                            typeof (response && response.data) === 'string'
                                ? response.data
                                : (response && response.data && response.data.message) ||
                                  rchB.countries_failed ||
                                  '';
                        $countryStatus.text(cErr).show();
                        return;
                    }
                    if (opts.length === 0 && !isRetry) {
                        rchLoadCountries(true);
                        return;
                    }
                    if (opts.length === 0 && isRetry) {
                        $countryStatus.text(rchB.countries_failed || '').show();
                        return;
                    }
                    $countryStatus.hide();
                    $dCountry.empty();
                    $dCountry.append(
                        $('<option></option>').attr('value', '').text(rchB.any_country || 'Any')
                    );
                    $.each(opts, function (_, row) {
                        if (row && row.value) {
                            $dCountry.append(
                                $('<option></option>')
                                    .attr('value', row.value)
                                    .text(row.label || row.value)
                            );
                        }
                    });
                    rchApplySavedCountrySelection();
                    var effectiveCountry = ($hCountry.val() || '').toString();
                    if (effectiveCountry) {
                        rchFetchStates(effectiveCountry, false);
                    }
                })
                .fail(function () {
                    rchCountryLoading(false);
                    $countryStatus.text(rchB.countries_failed || '').show();
                });
        }

        rchLoadCountries(false);
    }

    /*******************************
     * General settings: lead sources + tags (AJAX; one shared request per resource)
     *******************************/
    if (typeof rch_ajax_object !== 'undefined') {
        var LC = rch_ajax_object.lead_capture || {};

        var rchLeadChannelsPromise = null;
        function rchFetchLeadChannelsOnce() {
            if (!rchLeadChannelsPromise) {
                rchLeadChannelsPromise = jQuery.ajax({
                    url: rch_ajax_object.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'rch_fetch_lead_channels_settings',
                        nonce: rch_ajax_object.nonce
                    }
                });
            }
            return rchLeadChannelsPromise;
        }

        var rchTagsSettingsPromise = null;
        function rchFetchTagsSettingsOnce() {
            if (!rchTagsSettingsPromise) {
                rchTagsSettingsPromise = jQuery.ajax({
                    url: rch_ajax_object.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'rch_fetch_tags_settings',
                        nonce: rch_ajax_object.nonce
                    }
                });
            }
            return rchTagsSettingsPromise;
        }

        function rchBindTagChips($select, $container, $hidden) {
            var selectedTagNames = [];
            try {
                selectedTagNames = JSON.parse($hidden.val() || '[]');
            } catch (e1) {
                selectedTagNames = [];
            }
            if (!Array.isArray(selectedTagNames)) {
                selectedTagNames = [];
            }

            function updateHiddenInput() {
                $hidden.val(JSON.stringify(selectedTagNames));
            }

            function renderChips() {
                $container.empty();
                selectedTagNames.forEach(function (tagName) {
                    var $chip = jQuery('<span class="rch-tag-chip"></span>').text(tagName).css({
                        display: 'inline-block',
                        margin: '0 5px 5px 0',
                        padding: '5px 10px',
                        backgroundColor: '#ddd',
                        borderRadius: '3px'
                    });
                    var $close = jQuery('<span>&times;</span>')
                        .css({ marginLeft: '5px', cursor: 'pointer', fontWeight: 'bold' })
                        .attr('aria-label', 'Remove tag')
                        .on('click', function () {
                            selectedTagNames = selectedTagNames.filter(function (t) {
                                return t !== tagName;
                            });
                            updateHiddenInput();
                            renderChips();
                        });
                    $chip.append($close);
                    $container.append($chip);
                });
            }

            $select.off('change.rchTags').on('change.rchTags', function () {
                var v = $select.val();
                if (v && selectedTagNames.indexOf(v) === -1) {
                    selectedTagNames.push(v);
                    updateHiddenInput();
                    renderChips();
                }
                $select.val('');
            });

            renderChips();
        }

        jQuery('.rch-async-lead-channel').each(function () {
            var $wrap = jQuery(this);
            if ($wrap.data('rchLeadInit')) {
                return;
            }
            $wrap.data('rchLeadInit', true);
            var selected = String($wrap.attr('data-selected') || '');
            var $msg = $wrap.find('.rch-lead-channel-loading-msg');
            var $sel = $wrap.find('select');
            var $empty = $wrap.find('.rch-lead-channel-empty');
            var $err = $wrap.find('.rch-lead-channel-error');

            rchFetchLeadChannelsOnce()
                .done(function (response) {
                    $msg.hide();
                    if (!response || !response.success) {
                        var m =
                            response && response.data && response.data.message
                                ? response.data.message
                                : LC.channels_failed || '';
                        $err.text(m).show();
                        return;
                    }
                    var channels =
                        response.data && jQuery.isArray(response.data.channels)
                            ? response.data.channels
                            : [];
                    $sel.empty();
                    $sel.append(
                        jQuery('<option></option>').attr('value', '').text(LC.select_channel || '')
                    );
                    if (!channels.length) {
                        $empty.show();
                        $sel.prop('disabled', false).show();
                        return;
                    }
                    jQuery.each(channels, function (_, ch) {
                        if (!ch || typeof ch.id === 'undefined') {
                            return;
                        }
                        var id = String(ch.id);
                        var $opt = jQuery('<option></option>')
                            .attr('value', id)
                            .text(ch.title || id);
                        if (id === selected) {
                            $opt.prop('selected', true);
                        }
                        $sel.append($opt);
                    });
                    $sel.prop('disabled', false).show();
                })
                .fail(function () {
                    $msg.hide();
                    $err.text(LC.channels_failed || '').show();
                });
        });

        jQuery('.rch-async-tags').each(function () {
            var $wrap = jQuery(this);
            if ($wrap.data('rchTagsInit')) {
                return;
            }
            var sid = $wrap.attr('data-select-id');
            var cid = $wrap.attr('data-container-id');
            var hid = $wrap.attr('data-hidden-input-id');
            var $msg = $wrap.find('.rch-tags-loading-msg');
            var elSel = sid ? document.getElementById(sid) : null;
            var elC = cid ? document.getElementById(cid) : null;
            var elH = hid ? document.getElementById(hid) : null;
            var $sel = elSel ? jQuery(elSel) : jQuery();
            var $container = elC ? jQuery(elC) : jQuery();
            var $hidden = elH ? jQuery(elH) : jQuery();
            if (!$sel.length || !$container.length || !$hidden.length) {
                return;
            }
            $wrap.data('rchTagsInit', true);
            var $empty = $wrap.find('.rch-tags-empty');
            var $err = $wrap.find('.rch-tags-error');

            rchFetchTagsSettingsOnce()
                .done(function (response) {
                    $msg.hide();
                    if (!response || !response.success) {
                        var m =
                            response && response.data && response.data.message
                                ? response.data.message
                                : LC.tags_failed || '';
                        $err.text(m).show();
                        return;
                    }
                    var tags =
                        response.data && jQuery.isArray(response.data.tags) ? response.data.tags : [];
                    $sel.empty();
                    $sel.append(
                        jQuery('<option></option>')
                            .attr('value', '')
                            .text(LC.select_tag || '')
                    );
                    if (!tags.length) {
                        $empty.show();
                        $sel.prop('disabled', false).show();
                        return;
                    }
                    jQuery.each(tags, function (_, t) {
                        if (t && t.tag) {
                            $sel.append(
                                jQuery('<option></option>').attr('value', t.tag).text(t.tag)
                            );
                        }
                    });
                    $sel.prop('disabled', false).show();
                    $container.show();
                    rchBindTagChips($sel, $container, $hidden);
                })
                .fail(function () {
                    $msg.hide();
                    $err.text(LC.tags_failed || '').show();
                });
        });
    }
});
