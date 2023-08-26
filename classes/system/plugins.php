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
	* Вернёт таблицу с пагинацией
	*/
	public static function create_table($id_table = 'test', $data_table = [], $page = 0, $cout_row = 10) {
		$html = '<table class="table">';
		// Заголовок таблицы
		$html .= '<thead>';
		foreach ($data['columns'] as $column) {
			$html .= '<th title="' . ($column['comment'] ?? '') . '">' . $column['title'] . '</th>';
		}
		$html .= '</thead>';
		// Тело таблицы
		$html .= '<tbody>';
		foreach ($data['rows'] as $row) {
			$html .= '<tr>';
			foreach ($data['columns'] as $column) {
				$html .= '<td title="' . ($row['comment'] ?? '') . '">' . $row[$column['field']] . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody>';
		$html .= '</table>';
		return $html;
	}
	
 }