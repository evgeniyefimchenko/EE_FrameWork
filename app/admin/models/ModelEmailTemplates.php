<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;
use classes\system\ErrorLogger;

/**
 * Class ModelEmailTemplates
 * Модель для работы с почтовыми шаблонами и сниппетами
 */
class ModelEmailTemplates {

    /**
     * Получает почтовые шаблоны с возможностью фильтрации, сортировки и пагинации
     * @param string $order Строка, определяющая порядок сортировки (по умолчанию: 'template_id ASC')
     * @param string|null $where Условие для фильтрации (по умолчанию: NULL)
     * @param int $start Индекс начальной записи для пагинации (по умолчанию: 0)
     * @param int $limit Количество записей для извлечения (по умолчанию: 100)
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return array Массив с данными шаблонов и общим количеством
     */
    public function getEmailTemplates(string $order = 'template_id ASC', ?string $where = NULL, int $start = 0, int $limit = 100, string $languageCode = ENV_DEF_LANG): array {
        $orderString = $order ?: 'template_id ASC';
        $start = $start ?: 0;
        $params = [Constants::EMAIL_TEMPLATES_TABLE, $languageCode, $start, $limit];
        $whereString = $where ? "$where AND language_code = ?s" : "language_code = ?s";
        $sqlTemplates = "SELECT template_id FROM ?n WHERE $whereString ORDER BY $orderString LIMIT ?i, ?i";
        $resArray = SafeMySQL::gi()->getAll($sqlTemplates, ...$params);
        $res = [];
        foreach ($resArray as $template) {
            $res[] = $this->getEmailTemplateData($template['template_id']);
        }
        $sqlCount = "SELECT COUNT(*) as total_count FROM ?n WHERE $whereString";
        $totalCount = SafeMySQL::gi()->getOne($sqlCount, ...array_slice($params, 0, 2));
        return [
            'data' => $res,
            'total_count' => $totalCount
        ];
    }

    /**
     * Получает данные почтового шаблона по его ID
     * @param int $templateId ID шаблона
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return array|null Массив с данными шаблона или NULL, если шаблон не найден
     */
    public function getEmailTemplateData(int $templateId, string $languageCode = ENV_DEF_LANG): ?array {
        $sqlTemplate = "SELECT * FROM ?n WHERE template_id = ?i AND language_code = ?s";
        return SafeMySQL::gi()->getRow($sqlTemplate, Constants::EMAIL_TEMPLATES_TABLE, $templateId, $languageCode) ?: null;
    }

    /**
     * Получает данные почтового шаблона по его имени
     * @param int $name ID шаблона
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return array|null Массив с данными шаблона или NULL, если шаблон не найден
     */
    public function getEmailTemplateDataByName(string $name, string $languageCode = ENV_DEF_LANG): ?array {
        $sqlTemplate = "SELECT * FROM ?n WHERE name = ?s AND language_code = ?s";
        return SafeMySQL::gi()->getRow($sqlTemplate, Constants::EMAIL_TEMPLATES_TABLE, $name, $languageCode) ?: null;
    }

    /**
     * Получает все сниппеты с возможностью фильтрации, сортировки и пагинации
     * @param string $order Строка, определяющая порядок сортировки (по умолчанию: 'snippet_id ASC')
     * @param string|null $where Условие для фильтрации (по умолчанию: NULL)
     * @param int $start Индекс начальной записи для пагинации (по умолчанию: 0)
     * @param int $limit Количество записей для извлечения (по умолчанию: 100)
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return array Массив с данными сниппетов и общим количеством
     */
    public function getEmailSnippets(string $order = 'snippet_id ASC', ?string $where = NULL, int $start = 0, int $limit = 100, string $languageCode = ENV_DEF_LANG): array {
        $orderString = $order ?: 'snippet_id ASC';
        $start = $start ?: 0;
        $params = [Constants::EMAIL_SNIPPETS_TABLE, $languageCode, $start, $limit];
        $whereString = $where ? "$where AND language_code = ?s" : "language_code = ?s";
        $sqlSnippets = "SELECT snippet_id FROM ?n WHERE $whereString ORDER BY $orderString LIMIT ?i, ?i";
        $resArray = SafeMySQL::gi()->getAll($sqlSnippets, ...$params);
        $res = [];
        foreach ($resArray as $snippet) {
            $res[] = $this->getEmailSnippetData($snippet['snippet_id']);
        }
        $sqlCount = "SELECT COUNT(*) as total_count FROM ?n WHERE $whereString";
        $totalCount = SafeMySQL::gi()->getOne($sqlCount, ...array_slice($params, 0, 2));
        return [
            'data' => $res,
            'total_count' => $totalCount
        ];
    }

    /**
     * Получает данные сниппета по его ID
     * @param int $snippetId ID сниппета
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return array|null Массив с данными сниппета или NULL, если сниппет не найден
     */
    public function getEmailSnippetData(int $snippetId, string $languageCode = ENV_DEF_LANG): ?array {
        $sql_snippet = "SELECT * FROM ?n WHERE snippet_id = ?i AND language_code = ?s";
        return SafeMySQL::gi()->getRow($sql_snippet, Constants::EMAIL_SNIPPETS_TABLE, $snippetId, $languageCode) ?: null;
    }

    /**
     * Получает данные сниппета по его name
     * @param string $name ID сниппета
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return array|null Массив с данными сниппета или NULL, если сниппет не найден
     */
    public function getEmailSnippetDataByName(string $name, string $languageCode = ENV_DEF_LANG): ?array {
        $sql_snippet = "SELECT * FROM ?n WHERE name = ?i AND language_code = ?s";
        return SafeMySQL::gi()->getRow($sql_snippet, Constants::EMAIL_SNIPPETS_TABLE, $name, $languageCode) ?: null;
    }

