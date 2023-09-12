<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * 	Модель работы с категориями
 */
Class Model_categories Extends Users {

    /**
     * Получает данные категорий с возможностью фильтрации, сортировки и пагинации.
     * @param string $order Строка, определяющая порядок сортировки (по умолчанию: 'category_id ASC').
     * @param string|null $where Условие для фильтрации (по умолчанию: NULL).
     * @param int $start Индекс начальной записи для пагинации (по умолчанию: 0).
     * @param int $limit Количество записей для извлечения (по умолчанию: 100).
     * @return array Массив с двумя ключами: 'data' - массив с данными категорий; 'total_count' - общее количество категорий, соответствующих условию фильтрации.
     */
    public function get_categories_data($order = 'category_id ASC', $where = NULL, $start = 0, $limit = 100) {
        $orderString = $order ? $order : 'category_id ASC';
        $whereString = $where ? $where : '';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_categories = "SELECT `category_id` FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_categories = "SELECT `category_id` FROM ?n $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_categories, Constants::CATEGORIES_TABLE, $start, $limit);
        $res = [];
        foreach ($res_array as $category) {
            $res[] = $this->get_category_data($category['category_id']);
        }
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::CATEGORIES_TABLE);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает все данные категории по её ID вместе с названием родительской категории, если она существует.
     * @param int $category_id ID категории, для которой нужно получить данные.
     * @return array|null Массив с данными категории или NULL, если категория не найдена.
     */
    public function get_category_data($category_id) {
        $sql_category = "SELECT c.*, p.title as parent_title, t.name as type_name 
        FROM ?n AS c LEFT JOIN ?n AS p ON c.parent_id = p.category_id LEFT JOIN ?n AS t ON c.type_id = t.type_id WHERE c.category_id = ?i";
        $category_data = SafeMySQL::gi()->getRow(
                $sql_category,
                Constants::CATEGORIES_TABLE,
                Constants::CATEGORIES_TABLE,
                Constants::TYPES_TABLE,
                $category_id
        );
        if (!$category_data) {
            return null;
        }
        return $category_data;
    }

    /**
     * Функция для получения всех категорий в виде многомерного массива (дерева)
     * @param int|null $excludeCategoryID ID категории, которую нужно исключить (по умолчанию null).
     * @param array|null $excludeStatuses Массив статусов, которые нужно исключить (по умолчанию null).
     * @param bool $exclude_null Флаг исключения нулевой категории Без категории
     * @return array Массив, представляющий дерево категорий.
     */
    public function getCategoriesTree($excludeCategoryID = null, $excludeStatuses = null, $exclude_null = null) {
        $excludeCategoryID = !$excludeCategoryID ? NULL : $excludeCategoryID;
        $excludeStatuses = !$excludeStatuses ? NULL : $excludeStatuses;
        $exclude_null = !$exclude_null ? NULL : $exclude_null;
        $query = "SELECT * FROM ?n";
        $params = [Constants::CATEGORIES_TABLE];
        // Добавляем условие для исключения определенной категории
        if ($excludeCategoryID !== null) {
            $query .= " WHERE category_id != ?i";
            $params[] = $excludeCategoryID;
        }
        // Добавляем условие для исключения категорий с определенными статусами
        if ($excludeStatuses !== null && is_array($excludeStatuses) && !empty($excludeStatuses)) {
            $placeholders = implode(',', array_fill(0, count($excludeStatuses), '?'));
            $query .= " AND status NOT IN ($placeholders)";
            $params = array_merge($params, $excludeStatuses);
        }
        $categories = SafeMySQL::gi()->getAll($query, ...$params);              
        $categoriesArray = [];
        foreach ($categories as $category) {
            $category['parent_id'] = !$category['parent_id'] ? 0 : $category['parent_id'];
            $categoriesArray[$category['parent_id']][] = $category;
        }
        $res = $this->buildTree($categoriesArray);
        // Создаем категорию "Без категории" с category_id = 0 и добавляем ее в начало массива 
        if (!$exclude_null) {
            array_unshift($res, [
                'status' => 'active',
                'category_id' => 0,
                'title' => 'Без категории',
                'type_id' => 0,
                'parent_id' => null  // или можно оставить пустым, в зависимости от структуры данных
            ]);
        }
        return $res;
    }

    /**
     * Рекурсивная функция для построения дерева категорий.
     * @param array $categories Массив категорий.
     * @param int $parentId ID родительской категории (по умолчанию 0).
     * @param string $prefix Префикс для визуального отображения уровня вложенности (по умолчанию пустая строка).
     * @return array Дерево категорий.
     */
    private function buildTree(array $categories, int $parentId = 0, int $level = 0): array {
        $tree = [];
        if (isset($categories[$parentId])) {
            foreach ($categories[$parentId] as $category) {
                $tree[] = [
                    'status' => $category['status'],
                    'category_id' => $category['category_id'],
                    'title' => $category['title'],
                    'type_id' => $category['type_id'],
                    'level' => $level,
                ];
                $tree = array_merge($tree, $this->buildTree($categories, $category['category_id'], $level + 1));
            }
        }
        return $tree;
    }

    /**
     * Обновляет существующую запись категории или создает новую в базе данных
     * @param array $category_data Ассоциативный массив с данными категории. Может включать следующие ключи:
     *                             - 'category_id' (int, optional) - ID категории для обновления. Если не указан, будет создана новая запись.
     *                             - 'type_id' (int) - ID типа, к которому относится категория.
     *                             - 'title' (string) - Заголовок категории.
     *                             - 'description' (string, optional) - Полное описание категории. Если не указан, используется значение 'title'.
     *                             - 'short_description' (string, optional) - Краткое описание категории.
     *                             - 'parent_id' (int, optional) - ID родительской категории.
     *                             - 'status' (string) - Статус категории ('active', 'hidden', 'disabled').
     * @return mixed Возвращает ID обновленной или созданной записи в случае успеха, иначе false.
     * @throws Exception В случае ошибки в запросе к базе данных.
     */
    public function update_category_data($category_data = []) {
        $allowed_fields = [
            'category_id',
            'type_id',
            'title',
            'description',
            'short_description',
            'parent_id',
            'status'
        ];        
        $category_data = SafeMySQL::gi()->filterArray($category_data, $allowed_fields);
        $category_data = array_map('trim', $category_data);
        $category_data = SysClass::ee_convertArrayValuesToNumbers($category_data);
        $category_data['parent_id'] = (int)$category_data['parent_id'] !== 0 ? (int)$category_data['parent_id'] : NULL;
        // Если есть родитель то записываем его тип категории
        $category_data['type_id'] = $category_data['parent_id'] ? SafeMySQL::gi()->getOne('SELECT type_id FROM ?n WHERE category_id=?i', Constants::CATEGORIES_TABLE, $category_data['parent_id']) : $category_data['type_id'];
        if (empty($category_data['title'])) {
            return false;
        }
        if (!isset($category_data['description'])) {
            $category_data['description'] = $category_data['title'];
        }
        if (!empty($category_data['category_id']) && $category_data['category_id'] != 0) {
            $category_id = $category_data['category_id'];
            unset($category_data['category_id']); // Удаляем category_id из массива данных, чтобы избежать его обновление
            $sql = "UPDATE ?n SET ?u WHERE `category_id` = ?i";                        
            $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TABLE, $category_data, $category_id);
            return $result ? $category_id : false;
        } else {
            unset($category_data['category_id']);
        }
        // Проверяем уникальность названия в рамках одного типа
        $existingCategory = SafeMySQL::gi()->getRow(
                "SELECT `category_id` FROM ?n WHERE `title` = ?s AND type_id = ?i",
                Constants::CATEGORIES_TABLE,
                $category_data['title'], $category_data['type_id']
        );
        if ($existingCategory) {
            return false;
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TABLE, $category_data);        
        return $result ? SafeMySQL::gi()->insertId() : false;
    }
/*SysClass::pre(SafeMySQL::gi()->parse($sql, Constants::CATEGORIES_TABLE, $category_data, $category_id));*/
}
