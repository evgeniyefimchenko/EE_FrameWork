<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * 	Модель работы с типами категорий
 */
Class Model_types Extends Users {

    /**
     * Вернёт все типы
     */
    public function get_all_types() {
        $sql = "SELECT type_id, name FROM ?n";
        $types = SafeMySQL::gi()->getInd('type_id', $sql, Constants::TYPES_TABLE);
        return $types;
    }

    /**
     * Получает данные о типах
     * @param string $order Параметр для сортировки результатов запроса (по умолчанию: 'type_id ASC').
     * @param string|null $where Условие для фильтрации результатов запроса (по умолчанию: NULL).
     * @param int $start Начальная позиция для выборки результатов запроса (по умолчанию: 0).
     * @param int $limit Максимальное количество результатов для выборки (по умолчанию: 100).
     * @return array Массив, содержащий данные о типах и общее количество типов.
     */
    public function get_types_data($order = 'type_id ASC', $where = NULL, $start = 0, $limit = 100) {
        $orderString = $order ?: 'type_id ASC';
        $whereString = $where ?: '';
        $start = $start ?: 0;

        $sql_types = "SELECT `type_id` FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        $res_array = SafeMySQL::gi()->getAll($sql_types, Constants::TYPES_TABLE, $start, $limit);

        $res = [];
        foreach ($res_array as $type) {
            $res[] = $this->get_type_data($type['type_id']);  // Убедитесь, что у вас есть метод get_type_data
        }

        $sql_count = "SELECT COUNT(DISTINCT `type_id`) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::TYPES_TABLE);

        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные конкретного типа по его ID
     * @param int $type_id ID типа, данные которого необходимо получить
     * @return array|null Ассоциативный массив с данными типа или null, если тип не найден
     */
    public function get_type_data($type_id) {
        if (!$type_id) {
            return null;
        }
        $sql = "SELECT * FROM ?n WHERE `type_id` = ?i";
        $type_data = SafeMySQL::gi()->getRow($sql, Constants::TYPES_TABLE, $type_id);
        return $type_data;
    }

    /**
     * Обновляет существующий тип или создает новый.
     * @param array $type_data Ассоциативный массив с данными типа. Должен содержать ключи 'name' и 'description', и опционально 'type_id'.
     * @return int|bool ID нового или обновленного типа или false в случае ошибки.
     */
    public function update_type_data($type_data = []) {
        $allowed_fields = ['name', 'description', 'type_id'];
        $type_data = SafeMySQL::gi()->filterArray($type_data, $allowed_fields);
        $type_data = array_map('trim', $type_data);
        if (empty($type_data['name'])) {
            return false;
        }
        if (!isset($type_data['description'])) {
            $type_data['description'] = $type_data['name'];
        }
        if (!empty($type_data['type_id']) && $type_data['type_id'] != 0) {
            $type_id = $type_data['type_id'];
            unset($type_data['type_id']); // Удаляем type_id из массива данных, чтобы избежать его обновление
            $sql = "UPDATE ?n SET ?u WHERE `type_id` = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::TYPES_TABLE, $type_data, $type_id);
            return $result ? $type_id : false;
        }
        // Проверяем уникальность имени
         $existingType = SafeMySQL::gi()->getRow(
             "SELECT `type_id` FROM ?n WHERE `name` = ?s", 
             Constants::TYPES_TABLE, 
             $type_data['name']
         );
         if ($existingType) {
             return false; // или вернуть какое-то сообщение об ошибке
         }        
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::TYPES_TABLE, $type_data);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

}
