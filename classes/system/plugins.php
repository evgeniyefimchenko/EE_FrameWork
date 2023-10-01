<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Системный класc для создания плагинов на страницах сайта
 * Все методы статические
 * @author Evgeniy Efimchenko efimchenko.ru  
 */
Class Plugins {

    function __construct() {
        throw new Exception('Static only.');
    }

    /**
     * Создаёт таблицу с возможностью пагинации, фильтрации и сортировки.
     * @param string $id_table Идентификатор таблицы.
     * @param array $data_table Данные для таблицы.
     * @param str $callback_function Функция AJAX обработки таблицы.
     * @param int $page Текущая страница пагинации.
     * @param int $current_rows_per_page Текущее количество записей на странице.
     * @param array $filters Установленные фильтры для таблицы.
     * @param array $selected_sorting Уже выбранная сортировка.
     * @param str $add_class Дополнительный класс таблицы.
     * @param int $max_buttons Максимальное количество кнопок на странице пагинации.
     * @return string HTML таблицы.
     */
    public static function ee_show_table($id_table = 'test_table', $data_table = [], $callback_function = '', $filters = [], $page = 1, $current_rows_per_page = 25, $selected_sorting = [], $add_class = 'table-striped', $max_buttons = 5) {
        if (!is_array($data_table)) {
            return '<div class="alert alert-danger text-center">Ошибка формата данных</div>';
        }
        if (!is_string($add_class))
            $add_class = 'table-striped';
        $html = '<div class="mb-3" data-tableID="' . $id_table . '" id="' . $id_table . '_content_tables">';
        $html .= '<input type="hidden" id="' . $id_table . '_callback_function" value="' . $callback_function . '">';
        $html .= self::generateFilterSection($id_table, $data_table, $filters);
        $html .= '<table id="' . $id_table . '" class="table ' . $add_class . '">';
        $html .= self::generateTableHeader($id_table, $data_table['columns'], $selected_sorting);
        $html .= self::generateTableBody($data_table);
        $html .= '</table>';  // закрыть таблицу
        $html .= self::generatePagination($id_table, $page, $data_table, $current_rows_per_page, $max_buttons);
        $html .= self::generateRowsPerPageSection($id_table, $data_table, $current_rows_per_page);
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $html .= '<script src="' . ENV_URL_SITE . '/classes/system/js/plugins/ee_show_table.js" type="text/javascript" /></script>';
        }
        return $html;
    }

    /**
     * Генерирует HTML раздел фильтрации для таблицы на основе предоставленных данных.
     * @param string $id_table   Идентификатор таблицы, для которой создаются фильтры.
     * @param array  $data_table Массив данных таблицы, содержит информацию о колонках, их заголовках и фильтруемости.
     * @param array  $filters    Массив фильтров, каждый элемент которого содержит информацию о типе фильтра,
     *                           его идентификаторе, значении и других атрибутах.
     * @return string Возвращает HTML-код раздела фильтрации.
     */
    private static function generateFilterSection($id_table, $data_table, $filters) {
        $html = '';
        // Раздел фильтрации
        if (!is_array($filters) || empty($filters)) {
            $filters = [];
            foreach ($data_table['columns'] as $column) {
                if ($column['filterable']) {
                    $filters[$column['field']] = [
                        'type' => 'text',
                        'id' => $id_table . "_filter_" . $column['field'],
                        'value' => '',
                        'label' => $column['title']  // Значение по умолчанию для label
                    ];
                }
            }
        }

        $filterableColumnsCount = 0;
        foreach ($data_table['columns'] as $column) {
            if (isset($column['filterable']) && $column['filterable'] === true) { // Подсчет колонок с 'filterable' => true
                $filterableColumnsCount++;
            }
        }
        $count_filters = count($filters);
        if ($count_filters != $filterableColumnsCount) {
            $count_filters = 0;
            $html .= '<div class="alert alert-danger text-center">Ошибка: количество переданных фильтров не совпадает с количеством колонок для фильтрации!</div>';
        }
        $html .= '<form class="mb-3 ' . (!$count_filters ? 'd-none' : '') . '" id="' . $id_table . '_filters">';
        $html .= '<input type="hidden" name="' . $id_table . '_table_name" value="' . $id_table . '">';
        // Добавим префикс при его отсутствии
        foreach ($filters as $key => $filter) {
            if (strpos($filter['id'], $id_table . '_filter_') === false) {
                $filters[$key]['id'] = $id_table . '_filter_' . $filters[$key]['id'];
            }
        }
        $html .= '<input type="hidden" name="' . $id_table . '_old_filters" value="' . htmlspecialchars(json_encode($filters), ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="row justify-content-center">';
        foreach ($filters as $key => $filter) {
            $html .= '<div class="col-6 col-sm-3 text-center">';
            $filterId = $filter['id'] ?? $id_table . '_filter_' . $key;
            $filterValue = $filter['value'] ?? "";
            $filterLabel = $filter['label'] ? $filter['label'] : 'Unknown';
            $html .= '<label for="' . $filterId . '">' . $filterLabel . '</label>';
            switch ($filter['type']) {
                case 'text':
                    $html .= '<input type="text" class="form-control mb-2" name="' . $filterId . '" id="' . $filterId . '" value="' . $filterValue . '">';
                    break;
                case 'checkbox':
                    foreach ($filter['options'] as $option) {
                        $checked = in_array($option['value'], (array) $filterValue) ? ' checked' : '';
                        $html .= '<div class="form-check mb-2">';
                        $html .= '<input class="form-check-input" type="checkbox" value="' . $option['value'] . '" name="' . $filterId . '_' . $option['id'] . '" id="' . $filterId . '_' . $option['id'] . '"' . $checked . '>';
                        $html .= '<label class="form-check-label" for="' . $option['id'] . '">' . $option['label'] . '</label>';
                        $html .= '</div>';
                    }
                    break;
                case 'select':
                    $multiple = $filter['multiple'] ?? false ? ' multiple' : '';
                    if (!$multiple && count($filterValue) > 1) {
                        $html .= '<div class="alert alert-danger text-center">Ошибка: количество значений в select больше одного при отсутствии multiple!</div>';
                        break;
                    }
                    $html .= '<select class="form-select mb-2" name="' . $filterId . '[]" id="' . $filterId . '"' . $multiple . '>';
                    foreach ($filter['options'] as $option) {
                        $selected = in_array($option['value'], (array) $filterValue) ? ' selected' : '';
                        $html .= '<option value="' . $option['value'] . '"' . $selected . '>' . $option['label'] . '</option>';
                    }
                    $html .= '</select>';
                    break;
                case 'date':
                    $html .= '<input type="date" class="form-control mb-2" name="' . $filterId . '" id="' . $filterId . '" value="' . $filterValue . '">';
                    break;
            }
            $html .= '</div>';
        }
        $html .= '</div><div class="w-100 text-center"><button type="submit" class="btn btn-primary" form="' . $id_table . '_filters">Применить фильтры</button>
					<button type="reset" id="' . $id_table . '_filters_reset" class="btn btn-secondary" form="' . $id_table . '_filters">Сбросить</button></div></form>';
        return $html;
    }

    /**
     * Генерирует HTML раздел заголовка таблицы на основе предоставленных данных.
     * @param string $id_table   Идентификатор таблицы, для которой создается заголовок.
     * @param array  $columns Массив о колонках и их заголовках.
     * @return string Возвращает HTML-код заголовка таблицы.
     */
    private static function generateTableHeader($id_table, $columns, $selected_sorting = []) {
        if (!is_array($selected_sorting))
            $selected_sorting = [];
        $html = '<thead>';
        if (!$columns) {
            $html .= '<th>';
            $html .= 'Нет данных';
            $html .= '</th>';
        }
        foreach ($columns as $column) {
            $sortedIndicator = '';
            $current_sorting = null;
            // Если для данного столбца установлена сортировка в $selected_sorting, используем ее
            if ($selected_sorting && isset($selected_sorting[$column['field']])) {
                $current_sorting = $selected_sorting[$column['field']];
            } elseif (!$selected_sorting && isset($column['sorted'])) {
                $current_sorting = $column['sorted'];
            }
            if ($current_sorting) {
                $current_sorting = $current_sorting == 'ASC' ? 'ASC' : 'DESC';
                $sortedIcon = $current_sorting == 'ASC' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';
                $sortedIndicator = '<a href="#" data-current-sort="' . $current_sorting . '" id="' . $id_table . '_column_' . $column['field'] . '_' . $current_sorting . '">' . $sortedIcon . '</a>';
            }
            $html .= '<th>';
            $html .= $column['title'] . $sortedIndicator;
            $html .= '</th>';
        }
        $html .= '</thead>';
        return $html;
    }

    /**
     * Генерирует HTML раздел тела таблицы на основе предоставленных данных.
     * @param array $data_table Массив данных таблицы, содержащий строки и информацию о колонках.
     * @return string Возвращает HTML-код тела таблицы.
     */
    private static function generateTableBody($data_table) {
        $html = '<tbody>';
        if ($data_table['total_rows'] == 0 || count($data_table['rows']) == 0) { // Если записей нет
            $html .= '<tr><td colspan="' . count($data_table['columns']) . '" class="text-center">Нет записей</td></tr>';
        } else {
            foreach ($data_table['rows'] as $row) {
                $html .= '<tr>';
                foreach ($data_table['columns'] as $column) {
                    $value = $row[$column['field']];
                    $html .= '<td>' . $value . '</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody>';
        return $html;
    }

    /**
     * Генерирует HTML раздел пагинации для таблицы.
     * @param string $id_table Идентификатор таблицы.
     * @param int $page Текущая страница.
     * @param array $data_table Массив данных таблицы.
     * @param int $current_rows_per_page Текущее количество строк на странице.
     * @param int $max_buttons Максимальное количество кнопок пагинации.
     * @return string Возвращает HTML-код пагинации.
     */
    private static function generatePagination($id_table, $page, $data_table, $current_rows_per_page, $max_buttons) {
        $html = '';
        $total_pages = ceil($data_table['total_rows'] / $current_rows_per_page);
        if ($total_pages > 1) {
            $max_buttons = min($max_buttons, $total_pages);
            $start_page = max(1, $page - floor($max_buttons / 2));
            $end_page = min($total_pages, $start_page + $max_buttons - 1);
            $html .= '<nav aria-label="Table pagination">';
            $html .= '<ul class="pagination">';
            // Кнопки для перехода на первую страницу и на предыдущую
            if ($page > 1) {
                $html .= '<li class="page-item"><a class="page-link" href="#" data-page="1" aria-label="First"><i class="fas fa-angle-double-left"></i></a></li>';
                $html .= '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page - 1) . '" aria-label="Previous"><i class="fas fa-angle-left"></i></a></li>';
            }
            // Кнопки для номеров страниц
            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = ($i == $page) ? ' active' : '';
                $html .= '<li class="page-item' . $active . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
            }
            // Кнопки для перехода на следующую страницу и последнюю
            if ($page < $total_pages) {
                $html .= '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page + 1) . '" aria-label="Next"><i class="fas fa-angle-right"></i></a></li>';
                $html .= '<li class="page-item"><a class="page-link" href="#" data-page="' . $total_pages . '" aria-label="Last"><i class="fas fa-angle-double-right"></i></a></li>';
            }
            $html .= '</ul>';
            $html .= '</nav>';
        }
        return $html;
    }

    /**
     * Генерирует HTML раздел для выбора количества строк на странице.
     * @param string $id_table Идентификатор таблицы.
     * @param array $data_table Массив данных таблицы.
     * @param int $current_rows_per_page Текущее количество строк на странице.
     * @return string Возвращает HTML-код раздела для выбора количества строк.
     */
    private static function generateRowsPerPageSection($id_table, $data_table, $current_rows_per_page) {
        // Возможные значения строк на странице
        $possible_rows = [10, 25, 50, 100];
        $count_row = is_array($data_table['rows']) ? count($data_table['rows']) : 0;
        $html = '<div class="rows-per-page-section">';
        $html .= '<label for="' . $id_table . '-rows-per-page">Количество строк:</label>';
        $html .= '<select id="' . $id_table . '-rows-per-page" class="form-select form-select-sm d-inline-block" style="width: auto; cursor: pointer;">';

        foreach ($possible_rows as $value) {
            $selected = ($value == $current_rows_per_page) ? ' selected="selected"' : '';
            $html .= '<option value="' . $value . '"' . $selected . '>' . $value . '</option>';
        }
        if (!$data_table['total_rows'])
            $data_table['total_rows'] = 0;
        $html .= '</select>';
        $html .= '<div class="pagination-info float-end">';
        $html .= 'Записей на странице: <span class="current-page-count">' . $count_row . '</span> из <span class="total-count">' . $data_table['total_rows'] . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Извлекает имя таблицы и соответствующие фильтры из предоставленных данных.
     * @param array $data Входные данные, из которых нужно извлечь имя таблицы и фильтры с чистыми ключами без префикса $table_name . "_filter_"
     * @return array Возвращает массив из двух элементов: имени таблицы и ассоциативного массива фильтров.
     * Если имя таблицы не найдено, возвращает массив из двух значений false.
     */
    public static function ee_show_table_extractFilters($data) {
        // Находим имя таблицы
        $table_key_suffix = "_table_name";
        $table_name = "";
        foreach ($data as $key => $value) {
            if (strpos($key, $table_key_suffix) !== false) {
                $table_name = $value;
                break;
            }
        }
        // Если имя таблицы не найдено, возвращаем пустой массив
        if (!$table_name) {
            return [false, false];
        }
        // Ищем все соответствующие фильтры для этой таблицы
        $filters = [];
        $filter_prefix = $table_name . "_filter_";
        foreach ($data as $key => $value) {
            if (strpos($key, $filter_prefix) === 0) {
                $filter_key = str_replace($filter_prefix, "", $key);
                $filters[$filter_key] = $value;
            }
        }
        return [$table_name, $filters];
    }

    /**
     * Собирает структуру фильтров на основе данных таблицы и извлеченных фильтров.
     * @param array $extractedFilters Ассоциативный массив извлеченных фильтров.
     * @param array $columns       	  Столбцы таблицы.
     * @param string $table_name      Название таблицы, используется для создания префикса ID фильтра.
     * @param array|null $old_filters Опциональный параметр. Старая структура фильтров.
     * @return array                  Возвращает структуру фильтров.
     */
    public static function ee_show_table_buildFilters($extractedFilters, $columns, $table_name, $old_filters = null) {
        $filters = [];
        // Если старые фильтры не предоставлены, создаем новую структуру фильтров везде type text
        if (!$old_filters) {
            foreach ($columns as $column) {
                if ($column['filterable']) {
                    $filters[$column['field']] = [
                        'type' => 'text',
                        'id' => $table_name . '_filter_' . $column['field'],
                        'value' => '',
                        'label' => $column['title']
                    ];
                    // Обновляем значение фильтра, если оно присутствует в извлеченных данных
                    if (isset($extractedFilters[$column['field']])) {
                        $filters[$column['field']]['value'] = $extractedFilters[$column['field']];
                    }
                }
            }
        } else {
            $prefixedExtractedFilters = [];
            foreach ($extractedFilters as $key => $value) {
                $prefixedKey = $table_name . '_filter_' . $key;
                $prefixedExtractedFilters[$prefixedKey] = $value; // Извлечённые фильтры с разбиением по ключам
            }
            // Если старые фильтры предоставлены, обновляем их значения на основе extractedFilters
            foreach ($old_filters as $key => $filter) {
                if ($filter['type'] == 'checkbox') {
                    $res_checkbox = [];
                    foreach ($prefixedExtractedFilters as $checkbox_key => $checkbox_value) {
                        if (stripos($checkbox_key, $filter['id']) !== false) {
                            $res_checkbox[] = $checkbox_value;
                        }
                    }
                    $old_filters[$key]['value'] = $res_checkbox;
                } else { // type select text or date
                    if (isset($prefixedExtractedFilters[$filter['id']])) {
                        $old_filters[$key]['value'] = $prefixedExtractedFilters[$filter['id']];
                    }
                }
            }
            $filters = $old_filters;
        }
        return $filters;
    }

    /**
     * Подготовит массив сортировки.
     * @param array $inputArray Входной массив с ключами, которые могут начинаться с "sort_".
     * @return array Преобразованный массив с ключами без префикса "sort_".
     */
    public static function ee_show_table_transformSortingKeys($inputArray = []) {
        $outputArrayWithDisabled = [];
        $outputArrayWithoutDisabled = [];
        foreach ($inputArray as $key => $value) {
            if (strpos($key, 'sort_') === 0) {  // проверяем, начинается ли ключ с "sort_"
                $newKey = str_replace('sort_', '', $key);  // убираем префикс "sort_"
                if (strpos($newKey, '_disabled') !== false) {
                    $newKey = str_replace('_disabled', '', $newKey);  // убираем префикс "_disabled"
                    $outputArrayWithDisabled[$newKey] = $value;
                } else {
                    $outputArrayWithoutDisabled[$newKey] = $value;
                }
            }
        }
        return [
            $outputArrayWithDisabled + $outputArrayWithoutDisabled,
            $outputArrayWithoutDisabled,
        ];
    }

    /**
     * Подготавливает и возвращает параметры для SQL-запроса на основе предоставленных LIMIT, Filter и sort
     * @param array $LIMIT Массив с данными для ограничения количества записей. 
     * Пример: ['page' => 1, 'rows_per_page' => 25]
     * @param array $Filter Массив фильтров для применения к запросу.
     * Пример: 
     * [
     *   'name' => ['type' => 'text', 'value' => 'Иван'],
     *   'user_role_text' => ['type' => 'select', 'value' => [1]],
     * ]
     * @param array $sort Массив для сортировки результатов запроса.
     * Пример: ['id' => 'ASC', 'name' => 'DESC']
     * @return array Возвращает массив с подготовленными параметрами для SQL-запроса:
     * - whereString: строка условий для WHERE.
     * - orderString: строка для сортировки.
     * - start: начальная позиция для LIMIT.
     * - limit: максимальное количество записей для LIMIT.
     */
    public static function ee_show_table_prepare_params($post_data, $columns) {
        list($table_name, $extract_filters) = Plugins::ee_show_table_extractFilters($post_data); // $extract_filters извлекаем без префиксов что бы подставлять в запрос к БД
        $old_filters = isset($post_data[$table_name . '_old_filters']) ? json_decode(html_entity_decode($post_data[$table_name . '_old_filters'], ENT_QUOTES, 'UTF-8'), true) : null;
        $filter = Plugins::ee_show_table_buildFilters($extract_filters, $columns, $table_name, $old_filters);        
        list($out_sort, $sort) = Plugins::ee_show_table_transformSortingKeys($post_data);
        $limit = ['page' => $post_data["page"], 'rows_per_page' => $post_data["rows_per_page"]];
        // Преобразование limit
        $page = $limit['page'] ?? 1;
        $rows_per_page = $limit['rows_per_page'] ?? 25;
        $start = ($page - 1) * $rows_per_page;
        $start = $start < 0 ? 0 : $start;
        // Преобразование filter
        $whereConditions = [];
        foreach ($filter as $key => $filterItem) {
            if ($filterItem['type'] === 'text' && !empty($filterItem['value']) && $filterItem['value']) { // Отсекаем пустые значения
                $whereConditions[] = "$key LIKE '%" . $filterItem['value'] . "%'";
            } elseif ($filterItem['type'] === 'select' && !empty($filterItem['value'])) {                
                if ($filterItem['value'][0]) {
                    if ($filterItem['multiple']) {
                        $whereConditions[] = $key . ' IN (' . implode(',', $filterItem['value']) . ')';
                    } else {
                       $whereConditions[] = $key . ' = ' . $filterItem['value'][0]; 
                    }
                }
            } elseif ($filterItem['type'] === 'date' && !empty($filterItem['value'])) {
                if (preg_match('/\d{2}:\d{2}(:\d{2})?/', $filterItem['value'])) {
                    // Содержит время
                    $whereConditions[] = "$key = '" . $filterItem['value'] . "'";
                } else {
                    // Не содержит время, используем условие для целых суток
                    $dateStart = $filterItem['value'] . " 00:00:00";
                    $dateEnd = $filterItem['value'] . " 23:59:59";
                    $whereConditions[] = "$key >= '" . $dateStart . "' AND $key <= '" . $dateEnd . "'";
                }
            }
        }
        $whereString = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : '';
        // Преобразование sort
        $orderString = '';
        foreach ($sort as $key => $direction) {
            $orderString .= "$key $direction, ";
        }
        $orderString = rtrim($orderString, ", ");
        return [
            [
                'where' => $whereString,
                'order' => $orderString,
                'start' => $start,
                'limit' => $rows_per_page
            ],
            $filter,
            $out_sort
        ];
    }

    /**
     * Пример структуры $data_table:
     * 
      $data_table = [
      'columns' => [
      [
      'field' => 'name',       // Имя поля в данных
      'title' => 'Имя',       // Заголовок столбца
      'sorted' => 'ASC',      // Направление сортировки (ASC, DESC или false, если сортировка не применена)
      'filterable' => true    // Возможность фильтрации по этому столбцу (true или false)
      ],
      [
      'field' => 'age',
      'title' => 'Возраст',
      'sorted' => true,
      'filterable' => true
      ],
      [
      'field' => 'address',
      'title' => 'Адрес',
      'sorted' => false,
      'filterable' => true
      ],
      [
      'field' => 'gender',
      'title' => 'Пол',
      'sorted' => true,
      'filterable' => true
      ],
      [
      'field' => 'registration_date',
      'title' => 'Дата регистрации',
      'sorted' => false,
      'filterable' => true
      ],
      // ... другие столбцы ...
      ],
      'rows' => [
      [
      'name' => 'Джон',
      'age' => 25,
      'address' => 'ул. Ленина, 5',
      'gender' => 'Мужской',
      'registration_date' => '2021-01-15'
      ],
      [
      'name' => 'Джейн',
      'age' => 30,
      'address' => 'пр. Мира, 15',
      'gender' => 'Женский',
      'registration_date' => '2020-10-07'
      ],
      [
      'name' => 'Иван',
      'age' => 35,
      'address' => 'ул. Комсомольская, 4',
      'gender' => 'Мужской',
      'registration_date' => '2019-02-14'
      ],
      [
      'name' => 'Мария',
      'age' => 28,
      'address' => 'ул. Ленина, 22',
      'gender' => 'Женский',
      'registration_date' => '2018-05-21'
      ],
      [
      'name' => 'Александр',
      'age' => 42,
      'address' => 'пр. Революции, 7',
      'gender' => 'Мужской',
      'registration_date' => '2016-12-12'
      ],
      [
      'name' => 'Анна',
      'age' => 23,
      'address' => 'ул. Московская, 19',
      'gender' => 'Женский',
      'registration_date' => '2020-01-15'
      ],
      [
      'name' => 'Дмитрий',
      'age' => 37,
      'address' => 'пр. Строителей, 8',
      'gender' => 'Мужской',
      'registration_date' => '2017-03-03'
      ],
      [
      'name' => 'Ольга',
      'age' => 31,
      'address' => 'ул. Зеленая, 33',
      'gender' => 'Женский',
      'registration_date' => '2018-08-10'
      ],
      [
      'name' => 'Сергей',
      'age' => 40,
      'address' => 'ул. Парковая, 5',
      'gender' => 'Мужской',
      'registration_date' => '2015-06-30'
      ],
      [
      'name' => 'Екатерина',
      'age' => 29,
      'address' => 'пр. Королева, 50',
      'gender' => 'Женский',
      'registration_date' => '2019-09-09'
      ],
      [
      'name' => 'Андрей',
      'age' => 33,
      'address' => 'ул. Приморская, 70',
      'gender' => 'Мужской',
      'registration_date' => '2020-04-20'
      ],
      [
      'name' => 'Татьяна',
      'age' => 26,
      'address' => 'пр. Ветеранов, 2',
      'gender' => 'Женский',
      'registration_date' => '2021-02-28'
      ],

      // ... другие строки ...
      ],
      'total_rows' => 1020  // Общее количество записей (используется для пагинации)
      ];
      $filters = [];
      $filters = [
      'column1' => [
      'type' => 'text', // тип фильтра: текстовое поле
      'id' => "name", // идентификатор фильтра должен совпадать с ['columns']['field']
      'value' => '', // значение по умолчанию
      'label' => 'ФИО' // метка или заголовок фильтра
      ],
      'column2' => [
      'type' => 'select', // тип фильтра: выпадающий список
      'id' => "age",
      'value' => ['option2', 'option1'],
      'label' => 'Возраст',
      'options' => [ // опции для выпадающего списка
      ['value' => 'option1', 'label' => '30+'],
      ['value' => 'option2', 'label' => '100-']
      ],
      'multiple' => true
      ],
      'column3' => [
      'type' => 'checkbox', // тип фильтра: флажок
      'id' => "address",
      'value' => ['option1', 'option2'],
      'label' => 'Адрес проживания',
      'options' => [ // опции для флажка
      ['value' => 'option1', 'label' => 'Москва', 'id' => 'option1_id'],
      ['value' => 'option3', 'label' => 'Не москва', 'id' => 'option3_id'],
      ['value' => 'option4', 'label' => 'Край света', 'id' => 'option4_id'],
      ['value' => 'option2', 'label' => 'Начало света', 'id' => 'option2_id']
      ]
      ],
      'columnDate' => [
      'type' => 'text',
      'id' => "gender",
      'value' => '',
      'label' => 'Пол'
      ],
      'columnDate1' => [
      'type' => 'date',
      'id' => "registration_date",
      'value' => '2023-08-10',  // Пример даты по умолчанию
      'label' => 'Дата регистрации'
      ],
      ];
     */
    ///////////////////////////////////////////////////////////////////////////////////////////////////////END Plugin TABLE

    /**
     * Генерирует вертикальное меню на основе предоставленных данных.
     *
     * @param array $data Данные меню со следующей структурой:
     *                   - 'menuItems' => массив с элементами структуры меню
     *                   - 'footerTitle' => Заголовок для нижнего раздела меню
     * @return string Сгенерированный HTML-код для вертикального меню
     */
    public static function generate_vertical_menu($data) {
        $menuItems = $data['menuItems'];
        $footerTitle = $data['footerTitle'];
        $html = '<div id="layoutSidenav_nav"><nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion"><div class="sb-sidenav-menu"><div class="nav">';
        foreach ($menuItems['headings'] as $heading => $items) {
            $html .= '<div class="sb-sidenav-menu-heading">' . $heading . '</div>';
            foreach ($items as $item) {
                if (isset($item['subItems'])) {
                    $html .= '<a class="nav-link collapsed" href="' . ($item['link'] ?: '#') . '" data-bs-toggle="collapse" data-bs-target="#collapse_' . $item['title'] . '" aria-expanded="false" aria-controls="collapse_' . $item['title'] . '">
                                <div class="sb-nav-link-icon"><i class="fas ' . $item['icon'] . '"></i></div>' . $item['title'] .
                            '<div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div></a>
                                <div class="collapse" id="collapse_' . $item['title'] . '" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion"><nav class="sb-sidenav-menu-nested nav">';
                    foreach ($item['subItems'] as $subItem) {
                        if ($subItem['link']) {
                            $html .= '<a class="nav-link" href="' . $subItem['link'] . '" data-parent-bs-target="#collapse_' . $item['title'] . '">
                                    <div class="sb-nav-link-icon"><i class="fas ' . $subItem['icon'] . '"></i></div>' . $subItem['title'] .
                                    '</a>';
                        } else {
                            $html .= '<span class="nav">' . $subItem['title'] . '</span>';
                        }
                    }
                    $html .= '</nav></div>';
                } else {
                    if ($item['link']) {
                        $html .= '<a class="nav-link" href="' . $item['link'] . '">
                                    <div class="sb-nav-link-icon"><i class="fas ' . $item['icon'] . '"></i></div>' . $item['title'] .
                                '</a>';
                    } else {
                        $html .= '<span class="nav">
                                    </div>' . $item['title'] .
                                '</span>';
                    }
                }
            }
        }
        $html .= '</div></div><div class="sb-sidenav-footer"><div class="nav"><div class="sb-sidenav-menu-heading">' . $footerTitle . '</div>';
        foreach ($menuItems['footer'] as $item) {
            if ($item['link']) {
                $html .= '<a class="nav-link" href="' . $item['link'] . '">' . $item['title'] . '</a>';
            } else {
                $html .= '<span class="nav">' . $item['title'] . '</span>';
            }
        }
        $html .= '</div></nav></div>';
        return $html;
    }

    /**
     * Генерирует верхний navbar на основе предоставленных данных.
     *
     * @param array $data Данные для верхнего navbar.
     * @return string Сгенерированный HTML-код для верхнего navbar.
     */
    public static function generate_topbar($data) {
        $html = '<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">';
        $html .= '<a class="navbar-brand ps-3" href="' . $data['brand']['url'] . '" target="_BLANK">' . $data['brand']['name'] . '</a>';
        $html .= '<button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars"></i></button>';
        $html .= '<form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0"><div class="input-group"><input class="form-control" type="text" placeholder="Поиск..." aria-label="Поиск..." aria-describedby="btnNavbarSearch" /><button class="btn btn-primary" id="btnNavbarSearch" type="button"><i class="fas fa-search"></i></button></div></form>';
        $html .= '<ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">';
        if (isset($data['notifications']) && !empty($data['notifications'])) {
            $notificationCount = count($data['notifications']);
            $html .= '<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" id="notificationsDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							<i class="fas fa-bell"></i>';
            $html .= '<span class="notification-badge">' . $notificationCount . '</span>';
            $html .= '</a><ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">';
            $count = 1;
            foreach ($data['notifications'] as $notification) {
                if ($count > 1)
                    $html .= '<li><hr class="dropdown-divider" /></li>';
                $html .= '<li><a style="padding-left: 5px;" class="dropdown-item" href="' . $notification['url'] . '"><i style="color: ' . $notification['color'] . '" class="fas ' . $notification['icon'] . '"></i>&nbsp;' . $notification['text'] . '</a></li>';
                if ($count > 10) {
                    $html .= '<li><a class="dropdown-item" href="/admin/messages">...</a></li>';
                    break;
                }
                $count++;
            }
            $html .= '</ul></li>';
        }
        // User Menu
        $html .= '<li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-user fa-fw"></i></a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">';
        foreach ($data['userMenu'] as $item) {
            if ($item === 'divider') {
                $html .= '<li><hr class="dropdown-divider" /></li>';
            } else {
                $html .= '<li><a class="dropdown-item" href="' . $item['link'] . '">' . $item['title'] . '</a></li>';
            }
        }
        $html .= '</ul></li></ul></nav>';
        return $html;
    }

}
