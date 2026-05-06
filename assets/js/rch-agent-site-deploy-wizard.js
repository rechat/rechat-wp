/* global rchAgentWizard */
(function ($) {
    'use strict';

    var state = {
        agentId: 0,
        blogId: 0,
        title: '',
        meta: {},
        step: 1,
    };

    function getScope() {
        return $('input[name="rch_wz_scope"]:checked').val() === 'all' ? 'all' : 'single';
    }

    function showStep(n) {
        state.step = n;
        $('.rch-agent-wizard-panel').attr('hidden', true).filter('[data-step-panel="' + n + '"]').removeAttr('hidden');
        var $root = $('#rch-agent-site-wizard');
        $root.attr('data-active-step', n);
        $root.find('.rch-wz-stepnav__btn').removeClass('is-active').filter('[data-step="' + n + '"]').addClass('is-active');
    }

    function spin($el, on) {
        $el.toggleClass('is-active', !!on);
    }

    function labelForThemeSlug(slug) {
        var i,
            rows = rchAgentWizard.themeKeys;
        for (i = 0; i < rows.length; i++) {
            if (rows[i].slug === slug) {
                return rows[i].label;
            }
        }
        return slug;
    }

    function labelForMetaKey(key) {
        var m = rchAgentWizard.metaboxLabels;
        if (m && Object.prototype.hasOwnProperty.call(m, key)) {
            return m[key];
        }
        return key;
    }

    function str(name) {
        return (rchAgentWizard.strings && rchAgentWizard.strings[name]) || '';
    }

    function escapeHtml(s) {
        return $('<div>').text(s).html();
    }

    function escapeAttr(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function $rowForKey(key) {
        return $('.rch-wz-theme-row[data-theme-key="' + key + '"]');
    }

    function syncRowVisibility($row) {
        var mode = $row.find('.rch-wz-row-mode').val();
        $row.find('.rch-wz-row-meta-wrap').toggle(mode === 'meta');
        $row.find('.rch-wz-row-manual-wrap').toggle(mode === 'manual');
    }

    function updateMetaPreview($row) {
        var $meta = $row.find('.rch-wz-row-meta');
        var mk = $meta.val();
        var $out = $row.find('.rch-wz-meta-preview');
        if (!mk) {
            $out.text('');
            return;
        }
        var v = state.meta && Object.prototype.hasOwnProperty.call(state.meta, mk) ? String(state.meta[mk] || '') : '';
        if (getScope() === 'all') {
            $out.text(rchAgentWizard.strings.bulkPreview);
        } else {
            $out.text(v ? v.substring(0, 200) : '—');
        }
    }

    function bindRowHandlers() {
        $(document)
            .off('change.rchwz', '.rch-wz-row-mode')
            .on('change.rchwz', '.rch-wz-row-mode', function () {
                var $row = $(this).closest('.rch-wz-theme-row');
                syncRowVisibility($row);
                updateMetaPreview($row);
            });
        $(document)
            .off('change.rchwzmeta', '.rch-wz-row-meta')
            .on('change.rchwzmeta', '.rch-wz-row-meta', function () {
                updateMetaPreview($(this).closest('.rch-wz-theme-row'));
            });
    }

    function initAllRows() {
        $('.rch-wz-theme-row').each(function () {
            syncRowVisibility($(this));
            updateMetaPreview($(this));
        });
    }

    function collectThemeRows() {
        var out = {};
        $('.rch-wz-theme-row').each(function () {
            var $row = $(this);
            var key = $row.attr('data-theme-key');
            var mode = $row.find('.rch-wz-row-mode').val() || 'skip';
            if (mode === 'skip') {
                return;
            }
            if (mode === 'manual') {
                out[key] = {
                    mode: 'manual',
                    value: $row.find('.rch-wz-row-value').val(),
                };
                return;
            }
            if (mode === 'meta') {
                var mk = $row.find('.rch-wz-row-meta').val() || '';
                if (!mk) {
                    return;
                }
                out[key] = {
                    mode: 'meta',
                    meta_key: mk,
                };
            }
        });
        return out;
    }

    function previewResolvedRow(themeKey, cfg) {
        if (!cfg || cfg.mode === 'skip') {
            return null;
        }
        var themeLabel = labelForThemeSlug(themeKey);
        if (cfg.mode === 'manual') {
            var raw = typeof cfg.value === 'string' ? cfg.value : '';
            return {
                mode: 'manual',
                themeKey: themeKey,
                themeLabel: themeLabel,
                valuePreview: raw.length ? raw : '—',
            };
        }
        if (cfg.mode === 'meta' && cfg.meta_key) {
            var v =
                state.meta && Object.prototype.hasOwnProperty.call(state.meta, cfg.meta_key)
                    ? String(state.meta[cfg.meta_key] || '')
                    : '';
            return {
                mode: 'meta',
                themeKey: themeKey,
                themeLabel: themeLabel,
                metaLabel: labelForMetaKey(cfg.meta_key),
                valuePreview: getScope() === 'all' ? '…' : (v.length ? v.substring(0, 240) : '—'),
            };
        }
        return null;
    }

    function refreshPreview() {
        var rows = collectThemeRows();
        var scope = getScope();
        var items = [];
        $.each(rows, function (tk, cfg) {
            var it = previewResolvedRow(tk, cfg);
            if (it) {
                items.push(it);
            }
        });

        var warn = '';
        if (scope === 'single' && !state.blogId) {
            warn =
                '<p class="rch-wz-summary-warn"><span class="notice-warning">' +
                escapeHtml(rchAgentWizard.strings.noBlog) +
                '</span></p>';
        }
        if (scope === 'all' && !(rchAgentWizard.bulkCount > 0)) {
            warn =
                '<p class="rch-wz-summary-warn"><span class="notice-warning">' +
                escapeHtml(rchAgentWizard.strings.bulkNoSites) +
                '</span></p>';
        }

        var contextHtml = '';
        if (scope === 'single') {
            contextHtml =
                '<div class="rch-wz-summary-card">' +
                '<p class="rch-wz-summary-card__label">' +
                escapeHtml(str('scopeSingle')) +
                '</p>' +
                '<p class="rch-wz-summary-card__main">' +
                '<strong>' +
                escapeHtml(state.title || '') +
                '</strong>' +
                '<span class="rch-wz-summary-card__meta"> · ID ' +
                escapeHtml(String(state.agentId)) +
                ' · blog ' +
                escapeHtml(String(state.blogId)) +
                '</span></p>' +
                warn +
                '</div>';
        } else {
            var cnt = rchAgentWizard.bulkCount || 0;
            var sitesLine = (str('sitesCount') || '%d sites will be updated.').replace('%d', String(cnt));
            contextHtml =
                '<div class="rch-wz-summary-card">' +
                '<p class="rch-wz-summary-card__label">' +
                escapeHtml(str('scopeAll')) +
                '</p>' +
                '<p class="rch-wz-summary-card__main"><strong>' +
                escapeHtml(sitesLine) +
                '</strong></p>' +
                '<p class="rch-wz-summary-card__hint">' +
                escapeHtml(rchAgentWizard.strings.bulkPreview) +
                '</p>' +
                warn +
                '</div>';
        }
        $('#rch-wz-deploy-summary').html(contextHtml);

        var readable = '';
        if (!items.length) {
            readable =
                '<div class="rch-wz-preview-empty">' +
                '<p class="rch-wz-preview-empty__text">' +
                escapeHtml(str('previewEmpty')) +
                '</p></div>';
        } else {
            readable += '<h4 class="rch-wz-preview-title">' + escapeHtml(str('previewHeading')) + '</h4>';
            readable += '<ul class="rch-wz-preview-list">';
            var i;
            for (i = 0; i < items.length; i++) {
                var it = items[i];
                var badge =
                    it.mode === 'manual'
                        ? '<span class="rch-wz-preview-badge rch-wz-preview-badge--manual">' +
                          escapeHtml(str('badgeManual')) +
                          '</span>'
                        : '<span class="rch-wz-preview-badge rch-wz-preview-badge--meta">' +
                          escapeHtml(str('badgeMeta')) +
                          '</span>';
                var sub =
                    it.mode === 'meta'
                        ? '<p class="rch-wz-preview-item__meta">' + escapeHtml(it.metaLabel) + '</p>'
                        : '';
                readable +=
                    '<li class="rch-wz-preview-item">' +
                    '<div class="rch-wz-preview-item__head">' +
                    '<span class="rch-wz-preview-item__theme">' +
                    escapeHtml(it.themeLabel) +
                    '</span>' +
                    badge +
                    '</div>' +
                    sub +
                    '<div class="rch-wz-preview-item__body">' +
                    '<span class="rch-wz-preview-item__vl">' +
                    escapeHtml(str('valuePreview')) +
                    '</span>' +
                    '<div class="rch-wz-preview-item__value">' +
                    $('<div/>').text(it.valuePreview).html() +
                    '</div></div></li>';
            }
            readable += '</ul>';
        }
        $('#rch-wz-preview-readable').html(readable);

        $('#rch-wz-json-preview').text(JSON.stringify({ theme_rows: rows }, null, 2));
        $('#rch-wz-tech-details').prop('open', false);
    }

    function canLeaveStep1() {
        var scope = getScope();
        if (scope === 'all') {
            if (!(rchAgentWizard.bulkCount > 0)) {
                alert(rchAgentWizard.strings.bulkNoSites);
                return false;
            }
            return true;
        }
        if (!state.agentId) {
            alert(rchAgentWizard.strings.pickAgent);
            return false;
        }
        return true;
    }

    function openMedia(targetId, isVideo) {
        var frame = wp.media({
            title: isVideo ? 'Select video' : 'Select image',
            button: { text: 'Use this file' },
            multiple: false,
            library: isVideo ? { type: 'video' } : { type: 'image' },
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            var url = att.url || '';
            $('#' + targetId).val(url).trigger('change');
        });
        frame.open();
    }

    function applyThemeRowsToDom(rows) {
        if (!rows || typeof rows !== 'object') {
            return;
        }
        $.each(rows, function (key, cfg) {
            var $row = $rowForKey(key);
            if (!$row.length) {
                return;
            }
            var mode = cfg.mode || 'skip';
            $row.find('.rch-wz-row-mode').val(mode);
            if (cfg.meta_key) {
                $row.find('.rch-wz-row-meta').val(cfg.meta_key);
            }
            if (cfg.value !== undefined && cfg.value !== null) {
                $row.find('.rch-wz-row-value').val(cfg.value);
            }
            syncRowVisibility($row);
            updateMetaPreview($row);
        });
    }

    $(function () {
        bindRowHandlers();
        initAllRows();
        showStep(1);

        $('input[name="rch_wz_scope"]').on('change', function () {
            $('#rch-wz-single-agent-wrap').toggle(getScope() === 'single');
            $('.rch-wz-theme-row').each(function () {
                updateMetaPreview($(this));
            });
        });

        $('#rch-wz-load-agent').on('click', function () {
            var id = parseInt($('#rch-wz-agent-select').val(), 10);
            if (!id) {
                alert(rchAgentWizard.strings.pickAgent);
                return;
            }
            spin($('#rch-wz-agent-spinner'), true);
            $.post(rchAgentWizard.ajaxurl, {
                action: 'rch_agent_wizard_load_agent',
                nonce: rchAgentWizard.nonce,
                agent_id: id,
            })
                .done(function (res) {
                    spin($('#rch-wz-agent-spinner'), false);
                    if (!res.success) {
                        alert(res.data && res.data.message ? res.data.message : 'Error');
                        return;
                    }
                    var d = res.data;
                    state.agentId = d.agent_id;
                    state.blogId = d.blog_id;
                    state.title = d.title;
                    state.meta = d.meta || {};
                    $('#rch-wz-agent-summary')
                        .removeAttr('hidden')
                        .html(
                            '<p><strong>' +
                                $('<div>').text(d.title).html() +
                                '</strong></p><p>Blog ID: ' +
                                d.blog_id +
                                '</p>'
                        );
                    $('.rch-wz-theme-row').each(function () {
                        updateMetaPreview($(this));
                    });
                })
                .fail(function () {
                    spin($('#rch-wz-agent-spinner'), false);
                    alert('Request failed');
                });
        });

        $('#rch-wz-load-draft').on('click', function () {
            $.post(rchAgentWizard.ajaxurl, {
                action: 'rch_agent_wizard_load_draft',
                nonce: rchAgentWizard.nonce,
            }).done(function (res) {
                if (!res.success || !res.data.draft) {
                    alert('No draft');
                    return;
                }
                var dr = res.data.draft;
                if (dr.scope === 'all') {
                    $('#rch-wz-scope-all').prop('checked', true);
                } else {
                    $('#rch-wz-scope-single').prop('checked', true);
                }
                $('#rch-wz-single-agent-wrap').toggle(getScope() === 'single');
                if (dr.agentId) {
                    $('#rch-wz-agent-select').val(dr.agentId);
                }
                if (dr.meta) {
                    state.meta = dr.meta;
                }
                if (dr.agentId) {
                    state.agentId = dr.agentId;
                }
                if (dr.blogId !== undefined) {
                    state.blogId = dr.blogId;
                }
                if (dr.title) {
                    state.title = dr.title;
                }
                if (dr.themeRows) {
                    applyThemeRowsToDom(dr.themeRows);
                }
                showStep(dr.step || 1);
                alert('Draft restored');
            });
        });

        $('.rch-wz-next').on('click', function () {
            var next = parseInt($(this).data('next'), 10);
            if (next === 2 && !canLeaveStep1()) {
                return;
            }
            if (next === 3) {
                refreshPreview();
            }
            showStep(next);
        });

        $('.rch-wz-prev').on('click', function () {
            showStep(parseInt($(this).data('prev'), 10));
        });

        $('.rch-wz-goto').on('click', function () {
            var s = parseInt($(this).data('step'), 10);
            if (s >= 2 && !canLeaveStep1()) {
                return;
            }
            if (s === 3) {
                refreshPreview();
            }
            showStep(s);
        });

        $(document).on('click', '.rch-wz-media', function (e) {
            e.preventDefault();
            var id = $(this).data('target');
            var isVideo = $(this).hasClass('rch-wz-media-video');
            openMedia(id, isVideo);
        });

        $('#rch-wz-refresh-preview').on('click', refreshPreview);

        $('#rch-wz-save-draft').on('click', function () {
            var draft = {
                scope: getScope(),
                agentId: state.agentId,
                blogId: state.blogId,
                title: state.title,
                meta: state.meta,
                step: state.step,
                themeRows: collectThemeRows(),
            };
            $.post(rchAgentWizard.ajaxurl, {
                action: 'rch_agent_wizard_save_draft',
                nonce: rchAgentWizard.nonce,
                draft: JSON.stringify(draft),
            }).done(function (res) {
                alert(res.success ? res.data.message : res.data && res.data.message ? res.data.message : 'Error');
            });
        });

        $('#rch-wz-deploy').on('click', function () {
            var scope = getScope();
            if (scope === 'single' && !state.blogId) {
                alert(rchAgentWizard.strings.noBlog);
                return;
            }
            if (scope === 'all' && !(rchAgentWizard.bulkCount > 0)) {
                alert(rchAgentWizard.strings.bulkNoSites);
                return;
            }
            spin($('#rch-wz-deploy-spinner'), true);
            $('#rch-wz-deploy-result').empty();
            $.post(rchAgentWizard.ajaxurl, {
                action: 'rch_agent_wizard_deploy',
                nonce: rchAgentWizard.nonce,
                scope: scope,
                agent_id: state.agentId,
                theme_rows_json: JSON.stringify(collectThemeRows()),
            })
                .done(function (res) {
                    spin($('#rch-wz-deploy-spinner'), false);
                    if (!res.success) {
                        $('#rch-wz-deploy-result').html(
                            '<div class="notice notice-error inline"><p>' +
                                escapeHtml(res.data && res.data.message ? res.data.message : rchAgentWizard.strings.deployFail) +
                                '</p></div>'
                        );
                        return;
                    }
                    var html = '<div class="notice notice-success inline"><p>' + escapeHtml(res.data.message) + '</p>';
                    if (res.data.errors && res.data.errors.length) {
                        html += '<p><strong>Warnings</strong></p><ul>';
                        $.each(res.data.errors, function (i, err) {
                            html += '<li>' + escapeHtml(err) + '</li>';
                        });
                        html += '</ul>';
                    }
                    html += '</div>';
                    $('#rch-wz-deploy-result').html(html);
                })
                .fail(function () {
                    spin($('#rch-wz-deploy-spinner'), false);
                    $('#rch-wz-deploy-result').html(
                        '<div class="notice notice-error inline"><p>Request failed</p></div>'
                    );
                });
        });
    });
})(jQuery);
