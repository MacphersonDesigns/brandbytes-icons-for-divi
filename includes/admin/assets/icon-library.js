(function ($) {
    'use strict';

    var settings = window.cifdIconLibrary || { icons: [], strings: {} };
    var $grid = $('#cifd-icon-grid');
    var $empty = $('#cifd-icon-grid-empty');
    var $status = $('#cifd-icon-status');
    var mediaFrame = null;

    function ajax(action, data) {
        return $.post(settings.ajaxUrl, $.extend({ action: action, nonce: settings.nonce }, data));
    }

    function announce(message) {
        $status.text(message);
    }

    // Shared by both the mouse drag-and-drop path and the keyboard move
    // up/down buttons so there is a single place that constructs the
    // cifd_reorder_icons AJAX call. Always reads order from settings.icons.
    function persistOrder() {
        var ids = settings.icons.map(function (icon) {
            return icon.id;
        });
        return ajax('cifd_reorder_icons', { ids: ids });
    }

    function moveIcon(id, direction) {
        var index = -1;
        for (var i = 0; i < settings.icons.length; i++) {
            if (settings.icons[i].id === id) {
                index = i;
                break;
            }
        }
        if (index === -1) {
            return;
        }

        var newIndex = index + direction;
        if (newIndex < 0 || newIndex >= settings.icons.length) {
            return;
        }

        var icon = settings.icons[index];
        settings.icons.splice(index, 1);
        settings.icons.splice(newIndex, 0, icon);

        renderGrid();
        persistOrder();
        announce(icon.name + (direction < 0 ? ' moved up.' : ' moved down.'));

        // Keep keyboard focus on the moved card's action so repeated
        // up/down presses work without the user having to re-locate focus.
        var $sameDirectionBtn = direction < 0
            ? $('#cifd-icon-move-up-' + id)
            : $('#cifd-icon-move-down-' + id);
        if ($sameDirectionBtn.length && !$sameDirectionBtn.prop('disabled')) {
            $sameDirectionBtn.focus();
        } else {
            $('#cifd-icon-delete-' + id).focus();
        }
    }

    function renderGrid() {
        $grid.empty();
        var icons = settings.icons || [];
        $empty.prop('hidden', icons.length > 0);

        icons.forEach(function (icon, index) {
            var $item = $('<li>', { 'class': 'cifd-icon-card', 'data-id': icon.id });
            var $thumb = $('<img>', { src: icon.url, alt: '', 'class': 'cifd-icon-thumb' });
            var labelId = 'cifd-icon-name-' + icon.id;
            var $label = $('<label>', { 'for': labelId, text: settings.strings.namePlaceholder, 'class': 'screen-reader-text' });
            var $nameInput = $('<input>', { type: 'text', id: labelId, value: icon.name, 'class': 'cifd-icon-name' });

            var $actions = $('<div>', { 'class': 'cifd-icon-actions' });

            var $moveUpBtn = $('<button>', {
                type: 'button',
                id: 'cifd-icon-move-up-' + icon.id,
                'class': 'button-link cifd-icon-move cifd-icon-move-up',
                text: '↑',
                'aria-label': 'Move ' + icon.name + ' up'
            });
            var $moveDownBtn = $('<button>', {
                type: 'button',
                id: 'cifd-icon-move-down-' + icon.id,
                'class': 'button-link cifd-icon-move cifd-icon-move-down',
                text: '↓',
                'aria-label': 'Move ' + icon.name + ' down'
            });
            if (index === 0) {
                $moveUpBtn.prop('disabled', true);
            }
            if (index === icons.length - 1) {
                $moveDownBtn.prop('disabled', true);
            }

            var $deleteBtn = $('<button>', {
                type: 'button',
                id: 'cifd-icon-delete-' + icon.id,
                'class': 'button-link cifd-icon-delete',
                text: 'Delete',
                'aria-label': 'Delete ' + icon.name
            });

            $nameInput.on('change', function () {
                var newName = $(this).val();
                var previousName = icon.name;

                ajax('cifd_rename_icon', { id: icon.id, name: newName }).done(function (response) {
                    if (response.success) {
                        icon.name = newName;
                        $deleteBtn.attr('aria-label', 'Delete ' + newName);
                        $moveUpBtn.attr('aria-label', 'Move ' + newName + ' up');
                        $moveDownBtn.attr('aria-label', 'Move ' + newName + ' down');
                        announce('Renamed to ' + newName + '.');
                    } else {
                        $nameInput.val(previousName);
                        announce(response.data && response.data.message ? response.data.message : 'Rename failed.');
                    }
                });
            });

            $moveUpBtn.on('click', function () {
                moveIcon(icon.id, -1);
            });

            $moveDownBtn.on('click', function () {
                moveIcon(icon.id, 1);
            });

            $deleteBtn.on('click', function () {
                if (!window.confirm(settings.strings.deleteConfirm)) {
                    return;
                }

                ajax('cifd_delete_icon', { id: icon.id }).done(function (response) {
                    if (response.success) {
                        settings.icons = settings.icons.filter(function (i) { return i.id !== icon.id; });

                        // Determine the fallback focus target before the
                        // item is removed from the DOM, so focus is never
                        // dropped back to <body> after deletion.
                        var $cards = $grid.find('.cifd-icon-card');
                        var currentIndex = $cards.index($item);
                        var $next = $cards.eq(currentIndex + 1);
                        var $prev = $cards.eq(currentIndex - 1);
                        var $focusTarget;
                        if ($next.length) {
                            $focusTarget = $next.find('.cifd-icon-delete');
                        } else if ($prev.length) {
                            $focusTarget = $prev.find('.cifd-icon-delete');
                        } else {
                            $focusTarget = $('#cifd-add-icon');
                        }

                        $item.remove();
                        $empty.prop('hidden', settings.icons.length > 0);
                        announce(icon.name + ' deleted.');

                        if ($focusTarget && $focusTarget.length) {
                            $focusTarget.focus();
                        }
                    }
                });
            });

            $actions.append($moveUpBtn, $moveDownBtn, $deleteBtn);
            $item.append($thumb, $label, $nameInput, $actions);
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
                    announce(response.data.icon.name + ' added.');
                    $('#cifd-icon-name-' + response.data.icon.id).focus();
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
                    // Keep settings.icons in sync with the new DOM order so
                    // it stays the single source of truth for renderGrid()
                    // and the keyboard move buttons.
                    var ids = $grid.find('.cifd-icon-card').map(function () {
                        return $(this).data('id');
                    }).get();
                    settings.icons = ids.map(function (id) {
                        return settings.icons.filter(function (icon) {
                            return icon.id === id;
                        })[0];
                    });
                    persistOrder();
                }
            });
        }
    });
})(jQuery);