    /**
     * Обновляет или создает шаблон
     * @param array $templateData Ассоциативный массив с данными шаблона
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return int|false ID обновленной или созданной записи в случае успеха, иначе false
     */
    public function updateEmailTemplateData(array $templateData, string $languageCode = ENV_DEF_LANG): int|false {
        $templateData = SafeMySQL::gi()->filterArray($templateData, SysClass::ee_getFieldsTable(Constants::EMAIL_TEMPLATES_TABLE));
        $templateData = array_map(fn($value) => is_string($value) ? trim($value) : $value, $templateData);
        $templateData['language_code'] = $languageCode;
        if (isset($templateData['template_id']) && $templateData['template_id'] > 0) {
            $templateId = $templateData['template_id'];
            unset($templateData['template_id']);
            $result = SafeMySQL::gi()->query("UPDATE ?n SET ?u WHERE template_id = ?i", Constants::EMAIL_TEMPLATES_TABLE, $templateData, $templateId);
            return $result ? $templateId : false;
        }
        $result = SafeMySQL::gi()->query("INSERT INTO ?n SET ?u", Constants::EMAIL_TEMPLATES_TABLE, $templateData);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Обновляет или создает сниппет
     * @param array $snippetData Ассоциативный массив с данными сниппета
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return int|false ID обновленной или созданной записи в случае успеха, иначе false
     */
    public function updateEmailSnippetData(array $snippetData, string $languageCode = ENV_DEF_LANG): int|false {
        $snippetData = SafeMySQL::gi()->filterArray($snippetData, SysClass::ee_getFieldsTable(Constants::EMAIL_SNIPPETS_TABLE));
        $snippetData = array_map(fn($value) => is_string($value) ? trim($value) : $value, $snippetData);
        $snippetData['language_code'] = $languageCode;
        if (isset($snippetData['snippet_id']) && $snippetData['snippet_id'] > 0) {
            $snippetId = $snippetData['snippet_id'];
            unset($snippetData['snippet_id']);
            $result = SafeMySQL::gi()->query("UPDATE ?n SET ?u WHERE snippet_id = ?i", Constants::EMAIL_SNIPPETS_TABLE, $snippetData, $snippetId);
            return $result ? $snippetId : false;
        }
        $result = SafeMySQL::gi()->query("INSERT INTO ?n SET ?u", Constants::EMAIL_SNIPPETS_TABLE, $snippetData);
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удаляет почтовый шаблон
     * @param int $templateId ID шаблона
     * @return bool
     */
    public function deleteEmailTemplate(int $templateId): bool {
        $result = SafeMySQL::gi()->query("DELETE FROM ?n WHERE template_id = ?i", Constants::EMAIL_TEMPLATES_TABLE, $templateId);
        return (bool) $result;
    }

    /**
     * Удаляет сниппет
     * Перед удалением проверяет, используется ли сниппет в каких-либо шаблонах.
     * Если сниппет используется, записывает информацию в лог и возвращает false
     * @param int $snippetId ID сниппета
     * @return bool
     */
    public function deleteEmailSnippet(int $snippetId): bool {
        $sql = "SELECT template_id FROM ?n WHERE body LIKE ?s";
        $templatesUsingSnippet = SafeMySQL::gi()->getAll($sql, Constants::EMAIL_TEMPLATES_TABLE, '%{{' . $this->getSnippetNameById($snippetId) . '}}%');
        if (!empty($templatesUsingSnippet)) {
            $templateIds = array_column($templatesUsingSnippet, 'template_id');
            $message = "Невозможно удалить сниппет ID $snippetId, так как он используется в шаблонах: " . implode(', ', $templateIds);
            new ErrorLogger($message, __FUNCTION__, 'email_snippet', ['snippet_id' => $snippetId, 'template_ids' => $templateIds]);
            return false;
        }
        $result = SafeMySQL::gi()->query("DELETE FROM ?n WHERE snippet_id = ?i", Constants::EMAIL_SNIPPETS_TABLE, $snippetId);
        return (bool) $result;
    }

    /**
     * Получает имя сниппета по его ID
     * @param int $snippetId ID сниппета
     * @param string $languageCode Код языка по стандарту ISO 3166-2
     * @return string|null Имя сниппета или NULL, если сниппет не найден
     */
    private function getSnippetNameById(int $snippetId, string $languageCode = ENV_DEF_LANG): ?string {
        $sql = "SELECT name FROM ?n WHERE snippet_id = ?i AND language_code = ?s";
        return SafeMySQL::gi()->getOne($sql, Constants::EMAIL_SNIPPETS_TABLE, $snippetId, $languageCode) ?: null;
    }

    /**
     * Заменяет сниппеты в тексте шаблона
     * @param string $templateBody Тело шаблона, в котором нужно заменить сниппеты
     * @param string $languageCode Код языка по стандарту ISO 3166-2
     * @return string
     */
    public function replaceSnippets(string $templateBody, string $languageCode = ENV_DEF_LANG): string {
        preg_match_all('/\{\{([^}]+)\}\}/', $templateBody, $matches);
        if (isset($matches[1]) && count($matches[1]) > 0) {
            foreach ($matches[1] as $snippetName) {
                $sql = "SELECT content FROM ?n WHERE name = ?s AND language_code = ?s";
                $snippetContent = SafeMySQL::gi()->getOne($sql, Constants::EMAIL_SNIPPETS_TABLE, $snippetName, $languageCode);
                if ($snippetContent) {
                    $templateBody = str_replace('{{' . $snippetName . '}}', $snippetContent, $templateBody);
                }
            }
        }
        return $templateBody;
    }
}
