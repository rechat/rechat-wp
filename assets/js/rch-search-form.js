/**
 * JavaScript for the Rechat Search Listing Form
 * Simplified version for direct dropdown form (no toggle needed)
 */
(function ($) {
    'use strict';

    // Initialize on document ready
    $(document).ready(function () {
        initUrlParameters();
    });

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
