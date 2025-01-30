<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;
use classes\system\Hook;

/**
 * Модель работы с типами категорий
 */
class ModelCategoriesTypes {

    /**
     * Получает все типы категорий, с учетом определенных параметров фильтрации и структуры
     * Позволяет выборочно включить или исключить определенные типы и их потомков из результата,
     * а также опционально возвращать данные в иерархической структуре
     * @param int|null $excludeTypeID ID типа, который нужно исключить вместе с его потомками
     *                                Если не null, исключает указанный тип и всех его потомков из результата
     * @param bool $flatArray Если true, возвращает плоский массив типов; если false, возвращает иерархический массив
     *                         Плоский массив содержит типы на одном уровне, иерархический - организован как дерево
     * @param int|null $includeTypeID ID типа, начиная с которого и его потомков нужно включить в результат
     *                                Если не null, результат будет ограничен указанным типом и его потомками
     * @param string $languageCode Код языка по стандарту ISO. Используется для выборки типов определенного языка
     *                              По умолчанию используется значение из константы ENV_DEF_LANG
     * @return array Массив типов, организованный в соответствии с указанными параметрами
     *               Может быть как плоским списком, так и иерархически структурированным деревом
     */
    public function getAllTypes(int $excludeTypeID = null, bool $flatArray = true, int $includeTypeID = null, string $languageCode = ENV_DEF_LANG) {
        $sql = "SELECT type_id, parent_type_id, name FROM ?n WHERE language_code = ?s";
        $types = SafeMySQL::gi()->getAll($sql, Constants::CATEGORIES_TYPES_TABLE, $languageCode);
        if ($includeTypeID !== null) {
            // Выводим иерархию начиная с $includeTypeID типа
            $types = $this->includeTypeAndDescendants($types, $includeTypeID);
            return $flatArray ? $types : $this->buildHierarchyType($types, $includeTypeID);
        } elseif ($excludeTypeID > 0) {
            // Исключаем указанный тип и его потомков
            $types = $this->excludeTypeAndDescendants($types, $excludeTypeID);
        }        
        return $flatArray ? $types : $this->buildHierarchyType($types);
    }

    /**
     * Возвращает подмножество типов, включая указанный тип и всех его потомков
     * Эта функция используется для создания массива типов, который включает в себя конкретный тип
     * и всех его потомков в иерархической структуре.
     * Это может быть полезно, например, при отображении поддерева типов, начиная с определенного узла
     * @param array $types Исходный массив всех типов, каждый элемент которого содержит ключи 'type_id', 'parent_type_id', и 'name'
     *                     'type_id' - уникальный идентификатор типа,
     *                     'parent_type_id' - ID родительского типа (если таковой имеется)
     * @param int $includeTypeID ID типа, который нужно включить вместе с его потомками
     *                            В результат включаются все типы, соответствующие этому ID и его потомки
     * @return array Отфильтрованный массив, включающий указанный тип и всех его потомков
     *               Массив сохраняет свою исходную структуру, но ограничивается только включенными элементами
     */
    private function includeTypeAndDescendants($types, $includeTypeID) {
        $included_ids = $this->getDescendantTypeIds($types, $includeTypeID);
        $included_ids[] = $includeTypeID; // Включаем сам тип

        return array_filter($types, function ($type) use ($included_ids) {
            return in_array($type['type_id'], $included_ids);
        });
    }

