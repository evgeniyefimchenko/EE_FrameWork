<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Модель работы со свойствами
 */
Class Model_properties Extends Users {

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
            $res[] = $this->get_property_data($property['property_id'], $language_code);
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
     * @param int $property_id ID свойства, для которого нужно получить данные.
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array|null Массив с данными свойства или NULL, если свойство не найдено.
     */
    public function get_property_data($property_id, $language_code = ENV_DEF_LANG) {
        $sql_property = "SELECT p.*, pt.name as type_name, pt.fields as fields 
            FROM ?n AS p 
            LEFT JOIN ?n AS pt ON p.type_id = pt.type_id 
            WHERE p.property_id = ?i AND p.language_code = ?s";
        $property_data = SafeMySQL::gi()->getRow(
                $sql_property,
                Constants::PROPERTIES_TABLE,
                Constants::PROPERTY_TYPES_TABLE,
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
        $property_data['default_values'] = $property_data['default_values'] ? json_encode($property_data['default_values']) : '[]';
        $property_data = array_map('trim', $property_data);                
        $property_data['is_multiple'] = $property_data['is_multiple'] == 'on' ? 1 : 0;
        $property_data['is_required'] = $property_data['is_required'] == 'on' ? 1 : 0;
        $property_data['language_code'] = $language_code;  // добавлено
        if (empty($property_data['name']) || !isset($property_data['type_id'])) {
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
            return false;
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTIES_TABLE, $property_data);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Получает все типы свойств из таблицы типов свойств.
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array Массив, содержащий все типы свойств, каждый из которых представлен ассоциативным массивом с данными типа свойства.
     */
    public function get_all_property_types($language_code = ENV_DEF_LANG) {
        $sql = "SELECT * FROM ?n WHERE language_code = ?s";
        $property_types = SafeMySQL::gi()->getAll($sql, Constants::PROPERTY_TYPES_TABLE, $language_code);
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

}
