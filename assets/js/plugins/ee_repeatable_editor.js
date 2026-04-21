(function ($) {
    'use strict';

    if (!$) {
        return;
    }

    function markDirty($editor) {
        $editor.find('input[name="property_data_changed"]').val('1');
    }

    function getActiveItems($editor) {
        return $editor.find('[data-repeatable-item]').filter(function () {
            return $(this).attr('data-repeatable-active') === '1';
        });
    }

    function getVisibleSlots($editor) {
        var value = parseInt($editor.attr('data-visible-slots') || '1', 10);
        return Number.isFinite(value) && value > 0 ? value : 1;
    }

    function getTotalSlots($editor) {
        var value = parseInt($editor.attr('data-total-slots') || '1', 10);
        return Number.isFinite(value) && value > 0 ? value : 1;
    }

    function updateItemTitle($card) {
        var fallbackTitle = 'Элемент ' + ((parseInt($card.attr('data-repeatable-item') || '0', 10) || 0) + 1);
        var nameValue = $.trim(String($card.find('[data-repeatable-title-input="1"]').first().val() || ''));
        $card.find('[data-repeatable-title]').text(nameValue !== '' ? nameValue : fallbackTitle);
    }

    function clearItem($card) {
        $card.find('input[type="text"], input[type="number"], input[type="email"], input[type="tel"], input[type="password"], input[type="hidden"][name^="property_data["]').val('');
        $card.find('textarea').val('');
        $card.find('select').each(function () {
            this.selectedIndex = 0;
        });
        $card.find('input[type="radio"], input[type="checkbox"]').prop('checked', false);
        $card.find('.fileItem').remove();
        $card.find('input[name="ee_dataFiles[]"]').remove();
        $card.find('input[type="file"]').val('');
        updateItemTitle($card);
    }

    function toggleSlotInputs($card, slotIndex, shouldEnable) {
        var $slot = $card.find('[data-repeatable-slot="' + slotIndex + '"]');
        $slot.toggleClass('d-none', !shouldEnable);
        $slot.attr('data-slot-visible', shouldEnable ? '1' : '0');
        $slot.find('input, textarea, select').prop('disabled', !shouldEnable);
    }

    function syncItemState($editor, $card, isActive) {
        var visibleSlots = getVisibleSlots($editor);
        $card.attr('data-repeatable-active', isActive ? '1' : '0');
        $card.toggleClass('d-none', !isActive);
        $card.find('input, textarea, select').prop('disabled', !isActive);

        $card.find('[data-repeatable-slot]').each(function () {
            var slotIndex = parseInt($(this).attr('data-repeatable-slot') || '0', 10);
            if (!Number.isFinite(slotIndex) || slotIndex <= 0) {
                return;
            }
            toggleSlotInputs($card, slotIndex, isActive && slotIndex <= visibleSlots);
        });

        if (isActive) {
            updateItemTitle($card);
        }
    }

    function syncEditorButtons($editor) {
        var activeItems = getActiveItems($editor);
        var hiddenItems = $editor.find('[data-repeatable-item]').filter(function () {
            return $(this).attr('data-repeatable-active') !== '1';
        });
        var visibleSlots = getVisibleSlots($editor);
        var totalSlots = getTotalSlots($editor);

        $editor.find('[data-repeatable-add]').prop('disabled', hiddenItems.length === 0);
        $editor.find('[data-repeatable-add-slot]').prop('disabled', visibleSlots >= totalSlots);
        $editor.find('[data-repeatable-remove-slot]').prop('disabled', visibleSlots <= 1);
        activeItems.find('[data-repeatable-item-remove]').prop('disabled', activeItems.length <= 1);
    }

    function syncEditorState($editor) {
        var visibleSlots = getVisibleSlots($editor);
        $editor.find('[data-repeatable-item]').each(function () {
            syncItemState($editor, $(this), $(this).attr('data-repeatable-active') === '1');
        });
        $editor.find('[data-repeatable-item][data-repeatable-active="1"]').each(function () {
            updateItemTitle($(this));
        });
        $editor.attr('data-visible-slots', String(visibleSlots));
        syncEditorButtons($editor);
    }

    function initRepeatableEditor($editor) {
        if ($editor.data('repeatableEditorReady')) {
            syncEditorState($editor);
            return;
        }
        $editor.data('repeatableEditorReady', true);
        syncEditorState($editor);
    }

    $(document).on('input change', '[data-repeatable-editor="1"] input, [data-repeatable-editor="1"] textarea, [data-repeatable-editor="1"] select', function () {
        var $editor = $(this).closest('[data-repeatable-editor="1"]');
        if ($editor.length) {
            markDirty($editor);
            var $card = $(this).closest('[data-repeatable-item]');
            if ($card.length) {
                updateItemTitle($card);
            }
        }
    });

    $(document).on('click', '[data-repeatable-add]', function () {
        var $editor = $(this).closest('[data-repeatable-editor="1"]');
        var $targetCard = $editor.find('[data-repeatable-item]').filter(function () {
            return $(this).attr('data-repeatable-active') !== '1';
        }).first();
        if (!$targetCard.length) {
            return;
        }
        clearItem($targetCard);
        syncItemState($editor, $targetCard, true);
        markDirty($editor);
        syncEditorState($editor);
    });

    $(document).on('click', '[data-repeatable-item-remove], [data-repeatable-remove]', function () {
        var $editor = $(this).closest('[data-repeatable-editor="1"]');
        var $card = $(this).closest('[data-repeatable-item]');
        if (!$card.length) {
            $card = getActiveItems($editor).last();
        }
        if (getActiveItems($editor).length <= 1) {
            return;
        }
        clearItem($card);
        syncItemState($editor, $card, false);
        markDirty($editor);
        syncEditorState($editor);
    });

    $(document).on('click', '[data-repeatable-add-slot]', function () {
        var $editor = $(this).closest('[data-repeatable-editor="1"]');
        var visibleSlots = getVisibleSlots($editor);
        var totalSlots = getTotalSlots($editor);
        if (visibleSlots >= totalSlots) {
            return;
        }
        visibleSlots += 1;
        $editor.attr('data-visible-slots', String(visibleSlots));
        getActiveItems($editor).each(function () {
            toggleSlotInputs($(this), visibleSlots, true);
        });
        markDirty($editor);
        syncEditorState($editor);
    });

    $(document).on('click', '[data-repeatable-remove-slot]', function () {
        var $editor = $(this).closest('[data-repeatable-editor="1"]');
        var visibleSlots = getVisibleSlots($editor);
        if (visibleSlots <= 1) {
            return;
        }
        getActiveItems($editor).each(function () {
            var $card = $(this);
            var $slot = $card.find('[data-repeatable-slot="' + visibleSlots + '"]');
            $slot.find('input[type="text"], input[type="number"], textarea').val('');
            $slot.find('select').each(function () {
                this.selectedIndex = 0;
            });
            $slot.find('input[type="radio"], input[type="checkbox"]').prop('checked', false);
            toggleSlotInputs($card, visibleSlots, false);
        });
        $editor.attr('data-visible-slots', String(visibleSlots - 1));
        markDirty($editor);
        syncEditorState($editor);
    });

    $(function () {
        $('[data-repeatable-editor="1"]').each(function () {
            initRepeatableEditor($(this));
        });
    });
}(window.jQuery));
