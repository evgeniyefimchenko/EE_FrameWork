$(document).ready(function() {
    var t = function(key, fallback) {
        return (window.AppCore && typeof AppCore.getLangVar === 'function' ? AppCore.getLangVar(key) : '') || fallback;
    };
    function extractJsonObject(text, startIndex) {
        var start = parseInt(startIndex, 10);
        if (isNaN(start) || start < 0 || start >= text.length || text.charAt(start) !== '{') {
            return '';
        }

        var depth = 0;
        var inString = false;
        var isEscaped = false;
        for (var i = start; i < text.length; i++) {
            var ch = text.charAt(i);

            if (inString) {
                if (isEscaped) {
                    isEscaped = false;
                } else if (ch === '\\') {
                    isEscaped = true;
                } else if (ch === '"') {
                    inString = false;
                }
                continue;
            }

            if (ch === '"') {
                inString = true;
                continue;
            }
            if (ch === '{') {
                depth++;
                continue;
            }
            if (ch === '}') {
                depth--;
                if (depth === 0) {
                    return text.slice(start, i + 1);
                }
            }
        }
        return '';
    }

    function parseJsonResponse(raw) {
        if (typeof raw === 'object' && raw !== null) {
            return { payload: raw, extra: '' };
        }

        var text = String(raw || '');
        var trimmed = text.trim();
        if (trimmed === '') {
            throw new Error(t('sys.empty_response', 'Empty response'));
        }

        try {
            return { payload: JSON.parse(trimmed), extra: '' };
        } catch (e) {
            var starts = [];
            var pos = trimmed.indexOf('{"success"');
            while (pos >= 0) {
                starts.push(pos);
                pos = trimmed.indexOf('{"success"', pos + 1);
            }
            if (starts.length === 0) {
                for (var i = 0; i < trimmed.length; i++) {
                    if (trimmed.charAt(i) === '{') {
                        starts.push(i);
                        if (starts.length >= 40) {
                            break;
                        }
                    }
                }
            }

            for (var idx = 0; idx < starts.length; idx++) {
                var start = starts[idx];
                var candidate = extractJsonObject(trimmed, start);
                if (!candidate) {
                    continue;
                }
                try {
                    var parsed = JSON.parse(candidate);
                    var prefix = trimmed.slice(0, start);
                    var suffix = trimmed.slice(start + candidate.length);
                    var extra = (prefix + suffix).trim();
                    return { payload: parsed, extra: extra };
                } catch (ignored) {
                    // Try next candidate.
                }
            }
            throw e;
        }
    }

    function compactText(value, maxLen) {
        var text = String(value || '').replace(/\s+/g, ' ').trim();
        if (!maxLen || text.length <= maxLen) {
            return text;
        }
        return text.substring(0, maxLen) + '...';
    }

    function normalizeLineList(raw) {
        var lines = String(raw || '').split(/[\r\n,;]+/);
        var out = [];
        var seen = {};
        for (var i = 0; i < lines.length; i++) {
            var item = String(lines[i] || '').trim().toLowerCase();
            if (!item || seen[item]) {
                continue;
            }
            seen[item] = true;
            out.push(item);
        }
        return out;
    }

    function uniqueTrimmed(values) {
        var out = [];
        var seen = {};
        if (!Array.isArray(values)) {
            return out;
        }
        for (var i = 0; i < values.length; i++) {
            var text = String(values[i] || '').replace(/\s+/g, ' ').trim();
            if (!text) {
                continue;
            }
            var key = text.toLowerCase();
            if (seen[key]) {
                continue;
            }
            seen[key] = true;
            out.push(text);
        }
        return out;
    }

    function parseJsonArrayAttr(rawValue, toLower) {
        var text = String(rawValue || '').trim();
        if (!text) {
            return [];
        }
        var shouldLower = !!toLower;
        try {
            var decoded = JSON.parse(text);
            if (!Array.isArray(decoded)) {
                return [];
            }
            var result = [];
            for (var i = 0; i < decoded.length; i++) {
                var item = String(decoded[i] || '').trim();
                if (!item) {
                    continue;
                }
                if (shouldLower) {
                    item = item.toLowerCase();
                }
                result.push(item);
            }
            return result;
        } catch (e) {
            return [];
        }
    }

    function renderPreviewStatusCell($cell, statusKey, detailText) {
        var text = 'Не определено';
        var badgeClass = 'bg-light text-dark border';

        if (statusKey === 'explicit') {
            text = 'Явный маппинг';
            badgeClass = 'bg-success';
        } else if (statusKey === 'auto') {
            text = 'К импорту';
            badgeClass = 'bg-success';
        } else if (statusKey === 'disabled') {
            text = 'Отключено';
            badgeClass = 'bg-secondary';
        } else if (statusKey === 'filtered') {
            text = 'Исключено фильтром';
            badgeClass = 'bg-danger';
        } else if (statusKey === 'manual') {
            text = 'Исключено вручную';
            badgeClass = 'bg-warning text-dark';
        } else if (statusKey === 'standalone') {
            text = 'Без источника';
            badgeClass = 'bg-info text-dark';
        }

        var tooltipText = text;
        var normalizedDetail = String(detailText || '').trim();
        if (normalizedDetail) {
            tooltipText += ': ' + normalizedDetail;
        } else if (statusKey === 'auto') {
            tooltipText = 'Ключ будет импортирован';
        } else if (statusKey === 'standalone') {
            tooltipText = 'Ключ не связан с источниками, но проходит фильтры и будет импортирован';
        } else if (statusKey === 'disabled') {
            tooltipText = 'Ключ связан только с отключёнными источниками';
        } else if (statusKey === 'filtered') {
            tooltipText = 'Ключ исключён правилами фильтрации meta-ключей';
        } else if (statusKey === 'manual') {
            tooltipText = 'Ключ исключён вручную и не попадёт в импорт';
        }

        $cell.empty().append($('<span>', {
            'class': 'badge ' + badgeClass,
            title: tooltipText,
            'data-bs-toggle': 'tooltip',
            'data-bs-placement': 'top',
            text: text
        }));
        if (normalizedDetail) {
            $cell.append($('<div>', {
                'class': 'small text-muted mt-1',
                text: normalizedDetail
            }));
        }
    }

    function renderPreviewManualControl($cell, propertySourceId, isManuallyExcluded) {
        var sourceId = String(propertySourceId || '').trim().toLowerCase();
        $cell.empty();
        if (!sourceId) {
            $cell.append($('<span>', {
                'class': 'text-muted small',
                text: 'n/a'
            }));
            return;
        }
        var buttonClass = isManuallyExcluded ? 'btn-warning' : 'btn-outline-secondary';
        var buttonText = isManuallyExcluded ? 'Вернуть' : 'Исключить';
        var tooltipText = isManuallyExcluded
            ? 'Вернуть ключ в импорт и снова показывать его в доступных полях конструктора'
            : 'Исключить ключ из импорта и скрыть его из доступных полей конструктора';
        $cell.append(
            $('<button>', {
                type: 'button',
                'class': 'btn btn-sm ' + buttonClass + ' js-preview-toggle-manual-exclude',
                'data-property-source-id': sourceId,
                title: tooltipText,
                'data-bs-toggle': 'tooltip',
                'data-bs-placement': 'top',
                text: buttonText
            })
        );
    }

    function resetPreviewRowClasses($row) {
        $row.removeClass('table-success table-warning table-secondary table-info table-danger opacity-75');
    }

    function parseMaskPatterns(raw) {
        var lines = String(raw || '').split(/[\r\n,;]+/);
        var out = [];
        var seen = {};
        for (var i = 0; i < lines.length; i++) {
            var pattern = String(lines[i] || '').trim().toLowerCase();
            if (!pattern || seen[pattern]) {
                continue;
            }
            seen[pattern] = true;
            out.push(pattern);
        }
        return out;
    }

    function wildcardMatch(pattern, value) {
        var normalizedPattern = String(pattern || '').trim();
        if (!normalizedPattern) {
            return false;
        }
        var regexText = '^' + normalizedPattern
            .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
            .replace(/\*/g, '.*')
            .replace(/\?/g, '.') + '$';
        try {
            return new RegExp(regexText, 'iu').test(String(value || ''));
        } catch (e) {
            return false;
        }
    }

    function matchesAnyPattern(value, patterns) {
        for (var i = 0; i < patterns.length; i++) {
            if (wildcardMatch(patterns[i], value)) {
                return true;
            }
        }
        return false;
    }

    function extractMetaKeyFromSourceId(sourceId) {
        var value = String(sourceId || '').trim();
        if (!value) {
            return '';
        }
        var idx = value.indexOf(':');
        if (idx < 0) {
            return value;
        }
        return String(value.slice(idx + 1)).trim();
    }

    function evaluateMetaFilter(metaKeyRaw, includePrivateMetaKeys, includePatterns, excludePatterns) {
        var metaKey = String(metaKeyRaw || '').trim();
        if (!metaKey) {
            return { allowed: false, reason: 'Пустой meta-ключ' };
        }
        if (metaKey === '_thumbnail_id') {
            return { allowed: true, reason: '' };
        }
        if (!includePrivateMetaKeys && metaKey.charAt(0) === '_') {
            return { allowed: false, reason: 'Системный ключ `_` скрыт настройками' };
        }
        var normalized = metaKey.toLowerCase();
        if (includePatterns.length > 0 && !matchesAnyPattern(normalized, includePatterns)) {
            return { allowed: false, reason: 'Не входит в белый список' };
        }
        if (excludePatterns.length > 0 && matchesAnyPattern(normalized, excludePatterns)) {
            return { allowed: false, reason: 'Попал в чёрный список' };
        }
        return { allowed: true, reason: '' };
    }

    function parseEmbeddedJsonScript(id, fallbackValue) {
        var $el = $('#' + id);
        if (!$el.length) {
            return fallbackValue;
        }
        var raw = String($el.text() || '').trim();
        if (!raw) {
            return fallbackValue;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return fallbackValue;
        }
    }

    function normalizeCompositeEntityType(value) {
        var normalized = String(value || '').trim().toLowerCase();
        if (normalized !== 'category' && normalized !== 'page' && normalized !== 'all') {
            return 'all';
        }
        return normalized;
    }

    function inferCompositeSourceKind(sourceIdRaw) {
        var sourceId = String(sourceIdRaw || '').trim().toLowerCase();
        if (sourceId.indexOf('termmeta:') === 0 || sourceId.indexOf('taxonomy:') === 0) {
            return 'termmeta';
        }
        return 'postmeta';
    }

    function buildCompositePropertySourceId(sourceKind, metaKey) {
        var kind = String(sourceKind || '').trim().toLowerCase() === 'termmeta' ? 'termmeta' : 'postmeta';
        var rawMeta = String(metaKey || '').trim();
        if (!rawMeta) {
            return '';
        }
        if (rawMeta.indexOf('postmeta:') === 0 || rawMeta.indexOf('termmeta:') === 0) {
            return rawMeta.toLowerCase();
        }
        return kind + ':' + rawMeta.toLowerCase();
    }

    var compositeMetaOptions = parseEmbeddedJsonScript('composite-meta-options-json', []);
    var compositeFieldTypesMap = parseEmbeddedJsonScript('composite-field-types-json', {});
    var compositeLocalProperties = parseEmbeddedJsonScript('composite-local-properties-json', []);
    var compositeState = [];
    var manualExcludedSourceIds = {};
    var compositeMetaOptionsBySourceId = {};
    for (var compositeMetaIdx = 0; compositeMetaIdx < compositeMetaOptions.length; compositeMetaIdx++) {
        var compositeMetaItem = compositeMetaOptions[compositeMetaIdx];
        if (!compositeMetaItem || typeof compositeMetaItem !== 'object') {
            continue;
        }
        var compositeMetaSourceId = String(compositeMetaItem.property_source_id || '').trim().toLowerCase();
        if (!compositeMetaSourceId) {
            continue;
        }
        compositeMetaOptionsBySourceId[compositeMetaSourceId] = compositeMetaItem;
    }

    function getCompositeMetaOptionBySourceId(propertySourceId) {
        var sourceId = String(propertySourceId || '').trim().toLowerCase();
        if (!sourceId) {
            return null;
        }
        return compositeMetaOptionsBySourceId[sourceId] || null;
    }

    function parseManualExcludedSourceIdsMapFromInput() {
        var map = {};
        var values = normalizeLineList($('#excluded_property_source_ids').val());
        for (var i = 0; i < values.length; i++) {
            var sourceId = String(values[i] || '').trim().toLowerCase();
            if (!sourceId) {
                continue;
            }
            map[sourceId] = true;
        }
        return map;
    }

    function syncManualExcludedSourceIdsInput() {
        var sourceIds = Object.keys(manualExcludedSourceIds);
        sourceIds.sort();
        $('#excluded_property_source_ids').val(sourceIds.join('\n'));
    }

    function isPropertySourceManuallyExcluded(propertySourceId) {
        var sourceId = String(propertySourceId || '').trim().toLowerCase();
        if (!sourceId) {
            return false;
        }
        return !!manualExcludedSourceIds[sourceId];
    }

    function isPropertySourceMappedInComposite(propertySourceId) {
        var sourceId = String(propertySourceId || '').trim().toLowerCase();
        if (!sourceId || !Array.isArray(compositeState) || !compositeState.length) {
            return false;
        }
        for (var i = 0; i < compositeState.length; i++) {
            var item = compositeState[i];
            if (!item || typeof item !== 'object' || !Array.isArray(item.fields)) {
                continue;
            }
            for (var j = 0; j < item.fields.length; j++) {
                var field = item.fields[j];
                if (!field || typeof field !== 'object') {
                    continue;
                }
                var mappedSourceId = String(field.property_source_id || '').trim().toLowerCase();
                if (!mappedSourceId) {
                    continue;
                }
                if (mappedSourceId === sourceId) {
                    return true;
                }
                if ((mappedSourceId.indexOf('*') >= 0 || mappedSourceId.indexOf('?') >= 0) && wildcardMatch(mappedSourceId, sourceId)) {
                    return true;
                }
            }
        }
        return false;
    }

    function normalizeSourceSetIds(sourceSetIdsRaw) {
        if (!Array.isArray(sourceSetIdsRaw)) {
            return [];
        }
        var out = [];
        var seen = {};
        for (var i = 0; i < sourceSetIdsRaw.length; i++) {
            var sourceId = String(sourceSetIdsRaw[i] || '').trim().toLowerCase();
            if (!sourceId || seen[sourceId]) {
                continue;
            }
            seen[sourceId] = true;
            out.push(sourceId);
        }
        return out;
    }

    function getEnabledSourcesMap() {
        var enabledSourcesMap = {};
        $('.js-source-enabled').each(function() {
            var sourceId = String($(this).data('source-id') || '').trim().toLowerCase();
            if (!sourceId) {
                return;
            }
            enabledSourcesMap[sourceId] = $(this).is(':checked');
        });
        return enabledSourcesMap;
    }

    function getCurrentMetaFilterContext() {
        return {
            includePrivateMetaKeys: $('#include_private_meta_keys').is(':checked'),
            includePatterns: parseMaskPatterns($('#meta_include_patterns').val()),
            excludePatterns: parseMaskPatterns($('#meta_exclude_patterns').val()),
            enabledSourcesMap: getEnabledSourcesMap(),
            hasSourceSwitches: $('.js-source-enabled').length > 0
        };
    }

    function evaluateImportStateForPropertySource(propertySourceId, sourceSetIdsRaw, metaKeyRaw, context) {
        var sourceId = String(propertySourceId || '').trim().toLowerCase();
        var sourceSetIds = normalizeSourceSetIds(sourceSetIdsRaw);
        var activeLinkedSources = 0;
        for (var i = 0; i < sourceSetIds.length; i++) {
            if (!context.hasSourceSwitches || context.enabledSourcesMap[sourceSetIds[i]]) {
                activeLinkedSources++;
            }
        }
        var hasLinkedSources = sourceSetIds.length > 0;
        var rawMetaKey = String(metaKeyRaw || '').trim();
        if (!rawMetaKey) {
            rawMetaKey = extractMetaKeyFromSourceId(sourceId);
        }
        var metaFilterDecision = evaluateMetaFilter(
            rawMetaKey,
            context.includePrivateMetaKeys,
            context.includePatterns,
            context.excludePatterns
        );

        var statusKey = 'auto';
        var statusDetail = '';
        if (isPropertySourceManuallyExcluded(sourceId)) {
            statusKey = 'manual';
            statusDetail = 'Исключено вручную';
        } else if (hasLinkedSources && activeLinkedSources === 0) {
            statusKey = 'disabled';
            statusDetail = 'Все связанные источники отключены';
        } else if (!metaFilterDecision.allowed) {
            statusKey = 'filtered';
            statusDetail = metaFilterDecision.reason;
        } else if (!hasLinkedSources) {
            statusKey = 'standalone';
        }

        return {
            statusKey: statusKey,
            statusDetail: statusDetail,
            metaFilterDecision: metaFilterDecision
        };
    }

    function isPropertySourceSelectableForComposite(propertySourceId, sourceSetIdsRaw, metaKeyRaw, context) {
        var decision = evaluateImportStateForPropertySource(propertySourceId, sourceSetIdsRaw, metaKeyRaw, context);
        return decision.statusKey === 'auto' || decision.statusKey === 'standalone';
    }

    function findCompositePropertySourceIdByMetaKey(metaKey) {
        var normalizedMeta = String(metaKey || '').trim().toLowerCase();
        if (!normalizedMeta) {
            return '';
        }
        if (normalizedMeta.indexOf('postmeta:') === 0 || normalizedMeta.indexOf('termmeta:') === 0) {
            return normalizedMeta;
        }
        var exactSuffix = ':' + normalizedMeta;
        for (var i = 0; i < compositeMetaOptions.length; i++) {
            var option = compositeMetaOptions[i];
            if (!option || typeof option !== 'object') {
                continue;
            }
            var sourceId = String(option.property_source_id || '').trim().toLowerCase();
            if (!sourceId || sourceId.lastIndexOf(exactSuffix) !== sourceId.length - exactSuffix.length) {
                continue;
            }
            return sourceId;
        }
        return '';
    }

    function toCompositeTargetFieldIndex(value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return null;
        }
        var parsed = parseInt(value, 10);
        if (isNaN(parsed) || parsed < 0) {
            return -1;
        }
        return parsed;
    }

    function getCompositeLocalPropertyById(propertyId) {
        var wantedId = parseInt(propertyId, 10);
        if (isNaN(wantedId) || wantedId <= 0) {
            return null;
        }
        for (var i = 0; i < compositeLocalProperties.length; i++) {
            var item = compositeLocalProperties[i];
            if (!item || typeof item !== 'object') {
                continue;
            }
            var currentId = parseInt(item.id, 10);
            if (!isNaN(currentId) && currentId === wantedId) {
                return item;
            }
        }
        return null;
    }

    function getCompositeLocalPropertyFields(propertyId) {
        var property = getCompositeLocalPropertyById(propertyId);
        if (!property || !Array.isArray(property.fields)) {
            return [];
        }
        var result = [];
        for (var i = 0; i < property.fields.length; i++) {
            var field = property.fields[i];
            if (!field || typeof field !== 'object') {
                continue;
            }
            var index = parseInt(field.index, 10);
            if (isNaN(index) || index < 0) {
                continue;
            }
            var fieldName = String(field.name || '').trim();
            var fieldTitle = String(field.title || '').trim();
            var fieldLabel = String(field.label || '').trim();
            var fieldType = String(field.type || 'text').trim().toLowerCase() || 'text';
            if (!fieldName) {
                fieldName = fieldTitle || fieldLabel || ('Field #' + (index + 1));
            }
            result.push({
                index: index,
                name: fieldName,
                title: fieldTitle,
                label: fieldLabel,
                type: fieldType
            });
        }
        return result;
    }

    function buildCompositeTargetFieldOptionsHtml(targetPropertyId, selectedFieldIndex) {
        var selected = toCompositeTargetFieldIndex(selectedFieldIndex);
        if (selected === null) {
            selected = -1;
        }
        var fields = getCompositeLocalPropertyFields(targetPropertyId);
        var options = '<option value="-1"' + (selected === -1 ? ' selected' : '') + '>Создать новое поле</option>';
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            var index = parseInt(field.index, 10);
            if (isNaN(index) || index < 0) {
                continue;
            }
            var fieldName = String(field.name || ('Field #' + (index + 1))).trim();
            var fieldType = String(field.type || 'text').trim().toLowerCase() || 'text';
            var caption = '#' + (index + 1) + ' ' + fieldName + ' [' + fieldType + ']';
            options += '<option value="' + index + '"' + (selected === index ? ' selected' : '') + '>' +
                $('<div>').text(caption).html() +
                '</option>';
        }
        return options;
    }

    function normalizeCompositeState(rawState) {
        if (!Array.isArray(rawState)) {
            return [];
        }
        var normalized = [];
        for (var i = 0; i < rawState.length; i++) {
            var item = rawState[i];
            if (!item || typeof item !== 'object') {
                continue;
            }
            var name = String(item.name || '').trim();
            var setSourceId = String(item.set_source_id || '').trim().toLowerCase();
            var sourceKind = String(item.source_kind || inferCompositeSourceKind(setSourceId)).trim().toLowerCase();
            if (sourceKind !== 'termmeta' && sourceKind !== 'postmeta') {
                sourceKind = inferCompositeSourceKind(setSourceId);
            }
            var fields = Array.isArray(item.fields) ? item.fields : [];
            var normalizedFields = [];
            for (var j = 0; j < fields.length; j++) {
                var field = fields[j];
                if (!field || typeof field !== 'object') {
                    continue;
                }
                var fieldType = String(field.type || 'text').trim().toLowerCase();
                if (!fieldType) {
                    fieldType = 'text';
                }
                var propertySourceId = String(field.property_source_id || '').trim().toLowerCase();
                var metaKey = String(field.meta_key || '').trim();
                if (!metaKey && propertySourceId.indexOf(':') > 0) {
                    metaKey = propertySourceId.split(':').slice(1).join(':');
                }
                if (!propertySourceId && metaKey) {
                    propertySourceId = buildCompositePropertySourceId(sourceKind, metaKey);
                }
                var targetFieldIndex = Object.prototype.hasOwnProperty.call(field, 'target_field_index')
                    ? toCompositeTargetFieldIndex(field.target_field_index)
                    : null;
                normalizedFields.push({
                    type: fieldType,
                    label: String(field.label || '').trim(),
                    title: String(field.title || '').trim(),
                    meta_key: metaKey,
                    property_source_id: propertySourceId,
                    target_field_index: targetFieldIndex,
                    multiple: field.multiple ? 1 : 0,
                    required: field.required ? 1 : 0
                });
            }
            normalized.push({
                source_id: String(item.source_id || '').trim().toLowerCase(),
                name: name,
                entity_type: normalizeCompositeEntityType(item.entity_type || 'all'),
                set_source_id: setSourceId,
                source_kind: sourceKind,
                target_property_id: Math.max(0, parseInt(item.target_property_id, 10) || 0),
                is_multiple: item.is_multiple ? 1 : 0,
                is_required: item.is_required ? 1 : 0,
                fields: normalizedFields
            });
        }
        return normalized;
    }

    function parseCompositeStateFromInput() {
        var raw = String($('#composite_properties_map').val() || '').trim();
        if (!raw) {
            return [];
        }
        try {
            var decoded = JSON.parse(raw);
            return normalizeCompositeState(decoded);
        } catch (e) {
            return [];
        }
    }

    function buildCompositeFieldTypeOptionsHtml(selectedType) {
        var options = '';
        var selected = String(selectedType || 'text').toLowerCase();
        var keys = Object.keys(compositeFieldTypesMap || {});
        if (!keys.length) {
            keys = ['text', 'number', 'date', 'time', 'datetime-local', 'hidden', 'password', 'file', 'email', 'phone', 'select', 'textarea', 'image', 'checkbox', 'radio'];
        }
        for (var i = 0; i < keys.length; i++) {
            var code = String(keys[i] || '').trim();
            if (!code) {
                continue;
            }
            var label = String((compositeFieldTypesMap && compositeFieldTypesMap[code]) || code).trim();
            options += '<option value="' + $('<div>').text(code).html() + '"' + (selected === code ? ' selected' : '') + '>' +
                $('<div>').text(code + ' (' + label + ')').html() +
                '</option>';
        }
        return options;
    }

    function buildCompositePropertyOptionsHtml(selectedPropertyId) {
        var selected = parseInt(selectedPropertyId, 10);
        if (isNaN(selected) || selected < 0) {
            selected = 0;
        }
        var options = '<option value="0">Создать/подобрать автоматически</option>';
        for (var i = 0; i < compositeLocalProperties.length; i++) {
            var item = compositeLocalProperties[i];
            if (!item || typeof item !== 'object') {
                continue;
            }
            var propertyId = parseInt(item.id, 10);
            if (isNaN(propertyId) || propertyId <= 0) {
                continue;
            }
            var propertyName = String(item.name || ('property#' + propertyId)).trim();
            options += '<option value="' + propertyId + '"' + (selected === propertyId ? ' selected' : '') + '>' +
                $('<div>').text('#' + propertyId + ' ' + propertyName).html() +
                '</option>';
        }
        return options;
    }

    function buildCompositeMetaOptionsHtml(selectedPropertySourceId) {
        var selected = String(selectedPropertySourceId || '').trim().toLowerCase();
        var context = getCurrentMetaFilterContext();
        var hideAcfTechnical = $('#property-preview-hide-acf-technical').is(':checked');
        var withSampleOnly = $('#property-preview-with-sample-only').is(':checked');
        var options = '<option value="">Выбрать meta-ключ...</option>';
        for (var i = 0; i < compositeMetaOptions.length; i++) {
            var item = compositeMetaOptions[i];
            if (!item || typeof item !== 'object') {
                continue;
            }
            var sourceId = String(item.property_source_id || '').trim().toLowerCase();
            if (!sourceId) {
                continue;
            }
            var displayName = String(item.display_name || '').trim();
            var metaKey = String(item.meta_key || '').trim();
            var sourceSetIds = Array.isArray(item.source_set_ids) ? item.source_set_ids : [];
            var sample = String(item.sample_value || '').trim();
            var isCurrentSelected = selected !== '' && sourceId === selected;
            if (!isPropertySourceSelectableForComposite(sourceId, sourceSetIds, metaKey, context) && !isCurrentSelected) {
                continue;
            }
            if (hideAcfTechnical && !!item.is_acf_technical && !isCurrentSelected) {
                continue;
            }
            if (withSampleOnly && !sample && !isCurrentSelected) {
                continue;
            }
            var text = displayName ? (displayName + ' — ' + metaKey) : metaKey;
            if (sample) {
                text += ' | пример: ' + sample;
            }
            options += '<option value="' + $('<div>').text(sourceId).html() + '"' + (selected === sourceId ? ' selected' : '') + '>' +
                $('<div>').text(text).html() +
                '</option>';
        }
        return options;
    }

    function renderCompositeBuilder() {
        var $root = $('#composite-properties-builder');
        if (!$root.length) {
            return;
        }
        $root.empty();

        if (!compositeState.length) {
            $root.append('<div class="text-muted small">Пока не добавлено ни одного комплексного свойства.</div>');
            syncCompositeBuilderToHidden();
            return;
        }

        for (var i = 0; i < compositeState.length; i++) {
            var item = compositeState[i];
            var targetPropertyId = Math.max(0, parseInt(item.target_property_id, 10) || 0);
            var showNameInput = targetPropertyId <= 0;
            var $card = $('<div class="border rounded p-3">');
            $card.append(
                '<div class="d-flex justify-content-between align-items-center mb-2">' +
                '<strong>Комплексное свойство #' + (i + 1) + '</strong>' +
                '<button type="button" class="btn btn-outline-danger btn-sm js-composite-remove" data-prop-index="' + i + '">Удалить</button>' +
                '</div>'
            );

            var identityHtml = '' +
                '<div class="row g-2 mb-2">' +
                '<div class="col-md-3">' +
                '<label class="form-label small">Название свойства CMS</label>' +
                (showNameInput
                    ? '<input type="text" class="form-control form-control-sm js-composite-prop-name" data-prop-index="' + i + '" value="' + $('<div>').text(String(item.name || '')).html() + '" placeholder="SEO-данные">'
                    : '<div class="form-text mt-2">Используется выбранное свойство CMS.</div>') +
                '</div>' +
                '<div class="col-md-3">' +
                '<label class="form-label small">Целевое свойство CMS</label>' +
                '<select class="form-select form-select-sm js-composite-target-property" data-prop-index="' + i + '">' +
                buildCompositePropertyOptionsHtml(targetPropertyId) +
                '</select>' +
                '</div>' +
                '<div class="col-md-2">' +
                '<label class="form-label small">Entity type</label>' +
                '<select class="form-select form-select-sm js-composite-entity-type" data-prop-index="' + i + '">' +
                '<option value="all"' + (item.entity_type === 'all' ? ' selected' : '') + '>all</option>' +
                '<option value="category"' + (item.entity_type === 'category' ? ' selected' : '') + '>category</option>' +
                '<option value="page"' + (item.entity_type === 'page' ? ' selected' : '') + '>page</option>' +
                '</select>' +
                '</div>' +
                '<div class="col-md-2">' +
                '<label class="form-label small">Свойство multiple</label>' +
                '<div class="form-check mt-2">' +
                '<input class="form-check-input js-composite-prop-multiple" type="checkbox" data-prop-index="' + i + '"' + (item.is_multiple ? ' checked' : '') + '>' +
                '<label class="form-check-label small">Да</label>' +
                '</div>' +
                '</div>' +
                '<div class="col-md-2">' +
                '<label class="form-label small">Свойство required</label>' +
                '<div class="form-check mt-2">' +
                '<input class="form-check-input js-composite-prop-required" type="checkbox" data-prop-index="' + i + '"' + (item.is_required ? ' checked' : '') + '>' +
                '<label class="form-check-label small">Да</label>' +
                '</div>' +
                '</div>' +
                '</div>';
            $card.append(identityHtml);

            var $table = $('<table class="table table-sm table-bordered align-middle mb-2">');
            $table.append(
                '<thead><tr>' +
                '<th style="width:18%;">Поле</th>' +
                '<th style="width:12%;">Тип</th>' +
                '<th style="width:18%;">Поле CMS</th>' +
                '<th style="width:24%;">Meta-ключ из файла</th>' +
                '<th style="width:16%;">Или ввести вручную</th>' +
                '<th style="width:10%;">Флаги</th>' +
                '<th style="width:2%;"></th>' +
                '</tr></thead>'
            );
            var $tbody = $('<tbody>');
            var fields = Array.isArray(item.fields) ? item.fields : [];
            for (var j = 0; j < fields.length; j++) {
                var field = fields[j];
                var fieldTitle = String(field.title || '').trim();
                var fieldLabel = String(field.label || '').trim();
                var propertySourceId = String(field.property_source_id || '').trim().toLowerCase();
                var metaKeyManual = String(field.meta_key || '').trim();
                if (!metaKeyManual && propertySourceId.indexOf(':') > 0) {
                    metaKeyManual = propertySourceId.split(':').slice(1).join(':');
                }
                var targetFieldIndex = toCompositeTargetFieldIndex(field.target_field_index);
                if (targetFieldIndex === null) {
                    targetFieldIndex = -1;
                }
                var targetFieldCellHtml = '<td class="text-muted small">Автоматически</td>';
                if (targetPropertyId > 0) {
                    var targetPropertyFields = getCompositeLocalPropertyFields(targetPropertyId);
                    targetFieldCellHtml = '<td>' +
                        '<select class="form-select form-select-sm js-composite-target-field" data-prop-index="' + i + '" data-field-index="' + j + '">' +
                        buildCompositeTargetFieldOptionsHtml(targetPropertyId, targetFieldIndex) +
                        '</select>' +
                        (targetPropertyFields.length ? '' : '<div class="form-text text-warning">У выбранного свойства пока нет полей.</div>') +
                        '</td>';
                }

                var rowHtml = '' +
                    '<tr>' +
                    '<td>' +
                    '<input type="text" class="form-control form-control-sm mb-1 js-composite-field-title" data-prop-index="' + i + '" data-field-index="' + j + '" value="' + $('<div>').text(fieldTitle).html() + '" placeholder="title">' +
                    '<input type="text" class="form-control form-control-sm js-composite-field-label" data-prop-index="' + i + '" data-field-index="' + j + '" value="' + $('<div>').text(fieldLabel).html() + '" placeholder="label">' +
                    '</td>' +
                    '<td>' +
                    '<select class="form-select form-select-sm js-composite-field-type" data-prop-index="' + i + '" data-field-index="' + j + '">' +
                    buildCompositeFieldTypeOptionsHtml(field.type) +
                    '</select>' +
                    '</td>' +
                    targetFieldCellHtml +
                    '<td>' +
                    '<select id="composite-meta-select-' + i + '-' + j + '" class="form-select form-select-sm js-composite-field-meta-select" data-prop-index="' + i + '" data-field-index="' + j + '">' +
                    buildCompositeMetaOptionsHtml(propertySourceId) +
                    '</select>' +
                    '</td>' +
                    '<td>' +
                    '<input type="text" class="form-control form-control-sm js-composite-field-meta-manual" data-prop-index="' + i + '" data-field-index="' + j + '" value="' + $('<div>').text(metaKeyManual).html() + '" placeholder="postmeta:_yoast_wpseo_title">' +
                    '</td>' +
                    '<td>' +
                    '<div class="form-check form-check-inline m-0">' +
                    '<input class="form-check-input js-composite-field-multiple" type="checkbox" data-prop-index="' + i + '" data-field-index="' + j + '"' + (field.multiple ? ' checked' : '') + '>' +
                    '<label class="form-check-label small">multiple</label>' +
                    '</div>' +
                    '<div class="form-check form-check-inline m-0">' +
                    '<input class="form-check-input js-composite-field-required" type="checkbox" data-prop-index="' + i + '" data-field-index="' + j + '"' + (field.required ? ' checked' : '') + '>' +
                    '<label class="form-check-label small">required</label>' +
                    '</div>' +
                    '</td>' +
                    '<td class="text-center">' +
                    '<button type="button" class="btn btn-outline-danger btn-sm js-composite-remove-field" data-prop-index="' + i + '" data-field-index="' + j + '">&times;</button>' +
                    '</td>' +
                    '</tr>';
                $tbody.append(rowHtml);
            }
            $table.append($tbody);
            $card.append($table);
            $card.append('<button type="button" class="btn btn-outline-primary btn-sm js-composite-add-field" data-prop-index="' + i + '"><i class="fas fa-plus"></i> Добавить поле</button>');
            $root.append($card);
        }

        syncCompositeBuilderToHidden();
    }

    function syncCompositeBuilderToHidden() {
        var normalized = [];
        for (var i = 0; i < compositeState.length; i++) {
            var item = compositeState[i];
            if (!item || typeof item !== 'object') {
                continue;
            }
            var targetPropertyId = Math.max(0, parseInt(item.target_property_id, 10) || 0);
            var fields = Array.isArray(item.fields) ? item.fields : [];
            var outFields = [];
            var sourceKindCandidates = {};
            var setSourceCandidates = {};
            var fallbackSourceKind = String(item.source_kind || '').trim().toLowerCase();
            if (fallbackSourceKind !== 'termmeta' && fallbackSourceKind !== 'postmeta') {
                fallbackSourceKind = inferCompositeSourceKind(item.set_source_id);
            }
            for (var j = 0; j < fields.length; j++) {
                var field = fields[j];
                if (!field || typeof field !== 'object') {
                    continue;
                }
                var propertySourceId = String(field.property_source_id || '').trim().toLowerCase();
                var metaKey = String(field.meta_key || '').trim();
                if (!propertySourceId && metaKey) {
                    propertySourceId = findCompositePropertySourceIdByMetaKey(metaKey);
                }
                if (!metaKey && propertySourceId.indexOf(':') > 0) {
                    metaKey = propertySourceId.split(':').slice(1).join(':');
                }
                if (!propertySourceId && metaKey) {
                    propertySourceId = buildCompositePropertySourceId(fallbackSourceKind, metaKey);
                }
                if (!metaKey && propertySourceId.indexOf(':') > 0) {
                    metaKey = propertySourceId.split(':').slice(1).join(':');
                }
                if (!propertySourceId || !metaKey) {
                    continue;
                }
                sourceKindCandidates[inferCompositeSourceKind(propertySourceId)] = true;
                var metaOption = getCompositeMetaOptionBySourceId(propertySourceId);
                if (metaOption && Array.isArray(metaOption.source_set_ids)) {
                    for (var s = 0; s < metaOption.source_set_ids.length; s++) {
                        var setSourceId = String(metaOption.source_set_ids[s] || '').trim().toLowerCase();
                        if (!setSourceId) {
                            continue;
                        }
                        setSourceCandidates[setSourceId] = true;
                    }
                }
                var targetFieldIndex = toCompositeTargetFieldIndex(field.target_field_index);
                if (targetFieldIndex === null || targetPropertyId <= 0) {
                    targetFieldIndex = -1;
                }
                outFields.push({
                    type: String(field.type || 'text').trim().toLowerCase() || 'text',
                    label: String(field.label || '').trim(),
                    title: String(field.title || '').trim(),
                    meta_key: metaKey,
                    property_source_id: propertySourceId,
                    target_field_index: targetFieldIndex,
                    multiple: field.multiple ? 1 : 0,
                    required: field.required ? 1 : 0
                });
            }
            if (!outFields.length) {
                continue;
            }

            var resolvedSourceKind = fallbackSourceKind;
            var hasTermmeta = !!sourceKindCandidates.termmeta;
            var hasPostmeta = !!sourceKindCandidates.postmeta;
            if (hasTermmeta && !hasPostmeta) {
                resolvedSourceKind = 'termmeta';
            } else if (hasPostmeta) {
                resolvedSourceKind = 'postmeta';
            }

            var resolvedSetSourceId = String(item.set_source_id || '').trim().toLowerCase();
            var candidateSetIds = Object.keys(setSourceCandidates);
            if (candidateSetIds.length === 1) {
                resolvedSetSourceId = candidateSetIds[0];
            } else if (candidateSetIds.length > 1) {
                resolvedSetSourceId = '';
            }

            normalized.push({
                source_id: String(item.source_id || '').trim().toLowerCase(),
                name: String(item.name || '').trim(),
                entity_type: normalizeCompositeEntityType(item.entity_type || 'all'),
                set_source_id: resolvedSetSourceId,
                source_kind: resolvedSourceKind,
                target_property_id: targetPropertyId,
                is_multiple: item.is_multiple ? 1 : 0,
                is_required: item.is_required ? 1 : 0,
                fields: outFields
            });
        }

        $('#composite_properties_map').val(JSON.stringify(normalized));
    }

    function pruneCompositeStateByCurrentFilters() {
        if (!Array.isArray(compositeState) || !compositeState.length) {
            return false;
        }
        var context = getCurrentMetaFilterContext();
        var changed = false;
        for (var i = 0; i < compositeState.length; i++) {
            var item = compositeState[i];
            if (!item || typeof item !== 'object' || !Array.isArray(item.fields)) {
                continue;
            }
            for (var j = 0; j < item.fields.length; j++) {
                var field = item.fields[j];
                if (!field || typeof field !== 'object') {
                    continue;
                }
                var sourceId = String(field.property_source_id || '').trim().toLowerCase();
                if (!sourceId) {
                    continue;
                }
                var metaKey = String(field.meta_key || '').trim();
                var metaOption = getCompositeMetaOptionBySourceId(sourceId);
                var sourceSetIds = metaOption && Array.isArray(metaOption.source_set_ids) ? metaOption.source_set_ids : [];
                if (!metaKey && metaOption) {
                    metaKey = String(metaOption.meta_key || '').trim();
                }
                if (!isPropertySourceSelectableForComposite(sourceId, sourceSetIds, metaKey, context)) {
                    field.property_source_id = '';
                    field.meta_key = '';
                    changed = true;
                }
            }
        }
        return changed;
    }

    function getCompositePropertyRef(propIndex) {
        var p = parseInt(propIndex, 10);
        if (isNaN(p) || p < 0 || p >= compositeState.length) {
            return null;
        }
        var item = compositeState[p];
        if (!item || typeof item !== 'object') {
            return null;
        }
        if (!Array.isArray(item.fields)) {
            item.fields = [];
        }
        return { index: p, item: item };
    }

    function getCompositeFieldRef(propIndex, fieldIndex) {
        var propertyRef = getCompositePropertyRef(propIndex);
        if (!propertyRef) {
            return null;
        }
        var f = parseInt(fieldIndex, 10);
        if (isNaN(f) || f < 0 || f >= propertyRef.item.fields.length) {
            return null;
        }
        var field = propertyRef.item.fields[f];
        if (!field || typeof field !== 'object') {
            return null;
        }
        return { propertyRef: propertyRef, index: f, field: field };
    }

    function createCompositeDefaultProperty() {
        return {
            source_id: '',
            name: '',
            entity_type: 'all',
            set_source_id: '',
            source_kind: 'postmeta',
            target_property_id: 0,
            is_multiple: 0,
            is_required: 0,
            fields: [{
                type: 'text',
                label: '',
                title: '',
                meta_key: '',
                property_source_id: '',
                target_field_index: -1,
                multiple: 0,
                required: 0
            }]
        };
    }

    function collectMappingMetrics() {
        var metrics = {
            totalSources: $('.js-source-enabled').length,
            enabledSources: $('.js-source-enabled:checked').length,
            typeRowsEnabled: 0,
            typeRowsExplicit: 0,
            typeRowsAuto: 0
        };

        $('.js-type-map').each(function() {
            var $select = $(this);
            if ($select.prop('disabled')) {
                return;
            }
            metrics.typeRowsEnabled++;
            var localId = parseInt($select.val(), 10);
            if (!isNaN(localId) && localId > 0) {
                metrics.typeRowsExplicit++;
            } else {
                metrics.typeRowsAuto++;
            }
        });

        return metrics;
    }

    function refreshMappingHealth() {
        var metrics = collectMappingMetrics();
        var enabledLabel = String(metrics.enabledSources);
        if (metrics.totalSources > 0) {
            enabledLabel += '/' + String(metrics.totalSources);
        }

        $('#mh-enabled-sources').text(enabledLabel);
        $('#mh-type-explicit').text(String(metrics.typeRowsExplicit));
        $('#mh-type-auto').text(String(metrics.typeRowsAuto));

        var message = 'Сопоставление настроено.';
        if (metrics.enabledSources === 0) {
            message = 'Не выбрано ни одного источника. Импорт данных не выполнится.';
        } else if (metrics.typeRowsAuto > 0) {
            message = 'Часть активных источников использует автосоздание типов категорий.';
        } else if (metrics.typeRowsExplicit === metrics.typeRowsEnabled) {
            message = 'Все активные источники сопоставлены явно.';
        }
        $('#mh-message').text(message);
    }

    function collectPreviewStatusCounters() {
        var counters = {
            auto: 0,
            filtered: 0,
            manual: 0,
            disabled: 0,
            standalone: 0
        };
        $('.js-property-preview-row').each(function() {
            var statusKey = String($(this).attr('data-preview-status') || '').toLowerCase();
            if (!statusKey || !Object.prototype.hasOwnProperty.call(counters, statusKey)) {
                return;
            }
            counters[statusKey]++;
        });
        return counters;
    }

    function buildImportPreflight(stageScope) {
        var scope = String(stageScope || 'all').toLowerCase();
        var metrics = collectMappingMetrics();
        var previewCounters = collectPreviewStatusCounters();
        var blockers = [];
        var warnings = [];

        var fileId = parseInt($('#file_id_package').val(), 10);
        if (isNaN(fileId) || fileId <= 0) {
            blockers.push(t('sys.import_package_not_uploaded', 'Import package has not been uploaded.'));
        }
        if (metrics.enabledSources <= 0) {
            blockers.push(t('sys.import_source_none_selected', 'No source has been selected for import.'));
        }
        if (scope === 'content') {
            var coreCompletedAt = String($('#wizard_core_completed_at').val() || '').trim();
            if (!coreCompletedAt) {
                blockers.push(t('sys.import_stage1_complete_first', 'Finish stage 1 (property structure) first.'));
            }
        }
        if (scope !== 'core') {
            if (metrics.typeRowsEnabled > 0 && metrics.typeRowsExplicit === 0) {
                warnings.push(t('sys.import_types_auto_all', 'Category types will be created automatically for all active sources.'));
            } else if (metrics.typeRowsAuto > 0) {
                warnings.push(t('sys.import_types_auto_partial', 'Some active sources use automatic category type creation.'));
            }
        }
        if (previewCounters.filtered > 0) {
            warnings.push(t('sys.import_meta_filtered_partial', 'Some meta keys are excluded by filters: ') + previewCounters.filtered + '.');
        }
        if (previewCounters.manual > 0) {
            warnings.push(t('sys.import_meta_manual_partial', 'Some meta keys are excluded manually: ') + previewCounters.manual + '.');
        }
        if (previewCounters.disabled > 0) {
            warnings.push('В предпросмотре есть отключённые строки свойств: ' + previewCounters.disabled + '.');
        }

        return {
            blockers: blockers,
            warnings: warnings
        };
    }

    function setWizardChecklistState(stepNo, done) {
        var $icon = $('#wizard-step' + stepNo + '-check');
        var $item = $('#wizard-step' + stepNo + '-item');
        if ($icon.length) {
            $icon.removeClass('fas fa-check-circle text-success far fa-circle text-muted');
            if (done) {
                $icon.addClass('fas fa-check-circle text-success');
            } else {
                $icon.addClass('far fa-circle text-muted');
            }
        }
        if ($item.length) {
            $item.toggleClass('text-success', !!done);
            $item.toggleClass('text-muted', !done);
        }
    }

    function refreshWizardChecklist() {
        var fileId = parseInt($('#file_id_package').val(), 10);
        var packageReady = !isNaN(fileId) && fileId > 0;
        var metrics = collectMappingMetrics();
        var mappingReady = metrics.enabledSources > 0;
        var coreDone = String($('#wizard_core_completed_at').val() || '').trim() !== '';
        var contentDone = String($('#wizard_content_completed_at').val() || '').trim() !== '';

        setWizardChecklistState(1, packageReady);
        setWizardChecklistState(2, packageReady && mappingReady);
        setWizardChecklistState(3, coreDone);
        setWizardChecklistState(4, contentDone);
    }

    function refreshWizardFlowUI() {
        var fileId = parseInt($('#file_id_package').val(), 10);
        var packageReady = !isNaN(fileId) && fileId > 0;
        var coreDone = String($('#wizard_core_completed_at').val() || '').trim() !== '';
        var metrics = collectMappingMetrics();
        var mappingReady = metrics.enabledSources > 0;
        var canRunCore = packageReady && mappingReady;

        var $step2Section = $('#wizard-step2-section');
        var $step2Hint = $('#wizard-step2-lock-hint');
        if ($step2Section.length) {
            $step2Section.toggle(packageReady);
        }
        if ($step2Hint.length) {
            $step2Hint.toggle(!packageReady);
        }

        var $step3Hint = $('#wizard-step3-lock-hint');
        if ($step3Hint.length) {
            if (!packageReady) {
                $step3Hint.text('Шаг 3 будет доступен после выбора пакета импорта для этого профиля.');
            } else if (!mappingReady) {
                $step3Hint.text('Шаг 3 будет доступен после настройки хотя бы одного активного источника в шаге 2.');
            }
            $step3Hint.toggle(!canRunCore);
        }
        $('#import-wizard-panel').toggle(canRunCore);

        $('#wizard-content-stage-col').toggle(canRunCore && coreDone);
        $('#wizard-step4-lock-hint').toggle(canRunCore && !coreDone);
        refreshWizardChecklist();
    }

    function refreshWizardStatusUI() {
        var $coreBadge = $('#wizard-core-status-badge');
        var $coreText = $('#wizard-core-status-text');
        var $contentBadge = $('#wizard-content-status-badge');
        var $contentText = $('#wizard-content-status-text');
        if (!$coreBadge.length || !$contentBadge.length) {
            return;
        }

        var coreAt = String($('#wizard_core_completed_at').val() || '').trim();
        var contentAt = String($('#wizard_content_completed_at').val() || '').trim();
        var coreDone = coreAt !== '';
        var contentDone = contentAt !== '';

        $coreBadge
            .toggleClass('bg-success', coreDone)
            .toggleClass('bg-secondary', !coreDone)
            .text(coreDone ? 'завершён' : 'не запущен');
        $coreText.text(coreDone
            ? ('Завершён: ' + coreAt)
            : 'Импортирует: типы свойств, наборы свойств, свойства и их связи.');

        $contentBadge
            .toggleClass('bg-success', contentDone)
            .toggleClass('bg-secondary', !contentDone)
            .text(contentDone ? 'завершён' : 'не запущен');
        if (contentDone) {
            $contentText.text('Завершён: ' + contentAt);
        } else if (coreDone) {
            $contentText.text('Доступен к запуску: загрузит типы категорий, связи типов с наборами, категории, страницы и значения свойств.');
        } else {
            $contentText.text('Будет доступен после завершения этапа 1.');
        }

        $('#run-import-content-btn').prop('disabled', !coreDone);
        refreshWizardFlowUI();
    }

    function refreshPropertyPreview() {
        var $rows = $('.js-property-preview-row');
        if ($rows.length === 0) {
            $('#meta-filter-allowed-count').text('0');
            $('#meta-filter-blocked-count').text('0');
            $('#meta-filter-private-hint').hide();
            $('#property-preview-status-auto').text('0');
            $('#property-preview-status-filtered').text('0');
            $('#property-preview-status-manual').text('0');
            $('#property-preview-status-disabled').text('0');
            $('#property-preview-status-standalone').text('0');
            return;
        }

        var showActiveOnly = $('#property-preview-active-only').is(':checked');
        var hideAcfTechnical = $('#property-preview-hide-acf-technical').is(':checked');
        var withSampleOnly = $('#property-preview-with-sample-only').is(':checked');
        var filterContext = getCurrentMetaFilterContext();
        var strictCompositeMode = Array.isArray(compositeState) && compositeState.length > 0;

        var visibleCount = 0;
        var statusCounters = {
            auto: 0,
            filtered: 0,
            manual: 0,
            disabled: 0,
            standalone: 0
        };
        var metaAllowedCount = 0;
        var metaBlockedCount = 0;
        var metaPrivateBlockedCount = 0;

        $rows.each(function() {
            var $row = $(this);
            var sourceSetIds = parseJsonArrayAttr($row.attr('data-source-set-ids'), true);
            var propertySourceId = String($row.attr('data-property-source-id') || '').trim().toLowerCase();
            var rawMetaKey = String($row.attr('data-meta-key') || '').trim();
            if (!rawMetaKey) {
                rawMetaKey = extractMetaKeyFromSourceId(propertySourceId);
            }
            var importDecision = evaluateImportStateForPropertySource(propertySourceId, sourceSetIds, rawMetaKey, filterContext);
            var metaFilterDecision = importDecision.metaFilterDecision;
            if (metaFilterDecision && metaFilterDecision.allowed) {
                metaAllowedCount++;
            } else {
                metaBlockedCount++;
                if (String((metaFilterDecision && metaFilterDecision.reason) || '').indexOf('Системный ключ') === 0) {
                    metaPrivateBlockedCount++;
                }
            }

            var statusKey = importDecision.statusKey;
            var statusDetail = importDecision.statusDetail;
            if (strictCompositeMode && (statusKey === 'auto' || statusKey === 'standalone') && !isPropertySourceMappedInComposite(propertySourceId)) {
                statusKey = 'manual';
                statusDetail = 'Не сопоставлено в Конструкторе комплексных свойств';
            }
            statusCounters[statusKey] = (statusCounters[statusKey] || 0) + 1;

            resetPreviewRowClasses($row);
            if (statusKey === 'auto') {
                $row.addClass('table-success');
            } else if (statusKey === 'disabled') {
                $row.addClass('table-secondary opacity-75');
            } else if (statusKey === 'filtered') {
                $row.addClass('table-danger');
            } else if (statusKey === 'manual') {
                $row.addClass('table-warning');
            } else if (statusKey === 'standalone') {
                $row.addClass('table-info');
            }
            $row.attr('data-preview-status', statusKey);
            renderPreviewManualControl($row.find('.js-preview-manual-control'), propertySourceId, isPropertySourceManuallyExcluded(propertySourceId));
            renderPreviewStatusCell($row.find('.js-preview-status'), statusKey, statusDetail);

            var isAcfTechnical = String($row.attr('data-is-acf-technical') || '') === '1';
            var hasSample = String($row.attr('data-has-sample') || '') === '1';
            var isVisible = !(showActiveOnly && statusKey === 'disabled');
            if (isVisible && hideAcfTechnical && isAcfTechnical) {
                isVisible = false;
            }
            if (isVisible && withSampleOnly && !hasSample) {
                isVisible = false;
            }
            $row.toggle(isVisible);
            if (isVisible) {
                visibleCount++;
            }
        });

        $('#property-preview-visible-count').text(String(visibleCount));
        $('#property-preview-status-auto').text(String(statusCounters.auto || 0));
        $('#property-preview-status-filtered').text(String(statusCounters.filtered || 0));
        $('#property-preview-status-manual').text(String(statusCounters.manual || 0));
        $('#property-preview-status-disabled').text(String(statusCounters.disabled || 0));
        $('#property-preview-status-standalone').text(String(statusCounters.standalone || 0));
        $('#meta-filter-allowed-count').text(String(metaAllowedCount));
        $('#meta-filter-blocked-count').text(String(metaBlockedCount));
        $('#meta-filter-private-hint').toggle(metaPrivateBlockedCount > 0);
        $('#property-preview-empty-hint').toggle(visibleCount === 0);
    }

    function refreshMetaDrivenUi() {
        refreshPropertyPreview();
        pruneCompositeStateByCurrentFilters();
        renderCompositeBuilder();
    }

    function buildMapLines(selector) {
        var rows = [];
        $(selector).each(function() {
            var $select = $(this);
            if ($select.prop('disabled')) {
                return;
            }
            var sourceId = String($select.data('source-id') || '').trim().toLowerCase();
            var localId = parseInt($select.val(), 10);
            if (!sourceId || isNaN(localId) || localId <= 0) {
                return;
            }
            rows.push(sourceId + '=' + localId);
        });
        return rows.join('\n');
    }

    function updateSourceDependentRows() {
        if ($('.js-source-enabled').length === 0) {
            return;
        }

        var enabledMap = {};
        $('.js-source-enabled').each(function() {
            var sourceId = String($(this).data('source-id') || '').trim().toLowerCase();
            if (!sourceId) {
                return;
            }
            enabledMap[sourceId] = $(this).is(':checked');
        });

        $('.js-source-dependent-row').each(function() {
            var $row = $(this);
            var sourceId = String($row.data('source-id') || '').trim().toLowerCase();
            if (!sourceId) {
                return;
            }
            var isEnabled = !!enabledMap[sourceId];
            $row.toggleClass('table-secondary', !isEnabled);
            $row.toggleClass('opacity-75', !isEnabled);
            $row.find('select.js-type-map').prop('disabled', !isEnabled);
            $row.find('.js-row-disabled-hint').toggle(!isEnabled);
        });
    }

    function syncDynamicMappings() {
        updateSourceDependentRows();

        var hasTypeSelects = $('.js-type-map').length > 0;
        var hasSourceChecks = $('.js-source-enabled').length > 0;

        if (hasTypeSelects) {
            var typeMapText = buildMapLines('.js-type-map');
            $('#source_type_map').val(typeMapText);
        }
        $('#source_set_map').val('');

        if (hasSourceChecks) {
            var enabled = [];
            $('.js-source-enabled:checked').each(function() {
                var sourceId = String($(this).data('source-id') || '').trim().toLowerCase();
                if (sourceId) {
                    enabled.push(sourceId);
                }
            });
            var normalized = normalizeLineList(enabled.join('\n')).join('\n');
            $('#allowed_source_ids').val(normalized);
        }

        syncCompositeBuilderToHidden();
        refreshMappingHealth();
        refreshMetaDrivenUi();
        refreshWizardFlowUI();
    }

    function toggleTestModeLimit() {
        if ($('#test_mode').is(':checked')) {
            $('#test-mode-limit-group').show();
        } else {
            $('#test-mode-limit-group').hide();
        }
    }

    function appendLog($log, text) {
        if (!text) {
            return;
        }
        $log.append(document.createTextNode(text));
        $log.scrollTop($log[0].scrollHeight);
    }

    $('#test_mode').on('change', toggleTestModeLimit);
    toggleTestModeLimit();

    $(document).on('change', '.js-type-map', syncDynamicMappings);
    $(document).on('change', '.js-source-enabled', syncDynamicMappings);
    function refreshPreviewAndCompositeBuilder() {
        refreshPropertyPreview();
        renderCompositeBuilder();
    }

    $('#property-preview-active-only').on('change', refreshPreviewAndCompositeBuilder);
    $('#property-preview-hide-acf-technical').on('change', refreshPreviewAndCompositeBuilder);
    $('#property-preview-with-sample-only').on('change', refreshPreviewAndCompositeBuilder);
    $('#include_private_meta_keys').on('change', refreshMetaDrivenUi);
    $('#meta_include_patterns, #meta_exclude_patterns').on('input', refreshMetaDrivenUi);

    $(document).on('click', '.js-preview-toggle-manual-exclude', function() {
        var sourceId = String($(this).data('property-source-id') || '').trim().toLowerCase();
        if (!sourceId) {
            return;
        }
        if (manualExcludedSourceIds[sourceId]) {
            delete manualExcludedSourceIds[sourceId];
        } else {
            manualExcludedSourceIds[sourceId] = true;
        }
        syncManualExcludedSourceIdsInput();
        refreshMetaDrivenUi();
    });

    $('#composite-add-btn').on('click', function() {
        compositeState.push(createCompositeDefaultProperty());
        renderCompositeBuilder();
    });

    $('#composite-clear-btn').on('click', function() {
        if (!compositeState.length) {
            return;
        }
        if (!confirm('Удалить все комплексные свойства из конструктора?')) {
            return;
        }
        compositeState = [];
        renderCompositeBuilder();
    });

    $(document).on('click', '.js-composite-remove', function() {
        var propertyRef = getCompositePropertyRef($(this).data('prop-index'));
        if (!propertyRef) {
            return;
        }
        compositeState.splice(propertyRef.index, 1);
        renderCompositeBuilder();
    });

    $(document).on('input', '.js-composite-prop-name', function() {
        var propertyRef = getCompositePropertyRef($(this).data('prop-index'));
        if (!propertyRef) {
            return;
        }
        propertyRef.item.name = String($(this).val() || '').trim();
        syncCompositeBuilderToHidden();
    });

    $(document).on('change', '.js-composite-target-property', function() {
        var propertyRef = getCompositePropertyRef($(this).data('prop-index'));
        if (!propertyRef) {
            return;
        }
        var targetPropertyId = Math.max(0, parseInt($(this).val(), 10) || 0);
        propertyRef.item.target_property_id = targetPropertyId;
        if (targetPropertyId <= 0) {
            var propFields = Array.isArray(propertyRef.item.fields) ? propertyRef.item.fields : [];
            for (var i = 0; i < propFields.length; i++) {
                if (!propFields[i] || typeof propFields[i] !== 'object') {
                    continue;
                }
                propFields[i].target_field_index = -1;
            }
        }
        renderCompositeBuilder();
    });

    $(document).on('change', '.js-composite-entity-type', function() {
        var propertyRef = getCompositePropertyRef($(this).data('prop-index'));
        if (!propertyRef) {
            return;
        }
        propertyRef.item.entity_type = normalizeCompositeEntityType($(this).val());
        syncCompositeBuilderToHidden();
    });

    $(document).on('change', '.js-composite-prop-multiple', function() {
        var propertyRef = getCompositePropertyRef($(this).data('prop-index'));
        if (!propertyRef) {
            return;
        }
        propertyRef.item.is_multiple = $(this).is(':checked') ? 1 : 0;
        syncCompositeBuilderToHidden();
    });

    $(document).on('change', '.js-composite-prop-required', function() {
        var propertyRef = getCompositePropertyRef($(this).data('prop-index'));
        if (!propertyRef) {
            return;
        }
        propertyRef.item.is_required = $(this).is(':checked') ? 1 : 0;
        syncCompositeBuilderToHidden();
    });

    $(document).on('click', '.js-composite-add-field', function() {
        var propertyRef = getCompositePropertyRef($(this).data('prop-index'));
        if (!propertyRef) {
            return;
        }
        propertyRef.item.fields.push({
            type: 'text',
            label: '',
            title: '',
            meta_key: '',
            property_source_id: '',
            target_field_index: -1,
            multiple: 0,
            required: 0
        });
        renderCompositeBuilder();
    });

    $(document).on('click', '.js-composite-remove-field', function() {
        var fieldRef = getCompositeFieldRef($(this).data('prop-index'), $(this).data('field-index'));
        if (!fieldRef) {
            return;
        }
        fieldRef.propertyRef.item.fields.splice(fieldRef.index, 1);
        renderCompositeBuilder();
    });

    $(document).on('input', '.js-composite-field-title', function() {
        var fieldRef = getCompositeFieldRef($(this).data('prop-index'), $(this).data('field-index'));
        if (!fieldRef) {
            return;
        }
        fieldRef.field.title = String($(this).val() || '').trim();
        syncCompositeBuilderToHidden();
    });

    $(document).on('input', '.js-composite-field-label', function() {
        var fieldRef = getCompositeFieldRef($(this).data('prop-index'), $(this).data('field-index'));
        if (!fieldRef) {
            return;
        }
        fieldRef.field.label = String($(this).val() || '').trim();
        syncCompositeBuilderToHidden();
    });

    $(document).on('change', '.js-composite-field-type', function() {
        var fieldRef = getCompositeFieldRef($(this).data('prop-index'), $(this).data('field-index'));
        if (!fieldRef) {
            return;
        }
        var fieldType = String($(this).val() || '').trim().toLowerCase();
        if (!fieldType) {
            fieldType = 'text';
        }
        fieldRef.field.type = fieldType;
        syncCompositeBuilderToHidden();
    });

    $(document).on('change', '.js-composite-target-field', function() {
        var fieldRef = getCompositeFieldRef($(this).data('prop-index'), $(this).data('field-index'));
        if (!fieldRef) {
            return;
        }
        var targetFieldIndex = toCompositeTargetFieldIndex($(this).val());
        if (targetFieldIndex === null) {
            targetFieldIndex = -1;
        }
        fieldRef.field.target_field_index = targetFieldIndex;
        syncCompositeBuilderToHidden();
    });

    $(document).on('change', '.js-composite-field-meta-select', function() {
        var fieldRef = getCompositeFieldRef($(this).data('prop-index'), $(this).data('field-index'));
        if (!fieldRef) {
            return;
        }
        var sourceId = String($(this).val() || '').trim().toLowerCase();
        fieldRef.field.property_source_id = sourceId;
        if (sourceId.indexOf(':') > 0) {
            fieldRef.field.meta_key = sourceId.split(':').slice(1).join(':');
        } else {
            fieldRef.field.meta_key = '';
        }
        renderCompositeBuilder();
    });

    $(document).on('input', '.js-composite-field-meta-manual', function() {
        var fieldRef = getCompositeFieldRef($(this).data('prop-index'), $(this).data('field-index'));
        if (!fieldRef) {
            return;
        }
        var metaInput = String($(this).val() || '').trim();
        var propertySourceId = '';
        if (metaInput) {
            propertySourceId = findCompositePropertySourceIdByMetaKey(metaInput);
            if (!propertySourceId && (metaInput.toLowerCase().indexOf('postmeta:') === 0 || metaInput.toLowerCase().indexOf('termmeta:') === 0)) {
                propertySourceId = metaInput.toLowerCase();
            }
            if (!propertySourceId) {
                propertySourceId = buildCompositePropertySourceId(
                    inferCompositeSourceKind(fieldRef.field.property_source_id || fieldRef.propertyRef.item.source_kind || ''),
                    metaInput
                );
            }
        }
        fieldRef.field.meta_key = metaInput.indexOf(':') > 0 ? metaInput.split(':').slice(1).join(':') : metaInput;
        fieldRef.field.property_source_id = propertySourceId;
        syncCompositeBuilderToHidden();
    });

    $(document).on('change', '.js-composite-field-multiple', function() {
        var fieldRef = getCompositeFieldRef($(this).data('prop-index'), $(this).data('field-index'));
        if (!fieldRef) {
            return;
        }
        fieldRef.field.multiple = $(this).is(':checked') ? 1 : 0;
        syncCompositeBuilderToHidden();
    });

    $(document).on('change', '.js-composite-field-required', function() {
        var fieldRef = getCompositeFieldRef($(this).data('prop-index'), $(this).data('field-index'));
        if (!fieldRef) {
            return;
        }
        fieldRef.field.required = $(this).is(':checked') ? 1 : 0;
        syncCompositeBuilderToHidden();
    });

    manualExcludedSourceIds = parseManualExcludedSourceIdsMapFromInput();
    syncManualExcludedSourceIdsInput();
    compositeState = parseCompositeStateFromInput();
    renderCompositeBuilder();
    syncDynamicMappings();
    refreshWizardStatusUI();
    refreshWizardFlowUI();

    $('#import_package_file').on('change', function() {
        $('#file_id_package').val('0');
        $('#wizard_core_completed_at').val('');
        $('#wizard_content_completed_at').val('');
        $('.js-run-import-btn').prop('disabled', true);
        $('#log-output').text('');
        $('#import-status').text('Выбран новый пакет. Сначала сохраните профиль.').css('color', 'orange');
        refreshWizardStatusUI();
        refreshWizardFlowUI();
    });

    $('#save-settings-btn').on('click', function() {
        var $btn = $(this);
        var $status = $('#import-status');
        var $log = $('#log-output');
        var originalText = $btn.html();

        syncDynamicMappings();

        var maxBytes = parseInt($('#max-file-size-bytes').val() || 0, 10);
        var maxHuman = $('#max-file-size-human').val();
        var packageInput = $('#import_package_file').get(0);
        var packageFile = packageInput && packageInput.files ? packageInput.files[0] : null;
        if (maxBytes > 0 && packageFile && packageFile.size > maxBytes) {
            var fileSizeHuman = (packageFile.size / 1024 / 1024).toFixed(1) + ' MB';
            var errorMessage = 'Файл ' + packageFile.name + ' (' + fileSizeHuman + ') превышает лимит ' + maxHuman + '.';
            $log.text('ОШИБКА: ' + errorMessage);
            $status.text('Ошибка').css('color', 'red');
            alert(errorMessage);
            return;
        }

        var form = $('#import-settings-form')[0];
        var formData = new FormData(form);

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Сохранение...');
        $status.text('');
        $log.text('');

        $.ajax({
            url: '/admin/save_import_wp',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'text',
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (!evt.lengthComputable) {
                        return;
                    }
                    var percent = Math.round((evt.loaded / evt.total) * 100);
                    $log.text('Загрузка: ' + percent + '%');
                    if (percent === 100) {
                        $log.text('Файл загружен. Идёт обработка на сервере...');
                    }
                }, false);
                return xhr;
            },
            success: function(raw) {
                var parsed;
                try {
                    parsed = parseJsonResponse(raw);
                } catch (e) {
                    appendLog($log, '\nОШИБКА: невалидный JSON в ответе: ' + e.message + '\n');
                    appendLog($log, compactText(raw, 1200) + '\n');
                    $status.text('Ошибка').css('color', 'red');
                    return;
                }

                var response = parsed.payload || {};
                if (parsed.extra) {
                    appendLog($log, parsed.extra + '\n');
                }
                if (response.success) {
                    appendLog($log, '\n' + t('sys.profile_saved', 'Profile saved.') + '\n');
                    $status.text(response.message || t('sys.saved', 'Saved')).css('color', 'green');
                    window.location.href = '/admin/edit_import_wp/id/' + response.job_id;
                    return;
                }

                appendLog($log, '\n' + t('sys.error', 'Error') + ': ' + (response.message || t('sys.profile_save_failed', 'Failed to save profile')) + '\n');
                $status.text(response.message || t('sys.error', 'Error')).css('color', 'red');
            },
            error: function(xhr, status, error) {
                appendLog($log, '\n' + t('sys.ajax_error', 'AJAX error') + ': ' + error + '\n');
                appendLog($log, compactText(xhr && xhr.responseText ? xhr.responseText : '', 1200) + '\n');
                $status.text(t('sys.ajax_error', 'AJAX error')).css('color', 'red');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    $('.js-run-import-btn').on('click', function() {
        var $btn = $(this);
        var $allRunButtons = $('.js-run-import-btn');
        var $log = $('#log-output');
        var $status = $('#import-status');
        var jobId = $('#job_id').val();
        var originalText = $btn.html();
        var stageScope = String($btn.data('stage-scope') || 'all').toLowerCase();
        var stageTitle = String($btn.data('stage-title') || 'Импорт');
        var stageRestart = parseInt($btn.data('stage-restart'), 10);
        if (isNaN(stageRestart) || (stageRestart !== 0 && stageRestart !== 1)) {
            stageRestart = 1;
        }

        if (!jobId || jobId === '0') {
            alert('Сначала сохраните профиль.');
            return;
        }

        syncDynamicMappings();
        refreshWizardStatusUI();
        var preflight = buildImportPreflight(stageScope);
        if (preflight.blockers.length > 0) {
            alert('Импорт нельзя запустить:\n- ' + preflight.blockers.join('\n- '));
            return;
        }

        var confirmMessage = 'Запустить ' + stageTitle + '?';
        if (preflight.warnings.length > 0) {
            confirmMessage = 'Перед запуском есть предупреждения:\n- ' + preflight.warnings.join('\n- ') + '\n\nПродолжить: ' + stageTitle + '?';
        }
        if (!confirm(confirmMessage)) {
            return;
        }

        $allRunButtons.prop('disabled', true);
        $btn.html('<i class="fas fa-spinner fa-spin"></i> ' + ($btn.data('lang-running') || 'Выполняется...'));
        $status.text(stageTitle + ': запуск...').css('color', '#0d6efd');
        $log.text('');

        var testMode = $('#test_mode').is(':checked') ? '1' : '0';
        var testModeLimit = parseInt($('#test_mode_limit').val(), 10);
        if (isNaN(testModeLimit) || testModeLimit <= 0) {
            testModeLimit = 5;
        }

        var stepChunkRows = 40;
        var minStepChunkRows = 10;
        var maxStepChunkRows = 160;
        var isFirstStep = true;
        var retryCount = 0;
        var maxRetries = 6;
        var stepIndex = 0;
        var requestDelayMs = 300;

        function stopWithError(message) {
            appendLog($log, '\nОШИБКА: ' + message + '\n');
            $status.text(($btn.data('lang-error') || 'Ошибка') + '.').css('color', 'red');
            $btn.html(originalText);
            $allRunButtons.prop('disabled', false);
            refreshWizardStatusUI();
        }

        function getRetryDelayMs(httpStatus, currentRetry) {
            var base = (httpStatus === 503) ? 5000 : 2500;
            var jitter = Math.floor(Math.random() * 700);
            return Math.min(30000, (base * currentRetry) + jitter);
        }

        function buildAjaxErrorDetails(xhr, status, error) {
            var parts = [];
            if (xhr && typeof xhr.status === 'number' && xhr.status > 0) {
                parts.push('HTTP ' + xhr.status);
            }
            if (xhr && xhr.statusText) {
                parts.push('statusText="' + compactText(xhr.statusText, 100) + '"');
            }
            if (status) {
                parts.push('textStatus="' + compactText(status, 100) + '"');
            }
            if (error) {
                parts.push('error="' + compactText(error, 140) + '"');
            }
            if (xhr && xhr.responseText) {
                parts.push('response="' + compactText(xhr.responseText, 600) + '"');
            }
            return parts.join(', ');
        }

        function runNextStep(fixedStepNumber) {
            var currentStep = typeof fixedStepNumber === 'number' ? fixedStepNumber : (stepIndex + 1);
            stepIndex = currentStep;
            var startedAt = Date.now();

            $.ajax({
                url: '/admin/run_wp_import',
                type: 'POST',
                dataType: 'text',
                data: {
                    job_id: jobId,
                    step_mode: 1,
                    step_restart: isFirstStep ? stageRestart : 0,
                    step_scope: stageScope,
                    step_seq: currentStep,
                    step_chunk_rows: stepChunkRows,
                    test_mode: testMode,
                    test_mode_limit: testModeLimit
                },
                success: function(raw) {
                    isFirstStep = false;

                    var parsed;
                    try {
                        parsed = parseJsonResponse(raw);
                    } catch (e) {
                        retryCount++;
                        if (retryCount <= maxRetries) {
                            appendLog($log, '\nПРЕДУПРЕЖДЕНИЕ: не удалось разобрать JSON шага (' + e.message + '), повтор ' + retryCount + '/' + maxRetries + '...\n');
                            setTimeout(function() {
                                runNextStep(currentStep);
                            }, getRetryDelayMs(0, retryCount));
                            return;
                        }
                        stopWithError('Невалидный JSON ответа шага: ' + e.message);
                        return;
                    }

                    var response = parsed.payload || {};
                    if (parsed.extra) {
                        appendLog($log, parsed.extra + '\n');
                    }
                    appendLog($log, response.log || '');

                    var durationMs = parseInt(response && response.duration_ms ? response.duration_ms : 0, 10);
                    if (!isNaN(durationMs) && durationMs > 0) {
                        if (durationMs >= 16000 && stepChunkRows > minStepChunkRows) {
                            stepChunkRows = Math.max(minStepChunkRows, Math.floor(stepChunkRows * 0.8));
                            appendLog($log, '\nИНФО: уменьшаю размер чанка до ' + stepChunkRows + ' (' + durationMs + ' мс)\n');
                        } else if (durationMs <= 5000 && stepChunkRows < maxStepChunkRows) {
                            stepChunkRows = Math.min(maxStepChunkRows, Math.ceil(stepChunkRows * 1.1));
                            appendLog($log, '\nИНФО: увеличиваю размер чанка до ' + stepChunkRows + ' (' + durationMs + ' мс)\n');
                        }
                    }

                    if (!response || response.success !== true) {
                        if (response && response.error_code === 'IMPORT_LOCKED') {
                            retryCount++;
                            if (retryCount <= maxRetries) {
                                appendLog($log, '\nПРЕДУПРЕЖДЕНИЕ: профиль занят другим процессом, повтор ' + retryCount + '/' + maxRetries + '...\n');
                                setTimeout(function() {
                                    runNextStep(currentStep);
                                }, getRetryDelayMs(0, retryCount));
                                return;
                            }
                        }
                        stopWithError(response && response.message ? response.message : 'Неожиданный ответ сервера');
                        return;
                    }

                    retryCount = 0;
                    if (response.done) {
                        if (Object.prototype.hasOwnProperty.call(response, 'wizard_core_completed_at')) {
                            $('#wizard_core_completed_at').val(String(response.wizard_core_completed_at || '').trim());
                        }
                        if (Object.prototype.hasOwnProperty.call(response, 'wizard_content_completed_at')) {
                            $('#wizard_content_completed_at').val(String(response.wizard_content_completed_at || '').trim());
                        }
                        refreshWizardStatusUI();
                        $status.text($btn.data('lang-done') || 'Готово').css('color', 'green');
                        $btn.html(originalText);
                        $allRunButtons.prop('disabled', false);
                        refreshWizardStatusUI();
                        return;
                    }

                    $status.text(stageTitle + ': выполняется...').css('color', '#0d6efd');
                    setTimeout(function() {
                        runNextStep();
                    }, requestDelayMs);
                },
                error: function(xhr, status, error) {
                    isFirstStep = false;
                    retryCount++;
                    var elapsedMs = Date.now() - startedAt;
                    var details = buildAjaxErrorDetails(xhr, status, error);
                    var httpStatus = (xhr && typeof xhr.status === 'number') ? xhr.status : 0;

                    if (retryCount <= maxRetries) {
                        appendLog(
                            $log,
                            '\nПРЕДУПРЕЖДЕНИЕ: шаг #' + currentStep +
                            ' завершился с ошибкой через ' + elapsedMs + ' мс (' + details + '), повтор ' +
                            retryCount + '/' + maxRetries + '...\n'
                        );
                        setTimeout(function() {
                            runNextStep(currentStep);
                        }, getRetryDelayMs(httpStatus, retryCount));
                        return;
                    }

                    stopWithError('AJAX завершился ошибкой после повторов: ' + details);
                }
            });
        }

        runNextStep();
    });
});
