<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;
use classes\system\Logger;
use classes\system\OperationResult;

/**
 * Class ModelEmailTemplates
 * Модель для работы с почтовыми шаблонами и сниппетами
 */
class ModelEmailTemplates {

    private function getLanguageFallbacks(string $languageCode = ENV_DEF_LANG): array {
        $candidates = [
            strtoupper(trim($languageCode)),
            defined('ENV_PROTO_LANGUAGE') ? strtoupper(trim((string) ENV_PROTO_LANGUAGE)) : '',
            'RU',
            'EN',
        ];

        $fallbacks = [];
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && !in_array($candidate, $fallbacks, true)) {
                $fallbacks[] = $candidate;
            }
        }

        return $fallbacks;
    }

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
        foreach ($this->getLanguageFallbacks($languageCode) as $fallbackLanguage) {
            $row = SafeMySQL::gi()->getRow($sqlTemplate, Constants::EMAIL_TEMPLATES_TABLE, $templateId, $fallbackLanguage);
            if (!empty($row)) {
                return $row;
            }
        }

        return SafeMySQL::gi()->getRow(
            "SELECT * FROM ?n WHERE template_id = ?i LIMIT 1",
            Constants::EMAIL_TEMPLATES_TABLE,
            $templateId
        ) ?: null;
    }

    /**
     * Получает данные почтового шаблона по его имени
     * @param int $name ID шаблона
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return array|null Массив с данными шаблона или NULL, если шаблон не найден
     */
    public function getEmailTemplateDataByName(string $name, string $languageCode = ENV_DEF_LANG): ?array {
        $sqlTemplate = "SELECT * FROM ?n WHERE name = ?s AND language_code = ?s";
        foreach ($this->getLanguageFallbacks($languageCode) as $fallbackLanguage) {
            $row = SafeMySQL::gi()->getRow($sqlTemplate, Constants::EMAIL_TEMPLATES_TABLE, $name, $fallbackLanguage);
            if (!empty($row)) {
                return $row;
            }
        }

        return SafeMySQL::gi()->getRow(
            "SELECT * FROM ?n WHERE name = ?s LIMIT 1",
            Constants::EMAIL_TEMPLATES_TABLE,
            $name
        ) ?: null;
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
        foreach ($this->getLanguageFallbacks($languageCode) as $fallbackLanguage) {
            $row = SafeMySQL::gi()->getRow($sql_snippet, Constants::EMAIL_SNIPPETS_TABLE, $snippetId, $fallbackLanguage);
            if (!empty($row)) {
                return $row;
            }
        }

        return SafeMySQL::gi()->getRow(
            "SELECT * FROM ?n WHERE snippet_id = ?i LIMIT 1",
            Constants::EMAIL_SNIPPETS_TABLE,
            $snippetId
        ) ?: null;
    }

    /**
     * Получает данные сниппета по его name
     * @param string $name ID сниппета
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return array|null Массив с данными сниппета или NULL, если сниппет не найден
     */
    public function getEmailSnippetDataByName(string $name, string $languageCode = ENV_DEF_LANG): ?array {
        $sql_snippet = "SELECT * FROM ?n WHERE name = ?s AND language_code = ?s";
        foreach ($this->getLanguageFallbacks($languageCode) as $fallbackLanguage) {
            $row = SafeMySQL::gi()->getRow($sql_snippet, Constants::EMAIL_SNIPPETS_TABLE, $name, $fallbackLanguage);
            if (!empty($row)) {
                return $row;
            }
        }

        return SafeMySQL::gi()->getRow(
            "SELECT * FROM ?n WHERE name = ?s LIMIT 1",
            Constants::EMAIL_SNIPPETS_TABLE,
            $name
        ) ?: null;
    }

    /**
     * Обновляет или создает шаблон
     * @param array $templateData Ассоциативный массив с данными шаблона
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return OperationResult Результат сохранения шаблона
     */
    public function updateEmailTemplateData(array $templateData, string $languageCode = ENV_DEF_LANG): OperationResult {
        $templateData = SafeMySQL::gi()->filterArray($templateData, SysClass::ee_getFieldsTable(Constants::EMAIL_TEMPLATES_TABLE));
        $templateData = array_map(fn($value) => is_string($value) ? trim($value) : $value, $templateData);
        $templateData['language_code'] = $languageCode;
        if (empty($templateData['name'])) {
            return OperationResult::validation('Не указано имя шаблона письма', $templateData);
        }
        if (isset($templateData['template_id']) && $templateData['template_id'] > 0) {
            $templateId = $templateData['template_id'];
            unset($templateData['template_id']);
            $result = SafeMySQL::gi()->query("UPDATE ?n SET ?u WHERE template_id = ?i", Constants::EMAIL_TEMPLATES_TABLE, $templateData, $templateId);
            return $result
                ? OperationResult::success((int) $templateId, '', 'updated')
                : OperationResult::failure('Ошибка обновления почтового шаблона', 'email_template_update_error', ['template_data' => $templateData]);
        }
        $result = SafeMySQL::gi()->query("INSERT INTO ?n SET ?u", Constants::EMAIL_TEMPLATES_TABLE, $templateData);
        return $result
            ? OperationResult::success((int) SafeMySQL::gi()->insertId(), '', 'created')
            : OperationResult::failure('Ошибка создания почтового шаблона', 'email_template_insert_error', ['template_data' => $templateData]);
    }

    /**
     * Обновляет или создает сниппет
     * @param array $snippetData Ассоциативный массив с данными сниппета
     * @param string $languageCode Код языка по стандарту ISO 3166-2 (по умолчанию: ENV_DEF_LANG)
     * @return OperationResult Результат сохранения сниппета
     */
    public function updateEmailSnippetData(array $snippetData, string $languageCode = ENV_DEF_LANG): OperationResult {
        $snippetData = SafeMySQL::gi()->filterArray($snippetData, SysClass::ee_getFieldsTable(Constants::EMAIL_SNIPPETS_TABLE));
        $snippetData = array_map(fn($value) => is_string($value) ? trim($value) : $value, $snippetData);
        $snippetData['language_code'] = $languageCode;
        if (empty($snippetData['name'])) {
            return OperationResult::validation('Не указано имя сниппета', $snippetData);
        }
        if (isset($snippetData['snippet_id']) && $snippetData['snippet_id'] > 0) {
            $snippetId = $snippetData['snippet_id'];
            unset($snippetData['snippet_id']);
            $result = SafeMySQL::gi()->query("UPDATE ?n SET ?u WHERE snippet_id = ?i", Constants::EMAIL_SNIPPETS_TABLE, $snippetData, $snippetId);
            return $result
                ? OperationResult::success((int) $snippetId, '', 'updated')
                : OperationResult::failure('Ошибка обновления сниппета', 'email_snippet_update_error', ['snippet_data' => $snippetData]);
        }
        $result = SafeMySQL::gi()->query("INSERT INTO ?n SET ?u", Constants::EMAIL_SNIPPETS_TABLE, $snippetData);
        return $result
            ? OperationResult::success((int) SafeMySQL::gi()->insertId(), '', 'created')
            : OperationResult::failure('Ошибка создания сниппета', 'email_snippet_insert_error', ['snippet_data' => $snippetData]);
    }

    /**
     * Удаляет почтовый шаблон
     * @param int $templateId ID шаблона
     * @return OperationResult
     */
    public function deleteEmailTemplate(int $templateId): OperationResult {
        $result = SafeMySQL::gi()->query("DELETE FROM ?n WHERE template_id = ?i", Constants::EMAIL_TEMPLATES_TABLE, $templateId);
        return $result
            ? OperationResult::success(['template_id' => $templateId], '', 'deleted')
            : OperationResult::failure('Ошибка удаления почтового шаблона', 'email_template_delete_error', ['template_id' => $templateId]);
    }

    /**
     * Удаляет сниппет
     * Перед удалением проверяет, используется ли сниппет в каких-либо шаблонах.
     * Если сниппет используется, записывает информацию в лог и возвращает false
     * @param int $snippetId ID сниппета
     * @return OperationResult
     */
    public function deleteEmailSnippet(int $snippetId): OperationResult {
        $sql = "SELECT template_id FROM ?n WHERE body LIKE ?s";
        $templatesUsingSnippet = SafeMySQL::gi()->getAll($sql, Constants::EMAIL_TEMPLATES_TABLE, '%{{' . $this->getSnippetNameById($snippetId) . '}}%');
        if (!empty($templatesUsingSnippet)) {
            $templateIds = array_column($templatesUsingSnippet, 'template_id');
            $message = "Невозможно удалить сниппет ID $snippetId, так как он используется в шаблонах: " . implode(', ', $templateIds);
            Logger::warning('email_snippet', $message, [
                'snippet_id' => $snippetId,
                'template_ids' => $templateIds,
            ], [
                'initiator' => __FUNCTION__,
                'details' => $message,
            ]);
            return OperationResult::failure($message, 'email_snippet_delete_blocked', ['snippet_id' => $snippetId, 'template_ids' => $templateIds]);
        }
        $result = SafeMySQL::gi()->query("DELETE FROM ?n WHERE snippet_id = ?i", Constants::EMAIL_SNIPPETS_TABLE, $snippetId);
        return $result
            ? OperationResult::success(['snippet_id' => $snippetId], '', 'deleted')
            : OperationResult::failure('Ошибка удаления сниппета', 'email_snippet_delete_error', ['snippet_id' => $snippetId]);
    }

    /**
     * Получает имя сниппета по его ID
     * @param int $snippetId ID сниппета
     * @param string $languageCode Код языка по стандарту ISO 3166-2
     * @return string|null Имя сниппета или NULL, если сниппет не найден
     */
    private function getSnippetNameById(int $snippetId, string $languageCode = ENV_DEF_LANG): ?string {
        $sql = "SELECT name FROM ?n WHERE snippet_id = ?i AND language_code = ?s";
        foreach ($this->getLanguageFallbacks($languageCode) as $fallbackLanguage) {
            $name = SafeMySQL::gi()->getOne($sql, Constants::EMAIL_SNIPPETS_TABLE, $snippetId, $fallbackLanguage);
            if (!empty($name)) {
                return $name;
            }
        }

        return SafeMySQL::gi()->getOne(
            "SELECT name FROM ?n WHERE snippet_id = ?i LIMIT 1",
            Constants::EMAIL_SNIPPETS_TABLE,
            $snippetId
        ) ?: null;
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
                $snippetContent = null;
                foreach ($this->getLanguageFallbacks($languageCode) as $fallbackLanguage) {
                    $sql = "SELECT content FROM ?n WHERE name = ?s AND language_code = ?s";
                    $snippetContent = SafeMySQL::gi()->getOne($sql, Constants::EMAIL_SNIPPETS_TABLE, $snippetName, $fallbackLanguage);
                    if ($snippetContent) {
                        break;
                    }
                }
                if (!$snippetContent) {
                    $snippetContent = SafeMySQL::gi()->getOne(
                        "SELECT content FROM ?n WHERE name = ?s LIMIT 1",
                        Constants::EMAIL_SNIPPETS_TABLE,
                        $snippetName
                    );
                }
                if ($snippetContent) {
                    $templateBody = str_replace('{{' . $snippetName . '}}', $snippetContent, $templateBody);
                }
            }
        }
        return $templateBody;
    }
}
