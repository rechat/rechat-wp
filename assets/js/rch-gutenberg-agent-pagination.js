jQuery(document).ready(function($) {
    /*******************************
     * this script for agent gutenberg ajax pagination
     ******************************/
    var totalPages = $('#rch-agents-block').data('total-pages');
    var postsPerPage = $('#rch-agents-block').data('posts-per-page');
    var regionBgColor = $('#rch-agents-block').data('region-bg-color');
    var textColor = $('#rch-agents-block').data('text-color');
    var filterRegion = $('#rch-agents-block').data('filter-region');
    var filterOffice = $('#rch-agents-block').data('filter-office');
    var sortBy = $('#rch-agents-block').data('sort_by');
    var sortOrder = $('#rch-agents-block').data('sort_order');
    var nonce = $('#rch-agents-block').data('nonce');

    function loadAgents(page) {
        var loadingSpinner = $('#rch-loading-houses');
        loadingSpinner.show();

        $.ajax({
            url: rch_agents_params.ajax_url,
            type: 'POST',
            data: {
                action: 'rch_load_more_agents',
                page: page,
                posts_per_page: postsPerPage,
                region_bg_color: regionBgColor,
                text_color: textColor,
                filter_by_Regions:filterRegion,
                filter_by_offices:filterOffice,
                sort_by:sortBy,
                sort_order:sortOrder,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.rch-archive-agents').html(response.data.html);
                    $('#pagination-info').text('Page ' + page + ' of ' + totalPages);
                    $('#prev-page').data('page', page).prop('disabled', page <= 1);
                    $('#next-page').data('page', page).prop('disabled', page >= totalPages);

                    // Reinitialize image loading logic after new agents are loaded
                    initializeImageLoading();
                } else {
                    alert('Error loading agents.');
                }
                loadingSpinner.hide();
            }
        });
    }

    // Event handlers for pagination buttons
    $('#next-page').on('click', function() {
        var currentPage = $(this).data('page');
        var nextPage = currentPage + 1;
        loadAgents(nextPage);
    });

    $('#prev-page').on('click', function() {
        var currentPage = $(this).data('page');
        var prevPage = currentPage - 1;
        loadAgents(prevPage);
    });

    // Image loading logic - moved into a function to be reusable
    function initializeImageLoading() {
        $('.rch-image-container img').each(function () {
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
    }

    // Call the function on page load for the first time
    initializeImageLoading();
});
