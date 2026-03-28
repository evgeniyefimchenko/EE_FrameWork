<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\EntityPublicUrlService;
use classes\system\FileSystem;
use classes\system\PropertyFieldContract;
use classes\system\WordpressImporter;

class ModelPublicCatalog {

    private array $propertyIdCache = [];

    public function getPagePayload(int $pageId, string $languageCode = ENV_DEF_LANG): ?array {
        $basePayload = EntityPublicUrlService::getEntityViewPayload('page', $pageId, $languageCode);
        if (!$basePayload) {
            return null;
        }

        $pageId = (int) ($basePayload['entity_id'] ?? 0);
        $languageCode = (string) ($basePayload['language_code'] ?? ENV_DEF_LANG);
        $pageRow = SafeMySQL::gi()->getRow(
            'SELECT page_id, title, category_id, parent_page_id, language_code, short_description, description, status, slug
             FROM ?n
             WHERE page_id = ?i
             LIMIT 1',
            Constants::PAGES_TABLE,
            $pageId
        );
        if (!$pageRow) {
            return null;
        }

        $categoryRow = SafeMySQL::gi()->getRow(
            'SELECT category_id, title, parent_id, slug
             FROM ?n
             WHERE category_id = ?i
             LIMIT 1',
            Constants::CATEGORIES_TABLE,
            (int) ($pageRow['category_id'] ?? 0)
        ) ?: [];

        $propertyMap = $this->getEntityPropertyMap('page', $pageId, $languageCode);
        $gallery = $this->extractGalleryItems($propertyMap['Фотографии объекта'] ?? null);
        $roomOffer = $this->extractRoomOffer($propertyMap['Номера и цены'] ?? null);
        $phones = $this->extractPhoneEntries($propertyMap['Телефоны'] ?? null);
        $owner = $this->getPageOwner($pageId);

        $email = $this->extractScalarValue($propertyMap['Электронная почта'] ?? null);
        if ($email === '' && !empty($owner['email'])) {
            $email = trim((string) $owner['email']);
        }

        $website = $this->normalizeWebsite($this->extractScalarValue($propertyMap['Сайт'] ?? null));
        $address = $this->extractScalarValue($propertyMap['Адрес'] ?? null);
        $distanceToSea = $this->extractScalarValue($propertyMap['Удаленность от моря'] ?? null);
        $map = $this->extractMapData($propertyMap['Карта'] ?? null);

        $objectType = $this->extractChoiceSummary($propertyMap['Тип объекта'] ?? null);
        $operationMode = $this->extractChoiceSummary($propertyMap['Характер функционирования объекта'] ?? null);
        $childrenPolicy = $this->extractChoiceSummary($propertyMap['Дети'] ?? null);
        $services = $this->extractChoiceList($propertyMap['Предоставляемые услуги'] ?? null);
        $food = $this->extractChoiceList($propertyMap['Питание'] ?? null);
        $transfer = $this->extractChoiceList($propertyMap['Трансфер'] ?? null);
        $priceComment = $this->normalizeRichTextHtml($this->extractScalarValue($propertyMap['Комментарий к ценам'] ?? null));

        $summary = $this->resolveSummary(
            (string) ($pageRow['short_description'] ?? ''),
            (string) ($pageRow['description'] ?? '')
        );
        $descriptionHtml = $this->normalizeRichTextHtml((string) ($pageRow['description'] ?? ''));
        $metaDescription = $summary !== '' ? $summary : $this->truncateText($this->extractPlainText($descriptionHtml), 180);

        $facts = array_values(array_filter([
            $this->makeFact($this->langLabel('sys.type', 'Type'), $objectType),
            $this->makeFact($this->langLabel('sys.public_operation_mode', 'Operation mode'), $operationMode),
            $this->makeFact($this->langLabel('sys.public_children_policy', 'Children'), $childrenPolicy),
            $this->makeFact($this->langLabel('sys.public_distance_to_sea', 'Distance to the sea'), $distanceToSea !== '' ? trim($distanceToSea) . ' м' : ''),
        ]));

        return array_merge($basePayload, [
            'view_type' => 'page',
            'summary' => $summary,
            'meta_description' => $metaDescription !== '' ? $metaDescription : (string) ($basePayload['meta_description'] ?? ''),
            'description_html' => $descriptionHtml,
            'gallery' => $gallery,
            'gallery_preview' => array_slice($gallery, 0, 8),
            'resort' => [
                'title' => trim((string) ($categoryRow['title'] ?? '')),
                'url' => !empty($categoryRow['category_id'])
                    ? EntityPublicUrlService::buildEntityUrl('category', (int) $categoryRow['category_id'], $languageCode)
                    : '',
            ],
            'facts' => $facts,
            'services' => $services,
            'food' => $food,
            'transfer' => $transfer,
            'contacts' => [
                'phones' => $phones,
                'email' => $email,
                'email_href' => $email !== '' ? ('mailto:' . $email) : '',
                'website' => $website,
                'address' => $address,
            ],
            'location' => [
                'distance_to_sea' => $distanceToSea,
                'map' => $map,
            ],
            'room_offer' => $roomOffer,
            'price_comment_html' => $priceComment,
            'owner' => $owner,
            'language_links' => $this->filterLanguageLinks((array) ($basePayload['alternate_links'] ?? [])),
            'related_pages' => $this->getPageCardsByCategory((int) ($pageRow['category_id'] ?? 0), $languageCode, $pageId, 8),
        ]);
    }

