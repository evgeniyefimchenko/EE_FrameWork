<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;
use classes\system\Hook;
use classes\system\ErrorLogger;

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
     * @param int $propertyId ID свойства, для которого нужно получить данные
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @return array|null Массив с данными свойства или NULL, если свойство не найдено
     */
    public function getPropertyData($propertyId, $languageCode = ENV_DEF_LANG, $status = Constants::ALL_STATUS) {
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
                $propertyId,
                $languageCode
        );
        if (!$propertyData) {
            return null;
        }
        return $propertyData;
    }

    /**
     * Вернёт type_id свойства по его property_id
     * @param int $propertyId
     * @return int|null
     */
    public function getTypeIdByPropertyId(int $propertyId): ?int {
        return SafeMySQL::gi()->getOne('SELECT type_id FROM ?n WHERE property_id = ?i', Constants::PROPERTIES_TABLE, $propertyId);
    }

    /**
     * Обновляет данные свойства в таблице свойств
     * @param array $propertyData Ассоциативный массив с данными свойства для обновления
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return int|bool ID обновленного свойства или false в случае неудачи
     */
    public function updatePropertyData(array $propertyData = [], string $languageCode = ENV_DEF_LANG): int|bool {
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
            $message = 'Error: property_data name or type_id';
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'property', $propertyData);
            return false;
        }
        if (!empty($propertyData['property_id'])) {
            $propertyId = $propertyData['property_id'];
            if ($this->isExistSetsWithProperty($propertyId)) {
                unset($propertyData['type_id']);
            }
            unset($propertyData['property_id']); // Удаляем property_id из массива данных, чтобы избежать его обновление
            $sql = "UPDATE ?n SET ?u WHERE property_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTIES_TABLE, $propertyData, $propertyId);
            return $result ? $propertyId : false;
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
            $message = 'Error: existingProperty';
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'property', $propertyData);
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
        $property_types = SafeMySQL::gi()->getInd('type_id', $sql, Constants::PROPERTY_TYPES_TABLE, $status, $languageCode);
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
    public function getTypePropertiesData(string $order = 'type_id ASC', $where = null, int $start = 0, int $limit = 100, string $languageCode = ENV_DEF_LANG) {
        $orderString = $order ? $order : 'type_id ASC';
        $whereString = $where ? $where . ' AND language_code = ?s' : 'WHERE language_code = ?s';
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
     * @param int $typeId ID типа свойства, для которого нужно получить данные
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return array|null Массив с данными типа свойства или NULL, если тип свойства не найден
     */
    public function getTypePropertyData(int $typeId, string $languageCode = ENV_DEF_LANG) {
        $sql = "SELECT * FROM ?n WHERE type_id = ?i AND language_code = ?s";
        $typeData = SafeMySQL::gi()->getRow($sql, Constants::PROPERTY_TYPES_TABLE, $typeId, $languageCode);
        if (!$typeData) {
            return null;
        }
        return $typeData;
    }

    /**
     * Используется ли тип свойства у любого свойства
     * @param int $typeId
     * @return bool
     */
    public function isExistPropertiesWithType(int $typeId): bool {
        $sql = 'SELECT property_id FROM ?n WHERE type_id = ?i';
        $result = SafeMySQL::gi()->getOne($sql, Constants::PROPERTIES_TABLE, $typeId) ? true : false;
        return $result;
    }

    /**
     * Используется ли свойство в каком то наборе свойств
     * @param int $propertyId
     * @return bool
     */
    public function isExistSetsWithProperty(int $propertyId): bool {
        $sql = 'SELECT set_id FROM ?n WHERE property_id = ?i';
        $result = SafeMySQL::gi()->getOne($sql, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $propertyId) ? true : false;
        return $result;
    }

    /**
     * Используется ли набор свойств в любом типе категорий
     * @param int $setId
     * @return bool
     */
    public function isExistCategoryTypeWithSet(int $setId): bool {
        $sql = 'SELECT type_id FROM ?n WHERE set_id = ?i';
        $result = SafeMySQL::gi()->getOne($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $setId) ? true : false;
        return $result;
    }

    /**
     * Обновляет данные типа свойства в таблице типов свойств
     * @param array $propertyTypeData Ассоциативный массив с данными типа свойства для обновления
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return int|bool ID обновленного типа свойства или false в случае неудачи
     */
    public function updatePropertyTypeData(array $propertyTypeData = [], string $languageCode = ENV_DEF_LANG): int|bool {
        $propertyTypeData = SafeMySQL::gi()->filterArray($propertyTypeData, SysClass::ee_getFieldsTable(Constants::PROPERTY_TYPES_TABLE));
        $propertyTypeData = array_map('trim', $propertyTypeData);
        $propertyTypeData['language_code'] = $languageCode;  // добавлено
        if (empty($propertyTypeData['name'])) {
            return false;
        }
        if (!empty($propertyTypeData['type_id']) && $propertyTypeData['type_id'] != 0) {
            $typeId = $propertyTypeData['type_id'];
            if ($this->isExistPropertiesWithType($typeId) && !empty($propertyTypeData['fields'])) {
                unset($propertyTypeData['fields']);
            }
            unset($propertyTypeData['type_id']); // Удаляем type_id из массива данных, чтобы избежать его обновление
            $sql = "UPDATE ?n SET ?u WHERE type_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_TYPES_TABLE, $propertyTypeData, $typeId);
            return $result ? $typeId : false;
        } else {
            unset($propertyTypeData['type_id']);
        }
        // Проверяем уникальность имени в рамках одного языка
        $existingType = SafeMySQL::gi()->getRow(
                "SELECT type_id FROM ?n WHERE name = ?s AND language_code = ?s",
                Constants::PROPERTY_TYPES_TABLE,
                $propertyTypeData['name'], $languageCode
        );
        if ($existingType) {
            return false;
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_TYPES_TABLE, $propertyTypeData);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удалит тип свойства
     * @param type $typeId
     */
    public function typePropertiesDelete(int $typeId) {
        try {
            $sql = 'SELECT 1 FROM ?n WHERE type_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::PROPERTIES_TABLE, $typeId)) {
                return ['error' => 'Нельзя удалить тип свойства, так как он используется!'];
            }
            $sql_delete = "DELETE FROM ?n WHERE type_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::PROPERTY_TYPES_TABLE, $typeId);
            return $result ? [] : ['error' => 'Ошибка при выполнении запроса DELETE'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Удалит свойство
     * @param type $propertyId
     */
    public function propertyDelete(int $propertyId) {
        try {
            $sql = 'SELECT 1 FROM ?n WHERE property_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::PROPERTY_VALUES_TABLE, $propertyId)) {
                return ['error' => 'Нельзя удалить свойство, так как оно используется!'];
            }
            $sql_delete = "DELETE FROM ?n WHERE property_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::PROPERTIES_TABLE, $propertyId);
            return $result ? [] : ['error' => 'Ошибка при выполнении запроса DELETE'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Вернёт данные всех наборов свойств
     * @param bool $short - вернёт только set_id
     * @param array $select - массив полей БД
     * @param string $languageCode Код языка в формате ISO 3166-2. По умолчанию используется значение ENV_DEF_LANG
     * @return array|bool
     */
    public function getAllPropertySetsData(bool $short = true, array $select = [], string $languageCode = ENV_DEF_LANG): array|bool {
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
     * @param int $setId ID набора свойств, для которого нужно получить данные
     * @return array|null Массив с данными набора свойств или NULL, если набор свойств не найден
     */
    public function getPropertySetData(int $setId, string $languageCode = ENV_DEF_LANG): bool|array {
        // Получаем основные данные набора свойств
        $sql_set = 'SELECT * FROM ?n WHERE set_id = ?i';
        $setData = SafeMySQL::gi()->getRow($sql_set, Constants::PROPERTY_SETS_TABLE, $setId);
        if (!$setData) {
            return false;
        }
        // Получаем свойства, связанные с этим набором
        $sql_properties = '
        SELECT p.property_id as property_id, p.name, p.default_values, p.is_multiple, p.is_required, p.sort, p.entity_type as property_entity_type
        FROM ?n p
        JOIN ?n ps2p ON p.property_id = ps2p.property_id
        WHERE ps2p.set_id = ?i';
        $properties = SafeMySQL::gi()->getInd('property_id', $sql_properties, Constants::PROPERTIES_TABLE, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $setId);
        // Добавляем свойства к данным набора
        $setData['properties'] = $properties;
        return $setData;
    }

    /**
     * Обновляет данные набора свойств в таблице наборов свойств
     * @param array $propertySetData Ассоциативный массив с данными набора свойств для обновления
     * @param string $languageCode Код языка в формате ISO 3166-2. По умолчанию используется значение ENV_DEF_LANG
     * @return int|bool ID обновленного набора свойств или false в случае неудачи
     */
    public function updatePropertySetData(array $propertySetData = [], string $languageCode = ENV_DEF_LANG): int|bool {        
        // Фильтруем данные по полям таблицы набора свойств
        $propertySetData = SafeMySQL::gi()->filterArray($propertySetData, SysClass::ee_getFieldsTable(Constants::PROPERTY_SETS_TABLE));
        $propertySetData = array_map('trim', $propertySetData);
        // Проверка обязательного поля 'name'
        if (empty($propertySetData['name'])) {
            return false;
        }
        // Добавляем язык в массив данных
        $propertySetData['language_code'] = $languageCode;
        // Если есть set_id, обновляем существующую запись
        if (!empty($propertySetData['set_id']) && $propertySetData['set_id'] != 0) {
            $setId = $propertySetData['set_id'];
            unset($propertySetData['set_id']); // Удаляем set_id, чтобы избежать его обновления
            $sql = "UPDATE ?n SET ?u WHERE set_id = ?i AND language_code = ?s";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_SETS_TABLE, $propertySetData, $setId, $languageCode);
            return $result ? $setId : false;
        } else {
            unset($propertySetData['set_id']);
        }
        // Проверяем уникальность имени набора свойств для указанного языка
        $existingSet = SafeMySQL::gi()->getRow(
                "SELECT set_id FROM ?n WHERE name = ?s AND language_code = ?s",
                Constants::PROPERTY_SETS_TABLE,
                $propertySetData['name'],
                $languageCode
        );
        // Если такой набор уже существует, возвращаем false
        if ($existingSet) {
            return new ErrorLogger('Набор уже существует, измените имя!', __FUNCTION__, 'PropertySet_validation', $existingSet);
        }
        // Вставляем новую запись
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_SETS_TABLE, $propertySetData);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удалит набор свойств
     * @param int $setId - ID набора свойств
     * @return array - Массив с результатом или ошибкой
     */
    public function propertySetDelete($setId) {
        try {
            // Проверяем, используется ли данный набор свойств в связи с типами категорий
            $sql = 'SELECT 1 FROM ?n WHERE set_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $setId)) {
                return ['error' => 'Нельзя удалить набор свойств, так как он связан с типами категорий!'];
            }
            // Проверяем, используется ли данный набор свойств в связи со свойствами
            $sql = 'SELECT 1 FROM ?n WHERE set_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $setId)) {
                return ['error' => 'Нельзя удалить набор свойств, так как он связан со свойствами!'];
            }
            // Проверяем, используется ли данный набор свойств в связи с типами категорий
            $sql = 'SELECT 1 FROM ?n WHERE set_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $setId)) {
                return ['error' => 'Нельзя удалить набор свойств, так как он связан с категориями!'];
            }
            // Если проверки прошли успешно, удаляем набор свойств
            $sql_delete = "DELETE FROM ?n WHERE set_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::PROPERTY_SETS_TABLE, $setId);
            return $result ? [] : ['error' => 'Ошибка при выполнении запроса DELETE'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Удалить все свойства для указанного набора свойств
     * @param int $setId ID набора свойств
     */
    public function deletePreviousProperties(int $setId): void {
        $delete_previous_properties = "DELETE FROM ?n WHERE set_id = ?i";
        SafeMySQL::gi()->query($delete_previous_properties, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $setId);
    }

    /**
     * Удалит значение свойств для сущности
     * @param int $valueId
     */
    public function deletePropertyValues(int|array $valueId) {
        if (!is_array($valueId)) {
            $valueId = [$valueId];
        }
        $sql = 'DELETE FROM ?n WHERE value_id IN (?a)';
        SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $valueId);
    }

    /**
     * Добавить выбранные свойства в таблицу связей наборов свойств и свойств
     * @param int   $setId ID набора свойств
     * @param array $selectedProperties Массив с ID свойств
     */
    public function addPropertiesToSet(int $setId, array $selectedProperties): void {
        if (empty($selectedProperties)) {
            return;
        }
        foreach ($selectedProperties as $propertyId) {
            $insertQuery = "INSERT INTO ?n (set_id, property_id) VALUES (?i, ?i)";
            SafeMySQL::gi()->query($insertQuery, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, $setId, $propertyId);
        }
    }

    /**
     * Получает значения свойств для определенной сущности
     * @param int $entity_id Идентификатор сущности, для которой требуется получить свойства
     * @param string $entityType Тип сущности ('category', 'entity' и т.д.)
     * @param int $propertyId ID свойства
     * @param int $setId ID набора свойств
     * @param string $languageCode Код языка, по умолчанию 'RU'
     * @return array Возвращает массив значений свойств для сущности или пустой массив, если свойства не найдены
     */
    public function getPropertyValuesForEntity(int $entity_id, string $entityType, int $propertyId, int $setId, string $languageCode = ENV_DEF_LANG): array {
        $sql = 'SELECT * FROM ?n WHERE entity_id = ?i AND entity_type = ?s AND property_id = ?i AND set_id = ?i AND language_code = ?s';
        $property = SafeMySQL::gi()->getRow($sql, Constants::PROPERTY_VALUES_TABLE, $entity_id, $entityType, $propertyId, $setId, $languageCode);
        if (!$property || !$property['property_values'] || $property['property_values'] == 'null') {
            return [];
        }
        // Преобразование значений JSON в массивы PHP, если необходимо
        if (is_string($property['property_values'])) {
            $property['property_values'] = json_decode($property['property_values'], true);
        }
        $property['fields'] = $property['property_values'];
        unset($property['property_values']);
        return $property;
    }

    /**
     * Вернёт массив всех значений свойств сущности
     * @param int $entityIds Идентификатор сущности, для которой требуется получить свойства
     * @param string $entityType Тип сущности ('category', 'page' и т.д.)
     * @param string $languageCode Код языка, по умолчанию 'RU'
     * @return array Возвращает массив значений свойств для сущности или пустой массив, если свойства не найдены
     */
    public function getPropertiesValuesForEntity(int|array $entityIds, string $entityType, string $languageCode = ENV_DEF_LANG): array {
        if (!is_array($entityIds)) {
            $entityIds = [$entityIds];
        }
        $sql = 'SELECT * FROM ?n WHERE entity_id IN (?a) AND entity_type = ?s AND language_code = ?s';
        return SafeMySQL::gi()->getAll($sql, Constants::PROPERTY_VALUES_TABLE, $entityIds, $entityType, $languageCode);
    }

    /**
     * Удаляет все значения свойств для указанных сущностей(и) одного типа и языка
     * @param int|array $entityIds Идентификатор(ы) сущности
     * @param string $entityType Тип сущности ('category', 'page' и тд)
     * @param string $languageCode Код языка (по умолчанию ENV_DEF_LANG)
     * @return array|ErrorLogger Возвращает пустой массив в случае успеха или объект ErrorLogger с информацией об ошибке
     */
    public function deleteAllpropertiesValuesEntity(int|array $entityIds, string $entityType, string $languageCode = ENV_DEF_LANG): array|ErrorLogger {
        if (!is_array($entityIds)) {
            $entityIds = [$entityIds];
        }
        $entityIds = array_filter(array_map('intval', $entityIds), fn($id) => $id > 0);
        if (empty($entityIds)) {
            return new ErrorLogger('Не переданы валидные ID сущностей', __FUNCTION__, 'deleteProperties');
        }
        if (empty(trim($entityType))) {
            return new ErrorLogger('Не указан тип сущности', __FUNCTION__, 'deleteProperties');
        }
        if (empty(trim($languageCode))) {
            return new ErrorLogger('Не указан код языка', __FUNCTION__, 'deleteProperties');
        }
        try {
            $sql = 'DELETE FROM ?n WHERE entity_id IN (?a) AND entity_type = ?s AND language_code = ?s';
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $entityIds, $entityType, $languageCode);
            return [];
        } catch (\Throwable $e) {
            return new ErrorLogger('Исключение при удалении свойств: ' . $e->getMessage(), __FUNCTION__, 'deleteProperties', [
                'entityIds' => $entityIds,
                'entityType' => $entityType,
                'languageCode' => $languageCode,
                'exception' => $e
            ]);
        }
    }

    /**
     * Сохраняет или обновляет значение свойства для сущностей
     * @param mixed $propertyData Данные свойства для сохранения или обновления
     * @param string $languageCode Код языка для данных свойства, по умолчанию 'RU'
     * @return mixed Возвращает идентификатор записи в случае успеха или false в случае ошибки
     */
    public function updatePropertiesValueEntities(array $propertyData = [], string $languageCode = ENV_DEF_LANG): bool|int {
        $propertyData['property_values'] = !empty($propertyData['fields']) ? $propertyData['fields'] : (!empty($propertyData['property_values']) ? $propertyData['property_values'] : false);
        $propertyData = SafeMySQL::gi()->filterArray($propertyData, SysClass::ee_getFieldsTable(Constants::PROPERTY_VALUES_TABLE));
        $propertyData = SysClass::ee_trimArrayValues($propertyData);
        $propertyData['language_code'] = $languageCode;
        // Проверка наличия и валидность ключевых полей
        if (empty($propertyData['entity_id']) || empty($propertyData['property_id']) || empty($propertyData['entity_type']) || empty($propertyData['property_values']) || empty($propertyData['set_id'])) {
            return false; // Все ключевые поля обязательны
        }
        // Если свойство не предназначено для типа сущности то выходим
        $propEntityType = SafeMySQL::gi()->getOne('SELECT entity_type FROM ?n WHERE property_id = ?i', Constants::PROPERTIES_TABLE, $propertyData['property_id']);
        if ($propertyData['entity_type'] !== $propEntityType && $propEntityType !== 'all') {
            return false;
        }
        // Преобразование values в JSON, если это необходимо
        if (is_array($propertyData['property_values'])) {
            $propertyData['property_values'] = json_encode($propertyData['property_values'], JSON_UNESCAPED_UNICODE);
            if (!$propertyData['property_values']) {
                return false;
            }
        }
        $valueId = isset($propertyData['value_id']) ? $propertyData['value_id'] : 'false';
        unset($propertyData['value_id']); // Удаление value_id из массива данных, чтобы избежать его включения в обновление
        if (ctype_digit($valueId)) {
            $valueId = (int) $valueId;
        } else {
            $valueId = null;
        }
        Hook::run('preUpdatePropertiesValueEntities', $valueId, $propertyData);
        if (!empty($valueId)) {
            $sql = "UPDATE ?n SET ?u WHERE value_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $propertyData, $valueId);
            $action = 'update';
        } else {
            $sql = "INSERT INTO ?n SET ?u";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $propertyData);
            $valueId = SafeMySQL::gi()->insertId();
            $action = 'insert';
        }
        Hook::run('postUpdatePropertiesValueEntities', $valueId, $propertyData, $action);
        return !empty($valueId) ? $valueId : false;
    }

    /**
     * Записывает дефолтные значения сущности в её текущие
     * @param string $entityType Тип сущности 'category', 'page'
     * @param int $entityId ID сущности
     * @param array $entityData Данные сущности
     * @return bool
     */
    public function createPropertiesValueEntities(string $entityType, int $entityId, array $entityData = []): void {
        $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        $typeId = $objectModelCategories->getCategoryTypeId($entityData['category_id']);
        $setIds = $objectModelCategoriesTypes->getCategoriesTypeSetsData($typeId);
        foreach ($setIds as $setId) {
            $setData = $this->getPropertySetData($setId);
            foreach ($setData['properties'] as $propertyId => $property) {
                if ($property['property_entity_type'] == $entityType || $property['property_entity_type'] == 'all') {
                    $propertyData = [
                        'entity_id' => $entityId,
                        'property_id' => $propertyId,
                        'entity_type' => $entityType,
                        'set_id' => $setId,
                        'property_values' => $property['default_values']
                    ];
                    $this->updatePropertiesValueEntities($propertyData);
                }
            }
        }
    }

    /**
     * Удаляет связи между набором и конкретными свойствами
     * @param int   $setId        ID набора свойств
     * @param array $propertyIds  Массив ID свойств для удаления из набора
     */
    public function deletePropertiesFromSet(int $setId, array $propertyIds): void {
        if (empty($propertyIds)) {
            return;
        }
        SafeMySQL::gi()->query(
                "DELETE FROM ?n WHERE set_id = ?i AND property_id IN (?a)",
                Constants::PROPERTY_SET_TO_PROPERTIES_TABLE,
                $setId,
                $propertyIds
        );
    }
}
