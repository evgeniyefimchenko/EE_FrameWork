<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;

/**
 * Модель работы с страницами
 */
class ModelPages {

    /**
     * Получает данные всех страниц с возможностью сортировки, фильтрации и ограничения количества записей
     * @param string $order Строка с сортировкой (например, 'page_id ASC')
     * @param string|null $where Условие для фильтрации данных (опционально)
     * @param int $start Начальная позиция для выборки (опционально)
     * @param int $limit Количество записей для выборки (опционально)
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return array Массив с данными страниц
     */
    public function getPagesData($order = 'page_id ASC', $where = null, $start = 0, $limit = 100, $language_code = ENV_DEF_LANG) {
        $start = $start ? $start : 0;
        // Проверка, содержит ли $where или $order type_id
        $needsJoin = strpos($where, 'type_id') !== false || strpos($order, 'type_id') !== false;
        $languageCondition = "language_code = ?s";
        if ($where) {
            $where = "($where) AND $languageCondition";
        } else {
            $where = $languageCondition;
        }
        if ($needsJoin) {
            // Если type_id присутствует в $where или $order, применяем JOIN
            $order = SysClass::ee_addPrefixToFields($order, SysClass::ee_getFieldsTable(Constants::PAGES_TABLE), 'e.');
            $where = SysClass::ee_addPrefixToFields($where, SysClass::ee_getFieldsTable(Constants::PAGES_TABLE), 'e.');
            $order = str_replace('type_id', 't.type_id', $order);
            $where = str_replace('type_id', 't.type_id', $where);
            $sql_pages = "
            SELECT e.page_id
            FROM ?n AS e
            LEFT JOIN ?n AS c ON e.category_id = c.category_id
            LEFT JOIN ?n AS t ON c.type_id = t.type_id
            WHERE $where
            ORDER BY $order
            LIMIT ?i, ?i";
            $res_array = SafeMySQL::gi()->getAll($sql_pages, Constants::PAGES_TABLE, Constants::CATEGORIES_TABLE, Constants::CATEGORIES_TYPES_TABLE, $language_code, $start, $limit);
        } else {
            // Если type_id отсутствует, применяем простой запрос
            $orderString = $order ? $order : 'e.page_id ASC';
            $sql_pages = "SELECT e.page_id FROM ?n as e WHERE $where ORDER BY $orderString LIMIT ?i, ?i";
            $res_array = SafeMySQL::gi()->getAll($sql_pages, Constants::PAGES_TABLE, $language_code, $start, $limit);
        }
        $res = [];
        foreach ($res_array as $page) {
            $res[] = $this->getPageData($page['page_id']);
        }
        if ($needsJoin) {
            $sql_count = "
            SELECT COUNT(*) as total_count
            FROM ?n AS e
            LEFT JOIN ?n AS c ON e.category_id = c.category_id
            LEFT JOIN ?n AS t ON c.type_id = t.type_id
            WHERE $where";
            $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PAGES_TABLE, Constants::CATEGORIES_TABLE, Constants::CATEGORIES_TYPES_TABLE, $language_code);
        } else {
            $sql_count = "SELECT COUNT(*) as total_count FROM ?n WHERE $where";
            $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PAGES_TABLE, $language_code);
        }
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные одной страницы по её ID
     * @param int $pageId ID страницы, для которой нужно получить данные
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @return array|null Массив с данными страницы или NULL, если страница не найдена
     */
    public function getPageData($pageId, $language_code = ENV_DEF_LANG, $status = Constants::ALL_STATUS) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        $sql_page = "
        SELECT e.*, c.title as category_title, t.name as type_name 
        FROM ?n AS e 
        LEFT JOIN ?n AS c ON e.category_id = c.category_id AND c.language_code = ?s
        LEFT JOIN ?n AS t ON c.type_id = t.type_id AND t.language_code = ?s
        WHERE e.status IN (?a) AND e.page_id = ?i AND e.language_code = ?s";
        $pageData = SafeMySQL::gi()->getRow(
                $sql_page,
                Constants::PAGES_TABLE,
                Constants::CATEGORIES_TABLE,
                $language_code,
                Constants::CATEGORIES_TYPES_TABLE,
                $language_code,
                $status,
                $pageId,
                $language_code
        );
        if (!$pageData) {
            return null;
        }
        return $pageData;
    }

    /**
     * Получает все страницы, исключая одну по её ID
     * @param int|null $excludePageId ID страницы для исключения из результатов (по умолчанию NULL)
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @return array Массив ассоциативных массивов, каждый из которых содержит ID и заголовок страницы. Первый элемент массива всегда имеет page_id 0 и пустой заголовок
     */
    public function getAllPages($excludePageId = null, $language_code = ENV_DEF_LANG, $status = Constants::ALL_STATUS) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        $add_query = '';
        if (is_numeric($excludePageId)) {
            $add_query = ' AND page_id != ' . $excludePageId;
        }
        $sql_pages = "SELECT page_id, title FROM ?n WHERE status IN (?a) AND language_code = ?s" . $add_query;
        $res = SafeMySQL::gi()->getAll($sql_pages, Constants::PAGES_TABLE, $status, $language_code);  // Добавление параметра $language_code    
        return $res;
    }
    
    /**
     * Возвращает заголовок страницы по её идентификатору и коду языка.
     * @param int $pageId Идентификатор страницы.
     * @param string $languageCode Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG.
     * @return string|null Возвращает заголовок страницы или null, если страница не найдена.
     */
    public function getPageTitleById($pageId, $languageCode = ENV_DEF_LANG) {
        if (empty($pageId) || !is_numeric($pageId)) {
            return null; // Если идентификатор страницы некорректен, возвращаем null
        }

        // Выполняем запрос к базе данных для получения заголовка
        $title = SafeMySQL::gi()->getOne(
            'SELECT title FROM ?n WHERE page_id = ?i AND language_code = ?s',
            Constants::PAGES_TABLE,
            $pageId,
            $languageCode
        );

        return $title ?: null; // Возвращаем null, если заголовок не найден
    }

    /**
     * Обновляет данные страницы
     * @param array $pageData Массив данных страницы
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return int|bool Возвращает идентификатор обновленной страницы в случае успеха, или false в случае ошибки
     */
    public function updatePageData($pageData = [], $language_code = ENV_DEF_LANG) {
        $pageData = SafeMySQL::gi()->filterArray($pageData, SysClass::ee_getFieldsTable(Constants::PAGES_TABLE));
        $pageData = array_map('trim', $pageData);
        $pageData = SysClass::ee_convertArrayValuesToNumbers($pageData);
        if (empty($pageData['category_id']) || empty($pageData['title'])) {
            return new \classes\system\ErrorLogger('Отсутствует category_id или title', __FUNCTION__);
        }
        $pageData['parent_page_id'] = isset($pageData['parent_page_id']) && $pageData['parent_page_id'] !== 0 ? $pageData['parent_page_id'] : NULL;
        
        $pageData['category_id'] = !empty($pageData['parent_page_id']) ?
                (int) SafeMySQL::gi()->getOne('SELECT category_id FROM ?n WHERE page_id=?i', Constants::PAGES_TABLE, $pageData['parent_page_id']) :
                (int) $pageData['category_id'];
        $pageData['language_code'] = $language_code;  // добавлено
        if (empty($pageData['title'])) {
            return new \classes\system\ErrorLogger('Отсутствует title', __FUNCTION__);
        }
        if (!empty($pageData['page_id'])) {
            // Проверяем, не является ли parent_page_id предком для текущей страницы
            if (!empty($pageData['parent_page_id']) && $this->isAncestorPage($pageData['parent_page_id'], $pageData['page_id'])) {
                return new \classes\system\ErrorLogger('Страница не может быть родителем для самой себя или своих предков', __FUNCTION__);
            }            
            $pageId = $pageData['page_id'];
            unset($pageData['page_id']);
            $sql = "UPDATE ?n SET ?u WHERE page_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PAGES_TABLE, $pageData, $pageId);
            $method = 'update';
            return $result ? $pageId : false;
        } else {
            unset($pageData['page_id']);
            $method = 'insert';
            $sql = "INSERT INTO ?n SET ?u";
            $result = SafeMySQL::gi()->query($sql, Constants::PAGES_TABLE, $pageData);
            $pageId = SafeMySQL::gi()->insertId();
        }
        if ($method == 'insert') { // Записываем значения свойств для новой страницы
            $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
            $objectModelProperties->createPropertiesValueEntities('page', $pageId, $pageData);
        }
        return $result ? $pageId : false;
    }
    
    /**
     * Проверяет, можно ли назначить родителя ancestorPageId для страницы pageId.
     * @param int $ancestorPageId Идентификатор предполагаемого родителя.
     * @param int $pageId Идентификатор страницы, для которой назначается родитель.
     * @return bool Возвращает true, если назначение невозможно (есть циклическая зависимость), иначе false.
     */
    private function isAncestorPage($ancestorPageId, $pageId) {
        if ($ancestorPageId === null || $pageId === null) {
            return false;
        }
        $currentParentId = $ancestorPageId;
        while ($currentParentId !== null) {
            if ($currentParentId == $pageId) {
                return true;
            }
            $currentParentId = SafeMySQL::gi()->getOne('SELECT parent_page_id FROM ?n WHERE page_id = ?i', Constants::PAGES_TABLE, $currentParentId);
        }
        $descendants = $this->getDescendants($ancestorPageId);
        if (in_array($pageId, $descendants, true)) {
            return true;
        }
        return false;
    }

    /**
     * Возвращает массив всех потомков для заданной страницы.
     * @param int $pageId Идентификатор страницы, для которой ищем потомков.
     * @return array Массив идентификаторов потомков.
     */
    private function getDescendants($pageId) {
        $descendants = [];
        $queue = [$pageId];
        while (!empty($queue)) {
            $currentId = array_shift($queue);
            $children = SafeMySQL::gi()->getCol('SELECT page_id FROM ?n WHERE parent_page_id = ?i', Constants::PAGES_TABLE, $currentId);
            foreach ($children as $childId) {
                if (!in_array($childId, $descendants, true)) {
                    $descendants[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $descendants;
    }

    /**
     * Удаляет страницу по указанному page_id из таблицы pages и связанные значения из property_values
     * @param int $pageId Идентификатор страницы для удаления
     * @return array Возвращает пустой массив в случае успешного удаления или массив с информацией об ошибке
     */
    public function deletePage($pageId) {
        try {
            $sql_check = "SELECT COUNT(*) FROM ?n WHERE parent_page_id = ?i";
            $count = SafeMySQL::gi()->getOne($sql_check, Constants::PAGES_TABLE, $pageId);
            if ($count > 0) {                
                return new \classes\system\ErrorLogger('Нельзя удалить страницу, так как она является родительской для других.', __FUNCTION__);
            }
            $sql_delete_page = "DELETE FROM ?n WHERE page_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete_page, Constants::PAGES_TABLE, $pageId);
            if ($result) {
                $sql_delete_properties = "DELETE FROM ?n WHERE entity_id = ?i AND entity_type = 'page'";
                SafeMySQL::gi()->query($sql_delete_properties, Constants::PROPERTY_VALUES_TABLE, $pageId);
                return [];
            }
            return new \classes\system\ErrorLogger('Ошибка при выполнении запроса DELETE для ' . $pageId, __FUNCTION__);
        } catch (Exception $e) {
            return new \classes\system\ErrorLogger($e->getMessage(), __FUNCTION__);
        }
    }
}
