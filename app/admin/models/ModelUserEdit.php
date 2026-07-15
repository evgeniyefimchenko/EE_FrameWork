<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;
use classes\system\AuthService;
use classes\system\Logger;
use classes\system\OperationResult;

/**
 * Модель карты пользователя
 */
class ModelUserEdit {

    private static ?bool $userPropertyInfrastructureReady = null;

    private const USER_ROLE_WRITE_FIELDS = [
        'role_id',
        'role_key',
        'name',
    ];

    /**
     * Возвращает все свободные роли пользователей
     * кроме переданной и роли система
     */
    public function get_free_roles($role_id = 1, int $user_id = 0, bool $enforceSingleAdmin = true) {
        $excludedRoles = [(int) $role_id, Constants::SYSTEM];
        $hasAnotherAdmin = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM ?n WHERE user_role = ?i AND deleted = 0 AND user_id != ?i',
            Constants::USERS_TABLE,
            Constants::ADMIN,
            $user_id
        ) > 0;
        if ($enforceSingleAdmin && (int) $role_id !== Constants::ADMIN && $hasAnotherAdmin) {
            $excludedRoles[] = Constants::ADMIN;
        }
        $excludedRoles = array_values(array_unique(array_map('intval', $excludedRoles)));
        $sql = 'SELECT role_id, name FROM ?n WHERE role_id NOT IN (?a) ORDER BY role_id ASC';
        return SafeMySQL::gi()->getAll($sql, Constants::USERS_ROLES_TABLE, $excludedRoles);
    }

    /**
     * Получает данные ролей пользователей из таблицы ролей
     * @param string $order Строка для указания порядка сортировки (например, 'role_id ASC'). По умолчанию 'role_id ASC'
     * @param string|null $where Опциональная строка для условия WHERE в SQL запросе
     * @param int $start Начальный индекс для LIMIT в SQL запросе. По умолчанию 0
     * @param int $limit Количество записей для получения. По умолчанию 100
     * @return array Возвращает массив с двумя ключами:
     *               - 'data': массив объектов данных ролей,
     *               - 'total_count': общее количество ролей в таблице
     */
    public function get_users_roles_data($order = 'role_id ASC', $where = null, $start = 0, $limit = 100) {
        $orderString = $order ? $order : 'role_id ASC';
        $whereString = $where ? $where : ''; // Убрано условие с language_code
        $start = $start ? $start : 0;

        // Формируем SQL-запрос для получения role_id
        if ($orderString) {
            $sql_roles = "SELECT role_id FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_roles = "SELECT role_id FROM ?n $whereString LIMIT ?i, ?i";
        }

        // Выполняем запрос и получаем данные
        $res_array = SafeMySQL::gi()->getAll($sql_roles, Constants::USERS_ROLES_TABLE, $start, $limit);
        $res = [];
        foreach ($res_array as $role) {
            $res[] = $this->get_users_role_data($role['role_id']);
        }

        // Получаем общее количество записей
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::USERS_ROLES_TABLE);

        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные роли по её идентификатору из таблицы ролей пользователей.
     * @param int $role_id Идентификатор роли, данные которой необходимо получить.
     * @return array|null Возвращает ассоциативный массив с данными роли, если роль найдена.
     *                    Возвращает null, если роль не найдена.
     */
    public function get_users_role_data($role_id) {
        // SQL запрос для получения данных роли по идентификатору
        $sql_role = "SELECT r.* FROM ?n AS r WHERE r.role_id = ?i";
        $role_data = SafeMySQL::gi()->getRow($sql_role, Constants::USERS_ROLES_TABLE, $role_id);
        if (!$role_data) {
            return null;
        }
        return $role_data;
    }

    public function ensureUserPropertyInfrastructure(): bool {
        if (self::$userPropertyInfrastructureReady !== null) {
            return self::$userPropertyInfrastructureReady;
        }

        try {
            $this->ensureEntityTypeEnumSupportsUser(
                Constants::PROPERTIES_TABLE,
                "ENUM('category', 'page', 'user', 'all') NOT NULL DEFAULT 'all'"
            );
            $this->ensureEntityTypeEnumSupportsUser(
                Constants::PROPERTY_VALUES_TABLE,
                "ENUM('category', 'page', 'user') NOT NULL"
            );

            $tableExists = SafeMySQL::gi()->getOne(
                'SHOW TABLES LIKE ?s',
                Constants::USER_ROLE_TO_PROPERTY_SET_TABLE
            );
            if (!$tableExists) {
                $sql = "CREATE TABLE IF NOT EXISTS ?n (
                    role_id TINYINT UNSIGNED NOT NULL,
                    set_id INT UNSIGNED NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (role_id, set_id),
                    KEY idx_user_role_property_set_set (set_id),
                    CONSTRAINT fk_user_role_property_set_role FOREIGN KEY (role_id) REFERENCES ?n(role_id) ON DELETE CASCADE ON UPDATE RESTRICT,
                    CONSTRAINT fk_user_role_property_set_set FOREIGN KEY (set_id) REFERENCES ?n(set_id) ON DELETE CASCADE ON UPDATE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Таблица для связи ролей пользователей и наборов свойств'";
                SafeMySQL::gi()->query(
                    $sql,
                    Constants::USER_ROLE_TO_PROPERTY_SET_TABLE,
                    Constants::USERS_ROLES_TABLE,
                    Constants::PROPERTY_SETS_TABLE
                );
            }

            self::$userPropertyInfrastructureReady = true;
        } catch (\Throwable $e) {
            Logger::error('user_properties', 'Не удалось подготовить инфраструктуру свойств пользователей', [
                'exception' => $e,
            ], [
                'initiator' => __FUNCTION__,
                'include_trace' => true,
            ]);
            self::$userPropertyInfrastructureReady = false;
        }

        return self::$userPropertyInfrastructureReady;
    }

    private function ensureEntityTypeEnumSupportsUser(string $tableName, string $definition): void {
        $column = SafeMySQL::gi()->getRow(
            'SHOW COLUMNS FROM ?n LIKE ?s',
            $tableName,
            'entity_type'
        );
        $type = strtolower((string) ($column['Type'] ?? ''));
        if ($type !== '' && str_contains($type, "'user'")) {
            return;
        }
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n MODIFY entity_type ' . $definition,
            $tableName
        );
    }

    private function normalizeIntegerIds(mixed $ids): array {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $result = [];
        foreach ($ids as $id) {
            if (is_array($id)) {
                continue;
            }
            $id = (int) $id;
            if ($id > 0) {
                $result[$id] = $id;
            }
        }

        return array_values($result);
    }

    public function getUserRolePropertySetIds(int $roleId): array {
        if ($roleId <= 0 || !$this->ensureUserPropertyInfrastructure()) {
            return [];
        }

        return array_values(array_unique(array_map(
            'intval',
            SafeMySQL::gi()->getCol(
                'SELECT set_id FROM ?n WHERE role_id = ?i ORDER BY set_id ASC',
                Constants::USER_ROLE_TO_PROPERTY_SET_TABLE,
                $roleId
            )
        )));
    }

    public function getUserPropertySetIds(int $userId): array {
        if ($userId <= 0) {
            return [];
        }

        $roleId = (int) SafeMySQL::gi()->getOne(
            'SELECT user_role FROM ?n WHERE user_id = ?i LIMIT 1',
            Constants::USERS_TABLE,
            $userId
        );

        return $this->getUserRolePropertySetIds($roleId);
    }

    public function updateUserRolePropertySets(int $roleId, mixed $setIds): OperationResult {
        if ($roleId <= 0) {
            return OperationResult::validation('Не указана роль пользователя', ['role_id' => $roleId]);
        }
        if (!$this->ensureUserPropertyInfrastructure()) {
            return OperationResult::failure('Инфраструктура свойств пользователей не готова', 'user_property_infrastructure_unavailable', ['role_id' => $roleId]);
        }

        $setIds = $this->normalizeIntegerIds($setIds);
        SafeMySQL::gi()->query('START TRANSACTION');
        try {
            SafeMySQL::gi()->query(
                'DELETE FROM ?n WHERE role_id = ?i',
                Constants::USER_ROLE_TO_PROPERTY_SET_TABLE,
                $roleId
            );
            foreach ($setIds as $setId) {
                $result = SafeMySQL::gi()->query(
                    'INSERT INTO ?n SET ?u',
                    Constants::USER_ROLE_TO_PROPERTY_SET_TABLE,
                    [
                        'role_id' => $roleId,
                        'set_id' => $setId,
                    ]
                );
                if (!$result) {
                    SafeMySQL::gi()->query('ROLLBACK');
                    return OperationResult::failure('Не удалось связать роль пользователя с набором свойств', 'user_role_property_set_insert_failed', [
                        'role_id' => $roleId,
                        'set_id' => $setId,
                    ]);
                }
            }
            SafeMySQL::gi()->query('COMMIT');
        } catch (\Throwable $e) {
            SafeMySQL::gi()->query('ROLLBACK');
            Logger::error('user_properties', 'Ошибка сохранения наборов свойств роли пользователя', [
                'role_id' => $roleId,
                'set_ids' => $setIds,
                'exception' => $e,
            ], [
                'initiator' => __FUNCTION__,
                'include_trace' => true,
            ]);
            return OperationResult::failure('Ошибка сохранения наборов свойств роли пользователя', 'user_role_property_set_exception', [
                'role_id' => $roleId,
            ]);
        }

        return OperationResult::success([
            'role_id' => $roleId,
            'set_ids' => $setIds,
        ], '', 'updated');
    }

    /**
     * Обновляет или создает запись о роли пользователя в базе данных
     * @param array $usersRoleData Ассоциативный массив данных роли пользователя
     *     - role_id (int, необязательно): Идентификатор роли, если требуется обновление
     *     - name (string): Название роли пользователя (обязательно для создания или обновления)
     *     - другие поля соответствуют структуре таблицы Constants::USERS_ROLES_TABLE
     * @param string $language_code Код языка, используемый при сохранении роли (по умолчанию — ENV_DEF_LANG)
     * @return OperationResult Идентификатор роли и статус операции
     * Процесс работы:
     * - Фильтрует входные данные через SafeMySQL::filterArray().
     * - Обрезает лишние пробелы в строковых значениях.
     * - Конвертирует числовые значения в нужные типы.
     * - Если передан 'role_id', пытается обновить существующую запись.
     * - Если 'role_id' не передан или равен 0, создает новую запись.
     * - При ошибке SQL логирует её через Logger.
     */
    public function update_users_role_data(array $usersRoleData = []): OperationResult {
        $usersRoleData = SafeMySQL::gi()->filterArray($usersRoleData, self::USER_ROLE_WRITE_FIELDS);
        $usersRoleData = array_map(static fn($value) => is_string($value) ? trim($value) : $value, $usersRoleData);
        $usersRoleData = SysClass::ee_convertArrayValuesToNumbers($usersRoleData);
        if (empty($usersRoleData['name'])) {
            return OperationResult::validation('Не указано имя роли', $usersRoleData);
        }
        $roleId = (int) ($usersRoleData['role_id'] ?? 0);
        $existingRole = $roleId > 0 ? $this->get_users_role_data($roleId) : null;
        $roleKey = trim((string) ($existingRole['role_key'] ?? $usersRoleData['role_key'] ?? ''));
        if ($roleKey === '') {
            $roleKey = $this->generateRoleKey((string) $usersRoleData['name']);
        }
        $usersRoleData['role_key'] = $this->ensureUniqueRoleKey($roleKey, $roleId);

        if ($roleId > 0) {
            $role_id = $roleId;
            unset($usersRoleData['role_id']);
            $sql = "UPDATE ?n SET ?u WHERE role_id = ?i";
            $result = SafeMySQL::gi()->query($sql, Constants::USERS_ROLES_TABLE, $usersRoleData, $role_id);
            if (!$result) {
                $message = 'error SQL';
                Logger::error('user_role_edit', $message, [
                    'sql' => SafeMySQL::gi()->parse($sql, Constants::USERS_ROLES_TABLE, $usersRoleData, $role_id),
                ], [
                    'initiator' => __FUNCTION__,
                    'details' => $message,
                ]);
            }
            return $result
                ? OperationResult::success((int) $role_id, '', 'updated')
                : OperationResult::failure('Ошибка обновления роли пользователя', 'user_role_update_error', ['role_data' => $usersRoleData]);
        } else {
            unset($usersRoleData['role_id']);
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::USERS_ROLES_TABLE, $usersRoleData);
        if (!$result) {
            $message = 'error SQL';
            Logger::error('user_role_edit', $message, [
                'sql' => SafeMySQL::gi()->parse($sql, Constants::USERS_ROLES_TABLE, $usersRoleData),
            ], [
                'initiator' => __FUNCTION__,
                'details' => $message,
            ]);
        }
        return $result
            ? OperationResult::success((int) SafeMySQL::gi()->insertId(), '', 'created')
            : OperationResult::failure('Ошибка создания роли пользователя', 'user_role_insert_error', ['role_data' => $usersRoleData]);
    }

    private function generateRoleKey(string $name): string {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $normalized = strtolower((string) ($transliterated !== false ? $transliterated : $name));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');
        return $normalized !== '' ? $normalized : 'role';
    }

    private function ensureUniqueRoleKey(string $roleKey, int $excludeRoleId = 0): string {
        $baseKey = $roleKey !== '' ? $roleKey : 'role';
        $candidate = $baseKey;
        $suffix = 1;
        while (true) {
            $existingRoleId = (int) SafeMySQL::gi()->getOne(
                'SELECT role_id FROM ?n WHERE role_key = ?s LIMIT 1',
                Constants::USERS_ROLES_TABLE,
                $candidate
            );
            if ($existingRoleId === 0 || $existingRoleId === $excludeRoleId) {
                return $candidate;
            }
            $candidate = $baseKey . '_' . $suffix;
            $suffix++;
        }
    }

    /**
     * Удаляет роль пользователя из базы данных
     * @param int $role_id Идентификатор роли пользователя, которую необходимо удалить
     * @return void
     */
    public function users_role_dell(int $role_id): OperationResult {
        if ($this->ensureUserPropertyInfrastructure()) {
            SafeMySQL::gi()->query(
                'DELETE FROM ?n WHERE role_id = ?i',
                Constants::USER_ROLE_TO_PROPERTY_SET_TABLE,
                $role_id
            );
        }
        $sql_role = 'DELETE FROM ?n WHERE role_id = ?i';
        $result = SafeMySQL::gi()->query($sql_role, Constants::USERS_ROLES_TABLE, $role_id);
        return $result
            ? OperationResult::success(['role_id' => $role_id], '', 'deleted')
            : OperationResult::failure('Ошибка удаления роли пользователя', 'user_role_delete_error', ['role_id' => $role_id]);
    }

    /**
     * 	Удаление пользователя, присвоит флаг удалённый 
     * 	@param user_id - user_id пользователя
     */
    public function delete_user(int $user_id): OperationResult {
        return (new AuthService())->handleSoftDelete($user_id)
            ? OperationResult::success(['user_id' => $user_id], '', 'soft_deleted')
            : OperationResult::failure('Ошибка удаления пользователя', 'user_soft_delete_error', ['user_id' => $user_id]);
    }

    public function restore_user(int $user_id, bool $forcePasswordSetup = true): OperationResult {
        $restoredRole = (int) SafeMySQL::gi()->getOne(
            'SELECT user_role FROM ?n WHERE user_id = ?i LIMIT 1',
            Constants::USERS_TABLE,
            $user_id
        );
        if ($restoredRole === Constants::ADMIN) {
            $hasAnotherAdmin = (int) SafeMySQL::gi()->getOne(
                'SELECT COUNT(*) FROM ?n WHERE user_role = ?i AND deleted = 0 AND user_id != ?i',
                Constants::USERS_TABLE,
                Constants::ADMIN,
                $user_id
            ) > 0;
            if ($hasAnotherAdmin) {
                return OperationResult::failure('В системе уже есть активный администратор', 'admin_restore_conflict', ['user_id' => $user_id]);
            }
        }
        return (new AuthService())->restoreUser($user_id, $forcePasswordSetup, 'admin_restore')
            ? OperationResult::success(['user_id' => $user_id], '', 'restored')
            : OperationResult::failure('Ошибка восстановления пользователя', 'user_restore_error', ['user_id' => $user_id]);
    }

    public function purge_deleted_user(int $user_id): OperationResult {
        if ($user_id <= 0) {
            return OperationResult::validation('Не указан пользователь для удаления', ['user_id' => $user_id]);
        }

        $userRow = SafeMySQL::gi()->getRow(
            'SELECT user_id, email, user_role, deleted FROM ?n WHERE user_id = ?i LIMIT 1',
            Constants::USERS_TABLE,
            $user_id
        );
        if (!$userRow) {
            return OperationResult::failure('Пользователь не найден', 'user_not_found', ['user_id' => $user_id]);
        }

        $userRole = (int) ($userRow['user_role'] ?? 0);
        if (in_array($userRole, [Constants::ADMIN, Constants::SYSTEM], true)) {
            return OperationResult::failure('Системных пользователей нельзя удалять полностью', 'system_user_purge_blocked', ['user_id' => $user_id]);
        }

        if ((int) ($userRow['deleted'] ?? 0) !== 1) {
            return OperationResult::failure('Полное удаление доступно только для пользователей из архива', 'user_not_archived', ['user_id' => $user_id]);
        }

        $userEmail = trim((string) ($userRow['email'] ?? ''));
        $this->ensureUserPropertyInfrastructure();

        SafeMySQL::gi()->query('START TRANSACTION');
        try {
            SafeMySQL::gi()->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_AUTH_SESSIONS_TABLE, $user_id);
            SafeMySQL::gi()->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_AUTH_CREDENTIALS_TABLE, $user_id);
            SafeMySQL::gi()->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_AUTH_IDENTITIES_TABLE, $user_id);
            SafeMySQL::gi()->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_AUTH_CHALLENGES_TABLE, $user_id);
            SafeMySQL::gi()->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_API_KEYS_TABLE, $user_id);
            SafeMySQL::gi()->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_ACTIVATION_TABLE, $user_id);
            if ($userEmail !== '') {
                SafeMySQL::gi()->query('DELETE FROM ?n WHERE email = ?s', Constants::USERS_ACTIVATION_TABLE, $userEmail);
            }
            if (self::$userPropertyInfrastructureReady) {
                SafeMySQL::gi()->query(
                    'DELETE FROM ?n WHERE entity_type = ?s AND entity_id = ?i',
                    Constants::PROPERTY_VALUES_TABLE,
                    'user',
                    $user_id
                );
            }
            SafeMySQL::gi()->query('UPDATE ?n SET user_id = 0 WHERE user_id = ?i', Constants::FILES_TABLE, $user_id);
            SafeMySQL::gi()->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_TABLE, $user_id);

            if ((int) SafeMySQL::gi()->affectedRows() <= 0) {
                SafeMySQL::gi()->query('ROLLBACK');
                return OperationResult::failure('Не удалось удалить пользователя из архива', 'user_purge_delete_failed', ['user_id' => $user_id]);
            }

            SafeMySQL::gi()->query('COMMIT');
        } catch (\Throwable $e) {
            SafeMySQL::gi()->query('ROLLBACK');
            Logger::error('users_info', 'Ошибка полного удаления пользователя', [
                'user_id' => $user_id,
                'exception' => $e,
            ], [
                'initiator' => __FUNCTION__,
                'include_trace' => true,
            ]);
            return OperationResult::failure('Ошибка полного удаления пользователя', 'user_purge_exception', ['user_id' => $user_id]);
        }

        Logger::audit('users_info', 'Пользователь полностью удалён из архива', [
            'user_id' => $user_id,
            'email' => $userEmail,
        ], [
            'initiator' => __FUNCTION__,
            'details' => 'Пользователь полностью удалён из архива',
            'include_trace' => false,
        ]);

        return OperationResult::success(['user_id' => $user_id], '', 'purged');
    }
}
