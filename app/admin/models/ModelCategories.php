<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;

/**
 * Модель работы с категориями
 */
class ModelCategories {

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
                Constants::CATEGORIES_TYPES_TABLE,
                $category_id,
                $language_code
        );
        if (!$category_data) {
            return null;
        }
        $category_data['category_path'] = $this->get_patch_category($category_data['category_id']);
        $cat_paths = explode('/', $category_data['category_path']);
        $category_data['category_path_text'] = '';
        if (count($cat_paths) > 1) {
            foreach ($cat_paths as $category_id) {
                $category_data['category_path_text'] .= $this->get_category_name($category_id, $language_code) . '/';
            }
            $category_data['category_path_text'] = substr($category_data['category_path_text'], 0, -1);
        } else {
            $category_data['category_path_text'] = $category_data['title'];
        }
        return $category_data;
    }

    /**
     * Получает название категории по её идентификатору и коду языка.
     * Возвращает название категории как строку.
     * @param int $category_id Идентификатор категории, для которой требуется получить название.
     * @param string $language_code Код языка по стандарту ISO 3166-2, для которого нужно получить название категории.
     *                              По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return string|null Возвращает название категории или null, если категория с таким идентификатором не найдена.
     */
    public function get_category_name($category_id, $language_code = ENV_DEF_LANG) {
        $sql_category = 'SELECT title FROM ?n WHERE category_id =?i AND language_code = ?s';
        return SafeMySQL::gi()->getOne($sql_category, Constants::CATEGORIES_TABLE, $category_id, $language_code);
    }
    
    /**
     * Вернёт type_id категории по её id
     * @param type $category_id
     * @param type $language_code
     * @return type
     */
    public function get_category_type_id($category_id, $language_code = ENV_DEF_LANG) {
        $sql_category = 'SELECT type_id FROM ?n WHERE category_id =?i AND language_code = ?s';
        return SafeMySQL::gi()->getOne($sql_category, Constants::CATEGORIES_TABLE, $category_id, $language_code);
    }

    /**
     * Функция для получения всех категорий в виде многомерного массива (дерева).
     * Исключает определенную категорию и всех ее потомков, а также категории с определенными статусами.
     *
     * @param int|null $excludeCategoryID ID категории, которую нужно исключить вместе с ее потомками (по умолчанию null).
     * @param array|null $excludeStatuses Массив статусов, которые нужно исключить (по умолчанию null).
     * @param bool $exclude_null Флаг исключения категории "Без категории".
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array Массив, представляющий дерево категорий.
     */
    public function getCategoriesTree($excludeCategoryID = null, $excludeStatuses = null, $exclude_null = null, $language_code = ENV_DEF_LANG) {
        $query = "SELECT * FROM ?n WHERE language_code = ?s";
        $params = [Constants::CATEGORIES_TABLE, $language_code];
        $categories = SafeMySQL::gi()->getAll($query, ...$params);
        // Исключаем потомков выбранной категории
        if ($excludeCategoryID) {
            $excludedDescendants = $this->getDescendantCategoryIds($categories, $excludeCategoryID);
            $categories = array_filter($categories, function ($category) use ($excludedDescendants, $excludeCategoryID) {
                return !in_array($category['category_id'], $excludedDescendants) && $category['category_id'] != $excludeCategoryID;
            });
        }
        // Фильтруем по статусу, если необходимо
        if ($excludeStatuses !== null && is_array($excludeStatuses)) {
            $categories = array_filter($categories, function ($category) use ($excludeStatuses) {
                return !in_array($category['status'], $excludeStatuses);
            });
        }
        // Строим дерево категорий
        $categoriesArray = [];
        foreach ($categories as $category) {
            $categoriesArray[$category['parent_id'] ?: 0][] = $category;
        }
        $res = $this->buildTree($categoriesArray);
        // Добавляем категорию "Без категории", если нужно
        if (!$exclude_null) {
            array_unshift($res, [
                'status' => 'active',
                'category_id' => 0,
                'title' => 'Без категории',
                'type_id' => 0,
                'parent_id' => null
            ]);
        }
        return $res;
    }

    /**
     * Вспомогательная функция для получения всех потомков указанной категории.
     * @param array $categories Массив всех категорий.
     * @param int $parentId ID родительской категории.
     * @return array Массив ID всех потомков.
     */
    private function getDescendantCategoryIds($categories, $parentId) {
        $descendants = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $descendants[] = $category['category_id'];
                $descendants = array_merge($descendants, $this->getDescendantCategoryIds($categories, $category['category_id']));
            }
        }
        return $descendants;
    }

    /**
     * Рекурсивная функция для построения дерева категорий с учетом уровня вложенности.
     * @param array $categories Массив категорий.
     * @param int $parentId ID родительской категории, по умолчанию 0.
     * @param int $level Текущий уровень вложенности, по умолчанию 0.
     * @return array Дерево категорий с уровнями вложенности.
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
                    'parent_id' => $category['parent_id'],
                    'level' => $level,
                    'children' => $this->buildTree($categories, $category['category_id'], $level + 1)
                ];
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
        $category_data['language_code'] = $language_code;
        // Если есть родитель то можно записать только такой же тип категории или дочерний
        // НУЖНО ПЕРЕДЕЛАТЬ TODO

        if ($category_data['parent_id']) {
            $category_data['type_id'] = SafeMySQL::gi()->getOne('SELECT type_id FROM ?n WHERE category_id = ?i', Constants::CATEGORIES_TABLE, $category_data['parent_id']);
        } else if (!$category_data['type_id'] || !is_numeric($category_data['type_id'])) {
            SysClass::pre_file('error', 'update_category_data', 'error type_id', $category_data);
            return false;
        }

        if (empty($category_data['title'])) {
            SysClass::pre_file('error', 'update_category_data', 'empty title', $category_data);
            return false;
        }
        if (!isset($category_data['description'])) {
            $category_data['description'] = $category_data['title'];
        }
        if (!empty($category_data['category_id']) && $category_data['category_id'] != 0) {
            $category_id = $category_data['category_id'];
            unset($category_data['category_id']); // Удаляем category_id из массива данных, чтобы избежать его обновление
            $sql = "UPDATE ?n SET ?u WHERE `category_id` = ?i AND language_code = ?s";
            $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TABLE, $category_data, $category_id, $language_code);
            if (!$result) {
                SysClass::pre_file('error', 'update_category_data', 'error SQL ' . SafeMySQL::gi()->parse($sql, Constants::CATEGORIES_TABLE, $category_data, $category_id, $language_code));
            }
            return $result ? $category_id : false;
        } else {
            unset($category_data['category_id']);
        }
        // Проверяем уникальность названия в рамках одного типа
        $existingCategory = SafeMySQL::gi()->getRow(
                "SELECT `category_id` FROM ?n WHERE `title` = ?s AND type_id = ?i AND language_code = ?s",
                Constants::CATEGORIES_TABLE,
                $category_data['title'], $category_data['type_id'], $language_code
        );
        if ($existingCategory) {
            ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Не уникальное имя в рамках одного типа категории!', 'status' => 'danger']);
            SysClass::pre_file('error', 'update_category_data', 'existingCategory title: ' . $category_data['title'] . ' type_id: ' . $category_data['type_id']);
            return false;
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TABLE, $category_data);
        if (!$result) {
            SysClass::pre_file('error', 'update_category_data', 'error SQL ' . SafeMySQL::gi()->parse($sql, Constants::CATEGORIES_TABLE, $category_data));
        }
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удаляет категорию по указанному category_id из таблицы Constants::CATEGORIES_TABLE
     * @param int $category_id Идентификатор сущности для удаления
     * @return bool Возвращает true в случае успешного удаления, или false в случае ошибки
     */
    public function delete_category($category_id) {
        try {
            $sql = "DELETE FROM ?n WHERE `category_id` = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TABLE, $category_id);
            return $result ? [] : ['error' => 'Error query DELETE'];
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'foreign key constraint fails') !== false) {
                return ['error' => 'Невозможно удалить категорию, поскольку существуют объекты, ссылающиеся на неё!'];
            }
            return ['error' => $errorMessage];
        }
    }

    /**
     * Вернёт все сущности категории или множества категорий
     * @param int|array $category_ids ID категории
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return type
     */
    public function get_category_entities(int $category_ids = 0, $language_code = ENV_DEF_LANG) {
        if (!is_array($category_ids)) {
            $category_ids = [$category_ids];
        } else {
            $category_ids = implode(',', $category_ids);
        }
        $sql = 'SELECT entity_id, title, status, short_description, parent_entity_id FROM ?n WHERE category_id IN (?a) AND language_code = ?s';
        return SafeMySQL::gi()->getAll($sql, Constants::ENTITIES_TABLE, $category_ids, $language_code);
    }

    /**
     * Получает иерархический массив всех родительских категорий для указанной категории
     * @param int   $category_id Идентификатор категории для поиска родительских категорий
     * @param array $categories   Массив категорий для поиска внутри
     * @return array Иерархический массив родительских категорий
     */
    private function get_parent_categories_hierarchy(int $category_id, array $categories): array {
        $parentIds = [];
        foreach ($categories as $category) {
            if ($category['category_id'] == $category_id) {
                if ($category['parent_id']) {
                    $parentIds[] = (int) $category['parent_id'];
                    $parentIds = array_merge($parentIds, $this->get_parent_categories_hierarchy($category['parent_id'], $categories));
                }
                break;
            }
        }
        return array_reverse($parentIds);
    }

    /**
     * Получает путь категории в виде строки, включая родительские категории
     * @param int $category_id Идентификатор категории, для которой нужно получить путь
     * @return string Строка, представляющая путь категории в виде "parent/child/grandchild/category_id"
     * Или массив при  $string = false
     */
    public function get_patch_category(int $category_id, $string = true) {
        $query = "SELECT category_id, parent_id FROM ?n";
        $categories = SafeMySQL::gi()->getAll($query, Constants::CATEGORIES_TABLE);
        $res = $this->get_parent_categories_hierarchy($category_id, $categories);
        if (count($res)) {
            return $string ? implode('/', $res) . '/' . $category_id : array_merge($res, [$category_id]);
        } else {
            return $category_id;
        }
    }

}
