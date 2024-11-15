<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;

/**
 * Модель работы со свойствами
 */
class ModelProperties {

    /**
     * Получает все свойства на основе статуса и языкового кода     
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @param string $languageCode Языковой код для фильтрации. По умолчанию используется константа ENV_DEF_LANG
     * @return array Возвращает массив всех свойств, соответствующих заданным критериям
     */
    public function getAllProperties($status = Constants::ALL_STATUS, $languageCode = ENV_DEF_LANG, $short = true) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        if ($short) {
            $sql_properties = "SELECT * FROM ?n WHERE status IN (?a) AND language_code = ?s";
            $res = SafeMySQL::gi()->getAll($sql_properties, Constants::PROPERTIES_TABLE, $status, $languageCode);
            return $res;
        } else {
            $return = [];
            $sql_properties = "SELECT property_id FROM ?n WHERE status IN (?a) AND language_code = ?s";
            $res = SafeMySQL::gi()->getAll($sql_properties, Constants::PROPERTIES_TABLE, $status, $languageCode);
            foreach ($res as $item) {
                $data_prop = $this->getPropertyData($item['property_id'], $languageCode, $status);
                if (!$data_prop)
                    continue;
                $return[] = $data_prop;
            }
            return $return;
        }
        return false;
    }

    /**
     * Получает данные свойств с учетом параметров сортировки, фильтрации и пагинации
     * @param string $order Параметр для сортировки результатов (по умолчанию 'property_id ASC')
     * @param string|null $where Условие для фильтрации результатов (по умолчанию NULL)
     * @param int $start Начальный индекс для пагинации результатов (по умолчанию 0)
     * @param int $limit Максимальное количество результатов для возврата (по умолчанию 100)
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return array Массив, содержащий данные свойств и общее количество свойств
     */
    public function getPropertiesData($order = 'property_id ASC', $where = null, $start = 0, $limit = 100, $languageCode = ENV_DEF_LANG) {
        $orderString = $order ? $order : 'property_id ASC';
        $whereString = $where ? $where . ' AND language_code = ?s' : 'language_code = ?s';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_properties = "SELECT property_id FROM ?n WHERE $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_properties = "SELECT property_id FROM ?n WHERE $whereString LIMIT ?i, ?i";
        }        
        $res_array = SafeMySQL::gi()->getAll($sql_properties, Constants::PROPERTIES_TABLE, $languageCode, $start, $limit);
        $res = [];
        foreach ($res_array as $property) {
            $data_prop = $this->getPropertyData($property['property_id'], $languageCode);
            if (!$data_prop)
                continue;
            $res[] = $data_prop;
        }
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n WHERE $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PROPERTIES_TABLE, $languageCode);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные одного свойства по его ID
     * @param int $property_id ID свойства, для которого нужно получить данные
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @return array|null Массив с данными свойства или NULL, если свойство не найдено
     */
    public function getPropertyData($property_id, $languageCode = ENV_DEF_LANG, $status = Constants::ALL_STATUS) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        $sql_property = 'SELECT p.*, pt.name as type_name, pt.fields as fields 
            FROM ?n AS p 
            LEFT JOIN ?n AS pt ON p.type_id = pt.type_id 
            WHERE pt.status IN (?a) AND p.property_id = ?i AND p.language_code = ?s';
        $propertyData = SafeMySQL::gi()->getRow(
                $sql_property,
                Constants::PROPERTIES_TABLE,
                Constants::PROPERTY_TYPES_TABLE,
                $status,
                $property_id,
                $languageCode
        );
        if (!$propertyData) {
            return null;
        }
        return $propertyData;
    }

    /**
     * Обновляет данные свойства в таблице свойств
     * @param array $propertyData Ассоциативный массив с данными свойства для обновления
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return int|bool ID обновленного свойства или false в случае неудачи
     */
    public function updatePropertyData($propertyData = [], $languageCode = ENV_DEF_LANG) {
        $propertyData = SafeMySQL::gi()->filterArray($propertyData, SysClass::ee_getFieldsTable(Constants::PROPERTIES_TABLE));
        if (is_array($propertyData['default_values'])) {
            $propertyData['default_values'] = json_encode($propertyData['default_values'], JSON_UNESCAPED_UNICODE);
        } elseif (!SysClass::ee_isValidJson($propertyData['default_values'])) {
            $propertyData['default_values'] = '[]';
        }
        $propertyData = array_map('trim', $propertyData);
        $propertyData['is_multiple'] = isset($propertyData['is_multiple']) && ($propertyData['is_multiple'] == 'on' || $propertyData['is_multiple'] == 1) ? 1 : 0;
        $propertyData['is_required'] = isset($propertyData['is_required']) && ($propertyData['is_required'] == 'on' || $propertyData['is_required'] == 1) ? 1 : 0;
        $propertyData['language_code'] = $languageCode;  // добавлено
        if (empty($propertyData['name']) || !isset($propertyData['type_id'])) {
            SysClass::preFile('errors', 'update_property_data', 'Error: property_data', $propertyData);
            return false;
        }
        if (!empty($propertyData['property_id']) && $propertyData['property_id'] != 0) {
            $property_id = $propertyData['property_id'];
            unset($propertyData['property_id']); // Удаляем property_id из массива данных, чтобы избежать его обновление
            $sql = "UPDATE ?n SET ?u WHERE property_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTIES_TABLE, $propertyData, $property_id);
            return $result ? $property_id : false;
        } else {
            unset($propertyData['property_id']);
        }
        // Проверяем уникальность имени в рамках одного типа и языка
        $existingProperty = SafeMySQL::gi()->getRow(
                "SELECT property_id FROM ?n WHERE name = ?s AND type_id = ?i AND language_code = ?s",
                Constants::PROPERTIES_TABLE,
                $propertyData['name'], $propertyData['type_id'], $languageCode
        );
        if ($existingProperty) {
            SysClass::preFile('errors', 'update_property_data', 'Error: existingProperty', $propertyData);
            return false;
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTIES_TABLE, $propertyData);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Получает все типы свойств из таблицы типов свойств
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return array Массив, содержащий все типы свойств, каждый из которых представлен ассоциативным массивом с данными типа свойства
     */
    public function getAllPropertyTypes($status = Constants::ALL_STATUS, $languageCode = ENV_DEF_LANG) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        $sql = "SELECT * FROM ?n WHERE status IN (?a) AND language_code = ?s";
        $property_types = SafeMySQL::gi()->getAll($sql, Constants::PROPERTY_TYPES_TABLE, $status, $languageCode);
        return $property_types;
    }

    /**
     * Получает данные типов свойств с учетом параметров сортировки, фильтрации и пагинации
     * @param string $order Параметр для сортировки результатов (по умолчанию 'type_id ASC')
     * @param string|null $where Условие для фильтрации результатов (по умолчанию NULL)
     * @param int $start Начальный индекс для пагинации результатов (по умолчанию 0)
     * @param int $limit Максимальное количество результатов для возврата (по умолчанию 100)
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return array Массив, содержащий данные типов свойств и общее количество типов свойств
     */
    public function getTypePropertiesData($order = 'type_id ASC', $where = null, $start = 0, $limit = 100, $languageCode = ENV_DEF_LANG) {
        $orderString = $order ? $order : 'type_id ASC';
        $whereString = $where ? $where . ' AND language_code = ?s' : 'WHERE language_code = ?s';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_types = "SELECT type_id FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_types = "SELECT type_id FROM ?n $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_types, Constants::PROPERTY_TYPES_TABLE, $languageCode, $start, $limit);
        $res = [];
        foreach ($res_array as $type) {
            $res[] = $this->getTypePropertyData($type['type_id'], $languageCode);
        }
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PROPERTY_TYPES_TABLE, $languageCode);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные одного типа свойства по его ID
     * @param int $type_id ID типа свойства, для которого нужно получить данные
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return array|null Массив с данными типа свойства или NULL, если тип свойства не найден
     */
    public function getTypePropertyData($type_id, $languageCode = ENV_DEF_LANG) {
        $sql_type = "SELECT * FROM ?n WHERE type_id = ?i AND language_code = ?s";
        $type_data = SafeMySQL::gi()->getRow($sql_type, Constants::PROPERTY_TYPES_TABLE, $type_id, $languageCode);
        if (!$type_data) {
            return null;
        }
        return $type_data;
    }

    /**
     * Обновляет данные типа свойства в таблице типов свойств
     * @param array $property_type_data Ассоциативный массив с данными типа свойства для обновления
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return int|bool ID обновленного типа свойства или false в случае неудачи
     */
    public function updatePropertyTypeData($property_type_data = [], $languageCode = ENV_DEF_LANG) {
        $property_type_data = SafeMySQL::gi()->filterArray($property_type_data, SysClass::ee_getFieldsTable(Constants::PROPERTY_TYPES_TABLE));
        $property_type_data = array_map('trim', $property_type_data);
        $property_type_data['language_code'] = $languageCode;  // добавлено
        if (empty($property_type_data['name'])) {
            return false;
        }
        if (!empty($property_type_data['type_id']) && $property_type_data['type_id'] != 0) {
            $type_id = $property_type_data['type_id'];
            unset($property_type_data['type_id']); // Удаляем type_id из массива данных, чтобы избежать его обновление
            $sql = "UPDATE ?n SET ?u WHERE type_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_TYPES_TABLE, $property_type_data, $type_id);
            return $result ? $type_id : false;
        } else {
            unset($property_type_data['type_id']);
        }
        // Проверяем уникальность имени в рамках одного языка
        $existingType = SafeMySQL::gi()->getRow(
                "SELECT type_id FROM ?n WHERE name = ?s AND language_code = ?s",
                Constants::PROPERTY_TYPES_TABLE,
                $property_type_data['name'], $languageCode
        );
        if ($existingType) {
            return false;
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_TYPES_TABLE, $property_type_data);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удалит тип свойства
     * @param type $type_id
     */
    public function typePropertiesDelete(int $type_id) {
        try {
            $sql = 'SELECT 1 FROM ?n WHERE type_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::PROPERTIES_TABLE, $type_id)) {
                return ['error' => 'Нельзя удалить тип свойства, так как он используется!'];
            }
            $sql_delete = "DELETE FROM ?n WHERE type_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::PROPERTY_TYPES_TABLE, $type_id);
            return $result ? [] : ['error' => 'Ошибка при выполнении запроса DELETE'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Удалит свойство
     * @param type $property_id
     */
    public function propertyDelete(int $property_id) {
        try {
            $sql = 'SELECT 1 FROM ?n WHERE property_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::PROPERTY_VALUES_TABLE, $property_id)) {
                return ['error' => 'Нельзя удалить свойство, так как оно используется!'];
            }
            $sql_delete = "DELETE FROM ?n WHERE property_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::PROPERTIES_TABLE, $property_id);
            return $result ? [] : ['error' => 'Ошибка при выполнении запроса DELETE'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getAllPropertySetsData($short = true, array $select = [], string $languageCode = ENV_DEF_LANG): array|bool {
        if ($short) {
            return SafeMySQL::gi()->getInd('set_id', 'SELECT set_id FROM ?n WHERE language_code = ?s', Constants::PROPERTY_SETS_TABLE, $languageCode);
        } else {
            if (!count($select)) {
                $select = ['*'];
            }
            return SafeMySQL::gi()->getInd('set_id', 'SELECT ' . implode(',', $select) . ' FROM ?n WHERE language_code = ?s', Constants::PROPERTY_SETS_TABLE, $languageCode);
        }
    }

    /**
     * Получает данные наборов свойств с учетом параметров сортировки, фильтрации и пагинации
     * @param string $order Параметр для сортировки результатов (по умолчанию 'set_id ASC')
     * @param string|null $where Условие для фильтрации результатов (по умолчанию NULL)
     * @param int $start Начальный индекс для пагинации результатов (по умолчанию 0)
     * @param int $limit Максимальное количество результатов для возврата (по умолчанию 100)
     * @param string $languageCode Код языка в формате ISO 3166-2. По умолчанию используется значение ENV_DEF_LANG
     * @return array Массив, содержащий данные наборов свойств и общее количество наборов свойств
     */
    public function getPropertySetsData(bool|string $order = 'set_id ASC', bool|string $where = false, int $start = 0, int $limit = 100, string $languageCode = ENV_DEF_LANG): array {
        $orderString = $order ? $order : 'set_id ASC';
        $whereString = $where ? "WHERE " . $where . " AND language_code = ?s" : "WHERE language_code = ?s";
        $start = $start ? $start : 0;

        // Запрос для получения идентификаторов наборов свойств
        $sql_sets = "SELECT set_id FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        $res_array = SafeMySQL::gi()->getAll($sql_sets, Constants::PROPERTY_SETS_TABLE, $languageCode, $start, $limit);
        $res = [];

        // Получаем данные каждого набора свойств
        foreach ($res_array as $set) {
            $data_set = $this->getPropertySetData($set['set_id'], $languageCode);
            if (!$data_set) {
                continue;
            }
            $res[$set['set_id']] = $data_set;
        }

        // Запрос для получения общего количества наборов свойств
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PROPERTY_SETS_TABLE, $languageCode);

        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные одного набора свойств по его ID
     * @param int $set_id ID набора свойств, для которого нужно получить данные
     * @return array|null Массив с данными набора свойств или NULL, если набор свойств не найден
     */
    public function getPropertySetData(int $set_id): bool|array {
        // Получаем основные данные набора свойств
        $sql_set = 'SELECT * FROM ?n WHERE set_id = ?i';
        $set_data = SafeMySQL::gi()->getRow($sql_set, Constants::PROPERTY_SETS_TABLE, $set_id);
        if (!$set_data) {
            return null;
        }
        // Получаем свойства, связанные с этим набором
        $sql_properties = '
        SELECT p.property_id as p_id, p.name, p.default_values, p.is_multiple, p.is_required, p.sort
        FROM ?n p
        JOIN ?n ps2p ON p.property_id = ps2p.property_id
        WHERE ps2p.set_id = ?i
        ';
        $properties = SafeMySQL::gi()->getInd('p_id', $sql_properties, Constants::PROPERTIES_TABLE, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $set_id);
        // Добавляем свойства к данным набора
        $set_data['properties'] = $properties;
        return $set_data;
    }

    /**
     * Обновляет данные набора свойств в таблице наборов свойств
     * @param array $property_set_data Ассоциативный массив с данными набора свойств для обновления
     * @param string $languageCode Код языка в формате ISO 3166-2. По умолчанию используется значение ENV_DEF_LANG
     * @return int|bool ID обновленного набора свойств или false в случае неудачи
     */
    public function updatePropertySetData(array $property_set_data = [], string $languageCode = ENV_DEF_LANG): int|bool {
        // Фильтруем данные по полям таблицы набора свойств
        $property_set_data = SafeMySQL::gi()->filterArray($property_set_data, SysClass::ee_getFieldsTable(Constants::PROPERTY_SETS_TABLE));
        $property_set_data = array_map('trim', $property_set_data);

        // Проверка обязательного поля 'name'
        if (empty($property_set_data['name'])) {
            return false;
        }

        // Добавляем язык в массив данных
        $property_set_data['language_code'] = $languageCode;

        // Если есть set_id, обновляем существующую запись
        if (!empty($property_set_data['set_id']) && $property_set_data['set_id'] != 0) {
            $set_id = $property_set_data['set_id'];
            unset($property_set_data['set_id']); // Удаляем set_id, чтобы избежать его обновления

            $sql = "UPDATE ?n SET ?u WHERE set_id = ?i AND language_code = ?s";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_SETS_TABLE, $property_set_data, $set_id, $languageCode);
            return $result ? $set_id : false;
        } else {
            unset($property_set_data['set_id']);
        }

        // Проверяем уникальность имени набора свойств для указанного языка
        $existingSet = SafeMySQL::gi()->getRow(
                "SELECT set_id FROM ?n WHERE name = ?s AND language_code = ?s",
                Constants::PROPERTY_SETS_TABLE,
                $property_set_data['name'],
                $languageCode
        );

        // Если такой набор уже существует, возвращаем false
        if ($existingSet) {
            return false;
        }

        // Вставляем новую запись
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_SETS_TABLE, $property_set_data);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удалит набор свойств
     * @param int $set_id - ID набора свойств
     * @return array - Массив с результатом или ошибкой
     */
    public function property_set_delete($set_id) {
        try {
            // Проверяем, используется ли данный набор свойств в связи с типами категорий
            $sql = 'SELECT 1 FROM ?n WHERE set_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $set_id)) {
                return ['error' => 'Нельзя удалить набор свойств, так как он связан с типами категорий!'];
            }
            // Проверяем, используется ли данный набор свойств в связи со свойствами
            $sql = 'SELECT 1 FROM ?n WHERE set_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $set_id)) {
                return ['error' => 'Нельзя удалить набор свойств, так как он связан со свойствами!'];
            }
            // Проверяем, используется ли данный набор свойств в связи с типами категорий
            $sql = 'SELECT 1 FROM ?n WHERE set_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $set_id)) {
                return ['error' => 'Нельзя удалить набор свойств, так как он связан с категориями!'];
            }
            // Если проверки прошли успешно, удаляем набор свойств
            $sql_delete = "DELETE FROM ?n WHERE set_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::PROPERTY_SETS_TABLE, $set_id);
            return $result ? [] : ['error' => 'Ошибка при выполнении запроса DELETE'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Удалить все свойства для указанного набора свойств
     * @param int $set_id ID набора свойств
     */
    public function deletePreviousProperties(int $set_id): void {
        $delete_previous_properties = "DELETE FROM ?n WHERE set_id = ?i";
        SafeMySQL::gi()->query($delete_previous_properties, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $set_id);
    }

    /**
     * Удалит значение свойств для сущности
     * @param int $value_id
     */
    public function deletePropertyValues(int|array $value_id) {
        if (!is_array($value_id)) {
            $value_id = [$value_id];
        }
        $sql = 'DELETE FROM ?n WHERE value_id IN (?a)';
        SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $value_id);
    }

    /**
     * Добавить выбранные свойства в таблицу связей наборов свойств и свойств
     * @param int   $set_id ID набора свойств
     * @param array $selected_properties Массив с ID свойств
     */
    public function addPropertiesToSet(int $set_id, array $selected_properties): void {
        foreach ($selected_properties as $property_id) {
            $insert_query = "INSERT INTO ?n (set_id, property_id) VALUES (?i, ?i)";
            SafeMySQL::gi()->query($insert_query, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $set_id, $property_id);
        }
    }

    /**
     * Получает значения свойств для определенной сущности
     * @param int $entity_id Идентификатор сущности, для которой требуется получить свойства
     * @param string $entity_type Тип сущности ('category', 'entity' и т.д.)
     * @param int $property_id ID свойства
     * @param int $set_id ID набора свойств
     * @param string $languageCode Код языка, по умолчанию 'RU'
     * @return array Возвращает массив значений свойств для сущности или пустой массив, если свойства не найдены
     */
    public function getPropertyValuesForEntity(int $entity_id, string $entity_type, int $property_id, int $set_id, string $languageCode = ENV_DEF_LANG): array {
        $sql = 'SELECT * FROM ?n WHERE entity_id = ?i AND entity_type = ?s AND property_id = ?i AND set_id = ?i AND language_code = ?s';
        $property = SafeMySQL::gi()->getRow($sql, Constants::PROPERTY_VALUES_TABLE, $entity_id, $entity_type, $property_id, $set_id, $languageCode);
        if (!$property || !$property['property_values'] || $property['property_values'] == 'null') {
            return [];
        }
        // Преобразование значений JSON в массивы PHP, если необходимо
        if (is_string($property['property_values'])) {
            $property['property_values'] = json_decode($property['property_values'], true);
        }
        return $property;
    }

    /**
     * Вернёт массив всех значений свойств сущности
     * @param int $entity_ids Идентификатор сущности, для которой требуется получить свойства
     * @param string $entity_type Тип сущности ('category', 'page' и т.д.)
     * @param string $languageCode Код языка, по умолчанию 'RU'
     * @return array Возвращает массив значений свойств для сущности или пустой массив, если свойства не найдены
     */
    public function getPropertiesValuesForEntity(int|array $entity_ids, string $entity_type, string $languageCode = ENV_DEF_LANG): array {
        if (!is_array($entity_ids)) {
            $entity_ids = [$entity_ids];
        }
        $sql = 'SELECT * FROM ?n WHERE entity_id IN (?a) AND entity_type = ?s AND language_code = ?s';
        return SafeMySQL::gi()->getAll($sql, Constants::PROPERTY_VALUES_TABLE, $entity_ids, $entity_type, $languageCode);
    }

    /**
     * Удалит все значения свойств сущности
     * @param int $entity_ids Идентификатор сущности, для которой требуется получить свойства
     * @param string $entity_type Тип сущности ('category', 'entity' и т.д.)
     * @param string $languageCode Код языка, по умолчанию 'RU'
     * @return array Возвращает массив значений свойств для сущности или пустой массив, если свойства не найдены
     */
    public function deleteAllpropertiesValuesEntity(int|array $entity_ids, string $entity_type, string $languageCode = ENV_DEF_LANG): void {
        if (!is_array($entity_ids)) {
            $entity_ids = [$entity_ids];
        }
        $sql = 'DELETE FROM ?n WHERE entity_id IN (?a) AND entity_type = ?s AND language_code = ?s';
        SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $entity_ids, $entity_type, $languageCode);
    }

    /**
     * Ищет уникальный код в JSON-поле `default_values` в таблице
     * Эта функция выполняет поиск записи в таблице, где JSON-поле `default_values` 
     * содержит объект с полем `unique_code` и указанным значением
     * @param string $uniqueCode Уникальный код для поиска в JSON-поле `default_values`
     * @return array|null Возвращает массив с найденной записью, если код найден, или null, если код не найден
     */
    public function findUniqueCodeInPropertiesTable(string $uniqueCode): ?array {
        $sql = "SELECT * FROM ?n WHERE JSON_CONTAINS(default_values, JSON_OBJECT('unique_code', ?s))";
        $result = SafeMySQL::gi()->getRow($sql, Constants::PROPERTIES_TABLE, $uniqueCode);
        if ($result) {
            return $result;
        }
        return null;
    }

    /**
     * Сохраняет или обновляет значение свойства для сущностей
     * @param mixed $propertyData Данные свойства для сохранения или обновления
     * @param string $languageCode Код языка для данных свойства, по умолчанию 'RU'
     * @return mixed Возвращает идентификатор записи в случае успеха или false в случае ошибки
     */
    public function updatePropertiesValueEntities(array $propertyData = [], string $languageCode = ENV_DEF_LANG): bool|int {
        $propertyData = SafeMySQL::gi()->filterArray($propertyData, SysClass::ee_getFieldsTable(Constants::PROPERTY_VALUES_TABLE));
        $propertyData = SysClass::ee_trimArrayValues($propertyData);
        $propertyData['language_code'] = $languageCode;
        // Проверка наличия и валидность ключевых полей
        if (empty($propertyData['entity_id']) || empty($propertyData['property_id']) || empty($propertyData['entity_type']) || empty($propertyData['property_values']) || empty($propertyData['set_id'])) {
            return false; // Все ключевые поля обязательны
        }
        // Преобразование values в JSON, если это необходимо
        if (is_array($propertyData['property_values'])) {
            $propertyData['property_values'] = json_encode($propertyData['property_values'], JSON_UNESCAPED_UNICODE);
            if (!$propertyData['property_values']) {
                return false;
            }
        }
        $value_id = isset($propertyData['value_id']) ? $propertyData['value_id'] : 'false';
        unset($propertyData['value_id']); // Удаление value_id из массива данных, чтобы избежать его включения в обновление
        if (ctype_digit($value_id)) {
            $value_id = (int) $value_id;
        } else {
            $value_id = null;
        }
        if (!empty($value_id)) {
            $sql = "UPDATE ?n SET ?u WHERE value_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $propertyData, $value_id);
            return $result ? $value_id : false;
        } else {
            $sql = "INSERT INTO ?n SET ?u";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $propertyData);
            return $result ? SafeMySQL::gi()->insertId() : false;
        }
    }
}
