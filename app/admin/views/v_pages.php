<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<!-- Таблица сущностей -->
<main>
    <div class="container-fluid px-4">
        <?php
        $availableContentLanguageCodes = is_array($availableContentLanguageCodes ?? null) ? $availableContentLanguageCodes : [];
        $currentContentLanguageCode = strtoupper((string) ($currentContentLanguageCode ?? ee_get_default_content_lang_code()));
        $defaultContentLanguageCode = strtoupper((string) ($defaultContentLanguageCode ?? ee_get_default_content_lang_code()));
        $languageSwitchBaseUrl = (string) ($languageSwitchBaseUrl ?? '/admin/pages');
        $interfaceLanguageCodes = ee_get_interface_lang_codes();
        $currentInterfaceLanguageCode = ee_get_current_lang_code();
        $uiQuery = $_GET;
        unset($uiQuery['ui_lang']);
        ?>
        <a href="/admin/page_edit/id?language_code=<?= rawurlencode((string) ($currentContentLanguageCode ?: $defaultContentLanguageCode)) ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.add'] ?>" type="button"
           class="btn btn-info mx-1 float-end">
            <i class="fa fa-plus-circle"></i>&nbsp;<?= $lang['sys.add'] ?>
        </a>        
        <h1 class="mt-4"><?= $lang['sys.pages'] ?></h1>
        <?php if (!empty($interfaceLanguageCodes) || !empty($availableContentLanguageCodes)): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <?php if (!empty($interfaceLanguageCodes)): ?>
                    <span class="small text-muted"><?= htmlspecialchars((string)($lang['sys.interface_language'] ?? 'Interface language')) ?>:</span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="<?= htmlspecialchars((string)($lang['sys.interface_language'] ?? 'Interface language')) ?>">
                        <?php foreach ($interfaceLanguageCodes as $interfaceLanguageCode): ?>
                            <?php
                            $langQuery = array_merge($uiQuery, ['ui_lang' => $interfaceLanguageCode]);
                            $langUrl = $languageSwitchBaseUrl . '?' . http_build_query($langQuery);
                            ?>
                            <a
                                href="<?= htmlspecialchars($langUrl, ENT_QUOTES, 'UTF-8') ?>"
                                class="btn <?= $interfaceLanguageCode === $currentInterfaceLanguageCode ? 'btn-primary active' : 'btn-outline-secondary' ?>"
                                data-lang-switch
                                data-langcode="<?= htmlspecialchars((string)$interfaceLanguageCode, ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars((string)$interfaceLanguageCode) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($availableContentLanguageCodes)): ?>
                <span class="small text-muted"><?= htmlspecialchars((string)($lang['sys.content_language'] ?? 'Язык контента')) ?>:</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="<?= htmlspecialchars((string)($lang['sys.content_language'] ?? 'Язык контента')) ?>">
                    <?php foreach ($availableContentLanguageCodes as $availableLanguageCode): ?>
                        <a
                            href="<?= htmlspecialchars($languageSwitchBaseUrl . '?language_code=' . rawurlencode((string)$availableLanguageCode), ENT_QUOTES, 'UTF-8') ?>"
                            class="btn <?= $availableLanguageCode === $currentContentLanguageCode ? 'btn-primary active' : 'btn-outline-secondary' ?>"
                        >
                            <?= htmlspecialchars((string)$availableLanguageCode) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <span class="small text-muted"><?= htmlspecialchars((string)($lang['sys.translation_list_help'] ?? 'Список показывает записи выбранного языка контента.')) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col">
                <?= $pagesTable ?>
            </div>
        </div>
    </div>
</main>
