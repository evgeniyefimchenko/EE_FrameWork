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
     * @return array Массив, содержащий данные свойств и общее количество свойств.
     */
    public function get_properties_data($order = 'property_id ASC', $where = null, $start = 0, $limit = 100) {
        $orderString = $order ? $order : 'property_id ASC';
        $whereString = $where ? $where : '';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_properties = "SELECT `property_id` FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_properties = "SELECT `property_id` FROM ?n $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_properties, Constants::PROPERTIES_TABLE, $start, $limit);
        $res = [];
        foreach ($res_array as $property) {
            $res[] = $this->get_property_data($property['property_id']);
        }
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PROPERTIES_TABLE);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные одного свойства по его ID
     * @param int $property_id ID свойства, для которого нужно получить данные.
     * @return array|null Массив с данными свойства или NULL, если свойство не найдено.
     */
    public function get_property_data($property_id) {
        $sql_property = "SELECT p.*, pt.name as type_name 
                FROM ?n AS p 
                LEFT JOIN ?n AS pt ON p.type_id = pt.type_id 
                WHERE p.property_id = ?i";
        $property_data = SafeMySQL::gi()->getRow(
                $sql_property,
                Constants::PROPERTIES_TABLE,
                Constants::PROPERTY_TYPES_TABLE,
                $property_id
        );
        if (!$property_data) {
            return null;
        }
        return $property_data;
    }

    /**
     * Обновляет данные свойства в таблице свойств.
     *
     * @param array $property_data Ассоциативный массив с данными свойства для обновления.
     * @return int|bool ID обновленного свойства или false в случае неудачи.
     */
    public function update_property_data($property_data = []) {
        $allowed_fields = [
            'property_id',
            'type_id',
            'name',
            'default_values',
            'is_multiple',
            'is_required',
            'description',
        ];

        $property_data = SafeMySQL::gi()->filterArray($property_data, $allowed_fields);
        $property_data = array_map('trim', $property_data);
        $property_data['default_values'] = $property_data['default_values'] ? json_encode($property_data['default_values']) : '{[]}';
        $property_data['is_multiple'] = $property_data['is_multiple'] == 'on' ? 1 : 0;
        $property_data['is_required'] = $property_data['is_required'] == 'on' ? 1 : 0;
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

        // Проверяем уникальность имени в рамках одного типа
        $existingProperty = SafeMySQL::gi()->getRow(
                "SELECT `property_id` FROM ?n WHERE `name` = ?s AND `type_id` = ?i",
                Constants::PROPERTIES_TABLE,
                $property_data['name'], $property_data['type_id']
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
     * @return array Массив, содержащий все типы свойств, каждый из которых представлен ассоциативным массивом с данными типа свойства.
     */
    public function get_all_property_types() {
        $sql = "SELECT * FROM ?n";
        $property_types = SafeMySQL::gi()->getAll($sql, Constants::PROPERTY_TYPES_TABLE);
        return $property_types;
    }

    /**
     * Получает данные типов свойств с учетом параметров сортировки, фильтрации и пагинации.
     * @param string $order Параметр для сортировки результатов (по умолчанию 'type_id ASC').
     * @param string|null $where Условие для фильтрации результатов (по умолчанию NULL).
     * @param int $start Начальный индекс для пагинации результатов (по умолчанию 0).
     * @param int $limit Максимальное количество результатов для возврата (по умолчанию 100).
     * @return array Массив, содержащий данные типов свойств и общее количество типов свойств.
     */
    public function get_type_properties_data($order = 'type_id ASC', $where = null, $start = 0, $limit = 100) {
        $orderString = $order ? $order : 'type_id ASC';
        $whereString = $where ? $where : '';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_types = "SELECT `type_id` FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_types = "SELECT `type_id` FROM ?n $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_types, Constants::PROPERTY_TYPES_TABLE, $start, $limit);
        $res = [];
        foreach ($res_array as $type) {
            $res[] = $this->get_type_property_data($type['type_id']);
        }
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PROPERTY_TYPES_TABLE);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные одного типа свойства по его ID.
     * @param int $type_id ID типа свойства, для которого нужно получить данные.
     * @return array|null Массив с данными типа свойства или NULL, если тип свойства не найден.
     */
    public function get_type_property_data($type_id) {
        $sql_type = "SELECT * FROM ?n WHERE `type_id` = ?i";
        $type_data = SafeMySQL::gi()->getRow($sql_type, Constants::PROPERTY_TYPES_TABLE, $type_id);
        if (!$type_data) {
            return null;
        }
        return $type_data;
    }

    /**
     * Обновляет данные типа свойства в таблице типов свойств
     * @param array $property_type_data Ассоциативный массив с данными типа свойства для обновления.
     * @return int|bool ID обновленного типа свойства или false в случае неудачи.
     */
    public function update_property_type_data($property_type_data = []) {
        $allowed_fields = [
            'type_id',
            'name',
            'status',
            'description',
        ];
        $property_type_data = SafeMySQL::gi()->filterArray($property_type_data, $allowed_fields);
        $property_type_data = array_map('trim', $property_type_data);
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
        // Проверяем уникальность имени
        $existingType = SafeMySQL::gi()->getRow(
                "SELECT `type_id` FROM ?n WHERE `name` = ?s",
                Constants::PROPERTY_TYPES_TABLE,
                $property_type_data['name']
        );
        if ($existingType) {
            return false;
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PROPERTY_TYPES_TABLE, $property_type_data);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

}
