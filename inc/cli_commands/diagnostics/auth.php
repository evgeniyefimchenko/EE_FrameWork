<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\Cookies;
use classes\system\Session;
use classes\system\Users;

$bootstrapOutput = $eeCliBootstrapOutput ?? '';

require_once PROJECT_ROOT_DIR . '/app/admin/models/ModelUserEdit.php';
require_once PROJECT_ROOT_DIR . '/app/index/index.php';

$options = is_array($eeCliOptions ?? null) ? $eeCliOptions : [];
$jsonOutput = array_key_exists('json', $options);
$db = SafeMySQL::gi();
$adminUsers = new Users(1);

$cleanupUser = static function (int $userId) use ($db): void {
    if ($userId <= 0) {
        return;
    }

    $db->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_ACTIVATION_TABLE, $userId);
    if (defined('classes\system\Constants::USERS_NOTIFICATIONS_TABLE')) {
        $db->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_NOTIFICATIONS_TABLE, $userId);
    }
    $db->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_DATA_TABLE, $userId);
    $db->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_MESSAGE_TABLE, $userId);
    $db->query('DELETE FROM ?n WHERE user_id = ?i', Constants::USERS_TABLE, $userId);
};

$captureWarnings = static function (callable $callback): array {
    $warnings = [];
    set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$warnings): bool {
        $warnings[] = [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ];
        return true;
    });

    try {
        $result = $callback();
    } finally {
        restore_error_handler();
    }

    return [$result, $warnings];
};

$simulateRecoveryEndpoint = static function (string $email): array {
    $probeFile = tempnam(sys_get_temp_dir(), 'recovery_probe_');
    $probeCode = str_replace(
        ['__CONFIG__', '__STARTUP__', '__CONTROLLER__', '__EMAIL__'],
        [
            var_export(PROJECT_ROOT_DIR . '/inc/configuration.php', true),
            var_export(PROJECT_ROOT_DIR . '/inc/startup.php', true),
            var_export(PROJECT_ROOT_DIR . '/app/index/index.php', true),
            var_export($email, true),
        ],
        <<<'PHP'
<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_REFERER'] = 'http://localhost/show_login_form';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';

require __CONFIG__;
require __STARTUP__;
\AutoloadManager::init();
require_once __CONTROLLER__;
$_POST = ['email' => __EMAIL__];
$controller = new \ControllerIndex(new \classes\system\View());
$controller->recovery_password();
PHP
    );
    file_put_contents($probeFile, $probeCode);
    $output = shell_exec('php ' . escapeshellarg($probeFile) . ' 2>&1');
    @unlink($probeFile);

    return [
        'output' => trim((string) $output),
        'warnings' => [],
    ];
};

