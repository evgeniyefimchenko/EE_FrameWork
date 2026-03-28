<?php

namespace classes\system;

/**
 * Системный класc для создания плагинов на страницах сайта
 * Все методы статические
 */
class Plugins {

    private const RAW_TABLE_FIELDS = ['actions', 'action', 'controls', 'html'];

    /**
     * Загрузка библиотеки SortableJS на страницу
     */
    private static $sortableJS = false;
    
    function __construct() {
        throw new Exception('Static only.');
    }

    /**
     * Создаёт таблицу с возможностью пагинации, фильтрации и сортировки.
     * @param string $idTable Идентификатор таблицы.
     * @param array $dataTable Данные для таблицы.
     * @param str $callbackFunction Функция AJAX обработки таблицы.
     * @param int $page Текущая страница пагинации.
     * @param int $currentRowsPerPage Текущее количество записей на странице.
     * @param array $filters Установленные фильтры для таблицы.
     * @param array $selected_sorting Уже выбранная сортировка.
     * @param str $add_class Дополнительный класс таблицы.
     * @param int $max_buttons Максимальное количество кнопок на странице пагинации.
     * @return string HTML таблицы.
     */
    public static function ee_show_table($idTable = 'test_table', $dataTable = [], $callbackFunction = '', $filters = [], $page = 1, $currentRowsPerPage = 25, $selected_sorting = [], $add_class = 'table-striped', $max_buttons = 5) {
        if (!is_array($dataTable)) {
            return '<div class="alert alert-danger text-center">Ошибка формата данных</div>';
        }
        if (!is_string($add_class)) {
            $add_class = 'table-striped';
        }
        $columns = is_array($dataTable['columns'] ?? null) ? $dataTable['columns'] : [];
        $html = '<div class="mb-3" data-tableID="' . $idTable . '" id="' . $idTable . '_content_tables">';
        $html .= '<input type="hidden" id="' . $idTable . '_callback_function" value="' . $callbackFunction . '">';
        $html .= self::generateFilterSection($idTable, $dataTable, $filters);
        $html .= '<div class="table-responsive">';
        $html .= '<table id="' . $idTable . '" class="table table-sm align-middle ' . $add_class . '">';
        $html .= self::generateTableHeader($idTable, $columns, $selected_sorting);
        $html .= self::generateTableBody($dataTable, $idTable);
        $html .= '</table>';  // закрыть таблицу
        $html .= '</div>';
        $html .= self::generatePagination($idTable, (int) $page, $dataTable, (int) $currentRowsPerPage, (int) $max_buttons);
        $html .= self::generateRowsPerPageSection($idTable, $dataTable, $currentRowsPerPage);
        if (!SysClass::isAjaxRequestFromSameSite()) { // Не подгружаем если AJAX запрос
            $html .= '<script src="' . ENV_URL_SITE . '/assets/js/plugins/ee_show_table.js" type="text/javascript"></script>';
        }
        return $html . '</div>';
    }

