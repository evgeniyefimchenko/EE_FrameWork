<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301));
$uri = parse_url(__REQUEST['_SERVER']['REQUEST_URI'])['path'];
$interfaceLanguageCodes = ee_get_interface_lang_codes();
$currentInterfaceLanguageCode = ee_get_current_lang_code();
$impersonationState = classes\system\AuthSessionService::getImpersonationState();
$currentUserName = trim((string) (($userData['name'] ?? '') ?: ($userData['email'] ?? '')));
$currentUserEmail = trim((string) ($userData['email'] ?? ''));
$platformVersion = trim((string) ENV_VERSION_CORE);
$currentQuery = $_GET;
unset($currentQuery['ui_lang']);
$languageButtons = [];
foreach ($interfaceLanguageCodes as $languageCode) {
    $buttonClass = $languageCode === $currentInterfaceLanguageCode ? 'btn-primary active' : 'btn-outline-light';
    $langQuery = array_merge($currentQuery, ['ui_lang' => $languageCode]);
    $langUrl = $uri . (!empty($langQuery) ? '?' . http_build_query($langQuery) : '');
    $languageButtons[] = '<a href="' . htmlspecialchars($langUrl, ENT_QUOTES) . '" class="btn btn-sm ' . $buttonClass . '" data-lang-switch data-langcode="'
        . htmlspecialchars((string) $languageCode, ENT_QUOTES) . '">' . htmlspecialchars((string) $languageCode, ENT_QUOTES) . '</a>';
}
$toolbarHtml = '';
if (!empty($impersonationState['active'])) {
    $originUserName = trim((string) ($impersonationState['origin_user_name'] ?? ''));
    $targetUserName = trim((string) ($impersonationState['target_user_name'] ?? ''));
    $returnLabel = (string) ($lang['sys.stop_impersonation'] ?? 'Вернуться в аккаунт администратора');
    if ($originUserName !== '') {
        $returnLabel = sprintf(
            (string) ($lang['sys.return_to_admin'] ?? 'Вернуться к: %s'),
            $originUserName
        );
    }
    $impersonationText = (string) ($lang['sys.impersonation_active'] ?? 'Вы работаете от имени другого пользователя');
    if ($targetUserName !== '') {
        $impersonationText .= ': ' . $targetUserName;
    }
    $toolbarHtml .= '<div class="d-flex flex-wrap align-items-center gap-2 ms-2 ms-lg-3">'
        . '<span class="badge rounded-pill text-bg-warning text-dark">' . htmlspecialchars($impersonationText, ENT_QUOTES) . '</span>'
        . '<a href="' . htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/stop_impersonation'), ENT_QUOTES, 'UTF-8') . '" class="btn btn-sm btn-warning">' . htmlspecialchars($returnLabel, ENT_QUOTES) . '</a>'
        . '</div>';
}
if ($languageButtons !== []) {
    $toolbarHtml .= '<div class="d-flex flex-wrap align-items-center gap-2 ms-2 ms-lg-3">'
        . '<span class="small text-light opacity-75">' . htmlspecialchars((string) ($lang['sys.interface_language'] ?? 'Interface language'), ENT_QUOTES) . ':</span>'
        . '<div class="btn-group btn-group-sm" role="group" aria-label="' . htmlspecialchars((string) ($lang['sys.interface_language'] ?? 'Interface language'), ENT_QUOTES) . '">'
        . implode('', $languageButtons)
        . '</div></div>';
}
$userMenuHeaderParts = [];
if ($currentUserName !== '') {
    $userMenuHeaderParts[] = '<div class="small text-muted">' . htmlspecialchars((string) ($lang['sys.current_user'] ?? 'Current user'), ENT_QUOTES) . '</div>'
        . '<div class="fw-semibold">' . htmlspecialchars($currentUserName, ENT_QUOTES) . '</div>';
}
if ($currentUserEmail !== '' && $currentUserEmail !== $currentUserName) {
    $userMenuHeaderParts[] = '<div class="small text-muted">' . htmlspecialchars($currentUserEmail, ENT_QUOTES) . '</div>';
}
if ($platformVersion !== '') {
    $userMenuHeaderParts[] = '<div class="small text-muted mt-1">' .
        htmlspecialchars((string) ($lang['sys.platform_version'] ?? 'Platform version'), ENT_QUOTES) . ': ' .
        htmlspecialchars($platformVersion, ENT_QUOTES) . '</div>';
}
// Подготовка данных для верхней панели. Включает в себя бренд и меню пользователя.
$topbarData = [
    'brand' => [
        'url' => ENV_URL_SITE,
        'name' => ENV_SITE_NAME
    ],
    'searchPlaceholder' => (string) ($lang['sys.search_placeholder'] ?? 'Search...'),
    'searchAriaLabel' => (string) ($lang['sys.search_placeholder'] ?? 'Search...'),
    'toolbarHtml' => $toolbarHtml,
    'userMenuHeaderHtml' => implode('', $userMenuHeaderParts),
    'userMenu' => [// Меню пользователя с опциями
        [
            'title' => (string) ($lang['sys.settings'] ?? 'Settings'),
            'link' => '/admin/user_edit'
        ],
        [
            'title' => (string) ($lang['sys.messages'] ?? 'Messages'),
            'link' => '/admin/messages'
        ],
        'divider',
        [
            'title' => (string) ($lang['sys.logout'] ?? 'Logout'),
            'link' => '/exit_login' 
        ]
    ]
];

// Обработка уведомлений для отображения в верхней панели
if (isset($notifications) && is_array($notifications)) {
    $nowMs = (int) round(microtime(true) * 1000);
    foreach ($notifications as $notification) {
        $status = (string) ($notification['status'] ?? 'info');
        $showtime = max(0, (int) ($notification['showtime'] ?? 0));
        if ($status === 'primary' && $showtime > $nowMs) {
            continue;
        }
        $topbarData['notifications'][] = [
            'text' => classes\system\SysClass::truncateString(strip_tags((string) ($notification['text'] ?? '')), 33),
            'url' => (string) ($notification['url'] ?? '/admin/messages'),
            'icon' => (string) ($notification['icon'] ?? 'fa-regular fa-circle-question'),
            'color' => (string) ($notification['color'] ?? '#bcbebf')
        ];
    }
}

echo classes\system\Plugins::generate_topbar($topbarData);