    public function getCategoryPayload(int $categoryId, string $languageCode = ENV_DEF_LANG): ?array {
        $basePayload = EntityPublicUrlService::getEntityViewPayload('category', $categoryId, $languageCode);
        if (!$basePayload) {
            return null;
        }

        $categoryId = (int) ($basePayload['entity_id'] ?? 0);
        $languageCode = (string) ($basePayload['language_code'] ?? ENV_DEF_LANG);
        $categoryRow = SafeMySQL::gi()->getRow(
            'SELECT category_id, title, parent_id, type_id, language_code, short_description, description, status, slug
             FROM ?n
             WHERE category_id = ?i
             LIMIT 1',
            Constants::CATEGORIES_TABLE,
            $categoryId
        );
        if (!$categoryRow) {
            return null;
        }

        $propertyMap = $this->getEntityPropertyMap('category', $categoryId, $languageCode);
        $overviewHtml = $this->normalizeRichTextHtml(
            $this->extractScalarValue($propertyMap['Описание курорта'] ?? null, (string) ($categoryRow['description'] ?? ''))
        );
        $leftBlockHtml = $this->normalizeRichTextHtml($this->extractScalarValue($propertyMap['Левый блок текста'] ?? null));
        $rightBlockHtml = $this->normalizeRichTextHtml($this->extractScalarValue($propertyMap['Правый блок текста'] ?? null));
        $gallery = $this->extractGalleryItems($propertyMap['Фотографии курорта'] ?? null);
        $map = $this->extractMapData($propertyMap['Карта курорта'] ?? null);

        $summary = $this->resolveSummary(
            (string) ($categoryRow['short_description'] ?? ''),
            $overviewHtml !== '' ? $overviewHtml : (string) ($categoryRow['description'] ?? '')
        );
        $metaDescription = $summary !== '' ? $summary : $this->truncateText($this->extractPlainText($overviewHtml), 180);

        return array_merge($basePayload, [
            'view_type' => 'category',
            'summary' => $summary,
            'meta_description' => $metaDescription !== '' ? $metaDescription : (string) ($basePayload['meta_description'] ?? ''),
            'overview_html' => $overviewHtml,
            'left_block_html' => $leftBlockHtml,
            'right_block_html' => $rightBlockHtml,
            'gallery' => $gallery,
            'gallery_preview' => array_slice($gallery, 0, 10),
            'map' => $map,
            'language_links' => $this->filterLanguageLinks((array) ($basePayload['alternate_links'] ?? [])),
            'child_categories' => $this->getChildCategoryCards($categoryId, $languageCode, 24),
            'pages' => $this->getPageCardsByCategory($categoryId, $languageCode, 0, 24),
        ]);
    }

