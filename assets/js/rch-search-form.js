/**
 * JavaScript for the Rechat Search Listing Form
 * Simplified version for direct dropdown form (no toggle needed)
 */
(function ($) {
    'use strict';

    // Initialize on document ready
    $(document).ready(function () {
        initUrlParameters();
        initMinMaxFilters();
    });

    /**
     * Initialize Min/Max filters to ensure Max is always >= Min
     */
    function initMinMaxFilters() {
        // Handle Bedrooms
        $('#search-minimum_bedrooms').on('change', function () {
            updateMaxOptions('bedrooms', $(this).val());
        });

        $('#search-maximum_bedrooms').on('change', function () {
            updateMinOptions('bedrooms', $(this).val());
        });

        // Handle Price
        $('#search-minimum_price').on('change', function () {
            updateMaxOptions('price', $(this).val());
        });

        $('#search-maximum_price').on('change', function () {
            updateMinOptions('price', $(this).val());
        });

        // Initialize on page load if values are already set
        const minBeds = $('#search-minimum_bedrooms').val();
        const maxBeds = $('#search-maximum_bedrooms').val();
        const minPrice = $('#search-minimum_price').val();
        const maxPrice = $('#search-maximum_price').val();

        if (minBeds) updateMaxOptions('bedrooms', minBeds);
        if (maxBeds) updateMinOptions('bedrooms', maxBeds);
        if (minPrice) updateMaxOptions('price', minPrice);
        if (maxPrice) updateMinOptions('price', maxPrice);
    }

    /**
     * Update max dropdown options based on min selection
     */
    function updateMaxOptions(type, minValue) {
        if (!minValue) {
            // If min is cleared, enable all max options
            $(`#search-maximum_${type}`).find('option').prop('disabled', false);
            return;
        }

        const minVal = parseFloat(minValue);
        const maxSelect = $(`#search-maximum_${type}`);
        const currentMaxVal = parseFloat(maxSelect.val());

        // Enable/disable options based on min value
        maxSelect.find('option').each(function () {
            const optionVal = $(this).val();
            if (optionVal === '') {
                // Keep the placeholder enabled
                $(this).prop('disabled', false);
            } else {
                const optVal = parseFloat(optionVal);
                // Disable if option value is less than min value
                if (optVal < minVal) {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            }
        });

        // If current max value is less than min, reset max
        if (currentMaxVal && currentMaxVal < minVal) {
            maxSelect.val('');
        }
    }

    /**
     * Update min dropdown options based on max selection
     */
    function updateMinOptions(type, maxValue) {
        if (!maxValue) {
            // If max is cleared, enable all min options
            $(`#search-minimum_${type}`).find('option').prop('disabled', false);
            return;
        }

        const maxVal = parseFloat(maxValue);
        const minSelect = $(`#search-minimum_${type}`);
        const currentMinVal = parseFloat(minSelect.val());

        // Enable/disable options based on max value
        minSelect.find('option').each(function () {
            const optionVal = $(this).val();
            if (optionVal === '') {
                // Keep the placeholder enabled
                $(this).prop('disabled', false);
            } else {
                const optVal = parseFloat(optionVal);
                // Disable if option value is greater than max value
                if (optVal > maxVal) {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            }
        });

        // If current min value is greater than max, reset min
        if (currentMinVal && currentMinVal > maxVal) {
            minSelect.val('');
        }
    }

    /**
     * Initialize form values from URL parameters if they exist
     */
    function initUrlParameters() {
        const urlParams = new URLSearchParams(window.location.search);

        // Set values for all form fields if they exist in URL
        setSelectValueFromUrl('property_types', urlParams);
        setSelectValueFromUrl('minimum_price', urlParams);
        setSelectValueFromUrl('maximum_price', urlParams);
        setSelectValueFromUrl('minimum_bedrooms', urlParams);
        setSelectValueFromUrl('maximum_bedrooms', urlParams);
        setSelectValueFromUrl('minimum_bathrooms', urlParams);
        setSelectValueFromUrl('maximum_bathrooms', urlParams);
        setSelectValueFromUrl('minimum_square_meters', urlParams);
        setSelectValueFromUrl('maximum_square_meters', urlParams);

        // Set text search value
        if (urlParams.has('content')) {
            $('#content').val(urlParams.get('content'));
        }
    }

    /**
     * Helper function to set select values from URL parameters
     */
    function setSelectValueFromUrl(paramName, urlParams) {
        if (urlParams.has(paramName)) {
            const value = urlParams.get(paramName);
            $(`#search-${paramName}`).val(value);
        }
    }

    // Form submission validation to remove empty fields
    $('#rch-search-form').on('submit', function (e) {
        // Remove empty fields to keep the URL clean
        $(this).find('input[value=""], select[value=""]').attr('disabled', true);
        return true;
    });

})(jQuery);
