<?php

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Error: inc/cli.php can only be run from CLI.\n");
    exit(1);
}

if (!defined('PROJECT_ROOT_DIR')) {
    define('PROJECT_ROOT_DIR', dirname(__DIR__));
}

if (!defined('EE_CLI_RUN')) {
    define('EE_CLI_RUN', true);
}

if (!function_exists('ee_cli_registry')) {
    function ee_cli_registry(): array {
        $base = PROJECT_ROOT_DIR . '/inc/cli_commands/';

        return [
            'help' => [
                'file' => null,
                'description' => 'Show available CLI commands.',
            ],
            'list' => [
                'file' => null,
                'description' => 'Alias for help.',
            ],
            'cron:import' => [
                'file' => $base . 'cron/import.php',
                'description' => 'Run import job by id: cron:import <job_id>',
            ],
            'cron:run-agents' => [
                'file' => $base . 'cron/run_agents.php',
                'description' => 'Run due cron agents tick: cron:run-agents [--json]',
            ],
            'cron:run-agent' => [
                'file' => $base . 'cron/run_agent.php',
                'description' => 'Run one cron agent by id/code: cron:run-agent <id|code> [--json]',
            ],
            'cron:lifecycle' => [
                'file' => $base . 'cron/property_lifecycle.php',
                'description' => 'Run property lifecycle job: cron:lifecycle <job_id|next>',
            ],
            'cron:search-popularity' => [
                'file' => $base . 'cron/search_popularity.php',
                'description' => 'Recalculate search popularity scores.',
            ],
            'ops:health-check' => [
                'file' => $base . 'ops/health_check.php',
                'description' => 'Print system health report as JSON.',
            ],
            'diagnostics:auth' => [
                'file' => $base . 'diagnostics/auth.php',
                'description' => 'Run auth diagnostics.',
            ],
            'diagnostics:file-system' => [
                'file' => $base . 'diagnostics/file_system.php',
                'description' => 'Run file system diagnostics.',
            ],
            'diagnostics:filter-service' => [
                'file' => $base . 'diagnostics/filter_service.php',
                'description' => 'Run filter service diagnostics.',
            ],
            'diagnostics:notifications-messages' => [
                'file' => $base . 'diagnostics/notifications_messages.php',
                'description' => 'Run notifications/messages diagnostics.',
            ],
            'diagnostics:search-engine' => [
                'file' => $base . 'diagnostics/search_engine.php',
                'description' => 'Run search engine diagnostics.',
            ],
        ];
    }
}

if (!function_exists('ee_cli_print_help')) {
    function ee_cli_print_help(array $registry): int {
        echo "EE_FrameWork CLI\n";
        echo "Usage:\n";
        echo "  php inc/cli.php <command> [args] [--options]\n\n";
        echo "Commands:\n";
        foreach ($registry as $command => $meta) {
            echo '  ' . str_pad($command, 40) . ($meta['description'] ?? '') . "\n";
        }
        echo "\n";
        echo "Scheduler examples:\n";
        echo "  php app/cron/run.php\n";
        echo "  php inc/cli.php cron:run-agents --json\n";
        echo "  php inc/cli.php cron:run-agent property-lifecycle-next\n";
        echo "  php inc/cli.php cron:import 15\n";
        echo "  php inc/cli.php cron:lifecycle next\n";
        echo "  php inc/cli.php cron:search-popularity\n";
        echo "\n";
        echo "Diagnostics examples:\n";
        echo "  php inc/cli.php ops:health-check\n";
        echo "  php inc/cli.php diagnostics:auth --json\n";
        echo "  php inc/cli.php diagnostics:search-engine --query=hotel --json\n";
        return 0;
    }
}

if (!function_exists('ee_cli_bootstrap_runtime')) {
    function ee_cli_bootstrap_runtime(bool $verbose = false): string {
        static $bootstrapped = false;
        static $output = '';

        if ($bootstrapped) {
            return $output;
        }

        chdir(PROJECT_ROOT_DIR);

        ob_start();
        require_once PROJECT_ROOT_DIR . '/inc/bootstrap.php';
        ee_bootstrap_runtime();
        $output = trim((string) ob_get_clean());

        if ($verbose && $output !== '') {
            echo $output . PHP_EOL;
        }

        $bootstrapped = true;
        return $output;
    }
}

if (!function_exists('ee_cli_parse_tokens')) {
    function ee_cli_parse_tokens(array $tokens): array {
        $args = [];
        $options = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = (string) $tokens[$i];
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '--')) {
                $option = substr($token, 2);
                if ($option === '') {
                    continue;
                }

                if (str_contains($option, '=')) {
                    [$key, $value] = explode('=', $option, 2);
                    $options[$key] = $value;
                    continue;
                }

                $nextToken = $tokens[$i + 1] ?? null;
                if (is_string($nextToken) && $nextToken !== '' && !str_starts_with($nextToken, '-')) {
                    $options[$option] = $nextToken;
                    $i++;
                    continue;
                }

                $options[$option] = true;
                continue;
            }

            $args[] = $token;
        }

        return [$args, $options];
    }
}

$eeCliArgv = $GLOBALS['argv'] ?? $_SERVER['argv'] ?? [];
$eeCliRegistry = ee_cli_registry();
$eeCliCommand = isset($eeCliArgv[1]) ? trim((string) $eeCliArgv[1]) : 'help';

if ($eeCliCommand === '' || $eeCliCommand === '--help' || $eeCliCommand === '-h') {
    $eeCliCommand = 'help';
}

if (!isset($eeCliRegistry[$eeCliCommand])) {
    fwrite(STDERR, "Unknown CLI command: {$eeCliCommand}\n\n");
    exit(ee_cli_print_help($eeCliRegistry) ?: 1);
}

if ($eeCliCommand === 'help' || $eeCliCommand === 'list') {
    exit(ee_cli_print_help($eeCliRegistry));
}

$eeCliTokens = array_slice($eeCliArgv, 2);
[$eeCliArgs, $eeCliOptions] = ee_cli_parse_tokens($eeCliTokens);
$eeCliBootstrapOutput = ee_cli_bootstrap_runtime(false);
$eeCliCommandFile = $eeCliRegistry[$eeCliCommand]['file'] ?? null;

if (!is_string($eeCliCommandFile) || !is_file($eeCliCommandFile)) {
    fwrite(STDERR, "CLI command file not found for {$eeCliCommand}\n");
    exit(1);
}

$eeCliExitCode = require $eeCliCommandFile;
exit(is_int($eeCliExitCode) ? $eeCliExitCode : 0);
