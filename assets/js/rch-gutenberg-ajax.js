jQuery(document).ready(function ($) {
    let currentPage = 1;

    function loadPosts(postType, page) {
        const block = $(`#rch-block-${postType}`);
        const totalPages = block.data('total-pages');
        const postsPerPage = block.data('posts-per-page');
        const regionBgColor = block.data('region-bg-color');
        const textColor = block.data('text-color');
        const nonce = block.data('nonce');
        const loaderId = $(`#rch-loading-${postType}`);

        // Retrieve meta key and value from data attributes
        const metaKey = block.data('meta-key') || ''; // Use an empty string as default
        const metaValue = block.data('meta-value') || ''; // Use an empty string as default

        $.ajax({
            url: rch_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'rch_load_more_posts',
                nonce: nonce,
                postType: postType,
                page: page,
                postsPerPage: postsPerPage,
                regionBgColor: regionBgColor,
                textColor: textColor,
                meta_key: metaKey, // Include meta key
                meta_value: metaValue // Include meta value
            },
            beforeSend: function () {
                block.find('.rch-rechat-block').css('visibility', 'hidden');
                $(loaderId).show();
            },
            success: function (response) {
                if (response.success) {
                    block.find('.rch-rechat-block').html(response.data.html);
                    currentPage = page;
                    updatePaginationButtons(postType, currentPage, totalPages);
                } else {
                    alert('Error loading posts');
                }
            },
            complete: function () {
                $(loaderId).hide();
                block.find('.rch-rechat-block').css('visibility', 'visible');
            },
            error: function () {
                alert('An error occurred while loading the posts.');
            }
        });
    }

    function updatePaginationButtons(postType, page, totalPages) {
        const block = $(`#rch-block-${postType}`);
        block.find('#pagination-info').text(`Page ${page} of ${totalPages}`);
        block.find('#prev-page[data-post-type="' + postType + '"]').prop('disabled', page === 1);
        block.find('#next-page[data-post-type="' + postType + '"]').prop('disabled', page >= totalPages);
    }

    // Pagination button click events
    $('.rch-pagination-button').on('click', function () {
        const postType = $(this).data('post-type'); // Get the postType dynamically
        const block = $(`#rch-block-${postType}`); // Use the block corresponding to the postType
        const totalPages = block.data('total-pages'); // Get total pages dynamically

        if ($(this).attr('id') === 'next-page' && currentPage < totalPages) {
            loadPosts(postType, currentPage + 1);
        }

        if ($(this).attr('id') === 'prev-page' && currentPage > 1) {
            loadPosts(postType, currentPage - 1);
        }
    });

    // Initialize pagination for 'regions' and 'offices'
    updatePaginationButtons('regions', currentPage, $('#rch-block-regions').data('total-pages'));
    updatePaginationButtons('offices', currentPage, $('#rch-block-offices').data('total-pages'));
});
