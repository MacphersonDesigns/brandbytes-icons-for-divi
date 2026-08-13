(function ($) {
    'use strict';

    var settings = window.cifdIconLibrary || { icons: [], strings: {} };
    var $grid = $('#cifd-icon-grid');
    var $empty = $('#cifd-icon-grid-empty');
    var mediaFrame = null;

    function ajax(action, data) {
        return $.post(settings.ajaxUrl, $.extend({ action: action, nonce: settings.nonce }, data));
    }

    function renderGrid() {
        $grid.empty();
        var icons = settings.icons || [];
        $empty.prop('hidden', icons.length > 0);

        icons.forEach(function (icon) {
            var $item = $('<li>', { 'class': 'cifd-icon-card', 'data-id': icon.id });
            var $thumb = $('<img>', { src: icon.url, alt: '', 'class': 'cifd-icon-thumb' });
            var labelId = 'cifd-icon-name-' + icon.id;
            var $label = $('<label>', { 'for': labelId, text: settings.strings.namePlaceholder, 'class': 'screen-reader-text' });
            var $nameInput = $('<input>', { type: 'text', id: labelId, value: icon.name, 'class': 'cifd-icon-name' });
            var $deleteBtn = $('<button>', {
                type: 'button',
                'class': 'button-link cifd-icon-delete',
                text: 'Delete',
                'aria-label': 'Delete ' + icon.name
            });

            $nameInput.on('change', function () {
                var newName = $(this).val();
                ajax('cifd_rename_icon', { id: icon.id, name: newName }).done(function (response) {
                    if (response.success) {
                        icon.name = newName;
                        $deleteBtn.attr('aria-label', 'Delete ' + newName);
                    }
                });
            });

            $deleteBtn.on('click', function () {
                if (!window.confirm(settings.strings.deleteConfirm)) {
                    return;
                }
                ajax('cifd_delete_icon', { id: icon.id }).done(function (response) {
                    if (response.success) {
                        settings.icons = settings.icons.filter(function (i) { return i.id !== icon.id; });
                        $item.remove();
                        $empty.prop('hidden', settings.icons.length > 0);
                    }
                });
            });

            $item.append($thumb, $label, $nameInput, $deleteBtn);
            $grid.append($item);
        });
    }

    function addIcon() {
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: settings.strings.addIcon,
            library: { type: ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'] },
            multiple: false,
            button: { text: settings.strings.addIcon }
        });

        mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            var name = window.prompt(settings.strings.namePlaceholder, attachment.title || attachment.filename);
            if (!name) {
                return;
            }

            ajax('cifd_add_icon', { attachment_id: attachment.id, name: name }).done(function (response) {
                if (response.success) {
                    settings.icons.push(response.data.icon);
                    renderGrid();
                } else {
                    window.alert(response.data && response.data.message);
                }
            });
        });

        mediaFrame.open();
    }

    $(function () {
        renderGrid();

        $('#cifd-add-icon').on('click', addIcon);

        if ($.fn.sortable) {
            $grid.sortable({
                update: function () {
                    var ids = $grid.find('.cifd-icon-card').map(function () {
                        return $(this).data('id');
                    }).get();
                    ajax('cifd_reorder_icons', { ids: ids });
                }
            });
        }
    });
})(jQuery);
