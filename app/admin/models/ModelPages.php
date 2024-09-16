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
     * @param int $page_id ID страницы, для которой нужно получить данные
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @return array|null Массив с данными страницы или NULL, если страница не найдена
     */
    public function getPageData($page_id, $language_code = ENV_DEF_LANG, $status = Constants::ALL_STATUS) {
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
        $page_data = SafeMySQL::gi()->getRow(
                $sql_page,
                Constants::PAGES_TABLE,
                Constants::CATEGORIES_TABLE,
                $language_code,                
                Constants::CATEGORIES_TYPES_TABLE,                
                $language_code,
                $status,
                $page_id,
                $language_code
        );
        if (!$page_data) {
            return null;
        }
        return $page_data;
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
     * Обновляет данные страницы
     * @param array $page_data Массив данных страницы
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return int|bool Возвращает идентификатор обновленной страницы в случае успеха, или false в случае ошибки
     */
    public function updatePageData($page_data = [], $language_code = ENV_DEF_LANG) {
        $page_data = SafeMySQL::gi()->filterArray($page_data, SysClass::ee_getFieldsTable(Constants::PAGES_TABLE));
        $page_data = array_map('trim', $page_data);
        $page_data = SysClass::ee_convertArrayValuesToNumbers($page_data);       
        $page_data['parent_page_id'] = isset($page_data['parent_page_id']) && $page_data['parent_page_id'] !== 0 ? page_data['parent_page_id'] : NULL;
        $page_data['category_id'] = !empty($page_data['parent_page_id']) ?
                (int) SafeMySQL::gi()->getOne('SELECT category_id FROM ?n WHERE page_id=?i', Constants::PAGES_TABLE, $page_data['parent_page_id']) : 
                (int) $page_data['category_id'];
        $page_data['language_code'] = $language_code;  // добавлено
        if (empty($page_data['title'])) {
            return false;
        }
        if (!empty($page_data['page_id']) && $page_data['page_id'] != 0) {
            $page_id = $page_data['page_id'];
            unset($page_data['page_id']);
            $sql = "UPDATE ?n SET ?u WHERE page_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::PAGES_TABLE, $page_data, $page_id);
            return $result ? $page_id : false;
        } else {
            unset($page_data['page_id']);
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::PAGES_TABLE, $page_data);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удаляет страницу по указанному page_id из таблицы pages
     * @param int $page_id Идентификатор страницы для удаления
     * @return bool Возвращает true в случае успешного удаления, или false в случае ошибки
     */
    public function deletePage($page_id) {
        try {
            $sql_check = "SELECT COUNT(*) FROM ?n WHERE parent_page_id = ?i";
            $count = SafeMySQL::gi()->getOne($sql_check, Constants::PAGES_TABLE, $page_id);
            if ($count > 0) {
                return ['error' => 'Нельзя удалить страницу, так как она является родительской для других.'];
            }
            $sql_delete = "DELETE FROM ?n WHERE page_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::PAGES_TABLE, $page_id);
            return $result ? [] : ['error' => 'Ошибка при выполнении запроса DELETE'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

}
