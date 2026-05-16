(function ($) {
    'use strict';

    if (typeof rchAgentImport === 'undefined') {
        return;
    }

    var $form = $('#rch-agent-import-form');
    if (!$form.length) {
        return;
    }

    var $file = $('#rch-import-csv-file');
    var $fileName = $('#rch-import-file-name');
    var $dropzone = $('#rch-import-dropzone');
    var $previewBtn = $('#rch-import-preview-btn');
    var $runBtn = $('#rch-import-run-btn');
    var $feedback = $('#rch-import-feedback');
    var $results = $('#rch-import-results');
    var $tbody = $('#rch-import-table-body');
    var $summary = $('#rch-import-summary');
    var $errors = $('#rch-import-errors');
    var lastPreview = null;

    function setFeedback(message, type) {
        $feedback.removeClass('is-error is-success').attr('hidden', false);
        if (type === 'error') {
            $feedback.addClass('is-error');
        } else if (type === 'success') {
            $feedback.addClass('is-success');
        }
        $feedback.html(message);
    }

    function clearFeedback() {
        $feedback.attr('hidden', true).empty().removeClass('is-error is-success');
    }

    function buildFormData(action) {
        var fd = new FormData();
        var file = $file[0].files[0];
        if (!file) {
            return null;
        }
        fd.append('action', action);
        fd.append('nonce', rchAgentImport.nonce);
        fd.append('csv_file', file);
        fd.append('match_by', $('#rch-import-match-by').val());
        fd.append('import_bio', $('#rch-import-bio').is(':checked') ? '1' : '');
        fd.append('import_testimonials', $('#rch-import-testimonials').is(':checked') ? '1' : '');
        fd.append('testimonial_mode', $('input[name="testimonial_mode"]:checked').val() || 'replace');
        return fd;
    }

    function pill(text, mod) {
        return '<span class="rch-agent-import__pill' + (mod ? ' rch-agent-import__pill--' + mod : '') + '">' + text + '</span>';
    }

    function renderSummary(data) {
        var s = data.summary || {};
        var html = '';
        html += pill((s.total_rows || 0) + ' rows', '');
        html += pill((s.agents_ok || 0) + ' ready', 'ok');
        if (s.agents_skip) {
            html += pill(s.agents_skip + ' skipped', 'warn');
        }
        if (s.errors) {
            html += pill(s.errors + ' errors', 'err');
        }
        if (s.bios) {
            html += pill(s.bios + ' bios', 'ok');
        }
        if (s.testimonials) {
            html += pill(s.testimonials + ' new testimonials', 'ok');
        }
        if (s.updated !== undefined) {
            html += pill(s.updated + ' updated', 'ok');
        }
        $summary.html(html);
    }

    function renderTable(agents) {
        $tbody.empty();
        if (!agents || !agents.length) {
            $tbody.append('<tr><td colspan="5">' + rchAgentImport.i18n.noAgents + '</td></tr>');
            return;
        }
        agents.forEach(function (row) {
            var statusClass = 'rch-status-' + (row.status || 'skip');
            var bioCell = row.bio ? '✓' + (row.bio_preview ? ' ' + $('<div>').text(row.bio_preview).html() : '') : '—';
            var tCell = '—';
            if (row.testimonials_new) {
                tCell = row.testimonials_new + ' new';
                if (row.testimonial_mode === 'merge' && row.testimonials_count) {
                    tCell += ' → ' + row.testimonials_count + ' total';
                }
            } else if (row.testimonials_count) {
                tCell = String(row.testimonials_count);
            }
            var msg = row.message ? '<br><small>' + $('<div>').text(row.message).html() + '</small>' : '';
            $tbody.append(
                '<tr>' +
                '<td><strong>' + $('<div>').text(row.agent_title || '—').html() + '</strong></td>' +
                '<td>' + (row.agent_id || '—') + '</td>' +
                '<td>' + bioCell + '</td>' +
                '<td>' + tCell + '</td>' +
                '<td class="' + statusClass + '">' + (row.status || '') + msg + '</td>' +
                '</tr>'
            );
        });
    }

    function renderErrors(list) {
        $errors.empty();
        if (!list || !list.length) {
            return;
        }
        list.forEach(function (err) {
            $errors.append($('<li>').text(err));
        });
    }

    function handleResponse(data, isImport) {
        lastPreview = data;
        $results.attr('hidden', false);
        renderSummary(data);
        renderTable(data.agents || []);
        var errs = (data.errors || []).concat(data.parse_errors || []);
        renderErrors(errs);

        var ready = (data.summary && data.summary.agents_ok) || 0;
        $runBtn.prop('disabled', ready < 1);

        if (isImport) {
            setFeedback(data.message || rchAgentImport.i18n.importDone, 'success');
        } else {
            setFeedback(rchAgentImport.i18n.previewReady, 'success');
        }
    }

    function ajaxImport(action, isImport) {
        var fd = buildFormData(action);
        if (!fd) {
            setFeedback(rchAgentImport.i18n.chooseFile, 'error');
            return;
        }

        if (!$('#rch-import-bio').is(':checked') && !$('#rch-import-testimonials').is(':checked')) {
            setFeedback(rchAgentImport.i18n.selectOne, 'error');
            return;
        }

        if (isImport && !window.confirm(rchAgentImport.i18n.confirmImport)) {
            return;
        }

        var $btn = isImport ? $runBtn : $previewBtn;
        $btn.prop('disabled', true);
        clearFeedback();
        setFeedback(
            (isImport ? rchAgentImport.i18n.importing : rchAgentImport.i18n.previewing) +
            ' <span class="spinner is-active rch-agent-import__spinner"></span>'
        );

        $.ajax({
            url: rchAgentImport.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
        })
            .done(function (res) {
                if (res.success && res.data) {
                    handleResponse(res.data, isImport);
                } else {
                    var msg = (res.data && res.data.message) ? res.data.message : (isImport ? rchAgentImport.i18n.importFailed : rchAgentImport.i18n.previewFailed);
                    setFeedback(msg, 'error');
                    $runBtn.prop('disabled', true);
                }
            })
            .fail(function (xhr) {
                var msg = rchAgentImport.i18n.previewFailed;
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                setFeedback(msg, 'error');
                $runBtn.prop('disabled', true);
            })
            .always(function () {
                $previewBtn.prop('disabled', false);
                if (lastPreview && lastPreview.summary && lastPreview.summary.agents_ok > 0) {
                    $runBtn.prop('disabled', false);
                }
            });
    }

    $file.on('change', function () {
        var name = this.files[0] ? this.files[0].name : rchAgentImport.i18n.chooseFile;
        $fileName.text(name);
        $runBtn.prop('disabled', true);
        lastPreview = null;
    });

    $dropzone.on('dragover dragenter', function (e) {
        e.preventDefault();
        $dropzone.addClass('is-dragover');
    });
    $dropzone.on('dragleave dragend drop', function (e) {
        e.preventDefault();
        $dropzone.removeClass('is-dragover');
    });
    $dropzone.on('drop', function (e) {
        var files = e.originalEvent.dataTransfer.files;
        if (files.length) {
            $file[0].files = files;
            $file.trigger('change');
        }
    });

    $previewBtn.on('click', function () {
        ajaxImport('rch_agent_import_preview', false);
    });

    $runBtn.on('click', function () {
        ajaxImport('rch_agent_import_run', true);
    });
})(jQuery);
