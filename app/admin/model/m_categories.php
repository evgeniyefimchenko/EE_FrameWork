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
     * Получает все категории для указанного языка.
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array Массив категорий, индексированный по идентификаторам категорий.
     */
    public function get_all_categories($language_code = ENV_DEF_LANG) {
        return SafeMySQL::gi()->getInd('category_id', 'SELECT category_id FROM ?n WHERE language_code = ?s', Constants::CATEGORIES_TABLE, $language_code);
    }

    /**
     * Получает данные категорий с возможностью фильтрации, сортировки и пагинации.
     * @param string $order Строка, определяющая порядок сортировки (по умолчанию: 'category_id ASC').
     * @param string|null $where Условие для фильтрации (по умолчанию: NULL).
     * @param int $start Индекс начальной записи для пагинации (по умолчанию: 0).
     * @param int $limit Количество записей для извлечения (по умолчанию: 100).
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array Массив с двумя ключами: 'data' - массив с данными категорий; 'total_count' - общее количество категорий, соответствующих условию фильтрации.
     */
    public function get_categories_data($order = 'category_id ASC', $where = NULL, $start = 0, $limit = 100, $language_code = ENV_DEF_LANG) {
        $orderString = $order ? $order : 'category_id ASC';
        $start = $start ? $start : 0;
        $params = [Constants::CATEGORIES_TABLE, $language_code, $start, $limit];

        if ($where) {
            $whereString = "$where AND language_code = ?s";
        } else {
            $whereString = "language_code = ?s";
        }

        if ($orderString) {
            $sql_categories = "SELECT `category_id` FROM ?n WHERE $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_categories = "SELECT `category_id` FROM ?n WHERE $whereString LIMIT ?i, ?i";
        }

        $res_array = SafeMySQL::gi()->getAll($sql_categories, ...$params);
        $res = [];
        foreach ($res_array as $category) {
            $res[] = $this->get_category_data($category['category_id']);
        }

        $sql_count = "SELECT COUNT(*) as total_count FROM ?n WHERE $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, ...array_slice($params, 0, 2));  // передаем первые два параметра (имя таблицы и код языка)

        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает все данные категории по её ID вместе с названием родительской категории, если она существует.
     * @param int $category_id ID категории, для которой нужно получить данные.
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array|null Массив с данными категории или NULL, если категория не найдена.
     */
    public function get_category_data($category_id, $language_code = ENV_DEF_LANG) {
        $sql_category = "
            SELECT 
                c.*, 
                p.title as parent_title, 
                t.name as type_name, 
                (SELECT COUNT(*) FROM ?n WHERE category_id = ?i) as entity_count
            FROM ?n AS c 
            LEFT JOIN ?n AS p ON c.parent_id = p.category_id 
            LEFT JOIN ?n AS t ON c.type_id = t.type_id 
            WHERE c.category_id = ?i AND c.language_code = ?s";
        $category_data = SafeMySQL::gi()->getRow(
                $sql_category,
                Constants::ENTITIES_TABLE,
                $category_id,
                Constants::CATEGORIES_TABLE,
                Constants::CATEGORIES_TABLE,
                Constants::TYPES_TABLE,
                $category_id,
                $language_code
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
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array Массив, представляющий дерево категорий.
     */
    public function getCategoriesTree($excludeCategoryID = null, $excludeStatuses = null, $exclude_null = null, $language_code = ENV_DEF_LANG) {
        $excludeCategoryID = !$excludeCategoryID ? NULL : $excludeCategoryID;
        $excludeStatuses = !$excludeStatuses ? NULL : $excludeStatuses;
        $exclude_null = !$exclude_null ? NULL : $exclude_null;
        $query = "SELECT * FROM ?n WHERE language_code = ?s";  // Обновлено
        $params = [Constants::CATEGORIES_TABLE, $language_code];  // Обновлено
        // Добавляем условие для исключения определенной категории, если $excludeCategoryID не равно NULL
        if ($excludeCategoryID !== null) {
            $query .= " AND category_id != ?i";  // Обновлено
            $params[] = $excludeCategoryID;
        }
        // Добавляем условие для исключения категорий с определенными статусами
        if ($excludeStatuses !== null && is_array($excludeStatuses) && !empty($excludeStatuses)) {
            $placeholders = implode(',', array_fill(0, count($excludeStatuses), '?'));
            $query .= " AND status NOT IN ($placeholders)";  // Обновлено
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
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return mixed Возвращает ID обновленной или созданной записи в случае успеха, иначе false.
     * @throws Exception В случае ошибки в запросе к базе данных.
     */
    public function update_category_data($category_data = [], $language_code = ENV_DEF_LANG) {
        $category_data = SafeMySQL::gi()->filterArray($category_data, SysClass::ee_get_fields_table(Constants::CATEGORIES_TABLE));
        $category_data = array_map('trim', $category_data);
        $category_data = SysClass::ee_convertArrayValuesToNumbers($category_data);
        $category_data['parent_id'] = (int) $category_data['parent_id'] !== 0 ? (int) $category_data['parent_id'] : NULL;
        $category_data['language_code'] = $language_code;  // Добавлено
        // Если есть родитель то записываем его тип категории
        $category_data['type_id'] = $category_data['parent_id'] ? SafeMySQL::gi()->getOne('SELECT type_id FROM ?n WHERE category_id=?i AND language_code=?s', Constants::CATEGORIES_TABLE, $category_data['parent_id'], $language_code) : $category_data['type_id'];  // Обновлено
        if (!$category_data['parent_id'] && !empty($category_data['category_id'])) { // Нет родителя и категория существует, устанавливаем старый type_id
            $category_data['type_id'] = SafeMySQL::gi()->getOne('SELECT type_id FROM ?n WHERE category_id=?i AND language_code=?s', Constants::CATEGORIES_TABLE, $category_data['category_id'], $language_code);  // Обновлено
        }
        if (empty($category_data['title'])) {
            SysClass::pre_file('error', 'empty title');
            return false;
        }
        if (!isset($category_data['description'])) {
            $category_data['description'] = $category_data['title'];
        }
        if (!empty($category_data['category_id']) && $category_data['category_id'] != 0) {
            $category_id = $category_data['category_id'];
            unset($category_data['category_id']); // Удаляем category_id из массива данных, чтобы избежать его обновление
            $sql = "UPDATE ?n SET ?u WHERE `category_id` = ?i AND language_code = ?s";  // Обновлено                        
            $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TABLE, $category_data, $category_id, $language_code);  // Обновлено
            if (!$result) {
                SysClass::pre_file('error', 'error SQL ' . SafeMySQL::gi()->parse($sql, Constants::CATEGORIES_TABLE, $category_data, $category_id, $language_code));  // Обновлено
            }
            return $result ? $category_id : false;
        } else {
            unset($category_data['category_id']);
        }
        // Проверяем уникальность названия в рамках одного типа
        $existingCategory = SafeMySQL::gi()->getRow(
                "SELECT `category_id` FROM ?n WHERE `title` = ?s AND type_id = ?i AND language_code = ?s", // Обновлено
                Constants::CATEGORIES_TABLE,
                $category_data['title'], $category_data['type_id'], $language_code  // Обновлено
        );
        if ($existingCategory) {
            SysClass::pre_file('error', 'existingCategory title: ' . $category_data['title'] . ' type_id: ' . $category_data['type_id']);
            return false;
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TABLE, $category_data);
        if (!$result) {
            SysClass::pre_file('error', 'error SQL ' . SafeMySQL::gi()->parse($sql, Constants::CATEGORIES_TABLE, $category_data));
        }
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удаляет категорию по указанному entity_id из таблицы entities.
     * @param int $category_id Идентификатор сущности для удаления.
     * @return bool Возвращает true в случае успешного удаления, или false в случае ошибки.
     */
    public function delete_category($category_id) {
        try {
            $sql = "DELETE FROM ?n WHERE `category_id` = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TABLE, $category_id);
            return $result ? [] : ['error' => 'Error query DELETE'];
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'foreign key constraint fails') !== false) {
                return ['error' => 'Невозможно удалить категорию, поскольку существуют объекты, ссылающиеся на эту категорию. Сначала удалите или обновите зависимые объекты.'];
            }
            return ['error' => $errorMessage];
        }
    }

}
