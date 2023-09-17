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

}
