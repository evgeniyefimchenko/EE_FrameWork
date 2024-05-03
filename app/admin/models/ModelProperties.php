<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;

/**
 * Модель работы со свойствами
 */
class ModelProperties {

    /**
     * Получает все свойства на основе статуса и языкового кода.     
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @param string $language_code Языковой код для фильтрации. По умолчанию используется константа ENV_DEF_LANG.
     * @return array Возвращает массив всех свойств, соответствующих заданным критериям.
     */
    public function get_all_properties($status = Constants::ALL_STATUS, $language_code = ENV_DEF_LANG, $short = true) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        if ($short) {
            $sql_properties = "SELECT * FROM ?n WHERE status IN (?a) AND language_code = ?s";
            $res = SafeMySQL::gi()->getAll($sql_properties, Constants::PROPERTIES_TABLE, $status, $language_code);
            return $res;
        } else {
            $return = [];
            $sql_properties = "SELECT property_id FROM ?n WHERE status IN (?a) AND language_code = ?s";
            $res = SafeMySQL::gi()->getAll($sql_properties, Constants::PROPERTIES_TABLE, $status, $language_code);
            foreach ($res as $item) {
                $data_prop = $this->get_property_data($item['property_id'], $language_code, $status);
                if (!$data_prop)
                    continue;
                $return[] = $data_prop;
            }
            return $return;
        }
        return false;
    }

    /**
     * Получает данные свойств с учетом параметров сортировки, фильтрации и пагинации.
     * @param string $order Параметр для сортировки результатов (по умолчанию 'property_id ASC').
     * @param string|null $where Условие для фильтрации результатов (по умолчанию NULL).
     * @param int $start Начальный индекс для пагинации результатов (по умолчанию 0).
     * @param int $limit Максимальное количество результатов для возврата (по умолчанию 100).
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array Массив, содержащий данные свойств и общее количество свойств.
     */
    public function get_properties_data($order = 'property_id ASC', $where = null, $start = 0, $limit = 100, $language_code = ENV_DEF_LANG) {
        $orderString = $order ? $order : 'property_id ASC';
        $whereString = $where ? $where . ' AND language_code = ?s' : 'WHERE language_code = ?s';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_properties = "SELECT `property_id` FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_properties = "SELECT `property_id` FROM ?n $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_properties, Constants::PROPERTIES_TABLE, $language_code, $start, $limit);
        $res = [];
        foreach ($res_array as $property) {
            $data_prop = $this->get_property_data($property['property_id'], $language_code);
            if (!$data_prop)
                continue;
            $res[] = $data_prop;
        }
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PROPERTIES_TABLE, $language_code);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные одного свойства по его ID
     * @param int $property_id ID свойства, для которого нужно получить данные
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @return array|null Массив с данными свойства или NULL, если свойство не найдено
     */
    public function get_property_data($property_id, $language_code = ENV_DEF_LANG, $status = Constants::ALL_STATUS) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        $sql_property = 'SELECT p.*, pt.name as type_name, pt.fields as fields 
            FROM ?n AS p 
            LEFT JOIN ?n AS pt ON p.type_id = pt.type_id 
            WHERE pt.status IN (?a) AND p.property_id = ?i AND p.language_code = ?s';
        $property_data = SafeMySQL::gi()->getRow(
                $sql_property,
                Constants::PROPERTIES_TABLE,
                Constants::PROPERTY_TYPES_TABLE,
                $status,
                $property_id,
                $language_code
        );
        if (!$property_data) {
            return null;
        }
        return $property_data;
    }

    /**
     * Обновляет данные свойства в таблице свойств.
     * @param array $property_data Ассоциативный массив с данными свойства для обновления.
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return int|bool ID обновленного свойства или false в случае неудачи.
     */
    public function update_property_data($property_data = [], $language_code = ENV_DEF_LANG) {        
        $property_data = SafeMySQL::gi()->filterArray($property_data, SysClass::ee_get_fields_table(Constants::PROPERTIES_TABLE));
        if (is_array($property_data['default_values'])) {
            $property_data['default_values'] = json_encode($property_data['default_values'], JSON_UNESCAPED_UNICODE);
        } elseif (!SysClass::ee_isValidJson($property_data['default_values'])) {
            $property_data['default_values'] = '[]';
        }        
        $property_data = array_map('trim', $property_data);
        $property_data['is_multiple'] = isset($property_data['is_multiple']) && ($property_data['is_multiple'] == 'on' || $property_data['is_multiple'] == 1) ? 1 : 0;
        $property_data['is_required'] = isset($property_data['is_required']) && ($property_data['is_required'] == 'on' || $property_data['is_required'] == 1) ? 1 : 0;
        $property_data['language_code'] = $language_code;  // добавлено
        if (empty($property_data['name']) || !isset($property_data['type_id'])) {
            SysClass::pre_file('error', 'update_property_data', 'Error: property_data', $property_data);
            return false;
        }
        if (!empty($property_data['property_id']) && $property_data['property_id'] != 0) {
            $property_id = $property_data['property_id'];
            unset($property_data['property_id']); // Удаляем property_id из массива данных, чтобы избежать его обновление
            $sql = "UPDATE ?n SET ?u WHERE `property_id` = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTIES_TABLE, $property_data, $property_id);
            return $result ? $property_id : false;
        } else {
            unset($property_data['property_id']);
        }
        // Проверяем уникальность имени в рамках одного типа и языка
        $existingProperty = SafeMySQL::gi()->getRow(
                "SELECT `property_id` FROM ?n WHERE `name` = ?s AND `type_id` = ?i AND `language_code` = ?s",
                Constants::PROPERTIES_TABLE,
                $property_data['name'], $property_data['type_id'], $language_code
        );
        if ($existingProperty) {
            SysClass::pre_file('error', 'update_property_data', 'Error: existingProperty', $property_data);
            return false;
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTIES_TABLE, $property_data);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Получает все типы свойств из таблицы типов свойств.
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array Массив, содержащий все типы свойств, каждый из которых представлен ассоциативным массивом с данными типа свойства.
     */
    public function get_all_property_types($status = Constants::ALL_STATUS, $language_code = ENV_DEF_LANG) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        $sql = "SELECT * FROM ?n WHERE status IN (?a) AND language_code = ?s";
        $property_types = SafeMySQL::gi()->getAll($sql, Constants::PROPERTY_TYPES_TABLE, $status, $language_code);
        return $property_types;
    }

    /**
     * Получает данные типов свойств с учетом параметров сортировки, фильтрации и пагинации.
     * @param string $order Параметр для сортировки результатов (по умолчанию 'type_id ASC').
     * @param string|null $where Условие для фильтрации результатов (по умолчанию NULL).
     * @param int $start Начальный индекс для пагинации результатов (по умолчанию 0).
     * @param int $limit Максимальное количество результатов для возврата (по умолчанию 100).
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array Массив, содержащий данные типов свойств и общее количество типов свойств.
     */
    public function get_type_properties_data($order = 'type_id ASC', $where = null, $start = 0, $limit = 100, $language_code = ENV_DEF_LANG) {
        $orderString = $order ? $order : 'type_id ASC';
        $whereString = $where ? $where . ' AND language_code = ?s' : 'WHERE language_code = ?s';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_types = "SELECT `type_id` FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_types = "SELECT `type_id` FROM ?n $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_types, Constants::PROPERTY_TYPES_TABLE, $language_code, $start, $limit);
        $res = [];
        foreach ($res_array as $type) {
            $res[] = $this->get_type_property_data($type['type_id'], $language_code);
        }
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PROPERTY_TYPES_TABLE, $language_code);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные одного типа свойства по его ID.
     * @param int $type_id ID типа свойства, для которого нужно получить данные.
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array|null Массив с данными типа свойства или NULL, если тип свойства не найден.
     */
    public function get_type_property_data($type_id, $language_code = ENV_DEF_LANG) {
        $sql_type = "SELECT * FROM ?n WHERE `type_id` = ?i AND language_code = ?s";
        $type_data = SafeMySQL::gi()->getRow($sql_type, Constants::PROPERTY_TYPES_TABLE, $type_id, $language_code);
        if (!$type_data) {
            return null;
        }
        return $type_data;
    }

    /**
     * Обновляет данные типа свойства в таблице типов свойств
     * @param array $property_type_data Ассоциативный массив с данными типа свойства для обновления.
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return int|bool ID обновленного типа свойства или false в случае неудачи.
     */
    public function update_property_type_data($property_type_data = [], $language_code = ENV_DEF_LANG) {
        $property_type_data = SafeMySQL::gi()->filterArray($property_type_data, SysClass::ee_get_fields_table(Constants::PROPERTY_TYPES_TABLE));
        $property_type_data = array_map('trim', $property_type_data);
        $property_type_data['language_code'] = $language_code;  // добавлено
        if (empty($property_type_data['name'])) {
            return false;
        }
        if (!empty($property_type_data['type_id']) && $property_type_data['type_id'] != 0) {
            $type_id = $property_type_data['type_id'];
            unset($property_type_data['type_id']); // Удаляем type_id из массива данных, чтобы избежать его обновление
            $sql = "UPDATE ?n SET ?u WHERE `type_id` = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_TYPES_TABLE, $property_type_data, $type_id);
            return $result ? $type_id : false;
        } else {
            unset($property_type_data['type_id']);
        }
        // Проверяем уникальность имени в рамках одного языка
        $existingType = SafeMySQL::gi()->getRow(
                "SELECT `type_id` FROM ?n WHERE `name` = ?s AND `language_code` = ?s",
                Constants::PROPERTY_TYPES_TABLE,
                $property_type_data['name'], $language_code
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
    public function type_properties_delete($type_id) {
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
     * @param type $type_id
     */
    public function property_delete($property_id) {
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

    /**
     * Получает данные наборов свойств с учетом параметров сортировки, фильтрации и пагинации.
     * @param string $order Параметр для сортировки результатов (по умолчанию 'set_id ASC').
     * @param string|null $where Условие для фильтрации результатов (по умолчанию NULL).
     * @param int $start Начальный индекс для пагинации результатов (по умолчанию 0).
     * @param int $limit Максимальное количество результатов для возврата (по умолчанию 100).
     * @return array Массив, содержащий данные наборов свойств и общее количество наборов свойств.
     */
    public function get_property_sets_data($order = 'set_id ASC', $where = null, $start = 0, $limit = 100) {
        $orderString = $order ? $order : 'set_id ASC';
        $whereString = $where ? "WHERE " . $where : '';
        $start = $start ? $start : 0;

        $sql_sets = "SELECT `set_id` FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        $res_array = SafeMySQL::gi()->getAll($sql_sets, Constants::PROPERTY_SETS_TABLE, $start, $limit);

        $res = [];
        foreach ($res_array as $set) {
            $data_set = $this->get_property_set_data($set['set_id']);
            if (!$data_set)
                continue;
            $res[] = $data_set;
        }

        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PROPERTY_SETS_TABLE);

        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные одного набора свойств по его ID
     * @param int $set_id ID набора свойств, для которого нужно получить данные.
     * @return array|null Массив с данными набора свойств или NULL, если набор свойств не найден.
     */
    public function get_property_set_data($set_id) {
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
     * @param array $property_set_data Ассоциативный массив с данными набора свойств для обновления.
     * @return int|bool ID обновленного набора свойств или false в случае неудачи.
     */
    public function update_property_set_data($property_set_data = []) {
        $property_set_data = SafeMySQL::gi()->filterArray($property_set_data, SysClass::ee_get_fields_table(Constants::PROPERTY_SETS_TABLE));
        $property_set_data = array_map('trim', $property_set_data);
        if (empty($property_set_data['name'])) {
            return false;
        }
        if (!empty($property_set_data['set_id']) && $property_set_data['set_id'] != 0) {
            $set_id = $property_set_data['set_id'];
            unset($property_set_data['set_id']); // Удаляем set_id из массива данных, чтобы избежать его обновление

            $sql = "UPDATE ?n SET ?u WHERE `set_id` = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_SETS_TABLE, $property_set_data, $set_id);
            return $result ? $set_id : false;
        } else {
            unset($property_set_data['set_id']);
        }
        // Проверяем уникальность имени набора свойств
        $existingSet = SafeMySQL::gi()->getRow(
                "SELECT `set_id` FROM ?n WHERE `name` = ?s",
                Constants::PROPERTY_SETS_TABLE,
                $property_set_data['name']
        );
        if ($existingSet) {
            return false;
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_SETS_TABLE, $property_set_data);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удалит набор свойств.
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
        SafeMySQL::gi()->query($delete_previous_properties, 'property_set_to_properties', $set_id);
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
     * @param string $language_code Код языка, по умолчанию 'RU'
     * @return array Возвращает массив значений свойств для сущности или пустой массив, если свойства не найдены
     */
    public function getPropertyValuesForEntity(int $entity_id, string $entity_type, string $language_code = 'RU'):array {
        $sql = "SELECT * FROM ?n WHERE `entity_id` = ?i AND `entity_type` = ?s AND `language_code` = ?s";
        $properties = SafeMySQL::gi()->getInd('value_id', $sql, Constants::PROPERTY_VALUES_TABLE, $entity_id, $entity_type, $language_code);
        if (!$properties) {
            return [];
        }
        // Преобразование значений JSON в массивы PHP, если необходимо
        foreach ($properties as $key => $value) {
            if (is_string($properties[$key]['value'])) {
                $properties[$key]['value'] = json_decode($value['value'], true);
            }
        }
        return $properties;
    }

    /**
     * Сохраняет или обновляет значение свойства
     * @param array $property_data Данные свойства для сохранения или обновления
     * @param string $language_code Код языка для данных свойства, по умолчанию 'RU'
     * @return mixed Возвращает идентификатор записи в случае успеха или false в случае ошибки
     */
    public function updatePropertiesTypeData(array $property_data = [], string $language_code = 'RU'):mixed {
        // Фильтрация и подготовка данных свойства
        $property_data = SafeMySQL::gi()->filterArray($property_data, SysClass::ee_get_fields_table(Constants::PROPERTY_VALUES_TABLE_FIELDS));
        $property_data = array_map('trim', $property_data);
        $property_data['language_code'] = $language_code;
        // Проверка наличия и валидность ключевых полей
        if (empty($property_data['entity_id']) || empty($property_data['property_id']) || empty($property_data['entity_type']) || empty($property_data['value'])) {
            return false; // Все ключевые поля обязательны
        }
        // Преобразование value в JSON, если это необходимо
        if (!is_string($property_data['value'])) {
            $property_data['value'] = json_encode($property_data['value'], JSON_UNESCAPED_UNICODE);
        }
        // Проверка на наличие value_id для обновления
        if (!empty($property_data['value_id'])) {
            $value_id = $property_data['value_id'];
            unset($property_data['value_id']); // Удаление value_id из массива данных, чтобы избежать его включения в обновление
            $sql = "UPDATE ?n SET ?u WHERE `value_id` = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $property_data, $value_id);
            return $result ? $value_id : false;
        } else {
            unset($property_data['value_id']); // Удаление value_id, если оно пустое
            $sql = "INSERT INTO ?n SET ?u";
            $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, $property_data);
            return $result ? SafeMySQL::gi()->insertId() : false;
        }
    }

}