$runModeProbe = static function (int $authMode, int $oneIp) use ($db): array {
    $probeEmail = 'mode_probe_' . $authMode . '_' . $oneIp . '_' . str_replace('.', '', uniqid('', true)) . '@example.test';
    $probePass = 'ModePass!123';
    $probeFile = tempnam(sys_get_temp_dir(), 'auth_probe_');

    $probeCode = str_replace(
        ['__CONFIG__', '__STARTUP__', '__CONTROLLER__', '__SITE_PATH__', '__AUTH_MODE__', '__ONE_IP__', '__EMAIL__', '__PASS__'],
        [
            var_export(PROJECT_ROOT_DIR . '/inc/configuration.php', true),
            var_export(PROJECT_ROOT_DIR . '/inc/startup.php', true),
            var_export(PROJECT_ROOT_DIR . '/app/index/index.php', true),
            var_export(rtrim(PROJECT_ROOT_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, true),
            (string) $authMode,
            (string) $oneIp,
            var_export($probeEmail, true),
            var_export($probePass, true),
        ],
        <<<'PHP'
<?php
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '11.11.11.11';

$configSource = file_get_contents(__CONFIG__);
$configSource = str_replace(
    "'ENV_SITE_PATH' => realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,",
    "'ENV_SITE_PATH' => __SITE_PATH__,",
    $configSource
);
$configSource = str_replace("'ENV_ONE_IP_ONE_USER' => 0,", "'ENV_ONE_IP_ONE_USER' => __ONE_IP__,", $configSource);
$configSource = str_replace("'ENV_AUTH_USER' => 2,", "'ENV_AUTH_USER' => __AUTH_MODE__,", $configSource);
$tmpConfig = tempnam(sys_get_temp_dir(), 'cfg_override_');
file_put_contents($tmpConfig, $configSource);
require $tmpConfig;
@unlink($tmpConfig);
require __STARTUP__;
\AutoloadManager::init();
require_once __CONTROLLER__;

$db = \classes\plugins\SafeMySQL::gi();
$userId = 0;

try {
    $db->query(
        'INSERT INTO ?n SET ?u',
        \classes\system\Constants::USERS_TABLE,
        [
            'name' => 'Mode Probe',
            'email' => __EMAIL__,
            'pwd' => password_hash(__PASS__, PASSWORD_DEFAULT),
            'active' => 2,
            'user_role' => 4,
            'last_ip' => '11.11.11.11',
            'subscribed' => 1,
            'comment' => 'mode probe',
            'deleted' => 0,
        ]
    );
    $userId = (int) $db->insertId();
    (new \classes\system\Users(0))->setUserOptions($userId);

    $users = new \classes\system\Users(0);
    $loginResult = $users->confirmUser(__EMAIL__, __PASS__);
    $dbSession = (string) $db->getOne(
        'SELECT session FROM ?n WHERE user_id = ?i',
        \classes\system\Constants::USERS_TABLE,
        $userId
    );

    $sessionStore = \classes\system\Session::get('user_session');
    $cookieStore = \classes\system\Cookies::get('user_session');

    $loggedInProp = new \ReflectionProperty(\classes\system\ControllerBase::class, 'logged_in');
    $loggedInProp->setAccessible(true);

    $controllerSameIp = new \ControllerIndex(new \classes\system\View());
    $visibleSameIp = $loggedInProp->getValue($controllerSameIp);

    $_SERVER['REMOTE_ADDR'] = '12.12.12.12';
    $controllerOtherIp = new \ControllerIndex(new \classes\system\View());
    $visibleOtherIp = $loggedInProp->getValue($controllerOtherIp);

    echo json_encode([
        'auth_mode' => __AUTH_MODE__,
        'one_ip' => __ONE_IP__,
        'login_error' => $loginResult,
        'user_id' => $userId,
        'db_session' => $dbSession,
        'session_store' => $sessionStore,
        'cookie_store' => $cookieStore,
        'visible_same_ip' => $visibleSameIp,
        'visible_other_ip' => $visibleOtherIp,
        'storage_ok' => __AUTH_MODE__ === 0
            ? ($sessionStore === $dbSession && empty($cookieStore))
            : ($cookieStore === $dbSession),
        'cross_ip_allowed' => __ONE_IP__ === 1 ? ((int) $visibleOtherIp === $userId) : ((int) $visibleOtherIp === $userId),
        'one_ip_enforced' => __ONE_IP__ === 1 ? empty($visibleOtherIp) : false,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} finally {
    if ($userId > 0) {
        $db->query('DELETE FROM ?n WHERE user_id = ?i', \classes\system\Constants::USERS_ACTIVATION_TABLE, $userId);
        $db->query('DELETE FROM ?n WHERE user_id = ?i', \classes\system\Constants::USERS_NOTIFICATIONS_TABLE, $userId);
        $db->query('DELETE FROM ?n WHERE user_id = ?i', \classes\system\Constants::USERS_DATA_TABLE, $userId);
        $db->query('DELETE FROM ?n WHERE user_id = ?i', \classes\system\Constants::USERS_MESSAGE_TABLE, $userId);
        $db->query('DELETE FROM ?n WHERE user_id = ?i', \classes\system\Constants::USERS_TABLE, $userId);
    }
}
PHP
    );

    file_put_contents($probeFile, $probeCode);
    $command = 'php ' . escapeshellarg($probeFile) . ' 2>&1';
    $output = shell_exec($command);
    @unlink($probeFile);

    $decoded = json_decode((string) $output, true);
    return [
        'auth_mode' => $authMode,
        'one_ip' => $oneIp,
        'raw_output' => trim((string) $output),
        'parsed' => is_array($decoded) ? $decoded : null,
        'ok' => is_array($decoded),
    ];
};

$report = [
    'timestamp' => date('c'),
    'bootstrap_output' => $bootstrapOutput,
    'config' => [
        'ENV_AUTH_USER' => ENV_AUTH_USER,
        'ENV_ONE_IP_ONE_USER' => ENV_ONE_IP_ONE_USER,
        'ENV_TIME_AUTH_SESSION' => ENV_TIME_AUTH_SESSION,
        'ENV_CONFIRM_EMAIL' => ENV_CONFIRM_EMAIL,
        'ENV_SMTP' => ENV_SMTP,
    ],
    'functional' => [],
    'auth_mode_probes' => [],
    'static_analysis' => [],
];

Session::destroy();
Cookies::clear('user_session');

// 1. Public registration flow.
$publicEmail = 'public_reg_' . str_replace('.', '', uniqid('', true)) . '@example.test';
$publicRegistration = ['result' => null, 'error' => null];
try {
    $publicRegistration['result'] = $adminUsers->registrationUsers($publicEmail, 'PublicPass!123');
} catch (\Throwable $e) {
    $publicRegistration['error'] = $e->getMessage();
}
$createdPublicId = (int) $adminUsers->getUserIdByEmail($publicEmail);
$publicRegistration['created_user_id'] = $createdPublicId;
$report['functional']['public_registration'] = $publicRegistration;
$cleanupUser($createdPublicId);

// 2. Admin registration + update + duplicate email handling.
$updateEmail1 = 'update1_' . str_replace('.', '', uniqid('', true)) . '@example.test';
$updateEmail2 = 'update2_' . str_replace('.', '', uniqid('', true)) . '@example.test';
$adminUsers->registrationNewUser([
    'name' => 'Update One',
    'email' => $updateEmail1,
    'phone' => '+10000000001',
    'active' => '2',
    'user_role' => '4',
    'subscribed' => '1',
    'comment' => 'u1',
    'pwd' => 'Pass!12345',
], true);
$adminUsers->registrationNewUser([
    'name' => 'Update Two',
    'email' => $updateEmail2,
    'phone' => '+10000000002',
    'active' => '2',
    'user_role' => '4',
    'subscribed' => '1',
    'comment' => 'u2',
    'pwd' => 'Pass!12345',
], true);
$updateId1 = (int) $adminUsers->getUserIdByEmail($updateEmail1);
$updateId2 = (int) $adminUsers->getUserIdByEmail($updateEmail2);
$updateOk = $adminUsers->setUserData($updateId1, ['name' => 'Updated Name', 'phone' => '+19999999999', 'comment' => 'changed']);
$updateRow = $db->getRow(
    'SELECT name, phone, comment FROM ?n WHERE user_id = ?i',
    Constants::USERS_TABLE,
    $updateId1
);
$duplicateEmailError = null;
try {
    $adminUsers->setUserData($updateId2, ['email' => $updateEmail1]);
} catch (\Throwable $e) {
    $duplicateEmailError = $e->getMessage();
}
$report['functional']['admin_update'] = [
    'update_ok' => $updateOk,
    'updated_row' => $updateRow,
    'duplicate_email_error' => $duplicateEmailError,
];
$cleanupUser($updateId1);
$cleanupUser($updateId2);

// 3. Soft delete login bypass.
$deleteEmail = 'delete_login_' . str_replace('.', '', uniqid('', true)) . '@example.test';
$deletePass = 'AuditPass!123';
$adminUsers->registrationNewUser([
    'name' => 'Delete Login Audit',
    'email' => $deleteEmail,
    'phone' => '+10000000003',
    'active' => '2',
    'user_role' => '4',
    'subscribed' => '1',
    'comment' => 'delete login audit',
    'pwd' => $deletePass,
], true);
$deleteId = (int) $adminUsers->getUserIdByEmail($deleteEmail);
(new ModelUserEdit())->delete_user($deleteId);
Session::destroy();
Cookies::clear('user_session');
[$deleteLoginResult, $deleteLoginWarnings] = $captureWarnings(static fn() => $adminUsers->confirmUser($deleteEmail, $deletePass));
$deleteRow = $db->getRow(
    'SELECT user_id, email, active, deleted, session FROM ?n WHERE user_id = ?i',
    Constants::USERS_TABLE,
    $deleteId
);
$report['functional']['deleted_user_login'] = [
    'login_error' => $deleteLoginResult,
    'warnings' => $deleteLoginWarnings,
    'row_after_login' => $deleteRow,
    'cookie_session' => Cookies::get('user_session'),
    'session_session' => Session::get('user_session'),
];
$cleanupUser($deleteId);
Session::destroy();
Cookies::clear('user_session');

// 4. Password recovery endpoint.
$report['functional']['recovery_endpoint'] = $simulateRecoveryEndpoint('nobody@example.test');

// 5. Password recovery changes password before mail success.
$recoveryEmail = 'recovery_' . str_replace('.', '', uniqid('', true)) . '@example.test';
$adminUsers->registrationNewUser([
    'name' => 'Recovery Audit',
    'email' => $recoveryEmail,
    'phone' => '+10000000004',
    'active' => '2',
    'user_role' => '4',
    'subscribed' => '1',
    'comment' => 'recovery audit',
    'pwd' => 'StartPass!123',
], true);
$recoveryId = (int) $adminUsers->getUserIdByEmail($recoveryEmail);
$recoveryBeforeHash = (string) $db->getOne(
    'SELECT pwd FROM ?n WHERE user_id = ?i',
    Constants::USERS_TABLE,
    $recoveryId
);
[$recoverySendResult, $recoveryWarnings] = $captureWarnings(static fn() => $adminUsers->sendRecoveryPassword($recoveryEmail));
$recoveryAfterHash = (string) $db->getOne(
    'SELECT pwd FROM ?n WHERE user_id = ?i',
    Constants::USERS_TABLE,
    $recoveryId
);
$report['functional']['password_recovery_internal'] = [
    'send_result' => $recoverySendResult,
    'hash_changed' => $recoveryBeforeHash !== $recoveryAfterHash,
    'warnings' => $recoveryWarnings,
];
$cleanupUser($recoveryId);

// 6. Deleted user password recovery.
$deletedRecoveryEmail = 'deleted_recovery_' . str_replace('.', '', uniqid('', true)) . '@example.test';
$adminUsers->registrationNewUser([
    'name' => 'Deleted Recovery',
    'email' => $deletedRecoveryEmail,
    'phone' => '+10000000005',
    'active' => '2',
    'user_role' => '4',
    'subscribed' => '1',
    'comment' => 'deleted recovery',
    'pwd' => 'StartPass!123',
], true);
$deletedRecoveryId = (int) $adminUsers->getUserIdByEmail($deletedRecoveryEmail);
(new ModelUserEdit())->delete_user($deletedRecoveryId);
$deletedRecoveryBefore = (string) $db->getOne(
    'SELECT pwd FROM ?n WHERE user_id = ?i',
    Constants::USERS_TABLE,
    $deletedRecoveryId
);
[$deletedRecoveryResult, $deletedRecoveryWarnings] = $captureWarnings(static fn() => $adminUsers->sendRecoveryPassword($deletedRecoveryEmail));
$deletedRecoveryAfter = (string) $db->getOne(
    'SELECT pwd FROM ?n WHERE user_id = ?i',
    Constants::USERS_TABLE,
    $deletedRecoveryId
);
$report['functional']['deleted_user_password_recovery'] = [
    'deleted_flag' => (int) $db->getOne(
        'SELECT deleted FROM ?n WHERE user_id = ?i',
        Constants::USERS_TABLE,
        $deletedRecoveryId
    ),
    'send_result' => $deletedRecoveryResult,
    'hash_changed' => $deletedRecoveryBefore !== $deletedRecoveryAfter,
    'warnings' => $deletedRecoveryWarnings,
];
$cleanupUser($deletedRecoveryId);

// 7. Expired activation flow.
$activationEmail = 'activation_expired_' . str_replace('.', '', uniqid('', true)) . '@example.test';
$adminUsers->registrationNewUser([
    'name' => 'Activation Audit',
    'email' => $activationEmail,
    'phone' => '+10000000006',
    'active' => '1',
    'user_role' => '4',
    'subscribed' => '1',
    'comment' => 'activation audit',
    'pwd' => 'StartPass!123',
], true);
$activationId = (int) $adminUsers->getUserIdByEmail($activationEmail);
$db->query(
    'INSERT INTO ?n (user_id, email, code, stop_time) VALUES (?i, ?s, ?s, ?s)',
    Constants::USERS_ACTIVATION_TABLE,
    $activationId,
    $activationEmail,
    'expiredcode123',
    date('Y-m-d H:i:s', time() - 3600)
);
$activationResult = null;
$activationError = null;
try {
    $activationResult = $adminUsers->dellActivationCode($activationEmail, 'expiredcode123');
} catch (\Throwable $e) {
    $activationError = $e->getMessage();
}
$report['functional']['expired_activation'] = [
    'result' => $activationResult,
    'error' => $activationError,
];
$cleanupUser($activationId);

// Static capability inspection.
$adminIndexSource = file_get_contents(PROJECT_ROOT_DIR . '/app/admin/index.php');
$usersSource = file_get_contents(PROJECT_ROOT_DIR . '/classes/system/Users.php');
$report['static_analysis'] = [
    'restore_deleted_user_route_exists' => str_contains($adminIndexSource, 'function restore_user('),
    'restore_deleted_user_model_exists' => str_contains($adminIndexSource, 'restore_deleted'),
    'expired_activation_calls_missing_method' => str_contains($usersSource, 'dell_user_data('),
];

// Auth mode probes.
$report['auth_mode_probes'][] = $runModeProbe(0, 0);
$report['auth_mode_probes'][] = $runModeProbe(0, 1);
$report['auth_mode_probes'][] = $runModeProbe(2, 0);
$report['auth_mode_probes'][] = $runModeProbe(2, 1);

$findings = [];

if (
    ($report['functional']['deleted_user_login']['login_error'] ?? '') === ''
    || !empty($report['functional']['deleted_user_login']['cookie_session'])
    || !empty($report['functional']['deleted_user_login']['session_session'])
) {
    $findings[] = [
        'severity' => 'critical',
        'title' => 'Soft-deleted user can still authenticate',
        'evidence' => $report['functional']['deleted_user_login'],
    ];
}

if (!empty($report['functional']['password_recovery_internal']['hash_changed'])) {
    $findings[] = [
        'severity' => 'critical',
        'title' => 'Password recovery changes password before successful confirmation',
        'evidence' => $report['functional']['password_recovery_internal'],
    ];
} elseif (($report['functional']['password_recovery_internal']['send_result'] ?? true) === false) {
    $findings[] = [
        'severity' => 'medium',
        'title' => 'Password recovery mail delivery failed in diagnostics environment',
        'evidence' => $report['functional']['password_recovery_internal'],
    ];
}

if (($report['functional']['public_registration']['result'] ?? false) !== true) {
    $findings[] = [
        'severity' => 'high',
        'title' => 'Public registration is broken by schema drift',
        'evidence' => $report['functional']['public_registration'],
    ];
}

$recoveryEndpointOutput = (string) ($report['functional']['recovery_endpoint']['output'] ?? '');
if (
    str_contains($recoveryEndpointOutput, 'in development')
    || str_contains($recoveryEndpointOutput, 'it`s a lie')
    || str_contains($recoveryEndpointOutput, 'Fatal error')
) {
    $findings[] = [
        'severity' => 'high',
        'title' => 'Recovery endpoint is not implemented or crashes',
        'evidence' => $report['functional']['recovery_endpoint'],
    ];
}

if (($report['functional']['expired_activation']['error'] ?? null) !== null) {
    $findings[] = [
        'severity' => 'high',
        'title' => 'Expired activation flow crashes instead of failing safely',
        'evidence' => $report['functional']['expired_activation'],
    ];
}

if (!empty($report['functional']['deleted_user_password_recovery']['hash_changed'])) {
    $findings[] = [
        'severity' => 'high',
        'title' => 'Deleted user password recovery still mutates password data',
        'evidence' => $report['functional']['deleted_user_password_recovery'],
    ];
}

if (($report['static_analysis']['restore_deleted_user_route_exists'] ?? false) !== true) {
    $findings[] = [
        'severity' => 'medium',
        'title' => 'Deleted user restore flow is absent in admin',
        'evidence' => $report['static_analysis'],
    ];
}

if (!$findings) {
    $findings[] = [
        'severity' => 'low',
        'title' => 'No critical auth regressions detected by this diagnostics run',
        'evidence' => [
            'note' => 'Mail-related warnings may still appear if SMTP/templates are unavailable in the current environment.',
        ],
    ];
}

$report['findings'] = $findings;

if ($jsonOutput) {
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

echo "===== Auth Diagnostics =====" . PHP_EOL;
echo "Timestamp: {$report['timestamp']}" . PHP_EOL;
echo "Current mode: ENV_AUTH_USER=" . ENV_AUTH_USER . ', ENV_ONE_IP_ONE_USER=' . ENV_ONE_IP_ONE_USER . PHP_EOL;
echo PHP_EOL;

echo "[Functional]" . PHP_EOL;
foreach ($report['functional'] as $name => $data) {
    echo ' - ' . $name . ': ' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
echo PHP_EOL;

echo "[Auth Mode Probes]" . PHP_EOL;
foreach ($report['auth_mode_probes'] as $probe) {
    echo ' - mode=' . $probe['auth_mode'] . ' one_ip=' . $probe['one_ip'] . ' ok=' . ($probe['ok'] ? 'yes' : 'no') . PHP_EOL;
}
echo PHP_EOL;

echo "[Findings]" . PHP_EOL;
foreach ($report['findings'] as $finding) {
    echo ' - [' . strtoupper($finding['severity']) . '] ' . $finding['title'] . PHP_EOL;
}
