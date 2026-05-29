<?php

require_once PROJECT_ROOT_DIR . '/inc/installer/InstallerService.php';

$installer = new EEInstallerService(PROJECT_ROOT_DIR);
echo json_encode($installer->getStatus(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
return 0;
