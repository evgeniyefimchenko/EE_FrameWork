<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Модель работы с типами категорий
 */
Class Model_categories_types Extends Users {

    /**
     * Получает все типы, с учетом языка.
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array Массив, содержащий ID и названия всех типов.
     */
    public function get_all_types($language_code = ENV_DEF_LANG) {
        $sql = "SELECT type_id, name FROM ?n WHERE language_code = ?s";
        $types = SafeMySQL::gi()->getInd('type_id', $sql, Constants::CATEGORIES_TYPES_TABLE, $language_code);
        return $types;
    }

    /**
     * Получает данные о типах с учетом параметров сортировки, фильтрации, пагинации и языка.
     * @param string $order Параметр для сортировки результатов запроса (по умолчанию: 'type_id ASC').
     * @param string|null $where Условие для фильтрации результатов запроса (по умолчанию: NULL).
     * @param int $start Начальная позиция для выборки результатов запроса (по умолчанию: 0).
     * @param int $limit Максимальное количество результатов для выборки (по умолчанию: 100).
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array Массив, содержащий данные о типах и общее количество типов.
     */
    public function get_categories_types_data($order = 'type_id ASC', $where = NULL, $start = 0, $limit = 100, $language_code = ENV_DEF_LANG) {
        $orderString = $order ?: 'type_id ASC';
        $whereString = $where ? $where . " AND language_code = '$language_code'" : "WHERE language_code = '$language_code'";
        $start = $start ?: 0;
        $sql_types = "SELECT `type_id` FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        $res_array = SafeMySQL::gi()->getAll($sql_types, Constants::CATEGORIES_TYPES_TABLE, $start, $limit);
        $res = [];
        foreach ($res_array as $type) {
            $res[] = $this->get_categories_type_data($type['type_id'], $language_code);
        }
        $sql_count = "SELECT COUNT(DISTINCT `type_id`) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::CATEGORIES_TYPES_TABLE);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные конкретного типа по его ID и языку.
     * @param int $type_id ID типа, данные которого необходимо получить.
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array|null Ассоциативный массив с данными типа или null, если тип не найден.
     */
    public function get_categories_type_data($type_id, $language_code = ENV_DEF_LANG) {
        if (!$type_id) {
            return null;
        }
        $sql = "SELECT * FROM ?n WHERE `type_id` = ?i AND language_code = ?s";
        $type_data = SafeMySQL::gi()->getRow($sql, Constants::CATEGORIES_TYPES_TABLE, $type_id, $language_code);
        return $type_data;
    }

    /**
     * Обновляет существующий тип или создает новый с учетом языка.
     * @param array $type_data Ассоциативный массив с данными типа. Должен содержать ключи 'name' и 'description', и опционально 'type_id'.
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return int|bool ID нового или обновленного типа или false в случае ошибки.
     */
    public function update_categories_type_data($type_data = [], $language_code = ENV_DEF_LANG) {
        $type_data = SafeMySQL::gi()->filterArray($type_data, SysClass::ee_get_fields_table(Constants::CATEGORIES_TYPES_TABLE));
        $type_data = array_map('trim', $type_data);
        $type_data['language_code'] = $language_code;
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
            $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TYPES_TABLE, $type_data, $type_id);
            return $result ? $type_id : false;
        }
        // Проверяем уникальность имени
        $existingType = SafeMySQL::gi()->getRow(
                "SELECT `type_id` FROM ?n WHERE `name` = ?s AND language_code = ?s",
                Constants::CATEGORIES_TYPES_TABLE,
                $type_data['name'],
                $language_code
        );
        if ($existingType) {
            return false; // или вернуть какое-то сообщение об ошибке
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TYPES_TABLE, $type_data);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удалит тип категории
     * @param int $type_id
     */
    public function delete_categories_type(int $type_id) {
        try {
            $sql = 'SELECT 1 FROM ?n WHERE type_id = ?i';
            if (SafeMySQL::gi()->getOne($sql, Constants::CATEGORIES_TABLE, $type_id)) {
                return ['error' => 'Нельзя удалить тип категории, так как он используется!'];
            }
            $sql_delete = "DELETE FROM ?n WHERE type_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::CATEGORIES_TYPES_TABLE, $type_id);
            return $result ? [] : ['error' => 'Ошибка при выполнении запроса DELETE'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Вернёт все наборы свойств привязанные к типу категории
     * @param int $type_id
     * @return array
     */
    public function get_categories_type_sets_data(int $type_id = 0) {
        $sql = 'SELECT set_id FROM ?n WHERE type_id = ?i';
        return SafeMySQL::gi()->getCol($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $type_id);
    }
    
    /**
     * Обновляет связи между типами категориями и наборами свойств
     * @param int   $type_id  Идентификатор типа категории для обновления связей
     * @param array $set_ids  Идентификаторы наборов свойств для связывания с указанным типом категории
     */    
    public function update_categories_type_sets_data(int $type_id, array $set_ids) {
        $sql = "INSERT INTO ?n SET ?u";
        foreach ($set_ids as $set_id) {
            $data = ['type_id' => $type_id, 'set_id' => $set_id];
            SafeMySQL::gi()->query($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $data); 
        }              
    }
    
    /**
     * Удаляет связи между типами категорий и наборами свойств для указанных идентификаторов типов категорий
     * @param int|array $type_ids Идентификаторы типов категорий для удаления связей
     * @return void
     */
    public function delete_categories_type_sets_data($type_ids) {
        if (!is_array($type_ids)) {
            $type_ids = [$type_ids];
        } else {
            $type_ids = implode(',', $type_ids);
        }
        $sql_delete = "DELETE FROM ?n WHERE type_id IN (?a)";
        return SafeMySQL::gi()->query($sql_delete, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $type_ids);
    }
    
}
