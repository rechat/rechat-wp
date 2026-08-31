/**
 * Off Market — gallery media uploader (admin edit screen).
 * Multi-select from the Media Library, drag to reorder, remove.
 * Stores a comma-separated list of attachment IDs in the hidden input.
 */
(function ($) {
    'use strict';

    function serialize($wrap) {
        var ids = [];
        $wrap.find('.rch-om-gallery__item').each(function () {
            ids.push(String($(this).attr('data-id')));
        });
        $wrap.find('.rch-om-gallery__input').val(ids.join(','));
    }

    // Open the media frame and append selected images.
    $(document).on('click', '.rch-om-gallery__add', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.rch-om-gallery');

        var frame = wp.media({
            title: 'Select or upload listing images',
            button: { text: 'Use these images' },
            library: { type: 'image' },
            multiple: true
        });

        frame.on('select', function () {
            var selection = frame.state().get('selection');
            selection.each(function (attachment) {
                var att = attachment.toJSON();
                if ($wrap.find('.rch-om-gallery__item[data-id="' + att.id + '"]').length) {
                    return; // skip duplicates
                }
                var thumb = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
                var $item = $('<li class="rch-om-gallery__item"></li>').attr('data-id', att.id);
                $item.append($('<img alt="" />').attr('src', thumb));
                $item.append('<button type="button" class="rch-om-gallery__remove" aria-label="Remove image">&times;</button>');
                $wrap.find('.rch-om-gallery__list').append($item);
            });
            serialize($wrap);
        });

        frame.open();
    });

    // Remove an image.
    $(document).on('click', '.rch-om-gallery__remove', function (e) {
        e.preventDefault();
        var $wrap = $(this).closest('.rch-om-gallery');
        $(this).closest('.rch-om-gallery__item').remove();
        serialize($wrap);
    });

    // Drag to reorder (first image is the cover).
    $(function () {
        $('.rch-om-gallery__list').sortable({
            items: '> .rch-om-gallery__item',
            tolerance: 'pointer',
            update: function () {
                serialize($(this).closest('.rch-om-gallery'));
            }
        });
    });
})(jQuery);