    /**
     * Исключает указанный тип и всех его потомков из массива типов.
     * Эта функция используется для создания массива типов, исключая определенный тип и всех его потомков.
     * Это полезно, например, при отображении иерархической структуры типов без конкретного типа и его дочерних элементов.
     * @param array $types Исходный массив всех типов, каждый элемент которого содержит ключи 'type_id', 'parent_type_id', и 'name'.
     *                     'type_id' - уникальный идентификатор типа,
     *                     'parent_type_id' - ID родительского типа (если таковой имеется).
     * @param int $excludeTypeID ID типа, который нужно исключить вместе с его потомками.
     *                            Все типы, соответствующие этому ID и его потомки, будут удалены из результата.
     * @return array Отфильтрованный массив, исключающий указанный тип и всех его потомков.
     *               Массив сохраняет свою исходную структуру, за исключением удаленных элементов.
     */
    private function excludeTypeAndDescendants($types, $excludeTypeID) {
        $excluded_ids = $this->getDescendantTypeIds($types, $excludeTypeID);
        $excluded_ids[] = $excludeTypeID; // Исключаем также и сам тип
        return array_filter($types, function ($type) use ($excluded_ids) {
            return !in_array($type['type_id'], $excluded_ids);
        });
    }

    /**
     * Рекурсивно собирает ID всех потомков указанного типа
     * Используется внутри других функций для определения потомков заданного типа,
     * включая не только прямых потомков, но и все последующие уровни вложенности
     * @param array $types Массив типов, каждый элемент которого содержит ключи 'type_id' и 'parent_type_id'
     *                     'type_id' - уникальный идентификатор типа,
     *                     'parent_type_id' - ID родительского типа (если таковой имеется)
     * @param int $parent_type_id ID родительского типа, для которого необходимо найти всех потомков
     * @param array &$descendants Массив для сбора ID всех потомков
     *                            Используется для рекурсивного сбора данных и аккумулирования результатов поиска
     *                            Изначально передается пустым и наполняется в процессе выполнения функции
     * @return array Массив, содержащий ID всех потомков указанного типа, включая все уровни вложенности
     */
    private function getDescendantTypeIds($types, $parent_type_id, &$descendants = []) {
        foreach ($types as $type) {
            if ($type['parent_type_id'] == $parent_type_id) {
                $descendants[] = $type['type_id'];
                $this->getDescendantTypeIds($types, $type['type_id'], $descendants);
            }
        }
        return $descendants;
    }

    /**
     * Строит иерархическую структуру типов из переданного массива
     * Преобразует плоский массив типов в многоуровневую иерархическую структуру
     * Каждый тип может иметь дочерние типы, которые представлены в виде вложенного массива 'children'
     * @param array $types Массив типов, каждый элемент которого содержит ключи 'type_id', 'parent_type_id', и 'name'
     *                     'type_id' является уникальным идентификатором типа,
     *                     'parent_type_id' указывает на родительский тип (если есть),
     *                     'name' - название типа
     * @param int|null $rootId ID корневого типа, начиная с которого строится дерево. Если null, строится полное дерево
     *                         Этот параметр позволяет сформировать дерево, начиная с указанного типа и включая всех его потомков
     * @return array Возвращает иерархически организованный массив типов
     *               Если задан $rootId, возвращает дерево, начинающееся с указанного корневого типа и его потомков
     *               В противном случае, возвращает полное дерево со всеми типами
     */
    private function buildHierarchyType($types, $rootId = null) {
        if (empty($types)) {
            return [];
        }
        $children = [];
        $rootElement = null;
        foreach ($types as $type) {
            if ($rootId !== null && $type['type_id'] == $rootId) {
                $rootElement = $type;
            }
            $children[$type['parent_type_id']][] = $type;
        }
        $buildTree = function ($parentId) use ($children, &$buildTree) {
            $branch = [];
            if (isset($children[$parentId])) {
                foreach ($children[$parentId] as $child) {
                    $child['children'] = $buildTree($child['type_id']);
                    $branch[] = $child;
                }
            }
            return $branch;
        };
        // Если rootId задан, возвращаем дерево, начинающееся с rootId
        if ($rootId !== null && $rootElement !== null) {
            $rootElement['children'] = $buildTree($rootId);
            return [$rootElement];
        }
        // В противном случае, возвращаем полное дерево
        return $buildTree(null);
    }

