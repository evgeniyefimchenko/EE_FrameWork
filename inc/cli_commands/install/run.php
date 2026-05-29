<?php

require_once PROJECT_ROOT_DIR . '/inc/installer/InstallerService.php';

$usage = static function (): void {
    echo "Usage:\n";
    echo "  php inc/cli.php install:run --site-host=example.com --site-author=\"Owner\" --site-email=mail@example.com --admin-email=mail@example.com --db-name=example --db-user=example --db-pass=secret [options]\n\n";
    echo "Options:\n";
    echo "  --canonical-scheme=https|http\n";
    echo "  --site-name=<name>\n";
    echo "  --site-description=<text>\n";
    echo "  --support-email=<email>\n";
    echo "  --proto-language=EN|RU\n";
    echo "  --content-langs=RU,EN\n";
    echo "  --bootstrap533-cdn=1|0 --font-awesome-cdn=1|0\n";
    echo "  --no-bootstrap533-cdn --no-font-awesome-cdn\n";
    echo "  --db-host=localhost\n";
    echo "  --db-prefix=ee_\n";
    echo "  --create-database --db-admin-user=root --db-admin-pass=<secret>\n";
    echo "  --create-user --db-user-host=localhost\n";
    echo "  --admin-password=<secret> --moderator-password=<secret>\n";
    echo "  --overwrite-config --force\n";
    echo "  --json\n";
};

if (!empty($eeCliOptions['help']) || !empty($eeCliOptions['h'])) {
    $usage();
    return 0;
}

$map = [
    'site-host' => 'site_host',
    'canonical-scheme' => 'canonical_scheme',
    'site-name' => 'site_name',
    'site-description' => 'site_description',
    'site-author' => 'site_author',
    'owner-name' => 'owner_name',
    'site-email' => 'site_email',
    'admin-email' => 'admin_email',
    'support-email' => 'support_email',
    'content-lang' => 'content_lang',
    'content-langs' => 'content_langs',
    'proto-language' => 'proto_language',
    'bootstrap533-cdn' => 'bootstrap533_cdn',
    'font-awesome-cdn' => 'font_awesome_cdn',
    'db-host' => 'db_host',
    'db-name' => 'db_name',
    'db-user' => 'db_user',
    'db-pass' => 'db_pass',
    'db-prefix' => 'db_prefix',
    'db-pref' => 'db_pref',
    'db-admin-user' => 'db_admin_user',
    'db-admin-pass' => 'db_admin_pass',
    'db-user-host' => 'db_user_host',
    'create-database' => 'create_database',
    'create-user' => 'create_user',
    'overwrite-config' => 'overwrite_config',
    'force' => 'force',
    'admin-password' => 'admin_password',
    'moderator-password' => 'moderator_password',
    'legal-operator-status' => 'legal_operator_status',
    'legal-operator-name' => 'legal_operator_name',
    'legal-operator-address' => 'legal_operator_address',
    'legal-operator-inn' => 'legal_operator_inn',
    'legal-operator-ogrn' => 'legal_operator_ogrn',
];

$input = [];
foreach ($eeCliOptions as $key => $value) {
    if (in_array($key, ['json', 'no-bootstrap533-cdn', 'no-font-awesome-cdn'], true)) {
        continue;
    }
    $target = $map[$key] ?? str_replace('-', '_', (string) $key);
    $input[$target] = $value;
}
if (!empty($eeCliOptions['no-bootstrap533-cdn'])) {
    $input['bootstrap533_cdn'] = false;
}
if (!empty($eeCliOptions['no-font-awesome-cdn'])) {
    $input['font_awesome_cdn'] = false;
}

$required = ['site_host', 'site_author', 'site_email', 'admin_email', 'db_name', 'db_user'];
$missing = [];
foreach ($required as $key) {
    if (!array_key_exists($key, $input) || trim((string) $input[$key]) === '') {
        $missing[] = '--' . str_replace('_', '-', $key);
    }
}

if ($missing !== []) {
    fwrite(STDERR, 'Missing required options: ' . implode(', ', $missing) . PHP_EOL . PHP_EOL);
    $usage();
    return 2;
}

$json = !empty($eeCliOptions['json']);
$installer = new EEInstallerService(PROJECT_ROOT_DIR);

try {
    $result = $installer->run($input, $json ? null : static function (array $state): void {
        printf("[%3d%%] %s\n", (int) ($state['percent'] ?? 0), (string) ($state['message'] ?? ''));
    });

    if ($json) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
        return 0;
    }

    echo PHP_EOL . $result['message'] . PHP_EOL;
    echo 'Site: ' . $result['site_url'] . PHP_EOL;
    echo 'Configuration: ' . $result['configuration'] . PHP_EOL;
    if (!empty($result['backup_path'])) {
        echo 'Config backup: ' . $result['backup_path'] . PHP_EOL;
    }
    if (!empty($result['generated_credentials']['admin_password_generated'])) {
        echo 'Generated admin password: ' . $result['generated_credentials']['admin_password'] . PHP_EOL;
    }
    if (!empty($result['generated_credentials']['moderator_password_generated'])) {
        echo 'Generated moderator password: ' . $result['generated_credentials']['moderator_password'] . PHP_EOL;
    }
    echo 'Cron: ' . $result['cron']['recommended_line'] . PHP_EOL;
    return 0;
} catch (Throwable $e) {
    $installer->writeFailureState($e);
    if ($json) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        fwrite(STDERR, 'Install failed: ' . $e->getMessage() . PHP_EOL);
    }
    return 1;
}
