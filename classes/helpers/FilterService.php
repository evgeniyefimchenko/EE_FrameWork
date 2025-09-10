<?php

namespace classes\helpers;

use classes\system\SysClass;

/**
 * FilterService
 * Сервис для выполнения сложной бизнес-логики по расчету фильтров
 */
class FilterService {
    
    private \ModelFilters $modelFilters;
    private \ModelCategories $modelCategories;

    public function __construct() {
        $this->modelFilters = SysClass::getModelObject('admin', 'm_filters');
        $this->modelCategories = SysClass::getModelObject('admin', 'm_categories');
        if (!$this->modelFilters || !$this->modelCategories) {
            throw new \Exception('Не удалось загрузить одну из моделей: ModelFilters или ModelCategories.');
        }
    }

    public function regenerateFiltersForEntity(string $entityType, int $entityId): array {
        $pageIds = [];
        if ($entityType === 'category') {
            $descendants = $this->modelCategories->getCategoryDescendantsShort($entityId);
            $categoryIds = [];
            if (!empty($descendants)) {
                foreach ($descendants as $category) {
                    $categoryIds[] = (int)$category['category_id'];
                }
            }
            if (!empty($categoryIds)) {
                $pageIds = $this->modelFilters->getPageIdsForCategories($categoryIds);
            }
        } else {
            $pageIds = [$entityId]; 
        }

        if (empty($pageIds)) {
            $this->modelFilters->clearFilters($entityType, $entityId);
            return ['status' => 'success', 'message' => 'Страницы для анализа не найдены, фильтры для #' . $entityId . ' очищены'];
        }

        $rawProperties = $this->modelFilters->getRawPropertyDataForPages($pageIds);
        $aggregatedFilters = $this->aggregateRawProperties($rawProperties);
        
        if (empty($aggregatedFilters)) {
            $this->modelFilters->clearFilters($entityType, $entityId);
            return ['status' => 'success', 'message' => 'Не найдено подходящих свойств для создания фильтров для ' . $entityType . ' #' . $entityId];
        }

        $this->modelFilters->replaceFiltersForEntity($entityType, $entityId, $aggregatedFilters);
        return ['status' => 'success', 'message' => 'Фильтры для ' . $entityType . ' #' . $entityId . ' успешно пересчитаны'];
    }
    
    /**
     * Рекурсивно очищает массив от пробелов в ключах и строковых значениях.
     * @param mixed $data Входные данные.
     * @return mixed Очищенные данные.
     */
    private function deepClean($data) {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleanedKey = is_string($key) ? trim($key) : $key;
                $cleaned[$cleanedKey] = $this->deepClean($value);
            }
            return $cleaned;
        }
        return is_string($data) ? trim($data) : $data;
    }

    private function aggregateRawProperties(array $rawProperties): array {
        $tempStorage = [];

        foreach ($rawProperties as $row) {
            $cleanedRow = $this->deepClean($row);
            $propId = (int)$cleanedRow['property_id'];
            
            if (!isset($tempStorage[$propId])) {
                $tempStorage[$propId] = [
                    'property_name' => $cleanedRow['property_name'],
                    'property_multiple' => (bool)$cleanedRow['property_multiple'],
                    'values' => [],
                    'options_map' => [],
                    'filter_type' => null
                ];
            }
            
            $decodedJson = json_decode($cleanedRow['value_json'], true);
            if (!is_array($decodedJson)) continue;

            $cleanedJsonData = $this->deepClean($decodedJson);

            foreach ($cleanedJsonData as $propertyData) {
                if (!isset($propertyData['type'])) continue;
                
                $valueContainer = $propertyData['value'] ?? $propertyData['default'] ?? null;
                if (is_null($valueContainer) || $valueContainer === '') continue;
                
                $fieldType = $propertyData['type'];

                switch ($fieldType) {
                    case 'select':
                        $tempStorage[$propId]['filter_type'] = 'options';
                        $valuesToParse = is_array($valueContainer) ? $valueContainer : [$valueContainer];
                        foreach($valuesToParse as $optionsString) {
                            $parsedOptions = $this->parseSelectOptions($optionsString);
                            foreach ($parsedOptions as $option) {
                                $tempStorage[$propId]['options_map'][$option['value']] = $option['label'];
                                if ($option['selected']) { 
                                    $tempStorage[$propId]['values'][] = $option['value'];
                                }
                            }
                        }
                        break;
                    case 'radio':
                    case 'checkbox':
                        $tempStorage[$propId]['filter_type'] = 'options';
                        $labels = $propertyData['label'] ?? [];
                        $selectedIndexes = $valueContainer ?? [];
                        foreach ($labels as $index => $label) {
                            $valueKey = $this->transliterate($label);
                            $tempStorage[$propId]['options_map'][$valueKey] = $label;
                            if (in_array((string)$index, $selectedIndexes, true)) {
                                 $tempStorage[$propId]['values'][] = $valueKey;
                            }
                        }
                        break;
                    case 'number':
                        $tempStorage[$propId]['filter_type'] = 'range';
                        $values = is_array($valueContainer) ? $valueContainer : [$valueContainer];
                        foreach ($values as $val) {
                            if (is_numeric($val)) $tempStorage[$propId]['values'][] = (float)$val;
                        }
                        break;
                }
            }
        }
        
        $aggregated = [];
        foreach ($tempStorage as $propId => $data) {
            if (empty($data['values']) || is_null($data['filter_type'])) continue;

            $aggregated[$propId] = [
                'property_name' => $data['property_name'],
                'multiple' => $data['property_multiple'],
                'filter_type' => $data['filter_type']
            ];

            if ($data['filter_type'] === 'options') {
                $counts = array_count_values($data['values']);
                $options = [];
                foreach ($data['options_map'] as $valueKey => $label) {
                    if (isset($counts[$valueKey])) {
                        $options[] = ['id' => $valueKey, 'label' => $label, 'count' => $counts[$valueKey]];
                    }
                }
                if (!empty($options)) {
                    $aggregated[$propId]['options'] = $options;
                } else {
                    unset($aggregated[$propId]);
                }
            } elseif ($data['filter_type'] === 'range') {
                $aggregated[$propId]['min_value'] = min($data['values']);
                $aggregated[$propId]['max_value'] = max($data['values']);
                $aggregated[$propId]['count'] = count($data['values']);
            }
        }
        return $aggregated;
    }
    
    private function parseSelectOptions(string $string): array {
        $result = [];
        if (empty($string)) return $result;
        $trimmedString = trim($string);
        if (str_starts_with($trimmedString, '{|}')) $trimmedString = substr($trimmedString, 3);
        $pairs = explode('{|}', $trimmedString);
        foreach ($pairs as $pair) {
            if (empty($pair)) continue;
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2) {
                $label = $parts[0];
                $valuePart = $parts[1];
                $isSelected = false;
                if (str_ends_with($valuePart, '{*}')) {
                    $value = rtrim($valuePart, '{*}');
                    $isSelected = true;
                } else {
                    $value = $valuePart;
                }
                $result[] = ['label' => $label, 'value' => $value, 'selected' => $isSelected];
            }
        }
        return $result;
    }
    
    private function transliterate(string $string): string {
        $charMap = [
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'E','Ж'=>'ZH','З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'KH','Ц'=>'TS','Ч'=>'CH','Ш'=>'SH','Щ'=>'SHCH','Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'YU','Я'=>'YA',
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            ' '=>'_','.'=>'','/'=>'_','\\'=>'_',','=>'','('=>'',')'=>''
        ];
        $str = strtr($string, $charMap);
        return preg_replace('/[^A-Za-z0-9_]/', '', $str);
    }
}