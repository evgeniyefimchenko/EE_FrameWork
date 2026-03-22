<?php

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

$args = $_SERVER['argv'] ?? $argv ?? [];
$GLOBALS['argv'] = array_merge(
    [dirname(__DIR__, 2) . '/inc/cli.php', 'cron:lifecycle'],
    array_slice($args, 1)
);
$_SERVER['argv'] = $GLOBALS['argv'];

require dirname(__DIR__, 2) . '/inc/cli.php';