    /**
     * Рекурсивно получает все подчинённые типы по переданному type_id
     * @param int $type_id ID типа, для которого нужно получить подчинённые типы
     * @return array Массив type_id всех подчинённых типов по переданному type_id
     */
    function getAllTypeChildrensIds($type_id) {
        $subTypes = [];
        $query = "SELECT type_id FROM ?n WHERE parent_type_id = ?i";
        $result = SafeMySQL::gi()->getAll($query, Constants::CATEGORIES_TYPES_TABLE, $type_id);
        foreach ($result as $row) {
            $subTypes[] = $row['type_id'];
            $subTypes = array_merge($subTypes, $this->getAllTypeChildrensIds($row['type_id']));
        }
        return $subTypes;
    }

    /**
     * Получает все type_id начиная с переданного и всех вышестоящих родителей
     * Использует рекурсивный метод на уровне PHP для нахождения всех родительских type_id
     * @param int $type_id Начальный type_id для поиска
     * @return array Массив всех найденных type_id включая начальный и всех родителей
     */
    public function getAllTypeParentsIds(int $type_id): array {
        $result = [];
        if (!$type_id) {
            return $result;
        }
        $this->findParentsType($type_id, $result);
        return $result;
    }

    /**
     * Рекурсивно находит всех родителей для заданного type_id и добавляет их в массив результатов
     * @param int $type_id Начальный type_id для поиска
     * @param array $result Массив для сохранения найденных type_id
     */
    private function findParentsType(int $type_id, array &$result) {
        $sql = 'SELECT parent_type_id FROM ?n WHERE type_id = ?i';
        $parent_type_id = SafeMySQL::gi()->getOne($sql, Constants::CATEGORIES_TYPES_TABLE, $type_id);
        if ($parent_type_id !== null) {
            $result[] = (int) $parent_type_id;
            $this->findParentsType($parent_type_id, $result);
        }
    }

