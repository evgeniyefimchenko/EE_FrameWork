(function ($) {
    'use strict';

    if (!$) {
        return;
    }

    function markDirty($context) {
        if (!$context || !$context.length) {
            $('input[name="property_data_changed"]').val('1');
            return;
        }
        var $inputs = $context.find('input[name="property_data_changed"]');
        if ($inputs.length) {
            $inputs.val('1');
            return;
        }
        $context.closest('form').find('input[name="property_data_changed"]').val('1');
    }

    function clearControls($scope) {
        $scope.find('input[type="text"], input[type="number"], input[type="email"], input[type="tel"], input[type="date"], input[type="time"], input[type="datetime-local"], input[type="password"], input[type="hidden"][name^="property_data["]').val('');
        $scope.find('textarea').val('');
        $scope.find('select').each(function () {
            this.selectedIndex = 0;
        });
        $scope.find('input[type="radio"], input[type="checkbox"]').prop('checked', false);
        $scope.find('.fileItem').remove();
        $scope.find('input[name="ee_dataFiles[]"]').remove();
        $scope.find('input[type="file"]').val('');
    }

    function enableControls($scope, shouldEnable) {
        $scope.find('input, textarea, select, button').prop('disabled', !shouldEnable);
    }

    function replacePlaceholder(value, placeholder, replacement) {
        return String(value || '').split(placeholder).join(String(replacement));
    }

    function getNextIndex($items, attrName) {
        var maxIndex = -1;
        $items.each(function () {
            var raw = $(this).attr(attrName || 'data-repeatable-group-row');
            var parsed = parseInt(raw, 10);
            if (Number.isFinite(parsed)) {
                maxIndex = Math.max(maxIndex, parsed);
            }
        });
        return maxIndex + 1;
    }

    function syncGroupChoiceOptions($field) {
        var type = String($field.find('[data-repeatable-group-field-type]').first().val() || '').toLowerCase();
        var isChoice = ['select', 'checkbox', 'radio'].indexOf(type) !== -1;
        var $options = $field.find('> [data-repeatable-group-choice-options]');
        $options.toggleClass('d-none', !isChoice);
        $options.find('input, textarea, select, button').prop('disabled', !isChoice);
    }

    function resetNestedGroups($scope) {
        $scope.find('[data-repeatable-group="1"]').each(function () {
            var $group = $(this);
            var $rows = $group.find('> .repeatable-group__rows > [data-repeatable-group-row]');
            $rows.slice(1).remove();
            clearControls($rows.first());
        });
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
        clearControls($card);
        resetNestedGroups($card);
        updateItemTitle($card);
    }

    function toggleSlotInputs($card, slotIndex, shouldEnable) {
        var $slot = $card.find('[data-repeatable-slot="' + slotIndex + '"]');
        $slot.toggleClass('d-none', !shouldEnable);
        $slot.attr('data-slot-visible', shouldEnable ? '1' : '0');
        $slot.find('input, textarea, select').prop('disabled', !shouldEnable);
        $slot.find('[data-repeatable-group="1"] button').prop('disabled', !shouldEnable);
    }

    function syncItemState($editor, $card, isActive) {
        var visibleSlots = getVisibleSlots($editor);
        $card.attr('data-repeatable-active', isActive ? '1' : '0');
        $card.toggleClass('d-none', !isActive);
        $card.find('input, textarea, select').prop('disabled', !isActive);
        $card.find('[data-repeatable-group="1"] button').prop('disabled', !isActive);

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

    $(document).on('input change', '[data-repeatable-group-definition] input, [data-repeatable-group-definition] textarea, [data-repeatable-group-definition] select, [data-repeatable-group="1"] input, [data-repeatable-group="1"] textarea, [data-repeatable-group="1"] select', function () {
        var $editor = $(this).closest('[data-repeatable-editor="1"]');
        markDirty($editor.length ? $editor : $(this));
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
            $slot.find('input[type="text"], input[type="number"], input[type="email"], input[type="tel"], input[type="date"], input[type="time"], input[type="datetime-local"], textarea').val('');
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

    $(document).on('click', '[data-repeatable-group-add]', function () {
        var $group = $(this).closest('[data-repeatable-group="1"]');
        var $template = $group.find('> template[data-repeatable-group-template]').first();
        if (!$template.length) {
            return;
        }

        var $rowsContainer = $group.find('> .repeatable-group__rows').first();
        var nextIndex = getNextIndex($rowsContainer.find('> [data-repeatable-group-row]'), 'data-repeatable-group-row');
        var html = replacePlaceholder($template.html(), '__row__', nextIndex);
        var $row = $(html);
        $row.attr('data-repeatable-group-row', String(nextIndex));
        clearControls($row);
        enableControls($row, !$(this).is(':disabled'));
        $rowsContainer.append($row);

        var $editor = $group.closest('[data-repeatable-editor="1"]');
        markDirty($editor.length ? $editor : $group);
    });

    $(document).on('click', '[data-repeatable-group-remove]', function () {
        var $row = $(this).closest('[data-repeatable-group-row]');
        var $group = $(this).closest('[data-repeatable-group="1"]');
        var $rows = $group.find('> .repeatable-group__rows > [data-repeatable-group-row]');
        if ($rows.length <= 1) {
            clearControls($row);
        } else {
            $row.remove();
        }

        var $editor = $group.closest('[data-repeatable-editor="1"]');
        markDirty($editor.length ? $editor : $group);
    });

    $(document).on('click', '[data-repeatable-group-field-add]', function () {
        var $definition = $(this).closest('[data-repeatable-group-definition]');
        var $template = $definition.find('> template[data-repeatable-group-definition-template]').first();
        if (!$template.length) {
            return;
        }

        var $fieldsContainer = $definition.find('> .repeatable-group-definition__fields').first();
        var nextIndex = getNextIndex($fieldsContainer.find('> [data-repeatable-group-definition-field]'), 'data-repeatable-group-definition-field');
        var html = replacePlaceholder($template.html(), '__field__', nextIndex);
        var $field = $(html);
        $field.attr('data-repeatable-group-definition-field', String(nextIndex));
        clearControls($field);
        enableControls($field, true);
        syncGroupChoiceOptions($field);
        $fieldsContainer.append($field);
        markDirty($definition);
    });

    $(document).on('click', '[data-repeatable-group-field-remove]', function () {
        var $field = $(this).closest('[data-repeatable-group-definition-field]');
        var $definition = $(this).closest('[data-repeatable-group-definition]');
        var $fields = $definition.find('> .repeatable-group-definition__fields > [data-repeatable-group-definition-field]');
        if ($fields.length <= 1) {
            clearControls($field);
        } else {
            $field.remove();
        }
        markDirty($definition);
    });

    $(document).on('change', '[data-repeatable-group-field-type]', function () {
        var $field = $(this).closest('[data-repeatable-group-definition-field]');
        syncGroupChoiceOptions($field);
        markDirty($field.closest('[data-repeatable-group-definition]'));
    });

    $(document).on('click', '[data-repeatable-group-option-add]', function () {
        var $options = $(this).closest('[data-repeatable-group-choice-options]');
        var $template = $options.find('> template[data-repeatable-group-option-template]').first();
        if (!$template.length) {
            return;
        }
        var $rows = $options.find('> .repeatable-group-definition__option-rows').first();
        var nextIndex = getNextIndex($rows.find('> [data-repeatable-group-option]'), 'data-repeatable-group-option');
        var html = replacePlaceholder($template.html(), '__option__', nextIndex);
        var $option = $(html);
        $option.attr('data-repeatable-group-option', String(nextIndex));
        clearControls($option);
        enableControls($option, true);
        $rows.append($option);
        markDirty($options.closest('[data-repeatable-group-definition]'));
    });

    $(document).on('click', '[data-repeatable-group-option-remove]', function () {
        var $option = $(this).closest('[data-repeatable-group-option]');
        var $options = $(this).closest('[data-repeatable-group-choice-options]');
        var $rows = $options.find('> .repeatable-group-definition__option-rows > [data-repeatable-group-option]');
        if ($rows.length <= 1) {
            clearControls($option);
        } else {
            $option.remove();
        }
        markDirty($options.closest('[data-repeatable-group-definition]'));
    });

    $(function () {
        $('[data-repeatable-editor="1"]').each(function () {
            initRepeatableEditor($(this));
        });
        $('[data-repeatable-group-definition-field]').each(function () {
            syncGroupChoiceOptions($(this));
        });
    });
}(window.jQuery));
