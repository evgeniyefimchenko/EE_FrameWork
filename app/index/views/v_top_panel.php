<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$interfaceLanguageCodes = ee_get_interface_lang_codes();
$currentInterfaceLanguageCode = ee_get_current_lang_code();
$currentPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$currentQuery = $_GET;
unset($currentQuery['ui_lang']);
?>
<div class="ee-welcome-toolbar">
    <div class="ee-welcome-brand">
        <a class="ee-welcome-brand-mark" href="/" aria-label="EE_FrameWork">EE</a>
        <div class="ee-welcome-brand-copy">
            <strong>EE_FrameWork</strong>
            <span><?= htmlspecialchars((string) ($lang['sys.welcome_brand_caption'] ?? 'Open-source PHP framework + CMS'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <div class="ee-welcome-toolbar-actions">
        <?php
        if (isset($user_id)) {
            $dashboardLabel = (string) ($lang['sys.dashboard'] ?? 'Dashboard');
            echo '<a class="btn btn-primary ee-toolbar-btn" id="login_button" href="/admin" aria-label="' . htmlspecialchars($dashboardLabel, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($dashboardLabel, ENT_QUOTES, 'UTF-8') . '</a>';
        } else {
            $authorizationLabel = (string) ($lang['sys.authorization'] ?? 'Authorization');
            echo '<a class="btn btn-primary ee-toolbar-btn" id="login_button" href="/show_login_form" aria-label="' . htmlspecialchars($authorizationLabel, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($authorizationLabel, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        ?>
        <?php if (!empty($interfaceLanguageCodes)): ?>
            <div class="ee-welcome-language-switch">
                <span class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.interface_language'] ?? 'Interface language'), ENT_QUOTES, 'UTF-8') ?>:</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="<?= htmlspecialchars((string) ($lang['sys.interface_language'] ?? 'Interface language'), ENT_QUOTES, 'UTF-8') ?>">
                    <?php foreach ($interfaceLanguageCodes as $languageCode): ?>
                        <?php
                        $langQuery = array_merge($currentQuery, ['ui_lang' => $languageCode]);
                        $langUrl = $currentPath . (!empty($langQuery) ? '?' . http_build_query($langQuery) : '');
                        ?>
                        <a
                            href="<?= htmlspecialchars($langUrl, ENT_QUOTES, 'UTF-8') ?>"
                            class="btn <?= $languageCode === $currentInterfaceLanguageCode ? 'btn-primary active' : 'btn-outline-secondary' ?>"
                            data-lang-switch
                            data-langcode="<?= htmlspecialchars((string) $languageCode, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <?= htmlspecialchars((string) $languageCode, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
