<?php

namespace classes\system;

/**
 * Системный класc для создания плагинов на страницах сайта
 * Все методы статические
 */
class Plugins {

    /**
     * Загрузка библиотеки SortableJS на страницу
     */
    private static $sortableJS = false;

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
        if (!is_string($add_class)) {
            $add_class = 'table-striped';
        }
        $html = '<div class="mb-3" data-tableID="' . $id_table . '" id="' . $id_table . '_content_tables">';
        $html .= '<input type="hidden" id="' . $id_table . '_callback_function" value="' . $callback_function . '">';
        $html .= self::generateFilterSection($id_table, $data_table, $filters);
        $html .= '<table id="' . $id_table . '" class="table ' . $add_class . '">';
        $html .= self::generateTableHeader($id_table, $data_table['columns'], $selected_sorting);
        $html .= self::generateTableBody($data_table, $id_table);
        $html .= '</table>';  // закрыть таблицу
        $html .= self::generatePagination($id_table, (int) $page, $data_table, (int) $current_rows_per_page, (int) $max_buttons);
        $html .= self::generateRowsPerPageSection($id_table, $data_table, $current_rows_per_page);
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
            $html .= '<script src="' . ENV_URL_SITE . '/classes/system/js/plugins/ee_show_table.js" type="text/javascript"></script>';
        }
        return $html . '</div>';
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
        global $globalLang;
        $html = '';
        $html .= '<button class="btn btn-primary mb-2" type="button" data-table="' . $id_table . '" data-bs-toggle="collapse" data-bs-target="#' . $id_table . '_filtersCollapse"'
                . 'aria-expanded="false" id="' . $id_table . '_button_filtersCollapse" aria-controls="' . $id_table . '_filtersCollapse"><i class="fa-solid fa-filter"'
                . 'data-bs-toggle="tooltip" data-bs-placement="top" id="' . $id_table . '_icon_filtersCollapse" title="' . $globalLang['sys.filtres'] . '"></i></button>';
        // Начало блока collapse
        $html .= '<div class="collapse" id="' . $id_table . '_filtersCollapse">';
        $html .= '<div class="card card-body">';
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
		<button type="reset" id="' . $id_table . '_filters_reset" class="btn btn-secondary" form="' . $id_table . '_filters">Сбросить</button></div>';
        // Закрываем форму и блок collapse
        $html .= '</form>';
        $html .= '</div></div>'; // Закрываем .card-body и .collapse
        return $html;
    }

    /**
     * Генерирует HTML раздел заголовка таблицы на основе предоставленных данных.
     * @param string $id_table   Идентификатор таблицы, для которой создается заголовок.
     * @param array $columns Массив колонок.
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
            // Проверяем, установлено ли значение ширины для этой колонки
            $widthStyle = isset($column['width']) ? ' style="width:' . (int) $column['width'] . '%;"' : ' style="width:auto;"';
            $add_class = isset($column['align']) ? 'text-' . $column['align'] : '';
            $html .= '<th' . $widthStyle . ' class="' . $add_class . '">';
            $html .= $column['title'] . $sortedIndicator;
            $html .= '</th>';
        }
        $html .= '</thead>';
        return $html;
    }

    /**
     * Генерирует HTML для тела таблицы, включая вложенные таблицы, если они предоставлены.
     *
     * @param array $data_table Ассоциативный массив, содержащий данные для таблицы. Массив должен иметь следующую структуру:
     *      [
     *          'total_rows' => (int) Общее количество строк в таблице,
     *          'columns' => (array) Массив ассоциативных массивов, каждый из которых представляет собой столбец в таблице. Каждый массив столбца должен иметь следующие ключи:
     *              'field' => (string) Ключ, используемый для поиска значения этого столбца в массиве строк,
     *              'align' => (string, optional) Выравнивание текста для этого столбца. Должно быть 'left', 'right' или 'center'.
     *              'width' => (int, optional) Ширина столбца в процентах. Если параметр не передан, используется значение 'auto'.
     *          'rows' => (array) Массив ассоциативных массивов, каждый из которых представляет собой строку в таблице. Каждый массив строк должен иметь ключи, соответствующие значениям 'field' массива столбцов.
     *              'nested_table' => (array, optional) Ассоциативный массив, представляющий вложенную таблицу. Этот массив должен иметь ту же структуру, что и массив $data_table.
     *      ]
     * @param int $id_table ID таблицы
     * @return string HTML для тела таблицы.
     */
    private static function generateTableBody($data_table, $id_table) {
        $html = '<tbody>';
        if ($data_table['total_rows'] == 0 || count($data_table['rows']) == 0) { // Если записей нет
            $html .= '<tr><td colspan="' . count($data_table['columns']) . '" class="text-center">Нет записей</td></tr>';
        } else {
            $count_row = 1;
            foreach ($data_table['rows'] as $row) {
                $html .= '<tr>';
                $firstColumn = true;  // Флаг для отслеживания первой колонки
                foreach ($data_table['columns'] as $column) {
                    $value = $row[$column['field']];
                    $textAlignStyle = isset($column['align']) ? ' style="text-align:' . $column['align'] . ';"' : '';
                    $html .= '<td' . $textAlignStyle . '>';
                    $html .= $value;
                    if ($firstColumn && isset($row['nested_table'])) {
                        $html .= '<div class="expand_nested_table" data-nested_table="#' . $id_table . '_nested_table_' . $count_row . '">' .
                                '<i class="fa fa-plus" style="right: 8px; position: relative;"></i></div>';  // Кнопка "плюс" в первой колонке
                    }
                    $html .= '</td>';
                    $firstColumn = false;  // Сброс флага после обработки первой колонки
                }
                $html .= '</tr>';
                if (isset($row['nested_table'])) {
                    $nested_table = $row['nested_table'];
                    $colspan = count($data_table['columns']);
                    $html .= '<tr class="tr_nested_table" id="' . $id_table . '_nested_table_' . $count_row . '"><td colspan="' . $colspan . '">';
                    $html .= '<table class="nested_table">';
                    // Заголовок вложенной таблицы
                    $html .= '<thead><tr>';
                    foreach ($nested_table['columns'] as $column) {
                        $widthStyle = isset($column['width']) ? ' style="width:' . $column['width'] . '%;"' : '';
                        $html .= '<th' . $widthStyle . '>' . $column['title'] . '</th>';
                    }
                    $html .= '</tr></thead>';
                    $html .= '<tbody>';
                    foreach ($nested_table['rows'] as $nested_row) {
                        $html .= '<tr>';
                        foreach ($nested_table['columns'] as $column) {
                            $value = $nested_row[$column['field']];
                            $textAlignStyle = isset($column['align']) ? ' style="text-align:' . $column['align'] . ';"' : '';
                            $html .= '<td' . $textAlignStyle . '>' . $value . '</td>';
                        }
                        $html .= '</tr>';
                    }
                    $html .= '</tbody>';
                    $html .= '</table>';
                    $html .= '</td></tr>';
                }
                $count_row++;
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
        $count_row = isset($data_table['rows']) && is_array($data_table['rows']) ? count($data_table['rows']) : 0;
        $html = '<div class="rows-per-page-section">';
        $html .= '<label for="' . $id_table . '-rows-per-page">Количество строк:&nbsp;</label>';
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
            if (strpos($key, 'sort_') === 0) {
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
     * @param array $LIMIT Массив с данными для ограничения количества записей
     * Пример: ['page' => 1, 'rows_per_page' => 25]
     * @param array $Filter Массив фильтров для применения к запросу
     * Пример: 
     * [
     *   'name' => ['type' => 'text', 'value' => 'Иван'],
     *   'user_role_text' => ['type' => 'select', 'value' => [1]],
     * ]
     * @param array $sort Массив для сортировки результатов запроса
     * Пример: ['id' => 'ASC', 'name' => 'DESC']
     * @return array Возвращает массив с подготовленными параметрами для SQL-запроса:
     * - whereString: строка условий для WHERE
     * - orderString: строка для сортировки
     * - start: начальная позиция для LIMIT
     * - limit: максимальное количество записей для LIMIT
     */
    public static function ee_showTablePrepareParams($post_data, $columns) {
        list($table_name, $extract_filters) = Plugins::ee_show_table_extractFilters($post_data); // $extract_filters извлекаем без префиксов что бы подставлять в запрос к БД
        $old_filters = isset($post_data[$table_name . '_old_filters']) ? json_decode(html_entity_decode($post_data[$table_name . '_old_filters'], ENT_QUOTES, 'UTF-8'), true) : null;
        $filter = Plugins::ee_show_table_buildFilters($extract_filters, $columns, $table_name, $old_filters);
        list($out_sort, $sort) = Plugins::ee_show_table_transformSortingKeys($post_data);
        $limit = ['page' => $post_data["page"], 'rows_per_page' => $post_data["rows_per_page"]];
        // Преобразование limit
        $page = $limit['page'] ?? 1;
        $rows_per_page = $limit['rows_per_page'] ?? 25;
        $start = ((int) $page - 1) * (int) $rows_per_page;
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
        $whereString = !empty($whereConditions) ? implode(" AND ", $whereConditions) : '';
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

////////END Plugin TABLE

    /**
     * Генерирует вертикальное меню на основе предоставленных данных
     * @param array $data Данные меню со следующей структурой:
     *                   - 'menuItems' => массив с элементами структуры меню
     *                   - 'footerTitle' => Заголовок для нижнего раздела меню
     * @return string Сгенерированный HTML-код для вертикального меню
     */
    public static function generateVerticalMenu($data) {
        $menuItems = $data['menuItems'];
        $footerTitle = $data['footerTitle'];
        $html = '<div id="layoutSidenav_nav"><nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion"><div class="sb-sidenav-menu"><div class="nav">';
        foreach ($menuItems['headings'] as $heading => $items) {
            $html .= '<div class="sb-sidenav-menu-heading">' . $heading . '</div>';
            foreach ($items as $item) {
                $attributes = isset($item['attributes']) ? ' ' . $item['attributes'] : '';
                if (isset($item['subItems'])) {
                    $html .= '<a class="nav-link collapsed" href="' . ($item['link'] ?: '#') . '" data-bs-toggle="collapse"' .
                            'data-bs-target="#collapse_' . $item['title'] . '" aria-expanded="false" aria-controls="collapse_' . $item['title'] . '"' . $attributes . '>' .
                            '<div class="sb-nav-link-icon"><i class="fas ' . $item['icon'] . '"></i></div>' . $item['title'] .
                            '<div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div></a>' .
                            '<div class="collapse" id="collapse_' . $item['title'] . '" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">' .
                            '<nav class="sb-sidenav-menu-nested nav">';
                    foreach ($item['subItems'] as $subItem) {
                        $subAttributes = isset($subItem['attributes']) ? ' ' . $subItem['attributes'] : '';
                        if ($subItem['link']) {
                            $html .= '<a class="nav-link" href="' . $subItem['link'] . '" data-parent-bs-target="#collapse_' . $item['title'] . '"' . $subAttributes . '>
                                    <div class="sb-nav-link-icon"><i class="fas ' . $subItem['icon'] . '"></i></div>' . $subItem['title'] .
                                    '</a>';
                        } else {
                            $html .= '<span class="nav">' . $subItem['title'] . '</span>';
                        }
                    }
                    $html .= '</nav></div>';
                } else {
                    if ($item['link']) {
                        $html .= '<a class="nav-link" href="' . $item['link'] . '"' . $attributes . '>
                                <div class="sb-nav-link-icon"><i class="fas ' . $item['icon'] . '"></i></div>' . $item['title'] .
                                '</a>';
                    } else {
                        $html .= '<span class="nav">
                                <div class="sb-nav-link-icon"><i class="fas ' . $item['icon'] . '"></i></div>' . $item['title'] .
                                '</span>';
                    }
                }
            }
        }
        $html .= '</div></div><div class="sb-sidenav-footer" style="height: 50px; cursor: cell;"><div class="nav">' .
                '<div class="sb-sidenav-menu-heading">' . $footerTitle . '</div>';
        foreach ($menuItems['footer'] as $item) {
            $footerAttributes = isset($item['attributes']) ? ' ' . $item['attributes'] : '';
            if ($item['link']) {
                $html .= '<a class="nav-link" href="' . $item['link'] . '"' . $footerAttributes . '>' . $item['title'] . '</a>';
            } else {
                $html .= '<span class="nav">' . $item['title'] . '</span>';
            }
        }
        $html .= '</div></nav></div>';
        $script = '<script>$(document).ready(function() {
        var isOpen = false;
        $(\'.sb-sidenav-footer\').click(function() {
          var newHeight = isOpen ? 50 : 140;
          $(this).animate({ height: newHeight }, 500);
          isOpen = !isOpen;
        });
        });</script>';
        return $html . $script;
    }

    /**
     * Генерирует верхний navbar
     * @param array $data Данные для верхнего navbar
     * @return string Сгенерированный HTML-код для верхнего navbar
     */
    public static function generate_topbar($data) {
        $html = '<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark" id="general_info">';
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
                $meta = isset($item['meta']) ? $item['meta'] : '';
                $html .= '<li><a class="dropdown-item" ' . $meta . ' href="' . $item['link'] . '">' . $item['title'] . '</a></li>';
            }
        }
        $html .= '</ul></li></ul></nav>';
        return $html;
    }

    /**
     * Рендерит HTML-теги на основе переданного массива полей
     * Для заполнения дефолтных параметров
     * Функция принимает массив, значения типы полей (например, "text", "select" и т.д.)
     * На основе этого массива функция генерирует и выводит HTML-теги в контейнерах <div class="col-4 col-sm-4">
     * @param array $fields
     * @return void Функция ничего не возвращает, выводит HTML напрямую
     */
    public static function renderPropertyHtmlFields(mixed $fields, mixed $default = []) {
        global $globalLang;
        $count = 0;
        $result = '';
        if (!is_array($fields) && is_string($fields)) {
            $fields = json_decode($fields, true);
        }
        if (!is_array($default) && is_string($default)) {
            $default = json_decode($default, true);
        }
        $not_default = false;
        if (!count($default)) {
            $not_default = true;
        }
        foreach ($fields as $type) {
            if ($not_default || empty($default[$count])) {
                $default[$count] = ['title' => '', 'label' => '', 'default' => '', 'required' => 0, 'multiple' => 0, 'title' => ''];
            }
            $result .= $globalLang['sys.type'] . ' ' . ucfirst($type) . ': ';
            $result .= '<div class="row rounded-2 p-2 col-12 col-sm-12 mt-2 align-items-center property_content border">';
            if ($type !== 'checkbox' && $type !== 'radio') {
                $result .= '<div class="col">';
                $result .= '<label class="form-label">' . $globalLang['sys.title'] . '</label><input type="text" required name="property_data[' . $type . '_' . $count . '_label]"'
                        . 'class="form-control mb-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $default[$count]['label'] . '"'
                        . 'value="' . $default[$count]['label'] . '" />';
                $result .= '</div>';
            }
            $default[$count]['default'] = isset($default[$count]['default']) ? $default[$count]['default'] : '';
            $multiple_choice = true;
            $multiple = isset($default[$count]['multiple']) && $default[$count]['multiple'] == 1 ? true : false;
            $nameSufix = $multiple ? '[]' : '';
            $result .= '<div class="col pt-1">';
            $result .= '<label class="form-label" ' . (in_array($type, ["file", "image"]) ? " style=\"visibility: hidden;\"" : "") . '>' . $globalLang['sys.value'] . '</label>';
            $result .= var_export($default[$count]['default'], true); // TODO test
            switch ($type) {
                case 'text':
                case 'number':
                case 'date':
                case 'time':
                case 'datetime-local':
                case 'email':
                case 'phone':
                case 'password':
                case 'hidden':                
                    $itype = $type == 'phone' ? 'tel' : $type;
                    $itype = $type == 'hidden' ? 'text' : $type;
                    if ($multiple && is_array($default[$count]['default'])) {
                        foreach ($default[$count]['default'] as $defaultItem) {
                            $result .= '<div class="field_container d-flex align-items-center">';
                            $result .= '<input type="' . $itype . '" class="form-control"'
                                    . 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . $globalLang['sys.default'] . '" '
                                    . 'name="property_data[' . $type . '_' . $count . '_default]' . $nameSufix . '" value="' . htmlentities($defaultItem) . '" />';
                            $result .= '</div>';
                        }
                    } else {
                        $result .= '<input type="' . $itype . '"  class="form-control"'
                                . 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . $globalLang['sys.default'] . '" '
                                . 'name="property_data[' . $type . '_' . $count . '_default]' . $nameSufix . '" value="' . htmlentities($default[$count]['default']) . '" />';
                    }
                    break;
                case 'select':
                    $options = '';
                    if ($multiple && is_array($default[$count]['default'])) {
                        $countItemSelect = 0;
                        foreach ($default[$count]['default'] as $defaultItem) {
                            if (is_array($arr_opt = explode('{|}', html_entity_decode($defaultItem)))) {
                                $options = '';
                                foreach ($arr_opt as $item) {
                                    if ($item) {
                                        $arr_item = explode('=', $item);
                                        $options .= '<option value="' . $arr_item[0] . '">' . $arr_item[1] . '</option>';
                                    }
                                }
                            }
                            $result .= '<div class="field_container d-flex align-items-center">';
                            $result .= '<input type="hidden" name="property_data[' . $type . '_' . $count . '_default]' . $nameSufix . '" '
                                    . 'id="' . $type . '_' . $count . '_' . $countItemSelect . '_default"'
                                    . 'value="' . htmlentities($defaultItem) . '"/>'
                                    . '<div class="input-group">'
                                    . '<select class="form-select" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $globalLang['sys.default'] . '"'
                                    . 'id="' . $type . '_' . $count . '_' . $countItemSelect . '">' . $options . '</select>'
                                    . '<span data-select-id="' . $type . '_' . $count . '_' . $countItemSelect . '" id="' . $type . '_' . $count
                                    . '_default_add_select_values_' . $countItemSelect . '" role="button"'
                                    . 'class="input-group-text btn-primary openModal" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $globalLang['sys.separate_window'] . '">'
                                    . '<i class="fas fa-tree"></i></span>'
                                    . '</div>';
                            $result .= '</div>';
                            $countItemSelect++;
                        }
                    } else {
                        if ($default[$count]['default'] && is_array($arr_opt = explode('{|}', html_entity_decode($default[$count]['default'])))) {
                            foreach ($arr_opt as $item) {
                                if ($item) {
                                    $arr_item = explode('=', $item);
                                    $options .= '<option value="' . $arr_item[0] . '">' . $arr_item[1] . '</option>';
                                }
                            }
                        }
                        $result .= '<input type="hidden" name="property_data[' . $type . '_' . $count . '_default]' . $nameSufix . '" id="' . $type . '_' . $count . '_0_default"'
                                . 'value="' . $default[$count]['default'] . '"/>'
                                . '<div class="input-group">'
                                . '<select class="form-select" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $globalLang['sys.default'] . '"'
                                . 'id="' . $type . '_' . $count . '_0">' . $options . '</select>'
                                . '<span data-select-id="' . $type . '_' . $count . '_0" id="' . $type . '_' . $count . '_default_add_select_values_0" role="button"'
                                . 'class="input-group-text btn-primary openModal" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $globalLang['sys.separate_window'] . '">'
                                . '<i class="fas fa-tree"></i></span>'
                                . '</div>';
                    }
                    break;
                case 'textarea':
                    if ($multiple && is_array($default[$count]['default'])) {
                        foreach ($default[$count]['default'] as $defaultItem) {
                            $result .= '<div class="field_container d-flex align-items-center">';
                            $result .= '<textarea class="form-control" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $globalLang['sys.default'] . '"'
                                    . 'name="property_data[' . $type . '_' . $count . '_default]' . $nameSufix . '">' . $defaultItem . '</textarea>';
                            $result .= '</div>';
                        }
                    } else {
                        $result .= '<textarea class="form-control" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $globalLang['sys.default'] . '"'
                                . 'name="property_data[' . $type . '_' . $count . '_default]' . $nameSufix . '">' . $default[$count]['default'] . '</textarea>';
                    }
                    break;
                case 'file':
                case 'image':
                    if ($type == 'image') {
                        $allowed_extensions = 'jpeg, jpg, png, gif, bmp, tiff, webp, ico';
                    } else {
                        $allowed_extensions = 'zip, rar, 7z, gzip, bz2, pdf, doc, xlsx, odt, ods, odp, rtf, epub, woff, woff2, ttf, otf, sqlite, csv, txt, xml, json';
                    }
                    $preloaded_files = [];
                    if (isset($default[$count]['default']) && !empty($default[$count]['default'])) {
                        $preloaded_files = explode(',', $default[$count]['default']);
                    }                    
                    $result .= self::ee_uploader([
                                'name' => 'property_data[' . $type . '_' . $count . '_default]',
                                'allowed_extensions' => $allowed_extensions,
                                'multiple' => $default[$count]['multiple'],
                                'required' => $default[$count]['required'],
                                'preloaded_files' => $preloaded_files
                    ]);                   
                    break;
                case 'checkbox':
                    $count_items = $value_count = 0;
                    if (isset($default[$count]['count']) && $default[$count]['count']) {
                        $count_items = $default[$count]['count'];
                    }
                    $add_html = '';
                    $first_element = ['name' => '', 'checked' => NULL];
                    if ($count_items) {
                        if (!empty($default[$count]['default'])) {
                            $default_arr = is_array($default[$count]['default']) ? array_flip($default[$count]['default']) : array_flip(explode(',', $default[$count]['default']));
                        } else {
                            $default_arr = [];
                        }
                        foreach ($default[$count]['label'] as $k => $name) {
                            if (!$value_count) {
                                $first_element = ['name' => $name, 'checked' => in_array(0, $default_arr) ? 1 : NULL];
                                $value_count++;
                                continue;
                            }
                            $add_html .= '<div class="checkbox_container d-flex align-items-center">'
                                    . '<span>' . $globalLang['sys.name'] . '</span>&nbsp;'
                                    . '<input type="text" required value="' . $name . '" name="property_data[' . $type . '_' . $count . '_label][]"'
                                    . 'class="form-control mb-2 w-25" data-bs-toggle="tooltip" data-bs-placement="top" />&nbsp'
                                    . '<span>' . $globalLang['sys.value'] . '</span>&nbsp<div class="form-check float-start">'
                                    . '<input type="checkbox" ' . (isset($default_arr[$k]) ? 'checked ' : '') . ' class="form-check-input" '
                                    . 'data-bs-toggle="tooltip" data-bs-placement="top" value="' . $value_count . '"'
                                    . 'title="' . $globalLang['sys.default'] . '" name="property_data[' . $type . '_' . $count . '_default][]"></div>'
                                    . '<button type="button" class="btn btn-primary" data-general-name="' . $type . '_' . $count . '"'
                                    . 'id="' . $value_count . '_' . $type . '_' . $count . '_default_add_checkbox_values"><i class="fa fa-minus"></i></button></div>';
                            $value_count++;
                        }
                    }
                    $result .= '<div class="parent_checkbox_container d-flex align-items-center row">'
                            . '<input type="hidden" id="' . $type . '_' . $count . '_count" name="property_data[' . $type . '_' . $count . '_count]" value="' . $count_items . '">'
                            . '<div class="input-group mb-3 w-75"><span class="input-group-text me-0">' . $globalLang['sys.heading'] . '</span>'
                            . '<input type="text" required class="form-control" name="property_data[' . $type . '_' . $count . '_title]" value="' . $default[$count]['title'] . '"/></div>'
                            . '<div class="checkbox_container d-flex align-items-center">'
                            . '<span>' . $globalLang['sys.name'] . '</span>&nbsp;'
                            . '<input type="text" required value="' . $first_element['name'] . '" name="property_data[' . $type . '_' . $count . '_label][]"'
                            . 'class="form-control mb-2 w-25" data-bs-toggle="tooltip" data-bs-placement="top" >&nbsp'
                            . '<span>' . $globalLang['sys.value'] . '</span>&nbsp<div class="form-check float-start">'
                            . '<input type="checkbox" ' . (isset($first_element['checked']) ? 'checked ' : '') . 'value="0" class="form-check-input" data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'title="' . $globalLang['sys.default'] . '" name="property_data[' . $type . '_' . $count . '_default][]"></div>'
                            . '<button type="button" class="btn btn-primary" data-general-name="' . $type . '_' . $count . '"'
                            . 'id="' . $value_count . '_' . $type . '_' . $count . '_default_add_checkbox_values"><i class="fa fa-plus"></i></button>'
                            . '</div>' . $add_html . '</div>';
                    $multiple_choice = false;
                    break;
                case 'radio':
                    $count_items = 0;
                    if (isset($default[$count]['count']) && $default[$count]['count']) {
                        $count_items = $default[$count]['count'];
                    }
                    $add_html = '';
                    $first_element = ['name' => '', 'checked' => NULL];
                    if ($count_items) {
                        $default_arr = is_array($default[$count]['default']) ? array_flip($default[$count]['default']) : array_flip(explode(',', $default[$count]['default']));
                        $value_count = 0;
                        foreach ($default[$count]['label'] as $k => $name) {
                            if (!$value_count) {
                                $first_element = ['name' => $name, 'checked' => in_array(0, $default_arr) ? 1 : NULL];
                                $value_count++;
                                continue;
                            }
                            $add_html .= '<div class="radio_container d-flex align-items-center">'
                                    . '<span>' . $globalLang['sys.name'] . '</span>&nbsp;'
                                    . '<input type="text" required value="' . $name . '" name="property_data[' . $type . '_' . $count . '_label][]"'
                                    . 'class="form-control mb-2 w-25" data-bs-toggle="tooltip" data-bs-placement="top" >&nbsp'
                                    . '<span>' . $globalLang['sys.value'] . '</span>&nbsp<div class="form-check"><input type="radio"'
                                    . ' ' . (isset($default_arr[$k]) ? 'checked ' : '') . 'class="form-check-input" data-bs-toggle="tooltip"'
                                    . 'data-bs-placement="top" title="' . $globalLang['sys.default'] . '" value="' . $value_count . '" '
                                    . 'name="property_data[' . $type . '_' . $count . '_default][]"></div>'
                                    . '<button type="button" class="btn btn-primary" data-general-name="' . $type . '_' . $count . '"'
                                    . 'id="' . $value_count . '_' . $type . '_' . $count . '_default_add_checkbox_values"><i class="fa fa-minus"></i></button></div>';
                            $value_count++;
                        }
                        $multiple_choice = false;
                    }
                    $result .= '<div class="parent_radio_container d-flex align-items-center row">'
                            . '<input type="hidden" id="' . $type . '_' . $count . '_count" name="property_data[' . $type . '_' . $count . '_count]" value="' . $count_items . '">'
                            . '<div class="input-group mb-3 w-75"><span class="input-group-text me-0">' . $globalLang['sys.heading'] . '</span>'
                            . '<input type="text" required class="form-control" name="property_data[' . $type . '_' . $count . '_title]" value="' . $default[$count]['title'] . '"/></div>'
                            . '<div class="radio_container d-flex align-items-center">'
                            . '<span>' . $globalLang['sys.name'] . '</span>&nbsp;'
                            . '<input type="text" required value="' . $first_element['name'] . '" name="property_data[' . $type . '_' . $count . '_label][]"'
                            . 'class="form-control mb-2 w-25" data-bs-toggle="tooltip" data-bs-placement="top" >&nbsp'
                            . '<span>' . $globalLang['sys.value'] . '</span>&nbsp<div class="form-check"><input type="radio" class="form-check-input" data-bs-toggle="tooltip"'
                            . 'data-bs-placement="top" title="' . $globalLang['sys.default'] . '" ' . (isset($first_element['checked']) ? 'checked ' : '') . 'value="0"'
                            . ' name="property_data[' . $type . '_' . $count . '_default][]"></div>'
                            . '<button type="button" class="btn btn-primary" data-general-name="' . $type . '_' . $count . '"'
                            . 'id="' . $value_count . '_' . $type . '_' . $count . '_default_add_radio_values"><i class="fa fa-plus"></i></button>'
                            . '</div>' . $add_html . '</div>';
                    $multiple_choice = false;
                    break;
                default:
                    $result .= '<span class="text-danger">Unsupported field type: ' . $type . '</span>';
            }
            $result .= '</div>';
            $result .= '<div class="col pt-3">';
            $result .= '<label>' . $globalLang['sys.required'] . '</label><input type="checkbox"'
                    . 'class="" name="property_data[' . $type . '_' . $count . '_required]"'
                    . ($default[$count]['required'] ? 'checked ' : '') . '/>';
            $result .= '</div>';
            if ($multiple_choice) {
                $result .= '<div class="col multicheck pt-3">';
                $result .= '<label class="form-label">' . $globalLang['sys.multiple_choice'] . '</label><input type="checkbox"'
                        . 'class="" name="property_data[' . $type . '_' . $count . '_multiple]"'
                        . ($default[$count]['multiple'] ? 'checked ' : '') . '/>';
                $result .= '</div>';
            }
            $result .= '</div>';
            $count++;
        }
        return $result;
    }

    /**
     * Функция для генерации HTML-кода аккордеона для наборов категорий и их свойств
     * @param array $categoriesTypeSetsData Данные о наборах категорий и их свойствах
     * @param array $categoryId ID категории или страницы
     * @param string $typeEntity Тип сущности
     * @return string HTML-код аккордеона
     */
    public static function renderCategorySetsAccordion($categoriesTypeSetsData, $categoryId, $typeEntity = 'category') {
        global $globalLang;
        $html = '';
        foreach ($categoriesTypeSetsData as $cat_name => $cats_set) {
            foreach ($cats_set as $propertySet) {
                $propertySet_id = hash('crc32', $propertySet['set_id'] . $propertySet['name'] . $propertySet['created_at']);
                $html .= '<div class="accordion my-3" id="accordion-' . $propertySet_id . '">';
                $html .= '<div class="card">';
                $html .= '<div class="card-header" id="heading-' . $propertySet_id . '">';
                $html .= '<h2 class="mb-0">';
                $html .= '<span class="h5">' . $globalLang['sys.set'] . ':</span> ';
                $html .= '<button class="btn btn-link" type="button" '
                        . 'data-bs-toggle="collapse" data-bs-target="#collapse-' . $propertySet_id . '" aria-expanded="true" '
                        . 'aria-controls="collapse-' . $propertySet_id . '">';
                $html .= $propertySet['name'] . '<input type="hidden" name="set_id" value="' . $propertySet['set_id'] . '" />';
                $html .= '</button>';
                $html .= '</h2>';
                $html .= '</div>'; // card-header
                $html .= '<div id="collapse-' . $propertySet_id . '" class="collapse" aria-labelledby="heading-' . $propertySet_id . '" '
                        . 'data-bs-parent="#accordion-' . $propertySet['set_id'] . '">';
                $html .= '<div class="card-body">';
                $html .= '<h5>' . $globalLang['sys.description'] . '</h5>' . '<p>' . ($propertySet['description'] ?
                        $propertySet['description'] : '---') . '</p>';
                $html .= '<h6>' . $globalLang['sys.properties'] . '</h6>';
                if (!count($propertySet['properties'])) {
                    $html .= '---';
                }
                foreach ($propertySet['properties'] as $property) {
                    $property_id = hash('crc32', $propertySet['set_id'] . $property['p_id'] . $property['property_values']['value_id']);
                    $html .= '<div class="accordion my-3" id="accordion-' . $property_id . '">';
                    $html .= '<div class="card">';
                    $html .= '<div class="card-header" id="heading-' . $property_id . '">';
                    $html .= '<h2 class="mb-0">';
                    $html .= $property['sort'] . ' ' . '<button class="btn btn-link" type="button" data-bs-toggle="collapse" '
                            . 'data-bs-target="#collapse-' . $property_id . '"'
                            . ' aria-expanded="true" aria-controls="collapse-' . $property_id . '">';
                    $html .= $property['name'] . '<br/>';
                    $html .= '</button></h2></div>'; // card-header
                    $html .= '<div id="collapse-' . $property_id . '" class="collapse" aria-labelledby="heading-'
                            . $property_id . '" data-bs-parent="#accordion-' . $property_id . '">';
                    $html .= '<div class="card-body">';
                    /* Убрал для облегчения понимания структуры
                      $html .= '<div><label>Is Multiple:</label>';
                      $html .= '<input class="ms-1" type="checkbox" disabled ';
                      $html .= ($property['is_multiple'] == 1 ? 'checked' : '') . '>';
                      $html .= '&nbsp;&nbsp;<label>Is Required:</label>';
                      $html .= '<input class="ms-1" type="checkbox" disabled ';
                      $html .= ($property['is_required'] == 1 ? 'checked' : '') . '></div>';
                     */
                    $html .= self::renderPropertyHtmlFieldsByAdmin($property['property_values'], $categoryId, $typeEntity);
                    $html .= '</div>'; // Закрытие .card-body
                    $html .= '</div>'; // Закрытие #collapse-[id]
                    $html .= '</div>'; // Закрытие .card
                    $html .= '</div>'; // Закрытие .accordion для свойств
                }
                $html .= '</div>'; // Закрытие .card-body для набора свойств
                $html .= '</div>'; // Закрытие #collapse-[id]
                $html .= '</div>'; // Закрытие .card для набора свойств
                $html .= '</div>'; // Закрытие .accordion для набора свойств
            }
        }
        return $html;
    }

    /**
     * Вывод свойств у страниц и категорий для сохранения значений свойств
     * @param array $values
     * @param int $entityId ID сущности
     * @param string $entity_type Тип сущности
     */
    public static function renderPropertyHtmlFieldsByAdmin(array $values, int $entityId, string $entity_type): string {
        global $globalLang;
        $result = '';
        $value_id = $values['value_id'];
        $property_id = $values['property_id'];
        foreach ($values['property_values'] as $key => $value) {
            if (!isset($value['required'])) {
                $value['required'] = 0;
            }
            if (!isset($value['multiple'])) {
                $value['multiple'] = 0;
            }
            if (!isset($value['value'])) {
                $value['value'] = '';
            }
            $result .= '<div class="rounded-2 col-10 col-sm-12 mt-2 d-flex">';
            $multiple_choice = true;
            $valueName = $value_id . '_' . $key . '_' . $value['type'] . '_' . $entityId . '_' . $entity_type . '_' . $property_id . '_' . $values['set_id'];
            $result .= '<input type="hidden" name="property_data_changed" value="0" />';
            $result .= '<input type="hidden" name="property_data[' . $valueName . '_type]" value="' . $value['type'] . '"/>';
            if ($value['type'] !== 'checkbox' && $value['type'] !== 'radio') {
                $result .= count($value) == 1 ? '' : '<input type="text" required value="' . $value['label'] . '" name="property_data[' . $valueName . '_label]"'
                        . 'class="form-control mb-2 w-25 h-100 fw-bold" data-bs-toggle="tooltip" data-bs-placement="top" />';
            }
            switch ($value['type']) {
                case 'text':
                    $result .= '<input type="text" class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'name="property_data[' . $valueName . '_value]" value="' . $value['value'] . '" />';
                    break;
                case 'number':
                    $result .= '<input type="number" class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'name="property_data[' . $valueName . '_value]" value="' . $value['value'] . '" />';
                    break;
                case 'date':
                    $result .= '<input type="date" class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'name="property_data[' . $valueName . '_value]" value="' . $value['value'] . '" />';
                    break;
                case 'time':
                    $result .= '<input type="time" class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $globalLang['sys.default'] . '" '
                            . 'name="property_data[' . $valueName . '_value]" value="' . $value['value'] . '" />';
                    break;
                case 'datetime-local':
                    $result .= '<input type="datetime-local" class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'name="property_data[' . $valueName . '_value]" value="' . $value['value'] . '" />';
                    break;
                case 'hidden':
                    $result .= '<input type="text" class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'name="property_data[' . $valueName . '_value]" value="' . $value['value'] . '" />';
                    break;
                case 'password':
                    $result .= '<input type="password" class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'name="property_data[' . $valueName . '_value]" value="' . $value['value'] . '" />';
                    break;
                case 'file':
                    $result .= '<div style="display: none;"><input type="file" class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'title="' . $globalLang['sys.default'] . '" name="property_data[' . $valueName . '_value]">'
                            . '<span>' . $globalLang['sys.multiple_choice'] . '</span><div class="form-check">'
                            . '<input class="form-check-input" type="checkbox" disabled '
                            . ($value['multiple'] ? 'checked ' : '') . '/></div></div>';
                    $multiple_choice = false;
                    break;
                case 'email':
                    $result .= '<input type="email" class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'title="' . $globalLang['sys.email'] . '" name="property_data[' . $valueName . '_value]" value="' . $value['value'] . '" />';
                    break;
                case 'phone':
                    $result .= '<input type="tel" class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'name="property_data[' . $valueName . '_value]" value="' . $value['value'] . '" />';
                    break;
                case 'select':
                    $options = '';
                    if ($value['value'] && is_array($arr_opt = explode('&', html_entity_decode($value['value'])))) {
                        foreach ($arr_opt as $item) {
                            if ($item) {
                                $arr_item = explode('=', $item);
                                $options .= '<option value="' . $arr_item[0] . '">' . $arr_item[1] . '</option>';
                            }
                        }
                    }
                    $result .= '<input type="hidden" name="property_data[' . $valueName . '_value]" id="' . $valueName . '_value"'
                            . 'value="' . $value['value'] . '"/>'
                            . '<select class="form-select w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top"'
                            . 'id="' . $valueName . '">' . $options . '</select>'
                            . '<span data-select-id="' . $valueName . '" id="' . $valueName . '_default_add_select_values" role="button"'
                            . 'class="input-group-text btn-primary openModal mx-1" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $globalLang['sys.separate_window'] . '">'
                            . '<i class="fas fa-tree"></i></span>';
                    $multiple_choice = false;
                    break;
                case 'textarea':
                    $result .= '<textarea class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top"'
                            . 'name="property_data[' . $valueName . '_value]"></textarea>';
                    break;
                case 'image':
                    $result .= '<input type="file" accept="image/*" class="form-control w-50 h-100" data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'name="property_data[' . $valueName . '_value]">'
                            . '<span>' . $globalLang['sys.multiple_choice'] . '</span><div class="form-check">'
                            . '<input class="form-check-input" type="checkbox" '
                            . ($value['multiple'] ? 'checked ' : '') . '/></div>';
                    $multiple_choice = false;
                    break;
                case 'checkbox':
                    $count_items = 0;
                    if (isset($value['count']) && $value['count']) {
                        $count_items = $value['count'];
                    } else {
                        $count_items = count($value['label']);
                    }
                    $add_html = '';
                    $first_element = ['name' => '', 'checked' => NULL];
                    if ($count_items) {
                        $checked_arr = array_flip(explode(',', $value['value']));
                        $value_count = 0;
                        foreach ($value['label'] as $k => $name) {
                            if (!$value_count) {
                                $first_element = ['name' => $name, 'checked' => in_array('0', $checked_arr) ? 1 : NULL];
                                $value_count++;
                                continue;
                            }
                            $add_html .= '<div class="checkbox_container d-flex align-items-center">'
                                    . '<span>' . $globalLang['sys.title'] . '</span>&nbsp;'
                                    . '<input type="text" required value="' . $name . '" name="property_data[' . $valueName . '_label][]"'
                                    . 'class="form-control mb-2 w-25" data-bs-toggle="tooltip" data-bs-placement="top" />&nbsp'
                                    . '<span>' . $globalLang['sys.value'] . '</span>&nbsp<div class="form-check float-start">'
                                    . '<input type="checkbox" ' . (isset($checked_arr[$k]) ? 'checked ' : '') . 'class="form-check-input" '
                                    . 'data-bs-toggle="tooltip" data-bs-placement="top" value="' . $value_count . '"'
                                    . 'title="' . $globalLang['sys.default'] . '" name="property_data[' . $valueName . '_value][]"></div>'
                                    . '<button type="button" class="btn btn-primary" data-general-name="' . $valueName . '"'
                                    . 'id="' . $value_count . '_' . $valueName . '_add_checkbox_values"><i class="fa fa-minus"></i></button></div>';
                            $value_count++;
                        }
                    }
                    $result .= '<div class="parent_checkbox_container d-flex align-items-center row">'
                            . '<input type="hidden" id="' . $valueName . '_count" name="property_data[' . $valueName . '_count]" value="' . $count_items . '">'
                            . '<div class="input-group mb-3 w-75"><span class="input-group-text me-0 fw-bold">' . $globalLang['sys.heading'] . '</span>'
                            . '<input type="text" class="form-control" name="property_data[' . $valueName . '_title]" value="' . $value['title'] . '"/></div>'
                            . '<div class="checkbox_container d-flex align-items-center">'
                            . '<span>' . $globalLang['sys.title'] . '</span>&nbsp;'
                            . '<input type="text" required value="' . $first_element['name'] . '" name="property_data[' . $valueName . '_label][]"'
                            . 'class="form-control mb-2 w-25" data-bs-toggle="tooltip" data-bs-placement="top" >&nbsp'
                            . '<span>' . $globalLang['sys.value'] . '</span>&nbsp<div class="form-check float-start">'
                            . '<input type="checkbox" ' . (isset($first_element['checked']) ? 'checked ' : '') . ' value="0" class="form-check-input" '
                            . 'data-bs-toggle="tooltip" data-bs-placement="top" '
                            . 'title="' . $globalLang['sys.default'] . '" name="property_data[' . $valueName . '_value][]"></div>'
                            . '<button type="button" class="btn btn-primary" data-general-name="' . $valueName . '"'
                            . 'id="' . $value_count . '_' . $valueName . '_add_checkbox_values"><i class="fa fa-plus"></i></button>'
                            . '</div>' . $add_html . '</div>';
                    $multiple_choice = false;
                    break;
                case 'radio':
                    $count_items = 0;
                    if (isset($value['count']) && $value['count']) {
                        $count_items = $value['count'];
                    } else {
                        $count_items = count($value['label']);
                    }
                    $add_html = '';
                    if ($count_items) {
                        $first_element = ['name' => '', 'checked' => NULL];
                        $checked_arr = array_flip(explode(',', $value['value']));
                        $value_count = 0;
                        foreach ($value['label'] as $k => $name) {
                            if (!$value_count) {
                                $first_element = ['name' => $name, 'checked' => in_array('0', $checked_arr) ? 1 : NULL];
                                $value_count++;
                                continue;
                            }
                            $add_html .= '<div class="radio_container d-flex align-items-center">'
                                    . '<span>' . $globalLang['sys.title'] . '</span>&nbsp;'
                                    . '<input type="text" required value="' . $name . '" name="property_data[' . $valueName . '_label][]"'
                                    . 'class="form-control mb-2 w-25" data-bs-toggle="tooltip" data-bs-placement="top" >&nbsp'
                                    . '<span>' . $globalLang['sys.value'] . '</span>&nbsp<div class="form-check"><input type="radio"'
                                    . ' ' . (isset($checked_arr[$k]) ? 'checked ' : '') . 'class="form-check-input" data-bs-toggle="tooltip"'
                                    . 'data-bs-placement="top" value="' . $value_count . '" '
                                    . 'name="property_data[' . $valueName . '_value][]"></div>'
                                    . '<button type="button" class="btn btn-primary" data-general-name="' . $valueName . '"'
                                    . 'id="' . $value_count . '_' . $valueName . '_add_checkbox_values"><i class="fa fa-minus"></i></button></div>';
                            $value_count++;
                        }
                    }
                    $result .= '<div class="parent_radio_container d-flex align-items-center row">'
                            . '<input type="hidden" id="' . $valueName . '_count" name="property_data[' . $valueName . '_count]" value="' . $count_items . '">'
                            . '<div class="input-group mb-3 w-75"><span class="input-group-text me-0 fw-bold">' . $globalLang['sys.heading'] . '</span>'
                            . '<input type="text" class="form-control" name="property_data[' . $valueName . '_title]" value="' . $value['title'] . '"/></div>'
                            . '<div class="radio_container d-flex align-items-center">'
                            . '<span>' . $globalLang['sys.title'] . '</span>&nbsp;'
                            . '<input type="text" required value="' . $first_element['name'] . '" name="property_data[' . $valueName . '_label][]"'
                            . 'class="form-control mb-2 w-25" data-bs-toggle="tooltip" data-bs-placement="top" >&nbsp'
                            . '<span>' . $globalLang['sys.value'] . '</span>&nbsp<div class="form-check"><input type="radio" class="form-check-input" data-bs-toggle="tooltip"'
                            . 'data-bs-placement="top" ' . (isset($first_element['checked']) ? 'checked ' : '') . 'value="0"'
                            . ' name="property_data[' . $valueName . '_value][]"></div>'
                            . '<button type="button" class="btn btn-primary" data-general-name="' . $valueName . '"'
                            . 'id="' . $value_count . '_' . $valueName . '_add_radio_values"><i class="fa fa-plus"></i></button>'
                            . '</div>' . $add_html . '</div>';
                    $multiple_choice = false;
                    break;
                default:
                    $result .= '<span class="text-danger">Unsupported field type: ' . $value['type'] . '</span>';
            }
            $result .= '<span>' . $globalLang['sys.required'] . '</span><div class="form-check"><input type="checkbox"'
                    . 'class="form-check-input"' . ($value['required'] ? 'checked ' : '') . ' name="property_data[' . $valueName . '_required]"/></div>';
            if ($multiple_choice) {
                $result .= '<span>' . $globalLang['sys.multiple_choice'] . '</span><div class="form-check"><input type="checkbox"'
                        . 'class="form-check-input"' . ($value['multiple'] ? 'checked ' : '') . ' name="property_data[' . $valueName . '_multiple]"/></div>';
            }
            $result .= '</div>';
        }
        return $result;
    }

    /**
    * Генерирует HTML-код для загрузчика файлов с поддержкой множественных загрузок, ограничения по расширениям и модального окна для загрузки файлов по URL
    * Функция также подключает необходимые CSS и JS файлы для работы загрузчика и сортировки файлов
    * @param array $params Ассоциативный массив параметров для настройки загрузчика файлов. Поддерживаемые параметры:
    *  - 'name' (string): Имя инпута для загрузки файлов. По умолчанию 'upload_file'
    *  - 'id' (string): Уникальный идентификатор элемента загрузки. Если не указан, будет сгенерирован случайным образом
    *  - 'allowed_extensions' (string): Допустимые расширения файлов, разделенные запятыми (например, 'jpeg, png, gif'). По умолчанию пустая строка
    *  - 'multiple' (int): Флаг для поддержки множественной загрузки. Если 1, то разрешена множественная загрузка. По умолчанию 0
    *  - 'required' (int): Флаг, указывающий, является ли загрузка файлов обязательной. Если 1, инпут будет обязательным. По умолчанию 0
    *  - 'layout' (string): Макет отображения загрузчика ('horizontal' или 'vertical'). По умолчанию 'horizontal'
    *
    * @return string Возвращает сгенерированный HTML-код для загрузчика файлов.
    *
    * Пример использования:
    * ```php
    * echo self::ee_uploader([
    *     'allowed_extensions' => 'jpeg, png, gif',
    *     'multiple' => 1,
    *     'required' => 1,
    *     'layout' => 'vertical'
    * ]);
    * ```
    */
    public static function ee_uploader(array $params = []): string {
        global $globalLang;
        $defaultParams = [
            'name' => 'ee_upload_file_' . md5(SysClass::ee_generate_uuid()),
            'id' => 'upload_id_' . md5(SysClass::ee_generate_uuid()),
            'allowed_extensions' => '',
            'multiple' => 0,
            'required' => 0,
            'preloaded_files' => [],
            'layout' => 'horizontal' // 'vertical' или 'horizontal' TODO неработает
        ];
        $params = array_merge($defaultParams, $params);
        $params['preloaded_files'] = is_string($params['preloaded_files']) ? explode(',', $params['preloaded_files']) : $params['preloaded_files'];
        // Обработка allowed_extensions для атрибута accept
        $allowedExtensionsArray = array_map('trim', explode(',', $params['allowed_extensions']));
        if (!empty($allowedExtensionsArray[0])) {
            $acceptAttribute = 'accept=".' . implode(',.', $allowedExtensionsArray) . '"';
        } else {
            $acceptAttribute = '';
        }
        // Атрибуты multiple и required
        $multipleAttribute = $params['multiple'] ? 'multiple' : '';
        $requiredAttribute = $params['required'] ? 'required' : '';
        if (!self::$sortableJS) {
            $html = '<link rel="stylesheet" href="' . ENV_URL_SITE . '/classes/system/css/ee_uploader.css" type="text/css" />';
            $html .= '<script src="' . ENV_URL_SITE . '/classes/system/js/plugins/Sortable.min.js" type="text/javascript"></script>';
            $html .= '<script src="' . ENV_URL_SITE . '/classes/system/js/plugins/ee_uploader.js" type="text/javascript"></script>';
            self::$sortableJS = true;
        } else {
            $html = '';
        }       
        $html .= '<div class="card" style="align-items: center; width: 319px;" id="upload-content_' . $params['id'] . '" data-layout="' . $params['layout'] . '">';
        if (count($params['preloaded_files'])) { // Есть предзагруженные файлы, выводим их отдельно, учитывая сортировку
            $html .= '<div class="preloadedFilesData" id="preloaded_' . $params['id'] . '">';
            $preloadCount = 0;
            $matches = [];
            preg_match('/\[(.*?)\]/', $params['name'], $matches);
            $propertyName = isset($matches[1]) ? $matches[1] : $params['name'];
            foreach ($params['preloaded_files'] as $fileId) {
                $preloadCount++;
                $fileData = FileSystem::getFileData($fileId);
                if (!$fileData) continue;
                $fileData['unique_id'] = $fileData['file_id'];
                $fileData['property_name'] = $propertyName;
                $html .= '<div class="fileItem" data-unique-id="' . $fileData['file_id'] . '">';
                $html .= '<input type="hidden" name="ee_dataFiles[]" value="' . htmlentities(json_encode($fileData)) . '">';
                $html .= '<div class="fileName mb-2">' . $fileData['original_name'] . '</div>';
                if (strpos($fileData['mime_type'], 'image') !== false) {
                    $html .= '<img src="' . $fileData['file_url'] . '" />';
                } else {
                    $extension = pathinfo($fileData['file_path'], PATHINFO_EXTENSION);
                    $html .= FileSystem::getFileIcon($extension);
                }                                 
                $html .= '<div class="actionIcons" role="button"><i class="fas fa-trash actionIcon deleteIcon"></i><i class="fas fa-edit actionIcon editIcon"></i></div>';
                $html .= '</div>';
            }
            if ($preloadCount > 1 && empty($multipleAttribute)) {
                $message = 'Поле ' . $params['name'] . ' непомечено как множественное, удалите лишние файлы!';
                SysClass::preFile('errors', 'ee_uploader', $message, $params);
                \classes\helpers\ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            }            
            $html .= '</div>';
        }
        // general input
        $html .= '<input type="file" class="ee_fileInput" name="' . $params['name'] . '[]" id="' . $params['id'] . '" '
                . 'data-ee_uploader="true" data-allowed-extensions="'
                . $params['allowed_extensions'] . '" ' . $acceptAttribute . ' ' . $multipleAttribute . ' ' . $requiredAttribute . ' />';
        // HTML для модального окна
        $html .= '<div class="modal fade" id="uploadModal-' . $params['id'] . '" tabindex="-1" aria-labelledby="uploadModalLabel-' . $params['id'] . '" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="uploadModalLabel-' . $params['id'] . '">' . $globalLang['sys.download'] . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . $globalLang['sys.close'] . '"></button>
                    </div>
                    <div class="modal-body">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="file-url-input-' . $params['id'] . '" placeholder="' . $globalLang['sys.insert'] . ' URL">
                            <button class="btn btn-outline-secondary" type="button" id="add-file-by-url-' . $params['id'] . '">' . $globalLang['sys.add'] . '</button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="addFileParamsModal_' . $params['id'] . '" tabindex="-1" aria-labelledby="addFileParamsModalLabel_' . $params['id'] . '" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addFileParamsModalLabel_' . $params['id'] . '">' . $globalLang['sys.edit'] . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . $globalLang['sys.edit'] . '"></button>
                    </div>
                    <div class="modal-body">
                        <form id="fileParamsForm-' . $params['id'] . '">
                            <div class="mb-3">
                                <label for="file_name_' . $params['id'] . '" class="form-label">' . $globalLang['sys.file_name'] . '</label>
                                <input type="text" class="form-control" id="file_name_' . $params['id'] . '" name="original_name" value="">
                            </div>
                            <button type="button" class="btn btn-primary" id="submit_' . $params['id'] . '">' . $globalLang['sys.save'] . '</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>';
        $html .= '</div><!-- close card -->';
        return $html;
    }

    /**
     * Рекурсивно создает строку с опциями выпадающего списка для иерархических типов категорий
     * Каждый дочерний уровень типа будет иметь увеличенный отступ для визуального представления иерархии
     * Добавляет в начало списка пустой элемент <option>, чтобы обеспечить возможность выбора "пустоты"
     * @param array $types Массив типов категорий, где каждый тип содержит ключи 'type_id', 'parent_type_id', 'name' и, возможно, 'children'
     * @param int $selected_type_id ID выбранного типа. Этот тип будет отмечен как выбранный в выпадающем списке
     * @param int $parent_type_id ID родительского типа для текущего уровня иерархии. По умолчанию равен 0, что соответствует корневому уровню
     * @param int $level Текущий уровень иерархии. Используется для добавления отступов дочерним элементам. По умолчанию равен 0 для корневого уровня
     * @return string Строка с HTML кодом опций для элемента <select>
     */
    public static function showTypeCategogyForSelect($types, $selected_type_id = null, $parent_type_id = 0, $level = 0) {
        $html = '';
        foreach ($types as $type) {
            if ($type['parent_type_id'] == $parent_type_id) {
                $indent = str_repeat("&nbsp;&nbsp;&nbsp;", $level);
                $symbol = $level > 0 ? '↳ ' : '';
                $selected = $selected_type_id == $type['type_id'] ? ' selected' : '';
                $html .= '<option value="' . $type['type_id'] . '"' . $selected . '>' . $indent . $symbol . $type['name'] . '</option>';
                if (!empty($type['children'])) {
                    $html .= self::showTypeCategogyForSelect($type['children'], $selected_type_id, $type['type_id'], $level + 1);
                }
            }
        }
        return $html;
    }

    /**
     * Генерация HTML кода для наборов свойств
     * @param array $propertySetsDataДанные для наборов свойств
     * @param array $categoriesTypeSetsData Данные для типов наборов категорий
     * @return string Сгенерированный HTML код
     */
    public static function renderPropertySets($propertySetsData, $categoriesTypeSetsData) {
        global $globalLang;
        $html = '';
        if (count($categoriesTypeSetsData)) {
            foreach ($categoriesTypeSetsData as $item_ctsd) {
                $html .= '<input type="hidden" name="old_property_set[]" value="' . $item_ctsd . '" />';
            }
        } else {
            $html .= '<input type="hidden" name="old_property_set[]" value="" />';
        }
        foreach ($propertySetsData['data'] as $propertySet) {
            $html .= '<div class="accordion my-1" id="accordion-' . $propertySet['set_id'] . '">';
            $html .= '<div class="card">';
            $html .= '<div class="card-header" id="heading-' . $propertySet['set_id'] . '">';
            $html .= '<h2 class="mb-0">';
            $html .= '<input type="checkbox" id="checkbox-' . $propertySet['set_id'] . '" name="property_set[]"'
                    . 'value="' . $propertySet['set_id'] . '" class="form-check-input me-2"'
                    . (in_array($propertySet['set_id'], $categoriesTypeSetsData) ? "checked" : "") . '>';
            $html .= '<button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-' . $propertySet['set_id'] .
                    '" aria-expanded="true" aria-controls="collapse-' . $propertySet['set_id'] . '">';
            $html .= $propertySet['name'];
            $html .= '</button>';
            $html .= '</h2>';
            $html .= '</div>';
            $html .= '<div id="collapse-' . $propertySet['set_id'] . '" class="collapse" aria-labelledby="heading-' . $propertySet['set_id'] .
                    '" data-bs-parent="#accordion-' . $propertySet['set_id'] . '">';
            $html .= '<div class="card-body">';
            $html .= '<h5>' . $globalLang['sys.description'] . '</h5>' . '<p>' . ($propertySet['description'] ? $propertySet['description'] : '---') . '</p>';
            $html .= '<h6>' . $globalLang['sys.properties'] . '</h6>';
            if (!count($propertySet['properties'])) {
                $html .= '---';
            }
            foreach ($propertySet['properties'] as $property) {
                $html .= $property['name'] . '<br/>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        return $html;
    }

    /**
     * Рекурсивно выводит опции категорий для элемента select HTML, формируя иерархическую структуру
     * Каждая подкатегория имеет отступ, соответствующий ее уровню вложенности
     * @param array $categories Массив категорий, где каждая категория содержит информацию о себе и, возможно, о своих подкатегориях ('children')
     * @param int $selectedCategoryId ID выбранной категории. Если ID совпадает с ID категории в массиве, эта категория будет отмечена как выбранная
     * @param int $parentId ID родительской категории для текущего уровня иерархии. По умолчанию 0 (верхний уровень)
     * @param int $level Текущий уровень иерархии. Используется для определения количества отступов перед названием категории
     * @return string Строка HTML с опциями категорий для использования в элементе select
     */
    public static function showCategogyForSelect($categories, $selectedCategoryId, $parentId = 0, $level = 0) {
        $html = '';
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $indent = str_repeat('-', $level * 2);
                $selected = $selectedCategoryId == $category['category_id'] ? 'selected' : '';
                $html .= "<option $selected value='{$category['category_id']}'>{$indent} {$category['title']}</option>";
                if (!empty($category['children'])) {
                    $html .= self::showCategogyForSelect($category['children'], $selectedCategoryId, $category['category_id'], $level + 1);
                }
            }
        }
        return $html;
    }

    /**
     * Рекурсивно выводит дерево категорий в виде аккордеона Bootstrap.
     * @param array $categories Массив категорий.
     * @param string $parentId Родительский ID для связи элементов аккордеона.
     * @return string HTML код дерева категорий в виде аккордеона.
     */
    public static function renderCategoryTree($categories, $parentId = "root") {
        $html = "<div class='accordion' id='accordionCategory{$parentId}'>";
        foreach ($categories as $category) {
            if ($category['category_id'] === 0) {
                continue;
            }
            $childId = "category{$category['category_id']}";
            $titleWithId = "({$category['category_id']}) {$category['title']}";
            $hasChildren = !empty($category['children']);
            $buttonClass = $hasChildren ? 'accordion-button collapsed' : 'accordion-button collapsed no-chevron';
            $html .= "<div class='accordion-item'>
                        <h2 class='accordion-header' id='heading{$childId}'>
                            <button class='{$buttonClass}' data-category_id='{$category['category_id']}' type='button' " . ($hasChildren ? "data-bs-toggle='collapse' data-bs-target='#collapse{$childId}' aria-expanded='false'" : "") . " aria-controls='collapse{$childId}'>
                                {$titleWithId}
                            </button>
                        </h2>";
            if ($hasChildren) {
                $html .= "<div id='collapse{$childId}' class='accordion-collapse collapse' aria-labelledby='heading{$childId}' data-bs-parent='#accordionCategory{$parentId}'>
                            <div class='accordion-body'>";
                $html .= self::renderCategoryTree($category['children'], $category['category_id']);
                $html .= "  </div>
                        </div>";
            }
            $html .= "</div>";
        }
        $html .= "</div>";
        return $html;
    }

    /**
     * Генерирует HTML код модального окна Bootstrap 5
     * @param string $id Идентификатор модального окна
     * @param string $title Заголовок окна
     * @param string $bodyContent Содержимое тела окна
     * @param array $buttons Массив кнопок. Каждый элемент массива должен содержать текст кнопки, классы стилей и опционально тип кнопки
     * [
      ['text' => 'Закрыть', 'class' => 'btn-secondary', 'type' => 'button', 'meta' => 'data-bs-dismiss="modal"'],
      ['text' => 'Сохранить изменения', 'class' => 'btn-primary', 'type' => 'submit']
      ]
     * @return string HTML код модального окна
     */
    public static function ee_generateModal($id, $title, $bodyContent, $buttons = [['text' => 'Закрыть', 'class' => 'btn-secondary', 'type' => 'button', 'meta' => 'data-bs-dismiss="modal"']]) {
        $html = "<div class='modal fade' id='{$id}' tabindex='-1' aria-labelledby='{$id}Label' aria-hidden='true'>
                    <div class='modal-dialog modal-dialog-centered modal-dialog-scrollable'>
                        <div class='modal-content'>
                            <div class='modal-header'>
                                <h5 class='modal-title' id='{$id}Label'>{$title}</h5>
                                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                            </div>
                            <div class='modal-body' style='overflow-y: auto; max-height: 80vh;'>
                                {$bodyContent}
                            </div>
                            <div class='modal-footer'>";
        foreach ($buttons as $button) {
            $buttonType = $button['type'] ?? 'button';
            $meta = $button['meta'] ?? '';
            $html .= "<button type='{$buttonType}' class='btn {$button['class']}' {$meta}>{$button['text']}</button>";
        }
        $html .= "</div></div></div></div>";
        return $html;
    }
}
