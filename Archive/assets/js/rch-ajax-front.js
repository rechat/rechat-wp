jQuery(document).ready(function ($) {
    // Select all images within .rch-image-container
    /*******************************
     * show loading to images when they are not loaded
     ******************************/
    $('.rch-archive-agents .rch-image-container img').each(function () {
        var img = $(this);
        var loader = img.siblings('.rch-loader');

        img.on('load', function () {
            img.addClass('loaded');
            loader.hide(); // Hide loader once image is loaded
        });

        // In case the image is cached and already loaded
        if (img[0].complete) {
            img.trigger('load');
        }
    });
    /*******************************
     * ajax search in agents archive
     ******************************/
    $('#rch-agent-search').on('input', function () {
        var searchQuery = $(this).val();
        if (searchQuery.length > 0) {
            $.ajax({
                url: rch_ajax_front_params.ajax_url, // Use the localized AJAX URL
                type: 'GET',
                data: {
                    action: 'rch_agent_search', // Corresponds to your PHP function
                    query: searchQuery,
                    nonce: rch_ajax_front_params.nonce // Include the nonce for security
                },
                success: function (response) {
                    $('#rch-agent-search-results').html(response).slideDown();
                }

            });
        } else {
            $('#rch-agent-search-results').slideUp();
        }
    });

    // Hide the dropdown when clicking outside
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#rch-agent-search-form').length) {
            $('#rch-agent-search-results').slideUp();
        }
    });

});
