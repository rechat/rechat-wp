/* global rchOfficeWizard */
(function ($) {
    'use strict';

    var state = {
        agentId: 0,
        blogId: 0,
        title: '',
        meta: {},
        step: 1,
        /** @type {Record<number, boolean>} */
        broadcastPick: {},
        bcPaged: 1,
        bcMaxPages: 0,
        bcFound: 0,
        /** @type {Record<number, boolean>} */
        mwMenuPick: {},
        /** @type {{ id: string, title: string, url: string, sourcePostId?: number }[]} */
        menuBuilderItems: [],
        /** @type {Record<string, boolean>} */
        menuBuilderLoc: {},
        mbSearchPaged: 1,
        mbSearchMaxPages: 1,
        mbBcPaged: 1,
        mbBcMaxPages: 0,
        mbBcFound: 0,
        mbBcTotal: 0,
        testimonialCount: 0,
    };

    /** False until rch_agent_wizard_load_draft finishes (prevents silent persist wiping server draft before restore). */
    var draftHydrationDone = false;

    function getScope() {
        return $('input[name="rch_wz_scope"]:checked').val() === 'all' ? 'all' : 'single';
    }

    function wizardBroadcastPanelStep() {
        return 3;
    }

    function wizardMenusStep() {
        return rchOfficeWizard.broadcastStep ? 4 : 3;
    }

    function wizardPreviewStep() {
        return rchOfficeWizard.broadcastStep ? 5 : 4;
    }

    /**
     * Remap saved draft step from older wizard layouts (preview index + broadcast/menus order).
     *
     * @param {number} step
     * @param {number} dv draftVersion from payload
     * @param {boolean} hasBc
     * @returns {number}
     */
    function migrateWizardDraftStep(step, dv, hasBc) {
        if (dv >= 4) {
            return step;
        }
        var s = step;
        if (hasBc && dv < 3) {
            if (s === 4) {
                s = 5;
            } else if (s === 5) {
                s = 4;
            }
        }
        if (dv <= 3) {
            if (hasBc) {
                if (s === 3) {
                    return 5;
                }
                if (s === 4) {
                    return 3;
                }
                if (s === 5) {
                    return 4;
                }
            } else {
                if (s === 3) {
                    return 4;
                }
                if (s === 4) {
                    return 3;
                }
            }
        }
        return s;
    }

    function showStep(n) {
        state.step = n;
        $('.rch-agent-wizard-panel').attr('hidden', true).filter('[data-step-panel="' + n + '"]').removeAttr('hidden');
        var $root = $('#rch-agent-site-wizard');
        $root.attr('data-active-step', n);
        $root.find('.rch-wz-stepnav__btn').removeClass('is-active').filter('[data-step="' + n + '"]').addClass('is-active');
        var ms = wizardMenusStep();
        var ps = wizardPreviewStep();
        var bs = wizardBroadcastPanelStep();
        if (rchOfficeWizard.broadcastStep) {
            if (n === bs) {
                fillBcTargetSummary();
                loadBroadcastPosts(state.bcPaged || 1);
            } else if (n === ms) {
                fillMwTargetSummary();
                loadMwList();
            } else if (n === ps) {
                refreshPreview();
            }
        } else {
            if (n === ms) {
                fillMwTargetSummary();
                loadMwList();
            } else if (n === ps) {
                refreshPreview();
            }
        }
    }

    function normalizeWizardTargetMode(mode) {
        var allowed = ['agent_only', 'office_only', 'all_subsites', 'agent_office'];
        if (allowed.indexOf(mode) !== -1) {
            return mode;
        }
        return 'agent_only';
    }

    function getMwTargetMode() {
        return normalizeWizardTargetMode(String($('input[name="rch_wz_mw_target"]:checked').val() || 'agent_only'));
    }

    // Widget copy mode: 'none' | 'asis' | 'broadcast' (broadcast = copy + relink menu links to sub-site copies).
    function getMwWidgetMode() {
        var v = String($('input[name="rch_wz_mw_widget_mode"]:checked').val() || 'none');
        if (v !== 'asis' && v !== 'broadcast') {
            return 'none';
        }
        return v;
    }

    function setMwWidgetMode(mode) {
        var v = mode === 'asis' || mode === 'broadcast' ? mode : 'none';
        var $el = $('#rch-wz-mw-widgets-' + v);
        if (!$el.length) {
            v = 'none';
            $el = $('#rch-wz-mw-widgets-none');
        }
        $el.prop('checked', true);
    }

    function fillMwTargetSummary() {
        var c = rchOfficeWizard.menusWidgetsTargetCounts || {};
        var na = c.agent_only != null ? c.agent_only : 0;
        var no = c.office_only != null ? c.office_only : 0;
        var nb = c.all_subsites != null ? c.all_subsites : 0;
        var src = rchOfficeWizard.menusWidgetsSource || {};
        var line = (str('mwSourceLine') || '')
            .replace('%s', String(src.label || ''))
            .replace('%d', String(src.blog_id || ''));
        var t1 = (str('mwTargetAgents') || '').replace('%d', String(na));
        var t2 = (str('mwTargetOffices') || '').replace('%d', String(no));
        var t3 = (str('mwTargetAll') || '').replace('%d', String(nb));
        $('#rch-wz-mw-target-summary').html(
            '<strong>' +
                escapeHtml(line) +
                '</strong><br />' +
                escapeHtml(t1) +
                '<br />' +
                escapeHtml(t2) +
                '<br />' +
                escapeHtml(t3)
        );
    }

    function collectMwMenuTermIds() {
        var out = [];
        var k;
        for (k in state.mwMenuPick) {
            if (Object.prototype.hasOwnProperty.call(state.mwMenuPick, k) && state.mwMenuPick[k]) {
                out.push(parseInt(k, 10));
            }
        }
        return out;
    }

    function mbMakeId() {
        return 'mb' + Date.now() + Math.random().toString(36).slice(2, 9);
    }

    function renderMenuBuilderTable() {
        var $tb = $('#rch-wz-mb-items');
        $tb.empty();
        var rows = state.menuBuilderItems;
        $('#rch-wz-mb-items-empty').toggle(rows.length === 0);
        var i;
        for (i = 0; i < rows.length; i++) {
            var r = rows[i];
            var idx = i;
            $tb.append(
                '<tr data-mb-idx="' +
                    idx +
                    '">' +
                    '<td><input type="text" class="rch-wz-input rch-wz-mb-item-title" value="' +
                    escapeAttr(r.title || '') +
                    '" /></td>' +
                    '<td><input type="url" class="rch-wz-input rch-wz-mb-item-url" value="' +
                    escapeAttr(r.url || '') +
                    '" /></td>' +
                    '<td class="rch-wz-mb-col-actions">' +
                    '<button type="button" class="button rch-wz-mb-move-up" data-mb-idx="' +
                    idx +
                    '">' +
                    escapeHtml(str('mbUp')) +
                    '</button> ' +
                    '<button type="button" class="button rch-wz-mb-move-down" data-mb-idx="' +
                    idx +
                    '">' +
                    escapeHtml(str('mbDown')) +
                    '</button> ' +
                    '<button type="button" class="button rch-wz-mb-remove" data-mb-idx="' +
                    idx +
                    '">' +
                    escapeHtml(str('mbRemove')) +
                    '</button></td></tr>'
            );
        }
    }

    function renderMbLocations(locs) {
        var $w = $('#rch-wz-mb-locs');
        $w.empty();
        var has = Array.isArray(locs) && locs.length > 0;
        $('#rch-wz-mb-locs-empty').toggle(!has);
        if (!has) {
            return;
        }
        var i;
        for (i = 0; i < locs.length; i++) {
            var loc = locs[i];
            var slug = String(loc.slug || '');
            if (!slug) {
                continue;
            }
            var c = state.menuBuilderLoc[slug] ? ' checked' : '';
            $w.append(
                '<label class="rch-wz-mw-menu-line"><input type="checkbox" class="rch-wz-mb-loc" data-slug="' +
                    escapeAttr(slug) +
                    '"' +
                    c +
                    ' /> ' +
                    escapeHtml(loc.label || slug) +
                    ' <code>' +
                    escapeHtml(slug) +
                    '</code></label>'
            );
        }
    }

    function collectMenuBuilderLocSlugs() {
        var slugs = [];
        $('.rch-wz-mb-loc:checked').each(function () {
            var s = $(this).data('slug');
            if (s) {
                slugs.push(String(s));
            }
        });
        return slugs;
    }

    function loadMwList() {
        spin($('#rch-wz-mw-spinner'), true);
        $('#rch-wz-mw-result').empty();
        $.post(rchOfficeWizard.ajaxurl, {
            action: 'rch_agent_wizard_list_menus_widgets',
            nonce: rchOfficeWizard.nonce,
        })
            .done(function (res) {
                spin($('#rch-wz-mw-spinner'), false);
                var $wrap = $('#rch-wz-mw-menus');
                $wrap.empty();
                if (!res.success || !res.data) {
                    $wrap.append('<p class="rch-wz-hint">' + escapeHtml('Error') + '</p>');
                    return;
                }
                var menus = res.data.menus || [];
                var i;
                if (!menus.length) {
                    $wrap.append('<p class="rch-wz-hint">' + escapeHtml('—') + '</p>');
                } else {
                    for (i = 0; i < menus.length; i++) {
                        var m = menus[i];
                        var tid = m.term_id;
                        var checked = state.mwMenuPick[tid] ? ' checked' : '';
                        var lab = (m.name || '') + ' (' + (m.count || 0) + ')';
                        $wrap.append(
                            '<label class="rch-wz-mw-menu-line"><input type="checkbox" class="rch-wz-mw-menu-check" data-term-id="' +
                                tid +
                                '"' +
                                checked +
                                ' /> ' +
                                escapeHtml(lab) +
                                '</label>'
                        );
                    }
                }
                var side = res.data.sidebars || [];
                var parts = [];
                for (i = 0; i < side.length; i++) {
                    parts.push(side[i].label + ': ' + side[i].count);
                }
                var sn = $('#rch-wz-mw-sidebars-note');
                if (parts.length) {
                    sn.removeAttr('hidden').text((str('mwSidebarLine') || '').replace('%s', parts.join('; ')));
                } else {
                    sn.attr('hidden', true).empty();
                }
                renderMbLocations(res.data.nav_locations || []);
            })
            .fail(function () {
                spin($('#rch-wz-mw-spinner'), false);
                $('#rch-wz-mw-menus').html('<p class="rch-wz-hint">Request failed</p>');
            });
    }

    function mbMenuHasSourcePostId(sourcePostId) {
        var sid = parseInt(sourcePostId, 10) || 0;
        if (!sid) {
            return false;
        }
        var i;
        for (i = 0; i < state.menuBuilderItems.length; i++) {
            if (parseInt(state.menuBuilderItems[i].sourcePostId, 10) === sid) {
                return true;
            }
        }
        return false;
    }

    function mbAddItemsFromPosts(items) {
        var added = 0;
        var j;
        for (j = 0; j < items.length; j++) {
            var it = items[j];
            var sid = parseInt(it.id, 10) || 0;
            if (!sid || mbMenuHasSourcePostId(sid)) {
                continue;
            }
            var title = it.title || '#' + sid;
            state.menuBuilderItems.push({
                id: mbMakeId(),
                title: title,
                url: it.url || '',
                sourcePostId: sid,
            });
            added++;
        }
        if (added) {
            renderMenuBuilderTable();
            scheduleFieldDraftAutosave();
        }
        return added;
    }

    function loadMbBroadcastedPosts(page) {
        if (!rchOfficeWizard.broadcastStep || !$('#rch-wz-mb-bc-tbody').length) {
            return;
        }
        state.mbBcPaged = page || 1;
        spin($('#rch-wz-mb-bc-spinner'), true);
        $.post(rchOfficeWizard.ajaxurl, {
            action: 'rch_agent_wizard_list_menu_broadcasted_posts',
            nonce: rchOfficeWizard.nonce,
            paged: state.mbBcPaged,
            per_page: 20,
            search: $('#rch-wz-mb-bc-search').val() || '',
        })
            .done(function (res) {
                spin($('#rch-wz-mb-bc-spinner'), false);
                var $tb = $('#rch-wz-mb-bc-tbody');
                $tb.empty();
                if (!res.success || !res.data) {
                    $tb.append(
                        '<tr class="rch-wz-bc-placeholder"><td colspan="6">' + escapeHtml('Error') + '</td></tr>'
                    );
                    return;
                }
                var d = res.data;
                state.mbBcMaxPages = d.max_pages || 0;
                state.mbBcFound = d.found || 0;
                state.mbBcTotal = d.total_broadcasted != null ? d.total_broadcasted : 0;
                var info = '';
                if (state.mbBcTotal) {
                    info = (str('mbBroadcastedTotal') || '').replace('%d', String(state.mbBcTotal));
                }
                if (state.mbBcFound) {
                    info +=
                        (info ? ' · ' : '') +
                        'Page ' +
                        state.mbBcPaged +
                        ' / ' +
                        Math.max(1, state.mbBcMaxPages) +
                        ' · ' +
                        state.mbBcFound +
                        ' shown';
                } else {
                    info = info || str('mbBroadcastedEmpty') || str('bcEmpty');
                }
                $('#rch-wz-mb-bc-pageinfo').text(info);
                $('#rch-wz-mb-bc-prev').prop('disabled', state.mbBcPaged <= 1);
                $('#rch-wz-mb-bc-next').prop(
                    'disabled',
                    state.mbBcMaxPages < 1 || state.mbBcPaged >= state.mbBcMaxPages
                );
                var items = d.items || [];
                if (!items.length) {
                    $tb.append(
                        '<tr class="rch-wz-bc-placeholder"><td colspan="6">' +
                            escapeHtml(str('mbBroadcastedEmpty') || str('bcEmpty')) +
                            '</td></tr>'
                    );
                    return;
                }
                var i;
                for (i = 0; i < items.length; i++) {
                    var it = items[i];
                    var id = it.id;
                    var title = it.title || '(#' + id + ')';
                    var sites =
                        it.child_count != null
                            ? String(it.child_count)
                            : '—';
                    $tb.append(
                        '<tr>' +
                            '<td><input type="checkbox" class="rch-wz-mb-bc-check" data-id="' +
                            id +
                            '" /></td>' +
                            '<td>' +
                            escapeHtml(title) +
                            '</td>' +
                            '<td>' +
                            escapeHtml(it.type_label || it.type || '') +
                            '</td>' +
                            '<td>' +
                            escapeHtml(sites) +
                            '</td>' +
                            '<td>' +
                            escapeHtml(it.modified || '') +
                            '</td>' +
                            '<td><button type="button" class="button button-small rch-wz-mb-bc-add-hit" data-id="' +
                            id +
                            '">' +
                            escapeHtml(str('mbAdd')) +
                            '</button></td>' +
                            '</tr>'
                    );
                }
            })
            .fail(function () {
                spin($('#rch-wz-mb-bc-spinner'), false);
                $('#rch-wz-mb-bc-tbody').html(
                    '<tr class="rch-wz-bc-placeholder"><td colspan="6">Request failed</td></tr>'
                );
            });
    }

    function mbAddBroadcastedByIds(ids, $feedback) {
        if (!ids.length) {
            alert(str('mbBroadcastedNoneSelected'));
            return;
        }
        $.post(rchOfficeWizard.ajaxurl, {
            action: 'rch_agent_wizard_menu_builder_posts_by_ids',
            nonce: rchOfficeWizard.nonce,
            post_ids: JSON.stringify(ids),
        })
            .done(function (res) {
                if (!res.success || !res.data) {
                    if ($feedback) {
                        $feedback.text('Error');
                    }
                    return;
                }
                var added = mbAddItemsFromPosts(res.data.items || []);
                if ($feedback) {
                    if (added) {
                        $feedback.text((str('mbBroadcastedAdded') || '').replace('%d', String(added)));
                    } else {
                        $feedback.text(str('mbBroadcastedAllDup') || '');
                    }
                }
            })
            .fail(function () {
                if ($feedback) {
                    $feedback.text('Request failed');
                }
            });
    }

    function getBcTargetMode() {
        return normalizeWizardTargetMode(String($('input[name="rch_wz_bc_target"]:checked').val() || 'agent_only'));
    }

    function fillBcTargetSummary() {
        if (!rchOfficeWizard.broadcastStep) {
            return;
        }
        var c = rchOfficeWizard.broadcastTargetCounts || {};
        var na = c.agent_only != null ? c.agent_only : 0;
        var no = c.office_only != null ? c.office_only : 0;
        var nb = c.all_subsites != null ? c.all_subsites : 0;
        var src = rchOfficeWizard.broadcastSource || {};
        var line = (str('bcSourceLine') || 'Source: %s (blog ID %d)')
            .replace('%s', String(src.label || ''))
            .replace('%d', String(src.blog_id || ''));
        var t1 = (str('bcTargetAgents') || '').replace('%d', String(na));
        var t2 = (str('bcTargetOffices') || '').replace('%d', String(no));
        var t3 = (str('bcTargetAll') || '').replace('%d', String(nb));
        $('#rch-wz-bc-target-summary').html(
            '<strong>' +
                escapeHtml(line) +
                '</strong><br />' +
                escapeHtml(t1) +
                '<br />' +
                escapeHtml(t2) +
                '<br />' +
                escapeHtml(t3)
        );
    }

    function loadBroadcastPosts(page) {
        if (!rchOfficeWizard.broadcastStep) {
            return;
        }
        state.bcPaged = page || 1;
        spin($('#rch-wz-bc-spinner'), true);
        $('#rch-wz-bc-result').empty();
        $.post(rchOfficeWizard.ajaxurl, {
            action: 'rch_agent_wizard_list_broadcast_posts',
            nonce: rchOfficeWizard.nonce,
            paged: state.bcPaged,
            per_page: 20,
            search: $('#rch-wz-bc-search').val() || '',
        })
            .done(function (res) {
                spin($('#rch-wz-bc-spinner'), false);
                var $tb = $('#rch-wz-bc-tbody');
                $tb.empty();
                if (!res.success || !res.data) {
                    $tb.append(
                        '<tr class="rch-wz-bc-placeholder"><td colspan="6">' + escapeHtml('Error') + '</td></tr>'
                    );
                    return;
                }
                var d = res.data;
                state.bcMaxPages = d.max_pages || 1;
                state.bcFound = d.found || 0;
                var items = d.items || [];
                if (state.bcFound) {
                    $('#rch-wz-bc-pageinfo').text(
                        'Page ' +
                            state.bcPaged +
                            ' / ' +
                            state.bcMaxPages +
                            ' · ' +
                            state.bcFound +
                            ' items'
                    );
                } else {
                    $('#rch-wz-bc-pageinfo').text(str('bcEmpty'));
                }
                $('#rch-wz-bc-prev').prop('disabled', state.bcPaged <= 1);
                $('#rch-wz-bc-next').prop('disabled', state.bcPaged >= state.bcMaxPages);
                if (!items.length) {
                    $tb.append(
                        '<tr class="rch-wz-bc-placeholder"><td colspan="6">' +
                            escapeHtml(str('bcEmpty')) +
                            '</td></tr>'
                    );
                    return;
                }
                var i;
                for (i = 0; i < items.length; i++) {
                    var it = items[i];
                    var id = it.id;
                    var checked = state.broadcastPick[id] ? ' checked' : '';
                    var bcCount = it.child_count != null ? parseInt(it.child_count, 10) || 0 : 0;
                    var bcCell = bcCount > 0
                        ? '<span class="rch-wz-bc-badge" title="' +
                              escapeHtml(
                                  (str('bcBroadcastedCount') || '%d sub-site(s)').replace('%d', String(bcCount))
                              ) +
                              '">⇨ ' +
                              String(bcCount) +
                              '</span>'
                        : '<span class="rch-wz-bc-none">—</span>';
                    $tb.append(
                        '<tr class="' +
                            (bcCount > 0 ? 'rch-wz-bc-is-broadcasted' : '') +
                            '">' +
                            '<td><input type="checkbox" class="rch-wz-bc-check" data-id="' +
                            id +
                            '"' +
                            checked +
                            ' /></td>' +
                            '<td>' +
                            escapeHtml(it.title || '(#' + id + ')') +
                            '</td>' +
                            '<td>' +
                            escapeHtml(it.type_label || it.type || '') +
                            '</td>' +
                            '<td>' +
                            escapeHtml(it.status || '') +
                            '</td>' +
                            '<td>' +
                            bcCell +
                            '</td>' +
                            '<td>' +
                            escapeHtml(it.modified || '') +
                            '</td>' +
                            '</tr>'
                    );
                }
            })
            .fail(function () {
                spin($('#rch-wz-bc-spinner'), false);
                $('#rch-wz-bc-tbody').html(
                    '<tr class="rch-wz-bc-placeholder"><td colspan="6">Request failed</td></tr>'
                );
            });
    }

    function collectBroadcastPostIds() {
        var out = [];
        var k;
        for (k in state.broadcastPick) {
            if (Object.prototype.hasOwnProperty.call(state.broadcastPick, k) && state.broadcastPick[k]) {
                out.push(parseInt(k, 10));
            }
        }
        return out;
    }

    function spin($el, on) {
        $el.toggleClass('is-active', !!on);
    }

    function labelForThemeSlug(slug) {
        var i,
            rows = rchOfficeWizard.themeKeys;
        for (i = 0; i < rows.length; i++) {
            if (rows[i].slug === slug) {
                return rows[i].label;
            }
        }
        return slug;
    }

    function labelForMetaKey(key) {
        var m = rchOfficeWizard.metaboxLabels;
        if (m && Object.prototype.hasOwnProperty.call(m, key)) {
            return m[key];
        }
        return key;
    }

    function str(name) {
        return (rchOfficeWizard.strings && rchOfficeWizard.strings[name]) || '';
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
        var s = String(key);
        return $('.rch-wz-theme-row').filter(function () {
            return $(this).attr('data-theme-key') === s;
        });
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
            $out.text(rchOfficeWizard.strings.bulkPreview);
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
        refreshWizardTagChips();
    }

    function parseJsonStringArray(val) {
        try {
            var j = JSON.parse(val);
            return Array.isArray(j) ? j : [];
        } catch (e) {
            return [];
        }
    }

    function renderTagChips($wrap) {
        var $hidden = $wrap.find('.rch-wz-row-value');
        var $chips = $wrap.find('.rch-wz-tag-chips');
        var list = parseJsonStringArray($hidden.val() || '[]');
        $chips.empty();
        var i;
        for (i = 0; i < list.length; i++) {
            var tag = String(list[i]);
            if (!tag) {
                continue;
            }
            var $chip = $('<span class="rch-wz-tag-chip"/>').text(tag + ' ');
            var $x = $('<button type="button" class="rch-wz-tag-chip-x" aria-label="Remove"/>').text('×');
            $x.on('click', function (t) {
                return function () {
                    var cur = parseJsonStringArray($hidden.val() || '[]').filter(function (x) {
                        return String(x) !== t;
                    });
                    $hidden.val(JSON.stringify(cur)).trigger('change');
                };
            }(tag));
            $chip.append($x);
            $chips.append($chip);
        }
    }

    function refreshWizardTagChips() {
        $('.rch-wz-tags-wrap').each(function () {
            renderTagChips($(this));
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

    function formatManualPreviewValue(themeKey, raw) {
        var $row = $rowForKey(themeKey);
        if (!$row.length) {
            return raw.length ? raw : '—';
        }

        if ($row.find('.rch-wz-tags-wrap').length) {
            var tagList = parseJsonStringArray(raw || '[]');
            return tagList.length ? tagList.join(', ') : '—';
        }

        var $val = $row.find('.rch-wz-row-value').first();
        if ($val.is('select')) {
            var selectedVal = String($val.val() || raw || '');
            if (!selectedVal) {
                return '—';
            }
            var $opt = $val.find('option[value="' + escapeAttr(selectedVal) + '"]');
            var label = $opt.length ? String($opt.text()).trim() : '';
            return label !== '' ? label : selectedVal;
        }

        return raw.length ? raw : '—';
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
                valuePreview: formatManualPreviewValue(themeKey, raw),
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
                escapeHtml(rchOfficeWizard.strings.noBlog) +
                '</span></p>';
        }
        if (scope === 'all' && !(rchOfficeWizard.bulkCount > 0)) {
            warn =
                '<p class="rch-wz-summary-warn"><span class="notice-warning">' +
                escapeHtml(rchOfficeWizard.strings.bulkNoSites) +
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
            var cnt = rchOfficeWizard.bulkCount || 0;
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
                escapeHtml(rchOfficeWizard.strings.bulkPreview) +
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
            if (!(rchOfficeWizard.bulkCount > 0)) {
                alert(rchOfficeWizard.strings.bulkNoSites);
                return false;
            }
            return true;
        }
        if (!state.agentId) {
            alert(rchOfficeWizard.strings.pickAgent);
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

    function insertAtCursor(el, text) {
        if (!el) {
            return;
        }
        var start = el.selectionStart;
        var end = el.selectionEnd;
        var v = el.value || '';
        if (typeof start !== 'number' || typeof end !== 'number') {
            el.value = v + text;
            return;
        }
        el.value = v.slice(0, start) + text + v.slice(end);
        var pos = start + text.length;
        el.selectionStart = pos;
        el.selectionEnd = pos;
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
                $row.find('.rch-wz-row-value').val(cfg.value).trigger('change');
            }
            syncRowVisibility($row);
            updateMetaPreview($row);
        });
        refreshWizardTagChips();
    }

    function normalizeThemeRowCompareVal(v) {
        return String(v === null || v === undefined ? '' : v).trim();
    }

    function getImportMetaForThemeKey(themeKey) {
        var map = rchOfficeWizard.themeImportMap;
        if (!map || !Object.prototype.hasOwnProperty.call(map, themeKey)) {
            return '';
        }
        return String(map[themeKey] || '');
    }

    function manualThemeValueMatchesAgentMeta(manualVal, metaKey) {
        if (!metaKey || !state.meta || !Object.prototype.hasOwnProperty.call(state.meta, metaKey)) {
            return false;
        }
        return normalizeThemeRowCompareVal(manualVal) === normalizeThemeRowCompareVal(state.meta[metaKey]);
    }

    /**
     * Merge saved draft rows with last-deploy config so profile bindings (meta) are not lost
     * when an autosaved draft flattened them to manual literals.
     *
     * @param {Record<string, {mode?: string, value?: *, meta_key?: string}>|null} draftRows
     * @param {Record<string, {mode?: string, value?: *, meta_key?: string}>|null} deployRows
     * @returns {Record<string, {mode: string, value?: *, meta_key?: string}>}
     */
    function reconcileThemeRowsForHydrate(draftRows, deployRows, draftSavedAt, deployUpdatedAt) {
        var out = {};
        var keys = {};
        var k;
        var deployIsNewer =
            deployUpdatedAt &&
            draftSavedAt &&
            parseInt(deployUpdatedAt, 10) > parseInt(draftSavedAt, 10);

        function collectKeys(obj) {
            if (!obj || typeof obj !== 'object') {
                return;
            }
            for (k in obj) {
                if (Object.prototype.hasOwnProperty.call(obj, k)) {
                    keys[k] = true;
                }
            }
        }

        collectKeys(draftRows);
        collectKeys(deployRows);

        for (k in keys) {
            if (!Object.prototype.hasOwnProperty.call(keys, k)) {
                continue;
            }
            var d = draftRows && draftRows[k] ? draftRows[k] : null;
            var p = deployRows && deployRows[k] ? deployRows[k] : null;
            var chosen = null;

            if (p && p.mode === 'meta') {
                if (d && d.mode === 'manual' && manualThemeValueMatchesAgentMeta(d.value, p.meta_key)) {
                    chosen = p;
                } else if (!d || d.mode === 'skip') {
                    chosen = p;
                } else if (d.mode === 'meta') {
                    chosen = d;
                } else {
                    chosen = d;
                }
            } else if (p && p.mode !== 'skip' && (!d || d.mode === 'skip')) {
                chosen = p;
            } else if (d && d.mode !== 'skip') {
                chosen = d;
            } else if (p && p.mode !== 'skip') {
                chosen = p;
            }

            if (chosen && chosen.mode && chosen.mode !== 'skip') {
                out[k] = chosen;
            }
        }

        return out;
    }

    /**
     * Seed rows still in `skip` from the agent sub-site's saved theme options.
     * Prefer agent profile (meta) when the stored value matches a known import field.
     */
    function applyCurrentThemeOptionsToEmptyRows(opts) {
        if (!opts || typeof opts !== 'object') {
            return;
        }
        $.each(opts, function (key, value) {
            var $row = $rowForKey(key);
            if (!$row.length) {
                return;
            }
            var $mode = $row.find('.rch-wz-row-mode');
            var currentMode = String($mode.val() || 'skip');
            if (currentMode !== 'skip') {
                return;
            }
            var serialized = '';
            if ($.isArray(value)) {
                try {
                    serialized = JSON.stringify(value);
                } catch (e) {
                    serialized = '[]';
                }
                if (serialized === '[]') {
                    return;
                }
            } else if (value === null || value === undefined) {
                return;
            } else {
                serialized = String(value);
                if (serialized === '') {
                    return;
                }
            }

            var importMeta = getImportMetaForThemeKey(key);
            if (importMeta && manualThemeValueMatchesAgentMeta(serialized, importMeta)) {
                $mode.val('meta');
                $row.find('.rch-wz-row-meta').val(importMeta);
            } else {
                $mode.val('manual');
                $row.find('.rch-wz-row-value').val(serialized).trigger('change');
            }
            syncRowVisibility($row);
            updateMetaPreview($row);
        });
        refreshWizardTagChips();
    }

    /**
     * @param {Record<string, {mode?: string, value?: *, meta_key?: string}>|null} draftRows
     * @param {Record<string, {mode?: string, value?: *, meta_key?: string}>|null} deployRows
     * @param {Record<string, *>|null} currentTheme
     */
    function hydrateThemeRowsFromSources(draftRows, deployRows, currentTheme, draftSavedAt, deployUpdatedAt) {
        var merged = reconcileThemeRowsForHydrate(
            draftRows,
            deployRows,
            draftSavedAt || 0,
            deployUpdatedAt || 0
        );
        if (merged && typeof merged === 'object' && Object.keys(merged).length) {
            applyThemeRowsToDom(merged);
        }
        applyCurrentThemeOptionsToEmptyRows(currentTheme);
    }

    function refreshTestimonialsSyncUi() {
        var scope = getScope();
        var $panel = $('#rch-wz-testimonials-sync');
        var $count = $('#rch-wz-testimonials-count');
        if (!$panel.length) {
            return;
        }
        $panel.removeAttr('hidden');
        if (scope === 'all') {
            $count.text(str('testimonialsBulkHint') || '');
            return;
        }
        if (!state.agentId) {
            $count.text(str('testimonialsPickAgent') || '');
            return;
        }
        if (!state.blogId) {
            $count.text(str('testimonialsNoBlog') || '');
            return;
        }
        var n = state.testimonialCount || 0;
        if (n > 0) {
            $count.text((str('testimonialsCountSingle') || '%d').replace('%d', String(n)));
        } else {
            $count.text(str('testimonialsCountNone') || '');
        }
    }

    function applyAgentPayloadTestimonials(data) {
        var t = data && data.testimonials;
        state.testimonialCount = t && typeof t.count === 'number' ? t.count : 0;
        refreshTestimonialsSyncUi();
    }

    function buildDraftPayload(saveSrc) {
        var payload = {
            draftVersion: 4,
            scope: getScope(),
            agentId: state.agentId,
            blogId: state.blogId,
            title: state.title,
            meta: state.meta,
            step: state.step,
            themeRows: collectThemeRows(),
            savedAt: Math.floor(Date.now() / 1000),
            _draftSaveSrc: saveSrc === 'auto' ? 'auto' : 'user',
        };
        if (rchOfficeWizard.broadcastStep) {
            payload.broadcastPostIds = collectBroadcastPostIds();
            payload.broadcastTargetMode = getBcTargetMode();
        }
        payload.mwMenuTermIds = collectMwMenuTermIds();
        payload.mwWidgetMode = getMwWidgetMode();
        payload.mwTargetMode = getMwTargetMode();
        payload.menuBuilderName = String($('#rch-wz-mb-name').val() || '');
        payload.menuBuilderItems = state.menuBuilderItems.map(function (x) {
            var sid = parseInt(x.sourcePostId, 10) || 0;
            return {
                title: x.title || '',
                url: x.url || '',
                source_post_id: sid > 0 ? sid : 0,
            };
        });
        payload.menuBuilderLocSlugs = collectMenuBuilderLocSlugs();
        return payload;
    }

    function persistWizardDraft(silent) {
        if (silent && !draftHydrationDone) {
            return $.Deferred().resolve().promise();
        }
        return $.post(rchOfficeWizard.ajaxurl, {
            action: 'rch_office_wizard_save_draft',
            nonce: rchOfficeWizard.nonce,
            draft: JSON.stringify(buildDraftPayload(silent ? 'auto' : 'user')),
        }).done(function (res) {
            if (silent) {
                return;
            }
            if (res.success) {
                alert(res.data && res.data.message ? res.data.message : 'Saved');
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Error');
            }
        });
    }

    var fieldDraftTimer;
    function scheduleFieldDraftAutosave() {
        if (!draftHydrationDone) {
            return;
        }
        clearTimeout(fieldDraftTimer);
        fieldDraftTimer = setTimeout(function () {
            persistWizardDraft(true);
        }, 1800);
    }

    $(function () {
        bindRowHandlers();

        $(document)
            .off('change.rchwztagadd', '.rch-wz-tags-wrap .rch-wz-tag-add')
            .on('change.rchwztagadd', '.rch-wz-tags-wrap .rch-wz-tag-add', function () {
                var $add = $(this);
                var v = $add.val();
                if (!v) {
                    return;
                }
                var $wrap = $add.closest('.rch-wz-tags-wrap');
                var $hidden = $wrap.find('.rch-wz-row-value');
                var list = parseJsonStringArray($hidden.val() || '[]');
                if (list.indexOf(v) === -1) {
                    list.push(v);
                }
                $hidden.val(JSON.stringify(list)).trigger('change');
                $add.val('');
            });

        $(document)
            .off('change.rchwztaghidden', '.rch-wz-tags-wrap .rch-wz-row-value')
            .on('change.rchwztaghidden', '.rch-wz-tags-wrap .rch-wz-row-value', function () {
                renderTagChips($(this).closest('.rch-wz-tags-wrap'));
            });

        initAllRows();
        showStep(1);
        fillMwTargetSummary();

        // prefer: 'deployed' (default, on form open — global last-deployed config) or
        // 'personal' (explicit Load-draft button — this user's saved draft only).
        function loadDraft(silent, prefer) {
            return $.post(rchOfficeWizard.ajaxurl, {
                action: 'rch_office_wizard_load_draft',
                nonce: rchOfficeWizard.nonce,
                prefer: prefer === 'personal' ? 'personal' : 'deployed',
            })
                .done(function (res) {
                if (!res.success || !res.data || !res.data.draft) {
                    if (!silent) {
                        alert(prefer === 'personal' ? (str('draftNonePersonal') || 'No saved draft for your account.') : 'No deployed data yet.');
                    }
                    draftHydrationDone = true;
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
                    $('#rch-wz-agent-select').val(String(dr.agentId));
                }
                if (dr.blogId !== undefined) {
                    state.blogId = dr.blogId;
                }
                if (dr.title) {
                    state.title = dr.title;
                }
                if (dr.meta && typeof dr.meta === 'object') {
                    state.meta = dr.meta;
                }
                if (dr.agentId) {
                    state.agentId = dr.agentId;
                }

                function applyRestOfDraft(lastDeploymentRows, currentTheme, deployUpdatedAt) {
                    var rows = dr.themeRows || dr.theme_rows;
                    state.draftThemeRows = rows && typeof rows === 'object' ? rows : null;
                    hydrateThemeRowsFromSources(
                        rows,
                        lastDeploymentRows || null,
                        currentTheme || null,
                        dr.savedAt || 0,
                        deployUpdatedAt || 0
                    );
                    var mwm = normalizeWizardTargetMode(dr.mwTargetMode || 'agent_only');
                    if (mwm === 'agent_office') {
                        mwm = 'agent_only';
                    }
                    $('input[name="rch_wz_mw_target"][value="' + mwm + '"]').prop('checked', true);
                    state.mwMenuPick = {};
                    if (dr.mwMenuTermIds && Array.isArray(dr.mwMenuTermIds)) {
                        dr.mwMenuTermIds.forEach(function (id) {
                            var n = parseInt(id, 10);
                            if (n) {
                                state.mwMenuPick[n] = true;
                            }
                        });
                    }
                    if (dr.mwWidgetMode) {
                        setMwWidgetMode(dr.mwWidgetMode);
                    } else if (dr.mwCopyWidgets) {
                        // Legacy draft: old boolean checkbox → copy as-is.
                        setMwWidgetMode('asis');
                    } else {
                        setMwWidgetMode('none');
                    }
                    state.menuBuilderLoc = {};
                    if (dr.menuBuilderLocSlugs && Array.isArray(dr.menuBuilderLocSlugs)) {
                        dr.menuBuilderLocSlugs.forEach(function (s) {
                            if (s) {
                                state.menuBuilderLoc[String(s)] = true;
                            }
                        });
                    }
                    state.menuBuilderItems = [];
                    if (dr.menuBuilderItems && Array.isArray(dr.menuBuilderItems)) {
                        dr.menuBuilderItems.forEach(function (it) {
                            if (it && (it.title || it.url)) {
                                state.menuBuilderItems.push({
                                    id: it.id || mbMakeId(),
                                    title: String(it.title || ''),
                                    url: String(it.url || ''),
                                    sourcePostId: parseInt(it.source_post_id || it.sourcePostId, 10) || 0,
                                });
                            }
                        });
                    }
                    $('#rch-wz-mb-name').val(dr.menuBuilderName ? String(dr.menuBuilderName) : '');
                    renderMenuBuilderTable();
                    if (rchOfficeWizard.broadcastStep) {
                        var bcm = normalizeWizardTargetMode(dr.broadcastTargetMode || 'agent_only');
                        if (bcm === 'agent_office') {
                            bcm = 'agent_only';
                        }
                        $('input[name="rch_wz_bc_target"][value="' + bcm + '"]').prop('checked', true);
                        state.broadcastPick = {};
                        if (dr.broadcastPostIds && Array.isArray(dr.broadcastPostIds)) {
                            dr.broadcastPostIds.forEach(function (id) {
                                var n = parseInt(id, 10);
                                if (n) {
                                    state.broadcastPick[n] = true;
                                }
                            });
                        }
                    }
                    var step = dr.step || 1;
                    var dv = parseInt(dr.draftVersion, 10) || 0;
                    step = migrateWizardDraftStep(step, dv, rchOfficeWizard.broadcastStep);
                    showStep(step);
                    $('.rch-wz-theme-row').each(function () {
                        updateMetaPreview($(this));
                    });
                    if (!silent) {
                        alert('Draft restored');
                    }
                    draftHydrationDone = true;
                }

                if (dr.agentId && getScope() === 'single') {
                    $.post(rchOfficeWizard.ajaxurl, {
                        action: 'rch_office_wizard_load_office',
                        nonce: rchOfficeWizard.nonce,
                        agent_id: dr.agentId,
                    })
                        .done(function (ar) {
                            var current_theme = null;
                            var last_deployment_rows = null;
                            if (ar.success && ar.data) {
                                var d = ar.data;
                                state.agentId = d.agent_id;
                                state.blogId = d.blog_id;
                                state.title = d.title;
                                state.meta = d.meta || {};
                                applyAgentPayloadTestimonials(d);
                                current_theme = d.current_theme || null;
                                last_deployment_rows =
                                    d.last_deployment && d.last_deployment.theme_rows
                                        ? d.last_deployment.theme_rows
                                        : null;
                                $('#rch-wz-agent-summary')
                                    .removeAttr('hidden')
                                    .html(
                                        '<p><strong>' +
                                            $('<div>').text(d.title).html() +
                                            '</strong></p><p>Blog ID: ' +
                                            d.blog_id +
                                            '</p>'
                                    );
                            }
                            applyRestOfDraft(
                                last_deployment_rows,
                                current_theme,
                                d.last_deployment && d.last_deployment.updated_at
                                    ? d.last_deployment.updated_at
                                    : 0
                            );
                        })
                        .fail(function () {
                            applyRestOfDraft(null, null, 0);
                        });
                } else {
                    state.draftThemeRows = dr.themeRows || dr.theme_rows || null;
                    applyRestOfDraft(null, null, 0);
                }
                })
                .fail(function () {
                    draftHydrationDone = true;
                });
        }

        /**
         * Load agent meta + repaint theme rows with last-deployment config (or current values).
         * Used by the explicit Load Agent button, the agent dropdown change, and the auto-load on init.
         */
        function loadAgentAndRepaint(agent_id, opts) {
            opts = opts || {};
            var $spin = $('#rch-wz-agent-spinner');
            spin($spin, true);
            return $.post(rchOfficeWizard.ajaxurl, {
                action: 'rch_office_wizard_load_office',
                nonce: rchOfficeWizard.nonce,
                agent_id: agent_id,
            })
                .done(function (res) {
                    spin($spin, false);
                    if (!res.success) {
                        if (!opts.silent) {
                            alert(res.data && res.data.message ? res.data.message : 'Error');
                        }
                        return;
                    }
                    var d = res.data;
                    state.agentId = d.agent_id;
                    state.blogId = d.blog_id;
                    state.title = d.title;
                    state.meta = d.meta || {};
                    applyAgentPayloadTestimonials(d);
                    $('#rch-wz-agent-summary')
                        .removeAttr('hidden')
                        .html(
                            '<p><strong>' +
                                $('<div>').text(d.title).html() +
                                '</strong></p><p>Blog ID: ' +
                                d.blog_id +
                                '</p>'
                        );
                    hydrateThemeRowsFromSources(
                        collectThemeRows(),
                        d.last_deployment && d.last_deployment.theme_rows
                            ? d.last_deployment.theme_rows
                            : null,
                        d.current_theme || null,
                        0,
                        d.last_deployment && d.last_deployment.updated_at
                            ? d.last_deployment.updated_at
                            : 0
                    );
                    $('.rch-wz-theme-row').each(function () {
                        updateMetaPreview($(this));
                    });
                })
                .fail(function () {
                    spin($spin, false);
                    if (!opts.silent) {
                        alert('Request failed');
                    }
                });
        }

        /**
         * Auto-restore last draft on load.
         * If there's no draft (or draft has no agentId), fall back to the per-user last-loaded agent
         * so opening the wizard in a new tab after a deploy still shows the previously deployed data.
         */
        loadDraft(true, 'deployed');

        (function pollHydrate() {
            if (!draftHydrationDone) {
                setTimeout(pollHydrate, 80);
                return;
            }
            if (state.agentId || getScope() !== 'single') {
                return;
            }
            if (state.draftThemeRows && Object.keys(state.draftThemeRows).length) {
                return;
            }
            var lastId = parseInt(rchOfficeWizard.lastAgentId || 0, 10);
            if (lastId <= 0) {
                return;
            }
            $('#rch-wz-agent-select').val(String(lastId));
            loadAgentAndRepaint(lastId, { silent: true });
        })();

        $('input[name="rch_wz_scope"]').on('change', function () {
            $('#rch-wz-single-agent-wrap').toggle(getScope() === 'single');
            $('.rch-wz-theme-row').each(function () {
                updateMetaPreview($(this));
            });
            refreshTestimonialsSyncUi();
        });

        refreshTestimonialsSyncUi();

        function resolveWizardAgentId() {
            var fromSelect = parseInt($('#rch-wz-agent-select').val(), 10) || 0;
            return fromSelect > 0 ? fromSelect : state.agentId;
        }

        function postTestimonialSync(scope, agentId) {
            spin($('#rch-wz-sync-testimonials-spinner'), true);
            $('#rch-wz-sync-testimonials-result').empty();
            return $.post(rchOfficeWizard.ajaxurl, {
                action: 'rch_agent_wizard_sync_testimonials',
                nonce: rchOfficeWizard.nonce,
                scope: scope,
                agent_id: agentId,
            })
                .done(function (res) {
                    spin($('#rch-wz-sync-testimonials-spinner'), false);
                    if (!res.success) {
                        $('#rch-wz-sync-testimonials-result').html(
                            '<div class="notice notice-error inline"><p>' +
                                escapeHtml(res.data && res.data.message ? res.data.message : 'Error') +
                                '</p></div>'
                        );
                        return;
                    }
                    var html =
                        '<div class="notice notice-success inline"><p>' +
                        escapeHtml(res.data.message) +
                        '</p>';
                    if (res.data.summary && res.data.summary.errors && res.data.summary.errors.length) {
                        html += '<ul>';
                        $.each(res.data.summary.errors, function (i, err) {
                            html += '<li>' + escapeHtml(err) + '</li>';
                        });
                        html += '</ul>';
                    }
                    html += '</div>';
                    $('#rch-wz-sync-testimonials-result').html(html);
                    if (scope === 'single' && res.data.summary && typeof res.data.summary.total === 'number') {
                        state.testimonialCount = res.data.summary.total;
                        refreshTestimonialsSyncUi();
                    }
                })
                .fail(function () {
                    spin($('#rch-wz-sync-testimonials-spinner'), false);
                    $('#rch-wz-sync-testimonials-result').html(
                        '<div class="notice notice-error inline"><p>Request failed</p></div>'
                    );
                });
        }

        $('#rch-wz-sync-testimonials').on('click', function () {
            var scope = getScope();
            var agentId = resolveWizardAgentId();

            if (scope === 'single') {
                if (!agentId) {
                    alert(str('testimonialsPickAgent') || str('pickAgent'));
                    return;
                }
                if (agentId !== state.agentId || !state.blogId) {
                    loadAgentAndRepaint(agentId, { silent: true }).done(function (res) {
                        if (!res || !res.success || !res.data || !res.data.blog_id) {
                            alert(str('testimonialsNoBlog') || str('noBlog'));
                            return;
                        }
                        postTestimonialSync(scope, agentId);
                    });
                    return;
                }
            } else if (!(rchOfficeWizard.bulkCount > 0)) {
                alert(rchOfficeWizard.strings.bulkNoSites);
                return;
            }
            postTestimonialSync(scope, agentId);
        });

        function postTestimonialDelete(scope, agentId) {
            spin($('#rch-wz-delete-testimonials-spinner'), true);
            $('#rch-wz-delete-testimonials-result').empty();
            return $.post(rchOfficeWizard.ajaxurl, {
                action: 'rch_agent_wizard_delete_testimonials',
                nonce: rchOfficeWizard.nonce,
                scope: scope,
                agent_id: agentId,
            })
                .done(function (res) {
                    spin($('#rch-wz-delete-testimonials-spinner'), false);
                    if (!res.success) {
                        $('#rch-wz-delete-testimonials-result').html(
                            '<div class="notice notice-error inline"><p>' +
                                escapeHtml(res.data && res.data.message ? res.data.message : 'Error') +
                                '</p></div>'
                        );
                        return;
                    }
                    var html =
                        '<div class="notice notice-success inline"><p>' +
                        escapeHtml(res.data.message) +
                        '</p>';
                    if (res.data.summary && res.data.summary.errors && res.data.summary.errors.length) {
                        html += '<ul>';
                        $.each(res.data.summary.errors, function (i, err) {
                            html += '<li>' + escapeHtml(err) + '</li>';
                        });
                        html += '</ul>';
                    }
                    html += '</div>';
                    $('#rch-wz-delete-testimonials-result').html(html);
                    if (scope === 'single') {
                        state.testimonialCount = 0;
                        refreshTestimonialsSyncUi();
                    }
                })
                .fail(function () {
                    spin($('#rch-wz-delete-testimonials-spinner'), false);
                    $('#rch-wz-delete-testimonials-result').html(
                        '<div class="notice notice-error inline"><p>Request failed</p></div>'
                    );
                });
        }

        $('#rch-wz-delete-testimonials').on('click', function () {
            var scope = getScope();
            var agentId = resolveWizardAgentId();

            if (scope === 'single' && !agentId) {
                alert(str('testimonialsPickAgent') || str('pickAgent'));
                return;
            }
            if (scope === 'all' && !(rchOfficeWizard.bulkCount > 0)) {
                alert(rchOfficeWizard.strings.bulkNoSites);
                return;
            }

            var confirmMsg = scope === 'all'
                ? (str('testimonialsDeleteConfirmAll') || 'Permanently delete testimonials for EVERY agent sub-site and clear every agent testimonial list on the main site? This cannot be undone.')
                : (str('testimonialsDeleteConfirm') || 'Permanently delete all testimonials on this agent sub-site and clear the agent testimonial list on the main site? This cannot be undone.');
            if (!window.confirm(confirmMsg)) {
                return;
            }

            if (scope === 'single' && (agentId !== state.agentId || !state.blogId)) {
                loadAgentAndRepaint(agentId, { silent: true }).done(function (res) {
                    if (!res || !res.success || !res.data || !res.data.blog_id) {
                        alert(str('testimonialsNoBlog') || str('noBlog'));
                        return;
                    }
                    postTestimonialDelete(scope, agentId);
                });
                return;
            }
            postTestimonialDelete(scope, agentId);
        });

        $('#rch-wz-load-agent').on('click', function () {
            var id = parseInt($('#rch-wz-agent-select').val(), 10);
            if (!id) {
                alert(rchOfficeWizard.strings.pickAgent);
                return;
            }
            loadAgentAndRepaint(id, { silent: false });
        });

        // Auto-load when the user picks an agent from the dropdown (no need to click Load Agent).
        $('#rch-wz-agent-select').on('change', function () {
            if (getScope() !== 'single') {
                return;
            }
            var id = parseInt($(this).val(), 10);
            if (!id || id === state.agentId) {
                return;
            }
            loadAgentAndRepaint(id, { silent: true });
        });

        $(document)
            .off('click.rchwztoken', '.rch-wz-token')
            .on('click.rchwztoken', '.rch-wz-token', function () {
                var token = $(this).attr('data-token') || '';
                if (!token) {
                    return;
                }
                var $row = $(this).closest('.rch-wz-theme-row');
                var $input = $row.find('.rch-wz-row-value').first();
                if (!$input.length) {
                    return;
                }
                var el = $input.get(0);
                el.focus();
                insertAtCursor(el, token);
                $input.trigger('change');
            });

        $('#rch-wz-load-draft').on('click', function () {
            loadDraft(false, 'personal');
        });

        $(document).on('change', '.rch-wz-row-mode, .rch-wz-row-meta', scheduleFieldDraftAutosave);
        $(document).on('change input', '.rch-wz-row-value', scheduleFieldDraftAutosave);
        $(document).on('change', 'input[name="rch_wz_bc_target"]', scheduleFieldDraftAutosave);
        $(document).on('change', 'input[name="rch_wz_mw_target"]', scheduleFieldDraftAutosave);
        $(document).on('change', 'input[name="rch_wz_mw_widget_mode"]', scheduleFieldDraftAutosave);
        $(document).on('change', '.rch-wz-mw-menu-check', function () {
            var tid = parseInt($(this).data('term-id'), 10);
            if (tid) {
                state.mwMenuPick[tid] = $(this).prop('checked');
            }
            scheduleFieldDraftAutosave();
        });

        $('.rch-wz-next').on('click', function () {
            var next = parseInt($(this).data('next'), 10);
            if (next === 2 && !canLeaveStep1()) {
                return;
            }
            showStep(next);
            persistWizardDraft(true);
        });

        $('.rch-wz-prev').on('click', function () {
            showStep(parseInt($(this).data('prev'), 10));
            persistWizardDraft(true);
        });

        $('.rch-wz-goto').on('click', function () {
            var s = parseInt($(this).data('step'), 10);
            var skipStep1Check = false;
            if (rchOfficeWizard.broadcastStep) {
                skipStep1Check = s === 3 || s === 4;
            } else {
                skipStep1Check = s === 3;
            }
            if (s >= 2 && !skipStep1Check && !canLeaveStep1()) {
                return;
            }
            showStep(s);
            persistWizardDraft(true);
        });

        $(document).on('click', '.rch-wz-media', function (e) {
            e.preventDefault();
            var id = $(this).data('target');
            var isVideo = $(this).hasClass('rch-wz-media-video');
            openMedia(id, isVideo);
        });

        $('#rch-wz-refresh-preview').on('click', refreshPreview);

        $('#rch-wz-save-draft').on('click', function () {
            persistWizardDraft(false);
        });

        $('#rch-wz-deploy').on('click', function () {
            var scope = getScope();
            if (scope === 'single' && !state.blogId) {
                alert(rchOfficeWizard.strings.noBlog);
                return;
            }
            if (scope === 'all' && !(rchOfficeWizard.bulkCount > 0)) {
                alert(rchOfficeWizard.strings.bulkNoSites);
                return;
            }
            $('.rch-wz-row-value').each(function () {
                $(this).trigger('change');
            });
            var rowsToDeploy = collectThemeRows();
            if (scope === 'single' && (!rowsToDeploy || !Object.keys(rowsToDeploy).length)) {
                alert(
                    'No theme fields set to deploy. Set each field to “Set manually” or “From agent profile”, not “Do not change”.'
                );
                return;
            }
            spin($('#rch-wz-deploy-spinner'), true);
            $('#rch-wz-deploy-result').empty();
            $.post(rchOfficeWizard.ajaxurl, {
                action: 'rch_office_wizard_deploy',
                nonce: rchOfficeWizard.nonce,
                scope: scope,
                agent_id: state.agentId,
                theme_rows_json: JSON.stringify(rowsToDeploy),
            })
                .done(function (res) {
                    spin($('#rch-wz-deploy-spinner'), false);
                    if (!res.success) {
                        $('#rch-wz-deploy-result').html(
                            '<div class="notice notice-error inline"><p>' +
                                escapeHtml(res.data && res.data.message ? res.data.message : rchOfficeWizard.strings.deployFail) +
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
                    if (res.data && res.data.deployed_preview && res.data.deployed_preview.hero_shortcode) {
                        html +=
                            '<p class="description"><strong>Hero shortcode on sub-site:</strong> ' +
                            escapeHtml(res.data.deployed_preview.hero_shortcode.substring(0, 200)) +
                            (res.data.deployed_preview.hero_shortcode.length > 200 ? '…' : '') +
                            '</p>';
                    }
                    if (res.data && res.data.deployed_keys && res.data.deployed_keys.length) {
                        html +=
                            '<p class="description">Updated ' +
                            escapeHtml(String(res.data.deployed_keys.length)) +
                            ' option key(s). Purge Kinsta/cache on the agent site if the front page still looks old.</p>';
                    }
                    html += '</div>';
                    $('#rch-wz-deploy-result').html(html);
                    if (res.data && res.data.last_deployment && res.data.last_deployment.theme_rows) {
                        hydrateThemeRowsFromSources(
                            collectThemeRows(),
                            res.data.last_deployment.theme_rows,
                            null,
                            Math.floor(Date.now() / 1000),
                            res.data.last_deployment.updated_at || 0
                        );
                    }
                    persistWizardDraft(false);
                })
                .fail(function () {
                    spin($('#rch-wz-deploy-spinner'), false);
                    $('#rch-wz-deploy-result').html(
                        '<div class="notice notice-error inline"><p>Request failed</p></div>'
                    );
                });
        });

        $('#rch-wz-mw-load').on('click', function () {
            loadMwList();
        });
        $('#rch-wz-mw-apply').on('click', function () {
            var ids = collectMwMenuTermIds();
            var mode = getMwWidgetMode();
            var copyW = mode === 'asis' || mode === 'broadcast';
            var relink = mode === 'broadcast';
            if (!ids.length && !copyW) {
                alert(str('mwApplyNone'));
                return;
            }
            spin($('#rch-wz-mw-apply-spinner'), true);
            $('#rch-wz-mw-result').empty();
            $.post(rchOfficeWizard.ajaxurl, {
                action: 'rch_agent_wizard_apply_menus_widgets',
                nonce: rchOfficeWizard.nonce,
                menu_term_ids: JSON.stringify(ids),
                copy_widgets: copyW ? 1 : 0,
                relink_broadcast: relink ? 1 : 0,
                target_mode: getMwTargetMode(),
            })
                .done(function (res) {
                    spin($('#rch-wz-mw-apply-spinner'), false);
                    if (!res.success) {
                        $('#rch-wz-mw-result').html(
                            '<div class="notice notice-error inline"><p>' +
                                escapeHtml(res.data && res.data.message ? res.data.message : 'Error') +
                                '</p></div>'
                        );
                        return;
                    }
                    var html =
                        '<div class="notice notice-success inline"><p>' + escapeHtml(res.data.message) + '</p>';
                    if (res.data.errors && res.data.errors.length) {
                        html += '<ul>';
                        $.each(res.data.errors, function (i, err) {
                            html += '<li>' + escapeHtml(err) + '</li>';
                        });
                        html += '</ul>';
                    }
                    html += '</div>';
                    $('#rch-wz-mw-result').html(html);
                    persistWizardDraft(true);
                })
                .fail(function () {
                    spin($('#rch-wz-mw-apply-spinner'), false);
                    $('#rch-wz-mw-result').html(
                        '<div class="notice notice-error inline"><p>Request failed</p></div>'
                    );
                });
        });

        function runMbSearch(goPage) {
            var p = goPage != null ? goPage : 1;
            state.mbSearchPaged = p;
            var q = $('#rch-wz-mb-search').val() || '';
            var $out = $('#rch-wz-mb-search-results');
            $out.empty().text(str('bcLoading') || '…');
            $.post(rchOfficeWizard.ajaxurl, {
                action: 'rch_agent_wizard_menu_builder_search',
                nonce: rchOfficeWizard.nonce,
                search: q,
                paged: p,
                per_page: 15,
            })
                .done(function (res) {
                    $out.empty();
                    if (!res.success || !res.data) {
                        $out.append('<p class="rch-wz-hint">Error</p>');
                        return;
                    }
                    state.mbSearchMaxPages = res.data.max_pages || 1;
                    $('#rch-wz-mb-search-prev').prop('disabled', p <= 1);
                    $('#rch-wz-mb-search-next').prop('disabled', p >= state.mbSearchMaxPages);
                    var items = res.data.items || [];
                    var j;
                    if (!items.length) {
                        $out.append('<p class="rch-wz-hint">' + escapeHtml(str('bcEmpty')) + '</p>');
                        return;
                    }
                    for (j = 0; j < items.length; j++) {
                        var it = items[j];
                        var title = it.title || '#' + it.id;
                        $out.append(
                            '<div class="rch-wz-mb-hit">' +
                                '<span class="rch-wz-mb-hit__title">' +
                                escapeHtml(title) +
                                '</span> <span class="rch-wz-hint">' +
                                escapeHtml(it.type || '') +
                                '</span> ' +
                                '<button type="button" class="button button-small rch-wz-mb-add-hit" data-title="' +
                                escapeAttr(title) +
                                '" data-url="' +
                                escapeAttr(it.url || '') +
                                '" data-source-post-id="' +
                                escapeAttr(it.id ? String(it.id) : '') +
                                '">' +
                                escapeHtml(str('mbAdd')) +
                                '</button></div>'
                        );
                    }
                })
                .fail(function () {
                    $out.empty().append('<p class="rch-wz-hint">Request failed</p>');
                });
        }

        $(document).on('click', '.rch-wz-mb-add-hit', function () {
            var title = $(this).attr('data-title') || '';
            var url = $(this).attr('data-url') || '';
            var sid = parseInt($(this).attr('data-source-post-id'), 10) || 0;
            if (!title || (!url && !sid)) {
                return;
            }
            state.menuBuilderItems.push({ id: mbMakeId(), title: title, url: url, sourcePostId: sid });
            renderMenuBuilderTable();
            scheduleFieldDraftAutosave();
        });

        if (rchOfficeWizard.broadcastStep) {
            $('#rch-wz-mb-bc-load').on('click', function () {
                loadMbBroadcastedPosts(1);
            });
            $('#rch-wz-mb-bc-prev').on('click', function () {
                if (state.mbBcPaged > 1) {
                    loadMbBroadcastedPosts(state.mbBcPaged - 1);
                }
            });
            $('#rch-wz-mb-bc-next').on('click', function () {
                if (state.mbBcPaged < state.mbBcMaxPages) {
                    loadMbBroadcastedPosts(state.mbBcPaged + 1);
                }
            });
            $('#rch-wz-mb-bc-selall').on('click', function () {
                $('.rch-wz-mb-bc-check').prop('checked', true);
            });
            $('#rch-wz-mb-bc-selnone').on('click', function () {
                $('.rch-wz-mb-bc-check').prop('checked', false);
            });
            $('#rch-wz-mb-bc-add-selected').on('click', function () {
                var ids = [];
                $('.rch-wz-mb-bc-check:checked').each(function () {
                    var id = parseInt($(this).data('id'), 10);
                    if (id) {
                        ids.push(id);
                    }
                });
                mbAddBroadcastedByIds(ids, $('#rch-wz-mb-bc-pageinfo'));
            });
            $(document).on('click', '.rch-wz-mb-bc-add-hit', function () {
                var id = parseInt($(this).data('id'), 10);
                if (id) {
                    mbAddBroadcastedByIds([id], null);
                }
            });
        }

        $('#rch-wz-mb-search-btn').on('click', function () {
            runMbSearch(1);
        });
        $('#rch-wz-mb-search-prev').on('click', function () {
            if (state.mbSearchPaged > 1) {
                runMbSearch(state.mbSearchPaged - 1);
            }
        });
        $('#rch-wz-mb-search-next').on('click', function () {
            if (state.mbSearchPaged < state.mbSearchMaxPages) {
                runMbSearch(state.mbSearchPaged + 1);
            }
        });

        $('#rch-wz-mb-custom-add').on('click', function () {
            var url = String($('#rch-wz-mb-custom-url').val() || '').trim();
            var title = String($('#rch-wz-mb-custom-title').val() || '').trim();
            if (!title || !url) {
                return;
            }
            state.menuBuilderItems.push({ id: mbMakeId(), title: title, url: url });
            $('#rch-wz-mb-custom-url').val('');
            $('#rch-wz-mb-custom-title').val('');
            renderMenuBuilderTable();
            scheduleFieldDraftAutosave();
        });

        $(document).on('click', '.rch-wz-mb-remove', function () {
            var idx = parseInt($(this).attr('data-mb-idx'), 10);
            if (isNaN(idx)) {
                return;
            }
            state.menuBuilderItems.splice(idx, 1);
            renderMenuBuilderTable();
            scheduleFieldDraftAutosave();
        });

        $(document).on('click', '.rch-wz-mb-move-up', function () {
            var idx = parseInt($(this).attr('data-mb-idx'), 10);
            if (isNaN(idx) || idx < 1) {
                return;
            }
            var t = state.menuBuilderItems[idx - 1];
            state.menuBuilderItems[idx - 1] = state.menuBuilderItems[idx];
            state.menuBuilderItems[idx] = t;
            renderMenuBuilderTable();
            scheduleFieldDraftAutosave();
        });

        $(document).on('click', '.rch-wz-mb-move-down', function () {
            var idx = parseInt($(this).attr('data-mb-idx'), 10);
            if (isNaN(idx) || idx >= state.menuBuilderItems.length - 1) {
                return;
            }
            var t = state.menuBuilderItems[idx + 1];
            state.menuBuilderItems[idx + 1] = state.menuBuilderItems[idx];
            state.menuBuilderItems[idx] = t;
            renderMenuBuilderTable();
            scheduleFieldDraftAutosave();
        });

        $(document).on('input', '#rch-wz-mb-items .rch-wz-mb-item-title', function () {
            var $tr = $(this).closest('tr');
            var idx = parseInt($tr.attr('data-mb-idx'), 10);
            if (!isNaN(idx) && state.menuBuilderItems[idx]) {
                state.menuBuilderItems[idx].title = $(this).val();
                scheduleFieldDraftAutosave();
            }
        });
        $(document).on('input', '#rch-wz-mb-items .rch-wz-mb-item-url', function () {
            var $tr = $(this).closest('tr');
            var idx = parseInt($tr.attr('data-mb-idx'), 10);
            if (!isNaN(idx) && state.menuBuilderItems[idx]) {
                state.menuBuilderItems[idx].url = $(this).val();
                scheduleFieldDraftAutosave();
            }
        });

        $(document).on('change', '.rch-wz-mb-loc', function () {
            var s = $(this).data('slug');
            if (s) {
                state.menuBuilderLoc[String(s)] = $(this).prop('checked');
            }
            scheduleFieldDraftAutosave();
        });

        $('#rch-wz-mb-name').on('input', scheduleFieldDraftAutosave);

        $('#rch-wz-mb-create').on('click', function () {
            var name = String($('#rch-wz-mb-name').val() || '').trim();
            if (!name) {
                alert(str('mbNeedName'));
                return;
            }
            var items = state.menuBuilderItems
                .map(function (x) {
                    var sid = parseInt(x.sourcePostId, 10) || 0;
                    return {
                        title: String(x.title || '').trim(),
                        url: String(x.url || '').trim(),
                        source_post_id: sid > 0 ? sid : 0,
                    };
                })
                .filter(function (x) {
                    return x.title && (x.url || x.source_post_id > 0);
                });
            if (!items.length) {
                alert(str('mbNeedItem'));
                return;
            }
            spin($('#rch-wz-mb-spinner'), true);
            $('#rch-wz-mb-result').empty();
            $.post(rchOfficeWizard.ajaxurl, {
                action: 'rch_agent_wizard_create_builder_menu',
                nonce: rchOfficeWizard.nonce,
                menu_name: name,
                items: JSON.stringify(items),
                location_slugs: JSON.stringify(collectMenuBuilderLocSlugs()),
                target_mode: getMwTargetMode(),
            })
                .done(function (res) {
                    spin($('#rch-wz-mb-spinner'), false);
                    if (!res.success) {
                        $('#rch-wz-mb-result').html(
                            '<div class="notice notice-error inline"><p>' +
                                escapeHtml(res.data && res.data.message ? res.data.message : 'Error') +
                                '</p></div>'
                        );
                        return;
                    }
                    persistWizardDraft(true);
                    // Large networks are queued server-side; drive the batches from the browser so it
                    // finishes even when WP-cron is disabled. Cron remains a fallback if the tab closes.
                    if (res.data && res.data.queued) {
                        driveMenuBuildJob(res.data.target_count || 0);
                        return;
                    }
                    var html =
                        '<div class="notice notice-success inline"><p>' + escapeHtml(res.data.message) + '</p>';
                    if (res.data.errors && res.data.errors.length) {
                        html += '<ul>';
                        $.each(res.data.errors, function (i, err) {
                            html += '<li>' + escapeHtml(err) + '</li>';
                        });
                        html += '</ul>';
                    }
                    html += '</div>';
                    $('#rch-wz-mb-result').html(html);
                })
                .fail(function () {
                    spin($('#rch-wz-mb-spinner'), false);
                    $('#rch-wz-mb-result').html(
                        '<div class="notice notice-error inline"><p>Request failed</p></div>'
                    );
                });
        });

        // Repeatedly process one background menu-build chunk until done, showing live progress.
        function driveMenuBuildJob(total) {
            var $res = $('#rch-wz-mb-result');

            function renderProgress(processed, totalCount, ok, fail) {
                $res.html(
                    '<div class="notice notice-info inline"><p>' +
                        escapeHtml(
                            'Building menu on sub-sites: ' +
                                processed +
                                ' / ' +
                                totalCount +
                                ' processed (' +
                                ok +
                                ' created, ' +
                                fail +
                                ' failed). Keep this tab open…'
                        ) +
                        '</p></div>'
                );
            }

            renderProgress(0, total, 0, 0);
            spin($('#rch-wz-mb-spinner'), true);

            function step() {
                $.post(rchOfficeWizard.ajaxurl, {
                    action: 'rch_agent_wizard_run_menu_build_chunk',
                    nonce: rchOfficeWizard.nonce,
                })
                    .done(function (r) {
                        if (!r || !r.success || !r.data) {
                            spin($('#rch-wz-mb-spinner'), false);
                            $res.html(
                                '<div class="notice notice-error inline"><p>Background run failed. WP-cron will retry, or reload and try again.</p></div>'
                            );
                            return;
                        }
                        var d = r.data;
                        var tot = d.total || total || 0;
                        var processed = tot - (d.remaining || 0);

                        if (d.done) {
                            spin($('#rch-wz-mb-spinner'), false);
                            var html =
                                '<div class="notice notice-success inline"><p>' +
                                escapeHtml('Menu created on ' + (d.ok || 0) + ' site(s). Failed: ' + (d.fail || 0) + '.') +
                                '</p>';
                            if (d.errors && d.errors.length) {
                                html += '<ul>';
                                $.each(d.errors, function (i, err) {
                                    html += '<li>' + escapeHtml(err) + '</li>';
                                });
                                html += '</ul>';
                            }
                            html += '</div>';
                            $res.html(html);
                            return;
                        }

                        renderProgress(processed, tot, d.ok || 0, d.fail || 0);
                        setTimeout(step, 400);
                    })
                    .fail(function () {
                        spin($('#rch-wz-mb-spinner'), false);
                        $res.html(
                            '<div class="notice notice-error inline"><p>Background run request failed. WP-cron will continue, or reload to resume.</p></div>'
                        );
                    });
            }

            step();
        }

        // Repeatedly process one background broadcast chunk until done, showing live progress.
        function driveBroadcastJob(total) {
            var $res = $('#rch-wz-bc-result');

            function renderProgress(processed, totalCount, ok, skipped, fail) {
                $res.html(
                    '<div class="notice notice-info inline"><p>' +
                        escapeHtml(
                            'Broadcasting to sub-sites: ' +
                                processed +
                                ' / ' +
                                totalCount +
                                ' processed (' +
                                ok +
                                ' sent, ' +
                                skipped +
                                ' already there, ' +
                                fail +
                                ' failed). Keep this tab open…'
                        ) +
                        '</p></div>'
                );
            }

            renderProgress(0, total, 0, 0, 0);
            spin($('#rch-wz-bc-run-spinner'), true);

            function step() {
                $.post(rchOfficeWizard.ajaxurl, {
                    action: 'rch_agent_wizard_run_broadcast_chunk',
                    nonce: rchOfficeWizard.nonce,
                })
                    .done(function (r) {
                        if (!r || !r.success || !r.data) {
                            spin($('#rch-wz-bc-run-spinner'), false);
                            $res.html(
                                '<div class="notice notice-error inline"><p>Background broadcast failed. Reload and run again to resume the rest.</p></div>'
                            );
                            return;
                        }
                        var d = r.data;
                        var tot = d.total || total || 0;
                        var processed = tot - (d.remaining || 0);

                        if (d.done) {
                            spin($('#rch-wz-bc-run-spinner'), false);
                            var html =
                                '<div class="notice notice-success inline"><p>' +
                                escapeHtml(
                                    'Broadcast finished: ' +
                                        (d.ok || 0) +
                                        ' sent, ' +
                                        (d.skipped || 0) +
                                        ' already there, ' +
                                        (d.fail || 0) +
                                        ' failed, across ' +
                                        tot +
                                        ' sub-site(s).'
                                ) +
                                '</p>';
                            if (d.errors && d.errors.length) {
                                html += '<ul>';
                                $.each(d.errors, function (i, err) {
                                    html += '<li>' + escapeHtml(err) + '</li>';
                                });
                                html += '</ul>';
                            }
                            html += '</div>';
                            $res.html(html);
                            return;
                        }

                        renderProgress(processed, tot, d.ok || 0, d.skipped || 0, d.fail || 0);
                        setTimeout(step, 400);
                    })
                    .fail(function () {
                        spin($('#rch-wz-bc-run-spinner'), false);
                        $res.html(
                            '<div class="notice notice-error inline"><p>Background broadcast request failed. Reload to resume the rest.</p></div>'
                        );
                    });
            }

            step();
        }

        if (rchOfficeWizard.broadcastStep) {
            $(document).on('change', '.rch-wz-bc-check', function () {
                var id = parseInt($(this).data('id'), 10);
                if (id) {
                    state.broadcastPick[id] = $(this).prop('checked');
                }
                scheduleFieldDraftAutosave();
            });
            $('#rch-wz-bc-load').on('click', function () {
                loadBroadcastPosts(1);
            });
            $('#rch-wz-bc-prev').on('click', function () {
                if (state.bcPaged > 1) {
                    loadBroadcastPosts(state.bcPaged - 1);
                }
            });
            $('#rch-wz-bc-next').on('click', function () {
                if (state.bcPaged < state.bcMaxPages) {
                    loadBroadcastPosts(state.bcPaged + 1);
                }
            });
            $('#rch-wz-bc-selall').on('click', function () {
                $('.rch-wz-bc-check').each(function () {
                    $(this).prop('checked', true);
                    var id = parseInt($(this).data('id'), 10);
                    if (id) {
                        state.broadcastPick[id] = true;
                    }
                });
                scheduleFieldDraftAutosave();
            });
            $('#rch-wz-bc-selnone').on('click', function () {
                $('.rch-wz-bc-check').each(function () {
                    $(this).prop('checked', false);
                    var id = parseInt($(this).data('id'), 10);
                    if (id) {
                        delete state.broadcastPick[id];
                    }
                });
                scheduleFieldDraftAutosave();
            });
            $('#rch-wz-bc-run').on('click', function () {
                var ids = collectBroadcastPostIds();
                if (!ids.length) {
                    alert(str('bcNoneSelected'));
                    return;
                }
                spin($('#rch-wz-bc-run-spinner'), true);
                $('#rch-wz-bc-result').empty();
                $.post(rchOfficeWizard.ajaxurl, {
                    action: 'rch_agent_wizard_broadcast_posts',
                    nonce: rchOfficeWizard.nonce,
                    post_ids: JSON.stringify(ids),
                    target_mode: getBcTargetMode(),
                })
                    .done(function (res) {
                        spin($('#rch-wz-bc-run-spinner'), false);
                        if (!res.success) {
                            $('#rch-wz-bc-result').html(
                                '<div class="notice notice-error inline"><p>' +
                                    escapeHtml(res.data && res.data.message ? res.data.message : 'Error') +
                                    '</p></div>'
                            );
                            return;
                        }
                        // Large networks are chunked server-side; drive the batches from the browser so
                        // each request stays short and it finishes even when WP-cron is disabled.
                        if (res.data && res.data.queued) {
                            persistWizardDraft(true);
                            driveBroadcastJob(res.data.target_count || 0);
                            return;
                        }
                        var html =
                            '<div class="notice notice-success inline"><p>' +
                            escapeHtml(res.data.message) +
                            '</p>';
                        if (res.data.errors && res.data.errors.length) {
                            html += '<ul>';
                            $.each(res.data.errors, function (i, err) {
                                html += '<li>' + escapeHtml(err) + '</li>';
                            });
                            html += '</ul>';
                        }
                        html += '</div>';
                        $('#rch-wz-bc-result').html(html);
                        persistWizardDraft(true);
                    })
                    .fail(function () {
                        spin($('#rch-wz-bc-run-spinner'), false);
                        $('#rch-wz-bc-result').html(
                            '<div class="notice notice-error inline"><p>Request failed</p></div>'
                        );
                    });
            });
        }
    });
})(jQuery);
