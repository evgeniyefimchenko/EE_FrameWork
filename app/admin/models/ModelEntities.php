<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;

/**
 * Модель работы с сущностями
 */
class ModelEntities {

    /**
     * Получает данные всех страниц с возможностью сортировки, фильтрации и ограничения количества записей
     * @param string $order Строка с сортировкой (например, 'entity_id ASC')
     * @param string|null $where Условие для фильтрации данных (опционально)
     * @param int $start Начальная позиция для выборки (опционально)
     * @param int $limit Количество записей для выборки (опционально)
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return array Массив с данными страниц
     */
    public function get_entities_data($order = 'entity_id ASC', $where = null, $start = 0, $limit = 100, $language_code = ENV_DEF_LANG) {
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
            $order = SysClass::ee_addPrefixToFields($order, SysClass::ee_get_fields_table(Constants::ENTITIES_TABLE), 'e.');
            $where = SysClass::ee_addPrefixToFields($where, SysClass::ee_get_fields_table(Constants::ENTITIES_TABLE), 'e.');
            $order = str_replace('type_id', 't.type_id', $order);
            $where = str_replace('type_id', 't.type_id', $where);
            $sql_entities = "
            SELECT e.entity_id
            FROM ?n AS e
            LEFT JOIN ?n AS c ON e.category_id = c.category_id
            LEFT JOIN ?n AS t ON c.type_id = t.type_id
            WHERE $where
            ORDER BY $order
            LIMIT ?i, ?i
        ";
            $res_array = SafeMySQL::gi()->getAll($sql_entities, Constants::ENTITIES_TABLE, Constants::CATEGORIES_TABLE, Constants::CATEGORIES_TYPES_TABLE, $language_code, $start, $limit);
        } else {
            // Если type_id отсутствует, применяем простой запрос
            $orderString = $order ? $order : 'e.entity_id ASC';
            $sql_entities = "SELECT e.entity_id FROM ?n as e WHERE $where ORDER BY $orderString LIMIT ?i, ?i";
            $res_array = SafeMySQL::gi()->getAll($sql_entities, Constants::ENTITIES_TABLE, $language_code, $start, $limit);
        }
        $res = [];
        foreach ($res_array as $entity) {
            $res[] = $this->get_entity_data($entity['entity_id']);
        }
        if ($needsJoin) {
            $sql_count = "
            SELECT COUNT(*) as total_count
            FROM ?n AS e
            LEFT JOIN ?n AS c ON e.category_id = c.category_id
            LEFT JOIN ?n AS t ON c.type_id = t.type_id
            WHERE $where
        ";
            $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::ENTITIES_TABLE, Constants::CATEGORIES_TABLE, Constants::CATEGORIES_TYPES_TABLE, $language_code);
        } else {
            $sql_count = "SELECT COUNT(*) as total_count FROM ?n WHERE $where";
            $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::ENTITIES_TABLE, $language_code);
        }
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные одной страницы по её ID
     * @param int $entity_id ID страницы, для которой нужно получить данные
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @return array|null Массив с данными страницы или NULL, если страница не найдена
     */
    public function get_entity_data($entity_id, $language_code = ENV_DEF_LANG, $status = Constants::ALL_STATUS) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }        
        $sql_entity = "
        SELECT e.*, c.title as category_title, t.name as type_name 
        FROM ?n AS e 
        LEFT JOIN ?n AS c ON e.category_id = c.category_id AND c.language_code = ?s
        LEFT JOIN ?n AS t ON c.type_id = t.type_id AND t.language_code = ?s
        WHERE e.status IN (?a) AND e.entity_id = ?i AND e.language_code = ?s";
        $entity_data = SafeMySQL::gi()->getRow(
                $sql_entity,
                Constants::ENTITIES_TABLE,
                Constants::CATEGORIES_TABLE,
                $language_code,                
                Constants::CATEGORIES_TYPES_TABLE,                
                $language_code,
                $status,
                $entity_id,
                $language_code
        );
        if (!$entity_data) {
            return null;
        }
        return $entity_data;
    }

    /**
     * Получает все сущности, исключая одну по её ID
     * @param int|null $excludeEntityId ID сущности для исключения из результатов (по умолчанию NULL)
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @param string $status Статус типа свойства Constants::ALL_STATUS
     * @return array Массив ассоциативных массивов, каждый из которых содержит ID и заголовок сущности. Первый элемент массива всегда имеет entity_id 0 и пустой заголовок
     */
    public function get_all_entities($excludeEntityId = null, $language_code = ENV_DEF_LANG, $status = Constants::ALL_STATUS) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }         
        $add_query = '';
        if (is_numeric($excludeEntityId)) {
            $add_query = ' AND entity_id != ' . $excludeEntityId;
        }
        // Проверка, было ли уже добавлено условие WHERE, и добавление условия для language_code
        $sql_entities = "SELECT entity_id, title FROM ?n WHERE status IN (?a) AND language_code = ?s" . $add_query;
        $res = SafeMySQL::gi()->getAll($sql_entities, Constants::ENTITIES_TABLE, $status, $language_code);  // Добавление параметра $language_code    
        array_unshift($res, [
            'entity_id' => 0,
            'title' => ''
        ]);
        return $res;
    }

    /**
     * Обновляет данные сущности в таблице сущностей.
     * @param array $entity_data Массив данных сущности.
     * @param string $language_code Код языка по стандарту ISO 3166-2. По умолчанию используется значение из константы ENV_DEF_LANG
     * @return int|bool Возвращает идентификатор обновленной сущности в случае успеха, или false в случае ошибки
     */
    public function update_entity_data($entity_data = [], $language_code = ENV_DEF_LANG) {
        $entity_data = SafeMySQL::gi()->filterArray($entity_data, SysClass::ee_get_fields_table(Constants::ENTITIES_TABLE));
        $entity_data = array_map('trim', $entity_data);
        $entity_data = SysClass::ee_convertArrayValuesToNumbers($entity_data);
        $entity_data['parent_entity_id'] = isset($entity_data['parent_entity_id']) && (int) $entity_data['parent_entity_id'] !== 0 ? (int) $entity_data['parent_entity_id'] : NULL;
        $entity_data['category_id'] = isset($entity_data['parent_entity_id']) && (int) $entity_data['parent_entity_id'] !== 0 ? (int) SafeMySQL::gi()->getOne('SELECT category_id FROM ?n WHERE entity_id=?i', Constants::ENTITIES_TABLE, $entity_data['parent_entity_id']) : (int) $entity_data['category_id'];
        $entity_data['language_code'] = $language_code;  // добавлено
        if (empty($entity_data['title'])) {
            return false;
        }
        if (!empty($entity_data['entity_id']) && $entity_data['entity_id'] != 0) {
            $entity_id = $entity_data['entity_id'];
            unset($entity_data['entity_id']);
            $sql = "UPDATE ?n SET ?u WHERE entity_id = ?i";
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
            $sql_check = "SELECT COUNT(*) FROM ?n WHERE parent_entity_id = ?i";
            $count = SafeMySQL::gi()->getOne($sql_check, Constants::ENTITIES_TABLE, $entity_id);
            if ($count > 0) {
                return ['error' => 'Нельзя удалить сущность, так как она является родительской для других.'];
            }
            $sql_delete = "DELETE FROM ?n WHERE entity_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete, Constants::ENTITIES_TABLE, $entity_id);
            return $result ? [] : ['error' => 'Ошибка при выполнении запроса DELETE'];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

}