    /**
     * Генерирует HTML раздел фильтрации для таблицы на основе предоставленных данных.
     * @param string $idTable   Идентификатор таблицы, для которой создаются фильтры.
     * @param array  $dataTable Массив данных таблицы, содержит информацию о колонках, их заголовках и фильтруемости.
     * @param array  $filters    Массив фильтров, каждый элемент которого содержит информацию о типе фильтра,
     *                           его идентификаторе, значении и других атрибутах.
     * @return string Возвращает HTML-код раздела фильтрации.
     */
    private static function generateFilterSection($idTable, $dataTable, $filters) {
        $langCode = Session::get('lang');
        $globalLang = Lang::init($langCode);
        $applyFiltersLabel = (string) ($globalLang['sys.apply_filters'] ?? 'Применить фильтры');
        $html = '';
        $html .= '<button class="btn btn-primary mb-2" type="button" data-table="' . $idTable . '" data-bs-toggle="collapse" data-bs-target="#' . $idTable . '_filtersCollapse"'
                . 'aria-expanded="false" id="' . $idTable . '_button_filtersCollapse" aria-controls="' . $idTable . '_filtersCollapse"><i class="fa-solid fa-filter"'
                . 'data-bs-toggle="tooltip" data-bs-placement="top" id="' . $idTable . '_icon_filtersCollapse" title="' . $globalLang['sys.filtres'] . '"></i></button>';
        // Начало блока collapse
        $html .= '<div class="collapse" id="' . $idTable . '_filtersCollapse">';
        $html .= '<div class="card card-body">';
        // Раздел фильтрации
        if (!is_array($filters) || empty($filters)) {
            $filters = [];
            foreach ($dataTable['columns'] as $column) {
                if ($column['filterable']) {
                    $filters[$column['field']] = [
                        'type' => 'text',
                        'id' => $idTable . "_filter_" . $column['field'],
                        'value' => '',
                        'label' => $column['title']  // Значение по умолчанию для label
                    ];
                }
            }
        }
        $filterableColumnsCount = 0;
        foreach ($dataTable['columns'] as $column) {
            if (isset($column['filterable']) && $column['filterable'] === true) { // Подсчет колонок с 'filterable' => true
                $filterableColumnsCount++;
            }
        }
        $count_filters = count($filters);
        if ($count_filters != $filterableColumnsCount) {
            $count_filters = 0;
            $html .= '<div class="alert alert-danger text-center">Ошибка: количество переданных фильтров не совпадает с количеством колонок для фильтрации!</div>';
        }
        $html .= '<form class="mb-3 ' . (!$count_filters ? 'd-none' : '') . '" id="' . $idTable . '_filters">';
        $html .= '<input type="hidden" name="' . $idTable . '_table_name" value="' . $idTable . '">';
        // Добавим префикс при его отсутствии
        foreach ($filters as $key => $filter) {
            if (strpos($filter['id'], $idTable . '_filter_') === false) {
                $filters[$key]['id'] = $idTable . '_filter_' . $filters[$key]['id'];
            }
        }
        $html .= '<input type="hidden" name="' . $idTable . '_old_filters" value="' . htmlspecialchars(json_encode($filters), ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="row justify-content-center">';
        foreach ($filters as $key => $filter) {
            $html .= '<div class="col-6 col-sm-3 text-center">';
            $filterId = $filter['id'] ?? $idTable . '_filter_' . $key;
            $filterValue = $filter['value'] ?? "";
            $filterLabel = !empty($filter['label']) ? $filter['label'] : 'Unknown';
            $html .= '<label for="' . $filterId . '">' . htmlspecialchars((string) $filterLabel, ENT_QUOTES, 'UTF-8') . '</label>';
            switch ($filter['type']) {
                case 'text':
                    $html .= '<input type="text" class="form-control mb-2" name="' . $filterId . '" id="' . $filterId . '" value="' . htmlspecialchars((string) $filterValue, ENT_QUOTES, 'UTF-8') . '">';
                    break;
                case 'checkbox':
                    foreach ($filter['options'] as $option) {
                        $checked = in_array($option['value'], (array) $filterValue) ? ' checked' : '';
                        $html .= '<div class="form-check mb-2">';
                        $html .= '<input class="form-check-input" type="checkbox" value="' . htmlspecialchars((string) ($option['value'] ?? ''), ENT_QUOTES, 'UTF-8') . '" name="' . $filterId . '_' . ($option['id'] ?? '') . '" id="' . $filterId . '_' . ($option['id'] ?? '') . '"' . $checked . '>';
                        $html .= '<label class="form-check-label" for="' . ($option['id'] ?? '') . '">' . htmlspecialchars((string) ($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</label>';
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
                        $html .= '<option value="' . htmlspecialchars((string) ($option['value'] ?? ''), ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars((string) ($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    $html .= '</select>';
                    break;
                case 'date':
                    $html .= '<input type="date" class="form-control mb-2" name="' . $filterId . '" id="' . $filterId . '" value="' . htmlspecialchars((string) $filterValue, ENT_QUOTES, 'UTF-8') . '">';
                    break;
            }
            $html .= '</div>';
        }
        $html .= '</div><div class="w-100 text-center"><button type="submit" class="btn btn-primary" form="' . $idTable . '_filters">' . htmlspecialchars($applyFiltersLabel, ENT_QUOTES, 'UTF-8') . '</button>
		<button type="reset" id="' . $idTable . '_filters_reset" class="btn btn-secondary" form="' . $idTable . '_filters">' . htmlspecialchars((string) ($globalLang['sys.clear'] ?? 'Сбросить'), ENT_QUOTES, 'UTF-8') . '</button></div>';
        // Закрываем форму и блок collapse
        $html .= '</form>';
        $html .= '</div></div>'; // Закрываем .card-body и .collapse
        return $html;
    }

    /**
     * Генерирует HTML раздел заголовка таблицы на основе предоставленных данных.
     * @param string $idTable   Идентификатор таблицы, для которой создается заголовок.
     * @param array $columns Массив колонок.
     * @return string Возвращает HTML-код заголовка таблицы.
     */
    private static function generateTableHeader($idTable, $columns, $selected_sorting = []) {
        if (!is_array($selected_sorting))
            $selected_sorting = [];
        $html = '<thead>';
        if (!$columns) {
            $html .= '<th>';
            $html .= Lang::get('sys.no_data');
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
                $sortedIndicator = '<a href="#" data-current-sort="' . $current_sorting . '" id="' . $idTable . '_column_' . $column['field'] . '_' . $current_sorting . '">' . $sortedIcon . '</a>';
            }
            // Проверяем, установлено ли значение ширины для этой колонки
            $widthStyle = isset($column['width']) ? ' style="width:' . (int) $column['width'] . '%;"' : ' style="width:auto;"';
            $add_class = isset($column['align']) ? 'text-' . $column['align'] : '';
            $html .= '<th' . $widthStyle . ' class="' . $add_class . '">';
            $html .= htmlspecialchars((string) ($column['title'] ?? ''), ENT_QUOTES, 'UTF-8') . $sortedIndicator;
            $html .= '</th>';
        }
        $html .= '</thead>';
        return $html;
    }

    /**
     * Генерирует HTML для тела таблицы, включая вложенные таблицы, если они предоставлены.
     *
     * @param array $dataTable Ассоциативный массив, содержащий данные для таблицы. Массив должен иметь следующую структуру:
     *      [
     *          'total_rows' => (int) Общее количество строк в таблице,
     *          'columns' => (array) Массив ассоциативных массивов, каждый из которых представляет собой столбец в таблице. Каждый массив столбца должен иметь следующие ключи:
     *              'field' => (string) Ключ, используемый для поиска значения этого столбца в массиве строк,
     *              'align' => (string, optional) Выравнивание текста для этого столбца. Должно быть 'left', 'right' или 'center'.
     *              'width' => (int, optional) Ширина столбца в процентах. Если параметр не передан, используется значение 'auto'.
     *          'rows' => (array) Массив ассоциативных массивов, каждый из которых представляет собой строку в таблице. Каждый массив строк должен иметь ключи, соответствующие значениям 'field' массива столбцов.
     *              'nested_table' => (array, optional) Ассоциативный массив, представляющий вложенную таблицу. Этот массив должен иметь ту же структуру, что и массив $dataTable.
     *      ]
     * @param int $idTable ID таблицы
     * @return string HTML для тела таблицы.
     */
    private static function generateTableBody($dataTable, $idTable) {
        $langCode = Session::get('lang');
        $globalLang = Lang::init($langCode);
        $html = '<tbody>';
        if ($dataTable['total_rows'] == 0 || count($dataTable['rows']) == 0) { // Если записей нет
            $html .= '<tr><td colspan="' . count($dataTable['columns']) . '" class="text-center text-muted">' . htmlspecialchars((string) ($globalLang['sys.no_data'] ?? 'Нет данных'), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        } else {
            $count_row = 1;
            foreach ($dataTable['rows'] as $row) {
                $html .= '<tr>';
                $firstColumn = true;  // Флаг для отслеживания первой колонки
                foreach ($dataTable['columns'] as $column) {
                    $value = $row[$column['field']] ?? '';
                    $textAlignStyle = isset($column['align']) ? ' style="text-align:' . $column['align'] . ';"' : '';
                    $renderRaw = self::shouldRenderTableCellRaw((array) $column);
                    $cellHtml = $renderRaw
                        ? (string) $value
                        : htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                    $html .= '<td' . $textAlignStyle . '>';
                    $html .= $cellHtml;
                    if ($firstColumn && isset($row['nested_table'])) {
                        $html .= '<div class="expand_nested_table" data-nested_table="#' . $idTable . '_nested_table_' . $count_row . '">' .
                                '<i class="fa fa-plus" style="right: 8px; position: relative;"></i></div>';  // Кнопка "плюс" в первой колонке
                    }
                    $html .= '</td>';
                    $firstColumn = false;  // Сброс флага после обработки первой колонки
                }
                $html .= '</tr>';
                if (isset($row['nested_table'])) {
                    $nested_table = $row['nested_table'];
                    $colspan = count($dataTable['columns']);
                    $html .= '<tr class="tr_nested_table" id="' . $idTable . '_nested_table_' . $count_row . '"><td colspan="' . $colspan . '">';
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
                            $value = $nested_row[$column['field']] ?? '';
                            $textAlignStyle = isset($column['align']) ? ' style="text-align:' . $column['align'] . ';"' : '';
                            $renderRaw = self::shouldRenderTableCellRaw((array) $column);
                            $cellHtml = $renderRaw
                                ? (string) $value
                                : htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                            $html .= '<td' . $textAlignStyle . '>' . $cellHtml . '</td>';
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
     * @param string $idTable Идентификатор таблицы.
     * @param int $page Текущая страница.
     * @param array $dataTable Массив данных таблицы.
     * @param int $currentRowsPerPage Текущее количество строк на странице.
     * @param int $max_buttons Максимальное количество кнопок пагинации.
     * @return string Возвращает HTML-код пагинации.
     */
    private static function generatePagination($idTable, $page, $dataTable, $currentRowsPerPage, $max_buttons) {
        $html = '';
        $total_pages = ceil($dataTable['total_rows'] / $currentRowsPerPage);
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
     * @param string $idTable Идентификатор таблицы.
     * @param array $dataTable Массив данных таблицы.
     * @param int $currentRowsPerPage Текущее количество строк на странице.
     * @return string Возвращает HTML-код раздела для выбора количества строк.
     */
    private static function generateRowsPerPageSection($idTable, $dataTable, $currentRowsPerPage) {
        // Возможные значения строк на странице
        $possible_rows = [10, 25, 50, 100];
        $count_row = isset($dataTable['rows']) && is_array($dataTable['rows']) ? count($dataTable['rows']) : 0;
        $html = '<div class="rows-per-page-section">';
        $html .= '<label for="' . $idTable . '-rows-per-page">Количество строк:&nbsp;</label>';
        $html .= '<select id="' . $idTable . '-rows-per-page" class="form-select form-select-sm d-inline-block" style="width: auto; cursor: pointer;">';

        foreach ($possible_rows as $value) {
            $selected = ($value == $currentRowsPerPage) ? ' selected="selected"' : '';
            $html .= '<option value="' . $value . '"' . $selected . '>' . $value . '</option>';
        }
        if (!$dataTable['total_rows'])
            $dataTable['total_rows'] = 0;
        $html .= '</select>';
        $html .= '<div class="pagination-info float-end">';
        $html .= 'Записей на странице: <span class="current-page-count">' . $count_row . '</span> из <span class="total-count">' . $dataTable['total_rows'] . '</span>';
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
        $allowedFields = [];
        foreach ((array) $columns as $column) {
            $field = trim((string) ($column['field'] ?? ''));
            if ($field !== '') {
                $allowedFields[$field] = true;
            }
        }
        // Преобразование filter
        $whereConditions = [];
        foreach ($filter as $key => $filterItem) {
            $identifier = self::quoteSqlIdentifier((string) $key, $allowedFields);
            if ($identifier === null) {
                continue;
            }
            if ($filterItem['type'] === 'text' && !empty($filterItem['value']) && $filterItem['value']) { // Отсекаем пустые значения
                $whereConditions[] = $identifier . ' LIKE ' . self::escapeSqlLikeContains((string) $filterItem['value']) . " ESCAPE '\\\\'";
            } elseif ($filterItem['type'] === 'select' && !empty($filterItem['value'])) {
                $values = array_values(array_filter((array) $filterItem['value'], static function ($value) {
                    return trim((string) $value) !== '';
                }));
                if (!empty($values)) {
                    if (!empty($filterItem['multiple'])) {
                        $whereConditions[] = $identifier . ' IN (' . implode(',', array_map([self::class, 'escapeSqlLiteral'], $values)) . ')';
                    } else {
                        $whereConditions[] = $identifier . ' = ' . self::escapeSqlLiteral((string) reset($values));
                    }
                }
            } elseif ($filterItem['type'] === 'date' && !empty($filterItem['value'])) {
                if (preg_match('/\d{2}:\d{2}(:\d{2})?/', $filterItem['value'])) {
                    // Содержит время
                    $whereConditions[] = $identifier . ' = ' . self::escapeSqlLiteral((string) $filterItem['value']);
                } else {
                    // Не содержит время, используем условие для целых суток
                    $dateStart = $filterItem['value'] . " 00:00:00";
                    $dateEnd = $filterItem['value'] . " 23:59:59";
                    $whereConditions[] = $identifier . ' >= ' . self::escapeSqlLiteral($dateStart) . ' AND ' . $identifier . ' <= ' . self::escapeSqlLiteral($dateEnd);
                }
            }
        }
        $whereString = !empty($whereConditions) ? implode(" AND ", $whereConditions) : '';
        // Преобразование sort
        $orderString = '';
        foreach ($sort as $key => $direction) {
            $identifier = self::quoteSqlIdentifier((string) $key, $allowedFields);
            if ($identifier === null) {
                continue;
            }
            $direction = strtoupper(trim((string) $direction)) === 'DESC' ? 'DESC' : 'ASC';
            $orderString .= $identifier . ' ' . $direction . ', ';
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

    private static function shouldRenderTableCellRaw(array $column): bool {
        if (!empty($column['raw']) || !empty($column['html'])) {
            return true;
        }

        $field = strtolower(trim((string) ($column['field'] ?? '')));
        return in_array($field, self::RAW_TABLE_FIELDS, true);
    }

    private static function quoteSqlIdentifier(string $field, array $allowedFields): ?string {
        $field = trim($field);
        if ($field === '' || !isset($allowedFields[$field])) {
            return null;
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
            return null;
        }
        return '`' . $field . '`';
    }

    private static function escapeSqlLiteral(string $value): string {
        return "'" . str_replace(
            ["\\", "\0", "\n", "\r", "'", '"', "\x1a"],
            ["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"],
            $value
        ) . "'";
    }

    private static function escapeSqlLikeContains(string $value): string {
        $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
        return self::escapeSqlLiteral('%' . $value . '%');
    }

////////END Plugin TABLE

    /**
     * Генерирует вертикальное меню на основе предоставленных данных
     * @param array $data Данные меню со следующей структурой:
     *                   - 'menuItems' => массив с элементами структуры меню
     *                   - 'footerTitle' => Заголовок для нижнего раздела меню
     * @return string Сгенерированный HTML-код для вертикального меню
     */
    public static function generateVerticalMenu(array $data): string {
        $menuItems = $data['menuItems'];
        $footerTitle = $data['footerTitle'];
        $html = '<div id="layoutSidenav_nav"><nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion"><div class="sb-sidenav-menu"><div class="nav">';
        foreach ($menuItems['headings'] as $heading => $items) {
            $html .= '<div class="sb-sidenav-menu-heading">' . $heading . '</div>';
            foreach ($items as $item) {
                $attributes = isset($item['attributes']) ? ' ' . $item['attributes'] : '';
                if (isset($item['subItems'])) {
                    $collapseId = preg_replace('/\s+/u', '_', $item['title']);
                    $html .= '<a class="nav-link collapsed" href="' . ($item['link'] ?: '#') . '" data-bs-toggle="collapse"' .
                            'data-bs-target="#collapse_' . $collapseId . '" aria-expanded="false" aria-controls="collapse_' . $collapseId . '"' . $attributes . '>' .
                            '<div class="sb-nav-link-icon"><i class="fas ' . $item['icon'] . '"></i></div>' . $item['title'] .
                            '<div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div></a>' .
                            '<div class="collapse" id="collapse_' . $collapseId . '" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">' .
                            '<nav class="sb-sidenav-menu-nested nav">';
                    foreach ($item['subItems'] as $subItem) {
                        $subAttributes = isset($subItem['attributes']) ? ' ' . $subItem['attributes'] : '';
                        if ($subItem['link']) {
                            $html .= '<a class="nav-link" href="' . $subItem['link'] . '" data-parent-bs-target="#collapse_' . $collapseId . '"' . $subAttributes . '>
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
        // TODO что тут делает скрипт?!!!
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
        if (!empty($data['toolbarHtml'])) {
            $html .= (string) $data['toolbarHtml'];
        }
        $searchPlaceholder = htmlspecialchars((string) ($data['searchPlaceholder'] ?? 'Search...'), ENT_QUOTES);
        $searchAriaLabel = htmlspecialchars((string) ($data['searchAriaLabel'] ?? $searchPlaceholder), ENT_QUOTES);
        $html .= '<form action="/admin/search" method="GET" class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0"><div class="input-group"><input class="form-control" name="q" type="text" placeholder="' . $searchPlaceholder . '" aria-label="' . $searchAriaLabel . '" aria-describedby="btnNavbarSearch" value="' . htmlspecialchars($_GET['q'] ?? '') . '" /><button class="btn btn-primary" id="btnNavbarSearch" type="submit"><i class="fas fa-search"></i></button></div></form>';
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
                $iconClass = trim((string) ($notification['icon'] ?? 'fa-solid fa-circle-info'));
                if ($iconClass === '') {
                    $iconClass = 'fa-solid fa-circle-info';
                } elseif (strpos($iconClass, 'fa-') === false) {
                    $iconClass = 'fas ' . $iconClass;
                }
                if ($count > 1)
                    $html .= '<li><hr class="dropdown-divider" /></li>';
                $html .= '<li><a style="padding-left: 5px;" class="dropdown-item" href="' . htmlspecialchars((string) ($notification['url'] ?? '#'), ENT_QUOTES) . '"><i style="color: ' . htmlspecialchars((string) ($notification['color'] ?? '#61bdd1'), ENT_QUOTES) . '" class="' . htmlspecialchars($iconClass, ENT_QUOTES) . '"></i>&nbsp;' . htmlspecialchars((string) ($notification['text'] ?? ''), ENT_QUOTES) . '</a></li>';
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
     * Для заполнения дефолтных параметров свойств
     * Функция принимает массив, значения типы полей (например, "text", "select" и т.д.)
     * На основе этого массива функция генерирует и выводит HTML-теги в контейнерах
     * @param array $fields
     * @return string
     */
    public static function renderPropertyHtmlFields(mixed $fields, mixed $default = []): string {
        $langCode = Session::get('lang');
        $globalLang = Lang::init($langCode);
        $result = '';
        $fieldDefinitions = self::normalizeTypeFieldDefinitions($fields);
        $defaults = PropertyFieldContract::normalizeDefaultFieldsForStorage($default, $fieldDefinitions);

        foreach ($defaults as $count => $fieldValue) {
            $type = (string) ($fieldValue['type'] ?? 'text');
            $valueName = $type . '_' . $count;

            $result .= $globalLang['sys.type'] . ' ' . ucfirst($type) . ': ';
            $result .= '<div class="row rounded-2 p-2 col-12 col-sm-12 mt-2 align-items-start property_content border">';
            $result .= '<input type="hidden" name="property_data_changed" value="0" />';
            $result .= '<input type="hidden" name="property_data[' . $valueName . '_uid]" value="' . htmlspecialchars((string) ($fieldValue['uid'] ?? ('legacy_' . $count)), ENT_QUOTES) . '" />';
            $result .= '<input type="hidden" name="property_data[' . $valueName . '_type]" value="' . htmlspecialchars($type, ENT_QUOTES) . '" />';
            $result .= '<div class="col">';
            $result .= '<label class="form-label">' . $globalLang['sys.title'] . '</label>';
            $result .= '<input type="text" required name="property_data[' . $valueName . '_label]" class="form-control mb-2" value="' . htmlspecialchars((string) ($fieldValue['label'] ?? ''), ENT_QUOTES) . '" />';
            $result .= '</div>';
            $result .= '<div class="col">';
            $result .= '<label class="form-label">' . $globalLang['sys.heading'] . '</label>';
            $result .= '<input type="text" name="property_data[' . $valueName . '_title]" class="form-control mb-2" value="' . htmlspecialchars((string) ($fieldValue['title'] ?? ''), ENT_QUOTES) . '" />';
            $result .= '</div>';
            $fieldValue['value'] = $fieldValue['default'] ?? [];
            $result .= self::renderFieldsProperty($fieldValue, $type, $valueName, 'default');
        }

        return $result;
    }

    /**
     * Функция для генерации HTML-кода аккордеона для наборов категорий и их свойств
     * @param array $entitySetsData Данные о наборах сущностей и их свойствах
     * @param array $entityId ID категории или страницы
     * @param string $typeEntity Тип сущности
     * @return string HTML-код аккордеона
     */
    public static function renderPropertiesSetsAccordion($entitySetsData, $entityId, $typeEntity = 'category') {
        $langCode = Session::get('lang');
        $globalLang = Lang::init($langCode);
        $html = '';
        foreach ($entitySetsData as $entityName => $entitySet) {
            foreach ($entitySet as $propertySet) {
                foreach ($propertySet['properties'] as $kp => $property) {
                    if ($property['property_entity_type'] != $typeEntity && $property['property_entity_type'] != 'all') {
                        unset($propertySet['properties'][$kp]);
                    }
                }
                $showSet = count($propertySet['properties']) ? true : false;
                $propertySet_id = hash('crc32', $propertySet['set_id'] . $propertySet['name'] . $propertySet['created_at']);
                $html .= '<div class="accordion my-3" id="accordion-' . $propertySet_id . '">';
                $html .= '<div class="card">';
                $html .= '<div class="card-header" id="heading-' . $propertySet_id . '">';
                $html .= '<h2 class="mb-0">';
                $html .= '<span class="h5">' . $globalLang['sys.set'] . ':</span> ';
                $html .= '<button ' . (!$showSet ? 'disabled ' : '') . 'class="btn btn-link" type="button" data-setId="' . $propertySet['set_id'] . '"'
                        . 'data-bs-toggle="collapse" data-bs-target="#collapse-' . $propertySet_id . '" aria-expanded="true" '
                        . 'aria-controls="collapse-' . $propertySet_id . '">';
                $html .= $propertySet['name'] . '<input type="hidden" name="set_id" value="' . $propertySet['set_id'] . '" />';
                $html .= '</button>';
                $html .= '</h2>';
                $html .= '</div>'; // card-header
                $html .= '<div id="collapse-' . $propertySet_id . '" class="collapse" aria-labelledby="heading-' . $propertySet_id . '" '
                        . 'data-bs-parent="#accordion-' . $propertySet['set_id'] . '">';
                $html .= '<div class="card-body">';
                if (!empty($propertySet['description'])) {
                    $html .= '<h5>' . $globalLang['sys.description'] . '</h5>' . '<p>' . $propertySet['description'] . '</p>';
                }
                $html .= '<h6>' . $globalLang['sys.properties'] . '</h6>';
                if (!count($propertySet['properties'])) {
                    $html .= '---';
                }                
                foreach ($propertySet['properties'] as $property) {                    
                    $propertyId = hash('crc32', $propertySet['set_id'] . $property['property_id'] . $property['value_id']);
                    $html .= '<div class="accordion my-3" id="accordion-' . $propertyId . '">';
                    $html .= '<div class="card">';
                    $html .= '<div class="card-header" id="heading-' . $propertyId . '">';
                    $html .= '<h2 class="mb-0">';
                    $html .= $property['sort'] . ' ' . '<button class="btn btn-link" type="button" data-bs-toggle="collapse" data-propertyId="' . $property['property_id'] . '"'
                            . 'data-bs-target="#collapse-' . $propertyId . '"'
                            . ' aria-expanded="true" aria-controls="collapse-' . $propertyId . '">';
                    $html .= $property['name'] . '<br/>';
                    $html .= '</button></h2></div>'; // card-header                     
                    $html .= '<div id="collapse-' . $propertyId . '" class="collapse" aria-labelledby="heading-'
                            . $propertyId . '" data-bs-parent="#accordion-' . $propertyId . '">';
                    $html .= '<div class="card-body">';
                    $html .= self::renderPropertyHtmlFieldsByAdmin($property, $entityId, $typeEntity);
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
    * Генерирует HTML-код для вкладок (табов) с наборами свойств сущности
    * @param array $entitySetsData Данные о наборах свойств сущности
    * @param int $entityId ID сущности, для которой рендерятся вкладки
    * @param string $typeEntity Тип сущности, по умолчанию 'category'. Используется для фильтрации свойств
    * @param int|null $activeTabIndex ID активного таба, если null, активируется первый доступный
    * @return string Возвращает HTML-код для рендеринга вкладок с наборами свойств
    */
   public static function renderPropertiesSetsTabs($entitySetsData, $entityId, $typeEntity = 'category', $activeTabIndex = null) {       
       $langCode = Session::get('lang');
       $globalLang = Lang::init($langCode);
       $html = '';

       // Начало контейнера для табов
       $html .= '<ul class="nav nav-tabs" id="propertiesTab" role="tablist">';
       $tabContentHtml = '<div class="tab-content" id="propertiesTabContent">';

       $visibleTabsCount = 0; // Счетчик видимых табов
       $targetTabIndex = $activeTabIndex !== null ? (int)$activeTabIndex : 0;
       $hasVisibleTabs = false;

       foreach ($entitySetsData as $entityName => $entitySet) {
           foreach ($entitySet as $propertySet) {
               // Фильтрация свойств по типу сущности
               foreach ($propertySet['properties'] as $kp => $property) {
                   if ($property['property_entity_type'] != $typeEntity && $property['property_entity_type'] != 'all') {
                       unset($propertySet['properties'][$kp]);
                   }
               }
               $showSet = count($propertySet['properties']) > 0;
               if (!$showSet) continue; // Пропускаем скрытые наборы
               $isActive = ($visibleTabsCount === $targetTabIndex);
               $tabNumber = $visibleTabsCount; // Используем порядковый номер
               // Добавляем таб
               $html .= '<li class="nav-item" role="presentation">';
               $html .= '<button class="nav-link' . ($isActive ? ' active' : '') . 
                       '" id="tab-' . $tabNumber . '" data-bs-toggle="tab" data-bs-target="#content-' . $tabNumber . 
                       '" type="button" role="tab" aria-controls="content-' . $tabNumber . 
                       '" aria-selected="' . ($isActive ? 'true' : 'false') . '">';
               $html .= htmlspecialchars($propertySet['name']);
               $html .= '</button>';
               $html .= '</li>';
               // Добавляем контент для таба
               $tabContentHtml .= '<div class="tab-pane fade' . ($isActive ? ' show active' : '') . 
                                '" id="content-' . $tabNumber . '" role="tabpanel" aria-labelledby="tab-' . $tabNumber . '">';
               $tabContentHtml .= '<div class="card-body">';
               if (!empty($propertySet['description'])) {
                   $tabContentHtml .= '<h5>' . htmlspecialchars($globalLang['sys.description']) . '</h5><p>' . htmlspecialchars($propertySet['description']) . '</p>';
               }
               $tabContentHtml .= '<h6>' . htmlspecialchars($globalLang['sys.properties']) . '</h6>';
               if (empty($propertySet['properties'])) {
                   $tabContentHtml .= '---';
               } else {
                   foreach ($propertySet['properties'] as $property) {
                       $propertyId = hash('crc32', $propertySet['set_id'] . $property['property_id'] . $property['value_id']);
                       $tabContentHtml .= '<div class="accordion my-3" id="accordion-' . $propertyId . '">';
                       $tabContentHtml .= '<div class="card">';
                       $tabContentHtml .= '<div class="card-header" id="heading-' . $propertyId . '">';
                       $tabContentHtml .= '<h2 class="mb-0">';
                       $tabContentHtml .= '<button class="btn btn-link" type="button" data-bs-toggle="collapse" data-propertyId="' . $property['property_id'] . '"'
                               . ' data-bs-target="#collapse-' . $propertyId . '" id="button_collapse-' . $propertyId . '"'
                               . ' aria-expanded="true" aria-controls="collapse-' . $propertyId . '">';
                       $tabContentHtml .= htmlspecialchars($property['sort']) . ' ' . htmlspecialchars($property['name']);
                       $tabContentHtml .= '</button></h2></div>';
                       $tabContentHtml .= '<div id="collapse-' . $propertyId . '" class="collapse" aria-labelledby="heading-' . $propertyId . '" data-bs-parent="#accordion-' . $propertyId . '">';
                       $tabContentHtml .= '<div class="card-body">';
                       $tabContentHtml .= self::renderPropertyHtmlFieldsByAdmin($property, $entityId, $typeEntity);
                       $tabContentHtml .= '</div></div></div></div>';
                   }
               }
               $tabContentHtml .= '</div></div>';
               $visibleTabsCount++;
               $hasVisibleTabs = true;
           }
       }

       // Автокоррекция если запрошенный индекс больше количества табов
       if ($hasVisibleTabs && $targetTabIndex >= $visibleTabsCount) {
           $html = preg_replace('/(<button class="nav-link)(.*?)(active)/', '$1$2', $html); // Удаляем все active
           $html = preg_replace('/(<button class="nav-link)/', '$1 active', $html, 1); // Добавляем первому

           $tabContentHtml = preg_replace('/(<div class="tab-pane fade)(.*?)(show active)/', '$1$2', $tabContentHtml); // Удаляем все active
           $tabContentHtml = preg_replace('/(<div class="tab-pane fade)/', '$1 show active', $tabContentHtml, 1); // Добавляем первому
       }

       $html .= '</ul>' . $tabContentHtml;

       return $html;
   }

    /**
     * Вывод свойства у страниц и категорий для сохранения значений
     * @param array $arrValue
     * @param int $entityId ID сущности
     * @param string $entity_type Тип сущности
     */
    public static function renderPropertyHtmlFieldsByAdmin(array $arrValue, int $entityId, string $entity_type): string {
        $result = '';
        $valueId = (string) ($arrValue['value_id'] ?? '');
        $propertyId = (string) ($arrValue['property_id'] ?? '');
        $setId = (string) ($arrValue['set_id'] ?? '');
        $typeFields = $arrValue['fields_type'] ?? ($arrValue['type_fields'] ?? ($arrValue['fields_schema'] ?? ($arrValue['type_fields_json'] ?? [])));
        if ($typeFields === [] && isset($arrValue['fields']) && empty($arrValue['default_values'])) {
            $typeFields = $arrValue['fields'];
        }
        $runtimeFields = PropertyFieldContract::buildRuntimeFields(
            $arrValue['default_values'] ?? [],
            $arrValue['fields'] ?? [],
            $typeFields,
            $arrValue
        );

        foreach ($runtimeFields as $key => $value) {
            $type = strtolower(trim((string) ($value['type'] ?? 'text')));
            if ($type === '') {
                $type = 'text';
            }
            $valueName = $valueId . '_' . $key . '_' . $type . '_' . $entityId . '_' . $entity_type . '_' . $propertyId . '_' . $setId;
            $result .= '<div class="row rounded-2 p-2 col-12 col-sm-12 mt-2 align-items-start property_content border">';
            $result .= '<input type="hidden" name="property_data_changed" value="0" />';
            $result .= '<input type="hidden" name="property_data[' . $valueName . '_type]" value="' . htmlspecialchars($type, ENT_QUOTES) . '"/>';
            $result .= '<input type="hidden" name="property_data[' . $valueName . '_uid]" value="' . htmlspecialchars((string) ($value['uid'] ?? ('legacy_' . $key)), ENT_QUOTES) . '"/>';
            $result .= '<div class="col pt-3">';
            $result .= '<label class="form-label fw-bold">' . htmlspecialchars((string) ($value['label'] ?? ($arrValue['name'] ?? '')), ENT_QUOTES) . '</label>';
            if (!empty($value['title'])) {
                $result .= '<div class="text-muted small">' . htmlspecialchars((string) $value['title'], ENT_QUOTES) . '</div>';
            }
            $result .= '</div>';
            $result .= self::renderFieldsProperty($value, $type, $valueName, 'value');
        }
        return $result;
    }

    /**
     * Генерирует поля свойств
     * @param type $value
     * @param type $type
     * @param type $valueName
     * @return string
     */
    private static function renderFieldsProperty($value, $type, $valueName, $area) {
        $langCode = Session::get('lang');
        $globalLang = Lang::init($langCode);
        $result = '';
        $isDefinitionArea = $area === 'default';
        $multipleChoice = true;
        $multiple = !empty($value['multiple']) ? true : false;
        $required = !empty($value['required']) ? ' required ' : '';
        $nameSuffix = $multiple ? '[]' : '';
        $result .= '<div class="col pt-3">';
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
                $itype = match ($type) {
                    'phone' => 'tel',
                    'hidden', 'password' => 'text',
                    default => $type,
                };
                $scalarValue = $value['value'] ?? ($value['default'] ?? '');
                if ($multiple && is_array($scalarValue)) {
                    foreach ($scalarValue as $valueItem) {
                        $result .= '<div class="field_container d-flex align-items-center">';
                        $result .= '<input type="' . $itype . '" class="form-control"' . $required
                                . 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . $type . '" '
                                . 'name="property_data[' . $valueName . '_' . $area . ']' . $nameSuffix . '" value="' . htmlentities($valueItem) . '" />';
                        $result .= '</div>';
                    }
                } else {
                    $result .= '<input type="' . $itype . '"  class="form-control"' . $required
                            . 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . $type . '" '
                            . 'name="property_data[' . $valueName . '_' . $area . ']' . $nameSuffix . '" value="' . htmlentities(is_array($scalarValue) ? (string) reset($scalarValue) : (string) $scalarValue) . '" />';
                }
                break;
            case 'select':
            case 'checkbox':
            case 'radio':
                if ($isDefinitionArea) {
                    $result .= self::renderChoiceDefinitionEditor($value, $valueName, $type, $globalLang);
                } else {
                    $result .= self::renderChoiceValueInput($value, $valueName, $type, $required);
                }
                if ($type === 'checkbox' || $type === 'radio') {
                    $multipleChoice = false;
                }
                break;
            case 'textarea':
                $textareaValue = $value['value'] ?? ($value['default'] ?? '');
                if ($multiple && is_array($textareaValue)) {
                    foreach ($textareaValue as $valueItem) {
                        $result .= '<div class="field_container d-flex align-items-center">';
                        $result .= '<textarea class="form-control" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $type . '"' . $required
                                . 'name="property_data[' . $valueName . '_' . $area . ']' . $nameSuffix . '">' . htmlentities($valueItem) . '</textarea>';
                        $result .= '</div>';
                    }
                } else {
                    $result .= '<textarea class="form-control" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $type . '"' . $required
                            . 'name="property_data[' . $valueName . '_' . $area . ']' . $nameSuffix . '">' . htmlentities(is_array($textareaValue) ? (string) reset($textareaValue) : (string) $textareaValue) . '</textarea>';
                }
                break;
            case 'file':
            case 'image':
                $allowed_extensions = FileSystem::getAllowedExtensionsStringForFieldType($type);
                $preloaded_files = FileSystem::normalizeFileReferences($value['value'] ?? ($value['default'] ?? []));
                $result .= self::ee_uploader([
                            'name' => 'property_data[' . $valueName . '_' . $area . ']',
                            'allowed_extensions' => $allowed_extensions,
                            'multiple' => $multiple,
                            'required' => $value['required'],
                            'preloaded_files' => $preloaded_files
                ]);                   
                break;
            default:
                $result .= '<span class="text-danger">Unsupported field type: ' . $type . '</span>';
        }
        $result .= '</div>';
        if ($isDefinitionArea) {
            $result .= '<div class="col pt-3">';
            $result .= '<label>' . $globalLang['sys.required'] . '</label><input type="checkbox"'
                    . ' class="" name="property_data[' . $valueName . '_required]"'
                    . ($value['required'] ? ' checked' : '') . '/>';
            $result .= '</div>';
            if ($multipleChoice) {
                $result .= '<div class="col multicheck pt-3">';
                $result .= '<label class="form-label">' . $globalLang['sys.multiple_choice'] . '</label><input type="checkbox"'
                        . ' class="" name="property_data[' . $valueName . '_multiple]"'
                        . ($multiple ? ' checked' : '') . '/>';
                $result .= '</div>';
            }
        }
        $result .= '</div>';
        return $result;
    }

    private static function normalizeTypeFieldDefinitions(mixed $fields): array {
        return PropertyFieldContract::normalizeTypeFields($fields);
    }

    private static function renderChoiceDefinitionEditor(array $value, string $valueName, string $type, array $globalLang): string {
        $options = self::normalizeChoiceOptionsForRender($value);
        $selectedKeys = array_flip(self::normalizeChoiceSelectedKeys($value['value'] ?? ($value['default'] ?? [])));
        $selectorType = $type === 'radio' ? 'radio' : 'checkbox';
        $buttonSuffix = $type === 'select' ? 'select' : $type;
        $html = '<div class="choice-definition-editor">';

        if (empty($options)) {
            $options[] = ['key' => '', 'label' => '', 'disabled' => 0, 'sort' => 10];
        }

        foreach ($options as $index => $option) {
            $rowAction = $index === 0 ? 'plus' : 'minus';
            $html .= '<div class="choice-option-container d-flex align-items-center gap-2 mb-2">';
            $html .= '<input type="text" required class="form-control option-label-input" name="property_data[' . $valueName . '_option_label][]" placeholder="' . htmlspecialchars((string) $globalLang['sys.title'], ENT_QUOTES) . '" value="' . htmlspecialchars((string) ($option['label'] ?? ''), ENT_QUOTES) . '"/>';
            $html .= '<input type="text" class="form-control option-key-input" name="property_data[' . $valueName . '_option_key][]" placeholder="' . htmlspecialchars((string) $globalLang['sys.value'], ENT_QUOTES) . '" value="' . htmlspecialchars((string) ($option['key'] ?? ''), ENT_QUOTES) . '"/>';
            $html .= '<div class="form-check mb-0">';
            $html .= '<input class="form-check-input choice-option-selected" type="' . $selectorType . '" value="' . $index . '" name="property_data[' . $valueName . '_option_selected][]" ' . (isset($selectedKeys[(string) ($option['key'] ?? '')]) ? 'checked ' : '') . '/>';
            $html .= '<label class="form-check-label">' . htmlspecialchars((string) $globalLang['sys.default'], ENT_QUOTES) . '</label>';
            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-primary choice-option-toggle" data-selector-type="' . $selectorType . '" data-general-name="' . $valueName . '" id="' . $index . '_' . $valueName . '_add_' . $buttonSuffix . '_values"><i class="fa fa-' . ($rowAction === 'plus' ? 'plus' : 'minus') . '"></i></button>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private static function renderChoiceValueInput(array $value, string $valueName, string $type, string $required): string {
        $options = self::normalizeChoiceOptionsForRender($value);
        $selectedKeys = array_flip(self::normalizeChoiceSelectedKeys($value['value'] ?? []));
        $html = '';

        if ($type === 'select') {
            $name = 'property_data[' . $valueName . '_value][]';
            $html .= '<select class="form-select" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($type, ENT_QUOTES) . '" name="' . $name . '"' . ($value['multiple'] ? ' multiple' : '') . $required . '>';
            foreach ($options as $option) {
                $key = (string) ($option['key'] ?? '');
                if ($key === '' || !empty($option['disabled'])) {
                    continue;
                }
                $html .= '<option value="' . htmlspecialchars($key, ENT_QUOTES) . '"' . (isset($selectedKeys[$key]) ? ' selected' : '') . '>' . htmlspecialchars((string) ($option['label'] ?? $key), ENT_QUOTES) . '</option>';
            }
            $html .= '</select>';
            return $html;
        }

        $inputType = $type === 'radio' ? 'radio' : 'checkbox';
        $inputName = 'property_data[' . $valueName . '_value]' . ($type === 'checkbox' ? '[]' : '');
        foreach ($options as $option) {
            $key = (string) ($option['key'] ?? '');
            if ($key === '' || !empty($option['disabled'])) {
                continue;
            }
            $checked = isset($selectedKeys[$key]) ? ' checked' : '';
            $itemRequired = $type === 'checkbox' ? '' : $required;
            $html .= '<div class="form-check mb-2">';
            $html .= '<input class="form-check-input" type="' . $inputType . '" name="' . $inputName . '" value="' . htmlspecialchars($key, ENT_QUOTES) . '"' . $checked . $itemRequired . '>';
            $html .= '<label class="form-check-label">' . htmlspecialchars((string) ($option['label'] ?? $key), ENT_QUOTES) . '</label>';
            $html .= '</div>';
        }
        return $html;
    }

    private static function normalizeChoiceOptionsForRender(array $value): array {
        $options = $value['options'] ?? [];
        if (!is_array($options)) {
            return [];
        }
        $normalized = [];
        foreach (array_values($options) as $index => $option) {
            if (!is_array($option)) {
                continue;
            }
            $normalized[] = [
                'key' => (string) ($option['key'] ?? ''),
                'label' => (string) ($option['label'] ?? ''),
                'sort' => isset($option['sort']) ? (int) $option['sort'] : (($index + 1) * 10),
                'disabled' => !empty($option['disabled']) ? 1 : 0,
            ];
        }
        usort($normalized, static fn(array $left, array $right): int => ($left['sort'] ?? 0) <=> ($right['sort'] ?? 0));
        return $normalized;
    }

    private static function normalizeChoiceSelectedKeys(mixed $payload): array {
        if (is_string($payload)) {
            $payload = trim($payload);
            if ($payload === '') {
                return [];
            }
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            } elseif (str_contains($payload, ',')) {
                $payload = explode(',', $payload);
            } else {
                $payload = [$payload];
            }
        } elseif (!is_array($payload)) {
            $payload = [$payload];
        }

        $selected = [];
        foreach ($payload as $item) {
            if (is_array($item) && isset($item['key'])) {
                $item = $item['key'];
            }
            $item = trim((string) $item);
            if ($item !== '') {
                $selected[$item] = $item;
            }
        }
        return array_values($selected);
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
        $langCode = Session::get('lang');
        $globalLang = Lang::init($langCode);
        $fileUnavailableText = (string) ($globalLang['sys.file_unavailable'] ?? 'Файл недоступен');
        $allowedFilesLabel = (string) ($globalLang['sys.allowed_files'] ?? 'Допустимые файлы');
        $uploadLabel = (string) ($globalLang['sys.download'] ?? 'Upload');
        $pendingDeleteLabel = (string) ($globalLang['sys.file_marked_for_delete'] ?? 'Файл помечен на удаление');
        $undoDeleteLabel = (string) ($globalLang['sys.undo_delete'] ?? 'Отменить удаление');
        $externalReferenceLabel = (string) ($globalLang['sys.external_reference'] ?? 'Внешняя ссылка');
        $legacyReferenceLabel = (string) ($globalLang['sys.legacy_reference'] ?? 'Устаревшая ссылка');
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
        $params['preloaded_files'] = FileSystem::normalizeFileReferences($params['preloaded_files']);
        // Обработка allowed_extensions для атрибута accept
        $allowedExtensionsArray = array_values(array_filter(array_map('trim', explode(',', (string) $params['allowed_extensions']))));
        if (!empty($allowedExtensionsArray[0])) {
            $acceptAttribute = 'accept=".' . implode(',.', $allowedExtensionsArray) . '"';
        } else {
            $acceptAttribute = '';
        }
        $allowedExtensionsHint = !empty($allowedExtensionsArray)
            ? $allowedFilesLabel . ': ' . implode(', ', $allowedExtensionsArray)
            : '';
        // Атрибуты multiple и required
        $multipleAttribute = $params['multiple'] ? 'multiple' : '';
        $requiredAttribute = $params['required'] ? 'data-required="1"' : '';
        if (!self::$sortableJS) {
            $html = '<link rel="stylesheet" href="' . ENV_URL_SITE . '/assets/js/plugins/ee_uploader.css" type="text/css" />';
            $html .= '<script src="' . ENV_URL_SITE . '/assets/js/plugins/Sortable.min.js" type="text/javascript"></script>';
            $html .= '<script src="' . ENV_URL_SITE . '/assets/js/plugins/ee_uploader.js" type="text/javascript"></script>';
            self::$sortableJS = true;
        } else {
            $html = '';
        }
        $html .= '<div class="card ee-uploader-card" id="upload-content_' . $params['id'] . '" data-layout="' . $params['layout'] . '">';
        if (count($params['preloaded_files'])) { // Есть предзагруженные файлы, выводим их отдельно, учитывая сортировку
            $html .= '<div class="preloadedFilesData" id="preloaded_' . $params['id'] . '">';
            $preloadCount = 0;
            $matches = [];
            preg_match('/\[(.*?)\]/', $params['name'], $matches);
            $propertyName = isset($matches[1]) ? $matches[1] : $params['name'];
            foreach ($params['preloaded_files'] as $reference) {
                $preloadCount++;
                $fileReference = FileSystem::describeFileReference($reference);
                if (!$fileReference) {
                    continue;
                }

                $payload = [
                    'unique_id' => $fileReference['unique_id'],
                    'property_name' => $propertyName,
                    'original_name' => $fileReference['original_name'] ?: $fileUnavailableText,
                ];
                if (!empty($fileReference['is_legacy'])) {
                    $payload['legacy_value'] = $fileReference['reference'];
                    $payload['is_legacy'] = true;
                } else {
                    $payload['file_id'] = $fileReference['file_id'];
                    $payload['file_path'] = $fileReference['file_path'];
                    $payload['file_url'] = $fileReference['file_url'];
                    $payload['mime_type'] = $fileReference['mime_type'];
                }

                $html .= '<div class="fileItem' . (!empty($fileReference['is_legacy']) ? ' legacy-file' : '') . '" data-unique-id="' . htmlspecialchars((string) $fileReference['unique_id'], ENT_QUOTES) . '">';
                $html .= '<input type="hidden" name="ee_dataFiles[]" value="' . htmlentities(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '">';
                $html .= '<div class="fileName mb-2">' . htmlspecialchars((string) ($fileReference['original_name'] ?: $fileUnavailableText), ENT_QUOTES) . '</div>';
                if (!empty($fileReference['is_image']) && !empty($fileReference['file_url'])) {
                    $html .= '<img src="' . htmlspecialchars((string) $fileReference['file_url'], ENT_QUOTES) . '" />';
                } else {
                    $extension = pathinfo((string) ($fileReference['file_path'] ?: $fileReference['reference']), PATHINFO_EXTENSION);
                    $html .= FileSystem::getFileIcon($extension);
                }
                if (!empty($fileReference['is_legacy'])) {
                    $html .= '<div class="small text-muted mb-2">' . htmlspecialchars((string) ($fileReference['kind'] === 'external' ? $externalReferenceLabel : $legacyReferenceLabel), ENT_QUOTES) . '</div>';
                }
                $html .= '<div class="actionIcons" role="button"><i class="fas fa-trash actionIcon deleteIcon"></i>';
                if (!empty($fileReference['allow_edit'])) {
                    $html .= '<i class="fas fa-edit actionIcon editIcon"></i>';
                }
                if (!empty($fileReference['allow_transform'])) {
                    $html .= '<i class="fas fa-rotate-right actionIcon rotateIcon"></i><i class="fas fa-arrows-left-right actionIcon flipHIcon"></i><i class="fas fa-arrows-up-down actionIcon flipVIcon"></i>';
                }
                $html .= '</div>';
                $html .= '</div>';
            }
            if ($preloadCount > 1 && empty($multipleAttribute)) {
                $message = 'Поле ' . $params['name'] . ' непомечено как множественное, удалите лишние файлы!';
                Logger::warning('uploader', $message, $params, [
                    'initiator' => 'ee_uploader',
                    'details' => $message,
                    'include_trace' => false,
                ]);
                \classes\helpers\ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
            }            
            $html .= '</div>';
        }
        // general input
        $html .= '<input type="file" class="ee_fileInput" name="' . $params['name'] . '[]" id="' . $params['id'] . '" '
                . 'data-ee_uploader="true" data-allowed-extensions="'
                . $params['allowed_extensions'] . '" data-upload-label="' . htmlspecialchars($uploadLabel, ENT_QUOTES) . '"'
                . ' data-pending-delete-label="' . htmlspecialchars($pendingDeleteLabel, ENT_QUOTES) . '"'
                . ' data-undo-delete-label="' . htmlspecialchars($undoDeleteLabel, ENT_QUOTES) . '" '
                . $acceptAttribute . ' ' . $multipleAttribute . ' ' . $requiredAttribute . ' />'
                . '<input type="hidden" name="ee_check_file[]" value="0">';
        if ($allowedExtensionsHint !== '') {
            $html .= '<div class="ee-uploader-help small text-muted mt-2">' . htmlspecialchars($allowedExtensionsHint, ENT_QUOTES) . '</div>';
        }
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
     * @param int|null $selectedTypeId ID выбранного типа. Этот тип будет отмечен как выбранный в выпадающем списке
     * @param int $parentTypeId ID родительского типа для текущего уровня иерархии. По умолчанию равен 0, что соответствует корневому уровню
     * @param int $level Текущий уровень иерархии. Используется для добавления отступов дочерним элементам. По умолчанию равен 0 для корневого уровня
     * @return string Строка с HTML кодом опций для элемента <select>
     */
    public static function showTypeCategogyForSelect($types, $selectedTypeId = null, $parentTypeId = null, $level = 0) {
        $html = '';
        foreach ($types as $type) {
            // Если parentTypeId не указан или совпадает с parent_type_id, добавляем элемент
            if ($parentTypeId === null || $type['parent_type_id'] == $parentTypeId) {
                $indent = str_repeat("&nbsp;&nbsp;&nbsp;", $level);
                $symbol = $level > 0 ? '↳ ' : '';
                $selected = $selectedTypeId == $type['type_id'] ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars($type['type_id']) . '"' . $selected . '>' . $indent . $symbol . htmlspecialchars($type['name']) . '</option>';
            }
            // Рекурсивный вызов для дочерних элементов, если они есть
            if (isset($type['children']) && is_array($type['children'])) {
                $html .= self::showTypeCategogyForSelect($type['children'], $selectedTypeId, $type['type_id'], $level + 1);
            }
        }
        return $html;
    }

    /**
     * Генерация HTML кода для наборов свойств
     * @param array $propertySetsData Данные для наборов свойств
     * @param array $categoriesTypeSetsData Данные для типов наборов категорий
     * @return string Сгенерированный HTML код
     */
    public static function renderPropertySets($propertySetsData, $categoriesTypeSetsData, $directCategoriesTypeSetsData = []) {
        $langCode = Session::get('lang');
        $globalLang = Lang::init($langCode);
        $html = '';
        $directCategoriesTypeSetsData = array_values(array_unique(array_map('intval', (array) $directCategoriesTypeSetsData)));
        $categoriesTypeSetsData = array_values(array_unique(array_map('intval', (array) $categoriesTypeSetsData)));
        if (count($directCategoriesTypeSetsData)) {
            foreach ($directCategoriesTypeSetsData as $item_ctsd) {
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
            if (!empty($propertySet['description'])) {
                $html .= '<h5>' . $globalLang['sys.description'] . '</h5>' . '<p>' . $propertySet['description'] . '</p>';
            }
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