    /**
     * Получает данные о типах с учетом параметров сортировки, фильтрации, пагинации и языка.
     * @param string $order Параметр для сортировки результатов запроса (по умолчанию: 'type_id ASC').
     * @param string|null $where Условие для фильтрации результатов запроса (по умолчанию: NULL).
     * @param int $start Начальная позиция для выборки результатов запроса (по умолчанию: 0).
     * @param int $limit Максимальное количество результатов для выборки (по умолчанию: 100).
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return array Массив, содержащий данные о типах и общее количество типов.
     */
    public function getCategoriesTypesData($order = 'type_id ASC', $where = NULL, $start = 0, $limit = 100, $languageCode = ENV_DEF_LANG) {
        $orderString = $order ?: 'type_id ASC';
        $whereString = $where ? $where . " AND language_code = '$languageCode'" : "WHERE language_code = '$languageCode'";
        $start = $start ?: 0;
        $sql_types = "SELECT type_id FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        $res_array = SafeMySQL::gi()->getAll($sql_types, Constants::CATEGORIES_TYPES_TABLE, $start, $limit);
        $res = [];
        foreach ($res_array as $type) {
            $res[] = $this->getCategoriesTypeData($type['type_id'], $languageCode);
        }
        $sql_count = "SELECT COUNT(DISTINCT type_id) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::CATEGORIES_TYPES_TABLE);
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные конкретного типа по его ID и языку
     * @param int $type_id ID типа, данные которого необходимо получить
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return array|null Ассоциативный массив с данными типа или null, если тип не найден
     */
    public function getCategoriesTypeData($type_id, $languageCode = ENV_DEF_LANG) {
        if (!$type_id) {
            return null;
        }
        $sql = "SELECT * FROM ?n WHERE type_id = ?i AND language_code = ?s";
        $typeData = SafeMySQL::gi()->getRow($sql, Constants::CATEGORIES_TYPES_TABLE, $type_id, $languageCode);
        return $typeData;
    }

    /**
     * Обновляет существующий тип или создает новый с учетом языка
     * @param array $typeData Ассоциативный массив с данными типа. Должен содержать ключи 'name' и 'description', и опционально 'type_id'
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return int|bool ID нового или обновленного типа или false в случае ошибки
     */
    public function updateCategoriesTypeData(array $typeData = [], string $languageCode = ENV_DEF_LANG):bool|int {
        $typeData = SafeMySQL::gi()->filterArray($typeData, SysClass::ee_getFieldsTable(Constants::CATEGORIES_TYPES_TABLE));
        $typeData = array_map('trim', $typeData);
        $typeData['language_code'] = $languageCode;
        if (empty($typeData['name'])) {
            return false;
        }
        if (!isset($typeData['description'])) {
            $typeData['description'] = $typeData['name'];
        }
        if (!isset($typeData['parent_type_id']) || (isset($typeData['parent_type_id']) && !$typeData['parent_type_id'])) {
            $typeData['parent_type_id'] = NULL;
        }
        if (!empty($typeData['type_id']) && $typeData['type_id'] != 0) {
            $type_id = $typeData['type_id'];
            unset($typeData['type_id']); // Удаляем type_id из массива данных, чтобы избежать его обновление
            $sql = "UPDATE ?n SET ?u WHERE type_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TYPES_TABLE, $typeData, $type_id);
            return $result ? $type_id : false;
        }
        // Проверяем уникальность имени
        $existingType = $this->getIdCategoriesTypeByName($typeData['name']);
        if ($existingType) {
            return false; // или вернуть какое-то сообщение об ошибке
        }
        unset($typeData['type_id']);
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TYPES_TABLE, $typeData);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удалит тип категории
     * @param int $type_id
     */
    public function deleteCategoriesType(int $type_id) {
        try {
            $sql = 'SELECT title FROM ?n WHERE type_id = ?i';
            if ($title = SafeMySQL::gi()->getOne($sql, Constants::CATEGORIES_TABLE, $type_id)) {
                return ['error' => 'Нельзя удалить тип категории <b>' . $this->getNameCategoriesType($type_id) . '</b>,'
                    . 'так как он используется категорией <strong>' . $title . '</strong>'];
            }
            $sql_delete = "DELETE FROM ?n WHERE type_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::CATEGORIES_TYPES_TABLE, $type_id);
            return $result ? [] : ['error' => 'Ошибка при выполнении запроса DELETE'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Вернёт имя типа категории по его ID
     * @param int $type_id
     * @return string|bool
     */
    public function getNameCategoriesType(int $type_id, string $languageCode = ENV_DEF_LANG):string|bool {
        $sql = 'SELECT name FROM ?n WHERE type_id = ?i AND language_code = ?s';
        return SafeMySQL::gi()->getOne($sql, Constants::CATEGORIES_TYPES_TABLE, $type_id, $languageCode);
    }

    /**
     * Вернёт ID типа категории по его названию
     * @param string $name
     * @return string|bool
     */
    public function getIdCategoriesTypeByName(string $name, string $languageCode = ENV_DEF_LANG):string|bool {
        $sql = 'SELECT type_id FROM ?n WHERE name = ?s AND language_code = ?s';
        return SafeMySQL::gi()->getOne($sql, Constants::CATEGORIES_TYPES_TABLE, $name, $languageCode);        
    }
    
    /**
     * Вернёт все наборы свойств привязанные к типу категории
     * @param int $typeIds - Типы категорий
     * @return array
     */
    public function getCategoriesTypeSetsData(mixed $typeIds): array {
        if (!is_array($typeIds)) {
            $typeIds = [$typeIds];
        }
        $sql = 'SELECT set_id FROM ?n WHERE type_id IN (?a)';
        return SafeMySQL::gi()->getCol($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $typeIds);
    }

    /**
     * Обновляет связи между типами категориями и наборами свойств
     * Для переданного и всех его потомков
     * @param int $type_id Идентификатор типа категории для обновления связей
     * @param int|array $set_ids Идентификаторы наборов свойств для связывания с указанным типом категории
     */
    public function updateCategoriesTypeSetsData(int $type_id, mixed $set_ids): void {
        if (!is_array($set_ids)) {
            $set_ids = [$set_ids];
        }
        $allTypeChildrenIds = $this->getAllTypeChildrensIds($type_id);
        $allTypeChildrenIds[] = $type_id;
        $this->deleteCategoriesTypeSetsData($allTypeChildrenIds);
        $sql = "INSERT INTO ?n SET ?u";
        foreach ($allTypeChildrenIds as $item_type_id) {
            foreach ($set_ids as $set_id) {
                $data = ['type_id' => $item_type_id, 'set_id' => $set_id];
                SafeMySQL::gi()->query($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $data);
            }
        }
        Hook::run('postUpdateCategoriesTypeSetsData', $type_id, $set_ids, $allTypeChildrenIds);
    }

    /**
     * Удаляет связи между типами категорий и наборами свойств для указанных идентификаторов типов категорий
     * @param int|array $type_ids Идентификаторы типов категорий для удаления связей
     * @param int|array $set_ids Идентификаторы наборов
     * @return void
     */
    public function deleteCategoriesTypeSetsData(mixed $type_ids, mixed $set_ids = false): bool {
        $add_query = '';
        if (!is_array($type_ids)) {
            $type_ids = [$type_ids];
        }
        if ($set_ids) {
            if (is_array($set_ids)) {
                $set_ids = implode(',', $set_ids);
            }
            $add_query = ' AND set_id IN (' . $set_ids . ')';
        }
        $sql_delete = "DELETE FROM ?n WHERE type_id IN (?a)" . $add_query;
        return SafeMySQL::gi()->query($sql_delete, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, $type_ids);
    }

    /**
    * Получает список идентификаторов категорий по заданным идентификаторам типов
    * @param int|array $typeIds Идентификатор типа или массив идентификаторов типов
    * @param string $languageCode Код языка в формате ISO 3166-2. По умолчанию используется значение ENV_DEF_LANG
    * @return array Массив идентификаторов категорий, соответствующих заданным типам и языку
    */
    public function getAllCategoriesByType(int|array $typeIds, $languageCode = ENV_DEF_LANG): array {
        if (!is_array($typeIds)) {
            $typeIds = [$typeIds];
        }
        $sql = 'SELECT category_id FROM ?n WHERE type_id IN (?a) AND language_code = ?s';
        return SafeMySQL::gi()->getCol($sql, Constants::CATEGORIES_TABLE, $typeIds, $languageCode);
    }
    
    /**
     * Получает данные, связывающие категории, наборы свойств и страниц на основе заданных идентификаторов наборов свойств
     * @param array  $setIds Массив идентификаторов наборов свойств (set_id)
     * @param string $languageCode Код языка в формате ISO 3166-2. По умолчанию используется значение ENV_DEF_LANG
     * @return array Массив ассоциативных массивов с ключами 'category_id', 'set_id' и 'page_id'
     *               Каждая запись представляет связь между категорией, набором свойств и сущностью
     *               Если у категории нет связанных сущностей, 'page_id' может быть null
     */
    public function getCategorySetPageData(array $setIds, string $languageCode = ENV_DEF_LANG): array {
        $sql = 'SELECT c.category_id, cts.set_id, e.page_id FROM ?n c INNER JOIN ?n cts ON c.type_id = cts.type_id LEFT JOIN ?n e ON c.category_id = e.category_id WHERE 
                cts.set_id IN (?a) AND c.language_code = ?s ORDER BY c.category_id, cts.set_id, e.page_id';
        $result = SafeMySQL::gi()->getAll($sql, Constants::CATEGORIES_TABLE, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, Constants::PAGES_TABLE, $setIds, $languageCode);
        return $result;
    }

}
