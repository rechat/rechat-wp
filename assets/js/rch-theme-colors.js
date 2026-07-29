/**
 * Rechat theme color controls (General Settings tab).
 *
 * Each row = one CSS-variable override. The submitted value is the visible hex field
 * (#rrggbbaa). A native <input type="color"> drives the RGB part and a range drives the
 * alpha byte; both stay in sync with the hex field. "Reset" restores the row default,
 * "Reset all" restores every row. Saving a row at its default clears the override server-side.
 */
(function () {
    'use strict';

    function clampByte(n) {
        n = parseInt(n, 10);
        if (isNaN(n)) { return 255; }
        return Math.max(0, Math.min(255, n));
    }

    function byteToHex(n) {
        var h = clampByte(n).toString(16);
        return h.length === 1 ? '0' + h : h;
    }

    /** Normalize any input to a 6-digit RGB hex #rrggbb (alpha dropped), or '' when unparseable. */
    function normalize(value) {
        if (typeof value !== 'string') { return ''; }
        value = value.trim().toLowerCase();
        if (value.charAt(0) !== '#') { value = '#' + value; }

        if (/^#[0-9a-f]{3}$/.test(value)) {
            value = '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
        }
        if (/^#[0-9a-f]{8}$/.test(value)) { value = value.substring(0, 7); }
        if (/^#[0-9a-f]{6}$/.test(value)) { return value; }
        return '';
    }

    function paint(row, hex) {
        var fill = row.querySelector('.rch-tc-swatch__fill');
        if (fill) { fill.style.background = hex || 'transparent'; }
    }

    /** Empty a row (no override): clears the hex field and resets helper controls. */
    function clearRow(row) {
        var hexField = row.querySelector('.rch-tc-hex');
        var color    = row.querySelector('.rch-tc-color');
        var alpha    = row.querySelector('.rch-tc-alpha');
        if (hexField) { hexField.value = ''; }
        if (color) { color.value = '#000000'; }
        if (alpha) { alpha.value = '255'; }
        paint(row, '');
    }

    /** Reset a row to its default (empty default → cleared / no override). */
    function resetRow(row) {
        var def = normalize(row.getAttribute('data-default') || '');
        if (def === '') { clearRow(row); } else { applyHex(row, def); }
    }

    /** Push a #rrggbb into all controls of a row. */
    function applyHex(row, hex) {
        var norm = normalize(hex);
        if (norm === '') { return; }

        var hexField = row.querySelector('.rch-tc-hex');
        var color    = row.querySelector('.rch-tc-color');

        if (hexField) { hexField.value = norm; }
        if (color) { color.value = norm; }
        paint(row, norm);
    }

    /** Rebuild the hex from the color control (RGB only). */
    function syncFromControls(row) {
        var color = row.querySelector('.rch-tc-color');
        if (!color) { return; }
        var rgb = (color.value || '#000000').toLowerCase();
        var hexField = row.querySelector('.rch-tc-hex');
        if (hexField) { hexField.value = rgb; }
        paint(row, rgb);
    }

    function initRow(row) {
        var color    = row.querySelector('.rch-tc-color');
        var alpha    = row.querySelector('.rch-tc-alpha');
        var hexField = row.querySelector('.rch-tc-hex');
        var reset    = row.querySelector('.rch-tc-reset');

        if (color) {
            color.addEventListener('input', function () { syncFromControls(row); });
        }
        if (alpha) {
            alpha.addEventListener('input', function () { syncFromControls(row); });
        }
        if (hexField) {
            hexField.addEventListener('change', function () {
                var norm = normalize(hexField.value);
                if (norm === '') {
                    // Revert to whatever the controls currently represent.
                    syncFromControls(row);
                } else {
                    applyHex(row, norm);
                }
            });
        }
        if (reset) {
            reset.addEventListener('click', function () {
                resetRow(row);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var rows = document.querySelectorAll('.rch-tc-row');
        for (var i = 0; i < rows.length; i++) {
            initRow(rows[i]);
        }

        var resetAll = document.querySelector('.rch-tc-reset-all');
        if (resetAll) {
            resetAll.addEventListener('click', function () {
                for (var j = 0; j < rows.length; j++) {
                    resetRow(rows[j]);
                }
            });
        }
    });
}());
