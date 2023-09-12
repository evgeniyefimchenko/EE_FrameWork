<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * 	Модель работы с сущностями
 */
Class Model_pages Extends Users {

    /**
     * Получает данные всех страниц с возможностью сортировки, фильтрации и ограничения количества записей.
     * @param string $order Строка с сортировкой (например, 'entity_id ASC').
     * @param string|null $where Условие для фильтрации данных (опционально).
     * @param int $start Начальная позиция для выборки (опционально).
     * @param int $limit Количество записей для выборки (опционально).
     * @return array Массив с данными страниц.
     */
    public function get_pages_data($order = 'entity_id ASC', $where = null, $start = 0, $limit = 100) {
        $orderString = $order ? $order : 'entity_id ASC';
        $whereString = $where ? $where : '';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_pages = "SELECT `entity_id` FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_pages = "SELECT `entity_id` FROM ?n $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_pages, Constants::ENTITIES_TABLE, $start, $limit);
        $res = [];
        foreach ($res_array as $page) {
            $res[] = $this->get_page_data($page['entity_id']);
        }
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::ENTITIES_TABLE);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные одной страницы по её ID.
     * @param int $entity_id ID страницы, для которой нужно получить данные.
     * @return array|null Массив с данными страницы или NULL, если страница не найдена.
     */
    public function get_page_data($entity_id) {
        $sql_page = "SELECT e.*, c.title as category_title, t.name as type_name 
                    FROM ?n AS e 
                    LEFT JOIN ?n AS c ON e.category_id = c.category_id 
                    LEFT JOIN ?n AS t ON c.type_id = t.type_id 
                    WHERE e.entity_id = ?i";
        $page_data = SafeMySQL::gi()->getRow(
                $sql_page,
                Constants::ENTITIES_TABLE,
                Constants::CATEGORIES_TABLE,
                Constants::TYPES_TABLE,
                $entity_id
        );
        if (!$page_data) {
            return null;
        }
        return $page_data;
    }

    /**
     * Вернёт все страницы с id и title
     */
    public function get_all_pages() {
        $sql_pages = "SELECT entity_id, title FROM ?n";
        SafeMySQL::gi()->getAll($sql_pages, Constants::ENTITIES_TABLE);
    }

    /**
     * Обновляет данные сущности в таблице сущностей.
     *
     * @param array $entity_data Массив данных сущности.
     * @return int|bool Возвращает идентификатор обновленной сущности в случае успеха, или false в случае ошибки.
     */
    public function update_page_data($entity_data = []) {
        $allowed_fields = [
            'entity_id',
            'parent_entity_id',
            'category_id',
            'status',
            'title',
            'short_description',
            'description'
        ];
        $entity_data = SafeMySQL::gi()->filterArray($entity_data, $allowed_fields);
        $entity_data = array_map('trim', $entity_data);
        $entity_data = SysClass::ee_convertArrayValuesToNumbers($entity_data);
        $entity_data['parent_entity_id'] = (int) $entity_data['parent_entity_id'] !== 0 ? (int) $entity_data['parent_entity_id'] : NULL;
        $entity_data['type_id'] = SafeMySQL::gi()->getOne('SELECT type_id FROM ?n WHERE category_id=?i', Constants::CATEGORIES_TABLE, $entity_data['category_id']);
        if (empty($entity_data['title'])) {
            return false;
        }
        if (!empty($entity_data['entity_id']) && $entity_data['entity_id'] != 0) {
            $entity_id = $entity_data['entity_id'];
            unset($entity_data['entity_id']);
            $sql = "UPDATE ?n SET ?u WHERE `entity_id` = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::ENTITIES_TABLE, $entity_data, $entity_id);
            return $result ? $entity_id : false;
        } else {
            unset($entity_data['entity_id']);
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::ENTITIES_TABLE, $entity_data);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удаляет сущность по указанному entity_id из таблицы entities.
     * @param int $entity_id Идентификатор сущности для удаления.
     * @return bool Возвращает true в случае успешного удаления, или false в случае ошибки.
     */
    public function delete_entity($entity_id) {
        try {
            $sql = "DELETE FROM ?n WHERE `entity_id` = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::ENTITIES_TABLE, $entity_id);
            return $result ? true : false;
        } catch (Exception $e) {
            // Обработка ошибки удаления сущности
            error_log('Error deleting entity: ' . $e->getMessage());
            return false;
        }
    }    
    
}