    private function getEntityPropertyMap(string $entityType, int $entityId, string $languageCode): array {
        $rows = SafeMySQL::gi()->getAll(
            'SELECT p.property_id, p.name, p.default_values, p.is_multiple, p.is_required, pt.fields AS type_fields, pv.property_values
             FROM ?n AS pv
             JOIN ?n AS p ON p.property_id = pv.property_id
             LEFT JOIN ?n AS pt ON pt.type_id = p.type_id
             WHERE pv.entity_type = ?s AND pv.entity_id = ?i AND pv.language_code = ?s
             ORDER BY p.property_id ASC',
            Constants::PROPERTY_VALUES_TABLE,
            Constants::PROPERTIES_TABLE,
            Constants::PROPERTY_TYPES_TABLE,
            $entityType,
            $entityId,
            $languageCode
        );

        $map = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $runtimeFields = PropertyFieldContract::buildRuntimeFields(
                $row['default_values'] ?? [],
                $row['property_values'] ?? [],
                $row['type_fields'] ?? [],
                $row
            );
            $map[$name] = [
                'name' => $name,
                'runtime_fields' => $runtimeFields,
                'row' => $row,
            ];
        }

        return $map;
    }

    private function filterLanguageLinks(array $links): array {
        return array_values(array_filter($links, static function ($item): bool {
            return is_array($item)
                && !empty($item['href'])
                && strtoupper((string) ($item['language_code'] ?? '')) !== 'X-DEFAULT';
        }));
    }

    private function getPageOwner(int $pageId): array {
        $row = SafeMySQL::gi()->getRow(
            'SELECT pu.user_id, u.name, u.email, u.phone
             FROM ?n AS pu
             JOIN ?n AS u ON u.user_id = pu.user_id
             WHERE pu.page_id = ?i
             LIMIT 1',
            Constants::PAGE_USER_LINKS_TABLE,
            Constants::USERS_TABLE,
            $pageId
        );

        if (!$row) {
            return [];
        }

        $phone = trim((string) ($row['phone'] ?? ''));
        return [
            'user_id' => (int) ($row['user_id'] ?? 0),
            'name' => trim((string) ($row['name'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'phone' => $phone,
            'phone_href' => $phone !== '' ? ('tel:' . preg_replace('~[^0-9+]~', '', $phone)) : '',
        ];
    }

    private function getPageCardsByCategory(int $categoryId, string $languageCode, int $excludePageId = 0, int $limit = 12): array {
        if ($categoryId <= 0) {
            return [];
        }

        $sql = 'SELECT page_id, title, slug, short_description, description
                FROM ?n
                WHERE category_id = ?i AND status = ?s AND language_code = ?s';
        $params = [Constants::PAGES_TABLE, $categoryId, 'active', $languageCode];
        if ($excludePageId > 0) {
            $sql .= ' AND page_id != ?i';
            $params[] = $excludePageId;
        }
        $sql .= ' ORDER BY title ASC LIMIT ?i';
        $params[] = max(1, $limit);

        $rows = SafeMySQL::gi()->getAll($sql, ...$params);
        if (!$rows) {
            return [];
        }

        $pageIds = array_map(static fn($row): int => (int) ($row['page_id'] ?? 0), $rows);
        $previewMap = $this->getPagePreviewPropertyMap($pageIds, $languageCode);
        $cards = [];

        foreach ($rows as $row) {
            $pageId = (int) ($row['page_id'] ?? 0);
            $preview = $previewMap[$pageId] ?? [];
            $cards[] = [
                'page_id' => $pageId,
                'title' => trim((string) ($row['title'] ?? '')),
                'url' => EntityPublicUrlService::buildEntityUrl('page', $pageId, $languageCode),
                'summary' => $this->resolveSummary((string) ($row['short_description'] ?? ''), (string) ($row['description'] ?? '')),
                'image' => $preview['image'] ?? null,
                'object_type' => trim((string) ($preview['object_type'] ?? '')),
                'distance_to_sea' => trim((string) ($preview['distance_to_sea'] ?? '')),
            ];
        }

        return $cards;
    }

    private function getChildCategoryCards(int $parentCategoryId, string $languageCode, int $limit = 24): array {
        $rows = SafeMySQL::gi()->getAll(
            'SELECT category_id, title, slug, short_description, description
             FROM ?n
             WHERE parent_id = ?i AND status = ?s AND language_code = ?s
             ORDER BY title ASC
             LIMIT ?i',
            Constants::CATEGORIES_TABLE,
            $parentCategoryId,
            'active',
            $languageCode,
            max(1, $limit)
        );
        if (!$rows) {
            return [];
        }

        $categoryIds = array_map(static fn($row): int => (int) ($row['category_id'] ?? 0), $rows);
        $countRows = SafeMySQL::gi()->getAll(
            'SELECT category_id, COUNT(*) AS pages_total
             FROM ?n
             WHERE category_id IN (?a) AND status = ?s AND language_code = ?s
             GROUP BY category_id',
            Constants::PAGES_TABLE,
            $categoryIds,
            'active',
            $languageCode
        );
        $countMap = [];
        foreach ($countRows as $countRow) {
            $countMap[(int) ($countRow['category_id'] ?? 0)] = (int) ($countRow['pages_total'] ?? 0);
        }

        $previewMap = $this->getCategoryPreviewPropertyMap($categoryIds, $languageCode);
        $cards = [];
        foreach ($rows as $row) {
            $categoryId = (int) ($row['category_id'] ?? 0);
            $preview = $previewMap[$categoryId] ?? [];
            $cards[] = [
                'category_id' => $categoryId,
                'title' => trim((string) ($row['title'] ?? '')),
                'url' => EntityPublicUrlService::buildEntityUrl('category', $categoryId, $languageCode),
                'summary' => $this->resolveSummary((string) ($row['short_description'] ?? ''), (string) ($row['description'] ?? '')),
                'image' => $preview['image'] ?? null,
                'pages_total' => (int) ($countMap[$categoryId] ?? 0),
            ];
        }

        return $cards;
    }

    private function getPagePreviewPropertyMap(array $pageIds, string $languageCode): array {
        $pageIds = array_values(array_filter(array_map('intval', $pageIds), static fn(int $id): bool => $id > 0));
        if ($pageIds === []) {
            return [];
        }

        $galleryPropertyId = $this->getPropertyIdByName('Фотографии объекта');
        $typePropertyId = $this->getPropertyIdByName('Тип объекта');
        $distancePropertyId = $this->getPropertyIdByName('Удаленность от моря');
        $propertyIds = array_values(array_filter([$galleryPropertyId, $typePropertyId, $distancePropertyId], static fn(int $id): bool => $id > 0));
        if ($propertyIds === []) {
            return [];
        }

        $rows = SafeMySQL::gi()->getAll(
            'SELECT pv.entity_id, p.name, p.default_values, pt.fields AS type_fields, pv.property_values
             FROM ?n AS pv
             JOIN ?n AS p ON p.property_id = pv.property_id
             LEFT JOIN ?n AS pt ON pt.type_id = p.type_id
             WHERE pv.entity_type = ?s AND pv.entity_id IN (?a) AND pv.language_code = ?s AND pv.property_id IN (?a)',
            Constants::PROPERTY_VALUES_TABLE,
            Constants::PROPERTIES_TABLE,
            Constants::PROPERTY_TYPES_TABLE,
            'page',
            $pageIds,
            $languageCode,
            $propertyIds
        );

        $map = [];
        foreach ($rows as $row) {
            $entityId = (int) ($row['entity_id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($entityId <= 0 || $name === '') {
                continue;
            }
            $property = [
                'name' => $name,
                'runtime_fields' => PropertyFieldContract::buildRuntimeFields(
                    $row['default_values'] ?? [],
                    $row['property_values'] ?? [],
                    $row['type_fields'] ?? [],
                    $row
                ),
                'row' => $row,
            ];

            if ($name === 'Фотографии объекта' && empty($map[$entityId]['image'])) {
                $image = $this->extractFirstGalleryItem($property);
                if ($image) {
                    $map[$entityId]['image'] = $image;
                }
            } elseif ($name === 'Тип объекта') {
                $map[$entityId]['object_type'] = $this->extractChoiceSummary($property);
            } elseif ($name === 'Удаленность от моря') {
                $map[$entityId]['distance_to_sea'] = $this->extractScalarValue($property);
            }
        }

        return $map;
    }

    private function getCategoryPreviewPropertyMap(array $categoryIds, string $languageCode): array {
        $categoryIds = array_values(array_filter(array_map('intval', $categoryIds), static fn(int $id): bool => $id > 0));
        if ($categoryIds === []) {
            return [];
        }

        $galleryPropertyId = $this->getPropertyIdByName('Фотографии курорта');
        if ($galleryPropertyId <= 0) {
            return [];
        }

        $rows = SafeMySQL::gi()->getAll(
            'SELECT pv.entity_id, p.name, p.default_values, pt.fields AS type_fields, pv.property_values
             FROM ?n AS pv
             JOIN ?n AS p ON p.property_id = pv.property_id
             LEFT JOIN ?n AS pt ON pt.type_id = p.type_id
             WHERE pv.entity_type = ?s AND pv.entity_id IN (?a) AND pv.language_code = ?s AND pv.property_id = ?i',
            Constants::PROPERTY_VALUES_TABLE,
            Constants::PROPERTIES_TABLE,
            Constants::PROPERTY_TYPES_TABLE,
            'category',
            $categoryIds,
            $languageCode,
            $galleryPropertyId
        );

        $map = [];
        foreach ($rows as $row) {
            $entityId = (int) ($row['entity_id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }
            $property = [
                'name' => trim((string) ($row['name'] ?? '')),
                'runtime_fields' => PropertyFieldContract::buildRuntimeFields(
                    $row['default_values'] ?? [],
                    $row['property_values'] ?? [],
                    $row['type_fields'] ?? [],
                    $row
                ),
                'row' => $row,
            ];
            $image = $this->extractFirstGalleryItem($property);
            if ($image) {
                $map[$entityId]['image'] = $image;
            }
        }

        return $map;
    }

    private function extractGalleryItems(?array $property): array {
        if (!$property) {
            return [];
        }

        $items = [];
        foreach ((array) ($property['runtime_fields'] ?? []) as $field) {
            if (($field['type'] ?? '') !== 'image') {
                continue;
            }
            foreach ($this->extractFileItemsFromField($field) as $item) {
                $items[$item['unique_id']] = $item;
            }
        }

        return array_values($items);
    }

    private function extractFirstGalleryItem(?array $property): ?array {
        $gallery = $this->extractGalleryItems($property);
        return $gallery[0] ?? null;
    }

    private function extractFileItemsFromField(array $field): array {
        $references = FileSystem::normalizeFileReferences($field['value'] ?? []);
        $items = [];
        foreach ($references as $reference) {
            $descriptor = FileSystem::describeFileReference($reference);
            if (!$descriptor || empty($descriptor['file_url'])) {
                continue;
            }
            $items[] = $descriptor;
        }
        return $items;
    }

    private function extractChoiceSummary(?array $property): string {
        $items = $this->extractChoiceList($property);
        return $items[0] ?? '';
    }

    private function extractChoiceList(?array $property): array {
        if (!$property) {
            return [];
        }

        $labels = [];
        foreach ((array) ($property['runtime_fields'] ?? []) as $field) {
            $value = $this->extractFieldDisplayValue($field);
            if (is_array($value)) {
                foreach ($value as $item) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        $labels[] = $item;
                    }
                }
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $labels[] = $value;
            }
        }

        return array_values(array_unique($labels));
    }

    private function extractScalarValue(?array $property, string $fallback = ''): string {
        if (!$property) {
            return trim($fallback);
        }

        foreach ((array) ($property['runtime_fields'] ?? []) as $field) {
            if (in_array((string) ($field['type'] ?? ''), ['image', 'file'], true)) {
                continue;
            }

            $value = $this->extractFieldDisplayValue($field);
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map(static fn($item): string => trim((string) $item), $value)));
            }
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return trim($fallback);
    }

    private function extractPhoneEntries(?array $property): array {
        if (!$property) {
            return [];
        }

        $entries = [];
        foreach ((array) ($property['runtime_fields'] ?? []) as $field) {
            $type = trim((string) ($field['type'] ?? ''));
            if (!in_array($type, ['phone', 'text'], true)) {
                continue;
            }

            $fieldValues = $this->expandRoomFieldValues($field);
            if ($fieldValues === []) {
                $fieldValues = [$field['value'] ?? null];
            }

            foreach ($fieldValues as $index => $fieldValue) {
                $value = $this->extractFieldDisplayValueForRoomValue($field, $fieldValue);
                $valueString = is_array($value)
                    ? implode(', ', array_filter(array_map(static fn($item): string => trim((string) $item), $value)))
                    : trim((string) $value);
                if ($valueString === '') {
                    continue;
                }
                if (!isset($entries[$index])) {
                    $entries[$index] = ['phone' => '', 'comment' => ''];
                }
                if ($type === 'phone') {
                    $entries[$index]['phone'] = $valueString;
                } elseif ($entries[$index]['comment'] === '') {
                    $entries[$index]['comment'] = $valueString;
                }
            }
        }

        $normalized = [];
        foreach ($entries as $entry) {
            $finalEntry = $this->finalizePhoneEntry($entry);
            if ($finalEntry['phone'] === '' && $finalEntry['comment'] === '') {
                continue;
            }
            $normalized[] = $finalEntry;
        }

        return $normalized;
    }

    private function finalizePhoneEntry(array $entry): array {
        $phone = trim((string) ($entry['phone'] ?? ''));
        $comment = trim((string) ($entry['comment'] ?? ''));
        return [
            'phone' => $phone,
            'comment' => $comment,
            'tel_href' => $phone !== '' ? ('tel:' . preg_replace('~[^0-9+]~', '', $phone)) : '',
        ];
    }

    private function extractMapData(?array $property): ?array {
        if (!$property) {
            return null;
        }

        $coords = '';
        $zoom = '';
        foreach ((array) ($property['runtime_fields'] ?? []) as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            $value = trim((string) $this->extractFieldDisplayValue($field));
            if ($value === '') {
                continue;
            }
            if ($coords === '' && (($field['type'] ?? '') === 'text' || stripos($label, 'координат') !== false)) {
                $coords = $value;
                continue;
            }
            if ($zoom === '' && (($field['type'] ?? '') === 'number' || stripos($label, 'масштаб') !== false)) {
                $zoom = $value;
            }
        }

        if ($coords === '') {
            return null;
        }

        $parts = array_map('trim', explode(',', $coords));
        if (count($parts) < 2) {
            return null;
        }

        $lat = (float) str_replace(',', '.', $parts[0]);
        $lng = (float) str_replace(',', '.', $parts[1]);
        if (!$lat && !$lng) {
            return null;
        }

        $zoomValue = (int) preg_replace('~[^0-9]~', '', $zoom);
        if ($zoomValue <= 0) {
            $zoomValue = 15;
        }

        return [
            'coords' => $coords,
            'lat' => $lat,
            'lng' => $lng,
            'zoom' => $zoomValue,
            'google_url' => 'https://www.google.com/maps?q=' . rawurlencode($coords),
            'yandex_url' => 'https://yandex.ru/maps/?pt=' . rawurlencode($lng . ',' . $lat) . '&z=' . $zoomValue . '&l=map',
        ];
    }

    private function extractRoomOffer(?array $property): array {
        if (!$property) {
            return [];
        }

        $runtimeFields = (array) ($property['runtime_fields'] ?? []);
        if ($runtimeFields === []) {
            return [];
        }

        $rooms = [];
        foreach ($runtimeFields as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            $fieldValues = $this->expandRoomFieldValues($field);
            if ($fieldValues === []) {
                $fieldValues = [$field['value'] ?? null];
            }

            foreach ($fieldValues as $roomIndex => $roomValue) {
                if (!isset($rooms[$roomIndex])) {
                    $rooms[$roomIndex] = [
                        'name' => '',
                        'facts' => [],
                        'amenities' => [],
                        'description_html' => '',
                        'gallery' => [],
                        'price_mode' => '',
                        'periods' => [],
                    ];
                }
                $value = $this->extractFieldDisplayValueForRoomValue($field, $roomValue);
                $valueString = is_array($value)
                    ? implode(', ', array_filter(array_map(static fn($item): string => trim((string) $item), $value)))
                    : trim((string) $value);

                if (($field['type'] ?? '') === 'image' || $label === 'Фотографии номера') {
                    $gallery = $this->extractFileItemsFromRawValue($roomValue);
                    if ($gallery !== []) {
                        $rooms[$roomIndex]['gallery'] = $gallery;
                    }
                    continue;
                }

                if ($label === '' || $valueString === '') {
                    continue;
                }

                if ($label === 'Название номера') {
                    $rooms[$roomIndex]['name'] = $valueString;
                    continue;
                }

                if ($label === 'Дополнительные данные') {
                    $rooms[$roomIndex]['description_html'] = $this->normalizeRichTextHtml($valueString);
                    continue;
                }

                if ($label === 'Цены указываются') {
                    $rooms[$roomIndex]['price_mode'] = $valueString;
                    continue;
                }

                if (preg_match('/^Период\s+(\d+):\s+дата\s+с$/u', $label, $matches)) {
                    $rooms[$roomIndex]['periods'][(int) $matches[1]]['from'] = $valueString;
                    continue;
                }
                if (preg_match('/^Период\s+(\d+):\s+дата\s+по$/u', $label, $matches)) {
                    $rooms[$roomIndex]['periods'][(int) $matches[1]]['to'] = $valueString;
                    continue;
                }
                if (preg_match('/^Период\s+(\d+):\s+цена$/u', $label, $matches)) {
                    $rooms[$roomIndex]['periods'][(int) $matches[1]]['price'] = $valueString;
                    continue;
                }

                if (in_array($label, ['Спальных мест', 'Доп. места', 'Комнат в номере', 'Площадь номера'], true)) {
                    $rooms[$roomIndex]['facts'][] = ['label' => $label, 'value' => $valueString];
                    continue;
                }

                if (in_array($label, ['Туалет', 'Душ', 'Кондиционер', 'Телевизор', 'Спутниковое или кабельное ТВ', 'Wi‑Fi', 'Сейф', 'Холодильник', 'Кухня', 'Балкон'], true)) {
                    if (mb_strtolower($valueString) !== 'нет') {
                        $rooms[$roomIndex]['amenities'][] = $label . ': ' . $valueString;
                    }
                }
            }
        }

        $normalizedRooms = [];
        ksort($rooms);
        foreach ($rooms as $room) {
            $periods = [];
            if (!empty($room['periods']) && is_array($room['periods'])) {
                ksort($room['periods']);
                foreach ($room['periods'] as $period) {
                    $from = trim((string) ($period['from'] ?? ''));
                    $to = trim((string) ($period['to'] ?? ''));
                    $price = trim((string) ($period['price'] ?? ''));
                    if ($from === '' && $to === '' && $price === '') {
                        continue;
                    }
                    $periods[] = [
                        'from' => $from,
                        'to' => $to,
                        'price' => $price,
                    ];
                }
            }
            $room['periods'] = $periods;
            if ($room['name'] === '' && $room['description_html'] === '' && $room['gallery'] === [] && $room['periods'] === []) {
                continue;
            }
            $normalizedRooms[] = $room;
        }

        return $normalizedRooms;
    }

    private function expandRoomFieldValues(array $field): array {
        $value = $field['value'] ?? null;
        if (!is_array($value)) {
            return $value === null || $value === '' ? [] : [$value];
        }

        if ($value === []) {
            return [];
        }

        return array_values($value);
    }

    private function extractFieldDisplayValueForRoomValue(array $field, mixed $roomValue): mixed {
        $fieldCopy = $field;
        $fieldCopy['value'] = $roomValue;
        $fieldCopy['multiple'] = is_array($roomValue) && ($field['type'] ?? '') === 'checkbox' ? 1 : 0;
        return $this->extractFieldDisplayValue($fieldCopy);
    }

    private function extractFileItemsFromRawValue(mixed $value): array {
        $references = FileSystem::normalizeFileReferences($value);
        $items = [];
        foreach ($references as $reference) {
            $descriptor = FileSystem::describeFileReference($reference);
            if (!$descriptor || empty($descriptor['file_url'])) {
                continue;
            }
            $items[] = $descriptor;
        }
        return $items;
    }

    private function extractFieldDisplayValue(array $field): mixed {
        $type = strtolower(trim((string) ($field['type'] ?? '')));
        $value = $field['value'] ?? ($field['default'] ?? '');
        if (in_array($type, ['select', 'checkbox', 'radio'], true)) {
            $selected = is_array($value) ? $value : ($value === '' ? [] : [$value]);
            $optionMap = [];
            foreach ((array) ($field['options'] ?? []) as $option) {
                $key = trim((string) ($option['key'] ?? $option['value'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $optionMap[$key] = trim((string) ($option['label'] ?? $key));
            }
            $labels = [];
            foreach ($selected as $selectedValue) {
                $selectedKey = trim((string) $selectedValue);
                if ($selectedKey === '') {
                    continue;
                }
                $labels[] = $optionMap[$selectedKey] ?? $selectedKey;
            }
            return !empty($field['multiple']) ? $labels : ($labels[0] ?? '');
        }

        return $value;
    }

    private function normalizeRichTextHtml(string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        return WordpressImporter::normalizeImportedRichText($html);
    }

    private function resolveSummary(string $shortDescription, string $fallbackHtml): string {
        $shortDescription = trim($shortDescription);
        if ($shortDescription !== '') {
            $normalized = mb_strtolower(trim($shortDescription));
            if (!preg_match('~^[a-z0-9\\-_]+$~u', $normalized)) {
                return $this->truncateText($this->extractPlainText($shortDescription), 220);
            }
        }

        return $this->truncateText($this->extractPlainText($fallbackHtml), 220);
    }

    private function extractPlainText(string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('~\\s+~u', ' ', $text) ?? $text;
        return trim($text);
    }

    private function truncateText(string $text, int $limit): string {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, max(1, $limit - 1))) . '…';
    }

    private function makeFact(string $label, string $value): ?array {
        $label = trim($label);
        $value = trim($value);
        if ($label === '' || $value === '') {
            return null;
        }
        return ['label' => $label, 'value' => $value];
    }

    private function normalizeWebsite(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('~^https?://~iu', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    private function getPropertyIdByName(string $propertyName): int {
        $propertyName = trim($propertyName);
        if ($propertyName === '') {
            return 0;
        }
        if (isset($this->propertyIdCache[$propertyName])) {
            return $this->propertyIdCache[$propertyName];
        }

        $propertyId = (int) SafeMySQL::gi()->getOne(
            'SELECT property_id
             FROM ?n
             WHERE name = ?s
             ORDER BY property_id ASC
             LIMIT 1',
            Constants::PROPERTIES_TABLE,
            $propertyName
        );
        $this->propertyIdCache[$propertyName] = $propertyId;
        return $propertyId;
    }

    private function langLabel(string $key, string $fallback): string {
        return trim((string) (\classes\system\Lang::get($key, $fallback) ?? $fallback));
    }
}
